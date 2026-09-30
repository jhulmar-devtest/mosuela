<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'secretary') {
  header("Location: ../login.php");
  exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  header("Location: appointments.php");
  exit();
}

// Optimized Query
$query = "
  SELECT 
    a.id,
    a.reference_no,
    a.appointment_date,
    a.appointment_time,
    a.status,
    a.notes,
    a.created_at,
    a.patient_id,
    a.dentist_id,
    COALESCE(p.name, SUBSTRING_INDEX(a.notes, ': ', -1)) as patient_name,
    p.email as patient_email,
    p.phone as patient_phone,
    d.name as dentist_name
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  INNER JOIN users d ON a.dentist_id = d.id
  WHERE a.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Appointment not found'); window.location.href='appointments.php';</script>";
  exit();
}

$appointment = $result->fetch_assoc();

// Get services
$appt_with_services = get_appointment_with_services($conn, $id);
if ($appt_with_services['success']) {
  $appointment['services'] = $appt_with_services['services'];
  $appointment['total_price'] = $appt_with_services['total_price'];
  $appointment['total_duration'] = $appt_with_services['total_duration'];
}

$is_walkin = strpos($appointment['notes'], 'Walk-in:') === 0;

include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Appointment #<?= $appointment['id'] ?> - Details</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <style>
    /* =========================================
       Page Specific Styles (Matches Main.css)
       ========================================= */

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--spacing-xl);
    }

    .topbar-title {
      font-size: 24px;
      font-weight: var(--font-weight-bold);
      color: var(--dark);
    }

    .topbar-actions {
      display: flex;
      gap: 12px;
    }

    /* Grid Layout */
    .page-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: var(--spacing-xl);
    }

    @media (max-width: 1024px) {
      .page-grid {
        grid-template-columns: 1fr;
      }
    }

    /* Cards */
    .card {
      background: var(--white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: var(--spacing-xl);
      margin-bottom: var(--spacing-xl);
      border: 1px solid var(--gray-200);
    }

    .card-title {
      font-size: 16px;
      font-weight: var(--font-weight-bold);
      color: var(--gray-700);
      margin-bottom: var(--spacing-lg);
      display: flex;
      align-items: center;
      gap: 10px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--gray-200);
    }

    .card-title i {
      color: var(--primary);
    }

    /* Key Value Grid */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }

    .info-item label {
      display: block;
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .info-item span {
      font-size: 15px;
      font-weight: var(--font-weight-medium);
      color: var(--dark);
      display: block;
    }

    /* Status Badge */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      text-transform: capitalize;
    }

    .status-pending {
      background: #fff7ed;
      color: #c2410c;
      border: 1px solid #ffedd5;
    }

    .status-approved {
      background: #f0fdf4;
      color: #15803d;
      border: 1px solid #dcfce7;
    }

    .status-completed {
      background: #eff6ff;
      color: #1d4ed8;
      border: 1px solid #dbeafe;
    }

    .status-cancelled {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fee2e2;
    }

    /* Service List (Receipt Style) */
    .service-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px dashed var(--gray-300);
      font-size: 14px;
    }

    .service-row:last-child {
      border-bottom: none;
    }

    .service-meta {
      color: var(--muted);
      font-size: 12px;
      display: block;
      margin-top: 2px;
    }

    .total-box {
      background-color: var(--light);
      padding: 15px;
      border-radius: var(--radius-sm);
      margin-top: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .total-label {
      font-weight: 600;
      color: var(--gray-600);
    }

    .total-amount {
      font-size: 20px;
      font-weight: 800;
      color: var(--primary);
    }

    /* Buttons */
    .btn {
      padding: 10px 20px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition-fast);
      border: none;
      cursor: pointer;
    }

    .btn-outline {
      background: white;
      border: 1px solid var(--gray-300);
      color: var(--gray-700);
    }

    .btn-outline:hover {
      background: var(--gray-200);
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background: var(--primary-dark);
    }

    .btn-delete {
      background: #fee2e2;
      color: #b91c1c;
    }

    .btn-delete:hover {
      background: #fecaca;
    }

    .patient-avatar-lg {
      width: 60px;
      height: 60px;
      background: var(--light);
      color: var(--primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin-bottom: 12px;
    }

    .walkin-badge {
      background: #e0f2fe;
      color: #0369a1;
      font-size: 11px;
      padding: 2px 6px;
      border-radius: 4px;
      vertical-align: middle;
      margin-left: 5px;
    }
  </style>
</head>

<body>
  <div class="offset">

    <div class="topbar">
      <div>
        <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">Appointments / Details</div>
        <h1 class="topbar-title">Appointment #<?= $appointment['id'] ?></h1>
      </div>
      <div class="topbar-actions">
        <a href="appointments.php" class="btn btn-outline">
          <i class="fas fa-arrow-left"></i> Back
        </a>

        <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'approved'): ?>
        <?php endif; ?>

        <a href="edit_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-primary">
          <i class="fas fa-edit"></i> Edit Details
        </a>
      </div>
    </div>

    <div class="page-grid">

      <div class="col-main">

        <div class="card">
          <div class="card-title">
            <i class="far fa-calendar-alt"></i> Appointment Information
          </div>

          <div class="info-grid">
            <div class="info-item">
              <label>Date</label>
              <span><?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?></span>
            </div>
            <div class="info-item">
              <label>Time</label>
              <span><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></span>
            </div>
            <div class="info-item">
              <label>Reference Code</label>
              <span style="font-family: monospace; letter-spacing: 1px;"><?= htmlspecialchars($appointment['reference_no'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
              <label>Assigned Dentist</label>
              <span>Dr. <?= htmlspecialchars($appointment['dentist_name']) ?></span>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-title">
            <i class="fas fa-receipt"></i> Services & Billing
          </div>

          <div class="service-list">
            <?php if (isset($appointment['services']) && !empty($appointment['services'])): ?>
              <?php foreach ($appointment['services'] as $svc): ?>
                <div class="service-row">
                  <div>
                    <div style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($svc['name']) ?></div>
                    <span class="service-meta">
                      <?= (int)$svc['duration_minutes'] ?> mins
                      <?php if (!empty($svc['description'])) echo " • " . htmlspecialchars($svc['description']); ?>
                    </span>
                  </div>
                  <div style="font-weight: 500;">₱<?= number_format($svc['price'], 2) ?></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="padding: 15px; text-align: center; color: var(--muted); font-style: italic;">No services attached</div>
            <?php endif; ?>
          </div>

          <div class="total-box">
            <div>
              <div class="total-label">Total Duration</div>
              <div style="color: var(--muted); font-size: 14px;"><?= (int)($appointment['total_duration'] ?? 0) ?> Minutes</div>
            </div>
            <div style="text-align: right;">
              <div class="total-label">Grand Total</div>
              <div class="total-amount">₱<?= number_format($appointment['total_price'] ?? 0, 2) ?></div>
            </div>
          </div>
        </div>

        <?php if ($appointment['notes']): ?>
          <div class="card">
            <div class="card-title"><i class="fas fa-sticky-note"></i> Notes</div>
            <div style="background: #fff9c4; padding: 15px; border-radius: 8px; color: #5d4037; line-height: 1.6;">
              <?= nl2br(htmlspecialchars($appointment['notes'])) ?>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <div class="col-side">

        <div class="card" style="text-align: center;">
          <div style="margin-bottom: 10px; font-size: 12px; color: var(--muted); text-transform: uppercase;">Current Status</div>
          <span class="status-badge status-<?= strtolower($appointment['status']) ?>">
            <?php
            $icons = [
              'pending' => 'fa-clock',
              'approved' => 'fa-check-circle',
              'completed' => 'fa-flag-checkered',
              'cancelled' => 'fa-ban'
            ];
            $icon = $icons[strtolower($appointment['status'])] ?? 'fa-circle';
            ?>
            <i class="fas <?= $icon ?>"></i> <?= ucfirst($appointment['status']) ?>
          </span>
          <div style="margin-top: 15px; font-size: 12px; color: var(--muted);">
            Created: <?= date('M j, Y', strtotime($appointment['created_at'])) ?>
          </div>
        </div>

        <div class="card">
          <div class="card-title" style="border: none; padding-bottom: 0;"><i class="fas fa-user"></i> Patient Details</div>

          <div style="display: flex; flex-direction: column; align-items: center; margin: 20px 0;">
            <div class="patient-avatar-lg">
              <i class="fas fa-user"></i>
            </div>
            <h3 style="font-size: 18px; color: var(--dark); margin-bottom: 4px;">
              <?= htmlspecialchars($appointment['patient_name']) ?>
              <?php if ($is_walkin): ?><span class="walkin-badge">Walk-in</span><?php endif; ?>
            </h3>
            <span style="font-size: 13px; color: var(--muted);">Patient ID: <?= $appointment['patient_id'] ?? 'N/A' ?></span>
          </div>

          <div style="border-top: 1px solid var(--gray-200); padding-top: 15px;">
            <?php if (!$is_walkin): ?>
              <div style="margin-bottom: 12px; display: flex; gap: 10px;">
                <i class="fas fa-envelope" style="color: var(--muted); margin-top: 3px;"></i>
                <span style="font-size: 14px; word-break: break-all;"><?= htmlspecialchars($appointment['patient_email'] ?? 'No email') ?></span>
              </div>
              <div style="margin-bottom: 12px; display: flex; gap: 10px;">
                <i class="fas fa-phone" style="color: var(--muted); margin-top: 3px;"></i>
                <span style="font-size: 14px;"><?= htmlspecialchars($appointment['patient_phone'] ?? 'No phone') ?></span>
              </div>
            <?php else: ?>
              <div style="text-align: center; color: var(--muted); font-size: 13px; font-style: italic;">
                Limited contact info for walk-ins. Check notes.
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

  </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
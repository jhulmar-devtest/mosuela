<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'dentist') {
  header("Location: ../login.php");
  exit();
}

$dentist_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  header("Location: appointments.php");
  exit();
}

// Handle form submission BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $new_status = $_POST['status'] ?? '';
  $dentist_notes = trim($_POST['dentist_notes'] ?? '');
  $valid_statuses = ['pending', 'approved', 'completed', 'cancelled'];

  if (!in_array($new_status, $valid_statuses)) {
    echo "<script>
      alert('Invalid status');
      window.history.back();
    </script>";
    exit();
  }

  $update_query = "
    UPDATE appointments
    SET status = ?,
        notes = CONCAT(notes, '\n\nDentist Notes: ', ?)
    WHERE id = ? AND dentist_id = ?
  ";

  $update_stmt = $conn->prepare($update_query);
  $update_stmt->bind_param("ssii", $new_status, $dentist_notes, $id, $dentist_id);

  if ($update_stmt->execute()) {
    echo "<script>
      alert('Appointment status updated successfully!');
      window.location.href = 'view_appointment.php?id=$id';
    </script>";
  } else {
    echo "<script>
      alert('Error updating appointment: " . $conn->error . "');
    </script>";
  }

  $update_stmt->close();
  exit();
}

// Fetch appointment (ensure it belongs to this dentist)
$query = "
  SELECT 
    a.id,
    a.reference_no,
    a.appointment_date,
    a.appointment_time,
    a.status,
    a.notes,
    a.patient_id,
    a.dentist_id,
    a.total_price,
    a.total_duration,
    CASE 
      WHEN a.patient_id IS NULL AND a.notes LIKE 'Walk-in:%' THEN TRIM(SUBSTRING(a.notes, 9))
      WHEN a.patient_id IS NULL THEN 'Walk-in Patient'
      ELSE p.name 
    END AS patient_name,
    p.email,
    p.phone,
    d.name AS dentist_name
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  INNER JOIN users d ON a.dentist_id = d.id
  WHERE a.id = ? AND a.dentist_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id, $dentist_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Appointment not found or access denied'); window.location.href='appointments.php';</script>";
  exit();
}

$appointment = $result->fetch_assoc();

// Get services for this appointment using helper function
$appt_with_services = get_appointment_with_services($conn, $id);
if ($appt_with_services['success']) {
  $appointment['services'] = $appt_with_services['services'];
}

include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Update Appointment #<?= $appointment['id'] ?> - Miracle Mosuela</title>
  <style>
    .details-container {
      background: var(--card);
      box-shadow: var(--shadow);
      border-radius: var(--radius);
      padding: 30px 40px;
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
    }

    .back-link {
      text-decoration: none;
      color: var(--primary);
      font-weight: 600;
      display: inline-block;
      margin-bottom: 15px;
      transition: color 0.2s ease;
    }

    .back-link:hover {
      color: #21867a;
    }

    .topbar {
      display: flex;
      height: 80px;
      justify-content: space-between;
      align-items: center;
      padding: 15px 25px;
      background-color: var(--bg);
      box-shadow: 1px 9px 10px 0px rgba(0, 0, 0, 0.08);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .topbar h2 {
      margin: 0;
      font-size: 22px;
      color: var(--primary);
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 25px;
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 10px;
    }

    .header-row h1 {
      font-size: 26px;
      color: var(--primary);
      margin: 0;
    }

    .details-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 25px;
    }

    .details-table th {
      background: #f8fafc;
      color: var(--muted);
      text-transform: uppercase;
      font-size: 13px;
      padding: 12px 10px;
      border-bottom: 1px solid #e5e7eb;
    }

    .details-table td {
      padding: 14px 12px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: top;
      font-size: 15px;
    }

    .details-table tr:hover td {
      background: #f9fafb;
    }

    .status-strong {
      padding: 6px 12px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 13px;
    }

    .status-completed {
      background: #d1fae5;
      color: #065f46;
    }

    .status-pending {
      background: #fef9c3;
      color: #92400e;
    }

    .status-approved {
      background: #e0f2fe;
      color: #1e40af;
    }

    .status-cancelled {
      background: #fee2e2;
      color: #991b1b;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
    }

    select,
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }

    textarea {
      min-height: 100px;
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 20px;
    }

    .btn {
      background: var(--primary);
      color: white;
      border: none;
      padding: 10px 18px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
    }

    .btn:hover {
      background-color: #21867a;
    }

    .btn.btn-secondary {
      background: #ddd;
      color: #222;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .guide-list {
      margin-top: 40px;
      background: var(--card);
      padding: 20px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    .guide-list h3 {
      margin-top: 0;
      color: var(--primary);
    }

    .guide-list ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .guide-list li {
      margin-bottom: 8px;
      font-size: 14px;
    }

    @media (max-width: 900px) {
      body {
        padding: 16px;
      }

      .details-container {
        padding: 20px;
      }

      .header-row h1 {
        font-size: 22px;
      }

      .details-table td,
      .details-table th {
        font-size: 13px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>Update Appointment</h2>
      <div class="topbar-right">
        <i class="fa fa-bell notification-icon"></i>
      </div>
    </div>

    <div class="details-container">

      <a href="appointments.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Appointment Details
      </a>

      <div class="header-row">
        <h1>Update Appointment #<?= $appointment['id'] ?></h1>
      </div>

      <!-- Appointment Info -->
      <table class="details-table">
        <tr>
          <th>Reference Code</th>
          <td><strong><?= htmlspecialchars($appointment['reference_no'] ?? 'N/A') ?></strong></td>
        </tr>
        <tr>
          <th>Patient</th>
          <td><?= htmlspecialchars($appointment['patient_name']) ?></td>
        </tr>
        <tr>
          <th>Services</th>
          <td>
            <?php if (isset($appointment['services']) && !empty($appointment['services'])): ?>
              <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($appointment['services'] as $svc): ?>
                  <li>
                    <strong><?= htmlspecialchars($svc['name']) ?></strong>
                    <?php if (!empty($svc['description'])): ?>
                      <br><small style="color: var(--muted); font-style: italic;"><?= htmlspecialchars($svc['description']) ?></small>
                    <?php endif; ?>
                    <br><span style="color: var(--muted); font-size: 12px;">₱<?= number_format($svc['price'], 2) ?> • <?= (int)$svc['duration_minutes'] ?> min</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <em style="color: var(--muted);">No services</em>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Total Price</th>
          <td>₱<?= number_format($appointment['total_price'] ?? 0, 2) ?></td>
        </tr>
        <tr>
          <th>Total Duration</th>
          <td><?= (int)($appointment['total_duration'] ?? 0) ?> minutes</td>
        </tr>
        <tr>
          <th>Date</th>
          <td><?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?></td>
        </tr>
        <tr>
          <th>Time</th>
          <td><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></td>
        </tr>
        <tr>
          <th>Current Status</th>
          <td><span class="status-strong status-<?= $appointment['status'] ?>"><?= ucfirst($appointment['status']) ?></span></td>
        </tr>
      </table>

      <!-- Update Form -->
      <form method="POST">
        <div class="form-group">
          <label for="status"><i class="fas fa-tag"></i> Change Status To</label>
          <select id="status" name="status" required>
            <option value="pending" <?= $appointment['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $appointment['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="completed" <?= $appointment['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= $appointment['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>

        <div class="form-group">
          <label for="dentist_notes"><i class="fas fa-sticky-note"></i> Add Notes (Optional)</label>
          <textarea id="dentist_notes" name="dentist_notes" placeholder="Add any notes about this appointment..."></textarea>
          <small style="color: var(--muted);">These notes will be added to the appointment record</small>
        </div>

        <div class="form-actions">
          <a href="appointments.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
          <button type="submit" class="btn"><i class="fas fa-save"></i> Update Appointment</button>
        </div>
      </form>

      <!-- Status Guide -->
      <div class="guide-list">
        <h3><i class="fas fa-book"></i> Status Guide</h3>
        <ul>
          <li><strong>Pending:</strong> Waiting for confirmation</li>
          <li><strong>Approved:</strong> Confirmed appointment</li>
          <li><strong>Completed:</strong> Service completed</li>
          <li><strong>Cancelled:</strong> Appointment was cancelled</li>
        </ul>
      </div>
    </div>
  </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
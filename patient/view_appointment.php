<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'patient') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: appointments.php");
  exit();
}

$appointment_id = intval($_GET['id']);
$patient_id = $_SESSION['user_id'];

// Get appointment details (without services JOIN)
$query = "SELECT a.id, a.reference_no, a.appointment_date, a.appointment_time, a.status, a.notes, 
                 a.total_price, a.total_duration, d.name AS dentist_name
          FROM appointments a
          INNER JOIN users d ON a.dentist_id = d.id
          WHERE a.id = ? AND a.patient_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Appointment not found.'); window.location.href='appointments.php';</script>";
  exit();
}

$row = $result->fetch_assoc();

// Get services for this appointment using helper function
$appt_with_services = get_appointment_with_services($conn, $appointment_id);
if ($appt_with_services['success']) {
  $row['services'] = $appt_with_services['services'];
  $row['total_price'] = $appt_with_services['total_price'];
  $row['total_duration'] = $appt_with_services['total_duration'];
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>View Appointment - Miracle Mosuela</title>
  <style>
    .details-container {
      background: var(--card);
      box-shadow: var(--shadow);
      border-radius: var(--radius);
      padding: 30px 40px;
      width: 100%;
      max-width: 900px;
      margin: 0 auto;
    }

    .back-link {
      text-decoration: none;
      color: var(--primary);
      font-weight: 600;
      display: inline-block;
      margin-bottom: 20px;
      transition: color 0.2s ease;
    }

    .back-link:hover {
      color: #21867a;
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
      text-align: left;
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

    .action-links {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }

    .action-links a {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: var(--radius);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .action-links a:first-child {
      background: var(--primary);
      color: #fff;
    }

    .action-links a:first-child:hover {
      background: #21867a;
    }

    .delete-link {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .delete-link:hover {
      background: #fecaca;
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


    /* Topbar */
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
      margin: -24px -24px 24px -24px;
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
  </style>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>Appointment Details</h2>
      <div class="topbar-right">
      </div>
    </div>
    <div class="details-container">
      <table class="details-table">
        <tr>
          <th>Reference Code</th>
          <td><strong><?= htmlspecialchars($row['reference_no'] ?? 'N/A') ?></strong></td>
        </tr>
        <tr>
          <th>Services</th>
          <td>
            <?php if (isset($row['services']) && !empty($row['services'])): ?>
              <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($row['services'] as $svc): ?>
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
          <td>₱<?= number_format($row['total_price'] ?? 0, 2) ?></td>
        </tr>
        <tr>
          <th>Total Duration</th>
          <td><?= (int)($row['total_duration'] ?? 0) ?> minutes</td>
        </tr>
        <tr>
          <th>Dentist</th>
          <td>Dr. <?= htmlspecialchars($row['dentist_name']) ?></td>
        </tr>
        <tr>
          <th>Date</th>
          <td><?= htmlspecialchars($row['appointment_date']) ?></td>
        </tr>
        <tr>
          <th>Time</th>
          <td><?= date("h:i A", strtotime($row['appointment_time'])) ?></td>
        </tr>
        <tr>
          <th>Status</th>
          <td>
            <span class="status-strong status-<?= strtolower($row['status']) ?>">
              <?= ucfirst($row['status']) ?>
            </span>
          </td>
        </tr>
        <tr>
          <th>Notes</th>
          <td><?= htmlspecialchars($row['notes']) ?: 'None' ?></td>
        </tr>
        <tr>
          <th>Price</th>
          <td>₱<?= number_format($row['total_price'] ?? 0, 2) ?></td>
        </tr>
      </table>
      <a href="appointments.php" class="back-link">← Back to Appointments</a>
    </div>
  </div>
</body>

</html>
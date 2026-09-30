<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$patient_id) {
  header("Location: patients.php");
  exit();
}

// Get patient info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'patient'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
  echo "<script>alert('Patient not found'); window.location.href='patients.php';</script>";
  exit();
}

// Get medical history
$history_query = "SELECT * FROM patient_medical_history WHERE patient_id = ?";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $patient_id);
$history_stmt->execute();
$medical_history = $history_stmt->get_result()->fetch_assoc();

// Get appointments
$appt_query = "
  SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes, a.total_price, a.total_duration, d.name as dentist_name
  FROM appointments a
  INNER JOIN users d ON a.dentist_id = d.id
  WHERE a.patient_id = ?
  ORDER BY a.appointment_date DESC, a.appointment_time DESC
  LIMIT 10
";
$appt_stmt = $conn->prepare($appt_query);
$appt_stmt->bind_param("i", $patient_id);
$appt_stmt->execute();
$appointments = $appt_stmt->get_result();

// Get treatment records
$records_query = "
  SELECT pr.*, d.name as dentist_name
  FROM patient_records pr
  INNER JOIN users d ON pr.dentist_id = d.id
  WHERE pr.patient_id = ?
  ORDER BY pr.created_at DESC
";
$records_stmt = $conn->prepare($records_query);
$records_stmt->bind_param("i", $patient_id);
$records_stmt->execute();
$records = $records_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Records - <?= htmlspecialchars($patient['name']) ?></title>
  <style>
    /* ===== Topbar ===== */
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
      font-weight: 600;
    }

    /* ===== Card Styles ===== */
    .card {
      background: var(--card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 24px 28px;
      margin-bottom: 24px;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 12px;
    }

    .card-title {
      font-size: 18px;
      font-weight: 600;
      color: var(--primary);
      margin: 0;
    }

    /* ===== Table ===== */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
      background: white;
      border-radius: 8px;
      overflow: hidden;
    }

    th,
    td {
      padding: 12px 14px;
      border-bottom: 1px solid #f1f5f9;
      text-align: left;
      font-size: 14px;
    }

    th {
      background: #f8fafc;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      font-size: 12px;
    }

    tr:hover {
      background: #f9fafb;
    }

    /* ===== Record Cards ===== */
    .record-card {
      background: #f9fafb;
      border-radius: 8px;
      border: 1px solid var(--border);
      padding: 16px 20px;
      margin-bottom: 16px;
    }

    .record-card p {
      margin: 6px 0;
      font-size: 14px;
    }

    /* ===== Buttons ===== */
    .btn {
      border: none;
      padding: 6px 10px;
      border-radius: 8px;
      font-size: 12px;
      cursor: pointer;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s ease;
    }

    .btn-view {
      background: #dbeafe;
      color: #1e40af;
    }

    .btn-view:hover {
      background: #bfdbfe;
      box-shadow: 0 2px 4px rgba(30, 64, 175, 0.1);
    }

    /* ===== No Data ===== */
    .no-data {
      text-align: center;
      color: var(--muted);
      font-style: italic;
      padding: 20px;
    }

    /* ===== Back Link ===== */
    .back-link {
      display: inline-block;
      margin-top: 16px;
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    @media (max-width: 900px) {
      .offset {
        margin-left: 0;
        padding: 16px;
      }

      .card {
        padding: 20px;
      }

      th,
      td {
        padding: 8px 10px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>Patient Records - <?= htmlspecialchars($patient['name']) ?></h2>
    </div>

    <!-- Patient Info -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Patient Information</h3>
      </div>
      <table>
        <tr>
          <th>Name</th>
          <td><?= htmlspecialchars($patient['name']) ?></td>
        </tr>
        <tr>
          <th>Email</th>
          <td><?= htmlspecialchars($patient['email']) ?></td>
        </tr>
        <tr>
          <th>Phone</th>
          <td><?= htmlspecialchars($patient['phone'] ?: 'Not provided') ?></td>
        </tr>
        <tr>
          <th>Registered</th>
          <td><?= date('F j, Y', strtotime($patient['created_at'])) ?></td>
        </tr>
        <tr>
          <th>Status</th>
          <td><?= ucfirst($patient['status']) ?></td>
        </tr>
      </table>
    </div>

    <!-- Medical History -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Medical History</h3>
      </div>
      <?php if ($medical_history): ?>
        <table>
          <tr>
            <th>Allergies</th>
            <td><?= nl2br(htmlspecialchars($medical_history['allergies'] ?: 'None reported')) ?></td>
          </tr>
          <tr>
            <th>Current Medications</th>
            <td><?= nl2br(htmlspecialchars($medical_history['current_medications'] ?: 'None reported')) ?></td>
          </tr>
          <tr>
            <th>Medical Conditions</th>
            <td><?= nl2br(htmlspecialchars($medical_history['medical_conditions'] ?: 'None reported')) ?></td>
          </tr>
          <tr>
            <th>Previous Dental Work</th>
            <td><?= nl2br(htmlspecialchars($medical_history['previous_dental_work'] ?: 'None reported')) ?></td>
          </tr>
          <tr>
            <th>Blood Type</th>
            <td><?= htmlspecialchars($medical_history['blood_type'] ?: 'Not specified') ?></td>
          </tr>
          <tr>
            <th>Emergency Contact</th>
            <td>
              <?php if ($medical_history['emergency_contact_name']): ?>
                <?= htmlspecialchars($medical_history['emergency_contact_name']) ?>
                (<?= htmlspecialchars($medical_history['emergency_contact_phone']) ?>)
              <?php else: ?> Not provided <?php endif; ?>
            </td>
          </tr>
        </table>
      <?php else: ?>
        <p class="no-data">No medical history recorded yet.</p>
      <?php endif; ?>
    </div>

    <!-- Recent Appointments -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Recent Appointments</h3>
      </div>
      <?php if ($appointments->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Time</th>
              <th>Dentist</th>
              <th>Service</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($appt = $appointments->fetch_assoc()): ?>
              <tr>
                <td><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                <td><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                <td>Dr. <?= htmlspecialchars($appt['dentist_name']) ?></td>
                <td>
                  <?php
                  $appt_services = get_appointment_with_services($conn, $appt['id']);
                  if ($appt_services['success'] && !empty($appt_services['services'])) {
                    echo implode(', ', array_map(function ($s) {
                      return htmlspecialchars($s['name']);
                    }, $appt_services['services']));
                  } else {
                    echo '-';
                  }
                  ?>
                </td>
                <td><?= ucfirst($appt['status']) ?></td>
                <td><a href="view_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-view">View</a></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="no-data">No appointments found.</p>
      <?php endif; ?>
    </div>

    <!-- Treatment Records -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Treatment Records</h3>
      </div>
      <?php if ($records->num_rows > 0): ?>
        <?php while ($record = $records->fetch_assoc()): ?>
          <div class="record-card">
            <p><strong>Date:</strong> <?= date('F j, Y g:i A', strtotime($record['created_at'])) ?></p>
            <p><strong>Dentist:</strong> Dr. <?= htmlspecialchars($record['dentist_name']) ?></p>
            <?php if ($record['diagnosis']): ?>
              <p><strong>Diagnosis:</strong> <?= nl2br(htmlspecialchars($record['diagnosis'])) ?></p><?php endif; ?>
            <?php if ($record['treatment']): ?>
              <p><strong>Treatment:</strong> <?= nl2br(htmlspecialchars($record['treatment'])) ?></p><?php endif; ?>
            <?php if ($record['prescription']): ?>
              <p><strong>Prescription:</strong> <?= nl2br(htmlspecialchars($record['prescription'])) ?></p><?php endif; ?>
            <?php if ($record['notes']): ?>
              <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($record['notes'])) ?></p><?php endif; ?>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="no-data">No treatment records found.</p>
      <?php endif; ?>
    </div>

    <a href="patients.php" class="back-link">← Back to Patients</a>
  </div>
</body>

</html>

<?php
$stmt->close();
$history_stmt->close();
$appt_stmt->close();
$records_stmt->close();
$conn->close();
?>
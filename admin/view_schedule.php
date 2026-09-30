<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Get schedule ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  header("Location: schedules.php");
  exit();
}

// Fetch schedule details
$query = "
  SELECT da.*, u.name as dentist_name, u.email as dentist_email
  FROM dentist_availability da
  JOIN users u ON da.dentist_id = u.id
  WHERE da.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Schedule not found'); window.location.href='schedules.php';</script>";
  exit();
}

$schedule = $result->fetch_assoc();

// Get appointments for this date and dentist
$appointments_query = "
  SELECT 
    a.id,
    a.appointment_time,
    a.status,
    COALESCE(p.name, SUBSTRING_INDEX(a.notes, ': ', -1)) as patient_name,
    s.name as service_name,
    s.duration_minutes
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  INNER JOIN services s ON a.service_id = s.id
  WHERE a.dentist_id = ? 
  AND DATE(a.appointment_time) = ?
  ORDER BY a.appointment_time ASC
";

$appt_stmt = $conn->prepare($appointments_query);
$appt_stmt->bind_param("is", $schedule['dentist_id'], $schedule['date']);
$appt_stmt->execute();
$appointments_result = $appt_stmt->get_result();

// Calculate time slot utilization
$total_minutes = (strtotime($schedule['end_time']) - strtotime($schedule['start_time'])) / 60;
$booked_minutes = 0;
$appointments_result->data_seek(0); // Reset pointer
while ($appt = $appointments_result->fetch_assoc()) {
  if ($appt['status'] !== 'cancelled') {
    $booked_minutes += $appt['duration_minutes'];
  }
}
$appointments_result->data_seek(0); // Reset again for display

$utilization_percentage = $total_minutes > 0 ? round(($booked_minutes / $total_minutes) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Schedule #<?= $schedule['id'] ?></title>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>PSchedule Details - Dr. <?= htmlspecialchars($schedule['dentist_name']) ?></h2>
      <div class="topbar-right">
        <div class="notification-icon">
          <i class="fa fa-bell"></i>
        </div>
      </div>
    </div>

    <div class="details-container">

      <!-- New header row for title and buttons -->
      <div class="header-row">
        <h1>Schedule Details - Dr. <?= htmlspecialchars($schedule['dentist_name']) ?></h1>

        <div class="action-links">
          <a href="edit_schedule.php?id=<?= $schedule['id'] ?>">Edit Schedule</a>
          <!-- Using a styled button instead of the browser's confirm() dialog -->
          <a href="delete_schedule.php?id=<?= $schedule['id'] ?>" class="delete-link">Delete Schedule</a>
        </div>
      </div>

      <h2>Schedule Information</h2>
      <table class="details-table">
        <tr>
          <th>Field</th>
          <th>Information</th>
        </tr>
        <tr>
          <td><strong>Dentist</strong></td>
          <td>Dr. <?= htmlspecialchars($schedule['dentist_name']) ?></td>
        </tr>
        <tr>
          <td><strong>Dentist Email</strong></td>
          <td><?= htmlspecialchars($schedule['dentist_email']) ?></td>
        </tr>
        <tr>
          <td><strong>Date</strong></td>
          <td><?= date('l, F j, Y', strtotime($schedule['date'])) ?></td>
        </tr>
        <tr>
          <td><strong>Start Time</strong></td>
          <td><?= date('g:i A', strtotime($schedule['start_time'])) ?></td>
        </tr>
        <tr>
          <td><strong>End Time</strong></td>
          <td><?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
        </tr>
        <tr>
          <td><strong>Total Duration</strong></td>
          <td><?= $total_minutes ?> minutes (<span style="font-weight: 600;"><?= round($total_minutes / 60, 1) ?></span> hours)</td>
        </tr>
        <tr>
          <td><strong>Status</strong></td>
          <td>
            <?php
            $status_class = strtolower($schedule['status']);
            // Apply existing status styling
            echo "<span class='status-strong status-{$status_class}'>" . ucfirst($schedule['status']) . "</span>";
            ?>
          </td>
        </tr>
        <?php if ($schedule['notes']): ?>
          <tr>
            <td><strong>Notes</strong></td>
            <td><?= nl2br(htmlspecialchars($schedule['notes'])) ?></td>
          </tr>
        <?php endif; ?>
      </table>

      <h2>Utilization</h2>
      <table class="details-table stats-table">
        <thead>
          <tr>
            <th>Total Available Time</th>
            <th>Booked Time</th>
            <th>Remaining Time</th>
            <th>Utilization</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong style="color: var(--primary);"><?= $total_minutes ?></strong> mins</td>
            <td><strong style="color: #856404;"><?= $booked_minutes ?></strong> mins</td>
            <td><strong style="color: #155724;"><?= $total_minutes - $booked_minutes ?></strong> mins</td>
            <td><strong style="color: #dc3545;"><?= $utilization_percentage ?>%</strong></td>
          </tr>
        </tbody>
      </table>

      <h2>Appointments on This Date</h2>
      <?php if ($appointments_result->num_rows > 0): ?>
        <table class="details-table recent-appointments-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Time</th>
              <th>Patient</th>
              <th>Service</th>
              <th>Duration</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            while ($appt = $appointments_result->fetch_assoc()):
              $status_class = strtolower($appt['status']);
            ?>
              <tr>
                <td><?= $appt['id'] ?></td>
                <td><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                <td><?= htmlspecialchars($appt['patient_name']) ?></td>
                <td><?= htmlspecialchars($appt['service_name']) ?></td>
                <td><?= $appt['duration_minutes'] ?> mins</td>
                <td><span class="status-strong status-<?= $status_class ?>"><?= ucfirst($appt['status']) ?></span></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color: var(--muted); padding-left: 5px;">No appointments scheduled for this date yet.</p>
      <?php endif; ?>

      <p><a href="schedules.php">← Back to Schedules</a></p>
    </div>
  </div>
</body>

</html>

<?php
$stmt->close();
$appt_stmt->close();
$conn->close();
?>
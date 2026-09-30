<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Get dentist ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  header("Location: dentists.php");
  exit();
}

// Fetch dentist details
$query = "SELECT * FROM users WHERE id = ? AND role = 'dentist'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Dentist not found'); window.location.href='dentists.php';</script>";
  exit();
}

$dentist = $result->fetch_assoc();

// Fetch dentist's services
$services_query = "
  SELECT s.id, s.name, s.price, s.duration_minutes 
  FROM services s
  INNER JOIN dentist_services ds ON s.id = ds.service_id
  WHERE ds.dentist_id = ?
  ORDER BY s.name
";
$services_stmt = $conn->prepare($services_query);
$services_stmt->bind_param("i", $id);
$services_stmt->execute();
$services_result = $services_stmt->get_result();

// Fetch dentist's availability schedule
$availability_query = "
  SELECT date, start_time, end_time, status, notes
  FROM dentist_availability
  WHERE dentist_id = ? AND date >= CURDATE()
  ORDER BY date ASC
  LIMIT 10
";
$availability_stmt = $conn->prepare($availability_query);
$availability_stmt->bind_param("i", $id);
$availability_stmt->execute();
$availability_result = $availability_stmt->get_result();

// Get appointment statistics
$stats_query = "
  SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
  FROM appointments
  WHERE dentist_id = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get recent appointments
$recent_query = "
  SELECT 
    a.id,
    a.appointment_date,
    a.appointment_time,
    a.status,
    COALESCE(p.name, SUBSTRING_INDEX(a.notes, ': ', -1)) as patient_name
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  WHERE a.dentist_id = ?
  ORDER BY a.appointment_date DESC, a.appointment_time DESC
  LIMIT 5
";
$recent_stmt = $conn->prepare($recent_query);
$recent_stmt->bind_param("i", $id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Dentist - Dr. <?= htmlspecialchars($dentist['name']) ?></title>
  <style>
    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 40px;
      border-radius: var(--radius-lg);
      margin-bottom: 32px;
      box-shadow: var(--shadow-lg);
      position: relative;
      overflow: hidden;
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(233, 196, 106, 0.2), transparent);
      border-radius: 50%;
    }

    .page-header-content {
      position: relative;
      z-index: 2;
    }

    .page-title {
      font-size: 32px;
      font-weight: 800;
      margin: 0 0 8px 0;
    }

    .page-subtitle {
      font-size: 16px;
      opacity: 0.95;
      margin: 0;
    }

    .card {
      background: var(--card);
      border-radius: var(--radius-lg);
      padding: 24px;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      text-align: left;
      padding: 10px;
      border-bottom: 1px solid #e5e7eb;
    }

    th {
      background: #ffffff;
      font-weight: 600;
      color: #374151;
    }

    .status-strong {
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 13px;
    }

    .status-active {
      background: #d1fae5;
      color: #065f46;
    }

    .status-inactive {
      background: #fee2e2;
      color: #991b1b;
    }

    .status-approved {
      background: #bfdbfe;
      color: #1e3a8a;
    }

    .status-pending {
      background: #fef9c3;
      color: #78350f;
    }

    .status-completed {
      background: #d9f99d;
      color: #365314;
    }

    .status-cancelled {
      background: #fecaca;
      color: #7f1d1d;
    }

    @media (max-width: 900px) {
      .offset {
        margin-left: 0;
        padding: 16px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      table {
        font-size: 13px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">View Dentist</h1>
        <p class="page-subtitle">Dr. <?= htmlspecialchars($dentist['name']) ?></p>
      </div>
    </div>

    <!-- Info Cards -->
    <div class="card">
      <h2>Basic Information</h2>
      <table>
        <tr>
          <th>ID</th>
          <td><?= $dentist['id'] ?></td>
        </tr>
        <tr>
          <th>Name</th>
          <td>Dr. <?= htmlspecialchars($dentist['name']) ?></td>
        </tr>
        <tr>
          <th>Email</th>
          <td><?= htmlspecialchars($dentist['email']) ?></td>
        </tr>
        <tr>
          <th>Phone</th>
          <td><?= htmlspecialchars($dentist['phone'] ?: 'Not provided') ?></td>
        </tr>
        <tr>
          <th>Status</th>
          <td><span
              class="status-strong status-<?= strtolower($dentist['status']) ?>"><?= ucfirst($dentist['status']) ?></span>
          </td>
        </tr>
        <tr>
          <th>Created</th>
          <td><?= date('F j, Y g:i A', strtotime($dentist['created_at'])) ?></td>
        </tr>
      </table>
    </div>

    <div class="card">
      <h2>Services Offered</h2>
      <?php if ($services_result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Service</th>
              <th>Duration</th>
              <th>Price</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($service = $services_result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($service['name']) ?></td>
                <td><?= $service['duration_minutes'] ?> mins</td>
                <td>₱<?= number_format($service['price'], 2) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:var(--muted);">No services assigned.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Recent Appointments</h2>
      <?php if ($recent_result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Patient</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($appt = $recent_result->fetch_assoc()): ?>
              <tr>
                <td><?= $appt['id'] ?></td>
                <td><?= htmlspecialchars($appt['patient_name']) ?></td>
                <td><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                <td><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                <td><span
                    class="status-strong status-<?= strtolower($appt['status']) ?>"><?= ucfirst($appt['status']) ?></span>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:var(--muted);">No recent appointments found.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Upcoming Availability</h2>
      <?php if ($availability_result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Start</th>
              <th>End</th>
              <th>Status</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($avail = $availability_result->fetch_assoc()): ?>
              <tr>
                <td><?= date('M j, Y', strtotime($avail['date'])) ?></td>
                <td><?= date('g:i A', strtotime($avail['start_time'])) ?></td>
                <td><?= date('g:i A', strtotime($avail['end_time'])) ?></td>
                <td><span
                    class="status-strong status-<?= strtolower($avail['status']) ?>"><?= ucfirst($avail['status']) ?></span>
                </td>
                <td><?= htmlspecialchars($avail['notes'] ?: '-') ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color:var(--muted);">No upcoming availability.</p>
      <?php endif; ?>
    </div>

    <p><a href="dentists.php" class="back-link">← Back to Dentists</a></p>
  </div>
</body>

</html>

<?php
$stmt->close();
$services_stmt->close();
$availability_stmt->close();
$stats_stmt->close();
$recent_stmt->close();
$conn->close();
?>
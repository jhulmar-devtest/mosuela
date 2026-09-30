<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  header("Location: secretaries.php");
  exit();
}

// Get secretary info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'secretary'");
$stmt->bind_param("i", $id);
$stmt->execute();
$secretary = $stmt->get_result()->fetch_assoc();

if (!$secretary) {
  echo "<script>alert('Secretary not found'); window.location.href='secretaries.php';</script>";
  exit();
}

// Get activity statistics
$stats_query = "
  SELECT 
    COUNT(DISTINCT DATE(a.appointment_date)) as days_worked,
    COUNT(a.id) as appointments_managed
  FROM appointments a
  WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get recent activity
$activity_query = "
  SELECT 
    a.id,
    a.appointment_date,
    a.appointment_time,
    a.status,
    a.created_at,
    COALESCE(p.name, SUBSTRING_INDEX(a.notes, ': ', -1)) as patient_name,
    d.name as dentist_name
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  INNER JOIN users d ON a.dentist_id = d.id
  ORDER BY a.created_at DESC
  LIMIT 10
";
$activity_result = $conn->query($activity_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Secretary - <?= htmlspecialchars($secretary['name']) ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f7fc;
      margin: 0;
      padding: 20px;
      padding-left: 300px;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    h1 {
      color: #333;
      font-size: 24px;
    }

    h2 {
      color: #444;
      font-size: 20px;
      margin-bottom: 10px;
    }

    .section {
      background-color: white;
      padding: 20px;
      margin-bottom: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    table,
    th,
    td {
      border: 1px solid #ddd;
    }

    th,
    td {
      padding: 12px;
      text-align: left;
      font-size: 14px;
    }

    th {
      background-color: #f8f9fa;
    }

    .status {
      padding: 4px 12px;
      border-radius: 12px;
      font-weight: 600;
    }

    .status.active {
      background: #d4edda;
      color: #155724;
    }

    .status.inactive {
      background: #f8d7da;
      color: #721c24;
    }

    .actions a {
      padding: 10px 20px;
      margin: 5px;
      text-decoration: none;
      border-radius: 4px;
      color: white;
      font-weight: bold;
      transition: background-color 0.3s;
    }

    .actions .edit {
      background: #007bff;
    }

    .actions .edit:hover {
      background: #0056b3;
    }

    .actions .delete {
      background: #dc3545;
    }

    .actions .delete:hover {
      background: #c82333;
    }

    .back-link {
      font-size: 14px;
      color: #007bff;
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    /* Responsive Design */
    @media (max-width: 768px) {

      table,
      th,
      td {
        font-size: 12px;
      }

      .actions a {
        padding: 8px 16px;
        font-size: 14px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <header>
      <h1>Secretary Details: <?= htmlspecialchars($secretary['name']) ?></h1>
      <div class="actions">
        <a href="edit_secretary.php?id=<?= $secretary['id'] ?>" class="edit">
          <i class="ri-edit-fill"></i> Edit
        </a>
        <a href="delete_secretary.php?id=<?= $secretary['id'] ?>" class="delete" onclick="return confirm('Are you sure you want to delete this secretary?')">Delete</a>
      </div>
    </header>

    <div class="section">
      <h2>Basic Information</h2>
      <table>
        <tr>
          <td><strong>Name:</strong></td>
          <td><?= htmlspecialchars($secretary['name']) ?></td>
        </tr>
        <tr>
          <td><strong>Email:</strong></td>
          <td><?= htmlspecialchars($secretary['email']) ?></td>
        </tr>
        <tr>
          <td><strong>Phone:</strong></td>
          <td><?= htmlspecialchars($secretary['phone'] ?: 'Not provided') ?></td>
        </tr>
        <tr>
          <td><strong>Status:</strong></td>
          <td>
            <span class="status <?= $secretary['status'] ?>"><?= ucfirst($secretary['status']) ?></span>
          </td>
        </tr>
        <tr>
          <td><strong>Joined:</strong></td>
          <td><?= date('F j, Y', strtotime($secretary['created_at'])) ?></td>
        </tr>
      </table>
    </div>

    <div class="section">
      <h2>Activity Statistics (Last 30 Days)</h2>
      <table>
        <tr>
          <th>Days Worked</th>
          <th>Appointments Managed</th>
        </tr>
        <tr>
          <td style="text-align: center; font-size: 24px; font-weight: bold;"><?= $stats['days_worked'] ?></td>
          <td style="text-align: center; font-size: 24px; font-weight: bold;"><?= $stats['appointments_managed'] ?></td>
        </tr>
      </table>
    </div>

    <div class="section">
      <h2>Recent Activity</h2>
      <?php if ($activity_result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Date Created</th>
              <th>Appointment Date</th>
              <th>Patient</th>
              <th>Dentist</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($activity = $activity_result->fetch_assoc()): ?>
              <tr>
                <td><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></td>
                <td><?= date('M j, Y g:i A', strtotime($activity['appointment_date'] . ' ' . $activity['appointment_time'])) ?></td>
                <td><?= htmlspecialchars($activity['patient_name']) ?></td>
                <td>Dr. <?= htmlspecialchars($activity['dentist_name']) ?></td>
                <td><?= ucfirst($activity['status']) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No recent activity.</p>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
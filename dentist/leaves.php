<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'dentist') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$dentist_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>My Leaves - Miracle Mosuela</title>
</head>

<body class="offset">
  <div class="pages-header">
    <h2>Leave Requests - Dentist</h2>
    <a class="header-btn" href="file_leave.php">File New Leave</a>
  </div>

  <div class="content">
    <table border="1" class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Reason</th>
          <th>Status</th>
          <th>Date Filed</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $query = "
          SELECT id, start_date, end_date, reason, status, created_at 
          FROM dentist_leaves 
          WHERE dentist_id = ? 
          ORDER BY created_at DESC
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $dentist_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $statusColor = match ($row['status']) {
              'approved' => 'green',
              'rejected' => 'red',
              default => 'orange'
            };

            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['start_date']}</td>
                    <td>{$row['end_date']}</td>
                    <td>" . htmlspecialchars($row['reason']) . "</td>
                    <td style='color:$statusColor; font-weight:bold;'>" . ucfirst($row['status']) . "</td>
                    <td>{$row['created_at']}</td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='6'>No leave requests found.</td></tr>";
        }

        $stmt->close();
        ?>
      </tbody>
    </table>
  </div>
</body>

</html>
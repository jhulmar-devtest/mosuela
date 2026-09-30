<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Get schedule ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch existing schedule data
$stmt = $conn->prepare("SELECT * FROM dentist_availability WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Schedule not found.'); window.location.href='schedules.php';</script>";
  exit();
}

$schedule = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $dentist_id = $_POST['dentist_id'];
  $date = $_POST['date'];
  $start_time = $_POST['start_time'];
  $end_time = $_POST['end_time'];
  $status = $_POST['status'];

  $stmt = $conn->prepare("UPDATE dentist_availability 
                          SET dentist_id = ?, date = ?, start_time = ?, end_time = ?, status = ?
                          WHERE id = ?");
  $stmt->bind_param("issssi", $dentist_id, $date, $start_time, $end_time, $status, $id);

  if ($stmt->execute()) {
    echo "<script>alert('Schedule updated successfully!'); window.location.href='schedules.php';</script>";
  } else {
    echo "<script>alert('Error updating schedule: " . $conn->error . "');</script>";
  }

  $stmt->close();
  $conn->close();
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Edit Schedule - Miracle Mosuela</title>
  <link rel="stylesheet" href="../styles/style.css">
  <Style>
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
  </Style>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>Edit Schedule?></h2>
      <div class="topbar-right">
      </div>
    </div>
    <form class="card-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $id; ?>" method="POST">

      <!-- Dentist Select -->
      <div class="form-group">
        <label for="dentist_id">Dentist:</label>
        <select id="dentist_id" name="dentist_id" required>
          <?php
          // Mock data for preview, assuming $schedule['dentist_id'] = 2 and $id = 5
          $schedule = ['dentist_id' => 2, 'date' => '2025-10-20', 'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'available'];
          $id = 5;

          // Static options for preview
          $dentist_mock_data = [
            ['id' => 1, 'name' => 'Dr. Alice Smith'],
            ['id' => 2, 'name' => 'Dr. Bob Johnson'],
            ['id' => 3, 'name' => 'Dr. Carol King'],
          ];
          foreach ($dentist_mock_data as $dentist) {
            $selected = ($dentist['id'] == $schedule['dentist_id']) ? 'selected' : '';
            echo "<option value='{$dentist['id']}' $selected>" . htmlspecialchars($dentist['name']) . "</option>";
          }
          ?>
        </select>
      </div>

      <!-- Date Input -->
      <div class="form-group">
        <label for="date">Date:</label>
        <input type="date" id="date" name="date" value="<?php echo $schedule['date']; ?>" required>
      </div>

      <!-- Start Time Input -->
      <div class="form-group">
        <label for="start_time">Start Time:</label>
        <input type="time" id="start_time" name="start_time" value="<?php echo $schedule['start_time']; ?>" required>
      </div>

      <!-- End Time Input -->
      <div class="form-group">
        <label for="end_time">End Time:</label>
        <input type="time" id="end_time" name="end_time" value="<?php echo $schedule['end_time']; ?>" required>
      </div>

      <!-- Status Select -->
      <div class="form-group">
        <label for="status">Status:</label>
        <select id="status" name="status" required>
          <option value="available" <?php if ($schedule['status'] === 'available') echo 'selected'; ?>>Available</option>
          <option value="off" <?php if ($schedule['status'] === 'off') echo 'selected'; ?>>Off</option>
          <option value="half_day" <?php if ($schedule['status'] === 'half_day') echo 'selected'; ?>>Half Day</option>
        </select>
      </div>

      <div class="form-actions">
        <!-- Updated button class to match the dedicated submit style -->
        <button type="submit" class="btn-form-submit">Update Schedule</button>
        <a href="schedules.php" class="cancel-btn">Cancel</a>
      </div>
    </form>
  </div>
</body>

</html>
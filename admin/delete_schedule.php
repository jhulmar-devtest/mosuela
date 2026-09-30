<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
  $stmt = $conn->prepare("DELETE FROM dentist_availability WHERE id = ?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    echo "<script>alert('Schedule deleted successfully!'); window.location.href='schedules.php';</script>";
  } else {
    echo "<script>alert('Error deleting schedule: " . $conn->error . "'); window.location.href='schedules.php';</script>";
  }
  $stmt->close();
} else {
  echo "<script>alert('Invalid schedule ID.'); window.location.href='schedules.php';</script>";
}

$conn->close();

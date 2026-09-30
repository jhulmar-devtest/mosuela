<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
  echo "<script>alert('Invalid service ID.'); window.location.href='services.php';</script>";
  exit();
}

$service_id = intval($_GET['id']);

// Check if service exists
$stmt = $conn->prepare("SELECT id FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
  echo "<script>alert('Service not found.'); window.location.href='services.php';</script>";
  exit();
}

$stmt->close();

// Delete service (and cascade if foreign key is set)
$delete = $conn->prepare("DELETE FROM services WHERE id = ?");
$delete->bind_param("i", $service_id);

if ($delete->execute()) {
  echo "<script>alert('Service deleted successfully.'); window.location.href='services.php';</script>";
} else {
  echo "<script>alert('Error deleting service: " . $conn->error . "');</script>";
}

$delete->close();
$conn->close();

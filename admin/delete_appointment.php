<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

// Get appointment ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  header("Location: appointments.php");
  exit();
}

// Check if appointment exists
$check_query = "SELECT id FROM appointments WHERE id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Appointment not found'); window.location.href='appointments.php';</script>";
  exit();
}

// Delete the appointment with transaction to handle foreign keys
$conn->begin_transaction();

try {
  // First, delete related appointment_services records
  $delete_services = "DELETE FROM appointment_services WHERE appointment_id = ?";
  $service_stmt = $conn->prepare($delete_services);
  if (!$service_stmt) {
    throw new Exception("Prepare error: " . $conn->error);
  }
  $service_stmt->bind_param("i", $id);
  if (!$service_stmt->execute()) {
    throw new Exception("Delete services error: " . $service_stmt->error);
  }
  $service_stmt->close();

  // Then delete the appointment itself
  $delete_query = "DELETE FROM appointments WHERE id = ?";
  $delete_stmt = $conn->prepare($delete_query);
  if (!$delete_stmt) {
    throw new Exception("Prepare error: " . $conn->error);
  }
  $delete_stmt->bind_param("i", $id);
  if (!$delete_stmt->execute()) {
    throw new Exception("Delete appointment error: " . $delete_stmt->error);
  }
  $delete_stmt->close();

  // Commit the transaction
  $conn->commit();
  echo "<script>alert('Appointment deleted successfully!'); window.location.href='appointments.php';</script>";
} catch (Exception $e) {
  // Rollback on error
  $conn->rollback();
  echo "<script>alert('Error deleting appointment: " . htmlspecialchars($e->getMessage()) . "'); window.location.href='appointments.php';</script>";
}

$stmt->close();
$conn->close();

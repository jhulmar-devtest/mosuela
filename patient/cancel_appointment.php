<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'patient') {
  header("Location: ../login.php");
  exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: appointments.php");
  exit();
}

$appointment_id = intval($_GET['id']);
$patient_id = $_SESSION['user_id'];

// Only allow cancel if it's pending or approved
$query = "UPDATE appointments 
          SET status='cancelled' 
          WHERE id=? AND patient_id=? AND status IN ('pending','approved')";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $appointment_id, $patient_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
  echo "<script>alert('Appointment cancelled successfully.'); window.location.href='appointments.php';</script>";
} else {
  echo "<script>alert('Unable to cancel appointment. It may already be cancelled or completed.'); window.location.href='appointments.php';</script>";
}

$stmt->close();
$conn->close();

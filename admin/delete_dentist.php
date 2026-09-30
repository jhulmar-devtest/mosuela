<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
  echo "<script>alert('Invalid dentist ID.'); window.location.href='dentists.php';</script>";
  exit();
}

$dentist_id = intval($_GET['id']);

// Double-check dentist exists
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'dentist'");
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
  echo "<script>alert('Dentist not found.'); window.location.href='dentists.php';</script>";
  exit();
}

$stmt->close();

// Delete dentist (and cascade deletes their services if ON DELETE CASCADE is set)
$delete = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'dentist'");
$delete->bind_param("i", $dentist_id);

if ($delete->execute()) {
  echo "<script>alert('Dentist deleted successfully.'); window.location.href='dentists.php';</script>";
} else {
  echo "<script>alert('Error deleting dentist: " . $conn->error . "');</script>";
}

$delete->close();
$conn->close();

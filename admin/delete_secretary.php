<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  header("Location: secretaries.php");
  exit();
}

// Check if secretary exists
$stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'secretary'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Secretary not found'); window.location.href='secretaries.php';</script>";
  exit();
}

$secretary = $result->fetch_assoc();

// Delete secretary
$delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'secretary'");
$delete_stmt->bind_param("i", $id);

if ($delete_stmt->execute()) {
  echo "<script>alert('Secretary \"" . addslashes($secretary['name']) . "\" deleted successfully!'); window.location.href='secretaries.php';</script>";
} else {
  echo "<script>alert('Error deleting secretary'); window.location.href='secretaries.php';</script>";
}

$delete_stmt->close();
$stmt->close();
$conn->close();

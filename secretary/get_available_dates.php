<?php
include '../includes/db.php';

$dentist_id = intval($_GET['dentist_id']);

if (!isset($_GET['service_ids'])) {
  echo json_encode([]);
  exit;
}

$serviceIds = array_map('intval', explode(',', $_GET['service_ids']));
$serviceIds = array_filter($serviceIds); // Remove any 0 values

if (empty($serviceIds)) {
  echo json_encode([]);
  exit;
}

$placeholders = implode(',', array_fill(0, count($serviceIds), '?'));

// 1️⃣ Verify dentist can perform ALL services
$sql = "
  SELECT COUNT(DISTINCT ds.service_id) AS cnt
  FROM dentist_services ds
  WHERE ds.dentist_id = ?
    AND ds.service_id IN ($placeholders)
";

$stmt = $conn->prepare($sql);
$types = 'i' . str_repeat('i', count($serviceIds));
$stmt->bind_param($types, $dentist_id, ...$serviceIds);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['cnt'] !== count($serviceIds)) {
  echo json_encode([]);
  exit;
}

// 2️⃣ Fetch available dates
$query = "
  SELECT date
  FROM dentist_availability
  WHERE dentist_id = ?
    AND status = 'available'
    AND date >= CURDATE()
  ORDER BY date ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
while ($row = $result->fetch_assoc()) {
  // Validate date format (YYYY-MM-DD)
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'])) {
    $dates[] = $row['date'];
  }
}

echo json_encode($dates);

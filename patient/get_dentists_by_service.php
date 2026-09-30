<?php
include '../includes/db.php';

if (!isset($_GET['service_ids'])) {
  echo json_encode([]);
  exit;
}

// Convert "1,2,3" → [1,2,3]
$serviceIds = array_filter(array_map('intval', explode(',', $_GET['service_ids'])));

if (empty($serviceIds)) {
  echo json_encode([]);
  exit;
}

// Prepare placeholders (?, ?, ?)
$placeholders = implode(',', array_fill(0, count($serviceIds), '?'));

$sql = "
  SELECT u.id, u.name
  FROM users u
  JOIN dentist_services ds ON ds.dentist_id = u.id
  WHERE u.role = 'dentist'
    AND u.status = 'active'
    AND ds.service_id IN ($placeholders)
  GROUP BY u.id
  HAVING COUNT(DISTINCT ds.service_id) = ?
";

$stmt = $conn->prepare($sql);

// Bind params
$types = str_repeat('i', count($serviceIds)) . 'i';
$params = array_merge($serviceIds, [count($serviceIds)]);
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();

$dentists = [];
while ($row = $result->fetch_assoc()) {
  $dentists[] = $row;
}

echo json_encode($dentists);

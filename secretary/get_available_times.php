<?php
include '../includes/db.php';
date_default_timezone_set('Asia/Manila');

$dentist_id = intval($_GET['dentist_id']);
$date = $_GET['date'];
$duration = isset($_GET['duration']) ? intval($_GET['duration']) : 30;

// 1️⃣ Get dentist's available working hours
$q = "SELECT start_time, end_time 
      FROM dentist_availability 
      WHERE dentist_id=? AND date=? AND status='available'";
$stmt = $conn->prepare($q);
$stmt->bind_param("is", $dentist_id, $date);
$stmt->execute();
$stmt->bind_result($start, $end);
$stmt->fetch();
$stmt->close();

// No schedule found
if (!$start || !$end) {
  header('Content-Type: application/json');
  echo json_encode([]);
  exit();
}

// 2️⃣ Generate time slots (30-min intervals)
$startTime = strtotime($start);
$endTime   = strtotime((string)$end);
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s'); // Changed to include seconds
$slots = [];

while ($startTime < $endTime) {
  $slot = date('H:i:s', $startTime); // Changed to H:i:s format

  // Skip past times if date is today
  if ($date === $currentDate && $slot <= $currentTime) {
    $startTime = strtotime('+30 minutes', $startTime);
    continue;
  }

  $slots[] = $slot;
  $startTime = strtotime('+30 minutes', $startTime);
}

// 3️⃣ Get booked appointments with calculated duration
$bq = $conn->prepare("
  SELECT 
    a.appointment_time,
    a.total_duration
  FROM appointments a
  WHERE a.dentist_id=? AND a.appointment_date=? 
    AND a.status IN ('pending', 'approved')
");
$bq->bind_param("is", $dentist_id, $date);
$bq->execute();
$result = $bq->get_result();

$bookedRanges = [];
while ($row = $result->fetch_assoc()) {
  $startBooked = strtotime($row['appointment_time']);
  $totalDur = $row['total_duration'] ?? 30;
  $endBooked = strtotime($row['appointment_time'] . " +{$totalDur} minutes");
  $bookedRanges[] = ['start' => $startBooked, 'end' => $endBooked];
}
$bq->close();

// 4️⃣ Determine which slots are available
$available = [];

foreach ($slots as $slot) {
  $slotStart = strtotime($slot);
  $slotEnd   = strtotime("$slot +{$duration} minutes");
  $overlaps = false;

  foreach ($bookedRanges as $b) {
    if (
      ($slotStart >= $b['start'] && $slotStart < $b['end']) ||
      ($slotEnd > $b['start'] && $slotEnd <= $b['end']) ||
      ($slotStart <= $b['start'] && $slotEnd >= $b['end'])
    ) {
      $overlaps = true;
      break;
    }
  }

  if (!$overlaps) {
    $available[] = $slot;
  }
}

// 5️⃣ Return available times
header('Content-Type: application/json');
echo json_encode($available);

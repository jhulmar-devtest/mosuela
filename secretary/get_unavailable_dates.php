<?php
include '../includes/db.php';

// Get the dentist_id from the URL (example: get_unavailable_dates.php?dentist_id=3)
$dentist_id = $_GET['dentist_id'];

// Prepare and execute query to get all dates for that dentist
$query = $conn->prepare("SELECT date FROM dentist_availability WHERE dentist_id = ?");
$query->bind_param("i", $dentist_id);
$query->execute();
$result = $query->get_result();

// Store all the dates in an array
$dates = [];
while ($row = $result->fetch_assoc()) {
  $dates[] = $row['date']; // example: "2025-10-14"
}

// Send back as JSON (so JavaScript can read it easily)
echo json_encode($dates);

$query->close();
$conn->close();

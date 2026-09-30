<?php

/**
 * Appointment Helper Functions
 * Shared logic for appointment booking across all roles (patient, admin, secretary)
 */

/**
 * Generate unique reference number for appointment
 * @param mysqli $conn - Database connection
 * @return string - Unique reference number
 */
function generate_reference_number($conn) {
  // Get the last appointment ID
  $query = "SELECT MAX(id) as max_id FROM appointments";
  $result = $conn->query($query);
  $row = $result->fetch_assoc();
  $next_id = ($row['max_id'] ?? 0) + 1;

  // Format: APT-XXXXXX (6 digits, zero-padded)
  return 'APT-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);
}

/**
 * Get all active services
 * @param mysqli $conn - Database connection
 * @return array - Array of service records
 */
function get_all_services($conn) {
  $query = "SELECT id, name, description, price, duration_minutes FROM services WHERE status = 'active' ORDER BY name ASC";
  $result = $conn->query($query);
  return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Get dentists who can perform ALL selected services
 * Only shows dentists with all requested services
 * @param mysqli $conn - Database connection
 * @param array $service_ids - Array of selected service IDs
 * @return array - Array of dentist records who can perform all services
 */
function get_dentists_for_services($conn, $service_ids) {
  if (empty($service_ids)) {
    return [];
  }

  // Filter out invalid IDs and prepare array
  $service_ids = array_map('intval', $service_ids);
  $service_ids = array_filter($service_ids);

  if (empty($service_ids)) {
    return [];
  }

  $service_count = count($service_ids);
  $placeholders = implode(',', array_fill(0, $service_count, '?'));

  $query = "
    SELECT DISTINCT d.id, d.name, d.email, d.phone
    FROM users d
    INNER JOIN dentist_services ds ON d.id = ds.dentist_id
    WHERE d.role = 'dentist' 
      AND d.status = 'active'
      AND ds.service_id IN ($placeholders)
    GROUP BY d.id
    HAVING COUNT(DISTINCT ds.service_id) = ?
    ORDER BY d.name ASC
  ";

  $stmt = $conn->prepare($query);
  if (!$stmt) {
    error_log("Prepare error: " . $conn->error);
    return [];
  }

  // Bind parameters
  $types = str_repeat('i', $service_count) . 'i';
  $params = array_merge($service_ids, [$service_count]);
  $stmt->bind_param($types, ...$params);

  if (!$stmt->execute()) {
    error_log("Execute error: " . $stmt->error);
    $stmt->close();
    return [];
  }

  $result = $stmt->get_result();
  $dentists = $result->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  return $dentists;
}

/**
 * Get details of services by their IDs
 * @param mysqli $conn - Database connection
 * @param array $service_ids - Array of service IDs
 * @return array - Array of service details
 */
function get_services_by_ids($conn, $service_ids) {
  if (empty($service_ids)) {
    return [];
  }

  $service_ids = array_map('intval', $service_ids);
  $service_ids = array_filter($service_ids);

  if (empty($service_ids)) {
    return [];
  }

  $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
  $query = "SELECT id, name, description, price, duration_minutes FROM services WHERE id IN ($placeholders) AND status = 'active'";

  $stmt = $conn->prepare($query);
  if (!$stmt) {
    error_log("Prepare error: " . $conn->error);
    return [];
  }

  $types = str_repeat('i', count($service_ids));
  $stmt->bind_param($types, ...$service_ids);

  if (!$stmt->execute()) {
    error_log("Execute error: " . $stmt->error);
    $stmt->close();
    return [];
  }

  $result = $stmt->get_result();
  $services = $result->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  return $services;
}

/**
 * Calculate total price and duration for selected services
 * @param mysqli $conn - Database connection
 * @param array $service_ids - Array of service IDs
 * @return array - ['total_price' => float, 'total_duration' => int] or ['error' => 'message']
 */
function calculate_appointment_totals($conn, $service_ids) {
  if (empty($service_ids)) {
    return ['error' => 'No services selected'];
  }

  $services = get_services_by_ids($conn, $service_ids);

  if (empty($services)) {
    return ['error' => 'Invalid services selected'];
  }

  $total_price = 0.0;
  $total_duration = 0;

  foreach ($services as $service) {
    $total_price += floatval($service['price']);
    $total_duration += intval($service['duration_minutes']);
  }

  return [
    'total_price' => $total_price,
    'total_duration' => $total_duration,
    'service_count' => count($services)
  ];
}

/**
 * Create appointment with multiple services
 * 
 * @param mysqli $conn - Database connection
 * @param array $data - Appointment data
 *        [
 *          'patient_id' => int|null (null for walk-ins),
 *          'dentist_id' => int,
 *          'service_ids' => array,
 *          'appointment_date' => string (YYYY-MM-DD),
 *          'appointment_time' => string (HH:MM:SS),
 *          'status' => string (default: 'pending'),
 *          'notes' => string (optional)
 *        ]
 * @return array - ['success' => true, 'appointment_id' => int, 'reference_no' => string] or ['error' => 'message']
 */
function create_appointment_with_services($conn, $data) {
  // Validate required fields
  $required = ['dentist_id', 'service_ids', 'appointment_date', 'appointment_time'];
  foreach ($required as $field) {
    if (!isset($data[$field]) || (is_array($data[$field]) && empty($data[$field]))) {
      return ['error' => "Missing required field: $field"];
    }
  }

  // Sanitize and validate input
  $patient_id = isset($data['patient_id']) ? intval($data['patient_id']) : null;
  $dentist_id = intval($data['dentist_id']);
  $service_ids = array_map('intval', (array)$data['service_ids']);
  $service_ids = array_filter($service_ids);
  $appointment_date = trim($data['appointment_date']);
  $appointment_time = trim($data['appointment_time']);
  $status = isset($data['status']) ? trim($data['status']) : 'pending';
  $notes = isset($data['notes']) ? trim($data['notes']) : '';

  // Validate date format
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    return ['error' => 'Invalid appointment date format'];
  }

  // Validate time format
  if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $appointment_time)) {
    return ['error' => 'Invalid appointment time format'];
  }

  // Ensure time has seconds
  if (!preg_match('/:\d{2}$/', $appointment_time)) {
    $appointment_time .= ':00';
  }

  if (empty($service_ids)) {
    return ['error' => 'At least one service must be selected'];
  }

  // Calculate totals (validates services exist)
  $totals = calculate_appointment_totals($conn, $service_ids);
  if (isset($totals['error'])) {
    return $totals;
  }

  // Verify dentist exists and is active
  $dentist_check = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'dentist' AND status = 'active'");
  if (!$dentist_check) {
    return ['error' => 'Database error'];
  }
  $dentist_check->bind_param('i', $dentist_id);
  $dentist_check->execute();
  if (!$dentist_check->get_result()->fetch_assoc()) {
    $dentist_check->close();
    return ['error' => 'Invalid dentist selected'];
  }
  $dentist_check->close();

  // Verify dentist can perform all selected services
  $dentist_services = $conn->prepare("
    SELECT COUNT(DISTINCT service_id) as service_count 
    FROM dentist_services 
    WHERE dentist_id = ? AND service_id IN (" . implode(',', array_fill(0, count($service_ids), '?')) . ")
  ");

  if (!$dentist_services) {
    return ['error' => 'Database error'];
  }

  $types = 'i' . str_repeat('i', count($service_ids));
  $params = array_merge([$dentist_id], $service_ids);
  $dentist_services->bind_param($types, ...$params);
  $dentist_services->execute();
  $result = $dentist_services->get_result()->fetch_assoc();
  $dentist_services->close();

  if (!$result || $result['service_count'] != count($service_ids)) {
    return ['error' => 'Selected dentist cannot perform all selected services'];
  }

  // Start transaction
  $conn->begin_transaction();

  try {
    // Generate reference number
    $reference_no = generate_reference_number($conn);

    // Insert appointment
    $insert_stmt = $conn->prepare("
      INSERT INTO appointments (reference_no, patient_id, dentist_id, appointment_date, appointment_time, status, notes, total_price, total_duration)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$insert_stmt) {
      throw new Exception('Prepare error: ' . $conn->error);
    }

    $insert_stmt->bind_param(
      'siissssdi',
      $reference_no,
      $patient_id,
      $dentist_id,
      $appointment_date,
      $appointment_time,
      $status,
      $notes,
      $totals['total_price'],
      $totals['total_duration']
    );

    if (!$insert_stmt->execute()) {
      throw new Exception('Insert appointment error: ' . $insert_stmt->error);
    }

    $appointment_id = $insert_stmt->insert_id;
    $insert_stmt->close();

    // Insert services for this appointment
    $service_stmt = $conn->prepare("INSERT INTO appointment_services (appointment_id, service_id) VALUES (?, ?)");
    if (!$service_stmt) {
      throw new Exception('Prepare error: ' . $conn->error);
    }

    foreach ($service_ids as $service_id) {
      $service_stmt->bind_param('ii', $appointment_id, $service_id);
      if (!$service_stmt->execute()) {
        throw new Exception('Insert service error: ' . $service_stmt->error);
      }
    }
    $service_stmt->close();

    // Commit transaction
    $conn->commit();

    return [
      'success' => true,
      'appointment_id' => $appointment_id,
      'reference_no' => $reference_no,
      'total_price' => $totals['total_price'],
      'total_duration' => $totals['total_duration']
    ];
  } catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log('Appointment creation error: ' . $e->getMessage());
    return ['error' => 'Failed to create appointment. Please try again.'];
  }
}

/**
 * Get appointment details with all services
 * @param mysqli $conn - Database connection
 * @param int $appointment_id - Appointment ID
 * @return array - ['success' => true, 'services' => [...], ...] or ['success' => false]
 */
function get_appointment_with_services($conn, $appointment_id) {
  // Get appointment basic info
  $query = "
    SELECT 
      a.id,
      a.reference_no,
      a.patient_id,
      a.dentist_id,
      a.appointment_date,
      a.appointment_time,
      a.status,
      a.notes,
      a.total_price,
      a.total_duration,
      a.created_at
    FROM appointments a
    WHERE a.id = ?
  ";

  $stmt = $conn->prepare($query);
  if (!$stmt) {
    return ['success' => false, 'error' => 'Database error'];
  }

  $stmt->bind_param('i', $appointment_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $appointment = $result->fetch_assoc();
  $stmt->close();

  if (!$appointment) {
    return ['success' => false, 'error' => 'Appointment not found'];
  }

  // Get services for this appointment
  $services_query = "
    SELECT 
      s.id,
      s.name,
      s.description,
      s.price,
      s.duration_minutes
    FROM appointment_services aps
    INNER JOIN services s ON aps.service_id = s.id
    WHERE aps.appointment_id = ?
  ";

  $services_stmt = $conn->prepare($services_query);
  if (!$services_stmt) {
    return ['success' => false, 'error' => 'Database error'];
  }

  $services_stmt->bind_param('i', $appointment_id);
  $services_stmt->execute();
  $services_result = $services_stmt->get_result();
  $services = $services_result->fetch_all(MYSQLI_ASSOC);
  $services_stmt->close();

  return [
    'success' => true,
    'id' => $appointment['id'],
    'reference_no' => $appointment['reference_no'],
    'patient_id' => $appointment['patient_id'],
    'dentist_id' => $appointment['dentist_id'],
    'appointment_date' => $appointment['appointment_date'],
    'appointment_time' => $appointment['appointment_time'],
    'status' => $appointment['status'],
    'notes' => $appointment['notes'],
    'total_price' => $appointment['total_price'],
    'total_duration' => $appointment['total_duration'],
    'created_at' => $appointment['created_at'],
    'services' => $services
  ];
}

/**
 * Get all appointments for a patient with services
 * @param mysqli $conn - Database connection
 * @param int $patient_id - Patient ID
 * @return array - ['success' => true, 'appointments' => [...]]
 */
function get_patient_appointments_with_services($conn, $patient_id) {
  $query = "
    SELECT 
      a.id,
      a.reference_no,
      a.appointment_date,
      a.appointment_time,
      a.status,
      a.notes,
      a.total_price,
      a.total_duration,
      u.name as dentist_name,
      u.email as dentist_email
    FROM appointments a
    INNER JOIN users u ON a.dentist_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
  ";

  $stmt = $conn->prepare($query);
  if (!$stmt) {
    return ['success' => false, 'error' => 'Database error'];
  }

  $stmt->bind_param('i', $patient_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $appointments = [];

  while ($row = $result->fetch_assoc()) {
    // Get services for each appointment
    $services_query = "
      SELECT 
        s.id,
        s.name,
        s.price,
        s.duration_minutes
      FROM appointment_services aps
      INNER JOIN services s ON aps.service_id = s.id
      WHERE aps.appointment_id = ?
    ";

    $services_stmt = $conn->prepare($services_query);
    $services_stmt->bind_param('i', $row['id']);
    $services_stmt->execute();
    $services_result = $services_stmt->get_result();
    $row['services'] = $services_result->fetch_all(MYSQLI_ASSOC);
    $services_stmt->close();

    $appointments[] = $row;
  }
  $stmt->close();

  return [
    'success' => true,
    'appointments' => $appointments
  ];
}

/**
 * Get available time slots for a dentist on a specific date
 * Excludes time slots where dentist has appointments or unavailable periods
 * @param mysqli $conn - Database connection
 * @param int $dentist_id - Dentist ID
 * @param string $date - Date (YYYY-MM-DD)
 * @param int $duration - Required duration in minutes
 * @return array - Array of available time slots
 */
function get_available_time_slots($conn, $dentist_id, $date, $duration = 30) {
  // Get dentist availability for the date
  $availability = $conn->prepare("
    SELECT start_time, end_time, break_start, break_end
    FROM dentist_availability
    WHERE dentist_id = ? AND date = ?
  ");

  if (!$availability) {
    return [];
  }

  $availability->bind_param('is', $dentist_id, $date);
  $availability->execute();
  $avail_result = $availability->get_result()->fetch_assoc();
  $availability->close();

  if (!$avail_result) {
    return [];
  }

  $start = strtotime($avail_result['start_time']);
  $end = strtotime($avail_result['end_time']);
  $break_start = $avail_result['break_start'] ? strtotime($avail_result['break_start']) : null;
  $break_end = $avail_result['break_end'] ? strtotime($avail_result['break_end']) : null;

  // Get booked appointments for the dentist on this date
  $booked = $conn->prepare("
    SELECT appointment_time, total_duration
    FROM appointments
    WHERE dentist_id = ? AND appointment_date = ? AND status != 'cancelled'
  ");

  if (!$booked) {
    return [];
  }

  $booked->bind_param('is', $dentist_id, $date);
  $booked->execute();
  $booked_result = $booked->get_result();
  $booked_slots = [];

  while ($row = $booked_result->fetch_assoc()) {
    $slot_start = strtotime($row['appointment_time']);
    $slot_end = $slot_start + ($row['total_duration'] * 60);
    $booked_slots[] = ['start' => $slot_start, 'end' => $slot_end];
  }
  $booked->close();

  // Generate available slots
  $slots = [];
  $current = $start;

  while ($current + ($duration * 60) <= $end) {
    $current_end = $current + ($duration * 60);

    // Check if slot is during break
    if ($break_start && $current >= $break_start && $current < $break_end) {
      $current = $break_end;
      continue;
    }

    // Check if slot overlaps with break
    if ($break_start && $current_end > $break_start && $current < $break_end) {
      $current = $break_end;
      continue;
    }

    // Check if slot is booked
    $is_booked = false;
    foreach ($booked_slots as $booked_slot) {
      if ($current < $booked_slot['end'] && $current_end > $booked_slot['start']) {
        $is_booked = true;
        break;
      }
    }

    if (!$is_booked) {
      $slots[] = date('H:i:s', $current);
    }

    $current += 30 * 60; // 30-minute intervals
  }

  return $slots;
}

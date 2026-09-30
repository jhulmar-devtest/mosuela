<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Process monthly schedule form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $dentist_id = $_POST['dentist_id'];
  $duration_type = $_POST['duration_type'];

  // Handle different duration types
  if ($duration_type === 'single') {
    $start_month = $_POST['start_month'];
    $end_month = $start_month;
  } else {
    $start_month = $_POST['end_month_start'];
    $end_month = $_POST['end_month'];
  }

  $weekdays = $_POST['weekdays'] ?? [];
  $start_time = $_POST['start_time'];
  $end_time = $_POST['end_time'];
  $break_start = $_POST['break_start'];
  $break_end = $_POST['break_end'];
  $status = $_POST['status'];

  $current = new DateTime($start_month . '-01');
  $end = new DateTime($end_month . '-01');
  $end->modify('last day of this month');

  $stmt = $conn->prepare("INSERT INTO dentist_availability 
        (dentist_id, date, start_time, end_time, break_start, break_end, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");

  $created_count = 0;
  while ($current <= $end) {
    $dayName = strtolower($current->format('l'));

    if (in_array($dayName, $weekdays)) {
      $date = $current->format('Y-m-d');

      $check = $conn->prepare("SELECT id FROM dentist_availability WHERE dentist_id=? AND date=?");
      $check->bind_param("is", $dentist_id, $date);
      $check->execute();
      $check->store_result();

      if ($check->num_rows === 0) {
        $stmt->bind_param("issssss", $dentist_id, $date, $start_time, $end_time, $break_start, $break_end, $status);
        $stmt->execute();
        $created_count++;
      }

      $check->close();
    }

    $current->modify('+1 day');
  }

  $stmt->close();
  $success_message = "Successfully created $created_count schedule entries!";
}

// Summary numbers
$totalAppointments = $conn->query("SELECT COUNT(*) AS total FROM appointments")->fetch_assoc()['total'];
$totalSchedules = $conn->query("SELECT COUNT(*) AS total FROM dentist_availability")->fetch_assoc()['total'];
$totalAvailable = $conn->query("SELECT COUNT(*) AS total FROM dentist_availability WHERE status='available'")->fetch_assoc()['total'];
$totalDentists = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='dentist'")->fetch_assoc()['total'];

$selected_dentist = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;
$dentists = $conn->query("SELECT id, name FROM users WHERE role='dentist'");

// Build events array for calendar
$availability = [];
if ($selected_dentist) {
  $appt_stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes,
               COALESCE(p.name, SUBSTRING_INDEX(a.notes, ': ', -1)) AS patient_name
        FROM appointments a
        LEFT JOIN users p ON a.patient_id = p.id
        WHERE a.dentist_id = ?
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
  $appt_stmt->bind_param("i", $selected_dentist);
  $appt_stmt->execute();
  $appt_result = $appt_stmt->get_result();
  while ($row = $appt_result->fetch_assoc()) {
    $svc_info = get_appointment_with_services($conn, $row['id']);
    $service_text = (isset($svc_info['services']) && !empty($svc_info['services']))
      ? implode(', ', array_map(function ($s) {
        return $s['name'];
      }, $svc_info['services']))
      : 'No Service';
    $availability[] = [
      'title' => $row['patient_name'] . " - " . $service_text,
      'start' => $row['appointment_date'],
      'time' => date('g:i A', strtotime($row['appointment_time'])),
      'type' => 'appointment',
      'status' => $row['status']
    ];
  }
  $appt_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Schedules - Miracle Mosuela</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <style>
    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 40px;
      border-radius: var(--radius-lg);
      margin-bottom: 32px;
      box-shadow: var(--shadow-lg);
      position: relative;
      overflow: hidden;
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(233, 196, 106, 0.2), transparent);
      border-radius: 50%;
    }

    .page-header-content {
      position: relative;
      z-index: 2;
    }

    .page-title {
      font-size: 32px;
      font-weight: 800;
      margin: 0 0 8px 0;
    }

    .page-subtitle {
      font-size: 16px;
      opacity: 0.95;
      margin: 0;
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 24px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: var(--card);
      padding: 24px;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      display: flex;
      align-items: center;
      gap: 20px;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
      border-color: var(--secondary);
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      flex-shrink: 0;
    }

    .stat-content {
      flex: 1;
    }

    .stat-content h3 {
      font-size: 13px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin: 0 0 8px 0;
      font-weight: 600;
    }

    .stat-content .number {
      font-size: 32px;
      font-weight: 800;
      color: var(--primary);
      line-height: 1;
    }

    /* Success Message */
    .success-banner {
      background: #d1fae5;
      border-left: 4px solid #10b981;
      padding: 16px 20px;
      border-radius: var(--radius-lg);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .success-banner i {
      color: #10b981;
      font-size: 20px;
    }

    .success-banner p {
      margin: 0;
      color: #065f46;
      font-weight: 500;
    }

    /* Tabs Navigation */
    .tabs-container {
      background: var(--card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      overflow: hidden;
      margin-bottom: 24px;
    }

    .tabs-nav {
      display: flex;
      border-bottom: 2px solid #e5e7eb;
      background: #f9fafb;
    }

    .tab-button {
      flex: 1;
      padding: 16px 24px;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 15px;
      font-weight: 600;
      color: #6b7280;
      transition: all 0.3s ease;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .tab-button i {
      font-size: 18px;
    }

    .tab-button:hover {
      background: #f3f4f6;
      color: var(--primary);
    }

    .tab-button.active {
      color: var(--primary);
      background: white;
    }

    .tab-button.active::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      right: 0;
      height: 3px;
      background: var(--primary);
    }

    .tab-content {
      display: none;
      padding: 32px;
      animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
      display: block;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    /* Step Indicator */
    .step-indicator {
      display: flex;
      justify-content: space-between;
      margin-bottom: 40px;
      position: relative;
    }

    .step-indicator::before {
      content: '';
      position: absolute;
      top: 20px;
      left: 40px;
      right: 40px;
      height: 2px;
      background: #e5e7eb;
      z-index: 0;
    }

    .step-progress {
      position: absolute;
      top: 20px;
      left: 40px;
      height: 2px;
      background: var(--primary);
      z-index: 1;
      transition: width 0.3s ease;
    }

    .step {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
      position: relative;
      z-index: 2;
    }

    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #e5e7eb;
      color: #9ca3af;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      margin-bottom: 8px;
      transition: all 0.3s ease;
      border: 3px solid white;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .step.active .step-circle {
      background: var(--primary);
      color: white;
      transform: scale(1.1);
    }

    .step.completed .step-circle {
      background: #10b981;
      color: white;
    }

    .step.completed .step-circle i {
      display: block;
    }

    .step-label {
      font-size: 13px;
      font-weight: 600;
      color: #9ca3af;
      text-align: center;
      transition: color 0.3s ease;
    }

    .step.active .step-label,
    .step.completed .step-label {
      color: var(--primary);
    }

    /* Form Sections */
    .form-section {
      display: none;
      animation: fadeIn 0.3s ease;
    }

    .form-section.active {
      display: block;
    }

    .form-section-header {
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 2px solid #e5e7eb;
    }

    .form-section-header h3 {
      margin: 0 0 8px 0;
      font-size: 20px;
      color: #111827;
    }

    .form-section-header p {
      margin: 0;
      color: #6b7280;
      font-size: 14px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: #374151;
      font-size: 14px;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 12px;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    /* Duration Options */
    .duration-option input[type="radio"] {
      accent-color: var(--primary);
      cursor: pointer;
    }

    .duration-option:has(input:checked) {
      background: #d1fae5 !important;
      border-color: var(--primary) !important;
    }

    .duration-option:hover {
      border-color: var(--primary) !important;
      background: #f0fdfa !important;
    }

    /* Time Row */
    .time-row {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      gap: 16px;
      align-items: end;
    }

    .time-sep {
      color: #6b7280;
      font-weight: 600;
      padding-bottom: 12px;
    }

    /* Weekdays Grid */
    .weekdays {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 12px;
    }

    .weekday-checkbox {
      position: relative;
    }

    .weekday-checkbox input {
      position: absolute;
      opacity: 0;
    }

    .weekday-checkbox label {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 14px;
      background: #f9fafb;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: 500;
      gap: 8px;
    }

    .weekday-checkbox input:checked+label {
      background: #d1fae5;
      border-color: var(--primary);
      color: var(--primary);
    }

    .weekday-checkbox label:hover {
      border-color: var(--primary);
      background: #f0fdfa;
    }

    /* Form Navigation */
    .form-navigation {
      display: flex;
      justify-content: space-between;
      margin-top: 32px;
      padding-top: 24px;
      border-top: 2px solid #e5e7eb;
    }

    .btn {
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background: var(--secondary);
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(42, 157, 143, 0.3);
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Calendar Styles */
    .calendar-wrapper {
      margin-top: 24px;
    }

    .dentist-selector {
      background: var(--card);
      padding: 20px;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      margin-bottom: 20px;
    }

    .dentist-selector label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: #374151;
    }

    .dentist-selector select {
      width: 100%;
      padding: 12px;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-size: 14px;
    }

    .calendar-container {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 20px;
    }

    #calendar {
      background: var(--card);
      border-radius: var(--radius-lg);
      padding: 20px;
      box-shadow: var(--shadow);
    }

    /* Calendar Legend */
    .calendar-legend {
      background: var(--card);
      padding: 20px;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      margin-bottom: 20px;
    }

    .calendar-legend h4 {
      margin: 0 0 16px 0;
      font-size: 16px;
      color: #111827;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
      font-size: 13px;
    }

    .legend-color {
      width: 20px;
      height: 20px;
      border-radius: 4px;
    }

    .legend-available {
      background: #d1fae5;
      border: 2px solid #10b981;
    }

    .legend-appointment {
      background: #fef3f3;
      border: 2px solid #ef4444;
    }

    /* Appointments Panel */
    .appointments {
      background: var(--card);
      border-radius: var(--radius-lg);
      padding: 20px;
      box-shadow: var(--shadow);
      max-height: 600px;
      overflow-y: auto;
    }

    .appointments h3 {
      margin: 0 0 16px 0;
      font-size: 18px;
      color: #111827;
    }

    .appointment {
      padding: 14px;
      border-radius: 8px;
      margin-bottom: 12px;
      background: #f9fafb;
      border-left: 4px solid var(--primary);
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .appointment:hover {
      transform: translateX(4px);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .appointment h4 {
      margin: 0 0 6px;
      font-size: 14px;
      font-weight: 600;
      color: #111827;
    }

    .appointment p {
      margin: 0;
      font-size: 13px;
      color: #6b7280;
    }

    .appointment-event {
      border-left-color: #ef4444;
    }

    .availability {
      border-left-color: #10b981;
      background-color: #d1fae5;
    }

    /* FullCalendar Customization */
    .fc-event {
      border: none !important;
      padding: 4px 8px !important;
      border-radius: 4px !important;
      font-size: 12px !important;
    }

    .fc-event.availability {
      background-color: #d1fae5 !important;
      color: #065f46 !important;
      border-left: 3px solid #10b981 !important;
    }

    .fc-event.appointment-event {
      background-color: #fef3f3 !important;
      color: #b91c1c !important;
      border-left: 3px solid #ef4444 !important;
    }

    .fc-day-today {
      background-color: rgba(42, 157, 143, 0.1) !important;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .calendar-container {
        grid-template-columns: 1fr;
      }

      .step-indicator {
        flex-wrap: wrap;
      }

      .step {
        flex-basis: 33.333%;
        margin-bottom: 20px;
      }
    }

    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .time-row {
        grid-template-columns: 1fr;
      }

      .time-sep {
        display: none;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">Manage Schedules</h1>
        <p class="page-subtitle">View, create, and manage dentist schedules</p>
      </div>
    </div>

    <!-- Success Message -->
    <?php if (isset($success_message)): ?>
      <div class="success-banner">
        <i class="fas fa-check-circle"></i>
        <p><?= htmlspecialchars($success_message) ?></p>
      </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-content">
          <h3>Total Appointments</h3>
          <div class="number"><?= $totalAppointments ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
          <h3>Available Schedule</h3>
          <div class="number"><?= $totalAvailable ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-content">
          <h3>Total Schedule</h3>
          <div class="number"><?= $totalSchedules ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-md"></i></div>
        <div class="stat-content">
          <h3>Total Dentists</h3>
          <div class="number"><?= $totalDentists ?></div>
        </div>
      </div>
    </div>

    <!-- Tabs Container -->
    <div class="tabs-container">
      <div class="tabs-nav">
        <button class="tab-button active" onclick="switchTab('create')">
          <i class="fas fa-plus-circle"></i>
          Create Schedule
        </button>
        <button class="tab-button" onclick="switchTab('view')">
          <i class="fas fa-calendar"></i>
          View Schedules
        </button>
      </div>

      <!-- Create Schedule Tab -->
      <div class="tab-content active" id="create-tab">
        <!-- Step Indicator -->
        <div class="step-indicator">
          <div class="step-progress" id="step-progress" style="width: 0%"></div>
          <div class="step active" data-step="1">
            <div class="step-circle">1</div>
            <div class="step-label">Select Dentist</div>
          </div>
          <div class="step" data-step="2">
            <div class="step-circle">2</div>
            <div class="step-label">Set Date Range</div>
          </div>
          <div class="step" data-step="3">
            <div class="step-circle">3</div>
            <div class="step-label">Configure Time</div>
          </div>
          <div class="step" data-step="4">
            <div class="step-circle">4</div>
            <div class="step-label">Review & Submit</div>
          </div>
        </div>

        <!-- Multi-Step Form -->
        <form method="POST" action="" id="schedule-form">
          <!-- Step 1: Select Dentist -->
          <div class="form-section active" data-section="1">
            <div class="form-section-header">
              <h3>Select Dentist</h3>
              <p>Choose the dentist for whom you want to create a schedule</p>
            </div>
            <div class="form-group">
              <label for="dentist_id">Dentist Name:</label>
              <select name="dentist_id" id="dentist_id" required>
                <option value="" disabled selected>-- Select a Dentist --</option>
                <?php
                $dentists->data_seek(0);
                while ($row = $dentists->fetch_assoc()) {
                  echo "<option value='{$row['id']}'>Dr. " . htmlspecialchars($row['name']) . "</option>";
                }
                ?>
              </select>
            </div>
          </div>

          <!-- Step 2: Date Range & Days -->
          <div class="form-section" data-section="2">
            <div class="form-section-header">
              <h3>Set Date Range & Working Days</h3>
              <p>Define the month range and select which days of the week the dentist will be available</p>
            </div>

            <div class="form-group">
              <label>Schedule Duration:</label>
              <p style="font-size: 13px; color: #6b7280; margin: 0 0 12px 0;">
                <i class="fas fa-info-circle"></i> Choose how long you want to create this recurring schedule
              </p>
              <div class="duration-options" style="display: grid; gap: 12px; margin-bottom: 16px;">
                <label class="duration-option" style="display: flex; align-items: center; gap: 10px; padding: 14px; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">
                  <input type="radio" name="duration_type" value="single" checked onchange="toggleDurationInputs()" style="width: 18px; height: 18px;">
                  <div>
                    <strong style="display: block; color: #111827; margin-bottom: 2px;">Single Month</strong>
                    <small style="color: #6b7280;">Create schedule for one month only</small>
                  </div>
                </label>
                <label class="duration-option" style="display: flex; align-items: center; gap: 10px; padding: 14px; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">
                  <input type="radio" name="duration_type" value="range" onchange="toggleDurationInputs()" style="width: 18px; height: 18px;">
                  <div>
                    <strong style="display: block; color: #111827; margin-bottom: 2px;">Multiple Months</strong>
                    <small style="color: #6b7280;">Create schedule across several months</small>
                  </div>
                </label>
              </div>

              <div id="single-month-input">
                <label style="font-weight: 400; font-size: 13px; color: #6b7280; display: block; margin-bottom: 6px;">
                  Select Month & Year:
                </label>
                <input type="month" name="start_month" id="start_month" required
                  style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;"
                  placeholder="e.g., January 2025">
                <small style="display: block; margin-top: 6px; color: #6b7280;">
                  <i class="fas fa-lightbulb"></i> Example: December 2024
                </small>
              </div>

              <div id="range-month-input" style="display: none;">
                <div class="time-row">
                  <div>
                    <label style="font-weight: 400; font-size: 13px; color: #6b7280; display: block; margin-bottom: 6px;">
                      Start Month:
                    </label>
                    <input type="month" name="end_month_start" id="end_month_start"
                      style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                  </div>
                  <div class="time-sep">to</div>
                  <div>
                    <label style="font-weight: 400; font-size: 13px; color: #6b7280; display: block; margin-bottom: 6px;">
                      End Month:
                    </label>
                    <input type="month" name="end_month" id="end_month"
                      style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                  </div>
                </div>
                <small style="display: block; margin-top: 6px; color: #6b7280;">
                  <i class="fas fa-lightbulb"></i> Example: December 2024 to March 2025
                </small>
              </div>
            </div>

            <div class="form-group">
              <label>Working Days:</label>
              <div class="weekdays">
                <?php
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($days as $day) {
                  echo "<div class='weekday-checkbox'>";
                  echo "<input type='checkbox' name='weekdays[]' value='$day' id='day_$day'>";
                  echo "<label for='day_$day'><i class='fas fa-check'></i>" . ucfirst($day) . "</label>";
                  echo "</div>";
                }
                ?>
              </div>
            </div>
          </div>

          <!-- Step 3: Time Configuration -->
          <div class="form-section" data-section="3">
            <div class="form-section-header">
              <h3>Configure Working Hours</h3>
              <p>Set the daily working hours and break time for the dentist</p>
            </div>

            <div class="form-group">
              <label>Working Hours:</label>
              <div class="time-row">
                <div>
                  <label style="font-weight: 400; font-size: 13px; color: #6b7280;">Start Time:</label>
                  <input type="time" name="start_time" id="start_time" required>
                </div>
                <div class="time-sep">to</div>
                <div>
                  <label style="font-weight: 400; font-size: 13px; color: #6b7280;">End Time:</label>
                  <input type="time" name="end_time" id="end_time" required>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label>Break Hours:</label>
              <div class="time-row">
                <div>
                  <label style="font-weight: 400; font-size: 13px; color: #6b7280;">Break Start:</label>
                  <input type="time" name="break_start" id="break_start" required>
                </div>
                <div class="time-sep">to</div>
                <div>
                  <label style="font-weight: 400; font-size: 13px; color: #6b7280;">Break End:</label>
                  <input type="time" name="break_end" id="break_end" required>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="status">Availability Status:</label>
              <select name="status" id="status" required>
                <option value="available">Available</option>
                <option value="off">Off</option>
                <option value="half_day">Half Day</option>
              </select>
            </div>
          </div>

          <!-- Step 4: Review -->
          <div class="form-section" data-section="4">
            <div class="form-section-header">
              <h3>Review & Submit</h3>
              <p>Please review the schedule details before submitting</p>
            </div>

            <div id="review-content" style="background: #f9fafb; padding: 20px; border-radius: 8px; line-height: 1.8;">
              <p style="color: #6b7280;"><em>Please complete all previous steps to see the summary</em></p>
            </div>
          </div>

          <!-- Navigation Buttons -->
          <div class="form-navigation">
            <button type="button" class="btn btn-secondary" id="prev-btn" onclick="previousStep()" style="display: none;">
              <i class="fas fa-arrow-left"></i> Previous
            </button>
            <div></div>
            <button type="button" class="btn btn-primary" id="next-btn" onclick="nextStep()">
              Next <i class="fas fa-arrow-right"></i>
            </button>
            <button type="submit" class="btn btn-primary" id="submit-btn" style="display: none;">
              <i class="fas fa-check"></i> Create Schedule
            </button>
          </div>
        </form>
      </div>

      <!-- View Schedule Tab -->
      <div class="tab-content" id="view-tab">
        <div class="dentist-selector">
          <form method="GET" id="view-dentist-form">
            <label for="view_dentist_id">Select Dentist to View Schedule:</label>
            <select name="dentist_id" id="view_dentist_id" onchange="this.form.submit()">
              <option value="">-- Select a Dentist --</option>
              <?php
              $dentists->data_seek(0);
              while ($row = $dentists->fetch_assoc()):
              ?>
                <option value="<?= $row['id'] ?>" <?= $selected_dentist == $row['id'] ? 'selected' : '' ?>>
                  Dr. <?= htmlspecialchars($row['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </form>
        </div>

        <?php if ($selected_dentist): ?>
          <div class="calendar-legend">
            <h4><i class="fas fa-info-circle"></i> Schedule Legend</h4>
            <div class="legend-item">
              <div class="legend-color legend-available"></div>
              <span>Available Schedule - Dentist is available for appointments</span>
            </div>
            <div class="legend-item">
              <div class="legend-color legend-appointment"></div>
              <span>Booked Appointment - Time slot is occupied</span>
            </div>
          </div>

          <div class="calendar-container">
            <div id="calendar"></div>
            <div class="appointments" id="appointment-list">
              <h3><i class="fas fa-calendar-day"></i> Daily View</h3>
              <p style="color: #6b7280; font-size: 13px;">Click on a date in the calendar to view appointments</p>
            </div>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: 60px 20px; color: #6b7280;">
            <i class="fas fa-calendar-alt" style="font-size: 48px; margin-bottom: 16px; color: #d1d5db;"></i>
            <p style="font-size: 16px; margin: 0;">Please select a dentist to view their schedule</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <script>
      function toggleDurationInputs() {
        const durationType = document.querySelector('input[name="duration_type"]:checked').value;
        const singleInput = document.getElementById('single-month-input');
        const rangeInput = document.getElementById('range-month-input');
        const startMonthInput = document.getElementById('start_month');
        const endMonthStartInput = document.getElementById('end_month_start');
        const endMonthInput = document.getElementById('end_month');

        if (durationType === 'single') {
          singleInput.style.display = 'block';
          rangeInput.style.display = 'none';
          startMonthInput.required = true;
          endMonthStartInput.required = false;
          endMonthInput.required = false;
          // Clear range inputs
          endMonthStartInput.value = '';
          endMonthInput.value = '';
        } else {
          singleInput.style.display = 'none';
          rangeInput.style.display = 'block';
          startMonthInput.required = false;
          endMonthStartInput.required = true;
          endMonthInput.required = true;
          // Clear single input
          startMonthInput.value = '';
        }
      }

      let currentStep = 1;
      const totalSteps = 4;

      function switchTab(tab) {
        // Switch tab buttons
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

        if (tab === 'create') {
          document.querySelector('.tab-button:first-child').classList.add('active');
          document.getElementById('create-tab').classList.add('active');
        } else {
          document.querySelector('.tab-button:last-child').classList.add('active');
          document.getElementById('view-tab').classList.add('active');
        }
      }

      function updateStepIndicator() {
        // Update step circles
        document.querySelectorAll('.step').forEach((step, index) => {
          const stepNum = index + 1;
          step.classList.remove('active', 'completed');

          if (stepNum < currentStep) {
            step.classList.add('completed');
            step.querySelector('.step-circle').innerHTML = '<i class="fas fa-check"></i>';
          } else if (stepNum === currentStep) {
            step.classList.add('active');
            step.querySelector('.step-circle').textContent = stepNum;
          } else {
            step.querySelector('.step-circle').textContent = stepNum;
          }
        });

        // Update progress bar
        const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
        document.getElementById('step-progress').style.width = progress + '%';

        // Show/hide sections
        document.querySelectorAll('.form-section').forEach(section => {
          section.classList.remove('active');
        });
        document.querySelector(`[data-section="${currentStep}"]`).classList.add('active');

        // Update buttons
        document.getElementById('prev-btn').style.display = currentStep === 1 ? 'none' : 'inline-flex';
        document.getElementById('next-btn').style.display = currentStep === totalSteps ? 'none' : 'inline-flex';
        document.getElementById('submit-btn').style.display = currentStep === totalSteps ? 'inline-flex' : 'none';

        // Update review section
        if (currentStep === 4) {
          updateReview();
        }
      }

      function nextStep() {
        if (validateCurrentStep()) {
          if (currentStep < totalSteps) {
            currentStep++;
            updateStepIndicator();
          }
        }
      }

      function previousStep() {
        if (currentStep > 1) {
          currentStep--;
          updateStepIndicator();
        }
      }

      function validateCurrentStep() {
        const section = document.querySelector(`[data-section="${currentStep}"]`);
        const inputs = section.querySelectorAll('input[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
          if (input.type === 'checkbox') {
            // For checkboxes, check if at least one is checked
            const checkboxGroup = section.querySelectorAll('input[type="checkbox"]');
            const anyChecked = Array.from(checkboxGroup).some(cb => cb.checked);
            if (!anyChecked && currentStep === 2) {
              alert('Please select at least one working day');
              isValid = false;
            }
          } else if (!input.value) {
            input.focus();
            alert('Please fill in all required fields');
            isValid = false;
            return false;
          }
        });

        return isValid;
      }

      function updateReview() {
        const dentistSelect = document.getElementById('dentist_id');
        const dentistName = dentistSelect.options[dentistSelect.selectedIndex].text;

        // Handle duration type
        const durationType = document.querySelector('input[name="duration_type"]:checked').value;
        let monthRange = '';

        if (durationType === 'single') {
          const startMonth = document.getElementById('start_month').value;
          if (startMonth) {
            const date = new Date(startMonth + '-01');
            monthRange = date.toLocaleDateString('en-US', {
              month: 'long',
              year: 'numeric'
            });
          }
        } else {
          const startMonth = document.getElementById('end_month_start').value;
          const endMonth = document.getElementById('end_month').value;
          if (startMonth && endMonth) {
            const startDate = new Date(startMonth + '-01');
            const endDate = new Date(endMonth + '-01');
            monthRange = startDate.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
              }) +
              ' to ' +
              endDate.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
              });
          }
        }

        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        const breakStart = document.getElementById('break_start').value;
        const breakEnd = document.getElementById('break_end').value;
        const status = document.getElementById('status').value;

        const selectedDays = Array.from(document.querySelectorAll('input[name="weekdays[]"]:checked'))
          .map(cb => cb.nextElementSibling.textContent.trim())
          .join(', ');

        const reviewHTML = `
        <div style="display: grid; gap: 16px;">
          <div style="display: flex; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
            <strong style="color: #6b7280;">Dentist:</strong>
            <span style="color: #111827;">${dentistName}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
            <strong style="color: #6b7280;">Schedule Period:</strong>
            <span style="color: #111827;">${monthRange}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
            <strong style="color: #6b7280;">Working Days:</strong>
            <span style="color: #111827;">${selectedDays}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
            <strong style="color: #6b7280;">Working Hours:</strong>
            <span style="color: #111827;">${formatTime(startTime)} - ${formatTime(endTime)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
            <strong style="color: #6b7280;">Break Time:</strong>
            <span style="color: #111827;">${formatTime(breakStart)} - ${formatTime(breakEnd)}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <strong style="color: #6b7280;">Status:</strong>
            <span style="color: #111827; text-transform: capitalize;">${status.replace('_', ' ')}</span>
          </div>
        </div>
      `;

        document.getElementById('review-content').innerHTML = reviewHTML;
      }

      function formatTime(time) {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${minutes} ${ampm}`;
      }

      // Initialize
      updateStepIndicator();

      <?php if ($selected_dentist): ?>
        // Calendar initialization
        document.addEventListener('DOMContentLoaded', function() {
          const calendarEl = document.getElementById('calendar');
          const appointmentList = document.getElementById('appointment-list');
          const events = <?= json_encode($availability) ?>;

          const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 650,
            headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek'
            },
            events: events.map(ev => ({
              title: ev.title,
              start: ev.start,
              className: ev.type === 'availability' ? 'availability' : 'appointment-event',
              extendedProps: {
                time: ev.time,
                status: ev.status,
                type: ev.type
              }
            })),
            dateClick: function(info) {
              const selectedDate = info.dateStr;
              const dayEvents = events.filter(e => e.start === selectedDate);

              const dateObj = new Date(selectedDate);
              const formattedDate = dateObj.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
              });

              appointmentList.innerHTML = `
            <h3><i class="fas fa-calendar-day"></i> ${formattedDate}</h3>
          `;

              if (dayEvents.length > 0) {
                dayEvents.forEach(ev => {
                  const div = document.createElement('div');
                  div.classList.add('appointment');
                  if (ev.type === 'appointment') {
                    div.classList.add('appointment-event');
                  } else {
                    div.classList.add('availability');
                  }

                  div.innerHTML = `
                <h4><i class="fas fa-${ev.type === 'appointment' ? 'user' : 'clock'}"></i> ${ev.title}</h4>
                <p><i class="fas fa-clock"></i> Time: ${ev.time}</p>
                ${ev.status ? '<p><i class="fas fa-info-circle"></i> Status: '+ ev.status +'</p>' : ''}
              `;
                  appointmentList.appendChild(div);
                });
              } else {
                appointmentList.innerHTML += '<p style="color: #6b7280; font-size: 13px; margin-top: 16px;"><i class="fas fa-info-circle"></i> No appointments or availability scheduled for this date.</p>';
              }
            }
          });

          calendar.render();
        });
      <?php endif; ?>
    </script>
  </div>
</body>

</html>

<?php $conn->close(); ?>
<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'dentist') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$dentist_id = $_SESSION['user_id'];

// Dentist-specific stats (total, pending, completed, cancelled)
$tot_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE dentist_id = ?");
$tot_stmt->bind_param("i", $dentist_id);
$tot_stmt->execute();
$tot_result = $tot_stmt->get_result()->fetch_assoc();
$total_appointments = $tot_result['total'] ?? 0;
$tot_stmt->close();

$pending_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE dentist_id = ? AND status = 'pending'");
$pending_stmt->bind_param("i", $dentist_id);
$pending_stmt->execute();
$pending = $pending_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$pending_stmt->close();

$completed_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE dentist_id = ? AND status = 'completed'");
$completed_stmt->bind_param("i", $dentist_id);
$completed_stmt->execute();
$completed = $completed_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$completed_stmt->close();

$cancelled_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE dentist_id = ? AND status = 'cancelled'");
$cancelled_stmt->bind_param("i", $dentist_id);
$cancelled_stmt->execute();
$cancelled = $cancelled_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$cancelled_stmt->close();

$stats = [
  'total_appointments' => $total_appointments,
  'pending' => $pending,
  'completed' => $completed,
  'cancelled' => $cancelled
];

// Fetch schedules for this dentist
$schedule_query = "
  SELECT 
    da.id,
    da.dentist_id,
    da.date,
    da.start_time,
    da.end_time,
    da.status,
    da.notes
  FROM dentist_availability da
  WHERE da.dentist_id = ?
  AND da.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  ORDER BY da.date ASC, da.start_time ASC
";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$schedules_result = $stmt->get_result();

// Organize schedules by date
$schedules_by_date = [];
while ($schedule = $schedules_result->fetch_assoc()) {
  $date = $schedule['date'];
  if (!isset($schedules_by_date[$date])) {
    $schedules_by_date[$date] = [];
  }
  $schedules_by_date[$date][] = $schedule;
}

// Fetch recent and upcoming appointments for calendar display
$appt_query = "
  SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes, p.name as patient_name
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  WHERE a.dentist_id = ?
  AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  ORDER BY a.appointment_date ASC, a.appointment_time ASC
";
$appt_stmt = $conn->prepare($appt_query);
$appt_stmt->bind_param("i", $dentist_id);
$appt_stmt->execute();
$appts_result = $appt_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Schedule - Dentist</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
  <style>
    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 40px;
      border-radius: var(--radius);
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
      border-radius: var(--radius);
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

    /* Calendar Enhancements */
    #calendar {
      background: var(--card);
      border-radius: var(--radius);
      padding: 20px;
      box-shadow: var(--shadow);
    }

    .fc {
      font-family: 'Poppins', sans-serif;
    }

    .fc-toolbar-title {
      font-size: 1.5em !important;
      font-weight: 600;
      color: var(--primary);
    }

    .fc-button {
      background: var(--primary) !important;
      border: 1px solid var(--primary) !important;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .fc-button:hover {
      background: var(--secondary) !important;
      border-color: var(--secondary) !important;
    }

    .fc-button-active {
      background: var(--accent) !important;
      border-color: var(--accent) !important;
    }

    .fc-day-today {
      background-color: var(--primary-light) !important;
    }

    .fc-col-header-cell {
      background: var(--primary) !important;
      color: white !important;
      font-weight: 600;
    }

    .fc-col-header-cell-cushion {
      color: white !important;
    }

    /* Event Styles */
    .fc-event {
      cursor: pointer;
      border: none;
      border-radius: 4px;
      padding: 2px 4px;
      font-size: 12px;
      font-weight: 500;
    }

    .status-available {
      background-color: #10b981 !important;
      border-color: #10b981 !important;
    }

    .status-off {
      background-color: #ef4444 !important;
      border-color: #ef4444 !important;
    }

    .status-half_day {
      background-color: #f59e0b !important;
      border-color: #f59e0b !important;
    }

    /* Appointment status styles */
    .status-appt-pending {
      background-color: #f59e0b !important;
      border-color: #f59e0b !important;
    }

    .status-appt-approved {
      background-color: #3b82f6 !important;
      border-color: #3b82f6 !important;
    }

    .status-appt-completed {
      background-color: #10b981 !important;
      border-color: #10b981 !important;
    }

    .status-appt-cancelled {
      background-color: #ef4444 !important;
      border-color: #ef4444 !important;
    }

    /* Legend */
    .legend {
      background: var(--card);
      padding: 20px;
      border-radius: var(--radius);
      margin-bottom: 20px;
      display: flex;
      gap: 30px;
      flex-wrap: wrap;
      box-shadow: var(--shadow);
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .legend-color {
      width: 16px;
      height: 16px;
      border-radius: 4px;
    }

    .legend-text {
      font-size: 14px;
      color: var(--muted);
      font-weight: 500;
    }

    /* View Toggles */
    .view-toggles {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }

    .view-toggle {
      padding: 10px 20px;
      background: #f3f4f6;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .view-toggle.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    .view-toggle:hover {
      background: #e5e7eb;
    }

    .view-toggle.active:hover {
      background: var(--secondary);
    }

    /* Timetable Views */
    .timetable-view {
      display: none;
    }

    .timetable-view.active {
      display: block;
    }

    .week-timetable,
    .day-timetable {
      background: var(--card);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
    }

    .timetable-header {
      background: var(--primary);
      color: white;
      padding: 12px;
      text-align: center;
      font-weight: 600;
    }

    .timetable-cell {
      background: white;
      padding: 12px;
      min-height: 60px;
      border-bottom: 1px solid var(--border);
    }

    .time-slot {
      background: var(--primary-light);
      font-weight: 500;
      text-align: center;
      color: var(--primary);
    }

    /* Quick Actions */
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }

    .action-card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 28px 24px;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      transition: all 0.3s ease;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      border: 2px solid transparent;
    }

    .action-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary);
    }

    .action-icon {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      margin-bottom: 16px;
      font-size: 26px;
    }

    .action-title {
      font-weight: 700;
      margin-bottom: 6px;
      font-size: 16px;
      color: var(--primary);
    }

    .action-desc {
      font-size: 13px;
      color: var(--muted);
      line-height: 1.5;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .offset {
        margin-left: 0;
      }
    }

    @media (max-width: 768px) {
      body {
        padding: 16px;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }

      .quick-actions {
        grid-template-columns: 1fr;
      }

      .legend {
        flex-direction: column;
        gap: 15px;
      }

      .view-toggles {
        flex-wrap: wrap;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">My Appointments</h1>
        <p class="page-subtitle">Manage and track all your dental appointments</p>
      </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-content">
          <h3>Total</h3>
          <div class="number"><?= $stats['total_appointments'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-timeline"></i></div>
        <div class="stat-content">
          <h3>Pending</h3>
          <div class="number"><?= $stats['pending'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
          <h3>Completed</h3>
          <div class="number"><?= $stats['completed'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div class="stat-content">
          <h3>Cancelled</h3>
          <div class="number"><?= $stats['cancelled'] ?></div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="appointments.php" class="action-card">
        <div class="action-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="action-title">Manage Appointments</div>
        <div class="action-desc">View all appointments</div>
      </a>
      <a href="file_leave.php" class="action-card">
        <div class="action-icon"><i class="fa-solid fa-door-open"></i></div>
        <div class="action-title">Request Leave</div>
        <div class="action-desc">File time off requests</div>
      </a>
    </div>

    <!-- Legend -->
    <div class="legend">
      <div class="legend-item">
        <div class="legend-color" style="background: #10b981;"></div>
        <span class="legend-text">Available</span>
      </div>
      <div class="legend-item">
        <div class="legend-color" style="background: #ef4444;"></div>
        <span class="legend-text">Cancelled</span>
      </div>
      <div class="legend-item">
        <div class="legend-color" style="background: #f59e0b;"></div>
        <span class="legend-text">Pending</span>
      </div>
      <div class="legend-item">
        <div class="legend-color" style="background: #3b82f6;"></div>
        <span class="legend-text">Appointment</span>
      </div>
    </div>

    <!-- View Toggles -->
    <div class="view-toggles">
      <div class="view-toggle active" data-view="calendar">Calendar View</div>
    </div>

    <!-- Calendar View -->
    <div id="calendar-view" class="timetable-view active">
      <div id="calendar"></div>
    </div>

    <!-- Week View -->
    <div id="week-view" class="timetable-view">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Weekly Schedule</h3>
          <div class="card-actions">
            <button class="btn-sm btn-primary" id="prev-week">Previous Week</button>
            <button class="btn-sm btn-primary" id="next-week">Next Week</button>
          </div>
        </div>
        <div id="week-timetable" class="week-timetable">
          <!-- Week timetable will show actual database appointments -->
        </div>
      </div>
    </div>

    <!-- Day View -->
    <div id="day-view" class="timetable-view">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Daily Schedule</h3>
          <div class="card-actions">
            <button class="btn-sm btn-primary" id="prev-day">Previous Day</button>
            <span id="current-day"><?= date('F j, Y') ?></span>
            <button class="btn-sm btn-primary" id="next-day">Next Day</button>
          </div>
        </div>
        <div id="day-timetable" class="day-timetable">
          <!-- Day timetable will show actual database appointments -->
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // View Toggle Functionality
        const viewToggles = document.querySelectorAll('.view-toggle');
        const timetableViews = document.querySelectorAll('.timetable-view');

        viewToggles.forEach(toggle => {
          toggle.addEventListener('click', function() {
            const view = this.getAttribute('data-view');

            // Update active toggle
            viewToggles.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Show corresponding view
            timetableViews.forEach(view => view.classList.remove('active'));
            document.getElementById(`${view}-view`).classList.add('active');
          });
        });

        // FullCalendar Initialization
        var calendarEl = document.getElementById('calendar');
        var events = [
          <?php foreach ($schedules_by_date as $date => $schedules): ?>
            <?php foreach ($schedules as $schedule): ?> {
                id: '<?= $schedule['id'] ?>',
                title: '<?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?>',
                start: '<?= $date ?>',
                className: 'status-<?= $schedule['status'] ?>',
                allDay: true,
                extendedProps: {
                  startTime: '<?= date('g:i A', strtotime($schedule['start_time'])) ?>',
                  endTime: '<?= date('g:i A', strtotime($schedule['end_time'])) ?>',
                  status: '<?= ucfirst($schedule['status']) ?>',
                  notes: '<?= addslashes($schedule['notes'] ?? '') ?>'
                }
              },
            <?php endforeach; ?>
          <?php endforeach; ?>
          <?php if ($appts_result && $appts_result->num_rows > 0): ?>
            <?php while ($appt = $appts_result->fetch_assoc()): ?>
              <?php
              $svc_info = get_appointment_with_services($conn, $appt['id']);
              $service_text = (isset($svc_info['services']) && !empty($svc_info['services']))
                ? implode(', ', array_map(function ($s) {
                  return $s['name'];
                }, $svc_info['services']))
                : 'No Service';
              ?> {
                id: 'appt-<?= $appt['id'] ?>',
                title: '<?= addslashes($service_text) ?> — <?= addslashes($appt['patient_name'] ?: 'Walk-in') ?> (<?= date('g:i A', strtotime($appt['appointment_time'])) ?>)',
                start: '<?= $appt['appointment_date'] ?>',
                className: 'status-appt-<?= $appt['status'] ?>',
                allDay: true,
                extendedProps: {
                  time: '<?= date('g:i A', strtotime($appt['appointment_time'])) ?>',
                  status: '<?= ucfirst($appt['status']) ?>',
                  patient: '<?= addslashes($appt['patient_name'] ?: '') ?>',
                  notes: '<?= addslashes($appt['notes'] ?? '') ?>'
                }
              },
            <?php endwhile; ?>
          <?php endif; ?>
        ];

        var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'dayGridMonth',
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
          },
          buttonText: {
            today: 'Today',
            month: 'Month',
            week: 'Week',
            day: 'Day'
          },
          events: events,
          eventClick: function(info) {
            var props = info.event.extendedProps;
            var details =
              'Time: ' + (props.startTime || props.time) + '\n' +
              'Status: ' + props.status + '\n' +
              (props.patient ? 'Patient: ' + props.patient + '\n' : '') +
              (props.notes ? 'Notes: ' + props.notes + '\n' : '');
            alert(details);
          },
          height: 'auto',
          eventDisplay: 'block'
        });

        calendar.render();

        // Week and Day View Navigation
        document.getElementById('prev-week').addEventListener('click', function() {
          alert('Previous week navigation would show actual database appointments');
        });

        document.getElementById('next-week').addEventListener('click', function() {
          alert('Next week navigation would show actual database appointments');
        });

        document.getElementById('prev-day').addEventListener('click', function() {
          alert('Previous day navigation would show actual database appointments');
        });

        document.getElementById('next-day').addEventListener('click', function() {
          alert('Next day navigation would show actual database appointments');
        });
      });
    </script>
  </div>
</body>

</html>

<?php
$stmt->close();
if (isset($appt_stmt)) {
  $appt_stmt->close();
}
$conn->close();
?>
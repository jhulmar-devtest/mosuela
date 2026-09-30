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
$today = date('Y-m-d');

// Get overall statistics
$stats_query = "
  SELECT 
    (SELECT COUNT(*) FROM appointments) as total_appointments,
    (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
    (SELECT COUNT(*) FROM appointments WHERE status = 'approved') as approved_appointments,
    (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as completed_appointments,
    (SELECT COUNT(*) FROM appointments WHERE status = 'cancelled') as cancelled_appointments,
    (SELECT COUNT(*) FROM users WHERE role = 'dentist') as total_dentists,
    (SELECT COUNT(*) FROM users WHERE role = 'patient') as total_patients,
    (SELECT COUNT(*) FROM services WHERE status = 'active') as active_services
";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get today's appointments
$today_query = "
  SELECT 
    a.id,
    a.reference_no,
    a.appointment_time,
    a.appointment_date,
    a.status,
    a.total_duration,
    a.total_price,
    CASE 
      WHEN a.patient_id IS NULL AND a.notes LIKE 'Walk-in:%' THEN TRIM(SUBSTRING(a.notes, 9))
      WHEN a.patient_id IS NULL THEN 'Walk-in Patient'
      ELSE p.name 
    END as patient_name,
    d.name as dentist_name
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  INNER JOIN users d ON a.dentist_id = d.id
  WHERE DATE(a.appointment_date) = ?
  ORDER BY a.appointment_time ASC
";

$today_stmt = $conn->prepare($today_query);
$today_stmt->bind_param("s", $today);
$today_stmt->execute();
$today_result = $today_stmt->get_result();

// Get upcoming appointments for next 7 days

$upcoming_query = "
  SELECT 
    a.id,
    a.reference_no,
    a.appointment_date,
    a.appointment_time,
    a.status,
    a.total_duration,
    a.total_price,
    COALESCE(p.name, SUBSTRING_INDEX(a.notes, ': ', -1)) as patient_name,
    d.name as dentist_name
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  INNER JOIN users d ON a.dentist_id = d.id
  WHERE DATE(a.appointment_date) > ?
  AND DATE(a.appointment_date) <= DATE_ADD(?, INTERVAL 7 DAY)
  ORDER BY a.appointment_time ASC
  LIMIT 10
";

$upcoming_stmt = $conn->prepare($upcoming_query);
$upcoming_stmt->bind_param("ss", $today, $today);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();

// Statistics
$stats_query = "
  SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
  FROM appointments
  WHERE dentist_id = ? AND DATE(appointment_time) >= ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("is", $dentist_id, $today);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dentist Dashboard - Miracle Mosuela</title>
  <style>
    /* Welcome Header */
    .welcome-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 40px;
      border-radius: var(--radius);
      margin-bottom: 32px;
      box-shadow: var(--shadow-lg);
      position: relative;
      overflow: hidden;
    }

    .welcome-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(233, 196, 106, 0.2), transparent);
      border-radius: 50%;
    }

    .welcome-content {
      position: relative;
      z-index: 2;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }

    .welcome-text h1 {
      margin: 0 0 8px 0;
      font-size: 32px;
      font-weight: 800;
    }

    .welcome-text p {
      margin: 0;
      opacity: 0.95;
      font-size: 16px;
    }

    .welcome-date {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 12px 24px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .welcome-date i {
      font-size: 18px;
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

    /* ========== Layout ========== */
    .dashboard-container {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 24px;
    }

    /* ========== Dentist List ========== */
    .dentist-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .dentist-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px;
      border-radius: var(--radius);
      background: #f9fafb;
      border-left: 4px solid var(--primary);
    }

    .dentist-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
    }

    .dentist-info {
      flex: 1;
    }

    .dentist-name {
      font-weight: 600;
      margin-bottom: 4px;
    }

    .dentist-email {
      font-size: 12px;
      color: var(--muted);
    }

    .dentist-date {
      font-size: 12px;
      color: var(--muted);
    }

    /* ========== Patient List ========== */
    .patient-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .patient-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 16px;
      border-radius: var(--radius);
      background: #f9fafb;
      border-left: 4px solid var(--primary);
      box-shadow: var(--shadow);
    }

    .patient-info {
      display: flex;
      flex-direction: column;
    }

    .patient-time {
      font-weight: 600;
      font-size: 14px;
      color: #111827;
      margin-bottom: 4px;
    }

    .patient-name-service {
      font-size: 13px;
      color: var(--muted);
    }

    .status-badge {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
      text-transform: capitalize;
    }

    .status-confirmed {
      background: #d1fae5;
      color: #065f46;
    }

    .status-pending {
      background: #fef3c7;
      color: #92400e;
    }

    .status-completed {
      background: #e0e7ff;
      color: #3730a3;
    }

    .status-cancelled {
      background: #fecaca;
      color: #991b1b;
    }

    .view-all-btn {
      background: var(--primary);
      color: white;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 13px;
      text-decoration: none;
      transition: 0.2s ease;
    }

    .view-all-btn:hover {
      background: #23867d;
    }

    /* Topbar */
    .topbar {
      display: flex;
      height: 80px;
      justify-content: space-between;
      align-items: center;
      padding: 15px 25px;
      background-color: var(--bg);
      box-shadow: 1px 9px 10px 0px rgba(0, 0, 0, 0.08);
      position: sticky;
      top: 0;
      z-index: 100;
      margin: -24px -24px 24px -24px;
    }

    .topbar h2 {
      margin: 0;
      font-size: 22px;
      color: var(--primary);
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }


    .search-box {
      position: relative;
    }

    .search-box input {
      padding: 8px 35px 8px 12px;
      border: 1px solid #ccc;
      border-radius: 20px;
      outline: none;
      font-size: 14px;
    }

    .search-box i {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #666;
    }

    .notification-icon {
      position: relative;
      font-size: 20px;
      color: #333;
      cursor: pointer;
    }

    .notification-icon::after {
      content: '';
      position: absolute;
      top: 4px;
      right: 3px;
      width: 8px;
      height: 8px;
      background: red;
      border-radius: 50%;
      display: inline-block;
    }


    /* ========== Card Layouts ========== */
    .card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 24px;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 12px;
      border-bottom: 1px solid #e5e7eb;
    }

    .card-title {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .cards {
      background: var(--card);
      border-radius: var(--radius);
      /* Use padding for internal spacing */
      padding: 24px;
      box-shadow: var(--shadow);
      margin-bottom: 24px;

      /* --- ADDITIONS FOR SCROLLING --- */
      /* 1. Define the maximum height for the card */
      /* Adjust this value based on your layout needs (e.g., 400px, 30vh, etc.) */
      max-height: 450px;

      /* 2. Enable vertical scrolling when content exceeds max-height */
      overflow-y: auto;

      /* 3. Optional: Hide the horizontal scrollbar */
      overflow-x: hidden;
    }

    /* Optional: Clean up scrollbar appearance for better aesthetics */
    .card::-webkit-scrollbar {
      width: 8px;
    }

    .card::-webkit-scrollbar-thumb {
      background-color: #ccc;
      /* Or a color that matches your theme */
      border-radius: 4px;
    }

    /* ========== Table Styling ========== */
    .table-container {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: #f9fafb;
    }

    th {
      text-align: left;
      padding: 12px 16px;
      font-size: 14px;
      font-weight: 600;
      color: var(--muted);
      border-bottom: 1px solid #e5e7eb;
    }

    td {
      padding: 12px 16px;
      border-bottom: 1px solid #e5e7eb;
      vertical-align: top;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover {
      background: #f9fafb;
    }

    .sub-info {
      font-size: 12px;
      color: var(--muted);
    }

    /* ========== Status Badges ========== */
    .status-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
      text-transform: capitalize;
    }

    .status-confirmed {
      background: #d1fae5;
      color: #065f46;
    }

    .status-pending {
      background: #fef3c7;
      color: #92400e;
    }

    .status-completed {
      background: #e0e7ff;
      color: #3730a3;
    }

    .status-cancelled {
      background: #fecaca;
      color: #991b1b;
    }

    /* ========== Action Button ========== */
    .action-btn {
      background: var(--primary);
      color: #fff;
      padding: 6px 12px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 13px;
      transition: 0.2s;
    }

    .action-btn:hover {
      background: #23867d;
    }

    <?php include 'styles/style.css'; ?>
  </style>
</head>

<body>
  <div class="offset">
    <!-- Welcome Header -->
    <div class="welcome-header">
      <div class="welcome-content">
        <div class="welcome-text">
          <h1>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
          <p>System Overview & Management Panel</p>
        </div>
        <div class="welcome-date">
          <i class="fas fa-calendar"></i>
          <?= date('l, F j, Y') ?>
        </div>
      </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-calendar-week"></i></div>
        <div class="stat-content">
          <h3>Upcoming</h3>
          <div class="number"><?= $stats['total_appointments'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-timeline"></i></div>
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
        <div class="stat-icon"><i class="fas fa-times-circle"></i></i></div>
        <div class="stat-content">
          <h3>Cancelled</h3>
          <div class="number"><?= $stats['cancelled'] ?></div>
        </div>
      </div>
    </div>


    <div class="dashboard-container">
      <div>
        <!-- Quick Actions -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
          </div>
          <div class="quick-actions">
            <a href="patient_records.php" class="action-card">
              <div class="action-icon"><i class="fa-solid fa-users"></i></div>
              <div class="action-title">Patients</div>
              <div class="action-desc">Manage Patients</div>
            </a>
            <a href="schedules.php" class="action-card">
              <div class="action-icon"><i class="fa-solid fa-calendar-days"></i></i></div>
              <div class="action-title">Schedule</div>
              <div class="action-desc">My Schedule</div>
            </a>
            <a href="file_leave.php" class="action-card">
              <div class="action-icon"><i class="fa-solid fa-door-open"></i></div>
              <div class="action-title">Leave</div>
              <div class="action-desc">File Leave</div>
            </a>
            <a href="appointments.php" class="action-card">
              <div class="action-icon"><i class="fa-solid fa-calendar-check"></i></div>
              <div class="action-title">Appointments</div>
              <div class="action-desc">View Appointments</div>
            </a>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">Upcoming Appointments (Next 7 Days)</h2>
            <div class="card-actions">
              <a href="appointments.php" class="btn btn-secondary btn-sm">
                View All Appointments
              </a>
            </div>
          </div>

          <?php if ($upcoming_result->num_rows > 0): ?>
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Patient</th>
                    <th>Dentist</th>
                    <th>Date & Time</th>
                    <th>Duration</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($appt = $upcoming_result->fetch_assoc()): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($appt['reference_no'] ?? 'N/A') ?></strong></td>
                      <td>
                        <strong><?= htmlspecialchars($appt['patient_name']) ?></strong><br>
                        <span class="sub-info">
                          <?php
                          $appt_services = get_appointment_with_services($conn, $appt['id']);
                          if ($appt_services['success'] && !empty($appt_services['services'])) {
                            echo implode(', ', array_map(function ($s) {
                              return htmlspecialchars($s['name']);
                            }, $appt_services['services']));
                          } else {
                            echo '-';
                          }
                          ?>
                        </span>
                      </td>
                      <td>Dr. <?= htmlspecialchars($appt['dentist_name']) ?></td>
                      <td>
                        <?= date('M j, Y', strtotime($appt['appointment_date'])) ?><br>
                        <span class="sub-info"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></span>
                      </td>
                      <td><?= (int)($appt['total_duration'] ?? 0) ?> min</td>
                      <td>₱<?= number_format($appt['total_price'] ?? 0, 2) ?></td>
                      <td>
                        <span class="status-badge status-<?= $appt['status'] ?>">
                          <?= ucfirst($appt['status']) ?>
                        </span>
                      </td>
                      <td>
                        <a href="view_appointment.php?id=<?= $appt['id'] ?>" class="action-btn">View</a>

                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p>No upcoming appointments in the next 7 days.</p>
          <?php endif; ?>
        </div>




      </div>

      <div>
        <!-- Today's Appointments -->
        <div class="dashboard-section">
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">Today's Appointments</h2>
              <div class="card-actions">
                <a href="appointments.php" class="btn btn-secondary btn-sm">View All</a>
              </div>
            </div>

            <?php if ($today_result->num_rows > 0): ?>
              <div class="patient-list">
                <?php while ($appt = $today_result->fetch_assoc()): ?>
                  <div class="patient-item">
                    <div class="patient-info">
                      <div class="patient-time"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></div>
                      <div class="patient-name-service">
                        <strong><?= htmlspecialchars($appt['patient_name']) ?></strong> –
                        <?php
                        $appt_services = get_appointment_with_services($conn, $appt['id']);
                        if ($appt_services['success'] && !empty($appt_services['services'])) {
                          echo implode(', ', array_map(function ($s) {
                            return htmlspecialchars($s['name']);
                          }, $appt_services['services']));
                        } else {
                          echo '-';
                        }
                        ?>
                      </div>
                      <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                        Ref: <?= htmlspecialchars($appt['reference_no'] ?? 'N/A') ?> |
                        <?= (int)($appt['total_duration'] ?? 0) ?> min |
                        ₱<?= number_format($appt['total_price'] ?? 0, 2) ?>
                      </div>
                    </div>

                    <span class="status-badge status-<?= $appt['status'] ?>">
                      <?= ucfirst($appt['status']) ?>
                    </span>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <p>No appointments scheduled for today.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>

<?php
$today_stmt->close();
$upcoming_stmt->close();
$stats_stmt->close();
$conn->close();
?>
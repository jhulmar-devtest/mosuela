<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'secretary') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$today = date('Y-m-d');

// Get statistics
$stats_query = "
  SELECT 
    (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
    (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ?) as today_appointments,
    (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ? AND status = 'pending') as today_pending,
    (SELECT COUNT(*) FROM users WHERE role = 'patient') as total_patients
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("ss", $today, $today);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

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

// Get pending appointments (need action)
$pending_query = "
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
  WHERE a.status = 'pending'
  ORDER BY a.appointment_date ASC, a.appointment_time ASC
  LIMIT 10
";
$pending_result = $conn->query($pending_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Secretary Dashboard - Miracle Mosuela</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Welcome Header */
    .welcome-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 40px;
      border-radius: var(--radius-lg);
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

    /* Quick Actions */
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }

    .action-card {
      background: var(--card);
      border-radius: var(--radius-lg);
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

    /* Card */
    .card {
      background: var(--card);
      border-radius: var(--radius-lg);
      padding: 28px;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 2px solid #f3f4f6;
    }

    .card-title {
      font-size: 20px;
      font-weight: 700;
      margin: 0;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-title i {
      font-size: 22px;
    }

    .card-badge {
      background: #f3f4f6;
      color: var(--muted);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
    }

    /* Alert Banner */
    .alert-banner {
      background: linear-gradient(135deg, #fef3c7, #fde68a);
      border-left: 5px solid #f59e0b;
      padding: 20px;
      border-radius: var(--radius-lg);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: var(--shadow);
    }

    .alert-banner i {
      font-size: 32px;
      color: #92400e;
    }

    .alert-content h3 {
      margin: 0 0 6px 0;
      color: #92400e;
      font-size: 18px;
      font-weight: 700;
    }

    .alert-content p {
      margin: 0;
      color: #78350f;
      font-size: 14px;
    }

    /* Table */
    .table-container {
      overflow-x: auto;
      border-radius: var(--radius-lg);
      border: 1px solid #e5e7eb;
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
      padding: 16px;
      font-size: 13px;
      font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid #e5e7eb;
    }

    td {
      padding: 16px;
      border-bottom: 1px solid #f3f4f6;
      vertical-align: middle;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tbody tr {
      transition: all 0.2s ease;
      cursor: pointer;
    }

    tbody tr:hover {
      background: #f9fafb;
    }

    .patient-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .patient-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 16px;
      flex-shrink: 0;
    }

    /* Status Badges */
    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      text-transform: capitalize;
    }

    .status-pending {
      background: #fef3c7;
      color: #92400e;
    }

    .status-approved {
      background: #d1fae5;
      color: #065f46;
    }

    .status-completed {
      background: #dbeafe;
      color: #1e40af;
    }

    .status-cancelled {
      background: #fee2e2;
      color: #991b1b;
    }

    /* Buttons */
    .btn {
      padding: 8px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.3s ease;
      font-size: 13px;
      border: none;
      cursor: pointer;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      box-shadow: 0 2px 8px rgba(2, 62, 138, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(2, 62, 138, 0.4);
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
      border: 2px solid #e5e7eb;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
      border-color: var(--primary);
    }

    .btn-warning {
      background: #fef3c7;
      color: #92400e;
      border: 2px solid #fde68a;
    }

    .btn-warning:hover {
      background: #fde68a;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
    }

    .empty-state i {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.3;
    }

    .empty-state h3 {
      margin: 0 0 12px 0;
      font-size: 20px;
      color: #374151;
    }

    .empty-state p {
      margin: 0;
      font-size: 15px;
    }

    .success-state {
      background: #d1fae5;
      border-left: 5px solid #10b981;
      padding: 20px;
      border-radius: var(--radius-lg);
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .success-state i {
      font-size: 32px;
      color: #065f46;
    }

    .success-state p {
      margin: 0;
      color: #065f46;
      font-weight: 600;
      font-size: 16px;
    }

    /* Responsive */
    @media (max-width: 968px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .welcome-content {
        flex-direction: column;
        align-items: flex-start;
      }

      .quick-actions {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .welcome-text h1 {
        font-size: 24px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <!-- Welcome Header -->
    <div class="welcome-header">
      <div class="welcome-content">
        <div class="welcome-text">
          <h1>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</h1>
          <p>Ready to manage appointments and assist patients today</p>
        </div>
        <div class="welcome-date">
          <i class="fas fa-calendar"></i>
          <?= date('l, F j, Y') ?>
        </div>
      </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-content">
          <h3>Pending Approvals</h3>
          <div class="number"><?= $stats['pending_appointments'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-content">
          <h3>Today's Appointments</h3>
          <div class="number"><?= $stats['today_appointments'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
          <h3>Today Pending</h3>
          <div class="number"><?= $stats['today_pending'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-content">
          <h3>Total Patients</h3>
          <div class="number"><?= $stats['total_patients'] ?></div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="create_appointment.php" class="action-card">
        <div class="action-icon"><i class="fas fa-calendar-plus"></i></div>
        <div class="action-title">Book Appointment</div>
        <div class="action-desc">Schedule a patient appointment</div>
      </a>
      <a href="appointments.php" class="action-card">
        <div class="action-icon"><i class="fas fa-list-check"></i></div>
        <div class="action-title">All Appointments</div>
        <div class="action-desc">View all scheduled appointments</div>
      </a>
      <a href="schedules.php" class="action-card">
        <div class="action-icon"><i class="fas fa-calendar-days"></i></div>
        <div class="action-title">Schedules</div>
        <div class="action-desc">View and create dentist schedules</div>
      </a>
      <a href="patients.php" class="action-card">
        <div class="action-icon"><i class="fas fa-user-group"></i></div>
        <div class="action-title">Manage Patients</div>
        <div class="action-desc">View and manage patient records</div>
      </a>
    </div>

    <!-- Pending Appointments Alert -->
    <?php if ($stats['pending_appointments'] > 0): ?>
      <div class="alert-banner">
        <i class="fas fa-exclamation-triangle"></i>
        <div class="alert-content">
          <h3>Action Required</h3>
          <p><?= $stats['pending_appointments'] ?> appointment(s) waiting for approval. Please review and approve them.</p>
        </div>
      </div>
    <?php endif; ?>

    <!-- Today's Appointments -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-calendar-check"></i>
          Today's Appointments
        </h3>
        <span class="card-badge">
          <?= $today_result->num_rows ?> appointment(s)
        </span>
      </div>

      <?php if ($today_result->num_rows > 0): ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Reference</th>
                <th>Time</th>
                <th>Patient</th>
                <th>Dentist</th>
                <th>Service</th>
                <th>Duration</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($appt = $today_result->fetch_assoc()): ?>
                <tr onclick="window.location.href='view_appointment.php?id=<?= intval($appt['id']) ?>'">
                  <td><strong><?= htmlspecialchars($appt['reference_no'] ?? 'N/A') ?></strong></td>
                  <td style="font-weight: 600; font-size: 15px;">
                    <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
                  </td>
                  <td>
                    <div class="patient-info">
                      <div class="patient-avatar">
                        <?= strtoupper(substr($appt['patient_name'], 0, 1)) ?>
                      </div>
                      <span style="font-weight: 600;">
                        <?= htmlspecialchars($appt['patient_name']) ?>
                      </span>
                    </div>
                  </td>
                  <td>Dr. <?= htmlspecialchars($appt['dentist_name']) ?></td>
                  <td>
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
                  </td>
                  <td><?= (int)($appt['total_duration'] ?? 0) ?> min</td>
                  <td>₱<?= number_format($appt['total_price'] ?? 0, 2) ?></td>
                  <td>
                    <span class="status-badge status-<?= $appt['status'] ?>">
                      <?= ucfirst($appt['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-calendar-times"></i>
          <h3>No Appointments Today</h3>
          <p>There are no appointments scheduled for today.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Pending Appointments (Need Action) -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-clipboard-list"></i>
          Pending Approvals
        </h3>
        <span class="card-badge">
          <?= $pending_result->num_rows ?> pending
        </span>
      </div>

      <?php if ($pending_result->num_rows > 0): ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Reference</th>
                <th>Date & Time</th>
                <th>Patient</th>
                <th>Dentist</th>
                <th>Service</th>
                <th>Duration</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($appt = $pending_result->fetch_assoc()): ?>
                <tr onclick="window.location.href='edit_appointment.php?id=<?= intval($appt['id']) ?>'">
                  <td><strong><?= htmlspecialchars($appt['reference_no'] ?? 'N/A') ?></strong></td>
                  <td>
                    <div style="font-weight: 600;"><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></div>
                    <div style="font-size: 13px; color: var(--muted);"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></div>
                  </td>
                  <td>
                    <div class="patient-info">
                      <div class="patient-avatar">
                        <?= strtoupper(substr($appt['patient_name'], 0, 1)) ?>
                      </div>
                      <span style="font-weight: 600;">
                        <?= htmlspecialchars($appt['patient_name']) ?>
                      </span>
                    </div>
                  </td>
                  <td>Dr. <?= htmlspecialchars($appt['dentist_name']) ?></td>
                  <td>
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
                  </td>
                  <td><?= (int)($appt['total_duration'] ?? 0) ?> min</td>
                  <td>₱<?= number_format($appt['total_price'] ?? 0, 2) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="success-state">
          <i class="fas fa-check-circle"></i>
          <p>All appointments have been processed! Great work!</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>

<?php
$stats_stmt->close();
$today_stmt->close();
$conn->close();
?>
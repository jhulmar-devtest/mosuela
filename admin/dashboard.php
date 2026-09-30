<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$today = date('Y-m-d');

// Get quick summary statistics
$stats_query = "
  SELECT 
    (SELECT COUNT(*) FROM appointments) as total_appointments,
    (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ?) as appointments_today,
    (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
    (SELECT COUNT(*) FROM users WHERE role = 'patient') as total_patients,
    (SELECT COUNT(*) FROM users WHERE role = 'dentist' AND status = 'active') as total_dentists,
    (SELECT COALESCE(SUM(total_price), 0) FROM appointments WHERE status IN ('approved', 'completed')) as total_revenue
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("s", $today);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Safely handle null values
$total_appointments = $stats['total_appointments'] ?? 0;
$appointments_today = $stats['appointments_today'] ?? 0;
$pending_appointments = $stats['pending_appointments'] ?? 0;
$total_patients = $stats['total_patients'] ?? 0;
$total_dentists = $stats['total_dentists'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0.00;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Miracle Mosuela</title>
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
      background: radial-gradient(circle, rgba(255, 186, 8, 0.2), transparent);
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

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 24px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: var(--card);
      padding: 28px;
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
      width: 70px;
      height: 70px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
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
      font-size: 36px;
      font-weight: 800;
      color: var(--primary);
      line-height: 1;
    }

    .stat-content .revenue {
      color: #059669;
    }

    /* Quick Actions */
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

    @media (max-width: 968px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .welcome-content {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .welcome-text h1 {
        font-size: 24px;
      }

      .quick-actions {
        grid-template-columns: 1fr;
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
          <p>System Overview & Management Panel</p>
        </div>
        <div class="welcome-date">
          <i class="fas fa-calendar"></i>
          <?= date('l, F j, Y') ?>
        </div>
      </div>
    </div>

    <!-- Summary Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-content">
          <h3>Total Appointments</h3>
          <div class="number"><?= number_format($total_appointments) ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-content">
          <h3>Appointments Today</h3>
          <div class="number"><?= number_format($appointments_today) ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-content">
          <h3>Pending Appointments</h3>
          <div class="number"><?= number_format($pending_appointments) ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-content">
          <h3>Total Patients</h3>
          <div class="number"><?= number_format($total_patients) ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-doctor"></i></div>
        <div class="stat-content">
          <h3>Total Dentists</h3>
          <div class="number"><?= number_format($total_dentists) ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-content">
          <h3>Total Revenue</h3>
          <div class="number revenue">₱<?= number_format($total_revenue, 2) ?></div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="appointments.php" class="action-card">
        <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="action-title">Appointments</div>
        <div class="action-desc">View all appointments</div>
      </a>

      <a href="patients.php" class="action-card">
        <div class="action-icon"><i class="fas fa-users"></i></div>
        <div class="action-title">Patients</div>
        <div class="action-desc">Manage patient records</div>
      </a>

      <a href="dentists.php" class="action-card">
        <div class="action-icon"><i class="fas fa-user-doctor"></i></div>
        <div class="action-title">Dentists</div>
        <div class="action-desc">Manage dentist accounts</div>
      </a>

      <a href="services.php" class="action-card">
        <div class="action-icon"><i class="fas fa-tooth"></i></div>
        <div class="action-title">Services</div>
        <div class="action-desc">Manage dental services</div>
      </a>

      <a href="schedules.php" class="action-card">
        <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="action-title">Schedules</div>
        <div class="action-desc">Manage dentist schedules</div>
      </a>

      <a href="reports.php" class="action-card">
        <div class="action-icon"><i class="fas fa-chart-line"></i></div>
        <div class="action-title">Reports</div>
        <div class="action-desc">View detailed analytics</div>
      </a>
    </div>
  </div>
</body>

</html>

<?php
$conn->close();
?>
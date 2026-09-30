<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$today = date('Y-m-d');
$current_month = date('Y-m');

// ===== 1. APPOINTMENTS REPORT =====
$appt_stats_query = "
  SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN DATE(appointment_date) = ? THEN 1 ELSE 0 END) as today
  FROM appointments
";
$appt_stats_stmt = $conn->prepare($appt_stats_query);
$appt_stats_stmt->bind_param("s", $today);
$appt_stats_stmt->execute();
$appt_stats = $appt_stats_stmt->get_result()->fetch_assoc();
$appt_stats_stmt->close();

// Appointments per month (last 6 months)
$appt_monthly_query = "
  SELECT 
    DATE_FORMAT(appointment_date, '%Y-%m') as month,
    COUNT(*) as count
  FROM appointments
  WHERE appointment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
  GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
  ORDER BY month ASC
";
$appt_monthly_result = $conn->query($appt_monthly_query);
$appt_monthly_data = [];
while ($row = $appt_monthly_result->fetch_assoc()) {
  $appt_monthly_data[] = $row;
}

// ===== 2. PATIENTS REPORT =====
$patient_stats_query = "
  SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_this_month,
    (SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE patient_id IS NOT NULL) as with_appointments,
    COUNT(*) - (SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE patient_id IS NOT NULL) as without_appointments
  FROM users WHERE role = 'patient'
";
$patient_stats = $conn->query($patient_stats_query)->fetch_assoc();

// New patients per month (last 6 months)
$patient_monthly_query = "
  SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
  FROM users
  WHERE role = 'patient' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
  ORDER BY month ASC
";
$patient_monthly_result = $conn->query($patient_monthly_query);
$patient_monthly_data = [];
while ($row = $patient_monthly_result->fetch_assoc()) {
  $patient_monthly_data[] = $row;
}

// ===== 3. DENTISTS REPORT =====
$dentist_stats_query = "
  SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
  FROM users WHERE role = 'dentist'
";
$dentist_stats = $conn->query($dentist_stats_query)->fetch_assoc();

// Appointments per dentist
$dentist_appt_query = "
  SELECT 
    u.name as dentist_name,
    COUNT(a.id) as appointment_count
  FROM users u
  LEFT JOIN appointments a ON u.id = a.dentist_id
  WHERE u.role = 'dentist'
  GROUP BY u.id, u.name
  ORDER BY appointment_count DESC
";
$dentist_appt_result = $conn->query($dentist_appt_query);
$dentist_appt_data = [];
while ($row = $dentist_appt_result->fetch_assoc()) {
  $dentist_appt_data[] = $row;
}

// ===== 4. FINANCIAL REPORT =====
$revenue_query = "
  SELECT 
    COALESCE(SUM(total_price), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN DATE(appointment_date) = ? THEN total_price ELSE 0 END), 0) as revenue_today,
    COALESCE(SUM(CASE WHEN DATE_FORMAT(appointment_date, '%Y-%m') = ? THEN total_price ELSE 0 END), 0) as revenue_this_month,
    COALESCE(AVG(total_price), 0) as avg_appointment_value,
    COUNT(*) as revenue_appointments
  FROM appointments
  WHERE status IN ('approved', 'completed')
";
$revenue_stmt = $conn->prepare($revenue_query);
$revenue_stmt->bind_param("ss", $today, $current_month);
$revenue_stmt->execute();
$revenue_stats = $revenue_stmt->get_result()->fetch_assoc();
$revenue_stmt->close();

// Revenue per month (last 6 months)
$revenue_monthly_query = "
  SELECT 
    DATE_FORMAT(appointment_date, '%Y-%m') as month,
    COALESCE(SUM(total_price), 0) as revenue
  FROM appointments
  WHERE status IN ('approved', 'completed')
    AND appointment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
  GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
  ORDER BY month ASC
";
$revenue_monthly_result = $conn->query($revenue_monthly_query);
$revenue_monthly_data = [];
while ($row = $revenue_monthly_result->fetch_assoc()) {
  $revenue_monthly_data[] = $row;
}

// ===== 5. USER LOGIN HISTORY =====
$login_history_query = "
  SELECT 
    lh.id,
    lh.login_time,
    lh.ip_address,
    u.name as user_name,
    u.role as user_role
  FROM user_login_history lh
  INNER JOIN users u ON lh.user_id = u.id
  ORDER BY lh.login_time DESC
  LIMIT 50
";
$login_history_result = $conn->query($login_history_query);
$login_history_data = [];
while ($row = $login_history_result->fetch_assoc()) {
  $login_history_data[] = $row;
}

// Safe defaults for all stats
$appt_stats['total'] = $appt_stats['total'] ?? 0;
$appt_stats['pending'] = $appt_stats['pending'] ?? 0;
$appt_stats['approved'] = $appt_stats['approved'] ?? 0;
$appt_stats['completed'] = $appt_stats['completed'] ?? 0;
$appt_stats['cancelled'] = $appt_stats['cancelled'] ?? 0;
$appt_stats['today'] = $appt_stats['today'] ?? 0;

$patient_stats['total'] = $patient_stats['total'] ?? 0;
$patient_stats['new_this_month'] = $patient_stats['new_this_month'] ?? 0;
$patient_stats['with_appointments'] = $patient_stats['with_appointments'] ?? 0;
$patient_stats['without_appointments'] = $patient_stats['without_appointments'] ?? 0;

$dentist_stats['total'] = $dentist_stats['total'] ?? 0;
$dentist_stats['active'] = $dentist_stats['active'] ?? 0;

$revenue_stats['total_revenue'] = $revenue_stats['total_revenue'] ?? 0.00;
$revenue_stats['revenue_today'] = $revenue_stats['revenue_today'] ?? 0.00;
$revenue_stats['revenue_this_month'] = $revenue_stats['revenue_this_month'] ?? 0.00;
$revenue_stats['avg_appointment_value'] = $revenue_stats['avg_appointment_value'] ?? 0.00;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports & Analytics - Miracle Mosuela</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 40px;
      border-radius: var(--radius-lg);
      margin-bottom: 32px;
      box-shadow: var(--shadow-lg);
    }

    .page-header h1 {
      margin: 0 0 8px 0;
      font-size: 32px;
      font-weight: 800;
    }

    .page-header p {
      margin: 0;
      opacity: 0.95;
      font-size: 16px;
    }

    .section {
      margin-bottom: 40px;
    }

    .section-title {
      font-size: 24px;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .section-title i {
      font-size: 28px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: var(--card);
      padding: 24px;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      border-left: 4px solid var(--primary);
    }

    .stat-card h3 {
      font-size: 13px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin: 0 0 8px 0;
      font-weight: 600;
    }

    .stat-card .number {
      font-size: 32px;
      font-weight: 800;
      color: var(--primary);
    }

    .chart-container {
      background: var(--card);
      padding: 24px;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .chart-container h3 {
      margin: 0 0 20px 0;
      font-size: 18px;
      font-weight: 700;
      color: var(--dark);
    }

    .chart-wrapper {
      position: relative;
      height: 300px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow);
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

    tbody tr:hover {
      background: #f9fafb;
    }

    .role-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: capitalize;
    }

    .role-admin {
      background: #fee2e2;
      color: #991b1b;
    }

    .role-dentist {
      background: #dbeafe;
      color: #1e40af;
    }

    .role-secretary {
      background: #e0f2fe;
      color: #0369a1;
    }

    .role-patient {
      background: #d1fae5;
      color: #065f46;
    }

    @media (max-width: 968px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .chart-wrapper {
        height: 250px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="page-header">
      <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
      <p>Comprehensive system performance and analytics dashboard</p>
    </div>

    <!-- 1. APPOINTMENTS REPORT -->
    <div class="section">
      <h2 class="section-title"><i class="fas fa-calendar-check"></i> Appointments Report</h2>
      
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Appointments</h3>
          <div class="number"><?= number_format($appt_stats['total']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Pending</h3>
          <div class="number"><?= number_format($appt_stats['pending']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Approved</h3>
          <div class="number"><?= number_format($appt_stats['approved']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Completed</h3>
          <div class="number"><?= number_format($appt_stats['completed']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Cancelled</h3>
          <div class="number"><?= number_format($appt_stats['cancelled']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Today</h3>
          <div class="number"><?= number_format($appt_stats['today']) ?></div>
        </div>
      </div>

      <div class="chart-container">
        <h3>Appointments by Status</h3>
        <div class="chart-wrapper">
          <canvas id="appointmentStatusChart"></canvas>
        </div>
      </div>

      <div class="chart-container">
        <h3>Appointments per Month (Last 6 Months)</h3>
        <div class="chart-wrapper">
          <canvas id="appointmentMonthlyChart"></canvas>
        </div>
      </div>
    </div>

    <!-- 2. PATIENTS REPORT -->
    <div class="section">
      <h2 class="section-title"><i class="fas fa-users"></i> Patients Report</h2>
      
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Patients</h3>
          <div class="number"><?= number_format($patient_stats['total']) ?></div>
        </div>
        <div class="stat-card">
          <h3>New This Month</h3>
          <div class="number"><?= number_format($patient_stats['new_this_month']) ?></div>
        </div>
        <div class="stat-card">
          <h3>With Appointments</h3>
          <div class="number"><?= number_format($patient_stats['with_appointments']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Without Appointments</h3>
          <div class="number"><?= number_format($patient_stats['without_appointments']) ?></div>
        </div>
      </div>

      <div class="chart-container">
        <h3>New Patients per Month (Last 6 Months)</h3>
        <div class="chart-wrapper">
          <canvas id="patientMonthlyChart"></canvas>
        </div>
      </div>
    </div>

    <!-- 3. DENTISTS REPORT -->
    <div class="section">
      <h2 class="section-title"><i class="fas fa-user-doctor"></i> Dentists Report</h2>
      
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Dentists</h3>
          <div class="number"><?= number_format($dentist_stats['total']) ?></div>
        </div>
        <div class="stat-card">
          <h3>Active Dentists</h3>
          <div class="number"><?= number_format($dentist_stats['active']) ?></div>
        </div>
      </div>

      <div class="chart-container">
        <h3>Appointments per Dentist</h3>
        <div class="chart-wrapper">
          <canvas id="dentistAppointmentChart"></canvas>
        </div>
      </div>

      <div class="chart-container">
        <h3>Dentist Performance Summary</h3>
        <table>
          <thead>
            <tr>
              <th>Dentist Name</th>
              <th>Total Appointments</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($dentist_appt_data) > 0): ?>
              <?php foreach ($dentist_appt_data as $dentist): ?>
                <tr>
                  <td><strong>Dr. <?= htmlspecialchars($dentist['dentist_name']) ?></strong></td>
                  <td><?= number_format($dentist['appointment_count']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="2" style="text-align: center; color: var(--muted);">No data available</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 4. FINANCIAL REPORT -->
    <div class="section">
      <h2 class="section-title"><i class="fas fa-peso-sign"></i> Financial Report</h2>
      
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Total Revenue</h3>
          <div class="number" style="color: #059669;">₱<?= number_format($revenue_stats['total_revenue'], 2) ?></div>
        </div>
        <div class="stat-card">
          <h3>Revenue Today</h3>
          <div class="number" style="color: #059669;">₱<?= number_format($revenue_stats['revenue_today'], 2) ?></div>
        </div>
        <div class="stat-card">
          <h3>Revenue This Month</h3>
          <div class="number" style="color: #059669;">₱<?= number_format($revenue_stats['revenue_this_month'], 2) ?></div>
        </div>
        <div class="stat-card">
          <h3>Avg Appointment Value</h3>
          <div class="number" style="color: #059669;">₱<?= number_format($revenue_stats['avg_appointment_value'], 2) ?></div>
        </div>
      </div>

      <div class="chart-container">
        <h3>Revenue per Month (Last 6 Months)</h3>
        <div class="chart-wrapper">
          <canvas id="revenueMonthlyChart"></canvas>
        </div>
      </div>
    </div>

    <!-- 5. SYSTEM & AUDIT LOGS -->
    <div class="section">
      <h2 class="section-title"><i class="fas fa-clipboard-list"></i> System & Audit Logs</h2>
      
      <div class="chart-container">
        <h3>Recent User Login History (Last 50)</h3>
        <table>
          <thead>
            <tr>
              <th>User Name</th>
              <th>Role</th>
              <th>Login Time</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($login_history_data) > 0): ?>
              <?php foreach ($login_history_data as $log): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($log['user_name']) ?></strong></td>
                  <td><span class="role-badge role-<?= $log['user_role'] ?>"><?= htmlspecialchars($log['user_role']) ?></span></td>
                  <td><?= date('M j, Y g:i A', strtotime($log['login_time'])) ?></td>
                  <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" style="text-align: center; color: var(--muted);">No login history available</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    // Appointment Status Chart (Donut)
    new Chart(document.getElementById('appointmentStatusChart'), {
      type: 'doughnut',
      data: {
        labels: ['Pending', 'Approved', 'Completed', 'Cancelled'],
        datasets: [{
          data: [
            <?= $appt_stats['pending'] ?>,
            <?= $appt_stats['approved'] ?>,
            <?= $appt_stats['completed'] ?>,
            <?= $appt_stats['cancelled'] ?>
          ],
          backgroundColor: ['#fbbf24', '#3b82f6', '#10b981', '#ef4444']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });

    // Appointments per Month (Bar)
    new Chart(document.getElementById('appointmentMonthlyChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($appt_monthly_data, 'month')) ?>,
        datasets: [{
          label: 'Appointments',
          data: <?= json_encode(array_column($appt_monthly_data, 'count')) ?>,
          backgroundColor: '#d00000'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

    // New Patients per Month (Line)
    new Chart(document.getElementById('patientMonthlyChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode(array_column($patient_monthly_data, 'month')) ?>,
        datasets: [{
          label: 'New Patients',
          data: <?= json_encode(array_column($patient_monthly_data, 'count')) ?>,
          borderColor: '#d00000',
          backgroundColor: 'rgba(208, 0, 0, 0.1)',
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

    // Appointments per Dentist (Bar)
    new Chart(document.getElementById('dentistAppointmentChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($dentist_appt_data, 'dentist_name')) ?>,
        datasets: [{
          label: 'Appointments',
          data: <?= json_encode(array_column($dentist_appt_data, 'appointment_count')) ?>,
          backgroundColor: '#d00000'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { beginAtZero: true }
        }
      }
    });

    // Revenue per Month (Line)
    new Chart(document.getElementById('revenueMonthlyChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode(array_column($revenue_monthly_data, 'month')) ?>,
        datasets: [{
          label: 'Revenue (₱)',
          data: <?= json_encode(array_column($revenue_monthly_data, 'revenue')) ?>,
          borderColor: '#059669',
          backgroundColor: 'rgba(5, 150, 105, 0.1)',
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { 
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return '₱' + value.toLocaleString();
              }
            }
          }
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: function(context) {
                return 'Revenue: ₱' + context.parsed.y.toLocaleString();
              }
            }
          }
        }
      }
    });
  </script>
</body>

</html>

<?php
$conn->close();
?>

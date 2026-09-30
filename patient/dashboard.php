<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'patient') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$patient_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get statistics
$stats_query = "
  SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN appointment_date >= ? THEN 1 ELSE 0 END) as upcoming
  FROM appointments
  WHERE patient_id = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("si", $today, $patient_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get next appointment
$next_query = "
  SELECT a.id, a.reference_no, a.appointment_date, a.appointment_time, a.status, a.notes, a.total_duration, a.total_price, d.name as dentist_name
  FROM appointments a
  INNER JOIN users d ON a.dentist_id = d.id
  WHERE a.patient_id = ? AND a.appointment_date >= ? AND a.status != 'cancelled'
  ORDER BY a.appointment_date ASC, a.appointment_time ASC
  LIMIT 1
";
$next_stmt = $conn->prepare($next_query);
$next_stmt->bind_param("is", $patient_id, $today);
$next_stmt->execute();
$next_appointment = $next_stmt->get_result()->fetch_assoc();

// Get appointment history
$history_query = "
  SELECT a.id, a.reference_no, a.appointment_date, a.appointment_time, a.status, a.notes, a.total_duration, a.total_price, d.name as dentist_name
  FROM appointments a
  INNER JOIN users d ON a.dentist_id = d.id
  WHERE a.patient_id = ?
  ORDER BY a.appointment_date DESC, a.appointment_time DESC
  LIMIT 5
";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $patient_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Dashboard - Miracle Mosuela</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    /* Dashboard Layout */
    .dashboard-container {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 32px;
    }

    /* Cards */
    .card {
      background: var(--card);
      border-radius: var(--radius);
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

    /* Next Appointment */
    .next-appt {
      background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
      border-radius: var(--radius);
      padding: 28px;
      border-left: 5px solid var(--primary);
      position: relative;
      overflow: hidden;
    }

    .next-appt::before {
      content: '';
      position: absolute;
      top: -50px;
      right: -50px;
      width: 150px;
      height: 150px;
      background: radial-gradient(circle, rgba(208, 0, 0, 0.05), transparent);
      border-radius: 50%;
    }

    .appt-date {
      font-size: 24px;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .appt-date i {
      font-size: 28px;
    }

    .appt-time {
      font-size: 18px;
      font-weight: 600;
      color: var(--muted);
      margin-top: 8px;
    }

    .appt-details {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin: 24px 0;
    }

    .appt-detail {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .detail-label {
      font-size: 12px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }

    .detail-value {
      font-weight: 700;
      font-size: 15px;
      color: #1f2937;
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
      padding: 12px 24px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      font-size: 14px;
      border: none;
      cursor: pointer;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      box-shadow: 0 4px 12px rgba(2, 62, 138, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(2, 62, 138, 0.4);
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

    .btn-sm {
      padding: 8px 16px;
      font-size: 13px;
    }

    /* Table */
    .table-container {
      overflow-x: auto;
      border-radius: var(--radius);
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
      transition: background 0.2s ease;
    }

    tbody tr:hover {
      background: #f9fafb;
    }

    .sub-info {
      font-size: 12px;
      color: var(--muted);
      margin-top: 2px;
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
      margin: 0 0 24px 0;
      font-size: 15px;
    }

    /* Dental Tips */
    .tip-item {
      display: flex;
      gap: 16px;
      align-items: flex-start;
      padding: 16px;
      background: #f9fafb;
      border-radius: 10px;
      margin-bottom: 12px;
      transition: all 0.3s ease;
    }

    .tip-item:hover {
      background: #f3f4f6;
      transform: translateX(4px);
    }

    .tip-number {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 700;
      flex-shrink: 0;
    }

    .tip-content h4 {
      margin: 0 0 6px 0;
      font-weight: 700;
      font-size: 15px;
      color: #1f2937;
    }

    .tip-content p {
      margin: 0;
      font-size: 13px;
      color: var(--muted);
      line-height: 1.6;
    }

    /* Quick Links */
    .quick-link {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px;
      background: #f9fafb;
      border-radius: 10px;
      text-decoration: none;
      color: inherit;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .quick-link:hover {
      background: #f3f4f6;
      border-color: var(--primary);
      transform: translateX(4px);
    }

    .quick-link i {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
    }

    .quick-link span {
      font-weight: 600;
      font-size: 15px;
    }

    /* Scrollable Card */
    .scrollable-card {
      max-height: 600px;
      overflow-y: auto;
    }

    .scrollable-card::-webkit-scrollbar {
      width: 6px;
    }

    .scrollable-card::-webkit-scrollbar-thumb {
      background: #d1d5db;
      border-radius: 3px;
    }

    .scrollable-card::-webkit-scrollbar-thumb:hover {
      background: #9ca3af;
    }

    /* Responsive */
    @media (max-width: 968px) {
      .dashboard-container {
        grid-template-columns: 1fr;
      }

      .welcome-content {
        flex-direction: column;
        align-items: flex-start;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .appt-details {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .quick-actions {
        grid-template-columns: 1fr;
      }
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
          <p>Here's your dental health overview and upcoming appointments</p>
        </div>
        <div class="welcome-date">
          <i class="fas fa-calendar"></i>
          <?= date('l, F j, Y') ?>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-content">
          <h3>Total Appointments</h3>
          <div class="number"><?= $stats['total_appointments'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
          <h3>Upcoming</h3>
          <div class="number"><?= $stats['upcoming'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
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
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="book_appointment.php" class="action-card">
        <div class="action-icon"><i class="fas fa-calendar-plus"></i></div>
        <div class="action-title">Book Appointment</div>
        <div class="action-desc">Schedule your next dental visit</div>
      </a>
      <a href="appointments.php" class="action-card">
        <div class="action-icon"><i class="fas fa-list-check"></i></div>
        <div class="action-title">My Appointments</div>
        <div class="action-desc">View and manage all appointments</div>
      </a>
      <a href="records.php" class="action-card">
        <div class="action-icon"><i class="fas fa-file-medical"></i></div>
        <div class="action-title">Medical Records</div>
        <div class="action-desc">Access your dental history</div>
      </a>
      <a href="settings.php" class="action-card">
        <div class="action-icon"><i class="fas fa-cog"></i></div>
        <div class="action-title">Settings</div>
        <div class="action-desc">Update your profile and preferences</div>
      </a>
    </div>

    <div class="dashboard-container">
      <div>
        <!-- Next Appointment -->
        <div class="card">
          <div class="card-header">
            <div>
              <h2 class="card-title">
                <i class="fas fa-calendar-star"></i>
                Next Appointment
              </h2>
              <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Ref: <strong><?= htmlspecialchars($next_appointment['reference_no'] ?? 'N/A') ?></strong></div>
            </div>
          </div>
          <?php if ($next_appointment): ?>
            <div class="next-appt">
              <div class="appt-date">
                <i class="fas fa-calendar-day"></i>
                <?= date('l, F j, Y', strtotime($next_appointment['appointment_date'])) ?>
              </div>
              <div class="appt-time">
                <i class="fas fa-clock"></i>
                <?= date('g:i A', strtotime($next_appointment['appointment_time'])) ?>
              </div>
              <div class="appt-details">
                <div class="appt-detail">
                  <span class="detail-label">Services</span>
                  <span class="detail-value">
                    <?php
                    $appt_services = get_appointment_with_services($conn, $next_appointment['id']);
                    if ($appt_services['success'] && !empty($appt_services['services'])) {
                      echo implode(', ', array_map(function ($s) {
                        return htmlspecialchars($s['name']);
                      }, $appt_services['services']));
                    } else {
                      echo '-';
                    }
                    ?>
                  </span>
                </div>
                <div class="appt-detail">
                  <span class="detail-label">Dentist</span>
                  <span class="detail-value">Dr. <?= htmlspecialchars($next_appointment['dentist_name']) ?></span>
                </div>
                <div class="appt-detail">
                  <span class="detail-label">Duration</span>
                  <span class="detail-value"><?= (int)($next_appointment['total_duration'] ?? 0) ?> minutes</span>
                </div>
                <div class="appt-detail">
                  <span class="detail-label">Total Price</span>
                  <span class="detail-value">₱<?= number_format($next_appointment['total_price'] ?? 0, 2) ?></span>
                </div>
                <div class="appt-detail">
                  <span class="detail-label">Status</span>
                  <span class="status-badge status-<?= $next_appointment['status'] ?>">
                    <?= ucfirst($next_appointment['status']) ?>
                  </span>
                </div>
              </div>
              <a href="view_appointment.php?id=<?= $next_appointment['id'] ?>" class="btn btn-primary">
                <i class="fas fa-eye"></i> View Full Details
              </a>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-calendar-times"></i>
              <h3>No Upcoming Appointments</h3>
              <p>You don't have any scheduled appointments at the moment.</p>
              <a href="book_appointment.php" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Book Your Next Visit
              </a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Appointment History -->
        <div class="card scrollable-card">
          <div class="card-header">
            <h2 class="card-title">
              <i class="fas fa-history"></i>
              Recent Appointments
            </h2>
            <a href="appointments.php" class="btn btn-sm btn-primary">View All</a>
          </div>
          <?php if ($history_result->num_rows > 0): ?>
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Date & Time</th>
                    <th>Service</th>
                    <th>Dentist</th>
                    <th>Duration</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($appt = $history_result->fetch_assoc()): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($appt['reference_no'] ?? 'N/A') ?></strong></td>
                      <td>
                        <div style="font-weight: 600;"><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></div>
                        <div class="sub-info"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></div>
                      </td>
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
                      <td>Dr. <?= htmlspecialchars($appt['dentist_name']) ?></td>
                      <td><?= (int)($appt['total_duration'] ?? 0) ?> min</td>
                      <td>₱<?= number_format($appt['total_price'] ?? 0, 2) ?></td>
                      <td>
                        <span class="status-badge status-<?= $appt['status'] ?>">
                          <?= ucfirst($appt['status']) ?>
                        </span>
                      </td>
                      <td>
                        <a href="view_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-sm btn-secondary">
                          <i class="fas fa-eye"></i> View
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-history"></i>
              <h3>No Appointment History</h3>
              <p>You haven't had any appointments yet.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <!-- Quick Links -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">
              <i class="fas fa-link"></i>
              Quick Links
            </h2>
          </div>
          <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="settings.php" class="quick-link">
              <i class="fas fa-user"></i>
              <span>My Profile</span>
            </a>
            <a href="records.php" class="quick-link">
              <i class="fas fa-file-medical"></i>
              <span>Treatment History</span>
            </a>
            <a href="appointments.php" class="quick-link">
              <i class="fas fa-calendar-check"></i>
              <span>All Appointments</span>
            </a>
          </div>
        </div>

        <!-- Dental Tips -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">
              <i class="fas fa-lightbulb"></i>
              Dental Health Tips
            </h2>
          </div>
          <div>
            <div class="tip-item">
              <div class="tip-number">1</div>
              <div class="tip-content">
                <h4>Brush Twice Daily</h4>
                <p>Use fluoride toothpaste and brush for at least 2 minutes each time for optimal oral hygiene.</p>
              </div>
            </div>
            <div class="tip-item">
              <div class="tip-number">2</div>
              <div class="tip-content">
                <h4>Floss Regularly</h4>
                <p>Clean between your teeth daily to remove plaque and prevent gum disease.</p>
              </div>
            </div>
            <div class="tip-item">
              <div class="tip-number">3</div>
              <div class="tip-content">
                <h4>Regular Checkups</h4>
                <p>Visit your dentist every 6 months for professional cleaning and early detection of issues.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>

<?php
$stats_stmt->close();
$next_stmt->close();
$history_stmt->close();
$conn->close();
?>
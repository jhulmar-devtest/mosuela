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

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get patient appointments with services
$appointments_result = get_patient_appointments_with_services($conn, $patient_id);

// Filter and search
$result_data = [];
if ($appointments_result['success']) {
  foreach ($appointments_result['appointments'] as $appt) {
    // Apply status filter
    if ($status_filter !== 'all' && $appt['status'] !== $status_filter) {
      continue;
    }
    // Apply search filter
    if (!empty($search_query)) {
      $search_lower = strtolower($search_query);
      $service_match = false;
      $dentist_match = false;
      $reference_match = false;

      foreach ($appt['services'] as $svc) {
        if (strpos(strtolower($svc['name']), $search_lower) !== false) {
          $service_match = true;
          break;
        }
      }
      if (strpos(strtolower($appt['dentist_name']), $search_lower) !== false) {
        $dentist_match = true;
      }
      if (isset($appt['reference_no']) && strpos(strtolower($appt['reference_no']), $search_lower) !== false) {
        $reference_match = true;
      }

      if (!($service_match || $dentist_match || $reference_match)) {
        continue;
      }
    }
    $result_data[] = $appt;
  }
}

// Sort by date descending
usort($result_data, function ($a, $b) {
  $cmp = strtotime($b['appointment_date']) - strtotime($a['appointment_date']);
  if ($cmp === 0) {
    return strtotime($b['appointment_time']) - strtotime($a['appointment_time']);
  }
  return $cmp;
});

// Get appointment statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE patient_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $patient_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Appointments - Miracle Mosuela</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
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
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }

    .page-header-text h1 {
      font-size: 32px;
      font-weight: 800;
      margin: 0 0 8px 0;
    }

    .page-header-text p {
      font-size: 16px;
      opacity: 0.95;
      margin: 0;
    }

    .btn-book {
      background: white;
      color: var(--primary);
      padding: 14px 28px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      font-size: 15px;
    }

    .btn-book:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 25px rgba(0, 0, 0, 0.3);
      background: var(--secondary);
    }

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

    .card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 28px;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .filter-tabs {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
      background: #f9fafb;
      padding: 8px;
      border-radius: var(--radius);
      flex-wrap: wrap;
    }

    .filter-tab {
      padding: 12px 20px;
      background: transparent;
      color: var(--muted);
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .filter-tab:hover {
      background: white;
      color: var(--primary);
    }

    .filter-tab.active {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      box-shadow: 0 4px 12px rgba(42, 157, 143, 0.3);
    }

    .search-container {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 24px;
    }

    .search-form {
      display: flex;
      gap: 12px;
      flex: 1;
      min-width: 300px;
    }

    .search-input {
      flex: 1;
      padding: 12px 16px;
      border: 2px solid #e5e7eb;
      border-radius: 10px;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .search-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(42, 157, 143, 0.1);
    }

    .btn {
      padding: 12px 24px;
      border-radius: 10px;
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
      box-shadow: 0 4px 12px rgba(42, 157, 143, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(42, 157, 143, 0.4);
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

    .appointment-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .service-name {
      font-weight: 600;
      color: #1f2937;
    }

    .dentist-name {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .dentist-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 14px;
      flex-shrink: 0;
    }

    .sub-info {
      font-size: 13px;
      color: var(--muted);
    }

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

    .btn-sm {
      padding: 8px 14px;
      font-size: 13px;
    }

    .btn-view {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
    }

    .btn-cancel {
      background: #fee2e2;
      color: #991b1b;
      border: 2px solid #fca5a5;
    }

    .btn-cancel:hover {
      background: #fecaca;
    }

    .empty-state {
      text-align: center;
      padding: 80px 20px;
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

    @media (max-width: 968px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .page-header-content {
        flex-direction: column;
        align-items: flex-start;
      }

      .filter-tabs {
        flex-direction: column;
      }

      .search-form {
        flex-direction: column;
        min-width: auto;
      }

      .table-container {
        overflow-x: scroll;
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .page-header-text h1 {
        font-size: 24px;
      }

      .btn-book {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="page-header">
      <div class="page-header-content">
        <div class="page-header-text">
          <h1>My Appointments</h1>
          <p>Manage and track all your dental appointments</p>
        </div>
        <a href="book_appointment.php" class="btn-book">
          <i class="fas fa-calendar-plus"></i>
          Book New Appointment
        </a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content">
          <h3>Total</h3>
          <div class="number"><?= $stats['total'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3>Pending</h3>
          <div class="number"><?= $stats['pending'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
          <h3>Approved</h3>
          <div class="number"><?= $stats['approved'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-content">
          <h3>Completed</h3>
          <div class="number"><?= $stats['completed'] ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="filter-tabs">
        <a href="?status=all" class="filter-tab <?= $status_filter === 'all' ? 'active' : '' ?>">
          <i class="fas fa-list"></i> All
        </a>
        <a href="?status=pending" class="filter-tab <?= $status_filter === 'pending' ? 'active' : '' ?>">
          <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?status=approved" class="filter-tab <?= $status_filter === 'approved' ? 'active' : '' ?>">
          <i class="fas fa-check"></i> Approved
        </a>
        <a href="?status=completed" class="filter-tab <?= $status_filter === 'completed' ? 'active' : '' ?>">
          <i class="fas fa-check-double"></i> Completed
        </a>
        <a href="?status=cancelled" class="filter-tab <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
          <i class="fas fa-times"></i> Cancelled
        </a>
      </div>

      <div class="search-container">
        <form method="GET" class="search-form">
          <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
          <input type="text" name="search" class="search-input"
            placeholder="Search by reference, service or dentist..."
            value="<?= htmlspecialchars($search_query) ?>">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i> Search
          </button>
          <?php if ($search_query || $status_filter !== 'all'): ?>
            <a href="appointments.php" class="btn btn-secondary">
              <i class="fas fa-refresh"></i> Reset
            </a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card">
      <?php if (!empty($result_data)): ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Reference</th>
                <th>Date & Time</th>
                <th>Services & Dentist</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($result_data as $row): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($row['reference_no'] ?? 'N/A') ?></strong></td>
                  <td>
                    <div style="font-weight: 600;"><?= date('M j, Y', strtotime($row['appointment_date'])) ?></div>
                    <div class="sub-info"><?= date("g:i A", strtotime($row['appointment_time'])) ?></div>
                  </td>
                  <td>
                    <div class="appointment-info">
                      <div style="font-size: 12px; color: var(--muted); margin-bottom: 6px;">
                        <?php foreach ($row['services'] as $svc): ?>
                          <div>• <?= htmlspecialchars($svc['name']) ?> (₱<?= number_format($svc['price'], 2) ?>, <?= $svc['duration_minutes'] ?>m)</div>
                        <?php endforeach; ?>
                      </div>
                      <div class="dentist-name">
                        <div class="dentist-avatar">
                          <?= strtoupper(substr($row['dentist_name'], 0, 1)) ?>
                        </div>
                        <span class="sub-info">Dr. <?= htmlspecialchars($row['dentist_name']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 600;"><?= $row['total_duration'] ?? 0 ?> min</div>
                    <div class="sub-info">₱<?= number_format($row['total_price'] ?? 0, 2) ?></div>
                  </td>
                  <td>
                    <span class="status-badge status-<?= $row['status'] ?>">
                      <?= ucfirst($row['status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="view_appointment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-view">
                      <i class="fas fa-eye"></i> View
                    </a>

                    <?php if (in_array($row['status'], ['pending', 'approved'])): ?>
                      <a href="cancel_appointment.php?id=<?= $row['id'] ?>"
                        class="btn btn-sm btn-cancel"
                        onclick="return confirm('Are you sure you want to cancel this appointment?');">
                        <i class="fas fa-times"></i> Cancel
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-calendar-times"></i>
          <h3>No Appointments Found</h3>
          <p>
            <?php if ($status_filter !== 'all' || !empty($search_query)): ?>
              Try adjusting your filters or search terms.
            <?php else: ?>
              You have no appointments scheduled yet.
            <?php endif; ?>
          </p>
          <a href="book_appointment.php" class="btn btn-primary">
            <i class="fas fa-calendar-plus"></i> Book Your First Appointment
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>

<?php
$stats_stmt->close();
$conn->close();
?>
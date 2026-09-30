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
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_clause = "WHERE a.dentist_id = ?";

switch ($filter) {
  case 'upcoming':
    $where_clause .= " AND DATE(a.appointment_date) >= CURDATE()";
    break;
  case 'past':
    $where_clause .= " AND DATE(a.appointment_date) < CURDATE()";
    break;
  case 'all':
  default:
    break;
}

if ($search !== '') {
  $where_clause .= " AND (p.name LIKE ? OR a.notes LIKE ? OR a.reference_no LIKE ?)";
}

$query = "
  SELECT 
    a.id, 
    a.patient_id, 
    a.reference_no,
    a.appointment_date, 
    a.appointment_time, 
    a.status, 
    a.notes,
    a.total_price,
    a.total_duration,
    CASE 
      WHEN a.patient_id IS NULL AND a.notes LIKE 'Walk-in:%' THEN TRIM(SUBSTRING(a.notes, 9))
      WHEN a.patient_id IS NULL THEN 'Walk-in Patient'
      ELSE p.name 
    END as patient_name
  FROM appointments a
  LEFT JOIN users p ON a.patient_id = p.id
  $where_clause
  ORDER BY 
    CASE WHEN DATE(a.appointment_date) >= CURDATE() THEN 0 ELSE 1 END,
    a.appointment_date ASC, 
    a.appointment_time ASC
";

$stmt = $conn->prepare($query);

if ($search !== '') {
  $param = "%{$search}%";
  $stmt->bind_param("isss", $dentist_id, $param, $param, $param);
} else {
  $stmt->bind_param("i", $dentist_id);
}

$stmt->execute();
$result = $stmt->get_result();

// Build results with services for each appointment
$result_data = [];
while ($row = $result->fetch_assoc()) {
  $appt_with_services = get_appointment_with_services($conn, $row['id']);
  if ($appt_with_services['success']) {
    $row['services'] = $appt_with_services['services'];
    $row['total_price'] = $appt_with_services['total_price'];
    $row['total_duration'] = $appt_with_services['total_duration'];
    $result_data[] = $row;
  }
}

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE dentist_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $dentist_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Appointments - Dentist</title>
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

    .search-form {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .search-input {
      flex: 1;
      min-width: 300px;
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

    .filter-tabs {
      display: flex;
      gap: 12px;
      margin-bottom: 32px;
      background: var(--card);
      padding: 8px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    .filter-tab {
      flex: 1;
      padding: 14px 20px;
      background: transparent;
      color: var(--muted);
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border: 2px solid transparent;
    }

    .filter-tab:hover {
      background: #f9fafb;
      color: var(--primary);
    }

    .filter-tab.active {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      box-shadow: 0 4px 12px rgba(42, 157, 143, 0.3);
    }

    .card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 28px;
      box-shadow: var(--shadow);
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

    .card-badge {
      background: #f3f4f6;
      color: var(--muted);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
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

    .patient-details strong {
      display: block;
      color: #1f2937;
      margin-bottom: 2px;
    }

    .walkin-tag {
      background: #dbeafe;
      color: #1e40af;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      margin-left: 8px;
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
      box-shadow: 0 2px 8px rgba(42, 157, 143, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(42, 157, 143, 0.4);
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
      margin: 0;
      font-size: 15px;
    }

    @media (max-width: 968px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .filter-tabs {
        flex-direction: column;
      }

      .table-container {
        overflow-x: scroll;
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .page-header {
        padding: 30px 20px;
      }

      .page-title {
        font-size: 24px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">My Appointments</h1>
        <p class="page-subtitle">Manage and track all your dental appointments</p>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-content">
          <h3>Total</h3>
          <div class="number"><?= $stats['total'] ?></div>
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

    <form method="GET" class="search-form">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="text" name="search" class="search-input"
        placeholder="Search patients or reference number..."
        value="<?= htmlspecialchars($search) ?>">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-search"></i> Search
      </button>
      <?php if ($search): ?>
        <a href="?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-secondary">
          <i class="fas fa-times"></i> Clear
        </a>
      <?php endif; ?>
    </form>

    <div class="filter-tabs">
      <a href="?filter=upcoming&search=<?= urlencode($search) ?>" class="filter-tab <?= $filter === 'upcoming' ? 'active' : '' ?>">
        <i class="fas fa-calendar-day"></i> Upcoming
      </a>
      <a href="?filter=past&search=<?= urlencode($search) ?>" class="filter-tab <?= $filter === 'past' ? 'active' : '' ?>">
        <i class="fas fa-history"></i> Past
      </a>
      <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> All Appointments
      </a>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-calendar-check"></i>
          <?= ucfirst($filter) ?> Appointments
        </h3>
        <span class="card-badge">
          <?= count($result_data) ?> appointment(s)
        </span>
      </div>

      <?php if (!empty($result_data)): ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Reference</th>
                <th>Date & Time</th>
                <th>Patient</th>
                <th>Services</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($result_data as $appt): ?>
                <?php $is_walkin = strpos($appt['notes'] ?? '', 'Walk-in:') === 0; ?>
                <tr>
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
                      <div class="patient-details">
                        <strong><?= htmlspecialchars($appt['patient_name']) ?></strong>
                        <?php if ($is_walkin): ?>
                          <span class="walkin-tag">Walk-in</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size: 12px;">
                      <?php foreach ($appt['services'] as $svc): ?>
                        <div>• <?= htmlspecialchars($svc['name']) ?></div>
                      <?php endforeach; ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 600;"><?= $appt['total_duration'] ?> min</div>
                    <div style="font-size: 12px; color: var(--muted);">₱<?= number_format($appt['total_price'], 2) ?></div>
                  </td>
                  <td>
                    <span class="status-badge status-<?= $appt['status'] ?>">
                      <?= ucfirst($appt['status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="view_appointment.php?id=<?= $appt['id'] ?>" class="btn btn-primary">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <?php if ($appt['status'] !== 'completed' && $appt['status'] !== 'cancelled'): ?>
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
          <h3>No <?= $filter ?> Appointments</h3>
          <p>
            <?php if ($search): ?>
              No appointments found matching "<?= htmlspecialchars($search) ?>". Try different search terms.
            <?php else: ?>
              You don't have any <?= $filter ?> appointments at the moment.
            <?php endif; ?>
          </p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>

<?php
$stmt->close();
$stats_stmt->close();
$conn->close();
?>
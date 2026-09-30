<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'secretary') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get patient statistics
$stats_query = "SELECT 
    COUNT(*) as total_patients,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_patients,
    COUNT(CASE WHEN phone IS NOT NULL AND phone != '' THEN 1 END) as with_phone,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_patients
    FROM users WHERE role='patient'";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Build query with search
$query = "SELECT id, name, email, phone, created_at FROM users WHERE role='patient'";
if ($search !== '') {
  $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
}
$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if ($search !== '') {
  $search_param = "%{$search}%";
  $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Patients - Miracle Mosuela</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    /* Search Card */
    .search-card {
      background: var(--card);
      padding: 24px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .search-form {
      display: flex;
      gap: 12px;
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
      box-shadow: 0 0 0 4px rgba(208, 0, 0, 0.1);
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
      box-shadow: 0 4px 12px rgba(208, 0, 0, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(208, 0, 0, 0.4);
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

    /* Card */
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
      font-weight: 600;
    }

    .sub-info {
      font-size: 13px;
      color: var(--muted);
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

    .status-active {
      background: #d1fae5;
      color: #065f46;
    }

    .status-new {
      background: #dbeafe;
      color: #1e40af;
    }

    .status-inactive {
      background: #fef3c7;
      color: #92400e;
    }

    /* Action Buttons */
    .btn-sm {
      padding: 8px 14px;
      font-size: 13px;
    }

    .btn-view {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
    }

    .btn-appointment {
      background: #d1fae5;
      color: #065f46;
      border: 2px solid #6ee7b7;
    }

    .btn-appointment:hover {
      background: #a7f3d0;
    }

    /* Empty State */
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

    /* Responsive */
    @media (max-width: 968px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .search-form {
        flex-direction: column;
      }

      .search-input {
        min-width: auto;
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .page-title {
        font-size: 24px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">Manage Patients</h1>
        <p class="page-subtitle">View and manage all registered patients</p>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
          <h3>Total Patients</h3>
          <div class="number"><?= $stats['total_patients'] ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-user-plus"></i>
        </div>
        <div class="stat-content">
          <h3>New This Month</h3>
          <div class="number"><?= $stats['new_patients'] ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-phone"></i>
        </div>
        <div class="stat-content">
          <h3>With Contact</h3>
          <div class="number"><?= $stats['with_phone'] ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-calendar-week"></i>
        </div>
        <div class="stat-content">
          <h3>New This Week</h3>
          <div class="number"><?= $stats['recent_patients'] ?></div>
        </div>
      </div>
    </div>

    <!-- Search Card -->
    <div class="search-card">
      <form method="GET" class="search-form">
        <input type="text"
          name="search"
          class="search-input"
          placeholder="Search patients by name, email, or phone..."
          value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-search"></i>
          Search
        </button>
        <?php if ($search): ?>
          <a href="patients.php" class="btn btn-secondary">
            <i class="fas fa-times"></i>
            Clear
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Patients Table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-user-group"></i>
          Patient List
        </h3>
        <span class="card-badge">
          <?= $result->num_rows ?> patient(s)
        </span>
      </div>

      <?php if ($result->num_rows > 0): ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Patient</th>
                <th>Contact Information</th>
                <th>Registration Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                $isNew = strtotime($row['created_at']) >= strtotime('-7 days');
                $hasPhone = !empty($row['phone']);
                ?>
                <tr>
                  <td>
                    <div class="patient-info">
                      <div class="patient-avatar">
                        <?= strtoupper(substr($row['name'], 0, 1)) ?>
                      </div>
                      <div class="patient-details">
                        <strong><?= htmlspecialchars($row['name']) ?></strong>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="margin-bottom: 4px;">
                      <i class="fas fa-envelope" style="color: var(--muted); margin-right: 8px;"></i>
                      <?= htmlspecialchars($row['email']) ?>
                    </div>
                    <?php if ($hasPhone): ?>
                      <div>
                        <i class="fas fa-phone" style="color: var(--muted); margin-right: 8px;"></i>
                        <?= htmlspecialchars($row['phone']) ?>
                      </div>
                    <?php else: ?>
                      <div class="sub-info">No phone number</div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="font-weight: 600;"><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                    <?php if ($isNew): ?>
                      <span class="sub-info">New patient</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($isNew): ?>
                      <span class="status-badge status-new">New</span>
                    <?php elseif ($hasPhone): ?>
                      <span class="status-badge status-active">Active</span>
                    <?php else: ?>
                      <span class="status-badge status-inactive">Incomplete</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="view_patient.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-view">
                      <i class="fas fa-eye"></i> View
                    </a>
                    <a href="create_appointment.php?patient_id=<?= $row['id'] ?>" class="btn btn-sm btn-appointment">
                      <i class="fas fa-calendar-plus"></i> Book
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-user-slash"></i>
          <h3>No Patients Found</h3>
          <p>
            <?php if ($search): ?>
              No patients found matching "<?= htmlspecialchars($search) ?>".
            <?php else: ?>
              There are no registered patients in the system yet.
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
$stats_result->close();
$conn->close();
?>
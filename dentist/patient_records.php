<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'dentist') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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

// Get patient statistics
$stats_query = "SELECT 
    COUNT(*) as total_patients,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_patients,
    COUNT(CASE WHEN phone IS NOT NULL AND phone != '' THEN 1 END) as with_phone,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_patients
    FROM users WHERE role='patient'";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Records - Dentist</title>
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

    /* Search Bar */
    .search-container {
      background: var(--card);
      padding: 20px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .search-form {
      display: flex;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
    }

    .search-input {
      flex: 1;
      min-width: 300px;
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .search-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    .btn {
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      padding: 12px 24px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn:hover {
      background: #23867d;
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
    }

    /* Table Styling - Applied Exact Design */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
      background: var(--card);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
    }

    th,
    td {
      padding: 12px 16px;
      border-bottom: 1px solid #f1f5f9;
      text-align: left;
      font-size: 14px;
    }

    th {
      background: #f8fafc;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      font-size: 12px;
      letter-spacing: 0.5px;
    }

    tr:hover {
      background: #f9fafb;
    }

    tr:last-child td {
      border-bottom: none;
    }

    /* Action Buttons */
    .action-btn {
      background: var(--primary);
      color: #fff;
      padding: 6px 12px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 12px;
      font-weight: 500;
      transition: 0.2s;
      display: inline-block;
      margin-right: 5px;
    }

    .action-btn:hover {
      background: #23867d;
    }

    .action-btn-secondary {
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .action-btn-secondary:hover {
      background: #e5e7eb;
    }

    /* Status Badges */
    .status-badge {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
      text-transform: capitalize;
    }

    .status-active {
      background: #d1fae5;
      color: #065f46;
    }

    .status-inactive {
      background: #fef3c7;
      color: #92400e;
    }

    .status-new {
      background: #dbeafe;
      color: #1e40af;
    }

    /* Patient Profile */
    .patient-profile {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .patient-name {
      font-weight: 600;
      color: var(--primary);
    }

    .sub-info {
      font-size: 12px;
      color: var(--muted);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--muted);
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: 16px;
      color: #d1d5db;
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

      .search-form {
        flex-direction: column;
        align-items: stretch;
      }

      .search-input {
        min-width: auto;
      }

      table {
        display: block;
        overflow-x: auto;
      }

      .page-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
      }
    }
  </style>
</head>

<body class="offset">
  <div class="offset">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">Patient Records</h1>
        <p class="page-subtitle">Manage and review patient records</p>
      </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-content">
          <h3>Total Patients</h3>
          <div class="number"><?= $stats['total_patients'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
        <div class="stat-content">
          <h3>New This Month</h3>
          <div class="number"><?= $stats['new_patients'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-phone"></i></div>
        <div class="stat-content">
          <h3>With Phone</h3>
          <div class="number"><?= $stats['with_phone'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
        <div class="stat-content">
          <h3>Recent (7 days)</h3>
          <div class="number"><?= $stats['recent_patients'] ?></div>
        </div>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="search-container">
      <form method="GET" class="search-form">
        <input type="text" name="search" placeholder="Search patients by name, email, or phone..."
          value="<?= htmlspecialchars($search) ?>" class="search-input">
        <button type="submit" class="btn">
          <i class="fas fa-search"></i> Search
        </button>
        <?php if ($search): ?>
          <a href="patient_records.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Clear
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Patients Table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-list"></i>
          Patient List
        </h3>
        <div class="card-actions">
          <span style="color: var(--muted); font-size: 14px;">
            <?= $result->num_rows ?> patient(s) found
          </span>
        </div>
      </div>

      <?php if ($result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Name</th>
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
                  <div class="patient-profile">
                    <span class="patient-name"><?= htmlspecialchars($row['name']) ?></span>
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
                  <?= date('M j, Y', strtotime($row['created_at'])) ?>
                  <?php if ($isNew): ?>
                    <br><span class="sub-info">New patient</span>
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
                  <a href="view_patient.php?id=<?= $row['id'] ?>" class="action-btn">
                    <i class="fas fa-eye"></i> View
                  </a>
                  <a href="update_appointment.php?id=<?= $row['id'] ?>" class="action-btn action-btn-secondary">
                    <i class="fas fa-edit"></i> Update
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
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
<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Patients - Miracle Mosuela</title>
  <style>
    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 40px;
      border-radius: var(--radius-lg);
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

    /* ---------- Card Styles ---------- */
    .card {
      background: var(--card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 24px;
      margin-bottom: 24px;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 1px solid #e5e7eb;
    }

    .card-title {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
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


    /* ---------- Table Styles ---------- */
    .table-container {
      overflow-x: auto;
      border-radius: var(--radius-lg);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
      min-width: 900px;
      background: white;
    }

    thead {
      background: #f8fafc;
    }

    th,
    td {
      padding: 16px 12px;
      text-align: left;
      font-size: 15px;
      border-bottom: 1px solid #f1f5f9;
    }

    th {
      font-weight: 600;
      color: var(--muted);
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    tbody tr {
      transition: all 0.2s ease;
      cursor: pointer;
    }

    tbody tr:hover {
      background: #f9fafb;
    }

    /* ---------- Buttons ---------- */
    .btn {
      border: none;
      padding: 8px 12px;
      border-radius: var(--radius-lg);
      font-size: 12px;
      cursor: pointer;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s ease;
    }

    .btn-view {
      background: #dbeafe;
      color: #1e40af;
    }

    .btn-edit {
      background: #fef3c7;
      color: #92400e;
    }

    .btn-delete {
      background: #fee2e2;
      color: #991b1b;
    }

    .btn-view:hover {
      background: #bfdbfe;
    }

    .btn-edit:hover {
      background: #fde68a;
    }

    .btn-delete:hover {
      background: #fecaca;
    }

    /* ---------- Search Bar ---------- */
    .search-container {
      margin: 20px 0;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .search-container input {
      flex: 1;
      max-width: 300px;
      padding: 10px 12px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .search-container input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    .search-container button {
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      padding: 10px 20px;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.2s ease;
    }

    .search-container button:hover {
      background: #21867a;
    }

    .search-container a {
      background: #f3f4f6;
      color: #374151;
      padding: 10px 20px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
      border: 1px solid #d1d5db;
      transition: background 0.2s ease;
    }

    .search-container a:hover {
      background: #e5e7eb;
    }

    /* ---------- Patient Table ---------- */
    .patient-profile {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .patient-name {
      font-weight: 600;
    }

    .sub-info {
      font-size: 12px;
      color: var(--muted);
    }

    .btn-appointment {
      background: #d1fae5;
      color: #065f46;
      padding: 6px 12px;
      font-size: 12px;
      border-radius: var(--radius-lg);
      font-weight: 500;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }
  </style>
</head>

<body>
  <div class="offset">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">Patient List</h1>
        <p class="page-subtitle">Manage and track all registered patients</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Patient List</h2>
      </div>

      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Age</th>
              <th>Email</th>
              <th>Dentist</th>
              <th>Last Visit</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr onclick="window.location.href='view_patient.php?id=<?= intval($row['id']) ?>'">
                  <td>
                    <div class="patient-profile">
                      <span class="patient-name"><?= htmlspecialchars($row['name']) ?></span>
                      <span class="sub-info"><?= htmlspecialchars($row['phone'] ?: 'N/A') ?></span>
                    </div>
                  </td>
                  <td><?= isset($row['age']) ? $row['age'] . ' yrs' : 'N/A' ?></td>
                  <td><?= htmlspecialchars($row['email']) ?></td>
                  <td><?= htmlspecialchars($row['dentist_name'] ?? 'N/A') ?></td>
                  <td>
                    <span><?= isset($row['last_visit']) ? date('M j, Y', strtotime($row['last_visit'])) : 'N/A' ?></span><br>
                    <small class="sub-info"><?= htmlspecialchars($row['last_service'] ?? 'N/A') ?></small>
                  </td>
                  <td><span class="btn-appointment">Active</span></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6">No patients found<?= $search ? ' matching your search' : '' ?>.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
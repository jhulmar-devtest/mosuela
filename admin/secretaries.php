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
$query = "SELECT id, name, email, phone, created_at FROM users WHERE role='secretary'";
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
    :root {
      --bg: #f8f9fa;
      --card: #ffffff;
      --muted: #6b7280;
      --radius-lg: 12px;
      --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    body {
      background: var(--bg);
      margin: 0;
      padding: 20px;
      padding-left: 300px;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

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
    .secretary-profile {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .secretary-name {
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
        <div class="page-header-text">
          <h1 class="page-title">Manage Secretaries</h1>
          <p class="page-subtitle">View, add, edit, or remove secretary accounts</p>
        </div>
        <a href="create_secretary.php" class="btn-book">
          <i class="fas fa-user-plus"></i>
          Create New Secretary
        </a>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Secretary List</h2>
      </div>

      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Age</th>
              <th>Email</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>
                    <div class="secretary-profile">
                      <span class="secretary-name"><?= htmlspecialchars($row['name']) ?></span>
                      <span class="sub-info"><?= htmlspecialchars($row['phone'] ?: 'N/A') ?></span>
                    </div>
                  </td>
                  <td><?= isset($row['age']) ? $row['age'] . ' yrs' : 'N/A' ?></td>
                  <td><?= htmlspecialchars($row['email']) ?></td>
                  <td><span class="btn-appointment">Active</span></td>
                  <td>
                    <a href="view_secretary.php?id=<?= $row['id'] ?>" class="btn btn-view">View</a>
                  </td>
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
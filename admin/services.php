<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $price = trim($_POST['price']);
  $duration = trim($_POST['duration']);

  try {
    $stmt = $conn->prepare("INSERT INTO services (name, description, price, duration_minutes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdi", $name, $description, $price, $duration);

    if ($stmt->execute()) {
      echo "<script>alert('Service created successfully!'); window.location.href='services.php';</script>";
    }
  } catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) { // Duplicate entry
      echo "<script>alert('Service name already exists.');</script>";
    } else {
      echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
  }

  $stmt->close();
}
// Make search query available to the page
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// If a search query is present, fetch matching services (name/description); otherwise fetch all
if ($q !== '') {
  $like = "%" . $q . "%";
  $sstmt = $conn->prepare("SELECT id, name, description, price, duration_minutes, status FROM services WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC LIMIT 200");
  $sstmt->bind_param('ss', $like, $like);
  $sstmt->execute();
  $services_result = $sstmt->get_result();
  $sstmt->close();
} else {
  $services_result = $conn->query("SELECT id, name, description, price, duration_minutes, status FROM services ORDER BY name ASC");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Services - Miracle Mosuela</title>
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

    /* Card */
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

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
      background: var(--card);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }

    th,
    td {
      padding: 12px 10px;
      border-bottom: 1px solid #f1f5f9;
      text-align: left;
      font-size: 14px;
    }

    /* Ensure the actions column keeps a reserved width so it doesn't disappear
   or shift when the modal opens (Chrome/Windows scrollbar behavior fallback). */
    th.actions,
    td.actions {
      width: 160px;
      white-space: nowrap;
      text-align: center;
    }

    th {
      background: #f8fafc;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
    }

    /* Buttons */
    .btn {
      border: none;
      padding: 6px 10px;
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

    .btn-view:hover {
      background: #bfdbfe;
      box-shadow: 0 2px 4px rgba(30, 64, 175, 0.1);
    }

    .btn-edit {
      background: #fef3c7;
      color: #92400e;
    }

    .btn-edit:hover {
      background: #fde68a;
      box-shadow: 0 2px 4px rgba(146, 64, 14, 0.1);
    }

    .btn-delete {
      background: #fee2e2;
      color: #991b1b;
    }

    .btn-delete:hover {
      background: #fecaca;
      box-shadow: 0 2px 4px rgba(153, 27, 27, 0.1);
    }

    /* Form */
    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #374151;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 24px;
    }

    .btn-form-submit {
      background: var(--primary);
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn-form-submit:hover {
      background: var(--primary-dark);
    }

    .cancel-btn {
      padding: 10px 20px;
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
    }

    .cancel-btn:hover {
      background: #e5e7eb;
    }
  </style>
</head>

<body>
  <div class="offset">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">Manage Services</h1>
        <p class="page-subtitle">View, add, edit, or remove services</p>
      </div>
    </div>

    <!-- Create New Service Form -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Create New Service</h2>
      </div>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <div class="form-group">
          <label for="name">Service Name:</label>
          <input type="text" id="name" name="name" required placeholder="E.g., Teeth Whitening">
        </div>

        <div class="form-group">
          <label for="description">Description:</label>
          <textarea id="description" name="description" rows="3" required
            placeholder="Provide a brief description of the service..."></textarea>
        </div>

        <div class="form-group">
          <label for="price">Price (₱):</label>
          <input type="number" id="price" name="price" step="0.01" required placeholder="E.g., 150.00">
        </div>

        <div class="form-group">
          <label for="duration">Duration:</label>
          <select id="duration" name="duration" required>
            <option value="" disabled selected>Select duration</option>
            <option value="15">15 minutes</option>
            <option value="30">30 minutes</option>
            <option value="45">45 minutes</option>
            <option value="60">60 minutes</option>
            <option value="90">90 minutes</option>
            <option value="120">120 minutes</option>
          </select>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-form-submit">Create Service</button>
          <a href="services.php" class="cancel-btn">Cancel</a>
        </div>
      </form>
    </div>
    <!-- Services Table -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">List Of Services</h2>
      </div>
      <table>
        <thead>
          <tr>
            <th>Service Name</th>
            <th>Description</th>
            <th>Price</th>
            <th>Duration</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($services_result && $services_result->num_rows > 0) {
            while ($row = $services_result->fetch_assoc()) {
              echo "<tr>";
              echo "<td>" . htmlspecialchars($row['name']) . "</td>";
              echo "<td>" . htmlspecialchars($row['description']) . "</td>";
              echo "<td>₱" . number_format($row['price'], 2) . "</td>";
              echo "<td>" . $row['duration_minutes'] . " minutes</td>";
              echo "<td>" . ucfirst($row['status']) . "</td>";
              echo "<td>
                      <a class='btn btn-view' href='view_service.php?id=" . $row['id'] . "'>View</a>
                      <a class='btn btn-edit' href='edit_service.php?id=" . $row['id'] . "'>Edit</a>
                      <a class='btn btn-delete' href='delete_service.php?id=" . $row['id'] . "' onclick=\"return confirm('Are you sure?');\">Delete</a>
                    </td>";
              echo "</tr>";
            }
          } else {
            echo "<tr><td colspan='7'>No services found.</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</body>

</html>
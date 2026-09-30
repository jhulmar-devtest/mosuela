<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'secretary') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Handle form submission (Add new dentist)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $role = 'dentist';
  $services = isset($_POST['services']) ? $_POST['services'] : [];

  // Check if email already exists
  $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = ?");
  $stmt->bind_param("ss", $email, $role);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    echo "<script>alert('Email already exists for a dentist. Please use a different email.');</script>";
    $stmt->close();
    $conn->close();
    exit();
  } else {
    $stmt->close();

    // Insert dentist account
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $phone, $password, $role);

    if ($stmt->execute()) {
      $dentist_id = $conn->insert_id;

      // Insert selected services
      if (!empty($services)) {
        $service_stmt = $conn->prepare("INSERT INTO dentist_services (dentist_id, service_id) VALUES (?, ?)");
        foreach ($services as $service_id) {
          $service_stmt->bind_param("ii", $dentist_id, $service_id);
          $service_stmt->execute();
        }
        $service_stmt->close();
      }

      echo "<script>alert('Dentist added successfully!'); window.location.href='dentists.php';</script>";
    } else {
      echo "<script>alert('Error adding dentist: " . $conn->error . "');</script>";
    }

    $stmt->close();
    $conn->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Dentists - Secretary</title>
  <style>
    .page-header {
      margin-bottom: 30px;
    }

    .page-title {
      font-size: 28px;
      font-weight: 700;
      color: var(--primary);
      margin: 0 0 8px 0;
    }

    .page-description {
      color: var(--muted);
      font-size: 16px;
      margin: 0;
    }

    /* ---------- Card Styles ---------- */
    .card {
      background: var(--card);
      border-radius: var(--radius);
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

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
      background: var(--card);
      border-radius: var(--radius);
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
      border-radius: var(--radius);
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

    /* ---------- Form Styles ---------- */
    .form-row {
      display: flex;
      gap: 16px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }

    .form-field {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    label {
      margin-bottom: 6px;
      font-weight: 500;
      color: #374151;
    }

    input,
    select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    /* ✅ Highlight border + glow when focused */
    input:focus,
    select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    /* Multiple select styles */
    select[multiple] {
      min-height: 150px;
      background: white;
    }

    select[multiple] option {
      padding: 6px;
    }

    select[multiple] option:hover {
      background: #f3f4f6;
    }


    /* ---------- Form Actions ---------- */
    .form-actions {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 12px;
      margin-top: 20px;
      flex-wrap: nowrap;
    }

    .btn-form-submit {
      background: var(--primary);
      color: white;
      padding: 10px 20px;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      font-weight: 500;
      transition: background 0.2s ease;
    }

    .btn-form-submit:hover {
      background: #21867a;
    }

    .cancel-btn {
      padding: 10px 20px;
      background: #f3f4f6;
      color: #374151;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
      border: 1px solid #d1d5db;
      transition: background 0.2s ease;
    }

    .cancel-btn:hover {
      background: #e5e7eb;
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
  </style>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>Dentist Management</h2>
      <div class="topbar-right">
      </div>
    </div>

    <div class="page-header">
      <p class="page-description">Add, edit, and manage dentist profiles and their service specializations</p>
    </div>

    <!-- Manage Dentists Table -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Manage Dentists</h2>
      </div>

      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Specialization</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $query = "SELECT id, name, email, phone, 
                      (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') 
                       FROM dentist_services ds 
                       JOIN services s ON ds.service_id = s.id 
                       WHERE ds.dentist_id = u.id) AS specialization
                      FROM users u WHERE role='dentist'";
            $result = $conn->query($query);

            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['specialization'] ?: '—') . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                echo "<td class='actions'>
                        <a class='btn btn-view' href='view_dentist.php?id=" . $row['id'] . "'>View</a>
                        <a class='btn btn-edit' href='edit_dentist.php?id=" . $row['id'] . "'>Edit</a>
                        <a class='btn btn-delete' href='delete_dentist.php?id=" . $row['id'] . "' onclick=\"return confirm('Are you sure you want to delete this dentist?');\">Delete</a>
                      </td>";
                echo "</tr>";
              }
            } else {
              echo "<tr><td colspan='5'>No dentists found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add New Dentist Form -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Add New Dentist</h2>
      </div>

      <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <div class="form-row">
          <div class="form-field">
            <label for="name">Full Name:</label>
            <input type="text" id="name" name="name" required placeholder="E.g., Dr. Alice Smith">
          </div>
          <div class="form-field">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required placeholder="alice.smith@clinic.com">
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" placeholder="(123) 456-7890">
          </div>
          <div class="form-field">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required placeholder="Must be 8+ characters">
          </div>
        </div>

        <div class="form-field">
          <label for="services">Service Specializations:</label>
          <select id="services" name="services[]" multiple>
            <?php
            $result = $conn->query("SELECT id, name FROM services WHERE status='active'");
            if ($result && $result->num_rows > 0) {
              while ($service = $result->fetch_assoc()) {
                echo '<option value="' . $service['id'] . '">' . htmlspecialchars($service['name']) . '</option>';
              }
            } else {
              echo '<option disabled>No active services found</option>';
            }
            ?>
          </select>
          <small style="color:#6b7280;">Hold <b>Ctrl</b> (Windows) or <b>Cmd</b> (Mac) to select multiple
            services.</small>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-form-submit">Create Dentist</button>
          <a href="dentists.php" class="cancel-btn">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>

</html>
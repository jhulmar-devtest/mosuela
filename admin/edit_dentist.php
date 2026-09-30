<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Get dentist ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
  echo "<script>alert('Invalid dentist ID.'); window.location.href='dentists.php';</script>";
  exit();
}

$dentist_id = intval($_GET['id']);

// Fetch dentist details
$stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ? AND role = 'dentist'");
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Dentist not found.'); window.location.href='dentists.php';</script>";
  exit();
}

$dentist = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $newPassword = $_POST['password'];
  $services = isset($_POST['services']) ? $_POST['services'] : [];

  // Update dentist info
  if (!empty($newPassword)) {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, password_hash=? WHERE id=? AND role='dentist'");
    $update->bind_param("ssssi", $name, $email, $phone, $hashedPassword, $dentist_id);
  } else {
    $update = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=? AND role='dentist'");
    $update->bind_param("sssi", $name, $email, $phone, $dentist_id);
  }

  if ($update->execute()) {
    $update->close();

    // Update dentist_services associations
    $delete_stmt = $conn->prepare("DELETE FROM dentist_services WHERE dentist_id = ?");
    $delete_stmt->bind_param("i", $dentist_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    if (!empty($services)) {
      $service_stmt = $conn->prepare("INSERT INTO dentist_services (dentist_id, service_id) VALUES (?, ?)");
      $service_stmt->bind_param("ii", $dentist_id, $service_id);
      foreach ($services as $service_id) {
        $service_stmt->execute();
      }
      $service_stmt->close();
    }

    echo "<script>alert('Dentist updated successfully!'); window.location.href='dentists.php';</script>";
  } else {
    echo "<script>alert('Error updating dentist: " . $conn->error . "');</script>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Edit Dentist - Miracle Mosuela</title>
  <link rel="stylesheet" href="../styles/style.css">
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
    .card-form {
      background: var(--card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 30px;
      max-width: 1500px;
    }

    .card-form h2 {
      font-size: 22px;
      font-weight: 600;
      color: var(--primary);
      margin-top: 0;
      margin-bottom: 24px;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 10px;
    }

    /* ---------- Form Styles ---------- */
    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-weight: 500;
      color: #374151;
    }

    input,
    select,
    textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      font-size: 14px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      background: #fff;
    }

    input:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    /* ---------- Checkbox List ---------- */
    .checkbox-list {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px 16px;
      background: #f8fafc;
      padding: 12px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
    }

    .checkbox-list div {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .checkbox-list input[type="checkbox"] {
      accent-color: var(--primary);
      width: 16px;
      height: 16px;
    }

    /* ---------- Form Actions ---------- */
    .form-actions {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 12px;
      margin-top: 30px;
    }

    .btn-form-submit {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
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
      background: var(--primary-dark);
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


    /* ---------- Responsive ---------- */
    @media (max-width: 768px) {
      body {
        padding: 16px;
      }

      .card-form {
        padding: 20px;
      }

      .checkbox-list {
        grid-template-columns: 1fr;
      }

      .form-actions {
        flex-direction: column;
        gap: 10px;
      }

      .btn-form-submit,
      .cancel-btn {
        width: 100%;
        text-align: center;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">Edit Dentist</h1>
        <p class="page-subtitle">Update dentist details and services offered</p>
      </div>
    </div>

    <form class="card-form" action="" method="POST">



      <!-- Full Name -->
      <div class="form-group">
        <label for="name">Full Name:</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($dentist['name']); ?>" required>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($dentist['email']); ?>" required>
      </div>

      <!-- Phone -->
      <div class="form-group">
        <label for="phone">Phone:</label>
        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($dentist['phone']); ?>">
      </div>

      <!-- New Password -->
      <div class="form-group">
        <label for="password">New Password (leave blank to keep current):</label>
        <input type="password" id="password" name="password" placeholder="********">
      </div>

      <!-- Services Offered Checkbox List -->
      <div class="form-group">
        <label>Services Offered:</label>
        <div class="checkbox-list">
          <?php
          $selected_ids = [];
          $selected_query = "SELECT service_id FROM dentist_services WHERE dentist_id = ?";
          if (isset($conn) && isset($dentist_id) && $selected_stmt = $conn->prepare($selected_query)) {
            $selected_stmt->bind_param("i", $dentist_id);
            $selected_stmt->execute();
            $selected_result = $selected_stmt->get_result();
            while ($row = $selected_result->fetch_assoc()) {
              $selected_ids[] = $row['service_id'];
            }
            $selected_stmt->close();
          }

          $all_services_query = "SELECT id, name FROM services WHERE status='active' ORDER BY name ASC";
          $all_services_result = $conn->query($all_services_query);

          if ($all_services_result && $all_services_result->num_rows > 0) {
            while ($service = $all_services_result->fetch_assoc()) {
              $checked = in_array($service['id'], $selected_ids) ? 'checked' : '';
              echo '
                  <div>
                    <input type="checkbox" 
                           name="services[]" 
                           id="service_' . $service['id'] . '" 
                           value="' . $service['id'] . '" ' . $checked . '>
                    <label for="service_' . $service['id'] . '">' . htmlspecialchars($service['name']) . '</label>
                  </div>';
            }
          } else {
            echo '<p>No active services available.</p>';
          }
          ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-form-submit">Update Dentist</button>
        <a href="dentists.php" class="cancel-btn">Cancel</a>
      </div>
    </form>
  </div>
</body>

</html>

</html>
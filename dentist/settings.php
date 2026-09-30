<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'dentist') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get current user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $current_password = $_POST['current_password'] ?? '';
  $new_password = $_POST['new_password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  // Validate
  if (empty($name) || empty($email)) {
    $error = 'Name and email are required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Invalid email format.';
  } else {
    // Check if email is taken by another user
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
      $error = 'Email is already taken.';
    }
    $check_stmt->close();

    if (!$error) {
      // Update basic info
      $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
      $update_stmt->bind_param("sssi", $name, $email, $phone, $user_id);
      $update_stmt->execute();
      $update_stmt->close();

      // Handle password change
      if (!empty($current_password)) {
        if (empty($new_password) || empty($confirm_password)) {
          $error = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
          $error = 'New passwords do not match.';
        } elseif (!password_verify($current_password, $user['password_hash'])) {
          $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
          $error = 'New password must be at least 8 characters.';
        } else {
          $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
          $pass_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
          $pass_stmt->bind_param("si", $new_hash, $user_id);
          $pass_stmt->execute();
          $pass_stmt->close();
        }
      }

      if (!$error) {
        $_SESSION['user_name'] = $name;
        $success = 'Settings updated successfully!';

        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Account Settings - Dentist</title>
  <style>
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


    .topbar h2 {
      color: var(--primary);
      font-size: 26px;
      font-weight: 700;
      margin-bottom: 20px;
    }

    .alert {
      padding: 12px 16px;
      border-radius: var(--radius);
      margin-bottom: 20px;
      font-size: 14px;
    }

    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }

    .alert-error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 24px;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 12px;
      margin-bottom: 20px;
    }

    .card-title {
      font-size: 18px;
      font-weight: 600;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: var(--muted);
      font-size: 14px;
    }

    input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #d1d5db;
      border-radius: var(--radius);
      font-size: 14px;
    }

    input:focus {
      outline: none;
      border-color: var(--primary);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: var(--radius);
      font-size: 14px;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background: #21867a;
    }

    @media (max-width: 900px) {
      .main {
        margin: 0;
        padding: 16px;
      }

      .form-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="main">
      <div class="topbar">
        <h2>Settings</h2>
        <div class="topbar-right">

        </div>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Personal Information</h3>
        </div>
        <form method="POST">
          <div class="form-row">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Role</label>
            <input type="text" value="Dentist (cannot be changed)" disabled>
          </div>

          <hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;">

          <div class="card-header" style="margin-top: 0; margin-bottom: 20px;">
            <h3 class="card-title">Change Password</h3>
          </div>

          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="new_password">New Password</label>
              <input type="password" id="new_password" name="new_password">
            </div>
            <div class="form-group">
              <label for="confirm_password">Confirm New Password</label>
              <input type="password" id="confirm_password" name="confirm_password">
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="ri-save-line"></i> Save All Changes
          </button>
        </form>
      </div>
    </div>
  </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
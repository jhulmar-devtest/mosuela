<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'secretary') {
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

  // Validation
  if (empty($name) || empty($email)) {
    $error = 'Name and email are required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Invalid email format.';
  } else {
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
      $error = 'Email is already taken.';
    }
    $check_stmt->close();

    if (!$error) {
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
  <title>Account Settings - Miracle Mosuela</title>
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
    <div class="topbar">
      <h2>Account Settings</h2>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Personal Information</div>
        </div>

        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>

        <div class="form-group">
          <label>Phone</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Role</label>
          <input type="text" value="<?= ucfirst($user['role']) ?> (Cannot be changed)" readonly>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">Change Password</div>
        </div>
        <p style="color: var(--muted); font-size: 14px;">Leave blank if you don't want to change your password</p>

        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password">
        </div>

        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password">
        </div>

        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password">
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
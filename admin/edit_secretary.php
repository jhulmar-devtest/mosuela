<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  header("Location: secretaries.php");
  exit();
}

$errors = [];
$success = '';

// Get secretary info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'secretary'");
$stmt->bind_param("i", $id);
$stmt->execute();
$secretary = $stmt->get_result()->fetch_assoc();

if (!$secretary) {
  echo "<script>alert('Secretary not found'); window.location.href='secretaries.php';</script>";
  exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $status = $_POST['status'];
  $new_password = $_POST['new_password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  // Validation
  if (empty($name)) {
    $errors[] = 'Name is required.';
  }

  if (empty($email)) {
    $errors[] = 'Email is required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
  }

  // Check if email is taken by another user
  if (empty($errors)) {
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
      $errors[] = 'Email is already taken by another user.';
    }
    $check_stmt->close();
  }

  // Handle password change
  if (!empty($new_password)) {
    if (strlen($new_password) < 8) {
      $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
      $errors[] = 'Passwords do not match.';
    }
  }

  // Update secretary
  if (empty($errors)) {
    if (!empty($new_password)) {
      $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
      $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, status = ?, password_hash = ? WHERE id = ?");
      $update_stmt->bind_param("sssssi", $name, $email, $phone, $status, $password_hash, $id);
    } else {
      $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
      $update_stmt->bind_param("ssssi", $name, $email, $phone, $status, $id);
    }

    if ($update_stmt->execute()) {
      $success = 'Secretary updated successfully!';

      // Refresh secretary data
      $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'secretary'");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $secretary = $stmt->get_result()->fetch_assoc();
    } else {
      $errors[] = 'Error updating secretary.';
    }
    $update_stmt->close();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Secretary - <?= htmlspecialchars($secretary['name']) ?></title>
  <style>
    h1 {
      font-size: 26px;
      color: #333;
      margin-bottom: 15px;
    }

    .back-link {
      font-size: 14px;
      color: #007bff;
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    .section {
      background-color: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }

    .section h2 {
      font-size: 20px;
      color: #444;
      margin-bottom: 15px;
    }

    .form-table {
      width: 100%;
      margin-top: 10px;
    }

    .form-table td {
      padding: 10px;
      vertical-align: top;
    }

    .form-table input,
    .form-table select {
      width: 100%;
      padding: 10px;
      font-size: 16px;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-sizing: border-box;
    }

    .form-table small {
      color: #666;
      font-size: 12px;
    }

    .form-table input[type="submit"] {
      background-color: #007bff;
      color: white;
      border: none;
      cursor: pointer;
      font-size: 16px;
      padding: 12px 24px;
      border-radius: 4px;
      transition: background-color 0.3s ease;
    }

    .form-table input[type="submit"]:hover {
      background-color: #0056b3;
    }

    .error-message,
    .success-message {
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }

    .error-message {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .success-message {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .actions {
      display: flex;
      gap: 10px;
    }

    .actions a,
    .actions button {
      padding: 12px 24px;
      color: white;
      text-decoration: none;
      font-size: 16px;
      border: none;
      border-radius: 4px;
      transition: background-color 0.3s ease;
    }

    .actions .cancel {
      background-color: #6c757d;
    }

    .actions .cancel:hover {
      background-color: #5a6268;
    }

    .actions .update {
      background-color: #007bff;
    }

    .actions .update:hover {
      background-color: #0056b3;
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
      .form-table td {
        padding: 8px;
      }

      .form-table input {
        font-size: 14px;
      }

      .form-table small {
        font-size: 10px;
      }

      .actions a,
      .actions button {
        padding: 8px 16px;
        font-size: 14px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <h1>Edit Secretary: <?= htmlspecialchars($secretary['name']) ?></h1>
    <p><a href="view_secretary.php?id=<?= $secretary['id'] ?>" class="back-link">← Back to Secretary Details</a></p>

    <?php if ($success): ?>
      <div class="success-message">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="error-message">
        <strong>Error:</strong>
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="section">
        <h2>Basic Information</h2>
        <table class="form-table">
          <tr>
            <td><strong>Name: *</strong></td>
            <td><input type="text" name="name" value="<?= htmlspecialchars($secretary['name']) ?>" required></td>
          </tr>
          <tr>
            <td><strong>Email: *</strong></td>
            <td><input type="email" name="email" value="<?= htmlspecialchars($secretary['email']) ?>" required></td>
          </tr>
          <tr>
            <td><strong>Phone:</strong></td>
            <td><input type="tel" name="phone" value="<?= htmlspecialchars($secretary['phone'] ?? '') ?>"></td>
          </tr>
          <tr>
            <td><strong>Status:</strong></td>
            <td>
              <select name="status" required>
                <option value="active" <?= $secretary['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $secretary['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              </select>
            </td>
          </tr>
        </table>
      </div>

      <div class="section">
        <h2>Change Password (Optional)</h2>
        <p style="color: #666;">Leave blank to keep current password</p>
        <table class="form-table">
          <tr>
            <td><strong>New Password:</strong></td>
            <td><input type="password" name="new_password"><small>Must be at least 8 characters</small></td>
          </tr>
          <tr>
            <td><strong>Confirm Password:</strong></td>
            <td><input type="password" name="confirm_password"></td>
          </tr>
        </table>
      </div>

      <div class="actions">
        <button type="submit" class="update">Update Secretary</button>
        <a href="view_secretary.php?id=<?= $secretary['id'] ?>" class="cancel">Cancel</a>
      </div>
    </form>
  </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
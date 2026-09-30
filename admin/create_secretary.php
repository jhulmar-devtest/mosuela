<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];

  // Validation
  if (empty($name)) {
    $errors[] = 'Name is required.';
  }

  if (empty($email)) {
    $errors[] = 'Email is required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
  }

  if (empty($password)) {
    $errors[] = 'Password is required.';
  } elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
  }

  if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
  }

  // Check if email already exists
  if (empty($errors)) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
      $errors[] = 'Email already exists.';
    }
    $stmt->close();
  }

  // Create secretary
  if (empty($errors)) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
      INSERT INTO users (name, email, phone, password_hash, role, status) 
      VALUES (?, ?, ?, ?, 'secretary', 'active')
    ");
    $stmt->bind_param("ssss", $name, $email, $phone, $password_hash);

    if ($stmt->execute()) {
      $success = true;
    } else {
      $errors[] = 'Error creating secretary account.';
    }
    $stmt->close();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Secretary - Miracle Mosuela Admin</title>
</head>

<body>
  <div class="offset">
    <h1>Create New Secretary</h1>
    <p><a href="secretaries.php">← Back to Secretaries</a></p>

    <?php if ($success): ?>
      <div style="padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
        <strong>Success!</strong> Secretary account created successfully!
        <a href="secretaries.php">View all secretaries</a>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">
        <strong>Error:</strong>
        <ul style="margin: 10px 0 0 20px;">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!$success): ?>
      <form method="POST">
        <table border="1">
          <tr>
            <td style="width: 200px;"><strong>Full Name: *</strong></td>
            <td>
              <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                required style="width: 100%; padding: 8px;">
            </td>
          </tr>
          <tr>
            <td><strong>Email: *</strong></td>
            <td>
              <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required style="width: 100%; padding: 8px;">
            </td>
          </tr>
          <tr>
            <td><strong>Phone:</strong></td>
            <td>
              <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                style="width: 100%; padding: 8px;">
            </td>
          </tr>
          <tr>
            <td><strong>Password: *</strong></td>
            <td>
              <input type="password" name="password" required style="width: 100%; padding: 8px;">
              <small style="color: #666;">Must be at least 8 characters</small>
            </td>
          </tr>
          <tr>
            <td><strong>Confirm Password: *</strong></td>
            <td>
              <input type="password" name="confirm_password" required style="width: 100%; padding: 8px;">
            </td>
          </tr>
        </table>

        <br>

        <button type="submit" style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
          Create Secretary
        </button>
        <a href="secretaries.php" style="margin-left: 15px; padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
          Cancel
        </a>
      </form>
    <?php endif; ?>
  </div>
</body>

</html>

<?php
$conn->close();
?>
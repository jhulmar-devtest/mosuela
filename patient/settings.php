<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'patient') {
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

// Get medical history
$history_stmt = $conn->prepare("SELECT * FROM patient_medical_history WHERE patient_id = ?");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$medical_history = $history_stmt->get_result()->fetch_assoc();

// Handle Account Info Update
if (isset($_POST['update_account'])) {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $current_password = $_POST['current_password'] ?? '';
  $new_password = $_POST['new_password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

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
        $success = 'Account settings updated successfully!';

        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
      }
    }
  }
}

// Handle Medical History Update
if (isset($_POST['update_medical'])) {
  $allergies = trim($_POST['allergies']);
  $medications = trim($_POST['current_medications']);
  $conditions = trim($_POST['medical_conditions']);
  $dental_work = trim($_POST['previous_dental_work']);
  $blood_type = trim($_POST['blood_type']);
  $emergency_name = trim($_POST['emergency_contact_name']);
  $emergency_phone = trim($_POST['emergency_contact_phone']);

  if ($medical_history) {
    // Update existing
    $update_medical = $conn->prepare("
      UPDATE patient_medical_history 
      SET allergies = ?, current_medications = ?, medical_conditions = ?, 
          previous_dental_work = ?, blood_type = ?, emergency_contact_name = ?, 
          emergency_contact_phone = ?
      WHERE patient_id = ?
    ");
    $update_medical->bind_param(
      "sssssssi",
      $allergies,
      $medications,
      $conditions,
      $dental_work,
      $blood_type,
      $emergency_name,
      $emergency_phone,
      $user_id
    );
  } else {
    // Insert new
    $update_medical = $conn->prepare("
      INSERT INTO patient_medical_history 
      (patient_id, allergies, current_medications, medical_conditions, 
       previous_dental_work, blood_type, emergency_contact_name, emergency_contact_phone)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $update_medical->bind_param(
      "isssssss",
      $user_id,
      $allergies,
      $medications,
      $conditions,
      $dental_work,
      $blood_type,
      $emergency_name,
      $emergency_phone
    );
  }

  if ($update_medical->execute()) {
    $success = 'Medical history updated successfully!';

    // Refresh medical history
    $history_stmt = $conn->prepare("SELECT * FROM patient_medical_history WHERE patient_id = ?");
    $history_stmt->bind_param("i", $user_id);
    $history_stmt->execute();
    $medical_history = $history_stmt->get_result()->fetch_assoc();
  } else {
    $error = 'Error updating medical history.';
  }
  $update_medical->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Settings - Mircale Mosuela</title>
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


    /* Page Header */
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .page-header h2 {
      font-size: 28px;
      font-weight: 700;
      color: var(--primary);
    }

    /* Cards */
    .card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 24px;
      margin-bottom: 24px;
    }

    /* Tabs */
    .tabs {
      display: flex;
      gap: 10px;
      margin: 20px 0;
      border-bottom: 2px solid #e5e7eb;
    }

    .tab {
      padding: 12px 24px;
      background: transparent;
      border: none;
      cursor: pointer;
      font-size: 16px;
      font-weight: 500;
      border-bottom: 3px solid transparent;
      color: var(--muted);
      transition: all 0.2s ease;
    }

    .tab:hover {
      color: var(--primary);
    }

    .tab.active {
      border-bottom-color: var(--primary);
      color: var(--primary);
      font-weight: 600;
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    /* Form Tables */
    .form-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .form-table td {
      padding: 16px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: top;
    }

    .form-table td:first-child {
      width: 200px;
      font-weight: 600;
      background: #f8fafc;
      color: var(--muted);
    }

    /* Form Elements */
    input,
    textarea,
    select {
      width: 100%;
      padding: 12px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 14px;
      font-family: inherit;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    input:focus,
    textarea:focus,
    select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 80px;
    }

    /* Buttons */
    .btn {
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      padding: 12px 24px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn:hover {
      background: #21867a;
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
    }

    /* Alert Messages */
    .alert {
      padding: 16px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-weight: 500;
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

    /* Section Headers */
    .section-header {
      font-size: 20px;
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 16px;
      padding-bottom: 8px;
      border-bottom: 1px solid #e5e7eb;
    }

    .section-subtitle {
      color: var(--muted);
      font-size: 14px;
      margin-bottom: 20px;
    }

    /* Help Text */
    .help-text {
      color: var(--muted);
      font-size: 14px;
      font-style: italic;
      margin-bottom: 16px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .offset {
        margin-left: 0;
      }

      body {
        padding: 16px;
      }

      .tabs {
        flex-direction: column;
        gap: 5px;
      }

      .tab {
        text-align: left;
        padding: 10px 16px;
      }

      .form-table td:first-child {
        width: 150px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>Account Settings</h2>
      <div class="topbar-right">
      </div>
    </div>
    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
      <button class="tab active" onclick="showTab('account')">
        <i class="fas fa-user-cog"></i> Account Information
      </button>
      <button class="tab" onclick="showTab('medical')">
        <i class="fas fa-file-medical"></i> Medical History
      </button>
    </div>

    <!-- Account Information Tab -->
    <div id="account" class="tab-content active">
      <div class="card">
        <form method="POST">
          <div class="section-header">Personal Information</div>

          <table class="form-table">
            <tr>
              <td>Name</td>
              <td>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
              </td>
            </tr>
            <tr>
              <td>Email</td>
              <td>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
              </td>
            </tr>
            <tr>
              <td>Phone</td>
              <td>
                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Enter your phone number">
              </td>
            </tr>
          </table>

          <div class="section-header">Change Password</div>
          <div class="help-text">Leave blank if you don't want to change your password</div>

          <table class="form-table">
            <tr>
              <td>Current Password</td>
              <td>
                <input type="password" name="current_password" placeholder="Enter current password">
              </td>
            </tr>
            <tr>
              <td>New Password</td>
              <td>
                <input type="password" name="new_password" placeholder="Enter new password (min. 8 characters)">
              </td>
            </tr>
            <tr>
              <td>Confirm New Password</td>
              <td>
                <input type="password" name="confirm_password" placeholder="Confirm new password">
              </td>
            </tr>
          </table>

          <button type="submit" name="update_account" class="btn">
            <i class="fas fa-save"></i> Save Account Changes
          </button>
        </form>
      </div>
    </div>

    <!-- Medical History Tab -->
    <div id="medical" class="tab-content">
      <div class="card">
        <form method="POST">
          <div class="section-header">Medical History</div>
          <div class="section-subtitle">This information helps your dentist provide better care.</div>

          <table class="form-table">
            <tr>
              <td>Allergies</td>
              <td>
                <textarea name="allergies" placeholder="List any allergies you have (food, medication, etc.)"><?= htmlspecialchars($medical_history['allergies'] ?? '') ?></textarea>
              </td>
            </tr>
            <tr>
              <td>Current Medications</td>
              <td>
                <textarea name="current_medications" placeholder="List all medications you're currently taking"><?= htmlspecialchars($medical_history['current_medications'] ?? '') ?></textarea>
              </td>
            </tr>
            <tr>
              <td>Medical Conditions</td>
              <td>
                <textarea name="medical_conditions" placeholder="List any medical conditions (diabetes, hypertension, etc.)"><?= htmlspecialchars($medical_history['medical_conditions'] ?? '') ?></textarea>
              </td>
            </tr>
            <tr>
              <td>Previous Dental Work</td>
              <td>
                <textarea name="previous_dental_work" placeholder="Describe any previous dental procedures or treatments"><?= htmlspecialchars($medical_history['previous_dental_work'] ?? '') ?></textarea>
              </td>
            </tr>
            <tr>
              <td>Blood Type</td>
              <td>
                <select name="blood_type">
                  <option value="">-- Select Blood Type --</option>
                  <?php
                  $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                  foreach ($blood_types as $type) {
                    $selected = ($medical_history['blood_type'] ?? '') === $type ? 'selected' : '';
                    echo "<option value='$type' $selected>$type</option>";
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr>
              <td>Emergency Contact Name</td>
              <td>
                <input type="text" name="emergency_contact_name" value="<?= htmlspecialchars($medical_history['emergency_contact_name'] ?? '') ?>" placeholder="Full name of emergency contact">
              </td>
            </tr>
            <tr>
              <td>Emergency Contact Phone</td>
              <td>
                <input type="tel" name="emergency_contact_phone" value="<?= htmlspecialchars($medical_history['emergency_contact_phone'] ?? '') ?>" placeholder="Phone number of emergency contact">
              </td>
            </tr>
          </table>

          <button type="submit" name="update_medical" class="btn">
            <i class="fas fa-save"></i> Save Medical History
          </button>
        </form>
      </div>
    </div>

    <script>
      function showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(content => {
          content.classList.remove('active');
        });
        document.querySelectorAll('.tab').forEach(tab => {
          tab.classList.remove('active');
        });

        // Show selected tab
        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');
      }
    </script>
  </div>
</body>

</html>

<?php
$stmt->close();
$history_stmt->close();
$conn->close();
?>
<?php
include 'includes/db.php';

$error_message = '';
$success_message = '';

// Define upload directory
$upload_dir = 'uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
  mkdir($upload_dir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);
  $confirm_password = trim($_POST['confirm_password']);
  $phone = trim($_POST['phone']);
  $role = 'patient';
  $profile_picture = '';

  // Validate profile picture
  if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_picture'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];

    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Allowed extensions
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

    // Check if file extension is allowed
    if (in_array($file_ext, $allowed_ext)) {
      // Check file size (max 2MB)
      if ($file_size <= 2097152) {
        // Generate unique filename
        $unique_name = uniqid('profile_', true) . '.' . $file_ext;
        $upload_path = $upload_dir . $unique_name;

        // Move uploaded file
        if (move_uploaded_file($file_tmp, $upload_path)) {
          $profile_picture = $unique_name;
        } else {
          $error_message = 'Failed to upload profile picture.';
        }
      } else {
        $error_message = 'Profile picture size must be less than 2MB.';
      }
    } else {
      $error_message = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
    }
  } else {
    $error_message = 'Profile picture is required.';
  }

  // Continue with registration if no error with profile picture
  if (empty($error_message)) {
    // Validate password match
    if ($password !== $confirm_password) {
      $error_message = 'Passwords do not match.';
    }
    // Check if email is valid
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error_message = 'Invalid email format.';
    }
    // Check password strength
    elseif (strlen($password) < 8) {
      $error_message = 'Password must be at least 8 characters long.';
    } else {
      // Check if email already exists
      $checkEmailQuery = $conn->prepare("SELECT id FROM users WHERE email = ?");
      $checkEmailQuery->bind_param("s", $email);
      $checkEmailQuery->execute();
      $checkEmailQuery->store_result();

      if ($checkEmailQuery->num_rows > 0) {
        $error_message = 'Email already registered. Please use a different email.';
        // Remove uploaded file if email exists
        if (!empty($profile_picture)) {
          @unlink($upload_dir . $profile_picture);
        }
      } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertQuery = $conn->prepare("INSERT INTO users (name, email, password_hash, phone, role, profile_picture) VALUES (?, ?, ?, ?, ?, ?)");
        $insertQuery->bind_param("ssssss", $name, $email, $hashedPassword, $phone, $role, $profile_picture);

        if ($insertQuery->execute()) {
          $success_message = 'Registration successful! Redirecting to login...';
          echo "<script>
                            setTimeout(function() {
                                window.location.href = 'login.php';
                            }, 2000);
                        </script>";
          $insertQuery->close();
          $checkEmailQuery->close();
          $conn->close();
          exit();
        } else {
          $error_message = 'Error: Unable to register. Please try again later.';
          // Remove uploaded file if database insert fails
          if (!empty($profile_picture)) {
            @unlink($upload_dir . $profile_picture);
          }
        }
      }
      $checkEmailQuery->close();
    }
  }
  $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Miracle Mosuela - Register</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/main.css">

  <style>
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #2c3e50 0%, #34495e 50%, #2c3e50 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    body::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(233, 196, 106, 0.08) 0%, transparent 70%);
      animation: rotate 30s linear infinite;
    }

    @keyframes rotate {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .back-button {
      position: fixed;
      top: 30px;
      left: 30px;
      z-index: 1000;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      color: white;
      padding: 12px 24px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s ease;
      border: 2px solid rgba(255, 255, 255, 0.2);
      font-size: 15px;
    }

    .back-button:hover {
      background: rgba(255, 255, 255, 0.25);
      transform: translateX(-5px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .main-container {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: 1fr 1fr;
      max-width: 1200px;
      width: 100%;
      background: white;
      border-radius: 25px;
      overflow: hidden;
      box-shadow: var(--shadow-lg);
      animation: slideUp 0.6s ease;
      max-height: 90vh;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .left-panel {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      padding: 60px 50px;
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    .left-panel::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle, rgba(233, 196, 106, 0.15) 0%, transparent 70%);
      animation: pulse 4s ease-in-out infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
        opacity: 0.5;
      }

      50% {
        transform: scale(1.1);
        opacity: 0.8;
      }
    }

    .brand-logo {
      position: relative;
      z-index: 2;
      font-size: 2rem;
      font-weight: 800;
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .brand-logo::before {
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      font-size: 2.5rem;
      color: var(--secondary);
      animation: pulse 2s ease-in-out infinite;
    }

    .miracle {
      color: white;
    }

    .mosuela {
      color: var(--secondary);
    }

    .left-panel h2 {
      position: relative;
      z-index: 2;
      font-size: 2.2rem;
      margin-bottom: 20px;
      font-weight: 800;
    }

    .left-panel p {
      position: relative;
      z-index: 2;
      font-size: 1.05rem;
      line-height: 1.8;
      opacity: 0.95;
      margin-bottom: 30px;
    }

    .benefits-list {
      position: relative;
      z-index: 2;
      list-style: none;
      margin-top: 30px;
    }

    .benefits-list li {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 15px;
      font-size: 1rem;
      opacity: 0.9;
    }

    .benefits-list i {
      color: var(--secondary);
      font-size: 1.2rem;
    }

    .right-panel {
      padding: 60px 50px;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      /* Changed from center to flex-start */
      background: var(--white);
      /* Add these properties for scrolling */
      max-height: 90vh;
      overflow-y: auto;

    }


    .card-form {
      width: 100%;
      max-width: 450px;


    }


    .card-form h2 {
      color: var(--primary);
      font-size: 2rem;
      margin-bottom: 10px;
      font-weight: 800;
    }

    .card-form>p {
      color: #666;
      margin-bottom: 30px;
      font-size: 0.95rem;
    }

    .alert {
      padding: 15px 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .alert-error {
      background: #fee2e2;
      color: #b91c1c;
      border: 1px solid #fca5a5;
    }

    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #6ee7b7;
    }

    .alert i {
      font-size: 18px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: var(--dark);
      font-weight: 600;
      font-size: 14px;
    }

    .form-group label .optional {
      color: #999;
      font-weight: 400;
      font-size: 13px;
    }

    .input-wrapper {
      position: relative;
      display: block;
    }

    .input-wrapper>i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      font-size: 16px;
      transition: color 0.3s ease;
      pointer-events: none;
      z-index: 2;
    }

    .form-group input {
      width: 100%;
      padding: 14px 15px 14px 45px;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      font-size: 15px;
      transition: all 0.3s ease;
      background: var(--white);
      position: relative;
      z-index: 1;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: var(--shadow-primary-sm);
    }

    .form-group input:focus~i {
      color: var(--primary);
    }

    /* Profile Picture Upload Styles */
    .profile-picture-upload {
      margin-bottom: 25px;
    }

    .profile-picture-upload label {
      display: block;
      margin-bottom: 8px;
      color: var(--dark);
      font-weight: 600;
      font-size: 14px;
    }

    .upload-area {
      border: 2px dashed #e5e7eb;
      border-radius: 12px;
      padding: 25px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .upload-area:hover {
      border-color: var(--primary);
      background: rgba(59, 130, 246, 0.05);
    }

    .upload-area.dragover {
      border-color: var(--primary);
      background: rgba(59, 130, 246, 0.1);
    }

    .upload-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
    }

    .upload-icon {
      font-size: 40px;
      color: #9ca3af;
      transition: color 0.3s ease;
    }

    .upload-area:hover .upload-icon {
      color: var(--primary);
    }

    .upload-text h4 {
      color: #374151;
      font-size: 16px;
      margin-bottom: 4px;
    }

    .upload-text p {
      color: #6b7280;
      font-size: 13px;
      margin: 0;
    }

    .browse-button {
      background: var(--primary);
      color: white;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .browse-button:hover {
      background: var(--primary-dark);
    }

    #profile-preview {
      margin-top: 15px;
      display: none;
    }

    .preview-container {
      display: flex;
      align-items: center;
      gap: 15px;
      background: #f9fafb;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
    }

    .preview-image {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--primary);
    }

    .preview-info {
      flex: 1;
    }

    .preview-name {
      font-weight: 600;
      color: #374151;
      margin-bottom: 4px;
      font-size: 14px;
    }

    .preview-size {
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 4px;
    }

    .remove-preview {
      background: #fee2e2;
      color: #dc2626;
      border: none;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .remove-preview:hover {
      background: #fecaca;
    }

    .password-toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #999;
      font-size: 18px;
      transition: color 0.3s ease;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
      background: transparent;
      border: none;
    }

    .password-toggle:hover {
      color: var(--primary);
    }

    #password,
    #confirm_password {
      padding-right: 50px !important;
    }

    .password-strength {
      margin-top: 8px;
      font-size: 13px;
    }

    .strength-bar {
      height: 4px;
      border-radius: 2px;
      background: #e5e7eb;
      margin-top: 5px;
      overflow: hidden;
    }

    .strength-fill {
      height: 100%;
      width: 0;
      transition: all 0.3s ease;
      border-radius: 2px;
    }

    .strength-weak {
      width: 33%;
      background: #ef4444;
    }

    .strength-medium {
      width: 66%;
      background: #f59e0b;
    }

    .strength-strong {
      width: 100%;
      background: #10b981;
    }

    .btn {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 10px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-primary-lg);
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .login-link {
      text-align: center;
      margin-top: 25px;
      padding-top: 25px;
      border-top: 1px solid #e5e7eb;
    }

    .login-link p {
      color: #666;
      font-size: 15px;
    }

    .login-link a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 700;
      transition: color 0.3s ease;
    }

    .login-link a:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }

    .right-panel::-webkit-scrollbar {
      width: 8px;
      /* Thinner scrollbar */
    }

    .right-panel::-webkit-scrollbar-track {
      background: #f8f9fa;
      border-radius: 10px;
      margin: 15px 0;
      border: 1px solid #e9ecef;
    }

    .right-panel::-webkit-scrollbar-thumb {
      background: linear-gradient(to bottom,
          var(--primary-dark) 0%,
          var(--primary) 50%,
          var(--primary-dark) 100%);
      border-radius: 10px;
      border: 2px solid #f8f9fa;
    }

    .right-panel::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(to bottom,
          var(--primary-dark) 0%,
          var(--primary) 50%,
          var(--primary-dark) 100%);
    }

    @media (max-width: 968px) {
      .main-container {
        grid-template-columns: 1fr;
        max-width: 550px;
      }

      .left-panel {
        display: none;
      }

      .back-button {
        top: 20px;
        left: 20px;
      }

      .form-row {
        grid-template-columns: 1fr;
      }

      .right-panel {
        max-height: none;
      }
    }

    @media (max-width: 480px) {
      body {
        padding: 10px;
      }

      .back-button {
        position: absolute;
        top: 10px;
        left: 10px;
        padding: 8px 16px;
        font-size: 13px;
      }

      .right-panel {
        padding: 30px 20px;
      }

      .card-form h2 {
        font-size: 1.6rem;
      }

      .upload-area {
        padding: 20px;
      }
    }
  </style>
</head>

<body>
  <a href="index.php" class="back-button">
    <i class="fas fa-arrow-left"></i>
    Back to Home
  </a>

  <div class="main-container">
    <div class="left-panel">
      <div class="brand-logo">
        <span class="miracle">Miracle</span><span class="mosuela">Mosuela</span>
      </div>
      <h2>Join Our Dental Family</h2>
      <p>Create your account to schedule appointments, manage your dental health, and experience premium dental
        care with Miracle Mosuela Dental Clinic.</p>

      <ul class="benefits-list">
        <li>
          <i class="fas fa-calendar-check"></i>
          <span>Easy online appointment booking</span>
        </li>
        <li>
          <i class="fas fa-file-medical"></i>
          <span>Access your dental records anytime</span>
        </li>
        <li>
          <i class="fas fa-bell"></i>
          <span>Appointment reminders & notifications</span>
        </li>
        <li>
          <i class="fas fa-shield-alt"></i>
          <span>Secure & confidential</span>
        </li>
      </ul>
    </div>

    <div class="right-panel">
      <form class="card-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST"
        enctype="multipart/form-data" id="register-form">
        <h2>Create Account</h2>
        <p>Fill in your details to get started</p>

        <?php if ($error_message): ?>
          <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
          </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
          </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="name">Full Name</label>
          <div class="input-wrapper">
            <i class="fas fa-user"></i>
            <input type="text" id="name" name="name" placeholder="Enter your full name" required
              value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-wrapper">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="your.email@example.com" required
              value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="phone">Phone Number <span class="optional">(Optional)</span></label>
          <div class="input-wrapper">
            <i class="fas fa-phone"></i>
            <input type="tel" id="phone" name="phone" placeholder="+63 912 345 6789"
              value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
          </div>
        </div>

        <!-- Profile Picture Upload Section -->
        <div class="profile-picture-upload">
          <label for="profile_picture">Profile Picture <span class="optional">(Required, max
              2MB)</span></label>
          <div class="upload-area" id="upload-area">
            <div class="upload-content">
              <i class="fas fa-cloud-upload-alt upload-icon"></i>
              <div class="upload-text">
                <h4>Upload Profile Picture</h4>
                <p>Click to browse or drag & drop</p>
                <p>JPG, PNG, GIF up to 2MB</p>
              </div>
              <button type="button" class="browse-button">Browse Files</button>
            </div>
            <input type="file" id="profile_picture" name="profile_picture" accept=".jpg,.jpeg,.png,.gif"
              required style="display: none;">
          </div>
          <div id="profile-preview">
            <div class="preview-container">
              <img id="preview-image" class="preview-image" src="" alt="Preview">
              <div class="preview-info">
                <div id="preview-name" class="preview-name"></div>
                <div id="preview-size" class="preview-size"></div>
              </div>
              <button type="button" class="remove-preview" id="remove-preview">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Create a strong password"
              required minlength="8">
            <span class="password-toggle" id="toggle-password">
              <i class="fas fa-eye-slash"></i>
            </span>
          </div>
          <div class="password-strength" id="password-strength">
            <div class="strength-bar">
              <div class="strength-fill" id="strength-fill"></div>
            </div>
            <span id="strength-text"></span>
          </div>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input type="password" id="confirm_password" name="confirm_password"
              placeholder="Re-enter your password" required minlength="8">
            <span class="password-toggle" id="toggle-confirm-password">
              <i class="fas fa-eye-slash"></i>
            </span>
          </div>
          <small id="password-match" style="display: none; margin-top: 5px; font-size: 13px;"></small>
        </div>

        <button type="submit" class="btn" id="submit-btn">
          <span>Create Account</span>
          <i class="fas fa-arrow-right"></i>
        </button>

        <div class="login-link">
          <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Password Toggle
    function setupPasswordToggle(toggleId, inputId) {
      const toggle = document.getElementById(toggleId);
      const input = document.getElementById(inputId);

      if (toggle && input) {
        toggle.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();

          const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
          input.setAttribute('type', type);

          const icon = this.querySelector('i');
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        });
      }
    }

    setupPasswordToggle('toggle-password', 'password');
    setupPasswordToggle('toggle-confirm-password', 'confirm_password');

    // Password Strength Checker
    const passwordInput = document.getElementById('password');
    const strengthFill = document.getElementById('strength-fill');
    const strengthText = document.getElementById('strength-text');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;

      if (password.length >= 8) strength++;
      if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
      if (password.match(/[0-9]/)) strength++;
      if (password.match(/[^a-zA-Z0-9]/)) strength++;

      strengthFill.className = 'strength-fill';

      if (password.length === 0) {
        strengthText.textContent = '';
        strengthFill.style.width = '0';
      } else if (strength <= 1) {
        strengthFill.classList.add('strength-weak');
        strengthText.textContent = 'Weak password';
        strengthText.style.color = '#ef4444';
      } else if (strength <= 2) {
        strengthFill.classList.add('strength-medium');
        strengthText.textContent = 'Medium password';
        strengthText.style.color = '#f59e0b';
      } else {
        strengthFill.classList.add('strength-strong');
        strengthText.textContent = 'Strong password';
        strengthText.style.color = '#10b981';
      }
    });

    // Password Match Checker
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordMatchText = document.getElementById('password-match');

    confirmPasswordInput.addEventListener('input', function() {
      const password = passwordInput.value;
      const confirmPassword = this.value;

      if (confirmPassword.length === 0) {
        passwordMatchText.style.display = 'none';
      } else if (password === confirmPassword) {
        passwordMatchText.style.display = 'block';
        passwordMatchText.textContent = '✓ Passwords match';
        passwordMatchText.style.color = '#10b981';
      } else {
        passwordMatchText.style.display = 'block';
        passwordMatchText.textContent = '✗ Passwords do not match';
        passwordMatchText.style.color = '#ef4444';
      }
    });

    // Profile Picture Upload
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('profile_picture');
    const previewContainer = document.getElementById('profile-preview');
    const previewImage = document.getElementById('preview-image');
    const previewName = document.getElementById('preview-name');
    const previewSize = document.getElementById('preview-size');
    const removePreviewBtn = document.getElementById('remove-preview');
    const browseButton = uploadArea.querySelector('.browse-button');

    // Click on upload area to trigger file input
    uploadArea.addEventListener('click', function(e) {
      if (e.target !== browseButton) {
        fileInput.click();
      }
    });

    // Browse button click
    browseButton.addEventListener('click', function(e) {
      e.stopPropagation();
      fileInput.click();
    });

    // Drag and drop functionality
    uploadArea.addEventListener('dragover', function(e) {
      e.preventDefault();
      uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function() {
      uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
      e.preventDefault();
      uploadArea.classList.remove('dragover');
      if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        handleFileSelect();
      }
    });

    // File input change
    fileInput.addEventListener('change', handleFileSelect);

    function handleFileSelect() {
      const file = fileInput.files[0];
      if (file) {
        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
          alert('Please select a valid image file (JPG, PNG, GIF)');
          fileInput.value = '';
          return;
        }

        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
          alert('File size must be less than 2MB');
          fileInput.value = '';
          return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
          previewImage.src = e.target.result;
          previewName.textContent = file.name;
          previewSize.textContent = formatFileSize(file.size);
          previewContainer.style.display = 'block';
          uploadArea.style.display = 'none';
        };
        reader.readAsDataURL(file);
      }
    }

    // Remove preview
    removePreviewBtn.addEventListener('click', function() {
      fileInput.value = '';
      previewContainer.style.display = 'none';
      uploadArea.style.display = 'block';
    });

    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Form Validation
    const form = document.getElementById('register-form');
    const submitBtn = document.getElementById('submit-btn');

    form.addEventListener('submit', function(e) {
      const password = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;
      const profilePic = fileInput.files[0];

      // Check profile picture
      if (!profilePic) {
        e.preventDefault();
        alert('Profile picture is required!');
        return false;
      }

      // Check password match
      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
      }

      // Check password length
      if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long!');
        return false;
      }

      // Check password strength
      let strength = 0;
      if (password.length >= 8) strength++;
      if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
      if (password.match(/[0-9]/)) strength++;
      if (password.match(/[^a-zA-Z0-9]/)) strength++;

      if (strength <= 1) {
        e.preventDefault();
        alert('Password is too weak! Please use a stronger password.');
        return false;
      }

      submitBtn.disabled = true;
      submitBtn.querySelector('span').textContent = 'Creating Account...';
    });
  </script>
</body>

</html>
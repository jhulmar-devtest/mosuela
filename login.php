<?php
session_start();
include 'includes/db.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Miracle Mosuela - Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/main.css">
  <script src="https://accounts.google.com/gsi/client" async defer></script>

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

    /* Animated Background */
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

    /* Back Button */
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

    .back-button i {
      font-size: 16px;
    }

    /* Main Container */
    .main-container {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: 1fr 1fr;
      max-width: 1100px;
      width: 100%;
      background: white;
      border-radius: 25px;
      overflow: hidden;
      box-shadow: var(--shadow-lg);
      animation: slideUp 0.6s ease;
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

    /* Left Panel */
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
      /* content: '\f5b9'; */
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

    .features-list {
      position: relative;
      z-index: 2;
      list-style: none;
      margin-top: 30px;
    }

    .features-list li {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 15px;
      font-size: 1rem;
      opacity: 0.9;
    }

    .features-list i {
      color: var(--secondary);
      font-size: 1.2rem;
    }

    /* Right Panel */
    .right-panel {
      padding: 60px 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--white);
    }

    .card-form {
      width: 100%;
      max-width: 400px;
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

    /* Form Groups */
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

    .input-wrapper {
      position: relative;
      display: block;
    }

    .input-wrapper>i.fa-envelope,
    .input-wrapper>i.fa-lock {
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
      padding: 14px 50px 14px 45px;
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

    .form-group input:focus~i.fa-envelope,
    .form-group input:focus~i.fa-lock {
      color: var(--primary);
    }

    /* Password Toggle */
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

    /* Special styling for password input to prevent text overflow */
    #password {
      padding-right: 50px !important;
    }

    /* Forgot Password */
    .forgot-password {
      text-align: right;
      margin-top: -15px;
      margin-bottom: 25px;
    }

    .forgot-password a {
      color: var(--primary);
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .forgot-password a:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }

    /* Submit Button */
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
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-primary-lg);
    }

    .btn:active {
      transform: translateY(0);
    }

    /* Divider */
    .divider {
      text-align: center;
      margin: 25px 0;
      font-weight: 600;
      color: #999;
      font-size: 14px;
      position: relative;
    }

    .divider::before,
    .divider::after {
      content: '';
      position: absolute;
      top: 50%;
      width: 40%;
      height: 1px;
      background: #e5e7eb;
    }

    .divider::before {
      left: 0;
    }

    .divider::after {
      right: 0;
    }

    /* Google Sign In */
    .google-signin-wrapper {
      margin-bottom: 25px;
    }

    #g_id_onload {
      display: block;
    }

    .g_id_signin {
      display: flex !important;
      justify-content: center !important;
    }

    /* Register Link */
    .register-link {
      text-align: center;
      margin-top: 25px;
      padding-top: 25px;
      border-top: 1px solid #e5e7eb;
    }

    .register-link p {
      color: #666;
      font-size: 15px;
    }

    .register-link a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 700;
      transition: color 0.3s ease;
    }

    .register-link a:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }

    /* Alert Messages */
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

    /* Loading State */
    .btn.loading {
      pointer-events: none;
      opacity: 0.7;
    }

    .btn.loading::after {
      content: '';
      width: 16px;
      height: 16px;
      border: 2px solid white;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    /* Responsive Design */
    @media (max-width: 968px) {
      .main-container {
        grid-template-columns: 1fr;
        max-width: 500px;
      }

      .left-panel {
        display: none;
      }

      .back-button {
        top: 20px;
        left: 20px;
        padding: 10px 20px;
        font-size: 14px;
      }

      .right-panel {
        padding: 40px 30px;
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

      .brand-logo {
        font-size: 1.5rem;
      }
    }
  </style>
</head>

<body>
  <!-- Back Button -->
  <a href="index.php" class="back-button">
    <i class="fas fa-arrow-left"></i>
    Back to Home
  </a>

  <div class="main-container">
    <!-- Left Panel -->
    <div class="left-panel">
      <div class="brand-logo">
        <span class="miracle">Miracle</span><span class="mosuela">Mosuela</span>
      </div>
      <h2>Welcome Back!</h2>
      <p>Sign in to your account to access your dental dashboard, manage appointments, and continue your journey to better oral health.</p>

      <ul class="features-list">
        <li>
          <i class="fas fa-check-circle"></i>
          <span>Easy appointment scheduling</span>
        </li>
        <li>
          <i class="fas fa-check-circle"></i>
          <span>Access your dental records</span>
        </li>
        <li>
          <i class="fas fa-check-circle"></i>
          <span>Track your oral health journey</span>
        </li>
        <li>
          <i class="fas fa-check-circle"></i>
          <span>Secure and private</span>
        </li>
      </ul>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
      <form class="card-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" id="login-form">
        <h2>Sign In</h2>
        <p>Enter your credentials to access your account</p>

        <div id="alert-container"></div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-wrapper">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="your.email@example.com" required>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
            <span class="password-toggle" id="toggle-password">
              <i class="fas fa-eye-slash"></i>
            </span>
          </div>
        </div>

        <div class="forgot-password">
          <a href="#" onclick="alert('Please contact the clinic administrator to reset your password.'); return false;">Forgot Password?</a>
        </div>

        <button type="submit" class="btn" id="submit-btn">
          <span>Sign In</span>
          <i class="fas fa-arrow-right"></i>
        </button>

        <div class="divider">OR</div>

        <div class="google-signin-wrapper">
          <div id="g_id_onload"
            data-client_id=getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID'
            data-callback="handleCredentialResponse">
          </div>

          <div class="g_id_signin"
            data-type="standard"
            data-size="large"
            data-width="400">
          </div>
        </div>

        <div class="register-link">
          <p>Don't have an account? <a href="register.php">Create one now</a></p>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Password Toggle
    const togglePassword = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      });
    }

    // Form Submit Loading State
    const form = document.getElementById('login-form');
    const submitBtn = document.getElementById('submit-btn');

    form.addEventListener('submit', function() {
      submitBtn.classList.add('loading');
      submitBtn.querySelector('span').textContent = 'Signing in...';
    });

    // Google Sign In Handler
    function handleCredentialResponse(response) {
      fetch("google_login.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            token: response.credential
          })
        })
        .then(res => {
          if (!res.ok) {
            throw new Error("Server error: " + res.statusText);
          }
          return res.json();
        })
        .then(data => {
          if (data.status === "success") {
            showAlert('success', 'Login successful! Redirecting...');
            setTimeout(() => {
              window.location.href = data.redirect;
            }, 1000);
          } else {
            showAlert('error', data.message || 'Google login failed. Please try again.');
          }
        })
        .catch(error => {
          console.error("Google login error:", error);
          showAlert('error', 'An error occurred during Google login. Please try again.');
        });
    }

    // Alert Function
    function showAlert(type, message) {
      const alertContainer = document.getElementById('alert-container');
      const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
      const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

      alertContainer.innerHTML = `
        <div class="alert ${alertClass}">
          <i class="fas ${icon}"></i>
          <span>${message}</span>
        </div>
      `;

      // Auto-remove after 5 seconds
      setTimeout(() => {
        alertContainer.innerHTML = '';
      }, 5000);
    }
  </script>
</body>

</html>

<?php
// EMAIL/PASSWORD LOGIN SYSTEM
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);

  $query = "SELECT id, name, email, password_hash, role FROM users WHERE email = ?";
  $stmt = $conn->prepare($query);

  if (!$stmt) {
    echo "<script>showAlert('error', 'Database error. Please try again.');</script>";
  } else {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
      $stmt->bind_result($id, $name, $dbEmail, $hashedPassword, $dbRole);
      $stmt->fetch();

      if (password_verify($password, (string)$hashedPassword)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $dbEmail;
        $_SESSION['user_role'] = $dbRole;

        function track_user_login($conn, $user_id) {
          $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
          $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

          $stmt = $conn->prepare("INSERT INTO user_login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
          if ($stmt) {
            $stmt->bind_param("iss", $user_id, $ip_address, $user_agent);
            $stmt->execute();
            $stmt->close();
          }
        }

        // Call this function after successful login:
        track_user_login($conn, $_SESSION['user_id']);

        // Redirect based on role
        if ($dbRole === 'admin') {
          echo "<script>showAlert('success', 'Welcome back, Admin!'); setTimeout(() => { window.location.href = 'admin/dashboard.php'; }, 1000);</script>";
        } elseif ($dbRole === 'dentist') {
          echo "<script>showAlert('success', 'Welcome back, Doctor!'); setTimeout(() => { window.location.href = 'dentist/dashboard.php'; }, 1000);</script>";
        } elseif ($dbRole === 'secretary') {
          echo "<script>showAlert('success', 'Welcome back!'); setTimeout(() => { window.location.href = 'secretary/dashboard.php'; }, 1000);</script>";
        } elseif ($dbRole === 'patient') {
          echo "<script>showAlert('success', 'Welcome back!'); setTimeout(() => { window.location.href = 'patient/dashboard.php'; }, 1000);</script>";
        } else {
          echo "<script>showAlert('error', 'Invalid role in system. Please contact admin.');</script>";
        }
        exit;
      } else {
        echo "<script>showAlert('error', 'Incorrect password. Please try again.');</script>";
      }
    } else {
      echo "<script>showAlert('error', 'No account found with that email address.');</script>";
    }

    $stmt->close();
  }

  $conn->close();
}
?>
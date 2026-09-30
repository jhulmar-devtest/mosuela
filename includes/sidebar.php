<?php
include '../includes/db.php';
$role = $_SESSION['user_role'];
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch user profile picture
$user_id = $_SESSION['user_id'];
$profile_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$profile_query->bind_param("i", $user_id);
$profile_query->execute();
$profile_result = $profile_query->get_result();
$user_data = $profile_result->fetch_assoc();

$profile_picture = $user_data['profile_picture'] ?? '';
$profile_query->close();
?>

<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  .sidebar {
    background: var(--sidebar-bg);
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    padding: 0;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
    overflow-y: auto;
    z-index: 1000;
  }

  .sidebar::-webkit-scrollbar {
    width: 6px;
  }

  .sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
  }

  .sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
  }

  /* Brand Section */
  .brand-section {
    padding: 30px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: linear-gradient(135deg, rgba(157, 2, 8, 0.1), rgba(208, 0, 0, 0.05));
  }

  .brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 8px;
  }

  .brand i {
    font-size: 2rem;
    color: var(--secondary);
    animation: pulse 2s ease-in-out infinite;
  }

  @keyframes pulse {

    0%,
    100% {
      transform: scale(1);
    }

    50% {
      transform: scale(1.05);
    }
  }

  .brand-subtitle {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-left: 44px;
  }

  /* User Info */
  .user-info {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--sidebar-active) center/cover no-repeat;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
    border: 3px solid rgba(255, 255, 255, 0.2);
  }

  .user-details {
    flex: 1;
    min-width: 0;
  }

  .user-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .user-role {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: capitalize;
  }

  /* Navigation */
  nav {
    display: flex;
    flex-direction: column;
    padding: 20px 0;
    flex: 1;
  }

  .nav-section {
    margin-bottom: 24px;
  }

  .small-heading {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 700;
    padding: 0 24px;
    margin-bottom: 12px;
  }

  nav a {
    display: flex;
    align-items: center;
    gap: 14px;
    color: var(--text-secondary);
    padding: 14px 24px;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    font-size: 0.95rem;
    font-weight: 500;
  }

  nav a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--primary);
    transform: scaleY(0);
    transition: transform 0.3s ease;
  }

  nav a i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    transition: transform 0.3s ease;
  }

  nav a:hover {
    background: var(--sidebar-hover);
    color: var(--text-primary);
  }

  nav a:hover i {
    transform: scale(1.1);
  }

  nav a.active {
    background: var(--sidebar-hover);
    color: var(--text-primary);
    font-weight: 600;
  }

  nav a.active::before {
    transform: scaleY(1);
  }

  nav a.active i {
    color: var(--secondary);
  }

  /* Badge for notifications */
  .badge {
    background: var(--primary);
    color: white;
    font-size: 0.7rem;
    padding: 2px 7px;
    border-radius: 10px;
    font-weight: 700;
    margin-left: auto;
  }

  /* Logout Button */
  .logout {
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(157, 2, 8, 0.05);
  }

  .logout:hover {
    background: var(--primary);
    color: white !important;
  }

  .logout:hover i {
    transform: translateX(-3px);
  }

  /* Mobile Toggle */
  .sidebar-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1001;
    background: var(--sidebar-bg);
    color: white;
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    font-size: 1.2rem;
  }

  /* Responsive */
  @media (max-width: 968px) {
    .sidebar {
      transform: translateX(-100%);
      transition: transform 0.3s ease;
    }

    .sidebar.active {
      transform: translateX(0);
    }

    .sidebar-toggle {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
    }

    .sidebar-overlay.active {
      display: block;
    }
  }

  /* Animations */
  @keyframes slideIn {
    from {
      opacity: 0;
      transform: translateX(-10px);
    }

    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  nav a {
    animation: slideIn 0.3s ease forwards;
    opacity: 0;
  }

  nav a:nth-child(1) {
    animation-delay: 0.05s;
  }

  nav a:nth-child(2) {
    animation-delay: 0.1s;
  }

  nav a:nth-child(3) {
    animation-delay: 0.15s;
  }

  nav a:nth-child(4) {
    animation-delay: 0.2s;
  }

  nav a:nth-child(5) {
    animation-delay: 0.25s;
  }

  nav a:nth-child(6) {
    animation-delay: 0.3s;
  }

  nav a:nth-child(7) {
    animation-delay: 0.35s;
  }

  nav a:nth-child(8) {
    animation-delay: 0.4s;
  }
</style>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" id="sidebar-toggle">
  <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="sidebar">
  <!-- Brand Section -->
  <div class="brand-section">
    <div class="brand">
      <img src="../image/miracle-logo-yellow.png" alt="" style="height: 29px;">
      <span>Miracle Mosuela</span>
    </div>
    <div class="brand-subtitle">Dental Clinic</div>
  </div>

  <!-- User Info -->
  <div class="user-info">
    <div class="user-avatar"
      style="<?= !empty($profile_picture) ? 'background-image: url(../uploads/profile_pictures/' . htmlspecialchars($profile_picture) . ')' : '' ?>">
      <?php if (empty($profile_picture)): ?>
        <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
      <?php endif; ?>
    </div>
    <div class="user-details">
      <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
      <div class="user-role"><?= htmlspecialchars($role) ?></div>
    </div>
  </div>

  <nav>
    <div class="nav-section">
      <div class="small-heading">Menu</div>

      <a href="dashboard.php" class="<?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-simple"></i>
        <span>Dashboard</span>
      </a>

      <?php if ($role === 'admin'): ?>
        <a href="appointments.php" class="<?= ($current_page === 'appointments.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-check"></i>
          <span>Appointments</span>
        </a>
        <a href="patients.php" class="<?= ($current_page === 'patients.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-user"></i>
          <span>Patients</span>
        </a>
        <a href="dentists.php" class="<?= ($current_page === 'dentists.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-user-doctor"></i>
          <span>Dentists</span>
        </a>
        <a href="secretaries.php" class="<?= ($current_page === 'secretaries.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-user-nurse"></i>
          <span>Secretaries</span>
        </a>
        <a href="services.php" class="<?= ($current_page === 'services.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-tooth"></i>
          <span>Services</span>
        </a>
        <a href="schedules.php" class="<?= ($current_page === 'schedules.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-alt"></i>
          <span>Schedules</span>
        </a>
        <a href="reports.php" class="<?= ($current_page === 'reports.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-chart-line"></i>
          <span>Reports</span>
        </a>

      <?php elseif ($role === 'patient'): ?>
        <a href="appointments.php" class="<?= ($current_page === 'appointments.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-check"></i>
          <span>My Appointments</span>
        </a>
        <a href="records.php" class="<?= ($current_page === 'records.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-book-medical"></i>
          <span>My Records</span>
        </a>

      <?php elseif ($role === 'dentist'): ?>
        <a href="appointments.php" class="<?= ($current_page === 'appointments.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-check"></i>
          <span>Appointments</span>
        </a>
        <a href="patient_records.php" class="<?= ($current_page === 'patient_records.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-user-md"></i>
          <span>Patient Records</span>
        </a>
        <a href="schedules.php" class="<?= ($current_page === 'schedules.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-alt"></i>
          <span>My Schedule</span>
        </a>

      <?php elseif ($role === 'secretary'): ?>
        <a href="appointments.php" class="<?= ($current_page === 'appointments.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-check"></i>
          <span>Appointments</span>
        </a>
        <a href="schedules.php" class="<?= ($current_page === 'schedules.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-calendar-alt"></i>
          <span>Schedules</span>
        </a>
        <a href="patients.php" class="<?= ($current_page === 'patients.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-user"></i>
          <span>Patients</span>
        </a>
        <!-- <a href="services.php" class="<?= ($current_page === 'services.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-tooth"></i>
          <span>Services</span>
        </a> -->
      <?php endif; ?>
    </div>

    <div class="nav-section">
      <div class="small-heading">General</div>
      <a href="settings.php" class="<?= ($current_page === 'settings.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-cog"></i>
        <span>Settings</span>
      </a>
    </div>

    <a href="../logout.php" class="logout">
      <i class="fa-solid fa-sign-out-alt"></i>
      <span>Logout</span>
    </a>
  </nav>
</div>

<script>
  // Mobile Sidebar Toggle
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebar-toggle');
  const sidebarOverlay = document.getElementById('sidebar-overlay');

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
      sidebar.classList.toggle('active');
      sidebarOverlay.classList.toggle('active');
    });
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function() {
      sidebar.classList.remove('active');
      sidebarOverlay.classList.remove('active');
    });
  }

  // Close sidebar when clicking a link on mobile
  const navLinks = document.querySelectorAll('.sidebar a');
  navLinks.forEach(link => {
    link.addEventListener('click', function() {
      if (window.innerWidth <= 968) {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
      }
    });
  });
</script>
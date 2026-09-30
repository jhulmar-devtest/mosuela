<?php
include 'includes/db.php';
$page_title = 'Our Doctors';

// Fetch all active dentists with their services
$dentists_query = "
  SELECT u.id, u.name, u.email, u.phone,
         GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as services
  FROM users u
  LEFT JOIN dentist_services ds ON u.id = ds.dentist_id
  LEFT JOIN services s ON ds.service_id = s.id
  WHERE u.role='dentist' AND u.status='active'
  GROUP BY u.id
  ORDER BY u.name
";
$dentists_result = $conn->query($dentists_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Miracle Mosuela Dental Clinic - The Right Care for a Perfect Bite and a Dazzling Smile</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    .container {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* Header Styles */
    header {
      background-color: rgba(255, 255, 255, 0.98);
      padding: 20px 0;
      box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 999;
      transition: all 0.3s ease;
    }

    header.scrolled {
      padding: 15px 0;
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.15);
    }

    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 1.5rem;
      font-weight: 700;
      transition: transform 0.3s ease;
    }

    .logo:hover {
      transform: scale(1.05);
    }

    .logo i {
      font-size: 2.5rem;
      color: var(--primary);
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.1);
      }
    }

    .logo .miracle {
      color: var(--primary);
    }

    .logo .mosuela {
      color: var(--secondary);
    }

    nav ul {
      display: flex;
      list-style: none;
      gap: 30px;
    }

    nav ul li a {
      text-decoration: none;
      color: var(--dark);
      font-weight: 600;
      font-size: 15px;
      position: relative;
      transition: color 0.3s;
    }

    nav ul li a::after {
      content: '';
      position: absolute;
      bottom: -5px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--primary);
      transition: width 0.3s ease;
    }

    nav ul li a:hover {
      color: var(--primary);
    }

    nav ul li a:hover::after {
      width: 100%;
    }

    .btn {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 12px 30px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-block;
      border: none;
      cursor: pointer;
      box-shadow: var(--shadow-primary);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-primary-lg);
    }

    /* Mobile Menu Toggle */
    .mobile-toggle {
      display: none;
      font-size: 1.8rem;
      color: var(--primary);
      cursor: pointer;
    }

    /* Hero Section */
    .hero {
      position: relative;
      height: 100vh;
      min-height: 700px;
      display: flex;
      align-items: center;
      color: white;
      background: linear-gradient(135deg, rgba(208, 0, 0, 0.9), rgba(157, 2, 8, 0.85)),
        url('bg.webp') center/cover no-repeat fixed;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at 30% 50%, rgba(233, 196, 106, 0.1), transparent);
      animation: gradientShift 10s ease infinite;
    }

    @keyframes gradientShift {

      0%,
      100% {
        opacity: 0.5;
      }

      50% {
        opacity: 1;
      }
    }

    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 700px;
      animation: fadeInUp 1s ease;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .hero h2 {
      font-size: 3.5rem;
      margin-bottom: 20px;
      font-weight: 800;
      line-height: 1.2;
      text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    }

    .hero p {
      font-size: 1.3rem;
      margin-bottom: 35px;
      line-height: 1.8;
      opacity: 0.95;
    }

    .hero .btn {
      font-size: 1.1rem;
      padding: 15px 40px;
      background: white;
      color: var(--primary);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .hero .btn:hover {
      background: var(--secondary);
      color: var(--dark);
    }

    /* Stats Section */
    .stats-section {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      padding: 60px 0;
      color: white;
      margin-top: -60px;
      position: relative;
      z-index: 3;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 40px;
      text-align: center;
    }

    .stat-item {
      animation: fadeInUp 0.8s ease;
    }

    .stat-item i {
      font-size: 3rem;
      margin-bottom: 15px;
      color: var(--secondary);
    }

    .stat-number {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 10px;
      display: block;
    }

    .stat-label {
      font-size: 1.1rem;
      opacity: 0.9;
      font-weight: 500;
    }

    /* Section Styling */
    section {
      padding: 100px 0;
    }

    .section-title {
      text-align: center;
      margin-bottom: 60px;
      position: relative;
    }

    .section-title h2 {
      font-size: 2.8rem;
      color: var(--primary);
      margin-bottom: 15px;
      font-weight: 800;
    }

    .section-title p {
      font-size: 1.2rem;
      color: #666;
      max-width: 700px;
      margin: 0 auto;
    }

    .section-title::after {
      content: '';
      display: block;
      width: 80px;
      height: 4px;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      margin: 20px auto 0;
      border-radius: 2px;
    }

    /* About Section */
    .about {
      background: white;
    }

    .about-content {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
    }

    .about-text h3 {
      font-size: 2.2rem;
      color: var(--primary);
      margin-bottom: 20px;
      font-weight: 700;
    }

    .about-text p {
      font-size: 1.1rem;
      color: #555;
      line-height: 1.8;
      margin-bottom: 20px;
    }

    .about-features {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-top: 30px;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 15px;
      background: var(--light);
      border-radius: 12px;
      transition: transform 0.3s ease;
    }

    .feature-item:hover {
      transform: translateX(5px);
      background: #fff3e0;
    }

    .feature-item i {
      font-size: 1.5rem;
      color: var(--primary);
    }

    .about-image {
      position: relative;
    }

    .about-image img {
      width: 100%;
      border-radius: 20px;
      box-shadow: var(--shadow-lg);
      transition: transform 0.3s ease;
    }

    .about-image:hover img {
      transform: scale(1.02);
    }

    /* Mission Vision Cards */
    .mv-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 40px;
    }

    .mv-card {
      background: white;
      padding: 40px;
      border-radius: 20px;
      box-shadow: var(--shadow);
      text-align: center;
      transition: all 0.3s ease;
      border-top: 5px solid var(--primary);
    }

    .mv-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-lg);
    }

    .mv-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 25px;
      color: white;
      font-size: 2rem;
      box-shadow: var(--shadow-primary);
    }

    .mv-card h3 {
      color: var(--primary);
      margin-bottom: 20px;
      font-size: 1.8rem;
      font-weight: 700;
    }

    .mv-card p {
      color: #666;
      line-height: 1.8;
      font-size: 1.05rem;
    }

    /* Values Grid */
    .values-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 30px;
      margin-top: 50px;
    }

    .value-card {
      background: white;
      padding: 35px 25px;
      border-radius: 15px;
      box-shadow: var(--shadow);
      text-align: center;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .value-card:hover {
      transform: translateY(-8px);
      border-color: var(--secondary);
      box-shadow: var(--shadow-lg);
    }

    .value-icon {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      background: linear-gradient(135deg, #fff0f0, #ffdddd);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      color: var(--primary);
      font-size: 1.8rem;
    }

    .value-card h4 {
      color: var(--primary);
      margin-bottom: 12px;
      font-size: 1.3rem;
      font-weight: 700;
    }

    .value-card p {
      color: #666;
      line-height: 1.6;
    }

    /* Clinic Layout Zone Sections */
    .zone-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
      margin: 80px auto;
      background: white;
      border-radius: 25px;
      overflow: hidden;
      box-shadow: var(--shadow-lg);
    }

    .zone-section:nth-child(even) {
      direction: rtl;
    }

    .zone-section:nth-child(even)>* {
      direction: ltr;
    }

    .zone-content {
      padding: 50px;
    }

    .zone-title {
      font-size: 2rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 10px;
    }

    .zone-subtitle {
      color: var(--secondary);
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 20px;
    }

    .zone-content>p {
      color: #666;
      font-size: 1.05rem;
      line-height: 1.7;
      margin-bottom: 20px;
    }

    .facilities-list {
      list-style: none;
      padding: 0;
    }

    .facilities-list li {
      padding: 12px 0;
      padding-left: 30px;
      color: #555;
      font-weight: 500;
      position: relative;
    }

    .facilities-list li::before {
      content: '✓';
      position: absolute;
      left: 0;
      color: var(--primary);
      font-weight: bold;
      font-size: 1.2rem;
    }

    .zone-image {
      height: 450px;
      background-size: cover;
      background-position: center;
      position: relative;
    }

    .zone-image::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(to right, rgba(0, 0, 0, 0.1), transparent);
    }

    /* Services Scroll */
    .services-scroll {
      background: var(--light);
      padding: 100px 0;
      overflow: hidden;
    }

    .scroll-track {
      display: flex;
      gap: 30px;
      animation: scroll-left 40s linear infinite;
    }

    .scroll-track:hover {
      animation-play-state: paused;
    }

    .service-item {
      flex-shrink: 0;
      width: 380px;
      position: relative;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: transform 0.3s ease;
    }

    .service-item:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-lg);
    }

    .service-item img {
      width: 100%;
      height: 420px;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .service-item:hover img {
      transform: scale(1.1);
    }

    .service-item p {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 20px;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.9), transparent);
      color: white;
      font-size: 1.2rem;
      font-weight: 700;
      text-align: center;
      margin: 0;
    }

    @keyframes scroll-left {
      0% {
        transform: translateX(0);
      }

      100% {
        transform: translateX(-50%);
      }
    }

    .learn-more-btn {
      margin-top: 50px;
      background: var(--primary);
      color: white;
      padding: 15px 40px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 600;
      display: inline-block;
      transition: all 0.3s;
      box-shadow: var(--shadow-primary);
    }

    .learn-more-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-primary-lg);
    }

    /* Doctors Section */
    .doctors-section {
      background: white;
    }

    .doctors-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 40px;
    }

    .doctor-card {
      background: white;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .doctor-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-lg);
      border-color: var(--secondary);
    }

    .doctor-avatar {
      width: 160px;
      height: 160px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 4rem;
      color: white;
      font-weight: 800;
      margin: 40px auto 25px;
      box-shadow: var(--shadow-primary);
      position: relative;
    }

    .doctor-avatar::after {
      content: '';
      position: absolute;
      inset: -5px;
      border-radius: 50%;
      border: 3px solid var(--secondary);
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .doctor-card:hover .doctor-avatar::after {
      opacity: 1;
    }

    .doctor-info {
      padding: 0 35px 35px;
      text-align: center;
    }

    .doctor-info h3 {
      color: var(--primary);
      font-size: 1.6rem;
      margin-bottom: 8px;
      font-weight: 700;
    }

    .doctor-title {
      color: var(--secondary);
      font-weight: 600;
      margin-bottom: 20px;
      font-size: 1.05rem;
    }

    .doctor-specialties {
      background: var(--light);
      padding: 20px;
      border-radius: 12px;
      margin: 20px 0;
      text-align: left;
    }

    .doctor-specialties h4 {
      color: var(--primary);
      font-size: 0.9rem;
      margin-bottom: 10px;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 1px;
    }

    .doctor-specialties p {
      color: #666;
      font-size: 0.95rem;
      line-height: 1.7;
    }

    .doctor-contact {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 20px;
    }

    .doctor-contact span {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      color: #666;
      font-size: 0.95rem;
    }

    /* Testimonials */
    .testimonials {
      background: var(--light);
    }

    .testimonial-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 40px;
    }

    .testimonial-card {
      background: white;
      padding: 40px;
      border-radius: 20px;
      box-shadow: var(--shadow);
      border-left: 5px solid var(--secondary);
      transition: all 0.3s ease;
    }

    .testimonial-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
    }

    .testimonial-card p {
      font-style: italic;
      margin-bottom: 25px;
      color: #555;
      line-height: 1.8;
      font-size: 1.05rem;
    }

    .client {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .client img {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--secondary);
    }

    .client h4 {
      color: var(--primary);
      font-weight: 700;
      margin-bottom: 5px;
    }

    .client p {
      color: #999;
      font-size: 0.9rem;
      margin: 0;
      font-style: normal;
    }

    /* CTA Section */
    .appointment-cta {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      text-align: center;
      padding: 100px 20px;
      position: relative;
      overflow: hidden;
    }

    .appointment-cta::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle, rgba(233, 196, 106, 0.2), transparent);
      animation: rotate 20s linear infinite;
    }

    @keyframes rotate {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .appointment-cta h2 {
      font-size: 3rem;
      margin-bottom: 20px;
      font-weight: 800;
      position: relative;
      z-index: 2;
    }

    .appointment-cta p {
      font-size: 1.3rem;
      margin-bottom: 40px;
      opacity: 0.95;
      position: relative;
      z-index: 2;
    }

    .appointment-cta .btn {
      background: white;
      color: var(--primary);
      font-size: 1.1rem;
      padding: 15px 45px;
      position: relative;
      z-index: 2;
    }

    .appointment-cta .btn:hover {
      background: var(--secondary);
      color: var(--dark);
    }

    /* Footer */
    footer {
      background: linear-gradient(135deg, var(--dark), #2a2a2a);
      color: white;
      padding: 80px 0 30px;
    }

    .footer-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 50px;
      margin-bottom: 50px;
    }

    .footer-section h3 {
      color: var(--secondary);
      margin-bottom: 25px;
      font-size: 1.5rem;
      font-weight: 700;
    }

    .footer-section p,
    .footer-section li {
      margin-bottom: 12px;
      color: rgba(255, 255, 255, 0.8);
      line-height: 1.8;
    }

    .footer-section ul {
      list-style: none;
    }

    .footer-section a {
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      transition: all 0.3s;
      display: inline-block;
    }

    .footer-section a:hover {
      color: var(--secondary);
      transform: translateX(5px);
    }

    .social-icons {
      display: flex;
      gap: 15px;
      margin-top: 20px;
    }

    .social-icons a {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      transition: all 0.3s;
    }

    .social-icons a:hover {
      background: var(--secondary);
      color: var(--dark);
      transform: translateY(-3px);
    }

    .copyright {
      text-align: center;
      padding-top: 30px;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      color: rgba(255, 255, 255, 0.6);
    }

    /* Responsive Design */
    @media (max-width: 968px) {
      nav ul {
        display: none;
      }

      .mobile-toggle {
        display: block;
      }

      .hero h2 {
        font-size: 2.5rem;
      }

      .about-content,
      .zone-section {
        grid-template-columns: 1fr;
        gap: 40px;
      }

      .zone-section:nth-child(even) {
        direction: ltr;
      }

      .section-title h2 {
        font-size: 2.2rem;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .mv-grid,
      .values-grid {
        grid-template-columns: 1fr;
      }

      .doctors-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 600px) {
      .hero h2 {
        font-size: 2rem;
      }

      .hero p {
        font-size: 1.1rem;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }

      .footer-content {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <!-- Header -->
  <header id="header">
    <div class="container header-content">
      <div class="logo">
        <img src="image/miracle-logo.png" alt="" style="height: 50px;">
        <h1><span class="miracle">Miracle</span><span class="mosuela"> Mosuela</span></h1>
      </div>
      <nav>
        <ul>
          <li><a href="#home">Home</a></li>
          <li><a href="#about">About Us</a></li>
          <li><a href="#clinic-layout">Clinic</a></li>
          <li><a href="#services">Services</a></li>
          <li><a href="#dentists">Dentists</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </nav>
      <a href="login.php" class="btn">Sign In</a>
      <div class="mobile-toggle">
        <i class="fas fa-bars"></i>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="hero" id="home">
    <div class="container">
      <div class="hero-content">
        <h2>The Right Care for a Perfect Bite and a Dazzling Smile</h2>
        <p>Experience exceptional dental care with state-of-the-art technology and a compassionate team dedicated to your oral health and beautiful smile.</p>
        <a href="patient/book_appointment.php" class="btn">Schedule Your Visit</a>
      </div>
    </div>
  </section>

  <!-- Stats Section -->
  <!-- <section class="stats-section">
    <div class="container">
      <div class="stats-grid">
        <div class="stat-item">
          <i class="fas fa-smile-beam"></i>
          <span class="stat-number">5000+</span>
          <span class="stat-label">Happy Patients</span>
        </div>
        <div class="stat-item">
          <i class="fas fa-award"></i>
          <span class="stat-number">20+</span>
          <span class="stat-label">Years Experience</span>
        </div>
        <div class="stat-item">
          <i class="fas fa-user-md"></i>
          <span class="stat-number">15+</span>
          <span class="stat-label">Expert Dentists</span>
        </div>
        <div class="stat-item">
          <i class="fas fa-star"></i>
          <span class="stat-number">4.9</span>
          <span class="stat-label">Average Rating</span>
        </div>
      </div>
    </div>
  </section> -->

  <!-- About Section -->
  <section class="about" id="about">
    <div class="container">
      <div class="about-content">
        <div class="about-text">
          <h3>Why Choose Miracle Mosuela Dental Clinic</h3>
          <p>At Miracle Mosuela Dental, we combine cutting-edge technology with a gentle, compassionate touch to make your dental experience as comfortable as possible. Our team of experienced professionals is dedicated to providing personalized care tailored to each patient's unique needs.</p>
          <p>We accept most insurance plans and offer flexible payment options to ensure quality dental care is accessible to everyone in our community.</p>

          <div class="about-features">
            <div class="feature-item">
              <i class="fas fa-check-circle"></i>
              <span>Advanced Technology</span>
            </div>
            <div class="feature-item">
              <i class="fas fa-check-circle"></i>
              <span>Experienced Team</span>
            </div>
            <div class="feature-item">
              <i class="fas fa-check-circle"></i>
              <span>Flexible Payment Plans</span>
            </div>
            <div class="feature-item">
              <i class="fas fa-check-circle"></i>
              <span>Comfortable Environment</span>
            </div>
          </div>

          <a href="#mission" class="btn" style="margin-top: 30px;">Learn More About Us</a>
        </div>
        <div class="about-image">
          <img src="image/clinic.png" alt="Modern dental clinic interior">
        </div>
      </div>
    </div>
  </section>

  <!-- Mission, Vision, Values Section -->
  <section class="mission-vision" id="mission" style="background: var(--light); padding: 100px 0;">
    <div class="container">
      <div class="section-title">
        <h2>Our Mission, Vision & Values</h2>
        <p>The principles that guide everything we do</p>
      </div>

      <div class="mv-grid">
        <div class="mv-card">
          <div class="mv-icon">
            <i class="fas fa-heartbeat"></i>
          </div>
          <h3>Our Mission</h3>
          <p>To transform lives by delivering advanced dental care that powerfully boosts both systemic health and personal confidence, ensuring every smile we create is a "Miracle Mosuela" of vitality and self-assurance.</p>
        </div>

        <div class="mv-card">
          <div class="mv-icon">
            <i class="fas fa-eye"></i>
          </div>
          <h3>Our Vision</h3>
          <p>To be the community's recognized leader in Health-Centric Dentistry, where innovation and personalized care converge to unlock unparalleled oral well-being and empower every patient with enduring, radiant confidence.</p>
        </div>
      </div>

      <div class="section-title" style="margin-top: 80px; margin-bottom: 50px;">
        <h2>Our Core Values</h2>
        <p>The foundations of our exceptional dental care</p>
      </div>

      <div class="values-grid">
        <div class="value-card">
          <div class="value-icon">
            <i class="fas fa-globe-americas"></i>
          </div>
          <h4>Holistic Vitality</h4>
          <p>Treating the mouth as the gateway to overall health, ensuring comprehensive care that benefits your entire wellbeing.</p>
        </div>

        <div class="value-card">
          <div class="value-icon">
            <i class="fas fa-smile-beam"></i>
          </div>
          <h4>Smile Empowerment</h4>
          <p>Magnifying personal confidence through exceptional dental care that helps you smile with pride and assurance.</p>
        </div>

        <div class="value-card">
          <div class="value-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h4>Unwavering Integrity</h4>
          <p>Practicing honesty and transparency in all our interactions, building trust with every patient.</p>
        </div>

        <div class="value-card">
          <div class="value-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <h4>Relentless Growth</h4>
          <p>Using the latest technology and continuous learning to provide the most advanced dental care available.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Clinic Layout Section -->
  <section style="background: white; padding: 100px 0;" id="clinic-layout">
    <div class="container">
      <div class="section-title">
        <h2>Explore Our Clinic Layout</h2>
        <p>Designed to ensure comfort, efficiency, and excellent patient care through well-planned zones</p>
      </div>
    </div>

    <!-- Confidence Zone -->
    <div class="container">
      <div class="zone-section">
        <div class="zone-content">
          <h2 class="zone-title">The Confidence Zone</h2>
          <p class="zone-subtitle">Patient Welcome and Administrative Area</p>
          <p>Where your visit begins — designed to provide a warm, welcoming experience while ensuring seamless patient service.</p>
          <ul class="facilities-list">
            <li>Reception</li>
            <li>Waiting Area</li>
            <li>Consultation Room</li>
          </ul>
        </div>
        <div class="zone-image" style="background-image: url('image/front.jpg');"></div>
      </div>

      <!-- Vitality Zone -->
      <div class="zone-section">
        <div class="zone-content">
          <h2 class="zone-title">The Vitality Zone</h2>
          <p class="zone-subtitle">Clinical and Treatment Area</p>
          <p>The heart of dental care — equipped with advanced tools to deliver precision treatment in a clean and efficient environment.</p>
          <ul class="facilities-list">
            <li>Operatories (Treatment Rooms)</li>
            <li>Imaging Suite</li>
            <li>Sterilization Center</li>
          </ul>
        </div>
        <div class="zone-image" style="background-image: url('image/facility.jpg');"></div>
      </div>

      <!-- Staff and Support Zone -->
      <div class="zone-section">
        <div class="zone-content">
          <h2 class="zone-title">Staff and Support Area</h2>
          <p class="zone-subtitle">Operational Excellence Zone</p>
          <p>Behind-the-scenes spaces designed for staff well-being, coordination, and operational efficiency.</p>
          <ul class="facilities-list">
            <li>Staff Lounge</li>
            <li>Administrative Office</li>
            <li>Plant Room</li>
          </ul>
        </div>
        <div class="zone-image" style="background-image: url('image/staffroom.jpg');"></div>
      </div>
    </div>
  </section>

  <!-- Services Scroll Section -->
  <section class="services-scroll" id="services">
    <div class="container">
      <div class="section-title">
        <h2>Our Dental Services</h2>
        <p>Comprehensive dental care for all your oral health needs</p>
      </div>

      <div class="scroll-container">
        <div class="scroll-track">
          <div class="service-item">
            <img src="image/cleaning.jpg" alt="Teeth Cleaning">
            <p>Teeth Cleaning</p>
          </div>
          <div class="service-item">
            <img src="image/extraction.webp" alt="Tooth Extraction">
            <p>Tooth Extraction</p>
          </div>
          <div class="service-item">
            <img src="image/filling.jpg" alt="Filling">
            <p>Dental Filling</p>
          </div>
          <div class="service-item">
            <img src="image/oralprop.jpg" alt="Root Canal Treatment">
            <p>Root Canal Treatment</p>
          </div>
          <div class="service-item">
            <img src="image/whitening.jpg" alt="Tooth Whitening">
            <p>Tooth Whitening</p>
          </div>
          <div class="service-item">
            <img src="image/checkup.jpg" alt="Dental Checkups">
            <p>Dental Checkups</p>
          </div>
          <div class="service-item">
            <img src="image/brace.jpg" alt="Orthodontic Consultation">
            <p>Orthodontic Consultation</p>
          </div>
          <div class="service-item">
            <img src="image/xray.jpg" alt="Dental X-Ray">
            <p>Dental X-Ray</p>
          </div>
          <div class="service-item">
            <img src="image/restoration.jpg" alt="Tooth Restorations">
            <p>Tooth Restorations</p>
          </div>
          <div class="service-item">
            <img src="image/dentures.jpg" alt="Full Denture">
            <p>Full Denture</p>
          </div>

          <!-- Duplicate for seamless loop -->
          <div class="service-item">
            <img src="image/cleaning.jpg" alt="Teeth Cleaning">
            <p>Teeth Cleaning</p>
          </div>
          <div class="service-item">
            <img src="image/extraction.webp" alt="Tooth Extraction">
            <p>Tooth Extraction</p>
          </div>
          <div class="service-item">
            <img src="image/filling.jpg" alt="Filling">
            <p>Dental Filling</p>
          </div>
          <div class="service-item">
            <img src="image/oralprop.jpg" alt="Root Canal Treatment">
            <p>Root Canal Treatment</p>
          </div>
          <div class="service-item">
            <img src="image/whitening.jpg" alt="Tooth Whitening">
            <p>Tooth Whitening</p>
          </div>
          <div class="service-item">
            <img src="image/checkup.jpg" alt="Dental Checkups">
            <p>Dental Checkups</p>
          </div>
          <div class="service-item">
            <img src="image/brace.jpg" alt="Orthodontic Consultation">
            <p>Orthodontic Consultation</p>
          </div>
          <div class="service-item">
            <img src="image/xray.jpg" alt="Dental X-Ray">
            <p>Dental X-Ray</p>
          </div>
          <div class="service-item">
            <img src="image/restoration.jpg" alt="Tooth Restorations">
            <p>Tooth Restorations</p>
          </div>
          <div class="service-item">
            <img src="image/dentures.jpg" alt="Full Denture">
            <p>Full Denture</p>
          </div>
        </div>
      </div>

      <div style="text-align: center;">
        <a href="services.php" class="learn-more-btn">View All Services</a>
      </div>
    </div>
  </section>

  <!-- Doctors Section -->
  <section class="doctors-section" id="dentists">
    <div class="container">
      <div class="section-title">
        <h2>Meet Our Expert Dentists</h2>
        <p>Highly qualified and compassionate dental professionals dedicated to your care</p>
      </div>

      <?php if ($dentists_result->num_rows > 0): ?>
        <div class="doctors-grid">
          <?php while ($dentist = $dentists_result->fetch_assoc()): ?>
            <div class="doctor-card">
              <div class="doctor-avatar">
                <?= strtoupper(substr($dentist['name'], 0, 1)) ?>
              </div>
              <div class="doctor-info">
                <h3>Dr. <?= htmlspecialchars($dentist['name']) ?></h3>
                <div class="doctor-title">Dental Specialist</div>

                <?php if ($dentist['services']): ?>
                  <div class="doctor-specialties">
                    <h4>Specializations</h4>
                    <p><?= htmlspecialchars($dentist['services']) ?></p>
                  </div>
                <?php endif; ?>

                <div class="doctor-contact">
                  <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($dentist['email']) ?></span>
                  <?php if ($dentist['phone']): ?>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($dentist['phone']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <p style="text-align: center; color: #666; font-size: 18px; padding: 40px 0;">No dentists available at the moment.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- Testimonials Section -->
  <!-- <section class="testimonials">
    <div class="container">
      <div class="section-title">
        <h2>What Our Patients Say</h2>
        <p>Real experiences from real people</p>
      </div>

      <div class="testimonial-grid">
        <div class="testimonial-card">
          <p>"I've never felt more comfortable at a dental clinic. The staff is amazing and they really care about their patients. Dr. Mosuela is exceptional and takes time to explain everything."</p>
          <div class="client">
            <img src="https://randomuser.me/api/portraits/women/65.jpg" alt="Kristine Jardino">
            <div>
              <h4>Kristine Jardino</h4>
              <p>Patient for 5 years</p>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <p>"The technology they use is impressive. My procedure was quick and virtually painless. The entire team is professional and friendly. Highly recommend!"</p>
          <div class="client">
            <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Waren Sabilo">
            <div>
              <h4>Waren Sabilo</h4>
              <p>Patient for 2 years</p>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <p>"Best dental experience I've ever had! The clinic is beautiful, the staff is welcoming, and the care is top-notch. They made me feel at ease from start to finish."</p>
          <div class="client">
            <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Maria Santos">
            <div>
              <h4>Maria Santos</h4>
              <p>Patient for 3 years</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section> -->

  <!-- Appointment CTA -->
  <section class="appointment-cta">
    <div class="container">
      <h2>Ready for a Healthier Smile?</h2>
      <p>Schedule your appointment today and experience the difference</p>
      <a href="login.php" class="btn">Book Your Visit Now</a>
    </div>
  </section>

  <!-- Footer -->
  <footer id="contact">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <h3>Miracle Mosuela Dental</h3>
          <p>Providing quality dental care with a personal touch. Your smile is our priority, and your health is our commitment.</p>
          <div class="social-icons">
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-linkedin"></i></a>
          </div>
        </div>

        <div class="footer-section">
          <h3>Quick Links</h3>
          <ul>
            <li><a href="#about">About Us</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#dentists">Our Dentists</a></li>
            <li><a href="login.php">Book Appointment</a></li>
          </ul>
        </div>

        <div class="footer-section">
          <h3>Contact Info</h3>
          <p><i class="fas fa-map-marker-alt"></i> 123 Dental Avenue, Health City</p>
          <p><i class="fas fa-phone"></i> (555) 123-4567</p>
          <p><i class="fas fa-envelope"></i> info@brightbite.com</p>
        </div>

        <div class="footer-section">
          <h3>Clinic Hours</h3>
          <p><strong>Monday - Friday</strong><br>8:00 AM - 6:00 PM</p>
          <p><strong>Saturday</strong><br>9:00 AM - 2:00 PM</p>
          <p><strong>Sunday</strong><br>Closed</p>
        </div>
      </div>

      <div class="copyright">
        <p>&copy; 2025 Miracle Mosuela Dental Clinic. All rights reserved. | Designed with care for your smile</p>
      </div>
    </div>
  </footer>

  <script>
    // Header scroll effect
    window.addEventListener('scroll', function() {
      const header = document.getElementById('header');
      if (window.scrollY > 50) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });
  </script>
</body>

</html>
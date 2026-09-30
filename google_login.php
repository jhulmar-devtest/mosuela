<?php
session_start();
header('Content-Type: application/json');

// Include database connection
include 'includes/db.php';

// Check for database connection errors
if (!$conn) {
  echo json_encode(["status" => "failed", "message" => "Database connection error"]);
  exit;
}

// Require Google API client
require 'vendor/autoload.php';

// Get JSON input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data || !isset($data['token'])) {
  echo json_encode(["status" => "failed", "message" => "No token provided"]);
  exit;
}

$token = trim($data['token']);

try {
  // Initialize Google Client
  $client = new Google_Client();
  $client->setClientId(getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID');

  // Set HTTP client with SSL certificate verification
  $httpClient = new \GuzzleHttp\Client([
    'verify' => getenv('SSL_CA_BUNDLE') ?: true
  ]);
  $client->setHttpClient($httpClient);

  // Verify the token
  $payload = $client->verifyIdToken($token);

  if (!$payload) {
    echo json_encode(["status" => "failed", "message" => "Invalid token"]);
    exit;
  }

  // Extract user data from payload
  $email = isset($payload['email']) ? $payload['email'] : null;
  $name = isset($payload['name']) ? $payload['name'] : 'Google User';

  if (!$email) {
    echo json_encode(["status" => "failed", "message" => "Email not found in token"]);
    exit;
  }

  // Check if user exists
  $stmt = $conn->prepare("SELECT id, role, name FROM users WHERE email = ?");
  if (!$stmt) {
    echo json_encode(["status" => "failed", "message" => "Database error"]);
    exit;
  }

  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows == 0) {
    // New Google users can ONLY be created as PATIENTS
    // This prevents unauthorized creation of Admin, Dentist, or Secretary accounts
    $role = "patient";
    $password_hash = null;

    $insert = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");

    if (!$insert) {
      echo json_encode(["status" => "failed", "message" => "Failed to create user"]);
      exit;
    }

    $insert->bind_param("ssss", $name, $email, $password_hash, $role);
    if (!$insert->execute()) {
      echo json_encode(["status" => "failed", "message" => "Failed to insert user"]);
      exit;
    }
    $user_id = $insert->insert_id;
    $insert->close();
  } else {
    // User exists - use their role from database
    // SECURITY: Role comes from DB only, never from user input
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $role = $user['role'];  // Role MUST come from database
    $name = $user['name'];
  }

  $stmt->close();

  // Validate role is valid (security check)
  $allowed_roles = ['admin', 'dentist', 'secretary', 'patient'];
  if (!in_array($role, $allowed_roles)) {
    echo json_encode(["status" => "failed", "message" => "Invalid user role"]);
    exit;
  }

  // Create session
  $_SESSION['user_id'] = $user_id;
  $_SESSION['user_name'] = $name;
  $_SESSION['user_email'] = $email;
  $_SESSION['user_role'] = $role;

  // Determine redirect by role (auto-detected from database)
  $redirect = "login.php"; // default fallback
  if ($role === 'admin') {
    $redirect = "admin/dashboard.php";
  } elseif ($role === 'dentist') {
    $redirect = "dentist/dashboard.php";
  } elseif ($role === 'secretary') {
    $redirect = "secretary/dashboard.php";
  } elseif ($role === 'patient') {
    $redirect = "patient/dashboard.php";
  }

  echo json_encode(["status" => "success", "redirect" => $redirect]);
  exit;
} catch (Exception $e) {
  echo json_encode(["status" => "failed", "message" => "Token verification error: " . $e->getMessage()]);
  exit;
}

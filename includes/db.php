<?php
// Database connection for local/deployment environments.
// Credentials are intentionally not hard-coded for the public repository.

$db_server = getenv('DB_HOST') ?: 'localhost';
$db_user   = getenv('DB_USER') ?: 'root';
$db_pass   = getenv('DB_PASS') ?: '';
$db_name   = getenv('DB_NAME') ?: 'mosuela_db';

$conn = new mysqli($db_server, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

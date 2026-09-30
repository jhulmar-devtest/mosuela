<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Validate service ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
  echo "<script>alert('Invalid service ID.'); window.location.href='services.php';</script>";
  exit();
}

$service_id = intval($_GET['id']);

// Fetch existing service data
$stmt = $conn->prepare("SELECT name, description, price, duration_minutes, status FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Service not found.'); window.location.href='services.php';</script>";
  exit();
}

$service = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $price = trim($_POST['price']);
  $duration = trim($_POST['duration']);
  $status = trim($_POST['status']);

  $stmt = $conn->prepare("UPDATE services SET name = ?, description = ?, price = ?, duration_minutes = ?, status = ? WHERE id = ?");
  $stmt->bind_param("ssdssi", $name, $description, $price, $duration, $status, $service_id);

  if ($stmt->execute()) {
    echo "<script>alert('Service updated successfully!'); window.location.href='services.php';</script>";
  } else {
    echo "<script>alert('Error updating service: " . $conn->error . "');</script>";
  }

  $stmt->close();
  $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Edit Service - Miracle Mosuela</title>
  <style>
    /* ===== Topbar ===== */
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

    /* ===== Card Container ===== */
    .details-container {
      background: var(--card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 32px;
      max-width: 900px;
      margin: 0 auto 24px;
      transition: box-shadow 0.3s ease;
    }

    .details-container:hover {
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
    }

    /* ===== Header ===== */
    .back-link {
      display: inline-block;
      margin-bottom: 16px;
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    h1,
    h2 {
      color: var(--primary);
      margin-bottom: 20px;
    }

    h1 {
      font-size: 26px;
      font-weight: 700;
    }

    h2 {
      font-size: 20px;
      font-weight: 600;
      border-bottom: 1px solid var(--border);
      padding-bottom: 8px;
    }

    /* ===== Form ===== */
    form {
      margin-top: 10px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #374151;
    }

    input,
    select,
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 15px;
      font-family: inherit;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      background-color: white;
    }

    input:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    /* ===== Actions ===== */
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }

    .btn-form-submit {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 12px 24px;
      border-radius: 6px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn-form-submit:hover {
      background: var(--primary-dark);
    }

    .cancel-btn {
      background: #f3f4f6;
      color: #374151;
      padding: 12px 24px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
      border: 1px solid #d1d5db;
      transition: all 0.2s ease;
    }

    .cancel-btn:hover {
      background: #e5e7eb;
    }

    @media (max-width: 900px) {
      .offset {
        margin-left: 0;
        padding: 16px;
      }

      .details-container {
        padding: 20px;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>Edit Service - <?= htmlspecialchars($service['name']); ?></h2>
      <div class="topbar-right"></div>
    </div>

    <div class="details-container">

      <form method="POST">
        <h2>Service Information</h2>

        <div class="form-group">
          <label for="name">Service Name</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($service['name']); ?>" required>
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description"
            required><?= htmlspecialchars($service['description']); ?></textarea>
        </div>

        <div class="form-group">
          <label for="price">Price (₱)</label>
          <input type="number" id="price" name="price" step="0.01" value="<?= htmlspecialchars($service['price']); ?>"
            required>
        </div>

        <div class="form-group">
          <label for="duration">Duration (minutes)</label>
          <select id="duration" name="duration" required>
            <option value="30" <?= $service['duration_minutes'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
            <option value="45" <?= $service['duration_minutes'] == 45 ? 'selected' : ''; ?>>45 minutes</option>
            <option value="60" <?= $service['duration_minutes'] == 60 ? 'selected' : ''; ?>>1 hour</option>
            <option value="90" <?= $service['duration_minutes'] == 90 ? 'selected' : ''; ?>>1 hour 30 mins</option>
            <option value="120" <?= $service['duration_minutes'] == 120 ? 'selected' : ''; ?>>2 hours</option>
          </select>
        </div>

        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status" required>
            <option value="active" <?= $service['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?= $service['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
          </select>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-form-submit">Update Service</button>
          <a href="services.php" class="cancel-btn">Cancel</a>
        </div>
      </form>

      <p><a href="services.php" class="back-link">← Back to Services</a></p>
    </div>
  </div>
</body>

</html>
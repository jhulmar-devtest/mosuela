<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: services.php");
  exit();
}

$service_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT id, name, description, price, duration_minutes, status 
                        FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<script>alert('Service not found.'); window.location.href='services.php';</script>";
  exit();
}

$service = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Service Details - Miracle Mosuela</title>
  <style>
    .details-container {
      background: var(--card);
      box-shadow: var(--shadow);
      border-radius: var(--radius-lg);
      padding: 30px 40px;
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Back Link */
    .back-link {
      text-decoration: none;
      color: var(--primary);
      font-weight: 500;
      display: inline-block;
      margin-bottom: 16px;
      transition: color 0.2s ease;
    }

    .back-link:hover {
      color: #21867a;
    }

    /* Header */
    .header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 25px;
    }

    .header-row h1 {
      font-size: 26px;
      color: var(--primary);
      margin: 0;
    }

    /* Buttons */
    .action-links {
      display: flex;
      gap: 10px;
    }

    .action-links a {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: var(--radius-lg);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .action-links a:first-child {
      background: var(--primary);
      color: #fff;
    }

    .action-links a:first-child:hover {
      background: #21867a;
    }

    .delete-link {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .delete-link:hover {
      background: #fecaca;
    }

    /* Section Headings */
    h2 {
      margin-top: 30px;
      font-size: 20px;
      color: #374151;
      margin-bottom: 12px;
      border-left: 4px solid var(--primary);
      padding-left: 10px;
    }

    /* Table */
    .details-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      overflow: hidden;
      margin-bottom: 20px;
    }

    .details-table th {
      background: #f8fafc;
      padding: 16px 20px;
      text-align: left;
      font-size: 15px;
      color: var(--muted);
      text-transform: uppercase;
      font-weight: 600;
    }

    .details-table td {
      padding: 16px 20px;
      border-bottom: 1px solid #e5e7eb;
      font-size: 15px;
      color: #1f2937;
    }

    .details-table tr:last-child td {
      border-bottom: none;
    }

    /* Description Box */
    .description-box {
      background: var(--card);
      box-shadow: var(--shadow);
      border-radius: var(--radius-lg);
      padding: 20px;
      font-size: 15px;
      line-height: 1.6;
      color: #374151;
    }

    /* Status Styles */
    .status-strong {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      text-transform: capitalize;
    }

    .status-active {
      background: #dcfce7;
      color: #166534;
    }

    .status-inactive {
      background: #fee2e2;
      color: #991b1b;
    }

    /* Responsive */
    @media (max-width: 900px) {
      .details-container {
        margin-left: 0;
        padding: 20px;
      }

      .header-row h1 {
        font-size: 22px;
      }

      .details-table th,
      .details-table td {
        padding: 10px 12px;
        font-size: 14px;
      }
    }

    /* Buttons */
    .action-links {
      display: flex;
      gap: 10px;
    }

    .action-links a {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: var(--radius-lg);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .action-links a:first-child {
      background: var(--primary);
      color: #fff;
    }

    .action-links a:first-child:hover {
      background: #21867a;
    }

    .delete-link {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .delete-link:hover {
      background: #fecaca;
    }

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
  </style>
</head>

<body>
  <div class="offset">
    <div class="topbar">
      <h2>Service Details - <?= htmlspecialchars($service['name']) ?></h2>
    </div>
    <div class="details-container">

      <h2>General Information</h2>
      <table class="details-table">
        <tr>
          <th>Field</th>
          <th>Information</th>
        </tr>
        <tr>
          <td><strong>ID</strong></td>
          <td><?= $service['id'] ?></td>
        </tr>
        <tr>
          <td><strong>Name</strong></td>
          <td><?= htmlspecialchars($service['name']) ?></td>
        </tr>
        <tr>
          <td><strong>Price</strong></td>
          <td>₱<?= number_format($service['price'], 2) ?></td>
        </tr>
        <tr>
          <td><strong>Duration</strong></td>
          <td><?= $service['duration_minutes'] ?> minutes</td>
        </tr>
        <tr>
          <td><strong>Status</strong></td>
          <td>
            <?php
            $status_class = strtolower($service['status']) === 'active' ? 'active' : 'inactive';
            echo "<span class='status-strong status-{$status_class}'>" . ucfirst($service['status']) . "</span>";
            ?>
          </td>
        </tr>
      </table>

      <h2>Description</h2>
      <div class="description-box">
        <?= nl2br(htmlspecialchars($service['description'])) ?>
      </div>
      <p><a href="services.php" class="back-link">← Back to Services</a></p>
    </div>
  </div>
</body>

</html>
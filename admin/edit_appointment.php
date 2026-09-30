<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

// 1. AUTHENTICATION
if ($_SESSION['user_role'] !== 'admin') {
  header("Location: ../login.php");
  exit();
}

// 2. GET APPOINTMENT ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
  header("Location: appointments.php");
  exit();
}

// 3. FETCH DATA
$appt_with_services = get_appointment_with_services($conn, $id);

if (!$appt_with_services['success']) {
  echo "<script>alert('Appointment not found'); window.location.href='appointments.php';</script>";
  exit();
}

$appointment = $appt_with_services;
$is_walkin = strpos($appointment['notes'], 'Walk-in:') === 0;

// Get IDs of currently selected services for pre-checking checkboxes
$current_service_ids = array_map(function ($s) {
  return $s['id'];
}, $appointment['services']);

// Fetch ALL active services for the selection list
$all_services = get_all_services($conn);

// Fetch Dentist Name for display
$dentist_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$dentist_stmt->bind_param("i", $appointment['dentist_id']);
$dentist_stmt->execute();
$dentist = $dentist_stmt->get_result()->fetch_assoc();
$dentist_name = htmlspecialchars($dentist['name'] ?? 'Unknown');
$dentist_stmt->close();

// Fetch Patient Name for Display
$patient_name = "Unknown";
if ($is_walkin) {
  $patient_name = htmlspecialchars(substr($appointment['notes'], 9));
} else {
  $patient_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
  $patient_stmt->bind_param("i", $appointment['patient_id']);
  $patient_stmt->execute();
  $res = $patient_stmt->get_result()->fetch_assoc();
  $patient_name = htmlspecialchars($res['name'] ?? 'Unknown');
  $patient_stmt->close();
}

// 4. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $status = trim($_POST['status'] ?? '');
  $appointment_date = $_POST['appointment_date'];
  $appointment_time = $_POST['appointment_time'];
  $notes = trim($_POST['notes']);
  $service_ids = isset($_POST['service_ids']) ? (array)$_POST['service_ids'] : [];

  // Validate status against allowed values
  $allowed_statuses = ['pending', 'approved', 'completed', 'cancelled'];
  if (!in_array($status, $allowed_statuses)) {
    echo "<script>alert('Error: Invalid appointment status.'); window.history.back();</script>";
    exit();
  }

  // Validation
  if (empty($service_ids)) {
    echo "<script>alert('Error: You must select at least one service.'); window.history.back();</script>";
    exit();
  }

  // Calculate new totals based on submitted services
  $totals = calculate_appointment_totals($conn, $service_ids);
  if (isset($totals['error'])) {
    echo "<script>alert('Error: " . htmlspecialchars($totals['error']) . "'); window.history.back();</script>";
    exit();
  }

  // Transaction
  $conn->begin_transaction();

  try {
    // A. Update Main Appointment Details
    $update_query = "UPDATE appointments SET status=?, appointment_date=?, appointment_time=?, notes=?, total_price=?, total_duration=? WHERE id=?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param(
      "ssssdii",
      $status,
      $appointment_date,
      $appointment_time,
      $notes,
      $totals['total_price'],
      $totals['total_duration'],
      $id
    );

    if (!$update_stmt->execute()) {
      throw new Exception('Update appointment error: ' . $update_stmt->error);
    }
    $update_stmt->close();

    // B. Sync Services (Delete Old -> Insert New)
    $delete_services = $conn->prepare("DELETE FROM appointment_services WHERE appointment_id = ?");
    $delete_services->bind_param("i", $id);
    $delete_services->execute();
    $delete_services->close();

    $insert_service = $conn->prepare("INSERT INTO appointment_services (appointment_id, service_id) VALUES (?, ?)");
    foreach ($service_ids as $service_id) {
      $insert_service->bind_param("ii", $id, $service_id);
      if (!$insert_service->execute()) {
        throw new Exception('Insert service error: ' . $insert_service->error);
      }
    }
    $insert_service->close();

    $conn->commit();
    echo "<script>alert('Appointment updated successfully!'); window.location.href='view_appointment.php?id=$id';</script>";
  } catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Error updating: " . $conn->error . "');</script>";
  }
  exit();
}

include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Appointment #<?= $appointment['id'] ?> - Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <style>
    /* VISUAL IDENTITY: 
       Exact copies of Secretary CSS for consistency.
       Added .service-label for interactive checkboxes.
    */

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--spacing-xl);
    }

    .topbar h1 {
      font-size: 24px;
      font-weight: var(--font-weight-bold);
      color: var(--dark);
    }

    .topbar-actions .btn-back {
      color: var(--muted);
      text-decoration: none;
      font-weight: var(--font-weight-medium);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition-fast);
    }

    .topbar-actions .btn-back:hover {
      color: var(--primary);
    }

    /* Grid Layout */
    .page-grid {
      display: grid;
      grid-template-columns: 2fr 1.2fr;
      gap: var(--spacing-xl);
    }

    @media (max-width: 1024px) {
      .page-grid {
        grid-template-columns: 1fr;
      }
    }

    .card {
      background: var(--white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: var(--spacing-xl);
      margin-bottom: var(--spacing-xl);
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--spacing-lg);
      padding-bottom: var(--spacing-md);
      border-bottom: 1px solid var(--gray-200);
    }

    .card-title {
      font-size: 18px;
      font-weight: var(--font-weight-bold);
      color: var(--gray-700);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-title i {
      color: var(--primary);
    }

    /* Form Elements */
    .form-group {
      margin-bottom: var(--spacing-lg);
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--spacing-md);
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: var(--font-weight-medium);
      color: var(--gray-700);
      font-size: 14px;
    }

    input[type="text"],
    input[type="date"],
    input[type="time"],
    select,
    textarea {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid var(--gray-300);
      border-radius: var(--radius-sm);
      font-size: 14px;
      color: var(--dark);
      transition: var(--transition-fast);
      background-color: var(--white);
    }

    input:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(208, 0, 0, 0.1);
    }

    /* Form Actions */
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: var(--spacing-md);
      margin-top: var(--spacing-xl);
    }

    .btn {
      padding: 12px 24px;
      border-radius: var(--radius-sm);
      font-weight: var(--font-weight-semibold);
      cursor: pointer;
      border: none;
      font-size: 14px;
      transition: var(--transition-fast);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn-cancel {
      background-color: var(--light);
      color: var(--gray-600);
    }

    .btn-cancel:hover {
      background-color: var(--gray-200);
    }

    .btn-primary {
      background-color: var(--primary);
      color: var(--white);
      box-shadow: var(--shadow-primary-sm);
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: var(--shadow-primary);
    }

    /* Right Column Styles */
    .info-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 12px;
      font-size: 14px;
    }

    .info-label {
      color: var(--muted);
    }

    .info-val {
      font-weight: var(--font-weight-medium);
      color: var(--dark);
      text-align: right;
    }

    .patient-avatar {
      width: 48px;
      height: 48px;
      background: var(--warning-bg);
      color: var(--warning);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      margin-right: 15px;
    }

    .patient-header {
      display: flex;
      align-items: center;
      margin-bottom: var(--spacing-md);
    }

    /* Service List Styling - Modified for Interaction */
    .service-list {
      list-style: none;
      margin: 0;
      padding: 0;
      max-height: 400px;
      overflow-y: auto;
      /* Scrollable if many services */
    }

    /* Admin Specific: Checkbox Label 
       Wraps the service item to make the whole row clickable
    */
    .service-label {
      display: flex;
      justify-content: space-between;
      padding: 10px 8px;
      border-bottom: 1px dashed var(--gray-200);
      font-size: 14px;
      cursor: pointer;
      transition: background-color 0.2s;
      border-radius: var(--radius-sm);
      align-items: start;
    }

    .service-label:hover {
      background-color: var(--light);
    }

    .service-label input[type="checkbox"] {
      margin-top: 3px;
      margin-right: 10px;
      accent-color: var(--primary);
      cursor: pointer;
    }

    .service-content {
      flex: 1;
    }

    .service-meta {
      font-size: 12px;
      color: var(--muted);
      display: block;
      margin-top: 2px;
    }

    .total-block {
      background: var(--light);
      padding: 15px;
      border-radius: var(--radius-sm);
      margin-top: 15px;
      border: 1px solid var(--gray-200);
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      font-weight: var(--font-weight-bold);
      color: var(--primary);
      font-size: 16px;
    }

    .badge-walkin {
      background-color: var(--secondary);
      color: #744210;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: bold;
      margin-left: 8px;
      vertical-align: middle;
    }

    .admin-hint {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 10px;
      background: #f8fafc;
      padding: 8px;
      border-radius: 4px;
      border-left: 3px solid var(--primary);
    }
  </style>
</head>

<body>
  <div class="offset">

    <div class="topbar">
      <div>
        <div style="font-size: 13px; color: var(--muted); margin-bottom: 4px;">Appointments / Edit</div>
        <h1>Manage Appointment</h1>
      </div>
      <div class="topbar-actions">
        <a href="view_appointment.php?id=<?= $id ?>" class="btn-back">
          <i class="fas fa-arrow-left"></i> Back to Details
        </a>
      </div>
    </div>

    <form method="POST" id="editForm">
      <div class="page-grid">

        <div class="col-main">
          <div class="card">
            <div class="card-header">
              <span class="card-title"><i class="fas fa-edit"></i> Appointment Details</span>
              <span style="font-size: 12px; color: var(--muted);">Ref: #<?= $appointment['reference_no'] ?></span>
            </div>

            <div class="form-group">
              <label for="status">Current Status</label>
              <select id="status" name="status" required>
                <option value="pending" <?= $appointment['status'] === 'pending' ? 'selected' : '' ?>>Pending (Awaiting Confirmation)</option>
                <option value="approved" <?= $appointment['status'] === 'approved' ? 'selected' : '' ?>>Approved (Confirmed)</option>
                <option value="completed" <?= $appointment['status'] === 'completed' ? 'selected' : '' ?>>Completed (Done)</option>
                <option value="cancelled" <?= $appointment['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
              </select>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="appointment_date">Date</label>
                <input type="date" id="appointment_date" name="appointment_date"
                  value="<?= $appointment['appointment_date'] ?>" required>
              </div>
              <div class="form-group">
                <label for="appointment_time">Time</label>
                <input type="time" id="appointment_time" name="appointment_time"
                  value="<?= $appointment['appointment_time'] ?>" required>
              </div>
            </div>

            <div class="form-group">
              <label for="notes">Notes / Remarks</label>
              <textarea id="notes" name="notes" rows="6" placeholder="Admin notes regarding this appointment..."><?= htmlspecialchars($appointment['notes']) ?></textarea>
            </div>

            <div class="form-actions">
              <a href="view_appointment.php?id=<?= $appointment['id'] ?>" class="btn btn-cancel">Cancel</a>
              <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
          </div>
        </div>

        <div class="col-sidebar">

          <div class="card">
            <div class="patient-header">
              <div class="patient-avatar">
                <i class="fas fa-user"></i>
              </div>
              <div>
                <div style="font-weight: var(--font-weight-bold); color: var(--dark);">
                  <?= $patient_name ?>
                  <?php if ($is_walkin): ?><span class="badge-walkin">WALK-IN</span><?php endif; ?>
                </div>
                <div style="font-size: 12px; color: var(--muted);">Patient</div>
              </div>
            </div>
            <div style="border-top: 1px solid var(--gray-200); padding-top: 12px;">
              <div class="info-row">
                <span class="info-label">Dentist</span>
                <span class="info-val">Dr. <?= $dentist_name ?></span>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 10px;">
              <span class="card-title" style="font-size: 16px;"><i class="fas fa-receipt"></i> Select Services</span>
            </div>

            <div class="admin-hint">
              <i class="fas fa-check-square"></i> Check services to include. Totals update automatically.
            </div>

            <div class="service-list">
              <?php foreach ($all_services as $svc): ?>
                <?php
                $isChecked = in_array($svc['id'], $current_service_ids) ? 'checked' : '';
                ?>
                <label class="service-label">
                  <input type="checkbox"
                    name="service_ids[]"
                    value="<?= $svc['id'] ?>"
                    data-price="<?= $svc['price'] ?>"
                    data-duration="<?= $svc['duration_minutes'] ?>"
                    class="service-checkbox"
                    <?= $isChecked ?>>

                  <div class="service-content">
                    <div style="display:flex; justify-content:space-between;">
                      <strong><?= htmlspecialchars($svc['name']) ?></strong>
                      <span>₱<?= number_format($svc['price'], 2) ?></span>
                    </div>
                    <span class="service-meta"><?= (int)$svc['duration_minutes'] ?> mins</span>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="total-block">
              <div class="info-row" style="margin-bottom: 5px;">
                <span class="info-label">Total Duration</span>
                <span class="info-val"><span id="display-duration"><?= (int)$appointment['total_duration'] ?></span> mins</span>
              </div>
              <div class="total-row">
                <span>Total Est.</span>
                <span>₱<span id="display-price"><?= number_format($appointment['total_price'], 2) ?></span></span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </form>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const checkboxes = document.querySelectorAll('.service-checkbox');
      const displayPrice = document.getElementById('display-price');
      const displayDuration = document.getElementById('display-duration');

      function calculateTotals() {
        let totalPrice = 0;
        let totalDuration = 0;
        let checkedCount = 0;

        checkboxes.forEach(box => {
          if (box.checked) {
            totalPrice += parseFloat(box.dataset.price);
            totalDuration += parseInt(box.dataset.duration);
            checkedCount++;
          }
        });

        // Update UI
        displayPrice.textContent = totalPrice.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
        displayDuration.textContent = totalDuration;
      }

      // Add event listeners
      checkboxes.forEach(box => {
        box.addEventListener('change', calculateTotals);
      });

      // Initial calculation (safety check)
      calculateTotals();
    });
  </script>
</body>

</html>
<?php $conn->close(); ?>
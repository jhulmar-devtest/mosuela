<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'dentist') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Handle submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $dentist_id = $_SESSION['user_id'];
  $leave_range = $_POST['leave_range'];
  $reason = trim($_POST['reason']);

  // Split the range into start and end dates
  $dates = explode(" to ", $leave_range);
  $start_date = $dates[0] ?? null;
  $end_date = $dates[1] ?? null;

  if (!$start_date || !$end_date) {
    echo "<script>alert('Please select a valid leave range.');</script>";
  } else {
    $stmt = $conn->prepare("INSERT INTO dentist_leaves (dentist_id, start_date, end_date, reason, status, created_at)
                            VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("isss", $dentist_id, $start_date, $end_date, $reason);

    if ($stmt->execute()) {
      echo "<script>alert('Leave request submitted successfully!'); window.location.href='leaves.php';</script>";
    } else {
      echo "<script>alert('Error submitting leave request: " . $stmt->error . "');</script>";
    }

    $stmt->close();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>File Leave - Dentist</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    /* Page Header */
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .page-title {
      font-size: 28px;
      font-weight: 700;
      color: var(--primary);
      margin: 0;
    }

    .header-btn {
      text-decoration: none;
      background: var(--primary);
      color: white;
      padding: 10px 20px;
      border-radius: 6px;
      font-weight: 500;
      transition: background 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .header-btn:hover {
      background: #23867d;
    }

    /* Form Styling */
    .card-form {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 32px;
      max-width: 800px;
      margin: 0 auto;
      border: 1px solid var(--border);
    }

    .card-form h2 {
      font-size: 24px;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 24px;
      text-align: center;
      padding-bottom: 16px;
      border-bottom: 2px solid var(--primary);
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #374151;
      font-size: 14px;
    }

    input,
    textarea,
    select {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 14px;
      font-family: inherit;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      background: white;
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
      min-height: 100px;
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
      text-decoration: none;
    }

    .btn:hover {
      background: #23867d;
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
    }

    .form-actions {
      display: flex;
      gap: 12px;
      margin-top: 32px;
      justify-content: flex-end;
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

    /* Form Help Text */
    .help-text {
      color: var(--muted);
      font-size: 12px;
      margin-top: 4px;
      font-style: italic;
    }

    /* Flatpickr Customization */
    .flatpickr-calendar {
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
    }

    .flatpickr-day.selected {
      background: var(--primary);
      border-color: var(--primary);
    }

    .flatpickr-day.today {
      border-color: var(--primary);
    }

    .flatpickr-day:hover {
      background: #e0f2f1;
      border-color: #e0f2f1;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .offset {
        margin-left: 0;
      }

      body {
        padding: 16px;
      }

      .card-form {
        padding: 20px;
      }

      .form-actions {
        flex-direction: column;
      }

      .page-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
      }
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="page-header">
      <h1 class="page-title">File Leave Request</h1>
      <a href="leaves.php" class="header-btn">
        <i class="fas fa-arrow-left"></i> Back to Leaves
      </a>
    </div>

    <form class="card-form" method="POST" action="">
      <h2><i class="fas fa-calendar-plus"></i> Request Time Off</h2>

      <div class="form-group">
        <label for="leave_range">
          <i class="fas fa-calendar-alt"></i> Leave Dates
        </label>
        <input type="text" id="leave_range" name="leave_range" required placeholder="Select start and end dates">
        <div class="help-text">Select the start and end dates for your leave period</div>
      </div>

      <div class="form-group">
        <label for="reason">
          <i class="fas fa-file-alt"></i> Reason for Leave
        </label>
        <textarea id="reason" name="reason" required placeholder="Please provide a reason for your leave request"></textarea>
        <div class="help-text">Briefly explain why you're requesting time off</div>
      </div>

      <div class="form-actions">
        <a href="leaves.php" class="btn btn-secondary">
          <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" class="btn">
          <i class="fas fa-paper-plane"></i> Submit Leave Request
        </button>
      </div>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      flatpickr("#leave_range", {
        mode: "range",
        dateFormat: "Y-m-d",
        minDate: "today",
        placeholder: "Select start and end dates",
        onChange: function(selectedDates, dateStr, instance) {
          if (selectedDates.length === 2) {
            const start = selectedDates[0];
            const end = selectedDates[1];
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

            // Update help text with duration
            const helpText = document.querySelector('#leave_range + .help-text');
            if (helpText) {
              helpText.textContent = `Leave period: ${diffDays} day(s) selected`;
            }
          }
        }
      });
    </script>
  </div>
</body>

</html>
<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'patient') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

$patient_id = $_SESSION['user_id'];

// Get medical history
$history_query = "SELECT * FROM patient_medical_history WHERE patient_id = ?";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $patient_id);
$history_stmt->execute();
$medical_history = $history_stmt->get_result()->fetch_assoc();

// Get treatment records
$records_query = "
  SELECT pr.*, d.name as dentist_name, a.appointment_date
  FROM patient_records pr
  INNER JOIN users d ON pr.dentist_id = d.id
  LEFT JOIN appointments a ON pr.appointment_id = a.id
  WHERE pr.patient_id = ?
  ORDER BY pr.created_at DESC
";
$records_stmt = $conn->prepare($records_query);
$records_stmt->bind_param("i", $patient_id);
$records_stmt->execute();
$records = $records_stmt->get_result();

// Get records statistics
$stats_query = "SELECT 
    COUNT(*) as total_records,
    COUNT(CASE WHEN diagnosis IS NOT NULL AND diagnosis != '' THEN 1 END) as with_diagnosis,
    COUNT(CASE WHEN treatment IS NOT NULL AND treatment != '' THEN 1 END) as with_treatment,
    COUNT(CASE WHEN prescription IS NOT NULL AND prescription != '' THEN 1 END) as with_prescription
    FROM patient_records WHERE patient_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $patient_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Records - Miracle</title>
  <style>
    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 40px;
      border-radius: var(--radius);
      margin-bottom: 32px;
      box-shadow: var(--shadow-lg);
      position: relative;
      overflow: hidden;
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(233, 196, 106, 0.2), transparent);
      border-radius: 50%;
    }

    .page-header-content {
      position: relative;
      z-index: 2;
    }

    .page-title {
      font-size: 32px;
      font-weight: 800;
      margin: 0 0 8px 0;
    }

    .page-subtitle {
      font-size: 16px;
      opacity: 0.95;
      margin: 0;
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 24px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: var(--card);
      padding: 24px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      display: flex;
      align-items: center;
      gap: 20px;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
      border-color: var(--secondary);
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      flex-shrink: 0;
    }

    .stat-content {
      flex: 1;
    }

    .stat-content h3 {
      font-size: 13px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin: 0 0 8px 0;
      font-weight: 600;
    }

    .stat-content .number {
      font-size: 32px;
      font-weight: 800;
      color: var(--primary);
      line-height: 1;
    }

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
      background: var(--card);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
    }

    th,
    td {
      padding: 12px 10px;
      border-bottom: 1px solid #f1f5f9;
      text-align: left;
      font-size: 14px;
    }

    th.actions,
    td.actions {
      width: 160px;
      white-space: nowrap;
      text-align: center;
    }

    th {
      background: #f8fafc;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
    }

    /* Buttons */
    .btn {
      border: none;
      padding: 6px 10px;
      border-radius: var(--radius);
      font-size: 12px;
      cursor: pointer;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s ease;
    }

    .btn-view {
      background: #dbeafe;
      color: #1e40af;
    }

    .btn-view:hover {
      background: #bfdbfe;
      box-shadow: 0 2px 4px rgba(30, 64, 175, 0.1);
    }

    .btn-edit {
      background: #fef3c7;
      color: #92400e;
    }

    .btn-edit:hover {
      background: #fde68a;
      box-shadow: 0 2px 4px rgba(146, 64, 14, 0.1);
    }

    .btn-delete {
      background: #fee2e2;
      color: #991b1b;
    }

    .btn-delete:hover {
      background: #fecaca;
      box-shadow: 0 2px 4px rgba(153, 27, 27, 0.1);
    }

    /* Medical History Table */
    .medical-history-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
    }

    .medical-history-table td {
      padding: 16px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: top;
    }

    .medical-history-table td:first-child {
      width: 200px;
      font-weight: 600;
      background: #f8fafc;
      color: var(--muted);
    }

    /* Treatment Record Cards */
    .treatment-card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 20px;
      box-shadow: var(--shadow);
      margin-bottom: 20px;
      border-left: 4px solid var(--primary);
    }

    .treatment-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 1px solid #f1f5f9;
    }

    .treatment-date {
      font-weight: 600;
      color: var(--primary);
      font-size: 16px;
    }

    .treatment-dentist {
      color: var(--muted);
      font-size: 14px;
    }

    .treatment-section {
      margin-bottom: 16px;
    }

    .treatment-section:last-child {
      margin-bottom: 0;
    }

    .section-title {
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 8px;
      font-size: 14px;
    }

    .section-content {
      background: #f8fafc;
      padding: 12px;
      border-radius: 6px;
      font-size: 14px;
      line-height: 1.5;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--muted);
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: 16px;
      color: #d1d5db;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .offset {
        margin-left: 0;
      }
    }

    @media (max-width: 768px) {
      body {
        padding: 16px;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }

      .medical-history-table td:first-child {
        width: 150px;
      }

      .treatment-header {
        flex-direction: column;
        gap: 8px;
      }
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
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-content">
        <h1 class="page-title">My Records</h1>
        <p class="page-subtitle">View your medical history and treatment records</p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-file-medical"></i>
        </div>
        <div class="stat-content">
          <h3>Medical History</h3>
          <div class="number"><?= $medical_history ? '1' : '0' ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-teeth"></i>
        </div>
        <div class="stat-content">
          <h3>Treatment Records</h3>
          <div class="number"><?= $stats['total_records'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-stethoscope"></i>
        </div>
        <div class="stat-content">
          <h3>Diagnoses</h3>
          <div class="number"><?= $stats['with_diagnosis'] ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-pills"></i>
        </div>
        <div class="stat-content">
          <h3>Prescriptions</h3>
          <div class="number"><?= $stats['with_prescription'] ?></div>
        </div>
      </div>
    </div>

    <!-- Medical History Section -->
    <div class="card">
      <div style="margin-bottom: 20px;">
        <h3 style="font-size: 22px; margin: 0;">Medical History</h3>
      </div>

      <?php if ($medical_history): ?>
        <table class="medical-history-table">
          <tr>
            <td>Allergies</td>
            <td><?= nl2br(htmlspecialchars($medical_history['allergies'] ?: 'None reported')) ?></td>
          </tr>
          <tr>
            <td>Current Medications</td>
            <td><?= nl2br(htmlspecialchars($medical_history['current_medications'] ?: 'None reported')) ?></td>
          </tr>
          <tr>
            <td>Medical Conditions</td>
            <td><?= nl2br(htmlspecialchars($medical_history['medical_conditions'] ?: 'None reported')) ?></td>
          </tr>
          <tr>
            <td>Previous Dental Work</td>
            <td><?= nl2br(htmlspecialchars($medical_history['previous_dental_work'] ?: 'None reported')) ?></td>
          </tr>
          <tr>
            <td>Blood Type</td>
            <td><?= htmlspecialchars($medical_history['blood_type'] ?: 'Not specified') ?></td>
          </tr>
          <tr>
            <td>Emergency Contact</td>
            <td>
              <?php if ($medical_history['emergency_contact_name']): ?>
                <strong><?= htmlspecialchars($medical_history['emergency_contact_name']) ?></strong><br>
                <span style="color: var(--muted);"><?= htmlspecialchars($medical_history['emergency_contact_phone']) ?></span>
              <?php else: ?>
                Not provided
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td>Last Updated</td>
            <td><?= date('F j, Y', strtotime($medical_history['updated_at'])) ?></td>
          </tr>
        </table>

        <a href="settings.php" class="header-btn">
          <i class="fas fa-edit"></i> Update Medical History
        </a>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-file-medical-alt"></i>
          <h3>No Medical History Recorded</h3>
          <p>Please provide your medical history for better dental care.</p>
          <a href="settings.php" class="header-btn" style="margin-top: 15px;">
            <i class="fas fa-plus"></i> Add Medical History
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Treatment Records Section -->
    <div class="card">
      <div style="margin-bottom: 20px;">
        <h3 style="font-size: 22px; margin: 0;">Treatment Records</h3>
      </div>

      <?php if ($records->num_rows > 0): ?>
        <?php while ($record = $records->fetch_assoc()): ?>
          <div class="treatment-card">
            <div class="treatment-header">
              <div>
                <div class="treatment-date">
                  <?= date('F j, Y', strtotime($record['created_at'])) ?>
                </div>
                <div class="treatment-dentist">
                  Dr. <?= htmlspecialchars($record['dentist_name']) ?>
                </div>
              </div>
              <?php if ($record['appointment_date']): ?>
                <div style="text-align: right;">
                  <div style="font-size: 12px; color: var(--muted);">Appointment Date</div>
                  <div style="font-size: 14px; font-weight: 500;">
                    <?= date('M j, Y', strtotime($record['appointment_date'])) ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <?php if ($record['diagnosis']): ?>
              <div class="treatment-section">
                <div class="section-title">Diagnosis</div>
                <div class="section-content"><?= nl2br(htmlspecialchars($record['diagnosis'])) ?></div>
              </div>
            <?php endif; ?>

            <?php if ($record['treatment']): ?>
              <div class="treatment-section">
                <div class="section-title">Treatment Performed</div>
                <div class="section-content"><?= nl2br(htmlspecialchars($record['treatment'])) ?></div>
              </div>
            <?php endif; ?>

            <?php if ($record['prescription']): ?>
              <div class="treatment-section">
                <div class="section-title">Prescription</div>
                <div class="section-content"><?= nl2br(htmlspecialchars($record['prescription'])) ?></div>
              </div>
            <?php endif; ?>

            <?php if ($record['notes']): ?>
              <div class="treatment-section">
                <div class="section-title">Additional Notes</div>
                <div class="section-content"><?= nl2br(htmlspecialchars($record['notes'])) ?></div>
              </div>
            <?php endif; ?>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-teeth-open"></i>
          <h3>No Treatment Records</h3>
          <p>Your treatment records will appear here after dental visits.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>

<?php
$history_stmt->close();
$records_stmt->close();
$stats_stmt->close();
$conn->close();
?>
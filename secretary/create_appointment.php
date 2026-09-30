<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'secretary') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $patient_name = trim($_POST['patient_name'] ?? '');
  $service_ids = isset($_POST['service_ids']) ? (array)$_POST['service_ids'] : [];
  $dentist_id = isset($_POST['dentist_id']) ? intval($_POST['dentist_id']) : 0;
  $appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
  $appointment_time = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';

  // Validate patient name
  if (empty($patient_name)) {
    echo "<script>alert('Patient name is required.'); window.history.back();</script>";
    exit();
  }

  // Create appointment (walk-in, so patient_id = null)
  $notes = "Walk-in: " . $patient_name;
  $result = create_appointment_with_services($conn, [
    'patient_id' => null,
    'dentist_id' => $dentist_id,
    'service_ids' => $service_ids,
    'appointment_date' => $appointment_date,
    'appointment_time' => $appointment_time,
    'status' => 'pending',
    'notes' => $notes
  ]);

  if (isset($result['success']) && $result['success']) {
    echo "<script>alert('Walk-in appointment created successfully!'); window.location.href='appointments.php';</script>";
  } else {
    $error_msg = $result['error'] ?? 'Unknown error occurred';
    echo "<script>alert('Error: " . htmlspecialchars($error_msg) . "'); window.history.back();</script>";
  }
  $conn->close();
  exit();
}

// Get all active services
$services = get_all_services($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Create Walk-in Appointment - Miracle Mosuela</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .card-form {
      background: var(--card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 28px;
      max-width: 850px;
      margin: 0 auto;
    }

    .form-group {
      margin-bottom: 16px;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #374151;
      font-size: 13px;
    }

    input,
    select {
      width: 100%;
      padding: 10px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 13px;
    }

    input:focus,
    select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.1);
    }

    input:disabled,
    select:disabled {
      background: #f9fafb;
      cursor: not-allowed;
      opacity: 0.7;
    }

    .btn {
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn:hover {
      background: #21867a;
    }

    .btn:disabled {
      background: #9ca3af;
      cursor: not-allowed;
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
      margin-top: 24px;
      justify-content: flex-end;
    }

    .form-steps {
      display: flex;
      justify-content: center;
      margin-bottom: 24px;
      gap: 12px;
      flex-wrap: wrap;
    }

    .step {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }

    .step-number {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #f3f4f6;
      color: var(--muted);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 12px;
    }

    .step.active .step-number {
      background: var(--primary);
      color: white;
    }

    .step.completed .step-number {
      background: #10b981;
      color: white;
    }

    .step-label {
      font-size: 10px;
      color: var(--muted);
      font-weight: 500;
      max-width: 60px;
      text-align: center;
    }

    .step.active .step-label {
      color: var(--primary);
      font-weight: 600;
    }

    .services-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 10px;
      margin: 12px 0;
    }

    .service-card {
      border: 2px solid #e5e7eb;
      border-radius: 6px;
      padding: 10px;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
    }

    .service-card:hover {
      border-color: var(--primary);
      box-shadow: 0 2px 6px rgba(42, 157, 143, 0.1);
    }

    .service-card input {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 16px;
      height: 16px;
    }

    .service-card.selected {
      border-color: var(--primary);
      background: rgba(42, 157, 143, 0.05);
    }

    .service-name {
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 2px;
      padding-right: 24px;
      font-size: 12px;
    }

    .service-duration {
      font-size: 10px;
      color: var(--muted);
      margin-bottom: 3px;
    }

    .service-price {
      font-size: 13px;
      font-weight: 700;
      color: var(--primary);
    }

    .totals-section {
      background: #f9fafb;
      border-radius: 6px;
      padding: 10px;
      margin: 12px 0;
      border: 1px solid #e5e7eb;
      font-size: 12px;
    }

    .total-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 3px;
    }

    .total-item:last-child {
      margin: 0;
      padding-top: 4px;
      border-top: 1px solid #e5e7eb;
      font-weight: 700;
      color: var(--primary);
    }

    .alert {
      padding: 10px;
      border-radius: 6px;
      margin: 12px 0;
      font-weight: 500;
      font-size: 12px;
    }

    .alert-info {
      background: #dbeafe;
      color: #1e40af;
      border: 1px solid #93c5fd;
    }

    .alert-error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    @media (max-width: 768px) {
      .services-container {
        grid-template-columns: 1fr;
      }

      .form-actions {
        flex-direction: column-reverse;
      }

      .form-actions .btn {
        width: 100%;
        justify-content: center;
      }
    }

    .flatpickr-calendar {
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      border: 1px solid #e5e7eb;
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

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }

    .page-header h2 {
      font-size: 28px;
      font-weight: 700;
      color: var(--primary);
    }
  </style>
</head>

<body>
  <div class="offset">
    <div class="page-header">
      <h2>Create Walk-in Appointment</h2>
      <a href="appointments.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <div class="form-steps">
      <div class="step active" id="step-patient">
        <div class="step-number">1</div>
        <div class="step-label">Patient</div>
      </div>
      <div class="step" id="step-services">
        <div class="step-number">2</div>
        <div class="step-label">Services</div>
      </div>
      <div class="step" id="step-dentist">
        <div class="step-number">3</div>
        <div class="step-label">Dentist</div>
      </div>
      <div class="step" id="step-date">
        <div class="step-number">4</div>
        <div class="step-label">Date</div>
      </div>
      <div class="step" id="step-time">
        <div class="step-number">5</div>
        <div class="step-label">Time</div>
      </div>
    </div>

    <form class="card-form" id="appointmentForm" method="POST">
      <h2 style="text-align: center; color: var(--primary); font-size: 20px; margin-bottom: 20px;">Create Walk-in Appointment</h2>

      <div class="form-group">
        <label for="patient_name">Patient Name *</label>
        <input type="text" id="patient_name" name="patient_name" required placeholder="Enter patient name">
      </div>

      <div class="form-group">
        <label>Select Services *</label>
        <div class="services-container" id="servicesContainer">
          <?php foreach ($services as $service): ?>
            <label class="service-card" data-service-id="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>" data-duration="<?php echo $service['duration_minutes']; ?>">
              <input type="checkbox" name="service_ids[]" value="<?php echo $service['id']; ?>" class="service-checkbox">
              <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
              <div class="service-duration"><i class="fas fa-clock"></i> <?php echo $service['duration_minutes']; ?>m</div>
              <div class="service-price">₱<?php echo number_format($service['price'], 2); ?></div>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="alert alert-info" id="servicesAlert" style="display: none;">Select at least one service</div>
      </div>

      <div class="totals-section" id="totalsSection" style="display: none;">
        <div class="total-item"><span>Services:</span><span id="serviceCount">0</span></div>
        <div class="total-item"><span>Total:</span><span id="totalDisplay">₱0.00</span></div>
        <div class="total-item"><span>Duration:</span><span id="durationDisplay">0 min</span></div>
      </div>

      <div class="form-group">
        <label for="dentist_id">Select Dentist *</label>
        <select id="dentist_id" name="dentist_id" required disabled>
          <option value="">-- Select a dentist --</option>
        </select>
        <div class="alert alert-error" id="dentistAlert" style="display: none;">No dentist available for selected services</div>
      </div>

      <div class="form-group">
        <label for="appointment_date">Appointment Date *</label>
        <input type="text" id="appointment_date" name="appointment_date" required disabled placeholder="Select date">
      </div>

      <div class="form-group">
        <label for="appointment_time">Appointment Time *</label>
        <select id="appointment_time" name="appointment_time" required disabled>
          <option value="">-- Select time --</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="window.history.back();"><i class="fas fa-times"></i> Cancel</button>
        <button type="submit" id="submitBtn" class="btn" disabled><i class="fas fa-calendar-plus"></i> Create</button>
      </div>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    // CONSOLIDATED SCRIPT - All variables declared once
    (function() {
      'use strict';

      // DOM Elements - declared once
      const patientNameInput = document.getElementById('patient_name');
      const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
      const dentistSelect = document.getElementById('dentist_id');
      const dateInput = document.getElementById('appointment_date');
      const timeSelect = document.getElementById('appointment_time');
      const submitBtn = document.getElementById('submitBtn');
      const servicesAlert = document.getElementById('servicesAlert');
      const dentistAlert = document.getElementById('dentistAlert');
      const totalsSection = document.getElementById('totalsSection');

      // Step Elements
      const stepPatient = document.getElementById('step-patient');
      const stepServices = document.getElementById('step-services');
      const stepDentist = document.getElementById('step-dentist');
      const stepDate = document.getElementById('step-date');
      const stepTime = document.getElementById('step-time');

      // State
      let selectedServices = new Set();
      let flatpickrInstance;

      // Patient Name Handler
      patientNameInput.addEventListener('input', function() {
        if (this.value.trim()) {
          stepPatient.classList.add('completed');
          stepServices.classList.add('active');
        } else {
          stepPatient.classList.remove('completed');
          stepServices.classList.remove('active');
        }
        validateForm();
      });

      // Service Selection Handler
      serviceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          const serviceCard = this.closest('.service-card');
          if (this.checked) {
            selectedServices.add(this.value);
            serviceCard.classList.add('selected');
            stepServices.classList.add('completed');
          } else {
            selectedServices.delete(this.value);
            serviceCard.classList.remove('selected');
            if (selectedServices.size === 0) stepServices.classList.remove('completed');
          }
          updateTotals();
          updateDentistOptions();
          clearDateAndTime();
        });
      });

      // Update Totals Display
      function updateTotals() {
        if (selectedServices.size === 0) {
          totalsSection.style.display = 'none';
          servicesAlert.style.display = 'block';
          return;
        }
        servicesAlert.style.display = 'none';
        totalsSection.style.display = 'block';
        let totalPrice = 0,
          totalDuration = 0;
        selectedServices.forEach(id => {
          const card = document.querySelector(`[data-service-id="${id}"]`);
          if (card) {
            totalPrice += parseFloat(card.dataset.price);
            totalDuration += parseInt(card.dataset.duration);
          }
        });
        document.getElementById('serviceCount').textContent = selectedServices.size;
        document.getElementById('totalDisplay').textContent = '₱' + totalPrice.toFixed(2);
        document.getElementById('durationDisplay').textContent = totalDuration + ' min';
      }

      // Fetch Dentists by Services
      async function updateDentistOptions() {
        if (selectedServices.size === 0) {
          dentistSelect.innerHTML = '<option value="">-- Select services --</option>';
          dentistSelect.disabled = true;
          dentistAlert.style.display = 'none';
          return;
        }
        dentistSelect.disabled = false;
        try {
          const response = await fetch(`get_dentists_by_service.php?service_ids=${Array.from(selectedServices).join(',')}`);
          const dentists = await response.json();
          if (!Array.isArray(dentists) || dentists.length === 0) {
            dentistSelect.innerHTML = '<option value="">-- No dentists --</option>';
            dentistAlert.style.display = 'block';
            dentistSelect.disabled = true;
            stepDentist.classList.remove('completed');
            return;
          }
          dentistAlert.style.display = 'none';
          dentistSelect.innerHTML = '<option value="">-- Select dentist --</option>';
          dentists.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.name;
            dentistSelect.appendChild(opt);
          });
        } catch (e) {
          console.error(e);
          dentistSelect.innerHTML = '<option value="">-- Error --</option>';
          dentistAlert.style.display = 'block';
        }
      }

      // Initialize Date Picker
      function initDatePicker() {
        if (flatpickrInstance) flatpickrInstance.destroy();
        flatpickrInstance = flatpickr(dateInput, {
          minDate: new Date(),
          dateFormat: 'Y-m-d',
          // Don't set enable/disable initially - will be set when dentist is selected
          onChange: function() {
            loadAvailableTimes();
            stepDate.classList.add('completed');
            stepTime.classList.add('active');
            validateForm();
          }
        });
      }

      // Load Available Times
      async function loadAvailableTimes() {
        const dentistId = dentistSelect.value;
        const appointmentDate = dateInput.value;
        if (!dentistId || !appointmentDate) {
          timeSelect.innerHTML = '<option value="">-- Select dentist & date --</option>';
          timeSelect.disabled = true;
          return;
        }

        // Show loading state
        timeSelect.innerHTML = '<option value="">-- Loading available times... --</option>';
        timeSelect.disabled = true;
        stepTime.classList.remove('completed');

        const totalDuration = Array.from(selectedServices).reduce((sum, id) => {
          const card = document.querySelector(`[data-service-id="${id}"]`);
          return sum + (card ? parseInt(card.dataset.duration) : 0);
        }, 0);

        try {
          const url = `get_available_times.php?dentist_id=${dentistId}&date=${appointmentDate}&duration=${totalDuration}`;
          console.log('Fetching times from:', url);

          const response = await fetch(url);
          const responseText = await response.text();
          console.log('Raw times response:', responseText);

          let times;
          try {
            times = JSON.parse(responseText);
            console.log('Parsed times:', times);
          } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response was:', responseText);
            timeSelect.innerHTML = '<option value="">-- Error: Invalid response --</option>';
            timeSelect.disabled = true;
            return;
          }

          timeSelect.innerHTML = '<option value="">-- Select time --</option>';

          // Handle both formats: simple array or {available: [], booked: []}
          const availableTimes = Array.isArray(times) ? times : (times.available || []);

          if (availableTimes.length === 0) {
            timeSelect.innerHTML += '<option value="" disabled>-- No available times for this date --</option>';
            timeSelect.disabled = true;
            console.warn('No available times for', appointmentDate);
            return;
          }

          timeSelect.disabled = false;
          availableTimes.forEach(time => {
            const opt = document.createElement('option');
            opt.value = time;
            opt.textContent = time;
            timeSelect.appendChild(opt);
          });
        } catch (e) {
          console.error('Error loading times:', e);
          timeSelect.innerHTML = '<option value="">-- Error loading times --</option>';
          timeSelect.disabled = true;
        }
      }

      // Dentist Selection Handler
      dentistSelect.addEventListener('change', function() {
        if (this.value) {
          stepDentist.classList.add('completed');
          stepDentist.classList.remove('active');
          stepDate.classList.add('active');

          // Enable date input - allow all future dates
          dateInput.disabled = false;
          dateInput.placeholder = 'Select a date';

          // Clear previous date/time selections when dentist changes
          clearDateAndTime();
        } else {
          stepDentist.classList.remove('completed');
          stepDate.classList.remove('active');
          dateInput.disabled = true;
          dateInput.placeholder = 'Select dentist first';
        }
        validateForm();
      });

      // Time Selection Handler
      timeSelect.addEventListener('change', function() {
        if (this.value) stepTime.classList.add('completed');
        else stepTime.classList.remove('completed');
        validateForm();
      });

      // Clear Date and Time
      function clearDateAndTime() {
        dateInput.value = '';
        timeSelect.innerHTML = '<option value="">-- Select time --</option>';
        timeSelect.disabled = true;
        stepDate.classList.remove('completed');
        stepTime.classList.remove('completed');
        validateForm();
      }

      // Form Validation
      function validateForm() {
        const isValid = patientNameInput.value.trim() &&
          selectedServices.size > 0 &&
          dentistSelect.value &&
          dateInput.value &&
          timeSelect.value;
        submitBtn.disabled = !isValid;
      }

      // Initialize
      initDatePicker();
      validateForm();
    })();
  </script>
</body>

</html>
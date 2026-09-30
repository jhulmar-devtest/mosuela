<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/appointment_helpers.php';

if ($_SESSION['user_role'] !== 'patient') {
  header("Location: ../login.php");
  exit();
}

include '../includes/sidebar.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $patient_id = $_SESSION['user_id'];
  $service_ids = isset($_POST['service_ids']) ? (array)$_POST['service_ids'] : [];
  $dentist_id = isset($_POST['dentist_id']) ? intval($_POST['dentist_id']) : 0;
  $appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
  $appointment_time = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';

  // Create appointment with helper function
  $result = create_appointment_with_services($conn, [
    'patient_id' => $patient_id,
    'dentist_id' => $dentist_id,
    'service_ids' => $service_ids,
    'appointment_date' => $appointment_date,
    'appointment_time' => $appointment_time,
    'status' => 'pending'
  ]);

  if (isset($result['success']) && $result['success']) {
    echo "<script>alert('Appointment created successfully!'); window.location.href='appointments.php';</script>";
  } else {
    $error_msg = $result['error'] ?? 'Unknown error occurred';
    echo "<script>alert('Error: " . htmlspecialchars($error_msg) . "'); window.history.back();</script>";
  }
  $conn->close();
  exit();
}

// Get all active services for display
$services = get_all_services($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Book Appointment - Miracle Mosuela</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
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

    .card-form {
      background: var(--card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 32px;
      max-width: 800px;
      margin: 0 auto;
    }

    .card-form h2 {
      font-size: 24px;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 24px;
      text-align: center;
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
    select {
      width: 100%;
      padding: 12px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      background: white;
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
      color: var(--muted);
      cursor: not-allowed;
      opacity: 0.7;
    }

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
      margin-top: 32px;
      justify-content: flex-end;
    }

    .form-steps {
      display: flex;
      justify-content: center;
      margin-bottom: 32px;
      gap: 20px;
      flex-wrap: wrap;
    }

    .step {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }

    .step-number {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #f3f4f6;
      color: var(--muted);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
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
      font-size: 12px;
      color: var(--muted);
      font-weight: 500;
    }

    .step.active .step-label {
      color: var(--primary);
      font-weight: 600;
    }

    .services-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }

    .service-card {
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      padding: 16px;
      cursor: pointer;
      transition: all 0.2s ease;
      position: relative;
    }

    .service-card:hover {
      border-color: var(--primary);
      box-shadow: 0 2px 8px rgba(42, 157, 143, 0.1);
    }

    .service-card input[type="checkbox"] {
      position: absolute;
      top: 12px;
      right: 12px;
      width: 20px;
      height: 20px;
      cursor: pointer;
    }

    .service-card.selected {
      border-color: var(--primary);
      background: rgba(42, 157, 143, 0.05);
    }

    .service-name {
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 4px;
      padding-right: 30px;
    }

    .service-duration {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 8px;
    }

    .service-price {
      font-size: 16px;
      font-weight: 700;
      color: var(--primary);
    }

    .totals-section {
      background: #f9fafb;
      border-radius: 8px;
      padding: 16px;
      margin: 20px 0;
      border: 1px solid #e5e7eb;
    }

    .total-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
    }

    .total-item:last-child {
      margin-bottom: 0;
      padding-top: 8px;
      border-top: 2px solid #e5e7eb;
      font-weight: 700;
      font-size: 16px;
      color: var(--primary);
    }

    .alert {
      padding: 16px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-weight: 500;
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
      .card-form {
        padding: 20px;
      }

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
  </style>
</head>

<body>
  <div class="offset">
    <div class="page-header">
      <h2>Book New Appointment</h2>
      <a href="appointments.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
      </a>
    </div>

    <div class="form-steps">
      <div class="step active" id="step-services">
        <div class="step-number">1</div>
        <div class="step-label">Services</div>
      </div>
      <div class="step" id="step-dentist">
        <div class="step-number">2</div>
        <div class="step-label">Dentist</div>
      </div>
      <div class="step" id="step-date">
        <div class="step-number">3</div>
        <div class="step-label">Date</div>
      </div>
      <div class="step" id="step-time">
        <div class="step-number">4</div>
        <div class="step-label">Time</div>
      </div>
    </div>

    <form class="card-form" id="appointmentForm" method="POST">
      <h2>Book Your Appointment</h2>

      <div class="form-group">
        <label>Select Services (Choose one or more)</label>
        <div class="services-container" id="servicesContainer">
          <?php if (!empty($services)): ?>
            <?php foreach ($services as $service): ?>
              <label class="service-card" data-service-id="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>" data-duration="<?php echo $service['duration_minutes']; ?>">
                <input type="checkbox" name="service_ids[]" value="<?php echo $service['id']; ?>" class="service-checkbox">
                <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                <div class="service-duration">
                  <i class="fas fa-clock"></i> <?php echo $service['duration_minutes']; ?> min
                </div>
                <div class="service-price">
                  ₱<?php echo number_format($service['price'], 2); ?>
                </div>
              </label>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-error">No services available.</div>
          <?php endif; ?>
        </div>
        <div class="alert alert-info" id="servicesAlert" style="display: none;">
          Please select at least one service to continue.
        </div>
      </div>

      <div class="totals-section" id="totalsSection" style="display: none;">
        <div class="total-item">
          <span>Services Selected:</span>
          <span id="serviceCount">0</span>
        </div>
        <div class="total-item">
          <span>Est. Duration:</span>
          <span id="durationDisplay">0 min</span>
        </div>
        <div class="total-item">
          <span>Total:</span>
          <span id="totalDisplay">₱0.00</span>
        </div>
      </div>

      <div class="form-group">
        <label for="dentist_id">Select Dentist *</label>
        <select id="dentist_id" name="dentist_id" required disabled>
          <option value="">-- Select a dentist --</option>
        </select>
        <div class="alert alert-error" id="dentistAlert" style="display: none;">
          No dentist available for selected services.
        </div>
      </div>

      <div class="form-group">
        <label for="appointment_date">Appointment Date *</label>
        <input type="text" id="appointment_date" name="appointment_date" placeholder="Click to select" required disabled>
      </div>

      <div class="form-group">
        <label for="appointment_time">Appointment Time *</label>
        <select id="appointment_time" name="appointment_time" required disabled>
          <option value="">-- Select a time --</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="window.history.back();">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" id="submitBtn" class="btn" disabled>
          <i class="fas fa-calendar-check"></i> Book Now
        </button>
      </div>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    (function() {
      'use strict';

      // DOM Elements
      const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
      const dentistSelect = document.getElementById('dentist_id');
      const dateInput = document.getElementById('appointment_date');
      const timeSelect = document.getElementById('appointment_time');
      const submitBtn = document.getElementById('submitBtn');
      const servicesAlert = document.getElementById('servicesAlert');
      const dentistAlert = document.getElementById('dentistAlert');
      const totalsSection = document.getElementById('totalsSection');

      // Step indicators
      const stepServices = document.getElementById('step-services');
      const stepDentist = document.getElementById('step-dentist');
      const stepDate = document.getElementById('step-date');
      const stepTime = document.getElementById('step-time');

      let selectedServices = new Set();
      let flatpickrInstance;

      // Service selection handlers
      serviceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          const serviceCard = this.closest('.service-card');
          const serviceId = this.value;

          if (this.checked) {
            selectedServices.add(serviceId);
            serviceCard.classList.add('selected');
            stepServices.classList.add('completed');
            stepServices.classList.remove('active');
            stepDentist.classList.add('active');
          } else {
            selectedServices.delete(serviceId);
            serviceCard.classList.remove('selected');
            if (selectedServices.size === 0) {
              stepServices.classList.remove('completed');
              stepServices.classList.add('active');
              stepDentist.classList.remove('active');
            }
          }

          updateTotals();
          updateDentistOptions();
          clearDateAndTime();
        });
      });

      // Update totals display
      function updateTotals() {
        if (selectedServices.size === 0) {
          totalsSection.style.display = 'none';
          servicesAlert.style.display = 'block';
          return;
        }

        servicesAlert.style.display = 'none';
        totalsSection.style.display = 'block';

        let totalPrice = 0;
        let totalDuration = 0;

        selectedServices.forEach(serviceId => {
          const card = document.querySelector(`[data-service-id="${serviceId}"]`);
          if (card) {
            totalPrice += parseFloat(card.dataset.price);
            totalDuration += parseInt(card.dataset.duration);
          }
        });

        document.getElementById('serviceCount').textContent = selectedServices.size;
        document.getElementById('durationDisplay').textContent = totalDuration + ' min';
        document.getElementById('totalDisplay').textContent = '₱' + totalPrice.toFixed(2);
      }

      // Fetch and update dentist options
      async function updateDentistOptions() {
        if (selectedServices.size === 0) {
          dentistSelect.innerHTML = '<option value="">-- Select services first --</option>';
          dentistSelect.disabled = true;
          dentistAlert.style.display = 'none';
          return;
        }

        dentistSelect.disabled = false;

        try {
          const serviceIds = Array.from(selectedServices).join(',');
          const response = await fetch(`get_dentists_by_service.php?service_ids=${serviceIds}`);

          if (!response.ok) throw new Error('Failed to fetch dentists');

          const dentists = await response.json();

          if (!Array.isArray(dentists) || dentists.length === 0) {
            dentistSelect.innerHTML = '<option value="">-- No dentists available --</option>';
            dentistAlert.style.display = 'block';
            dentistSelect.disabled = true;
            stepDentist.classList.remove('completed');
            return;
          }

          dentistAlert.style.display = 'none';
          dentistSelect.innerHTML = '<option value="">-- Select a dentist --</option>';

          dentists.forEach(dentist => {
            const option = document.createElement('option');
            option.value = dentist.id;
            option.textContent = dentist.name;
            dentistSelect.appendChild(option);
          });
        } catch (error) {
          console.error('Error fetching dentists:', error);
          dentistSelect.innerHTML = '<option value="">-- Error loading --</option>';
          dentistAlert.style.display = 'block';
        }
      }

      // Initialize date picker
      function initDatePicker() {
        if (flatpickrInstance) {
          flatpickrInstance.destroy();
        }

        flatpickrInstance = flatpickr(dateInput, {
          minDate: new Date(),
          dateFormat: 'Y-m-d',
          onChange: function(selectedDates) {
            if (selectedDates.length > 0) {
              loadAvailableTimes();
              stepDate.classList.add('completed');
              stepDate.classList.remove('active');
              stepTime.classList.add('active');
              validateForm();
            }
          }
        });
      }

      // Load available time slots
      async function loadAvailableTimes() {
        const dentistId = dentistSelect.value;
        const appointmentDate = dateInput.value;

        if (!dentistId || !appointmentDate) {
          timeSelect.innerHTML = '<option value="">-- Select date and dentist --</option>';
          timeSelect.disabled = true;
          return;
        }

        // Show loading state
        timeSelect.innerHTML = '<option value="">-- Loading times... --</option>';
        timeSelect.disabled = true;

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

          timeSelect.innerHTML = '<option value="">-- Select a time --</option>';

          // Handle both formats: simple array or {available: [], booked: []}
          const availableTimes = Array.isArray(times) ? times : (times.available || []);

          if (availableTimes.length === 0) {
            timeSelect.innerHTML = '<option value="">-- No times available --</option>';
            timeSelect.disabled = true;
            return;
          }

          timeSelect.disabled = false;
          availableTimes.forEach(time => {
            const option = document.createElement('option');
            option.value = time;
            option.textContent = time;
            timeSelect.appendChild(option);
          });
        } catch (error) {
          console.error('Error:', error);
          timeSelect.innerHTML = '<option value="">-- Error loading --</option>';
          timeSelect.disabled = true;
        }
      }

      // Event listeners
      dentistSelect.addEventListener('change', function() {
        if (this.value) {
          stepDentist.classList.add('completed');
          stepDentist.classList.remove('active');
          stepDate.classList.add('active');
          dateInput.disabled = false;
          if (dateInput.value) {
            loadAvailableTimes();
          }
        } else {
          stepDentist.classList.remove('completed');
          dateInput.disabled = true;
        }
        validateForm();
      });

      timeSelect.addEventListener('change', function() {
        if (this.value) {
          stepTime.classList.add('completed');
        } else {
          stepTime.classList.remove('completed');
        }
        validateForm();
      });

      // Clear date and time when services change
      function clearDateAndTime() {
        dateInput.value = '';
        dateInput.disabled = true;
        timeSelect.innerHTML = '<option value="">-- Select a time --</option>';
        timeSelect.disabled = true;
        stepDate.classList.remove('completed');
        stepDate.classList.remove('active');
        stepTime.classList.remove('completed');
        stepTime.classList.remove('active');
        validateForm();
      }

      // Form validation
      function validateForm() {
        const isValid =
          selectedServices.size > 0 &&
          dentistSelect.value !== '' &&
          dateInput.value !== '' &&
          timeSelect.value !== '';

        submitBtn.disabled = !isValid;
      }

      // Initialize
      initDatePicker();
      validateForm();
    })();
  </script>
</body>

</html>
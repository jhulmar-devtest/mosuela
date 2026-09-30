<?php
include '../includes/db.php';
include '../includes/auth.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/sidebar.php';

// Get selected dentist ID
$selected_dentist = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;

// Fetch all dentists for selection dropdown
$dentists = $conn->query("SELECT id, name FROM users WHERE role='dentist'");

// Fetch dentist availability
$availability = [];
if ($selected_dentist) {
    $stmt = $conn->prepare("
        SELECT id, date, start_time, end_time, status, notes
        FROM dentist_availability
        WHERE dentist_id = ?
        ORDER BY date ASC
    ");
    $stmt->bind_param("i", $selected_dentist);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $availability[] = [
            'title' => ucfirst($row['status']) . ($row['notes'] ? " ({$row['notes']})" : ""),
            'start' => $row['date'],
            'time' => date('g:i A', strtotime($row['start_time'])) . " - " . date('g:i A', strtotime($row['end_time'])),
            'type' => 'availability'
        ];
    }
    $stmt->close();

    // Fetch appointments
    $appt_stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes,
               COALESCE(p.name, SUBSTRING_INDEX(a.notes, ': ', -1)) AS patient_name,
               s.name AS service_name
        FROM appointments a
        LEFT JOIN users p ON a.patient_id = p.id
        INNER JOIN services s ON a.service_id = s.id
        WHERE a.dentist_id = ?
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $appt_stmt->bind_param("i", $selected_dentist);
    $appt_stmt->execute();
    $appt_result = $appt_stmt->get_result();
    while ($row = $appt_result->fetch_assoc()) {
        $availability[] = [
            'title' => $row['patient_name'] . " - " . $row['service_name'],
            'start' => $row['appointment_date'],
            'time' => date('g:i A', strtotime($row['appointment_time'])),
            'type' => 'appointment',
            'status' => $row['status']
        ];
    }
    $appt_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dentist Schedule Overview</title>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<style>
body { font-family: "Inter", sans-serif; background: #f9fafb; padding: 40px; }
.container { max-width: 1200px; margin: auto; }
.select-dentist { margin-bottom: 20px; }
.calendar-container { display: flex; gap: 30px; }
#calendar { flex: 2; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
.appointments { flex: 1; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-height: 700px; overflow-y: auto; }
.appointment { padding: 12px; border-radius: 8px; margin-bottom: 10px; background: #f1f5f9; }
.appointment h4 { margin: 0 0 4px; font-size: 15px; }
.appointment p { margin: 0; font-size: 13px; color: #555; }
.availability { background-color: #d1e7dd !important; }
.appointment-event { background-color: #f8d7da !important; }
</style>
</head>
<body>
<div class="container">
    <div class="select-dentist">
        <form method="GET">
            <label for="dentist_id">Select Dentist:</label>
            <select name="dentist_id" id="dentist_id" onchange="this.form.submit()" required>
                <option value="">-- Select Dentist --</option>
                <?php while($row = $dentists->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= $selected_dentist == $row['id'] ? 'selected' : '' ?>>
                        Dr. <?= htmlspecialchars($row['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>

    <?php if ($selected_dentist): ?>
    <div class="calendar-container">
        <div id="calendar"></div>
        <div class="appointments" id="appointment-list">
            <h3>Appointments / Availability</h3>
            <p>Select a date to view details.</p>
        </div>
    </div>
    <?php else: ?>
        <p>Please select a dentist to view their schedule.</p>
    <?php endif; ?>
</div>

<?php if ($selected_dentist): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const appointmentList = document.getElementById('appointment-list');

    const events = <?= json_encode($availability) ?>;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 650,
        events: events.map(ev => ({
            title: ev.title,
            start: ev.start,
            className: ev.type === 'availability' ? 'availability' : 'appointment-event'
        })),
        dateClick: function(info) {
            const selectedDate = info.dateStr;
            const dayEvents = events.filter(e => e.start === selectedDate);

            appointmentList.innerHTML = `<h3>Events on ${selectedDate}</h3>`;

            if(dayEvents.length > 0) {
                dayEvents.forEach(ev => {
                    const div = document.createElement('div');
                    div.classList.add('appointment');
                    div.innerHTML = `<h4>${ev.title}</h4>
                                     <p>Time: ${ev.time}</p>
                                     ${ev.status ? '<p>Status: '+ ev.status +'</p>' : ''}`;
                    appointmentList.appendChild(div);
                });
            } else {
                appointmentList.innerHTML += '<p>No events scheduled.</p>';
            }
        }
    });

    calendar.render();
});
</script>
<?php endif; ?>
</body>
</html>

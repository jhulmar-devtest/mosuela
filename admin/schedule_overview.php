<?php
$appointments = [
  ["title" => "John Doe - Cleaning", "date" => "2025-10-20", "time" => "10:00 AM - 11:00 AM", "type" => "Cleaning", "color" => "#22c55e"],
  ["title" => "Jane Dela Cruz - Whitening", "date" => "2025-10-20", "time" => "1:00 PM - 2:00 PM", "type" => "Whitening", "color" => "#f59e0b"],
  ["title" => "Mark Santos - Braces Check", "date" => "2025-10-21", "time" => "9:00 AM - 10:00 AM", "type" => "Braces", "color" => "#3b82f6"],
  ["title" => "Anna Lopez - Tooth Extraction", "date" => "2025-10-22", "time" => "11:00 AM - 12:00 PM", "type" => "Extraction", "color" => "#ef4444"],
  ["title" => "Liam Cruz - Checkup", "date" => "2025-10-23", "time" => "3:00 PM - 4:00 PM", "type" => "Checkup", "color" => "#8b5cf6"]
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Dentist Calendar Overview</title>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
  <style>
    .dashboard-container {
      display: grid;
      grid-template-columns: 65% 35%;
      gap: 20px;
      max-width: 1300px;
      margin: auto;
    }

    .calendar-card,
    .upcoming-card {
      background: var(--card);
      border-radius: 14px;
      box-shadow: var(--shadow);
      padding: 20px;
    }

    .calendar-card h2,
    .upcoming-card h2 {
      margin-top: 0;
      font-size: 20px;
      font-weight: 600;
      color: #333;
      margin-bottom: 15px;
    }

    #calendar {
      max-width: 100%;
      height: 600px;
    }

    /* Upcoming list styling */
    .upcoming-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
      max-height: 600px;
      overflow-y: auto;
    }

    .event-item {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #f4f6f9;
      padding: 12px 15px;
      border-radius: 12px;
      border-left: 5px solid var(--blue);
    }

    .event-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 14px;
    }

    .event-info {
      flex: 1;
    }

    .event-title {
      font-weight: 600;
      font-size: 14px;
      color: #333;
      margin-bottom: 3px;
    }

    .event-time {
      font-size: 12px;
      color: #666;
    }

    .event-date {
      font-size: 12px;
      color: #888;
      text-align: right;
    }

    @media (max-width: 1000px) {
      .dashboard-container {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <div class="dashboard-container">
    <!-- Left: Calendar -->
    <div class="calendar-card">
      <h2>🗓 Dentist Calendar</h2>
      <div id="calendar"></div>
    </div>

    <!-- Right: Upcoming Appointments -->
    <div class="upcoming-card">
      <h2>📋 Upcoming Appointments</h2>
      <div class="upcoming-list" id="upcoming-list">
        <?php foreach ($appointments as $a): ?>
          <div class="event-item" style="border-left-color: <?= $a['color'] ?>">
            <div class="event-icon" style="background: <?= $a['color'] ?>">
              <?= strtoupper(substr($a['type'], 0, 2)) ?>
            </div>
            <div class="event-info">
              <div class="event-title"><?= htmlspecialchars($a['title']) ?></div>
              <div class="event-time"><?= htmlspecialchars($a['time']) ?></div>
            </div>
            <div class="event-date"><?= date("M d, Y", strtotime($a['date'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script>
    const appointments = <?= json_encode($appointments) ?>;

    document.addEventListener("DOMContentLoaded", function() {
      const calendarEl = document.getElementById("calendar");

      const events = appointments.map(a => ({
        title: a.type,
        start: a.date,
        color: a.color
      }));

      const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        height: 600,
        events: events,
        eventDisplay: "block",
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: ""
        }
      });

      calendar.render();
    });
  </script>
</body>

</html>
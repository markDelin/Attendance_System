<?php
// calendar.php - Interactive Attendance Calendar
date_default_timezone_set("Asia/Manila");
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <style>
        .fc-event { cursor: pointer; border-radius: 4px; padding: 2px 4px; font-weight: 500; font-size: 0.85rem; border: none; }
        .fc-toolbar-title { font-size: 1.25rem !important; color: var(--text-main); }
        .fc-button { background-color: var(--primary) !important; border-color: var(--primary) !important; }
        .fc-button:hover { background-color: var(--primary-hover) !important; border-color: var(--primary-hover) !important; }
        .fc-day-today { background-color: rgba(var(--primary-rgb), 0.1) !important; }

        .calendar-wrapper {
            background: var(--bg-card); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border);
        }

        @media (max-width: 600px) {
            .calendar-wrapper { padding: 0.75rem !important; margin-top: 0.5rem !important; }
            .fc-toolbar-title { font-size: 1rem !important; }
            .fc-toolbar { flex-direction: column; gap: 10px; }
            .fc-header-toolbar { margin-bottom: 1rem !important; }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        
        <div class="animate-fade-up" style="margin-top: 2rem; margin-bottom: 2rem;">
            <div class="calendar-wrapper">
                <div id='calendar'></div>
            </div>
        </div>

    </main>

    <!-- Details Modal -->
    <div id="dayModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="animate-fade-up" style="background:var(--bg-card); border: 1px solid var(--border); width:90%; max-width:500px; padding:1.5rem; border-radius:12px; max-height:80vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3 id="modalDate" style="margin:0;">Date</h3>
                <button onclick="closeModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <div id="modalContent">Testing...</div>
            <div style="margin-top:1.5rem; text-align:right">
                 <a id="viewFullLink" href="#" class="btn btn-sm btn-primary">View Full Records</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth'
                },
                events: 'api/get_calendar_events.php',
                eventClick: function(info) {
                    showDayDetails(info.event.extendedProps.date);
                },
                dateClick: function(info) {
                    showDayDetails(info.dateStr); // Allow clicking empty days too
                },
                height: 'auto',
                aspectRatio: 1.5
            });
            calendar.render();
        });

        function showDayDetails(date) {
            const modal = document.getElementById('dayModal');
            const content = document.getElementById('modalContent');
            const title = document.getElementById('modalDate');
            const link = document.getElementById('viewFullLink');
            
            // Format nice date
            const dateObj = new Date(date);
            title.innerText = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            
            // Generate link to records
            // We don't have a direct 'filter by date' URL param for view_attendance yet, but let's assume or just link to main
            // Actually, view_attendance lists all dates. We can just link there.
            link.href = 'view_attendance.php'; 

            content.innerHTML = '<p>Loading...</p>';
            modal.style.display = 'flex';

            // Fetch specific day's records
            fetch(`api/export.php?start_date=${date}&end_date=${date}&format=json_preview`) // We might need a new endpoint or reuse one
                 .catch(() => {
                     // Since we don't have a dedicated JSON fetch for a day list in api/export, let's just show a simple message or quick fetch
                     // Using a quick inline fetch script logic here would be complex.
                     // Let's use get_recent with a date param if it supported it.
                     // OR, create a quick viewer API. For now, let's keep it simple.
                     content.innerHTML = `<p>Click "View Full Records" to see details for this day.</p>`;
                 });
                 
            // Better approach: fetch specific day status
            fetch(`api/get_dashboard_stats.php?date=${date}`) // Reuse this if we modify it, or just use export logic
            // Let's just create a small list fetcher or embedding PHP logic is messy.
            // Placeholder for now.
             content.innerHTML = `<p style="color:var(--text-muted)">Select "View Full Records" to manage this day.</p>`;
        }

        function closeModal() {
            document.getElementById('dayModal').style.display = 'none';
        }

        // Close on outside click
        window.onclick = function(event) {
            if (event.target == document.getElementById('dayModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>

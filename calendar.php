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
        .fc-event { cursor: pointer; border-radius: 8px; padding: 4px 8px; font-weight: 700; font-size: 0.76rem; border: none; box-shadow: var(--shadow-neu-out-sm); transition: all 0.2s; }
        .fc-event:hover { transform: scale(1.03) translateY(-1px); box-shadow: var(--shadow-neu-out); }
        .fc-toolbar-title { font-size: 1.25rem !important; color: var(--text-main); font-weight: 900 !important; letter-spacing: -0.03em; font-family: 'Outfit', sans-serif; }
        
        /* Premium Navigation Buttons */
        .fc-button { 
            background-color: var(--bg-card) !important; 
            border: 1px solid var(--border) !important; 
            color: var(--text-main) !important; 
            box-shadow: var(--shadow-neu-out-sm) !important; 
            font-weight: 800 !important; 
            font-size: 0.78rem !important; 
            border-radius: 12px !important; 
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important; 
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
        }
        .fc-button:hover { 
            box-shadow: var(--shadow-neu-out) !important; 
            transform: translateY(-1px); 
            border-color: var(--primary) !important;
            color: var(--primary) !important;
        }
        .fc-button-active { 
            box-shadow: var(--shadow-neu-in-sm) !important; 
            color: var(--primary) !important; 
            background-color: var(--bg-main) !important;
        }
        
        /* Modernized Today / Cell Highlights */
        .fc-day-today { 
            background-color: color-mix(in srgb, var(--primary) 8%, transparent) !important; 
            position: relative;
        }
        .fc-day-today::after {
            content: '';
            position: absolute;
            top: 6px; right: 6px;
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--primary);
            box-shadow: 0 0 8px var(--primary);
        }
        .fc-col-header-cell { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); padding: 8px 0 !important; }
        .fc-daygrid-day-number { font-weight: 800; font-size: 0.85rem; color: var(--text-main); font-family: 'Outfit', sans-serif; padding: 6px 8px !important; }
        .fc th, .fc td { border-color: color-mix(in srgb, var(--text-muted) 8%, transparent) !important; }
        
        /* Glass wrapper */
        .calendar-wrapper {
            background: var(--bg-card); 
            padding: 2.25rem; 
            border-radius: 24px; 
            box-shadow: var(--shadow-neu-out); 
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        .calendar-wrapper:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.06);
        }

        /* Backdrop-Blurred Modal */
        .day-modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.45); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .day-modal-body {
            background: var(--bg-card); 
            width: 90%; 
            max-width: 480px; 
            padding: 2.25rem;
            border-radius: 24px; 
            max-height: 80vh; 
            overflow-y: auto;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out);
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        @media (max-width: 600px) {
            .calendar-wrapper { padding: 1rem !important; margin-top: 0.5rem !important; border-radius: 16px; }
            .fc-toolbar-title { font-size: 1.05rem !important; }
            .fc-toolbar { flex-direction: column; gap: 12px; }
            .fc-header-toolbar { margin-bottom: 1.25rem !important; }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        
        <div class="animate-fade-up stagger-1" style="margin-top: 2rem; margin-bottom: 2rem;">
            <div class="calendar-wrapper interactive-glow">
                <div id='calendar'></div>
            </div>
        </div>

    </main>

    <!-- Details Modal -->
    <div id="dayModal" class="day-modal-overlay">
        <div class="day-modal-body">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.75rem; padding-bottom:1rem; border-bottom: 1px solid var(--border);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:40px; height:40px; border-radius:12px; background:color-mix(in srgb, var(--primary) 10%, transparent); color:var(--primary); display:flex; align-items:center; justify-content:center; box-shadow: var(--shadow-neu-out-sm);">
                        <i class="bi bi-calendar-event" style="font-size:1.1rem;"></i>
                    </div>
                    <h3 id="modalDate" style="margin:0; font-weight:900; font-size:1.15rem; letter-spacing:-0.03em; font-family:'Outfit', sans-serif;">Date</h3>
                </div>
                <button onclick="closeModal()" class="btn-icon" title="Close Modal">&times;</button>
            </div>
            <div id="modalContent" style="color:var(--text-muted); font-size:0.9rem; line-height:1.6; font-weight:500;">Testing...</div>
            <div style="margin-top:2rem; text-align:right">
                 <a id="viewFullLink" href="#" class="btn btn-primary" style="border-radius:50px; padding:0.65rem 1.75rem; font-size:0.8rem; font-weight:800; letter-spacing:0.02em; box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 25%, transparent);">View Full Records</a>
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

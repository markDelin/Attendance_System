<?php
// reattendance.php - Re-attendance (Past Dates)
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

// Fetch users
$subjectId = isset($_GET['subject_id']) && $_GET['subject_id'] !== "" ? intval($_GET['subject_id']) : 0;
// Default to yesterday if not set, or today? Re-attendance usually means past.
// Let's default to today but allow change.
$selectedDate = $_GET['date'] ?? date('Y-m-d');

if ($subjectId > 0) {
    // Filter: Regular students + Irregular students enrolled in this subject
    $q = "SELECT u.* 
          FROM users u 
          LEFT JOIN student_subjects ss ON u.qr_code = ss.qr_code 
          WHERE (u.student_type IS NULL OR u.student_type = 'regular') 
             OR (u.student_type = 'irregular' AND ss.subject_id = ?)
          GROUP BY u.qr_code 
          ORDER BY u.name";
    $stmt = $pdo->prepare($q);
    $stmt->execute([$subjectId]);
} else {
    // No subject selected: Show all
    $stmt = $pdo->query("SELECT * FROM users ORDER BY name");
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-attendance | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <link rel="stylesheet" href="assets/css/AnimatedList.css">
    <script src="assets/js/AnimatedList.js"></script>
    <style>
        .page-header {
            background: var(--bg-card);
            padding: 2rem 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .date-container {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-main);
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            max-width: 400px;
            margin: 0 auto 1.5rem;
        }

        .date-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .date-control {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem;
            font-size: 0.95rem;
            color: var(--text-main);
            outline: none;
            background: var(--bg-card);
            flex: 1;
        }

        .search-area {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .search-container {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(23, 23, 23, 0.05);
        }

        .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        /* High-Density Row Design */
        .student-row {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid transparent;
        }

        .student-row:hover {
            border-color: var(--primary);
            transform: translateX(4px);
            background: var(--bg-hover);
        }

        .student-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 1.05rem;
            margin: 0;
            color: var(--text-main);
        }

        .student-id {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            color: var(--text-muted);
            background: var(--bg-main);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .action-group {
            display: flex;
            gap: 6px;
        }

        .btn-status {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg-main);
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 800;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .btn-status:hover {
            transform: scale(1.1);
            z-index: 2;
        }

        /* Status Colors */
        .student-row.present { border-left-color: #10b981; }
        .student-row.late { border-left-color: #f59e0b; }
        .student-row.absent { border-left-color: #ef4444; }

        .time-stamp {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
    </style>
</head>
<body>

    <!-- Nav -->
    <?php 
    $navbar_actions = '
        <div class="btn-group">
            <button onclick="setMode(\'daily\')" id="btnDaily" class="btn btn-primary btn-sm">Daily</button>
            <button onclick="setMode(\'subject\')" id="btnSubject" class="btn btn-ghost btn-sm">Subject</button>
        </div>
    ';
    include 'includes/navbar.php'; 
    ?>
    
    <div class="container animate-fade-up" style="max-width: 800px; margin-top: 2rem;">
        <div class="date-container">
            <span class="date-label">Attendance Date</span>
            <input type="date" id="attendanceDate" class="date-control" value="<?= $selectedDate ?>" onchange="refreshData()">
        </div>
        
        <div id="subjectControls" class="mobile-force-stack" style="display:none; margin-bottom: 1.5rem; gap: 1rem;">
             <select id="subjectSelect" class="form-control" onchange="refreshData()" style="border-radius: 12px; padding: 0.8rem; width: 100%;">
                <option value="">Loading Subjects...</option>
            </select>
        </div>
        
        <!-- Stats -->
         <div id="subjectStats" style="display:none; margin-bottom: 2rem;">
            <div class="mobile-force-stack" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 12px; text-align: center; border: 1px solid rgba(16, 185, 129, 0.2);">
                    <span style="display:block; font-size: 1.5rem; font-weight: 800; color: #10b981;" id="count-present">0</span>
                    <small style="color: #10b981; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">Present</small>
                </div>
                <div style="background: rgba(245, 158, 11, 0.1); padding: 1rem; border-radius: 12px; text-align: center; border: 1px solid rgba(245, 158, 11, 0.2);">
                    <span style="display:block; font-size: 1.5rem; font-weight: 800; color: #f59e0b;" id="count-late">0</span>
                    <small style="color: #f59e0b; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">Late</small>
                </div>
                <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 12px; text-align: center; border: 1px solid rgba(239, 68, 68, 0.2);">
                    <span style="display:block; font-size: 1.5rem; font-weight: 800; color: #ef4444;" id="count-absent">0</span>
                    <small style="color: #ef4444; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">Absent</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-area">
        <div class="container" style="max-width: 800px;">
            <div class="search-container">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search by name..." autocomplete="off">
            </div>
        </div>
    </div>

    <main class="container" style="max-width: 800px;">
        <div class="scroll-list-container">
            <div class="top-gradient"></div>
            <div class="bottom-gradient"></div>
            <div id="studentList" class="scroll-list student-list no-scrollbar" style="max-height: 60vh;">
                <?php foreach ($users as $user): ?>
                    <div class="student-row animated-item" id="row-<?= $user['qr_code'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>">
                    
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <h5 class="student-name"><?= htmlspecialchars($user['name']) ?></h5>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="student-id"><?= $user['qr_code'] ?></span>
                            <span class="time-stamp" style="display: none;">
                                <i class="bi bi-check-circle-fill"></i> Saved
                            </span>
                        </div>
                    </div>

                    <div class="action-group">
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'present')" class="btn-status btn-present" title="Mark Present">P</button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'late')" class="btn-status btn-late" title="Mark Late">L</button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'absent')" class="btn-status btn-absent" title="Mark Absent">A</button>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        // Search
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.student-row').forEach(row => {
                const name = row.dataset.name.toLowerCase();
                row.style.display = name.includes(term) ? 'flex' : 'none';
            });
        });

        // SweetAlert Toast Mixin
        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false,
            timer: 2000, timerProgressBar: true
        });

        // Mode Logic
        let currentMode = 'daily'; // 'daily' or 'subject'
        let currentSubjectId = null;

        function setMode(mode) {
             currentMode = mode;
             // Update Button Styles
             document.getElementById('btnDaily').className = mode === 'daily' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
             document.getElementById('btnSubject').className = mode === 'subject' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
             
             // Update UI
             document.getElementById('subjectControls').style.display = mode === 'subject' ? 'block' : 'none';
             
             if(mode === 'subject') {
                 loadSubjects();
             } else {
                 // Daily Mode
                 refreshData(); // Reloads page with daily filter (no subject)
             }
        }
        
        function refreshData() {
             const date = document.getElementById('attendanceDate').value;
             let url = 'reattendance.php?date=' + date;
             
             if (currentMode === 'subject') {
                 const sub = document.getElementById('subjectSelect').value;
                 if(sub) url += '&subject_id=' + sub;
             }
             
             // If we are already on this page with these params, fetch status only?
             // But existing PHP loads the user list based on subject.
             // So we must verify if URL subject_id matches selected subject_id
             const urlParams = new URLSearchParams(window.location.search);
             const urlSub = urlParams.get('subject_id');
             const urlDate = urlParams.get('date');
             
             // Reload if params changed (to filter list)
             if ( (currentMode === 'subject' && urlSub != document.getElementById('subjectSelect').value) ||
                  (urlDate != date) || 
                  (currentMode === 'daily' && urlSub) // switching back to daily
             ) {
                 window.location.href = url;
                 return;
             }
             
             // If params match, just fetch statuses
             fetchStatusUpdates();
        }

        function fetchStatusUpdates() {
             const date = document.getElementById('attendanceDate').value;
             let url = 'api/get_daily_status.php?date=' + date;
             
             const subId = document.getElementById('subjectSelect').value;
             // Only use subject ID if in subject mode and ID is selected
             // Note: URL param is already used for list filtering. 
             // We need to pass subject_id to API to get subject_attendance table data vs attendance table
             
             // Check URL again to be sure what mode we are effective in
             const urlParams = new URLSearchParams(window.location.search);
             if (urlParams.has('subject_id')) {
                 url += `&subject_id=${urlParams.get('subject_id')}`;
                 currentSubjectId = urlParams.get('subject_id');
                 // Ensure mode is set visually
                 currentMode = 'subject';
                 document.getElementById('btnDaily').className = 'btn btn-ghost btn-sm';
                 document.getElementById('btnSubject').className = 'btn btn-primary btn-sm';
                 document.getElementById('subjectControls').style.display = 'block';
                 // Don't call loadSubjects here as it might reset the select value, 
                 // assume select is populated or will be handled
             } else {
                 currentMode = 'daily';
                 document.getElementById('btnDaily').className = 'btn btn-primary btn-sm';
                 document.getElementById('btnSubject').className = 'btn btn-ghost btn-sm';
                 document.getElementById('subjectControls').style.display = 'none';
                 currentSubjectId = null;
             }
             
             console.log("Fetching...", url);

             fetch(url)
             .then(r => r.json())
             .then(res => {
                 if (res.status === 'success') {
                     const data = res.data; // { qr: {status, time} }
                     
                     document.querySelectorAll('.student-row').forEach(row => {
                         const qr = row.id.replace('row-', '');
                         const item = data[qr];
                         
                         row.classList.remove('present', 'late', 'absent');
                         const timeEl = row.querySelector('.time-stamp');
                         
                         if (item) {
                             row.classList.add(item.status);
                             if (timeEl) timeEl.style.display = 'inline-block';
                         } else {
                             if(timeEl) timeEl.style.display = 'none';
                         }
                     });
                     
                     updateStatsCounts();
                 }
             })
             .catch(e => console.error(e));
        }

        function updateStatsCounts() {
            let present = 0, late = 0, absent = 0;
            document.querySelectorAll('.student-row').forEach(row => {
                if (row.classList.contains('present')) present++;
                else if (row.classList.contains('late')) late++;
                else if (row.classList.contains('absent')) absent++;
            });
            
            document.getElementById('count-present').innerText = present;
            document.getElementById('count-late').innerText = late;
            document.getElementById('count-absent').innerText = absent;
            document.getElementById('subjectStats').style.display = 'block';
        }

        // Subject Logic
        function loadSubjects() {
            fetch('api/subject_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'get_subjects' })
            })
            .then(r => r.json())
            .then(res => {
                const sel = document.getElementById('subjectSelect');
                const existingVal = sel.value; // Keep if possible
                sel.innerHTML = '<option value="">-- Select Subject to Load --</option>';
                
                if (res.data) {
                    for (const [sem, subjects] of Object.entries(res.data)) {
                        let optgroup = document.createElement('optgroup');
                        optgroup.label = sem;
                        subjects.forEach(s => {
                            let opt = document.createElement('option');
                            opt.value = s.id;
                            opt.innerText = s.name;
                            optgroup.appendChild(opt);
                        });
                        sel.appendChild(optgroup);
                    }
                }
                
                // Auto-select from URL
                const urlParams = new URLSearchParams(window.location.search);
                const urlSubId = urlParams.get('subject_id');
                if(urlSubId) {
                     sel.value = urlSubId;
                }
            });
        }

        // Set Status (The core logic)
        function setStatus(qr, name, status) {
            const date = document.getElementById('attendanceDate').value;
            
            // UI Feedback (Optimistic)
            const row = document.getElementById('row-' + qr);
            row.style.opacity = '0.7';

            if (currentMode === 'subject') {
                if (!currentSubjectId) {
                    Toast.fire({ icon: 'warning', title: 'Select a subject first!' });
                    return;
                }
                
                fetch('api/subject_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        action: 'record_attendance', 
                        subject_id: currentSubjectId,
                        qr_code: qr, 
                        status: status,
                        custom_date: date // Pass Custom Date
                    })
                })
                .then(r => r.json())
                .then(d => {
                     row.style.opacity = '1';
                     if (d.status === 'success') {
                         Toast.fire({ icon: 'success', title: `${name}: ${status.toUpperCase()} (${date})` });
                         row.classList.remove('present', 'late', 'absent');
                         row.classList.add(status);
                         row.querySelector('.time-stamp').style.display = 'inline-block';
                         updateStatsCounts();
                     } else {
                         Toast.fire({ icon: 'error', title: d.message || 'Error occurred' });
                     }
                })
                .catch(e => {
                    row.style.opacity = '1';
                    Toast.fire({ icon: 'error', title: 'Network Error' });
                });
            } 
            else {
                // Daily Mode
                fetch('api/process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        manual_entry: true, 
                        qr_code: qr, 
                        name: name, 
                        force_status: status,
                        custom_date: date // Pass Custom Date
                    })
                })
                .then(r => r.json())
                .then(d => {
                     row.style.opacity = '1';
                     if(d.status === 'success') {
                         Toast.fire({ icon: 'success', title: `${name}: ${status.toUpperCase()} (${date})` });
                         row.classList.remove('present', 'late', 'absent');
                         row.classList.add(status);
                         row.querySelector('.time-stamp').style.display = 'inline-block';
                         updateStatsCounts();
                     } else {
                         Toast.fire({ icon: 'error', title: d.message });
                     }
                });
            }
        }
        
        // Init
        document.addEventListener('DOMContentLoaded', () => {
             // Load subjects if likely in subject mode
             loadSubjects();
             // Initial Fetch
             setTimeout(fetchStatusUpdates, 500);

             // Initialize Animated List
             initAnimatedList('.scroll-list-container');
        });

    </script>
</body>
</html>

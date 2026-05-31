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
    $stmt = $pdo->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY name");
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-attendance | QR Tools by MCK</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
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

        /* Glassmorphic Date Control */
        .date-container-glass {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            background: var(--bg-card);
            padding: 1.25rem 2rem;
            border-radius: 20px;
            border: 1px solid var(--border);
            max-width: 480px;
            margin: 0 auto 2rem;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .date-container-glass:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-neu-out);
        }
        .date-label { font-size: 0.72rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; }
        .date-control-glass {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.6rem 1rem;
            font-size: 0.92rem;
            color: var(--text-main);
            outline: none;
            background: var(--bg-main);
            box-shadow: var(--shadow-neu-in-sm);
            font-weight: 700;
            transition: all 0.25s;
        }
        .date-control-glass:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent);
        }

        .search-area {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--bg-main);
            padding: 1rem 0;
            margin-bottom: 1.25rem;
        }

        /* Interactive Search Input */
        .search-container { position: relative; max-width: 600px; margin: 0 auto; }
        .search-input-glass {
            width: 100%;
            padding: 0.9rem 1.25rem 0.9rem 3.2rem;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            font-size: 0.92rem;
            color: var(--text-main);
            box-shadow: var(--shadow-neu-out-sm);
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .search-input-glass:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 20%, transparent);
            transform: scale(0.995);
        }
        .search-icon { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.05rem; }

        /* Premium Student Row Styling */
        .student-row {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border-left: 5px solid transparent;
            box-shadow: var(--shadow-neu-out-sm);
        }
        .student-row:hover {
            transform: translateX(4px) translateY(-1px);
            box-shadow: var(--shadow-neu-out);
            border-color: color-mix(in srgb, var(--text-muted) 15%, transparent);
        }

        .student-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.98rem;
            margin: 0;
            color: var(--text-main);
            transition: color 0.2s;
        }
        .student-name:hover {
            color: var(--primary);
        }

        .student-id {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            color: var(--text-muted);
            background: var(--bg-main);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid var(--border);
        }

        .action-group {
            display: flex;
            gap: 6px;
        }

        /* Premium Toggle Capsule Buttons */
        .compact-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn-status {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.76rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            background: var(--bg-main);
            color: var(--text-muted);
            border: 1px solid var(--border);
            cursor: pointer;
            box-shadow: var(--shadow-neu-out-sm);
        }
        .btn-status:hover {
            transform: translateY(-2px) scale(1.05);
            color: var(--primary);
            border-color: var(--primary);
            box-shadow: var(--shadow-neu-out);
        }

        /* Active Status Animations */
        .student-row.present { border-left-color: #10b981; background: rgba(16, 185, 129, 0.02); }
        .student-row.present .btn-status.btn-present {
            background: #10b981;
            color: white;
            border-color: #10b981;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
            transform: scale(1.08) translateY(-1px);
        }

        .student-row.late { border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.02); }
        .student-row.late .btn-status.btn-late {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
            transform: scale(1.08) translateY(-1px);
        }

        .student-row.absent { border-left-color: #ef4444; background: rgba(239, 68, 68, 0.02); }
        .student-row.absent .btn-status.btn-absent {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
            transform: scale(1.08) translateY(-1px);
        }

        .time-stamp {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--primary);
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: color-mix(in srgb, var(--primary) 10%, transparent);
            padding: 3px 8px;
            border-radius: 6px;
        }

        /* Glass Stats Cards */
        .stat-badge-glass {
            background: var(--bg-card);
            padding: 1.25rem;
            border-radius: 16px;
            text-align: center;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .stat-badge-glass:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-neu-out);
        }

        @media (max-width: 600px) {
            .student-row {
                padding: 1.25rem 1rem;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .action-group {
                justify-content: space-between;
                border-top: 1px solid var(--border);
                padding-top: 10px;
                width: 100%;
            }
            .btn-status {
                flex: 1;
                height: 40px;
            }
        }
        /* ─── Layout Classes ─── */
        .container-sm {
            max-width: 800px;
            margin-top: 2rem;
            padding-top: 1rem;
        }
        .subject-select-styled {
            border-radius: 14px;
            padding: 0.75rem 1rem;
            font-weight: 700;
            background: var(--bg-main);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-in-sm);
            color: var(--text-main);
            width: 100%;
        }
        .reattend-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        .reattend-stat-value {
            display: block;
            font-size: 1.45rem;
            font-weight: 900;
        }
        .reattend-stat-label {
            color: var(--text-muted);
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>

    <!-- Nav -->
    <?php 
    $navbar_actions = '
        <button onclick="setMode(\'daily\')" id="btnDaily" class="btn-icon active" title="Daily Mode">
            <i class="bi bi-calendar-check" style="font-size: 0.95rem;"></i>
        </button>
        <button onclick="setMode(\'subject\')" id="btnSubject" class="btn-icon" title="Subject Mode">
            <i class="bi bi-book" style="font-size: 0.95rem;"></i>
        </button>
    ';
    include 'includes/navbar.php'; 
    ?>
    
    <div class="container animate-fade-up container-sm">
        
        <!-- Glassmorphic Date Control -->
        <div class="date-container-glass stagger-1">
            <span class="date-label">Attendance Date</span>
            <input type="date" id="attendanceDate" class="date-control-glass" value="<?= $selectedDate ?>" onchange="refreshData()">
        </div>
        
        <!-- Subject Selection Area -->
        <div id="subjectControls" class="stagger-2" style="display:none; margin-bottom: 2rem;">
             <select id="subjectSelect" class="subject-select-styled" onchange="refreshData()">
                <option value="">Loading Subjects...</option>
            </select>
        </div>
        
        <!-- Stats Grid -->
         <div id="subjectStats" class="stagger-3" style="display:none; margin-bottom: 2.25rem;">
            <div class="reattend-stats-grid">
                <div class="stat-badge-glass" style="border-top: 3px solid #10b981;">
                    <span class="reattend-stat-value" style="color: #10b981;" id="count-present">0</span>
                    <small class="reattend-stat-label">Present</small>
                </div>
                <div class="stat-badge-glass" style="border-top: 3px solid #f59e0b;">
                    <span class="reattend-stat-value" style="color: #f59e0b;" id="count-late">0</span>
                    <small class="reattend-stat-label">Late</small>
                </div>
                <div class="stat-badge-glass" style="border-top: 3px solid #ef4444;">
                    <span class="reattend-stat-value" style="color: #ef4444;" id="count-absent">0</span>
                    <small class="reattend-stat-label">Absent</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-area stagger-4">
        <div class="container" style="max-width: 800px;">
            <div class="search-container">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input-glass" placeholder="Search by name..." autocomplete="off">
            </div>
        </div>
    </div>

    <main class="container animate-fade-up stagger-5" style="max-width: 800px;">
        <div class="scroll-list-container">
            <div class="top-gradient"></div>
            <div class="bottom-gradient"></div>
            <div id="studentList" class="scroll-list student-list no-scrollbar" style="max-height: 60vh;">
                <?php 
                $idx = 0;
                foreach ($users as $user): 
                    $staggerClass = 'stagger-' . (($idx % 8) + 1);
                    $idx++;
                ?>
                    <div class="student-row animated-item interactive-glow <?= $staggerClass ?>" id="row-<?= $user['qr_code'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>">
                    
                    <div style="display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 0;">
                        <h5 class="student-name"><?= htmlspecialchars($user['name']) ?></h5>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                            <span class="student-id"><?= $user['qr_code'] ?></span>
                             <span class="time-stamp" style="display: none;">
                                <i class="bi bi-check-circle"></i> Saved
                            </span>
                        </div>
                    </div>

                    <div class="action-group">
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'present')" class="btn-status btn-present" title="Mark Present">P</button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'late')" class="btn-status btn-late" title="Mark Late">L</button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'absent')" class="btn-status btn-absent" title="Mark Absent">A</button>
                        <button onclick="clearStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>')" class="btn-status" title="Clear Record" style="color:var(--text-muted); font-size: 0.72rem;"><i class="bi bi-trash"></i></button>
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
             const bD = document.getElementById('btnDaily');
             const bS = document.getElementById('btnSubject');
             
             if (bD && bS) {
                 if(mode === 'daily') {
                     bD.className = 'btn-icon active';
                     bS.className = 'btn-icon';
                 } else {
                     bS.className = 'btn-icon active';
                     bD.className = 'btn-icon';
                 }
             }
             
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

        function clearStatus(qr, name) {
            const date = document.getElementById('attendanceDate').value;
            const row = document.getElementById('row-' + qr);
            
            Swal.fire({
                title: 'Clear Record?',
                text: `Remove attendance for ${name} on ${date}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, clear'
            }).then((res) => {
                if (res.isConfirmed) {
                    row.style.opacity = '0.7';
                    
                    let api = 'api/delete.php';
                    let formData = new FormData();
                    
                    if (currentMode === 'subject') {
                        api = 'api/delete_subject.php';
                        formData.append('type', 'subject_record_by_qr');
                        formData.append('qr_code', qr);
                        formData.append('subject_id', currentSubjectId);
                        formData.append('date', date);
                    } else {
                        formData.append('qr_code', qr);
                        formData.append('date', date);
                        formData.append('action', 'delete_by_qr');
                    }

                    fetch(api, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(d => {
                        row.style.opacity = '1';
                        if (d.status === 'success') {
                            row.classList.remove('present', 'late', 'absent');
                            const ts = row.querySelector('.time-stamp');
                            if(ts) ts.style.display = 'none';
                            
                            Swal.fire({
                                title: 'Cleared!',
                                text: 'Attendance record removed.',
                                icon: 'success',
                                confirmButtonColor: 'var(--primary)'
                            });
                            updateStatsCounts();
                        } else {
                            Swal.fire('Error', d.message, 'error');
                        }
                    });
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

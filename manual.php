<?php
// manual.php - Manual Entry
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$isMaintenance = ($settings['maintenance_mode'] ?? 0) == 1;

if ($isMaintenance) {
    echo "<!DOCTYPE html><html><head><title>System Maintenance</title><link href='assets/css/style.css' rel='stylesheet'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body style='background:var(--bg-main); display:flex; align-items:center; justify-content:center; height:100vh; font-family:sans-serif;'>";
    echo "<script>Swal.fire({icon:'info', title:'System Maintenance', text:'Manual entry is currently locked for maintenance. Please check back later.', showConfirmButton:false, allowOutsideClick:false, footer:'<a href=\"index.php\">Back to Dashboard</a>'});</script>";
    echo "</body></html>";
    exit;
}

// Fetch users
$subjectId = isset($_GET['subject_id']) && $_GET['subject_id'] !== "" ? intval($_GET['subject_id']) : 0;
$currentDate = $_GET['date'] ?? date('Y-m-d');

if ($subjectId > 0) {
    // Subject/Event Mode: Regular (All) + Irregular (Enrolled)
    $q = "SELECT u.* 
          FROM users u 
          LEFT JOIN student_subjects ss ON u.qr_code = ss.qr_code 
          WHERE (u.student_type IS NULL OR u.student_type = 'regular') 
             OR (u.student_type = 'irregular' AND ss.subject_id = ?)
          AND u.deleted_at IS NULL
          GROUP BY u.qr_code 
          ORDER BY u.name";
    $stmt = $pdo->prepare($q);
    $stmt->execute([$subjectId]);
} else {
    // Daily Mode: Show all active students
    $stmt = $pdo->prepare("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY name");
    $stmt->execute();
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine Title
$subjectName = "";
if ($subjectId > 0) {
    $sStmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
    $sStmt->execute([$subjectId]);
    $subjectName = $sStmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manual Entry | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .page-header { background: var(--bg-card); padding: 1.5rem 0; border-bottom: 1px solid var(--border); margin-bottom: 1.5rem; }
        .date-container { display: flex; align-items: center; gap: 12px; background: var(--bg-main); padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid var(--border); max-width: 400px; margin: 0 auto 1.5rem; }
        .date-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .date-control { border: 1px solid var(--border); border-radius: 8px; padding: 0.4rem; font-size: 0.9rem; color: var(--text-main); outline: none; background: var(--bg-main); flex: 1; }
        .toolbar-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .search-area { position: sticky; top: 0; z-index: 90; background: var(--glass-bg); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); padding: 1rem 0; border-bottom: 1px solid var(--border); margin-bottom: 1.5rem; }
        .search-container { position: relative; max-width: 600px; margin: 0 auto; }
        .search-input { width: 100%; padding: 0.75rem 1rem 0.75rem 3rem; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-main); font-size: 0.95rem; color: var(--text-main); }
        .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(23, 23, 23, 0.05); }
        .search-icon { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .student-row { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); border-left: 4px solid transparent; }
        .student-row:hover { border-color: var(--primary); transform: translateX(4px); background: var(--bg-hover); }
        .student-name { font-family: 'Outfit', sans-serif; font-weight: 600; font-size: 1.05rem; margin: 0; color: var(--text-main); }
        .student-id { font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; color: var(--text-muted); background: var(--bg-main); padding: 2px 6px; border-radius: 4px; }
        .action-group { display: flex; gap: 6px; }
        .btn-status { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-main); cursor: pointer; transition: all 0.2s; font-weight: 800; font-size: 0.85rem; color: var(--text-muted); }
        .btn-status:hover { transform: scale(1.1); z-index: 2; }
        .student-row.present { border-left-color: #10b981; background: rgba(16, 185, 129, 0.05); }
        .student-row.present .btn-present { background: #10b981; color: white; border-color: #10b981; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }
        .student-row.late { border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.05); }
        .student-row.late .btn-late { background: #f59e0b; color: white; border-color: #f59e0b; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }
        .student-row.absent { border-left-color: #ef4444; background: rgba(239, 68, 68, 0.05); }
        .student-row.absent .btn-absent { background: #ef4444; color: white; border-color: #ef4444; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3); }
        .time-stamp { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary); font-weight: 800; display: inline-flex; align-items: center; gap: 4px; }
        .stat-badge { flex: 1; padding: 0.75rem; border-radius: 12px; text-align: center; border: 1px solid var(--border); background: var(--bg-card); transition: all 0.3s; }
        @media (max-width: 600px) { 
            .mobile-stack { flex-direction: column; align-items: stretch !important; } 
            .student-row { 
                padding: 1.25rem 1rem; 
                flex-direction: column; 
                align-items: stretch;
                gap: 12px;
            } 
            .student-name { font-size: 1rem; } 
            .action-group {
                justify-content: space-between;
                border-top: 1px solid rgba(0,0,0,0.05);
                padding-top: 8px;
            }
            .btn-status {
                flex: 1;
                height: 44px;
            }
        }

        /* Subject Toolbar Icons */
        .action-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-main);
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            padding: 0;
        }
        .action-btn i { font-size: 0.9rem; }
        .action-btn:hover { background: var(--bg-hover); color: var(--primary); border-color: var(--primary); transform: translateY(-2px); }
        .action-btn.delete-btn:hover { color: var(--danger); border-color: var(--danger); }
    </style>
</head>
<body>

    <?php 
    $navbar_actions = '
        <div class="nav-tabs" style="margin-bottom:0; background: rgba(0,0,0,0.05);">
            <button onclick="setMode(\'daily\')" id="btnDaily" class="nav-link">Daily</button>
            <button onclick="setMode(\'subject\')" id="btnSubject" class="nav-link">Subject</button>
        </div>
    ';
    include 'includes/navbar.php'; 
    ?>

    <main class="container animate-fade-up" style="max-width: 800px; margin-top: 2rem; padding-top: 1rem;">
        
        <div class="glass-panel" style="padding: 1.5rem; border-radius: 20px; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 15px;">
                <span class="date-label">Session Date</span>
                <input type="date" id="attendanceDate" class="form-control" style="max-width: 200px; border-radius: 12px;" value="<?= date('Y-m-d', strtotime($currentDate)) ?>" onchange="refreshData()">
            </div>
        </div>

        <!-- Subject Selection Area -->
        <div id="subjectControls" style="display:none; margin-bottom: 2rem;">
            <div class="card" style="padding: 1.25rem; border-radius: 20px; border: 1px solid var(--border); background: white; box-shadow: var(--glass-shadow);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h6 style="margin:0; font-weight:800; color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; letter-spacing:0.1em;">Subject Access</h6>
                    <div id="subjectActions" style="display:none; gap:12px;">
                       <button onclick="editCurrentSubject()" class="action-btn edit-btn"><i class="bi bi-pencil"></i></button>
                       <button onclick="manageSchedule()" class="action-btn"><i class="bi bi-calendar3"></i></button>
                       <button onclick="deleteCurrentSubject()" class="action-btn delete-btn"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr auto; gap:12px;" class="mobile-stack">
                    <select id="subjectSelect" class="form-control" onchange="handleSubjectChange()" style="border-radius:12px;">
                        <option value="">Select Subject...</option>
                    </select>
                    <div style="display:flex; gap:8px;">
                        <button onclick="goToScan()" class="btn btn-slate" id="btnScanSubject" style="display:none; border-radius:12px; height:45px; width:45px; padding:0; justify-content:center;"><i class="bi bi-qr-code-scan"></i></button>
                        <button onclick="openAddSubject()" class="btn btn-primary" style="border-radius:12px; height:45px; width:45px; padding:0; justify-content:center;"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>

                <div id="subjectQuickTools" style="display:none; margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; gap:8px;">
                        <button onclick="markAllPresent()" class="btn btn-ghost btn-sm"><i class="bi bi-check-all"></i> Mark All</button>
                        <button onclick="resetSubjectAttendance()" class="btn btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                        <button onclick="cancelSubject()" class="btn btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-slash-circle"></i> Cancel Class</button>
                    </div>
                    <button onclick="exportSubject()" class="btn btn-ghost btn-sm"><i class="bi bi-download"></i> Export</button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div id="subjectStats" style="display:none; margin-top: 1.5rem; margin-bottom: 2rem;">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem;">
                    <div class="stat-badge glass-panel">
                        <span style="display:block; font-size: 1.1rem; font-weight: 800; color: var(--success);" id="count-present">0</span>
                        <small style="font-size:0.6rem; font-weight:700; color:var(--text-muted);">P</small>
                    </div>
                    <div class="stat-badge glass-panel">
                        <span style="display:block; font-size: 1.1rem; font-weight: 800; color: var(--warning);" id="count-late">0</span>
                        <small style="font-size:0.6rem; font-weight:700; color:var(--text-muted);">L</small>
                    </div>
                    <div class="stat-badge glass-panel">
                        <span style="display:block; font-size: 1.1rem; font-weight: 800; color: var(--danger);" id="count-absent">0</span>
                        <small style="font-size:0.6rem; font-weight:700; color:var(--text-muted);">A</small>
                    </div>
                    <div class="stat-badge glass-panel">
                        <span style="display:block; font-size: 1.1rem; font-weight: 800; color: var(--primary);" id="count-none">0</span>
                        <small style="font-size:0.6rem; font-weight:700; color:var(--text-muted);">LEFT</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Area -->
        <div class="search-container" style="margin-bottom: 2rem;">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="form-control" placeholder="Search entries..." autocomplete="off" style="padding-left: 3rem; border-radius: 12px; box-shadow: var(--glass-shadow);">
        </div>

        <!-- Context Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
            <div>
                <p id="manualContextIndicator" style="font-size: 0.7rem; color: var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing:0.1em; margin:0; line-height:1.5;">Daily Attendance</p>
                <h5 style="color: var(--text-main); font-weight: 800; margin: 0; font-size:1.4rem; letter-spacing:-0.03em;">Attendance List</h5>
            </div>
            <button id="btnNotify" onclick="finishAndNotifySubject()" class="btn btn-primary" style="border-radius:12px; padding: 0.6rem 1.5rem; display: flex; align-items: center; gap: 8px;">
                <i class="bi bi-send-check"></i> <span class="btn-text">Notify</span>
            </button>
        </div>

        <!-- Student List -->
        <div id="studentList" class="student-list">
            <?php foreach ($users as $user): ?>
                <div class="student-row" id="row-<?= $user['qr_code'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>">
                    <div style="flex: 1; min-width: 0;">
                        <h5 class="student-name" onclick="window.location.href='profile.php?qr=<?= urlencode($user['qr_code']) ?>'" style="cursor:pointer; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($user['name']) ?></h5>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="student-id" style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700;"><?= $user['qr_code'] ?></span>
                            <div class="time-stamp" style="display: none; font-size: 0.7rem; color: var(--primary); font-weight: 800;">
                                <i class="bi bi-clock-fill"></i> <span class="time-val"></span>
                            </div>
                        </div>
                    </div>

                    <div class="compact-actions">
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'present')" class="btn-stat-entry p" title="Present">P</button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'late')" class="btn-stat-entry l" title="Late">L</button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'absent')" class="btn-stat-entry a" title="Absent">A</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        // Global variables for mode control
        const urlParams = new URLSearchParams(window.location.search);
        let currentSubjectId = urlParams.get('subject_id') || "";
        let currentMode = currentSubjectId ? 'subject' : 'daily';

        // SweetAlert Toast
        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false,
            timer: 2000, timerProgressBar: true
        });

        function setMode(mode) {
            currentMode = mode;
            
            // UI Toggle
            document.getElementById('btnDaily').className = mode === 'daily' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
            document.getElementById('btnSubject').className = mode === 'subject' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
            document.getElementById('subjectControls').style.display = mode === 'subject' ? 'block' : 'none';
            
            if (mode === 'daily') {
                if (currentSubjectId !== "") {
                    // Redirect to daily mode if we were in a subject
                    window.location.href = 'manual.php';
                } else {
                    refreshUI();
                }
            } else {
                // Initialize subjects if they aren't loaded yet
                const sel = document.getElementById('subjectSelect');
                if (sel.options.length <= 1) {
                    loadSubjects();
                }
            }
        }

        function handleSubjectChange() {
            const newSubId = document.getElementById('subjectSelect').value;
            if (newSubId !== currentSubjectId) {
                const date = document.getElementById('attendanceDate').value;
                window.location.href = `manual.php?subject_id=${newSubId}&date=${date}`;
            }
        }

        function refreshData() {
            const date = document.getElementById('attendanceDate').value;
            const subPart = currentSubjectId ? `&subject_id=${currentSubjectId}` : '';
            window.location.href = `manual.php?date=${date}${subPart}`;
        }

        function loadSubjects() {
            fetch('api/subject_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'get_subjects' })
            })
            .then(r => r.json())
            .then(res => {
                const sel = document.getElementById('subjectSelect');
                sel.innerHTML = '<option value="">Select Subject...</option>';
                if (res.data) {
                    for (const [sy, semData] of Object.entries(res.data)) {
                        let syGroup = document.createElement('optgroup');
                        syGroup.label = sy;
                        for (const [sem, subs] of Object.entries(semData)) {
                            subs.forEach(s => {
                                let opt = document.createElement('option');
                                opt.value = s.id;
                                opt.innerText = `[${sem}] ${s.name}`;
                                if (s.id == currentSubjectId) opt.selected = true;
                                syGroup.appendChild(opt);
                            });
                        }
                        sel.appendChild(syGroup);
                    }
                }
                updateSubjectUIState();
            });
        }

        function updateSubjectUIState() {
            if (currentSubjectId) {
                document.getElementById('subjectActions').style.display = 'flex';
                document.getElementById('subjectQuickTools').style.display = 'flex';
                document.getElementById('subjectStats').style.display = 'block';
                document.getElementById('btnScanSubject').style.display = 'flex';
                fetchStatusUpdates();
            }
        }

        function fetchStatusUpdates() {
            const date = document.getElementById('attendanceDate').value;
            let url = `api/get_daily_status.php?date=${date}`;
            if (currentSubjectId) url += `&subject_id=${currentSubjectId}`;

            fetch(url)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    const data = res.data;
                    document.querySelectorAll('.student-row').forEach(row => {
                        const qr = row.id.replace('row-', '');
                        const item = data[qr];
                        row.classList.remove('present', 'late', 'absent', 'no-class');
                        const timeEl = row.querySelector('.time-stamp');
                        const timeVal = row.querySelector('.time-val');
                        
                        if (item) {
                            row.classList.add(item.status);
                            if (timeEl && item.time) {
                                timeEl.style.display = 'inline-flex';
                                timeVal.innerText = item.time;
                            }
                        } else {
                            if(timeEl) timeEl.style.display = 'none';
                        }
                    });
                    updateContextHeader(res.is_notified);
                    updateStatsCounts();
                }
            });
        }

        function updateContextHeader(isNotified) {
            const indicator = document.getElementById('manualContextIndicator');
            const dateStr = new Date(document.getElementById('attendanceDate').value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            
            if (currentMode === 'subject' && currentSubjectId) {
                const sel = document.getElementById('subjectSelect');
                const text = sel.options[sel.selectedIndex]?.text || 'Subject Attendance';
                indicator.innerText = `${text} • ${dateStr}`;
            } else {
                indicator.innerText = `Daily Morning Attendance • ${dateStr}`;
            }

            const btnNotify = document.getElementById('btnNotify');
            if (isNotified) {
                btnNotify.innerHTML = '<i class="bi bi-check-all"></i> Notified';
                btnNotify.disabled = true;
                btnNotify.className = 'btn btn-ghost btn-sm';
            } else {
                btnNotify.innerHTML = '<i class="bi bi-send-check"></i> Notify';
                btnNotify.disabled = false;
                btnNotify.className = 'btn btn-primary btn-sm';
            }
        }

        function updateStatsCounts() {
            let p = 0, l = 0, a = 0, n = 0, nc = 0;
            document.querySelectorAll('.student-row').forEach(row => {
                if (row.classList.contains('present')) p++;
                else if (row.classList.contains('late')) l++;
                else if (row.classList.contains('absent')) a++;
                else if (row.classList.contains('no-class')) nc++;
                else n++;
            });
            document.getElementById('count-present').innerText = p;
            document.getElementById('count-late').innerText = l;
            document.getElementById('count-absent').innerText = a;
            document.getElementById('count-none').innerText = n;
        }

        function setStatus(qr, name, status) {
            const date = document.getElementById('attendanceDate').value;
            const row = document.getElementById('row-' + qr);
            
            // Immediate UI feedback
            row.style.opacity = '0.7';
            row.classList.remove('present', 'late', 'absent', 'no-class');
            row.classList.add(status);

            let api = 'api/process.php';
            let params = { qr_code: qr, force_status: status, custom_date: date, manual_entry: true };
            if (currentMode === 'subject') {
                if (!currentSubjectId) return Toast.fire({ icon: 'warning', title: 'Select a subject!' });
                api = 'api/subject_process.php';
                params = { action: 'record_attendance', subject_id: currentSubjectId, qr_code: qr, status: status, custom_date: date };
            }

            fetch(api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params)
            })
            .then(r => r.json())
            .then(d => {
                row.style.opacity = '1';
                if (d.status === 'success' || d.status === 'duplicate') {
                    const ts = row.querySelector('.time-stamp');
                    const tv = row.querySelector('.time-val');
                    const sid = row.querySelector('.student-id');
                    
                    if (d.time) {
                        ts.style.display = 'block';
                        tv.innerText = d.time;
                        sid.style.display = 'none';
                    }

                    Toast.fire({ icon: 'success', title: `${name}: ${status.toUpperCase()}` });
                    updateStatsCounts();
                } else {
                    Toast.fire({ icon: 'error', title: d.message });
                    row.classList.remove(status);
                }
            })
            .catch(() => { 
                row.style.opacity = '1'; 
                row.classList.remove(status);
            });
        }

        // --- Toolbar Actions ---
        function openAddSubject() {
             Swal.fire({
                title: 'New Subject',
                html: `
                    <div style="text-align:left;">
                        <input id="swal-sy" class="swal2-input" placeholder="School Year (e.g. 2024-2025)">
                        <input id="swal-sem" class="swal2-input" placeholder="Semester (e.g. 1st Semester)">
                        <input id="swal-name" class="swal2-input" placeholder="Subject Name">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: 'var(--primary)',
                preConfirm: () => {
                    return {
                        sy: document.getElementById('swal-sy').value,
                        sem: document.getElementById('swal-sem').value,
                        name: document.getElementById('swal-name').value
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ 
                            action: 'add_subject', 
                            name: result.value.name, 
                            semester: result.value.sem, 
                            school_year: result.value.sy 
                        })
                    }).then(() => loadSubjects());
                }
            });
        }

        function finishAndNotifySubject() {
             const subId = currentSubjectId || '0';
             Swal.fire({
                 title: 'Finish & Notify?',
                 text: 'Mark missing students as Absent and send report?',
                 icon: 'question', showCancelButton: true, confirmButtonColor: 'var(--primary)',
                 showLoaderOnConfirm: true,
                 preConfirm: () => {
                     const formData = new FormData();
                     formData.append('subject_id', subId);
                     const date = document.getElementById('attendanceDate').value;
                     formData.append('date', date);
                     return fetch('api/mark_absentees.php', { method: 'POST', body: formData }).then(r => r.json());
                 }
             }).then((res) => {
                 if(res.isConfirmed && res.value?.status === 'success') {
                     Swal.fire('Sent!', res.value.message, 'success');
                     fetchStatusUpdates();
                 } else if (res.value) {
                     Swal.fire('Error', res.value.message, 'error');
                 }
             });
        }

        function markAllPresent() {
             if(!currentSubjectId) return;
             const date = document.getElementById('attendanceDate').value;
             fetch('api/subject_process.php', {
                method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'mark_all', subject_id: currentSubjectId, date: date })
             }).then(() => fetchStatusUpdates());
        }

        function resetSubjectAttendance() {
             if(!currentSubjectId) return;
             const date = document.getElementById('attendanceDate').value;
             Swal.fire({
                 title: 'Reset Attendance?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444',
                 confirmButtonText: 'Yes, Reset'
             }).then((res) => {
                 if(res.isConfirmed) {
                     fetch('api/subject_process.php', {
                        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'reset_attendance', subject_id: currentSubjectId, date: date })
                    }).then(() => fetchStatusUpdates());
                 }
             });
        }

        function cancelSubject() {
            if(!currentSubjectId) return;
            const date = document.getElementById('attendanceDate').value;
            Swal.fire({
                title: 'Cancel this Class?',
                text: "This will send a broadcast to Telegram and lock attendance for today.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, Cancel & Notify'
            }).then((res) => {
                if(res.isConfirmed) {
                    Swal.fire({ title: 'Sending...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    fetch('api/subject_process.php', {
                        method: 'POST', 
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'cancel_class', subject_id: currentSubjectId, date: date })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.status === 'success') {
                            Swal.fire('Broadcasted!', res.message, 'success').then(() => fetchStatusUpdates());
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    });
                }
            });
        }

        function goToScan() { if(currentSubjectId) window.location.href = `scan.php?subject_id=${currentSubjectId}`; }

        function manageSchedule() {
            if(!currentSubjectId) return;
            fetch('api/subject_process.php', {
                method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'get_schedules', subject_id: currentSubjectId })
            })
            .then(r => r.json())
            .then(res => {
                let html = '<div style="text-align:left; margin-bottom:1rem;">';
                if(res.data && res.data.length > 0) {
                     res.data.forEach(s => { html += `<div style="display:flex; justify-content:space-between; border-bottom:1px solid #eee; padding:5px;"><span><b>${s.day_of_week}</b>: ${s.start_time} - ${s.end_time}</span><button onclick="window.deleteSchedule(${s.id})" class="btn btn-sm btn-ghost" style="color:red; padding:0 5px;"><i class="bi bi-trash"></i></button></div>`; });
                } else html += '<small>No schedule set.</small>';
                html += '</div><div style="border-top:1px solid #ccc; padding-top:10px;"><select id="swal-sch-day" class="swal2-input" style="margin-bottom:10px;"><option value="Monday">Monday</option><option value="Tuesday">Tuesday</option><option value="Wednesday">Wednesday</option><option value="Thursday">Thursday</option><option value="Friday">Friday</option><option value="Saturday">Saturday</option><option value="Sunday">Sunday</option></select><div style="display:flex; gap:10px;"><input type="time" id="swal-sch-start" class="swal2-input"><input type="time" id="swal-sch-end" class="swal2-input"></div></div>';
                
                Swal.fire({
                    title: 'Manage Schedule', html: html, showCloseButton: true, showCancelButton: true, confirmButtonText: 'Add Schedule',
                    preConfirm: () => [document.getElementById('swal-sch-day').value, document.getElementById('swal-sch-start').value, document.getElementById('swal-sch-end').value]
                }).then((result) => {
                    if (result.isConfirmed) {
                        const [day, start, end] = result.value;
                        if(!start || !end) return Swal.fire('Error', 'Times required', 'error');
                        fetch('api/subject_process.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action: 'add_schedule', subject_id: currentSubjectId, day: day, start: start, end: end }) }).then(() => manageSchedule());
                    }
                });
            });
        }

        window.deleteSchedule = (id) => {
             fetch('api/subject_process.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action: 'delete_schedule', id: id }) }).then(() => { Swal.close(); manageSchedule(); });
        };

        function editCurrentSubject() {
            if(!currentSubjectId) return;
            const sel = document.getElementById('subjectSelect');
            const currentText = sel.options[sel.selectedIndex].text;
            
            // Extract roughly Name/SY/Sem if possible, but simplest is just rename
            Swal.fire({
                title: 'Rename Subject',
                input: 'text',
                inputValue: currentText.split('] ')[1] || currentText,
                showCancelButton: true,
                confirmButtonColor: 'var(--primary)',
                inputValidator: (value) => { if (!value) return 'Name required!' }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ 
                            action: 'update_subject', 
                            subject_id: currentSubjectId, 
                            name: result.value 
                        })
                    }).then(() => loadSubjects());
                }
            });
        }

        function deleteCurrentSubject() {
            if(!currentSubjectId) return;
            Swal.fire({
                title: 'Delete Subject?',
                text: "All attendance records for this subject will be lost!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--danger)',
                confirmButtonText: 'Yes, Delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ 
                            action: 'delete_subject', 
                            subject_id: currentSubjectId 
                        })
                    }).then(() => window.location.href = 'manual.php');
                }
            });
        }

        // --- Event Listeners ---
        const searchInp = document.getElementById('searchInput');
        if (searchInp) {
            searchInp.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('.student-row').forEach(row => {
                    row.style.display = row.dataset.name.toLowerCase().includes(term) ? 'flex' : 'none';
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
             // Initialize Mode
             setMode(currentMode);
             
             // Initial Subject Load if needed
             if (currentMode === 'subject') loadSubjects();
             
             // Initial Status Load
             fetchStatusUpdates();
             
             // Auto-Refresh
             setInterval(fetchStatusUpdates, 5000);
             
             // Detection for ongoing class
             if (currentMode === 'daily') {
                 fetch('api/subject_process.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action: 'get_current_subject' }) })
                 .then(r => r.json()).then(d => {
                     if(d.status === 'success') {
                         Swal.fire({
                             title: 'Class Ongoing', text: `Time for ${d.data.name}. Switch?`, icon: 'info',
                             showCancelButton: true, confirmButtonColor: 'var(--primary)', confirmButtonText: 'Yes, Switch'
                         }).then((res) => { if(res.isConfirmed) { currentSubjectId = d.data.id; handleSubjectChange(); } });
                     }
                 });
             }
        });
    </script>
</body>
</html>

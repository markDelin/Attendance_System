<?php
// manual.php - Manual Entry
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
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
          WHERE u.deleted_at IS NULL
          AND ((u.student_type IS NULL OR u.student_type = 'regular')
             OR (u.student_type = 'irregular' AND ss.subject_id = ?))
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
        
        /* Glassmorphic Date Panel */
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

        .toolbar-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .search-area { position: sticky; top: 0; z-index: 90; background: var(--bg-main); padding: 1rem 0; margin-bottom: 1.25rem; }
        
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
        
        .action-group { display: flex; gap: 6px; }
        
        /* Premium Toggle Capsule Buttons */
        .compact-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn-stat-entry {
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
        .btn-stat-entry:hover {
            transform: translateY(-2px) scale(1.05);
            color: var(--primary);
            border-color: var(--primary);
            box-shadow: var(--shadow-neu-out);
        }
        
        /* Active Status Animations */
        .student-row.present { border-left-color: #10b981; background: rgba(16, 185, 129, 0.02); }
        .student-row.present .btn-stat-entry.p {
            background: #10b981;
            color: white;
            border-color: #10b981;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
            transform: scale(1.08) translateY(-1px);
        }
        
        .student-row.late { border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.02); }
        .student-row.late .btn-stat-entry.l {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
            transform: scale(1.08) translateY(-1px);
        }
        
        .student-row.absent { border-left-color: #ef4444; background: rgba(239, 68, 68, 0.02); }
        .student-row.absent .btn-stat-entry.a {
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

        /* High-fidelity Glass Stats Cards */
        .stat-badge {
            flex: 1;
            padding: 1rem 0.75rem;
            border-radius: 16px;
            text-align: center;
            background: var(--bg-card);
            box-shadow: var(--shadow-neu-out-sm);
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .stat-badge:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-neu-out);
        }
        .stat-badge span {
            font-family: 'Outfit', sans-serif;
            text-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        @media (max-width: 600px) {
            .date-container-glass { max-width: 95%; padding: 1rem 1.25rem; gap: 10px; }
            .student-row { flex-direction: column; align-items: stretch; padding: 1rem 1.1rem; }
            .compact-actions { justify-content: space-between; padding-top: 0.85rem; border-top: 1px solid var(--border); }
            .compact-actions .btn, .compact-actions button { flex: 1; }
            .student-info { margin-bottom: 0; }
            .page-header { padding: 0.75rem 0; }
            .mobile-stack { grid-template-columns: 1fr !important; }
        }

        /* ─── Layout Classes ─── */
        .container-sm {
            max-width: 800px;
            margin-top: 2rem;
            padding-top: 1rem;
        }
        .subject-panel {
            padding: 1.5rem;
            border-radius: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out);
        }
        .subject-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .subject-panel-header h6 {
            margin: 0;
            font-weight: 800;
            color: var(--text-muted);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .subject-panel-actions {
            display: none;
            gap: 10px;
        }
        .subject-select-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
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
        .btn-subj-action {
            border-radius: 14px;
            height: 46px;
            width: 46px;
            padding: 0;
            justify-content: center;
        }
        .btn-subj-scan {
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out-sm);
        }
        .btn-subj-add {
            box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 25%, transparent);
        }
        .subject-quick-tools {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .subject-tools-left {
            display: flex;
            gap: 8px;
        }
        .subject-tools-left .btn,
        .subject-tools-left button {
            font-weight: 700;
            border-radius: 10px;
        }
        .btn-export-subj {
            font-weight: 700;
            border-radius: 10px;
        }
        .stats-subject-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.85rem;
        }
        .stat-value {
            display: block;
            font-size: 1.35rem;
            font-weight: 900;
        }
        .stat-value.present { color: #10b981; }
        .stat-value.late { color: #f59e0b; }
        .stat-value.absent { color: #ef4444; }
        .stat-value.remaining { color: var(--primary); }
        .stat-label-sm {
            font-size: 0.62rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .context-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 1.75rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1.25rem;
        }
    </style>
</head>
<body>

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

    <main class="container animate-fade-up container-sm">
        
        <!-- Glassmorphic Date Control -->
        <div class="date-container-glass stagger-1">
            <span class="date-label">Session Date</span>
            <input type="date" id="attendanceDate" class="date-control-glass" value="<?= date('Y-m-d', strtotime($currentDate)) ?>" onchange="refreshData()">
        </div>

        <!-- Subject Selection Area -->
        <div id="subjectControls" style="display:none; margin-bottom: 2rem;" class="stagger-2">
            <div class="subject-panel">
                <div class="subject-panel-header">
                    <h6>Subject Portal</h6>
                    <div id="subjectActions" class="subject-panel-actions">
                       <button onclick="editCurrentSubject()" class="action-btn edit-btn" title="Rename Subject"><i class="bi bi-pencil"></i></button>
                       <button onclick="manageSchedule()" class="action-btn" title="Schedule Settings"><i class="bi bi-calendar3"></i></button>
                       <button onclick="deleteCurrentSubject()" class="action-btn delete-btn" title="Delete Subject"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                
                <div class="subject-select-grid mobile-stack">
                    <select id="subjectSelect" class="subject-select-styled" onchange="handleSubjectChange()">
                        <option value="">Select Subject...</option>
                    </select>
                    <div style="display:flex; gap:8px;">
                        <button onclick="goToScan()" class="btn btn-slate btn-subj-action btn-subj-scan" id="btnScanSubject" style="display:none;"><i class="bi bi-qr-code-scan"></i></button>
                        <button onclick="openAddSubject()" class="btn btn-primary btn-subj-action btn-subj-add"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>

                <div id="subjectQuickTools" class="subject-quick-tools" style="display:none;">
                    <div class="subject-tools-left">
                        <button onclick="markAllPresent()" class="btn btn-ghost btn-sm"><i class="bi bi-check-all"></i> Mark All</button>
                        <button onclick="resetSubjectAttendance()" class="btn btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                        <button onclick="cancelSubject()" class="btn btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-slash-circle"></i> Cancel Class</button>
                    </div>
                    <button onclick="exportSubject()" class="btn btn-ghost btn-sm btn-export-subj"><i class="bi bi-download"></i> Export</button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div id="subjectStats" style="display:none; margin-top: 1.5rem; margin-bottom: 2rem;">
                <div class="stats-subject-grid">
                    <div class="stat-badge glass-panel">
                        <span class="stat-value present" id="count-present">0</span>
                        <small class="stat-label-sm">Present</small>
                    </div>
                    <div class="stat-badge glass-panel">
                        <span class="stat-value late" id="count-late">0</span>
                        <small class="stat-label-sm">Late</small>
                    </div>
                    <div class="stat-badge glass-panel">
                        <span class="stat-value absent" id="count-absent">0</span>
                        <small class="stat-label-sm">Absent</small>
                    </div>
                    <div class="stat-badge glass-panel">
                        <span class="stat-value remaining" id="count-none">0</span>
                        <small class="stat-label-sm">Remaining</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Area -->
        <div class="search-container stagger-3" style="margin-bottom: 2.25rem;">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input-glass" placeholder="Search by student name..." autocomplete="off">
        </div>

        <!-- Context Header -->
        <div class="context-header stagger-4">
            <div>
                <p id="manualContextIndicator" style="font-size: 0.72rem; color: var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing:0.12em; margin:0 0 4px; line-height:1.5;">Daily Attendance</p>
                <h5 style="color: var(--text-main); font-weight: 900; margin: 0; font-size:1.45rem; letter-spacing:-0.03em;">Attendance Dossier</h5>
            </div>
            <button id="btnNotify" onclick="finishAndNotifySubject()" class="btn btn-primary" style="border-radius:14px; padding: 0.65rem 1.5rem; display: flex; align-items: center; gap: 8px; font-weight:800; box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 25%, transparent);">
                <i class="bi bi-send-check"></i> <span class="btn-text">Notify</span>
            </button>
        </div>

        <!-- Student List -->
        <div id="studentList" class="student-list">
            <?php 
            $idx = 0;
            foreach ($users as $user): 
                $staggerClass = 'stagger-' . (($idx % 8) + 1);
                $idx++;
            ?>
                <div class="student-row <?= $staggerClass ?> animated-item interactive-glow" id="row-<?= $user['qr_code'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>">
                    <div style="flex: 1; min-width: 0;">
                        <h5 class="student-name" onclick="window.location.href='profile.php?qr=<?= urlencode($user['qr_code']) ?>'" style="cursor:pointer; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family:'Outfit', sans-serif;"><?= htmlspecialchars($user['name']) ?></h5>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                            <span class="student-id"><?= $user['qr_code'] ?></span>
                            <div class="time-stamp" style="display: none;">
                                <i class="bi bi-clock"></i> <span class="time-val"></span>
                            </div>
                        </div>
                    </div>

                    <div class="compact-actions">
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'present')" class="btn-stat-entry p" title="Present">P</button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'late')" class="btn-stat-entry l" title="Late">L</button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'absent')" class="btn-stat-entry a" title="Absent">A</button>
                        <button onclick="clearStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>')" class="btn-stat-entry" title="Clear Record" style="color:var(--text-muted); font-size: 0.72rem;"><i class="bi bi-trash"></i></button>
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
                body: new URLSearchParams({ action: 'get_subjects_active' })
            })
            .then(r => r.json())
            .then(res => {
                const sel = document.getElementById('subjectSelect');
                sel.innerHTML = '<option value="">Select Subject...</option>';
                if (res.data) {
                    // Label shows active SY if available
                    const activeSY = res.active_sy ? ` (${res.active_sy})` : '';
                    for (const [sem, subs] of Object.entries(res.data)) {
                        let grp = document.createElement('optgroup');
                        grp.label = sem + activeSY;
                        subs.forEach(s => {
                            let opt = document.createElement('option');
                            opt.value = s.id;
                            opt.innerText = s.name;
                            if (s.id == currentSubjectId) opt.selected = true;
                            grp.appendChild(opt);
                        });
                        sel.appendChild(grp);
                    }
                    if (sel.options.length <= 1) {
                        let noOpt = document.createElement('option');
                        noOpt.disabled = true;
                        noOpt.innerText = '— No subjects for current school year —';
                        sel.appendChild(noOpt);
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

        function clearStatus(qr, name) {
            const date = document.getElementById('attendanceDate').value;
            const row = document.getElementById('row-' + qr);
            
            Swal.fire({
                title: 'Clear Record?',
                text: `Remove attendance for ${name}?`,
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
                        formData.append('type', 'subject_record_by_qr'); // I need to add this handler
                        formData.append('qr_code', qr);
                        formData.append('subject_id', currentSubjectId);
                        formData.append('date', date);
                    } else {
                        formData.append('qr_code', qr);
                        formData.append('date', date);
                        formData.append('action', 'delete_by_qr'); // I need to add this handler
                    }

                    fetch(api, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(d => {
                        row.style.opacity = '1';
                        if (d.status === 'success') {
                            row.classList.remove('present', 'late', 'absent', 'no-class');
                            const ts = row.querySelector('.time-stamp');
                            const sid = row.querySelector('.student-id');
                            if(ts) ts.style.display = 'none';
                            if(sid) sid.style.display = 'block';
                            
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
            window.location.href = 'subjects.php';
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
                    }).then(() => window.location.reload());
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
                            id: currentSubjectId 
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

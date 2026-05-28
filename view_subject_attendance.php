<?php
// view_subject_attendance.php - View Specific Subject History
date_default_timezone_set("Asia/Manila");
require_once "includes/db.php";

$subjectId = $_GET['id'] ?? 0;
if (!$subjectId) { header("Location: view_subjects_list.php"); exit; }

// Get Subject Details
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
$stmt->execute([$subjectId]);
$subject = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subject) die("Subject not found");

// Fetch Dates for this Subject
$stmt = $pdo->prepare("SELECT DISTINCT date FROM subject_attendance WHERE subject_id = ? ORDER BY date DESC");
$stmt->execute([$subjectId]);
$dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($subject['name']) ?> | Records</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <link rel="stylesheet" href="assets/css/AnimatedList.css">
    <script src="assets/js/AnimatedList.js"></script>
    <style>
        .page-header-card {
            background: var(--bg-card); 
            border: none; 
            border-radius: var(--radius-lg); 
            padding: 2.5rem; 
            margin-bottom: 3rem; 
            position: relative; 
            overflow: hidden;
            box-shadow: var(--shadow-neu-out);
        }
        .page-header-card::after {
            content: ''; position: absolute; left: 0; top: 0; width: 6px; height: 100%; background: var(--primary); opacity: 0.1;
        }

        .date-section { margin-bottom: 4rem; }
        .date-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 1.25rem; margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--bg-main);
        }
        .date-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.03em; margin: 0; color: var(--text-main); }
        
        /* Neumorphic Row Card */
        .attendance-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1.25rem 1.5rem; 
            border: none; 
            border-radius: var(--radius-md);
            margin-bottom: 1rem; 
            background: var(--bg-card); 
            transition: all 0.3s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: var(--shadow-neu-out-sm);
        }
        .attendance-row:hover { 
            box-shadow: var(--shadow-neu-in-sm);
            transform: scale(0.99); 
        }
        
        .student-link { text-decoration: none; color: var(--text-main); font-weight: 700; font-size: 1.05rem; transition: color 0.2s; }
        .student-link:hover { color: var(--primary); }
        
        .row-meta { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
        .row-time { font-family: monospace; font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
        
        .status-badge-btn {
            border: none; 
            background: var(--bg-card); 
            padding: 0.6rem 1.25rem; 
            border-radius: 50px; 
            font-size: 0.75rem; 
            font-weight: 800; 
            cursor: pointer;
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            transition: all 0.2s;
            min-width: 110px; 
            text-align: center;
            box-shadow: var(--shadow-neu-out-sm);
        }
        .status-badge-btn:hover {
            box-shadow: var(--shadow-neu-in-sm);
            transform: scale(0.95);
        }

        /* Status Coloring with subtle glow */
        .attendance-row.present { border-left: 4px solid #10b981; }
        .attendance-row.present .status-badge-btn { color: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.1), var(--shadow-neu-out-sm); }
        
        .attendance-row.late { border-left: 4px solid #f59e0b; }
        .attendance-row.late .status-badge-btn { color: #f59e0b; box-shadow: 0 0 10px rgba(245, 158, 11, 0.1), var(--shadow-neu-out-sm); }
        
        .attendance-row.absent { border-left: 4px solid #ef4444; }
        .attendance-row.absent .status-badge-btn { color: #ef4444; box-shadow: 0 0 10px rgba(239, 68, 68, 0.1), var(--shadow-neu-out-sm); }

        .attendance-row.no-class { border-left: 4px solid #64748b; }
        .attendance-row.no-class .status-badge-btn { color: #64748b; box-shadow: var(--shadow-neu-out-sm); }

        .btn-toolbar { display: flex; gap: 10px; }
        .btn-tool {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            box-shadow: var(--shadow-neu-out-sm);
            border: none;
            color: var(--text-muted);
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-tool:hover {
            box-shadow: var(--shadow-neu-in-sm);
            color: var(--primary);
        }
        .btn-tool.danger:hover { color: var(--danger); }

        /* Status States */
        .attendance-row.present { border-left-color: #10b981; }
        .attendance-row.present .status-badge-btn { background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); }
        
        .attendance-row.late { border-left-color: #f59e0b; }
        .attendance-row.late .status-badge-btn { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-color: rgba(245, 158, 11, 0.2); }
        
        .attendance-row.absent { border-left-color: #ef4444; }
        .attendance-row.absent .status-badge-btn { background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }

        .attendance-row.no-class { border-left-color: #64748b; }
        .attendance-row.no-class .status-badge-btn { background: rgba(100, 116, 139, 0.1); color: #64748b; border-color: rgba(100, 116, 139, 0.2); }

        .btn-toolbar { display: flex; gap: 8px; }
    </style>
</head>
<body>

    <?php 
    $navbar_actions = '
        <button onclick="exportSubjectRange()" class="btn btn-ghost" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: 1px solid var(--border); background: var(--bg-card);" title="Matrix Export">
            <i class="bi bi-file-earmark-spreadsheet" style="font-size: 0.95rem; color: var(--text-muted);"></i>
        </button>
        <button onclick="window.open(\'api/export_all_subjects.php?format=xls\', \'_blank\')" class="btn btn-ghost" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: 1px solid var(--border); background: var(--bg-card);" title="Bulk Export">
            <i class="bi bi-collection" style="font-size: 0.95rem; color: var(--text-muted);"></i>
        </button>
    ';
    include 'includes/navbar.php'; 
    ?>

    <main class="container animate-fade-up" style="max-width: 850px; padding-top: 2rem;">
        
        <div class="page-header-card animate-fade-up hover-lift">
            <h1 style="margin: 0; font-size: 2rem;"><?= htmlspecialchars($subject['name'] ?? 'Untitled Subject') ?></h1>
            <div class="mobile-force-stack" style="display: flex; gap: 15px; margin-top: 0.75rem; color: var(--text-muted); font-weight: 600; font-size: 0.9rem;">
                <span><i class="bi bi-mortarboard" style="margin-right: 4px;"></i> <?= htmlspecialchars($subject['school_year'] ?? '') ?></span>
                <span><i class="bi bi-calendar3" style="margin-right: 4px;"></i> <?= htmlspecialchars($subject['semester'] ?? '') ?> Semester</span>
                <span><i class="bi bi-journal-check" style="margin-right: 4px;"></i> <?= count($dates) ?> Class Days</span>
            </div>
        </div>

        <?php if (empty($dates)): ?>
            <div style="padding: 5rem 2rem; text-align: center; border: 2px dashed var(--border); border-radius: 20px;">
                <i class="bi bi-calendar-x" style="font-size: 3rem; color: var(--text-muted); opacity: 0.3;"></i>
                <h3 style="margin-top: 1rem; color: var(--text-muted);">No records found.</h3>
                <p style="color: var(--text-muted);">Attendance has not been recorded for this subject yet.</p>
                <a href="manual.php?subject_id=<?= $subjectId ?>" class="btn btn-primary" style="margin-top: 1.5rem;">Start Attendance</a>
            </div>
        <?php else: ?>
            <?php foreach ($dates as $index => $row): ?>
                <div class="date-section">
                    <div class="date-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <h3 class="date-title"><?= date('F j, Y', strtotime($row['date'])) ?></h3>
                            <span style="font-size: 0.75rem; color: var(--text-muted); weight: 700; background: var(--bg-main); padding: 2px 8px; border-radius: 4px;">
                                <?php
                                $count = $pdo->query("SELECT COUNT(*) FROM subject_attendance WHERE subject_id = $subjectId AND date = '{$row['date']}'")->fetchColumn();
                                echo $count . " Entries";
                                ?>
                            </span>
                        </div>
                        
                        <div class="btn-toolbar">
                             <?php
                             $nCheckSub = $pdo->prepare("SELECT 1 FROM notified_contexts WHERE subject_id = ? AND date = ?");
                             $nCheckSub->execute([$subjectId, $row['date']]);
                             $isNotified = (bool)$nCheckSub->fetch();
                             ?>
                             <button onclick="markAndNotifyAbsentees('<?= $row['date'] ?>')" class="btn-tool hover-press" style="width: auto; padding: 0 1rem; border-radius: 50px; font-size: 0.7rem; gap: 6px; <?= $isNotified ? 'opacity: 0.6;' : '' ?>" <?= $isNotified ? 'disabled' : '' ?>>
                                <i class="bi bi-<?= $isNotified ? 'check-all' : 'bell' ?>"></i> <?= $isNotified ? 'Notified' : 'Notify' ?>
                             </button>
                             <button onclick="exportSubjectDay('<?= $row['date'] ?>')" class="btn-tool hover-press" title="Export Day">
                                <i class="bi bi-download"></i>
                             </button>
                             <button onclick="deleteDay('<?= $row['date'] ?>')" class="btn-tool danger hover-press" title="Clear Day">
                                <i class="bi bi-trash"></i>
                             </button>
                        </div>
                    </div>

                    <div class="scroll-list-container">
                        <div class="top-gradient"></div>
                        <div class="bottom-gradient"></div>
                        <div class="scroll-list no-scrollbar" style="max-height: 400px; padding: 0.5rem 0;">
                            <div class="attendance-list">
<?php
                                $sql = "SELECT sa.id, sa.status, sa.time, sa.qr_code, u.name 
                                        FROM subject_attendance sa 
                                        JOIN users u ON sa.qr_code = u.qr_code 
                                        WHERE sa.subject_id = ? AND sa.date = ? 
                                        ORDER BY sa.id DESC";
                                $stmtRec = $pdo->prepare($sql);
                                $stmtRec->execute([$subjectId, $row['date']]);
                                $records = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($records as $r): 
                                    $timeStr = $r['time'] ? date('h:i A', strtotime($r['time'])) : '--:--';
                                ?>
                                <div class="attendance-row <?= strtolower($r['status']) ?> animated-item hover-lift" id="rec-<?= $r['id'] ?>">
                            <div>
                                <a href="profile.php?qr=<?= urlencode($r['qr_code']) ?>" class="student-link"><?= htmlspecialchars($r['name']) ?></a>
                                <div class="row-meta">
                                    <span class="student-id" style="font-size: 0.7rem; font-family: monospace; color: var(--text-muted); opacity: 0.6;"><?= $r['qr_code'] ?></span>
                                    <span class="row-time"><i class="bi bi-clock" style="margin-right: 4px;"></i><?= $timeStr ?></span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <button onclick="toggleStatus(<?= $r['id'] ?>, 'subject', this)" class="status-badge-btn hover-press">
                                    <?= str_replace('-', ' ', strtoupper($r['status'])) ?>
                                </button>
                                <button onclick="deleteRecord(<?= $r['id'] ?>)" class="hover-press" style="background: none; border: none; cursor: pointer; color: var(--text-muted); opacity: 0.3; padding: 4px;">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
<?php endforeach; ?>
        <?php endif; ?>
    </main>
    <?php include 'includes/footer.php'; ?>

    <script>
        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false,
            timer: 2000, timerProgressBar: true
        });

        function exportSubjectRange() {
            Swal.fire({
                title: 'Export Subject Matrix',
                html: `
                    <div style="text-align:left; padding: 0.5rem;">
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;">Select date range for aggregated matrix export.</p>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                            <div>
                                <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">Start Date</label>
                                <input type="date" id="swal-start" class="form-control" value="<?= date('Y-m-d') ?>" style="border-radius:12px; margin-top:4px;">
                            </div>
                            <div>
                                <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">End Date</label>
                                <input type="date" id="swal-end" class="form-control" value="<?= date('Y-m-d') ?>" style="border-radius:12px; margin-top:4px;">
                            </div>
                        </div>
                        
                        <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; display:block; margin-bottom:8px;">Export Format</label>
                        <div style="display:flex; gap:15px; flex-wrap:wrap;">
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="xls" checked style="margin-right:8px; accent-color:var(--primary);"> 
                                Excel (.xls)
                            </label>
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="csv" style="margin-right:8px; accent-color:var(--primary);"> 
                                Raw CSV
                            </label>
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="html" style="margin-right:8px; accent-color:var(--primary);"> 
                                Web View
                            </label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Download Matrix',
                confirmButtonColor: 'var(--primary)',
                preConfirm: () => {
                    return [
                        document.getElementById('swal-start').value,
                        document.getElementById('swal-end').value,
                        document.querySelector('input[name="swal-format"]:checked').value
                    ]
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const [start, end, format] = result.value;
                    window.open(`api/export_subject.php?subject_id=<?= $subjectId ?>&start=${start}&end=${end}&format=${format}`, '_blank');
                }
            })
        }

        function exportSubjectDay(date) {
             const url = `api/export_subject.php?subject_id=<?= $subjectId ?>&start=${date}&end=${date}&format=xls`;
             window.open(url, '_blank');
             Toast.fire({ icon: 'success', title: 'Exporting Day...' });
        }
        
        function deleteRecord(id) {
             Swal.fire({
                title: 'Remove Record?',
                text: "Delete this student's attendance entry?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('type', 'subject_record');
                    formData.append('id', id);
                    
                    fetch('api/delete_subject.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(d => {
                            if(d.status === 'success') {
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: 'Attendance record removed.',
                                    icon: 'success',
                                    confirmButtonColor: 'var(--primary)'
                                }).then(() => location.reload());
                            } else Toast.fire({ icon: 'error', title: d.message });
                        });
                }
            })
        }

        function deleteDay(date) {
            Swal.fire({
                title: 'Clear Day?',
                text: `Are you sure you want to delete all ${date} records for this subject?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, clear date'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('type', 'subject_day');
                    formData.append('subject_id', <?= $subjectId ?>);
                    formData.append('date', date);
                    
                    fetch('api/delete_subject.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(d => {
                            if(d.status === 'success') {
                                Swal.fire({
                                    title: 'Cleared!',
                                    text: 'All records for this day have been removed.',
                                    icon: 'success',
                                    confirmButtonColor: 'var(--primary)'
                                }).then(() => location.reload());
                            } else Toast.fire({ icon: 'error', title: d.message });
                        });
                }
            })
        }

        function toggleStatus(id, type, btnElement) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('type', type);
            
            const originalText = btnElement.innerText;
            btnElement.innerText = '...';
            
            fetch('api/update_attendance_status.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        btnElement.innerText = d.new_status.replace('-', ' ').toUpperCase();
                        const row = document.getElementById('rec-' + id);
                        row.classList.remove('present', 'late', 'absent', 'no-class');
                        row.classList.add(d.new_status);
                        Toast.fire({ icon: 'success', title: 'Status: ' + d.new_status.toUpperCase() });
                    } else {
                        btnElement.innerText = originalText;
                        Toast.fire({ icon: 'error', title: d.message });
                    }
                }).catch(err => {
                    btnElement.innerText = originalText;
                    Toast.fire({ icon: 'error', title: 'Network error' });
                });
        }

        function markAndNotifyAbsentees(date) {
            Swal.fire({
                title: 'Mark & Notify Absentees?',
                text: 'Find missing students, mark them absent, and notify Telegram?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Notify Group',
                confirmButtonColor: 'var(--primary)',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const formData = new FormData();
                    formData.append('subject_id', <?= $subjectId ?>);
                    formData.append('date', date);
                    return fetch('api/mark_absentees.php', { method: 'POST', body: formData })
                           .then(r => r.json())
                           .then(d => {
                               if (d.status !== 'success') throw new Error(d.message);
                               return d;
                           }).catch(error => {
                               Swal.showValidationMessage(`Request failed: ${error}`);
                           });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Notification Sent', result.value.message, 'success').then(() => {
                        location.reload();
                    });
                 }
            })
        }

        // Initialize Animated List
        document.addEventListener('DOMContentLoaded', () => {
            initAnimatedList('.scroll-list-container');
        });
    </script>
</body>
</html>

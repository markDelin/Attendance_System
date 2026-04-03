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
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .page-header-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; 
            padding: 2rem; margin-bottom: 2.5rem; position: relative; overflow: hidden;
        }
        .page-header-card::after {
            content: ''; position: absolute; left: 0; top: 0; width: 4px; height: 100%; background: var(--primary);
        }

        .date-section { margin-bottom: 3rem; }
        .date-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 1rem; border-bottom: 1px solid var(--border); margin-bottom: 1.5rem;
        }
        .date-title { font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em; margin: 0; color: var(--text-main); }
        
        /* High-Density Row */
        .attendance-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 1.5rem; border: 1px solid var(--border); border-radius: 12px;
            margin-bottom: 0.75rem; background: var(--bg-card); transition: all 0.2s;
            border-left: 4px solid transparent;
        }
        .attendance-row:hover { border-color: var(--primary); transform: translateX(4px); background: var(--bg-hover); }
        
        .student-link { text-decoration: none; color: var(--text-main); font-weight: 700; font-size: 1rem; }
        .student-link:hover { color: var(--primary); }
        
        .row-meta { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
        .row-time { font-family: monospace; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
        
        .status-badge-btn {
            border: 1px solid var(--border); background: var(--bg-main); padding: 6px 16px; 
            border-radius: 50px; font-size: 0.75rem; font-weight: 800; cursor: pointer;
            text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.2s;
            min-width: 100px; text-align: center;
        }

        /* Status States */
        .attendance-row.present { border-left-color: #10b981; }
        .attendance-row.present .status-badge-btn { background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); }
        
        .attendance-row.late { border-left-color: #f59e0b; }
        .attendance-row.late .status-badge-btn { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-color: rgba(245, 158, 11, 0.2); }
        
        .attendance-row.absent { border-left-color: #ef4444; }
        .attendance-row.absent .status-badge-btn { background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }

        .btn-toolbar { display: flex; gap: 8px; }
    </style>
</head>
<body>

    <?php 
    $navbar_actions = '
        <button onclick="exportSubjectRange()" class="btn btn-ghost btn-sm" style="border-radius: 50px;">
            <i class="bi bi-file-earmark-spreadsheet"></i> <span class="hide-mobile">Matrix Export</span>
        </button>
    ';
    include 'includes/navbar.php'; 
    ?>

    <main class="container animate-fade-up" style="max-width: 850px; padding-top: 2rem;">
        
        <div class="page-header-card">
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
                <div class="date-section animate-fade-up delay-<?= min($index + 1, 3) ?>">
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
                             <button onclick="markAndNotifyAbsentees('<?= $row['date'] ?>')" class="btn <?= $isNotified ? 'btn-ghost' : 'btn-primary' ?> btn-sm" style="border-radius: 50px; font-size: 0.75rem; <?= $isNotified ? 'opacity: 0.6;' : '' ?>" <?= $isNotified ? 'disabled' : '' ?>>
                                <i class="bi bi-<?= $isNotified ? 'check-all' : 'bell' ?>"></i> <?= $isNotified ? 'Notified' : 'Notify' ?>
                             </button>
                             <button onclick="exportSubjectDay('<?= $row['date'] ?>')" class="btn btn-ghost btn-sm" style="border-radius: 50px; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Export Day">
                                <i class="bi bi-download"></i>
                             </button>
                             <button onclick="deleteDay('<?= $row['date'] ?>')" class="btn btn-ghost btn-sm" style="border-radius: 50px; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; color: var(--danger);" title="Clear Day">
                                <i class="bi bi-trash"></i>
                             </button>
                        </div>
                    </div>

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
                        <div class="attendance-row <?= strtolower($r['status']) ?>" id="rec-<?= $r['id'] ?>">
                            <div>
                                <a href="profile.php?qr=<?= urlencode($r['qr_code']) ?>" class="student-link"><?= htmlspecialchars($r['name']) ?></a>
                                <div class="row-meta">
                                    <span class="student-id" style="font-size: 0.7rem; font-family: monospace; color: var(--text-muted); opacity: 0.6;"><?= $r['qr_code'] ?></span>
                                    <span class="row-time"><i class="bi bi-clock" style="margin-right: 4px;"></i><?= $timeStr ?></span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <button onclick="toggleStatus(<?= $r['id'] ?>, 'subject', this)" class="status-badge-btn">
                                    <?= ucfirst($r['status']) ?>
                                </button>
                                <button onclick="deleteRecord(<?= $r['id'] ?>)" style="background: none; border: none; cursor: pointer; color: var(--text-muted); opacity: 0.3; padding: 4px;">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

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
                        <div style="display:flex; gap:20px;">
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="xls" checked style="margin-right:8px; accent-color:var(--primary);"> 
                                Excel (.xls)
                            </label>
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="html" style="margin-right:8px; accent-color:var(--primary);"> 
                                Google Sheets
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
                                document.getElementById('rec-' + id).style.opacity = '0';
                                setTimeout(() => location.reload(), 300);
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
                            if(d.status === 'success') location.reload();
                            else Toast.fire({ icon: 'error', title: d.message });
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
                        btnElement.innerText = d.new_status.charAt(0).toUpperCase() + d.new_status.slice(1);
                        const row = document.getElementById('rec-' + id);
                        row.classList.remove('present', 'late', 'absent');
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
    </script>
</body>
</html>

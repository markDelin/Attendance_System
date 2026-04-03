<?php
// student_history.php - Individual Student Records
date_default_timezone_set('Asia/Manila');
require 'includes/db.php';

$qr_code = $_GET['qr_code'] ?? $_GET['qr'] ?? '';
if (empty($qr_code)) {
    header("Location: manage_students.php");
    exit;
}

// Fetch Student
$stmt = $pdo->prepare("SELECT * FROM users WHERE qr_code = ?");
$stmt->execute([$qr_code]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) die("Student not found.");

// Fetch All Available School Years for this Student
$sy_stmt = $pdo->prepare("SELECT DISTINCT school_year FROM attendance WHERE qr_code = ? AND school_year IS NOT NULL ORDER BY school_year DESC");
$sy_stmt->execute([$qr_code]);
$sy_list = $sy_stmt->fetchAll(PDO::FETCH_COLUMN);
$active_sy = $_GET['sy'] ?? $pdo->query("SELECT active_school_year FROM settings LIMIT 1")->fetchColumn();

// Fetch Attendance Logs (Filtered by SY)
$logs = $pdo->prepare("SELECT * FROM attendance WHERE qr_code = ? AND (school_year = ? OR school_year IS NULL) ORDER BY date DESC, time DESC");
$logs->execute([$qr_code, $active_sy]);
$attendance = $logs->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$total = count($attendance);
$present = 0; $late = 0; $absent = 0;

foreach ($attendance as $a) {
    if ($a['status'] == 'present') $present++;
    elseif ($a['status'] == 'late') $late++;
    elseif ($a['status'] == 'absent') $absent++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History: <?= htmlspecialchars($student['name']) ?> | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .stat-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;
        }
        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: left;
            position: relative; overflow: hidden;
            transition: all 0.3s var(--ease-out-expo);
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .stat-val { font-size: 2.5rem; font-weight: 800; letter-spacing: -0.05em; color: var(--text-main); line-height: 1; margin-bottom: 0.5rem; }
        .stat-lbl { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; }
        
        .card-header { padding: 1.5rem 2.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .card-header h4 { margin: 0; letter-spacing: -0.02em; font-weight: 800; }

        table { width: 100%; border-collapse: collapse; }
        th { padding: 1rem 2.5rem; background: #f8fafc; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; border-bottom: 1px solid var(--border); }
        td { padding: 1.5rem 2.5rem; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        tr:hover td { background: #fafafa; }
        
        @media (max-width: 600px) {
            th, td { padding: 1rem 1.5rem; }
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="padding-top: 3rem;">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <h1 style="margin:0; font-weight: 800; letter-spacing: -0.05em;"><?= htmlspecialchars($student['name']) ?></h1>
                <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500; font-family: monospace;"><?= $student['qr_code'] ?></p>
            </div>
            <div style="min-width: 200px;">
                <label style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; display: block;">Filter by School Year</label>
                <select onchange="window.location.href='?qr_code=<?= $qr_code ?>&sy='+this.value" class="form-control" style="font-weight: 800; border-radius: 12px; background: var(--bg-card); cursor: pointer;">
                    <?php if (empty($sy_list)): ?>
                        <option value="<?= htmlspecialchars($active_sy) ?>"><?= htmlspecialchars($active_sy) ?></option>
                    <?php else: ?>
                        <?php foreach ($sy_list as $sy): ?>
                            <option value="<?= htmlspecialchars($sy) ?>" <?= $active_sy == $sy ? 'selected' : '' ?>><?= htmlspecialchars($sy) ?></option>
                        <?php endforeach; ?>
                        <?php if (!in_array($active_sy, $sy_list)): ?>
                            <option value="<?= htmlspecialchars($active_sy) ?>" selected><?= htmlspecialchars($active_sy) ?></option>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stat-grid animate-fade-up">
            <div class="stat-card" style="border-bottom: 4px solid var(--primary);">
                <div class="stat-val"><?= $total ?></div>
                <div class="stat-lbl">Total Scans</div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid var(--success);">
                <div class="stat-val" style="color: var(--success);"><?= $present ?></div>
                <div class="stat-lbl">Present</div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid var(--warning);">
                <div class="stat-val" style="color: var(--warning);"><?= $late ?></div>
                <div class="stat-lbl">Late</div>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid var(--danger);">
                <div class="stat-val" style="color: var(--danger);"><?= $absent ?></div>
                <div class="stat-lbl">Absent</div>
            </div>
        </div>

        <!-- Records Table -->
        <div class="card animate-fade-up" style="border-radius: 20px;">
            <div class="card-header">
                <h4>General Attendance</h4>
                <span class="badge" style="background:#f1f5f9; color:var(--text-muted);">DAILY REVIEWS</span>
            </div>
            <div class="table-wrapper" style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance)): ?>
                            <tr><td colspan="3" style="padding: 4rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">No daily records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($attendance as $row): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-main);"><?= date('F j, Y', strtotime($row['date'])) ?></td>
                                <td style="color: var(--text-muted); font-family: monospace;"><?= date('h:i:s A', strtotime($row['time'])) ?></td>
                                <td><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Subject Attendance Records -->
        <div class="card animate-fade-up" style="margin-top: 3rem; margin-bottom: 4rem; border-radius: 20px;">
            <div class="card-header">
                <h4>Specific Class Logs</h4>
                <span class="badge" style="background:#f1f5f9; color:var(--text-muted);">SUBJECTS</span>
            </div>
            <?php
            $stmtSub = $pdo->prepare("
                SELECT sa.*, s.name as subject_name, s.id as subject_id 
                FROM subject_attendance sa 
                JOIN subjects s ON sa.subject_id = s.id 
                WHERE sa.qr_code = ? 
                ORDER BY sa.date DESC, sa.time DESC
            ");
            $stmtSub->execute([$qr_code]);
            $subAttendance = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="table-wrapper" style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Class / Subject</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subAttendance)): ?>
                            <tr><td colspan="4" style="padding: 4rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">No subject-specific records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($subAttendance as $row): ?>
                            <tr>
                                <td style="font-weight: 700;">
                                    <a href="view_subject_attendance.php?id=<?= $row['subject_id'] ?>" style="color: var(--primary);">
                                        <?= htmlspecialchars($row['subject_name']) ?> <i class="bi bi-arrow-right-short"></i>
                                    </a>
                                </td>
                                <td><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                <td style="color: var(--text-muted); font-family: monospace;"><?= date('h:i:s A', strtotime($row['time'])) ?></td>
                                <td><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>
</html>

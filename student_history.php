<?php
// student_history.php - Individual Student Records
date_default_timezone_set('Asia/Manila');
require 'includes/db.php';

$qr_code = $_GET['qr_code'] ?? '';
if (empty($qr_code)) {
    header("Location: manage_students.php");
    exit;
}

// Fetch Student
$stmt = $pdo->prepare("SELECT * FROM users WHERE qr_code = ?");
$stmt->execute([$qr_code]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) die("Student not found.");

// Fetch Attendance Logs
$logs = $pdo->prepare("SELECT * FROM attendance WHERE qr_code = ? ORDER BY date DESC, time DESC");
$logs->execute([$qr_code]);
$attendance = $logs->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$total = count($attendance);
$present = 0;
$late = 0;
$absent = 0;

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            text-align: center;
        }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .stat-label { color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="manage_students.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> Back to Students
        </a>
        <h3 class="text-gradient"><?= htmlspecialchars($student['name']) ?></h3>
        <div></div>
    </nav>

    <main class="container" style="padding-top: 2rem;">
        
        <!-- Stats Grid -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
            <div class="stat-box" style="border-bottom: 4px solid var(--primary);">
                <div class="stat-number"><?= $total ?></div>
                <div class="stat-label">Total Logs</div>
            </div>
            <div class="stat-box" style="border-bottom: 4px solid var(--success);">
                <div class="stat-number" style="color: var(--success);"><?= $present ?></div>
                <div class="stat-label">Present</div>
            </div>
            <div class="stat-box" style="border-bottom: 4px solid var(--warning);">
                <div class="stat-number" style="color: var(--warning);"><?= $late ?></div>
                <div class="stat-label">Late</div>
            </div>
            <div class="stat-box" style="border-bottom: 4px solid var(--danger);">
                <div class="stat-number" style="color: var(--danger);"><?= $absent ?></div>
                <div class="stat-label">Absent</div>
            </div>
        </div>

        <!-- Records Table -->
        <div class="card">
            <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
                <h4>Attendance Log</h4>
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 1rem; border-bottom: 1px solid var(--border);">Date</th>
                        <th style="padding: 1rem; border-bottom: 1px solid var(--border);">Time</th>
                        <th style="padding: 1rem; border-bottom: 1px solid var(--border);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance)): ?>
                        <tr><td colspan="3" style="padding: 2rem; text-align: center; color: var(--text-muted);">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendance as $row): ?>
                        <tr>
                            <td style="padding: 1rem; border-bottom: 1px solid var(--border); font-weight: 500;">
                                <?= date('F j, Y', strtotime($row['date'])) ?>
                            </td>
                            <td style="padding: 1rem; border-bottom: 1px solid var(--border); color: var(--text-muted);">
                                <?= date('h:i:s A', strtotime($row['time'])) ?>
                            </td>
                            <td style="padding: 1rem; border-bottom: 1px solid var(--border);">
                                <span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</body>
</html>

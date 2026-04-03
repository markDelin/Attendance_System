<?php
// api/export_subject.php - Matrix Export for Subjects
ob_start();
date_default_timezone_set("Asia/Manila");
require "../includes/db.php";
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$subjectId = $_GET['subject_id'] ?? 0;
$startDate = $_GET['start'] ?? date('Y-m-d');
$endDate = $_GET['end'] ?? $startDate;

if (!$subjectId) { die("Error: No Subject Selected"); }

try {
    // 1. Get Subject Info
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$subjectId]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subject) die("Subject not found");

    $filename = "Attendance_" . preg_replace('/[^a-zA-Z0-9]/', '_', $subject['name']) . "_" . $startDate . ".xls";

    // 2. Fetch All Students (Rows)
    $users = $pdo->query("SELECT qr_code, name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Dates (Columns) - distinct dates recorded for this subject in range
    $stmt = $pdo->prepare("SELECT DISTINCT date FROM subject_attendance WHERE subject_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
    $stmt->execute([$subjectId, $startDate, $endDate]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($dates)) $dates = [$startDate];

    // 4. Fetch Data Map
    $stmt = $pdo->prepare("SELECT qr_code, date, time, status, recorded_at FROM subject_attendance WHERE subject_id = ? AND date BETWEEN ? AND ?");
    $stmt->execute([$subjectId, $startDate, $endDate]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach($logs as $l) {
        // Prefer 'time' column, fallback to recorded_at
        $timeDisplay = $l['time'];
        if (!$timeDisplay && $l['recorded_at']) {
            $timeDisplay = date('h:i A', strtotime($l['recorded_at'])); 
        }
        if (!$timeDisplay) $timeDisplay = 'Present'; // Fallback if no time

        // Store full info
        $map[$l['qr_code']][$l['date']] = [
            'status' => $l['status'],
            'time' => $timeDisplay
        ];
    }

} catch (PDOException $e) { die($e->getMessage()); }

$isSingleDay = ($startDate == $endDate);
$headerTitle = $isSingleDay ? "DATE: " . date('F j, Y', strtotime($startDate)) : "Period: $startDate to $endDate";

// Output
while (ob_get_level()) ob_end_clean();
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #000; padding: 5px 8px; vertical-align: middle; text-align: center; }
        .left { text-align: left; }
        .header { background-color: #7c3aed; color: #fff; font-weight: bold; font-size: 14pt; height: 40px; }
        .subheader { background-color: #f3e8ff; color: #5b21b6; font-weight: bold; }
        .col-header { background-color: #e2e8f0; font-weight: bold; }
        .present { background-color: #dcfce7; color: #166534; }
        .late { background-color: #ffedd5; color: #9a3412; }
        .absent { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<table>
    <tr><td colspan="<?= count($dates) + 5 ?>" class="header">SUBJECT ATTENDANCE: <?= htmlspecialchars($subject['name']) ?></td></tr>
    <tr><td colspan="<?= count($dates) + 5 ?>" class="subheader">Semester: <?= htmlspecialchars($subject['semester']) ?> | <?= $headerTitle ?></td></tr>
    <tr><td colspan="<?= count($dates) + 5 ?>" style="border:none; height:10px;"></td></tr>

    <tr>
        <th class="col-header" width="40">#</th>
        <th class="col-header left" width="250">STUDENT NAME</th>
        <?php foreach ($dates as $d): ?>
            <th class="col-header" width="100">
                <?php if($isSingleDay): ?>
                    STATUS
                <?php else: ?>
                    <?= date('M j', strtotime($d)) ?><br><small><?= date('D', strtotime($d)) ?></small>
                <?php endif; ?>
            </th>
        <?php endforeach; ?>
        <?php if(!$isSingleDay): ?>
            <th class="col-header" width="80" style="background:#f0fdf4">PRESENT</th>
            <th class="col-header" width="80" style="background:#fff7ed">LATE</th>
            <th class="col-header" width="80" style="background:#fef2f2">ABSENT</th>
        <?php endif; ?>
    </tr>

    <?php foreach ($users as $i => $u): 
        $qr = $u['qr_code'];
        $p=0; $l=0; $a=0;
    ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td class="left"><strong><?= htmlspecialchars($u['name']) ?></strong></td>
        <?php foreach ($dates as $d): 
            $entry = $map[$qr][$d] ?? null;
            $st = $entry ? $entry['status'] : '-';
            
            $cls = ''; $txt = '-';
            
            if ($st !== '-') {
                if ($st == 'present') { $p++; $cls='present'; $txt='P'; }
                elseif ($st == 'late') { $l++; $cls='late'; $txt='L'; }
                elseif ($st == 'absent') { $a++; $cls='absent'; $txt='A'; }
            }
        ?>
            <td class="<?= $cls ?>"><?= $txt ?></td>
        <?php endforeach; ?>
        <?php if(!$isSingleDay): ?>
            <td style="background:#f0fdf4; font-weight:bold"><?= $p ?></td>
            <td style="background:#fff7ed; font-weight:bold"><?= $l ?></td>
            <td style="background:#fef2f2; font-weight:bold"><?= $a ?></td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>

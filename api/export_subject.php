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
$format = $_GET['format'] ?? 'xls';
$ext = ($format === 'html') ? 'html' : 'xls';

if (!$subjectId) { die("Error: No Subject Selected"); }

try {
    // 1. Get Subject Info
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$subjectId]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subject) die("Subject not found");

    $filename = "Attendance_" . preg_replace('/[^a-zA-Z0-9]/', '_', $subject['name']) . "_" . $startDate . "." . $ext;

    // 2. Fetch Students (Rows)
    // RESTORED: Regular (All) + Irregular (Enrolled)
    $q = "SELECT u.qr_code, u.name 
          FROM users u 
          LEFT JOIN student_subjects ss ON u.qr_code = ss.qr_code 
          WHERE (u.student_type IS NULL OR u.student_type = 'regular') 
             OR (u.student_type = 'irregular' AND ss.subject_id = ?)
          GROUP BY u.qr_code 
          ORDER BY u.name ASC";
    
    $stmtUser = $pdo->prepare($q);
    $stmtUser->execute([$subjectId]);
    $users = $stmtUser->fetchAll(PDO::FETCH_ASSOC);

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

if ($format === 'html') {
    header("Content-Type: text/html; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
} else {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 10pt; background: #fff; }
        
        /* Table Structure */
        table { border-collapse: collapse; table-layout: fixed; width: auto; margin-bottom: 30px; border: 1px solid #94a3b8; }
        
        /* Cells */
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: middle; text-align: center; height: 30px; }
        
        /* Headers */
        .header-title { background-color: #4338ca; color: #ffffff; font-weight: bold; font-size: 14pt; height: 45px; text-transform: uppercase; }
        .header-meta { background-color: #e0e7ff; color: #3730a3; font-weight: bold; height: 35px; }
        .col-header { background-color: #f1f5f9; color: #334155; font-weight: bold; font-size: 9pt; }
        
        /* Column Specifics */
        .num-col { width: 30px; font-size: 8pt; color: #64748b; background-color: #f8fafc; }
        .name-col { width: 350px; text-align: left; padding-left: 10px; font-weight: bold; color: #0f172a; white-space: nowrap; }
        .date-col { width: 100px; }
        .summary-col { width: 60px; font-weight: bold; }

        /* Status Colors */
        .status-header-p { background-color: #dcfce7; color: #166534; }
        .status-header-l { background-color: #ffedd5; color: #9a3412; }
        .status-header-a { background-color: #fee2e2; color: #991b1b; }

        .p-cell { background-color: #dcfce7; color: #166534; font-weight: bold; }
        .l-cell { background-color: #ffedd5; color: #9a3412; font-weight: bold; }
        .a-cell { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
        .empty-cell { color: #cbd5e1; }

        /* Footer/Stats */
        .stat-p { background-color: #d1fae5; color: #065f46; font-weight: bold; }
        .stat-l { background-color: #ffedd5; color: #9a3412; font-weight: bold; }
        .stat-a { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <colgroup>
            <col width="30" style="width:30px"> <!-- Number -->
            <col width="350" style="width:350px"> <!-- Name -->
            <?php foreach ($dates as $d): ?><col width="100" style="width:100px"><?php endforeach; ?>
            <?php if(!$isSingleDay): ?>
                <col width="60" style="width:60px">
                <col width="60" style="width:60px">
                <col width="60" style="width:60px">
            <?php endif; ?>
        </colgroup>

        <!-- Main Headers -->
        <tr>
            <td colspan="<?= count($dates) + ($isSingleDay ? 2 : 5) ?>" class="header-title">
                SUBJECT ATTENDANCE: <?= htmlspecialchars($subject['name']) ?>
            </td>
        </tr>
        <tr>
            <td colspan="<?= count($dates) + ($isSingleDay ? 2 : 5) ?>" class="header-meta">
                SEMESTER: <?= htmlspecialchars($subject['semester']) ?> &nbsp;|&nbsp; <?= $headerTitle ?>
            </td>
        </tr>
        <tr><td colspan="<?= count($dates) + ($isSingleDay ? 2 : 5) ?>" style="border:none; height:10px;"></td></tr>

        <!-- Column Headers -->
        <tr>
            <th class="col-header" style="width:30px">#</th>
            <th class="col-header" style="width:350px; text-align:left;">STUDENT NAME</th>
            
            <?php foreach ($dates as $d): ?>
                <th class="col-header" style="width:100px">
                    <?php if($isSingleDay): ?>
                        STATUS
                    <?php else: ?>
                        <?= date('M j', strtotime($d)) ?><br>
                        <span style="font-weight:normal; font-size:8pt;"><?= date('D', strtotime($d)) ?></span>
                    <?php endif; ?>
                </th>
            <?php endforeach; ?>

            <?php if(!$isSingleDay): ?>
                <th class="summary-col status-header-p">PRES</th>
                <th class="summary-col status-header-l">LATE</th>
                <th class="summary-col status-header-a">ABS</th>
            <?php endif; ?>
        </tr>

        <!-- Data Rows -->
        <?php foreach ($users as $i => $u): 
            $qr = $u['qr_code'];
            $p=0; $l=0; $a=0;
        ?>
        <tr>
            <td class="num-col"><?= $i + 1 ?></td>
            <td class="name-col"><?= htmlspecialchars($u['name']) ?></td>
            
            <?php foreach ($dates as $d): 
                $entry = $map[$qr][$d] ?? null;
                $st = $entry ? strtolower($entry['status']) : '-';
                
                $cls = 'empty-cell'; $txt = '-';
                
                if ($st !== '-') {
                    if ($st == 'present') { $p++; $cls='p-cell'; $txt='P'; }
                    elseif ($st == 'late') { $l++; $cls='l-cell'; $txt='L'; }
                    elseif ($st == 'absent') { $a++; $cls='a-cell'; $txt='A'; }
                }
            ?>
                <td class="<?= $cls ?>"><?= $txt ?></td>
            <?php endforeach; ?>

            <?php if(!$isSingleDay): ?>
                <td class="stat-p"><?= $p ?></td>
                <td class="stat-l"><?= $l ?></td>
                <td class="stat-a"><?= $a ?></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>

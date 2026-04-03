<?php
// api/export_all_subjects.php - Bulk Export for All Subjects (HTML Format)
ob_start();
date_default_timezone_set("Asia/Manila");
require "../includes/db.php";
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$startDate = $_GET['start'] ?? date('Y-m-d');
$endDate = $_GET['end'] ?? $startDate;
$filterScheduled = isset($_GET['filter_scheduled']) && $_GET['filter_scheduled'] == '1';
$format = $_GET['format'] ?? 'xls';
$ext = ($format === 'html') ? 'html' : 'xls';

$filename = "Attendance_ALL_" . $startDate . ($startDate != $endDate ? "_to_$endDate" : "") . "." . $ext;

// Output Headers for Excel (HTML)
if ($format === 'html') {
    header("Content-Type: text/html; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
} else {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: \'Segoe UI\', Tahoma, Arial, sans-serif; font-size: 10pt; background: #fff; }
        
        table { border-collapse: collapse; table-layout: fixed; width: auto; margin-bottom: 40px; border: 1px solid #94a3b8; page-break-inside: avoid; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: middle; text-align: center; height: 30px; }
        
        /* Headers */
        .sem-header { background-color: #2e1065; color: #fff; text-align: left; padding: 10px; font-weight:bold; font-size: 16pt; border: none; }
        .header-title { background-color: #4338ca; color: #ffffff; font-weight: bold; font-size: 14pt; height: 45px; text-transform: uppercase; }
        .header-meta { background-color: #e0e7ff; color: #3730a3; font-weight: bold; height: 35px; }
        .col-header { background-color: #f1f5f9; color: #334155; font-weight: bold; font-size: 9pt; }
        
        /* Columns */
        .num-col { width: 30px; font-size: 8pt; color: #64748b; background-color: #f8fafc; }
        .name-col { width: 350px; text-align: left; padding-left: 10px; font-weight: bold; color: #0f172a; white-space: nowrap; }
        .date-col { width: 100px; }
        .summary-col { width: 60px; font-weight: bold; }

        /* Status */
        .status-header-p { background-color: #dcfce7; color: #166534; }
        .status-header-l { background-color: #ffedd5; color: #9a3412; }
        .status-header-a { background-color: #fee2e2; color: #991b1b; }

        .p-cell { background-color: #dcfce7; color: #166534; font-weight: bold; }
        .l-cell { background-color: #ffedd5; color: #9a3412; font-weight: bold; }
        .a-cell { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
        .empty-cell { color: #cbd5e1; }

        /* Stats */
        .stat-p { background-color: #d1fae5; color: #065f46; font-weight: bold; }
        .stat-l { background-color: #ffedd5; color: #9a3412; font-weight: bold; }
        .stat-a { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
    </style>
</head>
<body>';

try {
    // 1. Get Subjects
    $stmt = $pdo->query("SELECT * FROM subjects ORDER BY semester DESC, name ASC");
    $allSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($filterScheduled) {
        $dayOfWeek = date('l', strtotime($startDate));
        $stmt = $pdo->prepare("SELECT DISTINCT subject_id FROM schedules WHERE day_of_week = ?");
        $stmt->execute([$dayOfWeek]);
        $scheduledIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $allSubjects = array_filter($allSubjects, function($s) use ($scheduledIds) {
            return in_array($s['id'], $scheduledIds);
        });
    }

    $grouped = [];
    foreach ($allSubjects as $s) {
        $grouped[$s['semester']][] = $s;
    }

    // $users = $pdo->query("SELECT qr_code, name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); // REMOVED GLOBAL FETCH

    foreach ($grouped as $semester => $subjects) {
        echo "<h2 style='font-family:Segoe UI, sans-serif; color:#2e1065; border-bottom:2px solid #2e1065; padding-bottom:10px; margin-top:30px;'>SEMESTER: " . htmlspecialchars($semester) . "</h2>";

        foreach ($subjects as $subject) {
            $subjectId = $subject['id'];

            // FETCH ELIGIBLE USERS: Regular (All) + Irregular (Enrolled)
            $stmtU = $pdo->prepare("
                SELECT u.qr_code, u.name 
                FROM users u 
                LEFT JOIN student_subjects ss ON u.qr_code = ss.qr_code 
                WHERE (u.student_type IS NULL OR u.student_type = 'regular') 
                   OR (u.student_type = 'irregular' AND ss.subject_id = ?)
                GROUP BY u.qr_code
                ORDER BY u.name ASC
            ");
            $stmtU->execute([$subjectId]);
            $users = $stmtU->fetchAll(PDO::FETCH_ASSOC);

            // Get Dates
            $stmt = $pdo->prepare("SELECT DISTINCT date FROM subject_attendance WHERE subject_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
            $stmt->execute([$subjectId, $startDate, $endDate]);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($dates)) $dates = []; 

            // Get Logs
            $stmt = $pdo->prepare("SELECT qr_code, date, time, status, recorded_at FROM subject_attendance WHERE subject_id = ? AND date BETWEEN ? AND ?");
            $stmt->execute([$subjectId, $startDate, $endDate]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = [];
            foreach($logs as $l) {
                // simple mapping
                $map[$l['qr_code']][$l['date']] = strtolower($l['status']);
            }

            // Render Table
            ?>
            <table>
                <colgroup>
                    <col width="30" style="width:30px">
                    <col width="350" style="width:350px">
                    <?php if (empty($dates)): ?>
                        <col width="200" style="width:200px">
                    <?php else: ?>
                        <?php foreach ($dates as $d): ?><col width="100" style="width:100px"><?php endforeach; ?>
                        <col width="60" style="width:60px">
                        <col width="60" style="width:60px">
                        <col width="60" style="width:60px">
                    <?php endif; ?>
                </colgroup>
                
                <tr><td colspan="<?= count($dates) + 5 ?>" class="header-title">SUBJECT: <?= htmlspecialchars($subject['name']) ?></td></tr>
                <tr><td colspan="<?= count($dates) + 5 ?>" class="header-meta">Period: <?= $startDate ?> to <?= $endDate ?></td></tr>
                <tr><td colspan="<?= count($dates) + 5 ?>" style="border:none; height:10px;"></td></tr>

                <tr>
                    <th class="col-header" style="width:30px">#</th>
                    <th class="col-header" style="width:350px; text-align:left;">STUDENT NAME</th>
                    
                    <?php if (empty($dates)): ?>
                        <th class="col-header">No Records Found</th>
                    <?php else: ?>
                        <?php foreach ($dates as $d): ?>
                            <th class="col-header" style="width:100px">
                                <?= date('M j', strtotime($d)) ?><br>
                                <span style="font-weight:normal; font-size:8pt;"><?= date('D', strtotime($d)) ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th class="summary-col status-header-p">PRES</th>
                        <th class="summary-col status-header-l">LATE</th>
                        <th class="summary-col status-header-a">ABS</th>
                    <?php endif; ?>
                </tr>

                <?php foreach ($users as $i => $u): 
                    $qr = $u['qr_code'];
                    $p=0; $l=0; $a=0;
                ?>
                <tr>
                    <td class="num-col"><?= $i + 1 ?></td>
                    <td class="name-col"><?= htmlspecialchars($u['name']) ?></td>
                    
                    <?php if (empty($dates)): ?>
                        <td class="empty-cell">-</td>
                    <?php else: ?>
                        <?php foreach ($dates as $d): 
                            $st = $map[$qr][$d] ?? '-';
                            $cls = 'empty-cell'; $txt = '-';
                            
                            if ($st !== '-') {
                                if ($st == 'present') { $p++; $cls='p-cell'; $txt='P'; }
                                elseif ($st == 'late') { $l++; $cls='l-cell'; $txt='L'; }
                                elseif ($st == 'absent') { $a++; $cls='a-cell'; $txt='A'; }
                            }
                        ?>
                            <td class="<?= $cls ?>"><?= $txt ?></td>
                        <?php endforeach; ?>
                        
                        <td class="stat-p"><?= $p ?></td>
                        <td class="stat-l"><?= $l ?></td>
                        <td class="stat-a"><?= $a ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </table>
            <br>
            <?php
        }
    }

} catch (PDOException $e) {
    echo "<b>Error:</b> " . $e->getMessage();
}

echo '</body></html>';
?>

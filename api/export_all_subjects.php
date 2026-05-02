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
        body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; font-size: 10pt; color: #334155; background: #fff; }
        .container { padding: 20px; }
        
        /* Sem Header */
        .sem-title { font-family: \'Segoe UI\', sans-serif; color: #1e293b; border-bottom: 3px solid #1e293b; padding-bottom: 10px; margin-top: 40px; margin-bottom: 20px; text-transform: uppercase; font-size: 18pt; }

        /* Main Table */
        table.matrix-table { border-collapse: collapse; table-layout: fixed; width: auto; margin-bottom: 50px; border: 1px solid #cbd5e1; page-break-inside: avoid; }
        table.matrix-table th, table.matrix-table td { border: 1px solid #cbd5e1; padding: 8px; vertical-align: middle; text-align: center; }
        
        .header-main { background-color: #4338ca; color: #ffffff; font-weight: bold; font-size: 14pt; height: 50px; text-transform: uppercase; text-align: left !important; padding-left: 20px !important; }
        .header-sub { background-color: #3730a3; color: #f1f5f9; font-weight: bold; height: 35px; text-align: left !important; padding-left: 20px !important; }
        .col-header { background-color: #f1f5f9; color: #475569; font-weight: 800; font-size: 8pt; text-transform: uppercase; }
        
        .num-col { width: 40px; color: #94a3b8; font-size: 8pt; background: #f8fafc; }
        .id-col { width: 100px; color: #64748b; font-family: monospace; font-size: 9pt; }
        .name-col { width: 300px; text-align: left !important; padding-left: 15px !important; font-weight: 700; color: #1e293b; }
        .meta-col { width: 100px; font-size: 9pt; color: #64748b; }
        .date-col { width: 80px; }
        .summary-col { width: 50px; font-weight: 800; }

        /* Cells */
        .p-cell { background-color: #dcfce7; color: #166534; font-weight: 800; }
        .l-cell { background-color: #fef3c7; color: #92400e; font-weight: 800; }
        .a-cell { background-color: #fee2e2; color: #991b1b; font-weight: 800; }
        .empty-cell { color: #cbd5e1; }
        
        .summary-p { background-color: #ecfdf5; color: #059669; }
        .summary-l { background-color: #fffbeb; color: #d97706; }
        .summary-a { background-color: #fef2f2; color: #dc2626; }
        .summary-rate { background-color: #f1f5f9; color: #1e293b; width: 70px; }

        /* Legend */
        .legend { margin-top: 10px; font-size: 8pt; color: #64748b; }
        .legend-item { display: inline-block; margin-right: 15px; }
        .legend-box { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 4px; vertical-align: middle; }
    </style>
</head>
<body>
<div class="container">';

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

    foreach ($grouped as $semester => $subjects) {
        echo "<div class='sem-title'>Semester: " . htmlspecialchars($semester) . "</div>";

        foreach ($subjects as $subject) {
            $subjectId = $subject['id'];

            // FETCH ELIGIBLE USERS
            $stmtU = $pdo->prepare("
                SELECT u.qr_code, u.name, u.course, u.section 
                FROM users u 
                LEFT JOIN student_subjects ss ON u.qr_code = ss.qr_code 
                WHERE (u.deleted_at IS NULL) AND (
                    (u.student_type IS NULL OR u.student_type = 'regular') 
                    OR (u.student_type = 'irregular' AND ss.subject_id = ?)
                )
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
            $stmt = $pdo->prepare("SELECT qr_code, date, status FROM subject_attendance WHERE subject_id = ? AND date BETWEEN ? AND ?");
            $stmt->execute([$subjectId, $startDate, $endDate]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = [];
            foreach($logs as $l) {
                $map[$l['qr_code']][$l['date']] = strtolower($l['status']);
            }

            // Render Table
            ?>
            <table class="matrix-table">
                <tr><td colspan="<?= count($dates) + 8 ?>" class="header-main">Subject: <?= htmlspecialchars($subject['name']) ?></td></tr>
                <tr><td colspan="<?= count($dates) + 8 ?>" class="header-sub">Period: <?= $startDate ?> to <?= $endDate ?></td></tr>

                <tr>
                    <th class="col-header num-col">#</th>
                    <th class="col-header id-col">ID</th>
                    <th class="col-header name-col">Student Name</th>
                    <th class="col-header meta-col">Section</th>
                    
                    <?php if (empty($dates)): ?>
                        <th class="col-header">No Records Found</th>
                    <?php else: ?>
                        <?php foreach ($dates as $d): ?>
                            <th class="col-header date-col"><?= date('M j', strtotime($d)) ?></th>
                        <?php endforeach; ?>
                        <th class="col-header summary-col summary-p">P</th>
                        <th class="col-header summary-col summary-l">L</th>
                        <th class="col-header summary-col summary-a">A</th>
                        <th class="col-header summary-rate">%</th>
                    <?php endif; ?>
                </tr>

                <?php foreach ($users as $i => $u): 
                    $qr = $u['qr_code'];
                    $p=0; $l=0; $a=0;
                ?>
                <tr>
                    <td class="num-col"><?= $i + 1 ?></td>
                    <td class="id-col"><?= $qr ?></td>
                    <td class="name-col"><?= htmlspecialchars($u['name']) ?></td>
                    <td class="meta-col"><?= htmlspecialchars($u['section'] ?? '-') ?></td>
                    
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
                        
                        <?php 
                            $totalRow = $p + $l + $a;
                            $rate = ($totalRow > 0) ? round((($p + $l) / $totalRow) * 100, 1) : 0;
                        ?>
                        <td class="summary-p"><?= $p ?></td>
                        <td class="summary-l"><?= $l ?></td>
                        <td class="summary-a"><?= $a ?></td>
                        <td class="summary-rate"><?= $rate ?>%</td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="<?= count($dates) + 8 ?>" style="border:none; text-align:left; padding:10px 0;">
                        <div class="legend">
                            <div class="legend-item"><span class="legend-box p-cell"></span> P=Present</div>
                            <div class="legend-item"><span class="legend-box l-cell"></span> L=Late</div>
                            <div class="legend-item"><span class="legend-box a-cell"></span> A=Absent</div>
                        </div>
                    </td>
                </tr>
            </table>
            <?php
        }
    }

} catch (PDOException $e) {
    echo "<b>Error:</b> " . $e->getMessage();
}

echo '</div></body></html>';
?>

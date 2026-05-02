<?php
// api/export.php - Refined Matrix Export
// 1. Setup
ob_start();
date_default_timezone_set("Asia/Manila");
require "../includes/db.php";
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    ob_clean();
    die("Error: Invalid request.");
}

$startDate = $_POST["start_date"] ?? $_POST["export_date"] ?? date('Y-m-d');
$endDate = $_POST["end_date"] ?? $startDate;
$format = $_POST["format"] ?? $_GET["format"] ?? 'xls';
if ($format === 'html') $ext = 'html';
elseif ($format === 'csv') $ext = 'csv';
else $ext = 'xls';

// Filename
if ($startDate === $endDate) {
    $dateLabel = date("F j, Y", strtotime($startDate));
    $filename = "Attendance_Matrix_" . $startDate . "." . $ext;
} else {
    $dateLabel = date("M j", strtotime($startDate)) . " - " . date("M j, Y", strtotime($endDate));
    $filename = "Attendance_Matrix_" . $startDate . "_to_" . $endDate . "." . $ext;
}

try {
    // 2. Fetch All Students (Values for rows)
    $stmt = $pdo->query("SELECT qr_code, name, course, section FROM users WHERE deleted_at IS NULL ORDER BY name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch All Dates in Range (Values for columns)
    $stmt = $pdo->prepare("SELECT DISTINCT date FROM attendance WHERE date BETWEEN ? AND ? ORDER BY date ASC");
    $stmt->execute([$startDate, $endDate]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dates)) {
        $dates = [$startDate];
    }

    // 4. Fetch Attendance Map
    $stmt = $pdo->prepare("SELECT qr_code, date, status, time FROM attendance WHERE date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build Look-up Map: $map[qr][date] = {status, time}
    $map = [];
    $totalPresent = 0; $totalLate = 0; $totalAbsent = 0;
    foreach($logs as $l) {
        $map[$l['qr_code']][$l['date']] = [
            'status' => $l['status'],
            'time' => $l['time']
        ];
        $st = strtolower($l['status']);
        if ($st == 'present') $totalPresent++;
        elseif ($st == 'late') $totalLate++;
        elseif ($st == 'absent') $totalAbsent++;
    }

    $totalRecords = count($logs);
    $attendanceRate = ($totalRecords > 0) ? round((($totalPresent + $totalLate) / $totalRecords) * 100, 1) : 0;

} catch (PDOException $e) {
    ob_clean(); die("DB Error: " . $e->getMessage());
}

// 5. Output Logic
while (ob_get_level()) ob_end_clean();

if ($format === 'csv') {
    // CSV Output
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    
    $headers = ['#', 'Student ID', 'Student Name', 'Section'];
    foreach ($dates as $d) {
        $headers[] = date('M j', strtotime($d));
    }
    $headers[] = 'Present';
    $headers[] = 'Late';
    $headers[] = 'Absent';
    $headers[] = 'Rate (%)';
    
    fputcsv($fp, $headers);
    
    foreach ($users as $i => $u) {
        $qr = $u['qr_code'];
        $row = [$i + 1, $qr, $u['name'], $u['section']];
        
        $p = 0; $l = 0; $a = 0;
        foreach ($dates as $d) {
            $cell = $map[$qr][$d] ?? null;
            if ($cell) {
                $st = strtolower($cell['status']);
                if ($st == 'present') { $p++; $row[] = 'P'; }
                elseif ($st == 'late') { $l++; $row[] = 'L'; }
                elseif ($st == 'absent') { $a++; $row[] = 'A'; }
                else { $row[] = '-'; }
            } else { $row[] = '-'; }
        }
        
        $totalRow = $p + $l + $a;
        $rate = ($totalRow > 0) ? round((($p + $l) / $totalRow) * 100, 1) : 0;
        
        $row[] = $p;
        $row[] = $l;
        $row[] = $a;
        $row[] = $rate . '%';
        fputcsv($fp, $row);
    }
    fclose($fp);
    exit;
} elseif ($format === 'html') {
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 10pt; color: #334155; }
        .container { padding: 20px; }
        
        /* Summary Cards */
        .stats-grid { width: 100%; border-collapse: collapse; margin-bottom: 30px; border: none; }
        .stats-grid td { padding: 0 10px 0 0; border: none; }
        .stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; min-width: 150px; }
        .stat-label { font-size: 8pt; text-transform: uppercase; color: #64748b; font-weight: 800; margin-bottom: 5px; }
        .stat-value { font-size: 16pt; font-weight: 800; color: #1e293b; }
        .stat-primary { border-left: 4px solid #4f46e5; }
        .stat-success { border-left: 4px solid #10b981; }
        .stat-warning { border-left: 4px solid #f59e0b; }
        .stat-danger { border-left: 4px solid #ef4444; }

        /* Main Table */
        table.matrix-table { border-collapse: collapse; table-layout: fixed; width: auto; margin-bottom: 30px; border: 1px solid #cbd5e1; }
        table.matrix-table th, table.matrix-table td { border: 1px solid #cbd5e1; padding: 8px; vertical-align: middle; text-align: center; }
        
        .header-main { background-color: #1e293b; color: #ffffff; font-weight: bold; font-size: 14pt; height: 50px; text-transform: uppercase; text-align: left !important; padding-left: 20px !important; }
        .header-sub { background-color: #334155; color: #f1f5f9; font-weight: bold; height: 35px; text-align: left !important; padding-left: 20px !important; }
        .col-header { background-color: #f1f5f9; color: #475569; font-weight: 800; font-size: 8pt; text-transform: uppercase; }
        
        .num-col { width: 40px; color: #94a3b8; font-size: 8pt; background: #f8fafc; }
        .id-col { width: 100px; color: #64748b; font-family: monospace; font-size: 9pt; }
        .name-col { width: 300px; text-align: left !important; padding-left: 15px !important; font-weight: 700; color: #1e293b; }
        .section-col { width: 80px; font-size: 9pt; color: #64748b; }
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
        .legend { margin-top: 20px; font-size: 9pt; color: #64748b; }
        .legend-item { display: inline-block; margin-right: 20px; }
        .legend-box { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 5px; vertical-align: middle; }
    </style>
</head>
<body>
<div class="container">
    <table class="stats-grid">
        <tr>
            <td><div class="stat-card stat-primary"><div class="stat-label">Total Students</div><div class="stat-value"><?= count($users) ?></div></div></td>
            <td><div class="stat-card stat-success"><div class="stat-label">Present</div><div class="stat-value"><?= $totalPresent ?></div></div></td>
            <td><div class="stat-card stat-warning"><div class="stat-label">Late</div><div class="stat-value"><?= $totalLate ?></div></div></td>
            <td><div class="stat-card stat-danger"><div class="stat-label">Absent</div><div class="stat-value"><?= $totalAbsent ?></div></div></td>
            <td><div class="stat-card"><div class="stat-label">Attendance Rate</div><div class="stat-value"><?= $attendanceRate ?>%</div></div></td>
        </tr>
    </table>

    <table class="matrix-table">
        <tr><td colspan="<?= count($dates) + 8 ?>" class="header-main">Attendance Matrix Report</td></tr>
        <tr><td colspan="<?= count($dates) + 8 ?>" class="header-sub">Period: <?= $dateLabel ?></td></tr>
        
        <tr>
            <th class="col-header num-col">#</th>
            <th class="col-header id-col">Student ID</th>
            <th class="col-header name-col">Full Name</th>
            <th class="col-header section-col">Section</th>
            <?php foreach ($dates as $d): ?>
                <th class="col-header date-col"><?= date('M j', strtotime($d)) ?><br><span style="font-weight:normal; font-size:7pt;"><?= date('D', strtotime($d)) ?></span></th>
            <?php endforeach; ?>
            <th class="col-header summary-col summary-p">P</th>
            <th class="col-header summary-col summary-l">L</th>
            <th class="col-header summary-col summary-a">A</th>
            <th class="col-header summary-rate">%</th>
        </tr>

        <?php foreach ($users as $i => $u): 
            $qr = $u['qr_code'];
            $p = 0; $l = 0; $a = 0; 
        ?>
        <tr>
            <td class="num-col"><?= $i + 1 ?></td>
            <td class="id-col"><?= $qr ?></td>
            <td class="name-col"><?= htmlspecialchars($u['name']) ?></td>
            <td class="section-col"><?= htmlspecialchars($u['section'] ?? '-') ?></td>

            <?php foreach ($dates as $d): 
                $cell = $map[$qr][$d] ?? null;
                $txt = '-'; $cls = 'empty-cell';
                if ($cell) {
                    $st = strtolower($cell['status']);
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
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="legend">
        <strong>Legend:</strong>
        <div class="legend-item"><span class="legend-box p-cell"></span> P = Present</div>
        <div class="legend-item"><span class="legend-box l-cell"></span> L = Late</div>
        <div class="legend-item"><span class="legend-box a-cell"></span> A = Absent</div>
        <div class="legend-item"><span class="legend-box empty-cell" style="background:#cbd5e1"></span> - = No Record</div>
    </div>
</div>
</body>
</html>
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

// Filename
if ($startDate === $endDate) {
    $dateLabel = date("F j, Y", strtotime($startDate));
    $filename = "Attendance_Matrix_" . $startDate . ".xls";
} else {
    $dateLabel = date("M j", strtotime($startDate)) . " - " . date("M j, Y", strtotime($endDate));
    $filename = "Attendance_Matrix_" . $startDate . "_to_" . $endDate . ".xls";
}

try {
    // 2. Fetch All Students (Values for rows)
    $stmt = $pdo->query("SELECT qr_code, name FROM users ORDER BY name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch All Dates in Range (Values for columns)
    // We fetch distinct dates from attendance to know which columns to show, 
    // OR we could generate every day in range. 
    // Let's only show days with activity to save space, unless distinct dates is empty.
    $stmt = $pdo->prepare("SELECT DISTINCT date FROM attendance WHERE date BETWEEN ? AND ? ORDER BY date ASC");
    $stmt->execute([$startDate, $endDate]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dates)) {
        // If no attendance, at least show the start date column
        $dates = [$startDate];
    }

    // 4. Fetch Attendance Map
    $stmt = $pdo->prepare("SELECT qr_code, date, status, time FROM attendance WHERE date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build Look-up Map: $map[qr][date] = {status, time}
    $map = [];
    foreach($logs as $l) {
        $map[$l['qr_code']][$l['date']] = [
            'status' => $l['status'],
            'time' => $l['time']
        ];
    }

} catch (PDOException $e) {
    ob_clean(); die("DB Error: " . $e->getMessage());
}

// 5. Output Excel HTML
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
        
        .header { background-color: #2563eb; color: #fff; font-weight: bold; font-size: 14pt; height: 40px; }
        .subheader { background-color: #eff6ff; color: #1e3a8a; font-weight: bold; }
        .col-header { background-color: #e2e8f0; font-weight: bold; }
        
        .present { background-color: #dcfce7; color: #166534; }
        .late { background-color: #ffedd5; color: #9a3412; }
        .absent { background-color: #fee2e2; color: #991b1b; }
        .empty { color: #ccc; }
    </style>
</head>
<body>

<table>
    <!-- Title -->
    <tr><td colspan="<?= count($dates) + 5 ?>" class="header">ATTENDANCE REPORT (MATRIX)</td></tr>
    <tr><td colspan="<?= count($dates) + 5 ?>" class="subheader">Period: <?= $dateLabel ?></td></tr>
    <tr><td colspan="<?= count($dates) + 5 ?>" style="border:none; height:10px;"></td></tr>

    <!-- Table Header -->
    <tr>
        <th class="col-header" style="width: 40px;">#</th>
        <th class="col-header left" style="width: 200px;">STUDENT NAME</th>
        
        <!-- Date Columns -->
        <?php foreach ($dates as $d): ?>
            <th class="col-header" style="width: 100px;">
                <?= date('M j', strtotime($d)) ?><br>
                <span style="font-size:8pt; font-weight:normal;"><?= date('D', strtotime($d)) ?></span>
            </th>
        <?php endforeach; ?>

        <!-- Summary Columns -->
        <th class="col-header" style="width: 60px; background: #f0fdf4;">P</th>
        <th class="col-header" style="width: 60px; background: #fff7ed;">L</th>
        <th class="col-header" style="width: 60px; background: #fef2f2;">A</th>
    </tr>

    <!-- Rows -->
    <?php foreach ($users as $i => $u): 
        $qr = $u['qr_code'];
        $p = 0; $l = 0; $a = 0; 
    ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td class="left"><strong><?= htmlspecialchars($u['name']) ?></strong></td>

        <?php foreach ($dates as $d): 
            $cell = $map[$qr][$d] ?? null;
            $display = '';
            $class = '';

            if ($cell) {
                $st = strtolower($cell['status']);
                if ($st == 'present') { $p++; $class='present'; $display='P'; }
                elseif ($st == 'late') { $l++; $class='late'; $display='L'; }
                elseif ($st == 'absent') { $a++; $class='absent'; $display='A'; }
                
                // Optional: Show time? Too cramped for matrix usually. 
                // Maybe tooltip style if excel supported it, but it doesn't.
                // Could do "P (8:00)"
                if ($display && $st !== 'absent') {
                   $display .= " <br><span style='font-size:8pt'>" . date('H:i', strtotime($cell['time'])) . "</span>";
                }
            } else {
                // No record? Usually means absent if it was a school day, but we don't know schedule.
                // Leave empty or mark '-'
                $display = '-';
                $class = 'empty';
            }
        ?>
            <td class="<?= $class ?>"><?= $display ?></td>
        <?php endforeach; ?>

        <!-- Stats -->
        <td style="background: #f0fdf4; font-weight:bold;"><?= $p ?></td>
        <td style="background: #fff7ed; font-weight:bold;"><?= $l ?></td>
        <td style="background: #fef2f2; font-weight:bold;"><?= $a ?></td>
    </tr>
    <?php endforeach; ?>

</table>

</body>
</html>
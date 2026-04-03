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

// 5. Output Logic
while (ob_get_level()) ob_end_clean();

if ($format === 'csv') {
    // CSV Output
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    $fp = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // 1. Header Row
    $headers = ['#', 'Student Name'];
    foreach ($dates as $d) {
        $headers[] = date('M j (D)', strtotime($d));
    }
    // Add Summary Columns
    $headers[] = 'Present';
    $headers[] = 'Late';
    $headers[] = 'Absent';
    
    fputcsv($fp, $headers);
    
    // 2. Data Rows
    foreach ($users as $i => $u) {
        $qr = $u['qr_code'];
        $row = [
            $i + 1,
            $u['name']
        ];
        
        $p = 0; $l = 0; $a = 0;
        
        foreach ($dates as $d) {
            $cell = $map[$qr][$d] ?? null;
            if ($cell) {
                $st = strtolower($cell['status']);
                if ($st == 'present') { $p++; $row[] = 'P'; }
                elseif ($st == 'late') { $l++; $row[] = 'L'; }
                elseif ($st == 'absent') { $a++; $row[] = 'A'; }
                else { $row[] = '-'; }
            } else {
                $row[] = '-';
            }
        }
        
        $row[] = $p;
        $row[] = $l;
        $row[] = $a;
        
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
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 10pt; background: #fff; }
        
        table { border-collapse: collapse; table-layout: fixed; width: auto; margin-bottom: 30px; border: 1px solid #94a3b8; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: middle; text-align: center; height: 30px; }
        
        /* Headers */
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
<body>

<table>
    <colgroup>
        <col width="30" style="width:30px">
        <col width="350" style="width:350px">
        <?php foreach ($dates as $d): ?><col width="100" style="width:100px"><?php endforeach; ?>
        <col width="60" style="width:60px">
        <col width="60" style="width:60px">
        <col width="60" style="width:60px">
    </colgroup>

    <tr><td colspan="<?= count($dates) + 5 ?>" class="header-title">ATTENDANCE REPORT (MATRIX)</td></tr>
    <tr><td colspan="<?= count($dates) + 5 ?>" class="header-meta">Period: <?= $dateLabel ?></td></tr>
    <tr><td colspan="<?= count($dates) + 5 ?>" style="border:none; height:10px;"></td></tr>

    <tr>
        <th class="col-header" style="width: 30px;">#</th>
        <th class="col-header" style="width: 350px; text-align:left;">STUDENT NAME</th>
        
        <?php foreach ($dates as $d): ?>
            <th class="col-header" style="width: 100px;">
                <?= date('M j', strtotime($d)) ?><br>
                <span style="font-size:8pt; font-weight:normal;"><?= date('D', strtotime($d)) ?></span>
            </th>
        <?php endforeach; ?>

        <th class="summary-col status-header-p">PRES</th>
        <th class="summary-col status-header-l">LATE</th>
        <th class="summary-col status-header-a">ABS</th>
    </tr>

    <?php foreach ($users as $i => $u): 
        $qr = $u['qr_code'];
        $p = 0; $l = 0; $a = 0; 
    ?>
    <tr>
        <td class="num-col"><?= $i + 1 ?></td>
        <td class="name-col"><?= htmlspecialchars($u['name']) ?></td>

        <?php foreach ($dates as $d): 
            $cell = $map[$qr][$d] ?? null;
            $txt = '-';
            $cls = 'empty-cell';

            if ($cell) {
                $st = strtolower($cell['status']);
                if ($st == 'present') { $p++; $cls='p-cell'; $txt='P'; }
                elseif ($st == 'late') { $l++; $cls='l-cell'; $txt='L'; }
                elseif ($st == 'absent') { $a++; $cls='a-cell'; $txt='A'; }
                
                // Optional: Show time if not absent
                if ($txt !== '-' && $st !== 'absent' && $cell['time']) {
                   // $txt .= " <br><span style='font-size:8pt'>" . date('H:i', strtotime($cell['time'])) . "</span>";
                   // Keep it clean for better sizing, just showing status code is cleaner for Matrix.
                   // Or we can add it if user wants, but "better sizing" implies clean. 
                   // Let's stick to P/L/A.
                }
            }
        ?>
            <td class="<?= $cls ?>"><?= $txt ?></td>
        <?php endforeach; ?>

        <td class="stat-p"><?= $p ?></td>
        <td class="stat-l"><?= $l ?></td>
        <td class="stat-a"><?= $a ?></td>
    </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
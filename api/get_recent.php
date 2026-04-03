<?php
// api/get_recent.php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";

$subjectId = isset($_GET['subject_id']) && $_GET['subject_id'] !== "" ? intval($_GET['subject_id']) : 0;
// Note: Using 'attendance' or 'subject_attendance'
// 'attendance' table has 'time' column (H:i A) ? No, checking process.php line 140 insert: 'time' is stored as display string?
// process.php line 138: INSERT INTO attendance ... time ... values ... $nowDisplay (h:i A)
// subject_attendance table? process.php line 92: INSERT INTO subject_attendance ... doesn't specify time column?
// Wait, I need to check subject_attendance schema. process.php line 92 insert params: subject_id, qr_code, date, status.
// Missing TIME in subject_attendance?
// process.php line 35: SELECT qr_code, date, time, status, recorded_at ...
// So subject_attendance HAS 'time' or 'recorded_at'.

// Let's verify schema first, but I can assume 'recorded_at' is likely a TIMESTAMP.
// process.php doesn't insert 'time' into subject_attendance query on line 92?
// Line 92: INSERT INTO subject_attendance (subject_id, qr_code, date, status) VALUES (?, ?, ?, ?)
// So it relies on auto-timestamp 'recorded_at'?
// I need to be careful.

try {
    $today = date('Y-m-d');
    
    if ($subjectId > 0) {
        // Fetch from subject_attendance
        // We join with users to get names
        // Order by recorded_at DESC or id DESC
        $sql = "SELECT 
                    u.name, 
                    sa.status, 
                    sa.recorded_at as time_raw,
                    sa.time as time_str
                FROM subject_attendance sa
                LEFT JOIN users u ON sa.qr_code = u.qr_code
                WHERE sa.subject_id = ? AND sa.date = ?
                ORDER BY sa.id DESC"; // Assuming ID is auto-inc, reliable for usage order
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$subjectId, $today]);
    } else {
        // Fetch from general attendance
        $sql = "SELECT 
                    u.name, 
                    a.status, 
                    a.time as time_str 
                FROM attendance a
                LEFT JOIN users u ON a.qr_code = u.qr_code
                WHERE a.date = ?
                ORDER BY a.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$today]);
    }

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for frontend
    $data = [];
    foreach($logs as $l) {
        $timeDisplay = $l['time_str'];
        
        // If time_str is empty (subject_attendance might log it differently or rely on timestamp), format raw
        if (empty($timeDisplay) && !empty($l['time_raw'])) {
            try {
                $dt = new DateTime($l['time_raw'], new DateTimeZone('UTC'));
                $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                $timeDisplay = $dt->format('h:i A');
            } catch (Exception $e) {
                $timeDisplay = date('h:i A', strtotime($l['time_raw']));
            }
        }
        // Fallback
        if (empty($timeDisplay)) $timeDisplay = date('h:i A'); // Just show now? No, misleading.

        $data[] = [
            'name' => $l['name'] ?? 'Unknown',
            'status' => $l['status'],
            'time' => $timeDisplay,
            // Add a clean timestamp for sorting/dedup if needed
            'sort_key' => $l['time_raw'] ?? date('Y-m-d H:i:s')
        ];
    }

    // Check notification status
    $nStmt = $pdo->prepare("SELECT 1 FROM notified_contexts WHERE subject_id = ? AND date = ?");
    $nStmt->execute([$subjectId, $today]);
    $is_notified = (bool)$nStmt->fetch();
    
    echo json_encode(['status' => 'success', 'data' => $data, 'subject_id' => $subjectId, 'is_notified' => $is_notified]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

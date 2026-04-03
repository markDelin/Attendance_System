<?php
// api/get_daily_status.php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";

$today = $_GET['date'] ?? date('Y-m-d');

try {
    // Determine query based on mode (Daily vs Subject) - though for now let's just do Daily
    // If we want Subject, we can pass a param.
    // Let's support both via ?subject_id=...
    
    $subjectId = isset($_GET['subject_id']) && $_GET['subject_id'] !== "" ? intval($_GET['subject_id']) : 0;
    
    $data = [];

    if ($subjectId > 0) {
        $stmt = $pdo->prepare("SELECT qr_code, status, time FROM subject_attendance WHERE subject_id = ? AND date = ?");
        $stmt->execute([$subjectId, $today]);
    } else {
        $stmt = $pdo->prepare("SELECT qr_code, status, time FROM attendance WHERE date = ?");
        $stmt->execute([$today]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        // Format time
        $timeStr = '';
        if (isset($r['time'])) {
             // Both tables now have 'time' column with correct local time
             // Daily: 'h:i A' (e.g. 04:30 PM)
             // Subject: 'H:i:s' (e.g. 16:30:00)
             // We want consistent display.
             $timeStr = date('h:i A', strtotime($r['time']));
        }

        $data[$r['qr_code']] = [
            'status' => $r['status'],
            'time' => $timeStr
        ];
    }

    // Check notification status
    $nStmt = $pdo->prepare("SELECT 1 FROM notified_contexts WHERE subject_id = ? AND date = ?");
    $nStmt->execute([$subjectId, $today]);
    $is_notified = (bool)$nStmt->fetch();
    
    echo json_encode(['status' => 'success', 'data' => $data, 'is_notified' => $is_notified]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

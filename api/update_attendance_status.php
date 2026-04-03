<?php
// api/update_attendance_status.php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/telegram.php';
date_default_timezone_set("Asia/Manila");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$id = $_POST['id'] ?? 0;
$type = $_POST['type'] ?? ''; // 'global' or 'subject'

if (!$id || !in_array($type, ['global', 'subject'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

try {
    $table = ($type === 'global') ? 'attendance' : 'subject_attendance';
    
    // Get current record
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        echo json_encode(['status' => 'error', 'message' => 'Record not found']);
        exit;
    }
    
    // Cycle status: present -> late -> absent -> present
    $newStatus = 'present';
    if ($record['status'] === 'present') {
        $newStatus = 'late';
    } elseif ($record['status'] === 'late') {
        $newStatus = 'absent';
    }
    
    // Update DB
    $stmt = $pdo->prepare("UPDATE $table SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    // Fetch Student Details for Notification
    $stmtUser = $pdo->prepare("SELECT name FROM users WHERE qr_code = ?");
    $stmtUser->execute([$record['qr_code']]);
    $studentName = $stmtUser->fetchColumn() ?: 'Unknown Student';
    
    // Prepare Notification Message
    $dateStr = date('F j, Y', strtotime($record['date']));
    $notifContext = 'Daily Attendance';
    
    if ($type === 'subject') {
        // Fetch Subject Name
        $stmtSub = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
        $stmtSub->execute([$record['subject_id']]);
        $subName = $stmtSub->fetchColumn() ?: 'Unknown Subject';
        $notifContext = "Subject: <b>$subName</b>";
    } else {
        $session = ucfirst($record['session']);
        $notifContext = "Daily Attendance ($session)";
    }
    
    $statusEmoji = '✅';
    if ($newStatus === 'late') $statusEmoji = '⚠️';
    if ($newStatus === 'absent') $statusEmoji = '❌';
    
    $message = "🔄 <b>Attendance Updated</b>\n\n";
    $message .= "👤 <b>Student:</b> $studentName\n";
    $message .= "📅 <b>Date:</b> $dateStr\n";
    $message .= "🏫 <b>Context:</b> $notifContext\n";
    $message .= "📊 <b>Status:</b> $statusEmoji " . ucfirst($newStatus);
    
    echo json_encode(['status' => 'success', 'new_status' => $newStatus, 'message' => 'Updated successfully!']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

<?php
// api/delete_subject.php
require "../includes/db.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$type = $_POST['type'] ?? '';

try {
    if ($type === 'subject_record') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM subject_attendance WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
    } 
    elseif ($type === 'subject_record_by_qr') {
        $qr = $_POST['qr_code'] ?? '';
        $subId = $_POST['subject_id'] ?? 0;
        $date = $_POST['date'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM subject_attendance WHERE qr_code = ? AND subject_id = ? AND date = ?");
        $stmt->execute([$qr, $subId, $date]);
        echo json_encode(['status' => 'success']);
    }
    elseif ($type === 'subject_day') {
        $subjectId = $_POST['subject_id'] ?? 0;
        $date = $_POST['date'] ?? '';
        
        if(!$subjectId || !$date) throw new Exception("Missing parameters");

        $stmt = $pdo->prepare("DELETE FROM subject_attendance WHERE subject_id = ? AND date = ?");
        $stmt->execute([$subjectId, $date]);
        echo json_encode(['status' => 'success']);
    } 
    else {
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

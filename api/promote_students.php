<?php
// api/promote_students.php - Bulk year advancement logic
header('Content-Type: application/json');
require_once '../includes/db.php';

// Verify POST request and action
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action !== 'promote_all') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

try {
    // We target only 'regular' students
    // Mapping: 1st -> 2nd, 2nd -> 3rd, 3rd -> 4th, 4th -> Graduated
    $sql = "UPDATE users 
            SET year_level = CASE 
                WHEN year_level = '1st' THEN '2nd'
                WHEN year_level = '2nd' THEN '3rd'
                WHEN year_level = '3rd' THEN '4th'
                WHEN year_level = '4th' THEN 'Graduated'
                ELSE year_level 
            END,
            updated_at = CURRENT_TIMESTAMP
            WHERE student_type = 'regular' 
            AND deleted_at IS NULL 
            AND year_level IN ('1st', '2nd', '3rd', '4th')";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $affectedCount = $stmt->rowCount();
    // 2. Clear attendance performance matrix (daily attendance, subjects with cascade, and notified contexts)
    $pdo->exec("DELETE FROM attendance");
    $pdo->exec("DELETE FROM subjects"); // Cascades to schedules, subject_attendance, and student_subjects
    $pdo->exec("DELETE FROM notified_contexts"); // Clear notified state for Telegram bot
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully transitioned to new school year, promoted $affectedCount regular classmates, and reset attendance matrix.",
        'count' => $affectedCount
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

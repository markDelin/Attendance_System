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
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully promoted $affectedCount regular students.",
        'count' => $affectedCount
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

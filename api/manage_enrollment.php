<?php
// api/manage_enrollment.php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit();
}

$action = $_POST['action'] ?? '';
$qr_code = trim($_POST['qr_code'] ?? '');

if (empty($qr_code)) {
    echo json_encode(["status" => "error", "message" => "Student ID required"]);
    exit();
}

try {
    if ($action === 'get_enrollments') {
        // Fetch list of subject_ids student is enrolled in
        $stmt = $pdo->prepare("SELECT subject_id FROM student_subjects WHERE qr_code = ?");
        $stmt->execute([$qr_code]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Also fetch all available subjects (id, name, semester, school_year)
        $stmt2 = $pdo->query("SELECT id, name, semester, school_year FROM subjects ORDER BY school_year DESC, semester, name");
        $allSubjects = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "status" => "success", 
            "enrolled_ids" => $ids,
            "all_subjects" => $allSubjects
        ]);
        
    } elseif ($action === 'save_enrollments') {
        $subject_ids = isset($_POST['subjects']) ? json_decode($_POST['subjects'], true) : [];
        if (!is_array($subject_ids)) $subject_ids = [];
        
        $pdo->beginTransaction();
        
        // 1. Clear existing
        $del = $pdo->prepare("DELETE FROM student_subjects WHERE qr_code = ?");
        $del->execute([$qr_code]);
        
        // 2. Insert new
        if (!empty($subject_ids)) {
            $ins = $pdo->prepare("INSERT INTO student_subjects (qr_code, subject_id) VALUES (?, ?)");
            foreach ($subject_ids as $sid) {
                $ins->execute([$qr_code, $sid]);
            }
        }
        
        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Enrollment updated"]);
        
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
}
?>

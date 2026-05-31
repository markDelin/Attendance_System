<?php
// auto_enroll.php - Auto-enroll all regular students in all current SY subjects
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

try {
    $activeSY = $pdo->query("SELECT active_school_year FROM settings LIMIT 1")->fetchColumn();
    if (!$activeSY) throw new Exception('No active school year set.');

    // Get all subjects for current school year
    $stmtSubj = $pdo->prepare("SELECT id FROM subjects WHERE school_year = ?");
    $stmtSubj->execute([$activeSY]);
    $subjectIds = $stmtSubj->fetchAll(PDO::FETCH_COLUMN);

    if (empty($subjectIds)) {
        echo json_encode(['success' => true, 'message' => 'No subjects found for ' . $activeSY, 'count' => 0]);
        exit;
    }

    // Get all regular students
    $stmtUsers = $pdo->query("SELECT qr_code FROM users WHERE deleted_at IS NULL AND (student_type IS NULL OR student_type = 'regular')");
    $regularQRs = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    if (empty($regularQRs)) {
        echo json_encode(['success' => true, 'message' => 'No regular students found.', 'count' => 0]);
        exit;
    }

    $inserted = 0;
    $checkStmt = $pdo->prepare("SELECT 1 FROM student_subjects WHERE qr_code = ? AND subject_id = ?");
    $insertStmt = $pdo->prepare("INSERT INTO student_subjects (qr_code, subject_id) VALUES (?, ?)");

    $pdo->beginTransaction();
    foreach ($regularQRs as $qr) {
        foreach ($subjectIds as $sid) {
            $checkStmt->execute([$qr, $sid]);
            if (!$checkStmt->fetch()) {
                $insertStmt->execute([$qr, $sid]);
                $inserted++;
            }
        }
    }
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Enrolled $inserted subject(s) for " . count($regularQRs) . " regular student(s) in $activeSY",
        'count' => $inserted,
        'students' => count($regularQRs),
        'subjects' => count($subjectIds)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

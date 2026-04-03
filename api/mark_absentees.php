<?php
// api/mark_absentees.php - Mark enrolled students as absent if they missed the scan
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";
require_once "../includes/telegram.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") exit(json_encode(["status" => "error", "message" => "Invalid request method"]));

if (!isset($_POST["subject_id"])) exit(json_encode(["status" => "error", "message" => "Subject ID is required"]));

$subjectId = intval($_POST["subject_id"]);
$date = $_POST["date"] ?? date("Y-m-d");
$settings = $pdo->query("SELECT active_school_year FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$schoolYear = $settings['active_school_year'] ?? 'SY 2024-2025';

// subject_id 0 = Daily Attendance (Allowed)
// subject_id > 0 = Subject/Event (Allowed)

try {
    // Check if already notified
    $checkNotified = $pdo->prepare("SELECT 1 FROM notified_contexts WHERE subject_id = ? AND date = ?");
    $checkNotified->execute([$subjectId, $date]);
    if ($checkNotified->fetch()) {
        echo json_encode(["status" => "error", "message" => "Already notified for this context today. Records are closed."]);
        exit();
    }

    // Visual Bar Helper
    function get_visual_bar($present, $total) {
        if ($total <= 0) return "<code>[░░░░░░░░░░]</code> 0%";
        $percent = round(($present / $total) * 100);
        $filled = floor(($percent / 100) * 10);
        $bar = str_repeat("█", $filled) . str_repeat("░", 10 - $filled);
        return "<code>[$bar]</code> $percent%";
    }

    if ($subjectId > 0) {
        // 1. SUBJECT/EVENT MODE
        $stmtSub = $pdo->prepare("SELECT name, category FROM subjects WHERE id = ?");
        $stmtSub->execute([$subjectId]);
        $subData = $stmtSub->fetch(PDO::FETCH_ASSOC);
        $subjectName = $subData['name'];
        $category = $subData['category'] ?? 'subject';

        // Find students who should be here (Regular + Enrolled Irregular) but are MISSING from attendance
        $sql = "SELECT qr_code, name FROM users 
                WHERE (
                    (student_type IS NULL OR student_type = 'regular') 
                    OR 
                    (student_type = 'irregular' AND qr_code IN (SELECT qr_code FROM student_subjects WHERE subject_id = ?))
                )
                AND qr_code NOT IN (SELECT qr_code FROM subject_attendance WHERE subject_id = ? AND date = ?)
                AND deleted_at IS NULL";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$subjectId, $subjectId, $date]);
        $missingStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $label = ($category === 'event') ? 'Event' : 'Subject';
        $context = "<b>$label:</b> $subjectName";
    } else {
        // 2. DAILY MODE
        // Find all students in 'users' table who are not in 'attendance' table for today
        $sql = "SELECT qr_code, name 
                FROM users 
                WHERE qr_code NOT IN (
                    SELECT qr_code FROM attendance 
                    WHERE date = ?
                )
                AND deleted_at IS NULL";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date]);
        $missingStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $context = "<b>Daily Attendance</b>";
    }

    // Mark missing students as absent
    foreach ($missingStudents as $student) {
        if ($subjectId > 0) {
            $insert = $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, status) VALUES (?, ?, ?, 'absent')");
            $insert->execute([$subjectId, $student['qr_code'], $date]);
        } else {
            $insert = $pdo->prepare("INSERT INTO attendance (qr_code, date, status, time, school_year) VALUES (?, ?, 'absent', '--:--', ?)");
            $insert->execute([$student['qr_code'], $date, $schoolYear]);
        }
    }

    // FETCH FINAL ABSENTEES LIST FOR REPORTING (Unified)
    if ($subjectId > 0) {
        $stmtReport = $pdo->prepare("SELECT u.name FROM subject_attendance sa JOIN users u ON sa.qr_code = u.qr_code WHERE sa.subject_id = ? AND sa.date = ? AND sa.status = 'absent' ORDER BY u.name");
        $stmtReport->execute([$subjectId, $date]);
        $finalAbsentees = $stmtReport->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM subject_attendance WHERE subject_id = ? AND date = ?");
        $stmtTotal->execute([$subjectId, $date]);
        $totalExpect = $stmtTotal->fetchColumn();
    } else {
        $stmtReport = $pdo->prepare("SELECT u.name FROM attendance a JOIN users u ON a.qr_code = u.qr_code WHERE a.date = ? AND a.status = 'absent' ORDER BY u.name");
        $stmtReport->execute([$date]);
        $finalAbsentees = $stmtReport->fetchAll(PDO::FETCH_COLUMN);

        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
        $stmtTotal->execute();
        $totalExpect = $stmtTotal->fetchColumn();
    }
    $finalCount = count($finalAbsentees);
    $presentCount = $totalExpect - $finalCount;
    $visualBar = get_visual_bar($presentCount, $totalExpect);

    // BATCH NOTIFICATION (Minimalist)
    if ($finalCount > 0) {
        $msg = "$context\n";
        $msg .= "Date: " . date("M j, Y", strtotime($date)) . "\n";
        $msg .= "$visualBar\n\n";
        $msg .= "<b>ABSENTEES (" . $finalCount . "):</b>\n";
        foreach($finalAbsentees as $name) {
            $msg .= "• $name\n";
        }
        $msg .= "\n<i>Attendance Finalized.</i>";
        
        send_telegram_notification($msg);
    } else {
        // Zero absentees report
        send_telegram_notification("$context\nDate: " . date("M j, Y", strtotime($date)) . "\n$visualBar\n\n<b>Zero Absentees.</b> All students recorded as Present/Late.\n\n<i>Attendance Finalized.</i>");
    }

    // RECORD NOTIFICATION
    $pdo->prepare("INSERT OR IGNORE INTO notified_contexts (subject_id, date) VALUES (?, ?)")->execute([$subjectId, $date]);

    echo json_encode([
        "status" => "success", 
        "message" => "Finalized attendance. Total absentees reported: $finalCount.",
        "count" => $finalCount,
        "just_marked" => count($missingStudents)
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

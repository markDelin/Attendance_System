<?php
// api/manage_users.php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

$action = $_POST['action'] ?? '';
$qr_code = trim($_POST['qr_code'] ?? '');

if (empty($qr_code)) {
    echo json_encode(["status" => "error", "message" => "Valid QR Code/ID required"]);
    exit();
}

try {
    if ($action === 'add') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $middle_initial = trim($_POST['middle_initial'] ?? '');
        
        // Validation
        if (empty($first_name) || empty($last_name)) {
             echo json_encode(["status" => "error", "message" => "First Name and Last Name are required"]);
             exit();
        }

        // Construct composite name
        $name = $last_name . ', ' . $first_name . ($middle_initial ? ' ' . $middle_initial . '.' : '');

        // Check duplicate (ignoring soft-deleted, so they can be re-added if desired, or maybe we want to allow restore instead? Let's assume re-adding is fine if deleted)
        $check = $pdo->prepare("SELECT qr_code FROM users WHERE qr_code = ? AND deleted_at IS NULL");
        $check->execute([$qr_code]);
        if ($check->fetch()) {
            echo json_encode(["status" => "error", "message" => "Student ID/QR Code already exists"]);
            exit;
        }

        $type = $_POST['student_type'] ?? 'regular';
        
        // New Fields
        $course = $_POST['course'] ?? '';
        $section = $_POST['section'] ?? '';
        $place_of_birth = $_POST['place_of_birth'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $civil_status = $_POST['civil_status'] ?? '';
        $religion = $_POST['religion'] ?? '';
        $citizenship = $_POST['citizenship'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $email = $_POST['email'] ?? '';
        $birthday = $_POST['birthday'] ?? '';
        $birthday_image = $_POST['birthday_image'] ?? '';
        $home_address = $_POST['home_address'] ?? '';
        $guardian_name = $_POST['guardian_name'] ?? '';
        $guardian_contact = $_POST['guardian_contact'] ?? '';
        $blood_type = $_POST['blood_type'] ?? '';
        $lrn = $_POST['lrn'] ?? '';
        $mother_name = $_POST['mother_name'] ?? '';
        $father_name = $_POST['father_name'] ?? '';
        $guardian_relationship = $_POST['guardian_relationship'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO users (qr_code, name, first_name, last_name, middle_initial, student_type, course, section, place_of_birth, sex, civil_status, religion, citizenship, contact_number, email, birthday, birthday_image, home_address, guardian_name, guardian_contact, blood_type, lrn, mother_name, father_name, guardian_relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$qr_code, $name, $first_name, $last_name, $middle_initial, $type, $course, $section, $place_of_birth, $sex, $civil_status, $religion, $citizenship, $contact_number, $email, $birthday, $birthday_image, $home_address, $guardian_name, $guardian_contact, $blood_type, $lrn, $mother_name, $father_name, $guardian_relationship]);
        
        echo json_encode(["status" => "success", "message" => "Student added successfully"]);

    } elseif ($action === 'update') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $middle_initial = trim($_POST['middle_initial'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            echo json_encode(["status" => "error", "message" => "First Name and Last Name are required"]);
            exit();
        }

        $name = $last_name . ', ' . $first_name . ($middle_initial ? ' ' . $middle_initial . '.' : '');
        $type = $_POST['student_type'] ?? 'regular';
        
        // New Fields
        $course = $_POST['course'] ?? '';
        $section = $_POST['section'] ?? '';
        $place_of_birth = $_POST['place_of_birth'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $civil_status = $_POST['civil_status'] ?? '';
        $religion = $_POST['religion'] ?? '';
        $citizenship = $_POST['citizenship'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $email = $_POST['email'] ?? '';
        $birthday = $_POST['birthday'] ?? '';
        $year_level = $_POST['year_level'] ?? '1st';
        $birthday_image = $_POST['birthday_image'] ?? '';
        $home_address = $_POST['home_address'] ?? '';
        $guardian_name = $_POST['guardian_name'] ?? '';
        $guardian_contact = $_POST['guardian_contact'] ?? '';
        $blood_type = $_POST['blood_type'] ?? '';
        $lrn = $_POST['lrn'] ?? '';
        $mother_name = $_POST['mother_name'] ?? '';
        $father_name = $_POST['father_name'] ?? '';
        $guardian_relationship = $_POST['guardian_relationship'] ?? '';

        $stmt = $pdo->prepare("UPDATE users SET name = ?, first_name = ?, last_name = ?, middle_initial = ?, student_type = ?, course = ?, section = ?, place_of_birth = ?, sex = ?, civil_status = ?, religion = ?, citizenship = ?, contact_number = ?, email = ?, birthday = ?, year_level = ?, birthday_image = ?, home_address = ?, guardian_name = ?, guardian_contact = ?, blood_type = ?, lrn = ?, mother_name = ?, father_name = ?, guardian_relationship = ? WHERE qr_code = ?");
        $stmt->execute([$name, $first_name, $last_name, $middle_initial, $type, $course, $section, $place_of_birth, $sex, $civil_status, $religion, $citizenship, $contact_number, $email, $birthday, $year_level, $birthday_image, $home_address, $guardian_name, $guardian_contact, $blood_type, $lrn, $mother_name, $father_name, $guardian_relationship, $qr_code]);
        
        echo json_encode(["status" => "success", "message" => "User updated successfully"]);

    } elseif ($action === 'delete') {
        $reason = trim($_POST['reason'] ?? 'Unknown');
        
        // Fetch student name before deleting or updating
        $stmt = $pdo->prepare("SELECT name FROM users WHERE qr_code = ?");
        $stmt->execute([$qr_code]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $studentName = $userData ? $userData['name'] : 'Unknown Student';

        // Cascade delete handled by DB Foreign Keys if enabled, but let's be explicit
        $pdo->beginTransaction();
        
        // Do NOT delete attendance records here. Soft delete them instead.
        // $stmt = $pdo->prepare("DELETE FROM attendance WHERE qr_code = ?");
        // $stmt->execute([$qr_code]);

        // PRESERVED: Billing/History is NOT deleted on soft delete
        // It will only be deleted on 'permanent_delete'

        // Soft Delete: Mark as deleted instead of removing
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE qr_code = ?");
        $stmt->execute([$qr_code]);
        
        // Also soft delete attendance? 
        // Optional: If we want to hide their attendance from reports but keep it recoverable
        $pdo->prepare("UPDATE attendance SET deleted_at = CURRENT_TIMESTAMP WHERE qr_code = ?")->execute([$qr_code]);

        $pdo->commit();

        // --- System Notice & Telegram Notification ---
        require_once "../includes/telegram.php";
        
        $noticeContent = "<b>$studentName</b> has been removed from the records (Status: $reason).";
        $pdo->prepare("INSERT INTO system_notices (type, content) VALUES ('removal', ?)")->execute([$noticeContent]);

        $telegramMsg = "<b>SYSTEM NOTICE: RECORD REMOVAL</b>\n━━━━━━━━━━━━━━━━━━━━┳═─\n\n" .
                      "<i>A student has been removed from the logs.</i>\n\n" .
                      "Student: <b>$studentName</b>\n" .
                      "Status: <b>$reason</b>\n\n" .
                      "<i>Data Protocol :: System Audit</i>";
        
        send_telegram_notification($telegramMsg);

        echo json_encode(["status" => "success", "message" => "User moved to Recycle Bin"]);
    
    } elseif ($action === 'restore') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE qr_code = ?")->execute([$qr_code]);
        $pdo->prepare("UPDATE attendance SET deleted_at = NULL WHERE qr_code = ?")->execute([$qr_code]);
        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "User restored successfully"]);

    } elseif ($action === 'permanent_delete') {
         $pdo->beginTransaction();
         $pdo->prepare("DELETE FROM attendance WHERE qr_code = ?")->execute([$qr_code]);
         $pdo->prepare("DELETE FROM subject_attendance WHERE qr_code = ?")->execute([$qr_code]);
         $pdo->prepare("DELETE FROM billing WHERE qr_code = ?")->execute([$qr_code]);
         $pdo->prepare("DELETE FROM billing_history WHERE qr_code = ?")->execute([$qr_code]);
         $pdo->prepare("DELETE FROM student_subjects WHERE qr_code = ?")->execute([$qr_code]);
         $stmt = $pdo->prepare("DELETE FROM users WHERE qr_code = ?");
         $stmt->execute([$qr_code]);
         $pdo->commit();
         echo json_encode(["status" => "success", "message" => "User permanently deleted"]);

    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>

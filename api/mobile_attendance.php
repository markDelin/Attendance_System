<?php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/db.php';

try {
    $settings = $pdo->query("SELECT telegram_bot_token, telegram_group_id, active_school_year FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $botToken = trim($settings['telegram_bot_token'] ?? '');
    $schoolYear = $settings['active_school_year'] ?? 'SY 2024-2025';

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $students = $pdo->query("SELECT qr_code, name, course, year_level FROM users WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $subjects = $pdo->query("SELECT id, name, code, room, lecturer, semester, category FROM subjects WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "students" => $students, "subjects" => $subjects]);
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["status" => "error", "message" => "Invalid request method"]);
        exit;
    }

    $raw = file_get_contents("php://input");
    $input = json_decode($raw, true);
    if (!$input) $input = $_POST;

    $apiKey = trim($input['api_key'] ?? '');
    if (empty($apiKey) || $apiKey !== $botToken) {
        echo json_encode(["status" => "error", "message" => "Invalid API key"]);
        exit;
    }

    $subjectId = isset($input['subject_id']) && $input['subject_id'] !== '' ? intval($input['subject_id']) : null;
    $date = trim($input['date'] ?? date("Y-m-d"));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(["status" => "error", "message" => "Invalid date format"]);
        exit;
    }

    $records = $input['records'] ?? [];
    if (!is_array($records) || empty($records)) {
        echo json_encode(["status" => "error", "message" => "No records provided"]);
        exit;
    }

    $processed = 0;
    $errors = [];
    $present = 0;
    $late = 0;
    $absent = 0;

    foreach ($records as $record) {
        $qr = trim($record['qr_code'] ?? '');
        $status = trim($record['status'] ?? '');
        if (empty($qr) || !in_array($status, ['present', 'late', 'absent'])) {
            $errors[] = "Invalid record: " . json_encode($record);
            continue;
        }

        $time = date("h:i A");

        if ($subjectId) {
            $check = $pdo->prepare("SELECT id FROM subject_attendance WHERE subject_id = ? AND qr_code = ? AND date = ?");
            $check->execute([$subjectId, $qr, $date]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $pdo->prepare("UPDATE subject_attendance SET status = ?, time = ? WHERE id = ?")->execute([$status, $time, $existing['id']]);
            } else {
                $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, time, status) VALUES (?, ?, ?, ?, ?)")->execute([$subjectId, $qr, $date, $time, $status]);
            }
        } else {
            $check = $pdo->prepare("SELECT id FROM attendance WHERE qr_code = ? AND date = ?");
            $check->execute([$qr, $date]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $pdo->prepare("UPDATE attendance SET status = ?, time = ? WHERE id = ?")->execute([$status, $time, $existing['id']]);
            } else {
                $pdo->prepare("INSERT INTO attendance (qr_code, date, time, status, school_year) VALUES (?, ?, ?, ?, ?)")->execute([$qr, $date, $time, $status, $schoolYear]);
            }
        }

        ${$status}++;
        $processed++;
    }

    if ($processed > 0) {
        $context = $subjectId ? "Subject #{$subjectId}" : "Daily";
        $total = $processed;
        $percent = $total > 0 ? round(($present / $total) * 100) : 0;
        $filled = floor(($percent / 100) * 10);
        $bar = str_repeat("i", $filled) . str_repeat(".", 10 - $filled);

        $msg = "[MOBILE] <b>ATTENDANCE UPLOADED</b>\n"
             . "==============================\n"
             . "Context: <b>{$context}</b>\n"
             . "Date: <b>{$date}</b>\n"
             . "Records: <b>{$processed}</b>\n\n"
             . "<code>[{$bar}] {$percent}%</code>\n"
             . "[P] Present: <b>{$present}</b>\n"
             . "[L] Late: <b>{$late}</b>\n"
             . "[A] Absent: <b>{$absent}</b>\n\n"
             . "<i>Synced from mobile app</i>";

        $pdo->prepare("INSERT INTO telegram_queue (message) VALUES (?)")->execute([$msg]);
    }

    echo json_encode([
        "status" => "success",
        "processed" => $processed,
        "summary" => ["present" => $present, "late" => $late, "absent" => $absent],
        "errors" => $errors
    ]);

} catch (Exception $e) {
    error_log("Mobile Attendance API Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Server error occurred"]);
}

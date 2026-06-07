<?php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/db.php';

try {
    $settings = $pdo->query("SELECT telegram_bot_token, telegram_group_id, active_school_year FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $botToken = trim($settings['telegram_bot_token'] ?? '');
    $schoolYear = $settings['active_school_year'] ?? 'SY 2024-2025';

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $stmt = $pdo->query("SELECT qr_code, name, course, year_level FROM users WHERE deleted_at IS NULL ORDER BY name ASC");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "students" => $students]);
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["status" => "error", "message" => "Invalid request method"]);
        exit;
    }

    $raw = file_get_contents("php://input");
    $input = json_decode($raw, true);
    if (!$input) {
        $input = $_POST;
    }

    $apiKey = trim($input['api_key'] ?? '');
    if (empty($apiKey) || $apiKey !== $botToken) {
        echo json_encode(["status" => "error", "message" => "Invalid API key"]);
        exit;
    }

    $date = trim($input['date'] ?? date("Y-m-d"));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(["status" => "error", "message" => "Invalid date format (expected YYYY-MM-DD)"]);
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

    $checkStmt = $pdo->prepare("SELECT id FROM attendance WHERE qr_code = ? AND date = ?");
    $insertStmt = $pdo->prepare("INSERT INTO attendance (qr_code, date, time, status, school_year) VALUES (?, ?, ?, ?, ?)");
    $updateStmt = $pdo->prepare("UPDATE attendance SET status = ?, time = ? WHERE id = ?");

    foreach ($records as $record) {
        $qr = trim($record['qr_code'] ?? '');
        $status = trim($record['status'] ?? '');
        if (empty($qr) || !in_array($status, ['present', 'late', 'absent'])) {
            $errors[] = "Invalid record: " . json_encode($record);
            continue;
        }

        $time = date("h:i A");
        $checkStmt->execute([$qr, $date]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $updateStmt->execute([$status, $time, $existing['id']]);
        } else {
            $insertStmt->execute([$qr, $date, $time, $status, $schoolYear]);
        }

        ${$status}++;
        $processed++;
    }

    if ($processed > 0) {
        $total = $processed;
        $percent = $total > 0 ? round(($present / $total) * 100) : 0;
        $filled = floor(($percent / 100) * 10);
        $bar = str_repeat("i", $filled) . str_repeat(".", 10 - $filled);

        $msg = "[MOBILE] <b>ATTENDANCE UPLOADED</b>\n"
             . "==============================\n"
             . "Date: <b>{$date}</b>\n"
             . "Records: <b>{$processed}</b>\n\n"
             . "<code>[{$bar}] {$percent}%</code>\n"
             . "[P] Present: <b>{$present}</b>\n"
             . "[L] Late: <b>{$late}</b>\n"
             . "[A] Absent: <b>{$absent}</b>\n\n"
             . "<i>Synced from mobile app</i>";

        $qStmt = $pdo->prepare("INSERT INTO telegram_queue (message) VALUES (?)");
        $qStmt->execute([$msg]);
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

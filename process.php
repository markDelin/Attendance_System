<?php
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

if (!isset($_POST['qr_code']) || empty(trim($_POST['qr_code']))) {
    echo json_encode(['status' => 'error', 'message' => 'QR code is required.']);
    exit;
}

// Kunin ang settings mula sa database
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Gamitin ang default values kung walang settings
$CALL_TIME = $settings['call_time'] ?? '08:00';
$GRACE_PERIOD = $settings['grace_period'] ?? 20;
$ABSENT_AFTER = $settings['absent_after'] ?? 30;

$qr_code = trim($_POST['qr_code']);
$name = isset($_POST['name']) ? trim($_POST['name']) : '';

$today = date('Y-m-d');
$currentTime = date('H:i:s'); // 24-hour format for calculation
$currentTimeFormatted = date('h:i:s A'); // 12-hour format for display

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE qr_code = :qr_code");
    $stmt->execute([':qr_code' => $qr_code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Kung bagong QR code at walang name, hilingin muna ang name
        if ($name === "") {
            echo json_encode(['status' => 'new', 'message' => 'New QR code detected. Please provide your name.']);
            exit;
        } else {
            // I-insert ang bagong user
            $stmt = $pdo->prepare("INSERT INTO users (qr_code, name) VALUES (:qr_code, :name)");
            $stmt->execute([':qr_code' => $qr_code, ':name' => $name]);
        }
    }

    // I-check kung naitala na ang attendance para sa araw na ito
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE qr_code = :qr_code AND date = :today");
    $stmt->execute([':qr_code' => $qr_code, ':today' => $today]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attendance) {
        echo json_encode(['status' => 'error', 'message' => 'Attendance already recorded for today.']);
        exit;
    }

    // Tukuyin ang status ng attendance
    $status = 'present';
    $timeDifference = strtotime($currentTime) - strtotime($CALL_TIME.':00');
    
    if ($timeDifference > ($GRACE_PERIOD * 60)) {
        $status = 'late';
        
        if ($timeDifference > (($GRACE_PERIOD + $ABSENT_AFTER) * 60)) {
            $status = 'absent';
        }
    }

    // I-save ang attendance record with status
    $stmt = $pdo->prepare("INSERT INTO attendance (qr_code, date, time, status) VALUES (:qr_code, :date, :time, :status)");
    $stmt->execute([
        ':qr_code' => $qr_code,
        ':date' => $today,
        ':time' => $currentTimeFormatted,
        ':status' => $status
    ]);

    $message = ($status === 'present') ? 'Attendance recorded successfully!' : 
              (($status === 'late') ? 'Late attendance recorded' : 'Marked as absent');

    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'attendance_status' => $status,
        'time_recorded' => $currentTimeFormatted
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
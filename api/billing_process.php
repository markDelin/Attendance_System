<?php
// api/billing_process.php
date_default_timezone_set('Asia/Manila');
require '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$action = $_POST['action'] ?? '';
$qr_code = $_POST['qr_code'] ?? '';
$amount = floatval($_POST['amount'] ?? 0);
$event_id = intval($_POST['event_id'] ?? 1); // Default to 1 (General)

if (empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Action']);
    exit;
}

try {
    // 1. Handle Global/Event Reset
    if ($action === 'reset_event') {
        $pdo->prepare("DELETE FROM billing WHERE event_id = ?")->execute([$event_id]);
        echo json_encode(['status' => 'success', 'message' => 'Payments for this event cleared.']);
        exit;
    }
    
    // Create Event
    if ($action === 'create_event') {
        $name = $_POST['event_name'] ?? 'New Event';
        $quota = floatval($_POST['event_amount'] ?? 0);
        
        $stmt = $pdo->prepare("INSERT INTO billing_events (name, amount) VALUES (?, ?)");
        $stmt->execute([$name, $quota]);
        
        echo json_encode(['status' => 'success', 'message' => 'Event created!', 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // 2. Handle User Actions (Need QR Code)
    if (empty($qr_code)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing User ID']);
        exit;
    }

    // Check User Exists
    $stmt = $pdo->prepare("SELECT name FROM users WHERE qr_code = ?");
    $stmt->execute([$qr_code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Student not found.']);
        exit;
    }
    $name = $user['name'];

    if ($action === 'pay') {
        // Check duplicate for THIS event
        $check = $pdo->prepare("SELECT id FROM billing WHERE qr_code = ? AND event_id = ?");
        $check->execute([$qr_code, $event_id]);
        if ($check->fetch()) {
            echo json_encode(['status' => 'duplicate', 'message' => "$name is already paid."]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO billing (qr_code, event_id, amount, payment_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$qr_code, $event_id, $amount, date('Y-m-d H:i:s')]);

        $hist = $pdo->prepare("INSERT INTO billing_history (qr_code, event_id, amount, payment_date, description) VALUES (?, ?, ?, ?, ?)");
        $hist->execute([$qr_code, $event_id, $amount, date('Y-m-d H:i:s'), "Payment Received"]);

        echo json_encode(['status' => 'success', 'message' => "Payment recorded for $name"]);

    } elseif ($action === 'unpay') {
        $stmt = $pdo->prepare("DELETE FROM billing WHERE qr_code = ? AND event_id = ?");
        $stmt->execute([$qr_code, $event_id]);

        $hist = $pdo->prepare("INSERT INTO billing_history (qr_code, event_id, amount, payment_date, description) VALUES (?, ?, ?, ?, ?)");
        $hist->execute([$qr_code, $event_id, -$amount, date('Y-m-d H:i:s'), "Payment Removed"]);

        echo json_encode(['status' => 'success', 'message' => "Payment removed for $name"]);

    } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE billing SET amount = ?, payment_date = ? WHERE qr_code = ? AND event_id = ?");
        $stmt->execute([$amount, date('Y-m-d H:i:s'), $qr_code, $event_id]);

        $hist = $pdo->prepare("INSERT INTO billing_history (qr_code, event_id, amount, payment_date, description) VALUES (?, ?, ?, ?, ?)");
        $hist->execute([$qr_code, $event_id, $amount, date('Y-m-d H:i:s'), "Payment Updated"]);

        echo json_encode(['status' => 'success', 'message' => "Updated $name to ₱" . number_format($amount, 2)]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>

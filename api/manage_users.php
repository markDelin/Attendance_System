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
    if ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(["status" => "error", "message" => "Name cannot be empty"]);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE qr_code = ?");
        $stmt->execute([$name, $qr_code]);
        
        echo json_encode(["status" => "success", "message" => "User updated successfully"]);

    } elseif ($action === 'delete') {
        // Cascade delete handled by DB Foreign Keys if enabled, but let's be explicit
        $pdo->beginTransaction();
        
        // Delete attendance records First
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE qr_code = ?");
        $stmt->execute([$qr_code]);

        // Delete billing/history if exists
        $pdo->prepare("DELETE FROM billing WHERE qr_code = ?")->execute([$qr_code]);
        $pdo->prepare("DELETE FROM billing_history WHERE qr_code = ?")->execute([$qr_code]);

        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE qr_code = ?");
        $stmt->execute([$qr_code]);
        
        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "User and records deleted"]);

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

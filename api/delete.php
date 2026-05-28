<?php
// delete.php - Delete attendance record(s)
header("Content-Type: application/json");
require '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

try {
    if (isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['status' => 'success', 'message' => 'Record deleted']);
    } elseif (isset($_POST['date']) && isset($_POST['qr_code'])) {
        // AJAX individual delete from manual.php
        $date = $_POST['date'];
        $qr = $_POST['qr_code'];
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE date = ? AND qr_code = ?");
        $stmt->execute([$date, $qr]);
        echo json_encode(['status' => 'success', 'message' => 'Record deleted']);
    } elseif (isset($_POST['date'])) {
        $date = $_POST['date'];
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE date = :date");
        $stmt->execute([':date' => $date]);
        echo json_encode(['status' => 'success', 'message' => 'All records for date deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No valid parameters provided']);
        exit;
    }

    // Redirect for non-AJAX requests
    if (!isset($_POST['qr_code']) && !(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
        header("Location: ../view_attendance.php");
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
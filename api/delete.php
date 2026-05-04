<?php
// delete.php - Delete attendance record(s)
require '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") exit("Invalid request method");

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = :id");
    $stmt->execute([':id' => $id]);
} elseif (isset($_POST['date'])) {
    $date = $_POST['date'];
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE date = :date");
    $stmt->execute([':date' => $date]);
}

header("Location: ../view_attendance.php");
exit;
?>
<?php
// delete.php - Delete attendance record(s)
require 'db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = :id");
    $stmt->execute([':id' => $id]);
} elseif (isset($_GET['date'])) {
    $date = $_GET['date'];
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE date = :date");
    $stmt->execute([':date' => $date]);
}

header("Location: view_attendance.php");
exit;
?>

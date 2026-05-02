<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM telegram_queue ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
header("Content-Type: application/json");
echo json_encode($row, JSON_PRETTY_PRINT);
?>

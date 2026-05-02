<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM telegram_queue ORDER BY created_at DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
header("Content-Type: application/json");
echo json_encode($rows, JSON_PRETTY_PRINT);
?>

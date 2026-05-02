<?php
require 'includes/db.php';
$msg = $pdo->query("SELECT message FROM telegram_queue ORDER BY id DESC LIMIT 1")->fetchColumn();
echo $msg;
?>

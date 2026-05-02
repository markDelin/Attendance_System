<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT DISTINCT section FROM users WHERE deleted_at IS NULL");
$sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Current sections: " . implode(", ", $sections) . "\n";
?>

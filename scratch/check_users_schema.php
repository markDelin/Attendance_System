<?php
require 'includes/db.php';
$stmt = $pdo->query("PRAGMA table_info(users)");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . " (" . $row['type'] . ")\n";
}
?>

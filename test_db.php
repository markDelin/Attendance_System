<?php
require 'includes/db.php';
try {
    $students = $pdo->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($students) . "\n";
    foreach ($students as $s) {
        echo "Name: " . $s['name'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

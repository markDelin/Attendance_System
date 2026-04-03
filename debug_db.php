<?php
// debug_db.php
require 'includes/db.php';

try {
    echo "<h2>Database Check</h2>";
    echo "Connected successfully.<br>";
    
    // Check tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables:</h3>";
    echo "<ul>";
    foreach ($tables as $t) echo "<li>$t</li>";
    echo "</ul>";

    // Check attendance columns
    echo "<h3>'attendance' Columns:</h3>";
    $cols = $pdo->query("PRAGMA table_info(attendance)")->fetchAll(PDO::FETCH_ASSOC);
    echo "<ul>";
    foreach ($cols as $c) {
        echo "<li>{$c['name']} ({$c['type']})</li>";
    }
    echo "</ul>";

    // Check DB File Path
    echo "<h3>File Status:</h3>";
    $dbPath = __DIR__ . '/database.sqlite'; // Assuming based on previous context
    echo "Expected Path: $dbPath<br>";
    echo "Exists: " . (file_exists($dbPath) ? "Yes" : "No") . "<br>";
    echo "Size: " . (file_exists($dbPath) ? filesize($dbPath) : 0) . " bytes<br>";
    echo "Writable: " . (is_writable($dbPath) ? "Yes" : "No") . "<br>";

} catch (PDOException $e) {
    echo "<h1>Connection Failed</h1>";
    echo "Error: " . $e->getMessage();
}
?>

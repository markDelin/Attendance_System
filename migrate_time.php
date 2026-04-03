<?php
require 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE subject_attendance ADD COLUMN time TEXT");
    echo "Migration Successful: Added 'time' column to subject_attendance.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "Column 'time' already exists. No changes needed.";
    } else {
        echo "Migration Failed: " . $e->getMessage();
    }
}
?>

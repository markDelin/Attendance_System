<?php
// db.php - Secure SQLite3 Database Connection
// Define paths
$database = __DIR__ . '/attendance.db';
$errorLog = __DIR__ . '/database_errors.log';

try {
    // 1. Directory Validation
    if (!is_writable(dirname($database))) {
        throw new Exception("Database directory is not writable");
    }

    // 2. Database Connection
    $pdo = new PDO("sqlite:" . $database);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. Enable Foreign Key Support
    $pdo->exec("PRAGMA foreign_keys = ON");

    // 4. Table Creation with Enhanced Schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        qr_code TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        qr_code TEXT NOT NULL,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        status TEXT NOT NULL CHECK(status IN ('present', 'late', 'absent')),
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(qr_code) REFERENCES users(qr_code) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        call_time TEXT NOT NULL DEFAULT '08:00',
        grace_period INTEGER NOT NULL DEFAULT 20,
        absent_after INTEGER NOT NULL DEFAULT 30,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Initialize Default Settings
    $settingsCount = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settingsCount == 0) {
        $pdo->exec("INSERT INTO settings (call_time, grace_period, absent_after)
                    VALUES ('08:00', 20, 30)");
    }

} catch (PDOException $e) {
    // 6. Secure Error Handling
    $errorMessage = date('[Y-m-d H:i:s]') . " DB Error: " . $e->getMessage() . PHP_EOL;
    file_put_contents($errorLog, $errorMessage, FILE_APPEND);

    // Generic error message for users
    die("System maintenance in progress. Please try again later.");
} catch (Exception $e) {
    die("Configuration error: " . htmlspecialchars($e->getMessage()));
}
?>

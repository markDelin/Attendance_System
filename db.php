<?php
// db.php - SQLite connection and initialization with all required tables

$database = __DIR__ . '/attendance.db';

try {
    $pdo = new PDO("sqlite:" . $database);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create users table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        qr_code TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create attendance table if not exists (with status column)
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        qr_code TEXT NOT NULL,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'present',
        FOREIGN KEY(qr_code) REFERENCES users(qr_code),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create settings table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        call_time TEXT NOT NULL DEFAULT '08:00',
        grace_period INTEGER NOT NULL DEFAULT 20,
        absent_after INTEGER NOT NULL DEFAULT 30,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create events table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        event_date DATE PRIMARY KEY,
        event_name TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default settings if none exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (call_time, grace_period, absent_after) 
                   VALUES ('08:00', 20, 30)");
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
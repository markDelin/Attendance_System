
<?php
// db.php - SQLite connection and initialization

$database = __DIR__ . '/attendance.db';

try {
    $pdo = new PDO("sqlite:" . $database);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create users table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        qr_code TEXT PRIMARY KEY,
        name TEXT NOT NULL
    )");

    // Create attendance table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        qr_code TEXT NOT NULL,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        FOREIGN KEY(qr_code) REFERENCES users(qr_code)
    )");
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

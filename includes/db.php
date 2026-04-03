<?php
// db.php - Secure SQLite3 Database Connection with Time In/Out and Billing Support
$database = __DIR__ . '/../database/attendance.db';
$errorLog = __DIR__ . '/../database/database_errors.log';

try {
    // 1. Directory and Connection Setup
    // Use realpath to resolve nested paths like includes/../database
    $realPath = realpath($database);
    if ($realPath) {
        $database = $realPath;
    }

    $pdo = new PDO("sqlite:" . $database);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA foreign_keys = ON");

    // Auto-Migration for 'time' column in subject_attendance
    // This ensures the column exists even if migration script failed
    try {
        $pdo->exec("ALTER TABLE subject_attendance ADD COLUMN time TEXT");
    } catch (PDOException $e) {
        // Ignore "duplicate column" error
    }

    // 2. Table Definitions
    $tables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            qr_code TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "attendance" => "CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qr_code TEXT NOT NULL,
            date TEXT NOT NULL,
            time TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('present', 'late', 'absent')),
            session TEXT CHECK(session IN ('morning', 'afternoon')),
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(qr_code) REFERENCES users(qr_code) ON DELETE CASCADE
        )",
        
        "settings" => "CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            call_time TEXT NOT NULL DEFAULT '08:00',
            grace_period INTEGER NOT NULL DEFAULT 20,
            absent_after INTEGER NOT NULL DEFAULT 30,
            time_in_out_enabled INTEGER NOT NULL DEFAULT 0,
            registration_lock INTEGER NOT NULL DEFAULT 0,
            billing_quota REAL NOT NULL DEFAULT 1000.00,
            billing_target_date TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "billing_events" => "CREATE TABLE IF NOT EXISTS billing_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            amount REAL NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        "saved_groups" => "CREATE TABLE IF NOT EXISTS saved_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            members TEXT NOT NULL, -- JSON stored as text
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        "subjects" => "CREATE TABLE IF NOT EXISTS subjects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            semester TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "subject_attendance" => "CREATE TABLE IF NOT EXISTS subject_attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject_id INTEGER NOT NULL,
            qr_code TEXT NOT NULL,
            date TEXT NOT NULL,
            time TEXT, -- Added for specific class time
            status TEXT NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        )",
        
        "schedules" => "CREATE TABLE IF NOT EXISTS schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject_id INTEGER NOT NULL,
            day_of_week TEXT NOT NULL, -- Monday, Tuesday...
            start_time TEXT NOT NULL, -- HH:MM (24-hour)
            end_time TEXT NOT NULL,    -- HH:MM
            FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        )",
        
        "billing" => "CREATE TABLE IF NOT EXISTS billing (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qr_code TEXT NOT NULL,
            event_id INTEGER DEFAULT 1, -- Default to General
            amount REAL NOT NULL,
            payment_date TEXT NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(qr_code) REFERENCES users(qr_code) ON DELETE CASCADE,
            FOREIGN KEY(event_id) REFERENCES billing_events(id) ON DELETE CASCADE
        )",
        
        "billing_history" => "CREATE TABLE IF NOT EXISTS billing_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qr_code TEXT NOT NULL,
            event_id INTEGER DEFAULT 1,
            amount REAL NOT NULL,
            payment_date TEXT NOT NULL,
            description TEXT,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(qr_code) REFERENCES users(qr_code) ON DELETE CASCADE
        )"
    ];

    foreach ($tables as $table => $sql) {
        $pdo->exec($sql);
    }

    // 3. Check and add columns if missing
    $columns = $pdo->query("PRAGMA table_info(attendance)")->fetchAll(PDO::FETCH_ASSOC);
    $sessionColumnExists = false;
    $remarksColumnExists = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'session') $sessionColumnExists = true;
    }
    
    if (!$sessionColumnExists) {
        try {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN session TEXT CHECK(session IN ('morning', 'afternoon'))");
        } catch (Exception $e) { /* Ignore */ }
    }


    // 4. Initialize Default Settings & Migrations
    // Migration: Add registration_lock if missing
    $settingsCols = $pdo->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_ASSOC);
    $cols = array_column($settingsCols, 'name');

    // Users Migration (Birthday, Email)
    $userCols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $uCols = array_column($userCols, 'name');

    if (!in_array('birthday', $uCols)) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN birthday DATE"); } catch (Exception $e) {}
    }
    if (!in_array('email', $uCols)) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT"); } catch (Exception $e) {}
    }

    if (!in_array('registration_lock', $cols)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN registration_lock INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('billing_quota', $cols)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN billing_quota REAL NOT NULL DEFAULT 50.00");
    }
    if (!in_array('billing_mode', $cols)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN billing_mode TEXT NOT NULL DEFAULT 'fixed'"); // 'fixed' or 'quota'
    }

    // Billing Events Migration
    $billingCols = $pdo->query("PRAGMA table_info(billing)")->fetchAll(PDO::FETCH_ASSOC);
    $bCols = array_column($billingCols, 'name');
    if (!in_array('event_id', $bCols)) {
        // We need to add event_id column. 
        // 1. Ensure a default event exists
        $pdo->exec("INSERT OR IGNORE INTO billing_events (id, name, amount) VALUES (1, 'General Contribution', (SELECT billing_quota FROM settings LIMIT 1))");
        
        // 2. Add column (SQLite supports ADD COLUMN but lacks complex alter for constraints easily, so we just add column)
        try { 
            $pdo->exec("ALTER TABLE billing ADD COLUMN event_id INTEGER DEFAULT 1 REFERENCES billing_events(id) ON DELETE CASCADE"); 
            $pdo->exec("ALTER TABLE billing_history ADD COLUMN event_id INTEGER DEFAULT 1");
        } catch (Exception $e) {}
    } else {
         // Ensure default event exists if table is empty but structure is there
         $eventCount = $pdo->query("SELECT COUNT(*) FROM billing_events")->fetchColumn();
         if ($eventCount == 0) {
             $pdo->exec("INSERT INTO billing_events (id, name, amount) VALUES (1, 'General Contribution', 50.00)");
         }
    }

    $settingsCount = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settingsCount == 0) {
        $pdo->exec("INSERT INTO settings (call_time, grace_period, absent_after, time_in_out_enabled, billing_quota, billing_mode) 
                    VALUES ('08:00', 20, 30, 0, 50.00, 'fixed')");
    }

    /**
     * Get Attendance Summary with Time In/Out Support
     */
    function getAttendanceSummary($qr_code) {
        global $pdo;
        
        $summary = [
            'total' => 0,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'percentage' => 0,
            'today_status' => 'Not Recorded',
            'morning' => null,
            'afternoon' => null,
            'recent' => []
        ];
        
        try {
            // Get total days
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT date) FROM attendance");
            $stmt->execute();
            $total_days = $stmt->fetchColumn();
            
            if ($total_days > 0) {
                // Get summary counts
                $stmt = $pdo->prepare("SELECT 
                    COUNT(*) as total,
                    SUM(status = 'present') as present,
                    SUM(status = 'late') as late,
                    SUM(status = 'absent') as absent
                    FROM attendance 
                    WHERE qr_code = ?");
                $stmt->execute([$qr_code]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data) {
                    $summary['total'] = (int)$data['total'];
                    $summary['present'] = (int)$data['present'];
                    $summary['late'] = (int)$data['late'];
                    $summary['absent'] = (int)$data['absent'];
                    $summary['percentage'] = $total_days > 0 ? round(($data['present'] / $total_days) * 100) : 0;
                }
                
                // Get today's records
                $stmt = $pdo->prepare("SELECT id, time, status, session 
                                      FROM attendance 
                                      WHERE qr_code = ? AND date = DATE('now')
                                      ORDER BY time");
                $stmt->execute([$qr_code]);
                $todayRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($todayRecords)) {
                    foreach ($todayRecords as $record) {
                        if ($record['session'] === 'morning') {
                            $summary['morning'] = [
                                'time' => $record['time'],
                                'status' => $record['status']
                            ];
                        } elseif ($record['session'] === 'afternoon') {
                            $summary['afternoon'] = [
                                'time' => $record['time'],
                                'status' => $record['status']
                            ];
                        }
                    }
                    $summary['today_status'] = 'Recorded';
                }
                
                // Get recent records
                $stmt = $pdo->prepare("SELECT date, time, status, session 
                                      FROM attendance 
                                      WHERE qr_code = ?
                                      ORDER BY date DESC, time DESC
                                      LIMIT 5");
                $stmt->execute([$qr_code]);
                $summary['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Attendance summary error: " . $e->getMessage());
        }
        
        return $summary;
    }

    
} catch (PDOException $e) {
    // DEBUG: Showing actual error to fix connection issue
    die("Database Error: " . $e->getMessage() . " <br>Path: " . $database);
} catch (Exception $e) {
    die("Configuration error: " . htmlspecialchars($e->getMessage()));
}
?>
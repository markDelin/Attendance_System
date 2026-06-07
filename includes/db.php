<?php
// db.php - Secure SQLite3 Database Connection with Time In/Out and Billing Support
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');
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

    // Note: Other column migrations are handled in the consolidated migration section below

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
            school_year TEXT,
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
            active_school_year TEXT DEFAULT 'SY 2024-2025',
            sy_start_date TEXT,
            sy_end_date TEXT,
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
            code TEXT,
            room TEXT,
            lecturer TEXT,
            semester TEXT NOT NULL,
            school_year TEXT,
            category TEXT DEFAULT 'subject',
            is_active INTEGER DEFAULT 1,
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
            school_year TEXT,
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
            school_year TEXT,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(qr_code) REFERENCES users(qr_code) ON DELETE CASCADE
        )",
        
        "student_subjects" => "CREATE TABLE IF NOT EXISTS student_subjects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qr_code TEXT NOT NULL,
            subject_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(qr_code) REFERENCES users(qr_code) ON DELETE CASCADE,
            FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        )",
        
        "schedule_settings" => "CREATE TABLE IF NOT EXISTS schedule_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "notified_contexts" => "CREATE TABLE IF NOT EXISTS notified_contexts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(subject_id, date)
        )",
        
        "telegram_queue" => "CREATE TABLE IF NOT EXISTS telegram_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message TEXT NOT NULL,
            status TEXT DEFAULT 'pending', -- pending, sent, failed
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        "birthday_greetings" => "CREATE TABLE IF NOT EXISTS birthday_greetings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qr_code TEXT NOT NULL,
            year TEXT NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(qr_code, year)
        )",

        "scheduled_announcements" => "CREATE TABLE IF NOT EXISTS scheduled_announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message TEXT NOT NULL,
            scheduled_date TEXT NOT NULL,
            scheduled_time TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            created_by TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        "system_notices" => "CREATE TABLE IF NOT EXISTS system_notices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "wifi_networks" => "CREATE TABLE IF NOT EXISTS wifi_networks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ssid TEXT NOT NULL,
            password TEXT,
            encryption TEXT DEFAULT 'WPA',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($tables as $table => $sql) {
        $pdo->exec($sql);
    }

    // 3. Check and add columns if missing
    $columns = $pdo->query("PRAGMA table_info(attendance)")->fetchAll(PDO::FETCH_ASSOC);
    $sessionColumnExists = false;
    
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
    // New Student Info Columns
    if (!in_array('first_name', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN first_name TEXT"); } catch (Exception $e) {} }
    if (!in_array('last_name', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN last_name TEXT"); } catch (Exception $e) {} }
    if (!in_array('middle_initial', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN middle_initial TEXT"); } catch (Exception $e) {} }
    if (!in_array('student_type', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN student_type TEXT DEFAULT 'regular'"); } catch (Exception $e) {} }
    if (!in_array('course', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN course TEXT"); } catch (Exception $e) {} }
    if (!in_array('section', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN section TEXT"); } catch (Exception $e) {} }
    if (!in_array('place_of_birth', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN place_of_birth TEXT"); } catch (Exception $e) {} }
    if (!in_array('sex', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN sex TEXT"); } catch (Exception $e) {} }
    if (!in_array('civil_status', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN civil_status TEXT"); } catch (Exception $e) {} }
    if (!in_array('religion', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN religion TEXT"); } catch (Exception $e) {} }
    if (!in_array('citizenship', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN citizenship TEXT"); } catch (Exception $e) {} }
    if (!in_array('contact_number', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN contact_number TEXT"); } catch (Exception $e) {} }
    if (!in_array('year_level', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN year_level TEXT DEFAULT '1st'"); } catch (Exception $e) {} }
    if (!in_array('birthday_image', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN birthday_image TEXT DEFAULT NULL"); } catch (Exception $e) {} }
    if (!in_array('deleted_at', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN deleted_at DATETIME DEFAULT NULL"); } catch (Exception $e) {} }
    if (!in_array('home_address', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN home_address TEXT"); } catch (Exception $e) {} }
    if (!in_array('guardian_name', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN guardian_name TEXT"); } catch (Exception $e) {} }
    if (!in_array('guardian_contact', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN guardian_contact TEXT"); } catch (Exception $e) {} }
    if (!in_array('blood_type', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN blood_type TEXT"); } catch (Exception $e) {} }
    if (!in_array('lrn', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN lrn TEXT"); } catch (Exception $e) {} }
    if (!in_array('mother_name', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN mother_name TEXT"); } catch (Exception $e) {} }
    if (!in_array('father_name', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN father_name TEXT"); } catch (Exception $e) {} }
    if (!in_array('guardian_relationship', $uCols)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN guardian_relationship TEXT"); } catch (Exception $e) {} }

    // Attendance soft delete migration
    $attColNames = array_column($pdo->query("PRAGMA table_info(attendance)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('deleted_at', $attColNames)) {
        try { $pdo->exec("ALTER TABLE attendance ADD COLUMN deleted_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}
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
    if (!in_array('maintenance_mode', $cols)) {
        try { $pdo->exec("ALTER TABLE settings ADD COLUMN maintenance_mode INTEGER DEFAULT 0"); } catch (Exception $e) {}
    }

    if (!in_array('telegram_bot_token', $cols)) {
        try { $pdo->exec("ALTER TABLE settings ADD COLUMN telegram_bot_token TEXT DEFAULT ''"); } catch (Exception $e) {}
    }
    if (!in_array('telegram_group_id', $cols)) {
        try { $pdo->exec("ALTER TABLE settings ADD COLUMN telegram_group_id TEXT DEFAULT ''"); } catch (Exception $e) {}
    }
    if (!in_array('admin_telegram_id', $cols)) {
        try { $pdo->exec("ALTER TABLE settings ADD COLUMN admin_telegram_id TEXT DEFAULT ''"); } catch (Exception $e) {}
    }
    
    // School Year Settings Migration
    if (!in_array('active_school_year', $cols)) {
        try { $pdo->exec("ALTER TABLE settings ADD COLUMN active_school_year TEXT DEFAULT 'SY 2024-2025'"); } catch (Exception $e) {}
    }
    if (!in_array('sy_start_date', $cols)) {
        try { $pdo->exec("ALTER TABLE settings ADD COLUMN sy_start_date TEXT"); } catch (Exception $e) {}
    }
    if (!in_array('sy_end_date', $cols)) {
        try { $pdo->exec("ALTER TABLE settings ADD COLUMN sy_end_date TEXT"); } catch (Exception $e) {}
    }
    if (!in_array('birthday_image', $cols)) {
        try { $pdo->exec("ALTER TABLE settings ADD COLUMN birthday_image TEXT DEFAULT NULL"); } catch (Exception $e) {}
    }

    // Attendance & Billing SY Migration
    $attCols = $pdo->query("PRAGMA table_info(attendance)")->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('school_year', array_column($attCols, 'name'))) {
        try { 
            $pdo->exec("ALTER TABLE attendance ADD COLUMN school_year TEXT"); 
            $pdo->exec("UPDATE attendance SET school_year = (SELECT active_school_year FROM settings LIMIT 1) WHERE school_year IS NULL");
        } catch (Exception $e) {}
    }

    $billCols = $pdo->query("PRAGMA table_info(billing)")->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('school_year', array_column($billCols, 'name'))) {
        try { 
            $pdo->exec("ALTER TABLE billing ADD COLUMN school_year TEXT"); 
            $pdo->exec("UPDATE billing SET school_year = (SELECT active_school_year FROM settings LIMIT 1) WHERE school_year IS NULL");
        } catch (Exception $e) {}
    }

    $billHistCols = $pdo->query("PRAGMA table_info(billing_history)")->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('school_year', array_column($billHistCols, 'name'))) {
        try { 
            $pdo->exec("ALTER TABLE billing_history ADD COLUMN school_year TEXT"); 
            $pdo->exec("UPDATE billing_history SET school_year = (SELECT active_school_year FROM settings LIMIT 1) WHERE school_year IS NULL");
        } catch (Exception $e) {}
    }

    // Subjects Migration (Code, Room, Lecturer)
    $subjectCols = $pdo->query("PRAGMA table_info(subjects)")->fetchAll(PDO::FETCH_ASSOC);
    $sCols = array_column($subjectCols, 'name');

    if (!in_array('code', $sCols)) {
        try { $pdo->exec("ALTER TABLE subjects ADD COLUMN code TEXT"); } catch (Exception $e) {}
    }
    if (!in_array('room', $sCols)) {
        try { $pdo->exec("ALTER TABLE subjects ADD COLUMN room TEXT"); } catch (Exception $e) {}
    }
    if (!in_array('lecturer', $sCols)) {
        try { $pdo->exec("ALTER TABLE subjects ADD COLUMN lecturer TEXT"); } catch (Exception $e) {}
    }
    if (!in_array('category', $sCols)) {
        try { $pdo->exec("ALTER TABLE subjects ADD COLUMN category TEXT DEFAULT 'subject'"); } catch (Exception $e) {}
    }
    if (!in_array('is_active', $sCols)) {
        try { $pdo->exec("ALTER TABLE subjects ADD COLUMN is_active INTEGER DEFAULT 1"); } catch (Exception $e) {}
    }
    if (!in_array('school_year', $sCols)) {
        try { 
            $pdo->exec("ALTER TABLE subjects ADD COLUMN school_year TEXT"); 
            
            // Auto-Migration of data
            $stmt = $pdo->query("SELECT id, semester FROM subjects");
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($subs as $sub) {
                if (preg_match('/(\d{4}-\d{4})/', $sub['semester'], $m)) {
                    $sy = 'SY ' . $m[1];
                    $upd = $pdo->prepare("UPDATE subjects SET school_year = ? WHERE id = ?");
                    $upd->execute([$sy, $sub['id']]);
                }
            }
        } catch (Exception $e) {}
    }

    // Notified Contexts Migration
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notified_contexts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(subject_id, date)
        )");
    } catch (Exception $e) {}

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
        $pdo->exec("INSERT INTO settings (call_time, grace_period, absent_after, time_in_out_enabled, billing_quota, billing_mode, active_school_year) 
                    VALUES ('08:00', 20, 30, 0, 50.00, 'fixed', 'SY 2024-2025')");
    }

    /**
     * Get Attendance Summary with Time In/Out Support
     */
    if (!function_exists('getAttendanceSummary')) {
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
                    WHERE qr_code = ? AND (school_year = (SELECT active_school_year FROM settings LIMIT 1) OR school_year IS NULL)");
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
    } // end function_exists check

    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " | Path: " . $database, 3, $errorLog);
    die("Database connection error. Please check the server configuration.");
} catch (Exception $e) {
    error_log("Configuration error: " . $e->getMessage(), 3, $errorLog);
    die("Configuration error. Please contact the administrator.");
}
?>
<?php
// api/restore_process.php
date_default_timezone_set('Asia/Manila');
require_once '../includes/db.php'; // $pdo is current DB

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$filename = $_POST['filename'] ?? '';
$backupDir = __DIR__ . '/../backups';
$tempDir = __DIR__ . '/../temp_restore';
$zipPath = $backupDir . '/' . basename($filename);

if (!file_exists($zipPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Backup file not found']);
    exit;
}

// Helper to cleanup temp
function cleanup($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? cleanup("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}

try {
    // 1. Extract if not already extracted (or re-extract to be safe)
    if (is_dir($tempDir)) cleanup($tempDir);
    mkdir($tempDir);

    $zip = new ZipArchive;
    if ($zip->open($zipPath) === TRUE) {
        $zip->extractTo($tempDir);
        $zip->close();
    } else {
        throw new Exception("Failed to unzip backup");
    }

    // Locate database in temp
    // It should be 'database/attendance.db' based on structure, or just 'attendance.db' depending on how it was zipped.
    // Let's search for it.
    $dbPath = '';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir));
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'attendance.db') {
            $dbPath = $file->getPathname();
            break;
        }
    }

    if (!$dbPath) throw new Exception("attendance.db not found in backup");

    // Connect to Backup DB
    $backupPdo = new PDO("sqlite:" . $dbPath);
    $backupPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Analyze or Restore
    if ($action === 'analyze') {
        // Find missing users
        $stmt = $backupPdo->query("SELECT qr_code FROM users");
        $backupUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->query("SELECT qr_code FROM users"); // Current
        $currentUsers = $stmt->fetchAll(PDO::FETCH_COLUMN); // Array of IDs
        
        $missingUsers = array_diff($backupUsers, $currentUsers); // IDs in backup but not current

        // Find missing attendance
        // We can't easily diff all rows by ID because IDs might overlap if autoincrement was reset (unlikely with UUID/QR, but attendance ID is int).
        // Better to check unique constraints: qr_code + date + time
        // Fetching all might be heavy. Let's fetch hashes or just iterate.
        // For simplicity, let's just count.
        
        // Actually, "Restore" needs to know WHAT to restore.
        // Let's just return counts for analysis.
        
        $missingAttendanceCount = 0;
        
        // This is an O(N*M) or O(N) operation. For small DB it's fine.
        // Optimization: Use NOT IN with attached DB if possible, but separate PDO is strictly safer.
        // Let's fetch all attendance signatures from Current
        $stmt = $pdo->query("SELECT qr_code || '-' || date || '-' || time as sig FROM attendance");
        $currentSigs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $currentSigSet = array_flip($currentSigs); // Faster lookup

        $stmt = $backupPdo->query("SELECT qr_code || '-' || date || '-' || time as sig FROM attendance");
        $backupSigs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingSigs = [];
        foreach($backupSigs as $sig) {
            if(!isset($currentSigSet[$sig])) {
                $missingSigs[] = $sig;
            }
        }

        echo json_encode([
            'status' => 'success',
            'missing_users_count' => count($missingUsers),
            'missing_attendance_count' => count($missingSigs)
        ]);

    } elseif ($action === 'restore') {
        
        $pdo->beginTransaction();
        $restoredUsers = 0;
        $restoredAtt = 0;

        // Restore Users
        // Get missing User IDs again (re-calc to be stateless/safe)
        $stmt = $backupPdo->query("SELECT qr_code FROM users");
        $backupUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->query("SELECT qr_code FROM users"); 
        $currentUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missingUsers = array_diff($backupUsers, $currentUsers);

        if (!empty($missingUsers)) {
            $placeholders = str_repeat('?,', count($missingUsers) - 1) . '?';
            $sql = "SELECT * FROM users WHERE qr_code IN ($placeholders)";
            $stmt = $backupPdo->prepare($sql);
            $stmt->execute(array_values($missingUsers));
            $usersToRestore = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $insert = $pdo->prepare("INSERT INTO users (qr_code, name, first_name, last_name, middle_initial, course, section, student_type, sex, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($usersToRestore as $u) {
                $insert->execute([
                    $u['qr_code'], $u['name'], 
                    $u['first_name'] ?? '', $u['last_name'] ?? '', $u['middle_initial'] ?? '',
                    $u['course'] ?? '', $u['section'] ?? '', $u['student_type'] ?? 'regular',
                    $u['sex'] ?? '', $u['contact_number'] ?? ''
                ]);
                $restoredUsers++;
            }
        }

        // Restore Attendance
        // For attendance, we need to fetch all and check existence one by one or via signature map
        // Retrying the signature approach
        $stmt = $pdo->query("SELECT qr_code || '-' || date || '-' || time as sig FROM attendance");
        $currentSigs = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $stmt = $backupPdo->query("SELECT * FROM attendance");
        $backupRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $insert = $pdo->prepare("INSERT INTO attendance (qr_code, date, time, status, session, recorded_at) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($backupRecords as $row) {
            $sig = $row['qr_code'] . '-' . $row['date'] . '-' . $row['time'];
            if (!isset($currentSigs[$sig])) {
                // Ensure user exists (if we didn't restore them above, we might fail FK constraint)
                // If user was missing, we restored them above. If user exists in current, we are good.
                // So this is safe.
                try {
                    $insert->execute([
                        $row['qr_code'], $row['date'], $row['time'], 
                        $row['status'], $row['session'] ?? null, $row['recorded_at']
                    ]);
                    $restoredAtt++;
                } catch (PDOException $e) {
                    // Ignore FK violations if any (orphan records)
                }
            }
        }

        $pdo->commit();
        
        // Cleanup
        cleanup($tempDir);

        echo json_encode([
            'status' => 'success',
            'message' => "Restored: $restoredUsers Users, $restoredAtt Attendance Records."
        ]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

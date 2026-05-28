<?php
// api/import_data.php
require_once '../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['status' => 'error', 'message' => 'Invalid method']));
}

if (!isset($_FILES['import_file'])) {
    exit(json_encode(['status' => 'error', 'message' => 'No file uploaded']));
}

try {
    $file = $_FILES['import_file']['tmp_name'];
    // $mime = mime_content_type($file); // Removed: causing error on some systems
    $data = [];

    // Detect JSON or CSV
    $content = file_get_contents($file);
    $firstChar = substr(trim($content), 0, 1);

    if ($firstChar === '[' || $firstChar === '{') {
        // JSON
        $data = json_decode($content, true);
        if (!is_array($data)) throw new Exception("Invalid JSON format");
    } else {
        // CSV (Assume) header: QR Code, Student Name, Date, Time, Status, Session, Recorded At
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",", "\"", "\\"); // Skip header
            while (($row = fgetcsv($handle, 1000, ",", "\"", "\\")) !== FALSE) {
                // Map CSV columns to array
                 if(count($row) < 4) continue; // Basic validation
                 $data[] = [
                    'qr_code' => $row[0],
                    // Name is row 1, ignored
                    'date' => $row[2],
                    'time' => $row[3],
                    'status' => $row[4] ?? 'present',
                    'session' => $row[5] ?? null,
                    // recorded_at is row 6
                 ];
            }
            fclose($handle);
        } else {
             throw new Exception("Could not open file");
        }
    }

    $pdo->beginTransaction();
    $imported = 0;
    $skipped = 0;

    // Get Import Type
    $type = $_POST['type'] ?? 'daily';
    $subject_id = $_POST['subject_id'] ?? 0;

    if ($type === 'subject' && !$subject_id) {
         throw new Exception("Subject ID is required for subject import");
    }

    foreach ($data as $row) {
        // Validation
        if (empty($row['qr_code']) || empty($row['date'])) continue;

        if ($type === 'subject') {
            // SUBJECT ATTENDANCE
            
            // Check existence
            $stmtCheck = $pdo->prepare("SELECT id FROM subject_attendance WHERE subject_id = ? AND qr_code = ? AND date = ? AND time = ?");
            $stmtCheck->execute([$subject_id, $row['qr_code'], $row['date'], $row['time']]);
            
            if ($stmtCheck->fetch()) {
                $skipped++;
                continue;
            }

            // Insert
            $stmtInsert = $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, time, status) VALUES (?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $subject_id,
                $row['qr_code'],
                $row['date'],
                $row['time'],
                $row['status'] ?? 'present'
            ]);
            $imported++;

        } elseif ($type === 'auto') {
            // AUTO-ASSIGN (Schedule Based)
            
            $dayOfWeek = date('l', strtotime($row['date'])); // e.g., 'Monday'
            // row['time'] is 'HH:mm:ss' or 'HH:mm'
            
            // Find Subject matching Schedule
            // Schedule: day_of_week, start_time, end_time
            $stmtSchedule = $pdo->prepare("
                SELECT subject_id FROM schedules 
                WHERE day_of_week = ? 
                AND ? >= start_time 
                AND ? < end_time 
                LIMIT 1
            ");
            $stmtSchedule->execute([$dayOfWeek, $row['time'], $row['time']]);
            $schedule = $stmtSchedule->fetch(PDO::FETCH_ASSOC);
            
            if ($schedule) {
                $targetSubjectId = $schedule['subject_id'];
                
                // Check Duplicate
                $stmtCheck = $pdo->prepare("SELECT id FROM subject_attendance WHERE subject_id = ? AND qr_code = ? AND date = ? AND time = ?");
                $stmtCheck->execute([$targetSubjectId, $row['qr_code'], $row['date'], $row['time']]);
                
                if ($stmtCheck->fetch()) {
                    $skipped++;
                } else {
                    // Insert
                    $stmtInsert = $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, time, status) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsert->execute([
                        $targetSubjectId,
                        $row['qr_code'],
                        $row['date'],
                        $row['time'],
                        $row['status'] ?? 'present'
                    ]);
                    $imported++;
                }
            } else {
                // No schedule found for this time -> Unassigned
                $skipped++; 
            }

        } else {
            // DAILY ATTENDANCE (Original Logic)
            
            $stmtCheck = $pdo->prepare("SELECT id FROM attendance WHERE qr_code = ? AND date = ? AND time = ?");
            $stmtCheck->execute([$row['qr_code'], $row['date'], $row['time']]);
            
            if ($stmtCheck->fetch()) {
                $skipped++;
                continue;
            }

            $session = $row['session'] ?? null;
            if(!$session) {
                 $hour = (int)explode(':', $row['time'])[0]; 
                 $session = ($hour < 12) ? 'morning' : 'afternoon'; 
            }

            $stmtInsert = $pdo->prepare("INSERT INTO attendance (qr_code, date, time, status, session) VALUES (?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $row['qr_code'],
                $row['date'],
                $row['time'],
                $row['status'] ?? 'present',
                $session
            ]);
            $imported++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Imported: $imported records. Skipped: $skipped duplicates."
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

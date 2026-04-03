<?php
// api/subject_process.php
date_default_timezone_set('Asia/Manila');
require '../includes/db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    // 1. Add Subject
    if ($action === 'add_subject') {
        $name = trim($_POST['name'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        
        if (empty($name) || empty($semester)) {
            echo json_encode(['status' => 'error', 'message' => 'Name and Semester are required.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO subjects (name, semester) VALUES (?, ?)");
        $stmt->execute([$name, $semester]);
        
        echo json_encode(['status' => 'success', 'message' => 'Subject added.', 'id' => $pdo->lastInsertId()]);

    // 2. Get Subjects
    } elseif ($action === 'get_subjects') {
        $stmt = $pdo->query("SELECT * FROM subjects ORDER BY semester DESC, name ASC");
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by Semester
        $grouped = [];
        foreach ($subjects as $s) {
            $grouped[$s['semester']][] = $s;
        }
        
        echo json_encode(['status' => 'success', 'data' => $grouped]);

    // 3. Record Subject Attendance
    } elseif ($action === 'record_attendance') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $qr_code = $_POST['qr_code'] ?? '';
        $status = $_POST['status'] ?? 'present';
        $date = date('Y-m-d');

        // Check if exists today
        $stmt = $pdo->prepare("SELECT id FROM subject_attendance WHERE subject_id = ? AND qr_code = ? AND date = ?");
        $stmt->execute([$subject_id, $qr_code, $date]);
        $existing = $stmt->fetch();

        $timeNow = date('H:i:s');

        // Insert/Update
    try {
        if ($existing) {
            // Update existing record
            $update = $pdo->prepare("UPDATE subject_attendance SET status = ?, time = ?, recorded_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$status, $timeNow, $existing['id']]);
            echo json_encode(['status' => 'success', 'message' => 'Updated: ' . ucfirst($status)]);
        } else {
            // Insert new record
            $insert = $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, time, status) VALUES (?, ?, ?, ?, ?)");
            $insert->execute([$subject_id, $qr_code, $date, $timeNow, $status]);
            echo json_encode(['status' => 'success', 'message' => 'Recorded: ' . ucfirst($status)]);
        }
    } catch (PDOException $e) {
        // Fallback for missing 'time' column (if migration failed slightly)
        if (strpos($e->getMessage(), 'no such column: time') !== false) {
             if ($existing) {
                $pdo->prepare("UPDATE subject_attendance SET status = ?, recorded_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$status, $existing['id']]);
             } else {
                $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, status) VALUES (?, ?, ?, ?)")->execute([$subject_id, $qr_code, $date, $status]);
             }
             echo json_encode(['status' => 'success', 'message' => 'Recorded (No Time Sync)']);
        } else {
             http_response_code(500);
             echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
        }
    }

    // 4. Delete Subject
    } elseif ($action === 'delete_subject') {
        $id = $_POST['id'] ?? 0;
        $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Subject deleted.']);

    // 5. Update Subject
    } elseif ($action === 'update_subject') {
        $id = $_POST['id'] ?? 0;
        $name = trim($_POST['name'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        
        if (empty($name) || empty($semester)) { echo json_encode(['status' => 'error', 'message' => 'Invalid data.']); exit; }

        $pdo->prepare("UPDATE subjects SET name = ?, semester = ? WHERE id = ?")->execute([$name, $semester, $id]);
        echo json_encode(['status' => 'success', 'message' => 'Subject updated.']);
        
    // 6. Get Realtime Attendance (For UI Highlight)
    } elseif ($action === 'get_subject_attendance') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $date = date('Y-m-d');
        
        $stmt = $pdo->prepare("SELECT qr_code, status FROM subject_attendance WHERE subject_id = ? AND date = ?");
        $stmt->execute([$subject_id, $date]);
        $logs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // returns [qr => status]
        
        echo json_encode(['status' => 'success', 'data' => $logs]);

    // 7. Mark All Present
    } elseif ($action === 'mark_all') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $date = date('Y-m-d');
        
        // Fetch all users
        $allUsers = $pdo->query("SELECT qr_code FROM users")->fetchAll(PDO::FETCH_COLUMN);
        
        // Fetch already marked
        $marked = $pdo->prepare("SELECT qr_code FROM subject_attendance WHERE subject_id = ? AND date = ?");
        $marked->execute([$subject_id, $date]);
        $existingQRs = $marked->fetchAll(PDO::FETCH_COLUMN);
        
        $toInsert = array_diff($allUsers, $existingQRs);
        
        if (!empty($toInsert)) {
            $sql = "INSERT INTO subject_attendance (subject_id, qr_code, date, status) VALUES ";
            $vals = [];
            foreach ($toInsert as $qr) {
                $vals[] = "($subject_id, '$qr', '$date', 'present')";
            }
            $sql .= implode(", ", $vals);
            $pdo->exec($sql);
        }
        
        echo json_encode(['status' => 'success', 'message' => 'All remaining students marked Present.']);

    // 8. Auto-Detect Current Subject
    } elseif ($action === 'get_current_subject') {
        $day = date('l'); // Monday, Tuesday...
        $time = date('H:i'); // 14:30
        
        // Find schedule matching day and time
        // Checking if CurrentTime is >= StartTime AND CurrentTime < EndTime
        $stmt = $pdo->prepare("
            SELECT s.* 
            FROM schedules sch
            JOIN subjects s ON sch.subject_id = s.id
            WHERE sch.day_of_week = ? 
            AND ? >= sch.start_time 
            AND ? < sch.end_time
            LIMIT 1
        ");
        $stmt->execute([$day, $time, $time]);
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subject) {
            echo json_encode(['status' => 'success', 'data' => $subject]);
        } else {
            echo json_encode(['status' => 'not_found', 'message' => 'No class scheduled right now.']);
        }

    // 9. Get Schedules for a Subject
    } elseif ($action === 'get_schedules') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM schedules WHERE subject_id = ? ORDER BY day_of_week, start_time");
        $stmt->execute([$subject_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    // 10. Add Schedule
    } elseif ($action === 'add_schedule') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $day = $_POST['day'] ?? '';
        $start = $_POST['start'] ?? '';
        $end = $_POST['end'] ?? '';

        if (!$subject_id || !$day || !$start || !$end) {
            echo json_encode(['status' => 'error', 'message' => 'All fields required']);
            exit;
        }

        $pdo->prepare("INSERT INTO schedules (subject_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)")
            ->execute([$subject_id, $day, $start, $end]);
        
        echo json_encode(['status' => 'success', 'message' => 'Schedule added']);

    // 11. Delete Schedule
    } elseif ($action === 'delete_schedule') {
        $id = $_POST['id'] ?? 0;
        $pdo->prepare("DELETE FROM schedules WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Schedule removed']);

    } elseif ($action === 'delete_schedule') {
        $id = $_POST['id'] ?? 0;
        $pdo->prepare("DELETE FROM schedules WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Schedule removed']);

    // 12. Reset Subject Attendance
    } elseif ($action === 'reset_attendance') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $date = date('Y-m-d');
        
        $pdo->prepare("DELETE FROM subject_attendance WHERE subject_id = ? AND date = ?")->execute([$subject_id, $date]);
        
        echo json_encode(['status' => 'success', 'message' => 'Attendance reset for today.']);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

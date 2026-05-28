<?php
// api/subject_process.php
date_default_timezone_set('Asia/Manila');
require '../includes/db.php';
require_once '../includes/telegram.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    // 1. Add Subject
    if ($action === 'add_subject') {
        $category = trim($_POST['category'] ?? 'subject');
        $name = trim($_POST['name'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $school_year = trim($_POST['school_year'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $room = trim($_POST['room'] ?? '');
        $lecturer = trim($_POST['lecturer'] ?? '');
        
        if (empty($name) || empty($semester)) {
            echo json_encode(['status' => 'error', 'message' => 'Name and Semester are required.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO subjects (name, semester, school_year, category, code, room, lecturer) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $semester, $school_year, $category, $code, $room, $lecturer]);
        
        echo json_encode(['status' => 'success', 'message' => ucfirst($category) . ' added.', 'id' => $pdo->lastInsertId()]);

    // 2. Get Subjects
    } elseif ($action === 'get_subjects') {
        $stmt = $pdo->query("SELECT * FROM subjects ORDER BY school_year DESC, semester DESC, name ASC");
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by School Year -> Semester
        $grouped = [];
        foreach ($subjects as $s) {
            $sy = $s['school_year'] ?: 'No School Year';
            $sem = $s['semester'] ?: 'No Semester';
            $grouped[$sy][$sem][] = $s;
        }
        
        echo json_encode(['status' => 'success', 'data' => $grouped]);

    // 3. Record Subject Attendance
    } elseif ($action === 'record_attendance') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $qr_code = $_POST['qr_code'] ?? '';
        $status = $_POST['status'] ?? 'present';
        $date = $_POST['custom_date'] ?? date('Y-m-d');

        // ENROLLMENT CHECK (Modified)
        // 1. Get User Type
        $uStmt = $pdo->prepare("SELECT student_type FROM users WHERE qr_code = ?");
        $uStmt->execute([$qr_code]);
        $uType = $uStmt->fetchColumn() ?: 'regular';

        // 2. If Irregular, Check Enrollment
        if ($uType === 'irregular') {
             $check = $pdo->prepare("SELECT 1 FROM student_subjects WHERE qr_code = ? AND subject_id = ?");
             $check->execute([$qr_code, $subject_id]);
             if (!$check->fetch()) {
                 echo json_encode(['status' => 'error', 'message' => 'Student NOT enrolled in this subject.']);
                 exit;
             }
        }

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
        // Accept both 'id' and 'subject_id' for compatibility
        $id = $_POST['id'] ?? $_POST['subject_id'] ?? 0;
        $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Subject deleted.']);

    // 4b. Get Active-SY Subjects (for Manual Entry dropdown)
    } elseif ($action === 'get_subjects_active') {
        // Fetch active school year from settings
        $syStmt = $pdo->query("SELECT active_school_year FROM settings LIMIT 1");
        $activeSY = $syStmt ? ($syStmt->fetchColumn() ?: '') : '';

        if ($activeSY) {
            $stmt = $pdo->prepare("SELECT * FROM subjects WHERE school_year = ? ORDER BY semester DESC, name ASC");
            $stmt->execute([$activeSY]);
        } else {
            // fallback: return all if no active SY set
            $stmt = $pdo->query("SELECT * FROM subjects ORDER BY school_year DESC, semester DESC, name ASC");
        }
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by Semester
        $grouped = [];
        foreach ($subjects as $s) {
            $sem = $s['semester'] ?: 'No Semester';
            $grouped[$sem][] = $s;
        }
        echo json_encode(['status' => 'success', 'data' => $grouped, 'active_sy' => $activeSY]);

    // 5. Update Subject
    } elseif ($action === 'update_subject') {
        $id = $_POST['id'] ?? $_POST['subject_id'] ?? 0;
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Subject ID is required.']);
            exit;
        }

        // Fetch existing subject details to handle partial updates cleanly
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['status' => 'error', 'message' => 'Subject not found.']);
            exit;
        }

        $name = isset($_POST['name']) ? trim($_POST['name']) : $existing['name'];
        $semester = isset($_POST['semester']) ? trim($_POST['semester']) : $existing['semester'];
        $school_year = isset($_POST['school_year']) ? trim($_POST['school_year']) : $existing['school_year'];
        $category = isset($_POST['category']) ? trim($_POST['category']) : $existing['category'];
        $code = isset($_POST['code']) ? trim($_POST['code']) : $existing['code'];
        $room = isset($_POST['room']) ? trim($_POST['room']) : $existing['room'];
        $lecturer = isset($_POST['lecturer']) ? trim($_POST['lecturer']) : $existing['lecturer'];
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : (int)$existing['is_active'];
        
        if (empty($name) || empty($semester)) { 
            echo json_encode(['status' => 'error', 'message' => 'Name and Semester are required.']); 
            exit; 
        }

        $pdo->prepare("UPDATE subjects SET name = ?, semester = ?, school_year = ?, category = ?, code = ?, room = ?, lecturer = ?, is_active = ? WHERE id = ?")->execute([$name, $semester, $school_year, $category, $code, $room, $lecturer, $is_active, $id]);
        echo json_encode(['status' => 'success', 'message' => ucfirst($category) . ' updated.']);
        
    // 6. Get Realtime Attendance (For UI Highlight)
    } elseif ($action === 'get_subject_attendance') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $date = $_POST['custom_date'] ?? date('Y-m-d');
        
        $stmt = $pdo->prepare("SELECT qr_code, status FROM subject_attendance WHERE subject_id = ? AND date = ?");
        $stmt->execute([$subject_id, $date]);
        $logs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // returns [qr => status]

        // Check notification status
        $nStmt = $pdo->prepare("SELECT 1 FROM notified_contexts WHERE subject_id = ? AND date = ?");
        $nStmt->execute([$subject_id, $date]);
        $is_notified = (bool)$nStmt->fetch();
        
        echo json_encode(['status' => 'success', 'data' => $logs, 'is_notified' => $is_notified]);

    // 7. Mark All Present
    } elseif ($action === 'mark_all') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $date = date('Y-m-d');
        
        // Fetch Eligible Users: Regular (All) + Irregular (Enrolled)
        $q = "SELECT u.qr_code FROM users u 
              LEFT JOIN student_subjects ss ON u.qr_code = ss.qr_code 
              WHERE u.deleted_at IS NULL AND (
                  (u.student_type IS NULL OR u.student_type = 'regular') 
                  OR (u.student_type = 'irregular' AND ss.subject_id = ?)
              )";
        
        $stmtUsers = $pdo->prepare($q);
        $stmtUsers->execute([$subject_id]);
        $eligibleUsers = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);
        
        // Fetch already marked
        $marked = $pdo->prepare("SELECT qr_code FROM subject_attendance WHERE subject_id = ? AND date = ?");
        $marked->execute([$subject_id, $date]);
        $existingQRs = $marked->fetchAll(PDO::FETCH_COLUMN);
        
        $toInsert = array_diff($eligibleUsers, $existingQRs);
        
        if (!empty($toInsert)) {
            $insertStmt = $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, status) VALUES (?, ?, ?, 'present')");
            foreach ($toInsert as $qr) {
                $insertStmt->execute([$subject_id, $qr, $date]);
            }
        }
        
        echo json_encode(['status' => 'success', 'message' => 'All remaining eligible students marked Present.']);

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


    // 12. Reset Subject Attendance
    } elseif ($action === 'reset_attendance') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $date = date('Y-m-d');
        
        $pdo->prepare("DELETE FROM subject_attendance WHERE subject_id = ? AND date = ?")->execute([$subject_id, $date]);
        
        echo json_encode(['status' => 'success', 'message' => 'Attendance reset for today.']);

    // 13. Cancel Class
    } elseif ($action === 'cancel_class') {
        $subject_id = $_POST['subject_id'] ?? 0;
        $date = $_POST['date'] ?? date('Y-m-d');
        
        if (!$subject_id) {
            echo json_encode(['status' => 'error', 'message' => 'Subject ID required']);
            exit;
        }

        // Fetch subject details
        $stmt = $pdo->prepare("SELECT name, category FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subject) {
            echo json_encode(['status' => 'error', 'message' => 'Subject not found']);
            exit;
        }

        $label = ($subject['category'] === 'event') ? 'Event' : 'Subject';
        $formattedDate = date('M j, Y', strtotime($date));
        $subjectName = strtoupper($subject['name']);
        
        $msg = "<b>$label:</b> $subjectName\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━┳═─\n\n";
        $msg .= "Date: $formattedDate\n\n";
        $msg .= "<b>STATUS: NO CLASS</b>\n\n";
        $msg .= "<i>Automated Message by System Admin</i>";

        send_telegram_notification($msg);

        // 1. Fetch Eligible Students (Regular + Enrolled Irregular)
        $q = "SELECT u.qr_code FROM users u 
              LEFT JOIN student_subjects ss ON u.qr_code = ss.qr_code 
              WHERE u.deleted_at IS NULL AND (
                  (u.student_type IS NULL OR u.student_type = 'regular') 
                  OR (u.student_type = 'irregular' AND ss.subject_id = ?)
              )";
        
        $stmtUsers = $pdo->prepare($q);
        $stmtUsers->execute([$subject_id]);
        $eligibleUsers = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($eligibleUsers)) {
             // 2. Clear existing records for this subject/date to avoid duplicates
             $pdo->prepare("DELETE FROM subject_attendance WHERE subject_id = ? AND date = ?")->execute([$subject_id, $date]);
             
             // 3. Mark everyone as "no-class" using prepared statement
             $insertStmt = $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, status) VALUES (?, ?, ?, 'no-class')");
             foreach ($eligibleUsers as $qr) {
                 $insertStmt->execute([$subject_id, $qr, $date]);
             }
        }

        // 4. Lock the context to prevent further attendance recording for this cancelled class
        $pdo->prepare("INSERT OR IGNORE INTO notified_contexts (subject_id, date) VALUES (?, ?)")->execute([$subject_id, $date]);

        echo json_encode(['status' => 'success', 'message' => 'Cancellation broadcast sent and records updated to NO CLASS.']);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

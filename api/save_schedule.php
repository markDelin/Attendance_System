<?php
// api/save_schedule.php
header('Content-Type: application/json');
require_once '../includes/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid input');
    }

    $pdo->beginTransaction();

    // 1. Handle Metadata Updates (Header/Footer info)
    if (isset($input['meta'])) {
        foreach ($input['meta'] as $key => $value) {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO schedule_settings (key, value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
    }

    // 2. Handle Row Updates (Subject + Schedule)
    if (isset($input['rows'])) {
        foreach ($input['rows'] as $row) {
            $id = $row['id'];
            $name = $row['name'];
            $code = $row['code'];
            $room = $row['room'];
            $lecturer = $row['lecturer'];
            $dayStr = $row['days']; // e.g. "Monday & Tuesday"
            $timeStr = $row['time']; // e.g. "07:30 - 09:00" or "7:30 AM - 9:00 AM"

            // A. Update Subject Details
            $stmt = $pdo->prepare("UPDATE subjects SET name = ?, code = ?, room = ?, lecturer = ? WHERE id = ?");
            $stmt->execute([$name, $code, $room, $lecturer, $id]);

            // B. Update Schedule (Days & Time)
            // Parse Days
            $days = [];
            $parts = explode('&', $dayStr);
            foreach ($parts as $p) {
                $d = trim($p);
                // Simple mapping/normalization if needed, but assuming user types full names or we match partials
                // Let's try to map generic input to full day names
                if (stripos($d, 'mon') !== false) $days[] = 'Monday';
                elseif (stripos($d, 'tue') !== false) $days[] = 'Tuesday';
                elseif (stripos($d, 'wed') !== false) $days[] = 'Wednesday';
                elseif (stripos($d, 'thu') !== false) $days[] = 'Thursday';
                elseif (stripos($d, 'fri') !== false) $days[] = 'Friday';
                elseif (stripos($d, 'sat') !== false) $days[] = 'Saturday';
                elseif (stripos($d, 'sun') !== false) $days[] = 'Sunday';
                else $days[] = $d; // Fallback
            }

            // Parse Time
            // Expected format: "Start - End"
            $timeParts = explode('-', $timeStr);
            if (count($timeParts) == 2) {
                $startRaw = trim($timeParts[0]);
                $endRaw = trim($timeParts[1]);
                
                // Convert to 24h format for storage
                $startTime = date('H:i', strtotime($startRaw));
                $endTime = date('H:i', strtotime($endRaw));

                // C. Replace Schedules
                // First, remove existing schedules for this subject
                // NOTE: This assumes all schedules for this subject share the same time/days block being edited.
                // If a subject has multiple distinct time blocks (e.g. Mon 9-10 AND Wed 1-2), 
                // this simple UI/Logic might overwrite *all* of them with the single edited line.
                // For this specific iteration, we assume 1 subject = 1 uniform schedule block as per the UI design.
                $pdo->prepare("DELETE FROM schedules WHERE subject_id = ?")->execute([$id]);

                foreach ($days as $day) {
                    $stmt = $pdo->prepare("INSERT INTO schedules (subject_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id, $day, $startTime, $endTime]);
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

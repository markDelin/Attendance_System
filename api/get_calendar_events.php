<?php
// api/get_calendar_events.php
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json');
require_once '../includes/db.php';

try {
    $events = [];

    // Get dates with attendance
    $sql = "SELECT date, 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
            FROM attendance 
            GROUP BY date";
            
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $count = $row['present_count'] + $row['late_count'];
        
        // Event 1: Presence Count (Green)
        if ($count > 0) {
            $events[] = [
                'title' => "$count Present",
                'start' => $row['date'],
                'backgroundColor' => '#dcfce7',
                'borderColor' => '#dcfce7',
                'textColor' => '#166534',
                'extendedProps' => [
                   'date' => $row['date'] 
                ]
            ];
        }
    }

    echo json_encode($events);

} catch (PDOException $e) {
    echo json_encode([]);
}
?>

<?php
// api/dashboard_stats.php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";

$today = date('Y-m-d');

try {
    // 1. Total Students (exclude soft-deleted)
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();

    // 2. Today's Total Attendance (Modern Morning)
    $todayMorning = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE date = ? GROUP BY status");
    $todayMorning->execute([$today]);
    $morningStats = $todayMorning->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $dayOfWeek = date('l');
    $activeSubjects = $pdo->prepare("SELECT COUNT(DISTINCT subject_id) FROM schedules WHERE day_of_week = ?");
    $activeSubjects->execute([$dayOfWeek]);
    $subjectCount = $activeSubjects->fetchColumn();

    // 4. Quick Recent Activity (Last 5 marks)
    // Combine from both tables? Or just "attendance" for morning.
    // Let's do a UNION to see the latest 5 regardless of type.
    $recentActivity = $pdo->prepare("
        SELECT u.name, a.status, a.time, 'Daily' as type 
         FROM attendance a 
         JOIN users u ON a.qr_code = u.qr_code 
         WHERE a.date = ?
        UNION ALL
        SELECT u.name, sa.status, sa.time, s.name as type 
         FROM subject_attendance sa 
         JOIN users u ON sa.qr_code = u.qr_code 
         JOIN subjects s ON sa.subject_id = s.id
         WHERE sa.date = ?
        ORDER BY time DESC 
        LIMIT 5
    ");
    $recentActivity->execute([$today, $today]);
    $recentActivity = $recentActivity->fetchAll(PDO::FETCH_ASSOC);

    // Format times for recent activity
    foreach ($recentActivity as &$act) {
        $act['time'] = date('h:i A', strtotime($act['time']));
    }

    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total_students' => (int)$totalStudents,
            'active_subjects' => (int)$subjectCount,
            'morning' => [
                'present' => (int)($morningStats['present'] ?? 0),
                'late' => (int)($morningStats['late'] ?? 0),
                'absent' => (int)($morningStats['absent'] ?? 0),
            ]
        ],
        'recent' => $recentActivity
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

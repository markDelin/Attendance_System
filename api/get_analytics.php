<?php
// api/get_analytics.php - Attendance Analytics for Dashboard Charts
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";

try {
    // 1. Last 7 Days Attendance Trend
    $days = [];
    $presentData = [];
    $lateData = [];
    $absentData = [];

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $label = date('D', strtotime($date));
        $days[] = $label;

        // Morning/Daily Attendance
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE date = ? GROUP BY status");
        $stmt->execute([$date]);
        $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $presentData[] = (int)($stats['present'] ?? 0);
        $lateData[] = (int)($stats['late'] ?? 0);
        $absentData[] = (int)($stats['absent'] ?? 0);
    }

    // 2. Top Absentees (Most absent in the last 30 days)
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $topAbsentees = $pdo->prepare("
        SELECT u.name, COUNT(*) as absences 
        FROM attendance a
        JOIN users u ON a.qr_code = u.qr_code
        WHERE a.status = 'absent' AND a.date >= ?
        GROUP BY a.qr_code
        ORDER BY absences DESC
        LIMIT 5
    ");
    $topAbsentees->execute([$thirtyDaysAgo]);
    $absenteesList = $topAbsentees->fetchAll(PDO::FETCH_ASSOC);

    // 3. Subject-specific attendance rates (Overall)
    $subjectPerformance = $pdo->query("
        SELECT s.name, 
               ROUND(SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as rate
        FROM subject_attendance sa
        JOIN subjects s ON sa.subject_id = s.id
        GROUP BY sa.subject_id
        ORDER BY rate ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'trend' => [
            'labels' => $days,
            'present' => $presentData,
            'late' => $lateData,
            'absent' => $absentData
        ],
        'at_risk' => $absenteesList,
        'subject_performance' => $subjectPerformance
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

<?php
// api/fetch_stats.php
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json');

// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once '../includes/db.php';

try {
    // 1. Today's Stats
    $today = date('Y-m-d');
    
    // Get distinct status counts
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE date = ? GROUP BY status");
    $stmt->execute([$today]);
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['present' => 10, 'late' => 2]

    $present = $stats['present'] ?? 0;
    $late = $stats['late'] ?? 0;
    $absent = $stats['absent'] ?? 0; 
    
    // Total students (for percentage context)
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE student_type = 'regular' AND deleted_at IS NULL")->fetchColumn();
    
    // 2. Weekly Trend (Last 7 Days)
    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND status IN ('present', 'late')");
        $stmtCount->execute([$date]);
        $count = $stmtCount->fetchColumn();
        
        $trend[] = [
            'date' => date('M d', strtotime($date)), 
            'count' => (int)$count
        ];
    }
    
    // 3. Recent Scans (Top 5)
    $stmt = $pdo->prepare("SELECT u.name, a.time, a.status 
                          FROM attendance a 
                          JOIN users u ON a.qr_code = u.qr_code 
                          WHERE a.date = ? 
                          ORDER BY a.time DESC 
                          LIMIT 5");
    $stmt->execute([$today]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'today' => [
            'present' => $present,
            'late' => $late,
            'absent' => $absent, 
            'total_students' => $totalStudents,
            'attendance_rate' => $totalStudents > 0 ? round((($present + $late) / $totalStudents) * 100, 1) : 0
        ],
        'weekly' => $trend,
        'recent' => $recent
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

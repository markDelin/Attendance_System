<?php
// api/export_data.php
require_once '../includes/db.php';

$format = $_GET['format'] ?? 'json';

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['QR Code', 'Student Name', 'Date', 'Time', 'Status', 'Session', 'Recorded At']);

    // Fetch with names
    $sql = "SELECT a.qr_code, u.name, a.date, a.time, a.status, a.session, a.recorded_at 
            FROM attendance a 
            LEFT JOIN users u ON a.qr_code = u.qr_code 
            ORDER BY a.date DESC, a.time DESC";
            
    $stmt = $pdo->query($sql);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['qr_code'],
            $row['name'] ?? 'Unknown',
            $row['date'],
            $row['time'],
            $row['status'],
            $row['session'],
            $row['recorded_at']
        ]);
    }
    fclose($output);
    exit;
}

// JSON Default
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="attendance_export_' . date('Y-m-d') . '.json"');

try {
    $stmt = $pdo->query("SELECT * FROM attendance ORDER BY date DESC, time DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data, JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
// api/search_students.php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

try {
    $search = "%$query%";
    $stmt = $pdo->prepare("
        SELECT qr_code, name 
        FROM users 
        WHERE (name LIKE ? OR qr_code LIKE ?) AND deleted_at IS NULL
        ORDER BY name ASC 
        LIMIT 10
    ");
    $stmt->execute([$search, $search]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $results]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

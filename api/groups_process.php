<?php
// api/groups_process.php
date_default_timezone_set('Asia/Manila');
require '../includes/db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    if ($action === 'save_group') {
        $name = $_POST['name'] ?? 'Untitled Group';
        $members = $_POST['members'] ?? '[]'; // Expecting JSON string
        
        $stmt = $pdo->prepare("INSERT INTO saved_groups (name, members) VALUES (?, ?)");
        $stmt->execute([$name, $members]);
        
        echo json_encode(['status' => 'success', 'message' => 'Group saved successfully.']);

    } elseif ($action === 'load_groups') {
        $stmt = $pdo->query("SELECT * FROM saved_groups ORDER BY created_at DESC");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $groups]);

    } elseif ($action === 'delete_group') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM saved_groups WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Group deleted.']);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

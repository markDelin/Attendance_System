<?php
require_once __DIR__ . '/../includes/db.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }

$type = $_GET['type'] ?? 'all';

switch ($type) {
    case 'students':
        $data = $pdo->query("SELECT qr_code, name, course, year_level FROM users WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        header("Content-Disposition: attachment; filename=students.json");
        echo json_encode(["students" => $data], JSON_PRETTY_PRINT);
        break;

    case 'subjects':
        $data = $pdo->query("SELECT id, name, code, room, lecturer, semester, category FROM subjects WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        header("Content-Disposition: attachment; filename=subjects.json");
        echo json_encode(["subjects" => $data], JSON_PRETTY_PRINT);
        break;

    case 'schedules':
        $data = $pdo->query("SELECT sc.id, sc.subject_id, s.name as subject_name, sc.day_of_week, sc.start_time, sc.end_time FROM schedules sc JOIN subjects s ON s.id = sc.subject_id WHERE s.is_active = 1 ORDER BY sc.start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
        header("Content-Disposition: attachment; filename=schedules.json");
        echo json_encode(["schedules" => $data], JSON_PRETTY_PRINT);
        break;

    default: // all
        $students = $pdo->query("SELECT qr_code, name, course, year_level FROM users WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $subjects = $pdo->query("SELECT id, name, code, room, lecturer, semester, category FROM subjects WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $schedules = $pdo->query("SELECT sc.id, sc.subject_id, s.name as subject_name, sc.day_of_week, sc.start_time, sc.end_time FROM schedules sc JOIN subjects s ON s.id = sc.subject_id WHERE s.is_active = 1 ORDER BY sc.start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
        header("Content-Disposition: attachment; filename=attendance-data.json");
        echo json_encode(["students" => $students, "subjects" => $subjects, "schedules" => $schedules], JSON_PRETTY_PRINT);
        break;
}

<?php
error_reporting(0);
ini_set('display_errors', '0');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }

$dbPath = __DIR__ . '/../database/attendance.db';

if (!file_exists($dbPath)) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Database not found"]);
    exit;
}

$size = filesize($dbPath);
header("Content-Type: application/x-sqlite3");
header("Content-Length: " . $size);
header("Content-Disposition: attachment; filename=attendance.db");
header("Cache-Control: no-cache, must-revalidate");
readfile($dbPath);

<?php
// scratch/test_exports.php
$root = dirname(__DIR__);
require_once $root . "/includes/db.php";

echo "Testing api/export.php (Daily Records)...\n";
$_SERVER["REQUEST_METHOD"] = "POST";
$_POST["start_date"] = date('Y-m-d');
$_POST["format"] = "csv";

ob_start();
// Since api/export.php uses relative require, we might need to change dir or fix paths there.
// But for a quick test, let's just mock what it needs.
include $root . "/api/export.php";
$output = ob_get_clean();

if (strpos($output, "Student ID") !== false && strpos($output, "Section") !== false) {
    echo "SUCCESS: CSV header contains Student ID and Section.\n";
} else {
    echo "FAILURE: CSV header missing Student ID or Section.\n";
    echo "Output snippet: " . substr($output, 0, 100) . "\n";
}

echo "\nTesting api/export_subject.php (Subject Records)...\n";
$subj = $pdo->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();
if ($subj) {
    $_GET["subject_id"] = $subj;
    $_GET["format"] = "html";
    $_GET["start"] = date('Y-m-d');
    
    ob_start();
    include $root . "/api/export_subject.php";
    $output = ob_get_clean();
    
    if (strpos($output, "stat-card") !== false && strpos($output, "matrix-table") !== false) {
        echo "SUCCESS: HTML output contains summary cards and matrix table.\n";
    } else {
        echo "FAILURE: HTML output missing summary cards or matrix table.\n";
        echo "Output snippet: " . substr($output, 0, 500) . "\n";
    }
} else {
    echo "SKIPPED: No subjects found in DB.\n";
}
?>

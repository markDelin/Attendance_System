<?php
// api/download_dossier.php - PDF Download Bridge
require_once '../includes/db.php';

$qr = $_GET['qr'] ?? '';
if (empty($qr)) die("Error: No student ID provided.");

// Determine correct python command (python3 in Termux/Linux)
$python = (PHP_OS_FAMILY === 'Windows') ? 'python' : 'python3';

// Path to the generator script
$generator = realpath(__DIR__ . '/../bot/generate_report.py');

if (!$generator || !file_exists($generator)) {
    die("Error: PDF generator script not found. Please ensure bot/generate_report.py exists.");
}

$workingDir = dirname($generator);

// Execute the Python script
$output = [];
$return_var = 0;
// We pass the QR code as an argument. The script prints the filename it created.
$escapedDir = escapeshellarg($workingDir);
exec("cd $escapedDir && $python " . escapeshellarg($generator) . " " . escapeshellarg($qr), $output, $return_var);

if ($return_var !== 0) {
    die("Error generating PDF dossier. Status: $return_var. Check Python install.");
}

// The script should have printed the filename (e.g. dossier_12345.pdf)
$filename = trim(implode('', $output));
$filePath = $workingDir . DIRECTORY_SEPARATOR . $filename;

if (file_exists($filePath)) {
    // Serve the PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    
    // Cleanup the temp file
    unlink($filePath);
    exit;
} else {
    die("Error: Generated PDF file not found at $filePath. Output: " . implode("\n", $output));
}
?>

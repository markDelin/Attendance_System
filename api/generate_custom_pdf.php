<?php
// api/generate_custom_pdf.php - Custom PDF Generation Bridge
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Invalid request method.");

$md_text = $_POST['markdown'] ?? '';
if (empty($md_text)) die("Error: No content provided.");

// Determine correct python command (python3 in Termux/Linux)
$python = (PHP_OS_FAMILY === 'Windows') ? 'python' : 'python3';

// Path to the generator script
$generator = realpath(__DIR__ . '/../generate_report.py');
$workingDir = dirname($generator);

// Create a temporary file for the markdown content to avoid command-line length limits
$tempMdPath = $workingDir . DIRECTORY_SEPARATOR . 'temp_' . uniqid() . '.md';
if (file_put_contents($tempMdPath, $md_text) === false) {
    die("Error: Failed to write temporary markdown file to $tempMdPath. Check folder permissions.");
}

// Execute the Python script with --raw flag pointing to the temp file
$output = [];
$return_var = 0;
// Capture stderr into the output array using 2>&1
$command = "cd " . escapeshellarg($workingDir) . " && $python " . escapeshellarg($generator) . " --raw " . escapeshellarg($tempMdPath) . " 2>&1";
exec($command, $output, $return_var);

// Cleanup the temp markdown file
if (file_exists($tempMdPath)) unlink($tempMdPath);

if ($return_var !== 0) {
    $errorMsg = implode("\n", $output);
    die("Error generating PDF. Status: $return_var.\n\nPython Output:\n" . (empty($errorMsg) ? "[No output from Python]" : $errorMsg));
}

// The script should have printed the filename (e.g. dossier_custom_12345.pdf) as the LAST line
$filename = "";
foreach (array_reverse($output) as $line) {
    if (preg_match('/^dossier_.*\.pdf$/', trim($line))) {
        $filename = trim($line);
        break;
    }
}

if (empty($filename)) {
    die("Error: Could not determine PDF filename from Python output. Output: " . implode("\n", $output));
}

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

<?php
// api/upload_image.php - Handle Image Uploads
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'No image uploaded']);
    exit;
}

$file = $_FILES['image'];

// Handle PHP Upload Errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'Upload failed: ';
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
            $errorMsg .= 'The image is too large. Please upload an image smaller than ' . ini_get('upload_max_filesize') . '.';
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $errorMsg .= 'The image exceeds the MAX_FILE_SIZE directive in the HTML form.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMsg .= 'The image was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMsg .= 'No image was uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMsg .= 'Missing a temporary folder.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMsg .= 'Failed to write image to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMsg .= 'A PHP extension stopped the image upload.';
            break;
        default:
            $errorMsg .= 'Unknown upload error.';
            break;
    }
    echo json_encode(['success' => false, 'error' => $errorMsg, 'error_code' => $file['error']]);
    exit;
}
$targetDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

// Create directory if not exists
if (!file_exists($targetDir)) {
    if (!mkdir($targetDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create uploads directory: ' . $targetDir]);
        exit;
    }
}

if (!is_writable($targetDir)) {
    echo json_encode(['success' => false, 'error' => 'Uploads directory is not writable: ' . $targetDir]);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array(strtolower($ext), $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed)]);
    exit;
}

// Validate MIME type (prevents disguised PHP files)
$mimeType = false;
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
} elseif (function_exists('mime_content_type')) {
    $mimeType = mime_content_type($file['tmp_name']);
} elseif (function_exists('getimagesize')) {
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo !== false) {
        $mimeType = $imageInfo['mime'];
    }
}
if (!$mimeType) {
    $extMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    $lowerExt = strtolower($ext);
    if (isset($extMap[$lowerExt])) {
        $mimeType = $extMap[$lowerExt];
    }
}
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($mimeType, $allowedMimes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file content. The file does not appear to be a valid image.']);
    exit;
}

// File size limit (5MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// Generate unique filename
$filename = uniqid('img_') . '.' . $ext;
$targetPath = $targetDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Return the relative path for storage in DB
    $relativePath = 'assets/uploads/' . $filename;
    echo json_encode(['success' => true, 'path' => $relativePath]);
} else {
    $error = error_get_last();
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to move uploaded file. Check directory permissions.',
        'details' => [
            'error_code' => $file['error'],
            'tmp_name' => $file['tmp_name'],
            'target' => $targetPath,
            'last_php_error' => $error ? $error['message'] : 'none'
        ]
    ]);
}
?>

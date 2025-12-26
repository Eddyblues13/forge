<?php
/**
 * Secure File Viewer
 * Serves uploaded ID documents securely
 */

require_once 'config/config.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die('Access denied');
}

// Get file path from query parameter
$file_path = $_GET['file'] ?? '';

if (empty($file_path)) {
    http_response_code(400);
    die('File parameter required');
}

// Sanitize file path - only allow files from uploads/ids/ directory
$file_path = str_replace('..', '', $file_path); // Remove directory traversal
$file_path = ltrim($file_path, '/'); // Remove leading slashes

// Ensure file is in uploads/ids/ directory
if (strpos($file_path, 'uploads/ids/') !== 0) {
    http_response_code(403);
    die('Invalid file path');
}

// Get full file path
$full_path = dirname(__DIR__) . '/' . $file_path;

// Check if file exists
if (!file_exists($full_path) || !is_file($full_path)) {
    http_response_code(404);
    die('File not found');
}

// Get file extension
$file_ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

// Set appropriate content type
$content_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf'
];

$content_type = $content_types[$file_ext] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($full_path));
header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
header('Cache-Control: private, max-age=3600');

// Output file
readfile($full_path);
exit;


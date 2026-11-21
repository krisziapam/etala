<?php
require_once __DIR__ . '/includes/v2.php';
require_once __DIR__ . '/includes/upload.php';
require_login();

$image = find_image((int) ($_GET['id'] ?? 0));
$user = current_user();
if ($image && !v2_can_report($user,(int)$image['report_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

$base  = realpath(UPLOAD_DIR);
$path  = $image ? realpath(UPLOAD_DIR . '/' . $image['file_path']) : false;

// The file must exist and be inside the uploads folder.
if ($path === false || $base === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('Photo not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
readfile($path);
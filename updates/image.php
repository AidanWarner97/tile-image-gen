<?php
declare(strict_types=1);

$filename = basename((string)($_GET['file'] ?? ''));
$path = __DIR__ . '/../posts/images/' . $filename;

if ($filename === '' || !is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
if (strpos($mime, 'image/') !== 0) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($path);
<?php
declare(strict_types=1);

require_once __DIR__ . '/generate-log.php';
require_once __DIR__ . '/includes/catalogue-storage.php';

header('X-Content-Type-Options: nosniff');

$path = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
$objectKey = ltrim($path, '/');
try {
    $objectKey = catalogue_storage_object_key($objectKey);
} catch (Throwable) {
    http_response_code(404);
    exit;
}

if (catalogue_storage_env('BUNNY_CDN_HOST') !== '') {
    header('Cache-Control: public, max-age=300');
    header('Location: ' . catalogue_storage_cdn_url($objectKey), true, 302);
    exit;
}

$localFile = catalogue_storage_local_file($objectKey);
if ($localFile === null) {
    http_response_code(404);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($localFile);
if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($localFile));
header('Cache-Control: public, max-age=86400');
readfile($localFile);

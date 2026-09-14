<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/feedback-data/')) {
    http_response_code(404);
    exit;
}

if (preg_match('#^/feedback/([^/]+)/?$#', $path, $matches) === 1) {
    $_GET['id'] = rawurldecode($matches[1]);
    require __DIR__ . '/feedback-detail/detail.php';
    return true;
}

if ($path === '/feedback' || $path === '/feedback/') {
    require __DIR__ . '/feedback.php';
    return true;
}

if ($path === '/updates' || $path === '/updates/') {
    require __DIR__ . '/updates/index.php';
    return true;
}

if (preg_match('#^/updates/images/([^/]+)$#', $path, $matches) === 1) {
    $_GET['file'] = rawurldecode($matches[1]);
    require __DIR__ . '/updates/image.php';
    return true;
}

if (preg_match('#^/updates/post/([^/]+)/?$#', $path, $matches) === 1) {
    $_GET['slug'] = rawurldecode($matches[1]);
    require __DIR__ . '/updates/post/index.php';
    return true;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

return false;
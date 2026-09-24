<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$action = (string)($_GET['action'] ?? '');
if (!auth_google_configured() && $action !== 'logout') {
    http_response_code(503);
    exit('Google login is not configured.');
}

if ($action === 'login') {
    header('Location: ' . auth_login_url((string)($_GET['return'] ?? '/feedback')));
    exit;
}
if ($action === 'callback') {
    auth_google_callback();
}
if ($action === 'logout') {
    auth_logout();
}

http_response_code(404);

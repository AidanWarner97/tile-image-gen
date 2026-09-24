<?php
declare(strict_types=1);

function auth_load_environment(): void
{
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile)) {
        return;
    }

    $values = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (!is_array($values)) {
        return;
    }

    foreach ($values as $key => $value) {
        if (getenv((string)$key) === false) {
            putenv((string)$key . '=' . (string)$value);
        }
    }
}

auth_load_environment();

function auth_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

function auth_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function auth_google_configured(): bool
{
    return auth_env('GOOGLE_CLIENT_ID') !== '' && auth_env('GOOGLE_CLIENT_SECRET') !== '';
}

function auth_redirect_uri(): string
{
    $configured = auth_env('GOOGLE_REDIRECT_URI');
    if ($configured !== '') {
        return $configured;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . '/auth/google/callback';
}

function auth_user(): ?array
{
    auth_start_session();
    return isset($_SESSION['google_user']) && is_array($_SESSION['google_user']) ? $_SESSION['google_user'] : null;
}

function auth_login_url(string $returnTo = '/feedback'): string
{
    if (!auth_google_configured()) {
        return '#';
    }

    auth_start_session();
    $_SESSION['google_oauth_state'] = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_return'] = str_starts_with($returnTo, '/') ? $returnTo : '/feedback';

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => auth_env('GOOGLE_CLIENT_ID'),
        'redirect_uri' => auth_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $_SESSION['google_oauth_state'],
        'access_type' => 'online',
        'prompt' => 'select_account',
    ]);
}

function auth_http_json(string $url, array $fields): ?array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function auth_google_callback(): void
{
    auth_start_session();
    $state = (string)($_GET['state'] ?? '');
    $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
    $returnTo = (string)($_SESSION['google_oauth_return'] ?? '/feedback');
    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_return']);

    if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
        http_response_code(400);
        exit('Invalid Google login state. Please try again.');
    }
    if (isset($_GET['error']) || (string)($_GET['code'] ?? '') === '') {
        header('Location: ' . $returnTo . '?auth_error=cancelled');
        exit;
    }

    $token = auth_http_json('https://oauth2.googleapis.com/token', [
        'code' => (string)$_GET['code'],
        'client_id' => auth_env('GOOGLE_CLIENT_ID'),
        'client_secret' => auth_env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => auth_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);
    $accessToken = is_array($token) ? (string)($token['access_token'] ?? '') : '';
    if ($accessToken === '') {
        http_response_code(502);
        exit('Google login could not be completed.');
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer " . $accessToken . "\r\n",
            'timeout' => 8,
        ],
    ]);
    $profileJson = @file_get_contents('https://openidconnect.googleapis.com/v1/userinfo', false, $context);
    $profile = is_string($profileJson) ? json_decode($profileJson, true) : null;
    if (!is_array($profile) || empty($profile['sub']) || empty($profile['email'])) {
        http_response_code(502);
        exit('Google profile could not be loaded.');
    }

    session_regenerate_id(true);
    $_SESSION['google_user'] = [
        'sub' => (string)$profile['sub'],
        'email' => (string)$profile['email'],
        'name' => (string)($profile['name'] ?? $profile['email']),
        'first_name' => (string)($profile['given_name'] ?? ''),
        'last_name' => (string)($profile['family_name'] ?? ''),
        'picture' => (string)($profile['picture'] ?? ''),
    ];

    header('Location: ' . $returnTo . '?auth=success');
    exit;
}

function auth_logout(): void
{
    auth_start_session();
    unset($_SESSION['google_user']);
    header('Location: /feedback?auth=logged_out');
    exit;
}

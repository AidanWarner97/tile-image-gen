<?php
declare(strict_types=1);

const GENERATE_LOG_TABLE = 'tig_generate_log';
const GENERATE_LOG_QUEUE = 'tig_generate_log_queue';

$generateLogEnvFile = __DIR__ . '/.env';
if (is_file($generateLogEnvFile)) {
    $generateLogValues = parse_ini_file($generateLogEnvFile, false, INI_SCANNER_RAW);
    if (is_array($generateLogValues)) {
        foreach ($generateLogValues as $generateLogKey => $generateLogValue) {
            if (getenv((string)$generateLogKey) === false) {
                putenv((string)$generateLogKey . '=' . (string)$generateLogValue);
            }
        }
    }
}

function generate_log_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

function generate_log_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    $dsn = generate_log_env('DB_DSN', generate_log_env('MYSQL_DSN'));
    if ($dsn === '') {
        $host = generate_log_env('DB_HOST', '127.0.0.1');
        $port = generate_log_env('DB_PORT', '3306');
        $name = generate_log_env('DB_NAME', generate_log_env('DB_DATABASE', 'tile_image_gen'));
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    }

    $user = generate_log_env('DB_USER', generate_log_env('DB_USERNAME', 'root'));
    $password = generate_log_env('DB_PASSWORD', generate_log_env('DB_PASS'));
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec('CREATE TABLE IF NOT EXISTS ' . GENERATE_LOG_TABLE . ' (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id CHAR(32) NOT NULL UNIQUE,
        ip VARCHAR(45) NOT NULL,
        tile_name VARCHAR(255) NOT NULL,
        image_file_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        tile_size_width INT UNSIGNED NOT NULL,
        tile_size_height INT UNSIGNED NOT NULL,
        layout VARCHAR(40) NOT NULL,
        grout_colour VARCHAR(7) NOT NULL,
        grout_size INT UNSIGNED NOT NULL,
        status ENUM("generated", "error") NOT NULL,
        downloaded TINYINT(1) NOT NULL DEFAULT 0,
        error_message TEXT NULL,
        created_at DATETIME NOT NULL,
        downloaded_at DATETIME NULL,
        INDEX tig_generate_log_created_at (created_at),
        INDEX tig_generate_log_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    return $db;
}

function generate_log_redis_command(string $command, array $arguments = []): string
{
    $parts = array_merge([$command], $arguments);
    $payload = '*' . count($parts) . "\r\n";
    foreach ($parts as $part) {
        $part = (string)$part;
        $payload .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
    }
    return $payload;
}

function generate_log_redis(array $record): bool
{
    $socketPath = generate_log_env('REDIS_SOCKET', '/run/redis/redis.sock');
    $timeout = (float)generate_log_env('REDIS_TIMEOUT', '0.5');
    $errorCode = 0;
    $errorMessage = '';
    if ($socketPath !== '' && generate_log_socket_available($socketPath)) {
        $socket = @stream_socket_client('unix://' . $socketPath, $errorCode, $errorMessage, $timeout);
    } else {
        $host = generate_log_env('REDIS_HOST');
        if ($host === '') {
            return false;
        }

        $port = (int)generate_log_env('REDIS_PORT', '6379');
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, $timeout);
    }
    if (!is_resource($socket)) {
        return false;
    }
    stream_set_timeout($socket, (int)$timeout, (int)(($timeout - floor($timeout)) * 1000000));

    $password = generate_log_env('REDIS_PASSWORD');
    if ($password !== '') {
        fwrite($socket, generate_log_redis_command('AUTH', [$password]));
        fgets($socket);
    }
    $database = (int)generate_log_env('REDIS_DATABASE', '0');
    if ($database > 0) {
        fwrite($socket, generate_log_redis_command('SELECT', [(string)$database]));
        fgets($socket);
    }

    fwrite($socket, generate_log_redis_command('LPUSH', [GENERATE_LOG_QUEUE, json_encode($record, JSON_THROW_ON_ERROR)]));
    $response = fgets($socket);
    fclose($socket);
    return is_string($response) && str_starts_with($response, ':');
}

function generate_log_socket_available(string $path): bool
{
    return $path !== '' && file_exists($path) && is_readable($path);
}

function generate_log_write(array $record): void
{
    try {
        generate_log_redis($record);
    } catch (Throwable) {
    }

    try {
        $db = generate_log_db();
        $stmt = $db->prepare('INSERT INTO ' . GENERATE_LOG_TABLE . ' (request_id, ip, tile_name, image_file_count, tile_size_width, tile_size_height, layout, grout_colour, grout_size, status, downloaded, error_message, created_at) VALUES (:request_id, :ip, :tile_name, :image_file_count, :tile_size_width, :tile_size_height, :layout, :grout_colour, :grout_size, :status, :downloaded, :error_message, :created_at)');
        $stmt->execute([
            ':request_id' => $record['request_id'],
            ':ip' => $record['ip'],
            ':tile_name' => $record['tile_name'],
            ':image_file_count' => $record['image_file_count'],
            ':tile_size_width' => $record['tile_size_width'],
            ':tile_size_height' => $record['tile_size_height'],
            ':layout' => $record['layout'],
            ':grout_colour' => $record['grout_colour'],
            ':grout_size' => $record['grout_size'],
            ':status' => $record['status'],
            ':downloaded' => $record['downloaded'],
            ':error_message' => $record['error_message'],
            ':created_at' => $record['created_at'],
        ]);
    } catch (Throwable) {
    }
}

function generate_log_update_download(string $requestId): void
{
    if (!preg_match('/^[a-f0-9]{32}$/', $requestId)) {
        return;
    }

    try {
        generate_log_db()->prepare('UPDATE ' . GENERATE_LOG_TABLE . ' SET downloaded = 1, downloaded_at = UTC_TIMESTAMP() WHERE request_id = :request_id')->execute([':request_id' => $requestId]);
    } catch (Throwable) {
    }
}

function generate_log_record(string $requestId, string $status, string $errorMessage = ''): array
{
    $record = array_merge([
        'ip' => '',
        'tile_name' => 'unknown',
        'image_file_count' => 0,
        'tile_size_width' => 0,
        'tile_size_height' => 0,
        'layout' => 'Unknown',
        'grout_colour' => '#000000',
        'grout_size' => 0,
    ], $GLOBALS['generateLogRecord'] ?? []);
    $record['request_id'] = $requestId;
    $record['status'] = $status;
    $record['error_message'] = $errorMessage === '' ? null : $errorMessage;
    $record['downloaded'] = 0;
    $record['created_at'] = gmdate('Y-m-d H:i:s');
    return $record;
}

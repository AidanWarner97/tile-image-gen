<?php
declare(strict_types=1);

require_once __DIR__ . '/generate-log.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (is_array($payload)) {
    generate_log_update_download((string)($payload['request_id'] ?? ''));
}

http_response_code(204);

<?php
declare(strict_types=1);

require_once __DIR__ . '/generate-log.php';
require_once __DIR__ . '/includes/catalogue-storage.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=86400');

try {
    $catalogue = catalogue_storage_load();
} catch (Throwable) {
    http_response_code(503);
    echo json_encode(['error' => 'Tile catalogue unavailable']);
    exit;
}

echo json_encode($catalogue, JSON_UNESCAPED_SLASHES);

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=86400');

$cataloguePath = __DIR__ . '/catalogue/tiles.json';
if (!is_file($cataloguePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Tile catalogue not found']);
    exit;
}

$catalogue = json_decode((string)file_get_contents($cataloguePath), true);
if (!is_array($catalogue)) {
    http_response_code(500);
    echo json_encode(['error' => 'Tile catalogue is invalid']);
    exit;
}

echo json_encode($catalogue, JSON_UNESCAPED_SLASHES);

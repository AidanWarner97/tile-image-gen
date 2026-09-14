<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/updates.php';

header('Content-Type: application/json; charset=utf-8');

$posts = get_all_posts();

echo json_encode(array_map(static fn(array $post): array => [
    'id' => $post['slug'],
    'link' => '/updates/post/' . rawurlencode($post['slug']),
    'title' => ['rendered' => $post['title']],
    'excerpt' => ['rendered' => $post['excerpt']],
    'date' => $post['date'],
    'author' => $post['author'],
], array_slice($posts, 0, 5)), JSON_THROW_ON_ERROR);

<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';
require_once __DIR__ . '/portal/social-posts-service.php';

header('Content-Type: application/activity+json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$uuid = strtolower(trim((string)($_GET['id'] ?? '')));
$post = social_posts_find_uuid($uuid);

if (!$post || (string)$post['visibility'] !== 'public' || (string)$post['status'] === 'draft') {
    http_response_code(404);
    echo json_encode(['error' => 'Social post not found.']);
    exit;
}

$isGone = (string)$post['status'] === 'deleted';
$payload = $isGone
    ? [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => social_posts_object_url($post),
        'type' => 'Tombstone',
        'formerType' => 'Note',
        'deleted' => gmdate(
            DATE_ATOM,
            strtotime((string)($post['deleted_at'] ?: $post['updated_at'])) ?: time()
        ),
      ]
    : ['@context' => 'https://www.w3.org/ns/activitystreams']
        + social_posts_activity_object($post);

http_response_code($isGone ? 410 : 200);
echo json_encode(
    $payload,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);

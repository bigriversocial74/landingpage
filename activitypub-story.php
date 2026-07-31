<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';
require_once __DIR__ . '/portal/stories-service.php';

header('Content-Type: application/activity+json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
$uuid = strtolower(trim((string)($_GET['id'] ?? '')));
$story = stories_find_local_uuid($uuid);
if (!$story) {
    http_response_code(404);
    echo json_encode(['error' => 'Story not found.']);
    exit;
}
$isGone = (string)$story['status'] !== 'active'
    || strtotime((string)$story['expires_at']) <= time();
$payload = $isGone
    ? [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => stories_object_url($story),
        'type' => 'Tombstone',
        'formerType' => 'Note',
        'deleted' => gmdate(DATE_ATOM, strtotime((string)($story['deleted_at'] ?: $story['expires_at'])) ?: time()),
      ]
    : ['@context' => 'https://www.w3.org/ns/activitystreams'] + stories_activity_object($story);
http_response_code($isGone ? 410 : 200);
echo json_encode(
    $payload,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/activitypub-service.php';
require_once dirname(__DIR__) . '/portal/stories-service.php';

$limit = isset($argv[1]) ? (int)$argv[1] : 100;
$processed = stories_expire_due(max(1, min(500, $limit)));
echo json_encode([
    'ok' => true,
    'expired_story_ids' => $processed,
    'count' => count($processed),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

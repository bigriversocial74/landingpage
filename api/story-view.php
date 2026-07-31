<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/activitypub-service.php';
require_once dirname(__DIR__) . '/portal/stories-service.php';

header('Content-Type: application/json; charset=utf-8');
$user = require_role('admin');
if (!is_post() || !same_origin_request()) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}
verify_csrf();
enforce_authenticated_action_limit($user);
$storyId = int_input('story_id');
$ok = stories_mark_viewed($storyId, (int)$user['id']);
http_response_code($ok ? 200 : 404);
echo json_encode([
    'ok' => $ok,
    'story_id' => $storyId,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

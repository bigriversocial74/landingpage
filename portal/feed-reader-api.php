<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/feed-reader-core.php';
require_once __DIR__ . '/feed-reader-media.php';

$user = current_user();
if (!$user) {
    json_response(['ok' => false, 'message' => 'Authentication required.'], 401);
}

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'POST required.'], 405);
}

if (!same_origin_request()) {
    json_response(['ok' => false, 'message' => 'Origin not permitted.'], 403);
}

verify_csrf();
enforce_authenticated_action_limit($user);
feed_reader_require_enabled();

if (!feed_reader_schema_available()) {
    json_response(['ok' => false, 'message' => 'Feed Reader database migration is required.'], 503);
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = trim((string)($payload['action'] ?? ''));
$userId = (int)$user['id'];

try {
    if ($action === 'item_state') {
        $itemId = max(0, (int)($payload['item_id'] ?? 0));
        $state = trim((string)($payload['state'] ?? ''));
        $value = filter_var($payload['value'] ?? false, FILTER_VALIDATE_BOOL);
        $result = feed_reader_set_item_state($userId, $itemId, $state, $value);
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'playback_state') {
        $result = feed_reader_save_playback(
            $userId,
            max(0, (int)($payload['item_id'] ?? 0)),
            max(0, (int)($payload['position'] ?? 0)),
            max(0, (int)($payload['duration'] ?? 0)),
            filter_var($payload['listened'] ?? false, FILTER_VALIDATE_BOOL)
        );
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'save_note') {
        $result = feed_reader_save_note($userId, max(0, (int)($payload['item_id'] ?? 0)), (string)($payload['note'] ?? ''));
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'collection_toggle') {
        $result = feed_reader_toggle_collection(
            $userId,
            max(0, (int)($payload['item_id'] ?? 0)),
            max(0, (int)($payload['collection_id'] ?? 0)),
            filter_var($payload['value'] ?? false, FILTER_VALIDATE_BOOL)
        );
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'mark_read') {
        $itemId = max(0, (int)($payload['item_id'] ?? 0));
        $result = feed_reader_set_item_state($userId, $itemId, 'read', true);
        json_response(['ok' => true, 'result' => $result]);
    }

    json_response(['ok' => false, 'message' => 'Unsupported Feed Reader action.'], 400);
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'message' => feed_reader_limit_text($exception->getMessage(), 500),
    ], 422);
}

<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-unified-social-inbox-api-v66D */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/unified-inbox.php';

$user = require_role('admin');
if (!is_post()) json_response(['ok' => false, 'message' => 'POST required.'], 405);
if (!same_origin_request()) json_response(['ok' => false, 'message' => 'Origin not permitted.'], 403);
verify_csrf();
enforce_authenticated_action_limit($user);

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = $_POST;
$action = trim((string)($payload['action'] ?? ''));
$sourceType = trim((string)($payload['source_type'] ?? ''));
$sourceId = max(0, (int)($payload['source_id'] ?? 0));

$capability = match ($action) {
    'summarize' => 'message_summary',
    'suggest_reply' => 'suggest_reply',
    default => '',
};
if ($capability === '') json_response(['ok' => false, 'message' => 'Unsupported private intelligence action.'], 400);

try {
    unified_inbox_validate_source($sourceType, $sourceId);
    if (!homeserver_capability_available($capability)) {
        json_response(['ok' => false, 'available' => false, 'message' => 'The paired HomeServer has not authorized this capability.'], 409);
    }
    $selected = null;
    foreach (unified_inbox_collect($user) as $item) {
        if ($item['source_type'] === $sourceType && (int)$item['source_id'] === $sourceId) {
            $selected = $item;
            break;
        }
    }
    if (!$selected) throw new RuntimeException('The inbox item is no longer available.');

    $result = homeserver_request($capability, [
        'source_type' => $selected['source_type'],
        'source_id' => $selected['source_id'],
        'channel' => $selected['category'],
        'title' => $selected['title'],
        'participant' => $selected['participant'],
        'preview' => $selected['preview'],
        'occurred_at' => $selected['occurred_at'],
        'native_status' => $selected['native_status'],
    ]);
    if (!($result['ok'] ?? false)) {
        json_response([
            'ok' => false,
            'available' => (bool)($result['available'] ?? true),
            'message' => (string)($result['message'] ?? 'The HomeServer request could not be completed.'),
        ], 422);
    }
    $text = trim((string)($result['text'] ?? $result['summary'] ?? $result['reply'] ?? $result['result'] ?? ''));
    json_response(['ok' => true, 'capability' => $capability, 'text' => mb_substr($text, 0, 12000)]);
} catch (RuntimeException $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable) {
    json_response(['ok' => false, 'message' => 'The private intelligence request could not be completed.'], 500);
}

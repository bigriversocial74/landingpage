<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/notification-delivery.php';

$user = require_role('admin');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $key = notification_delivery_active_vapid_key();
    json_response([
        'ok' => true,
        'schema_available' => notification_delivery_schema_available(),
        'enabled' => notification_delivery_settings()['push_enabled'],
        'public_key' => $key ? (string)$key['public_key'] : '',
        'active_subscriptions' => notification_delivery_active_push_count((int)$user['id']),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!same_origin_request()) {
    json_response(['ok' => false, 'message' => 'The request origin is not authorized.'], 403);
}
verify_csrf();
enforce_authenticated_action_limit($user);

$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 16384) {
    json_response(['ok' => false, 'message' => 'The request body is invalid.'], 400);
}
try {
    $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    json_response(['ok' => false, 'message' => 'The request body is not valid JSON.'], 400);
}
if (!is_array($payload)) {
    json_response(['ok' => false, 'message' => 'The request body is invalid.'], 400);
}

$action = trim((string)($payload['action'] ?? ''));
try {
    if ($action === 'subscribe') {
        $subscription = is_array($payload['subscription'] ?? null) ? $payload['subscription'] : [];
        $uuid = notification_delivery_register_subscription(
            (int)$user['id'],
            $subscription,
            (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
        );
        json_response([
            'ok' => true,
            'subscription_uuid' => $uuid,
            'active_subscriptions' => notification_delivery_active_push_count((int)$user['id']),
        ]);
    }
    if ($action === 'unsubscribe') {
        $endpoint = trim((string)($payload['endpoint'] ?? ''));
        $uuid = trim((string)($payload['subscription_uuid'] ?? ''));
        $removed = notification_delivery_revoke_subscription((int)$user['id'], $uuid, $endpoint);
        json_response([
            'ok' => true,
            'revoked' => $removed,
            'active_subscriptions' => notification_delivery_active_push_count((int)$user['id']),
        ]);
    }
    json_response(['ok' => false, 'message' => 'Unsupported push action.'], 400);
} catch (Throwable $exception) {
    error_log('North Mountain Media push subscription failed: ' . $exception->getMessage());
    json_response(['ok' => false, 'message' => $exception->getMessage()], 422);
}

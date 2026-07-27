<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/notifications.php';

$user = current_user();

if (!$user || !in_array($user['role'], ['admin', 'client'], true)) {
    json_response(['ok' => false, 'message' => 'Authentication required.'], 401);
}

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!same_origin_request()) {
    json_response(['ok' => false, 'message' => 'Invalid request origin.'], 403);
}

verify_csrf();
enforce_authenticated_action_limit($user);

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = str_contains($contentType, 'application/json')
    ? json_decode((string)file_get_contents('php://input'), true)
    : $_POST;

if (!is_array($payload)) {
    json_response(['ok' => false, 'message' => 'Invalid request.'], 400);
}

$action = trim((string)($payload['action'] ?? ''));

if ($action === 'mark_read') {
    $notificationId = (int)($payload['notification_id'] ?? 0);
    notification_mark_read($notificationId, (int)$user['id']);

    json_response([
        'ok' => true,
        'unread_count' => notification_unread_count((int)$user['id']),
    ]);
}

if ($action === 'mark_all_read') {
    notification_mark_all_read((int)$user['id']);

    json_response([
        'ok' => true,
        'unread_count' => 0,
    ]);
}

if ($action === 'poll') {
    $notifications = array_map(
        static function (array $notification) use ($user): array {
            return [
                'id' => (int)$notification['id'],
                'category' => (string)$notification['category'],
                'title' => (string)$notification['title'],
                'body' => $notification['body'],
                'priority' => (string)$notification['priority'],
                'is_read' => (int)$notification['is_read'],
                'created_at' => $notification['created_at'],
                'display_url' => notification_portal_link(
                    $user,
                    $notification['link_url']
                ),
            ];
        },
        notification_recent((int)$user['id'], 8, false)
    );

    json_response([
        'ok' => true,
        'unread_count' => notification_unread_count((int)$user['id']),
        'notifications' => $notifications,
    ]);
}

json_response(['ok' => false, 'message' => 'Unsupported notification action.'], 422);

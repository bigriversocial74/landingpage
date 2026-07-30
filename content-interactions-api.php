<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-content-interactions-api-v66C */

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/publishing.php';
require_once __DIR__ . '/portal/content-interactions.php';

$user = current_user();
if (!$user) json_response(['ok' => false, 'message' => 'Sign in to participate.'], 401);
if (!is_post()) json_response(['ok' => false, 'message' => 'POST required.'], 405);
if (!same_origin_request()) json_response(['ok' => false, 'message' => 'Origin not permitted.'], 403);
verify_csrf();
enforce_authenticated_action_limit($user);
if (!content_interactions_schema_available()) json_response(['ok' => false, 'message' => 'Content interaction migration is required.'], 503);

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = $_POST;
$action = trim((string)($payload['action'] ?? ''));
$userId = (int)$user['id'];
$contentType = trim((string)($payload['content_type'] ?? 'blog_post'));
$contentId = max(0, (int)($payload['content_id'] ?? 0));
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);

try {
    if ($action === 'comment_create') {
        rate_limit('content-comment-user:' . $userId, 8, 300);
        rate_limit('content-comment-ip:' . $ip, 20, 3600);
        $result = content_interactions_create_comment(
            $userId,
            $contentType,
            $contentId,
            max(0, (int)($payload['parent_id'] ?? 0)),
            (string)($payload['body'] ?? ''),
            (string)($user['role'] ?? 'client')
        );
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'comment_edit') {
        rate_limit('content-comment-edit:' . $userId, 12, 600);
        $result = content_interactions_edit_comment(
            max(0, (int)($payload['comment_id'] ?? 0)),
            $user,
            (string)($payload['body'] ?? '')
        );
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'comment_delete') {
        content_interactions_delete_comment(max(0, (int)($payload['comment_id'] ?? 0)), $user);
        json_response(['ok' => true, 'result' => ['deleted' => true]]);
    }

    if ($action === 'reaction_toggle') {
        rate_limit('content-reaction-user:' . $userId, 60, 3600);
        $result = content_interactions_toggle_reaction(
            $userId,
            trim((string)($payload['target_type'] ?? 'content')),
            $contentType,
            max(0, (int)($payload['target_id'] ?? 0)),
            trim((string)($payload['reaction_type'] ?? ''))
        );
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'comment_report') {
        rate_limit('content-report-user:' . $userId, 10, 86400);
        $result = content_interactions_report_comment(
            max(0, (int)($payload['comment_id'] ?? 0)),
            $userId,
            (string)($payload['reason'] ?? '')
        );
        json_response(['ok' => true, 'result' => $result]);
    }

    json_response(['ok' => false, 'message' => 'Unsupported interaction action.'], 400);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 422);
}

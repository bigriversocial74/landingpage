<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/pod-follow-handoff.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

try {
    if (is_post()) {
        if (!same_origin_request()) {
            http_response_code(403);
            throw new RuntimeException('Cross-origin follow intent creation is not allowed.');
        }
        verify_csrf();
        if (rate_limit_exceeded('pod_follow_intent', request_ip(), 40, 3600)) {
            http_response_code(429);
            throw new RuntimeException('Too many follow requests. Wait briefly and try again.');
        }
        if (!nmm_module_enabled('social_feed') || !activitypub_settings()['enabled']) {
            http_response_code(409);
            throw new RuntimeException('POD following is not active for this site.');
        }
        $returnUrl = input('return_url', app_url('index.php'));
        $intent = pod_follow_create_intent($returnUrl);
        echo json_encode([
            'ok' => true,
            'protocol' => 'pod-follow-launch-1',
            'intent_url' => pod_follow_intent_url((string)$intent['token']),
            'target_actor' => (string)$intent['payload']['target_actor'],
            'target_name' => (string)$intent['payload']['target_name'],
            'expires_at' => (int)$intent['payload']['expires_at'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
        http_response_code(405);
        header('Allow: GET, POST');
        throw new RuntimeException('A signed POD follow token is required.');
    }
    $payload = pod_follow_verify_intent_token($token);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'pod_follow_intent_failed',
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

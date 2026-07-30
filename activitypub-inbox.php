<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
if (rate_limit_exceeded('public_activitypub_inbox', request_ip(), 120, 3600)) {
    http_response_code(429);
    exit;
}
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (
    $contentType !== ''
    && !str_contains($contentType, 'application/activity+json')
    && !str_contains($contentType, 'application/ld+json')
    && !str_contains($contentType, 'application/json')
) {
    http_response_code(415);
    exit;
}
$body = (string)file_get_contents('php://input');
try {
    activitypub_receive_inbox(
        $body,
        activitypub_header_map(),
        'POST',
        activitypub_request_target(publishing_absolute_url('activitypub-inbox.php'))
    );
    http_response_code(202);
} catch (RuntimeException $exception) {
    http_response_code(400);
    error_log('ActivityPub inbox rejection: ' . $exception->getMessage());
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('ActivityPub inbox failure: ' . $exception->getMessage());
}

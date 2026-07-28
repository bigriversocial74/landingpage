<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/../portal/bootstrap.php';
require_once __DIR__ . '/../portal/pod-messaging.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-POD-Protocol: pod-message-1');

if (!is_post()) {
    json_response([
        'ok' => false,
        'message' => 'POD messaging accepts signed POST requests only.',
    ], 405);
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_contains($contentType, 'application/json')) {
    json_response([
        'ok' => false,
        'message' => 'POD messaging requires application/json.',
    ], 415);
}

$rawBody = (string)file_get_contents('php://input');
if ($rawBody === '' || strlen($rawBody) > 32768) {
    json_response([
        'ok' => false,
        'message' => 'The POD message request is empty or too large.',
    ], 413);
}

$token = pod_message_extract_token();
if ($token === '') {
    json_response([
        'ok' => false,
        'message' => 'POD messaging authentication is required.',
    ], 401);
}

$rateKey = hash('sha256', request_ip() . '|' . hash('sha256', $token));
if (rate_limit_exceeded('pod_message_receive', $rateKey, 120, 3600)) {
    json_response([
        'ok' => false,
        'message' => 'The POD messaging rate limit was reached.',
    ], 429);
}

try {
    $context = pod_authorize_message_token($token);
    pod_verify_message_signature(
        $token,
        $rawBody,
        trim((string)($_SERVER['HTTP_X_POD_TIMESTAMP'] ?? '')),
        strtolower(trim((string)($_SERVER['HTTP_X_POD_SIGNATURE'] ?? '')))
    );

    $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || (string)($payload['protocol'] ?? '') !== 'pod-message-1') {
        throw new RuntimeException('The POD messaging protocol is unsupported.');
    }

    $result = pod_receive_message($context, $payload);
    json_response([
        'ok' => true,
        'protocol' => 'pod-message-1',
        'duplicate' => (bool)$result['duplicate'],
        'message_uuid' => (string)$payload['message_uuid'],
        'conversation_uuid' => (string)$payload['conversation_uuid'],
        'receipt_id' => (string)$result['receipt_id'],
        'received_at' => gmdate('c'),
    ], $result['duplicate'] ? 200 : 201);
} catch (JsonException) {
    json_response([
        'ok' => false,
        'message' => 'The POD message JSON is invalid.',
    ], 400);
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 403);
}

<?php
declare(strict_types=1);

if (!defined('NMM_PUBLIC_PAGE')) define('NMM_PUBLIC_PAGE', true);
$root = dirname(__DIR__, 3);
require_once $root . '/portal/bootstrap.php';
require_once $root . '/portal/pod-homeserver-provider.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-POD-Provider-Contract: pod-homeserver-voice-1');

function pod_homeserver_provider_response(
    bool $ok,
    string $message,
    array $data = [],
    int $status = 200,
    ?string $code = null
): never {
    http_response_code($status);
    echo json_encode([
        'ok' => $ok,
        'message' => $message,
        'code' => $code,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function pod_homeserver_provider_error_code(Throwable $exception): string
{
    $message = strtolower($exception->getMessage());
    return match (true) {
        str_contains($message, 'disabled') => 'pod_provider_disabled',
        str_contains($message, 'sync code') && str_contains($message, 'expired') => 'pod_sync_code_expired',
        str_contains($message, 'sync code') => 'pod_sync_code_invalid',
        str_contains($message, 'already used'), str_contains($message, 'nonce') => 'pod_request_replayed',
        str_contains($message, 'signature'), str_contains($message, 'signed') => 'pod_signature_invalid',
        str_contains($message, 'bearer'), str_contains($message, 'credential') => 'pod_credentials_rejected',
        str_contains($message, 'capability') => 'pod_capability_unsupported',
        str_contains($message, 'lease') => 'pod_voice_lease_invalid',
        str_contains($message, 'artifact') => 'pod_voice_artifact_invalid',
        str_contains($message, 'too large') => 'pod_request_too_large',
        default => 'pod_provider_request_rejected',
    };
}

function pod_homeserver_provider_json_body(bool $signed = true): array
{
    if (!is_post()) {
        pod_homeserver_provider_response(false, 'Method not allowed.', [], 405, 'method_not_allowed');
    }
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (!str_contains($contentType, 'application/json')) {
        pod_homeserver_provider_response(false, 'Content-Type application/json is required.', [], 415, 'content_type_required');
    }
    $max = max(64 * 1024, min(16 * 1024 * 1024, (int)(pod_homeserver_config()['max_request_bytes'] ?? 12 * 1024 * 1024)));
    $raw = (string)file_get_contents('php://input');
    if ($raw === '' || strlen($raw) > $max) {
        pod_homeserver_provider_response(false, 'The request is empty or too large.', [], 413, 'pod_request_too_large');
    }
    try {
        $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        pod_homeserver_provider_response(false, 'The request JSON is invalid.', [], 400, 'invalid_json');
    }
    if (!is_array($decoded)) {
        pod_homeserver_provider_response(false, 'The request JSON must be an object.', [], 400, 'invalid_json');
    }
    if ($signed) {
        $connection = pod_homeserver_authorize_signed_request($raw);
        return ['raw' => $raw, 'payload' => $decoded, 'connection' => $connection];
    }
    return ['raw' => $raw, 'payload' => $decoded, 'connection' => null];
}

function pod_homeserver_provider_reject(Throwable $exception, int $status = 403): never
{
    pod_homeserver_provider_response(
        false,
        $exception->getMessage(),
        [],
        $status,
        pod_homeserver_provider_error_code($exception)
    );
}

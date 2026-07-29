<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/vp3-license-settings-store.php';
require_once dirname(__DIR__) . '/portal/vp3-licensing.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $configured = trim((string)(nmm_config('vp3_licensing')['cron_token'] ?? ''));
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    $provided = str_starts_with($authorization, 'Bearer ')
        ? trim(substr($authorization, 7))
        : '';
    if ($configured === '' || strlen($provided) !== strlen($configured) || !hash_equals($configured, $provided)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'authorization_required']);
        exit;
    }
}

try {
    if (!vp3_license_schema_available()) {
        throw new RuntimeException('Import database/vp3_pod_licensing_v64.sql first.');
    }
    $service = vp3_license_service();
    $current = $service->validateNow();
    try {
        $service->heartbeat();
    } catch (Throwable $heartbeatError) {
        // A verified entitlement refresh remains valid even if the follow-up
        // heartbeat endpoint is temporarily unavailable.
        error_log('VP3 licensing heartbeat failed: ' . $heartbeatError->getMessage());
    }
    $storage = $service->storage(true);
    $result = [
        'ok' => true,
        'license_status' => $current['status'],
        'connection_state' => $current['connection_state'],
        'offline_lease_valid' => $current['offline_lease_valid'],
        'storage_warning_state' => $storage['warning_state'],
        'validated_at' => gmdate('c'),
    ];
    if ($isCli) {
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    } else {
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $exception) {
    $current = vp3_license_schema_available()
        ? vp3_license_service()->current()
        : ['status' => 'unknown', 'offline_lease_valid' => false];
    $result = [
        'ok' => (bool)($current['offline_lease_valid'] ?? false),
        'license_status' => (string)($current['status'] ?? 'unknown'),
        'offline_lease_valid' => (bool)($current['offline_lease_valid'] ?? false),
        'error' => 'vp3_license_refresh_failed',
        'message' => mb_substr($exception->getMessage(), 0, 300),
    ];
    if ($isCli) {
        fwrite(STDERR, json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit($result['ok'] ? 0 : 1);
    }
    http_response_code($result['ok'] ? 200 : 503);
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

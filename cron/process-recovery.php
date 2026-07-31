<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/incident-response.php';

$limit = max(1, min(25, (int)($argv[1] ?? 0)));
try {
    recovery_sync_catalog(null);
    recovery_refresh_recommendations();
    $result = recovery_run_worker($limit);
    fwrite(STDOUT, recovery_json_encode($result + ['completed_at' => gmdate(DATE_ATOM)]) . PHP_EOL);
    exit(($result['status'] ?? '') === 'completed' || ($result['status'] ?? '') === 'disabled' ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, recovery_json_encode([
        'status' => 'failed',
        'error_code' => 'recovery_worker_failed',
        'error_message' => mb_substr(trim(preg_replace('/\s+/u', ' ', $exception->getMessage()) ?? ''), 0, 1000),
        'completed_at' => gmdate(DATE_ATOM),
    ]) . PHP_EOL);
    exit(1);
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/automation-rules.php';
require_once dirname(__DIR__) . '/portal/operations-analytics.php';
require_once dirname(__DIR__) . '/portal/operations-analytics-extensions.php';
require_once dirname(__DIR__) . '/portal/notifications.php';
require_once dirname(__DIR__) . '/portal/operations-admin.php';

$windowType = strtolower(trim((string)($argv[1] ?? 'hour')));
if (!in_array($windowType, ['hour', 'day'], true)) {
    fwrite(STDERR, "Usage: php cron/process-operations-analytics.php [hour|day] [--force]\n");
    exit(2);
}
$force = in_array('--force', $argv, true);

try {
    $result = operations_analytics_run_extended($windowType, $force);
    fwrite(STDOUT, operations_analytics_json_encode($result) . PHP_EOL);
    exit(($result['status'] ?? '') === 'failed' ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, operations_analytics_json_encode([
        'status' => 'failed',
        'error_code' => 'operations_analytics_worker_failed',
        'error_message' => mb_substr(trim(preg_replace('/\s+/u', ' ', $exception->getMessage()) ?? ''), 0, 1000),
    ]) . PHP_EOL);
    exit(1);
}

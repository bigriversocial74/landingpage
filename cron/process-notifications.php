<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/notifications.php';
require_once dirname(__DIR__) . '/portal/notification-delivery.php';

$limit = isset($argv[1]) ? (int)$argv[1] : 0;
$limit = max(1, min(100, $limit > 0 ? $limit : notification_delivery_settings()['worker_batch_size']));

try {
    $result = notification_delivery_run($limit);
    $result['cleanup'] = notification_delivery_cleanup();
    $result['completed_at'] = gmdate(DATE_ATOM);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => 'notification_worker_failed',
        'message' => $exception->getMessage(),
        'completed_at' => gmdate(DATE_ATOM),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}

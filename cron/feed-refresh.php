<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/feed-reader-core.php';

$config = feed_reader_config();
$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $provided = trim((string)(
        $_SERVER['HTTP_X_FEED_REFRESH_TOKEN']
        ?? $_GET['token']
        ?? ''
    ));
    $expected = trim((string)$config['cron_token']);

    if (
        $expected === ''
        || $expected === 'replace-with-a-long-random-feed-refresh-token'
        || $provided === ''
        || !hash_equals($expected, $provided)
    ) {
        json_response(['ok' => false, 'message' => 'Feed refresh token rejected.'], 403);
    }
}

try {
    feed_reader_require_enabled();
    if (!feed_reader_schema_available()) {
        throw new RuntimeException('Feed Reader database migration is required.');
    }
    $limit = $isCli
        ? (int)($argv[1] ?? $config['refresh_batch_size'])
        : max(1, (int)($_GET['limit'] ?? $config['refresh_batch_size']));
    $result = feed_reader_run_scheduled_refresh($limit);
    $result['cleanup'] = feed_reader_cleanup();

    if ($isCli) {
        echo json_encode(['ok' => true, 'result' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    json_response(['ok' => true, 'result' => $result]);
} catch (Throwable $exception) {
    if ($isCli) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
    json_response(['ok' => false, 'message' => $exception->getMessage()], 500);
}

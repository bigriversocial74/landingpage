<?php
declare(strict_types=1);

require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/transcription.php';

$config = transcription_config();
$limit = max(
    1,
    min(
        10,
        (int)($config['max_jobs_per_run'] ?? 2)
    )
);

if (PHP_SAPI === 'cli') {
    foreach ($argv ?? [] as $argument) {
        if (preg_match('/^--limit=(\d+)$/', (string)$argument, $matches)) {
            $limit = max(1, min(10, (int)$matches[1]));
        }
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');

    $expected = trim((string)($config['worker_token'] ?? ''));
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    $bearer = '';

    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        $bearer = trim((string)$matches[1]);
    }

    $provided = trim((string)(
        $_SERVER['HTTP_X_TRANSCRIPTION_TOKEN']
        ?? $bearer
        ?? ''
    ));

    if ($provided === '') {
        $provided = trim((string)($_GET['token'] ?? ''));
    }

    if (
        $expected === ''
        || $expected === 'replace-with-a-long-random-transcription-worker-token'
        || $provided === ''
        || !hash_equals($expected, $provided)
    ) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid transcription worker token.',
        ]);
        exit;
    }

    $requestedLimit = filter_input(
        INPUT_GET,
        'limit',
        FILTER_VALIDATE_INT
    );

    if ($requestedLimit !== false && $requestedLimit !== null) {
        $limit = max(1, min(10, (int)$requestedLimit));
    }
}

if (!(bool)($config['enabled'] ?? false)) {
    $payload = [
        'ok' => false,
        'message' => 'Automatic transcription is disabled.',
        'processed' => 0,
        'results' => [],
    ];
} elseif (trim((string)($config['api_key'] ?? '')) === '') {
    $payload = [
        'ok' => false,
        'message' => 'The transcription API key is not configured.',
        'processed' => 0,
        'results' => [],
    ];
} else {
    try {
        $results = transcription_run_queue($limit);
        $payload = [
            'ok' => true,
            'processed' => count($results),
            'results' => $results,
        ];
    } catch (Throwable $exception) {
        http_response_code(500);
        $payload = [
            'ok' => false,
            'message' => $exception->getMessage(),
            'processed' => 0,
            'results' => [],
        ];
    }
}

$output = json_encode(
    $payload,
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);

if (PHP_SAPI === 'cli') {
    fwrite(STDOUT, $output . PHP_EOL);
} else {
    echo $output;
}

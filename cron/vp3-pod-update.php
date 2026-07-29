<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/vp3-update-core.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }
    $configured = vp3_update_worker_token();
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    $provided = str_starts_with($authorization, 'Bearer ')
        ? trim(substr($authorization, 7))
        : '';
    if (
        $configured === ''
        || str_starts_with($configured, 'replace-with-')
        || strlen($provided) !== strlen($configured)
        || !hash_equals($configured, $provided)
    ) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'authorization_required']);
        exit;
    }
}

@set_time_limit(0);
@ignore_user_abort(true);

$mode = 'run';
$releaseId = 0;
if ($isCli) {
    foreach ($argv ?? [] as $argument) {
        if (str_starts_with($argument, '--mode=')) {
            $mode = substr($argument, 7);
        } elseif (str_starts_with($argument, '--release=')) {
            $releaseId = (int)substr($argument, 10);
        }
    }
} else {
    $mode = trim((string)($_SERVER['HTTP_X_VP3_UPDATE_MODE'] ?? 'run'));
    $releaseId = (int)($_SERVER['HTTP_X_VP3_RELEASE_ID'] ?? 0);
}

try {
    $agent = new Vp3UpdateAgent();
    $result = match ($mode) {
        'check' => $agent->check(null, 'worker'),
        'prepare' => $releaseId > 0
            ? $agent->prepare($releaseId, null, 'worker')
            : throw new RuntimeException('A release ID is required for prepare mode.'),
        'install' => $releaseId > 0
            ? $agent->install($releaseId, null, 'worker')
            : throw new RuntimeException('A release ID is required for install mode.'),
        default => $agent->runScheduled(),
    };
    $payload = ['ok' => true, 'mode' => $mode, 'result' => $result, 'completed_at' => gmdate('c')];
    if ($isCli) {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    } else {
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $exception) {
    $payload = [
        'ok' => false,
        'mode' => $mode,
        'error' => 'vp3_update_worker_failed',
        'message' => mb_substr($exception->getMessage(), 0, 500),
        'completed_at' => gmdate('c'),
    ];
    if ($isCli) {
        fwrite(STDERR, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

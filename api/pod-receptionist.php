<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/../portal/bootstrap.php';
require_once __DIR__ . '/../portal/pod-agent-receptionist.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

verify_csrf();
if (rate_limit_exceeded('pod_receptionist', request_ip(), 120, 3600)) {
    json_response(['ok' => false, 'message' => 'Receptionist request limit reached.'], 429);
}

$connectedContext = pod_connected_call_context();
if (!$connectedContext) {
    json_response([
        'ok' => false,
        'message' => 'A valid connected POD call session is required.',
    ], 403);
}

$payload = request_json();
$action = trim((string)($payload['action'] ?? ''));
$sessionUuid = trim((string)($payload['session_uuid'] ?? ($_SESSION['pod_receptionist_session_uuid'] ?? '')));

try {
    if ($action === 'start') {
        json_response(['ok' => true, 'session' => pod_receptionist_start($connectedContext)]);
    }

    if ($action === 'ask') {
        json_response([
            'ok' => true,
            'result' => pod_receptionist_answer(
                $sessionUuid,
                trim((string)($payload['query'] ?? ''))
            ),
        ]);
    }

    if ($action === 'request_callback') {
        json_response([
            'ok' => true,
            'result' => pod_receptionist_capture(
                $sessionUuid,
                'callback',
                trim((string)($payload['message'] ?? '')),
                trim((string)($payload['preferred_at'] ?? '')) ?: null
            ),
        ]);
    }

    if ($action === 'leave_message') {
        json_response([
            'ok' => true,
            'result' => pod_receptionist_capture(
                $sessionUuid,
                'message',
                trim((string)($payload['message'] ?? ''))
            ),
        ]);
    }

    if ($action === 'transfer') {
        json_response([
            'ok' => true,
            'result' => pod_receptionist_request_transfer($sessionUuid),
        ]);
    }

    if ($action === 'complete') {
        json_response([
            'ok' => true,
            'result' => pod_receptionist_complete($sessionUuid),
        ]);
    }

    throw new RuntimeException('Unsupported receptionist action.');
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/../portal/bootstrap.php';
require_once __DIR__ . '/../portal/pod-messaging.php';
require_once __DIR__ . '/../portal/pod-agent-voice.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

verify_csrf();
if (rate_limit_exceeded('pod_agent_voice', request_ip(), 240, 3600)) {
    json_response(['ok' => false, 'message' => 'Browser voice request limit reached.'], 429);
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
$voiceSessionUuid = trim((string)($payload['voice_session_uuid'] ?? ''));

try {
    if ($action === 'start') {
        $session = pod_voice_start_session(
            trim((string)($payload['receptionist_session_uuid'] ?? '')),
            !empty($payload['recognition_supported']),
            !empty($payload['synthesis_supported']),
            trim((string)($payload['selected_voice_name'] ?? '')),
            trim((string)($payload['recognition_language'] ?? '')),
            !empty($payload['hands_free_enabled']),
            !empty($payload['spoken_replies_enabled'])
        );
        json_response([
            'ok' => true,
            'session' => [
                'voice_session_uuid' => (string)$session['voice_session_uuid'],
                'capability_mode' => (string)$session['capability_mode'],
                'recognized_turns' => (int)$session['recognized_turns'],
                'spoken_turns' => (int)$session['spoken_turns'],
                'error_count' => (int)$session['error_count'],
            ],
        ]);
    }

    if ($action === 'record') {
        json_response([
            'ok' => true,
            'counters' => pod_voice_record(
                $voiceSessionUuid,
                trim((string)($payload['event_type'] ?? '')),
                is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []
            ),
        ]);
    }

    if ($action === 'complete') {
        json_response([
            'ok' => true,
            'result' => pod_voice_complete(
                $voiceSessionUuid,
                trim((string)($payload['status'] ?? 'completed'))
            ),
        ]);
    }

    throw new RuntimeException('Unsupported browser voice action.');
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

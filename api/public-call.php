<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/bootstrap.php';
if(!nmm_module_enabled('call_us'))json_response(['ok'=>false,'message'=>'This public module is currently unavailable.'],404);
require_once dirname(__DIR__) . '/portal/call-center.php';
require_once dirname(__DIR__) . '/portal/visitor-intelligence.php';

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!same_origin_request()) {
    json_response(['ok' => false, 'message' => 'Invalid request origin.'], 403);
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 150000) {
    json_response(['ok' => false, 'message' => 'Request is too large.'], 413);
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = str_contains($contentType, 'application/json')
    ? json_decode((string)file_get_contents('php://input'), true)
    : $_POST;

if (!is_array($payload)) {
    json_response(['ok' => false, 'message' => 'Invalid request.'], 400);
}

verify_csrf();
$action = trim((string)($payload['action'] ?? ''));

function public_call_token_request(
    int $requestId,
    string $token
): array {
    if ($requestId <= 0 || strlen($token) < 32) {
        throw new RuntimeException('The public call session is invalid.');
    }

    $hash = hash('sha256', $token);
    $statement = db()->prepare(
        'SELECT request_record.*,
                admin.display_name AS assigned_admin_name
         FROM call_center_requests request_record
         LEFT JOIN users admin
           ON admin.id=request_record.assigned_admin_user_id
         WHERE request_record.id=:id
           AND request_record.source="public"
           AND request_record.public_token_hash=:token_hash
           AND request_record.token_expires_at>=UTC_TIMESTAMP()
         LIMIT 1'
    );
    $statement->execute([
        'id' => $requestId,
        'token_hash' => $hash,
    ]);
    $request = $statement->fetch();

    if (!$request) {
        throw new RuntimeException('The public call session expired or is unavailable.');
    }

    return $request;
}

function public_call_duration(array $request): int
{
    if (empty($request['answered_at'])) {
        return 0;
    }

    try {
        $answered = new DateTimeImmutable(
            (string)$request['answered_at'],
            new DateTimeZone('UTC')
        );

        return max(0, time() - $answered->getTimestamp());
    } catch (Throwable) {
        return 0;
    }
}

try {
    if ($action === 'start') {
        if (trim((string)($payload['website'] ?? '')) !== '') {
            json_response([
                'ok' => true,
                'mode' => 'callback',
                'message' => 'Your request was received.',
            ]);
        }

        $mode = trim((string)($payload['mode'] ?? 'callback'));
        $name = trim((string)($payload['name'] ?? ''));
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $phone = trim((string)($payload['phone'] ?? ''));
        $company = trim((string)($payload['company'] ?? ''));
        $subject = trim((string)($payload['subject'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));
        $preferredAt = trim((string)($payload['preferred_at'] ?? ''));
        $consent = filter_var(
            $payload['microphone_consent'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if (!in_array($mode, ['live', 'message', 'callback'], true)) {
            $mode = 'message';
        }

        if ($name === '' || strlen($name) > 160) {
            throw new RuntimeException('Enter your name.');
        }

        if (
            $email !== ''
            && (
                !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 190
            )
        ) {
            throw new RuntimeException(
                'Enter a valid email address or leave it blank.'
            );
        }

        if (strlen($phone) > 60 || strlen($company) > 190) {
            throw new RuntimeException(
                'One of the optional contact fields is too long.'
            );
        }

        if (strlen($subject) > 190) {
            throw new RuntimeException(
                'The optional call topic is too long.'
            );
        }

        if (strlen($message) > 8000) {
            throw new RuntimeException(
                'The optional message must be under 8,000 characters.'
            );
        }

        $requestCallback = filter_var(
            $payload['request_callback'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        $subject = $subject !== ''
            ? $subject
            : match ($mode) {
                'live' => 'Public browser call',
                'callback' => 'Public callback request',
                default => $requestCallback
                    ? 'Public message and callback request'
                    : 'Public message',
            };

        if (
            $mode === 'message'
            && $message === ''
            && !$requestCallback
        ) {
            throw new RuntimeException(
                'Enter a message or request a callback.'
            );
        }

        if ($mode === 'live' && !$consent) {
            throw new RuntimeException('Confirm microphone access before starting a browser call.');
        }

        if ($preferredAt !== '') {
            $preferredAt = str_replace('T', ' ', $preferredAt);
            try {
                $preferredAt = (new DateTimeImmutable($preferredAt))->format('Y-m-d H:i:s');
            } catch (Throwable) {
                throw new RuntimeException('Enter a valid callback time.');
            }
        } else {
            $preferredAt = null;
        }

        $ip = request_ip();
        $security = nmm_config('security');
        $window = max(60, (int)($security['contact_window_seconds'] ?? 3600));
        $ipLimit = max(1, (int)(call_center_config()['public_ip_limit'] ?? 4));
        $emailLimit = max(1, (int)(call_center_config()['public_email_limit'] ?? 3));

        $identityRateKey = $email !== ''
            ? $email
            : strtolower($name) . '|' . $phone;

        if (
            rate_limit_exceeded(
                'public_call_ip',
                $ip,
                $ipLimit,
                $window
            )
            || rate_limit_exceeded(
                'public_call_identity',
                $identityRateKey,
                $emailLimit,
                $window
            )
        ) {
            throw new RuntimeException(
                'Too many call requests were submitted. Try again later.'
            );
        }

        $lineStatus = call_center_public_status();

        if ($mode === 'live' && $lineStatus !== 'available') {
            throw new RuntimeException(call_center_public_status_message());
        }

        $pdo = db();
        $pdo->beginTransaction();

        $crmEmail = $email;

        if ($crmEmail === '') {
            $crmEmail = 'public-call-' . substr(
                hash(
                    'sha256',
                    strtolower($name) . '|' .
                    $phone . '|' .
                    $ip
                ),
                0,
                24
            ) . '@local.invalid';
        }

        $contactId = call_center_upsert_public_contact(
            $name,
            $crmEmail,
            $phone !== '' ? $phone : null,
            $company !== '' ? $company : null
        );

        $adminId = call_center_default_admin_id();
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $tokenMinutes = max(
            5,
            min(120, (int)(call_center_config()['public_token_minutes'] ?? 30))
        );
        $ringSeconds = call_center_ring_seconds();
        $maxRings = call_center_max_rings();
        $now = time();
        $isLive = $mode === 'live';
        $isCallback = !$isLive && (
            $mode === 'callback'
            || $requestCallback
            || $preferredAt !== null
        );
        $requestType = $isLive
            ? 'live_call'
            : ($isCallback ? 'callback' : 'call_request');

        $request = call_center_create_request([
            'source' => 'public',
            'request_type' => $requestType,
            'crm_contact_id' => $contactId,
            'assigned_admin_user_id' => $adminId > 0 ? $adminId : null,
            'guest_name' => $name,
            'guest_email' => $email !== '' ? $email : null,
            'guest_phone' => $phone !== '' ? $phone : null,
            'guest_company' => $company !== '' ? $company : null,
            'subject' => $subject,
            'message' => $message,
            'preferred_at' => $preferredAt,
            'priority' => $isLive ? 'high' : 'normal',
            'status' => $isLive
                ? 'ringing'
                : ($isCallback ? 'queued' : 'new'),
            'disposition' => $isLive
                ? 'unassigned'
                : 'left_message',
            'queued_at' => gmdate('Y-m-d H:i:s'),
            'ringing_at' => $isLive ? gmdate('Y-m-d H:i:s') : null,
            'guest_heartbeat_at' => $isLive
                ? gmdate('Y-m-d H:i:s')
                : null,
            'public_token_hash' => $tokenHash,
            'token_expires_at' => gmdate('Y-m-d H:i:s', $now + ($tokenMinutes * 60)),
            'expires_at' => $isLive ? gmdate('Y-m-d H:i:s', $now + $ringSeconds) : null,
            'ip_address' => $ip,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
        $requestId = (int)$request['id'];

        db()->prepare(
            'INSERT INTO crm_activities
                (contact_id,activity_type,subject,body)
             VALUES
                (:contact_id,"call",:subject,:body)'
        )->execute([
            'contact_id' => $contactId,
            'subject' => $isLive
                ? 'Public browser call started'
                : (
                    $isCallback
                        ? 'Public callback requested'
                        : 'Public message received'
                ),
            'body' => $subject . "\n\n" . $message,
        ]);

        $pdo->commit();

        call_center_event(
            $requestId,
            $isLive
                ? 'public_call_ringing'
                : (
                    $isCallback
                        ? 'public_callback_requested'
                        : 'public_message_received'
                ),
            null,
            $message,
            [
                'guest_name' => $name,
                'guest_email' => $email !== '' ? $email : null,
                'preferred_at' => $preferredAt,
            ]
        );

        notification_create_for_role(
            'admin',
            'call',
            $isLive
                ? 'Incoming public call from ' . $name
                : (
                    $isCallback
                        ? 'Public callback request from ' . $name
                        : 'New public message from ' . $name
                ),
            $subject . ' — ' . $message,
            'portal/admin.php?view=call-center&request=' . $requestId,
            'call_center_request',
            $requestId,
            $isLive ? 'urgent' : 'high'
        );

        call_center_refresh_contact_stats($contactId);

        try {
            visitor_intelligence_attach_contact(
                $contactId,
                $isLive
                    ? 'call_started'
                    : (
                        $isCallback
                            ? 'callback_requested'
                            : 'public_message_submitted'
                    ),
                [
                    'event_label' => $subject,
                    'metadata' => [
                        'request_id' => $requestId,
                        'mode' => $mode,
                        'preferred_at' => $preferredAt,
                    ],
                ]
            );
        } catch (Throwable $trackingException) {
            error_log(
                'North Mountain Media public call attribution failed: '
                . $trackingException->getMessage()
            );
        }

        json_response([
            'ok' => true,
            'mode' => $mode,
            'request_id' => $requestId,
            'token' => $token,
            'status' => $isLive
                ? 'ringing'
                : ($isCallback ? 'queued' : 'new'),
            'ice_servers' => communication_safe_ice_servers(),
            'max_rings' => $isLive ? $maxRings : null,
            'ring_seconds' => $isLive ? $ringSeconds : null,
            'greeting_url' => call_center_public_greeting_url(),
            'message' => $isLive
                ? 'Dave has been notified. Your browser call is ringing.'
                : (
                    $isCallback
                        ? 'Your message and callback request were added to Dave’s Call Center.'
                        : 'Your message was delivered to Dave’s Call Center.'
                ),
        ]);
    }

    $requestId = (int)($payload['request_id'] ?? 0);
    $token = trim((string)($payload['token'] ?? ''));
    $request = public_call_token_request($requestId, $token);

    if ($action === 'poll') {
        if (in_array($request['status'], ['ringing', 'accepted'], true)) {
            $sessionMinutes = max(
                30,
                min(
                    480,
                    (int)(
                        call_center_config()['public_session_minutes']
                        ?? 180
                    )
                )
            );

            db()->prepare(
                'UPDATE call_center_requests
                 SET guest_heartbeat_at=UTC_TIMESTAMP(),
                     token_expires_at=GREATEST(
                         token_expires_at,
                         UTC_TIMESTAMP()+INTERVAL ' .
                         $sessionMinutes . ' MINUTE
                     )
                 WHERE id=:id
                   AND public_token_hash=:token_hash
                   AND status IN ("ringing","accepted")'
            )->execute([
                'id' => $requestId,
                'token_hash' => hash('sha256', $token),
            ]);
        }

        call_center_expire_public_calls();
        $request = public_call_token_request($requestId, $token);
        $afterSignalId = max(0, (int)($payload['after_signal_id'] ?? 0));
        $signals = [];

        if (in_array($request['status'], ['ringing', 'accepted'], true)) {
            $signalStatement = db()->prepare(
                'SELECT id,signal_type,payload_json,created_at
                 FROM call_center_signals
                 WHERE request_id=:request_id
                   AND sender_side="admin"
                   AND id>:after_signal_id
                 ORDER BY id ASC
                 LIMIT 300'
            );
            $signalStatement->execute([
                'request_id' => $requestId,
                'after_signal_id' => $afterSignalId,
            ]);

            foreach ($signalStatement->fetchAll() as $signal) {
                $signals[] = [
                    'id' => (int)$signal['id'],
                    'type' => $signal['signal_type'],
                    'payload' => json_decode((string)$signal['payload_json'], true),
                    'created_at' => $signal['created_at'],
                ];
            }
        }

        json_response([
            'ok' => true,
            'request' => call_center_public_payload($request),
            'signals' => $signals,
        ]);
    }

    if ($action === 'post_signal') {
        $signalType = trim((string)($payload['signal_type'] ?? ''));
        $signalPayload = $payload['signal'] ?? null;

        if (!in_array($request['status'], ['ringing', 'accepted'], true)) {
            throw new RuntimeException('The public call is no longer active.');
        }

        if (!in_array($signalType, ['offer', 'answer', 'ice', 'hangup'], true)) {
            throw new RuntimeException('Invalid call signal.');
        }

        if (!is_array($signalPayload)) {
            throw new RuntimeException('Invalid call signal payload.');
        }

        $encoded = json_encode(
            $signalPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($encoded === false || strlen($encoded) > 131072) {
            throw new RuntimeException('The call signal is too large.');
        }

        db()->prepare(
            'INSERT INTO call_center_signals
                (request_id,sender_side,signal_type,payload_json)
             VALUES
                (:request_id,"guest",:signal_type,:payload_json)'
        )->execute([
            'request_id' => $requestId,
            'signal_type' => $signalType,
            'payload_json' => $encoded,
        ]);

        json_response([
            'ok' => true,
            'signal_id' => (int)db()->lastInsertId(),
        ]);
    }

    if ($action === 'end') {
        if (!in_array($request['status'], ['ringing', 'accepted'], true)) {
            json_response([
                'ok' => true,
                'request' => call_center_public_payload($request),
            ]);
        }

        $duration = public_call_duration($request);
        $newStatus = $request['status'] === 'accepted'
            ? 'completed'
            : 'cancelled';

        db()->prepare(
            'UPDATE call_center_requests
             SET status=:status,
                 disposition=:disposition,
                 ended_at=UTC_TIMESTAMP(),
                 duration_seconds=:duration_seconds,
                 last_contact_at=UTC_TIMESTAMP()
             WHERE id=:id
               AND public_token_hash=:token_hash
               AND status IN ("ringing","accepted")'
        )->execute([
            'status' => $newStatus,
            'disposition' => $newStatus === 'completed'
                ? 'connected'
                : 'no_answer',
            'duration_seconds' => $duration,
            'id' => $requestId,
            'token_hash' => hash('sha256', $token),
        ]);

        call_center_event(
            $requestId,
            'public_caller_ended',
            null,
            'The public caller ended the browser call.',
            ['duration_seconds' => $duration]
        );

        if (!empty($request['crm_contact_id'])) {
            call_center_refresh_contact_stats((int)$request['crm_contact_id']);
        }

        json_response([
            'ok' => true,
            'request' => call_center_public_payload(
                call_center_request($requestId) ?: $request
            ),
        ]);
    }

    throw new RuntimeException('Unsupported public call action.');
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

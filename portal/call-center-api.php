<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/call-center.php';

$user = current_user();

if (!$user || !in_array($user['role'], ['admin', 'client'], true)) {
    json_response(['ok' => false, 'message' => 'Authentication required.'], 401);
}

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!same_origin_request()) {
    json_response(['ok' => false, 'message' => 'Invalid request origin.'], 403);
}

verify_csrf();
enforce_authenticated_action_limit($user);

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = str_contains($contentType, 'application/json')
    ? json_decode((string)file_get_contents('php://input'), true)
    : $_POST;

if (!is_array($payload)) {
    json_response(['ok' => false, 'message' => 'Invalid request.'], 400);
}

$action = trim((string)($payload['action'] ?? ''));

function call_center_api_int(array $payload, string $key): int
{
    $value = filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT);
    return $value === false || $value === null ? 0 : (int)$value;
}

function call_center_api_datetime(?string $value): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);

    try {
        $date = new DateTimeImmutable($value);
        return $date->format('Y-m-d H:i:s');
    } catch (Throwable) {
        throw new RuntimeException('Enter a valid date and time.');
    }
}

function call_center_api_request_for_user(int $requestId, array $user): array
{
    $request = call_center_request($requestId);

    if (!$request) {
        throw new RuntimeException('Call Center record not found.');
    }

    if (
        $user['role'] === 'client'
        && (int)$request['client_user_id'] !== (int)$user['id']
    ) {
        throw new RuntimeException('This call request is not available to your account.');
    }

    return $request;
}

function call_center_api_duration(array $request): int
{
    if (empty($request['answered_at'])) {
        return 0;
    }

    try {
        $start = new DateTimeImmutable(
            (string)$request['answered_at'],
            new DateTimeZone('UTC')
        );
        return max(0, time() - $start->getTimestamp());
    } catch (Throwable) {
        return 0;
    }
}

try {
    if ($action === 'client_request_call') {
        if ($user['role'] !== 'client') {
            throw new RuntimeException('Only client accounts can use this call-request form.');
        }

        $subject = trim((string)($payload['subject'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));
        $priority = trim((string)($payload['priority'] ?? 'normal'));
        $preferredAt = call_center_api_datetime($payload['preferred_at'] ?? null);

        if ($subject === '' || strlen($subject) > 190) {
            throw new RuntimeException('Enter a call topic under 190 characters.');
        }

        if ($message === '' || strlen($message) > 8000) {
            throw new RuntimeException('Enter a message under 8,000 characters.');
        }

        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $contactStatement = db()->prepare(
            'SELECT id
             FROM crm_contacts
             WHERE client_user_id=:client_user_id
             ORDER BY id
             LIMIT 1'
        );
        $contactStatement->execute(['client_user_id' => $user['id']]);
        $contactId = (int)($contactStatement->fetchColumn() ?: 0);

        $threadStatement = db()->prepare(
            'SELECT id
             FROM communication_threads
             WHERE client_user_id=:client_user_id
               AND status<>"archived"
             ORDER BY COALESCE(last_message_at,created_at) DESC
             LIMIT 1'
        );
        $threadStatement->execute(['client_user_id' => $user['id']]);
        $threadId = (int)($threadStatement->fetchColumn() ?: 0);

        if ($threadId <= 0) {
            $threadId = communication_create_thread(
                $user,
                (int)$user['id'],
                null,
                'Call requests and follow-up'
            );
        }

        $adminId = call_center_default_admin_id();

        $request = call_center_create_request([
            'source' => 'client',
            'request_type' => 'call_request',
            'client_user_id' => (int)$user['id'],
            'crm_contact_id' => $contactId > 0 ? $contactId : null,
            'communication_thread_id' => $threadId,
            'assigned_admin_user_id' => $adminId > 0 ? $adminId : null,
            'requested_by_user_id' => (int)$user['id'],
            'subject' => $subject,
            'message' => $message,
            'preferred_at' => $preferredAt,
            'priority' => $priority,
            'status' => 'queued',
            'queued_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $requestId = (int)$request['id'];

        $messageBody = "Call request: {$subject}\n\n{$message}";
        if ($preferredAt) {
            $messageBody .= "\n\nPreferred time: {$preferredAt} UTC";
        }

        communication_insert_message(
            $threadId,
            (int)$user['id'],
            'client',
            'text',
            $messageBody
        );

        call_center_event(
            $requestId,
            'client_call_request_submitted',
            (int)$user['id'],
            $message,
            ['preferred_at' => $preferredAt, 'priority' => $priority]
        );

        if ($adminId > 0) {
            notification_create(
                $adminId,
                'call',
                'Client call request: ' . $subject,
                $user['display_name'] . ' requested a call. ' . $message,
                'portal/admin.php?view=call-center&request=' . $requestId,
                'call_center_request',
                $requestId,
                in_array($priority, ['high', 'urgent'], true) ? $priority : 'normal'
            );
        } else {
            notification_create_for_role(
                'admin',
                'call',
                'Client call request: ' . $subject,
                $user['display_name'] . ' requested a call. ' . $message,
                'portal/admin.php?view=call-center&request=' . $requestId,
                'call_center_request',
                $requestId,
                $priority
            );
        }

        communication_log_crm_activity(
            $threadId,
            'call',
            'Client call requested',
            $message,
            null
        );

        json_response([
            'ok' => true,
            'request_id' => $requestId,
            'message' => 'Your call request was sent to Dave.',
        ]);
    }

    if ($user['role'] !== 'admin') {
        throw new RuntimeException('Administrator access is required.');
    }

    if ($action === 'set_line_status') {
        $status = trim((string)($payload['public_call_status'] ?? 'offline'));
        $message = trim((string)($payload['public_call_message'] ?? ''));
        $maxRings = call_center_api_int(
            $payload,
            'public_call_max_rings'
        );

        if (!in_array($status, ['available', 'busy', 'offline'], true)) {
            throw new RuntimeException('Select a valid public line status.');
        }

        if (strlen($message) > 500) {
            throw new RuntimeException('The public line message is too long.');
        }

        if ($maxRings < 1 || $maxRings > 12) {
            throw new RuntimeException(
                'Max rings must be between 1 and 12.'
            );
        }

        $statement = db()->prepare(
            'INSERT INTO settings(setting_key,setting_value)
             VALUES(:setting_key,:setting_value)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
        );
        $statement->execute([
            'setting_key' => 'public_call_status',
            'setting_value' => $status,
        ]);
        $statement->execute([
            'setting_key' => 'public_call_message',
            'setting_value' => $message,
        ]);
        $statement->execute([
            'setting_key' => 'public_call_max_rings',
            'setting_value' => (string)$maxRings,
        ]);

        log_activity(
            'public_call_status_updated',
            'settings',
            null,
            [
                'status' => $status,
                'max_rings' => $maxRings,
            ]
        );

        json_response([
            'ok' => true,
            'status' => $status,
            'max_rings' => $maxRings,
            'ring_seconds' => (
                $maxRings
                * call_center_ring_cycle_seconds()
            ),
            'message' => 'Public line status and max rings updated.',
        ]);
    }

    if ($action === 'update_request') {
        $requestId = call_center_api_int($payload, 'request_id');
        $request = call_center_api_request_for_user($requestId, $user);
        $status = trim((string)($payload['status'] ?? $request['status']));
        $disposition = trim((string)($payload['disposition'] ?? $request['disposition']));
        $priority = trim((string)($payload['priority'] ?? $request['priority']));
        $adminId = call_center_api_int($payload, 'assigned_admin_user_id');
        $preferredAt = call_center_api_datetime($payload['preferred_at'] ?? null);
        $adminNotes = trim((string)($payload['admin_notes'] ?? ''));
        $transcript = trim((string)($payload['transcript_text'] ?? ''));

        $statuses = [
            'new','queued','scheduled','ringing','accepted','completed',
            'missed','declined','cancelled','failed','voicemail','resolved','spam'
        ];
        $dispositions = [
            'unassigned','connected','callback_scheduled','left_message',
            'no_answer','not_available','declined','resolved','spam'
        ];

        if (!in_array($status, $statuses, true)) {
            throw new RuntimeException('Select a valid call status.');
        }

        if (!in_array($disposition, $dispositions, true)) {
            throw new RuntimeException('Select a valid call disposition.');
        }

        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        if (strlen($adminNotes) > 50000 || strlen($transcript) > 100000) {
            throw new RuntimeException('The notes or transcript are too long.');
        }

        $firstResponse = $request['first_response_at'];
        if (!$firstResponse && !in_array($status, ['new', 'queued', 'ringing'], true)) {
            $firstResponse = gmdate('Y-m-d H:i:s');
        }

        $endedAt = $request['ended_at'];
        if (
            !$endedAt
            && in_array(
                $status,
                ['completed','missed','declined','cancelled','failed','resolved','spam'],
                true
            )
        ) {
            $endedAt = gmdate('Y-m-d H:i:s');
        }

        db()->prepare(
            'UPDATE call_center_requests
             SET status=:status,
                 disposition=:disposition,
                 priority=:priority,
                 assigned_admin_user_id=:assigned_admin_user_id,
                 preferred_at=:preferred_at,
                 first_response_at=:first_response_at,
                 ended_at=:ended_at,
                 admin_notes=:admin_notes,
                 transcript_text=:transcript_text,
                 last_contact_at=CASE
                    WHEN :contacted=1 THEN UTC_TIMESTAMP()
                    ELSE last_contact_at
                 END
             WHERE id=:id'
        )->execute([
            'status' => $status,
            'disposition' => $disposition,
            'priority' => $priority,
            'assigned_admin_user_id' => $adminId > 0 ? $adminId : null,
            'preferred_at' => $preferredAt,
            'first_response_at' => $firstResponse,
            'ended_at' => $endedAt,
            'admin_notes' => $adminNotes !== '' ? $adminNotes : null,
            'transcript_text' => $transcript !== '' ? $transcript : null,
            'contacted' => in_array(
                $status,
                ['scheduled','accepted','completed','resolved'],
                true
            ) ? 1 : 0,
            'id' => $requestId,
        ]);

        notification_mark_entity_read(
            'call_center_request',
            $requestId
        );

        call_center_event(
            $requestId,
            'call_record_updated',
            (int)$user['id'],
            $adminNotes !== '' ? $adminNotes : null,
            [
                'status' => $status,
                'disposition' => $disposition,
                'priority' => $priority,
            ]
        );

        if (!empty($request['crm_contact_id'])) {
            call_center_refresh_contact_stats((int)$request['crm_contact_id']);

            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,admin_user_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:admin_user_id,"call",:subject,:body)'
            )->execute([
                'contact_id' => $request['crm_contact_id'],
                'admin_user_id' => $user['id'],
                'subject' => 'Call Center record: ' . status_label($status),
                'body' => $adminNotes !== '' ? $adminNotes : $transcript,
            ]);
        }

        if (!empty($request['client_user_id'])) {
            notification_create(
                (int)$request['client_user_id'],
                'call',
                'Call request updated',
                'Your call request "' . $request['subject'] . '" is now ' .
                    strtolower(status_label($status)) . '.',
                'portal/client.php?view=call-center',
                'call_center_request',
                $requestId,
                'normal'
            );
        }

        json_response([
            'ok' => true,
            'request' => call_center_request($requestId),
            'message' => 'Call record saved.',
        ]);
    }

    if ($action === 'save_media_transcript') {
        $requestId = call_center_api_int($payload, 'request_id');
        $mediaId = call_center_api_int($payload, 'media_id');
        $requestRecord = call_center_api_request_for_user(
            $requestId,
            $user
        );
        $media = call_center_media($mediaId);

        if (
            !$media
            || (int)$media['request_id'] !== $requestId
        ) {
            throw new RuntimeException(
                'The voicemail media record was not found.'
            );
        }

        $reviewedText = trim(
            (string)($payload['reviewed_transcript_text'] ?? '')
        );
        $rawText = trim(
            (string)($payload['raw_transcript_text'] ?? '')
        );
        $status = trim(
            (string)($payload['transcript_status'] ?? 'review')
        );

        if (!in_array($status, ['review', 'approved'], true)) {
            $status = 'review';
        }

        if (
            strlen($reviewedText) > 100000
            || strlen($rawText) > 100000
        ) {
            throw new RuntimeException(
                'The voicemail transcript is too long.'
            );
        }

        if ($status === 'approved' && $reviewedText === '') {
            throw new RuntimeException(
                'Enter reviewed transcript text before approval.'
            );
        }

        db()->prepare(
            'UPDATE call_center_media
             SET raw_transcript_text=:raw_transcript_text,
                 reviewed_transcript_text=:reviewed_transcript_text,
                 transcript_status=:transcript_status,
                 transcription_source="manual",
                 transcription_error=NULL,
                 reviewed_by_user_id=:reviewed_by_user_id,
                 reviewed_at=CASE
                    WHEN :approved=1 THEN UTC_TIMESTAMP()
                    ELSE reviewed_at
                 END
             WHERE id=:id
               AND request_id=:request_id'
        )->execute([
            'raw_transcript_text' =>
                $rawText !== '' ? $rawText : null,
            'reviewed_transcript_text' =>
                $reviewedText !== '' ? $reviewedText : null,
            'transcript_status' => $status,
            'reviewed_by_user_id' => $user['id'],
            'approved' => $status === 'approved' ? 1 : 0,
            'id' => $mediaId,
            'request_id' => $requestId,
        ]);

        if ($reviewedText !== '') {
            db()->prepare(
                'UPDATE call_center_requests
                 SET transcript_text=:transcript_text
                 WHERE id=:id'
            )->execute([
                'transcript_text' => $reviewedText,
                'id' => $requestId,
            ]);
        }

        call_center_event(
            $requestId,
            $status === 'approved'
                ? 'voicemail_transcript_approved'
                : 'voicemail_transcript_saved',
            (int)$user['id'],
            $reviewedText !== ''
                ? substr($reviewedText, 0, 1000)
                : null,
            [
                'media_id' => $mediaId,
                'transcript_status' => $status,
            ]
        );

        if (!empty($requestRecord['crm_contact_id'])) {
            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,admin_user_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:admin_user_id,"call",:subject,:body)'
            )->execute([
                'contact_id' => $requestRecord['crm_contact_id'],
                'admin_user_id' => $user['id'],
                'subject' => $status === 'approved'
                    ? 'Voicemail transcript approved'
                    : 'Voicemail transcript updated',
                'body' => $reviewedText !== ''
                    ? $reviewedText
                    : $rawText,
            ]);
        }

        json_response([
            'ok' => true,
            'media' => call_center_media($mediaId),
            'message' => $status === 'approved'
                ? 'Voicemail transcript approved and added to the CRM history.'
                : 'Voicemail transcript saved for review.',
        ]);
    }

    if ($action === 'log_attempt') {
        $requestId = call_center_api_int($payload, 'request_id');
        $request = call_center_api_request_for_user($requestId, $user);
        $notes = trim((string)($payload['notes'] ?? 'Contact attempt recorded.'));

        db()->prepare(
            'UPDATE call_center_requests
             SET attempt_count=attempt_count+1,
                 last_contact_at=UTC_TIMESTAMP(),
                 first_response_at=COALESCE(first_response_at,UTC_TIMESTAMP())
             WHERE id=:id'
        )->execute(['id' => $requestId]);

        call_center_event(
            $requestId,
            'contact_attempt',
            (int)$user['id'],
            $notes
        );

        if (!empty($request['crm_contact_id'])) {
            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,admin_user_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:admin_user_id,"call","Call attempt",:body)'
            )->execute([
                'contact_id' => $request['crm_contact_id'],
                'admin_user_id' => $user['id'],
                'body' => $notes,
            ]);
            call_center_refresh_contact_stats((int)$request['crm_contact_id']);
        }

        json_response([
            'ok' => true,
            'request' => call_center_request($requestId),
            'message' => 'Contact attempt recorded.',
        ]);
    }

    if ($action === 'poll_admin') {
        $activeRequestId = call_center_api_int($payload, 'request_id');
        $afterSignalId = call_center_api_int($payload, 'after_signal_id');
        $activeRequest = $activeRequestId > 0
            ? call_center_request($activeRequestId)
            : null;

        if (
            $activeRequest
            && $activeRequest['source'] === 'public'
            && in_array(
                $activeRequest['status'],
                ['ringing', 'accepted'],
                true
            )
        ) {
            db()->prepare(
                'UPDATE call_center_requests
                 SET admin_heartbeat_at=UTC_TIMESTAMP()
                 WHERE id=:id
                   AND status IN ("ringing","accepted")'
            )->execute(['id' => $activeRequestId]);
        }

        call_center_expire_public_calls();
        $activeRequest = $activeRequestId > 0
            ? call_center_request($activeRequestId)
            : null;

        $ringingStatement = db()->query(
            'SELECT id
             FROM call_center_requests
             WHERE source="public"
               AND request_type="live_call"
               AND status="ringing"
               AND expires_at>=UTC_TIMESTAMP()
             ORDER BY priority="urgent" DESC,
                      priority="high" DESC,
                      ringing_at ASC
             LIMIT 1'
        );
        $ringingId = (int)($ringingStatement->fetchColumn() ?: 0);
        $ringing = $ringingId > 0 ? call_center_request($ringingId) : null;
        $signals = [];

        if (
            $activeRequest
            && $activeRequest['source'] === 'public'
            && in_array($activeRequest['status'], ['ringing', 'accepted'], true)
        ) {
            $signalStatement = db()->prepare(
                'SELECT id,signal_type,payload_json,created_at
                 FROM call_center_signals
                 WHERE request_id=:request_id
                   AND sender_side="guest"
                   AND id>:after_signal_id
                 ORDER BY id ASC
                 LIMIT 300'
            );
            $signalStatement->execute([
                'request_id' => $activeRequestId,
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
            'ringing' => $ringing,
            'active_request' => $activeRequest,
            'signals' => $signals,
            'unread_count' => notification_unread_count((int)$user['id']),
        ]);
    }

    if ($action === 'accept_public_call') {
        $requestId = call_center_api_int($payload, 'request_id');
        $request = call_center_api_request_for_user($requestId, $user);

        if (
            $request['source'] !== 'public'
            || $request['request_type'] !== 'live_call'
            || $request['status'] !== 'ringing'
        ) {
            throw new RuntimeException('This public call can no longer be answered.');
        }

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

        $acceptStatement = db()->prepare(
            'UPDATE call_center_requests
             SET status="accepted",
                 disposition="connected",
                 assigned_admin_user_id=:admin_user_id,
                 first_response_at=COALESCE(first_response_at,UTC_TIMESTAMP()),
                 answered_at=UTC_TIMESTAMP(),
                 admin_heartbeat_at=UTC_TIMESTAMP(),
                 token_expires_at=UTC_TIMESTAMP()+INTERVAL ' .
                 $sessionMinutes . ' MINUTE,
                 attempt_count=attempt_count+1,
                 last_contact_at=UTC_TIMESTAMP()
             WHERE id=:id
               AND status="ringing"'
        );
        $acceptStatement->execute([
            'admin_user_id' => $user['id'],
            'id' => $requestId,
        ]);

        if ($acceptStatement->rowCount() !== 1) {
            throw new RuntimeException(
                'Another administrator already handled this public call.'
            );
        }

        notification_mark_entity_read(
            'call_center_request',
            $requestId
        );

        call_center_event(
            $requestId,
            'public_call_answered',
            (int)$user['id'],
            $user['display_name'] . ' answered the public browser call.'
        );

        if (!empty($request['crm_contact_id'])) {
            call_center_refresh_contact_stats((int)$request['crm_contact_id']);
        }

        json_response([
            'ok' => true,
            'request' => call_center_request($requestId),
            'ice_servers' => communication_safe_ice_servers(),
        ]);
    }

    if ($action === 'decline_public_call') {
        $requestId = call_center_api_int($payload, 'request_id');
        $request = call_center_api_request_for_user($requestId, $user);

        if (
            $request['source'] !== 'public'
            || $request['request_type'] !== 'live_call'
            || $request['status'] !== 'ringing'
        ) {
            throw new RuntimeException('This public call can no longer be declined.');
        }

        $declineStatement = db()->prepare(
            'UPDATE call_center_requests
             SET status="declined",
                 disposition="declined",
                 assigned_admin_user_id=:admin_user_id,
                 first_response_at=COALESCE(first_response_at,UTC_TIMESTAMP()),
                 ended_at=UTC_TIMESTAMP(),
                 attempt_count=attempt_count+1,
                 last_contact_at=UTC_TIMESTAMP()
             WHERE id=:id
               AND status="ringing"'
        );
        $declineStatement->execute([
            'admin_user_id' => $user['id'],
            'id' => $requestId,
        ]);

        if ($declineStatement->rowCount() !== 1) {
            throw new RuntimeException(
                'Another administrator already handled this public call.'
            );
        }

        notification_mark_entity_read(
            'call_center_request',
            $requestId
        );

        call_center_event(
            $requestId,
            'public_call_declined',
            (int)$user['id'],
            'The public browser call was declined.'
        );

        if (!empty($request['crm_contact_id'])) {
            call_center_refresh_contact_stats((int)$request['crm_contact_id']);
        }

        json_response([
            'ok' => true,
            'request' => call_center_request($requestId),
        ]);
    }

    if ($action === 'post_public_signal') {
        $requestId = call_center_api_int($payload, 'request_id');
        $request = call_center_api_request_for_user($requestId, $user);
        $signalType = trim((string)($payload['signal_type'] ?? ''));
        $signalPayload = $payload['signal'] ?? null;

        if (
            $request['source'] !== 'public'
            || !in_array($request['status'], ['ringing', 'accepted'], true)
        ) {
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
                (:request_id,"admin",:signal_type,:payload_json)'
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

    if ($action === 'end_public_call') {
        $requestId = call_center_api_int($payload, 'request_id');
        $request = call_center_api_request_for_user($requestId, $user);

        if (
            $request['source'] !== 'public'
            || !in_array($request['status'], ['ringing', 'accepted'], true)
        ) {
            throw new RuntimeException('The public call has already ended.');
        }

        $duration = call_center_api_duration($request);
        $newStatus = $request['status'] === 'accepted' ? 'completed' : 'cancelled';
        $disposition = $newStatus === 'completed' ? 'connected' : 'no_answer';

        db()->prepare(
            'UPDATE call_center_requests
             SET status=:status,
                 disposition=:disposition,
                 ended_at=UTC_TIMESTAMP(),
                 duration_seconds=:duration_seconds,
                 last_contact_at=UTC_TIMESTAMP()
             WHERE id=:id
               AND status IN ("ringing","accepted")'
        )->execute([
            'status' => $newStatus,
            'disposition' => $disposition,
            'duration_seconds' => $duration,
            'id' => $requestId,
        ]);

        notification_mark_entity_read(
            'call_center_request',
            $requestId
        );

        call_center_event(
            $requestId,
            'public_call_ended',
            (int)$user['id'],
            'Public browser call ended after ' . call_center_seconds_label($duration) . '.',
            ['duration_seconds' => $duration]
        );

        if (!empty($request['crm_contact_id'])) {
            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,admin_user_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:admin_user_id,"call","Public browser call completed",:body)'
            )->execute([
                'contact_id' => $request['crm_contact_id'],
                'admin_user_id' => $user['id'],
                'body' => 'Duration: ' . call_center_seconds_label($duration),
            ]);
            call_center_refresh_contact_stats((int)$request['crm_contact_id']);
        }

        json_response([
            'ok' => true,
            'request' => call_center_request($requestId),
        ]);
    }

    throw new RuntimeException('Unsupported Call Center action.');
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

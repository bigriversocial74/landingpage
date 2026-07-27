<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/communications.php';
require_once __DIR__ . '/call-center.php';

$user = current_user();

if (!$user || !in_array($user['role'], ['admin', 'client'], true)) {
    json_response([
        'ok' => false,
        'message' => 'Authentication required.',
    ], 401);
}

if (!communication_enabled()) {
    json_response([
        'ok' => false,
        'message' => 'Communications are disabled.',
    ], 503);
}

if (!is_post()) {
    json_response([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

if (!same_origin_request()) {
    json_response([
        'ok' => false,
        'message' => 'Invalid request origin.',
    ], 403);
}

verify_csrf();
enforce_authenticated_action_limit($user);

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = str_contains($contentType, 'application/json')
    ? json_decode((string)file_get_contents('php://input'), true)
    : $_POST;

if (!is_array($payload)) {
    json_response([
        'ok' => false,
        'message' => 'Invalid request payload.',
    ], 400);
}

$action = trim((string)($payload['action'] ?? ''));

function communication_api_int(
    array $payload,
    string $key,
    int $default = 0
): int {
    $value = filter_var(
        $payload[$key] ?? null,
        FILTER_VALIDATE_INT
    );

    return ($value === false || $value === null)
        ? $default
        : (int)$value;
}

function communication_api_bool(
    array $payload,
    string $key
): bool {
    return filter_var(
        $payload[$key] ?? false,
        FILTER_VALIDATE_BOOL
    );
}

function communication_api_call_payload(array $call, array $user): array
{
    $isInitiator = (int)$call['initiator_user_id'] === (int)$user['id'];
    $otherName = $isInitiator
        ? (string)$call['recipient_name']
        : (string)$call['initiator_name'];

    $ownConsent = $isInitiator
        ? (string)$call['initiator_recording_consent']
        : (string)$call['recipient_recording_consent'];
    $otherConsent = $isInitiator
        ? (string)$call['recipient_recording_consent']
        : (string)$call['initiator_recording_consent'];

    return [
        'id' => (int)$call['id'],
        'thread_id' => (int)$call['thread_id'],
        'initiator_user_id' => (int)$call['initiator_user_id'],
        'recipient_user_id' => (int)$call['recipient_user_id'],
        'is_initiator' => $isInitiator,
        'other_name' => $otherName,
        'status' => (string)$call['status'],
        'recording_status' => (string)$call['recording_status'],
        'own_recording_consent' => $ownConsent,
        'other_recording_consent' => $otherConsent,
        'ringing_at' => $call['ringing_at'],
        'expires_at' => $call['expires_at'],
        'answered_at' => $call['answered_at'],
        'ended_at' => $call['ended_at'],
        'duration_seconds' => communication_call_duration($call),
    ];
}

try {
    if ($action === 'create_thread') {
        $clientUserId = communication_api_int($payload, 'client_user_id');
        $projectId = communication_api_int($payload, 'project_id');
        $subject = trim((string)($payload['subject'] ?? ''));
        $body = trim((string)($payload['body'] ?? ''));

        $threadId = communication_create_thread(
            $user,
            $clientUserId,
            $projectId > 0 ? $projectId : null,
            $subject
        );

        if ($body !== '') {
            if (strlen($body) > 12000) {
                throw new RuntimeException('The opening message is too long.');
            }

            communication_insert_message(
                $threadId,
                (int)$user['id'],
                (string)$user['role'],
                'text',
                $body
            );
        }

        json_response([
            'ok' => true,
            'thread_id' => $threadId,
            'redirect' => app_url(
                'portal/' .
                ($user['role'] === 'admin' ? 'admin.php' : 'client.php') .
                '?view=communications&thread=' . $threadId
            ),
        ]);
    }

    $threadId = communication_api_int($payload, 'thread_id');

    if ($threadId <= 0 && !in_array($action, ['poll_global'], true)) {
        throw new RuntimeException('Select a communication thread.');
    }

    $thread = $threadId > 0
        ? communication_require_thread($threadId, $user)
        : null;

    if ($action === 'send_message') {
        $body = trim((string)($payload['body'] ?? ''));
        $internal = $user['role'] === 'admin'
            && communication_api_bool($payload, 'internal_note');

        if ($body === '' || strlen($body) > 12000) {
            throw new RuntimeException('Enter a message under 12,000 characters.');
        }

        $messageId = communication_insert_message(
            $threadId,
            (int)$user['id'],
            (string)$user['role'],
            $internal ? 'internal_note' : 'text',
            $body,
            null,
            null,
            null,
            $internal ? 'admin' : 'client'
        );

        communication_log_crm_activity(
            $threadId,
            $internal ? 'note' : 'email',
            $internal
                ? 'Administrator communication note'
                : ($user['role'] === 'admin'
                    ? 'Portal message sent'
                    : 'Portal message received'),
            $body,
            $user['role'] === 'admin' ? (int)$user['id'] : null
        );

        $messageRecipientId = $user['role'] === 'admin'
            ? (int)$thread['client_user_id']
            : (int)($thread['assigned_admin_user_id'] ?: communication_default_admin_id());

        if ($messageRecipientId > 0 && !$internal) {
            notification_create(
                $messageRecipientId,
                'message',
                'New message: ' . $thread['subject'],
                $user['display_name'] . ': ' . substr($body, 0, 350),
                'portal/' .
                    ($user['role'] === 'admin' ? 'client.php' : 'admin.php') .
                    '?view=communications&thread=' . $threadId,
                'communication_message',
                $messageId,
                'normal'
            );
        }

        log_activity(
            'communication_message_sent',
            'communication_message',
            $messageId,
            [
                'thread_id' => $threadId,
                'internal_note' => $internal,
            ]
        );

        json_response([
            'ok' => true,
            'message_id' => $messageId,
        ]);
    }

    if ($action === 'poll' || $action === 'poll_global') {
        $afterMessageId = communication_api_int(
            $payload,
            'after_message_id'
        );
        $callId = communication_api_int($payload, 'call_id');
        $afterSignalId = communication_api_int(
            $payload,
            'after_signal_id'
        );

        $messages = [];

        if ($threadId > 0) {
            $messages = communication_messages(
                $threadId,
                $user,
                $afterMessageId,
                200
            );

            $lastMessageId = $afterMessageId;

            foreach ($messages as $message) {
                $lastMessageId = max(
                    $lastMessageId,
                    (int)$message['id']
                );
            }

            communication_mark_read(
                $threadId,
                $user,
                $lastMessageId > 0 ? $lastMessageId : null
            );
        }

        $polledCall = null;

        if ($callId > 0) {
            $polledCall = communication_require_call($callId, $user);

            if (
                $threadId > 0
                && (int)$polledCall['thread_id'] !== $threadId
            ) {
                throw new RuntimeException(
                    'The audio call does not belong to this conversation.'
                );
            }

            if (in_array(
                $polledCall['status'],
                ['ringing', 'accepted'],
                true
            )) {
                db()->prepare(
                    'UPDATE communication_calls
                     SET updated_at = UTC_TIMESTAMP()
                     WHERE id = :id
                       AND status IN ("ringing", "accepted")'
                )->execute(['id' => $callId]);
            }
        }

        $activeCall = $threadId > 0
            ? communication_active_call_for_thread($threadId, $user)
            : null;
        $incomingCall = communication_incoming_call($user);
        $signals = [];

        if (
            $callId > 0
            && $polledCall
            && in_array(
                $polledCall['status'],
                ['ringing', 'accepted'],
                true
            )
        ) {
            $signalStatement = db()->prepare(
                'SELECT id, signal_type, payload_json, created_at
                 FROM communication_call_signals
                 WHERE call_id = :call_id
                   AND id > :after_signal_id
                   AND sender_user_id <> :user_id
                 ORDER BY id ASC
                 LIMIT 300'
            );
            $signalStatement->execute([
                'call_id' => $callId,
                'after_signal_id' => $afterSignalId,
                'user_id' => $user['id'],
            ]);

            foreach ($signalStatement->fetchAll() as $signal) {
                $signals[] = [
                    'id' => (int)$signal['id'],
                    'type' => (string)$signal['signal_type'],
                    'payload' => json_decode(
                        (string)$signal['payload_json'],
                        true
                    ),
                    'created_at' => $signal['created_at'],
                ];
            }
        }

        json_response([
            'ok' => true,
            'messages' => $messages,
            'active_call' => $activeCall
                ? communication_api_call_payload($activeCall, $user)
                : null,
            'incoming_call' => $incomingCall
                ? [
                    'id' => (int)$incomingCall['id'],
                    'thread_id' => (int)$incomingCall['thread_id'],
                    'subject' => (string)$incomingCall['subject'],
                    'initiator_name' => (string)$incomingCall['initiator_name'],
                    'expires_at' => $incomingCall['expires_at'],
                ]
                : null,
            'signals' => $signals,
            'server_time' => gmdate('c'),
        ]);
    }

    if ($action === 'create_call') {
        if ($user['role'] !== 'admin') {
            throw new RuntimeException(
                'Clients request calls through the Call Us page.'
            );
        }

        $config = communication_config();

        if (
            !isset($thread['assigned_admin_user_id'])
            || (int)$thread['assigned_admin_user_id'] <= 0
        ) {
            $adminId = communication_default_admin_id();

            if ($adminId <= 0) {
                throw new RuntimeException('No active administrator is available for calls.');
            }

            db()->prepare(
                'UPDATE communication_threads
                 SET assigned_admin_user_id = :admin_user_id
                 WHERE id = :id'
            )->execute([
                'admin_user_id' => $adminId,
                'id' => $threadId,
            ]);
            $thread['assigned_admin_user_id'] = $adminId;
            communication_ensure_member($threadId, $adminId, 'admin');
        }

        $recipientId = $user['role'] === 'admin'
            ? (int)$thread['client_user_id']
            : (int)$thread['assigned_admin_user_id'];

        if ($recipientId <= 0 || $recipientId === (int)$user['id']) {
            throw new RuntimeException('The call recipient is unavailable.');
        }

        $active = db()->prepare(
            'SELECT id
             FROM communication_calls
             WHERE status IN ("ringing", "accepted")
               AND (
                   thread_id = :thread_id
                   OR initiator_user_id IN (:current_user_id, :recipient_user_id)
                   OR recipient_user_id IN (:current_user_id_2, :recipient_user_id_2)
               )
             LIMIT 1'
        );
        $active->execute([
            'thread_id' => $threadId,
            'current_user_id' => $user['id'],
            'recipient_user_id' => $recipientId,
            'current_user_id_2' => $user['id'],
            'recipient_user_id_2' => $recipientId,
        ]);

        if ($active->fetchColumn()) {
            throw new RuntimeException('Another call is already active in this conversation.');
        }

        $ringSeconds = max(
            20,
            min(120, (int)($config['ring_seconds'] ?? 45))
        );
        $expiresAt = gmdate(
            'Y-m-d H:i:s',
            time() + $ringSeconds
        );

        $statement = db()->prepare(
            'INSERT INTO communication_calls
                (thread_id, initiator_user_id, recipient_user_id, expires_at)
             VALUES
                (:thread_id, :initiator_user_id, :recipient_user_id, :expires_at)'
        );
        $statement->execute([
            'thread_id' => $threadId,
            'initiator_user_id' => $user['id'],
            'recipient_user_id' => $recipientId,
            'expires_at' => $expiresAt,
        ]);
        $callId = (int)db()->lastInsertId();
        call_center_sync_communication_call($callId);

        communication_insert_message(
            $threadId,
            (int)$user['id'],
            (string)$user['role'],
            'call_event',
            $user['display_name'] . ' started an audio call.',
            null,
            $callId
        );

        $call = communication_require_call($callId, $user);

        communication_log_crm_activity(
            $threadId,
            'call',
            'Audio call started',
            $user['display_name'] . ' started an audio call.',
            $user['role'] === 'admin' ? (int)$user['id'] : null
        );

        notification_create(
            $recipientId,
            'call',
            'Incoming portal audio call',
            $user['display_name'] . ' is calling about "' . $thread['subject'] . '".',
            'portal/' .
                ($user['role'] === 'admin' ? 'client.php' : 'admin.php') .
                '?view=communications&thread=' . $threadId,
            'communication_call',
            $callId,
            'urgent'
        );

        log_activity(
            'communication_call_started',
            'communication_call',
            $callId,
            ['thread_id' => $threadId]
        );

        json_response([
            'ok' => true,
            'call' => communication_api_call_payload($call, $user),
            'ice_servers' => communication_safe_ice_servers(),
        ]);
    }

    if (in_array(
        $action,
        ['accept_call', 'decline_call', 'cancel_call', 'end_call'],
        true
    )) {
        $callId = communication_api_int($payload, 'call_id');
        $call = communication_require_call($callId, $user);

        if ((int)$call['thread_id'] !== $threadId) {
            throw new RuntimeException(
                'The audio call does not belong to this conversation.'
            );
        }

        $isInitiator = (int)$call['initiator_user_id'] === (int)$user['id'];
        $isRecipient = (int)$call['recipient_user_id'] === (int)$user['id'];

        if ($action === 'accept_call') {
            if (!$isRecipient || $call['status'] !== 'ringing') {
                throw new RuntimeException('This call can no longer be accepted.');
            }

            db()->prepare(
                'UPDATE communication_calls
                 SET status = "accepted",
                     answered_at = UTC_TIMESTAMP()
                 WHERE id = :id
                   AND status = "ringing"'
            )->execute(['id' => $callId]);

            communication_insert_message(
                $threadId,
                (int)$user['id'],
                (string)$user['role'],
                'call_event',
                $user['display_name'] . ' answered the audio call.',
                null,
                $callId
            );
        }

        if ($action === 'decline_call') {
            if (!$isRecipient || $call['status'] !== 'ringing') {
                throw new RuntimeException('This call can no longer be declined.');
            }

            db()->prepare(
                'UPDATE communication_calls
                 SET status = "declined",
                     ended_at = UTC_TIMESTAMP(),
                     end_reason = "Declined"
                 WHERE id = :id
                   AND status = "ringing"'
            )->execute(['id' => $callId]);

            communication_insert_message(
                $threadId,
                (int)$user['id'],
                (string)$user['role'],
                'call_event',
                $user['display_name'] . ' declined the audio call.',
                null,
                $callId
            );
        }

        if ($action === 'cancel_call') {
            if (!$isInitiator || $call['status'] !== 'ringing') {
                throw new RuntimeException('This call can no longer be cancelled.');
            }

            db()->prepare(
                'UPDATE communication_calls
                 SET status = "cancelled",
                     ended_at = UTC_TIMESTAMP(),
                     end_reason = "Caller cancelled"
                 WHERE id = :id
                   AND status = "ringing"'
            )->execute(['id' => $callId]);

            communication_insert_message(
                $threadId,
                (int)$user['id'],
                (string)$user['role'],
                'call_event',
                'Audio call cancelled.',
                null,
                $callId
            );
        }

        if ($action === 'end_call') {
            if (
                (!$isInitiator && !$isRecipient)
                || !in_array($call['status'], ['ringing', 'accepted'], true)
            ) {
                throw new RuntimeException('This call has already ended.');
            }

            $duration = communication_call_duration($call);
            $newStatus = $call['status'] === 'ringing'
                ? 'cancelled'
                : 'ended';

            db()->prepare(
                'UPDATE communication_calls
                 SET status = :status,
                     ended_at = UTC_TIMESTAMP(),
                     duration_seconds = :duration_seconds,
                     end_reason = :end_reason
                 WHERE id = :id
                   AND status IN ("ringing", "accepted")'
            )->execute([
                'status' => $newStatus,
                'duration_seconds' => $duration,
                'end_reason' => $newStatus === 'ended'
                    ? 'Participant ended call'
                    : 'Call ended before answer',
                'id' => $callId,
            ]);

            communication_insert_message(
                $threadId,
                (int)$user['id'],
                (string)$user['role'],
                'call_event',
                $newStatus === 'ended'
                    ? 'Audio call ended after ' .
                        sprintf(
                            '%02d:%02d:%02d',
                            intdiv($duration, 3600),
                            intdiv($duration % 3600, 60),
                            $duration % 60
                        ) .
                        '.'
                    : 'Audio call ended before it was answered.',
                null,
                $callId
            );
        }

        $updated = communication_require_call($callId, $user);
        call_center_sync_communication_call($callId);

        if (in_array(
            $action,
            ['decline_call', 'cancel_call', 'end_call'],
            true
        )) {
            communication_log_crm_activity(
                $threadId,
                'call',
                'Audio call ' . status_label((string)$updated['status']),
                'Call status: ' . status_label((string)$updated['status']) .
                '. Duration: ' .
                sprintf(
                    '%02d:%02d:%02d',
                    intdiv((int)$updated['duration_seconds'], 3600),
                    intdiv(((int)$updated['duration_seconds']) % 3600, 60),
                    ((int)$updated['duration_seconds']) % 60
                ) .
                '.',
                $user['role'] === 'admin' ? (int)$user['id'] : null
            );
        }

        json_response([
            'ok' => true,
            'call' => communication_api_call_payload($updated, $user),
        ]);
    }

    if ($action === 'post_signal') {
        $callId = communication_api_int($payload, 'call_id');
        $call = communication_require_call($callId, $user);

        if ((int)$call['thread_id'] !== $threadId) {
            throw new RuntimeException(
                'The WebRTC signal does not belong to this conversation.'
            );
        }

        $signalType = trim((string)($payload['signal_type'] ?? ''));
        $signalPayload = $payload['signal'] ?? null;

        if (!in_array($signalType, ['offer', 'answer', 'ice', 'hangup'], true)) {
            throw new RuntimeException('Invalid WebRTC signal type.');
        }

        if (!in_array($call['status'], ['ringing', 'accepted'], true)) {
            throw new RuntimeException('The audio call is no longer active.');
        }

        if (!is_array($signalPayload)) {
            throw new RuntimeException('Invalid WebRTC signal payload.');
        }

        $encoded = json_encode(
            $signalPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($encoded === false || strlen($encoded) > 131072) {
            throw new RuntimeException('The WebRTC signal is too large.');
        }

        $statement = db()->prepare(
            'INSERT INTO communication_call_signals
                (call_id, sender_user_id, signal_type, payload_json)
             VALUES
                (:call_id, :sender_user_id, :signal_type, :payload_json)'
        );
        $statement->execute([
            'call_id' => $callId,
            'sender_user_id' => $user['id'],
            'signal_type' => $signalType,
            'payload_json' => $encoded,
        ]);

        json_response([
            'ok' => true,
            'signal_id' => (int)db()->lastInsertId(),
        ]);
    }

    if ($action === 'request_recording') {
        $callId = communication_api_int($payload, 'call_id');
        $call = communication_require_call($callId, $user);

        if ((int)$call['thread_id'] !== $threadId) {
            throw new RuntimeException(
                'The recording request does not belong to this conversation.'
            );
        }

        $config = communication_config();

        if (!(bool)($config['call_recording_enabled'] ?? false)) {
            throw new RuntimeException('Call recording is disabled.');
        }

        if ($call['status'] !== 'accepted') {
            throw new RuntimeException('Recording can be requested only during an active call.');
        }

        $isInitiator = (int)$call['initiator_user_id'] === (int)$user['id'];

        $sql = $isInitiator
            ? 'UPDATE communication_calls
               SET recording_status = "requested",
                   initiator_recording_consent = "granted",
                   recipient_recording_consent = "pending",
                   recording_requested_by = :user_id
               WHERE id = :id'
            : 'UPDATE communication_calls
               SET recording_status = "requested",
                   recipient_recording_consent = "granted",
                   initiator_recording_consent = "pending",
                   recording_requested_by = :user_id
               WHERE id = :id';

        db()->prepare($sql)->execute([
            'user_id' => $user['id'],
            'id' => $callId,
        ]);

        communication_insert_message(
            $threadId,
            (int)$user['id'],
            (string)$user['role'],
            'call_event',
            $user['display_name'] .
            ' requested permission to record this call. Recording will not begin without both participants’ consent.',
            null,
            $callId
        );

        json_response(['ok' => true]);
    }

    if ($action === 'recording_consent') {
        $callId = communication_api_int($payload, 'call_id');
        $call = communication_require_call($callId, $user);

        if ((int)$call['thread_id'] !== $threadId) {
            throw new RuntimeException(
                'The recording consent does not belong to this conversation.'
            );
        }

        $decision = trim((string)($payload['decision'] ?? ''));

        if (!in_array($decision, ['granted', 'declined'], true)) {
            throw new RuntimeException('Select a recording consent decision.');
        }

        if ($call['status'] !== 'accepted') {
            throw new RuntimeException('The call is no longer active.');
        }

        $isInitiator = (int)$call['initiator_user_id'] === (int)$user['id'];
        $column = $isInitiator
            ? 'initiator_recording_consent'
            : 'recipient_recording_consent';

        db()->prepare(
            'UPDATE communication_calls
             SET ' . $column . ' = :decision
             WHERE id = :id'
        )->execute([
            'decision' => $decision,
            'id' => $callId,
        ]);

        $updated = communication_require_call($callId, $user);
        $bothGranted = (
            $updated['initiator_recording_consent'] === 'granted'
            && $updated['recipient_recording_consent'] === 'granted'
        );
        $declined = (
            $updated['initiator_recording_consent'] === 'declined'
            || $updated['recipient_recording_consent'] === 'declined'
        );

        $recordingStatus = $bothGranted
            ? 'consented'
            : ($declined ? 'declined' : 'requested');

        db()->prepare(
            'UPDATE communication_calls
             SET recording_status = :recording_status
             WHERE id = :id'
        )->execute([
            'recording_status' => $recordingStatus,
            'id' => $callId,
        ]);

        communication_insert_message(
            $threadId,
            (int)$user['id'],
            (string)$user['role'],
            'call_event',
            $decision === 'granted'
                ? $user['display_name'] . ' consented to call recording.'
                : $user['display_name'] . ' declined call recording.',
            null,
            $callId
        );

        json_response([
            'ok' => true,
            'recording_status' => $recordingStatus,
        ]);
    }

    if ($action === 'recording_started') {
        $callId = communication_api_int($payload, 'call_id');
        $call = communication_require_call($callId, $user);

        if ((int)$call['thread_id'] !== $threadId) {
            throw new RuntimeException(
                'The call recording does not belong to this conversation.'
            );
        }

        if (
            $user['role'] !== 'admin'
            || $call['recording_status'] !== 'consented'
        ) {
            throw new RuntimeException('Recording cannot start without mutual consent and an administrator recorder.');
        }

        db()->prepare(
            'UPDATE communication_calls
             SET recording_status = "recording"
             WHERE id = :id'
        )->execute(['id' => $callId]);

        communication_insert_message(
            $threadId,
            (int)$user['id'],
            'admin',
            'call_event',
            'Call recording started with both participants’ consent.',
            null,
            $callId
        );

        json_response(['ok' => true]);
    }

    if ($action === 'update_thread') {
        if ($user['role'] !== 'admin') {
            throw new RuntimeException('Only administrators can update conversation settings.');
        }

        $status = trim((string)($payload['status'] ?? 'open'));
        $priority = trim((string)($payload['priority'] ?? 'normal'));
        $adminId = communication_api_int(
            $payload,
            'assigned_admin_user_id'
        );
        $projectId = communication_api_int($payload, 'project_id');

        if (!in_array(
            $status,
            ['open', 'waiting_admin', 'waiting_client', 'resolved', 'archived'],
            true
        )) {
            $status = 'open';
        }

        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        if ($adminId <= 0) {
            $adminId = communication_default_admin_id();
        }

        if ($projectId > 0) {
            $projectCheck = db()->prepare(
                'SELECT id
                 FROM projects
                 WHERE id = :id
                   AND client_user_id = :client_user_id'
            );
            $projectCheck->execute([
                'id' => $projectId,
                'client_user_id' => $thread['client_user_id'],
            ]);

            if (!$projectCheck->fetchColumn()) {
                throw new RuntimeException('The project does not belong to this client.');
            }
        }

        db()->prepare(
            'UPDATE communication_threads
             SET status = :status,
                 priority = :priority,
                 assigned_admin_user_id = :assigned_admin_user_id,
                 project_id = :project_id
             WHERE id = :id'
        )->execute([
            'status' => $status,
            'priority' => $priority,
            'assigned_admin_user_id' => $adminId > 0 ? $adminId : null,
            'project_id' => $projectId > 0 ? $projectId : null,
            'id' => $threadId,
        ]);

        if ($adminId > 0) {
            communication_ensure_member($threadId, $adminId, 'admin');
        }

        json_response(['ok' => true]);
    }

    if ($action === 'save_transcript' || $action === 'approve_transcript') {
        if ($user['role'] !== 'admin') {
            throw new RuntimeException('Only administrators can review transcripts.');
        }

        $transcriptId = communication_api_int(
            $payload,
            'transcript_id'
        );
        $rawText = knowledge_clean_text(
            (string)($payload['raw_text'] ?? '')
        );
        $reviewedText = knowledge_clean_text(
            (string)($payload['reviewed_text'] ?? '')
        );
        $share = communication_api_bool(
            $payload,
            'shared_with_client'
        );

        $transcriptStatement = db()->prepare(
            'SELECT *
             FROM communication_transcripts
             WHERE id = :id
               AND thread_id = :thread_id'
        );
        $transcriptStatement->execute([
            'id' => $transcriptId,
            'thread_id' => $threadId,
        ]);
        $transcript = $transcriptStatement->fetch();

        if (!$transcript) {
            throw new RuntimeException('Transcript record not found.');
        }

        if ($action === 'approve_transcript' && $reviewedText === '') {
            throw new RuntimeException('Enter the reviewed transcript before approval.');
        }

        $status = $action === 'approve_transcript'
            ? 'approved'
            : ($reviewedText !== '' ? 'review' : 'draft');

        db()->prepare(
            'UPDATE communication_transcripts
             SET status = :status,
                 raw_text = :raw_text,
                 reviewed_text = :reviewed_text,
                 shared_with_client = :shared_with_client,
                 reviewed_by = :reviewed_by,
                 reviewed_at = CASE
                    WHEN :approved = 1 THEN UTC_TIMESTAMP()
                    ELSE reviewed_at
                 END
             WHERE id = :id'
        )->execute([
            'status' => $status,
            'raw_text' => $rawText !== '' ? $rawText : null,
            'reviewed_text' => $reviewedText !== '' ? $reviewedText : null,
            'shared_with_client' => $share ? 1 : 0,
            'reviewed_by' => $user['id'],
            'approved' => $action === 'approve_transcript' ? 1 : 0,
            'id' => $transcriptId,
        ]);

        db()->prepare(
            'UPDATE communication_messages
             SET visibility = :visibility
             WHERE thread_id = :thread_id
               AND transcript_id = :transcript_id
               AND message_type = "transcript"'
        )->execute([
            'visibility' => $share ? 'client' : 'admin',
            'thread_id' => $threadId,
            'transcript_id' => $transcriptId,
        ]);

        if ($action === 'approve_transcript' && $share) {
            $existing = db()->prepare(
                'SELECT id
                 FROM communication_messages
                 WHERE thread_id = :thread_id
                   AND transcript_id = :transcript_id
                   AND message_type = "transcript"
                 LIMIT 1'
            );
            $existing->execute([
                'thread_id' => $threadId,
                'transcript_id' => $transcriptId,
            ]);

            if (!$existing->fetchColumn()) {
                communication_insert_message(
                    $threadId,
                    (int)$user['id'],
                    'admin',
                    'transcript',
                    'Reviewed transcript shared by Dave.',
                    null,
                    null,
                    $transcriptId,
                    'client'
                );
            }
        }

        if (
            $action === 'approve_transcript'
            && $share
            && !empty($thread['client_user_id'])
        ) {
            notification_create(
                (int)$thread['client_user_id'],
                'transcript',
                'Reviewed transcript shared',
                'Dave shared an approved transcript in "' . $thread['subject'] . '".',
                'portal/client.php?view=communications&thread=' . $threadId,
                'communication_transcript',
                $transcriptId,
                'normal'
            );
        }

        if ($action === 'approve_transcript') {
            communication_log_crm_activity(
                $threadId,
                'note',
                'Communication transcript approved',
                substr($reviewedText, 0, 4000),
                (int)$user['id']
            );
        }

        log_activity(
            $action === 'approve_transcript'
                ? 'communication_transcript_approved'
                : 'communication_transcript_saved',
            'communication_transcript',
            $transcriptId,
            [
                'thread_id' => $threadId,
                'shared_with_client' => $share,
            ]
        );

        json_response([
            'ok' => true,
            'status' => $status,
        ]);
    }

    if ($action === 'send_transcript_to_knowledge') {
        if ($user['role'] !== 'admin') {
            throw new RuntimeException('Only administrators can create Knowledge Center drafts.');
        }

        $transcriptId = communication_api_int(
            $payload,
            'transcript_id'
        );
        $assetId = communication_transcript_to_knowledge(
            $transcriptId,
            $user
        );

        json_response([
            'ok' => true,
            'knowledge_asset_id' => $assetId,
            'redirect' => app_url(
                'portal/admin.php?view=knowledge&asset=' . $assetId
            ),
        ]);
    }

    throw new RuntimeException('Unsupported communications action.');
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

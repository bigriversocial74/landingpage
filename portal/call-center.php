<?php
declare(strict_types=1);

require_once __DIR__ . '/communications.php';
require_once __DIR__ . '/notifications.php';

function call_center_config(): array
{
    return nmm_config('call_center');
}

function call_center_public_status(): string
{
    if (!(bool)(call_center_config()['enabled'] ?? true)) {
        return 'offline';
    }

    $status = setting(
        'public_call_status',
        (string)(call_center_config()['default_public_status'] ?? 'available')
    );

    return in_array($status, ['available', 'busy', 'offline'], true)
        ? $status
        : 'offline';
}

function call_center_public_status_message(): string
{
    if (!(bool)(call_center_config()['enabled'] ?? true)) {
        return 'Public browser calling is currently disabled. Use the contact form or email Dave.';
    }

    $custom = trim(setting('public_call_message', ''));

    if ($custom !== '') {
        return $custom;
    }

    return match (call_center_public_status()) {
        'available' => 'Dave is accepting browser audio calls.',
        'busy' => 'Dave is busy. Request a callback and include a message.',
        default => 'Live calling is offline. Leave a message or request a callback.',
    };
}

function call_center_default_admin_id(): int
{
    return communication_default_admin_id();
}


function call_center_max_rings(): int
{
    $configured = (int)setting(
        'public_call_max_rings',
        (string)(call_center_config()['public_max_rings'] ?? 6)
    );

    return max(1, min(12, $configured));
}

function call_center_ring_cycle_seconds(): int
{
    return max(
        4,
        min(
            10,
            (int)(
                call_center_config()['public_ring_cycle_seconds']
                ?? 6
            )
        )
    );
}

function call_center_ring_seconds(): int
{
    return call_center_max_rings()
        * call_center_ring_cycle_seconds();
}

function call_center_greeting_storage_path(string $storedName): string
{
    return NMM_ROOT
        . '/storage/call-center-greetings/'
        . basename($storedName);
}

function call_center_active_greeting(): ?array
{
    try {
        $statement = db()->query(
            'SELECT greeting.*,admin.display_name AS admin_name
             FROM call_center_greetings greeting
             JOIN users admin ON admin.id=greeting.admin_user_id
             WHERE greeting.is_active=1
             ORDER BY greeting.updated_at DESC,greeting.id DESC
             LIMIT 1'
        );
        $greeting = $statement->fetch();

        return $greeting ?: null;
    } catch (Throwable) {
        return null;
    }
}

function call_center_greeting_allowed_extensions(): array
{
    return [
        'webm' => 'audio/webm',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'wav' => 'audio/wav',
        'aac' => 'audio/aac',
    ];
}

function call_center_public_greeting_url(): ?string
{
    return call_center_active_greeting()
        ? app_url('api/call-center-greeting.php')
        : null;
}

function call_center_media_storage_path(string $storedName): string
{
    return NMM_ROOT . '/storage/call-center-media/' . basename($storedName);
}

function call_center_media_allowed_extensions(): array
{
    return [
        'webm' => ['audio/webm', 'audio'],
        'ogg' => ['audio/ogg', 'audio'],
        'oga' => ['audio/ogg', 'audio'],
        'mp3' => ['audio/mpeg', 'audio'],
        'm4a' => ['audio/mp4', 'audio'],
        'mp4' => ['audio/mp4', 'audio'],
        'wav' => ['audio/wav', 'audio'],
        'aac' => ['audio/aac', 'audio'],
    ];
}

function call_center_request_type_label(array $request): string
{
    $type = (string)($request['request_type'] ?? 'call_request');

    if (
        ($request['source'] ?? '') === 'public'
        && $type === 'call_request'
    ) {
        return 'Message';
    }

    return match ($type) {
        'live_call' => 'Live Call',
        'callback' => 'Callback',
        'voicemail' => 'Voicemail',
        default => 'Call Request',
    };
}

function call_center_request_media(int $requestId): array
{
    if ($requestId <= 0) {
        return [];
    }

    try {
        $statement = db()->prepare(
            'SELECT media_record.*,
                    uploader.display_name AS uploaded_by_name,
                    reviewer.display_name AS reviewed_by_name
             FROM call_center_media media_record
             LEFT JOIN users uploader
               ON uploader.id=media_record.uploaded_by_user_id
             LEFT JOIN users reviewer
               ON reviewer.id=media_record.reviewed_by_user_id
             WHERE media_record.request_id=:request_id
             ORDER BY media_record.created_at DESC,media_record.id DESC'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function call_center_dashboard_history(int $limit = 8): array
{
    call_center_expire_public_calls();
    $limit = max(1, min(24, $limit));

    try {
        $statement = db()->prepare(
            'SELECT request_record.*,
                    COALESCE(
                        request_record.guest_name,
                        client.display_name,
                        contact.display_name,
                        "Unknown caller"
                    ) AS caller_name,
                    COALESCE(
                        request_record.guest_email,
                        client.email,
                        CASE
                            WHEN contact.email LIKE "%@local.invalid"
                            THEN NULL
                            ELSE contact.email
                        END
                    ) AS caller_email,
                    COALESCE(
                        request_record.guest_phone,
                        client.phone,
                        contact.phone
                    ) AS caller_phone,
                    COALESCE(
                        request_record.guest_company,
                        client.company,
                        contact.company
                    ) AS caller_company,
                    admin.display_name AS assigned_admin_name,
                    media_record.id AS media_id,
                    media_record.media_type,
                    media_record.mime_type AS media_mime_type,
                    media_record.duration_seconds AS media_duration_seconds,
                    media_record.transcript_status,
                    COALESCE(
                        media_record.reviewed_transcript_text,
                        media_record.raw_transcript_text
                    ) AS media_transcript,
                    media_record.created_at AS media_created_at
             FROM call_center_requests request_record
             LEFT JOIN users client
               ON client.id=request_record.client_user_id
             LEFT JOIN crm_contacts contact
               ON contact.id=request_record.crm_contact_id
             LEFT JOIN users admin
               ON admin.id=request_record.assigned_admin_user_id
             LEFT JOIN call_center_media media_record
               ON media_record.id=(
                    SELECT newest_media.id
                    FROM call_center_media newest_media
                    WHERE newest_media.request_id=request_record.id
                    ORDER BY newest_media.created_at DESC,newest_media.id DESC
                    LIMIT 1
               )
             ORDER BY
                COALESCE(
                    request_record.ended_at,
                    request_record.answered_at,
                    request_record.ringing_at,
                    request_record.requested_at
                ) DESC,
                request_record.id DESC
             LIMIT ' . $limit
        );
        $statement->execute();

        return $statement->fetchAll();
    } catch (Throwable) {
        return array_map(
            static function (array $request): array {
                return $request + [
                    'media_id' => null,
                    'media_type' => null,
                    'media_mime_type' => null,
                    'media_duration_seconds' => null,
                    'transcript_status' => null,
                    'media_transcript' => null,
                    'media_created_at' => null,
                ];
            },
            call_center_admin_requests(null, $limit)
        );
    }
}

function call_center_history_time(array $request): ?string
{
    foreach ([
        'ended_at',
        'answered_at',
        'ringing_at',
        'requested_at',
    ] as $field) {
        $value = trim((string)($request[$field] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function call_center_contact_interaction_counts(int $contactId): array
{
    if ($contactId <= 0) {
        return [
            'voicemails' => 0,
            'messages' => 0,
            'callbacks' => 0,
            'last_message_at' => null,
            'last_voicemail_at' => null,
        ];
    }

    try {
        $statement = db()->prepare(
            'SELECT
                COALESCE(SUM(request_type="voicemail"),0) AS voicemails,
                COALESCE(SUM(
                    request_type="call_request"
                    AND source="public"
                ),0) AS messages,
                COALESCE(SUM(request_type="callback"),0) AS callbacks,
                MAX(
                    CASE
                        WHEN request_type IN ("call_request","callback")
                        THEN requested_at
                        ELSE NULL
                    END
                ) AS last_message_at,
                MAX(
                    CASE
                        WHEN request_type="voicemail"
                        THEN requested_at
                        ELSE NULL
                    END
                ) AS last_voicemail_at
             FROM call_center_requests
             WHERE crm_contact_id=:contact_id'
        );
        $statement->execute(['contact_id' => $contactId]);

        return $statement->fetch() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function call_center_store_media(
    int $requestId,
    string $temporaryPath,
    string $originalName,
    string $extension,
    string $mimeType,
    int $sizeBytes,
    ?float $durationSeconds = null,
    string $mediaType = 'voicemail',
    ?int $uploadedByUserId = null
): int {
    if ($requestId <= 0 || !is_file($temporaryPath)) {
        throw new RuntimeException('The voicemail recording is unavailable.');
    }

    $allowed = call_center_media_allowed_extensions();

    if (!isset($allowed[$extension])) {
        throw new RuntimeException('Unsupported voicemail audio format.');
    }

    $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
    $destination = call_center_media_storage_path($storedName);

    if (!move_uploaded_file($temporaryPath, $destination)) {
        if (!rename($temporaryPath, $destination)) {
            throw new RuntimeException(
                'The server could not store the voicemail recording.'
            );
        }
    }

    chmod($destination, 0640);
    $sha256 = hash_file('sha256', $destination);

    if ($sha256 === false) {
        @unlink($destination);
        throw new RuntimeException(
            'The server could not verify the voicemail recording.'
        );
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO call_center_media
                (request_id,uploaded_by_user_id,media_type,original_name,
                 stored_name,extension,mime_type,size_bytes,duration_seconds,
                 sha256,transcript_status)
             VALUES
                (:request_id,:uploaded_by_user_id,:media_type,:original_name,
                 :stored_name,:extension,:mime_type,:size_bytes,:duration_seconds,
                 :sha256,"not_requested")'
        );
        $statement->execute([
            'request_id' => $requestId,
            'uploaded_by_user_id' => $uploadedByUserId,
            'media_type' => in_array(
                $mediaType,
                ['voicemail', 'call_recording'],
                true
            ) ? $mediaType : 'voicemail',
            'original_name' => substr(basename($originalName), 0, 255),
            'stored_name' => $storedName,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'duration_seconds' => $durationSeconds,
            'sha256' => $sha256,
        ]);

        return (int)db()->lastInsertId();
    } catch (Throwable $exception) {
        @unlink($destination);
        throw $exception;
    }
}

function call_center_media(int $mediaId): ?array
{
    if ($mediaId <= 0) {
        return null;
    }

    try {
        $statement = db()->prepare(
            'SELECT media_record.*,
                    request_record.crm_contact_id,
                    request_record.client_user_id,
                    request_record.guest_name,
                    request_record.subject
             FROM call_center_media media_record
             JOIN call_center_requests request_record
               ON request_record.id=media_record.request_id
             WHERE media_record.id=:id
             LIMIT 1'
        );
        $statement->execute(['id' => $mediaId]);
        $media = $statement->fetch();

        return $media ?: null;
    } catch (Throwable) {
        return null;
    }
}


function call_center_event(
    int $requestId,
    string $eventType,
    ?int $actorUserId = null,
    ?string $notes = null,
    array $metadata = []
): int {
    if ($requestId <= 0 || trim($eventType) === '') {
        return 0;
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO call_center_events
                (request_id,actor_user_id,event_type,notes,metadata_json)
             VALUES
                (:request_id,:actor_user_id,:event_type,:notes,:metadata_json)'
        );
        $statement->execute([
            'request_id' => $requestId,
            'actor_user_id' => $actorUserId,
            'event_type' => substr(trim($eventType), 0, 80),
            'notes' => $notes,
            'metadata_json' => $metadata
                ? json_encode(
                    $metadata,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                )
                : null,
        ]);

        return (int)db()->lastInsertId();
    } catch (Throwable $exception) {
        error_log('North Mountain Media call event failed: ' . $exception->getMessage());
        return 0;
    }
}

function call_center_refresh_contact_stats(int $contactId): void
{
    if ($contactId <= 0) {
        return;
    }

    try {
        $statement = db()->prepare(
            'SELECT
                COUNT(*) AS total_requests,
                COALESCE(SUM(request_type = "live_call"),0) AS total_calls,
                COALESCE(SUM(
                    request_type = "live_call"
                    AND status = "completed"
                ),0) AS completed_calls,
                COALESCE(SUM(
                    request_type = "live_call"
                    AND status = "missed"
                ),0) AS missed_calls,
                COALESCE(SUM(
                    request_type = "live_call"
                    AND status = "declined"
                ),0) AS declined_calls,
                COALESCE(SUM(
                    CASE
                        WHEN request_type = "live_call"
                        THEN COALESCE(duration_seconds,0)
                        ELSE 0
                    END
                ),0) AS total_duration_seconds,
                AVG(
                    CASE
                        WHEN first_response_at IS NOT NULL
                        THEN TIMESTAMPDIFF(
                            SECOND,
                            requested_at,
                            first_response_at
                        )
                        ELSE NULL
                    END
                ) AS average_response_seconds,
                MIN(
                    CASE
                        WHEN request_type = "live_call"
                        THEN requested_at
                        ELSE NULL
                    END
                ) AS first_call_at,
                MAX(
                    CASE
                        WHEN request_type = "live_call"
                        THEN COALESCE(
                            ended_at,
                            answered_at,
                            ringing_at,
                            requested_at
                        )
                        ELSE NULL
                    END
                ) AS last_call_at
             FROM call_center_requests
             WHERE crm_contact_id = :crm_contact_id'
        );
        $statement->execute(['crm_contact_id' => $contactId]);
        $stats = $statement->fetch() ?: [];

        $latestStatement = db()->prepare(
            'SELECT status,source
             FROM call_center_requests
             WHERE crm_contact_id = :crm_contact_id
               AND request_type = "live_call"
             ORDER BY COALESCE(
                 ended_at,
                 answered_at,
                 ringing_at,
                 requested_at
             ) DESC
             LIMIT 1'
        );
        $latestStatement->execute(['crm_contact_id' => $contactId]);
        $latest = $latestStatement->fetch();

        db()->prepare(
            'INSERT INTO crm_contact_call_stats
                (contact_id,total_requests,total_calls,completed_calls,
                 missed_calls,declined_calls,total_duration_seconds,
                 average_response_seconds,first_call_at,last_call_at,
                 last_call_status,last_call_source)
             VALUES
                (:contact_id,:total_requests,:total_calls,:completed_calls,
                 :missed_calls,:declined_calls,:total_duration_seconds,
                 :average_response_seconds,:first_call_at,:last_call_at,
                 :last_call_status,:last_call_source)
             ON DUPLICATE KEY UPDATE
                total_requests=VALUES(total_requests),
                total_calls=VALUES(total_calls),
                completed_calls=VALUES(completed_calls),
                missed_calls=VALUES(missed_calls),
                declined_calls=VALUES(declined_calls),
                total_duration_seconds=VALUES(total_duration_seconds),
                average_response_seconds=VALUES(average_response_seconds),
                first_call_at=VALUES(first_call_at),
                last_call_at=VALUES(last_call_at),
                last_call_status=VALUES(last_call_status),
                last_call_source=VALUES(last_call_source)'
        )->execute([
            'contact_id' => $contactId,
            'total_requests' => (int)($stats['total_requests'] ?? 0),
            'total_calls' => (int)($stats['total_calls'] ?? 0),
            'completed_calls' => (int)($stats['completed_calls'] ?? 0),
            'missed_calls' => (int)($stats['missed_calls'] ?? 0),
            'declined_calls' => (int)($stats['declined_calls'] ?? 0),
            'total_duration_seconds' =>
                (int)($stats['total_duration_seconds'] ?? 0),
            'average_response_seconds' =>
                isset($stats['average_response_seconds'])
                    ? (int)$stats['average_response_seconds']
                    : null,
            'first_call_at' => $stats['first_call_at'] ?? null,
            'last_call_at' => $stats['last_call_at'] ?? null,
            'last_call_status' => $latest['status'] ?? null,
            'last_call_source' => $latest['source'] ?? null,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'North Mountain Media call stats refresh failed: ' .
            $exception->getMessage()
        );
    }
}

function call_center_sync_communication_call(int $callId): void
{
    if ($callId <= 0) {
        return;
    }

    try {
        $statement = db()->prepare(
            'SELECT call_record.*,
                    conversation.client_user_id,
                    conversation.crm_contact_id,
                    conversation.assigned_admin_user_id,
                    conversation.subject,
                    conversation.priority,
                    initiator.role AS initiator_role,
                    recipient.role AS recipient_role
             FROM communication_calls call_record
             JOIN communication_threads conversation
               ON conversation.id=call_record.thread_id
             JOIN users initiator
               ON initiator.id=call_record.initiator_user_id
             JOIN users recipient
               ON recipient.id=call_record.recipient_user_id
             WHERE call_record.id=:id
             LIMIT 1'
        );
        $statement->execute(['id' => $callId]);
        $call = $statement->fetch();

        if (!$call) {
            return;
        }

        $existingStatement = db()->prepare(
            'SELECT id,status
             FROM call_center_requests
             WHERE communication_call_id=:communication_call_id
             LIMIT 1'
        );
        $existingStatement->execute([
            'communication_call_id' => $callId,
        ]);
        $existing = $existingStatement->fetch();

        $status = match ((string)$call['status']) {
            'ended' => 'completed',
            'ringing', 'accepted', 'missed', 'declined',
            'cancelled', 'failed' => (string)$call['status'],
            default => 'failed',
        };

        $disposition = match ((string)$call['status']) {
            'ended', 'accepted' => 'connected',
            'missed', 'cancelled' => 'no_answer',
            'declined' => 'declined',
            'failed' => 'not_available',
            default => 'unassigned',
        };

        $adminId = null;

        if ($call['initiator_role'] === 'admin') {
            $adminId = (int)$call['initiator_user_id'];
        } elseif ($call['recipient_role'] === 'admin') {
            $adminId = (int)$call['recipient_user_id'];
        } elseif (!empty($call['assigned_admin_user_id'])) {
            $adminId = (int)$call['assigned_admin_user_id'];
        }

        db()->prepare(
            'INSERT INTO call_center_requests
                (source,request_type,client_user_id,crm_contact_id,
                 communication_thread_id,communication_call_id,
                 assigned_admin_user_id,requested_by_user_id,
                 subject,message,priority,status,disposition,
                 requested_at,queued_at,first_response_at,ringing_at,
                 answered_at,ended_at,duration_seconds,last_contact_at,
                 attempt_count,created_at,updated_at)
             VALUES
                ("client","live_call",:client_user_id,:crm_contact_id,
                 :communication_thread_id,:communication_call_id,
                 :assigned_admin_user_id,:requested_by_user_id,
                 :subject,"Authenticated portal audio call",:priority,
                 :status,:disposition,:requested_at,:queued_at,
                 :first_response_at,:ringing_at,:answered_at,:ended_at,
                 :duration_seconds,:last_contact_at,:attempt_count,
                 :created_at,:updated_at)
             ON DUPLICATE KEY UPDATE
                client_user_id=VALUES(client_user_id),
                crm_contact_id=VALUES(crm_contact_id),
                communication_thread_id=VALUES(communication_thread_id),
                assigned_admin_user_id=VALUES(assigned_admin_user_id),
                requested_by_user_id=VALUES(requested_by_user_id),
                subject=VALUES(subject),
                priority=VALUES(priority),
                status=VALUES(status),
                disposition=VALUES(disposition),
                first_response_at=VALUES(first_response_at),
                ringing_at=VALUES(ringing_at),
                answered_at=VALUES(answered_at),
                ended_at=VALUES(ended_at),
                duration_seconds=VALUES(duration_seconds),
                last_contact_at=VALUES(last_contact_at),
                attempt_count=GREATEST(
                    attempt_count,
                    VALUES(attempt_count)
                ),
                updated_at=VALUES(updated_at)'
        )->execute([
            'client_user_id' => $call['client_user_id'],
            'crm_contact_id' => $call['crm_contact_id'],
            'communication_thread_id' => $call['thread_id'],
            'communication_call_id' => $callId,
            'assigned_admin_user_id' => $adminId,
            'requested_by_user_id' => $call['initiator_user_id'],
            'subject' => $call['subject'],
            'priority' => $call['priority'],
            'status' => $status,
            'disposition' => $disposition,
            'requested_at' => $call['created_at'],
            'queued_at' => $call['created_at'],
            'first_response_at' => $call['answered_at'],
            'ringing_at' => $call['ringing_at'],
            'answered_at' => $call['answered_at'],
            'ended_at' => $call['ended_at'],
            'duration_seconds' => $call['duration_seconds'],
            'last_contact_at' =>
                $call['ended_at'] ?: $call['answered_at'],
            'attempt_count' => in_array(
                $call['status'],
                ['accepted', 'ended', 'missed', 'declined', 'failed'],
                true
            ) ? 1 : 0,
            'created_at' => $call['created_at'],
            'updated_at' => $call['updated_at'],
        ]);

        $requestStatement = db()->prepare(
            'SELECT id
             FROM call_center_requests
             WHERE communication_call_id=:communication_call_id
             LIMIT 1'
        );
        $requestStatement->execute([
            'communication_call_id' => $callId,
        ]);
        $requestId = (int)($requestStatement->fetchColumn() ?: 0);

        if (
            $requestId > 0
            && (
                !$existing
                || (string)$existing['status'] !== $status
            )
        ) {
            call_center_event(
                $requestId,
                'authenticated_call_' . $status,
                null,
                'Authenticated portal call status changed to ' .
                    status_label($status) . '.',
                [
                    'communication_call_id' => $callId,
                    'communication_thread_id' => (int)$call['thread_id'],
                ]
            );
        }

        if (!empty($call['crm_contact_id'])) {
            call_center_refresh_contact_stats(
                (int)$call['crm_contact_id']
            );
        }
    } catch (Throwable $exception) {
        error_log(
            'North Mountain Media authenticated-call sync failed: ' .
            $exception->getMessage()
        );
    }
}

function call_center_upsert_public_contact(
    string $name,
    string $email,
    ?string $phone,
    ?string $company
): int {
    $statement = db()->prepare(
        'INSERT INTO crm_contacts
            (email,display_name,company,phone,lifecycle_stage,source,last_inquiry_at)
         VALUES
            (:email,:display_name,:company,:phone,"lead","public_call",UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            id=LAST_INSERT_ID(id),
            display_name=VALUES(display_name),
            company=COALESCE(NULLIF(VALUES(company),""),company),
            phone=COALESCE(NULLIF(VALUES(phone),""),phone),
            last_inquiry_at=UTC_TIMESTAMP(),
            updated_at=UTC_TIMESTAMP()'
    );
    $statement->execute([
        'email' => $email,
        'display_name' => $name,
        'company' => $company,
        'phone' => $phone,
    ]);

    return (int)db()->lastInsertId();
}

function call_center_create_request(array $data): array
{
    $source = in_array($data['source'] ?? '', ['client', 'public', 'admin'], true)
        ? (string)$data['source']
        : 'public';
    $requestType = in_array(
        $data['request_type'] ?? '',
        ['call_request', 'live_call', 'callback', 'voicemail'],
        true
    )
        ? (string)$data['request_type']
        : 'call_request';
    $status = in_array(
        $data['status'] ?? '',
        ['new', 'queued', 'scheduled', 'ringing', 'accepted', 'completed',
         'missed', 'declined', 'cancelled', 'failed', 'voicemail', 'resolved', 'spam'],
        true
    )
        ? (string)$data['status']
        : 'new';
    $priority = in_array($data['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true)
        ? (string)$data['priority']
        : 'normal';

    $statement = db()->prepare(
        'INSERT INTO call_center_requests
            (source,request_type,client_user_id,crm_contact_id,
             communication_thread_id,assigned_admin_user_id,
             requested_by_user_id,guest_name,guest_email,guest_phone,
             guest_company,subject,message,preferred_at,priority,status,
             disposition,public_token_hash,token_expires_at,expires_at,
             queued_at,ringing_at,guest_heartbeat_at,admin_heartbeat_at,
             ip_address,user_agent)
         VALUES
            (:source,:request_type,:client_user_id,:crm_contact_id,
             :communication_thread_id,:assigned_admin_user_id,
             :requested_by_user_id,:guest_name,:guest_email,:guest_phone,
             :guest_company,:subject,:message,:preferred_at,:priority,:status,
             :disposition,:public_token_hash,:token_expires_at,:expires_at,
             :queued_at,:ringing_at,:guest_heartbeat_at,:admin_heartbeat_at,
             :ip_address,:user_agent)'
    );
    $statement->execute([
        'source' => $source,
        'request_type' => $requestType,
        'client_user_id' => $data['client_user_id'] ?? null,
        'crm_contact_id' => $data['crm_contact_id'] ?? null,
        'communication_thread_id' => $data['communication_thread_id'] ?? null,
        'assigned_admin_user_id' => $data['assigned_admin_user_id'] ?? null,
        'requested_by_user_id' => $data['requested_by_user_id'] ?? null,
        'guest_name' => $data['guest_name'] ?? null,
        'guest_email' => $data['guest_email'] ?? null,
        'guest_phone' => $data['guest_phone'] ?? null,
        'guest_company' => $data['guest_company'] ?? null,
        'subject' => substr(trim((string)($data['subject'] ?? 'Call request')), 0, 190),
        'message' => $data['message'] ?? null,
        'preferred_at' => $data['preferred_at'] ?? null,
        'priority' => $priority,
        'status' => $status,
        'disposition' => $data['disposition'] ?? 'unassigned',
        'public_token_hash' => $data['public_token_hash'] ?? null,
        'token_expires_at' => $data['token_expires_at'] ?? null,
        'expires_at' => $data['expires_at'] ?? null,
        'queued_at' => $data['queued_at'] ?? null,
        'ringing_at' => $data['ringing_at'] ?? null,
        'guest_heartbeat_at' => $data['guest_heartbeat_at'] ?? null,
        'admin_heartbeat_at' => $data['admin_heartbeat_at'] ?? null,
        'ip_address' => $data['ip_address'] ?? null,
        'user_agent' => $data['user_agent'] ?? null,
    ]);

    $requestId = (int)db()->lastInsertId();
    call_center_event(
        $requestId,
        'request_created',
        isset($data['requested_by_user_id'])
            ? (int)$data['requested_by_user_id']
            : null,
        $data['message'] ?? null,
        [
            'source' => $source,
            'request_type' => $requestType,
            'status' => $status,
        ]
    );

    if (!empty($data['crm_contact_id'])) {
        call_center_refresh_contact_stats((int)$data['crm_contact_id']);
    }

    return call_center_request($requestId) ?: ['id' => $requestId];
}

function call_center_request(int $requestId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    try {
        $statement = db()->prepare(
            'SELECT request_record.*,
                    client.display_name AS client_name,
                    client.email AS client_email,
                    client.phone AS client_phone,
                    client.company AS client_company,
                    admin.display_name AS assigned_admin_name,
                    contact.display_name AS contact_name,
                    CASE
                        WHEN contact.email LIKE "%@local.invalid"
                        THEN NULL
                        ELSE contact.email
                    END AS contact_email,
                    contact.phone AS contact_phone,
                    contact.company AS contact_company,
                    contact.lifecycle_stage AS contact_stage,
                    conversation.subject AS conversation_subject,
                    stats.total_requests AS contact_total_requests,
                    stats.total_calls AS contact_total_calls,
                    stats.completed_calls AS contact_completed_calls,
                    stats.missed_calls AS contact_missed_calls,
                    stats.declined_calls AS contact_declined_calls,
                    stats.total_duration_seconds AS contact_total_duration_seconds,
                    stats.average_response_seconds AS contact_average_response_seconds,
                    stats.last_call_at AS contact_last_call_at,
                    stats.last_call_status AS contact_last_call_status,
                    (
                        SELECT COUNT(*)
                        FROM call_center_requests related_voicemail
                        WHERE related_voicemail.crm_contact_id=request_record.crm_contact_id
                          AND related_voicemail.request_type="voicemail"
                    ) AS contact_total_voicemails,
                    (
                        SELECT COUNT(*)
                        FROM call_center_requests related_message
                        WHERE related_message.crm_contact_id=request_record.crm_contact_id
                          AND related_message.source="public"
                          AND related_message.request_type IN ("call_request","callback")
                    ) AS contact_total_messages,
                    (
                        SELECT COUNT(*)
                        FROM call_center_media media_count
                        WHERE media_count.request_id=request_record.id
                    ) AS media_count
             FROM call_center_requests request_record
             LEFT JOIN users client
               ON client.id=request_record.client_user_id
             LEFT JOIN users admin
               ON admin.id=request_record.assigned_admin_user_id
             LEFT JOIN crm_contacts contact
               ON contact.id=request_record.crm_contact_id
             LEFT JOIN communication_threads conversation
               ON conversation.id=request_record.communication_thread_id
             LEFT JOIN crm_contact_call_stats stats
               ON stats.contact_id=request_record.crm_contact_id
             WHERE request_record.id=:id
             LIMIT 1'
        );
        $statement->execute(['id' => $requestId]);
        $request = $statement->fetch();

        return $request ?: null;
    } catch (Throwable) {
        return null;
    }
}

function call_center_request_events(int $requestId): array
{
    try {
        $statement = db()->prepare(
            'SELECT event_record.*,actor.display_name AS actor_name
             FROM call_center_events event_record
             LEFT JOIN users actor ON actor.id=event_record.actor_user_id
             WHERE event_record.request_id=:request_id
             ORDER BY event_record.event_at DESC,event_record.id DESC'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function call_center_expire_public_calls(): void
{
    try {
        $statement = db()->query(
            'SELECT id,crm_contact_id
             FROM call_center_requests
             WHERE source="public"
               AND request_type="live_call"
               AND status="ringing"
               AND expires_at IS NOT NULL
               AND expires_at<UTC_TIMESTAMP()'
        );

        foreach ($statement->fetchAll() as $request) {
            $update = db()->prepare(
                'UPDATE call_center_requests
                 SET status="missed",
                     disposition="no_answer",
                     ended_at=UTC_TIMESTAMP(),
                     last_contact_at=UTC_TIMESTAMP()
                 WHERE id=:id
                   AND status="ringing"'
            );
            $update->execute(['id' => $request['id']]);

            if ($update->rowCount() > 0) {
                call_center_event(
                    (int)$request['id'],
                    'public_call_missed',
                    null,
                    'The public browser call reached the configured maximum rings and was moved to voicemail.'
                );
                notification_create_for_role(
                    'admin',
                    'call',
                    'Missed public call',
                    'A public browser call reached the maximum rings. The caller was offered voicemail and the contact remains in the Call Center queue.',
                    'portal/admin.php?view=call-center&request=' . (int)$request['id'],
                    'call_center_request',
                    (int)$request['id'],
                    'high'
                );

                if (!empty($request['crm_contact_id'])) {
                    call_center_refresh_contact_stats((int)$request['crm_contact_id']);
                }
            }
        }

        $staleSeconds = max(
            60,
            min(
                600,
                (int)(
                    call_center_config()['public_call_stale_seconds']
                    ?? 120
                )
            )
        );

        $staleStatement = db()->query(
            'SELECT id,crm_contact_id,answered_at
             FROM call_center_requests
             WHERE source="public"
               AND request_type="live_call"
               AND status="accepted"
               AND answered_at<(
                   UTC_TIMESTAMP()-INTERVAL 30 SECOND
               )
               AND (
                   guest_heartbeat_at IS NULL
                   OR guest_heartbeat_at<(
                       UTC_TIMESTAMP()-INTERVAL ' . $staleSeconds . ' SECOND
                   )
                   OR admin_heartbeat_at IS NULL
                   OR admin_heartbeat_at<(
                       UTC_TIMESTAMP()-INTERVAL ' . $staleSeconds . ' SECOND
                   )
               )'
        );

        foreach ($staleStatement->fetchAll() as $request) {
            $duration = 0;

            if (!empty($request['answered_at'])) {
                try {
                    $duration = max(
                        0,
                        time() - (
                            new DateTimeImmutable(
                                (string)$request['answered_at'],
                                new DateTimeZone('UTC')
                            )
                        )->getTimestamp()
                    );
                } catch (Throwable) {
                    $duration = 0;
                }
            }

            $update = db()->prepare(
                'UPDATE call_center_requests
                 SET status="failed",
                     disposition="not_available",
                     ended_at=UTC_TIMESTAMP(),
                     duration_seconds=:duration_seconds,
                     last_contact_at=UTC_TIMESTAMP()
                 WHERE id=:id
                   AND status="accepted"'
            );
            $update->execute([
                'duration_seconds' => $duration,
                'id' => $request['id'],
            ]);

            if ($update->rowCount() > 0) {
                call_center_event(
                    (int)$request['id'],
                    'public_call_heartbeat_stopped',
                    null,
                    'The public call was closed after one participant stopped sending a heartbeat.',
                    ['duration_seconds' => $duration]
                );

                notification_create_for_role(
                    'admin',
                    'call',
                    'Public call connection lost',
                    'A connected public browser call closed after its heartbeat stopped.',
                    'portal/admin.php?view=call-center&request=' .
                        (int)$request['id'],
                    'call_center_request',
                    (int)$request['id'],
                    'high'
                );

                if (!empty($request['crm_contact_id'])) {
                    call_center_refresh_contact_stats(
                        (int)$request['crm_contact_id']
                    );
                }
            }
        }

        $retentionHours = max(
            1,
            (int)(call_center_config()['signal_retention_hours'] ?? 24)
        );

        if (random_int(1, 40) === 1) {
            db()->exec(
                'DELETE FROM call_center_signals
                 WHERE created_at<(
                     UTC_TIMESTAMP()-INTERVAL ' . $retentionHours . ' HOUR
                 )'
            );
        }
    } catch (Throwable $exception) {
        error_log('North Mountain Media public call expiry failed: ' . $exception->getMessage());
    }
}

function call_center_admin_metrics(): array
{
    call_center_expire_public_calls();

    try {
        $statement = db()->query(
            'SELECT
                COUNT(*) AS total,
                SUM(DATE(requested_at)=UTC_DATE()) AS today,
                SUM(status IN ("new","queued","scheduled","ringing")) AS waiting,
                SUM(status="ringing") AS ringing,
                SUM(status="completed") AS completed,
                SUM(status="accepted") AS active,
                SUM(status="missed") AS missed,
                SUM(source="public") AS public_total,
                SUM(source="client") AS client_total,
                SUM(request_type="voicemail") AS voicemail_total,
                SUM(
                    source="public"
                    AND request_type IN ("call_request","callback")
                ) AS message_total,
                COUNT(DISTINCT crm_contact_id) AS contacts,
                AVG(
                    CASE
                        WHEN first_response_at IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND,requested_at,first_response_at)
                        ELSE NULL
                    END
                ) AS average_response_seconds,
                AVG(
                    CASE
                        WHEN duration_seconds IS NOT NULL
                        THEN duration_seconds
                        ELSE NULL
                    END
                ) AS average_duration_seconds
             FROM call_center_requests'
        );

        return $statement->fetch() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function call_center_admin_requests(
    ?string $status = null,
    int $limit = 250
): array {
    call_center_expire_public_calls();
    $limit = max(1, min(500, $limit));

    try {
        $sql = 'SELECT request_record.*,
                       COALESCE(
                           request_record.guest_name,
                           client.display_name,
                           contact.display_name,
                           "Unknown caller"
                       ) AS caller_name,
                       COALESCE(
                           request_record.guest_email,
                           client.email,
                           CASE
                               WHEN contact.email LIKE "%@local.invalid"
                               THEN NULL
                               ELSE contact.email
                           END
                       ) AS caller_email,
                       COALESCE(
                           request_record.guest_phone,
                           client.phone,
                           contact.phone
                       ) AS caller_phone,
                       COALESCE(
                           request_record.guest_company,
                           client.company,
                           contact.company
                       ) AS caller_company,
                       admin.display_name AS assigned_admin_name,
                       stats.total_calls AS contact_total_calls,
                       stats.missed_calls AS contact_missed_calls,
                       stats.last_call_at AS contact_last_call_at,
                       (
                           SELECT COUNT(*)
                           FROM call_center_media media_count
                           WHERE media_count.request_id=request_record.id
                       ) AS media_count
                FROM call_center_requests request_record
                LEFT JOIN users client
                  ON client.id=request_record.client_user_id
                LEFT JOIN crm_contacts contact
                  ON contact.id=request_record.crm_contact_id
                LEFT JOIN users admin
                  ON admin.id=request_record.assigned_admin_user_id
                LEFT JOIN crm_contact_call_stats stats
                  ON stats.contact_id=request_record.crm_contact_id';

        $parameters = [];

        if ($status !== null && $status !== '') {
            $sql .= ' WHERE request_record.status=:status';
            $parameters['status'] = $status;
        }

        $sql .= ' ORDER BY
                    FIELD(
                        request_record.status,
                        "ringing","new","queued","scheduled","accepted",
                        "missed","declined","failed","completed","resolved",
                        "cancelled","voicemail","spam"
                    ),
                    FIELD(request_record.priority,"urgent","high","normal","low"),
                    request_record.requested_at DESC
                  LIMIT ' . $limit;

        $statement = db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function call_center_client_requests(int $clientUserId): array
{
    try {
        $statement = db()->prepare(
            'SELECT request_record.*,
                    admin.display_name AS assigned_admin_name
             FROM call_center_requests request_record
             LEFT JOIN users admin
               ON admin.id=request_record.assigned_admin_user_id
             WHERE request_record.client_user_id=:client_user_id
             ORDER BY request_record.requested_at DESC
             LIMIT 100'
        );
        $statement->execute(['client_user_id' => $clientUserId]);

        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function call_center_seconds_label(?int $seconds): string
{
    if ($seconds === null) {
        return '—';
    }

    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;

    return $hours > 0
        ? sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining)
        : sprintf('%02d:%02d', $minutes, $remaining);
}

function call_center_public_payload(array $request): array
{
    return [
        'id' => (int)$request['id'],
        'status' => (string)$request['status'],
        'disposition' => (string)$request['disposition'],
        'assigned_admin_name' => $request['assigned_admin_name'] ?? null,
        'requested_at' => $request['requested_at'] ?? null,
        'ringing_at' => $request['ringing_at'] ?? null,
        'answered_at' => $request['answered_at'] ?? null,
        'ended_at' => $request['ended_at'] ?? null,
        'duration_seconds' => (int)($request['duration_seconds'] ?? 0),
        'message' => $request['message'] ?? null,
    ];
}

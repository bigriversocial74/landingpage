<?php
declare(strict_types=1);

require_once __DIR__ . '/knowledge-assets.php';
require_once __DIR__ . '/notifications.php';

function communication_config(): array
{
    return nmm_config('communications');
}

function communication_enabled(): bool
{
    return (bool)(communication_config()['enabled'] ?? true);
}

function communication_storage_path(string $storedName): string
{
    return NMM_ROOT . '/storage/communication-assets/' . basename($storedName);
}

function communication_allowed_extensions(): array
{
    return [
        'pdf' => ['application/pdf', 'document'],
        'doc' => ['application/msword', 'document'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'document',
        ],
        'xls' => ['application/vnd.ms-excel', 'data'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'data',
        ],
        'ppt' => ['application/vnd.ms-powerpoint', 'document'],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'document',
        ],
        'txt' => ['text/plain', 'document'],
        'md' => ['text/markdown', 'document'],
        'csv' => ['text/csv', 'data'],
        'json' => ['application/json', 'data'],
        'xml' => ['application/xml', 'data'],
        'zip' => ['application/zip', 'data'],

        'jpg' => ['image/jpeg', 'image'],
        'jpeg' => ['image/jpeg', 'image'],
        'png' => ['image/png', 'image'],
        'gif' => ['image/gif', 'image'],
        'webp' => ['image/webp', 'image'],

        'mp3' => ['audio/mpeg', 'audio'],
        'wav' => ['audio/wav', 'audio'],
        'm4a' => ['audio/mp4', 'audio'],
        'aac' => ['audio/aac', 'audio'],
        'ogg' => ['audio/ogg', 'audio'],
        'oga' => ['audio/ogg', 'audio'],
        'webm' => ['audio/webm', 'audio'],
        'flac' => ['audio/flac', 'audio'],

        'mp4' => ['video/mp4', 'video'],
        'm4v' => ['video/x-m4v', 'video'],
        'mov' => ['video/quicktime', 'video'],
        'ogv' => ['video/ogg', 'video'],
    ];
}

function communication_safe_ice_servers(): array
{
    $servers = communication_config()['ice_servers'] ?? [];

    if (!is_array($servers)) {
        return [];
    }

    $safe = [];

    foreach ($servers as $server) {
        if (!is_array($server)) {
            continue;
        }

        $urls = $server['urls'] ?? [];

        if (is_string($urls)) {
            $urls = [$urls];
        }

        if (!is_array($urls)) {
            continue;
        }

        $urls = array_values(array_filter(
            array_map(
                static function ($url): string {
                    $url = trim((string)$url);
                    return preg_match('#^(stun|turn|turns):#i', $url)
                        ? $url
                        : '';
                },
                $urls
            )
        ));

        if (!$urls) {
            continue;
        }

        $entry = ['urls' => $urls];

        if (!empty($server['username'])) {
            $entry['username'] = (string)$server['username'];
        }

        if (!empty($server['credential'])) {
            $entry['credential'] = (string)$server['credential'];
        }

        $safe[] = $entry;
    }

    return $safe;
}

function communication_thread(int $threadId, array $user): ?array
{
    if ($threadId <= 0) {
        return null;
    }

    $isAdmin = $user['role'] === 'admin';
    $sql = 'SELECT t.*,
                   client.display_name AS client_name,
                   client.email AS client_email,
                   client.company AS client_company,
                   client.phone AS client_phone,
                   admin.display_name AS assigned_admin_name,
                   project.title AS project_title,
                   crm.lifecycle_stage AS crm_stage,
                   crm.email AS crm_email
            FROM communication_threads t
            JOIN users client ON client.id = t.client_user_id
            LEFT JOIN users admin ON admin.id = t.assigned_admin_user_id
            LEFT JOIN projects project ON project.id = t.project_id
            LEFT JOIN crm_contacts crm ON crm.id = t.crm_contact_id
            WHERE t.id = :id';

    if (!$isAdmin) {
        $sql .= ' AND t.client_user_id = :client_user_id';
    }

    $statement = db()->prepare($sql);
    $parameters = ['id' => $threadId];

    if (!$isAdmin) {
        $parameters['client_user_id'] = $user['id'];
    }

    $statement->execute($parameters);
    $thread = $statement->fetch();

    return $thread ?: null;
}

function communication_require_thread(int $threadId, array $user): array
{
    $thread = communication_thread($threadId, $user);

    if (!$thread) {
        throw new RuntimeException('Communication thread not found.');
    }

    return $thread;
}

function communication_default_admin_id(): int
{
    $statement = db()->query(
        'SELECT id
         FROM users
         WHERE role = "admin"
           AND status = "active"
         ORDER BY id
         LIMIT 1'
    );

    return (int)($statement->fetchColumn() ?: 0);
}

function communication_ensure_member(
    int $threadId,
    int $userId,
    string $role
): void {
    db()->prepare(
        'INSERT INTO communication_thread_members
            (thread_id, user_id, member_role)
         VALUES
            (:thread_id, :user_id, :member_role)
         ON DUPLICATE KEY UPDATE
            member_role = VALUES(member_role)'
    )->execute([
        'thread_id' => $threadId,
        'user_id' => $userId,
        'member_role' => $role === 'admin' ? 'admin' : 'client',
    ]);
}

function communication_log_crm_activity(
    int $threadId,
    string $activityType,
    string $subject,
    ?string $body = null,
    ?int $adminUserId = null
): void {
    $allowedTypes = [
        'inquiry',
        'note',
        'email',
        'call',
        'meeting',
        'status_change',
        'conversion',
        'system',
    ];

    if (!in_array($activityType, $allowedTypes, true)) {
        $activityType = 'note';
    }

    try {
        $threadStatement = db()->prepare(
            'SELECT crm_contact_id
             FROM communication_threads
             WHERE id = :id'
        );
        $threadStatement->execute(['id' => $threadId]);
        $contactId = (int)($threadStatement->fetchColumn() ?: 0);

        if ($contactId <= 0) {
            return;
        }

        db()->prepare(
            'INSERT INTO crm_activities
                (contact_id, admin_user_id, activity_type, subject, body)
             VALUES
                (:contact_id, :admin_user_id, :activity_type, :subject, :body)'
        )->execute([
            'contact_id' => $contactId,
            'admin_user_id' => $adminUserId,
            'activity_type' => $activityType,
            'subject' => substr($subject, 0, 190),
            'body' => $body,
        ]);

        db()->prepare(
            'UPDATE crm_contacts
             SET last_contacted_at = UTC_TIMESTAMP()
             WHERE id = :id'
        )->execute(['id' => $contactId]);
    } catch (Throwable) {
        // Communications remain usable even if CRM activity logging fails.
    }
}

function communication_create_thread(
    array $user,
    int $clientUserId,
    ?int $projectId,
    string $subject
): int {
    $subject = trim($subject);

    if ($subject === '' || strlen($subject) > 190) {
        throw new RuntimeException('Enter a conversation subject.');
    }

    if ($user['role'] === 'client') {
        $clientUserId = (int)$user['id'];
    }

    $clientStatement = db()->prepare(
        'SELECT id
         FROM users
         WHERE id = :id
           AND role = "client"
           AND status = "active"'
    );
    $clientStatement->execute(['id' => $clientUserId]);

    if (!$clientStatement->fetchColumn()) {
        throw new RuntimeException('Select an active client.');
    }

    if ($projectId !== null) {
        $projectStatement = db()->prepare(
            'SELECT id
             FROM projects
             WHERE id = :id
               AND client_user_id = :client_user_id'
        );
        $projectStatement->execute([
            'id' => $projectId,
            'client_user_id' => $clientUserId,
        ]);

        if (!$projectStatement->fetchColumn()) {
            throw new RuntimeException('The selected project does not belong to this client.');
        }
    }

    $adminId = $user['role'] === 'admin'
        ? (int)$user['id']
        : communication_default_admin_id();

    if ($adminId <= 0) {
        throw new RuntimeException('No active administrator is available.');
    }

    $crmStatement = db()->prepare(
        'SELECT id
         FROM crm_contacts
         WHERE client_user_id = :client_user_id
         ORDER BY id
         LIMIT 1'
    );
    $crmStatement->execute(['client_user_id' => $clientUserId]);
    $crmContactId = (int)($crmStatement->fetchColumn() ?: 0);

    $statement = db()->prepare(
        'INSERT INTO communication_threads
            (client_user_id, crm_contact_id, project_id,
             assigned_admin_user_id, subject, created_by)
         VALUES
            (:client_user_id, :crm_contact_id, :project_id,
             :assigned_admin_user_id, :subject, :created_by)'
    );
    $statement->execute([
        'client_user_id' => $clientUserId,
        'crm_contact_id' => $crmContactId > 0 ? $crmContactId : null,
        'project_id' => $projectId,
        'assigned_admin_user_id' => $adminId,
        'subject' => $subject,
        'created_by' => $user['id'],
    ]);

    $threadId = (int)db()->lastInsertId();

    communication_ensure_member($threadId, $clientUserId, 'client');
    communication_ensure_member($threadId, $adminId, 'admin');

    communication_insert_message(
        $threadId,
        (int)$user['id'],
        (string)$user['role'],
        'system',
        'Conversation created.',
        null,
        null,
        null,
        'client'
    );

    communication_log_crm_activity(
        $threadId,
        'system',
        'Communication thread created',
        $subject,
        $user['role'] === 'admin' ? (int)$user['id'] : null
    );

    log_activity(
        'communication_thread_created',
        'communication_thread',
        $threadId,
        [
            'client_user_id' => $clientUserId,
            'project_id' => $projectId,
        ]
    );

    return $threadId;
}

function communication_insert_message(
    int $threadId,
    ?int $senderUserId,
    string $senderRole,
    string $messageType,
    ?string $body = null,
    ?int $attachmentId = null,
    ?int $callId = null,
    ?int $transcriptId = null,
    string $visibility = 'client'
): int {
    $allowedTypes = [
        'text', 'voice', 'file', 'call_event', 'call_recording',
        'transcript', 'internal_note', 'system',
    ];

    if (!in_array($messageType, $allowedTypes, true)) {
        throw new RuntimeException('Invalid communication message type.');
    }

    if (!in_array($senderRole, ['admin', 'client', 'system'], true)) {
        $senderRole = 'system';
    }

    if (!in_array($visibility, ['client', 'admin'], true)) {
        $visibility = 'client';
    }

    $statement = db()->prepare(
        'INSERT INTO communication_messages
            (thread_id, sender_user_id, sender_role, message_type,
             body, attachment_id, call_id, transcript_id, visibility)
         VALUES
            (:thread_id, :sender_user_id, :sender_role, :message_type,
             :body, :attachment_id, :call_id, :transcript_id, :visibility)'
    );
    $statement->execute([
        'thread_id' => $threadId,
        'sender_user_id' => $senderUserId,
        'sender_role' => $senderRole,
        'message_type' => $messageType,
        'body' => $body,
        'attachment_id' => $attachmentId,
        'call_id' => $callId,
        'transcript_id' => $transcriptId,
        'visibility' => $visibility,
    ]);

    $messageId = (int)db()->lastInsertId();

    db()->prepare(
        'UPDATE communication_threads
         SET last_message_at = UTC_TIMESTAMP(),
             status = CASE
                WHEN :sender_role = "admin" THEN "waiting_client"
                WHEN :sender_role = "client" THEN "waiting_admin"
                ELSE status
             END
         WHERE id = :id'
    )->execute([
        'sender_role' => $senderRole,
        'id' => $threadId,
    ]);

    return $messageId;
}

function communication_messages(
    int $threadId,
    array $user,
    int $afterId = 0,
    int $limit = 150
): array {
    $limit = max(1, min(300, $limit));
    $isAdmin = $user['role'] === 'admin';

    $transcriptTextSelect = $isAdmin
        ? 'transcript.reviewed_text'
        : 'CASE
               WHEN transcript.shared_with_client = 1
                 AND transcript.status = "approved"
               THEN transcript.reviewed_text
               ELSE NULL
           END';

    $sql = 'SELECT m.*,
                   sender.display_name AS sender_name,
                   a.original_name,
                   a.extension,
                   a.mime_type,
                   a.media_kind,
                   a.size_bytes,
                   a.duration_seconds,
                   transcript.status AS transcript_status,
                   ' . $transcriptTextSelect . ' AS transcript_reviewed_text,
                   transcript.shared_with_client AS transcript_shared_with_client
            FROM communication_messages m
            LEFT JOIN users sender ON sender.id = m.sender_user_id
            LEFT JOIN communication_attachments a ON a.id = m.attachment_id
            LEFT JOIN communication_transcripts transcript ON transcript.id = m.transcript_id
            WHERE m.thread_id = :thread_id
              AND m.id > :after_id';

    if (!$isAdmin) {
        $sql .= ' AND m.visibility = "client"';
    }

    $sql .= ' ORDER BY m.id ASC LIMIT ' . $limit;

    $statement = db()->prepare($sql);
    $statement->execute([
        'thread_id' => $threadId,
        'after_id' => $afterId,
    ]);

    return $statement->fetchAll();
}

function communication_thread_transcripts(
    int $threadId,
    array $user
): array {
    $isAdmin = $user['role'] === 'admin';

    $sql = 'SELECT t.*,
                   a.original_name,
                   a.mime_type,
                   a.media_kind,
                   reviewer.display_name AS reviewed_by_name
            FROM communication_transcripts t
            LEFT JOIN communication_attachments a
              ON a.id = t.source_attachment_id
            LEFT JOIN users reviewer
              ON reviewer.id = t.reviewed_by
            WHERE t.thread_id = :thread_id';

    if (!$isAdmin) {
        $sql .= ' AND t.status = "approved"
                  AND t.shared_with_client = 1';
    }

    $sql .= ' ORDER BY t.updated_at DESC';

    $statement = db()->prepare($sql);
    $statement->execute(['thread_id' => $threadId]);

    return $statement->fetchAll();
}

function communication_mark_read(
    int $threadId,
    array $user,
    ?int $messageId = null
): void {
    communication_ensure_member(
        $threadId,
        (int)$user['id'],
        (string)$user['role']
    );

    if ($messageId === null) {
        $statement = db()->prepare(
            'SELECT MAX(id)
             FROM communication_messages
             WHERE thread_id = :thread_id'
        );
        $statement->execute(['thread_id' => $threadId]);
        $messageId = (int)($statement->fetchColumn() ?: 0);
    }

    db()->prepare(
        'UPDATE communication_thread_members
         SET last_read_message_id = :message_id,
             last_read_at = UTC_TIMESTAMP()
         WHERE thread_id = :thread_id
           AND user_id = :user_id'
    )->execute([
        'message_id' => $messageId > 0 ? $messageId : null,
        'thread_id' => $threadId,
        'user_id' => $user['id'],
    ]);
}

function communication_thread_list(array $user): array
{
    $isAdmin = $user['role'] === 'admin';

    $sql = 'SELECT t.*,
                   client.display_name AS client_name,
                   client.company AS client_company,
                   project.title AS project_title,
                   admin.display_name AS assigned_admin_name,
                   (
                       SELECT COUNT(*)
                       FROM communication_messages unread
                       WHERE unread.thread_id = t.id
                         AND unread.id > COALESCE(thread_member.last_read_message_id, 0)
                         AND (
                             unread.sender_user_id IS NULL
                             OR unread.sender_user_id <> :unread_user_id
                         )';

    if (!$isAdmin) {
        $sql .= ' AND unread.visibility = "client"';
    }

    $sql .= '
                   ) AS unread_count,
                   (
                       SELECT message.body
                       FROM communication_messages message
                       WHERE message.thread_id = t.id';

    if (!$isAdmin) {
        $sql .= ' AND message.visibility = "client"';
    }

    $sql .= '
                       ORDER BY message.id DESC
                       LIMIT 1
                   ) AS latest_message
            FROM communication_threads t
            JOIN users client ON client.id = t.client_user_id
            LEFT JOIN projects project ON project.id = t.project_id
            LEFT JOIN users admin ON admin.id = t.assigned_admin_user_id
            LEFT JOIN communication_thread_members thread_member
              ON thread_member.thread_id = t.id
             AND thread_member.user_id = :current_user_id';

    $parameters = [
        'current_user_id' => $user['id'],
        'unread_user_id' => $user['id'],
    ];

    if (!$isAdmin) {
        $sql .= ' WHERE t.client_user_id = :client_user_id
                    AND t.status <> "archived"';
        $parameters['client_user_id'] = $user['id'];
    }

    $sql .= ' ORDER BY
                 CASE WHEN t.status = "archived" THEN 1 ELSE 0 END,
                 COALESCE(t.last_message_at, t.created_at) DESC,
                 t.id DESC';

    $statement = db()->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function communication_call(
    int $callId,
    array $user
): ?array {
    $statement = db()->prepare(
        'SELECT c.*,
                initiator.display_name AS initiator_name,
                recipient.display_name AS recipient_name,
                t.client_user_id,
                t.assigned_admin_user_id
         FROM communication_calls c
         JOIN communication_threads t ON t.id = c.thread_id
         JOIN users initiator ON initiator.id = c.initiator_user_id
         JOIN users recipient ON recipient.id = c.recipient_user_id
         WHERE c.id = :id
           AND (
               :is_admin = 1
               OR t.client_user_id = :user_id
           )
         LIMIT 1'
    );
    $statement->execute([
        'id' => $callId,
        'is_admin' => $user['role'] === 'admin' ? 1 : 0,
        'user_id' => $user['id'],
    ]);
    $call = $statement->fetch();

    if (!$call) {
        return null;
    }

    if (
        (int)$call['initiator_user_id'] !== (int)$user['id']
        && (int)$call['recipient_user_id'] !== (int)$user['id']
    ) {
        return null;
    }

    return $call;
}

function communication_require_call(int $callId, array $user): array
{
    $call = communication_call($callId, $user);

    if (!$call) {
        throw new RuntimeException('Audio call not found.');
    }

    return $call;
}

function communication_expire_calls(): void
{
    $statement = db()->query(
        'SELECT id, thread_id
         FROM communication_calls
         WHERE status = "ringing"
           AND expires_at < UTC_TIMESTAMP()'
    );
    $calls = $statement->fetchAll();

    foreach ($calls as $call) {
        $update = db()->prepare(
            'UPDATE communication_calls
             SET status = "missed",
                 ended_at = UTC_TIMESTAMP(),
                 end_reason = "No answer"
             WHERE id = :id
               AND status = "ringing"'
        );
        $update->execute(['id' => $call['id']]);

        if ($update->rowCount() > 0) {
            communication_insert_message(
                (int)$call['thread_id'],
                null,
                'system',
                'call_event',
                'Missed audio call.',
                null,
                (int)$call['id']
            );

            communication_log_crm_activity(
                (int)$call['thread_id'],
                'call',
                'Missed audio call',
                'The audio call was not answered before the ringing period expired.'
            );

            $participantStatement = db()->prepare(
                'SELECT initiator_user_id,recipient_user_id
                 FROM communication_calls
                 WHERE id=:id'
            );
            $participantStatement->execute(['id' => $call['id']]);
            $participants = $participantStatement->fetch();

            if ($participants) {
                foreach ([
                    (int)$participants['initiator_user_id'],
                    (int)$participants['recipient_user_id'],
                ] as $participantId) {
                    notification_create(
                        $participantId,
                        'call',
                        'Missed audio call',
                        'An authenticated portal call was not answered.',
                        'portal/' .
                            (
                                db()->query(
                                    'SELECT role FROM users WHERE id=' .
                                    $participantId
                                )->fetchColumn() === 'admin'
                                    ? 'admin.php'
                                    : 'client.php'
                            ) .
                            '?view=communications&thread=' .
                            (int)$call['thread_id'],
                        'communication_call',
                        (int)$call['id'],
                        'high'
                    );
                }
            }

            if (function_exists('call_center_sync_communication_call')) {
                call_center_sync_communication_call((int)$call['id']);
            }
        }
    }

    $staleSeconds = max(
        60,
        min(
            600,
            (int)(communication_config()['call_stale_seconds'] ?? 120)
        )
    );

    $staleCalls = db()->query(
        'SELECT id, thread_id, answered_at
         FROM communication_calls
         WHERE status = "accepted"
           AND updated_at < (
               UTC_TIMESTAMP() - INTERVAL ' . $staleSeconds . ' SECOND
           )'
    )->fetchAll();

    foreach ($staleCalls as $call) {
        $duration = 0;

        if (!empty($call['answered_at'])) {
            try {
                $duration = max(
                    0,
                    time() - (
                        new DateTimeImmutable(
                            (string)$call['answered_at'],
                            new DateTimeZone('UTC')
                        )
                    )->getTimestamp()
                );
            } catch (Throwable) {
                $duration = 0;
            }
        }

        $update = db()->prepare(
            'UPDATE communication_calls
             SET status = "failed",
                 ended_at = UTC_TIMESTAMP(),
                 duration_seconds = :duration_seconds,
                 end_reason = "Connection heartbeat stopped"
             WHERE id = :id
               AND status = "accepted"'
        );
        $update->execute([
            'duration_seconds' => $duration,
            'id' => $call['id'],
        ]);

        if ($update->rowCount() > 0) {
            communication_insert_message(
                (int)$call['thread_id'],
                null,
                'system',
                'call_event',
                'Audio call closed because the connection heartbeat stopped.',
                null,
                (int)$call['id']
            );

            communication_log_crm_activity(
                (int)$call['thread_id'],
                'call',
                'Audio call connection lost',
                'The active call was closed after its portal heartbeat stopped.'
            );

            if (function_exists('call_center_sync_communication_call')) {
                call_center_sync_communication_call((int)$call['id']);
            }
        }
    }

    $hours = max(
        1,
        (int)(communication_config()['signal_retention_hours'] ?? 24)
    );

    if (random_int(1, 50) === 1) {
        db()->exec(
            'DELETE FROM communication_call_signals
             WHERE created_at < (
                 UTC_TIMESTAMP() - INTERVAL ' . $hours . ' HOUR
             )'
        );
    }
}

function communication_active_call_for_thread(
    int $threadId,
    array $user
): ?array {
    communication_expire_calls();

    $statement = db()->prepare(
        'SELECT c.*,
                initiator.display_name AS initiator_name,
                recipient.display_name AS recipient_name
         FROM communication_calls c
         JOIN users initiator ON initiator.id = c.initiator_user_id
         JOIN users recipient ON recipient.id = c.recipient_user_id
         WHERE c.thread_id = :thread_id
           AND c.status IN ("ringing", "accepted")
           AND (
               c.initiator_user_id = :user_id
               OR c.recipient_user_id = :user_id
           )
         ORDER BY c.id DESC
         LIMIT 1'
    );
    $statement->execute([
        'thread_id' => $threadId,
        'user_id' => $user['id'],
    ]);
    $call = $statement->fetch();

    return $call ?: null;
}

function communication_incoming_call(array $user): ?array
{
    communication_expire_calls();

    $statement = db()->prepare(
        'SELECT c.*,
                t.subject,
                initiator.display_name AS initiator_name
         FROM communication_calls c
         JOIN communication_threads t ON t.id = c.thread_id
         JOIN users initiator ON initiator.id = c.initiator_user_id
         WHERE c.recipient_user_id = :user_id
           AND c.status = "ringing"
           AND c.expires_at >= UTC_TIMESTAMP()
         ORDER BY c.id DESC
         LIMIT 1'
    );
    $statement->execute(['user_id' => $user['id']]);
    $call = $statement->fetch();

    return $call ?: null;
}

function communication_call_duration(array $call): int
{
    if (empty($call['answered_at'])) {
        return 0;
    }

    try {
        $utc = new DateTimeZone('UTC');
        $start = (
            new DateTimeImmutable(
                (string)$call['answered_at'],
                $utc
            )
        )->getTimestamp();

        $end = !empty($call['ended_at'])
            ? (
                new DateTimeImmutable(
                    (string)$call['ended_at'],
                    $utc
                )
            )->getTimestamp()
            : time();

        return max(0, $end - $start);
    } catch (Throwable) {
        return 0;
    }
}

function communication_create_attachment(
    int $threadId,
    int $uploadedBy,
    string $temporaryPath,
    string $originalName,
    string $extension,
    string $mimeType,
    string $mediaKind,
    int $sizeBytes,
    ?float $durationSeconds = null
): int {
    $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
    $destination = communication_storage_path($storedName);

    if (!move_uploaded_file($temporaryPath, $destination)) {
        if (!rename($temporaryPath, $destination)) {
            throw new RuntimeException('The server could not store the communication file.');
        }
    }

    chmod($destination, 0640);
    $sha256 = hash_file('sha256', $destination);

    if ($sha256 === false) {
        @unlink($destination);
        throw new RuntimeException('The server could not verify the communication file.');
    }

    $statement = db()->prepare(
        'INSERT INTO communication_attachments
            (thread_id, uploaded_by, original_name, stored_name,
             extension, mime_type, media_kind, size_bytes,
             duration_seconds, sha256)
         VALUES
            (:thread_id, :uploaded_by, :original_name, :stored_name,
             :extension, :mime_type, :media_kind, :size_bytes,
             :duration_seconds, :sha256)'
    );
    $statement->execute([
        'thread_id' => $threadId,
        'uploaded_by' => $uploadedBy,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'extension' => $extension,
        'mime_type' => $mimeType,
        'media_kind' => $mediaKind,
        'size_bytes' => $sizeBytes,
        'duration_seconds' => $durationSeconds,
        'sha256' => $sha256,
    ]);

    return (int)db()->lastInsertId();
}

function communication_transcript_to_knowledge(
    int $transcriptId,
    array $user
): int {
    if ($user['role'] !== 'admin') {
        throw new RuntimeException('Only administrators can create Knowledge Center drafts.');
    }

    $statement = db()->prepare(
        'SELECT transcript.*,
                attachment.original_name,
                attachment.stored_name,
                attachment.extension,
                attachment.mime_type,
                attachment.media_kind,
                attachment.size_bytes,
                conversation.subject,
                client.display_name AS client_name
         FROM communication_transcripts transcript
         JOIN communication_threads conversation ON conversation.id = transcript.thread_id
         JOIN users client ON client.id = conversation.client_user_id
         LEFT JOIN communication_attachments attachment
           ON attachment.id = transcript.source_attachment_id
         WHERE transcript.id = :id
           AND transcript.status = "approved"
         LIMIT 1'
    );
    $statement->execute(['id' => $transcriptId]);
    $transcript = $statement->fetch();

    if (!$transcript) {
        throw new RuntimeException('Approve the transcript before creating a Knowledge Center draft.');
    }

    if ((int)($transcript['knowledge_asset_id'] ?? 0) > 0) {
        return (int)$transcript['knowledge_asset_id'];
    }

    $text = knowledge_clean_text(
        (string)(
            $transcript['reviewed_text']
            ?: $transcript['raw_text']
        )
    );

    if ($text === '') {
        throw new RuntimeException('The approved transcript has no text.');
    }

    $extension = (string)($transcript['extension'] ?? 'txt');
    $mimeType = (string)($transcript['mime_type'] ?? 'text/plain');
    $mediaKind = (string)($transcript['media_kind'] ?? 'document');
    $sourcePath = !empty($transcript['stored_name'])
        ? communication_storage_path((string)$transcript['stored_name'])
        : null;

    if ($sourcePath && is_file($sourcePath)) {
        $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = knowledge_storage_path($storedName);

        if (!copy($sourcePath, $destination)) {
            throw new RuntimeException('The source media could not be copied into the Knowledge Center.');
        }

        chmod($destination, 0640);
        $copiedSize = filesize($destination);
        $copiedHash = hash_file('sha256', $destination);

        if ($copiedSize === false || $copiedHash === false) {
            @unlink($destination);
            throw new RuntimeException(
                'The copied Knowledge Center media could not be verified.'
            );
        }

        $sizeBytes = (int)$copiedSize;
        $sha256 = $copiedHash;
        $originalName = (string)$transcript['original_name'];
    } else {
        $extension = 'txt';
        $mimeType = 'text/plain';
        $mediaKind = 'document';
        $storedName = bin2hex(random_bytes(24)) . '.txt';
        $destination = knowledge_storage_path($storedName);
        if (file_put_contents($destination, $text, LOCK_EX) === false) {
            throw new RuntimeException(
                'The transcript file could not be created in the Knowledge Center.'
            );
        }

        chmod($destination, 0640);
        $textSize = filesize($destination);
        $textHash = hash_file('sha256', $destination);

        if ($textSize === false || $textHash === false) {
            @unlink($destination);
            throw new RuntimeException(
                'The Knowledge Center transcript file could not be verified.'
            );
        }

        $sizeBytes = (int)$textSize;
        $sha256 = $textHash;
        $originalName = slugify((string)$transcript['subject']) . '-transcript.txt';
    }

    $title = trim(
        (string)$transcript['subject'] .
        ' — communication transcript'
    );
    $summary = knowledge_auto_summary($text, $title);
    $keywords = implode(', ', knowledge_auto_keywords($text, $title));

    $insert = db()->prepare(
        'INSERT INTO knowledge_assets
            (original_name, stored_name, extension, mime_type, media_kind,
             size_bytes, sha256, title, category, summary, keywords,
             audiences_json, extracted_text, extraction_method,
             extraction_status, is_public, status, uploaded_by)
         VALUES
            (:original_name, :stored_name, :extension, :mime_type, :media_kind,
             :size_bytes, :sha256, :title, "communication-transcript",
             :summary, :keywords, :audiences_json, :extracted_text,
             "reviewed-communication-transcript", "ready", 0, "draft",
             :uploaded_by)'
    );
    $insert->execute([
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'extension' => $extension,
        'mime_type' => $mimeType,
        'media_kind' => $mediaKind,
        'size_bytes' => $sizeBytes,
        'sha256' => $sha256,
        'title' => $title,
        'summary' => $summary,
        'keywords' => $keywords,
        'audiences_json' => json_encode(['client']),
        'extracted_text' => $text,
        'uploaded_by' => $user['id'],
    ]);

    $assetId = (int)db()->lastInsertId();

    db()->prepare(
        'UPDATE communication_transcripts
         SET knowledge_asset_id = :knowledge_asset_id
         WHERE id = :id'
    )->execute([
        'knowledge_asset_id' => $assetId,
        'id' => $transcriptId,
    ]);

    log_activity(
        'communication_transcript_knowledge_draft',
        'knowledge_asset',
        $assetId,
        ['transcript_id' => $transcriptId]
    );

    return $assetId;
}

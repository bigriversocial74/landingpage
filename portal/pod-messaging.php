<?php
declare(strict_types=1);

require_once __DIR__ . '/pod-identity.php';
require_once __DIR__ . '/notifications.php';

function pod_messaging_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    if (!pod_identity_schema_available()) return $available = false;

    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "pod_relationship_message_links","pod_message_threads",
                    "pod_messages","pod_message_receipts","pod_message_events"
               )'
        );
        $available = (int)$statement->fetchColumn() === 5;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function pod_require_messaging_schema(): void
{
    if (!pod_messaging_schema_available()) {
        throw new RuntimeException(
            'Import database/pod_connected_messaging_v63_2.sql before using connected POD messaging.'
        );
    }
}

function pod_message_secret_key(): string
{
    $security = nmm_config('security');
    $app = nmm_config('app');
    $candidates = [
        (string)($security['pod_message_link_secret'] ?? ''),
        (string)($security['pod_call_link_secret'] ?? ''),
        (string)($security['booking_slot_secret'] ?? ''),
        (string)($app['setup_token'] ?? ''),
    ];

    foreach ($candidates as $secret) {
        $secret = trim($secret);
        if (
            strlen($secret) >= 24
            && !str_contains($secret, 'replace-with')
            && !str_contains($secret, 'change-this')
        ) {
            return hash('sha256', 'pod-message-link-v1|' . $secret, true);
        }
    }

    throw new RuntimeException(
        'Configure security.pod_message_link_secret with a private value of at least 24 characters.'
    );
}

function pod_message_uuid(): string
{
    return pod_uuid_v4();
}

function pod_message_valid_uuid(string $value): bool
{
    return (bool)preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        trim($value)
    );
}

function pod_encrypt_message_credential(array $credential): array
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required to protect POD messaging credentials.');
    }

    $plaintext = json_encode($credential, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        pod_message_secret_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'pod-message-link-v1'
    );

    if (!is_string($ciphertext) || $ciphertext === '' || $tag === '') {
        throw new RuntimeException('The POD messaging credential could not be encrypted.');
    }

    return [
        'ciphertext' => base64_encode($ciphertext),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
    ];
}

function pod_decrypt_message_credential(array $record): array
{
    $ciphertext = base64_decode((string)($record['secret_ciphertext'] ?? ''), true);
    $iv = base64_decode((string)($record['secret_iv'] ?? ''), true);
    $tag = base64_decode((string)($record['secret_tag'] ?? ''), true);

    if (!is_string($ciphertext) || !is_string($iv) || !is_string($tag)) return [];

    try {
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            pod_message_secret_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'pod-message-link-v1'
        );
    } catch (Throwable) {
        return [];
    }

    if (!is_string($plaintext) || $plaintext === '') return [];
    $decoded = json_decode($plaintext, true);
    return is_array($decoded) ? $decoded : [];
}

function pod_message_relationship(int $relationshipId): ?array
{
    if ($relationshipId <= 0 || !pod_messaging_schema_available()) return null;

    $statement = db()->prepare(
        'SELECT relationship.*,
                local_identity.pod_uuid AS local_pod_uuid,
                local_identity.display_name AS local_pod_name,
                remote_identity.pod_uuid AS remote_pod_uuid,
                remote_identity.display_name AS remote_pod_name,
                remote_identity.identity_type AS remote_identity_type,
                remote_identity.canonical_origin AS remote_origin,
                remote_identity.profile_url AS remote_profile_url,
                remote_identity.agent_url AS remote_agent_url,
                remote_identity.avatar_url AS remote_avatar_url,
                contact.display_name AS contact_name,
                contact.email AS contact_email,
                contact.phone AS contact_phone,
                contact.company AS contact_company
         FROM pod_relationships relationship
         JOIN pod_identities local_identity
           ON local_identity.id=relationship.local_identity_id
          AND local_identity.is_local=1
         JOIN pod_identities remote_identity
           ON remote_identity.id=relationship.remote_identity_id
          AND remote_identity.is_local=0
         LEFT JOIN crm_contacts contact ON contact.id=relationship.crm_contact_id
         WHERE relationship.id=:relationship_id LIMIT 1'
    );
    $statement->execute(['relationship_id' => $relationshipId]);
    return $statement->fetch() ?: null;
}

function pod_require_messageable_relationship(int $relationshipId): array
{
    $relationship = pod_message_relationship($relationshipId);
    if (!$relationship) throw new RuntimeException('The POD relationship was not found.');
    if ((string)$relationship['status'] !== 'connected') {
        throw new RuntimeException('Connect this POD relationship before enabling messaging.');
    }
    if ((string)$relationship['messaging_permission'] !== 'message') {
        throw new RuntimeException('Set the relationship Messaging permission to Message first.');
    }
    if (in_array((string)$relationship['trust_status'], ['mismatch', 'revoked'], true)) {
        throw new RuntimeException('Messaging is unavailable because the remote POD identity is not trusted.');
    }
    return $relationship;
}

function pod_message_contact_email(array $relationship): string
{
    $email = strtolower(trim((string)($relationship['contact_email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) return $email;

    return 'pod-' . substr(
        hash('sha256', (string)$relationship['remote_pod_uuid']),
        0,
        24
    ) . '@local.invalid';
}

function pod_message_ensure_contact(int $relationshipId, ?int $actorUserId): array
{
    $relationship = pod_require_messageable_relationship($relationshipId);
    $contactId = (int)($relationship['crm_contact_id'] ?? 0);
    $email = pod_message_contact_email($relationship);

    if ($contactId > 0) {
        db()->prepare(
            'UPDATE crm_contacts
             SET email=CASE
                    WHEN email IS NULL OR email="" OR email NOT LIKE "%@%"
                    THEN :email ELSE email END,
                 updated_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute(['email' => $email, 'id' => $contactId]);
        return pod_message_relationship($relationshipId) ?? $relationship;
    }

    $find = db()->prepare('SELECT id FROM crm_contacts WHERE email=:email LIMIT 1');
    $find->execute(['email' => $email]);
    $contactId = (int)($find->fetchColumn() ?: 0);

    if ($contactId <= 0) {
        db()->prepare(
            'INSERT INTO crm_contacts
                (email,display_name,company,lifecycle_stage,source,
                 owner_user_id,last_inquiry_at,notes)
             VALUES
                (:email,:display_name,:company,"partner","pod_relationship",
                 :owner_user_id,UTC_TIMESTAMP(),:notes)'
        )->execute([
            'email' => $email,
            'display_name' => (string)$relationship['remote_pod_name'],
            'company' => null,
            'owner_user_id' => ($actorUserId ?? 0) > 0 ? $actorUserId : null,
            'notes' => 'Connected POD identity: ' . (string)$relationship['remote_pod_uuid'],
        ]);
        $contactId = (int)db()->lastInsertId();
    }

    db()->prepare(
        'UPDATE pod_relationships SET crm_contact_id=:contact_id WHERE id=:relationship_id'
    )->execute(['contact_id' => $contactId, 'relationship_id' => $relationshipId]);

    pod_relationship_event(
        $relationshipId,
        (int)($actorUserId ?? 0),
        'contact_linked',
        (string)$relationship['status'],
        (string)$relationship['status'],
        ['crm_contact_id' => $contactId, 'source' => 'pod_messaging']
    );

    return pod_message_relationship($relationshipId) ?? $relationship;
}

function pod_message_event(
    int $relationshipId,
    string $eventType,
    ?int $threadId = null,
    ?int $messageId = null,
    ?int $linkId = null,
    ?int $actorUserId = null,
    array $metadata = []
): void {
    try {
        db()->prepare(
            'INSERT INTO pod_message_events
                (relationship_id,thread_id,message_id,message_link_id,
                 actor_user_id,event_type,metadata_json)
             VALUES
                (:relationship_id,:thread_id,:message_id,:message_link_id,
                 :actor_user_id,:event_type,:metadata_json)'
        )->execute([
            'relationship_id' => $relationshipId,
            'thread_id' => $threadId,
            'message_id' => $messageId,
            'message_link_id' => $linkId,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'metadata_json' => $metadata
                ? json_encode($metadata, JSON_THROW_ON_ERROR)
                : null,
        ]);
    } catch (Throwable) {
    }
}

function pod_message_receipt(
    int $messageId,
    int $relationshipId,
    string $type,
    ?int $statusCode = null,
    array $details = []
): string {
    $receiptUuid = pod_message_uuid();
    db()->prepare(
        'INSERT INTO pod_message_receipts
            (receipt_uuid,message_id,relationship_id,receipt_type,
             remote_status_code,details_json)
         VALUES
            (:receipt_uuid,:message_id,:relationship_id,:receipt_type,
             :remote_status_code,:details_json)'
    )->execute([
        'receipt_uuid' => $receiptUuid,
        'message_id' => $messageId,
        'relationship_id' => $relationshipId,
        'receipt_type' => $type,
        'remote_status_code' => $statusCode,
        'details_json' => $details
            ? json_encode($details, JSON_THROW_ON_ERROR)
            : null,
    ]);
    return $receiptUuid;
}

function pod_issue_message_link(
    int $relationshipId,
    int $actorUserId,
    int $validDays = 180
): string {
    pod_require_messaging_schema();
    $relationship = pod_message_ensure_contact($relationshipId, $actorUserId);
    $origin = pod_configured_origin();
    if ($origin === '') {
        throw new RuntimeException('Configure app.base_url before issuing POD message links.');
    }

    $validDays = max(1, min(365, $validDays));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $tokenHint = substr($token, 0, 6) . '…' . substr($token, -4);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + ($validDays * 86400));

    $existing = db()->prepare(
        'SELECT id FROM pod_relationship_message_links
         WHERE relationship_id=:relationship_id AND direction="inbound" LIMIT 1'
    );
    $existing->execute(['relationship_id' => $relationshipId]);
    $previousId = (int)($existing->fetchColumn() ?: 0);

    db()->prepare(
        'INSERT INTO pod_relationship_message_links
            (relationship_id,direction,token_hash,token_hint,endpoint_origin,
             endpoint_path,status,expires_at,created_by_user_id,updated_by_user_id)
         VALUES
            (:relationship_id,"inbound",:token_hash,:token_hint,:endpoint_origin,
             "/api/pod-message.php","active",:expires_at,:actor_user_id,:actor_user_id)
         ON DUPLICATE KEY UPDATE
            token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),
            endpoint_origin=VALUES(endpoint_origin),endpoint_path=VALUES(endpoint_path),
            status="active",expires_at=VALUES(expires_at),last_used_at=NULL,
            use_count=0,updated_by_user_id=VALUES(updated_by_user_id)'
    )->execute([
        'relationship_id' => $relationshipId,
        'token_hash' => $tokenHash,
        'token_hint' => $tokenHint,
        'endpoint_origin' => $origin,
        'expires_at' => $expiresAt,
        'actor_user_id' => $actorUserId,
    ]);

    $select = db()->prepare(
        'SELECT id FROM pod_relationship_message_links
         WHERE relationship_id=:relationship_id AND direction="inbound" LIMIT 1'
    );
    $select->execute(['relationship_id' => $relationshipId]);
    $linkId = (int)$select->fetchColumn();

    pod_message_event(
        $relationshipId,
        $previousId > 0 ? 'link_rotated' : 'link_issued',
        null,
        null,
        $linkId,
        $actorUserId,
        ['token_hint' => $tokenHint, 'expires_at' => $expiresAt]
    );
    log_activity(
        $previousId > 0 ? 'pod_message_link_rotated' : 'pod_message_link_issued',
        'pod_relationship',
        $relationshipId,
        ['remote_pod_id' => $relationship['remote_pod_uuid']]
    );

    return $origin . '/api/pod-message.php#access=' . rawurlencode($token);
}

function pod_revoke_message_link(int $relationshipId, int $actorUserId): void
{
    pod_require_messaging_schema();
    $link = pod_message_link_record($relationshipId, 'inbound');
    db()->prepare(
        'UPDATE pod_relationship_message_links
         SET status="revoked",token_hash=NULL,updated_by_user_id=:actor_user_id
         WHERE relationship_id=:relationship_id AND direction="inbound"'
    )->execute([
        'actor_user_id' => $actorUserId,
        'relationship_id' => $relationshipId,
    ]);
    pod_message_event(
        $relationshipId,
        'link_revoked',
        null,
        null,
        $link ? (int)$link['id'] : null,
        $actorUserId
    );
    log_activity('pod_message_link_revoked', 'pod_relationship', $relationshipId);
}

function pod_validate_remote_message_link(string $value, array $relationship): array
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 1800 || !filter_var($value, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Enter a valid connected POD message link.');
    }

    $parts = parse_url($value);
    if (!is_array($parts)) throw new RuntimeException('The connected POD message link is invalid.');
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');
    $fragment = (string)($parts['fragment'] ?? '');

    if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
        throw new RuntimeException('POD message links must use HTTP or HTTPS.');
    }
    if ($path !== '/api/pod-message.php') {
        throw new RuntimeException('The remote link must use /api/pod-message.php.');
    }

    parse_str($fragment, $fragmentValues);
    $token = trim((string)($fragmentValues['access'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new RuntimeException('The remote POD message link is missing its scoped token.');
    }

    $origin = $scheme . '://' . $host;
    if (isset($parts['port'])) $origin .= ':' . (int)$parts['port'];
    $expectedOrigin = pod_normalize_origin((string)($relationship['remote_origin'] ?? ''));
    if ($expectedOrigin === '' || !hash_equals($expectedOrigin, pod_normalize_origin($origin))) {
        throw new RuntimeException('The message link does not match the remote POD canonical origin.');
    }

    return [
        'endpoint' => $origin . $path,
        'origin' => $origin,
        'path' => $path,
        'token' => $token,
    ];
}

function pod_save_remote_message_link(
    int $relationshipId,
    string $value,
    int $actorUserId
): void {
    pod_require_messaging_schema();
    $relationship = pod_message_ensure_contact($relationshipId, $actorUserId);
    $credential = pod_validate_remote_message_link($value, $relationship);
    $encrypted = pod_encrypt_message_credential([
        'endpoint' => $credential['endpoint'],
        'token' => $credential['token'],
    ]);

    db()->prepare(
        'INSERT INTO pod_relationship_message_links
            (relationship_id,direction,endpoint_origin,endpoint_path,
             secret_ciphertext,secret_iv,secret_tag,status,
             created_by_user_id,updated_by_user_id)
         VALUES
            (:relationship_id,"outbound",:endpoint_origin,:endpoint_path,
             :secret_ciphertext,:secret_iv,:secret_tag,"active",
             :actor_user_id,:actor_user_id)
         ON DUPLICATE KEY UPDATE
            endpoint_origin=VALUES(endpoint_origin),endpoint_path=VALUES(endpoint_path),
            secret_ciphertext=VALUES(secret_ciphertext),secret_iv=VALUES(secret_iv),
            secret_tag=VALUES(secret_tag),status="active",
            updated_by_user_id=VALUES(updated_by_user_id)'
    )->execute([
        'relationship_id' => $relationshipId,
        'endpoint_origin' => $credential['origin'],
        'endpoint_path' => $credential['path'],
        'secret_ciphertext' => $encrypted['ciphertext'],
        'secret_iv' => $encrypted['iv'],
        'secret_tag' => $encrypted['tag'],
        'actor_user_id' => $actorUserId,
    ]);

    $link = pod_message_link_record($relationshipId, 'outbound');
    pod_message_event(
        $relationshipId,
        'remote_link_saved',
        null,
        null,
        $link ? (int)$link['id'] : null,
        $actorUserId,
        ['endpoint_origin' => $credential['origin']]
    );
    log_activity('pod_remote_message_link_saved', 'pod_relationship', $relationshipId);
}

function pod_remove_remote_message_link(int $relationshipId, int $actorUserId): void
{
    pod_require_messaging_schema();
    $link = pod_message_link_record($relationshipId, 'outbound');
    db()->prepare(
        'UPDATE pod_relationship_message_links
         SET status="revoked",secret_ciphertext=NULL,secret_iv=NULL,secret_tag=NULL,
             updated_by_user_id=:actor_user_id
         WHERE relationship_id=:relationship_id AND direction="outbound"'
    )->execute([
        'actor_user_id' => $actorUserId,
        'relationship_id' => $relationshipId,
    ]);
    pod_message_event(
        $relationshipId,
        'remote_link_removed',
        null,
        null,
        $link ? (int)$link['id'] : null,
        $actorUserId
    );
    log_activity('pod_remote_message_link_removed', 'pod_relationship', $relationshipId);
}

function pod_message_link_record(int $relationshipId, string $direction): ?array
{
    if (!in_array($direction, ['inbound', 'outbound'], true)) return null;
    $statement = db()->prepare(
        'SELECT * FROM pod_relationship_message_links
         WHERE relationship_id=:relationship_id AND direction=:direction LIMIT 1'
    );
    $statement->execute([
        'relationship_id' => $relationshipId,
        'direction' => $direction,
    ]);
    return $statement->fetch() ?: null;
}

function pod_message_remote_credential(int $relationshipId): array
{
    $link = pod_message_link_record($relationshipId, 'outbound');
    if (!$link || (string)$link['status'] !== 'active') return [];
    return pod_decrypt_message_credential($link);
}

function pod_message_contacts(): array
{
    pod_require_messaging_schema();
    $rows = db()->query(
        'SELECT relationship.*,
                identity.pod_uuid AS remote_pod_uuid,
                identity.display_name AS remote_pod_name,
                identity.identity_type AS remote_identity_type,
                identity.profile_url AS remote_profile_url,
                identity.agent_url AS remote_agent_url,
                identity.avatar_url AS remote_avatar_url,
                contact.display_name AS contact_name,
                contact.company AS contact_company,
                inbound_link.status AS inbound_link_status,
                inbound_link.token_hint AS inbound_token_hint,
                inbound_link.expires_at AS inbound_expires_at,
                inbound_link.use_count AS inbound_use_count,
                outbound_link.status AS outbound_link_status,
                outbound_link.endpoint_origin AS outbound_origin,
                (SELECT COUNT(*)
                 FROM pod_messages message
                 JOIN pod_message_threads thread ON thread.id=message.thread_id
                 WHERE thread.relationship_id=relationship.id
                   AND message.direction="inbound"
                   AND message.read_at IS NULL) AS unread_count,
                (SELECT MAX(thread.last_message_at)
                 FROM pod_message_threads thread
                 WHERE thread.relationship_id=relationship.id) AS last_message_at
         FROM pod_relationships relationship
         JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
         LEFT JOIN crm_contacts contact ON contact.id=relationship.crm_contact_id
         LEFT JOIN pod_relationship_message_links inbound_link
           ON inbound_link.relationship_id=relationship.id
          AND inbound_link.direction="inbound"
         LEFT JOIN pod_relationship_message_links outbound_link
           ON outbound_link.relationship_id=relationship.id
          AND outbound_link.direction="outbound"
         WHERE relationship.status IN ("connected","pending_inbound","pending_outbound")
         ORDER BY COALESCE(last_message_at,relationship.updated_at) DESC,
                  COALESCE(contact.display_name,identity.display_name)'
    )->fetchAll();

    foreach ($rows as &$row) {
        $credential = pod_message_remote_credential((int)$row['id']);
        $row['message_ready'] = (
            (string)$row['status'] === 'connected'
            && (string)$row['messaging_permission'] === 'message'
            && (string)($row['outbound_link_status'] ?? '') === 'active'
            && !empty($credential['endpoint'])
            && !empty($credential['token'])
        );
    }
    unset($row);
    return $rows;
}

function pod_message_threads(int $relationshipId = 0): array
{
    pod_require_messaging_schema();
    $sql = 'SELECT thread.*,
                   relationship.remote_identity_id,
                   identity.pod_uuid AS remote_pod_uuid,
                   identity.display_name AS remote_pod_name,
                   contact.display_name AS contact_name,
                   (SELECT COUNT(*) FROM pod_messages message
                    WHERE message.thread_id=thread.id
                      AND message.direction="inbound"
                      AND message.read_at IS NULL) AS unread_count,
                   (SELECT body FROM pod_messages message
                    WHERE message.thread_id=thread.id
                    ORDER BY message.id DESC LIMIT 1) AS last_message_body,
                   (SELECT delivery_status FROM pod_messages message
                    WHERE message.thread_id=thread.id
                    ORDER BY message.id DESC LIMIT 1) AS last_delivery_status
            FROM pod_message_threads thread
            JOIN pod_relationships relationship ON relationship.id=thread.relationship_id
            JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
            LEFT JOIN crm_contacts contact ON contact.id=thread.crm_contact_id';
    $parameters = [];
    if ($relationshipId > 0) {
        $sql .= ' WHERE thread.relationship_id=:relationship_id';
        $parameters['relationship_id'] = $relationshipId;
    }
    $sql .= ' ORDER BY COALESCE(thread.last_message_at,thread.created_at) DESC,thread.id DESC';
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function pod_message_thread(int $threadId): ?array
{
    if ($threadId <= 0) return null;
    $statement = db()->prepare(
        'SELECT thread.*,
                relationship.status AS relationship_status,
                relationship.messaging_permission,
                relationship.trust_status,
                identity.pod_uuid AS remote_pod_uuid,
                identity.display_name AS remote_pod_name,
                identity.profile_url AS remote_profile_url,
                contact.display_name AS contact_name
         FROM pod_message_threads thread
         JOIN pod_relationships relationship ON relationship.id=thread.relationship_id
         JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
         LEFT JOIN crm_contacts contact ON contact.id=thread.crm_contact_id
         WHERE thread.id=:thread_id LIMIT 1'
    );
    $statement->execute(['thread_id' => $threadId]);
    return $statement->fetch() ?: null;
}

function pod_message_thread_messages(int $threadId): array
{
    if ($threadId <= 0) return [];
    $statement = db()->prepare(
        'SELECT message.*,
                sender.display_name AS sent_by_name
         FROM pod_messages message
         LEFT JOIN users sender ON sender.id=message.sent_by_user_id
         WHERE message.thread_id=:thread_id
         ORDER BY message.id ASC'
    );
    $statement->execute(['thread_id' => $threadId]);
    return $statement->fetchAll();
}

function pod_mark_message_thread_read(int $threadId): void
{
    db()->prepare(
        'UPDATE pod_messages SET read_at=COALESCE(read_at,UTC_TIMESTAMP())
         WHERE thread_id=:thread_id AND direction="inbound"'
    )->execute(['thread_id' => $threadId]);
}

function pod_find_or_create_message_thread(
    int $relationshipId,
    string $conversationUuid,
    string $subject,
    ?int $actorUserId
): array {
    if (!pod_message_valid_uuid($conversationUuid)) {
        throw new RuntimeException('Invalid POD conversation identifier.');
    }
    $subject = trim($subject);
    if ($subject === '' || strlen($subject) > 190) {
        throw new RuntimeException('Enter a conversation subject up to 190 characters.');
    }

    $relationship = pod_message_ensure_contact($relationshipId, $actorUserId);
    $existing = db()->prepare(
        'SELECT * FROM pod_message_threads WHERE conversation_uuid=:uuid LIMIT 1'
    );
    $existing->execute(['uuid' => $conversationUuid]);
    $thread = $existing->fetch();
    if ($thread) {
        if ((int)$thread['relationship_id'] !== $relationshipId) {
            throw new RuntimeException('The conversation identifier belongs to another POD relationship.');
        }
        return $thread;
    }

    db()->prepare(
        'INSERT INTO pod_message_threads
            (conversation_uuid,relationship_id,crm_contact_id,subject,
             status,created_by_user_id)
         VALUES
            (:conversation_uuid,:relationship_id,:crm_contact_id,:subject,
             "open",:created_by_user_id)'
    )->execute([
        'conversation_uuid' => $conversationUuid,
        'relationship_id' => $relationshipId,
        'crm_contact_id' => (int)($relationship['crm_contact_id'] ?? 0) ?: null,
        'subject' => $subject,
        'created_by_user_id' => ($actorUserId ?? 0) > 0 ? $actorUserId : null,
    ]);
    $threadId = (int)db()->lastInsertId();
    pod_message_event(
        $relationshipId,
        'thread_created',
        $threadId,
        null,
        null,
        $actorUserId,
        ['conversation_uuid' => $conversationUuid]
    );
    log_activity('pod_message_thread_created', 'pod_message_thread', $threadId, [
        'remote_pod_id' => $relationship['remote_pod_uuid'],
    ]);

    return pod_message_thread($threadId) ?? [
        'id' => $threadId,
        'conversation_uuid' => $conversationUuid,
        'relationship_id' => $relationshipId,
        'subject' => $subject,
    ];
}

function pod_message_endpoint_target(string $url, string $expectedOrigin): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('The remote POD message endpoint is invalid.');
    }
    $parts = parse_url($url);
    if (!is_array($parts)) throw new RuntimeException('The remote POD message endpoint is invalid.');
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');
    $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);

    if (!in_array($scheme, ['https', 'http'], true) || $host === '' || $path !== '/api/pod-message.php') {
        throw new RuntimeException('The remote POD message endpoint is not allowed.');
    }
    if (!in_array($port, [80, 443], true)) {
        throw new RuntimeException('The remote POD message endpoint uses a blocked port.');
    }
    if (!hash_equals(pod_normalize_origin($expectedOrigin), pod_normalize_origin($url))) {
        throw new RuntimeException('The remote endpoint no longer matches the POD canonical origin.');
    }
    if ($scheme !== 'https' && (bool)(nmm_config('security')['force_https'] ?? false)) {
        throw new RuntimeException('Secure POD messaging requires HTTPS.');
    }

    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip !== '') $ips[] = $ip;
        }
    }
    $ips = array_values(array_unique($ips));
    if (!$ips) throw new RuntimeException('The remote POD hostname could not be resolved.');

    foreach ($ips as $ip) {
        if (!filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            throw new RuntimeException('The remote POD endpoint resolves to a private or reserved network.');
        }
    }

    $chosen = $ips[0];
    $resolveAddress = str_contains($chosen, ':') ? '[' . $chosen . ']' : $chosen;
    return [
        'url' => $scheme . '://' . $host . ($port !== ($scheme === 'https' ? 443 : 80) ? ':' . $port : '') . $path,
        'resolve' => $host . ':' . $port . ':' . $resolveAddress,
    ];
}

function pod_message_http_deliver(
    array $relationship,
    array $credential,
    array $payload
): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The cURL extension is required for POD-to-POD messaging.');
    }

    $endpoint = trim((string)($credential['endpoint'] ?? ''));
    $token = trim((string)($credential['token'] ?? ''));
    if ($endpoint === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new RuntimeException('The remote POD messaging credential is unavailable.');
    }

    $target = pod_message_endpoint_target(
        $endpoint,
        (string)$relationship['remote_origin']
    );
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $timestamp = (string)time();
    $signature = hash_hmac('sha256', $timestamp . "\n" . $body, $token);

    $handle = curl_init($target['url']);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'X-POD-Message-Token: ' . $token,
            'X-POD-Protocol: pod-message-1',
            'X-POD-Timestamp: ' . $timestamp,
            'X-POD-Signature: ' . $signature,
        ],
        CURLOPT_USERAGENT => 'PersonalOnlineDeployment/63.2 (+connected messaging)',
        CURLOPT_RESOLVE => [$target['resolve']],
    ]);

    if (defined('CURLOPT_PROTOCOLS')) {
        curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }
    if (defined('CURLOPT_REDIR_PROTOCOLS')) {
        curl_setopt($handle, CURLOPT_REDIR_PROTOCOLS, 0);
    }

    $responseBody = curl_exec($handle);
    $error = curl_error($handle);
    $statusCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    if (!is_string($responseBody)) {
        throw new RuntimeException('Remote POD delivery failed: ' . ($error !== '' ? $error : 'no response'));
    }
    if (strlen($responseBody) > 1024 * 1024) {
        throw new RuntimeException('The remote POD returned an oversized response.');
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('The remote POD returned an invalid messaging response.');
    }
    if ($statusCode < 200 || $statusCode >= 300 || empty($decoded['ok'])) {
        throw new RuntimeException(
            (string)($decoded['message'] ?? ('Remote POD rejected the message with HTTP ' . $statusCode . '.'))
        );
    }

    return ['status_code' => $statusCode, 'response' => $decoded];
}

function pod_create_outbound_message(
    int $relationshipId,
    string $subject,
    string $body,
    int $actorUserId,
    ?string $conversationUuid = null,
    ?string $inReplyTo = null
): array {
    pod_require_messaging_schema();
    $relationship = pod_message_ensure_contact($relationshipId, $actorUserId);
    $body = trim($body);
    if ($body === '' || strlen($body) > 12000) {
        throw new RuntimeException('Enter a message under 12,000 characters.');
    }
    $conversationUuid = $conversationUuid && pod_message_valid_uuid($conversationUuid)
        ? $conversationUuid
        : pod_message_uuid();
    $thread = pod_find_or_create_message_thread(
        $relationshipId,
        $conversationUuid,
        $subject,
        $actorUserId
    );
    $messageUuid = pod_message_uuid();

    db()->prepare(
        'INSERT INTO pod_messages
            (message_uuid,thread_id,direction,sender_pod_uuid,recipient_pod_uuid,
             sender_display_name,message_type,body,in_reply_to_uuid,
             delivery_status,sent_by_user_id,sent_at)
         VALUES
            (:message_uuid,:thread_id,"outbound",:sender_pod_uuid,:recipient_pod_uuid,
             :sender_display_name,"text",:body,:in_reply_to_uuid,
             "queued",:sent_by_user_id,UTC_TIMESTAMP())'
    )->execute([
        'message_uuid' => $messageUuid,
        'thread_id' => (int)$thread['id'],
        'sender_pod_uuid' => (string)$relationship['local_pod_uuid'],
        'recipient_pod_uuid' => (string)$relationship['remote_pod_uuid'],
        'sender_display_name' => (string)$relationship['local_pod_name'],
        'body' => $body,
        'in_reply_to_uuid' => $inReplyTo && pod_message_valid_uuid($inReplyTo)
            ? $inReplyTo
            : null,
        'sent_by_user_id' => $actorUserId,
    ]);
    $messageId = (int)db()->lastInsertId();
    pod_message_event(
        $relationshipId,
        'message_queued',
        (int)$thread['id'],
        $messageId,
        null,
        $actorUserId
    );

    return pod_deliver_outbound_message($messageId, $actorUserId);
}

function pod_deliver_outbound_message(int $messageId, int $actorUserId): array
{
    $statement = db()->prepare(
        'SELECT message.*,thread.conversation_uuid,thread.subject,
                thread.relationship_id,
                relationship.local_identity_id,relationship.remote_identity_id,
                local_identity.pod_uuid AS local_pod_uuid,
                local_identity.display_name AS local_pod_name,
                remote_identity.pod_uuid AS remote_pod_uuid,
                remote_identity.canonical_origin AS remote_origin
         FROM pod_messages message
         JOIN pod_message_threads thread ON thread.id=message.thread_id
         JOIN pod_relationships relationship ON relationship.id=thread.relationship_id
         JOIN pod_identities local_identity ON local_identity.id=relationship.local_identity_id
         JOIN pod_identities remote_identity ON remote_identity.id=relationship.remote_identity_id
         WHERE message.id=:message_id AND message.direction="outbound" LIMIT 1'
    );
    $statement->execute(['message_id' => $messageId]);
    $message = $statement->fetch();
    if (!$message) throw new RuntimeException('The outbound POD message was not found.');

    $relationship = pod_require_messageable_relationship((int)$message['relationship_id']);
    $credential = pod_message_remote_credential((int)$message['relationship_id']);
    if (!$credential) {
        throw new RuntimeException('Save the remote POD message link before sending.');
    }

    db()->prepare(
        'UPDATE pod_messages
         SET delivery_status="sending",failure_code=NULL,failure_message=NULL
         WHERE id=:id'
    )->execute(['id' => $messageId]);

    $payload = [
        'protocol' => 'pod-message-1',
        'message_uuid' => (string)$message['message_uuid'],
        'conversation_uuid' => (string)$message['conversation_uuid'],
        'sender_pod_id' => (string)$message['local_pod_uuid'],
        'recipient_pod_id' => (string)$message['remote_pod_uuid'],
        'sender_name' => (string)$message['local_pod_name'],
        'subject' => (string)$message['subject'],
        'body' => (string)$message['body'],
        'message_type' => 'text',
        'in_reply_to' => (string)($message['in_reply_to_uuid'] ?? ''),
        'sent_at' => gmdate('c', strtotime((string)$message['sent_at']) ?: time()),
    ];

    try {
        $delivery = pod_message_http_deliver($relationship, $credential, $payload);
        $response = $delivery['response'];
        $receiptUuid = trim((string)($response['receipt_id'] ?? ''));
        $receivedAt = trim((string)($response['received_at'] ?? ''));

        db()->prepare(
            'UPDATE pod_messages
             SET delivery_status="delivered",remote_receipt_uuid=:receipt_uuid,
                 remote_received_at=:received_at,failure_code=NULL,failure_message=NULL
             WHERE id=:id'
        )->execute([
            'receipt_uuid' => pod_message_valid_uuid($receiptUuid) ? $receiptUuid : null,
            'received_at' => $receivedAt !== ''
                ? gmdate('Y-m-d H:i:s', strtotime($receivedAt) ?: time())
                : gmdate('Y-m-d H:i:s'),
            'id' => $messageId,
        ]);
        db()->prepare(
            'UPDATE pod_message_threads
             SET last_message_at=UTC_TIMESTAMP(),last_outbound_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute(['id' => (int)$message['thread_id']]);
        pod_message_receipt(
            $messageId,
            (int)$message['relationship_id'],
            'delivered',
            (int)$delivery['status_code'],
            ['remote_receipt_uuid' => $receiptUuid]
        );
        pod_message_event(
            (int)$message['relationship_id'],
            'message_delivered',
            (int)$message['thread_id'],
            $messageId,
            null,
            $actorUserId
        );
        pod_message_log_crm(
            (int)$message['relationship_id'],
            'email',
            'POD message sent: ' . (string)$message['subject'],
            (string)$message['body'],
            $actorUserId
        );
        log_activity('pod_message_delivered', 'pod_message', $messageId, [
            'relationship_id' => (int)$message['relationship_id'],
        ]);
    } catch (Throwable $exception) {
        db()->prepare(
            'UPDATE pod_messages
             SET delivery_status="failed",failure_code="delivery_failed",
                 failure_message=:failure_message
             WHERE id=:id'
        )->execute([
            'failure_message' => substr($exception->getMessage(), 0, 700),
            'id' => $messageId,
        ]);
        pod_message_receipt(
            $messageId,
            (int)$message['relationship_id'],
            'failed',
            null,
            ['message' => substr($exception->getMessage(), 0, 700)]
        );
        pod_message_event(
            (int)$message['relationship_id'],
            'message_failed',
            (int)$message['thread_id'],
            $messageId,
            null,
            $actorUserId,
            ['message' => substr($exception->getMessage(), 0, 700)]
        );
        throw $exception;
    }

    return pod_message_record($messageId) ?? $message;
}

function pod_retry_outbound_message(int $messageId, int $actorUserId): array
{
    $message = pod_message_record($messageId);
    if (!$message || (string)$message['direction'] !== 'outbound') {
        throw new RuntimeException('The POD message cannot be retried.');
    }
    if (!in_array((string)$message['delivery_status'], ['failed', 'queued'], true)) {
        throw new RuntimeException('Only queued or failed POD messages can be retried.');
    }
    pod_message_receipt(
        $messageId,
        (int)$message['relationship_id'],
        'retried'
    );
    pod_message_event(
        (int)$message['relationship_id'],
        'message_retried',
        (int)$message['thread_id'],
        $messageId,
        null,
        $actorUserId
    );
    return pod_deliver_outbound_message($messageId, $actorUserId);
}

function pod_message_record(int $messageId): ?array
{
    $statement = db()->prepare(
        'SELECT message.*,thread.relationship_id,thread.subject,thread.conversation_uuid
         FROM pod_messages message
         JOIN pod_message_threads thread ON thread.id=message.thread_id
         WHERE message.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $messageId]);
    return $statement->fetch() ?: null;
}

function pod_message_log_crm(
    int $relationshipId,
    string $activityType,
    string $subject,
    string $body,
    ?int $adminUserId
): void {
    try {
        $relationship = pod_message_relationship($relationshipId);
        $contactId = (int)($relationship['crm_contact_id'] ?? 0);
        if ($contactId <= 0) return;
        db()->prepare(
            'INSERT INTO crm_activities
                (contact_id,admin_user_id,activity_type,subject,body)
             VALUES
                (:contact_id,:admin_user_id,:activity_type,:subject,:body)'
        )->execute([
            'contact_id' => $contactId,
            'admin_user_id' => $adminUserId,
            'activity_type' => in_array($activityType, ['email','note','system'], true)
                ? $activityType
                : 'note',
            'subject' => substr($subject, 0, 190),
            'body' => $body,
        ]);
        db()->prepare(
            'UPDATE crm_contacts SET last_contacted_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute(['id' => $contactId]);
    } catch (Throwable) {
    }
}

function pod_message_extract_token(): string
{
    $authorization = trim((string)(
        $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? ''
    ));
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
        return strtolower($matches[1]);
    }
    $fallback = trim((string)($_SERVER['HTTP_X_POD_MESSAGE_TOKEN'] ?? ''));
    return preg_match('/^[a-f0-9]{64}$/i', $fallback) ? strtolower($fallback) : '';
}

function pod_authorize_message_token(string $token): array
{
    pod_require_messaging_schema();
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new RuntimeException('Invalid POD messaging credential.');
    }

    $statement = db()->prepare(
        'SELECT link.id AS message_link_id,link.relationship_id,
                link.status AS link_status,link.expires_at,
                relationship.status AS relationship_status,
                relationship.messaging_permission,relationship.trust_status,
                relationship.crm_contact_id,
                local_identity.pod_uuid AS local_pod_uuid,
                local_identity.display_name AS local_pod_name,
                remote_identity.pod_uuid AS remote_pod_uuid,
                remote_identity.display_name AS remote_pod_name,
                remote_identity.canonical_origin AS remote_origin
         FROM pod_relationship_message_links link
         JOIN pod_relationships relationship ON relationship.id=link.relationship_id
         JOIN pod_identities local_identity ON local_identity.id=relationship.local_identity_id
         JOIN pod_identities remote_identity ON remote_identity.id=relationship.remote_identity_id
         WHERE link.direction="inbound" AND link.token_hash=:token_hash LIMIT 1'
    );
    $statement->execute(['token_hash' => hash('sha256', $token)]);
    $context = $statement->fetch();
    if (!$context) throw new RuntimeException('The POD messaging credential is unavailable.');
    if ((string)$context['link_status'] !== 'active') {
        throw new RuntimeException('The POD messaging credential has been revoked.');
    }
    if (!empty($context['expires_at']) && strtotime((string)$context['expires_at']) < time()) {
        db()->prepare(
            'UPDATE pod_relationship_message_links SET status="expired" WHERE id=:id'
        )->execute(['id' => (int)$context['message_link_id']]);
        throw new RuntimeException('The POD messaging credential expired.');
    }
    if ((string)$context['relationship_status'] !== 'connected') {
        throw new RuntimeException('The POD relationship is no longer connected.');
    }
    if ((string)$context['messaging_permission'] !== 'message') {
        throw new RuntimeException('POD messaging is not permitted for this relationship.');
    }
    if (in_array((string)$context['trust_status'], ['mismatch', 'revoked'], true)) {
        throw new RuntimeException('The remote POD identity cannot be trusted.');
    }

    db()->prepare(
        'UPDATE pod_relationship_message_links
         SET last_used_at=UTC_TIMESTAMP(),use_count=use_count+1 WHERE id=:id'
    )->execute(['id' => (int)$context['message_link_id']]);
    return $context;
}

function pod_verify_message_signature(
    string $token,
    string $rawBody,
    string $timestamp,
    string $signature
): void {
    if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300) {
        throw new RuntimeException('The POD message timestamp is outside the allowed window.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/i', $signature)) {
        throw new RuntimeException('The POD message signature is invalid.');
    }
    $expected = hash_hmac('sha256', $timestamp . "\n" . $rawBody, $token);
    if (!hash_equals($expected, strtolower($signature))) {
        throw new RuntimeException('The POD message signature could not be verified.');
    }
}

function pod_receive_message(array $context, array $payload): array
{
    $messageUuid = strtolower(trim((string)($payload['message_uuid'] ?? '')));
    $conversationUuid = strtolower(trim((string)($payload['conversation_uuid'] ?? '')));
    $senderPod = trim((string)($payload['sender_pod_id'] ?? ''));
    $recipientPod = trim((string)($payload['recipient_pod_id'] ?? ''));
    $senderName = trim((string)($payload['sender_name'] ?? ''));
    $subject = trim((string)($payload['subject'] ?? ''));
    $body = trim((string)($payload['body'] ?? ''));
    $messageType = trim((string)($payload['message_type'] ?? 'text'));
    $inReplyTo = strtolower(trim((string)($payload['in_reply_to'] ?? '')));
    $sentAtValue = trim((string)($payload['sent_at'] ?? ''));

    if (!pod_message_valid_uuid($messageUuid) || !pod_message_valid_uuid($conversationUuid)) {
        throw new RuntimeException('The POD message identifiers are invalid.');
    }
    if (!hash_equals((string)$context['remote_pod_uuid'], $senderPod)) {
        throw new RuntimeException('The sender POD identity does not match the relationship.');
    }
    if (!hash_equals((string)$context['local_pod_uuid'], $recipientPod)) {
        throw new RuntimeException('This POD is not the intended recipient.');
    }
    if ($senderName === '' || strlen($senderName) > 190) {
        throw new RuntimeException('The sender display name is invalid.');
    }
    if ($subject === '' || strlen($subject) > 190) {
        throw new RuntimeException('The POD message subject is invalid.');
    }
    if ($body === '' || strlen($body) > 12000 || $messageType !== 'text') {
        throw new RuntimeException('The POD message body or type is invalid.');
    }
    if ($inReplyTo !== '' && !pod_message_valid_uuid($inReplyTo)) {
        throw new RuntimeException('The reply reference is invalid.');
    }

    $existing = db()->prepare(
        'SELECT message.id,message.thread_id,message.remote_receipt_uuid
         FROM pod_messages message WHERE message.message_uuid=:uuid LIMIT 1'
    );
    $existing->execute(['uuid' => $messageUuid]);
    $duplicate = $existing->fetch();
    if ($duplicate) {
        $receiptUuid = pod_message_uuid();
        pod_message_receipt(
            (int)$duplicate['id'],
            (int)$context['relationship_id'],
            'duplicate'
        );
        return [
            'duplicate' => true,
            'message_id' => (int)$duplicate['id'],
            'thread_id' => (int)$duplicate['thread_id'],
            'receipt_id' => $receiptUuid,
        ];
    }

    $relationship = pod_message_ensure_contact((int)$context['relationship_id'], null);
    $thread = pod_find_or_create_message_thread(
        (int)$context['relationship_id'],
        $conversationUuid,
        $subject,
        null
    );
    $sentAt = strtotime($sentAtValue);
    if ($sentAt === false || abs(time() - $sentAt) > 7 * 86400) $sentAt = time();
    $receiptUuid = pod_message_uuid();

    db()->prepare(
        'INSERT INTO pod_messages
            (message_uuid,thread_id,direction,sender_pod_uuid,recipient_pod_uuid,
             sender_display_name,message_type,body,in_reply_to_uuid,
             delivery_status,remote_receipt_uuid,remote_received_at,sent_at)
         VALUES
            (:message_uuid,:thread_id,"inbound",:sender_pod_uuid,:recipient_pod_uuid,
             :sender_display_name,"text",:body,:in_reply_to_uuid,
             "received",:receipt_uuid,UTC_TIMESTAMP(),:sent_at)'
    )->execute([
        'message_uuid' => $messageUuid,
        'thread_id' => (int)$thread['id'],
        'sender_pod_uuid' => $senderPod,
        'recipient_pod_uuid' => $recipientPod,
        'sender_display_name' => $senderName,
        'body' => $body,
        'in_reply_to_uuid' => $inReplyTo !== '' ? $inReplyTo : null,
        'receipt_uuid' => $receiptUuid,
        'sent_at' => gmdate('Y-m-d H:i:s', $sentAt),
    ]);
    $messageId = (int)db()->lastInsertId();
    db()->prepare(
        'UPDATE pod_message_threads
         SET subject=:subject,last_message_at=UTC_TIMESTAMP(),
             last_inbound_at=UTC_TIMESTAMP(),status="open",
             crm_contact_id=:crm_contact_id
         WHERE id=:id'
    )->execute([
        'subject' => $subject,
        'crm_contact_id' => (int)($relationship['crm_contact_id'] ?? 0) ?: null,
        'id' => (int)$thread['id'],
    ]);
    pod_message_receipt(
        $messageId,
        (int)$context['relationship_id'],
        'accepted'
    );
    pod_message_event(
        (int)$context['relationship_id'],
        'message_received',
        (int)$thread['id'],
        $messageId,
        (int)$context['message_link_id'],
        null,
        ['sender_pod_id' => $senderPod]
    );
    pod_message_log_crm(
        (int)$context['relationship_id'],
        'email',
        'POD message received: ' . $subject,
        $body,
        null
    );

    $adminId = pod_message_default_admin_id();
    if ($adminId > 0) {
        notification_create(
            $adminId,
            'message',
            'New POD message: ' . $subject,
            $senderName . ': ' . substr($body, 0, 350),
            'portal/pod-messages.php?thread=' . (int)$thread['id'],
            'pod_message',
            $messageId,
            'normal'
        );
    }
    log_activity('pod_message_received', 'pod_message', $messageId, [
        'relationship_id' => (int)$context['relationship_id'],
        'remote_pod_id' => $senderPod,
    ]);

    return [
        'duplicate' => false,
        'message_id' => $messageId,
        'thread_id' => (int)$thread['id'],
        'receipt_id' => $receiptUuid,
    ];
}

function pod_message_default_admin_id(): int
{
    $statement = db()->query(
        'SELECT id FROM users
         WHERE role="admin" AND status="active" ORDER BY id LIMIT 1'
    );
    return (int)($statement->fetchColumn() ?: 0);
}

function pod_messaging_discovery(array $document): array
{
    $origin = (string)($document['canonical_origin'] ?? pod_configured_origin());
    $document['capabilities']['messaging'] = [
        'version' => '1.0',
        'relationship_messaging' => true,
        'transport' => 'signed_https_json',
        'endpoint' => $origin !== '' ? $origin . '/api/pod-message.php' : '',
        'credential_exchange' => 'relationship_scoped_link',
        'message_types' => ['text'],
        'maximum_body_characters' => 12000,
    ];
    return $document;
}

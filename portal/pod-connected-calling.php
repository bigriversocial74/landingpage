<?php
declare(strict_types=1);

require_once __DIR__ . '/pod-identity.php';

function pod_connected_calling_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;

    if (!pod_identity_schema_available()) {
        $available = false;
        return false;
    }

    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "pod_relationship_call_links",
                    "pod_connected_call_events"
               )'
        );
        $available = (int)$statement->fetchColumn() === 2;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function pod_require_connected_calling_schema(): void
{
    if (!pod_connected_calling_schema_available()) {
        throw new RuntimeException(
            'Import database/pod_connected_calling_v63_1.sql before managing connected POD calls.'
        );
    }
}

function pod_call_link_secret_key(): string
{
    $security = nmm_config('security');
    $app = nmm_config('app');
    $candidates = [
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
            return hash('sha256', 'pod-connected-call-link-v1|' . $secret, true);
        }
    }

    throw new RuntimeException(
        'Configure security.pod_call_link_secret with a private value of at least 24 characters before storing remote call links.'
    );
}

function pod_encrypt_call_url(string $url): array
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required to protect connected POD call links.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $url,
        'aes-256-gcm',
        pod_call_link_secret_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'pod-call-link-v1'
    );

    if (!is_string($ciphertext) || $ciphertext === '' || $tag === '') {
        throw new RuntimeException('The connected POD call link could not be encrypted.');
    }

    return [
        'ciphertext' => base64_encode($ciphertext),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
    ];
}

function pod_decrypt_call_url(array $record): string
{
    $ciphertext = base64_decode((string)($record['secret_ciphertext'] ?? ''), true);
    $iv = base64_decode((string)($record['secret_iv'] ?? ''), true);
    $tag = base64_decode((string)($record['secret_tag'] ?? ''), true);

    if (!is_string($ciphertext) || !is_string($iv) || !is_string($tag)) {
        return '';
    }

    try {
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            pod_call_link_secret_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'pod-call-link-v1'
        );
    } catch (Throwable) {
        return '';
    }

    return is_string($plaintext) ? $plaintext : '';
}

function pod_connected_call_event(
    int $relationshipId,
    ?int $callLinkId,
    ?int $actorUserId,
    string $eventType,
    array $metadata = []
): void {
    try {
        db()->prepare(
            'INSERT INTO pod_connected_call_events
                (relationship_id,call_link_id,actor_user_id,event_type,metadata_json)
             VALUES
                (:relationship_id,:call_link_id,:actor_user_id,:event_type,:metadata_json)'
        )->execute([
            'relationship_id' => $relationshipId,
            'call_link_id' => $callLinkId,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'metadata_json' => $metadata
                ? json_encode($metadata, JSON_THROW_ON_ERROR)
                : null,
        ]);
    } catch (Throwable) {
    }
}

function pod_call_relationship(int $relationshipId): ?array
{
    if ($relationshipId <= 0 || !pod_connected_calling_schema_available()) return null;

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
         WHERE relationship.id=:relationship_id
         LIMIT 1'
    );
    $statement->execute(['relationship_id' => $relationshipId]);
    return $statement->fetch() ?: null;
}

function pod_require_callable_relationship(int $relationshipId): array
{
    $relationship = pod_call_relationship($relationshipId);
    if (!$relationship) {
        throw new RuntimeException('The POD relationship was not found.');
    }
    if ((string)$relationship['status'] !== 'connected') {
        throw new RuntimeException('Connect this POD relationship before enabling direct calling.');
    }
    if ((string)$relationship['calling_permission'] !== 'call') {
        throw new RuntimeException('Set the relationship calling permission to Call first.');
    }
    if (in_array((string)$relationship['trust_status'], ['mismatch', 'revoked'], true)) {
        throw new RuntimeException('Calling is unavailable because the remote POD identity is not trusted.');
    }

    return $relationship;
}

function pod_relationship_contact_email(array $relationship): string
{
    $email = strtolower(trim((string)($relationship['contact_email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }

    return 'pod-' . substr(
        hash('sha256', (string)$relationship['remote_pod_uuid']),
        0,
        24
    ) . '@local.invalid';
}

function pod_ensure_relationship_contact(int $relationshipId, int $actorUserId): array
{
    $relationship = pod_require_callable_relationship($relationshipId);
    $contactId = (int)($relationship['crm_contact_id'] ?? 0);

    if ($contactId > 0) return $relationship;

    $email = pod_relationship_contact_email($relationship);
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
            'owner_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'notes' => 'Connected POD identity: ' . (string)$relationship['remote_pod_uuid'],
        ]);
        $contactId = (int)db()->lastInsertId();

        db()->prepare(
            'INSERT INTO crm_activities
                (contact_id,admin_user_id,activity_type,subject,body)
             VALUES
                (:contact_id,:admin_user_id,"system",
                 "Connected POD contact created",:body)'
        )->execute([
            'contact_id' => $contactId,
            'admin_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'body' => (string)$relationship['remote_pod_uuid'],
        ]);
    }

    db()->prepare(
        'UPDATE pod_relationships SET crm_contact_id=:crm_contact_id WHERE id=:id'
    )->execute(['crm_contact_id' => $contactId, 'id' => $relationshipId]);

    pod_relationship_event(
        $relationshipId,
        $actorUserId,
        'contact_linked',
        (string)$relationship['status'],
        (string)$relationship['status'],
        ['crm_contact_id' => $contactId]
    );
    log_activity('pod_call_contact_linked', 'pod_relationship', $relationshipId, [
        'crm_contact_id' => $contactId,
    ]);

    return pod_call_relationship($relationshipId) ?? $relationship;
}

function pod_issue_connected_call_link(
    int $relationshipId,
    int $actorUserId,
    int $validDays = 180
): string {
    pod_require_connected_calling_schema();
    $relationship = pod_ensure_relationship_contact($relationshipId, $actorUserId);
    $validDays = max(1, min(365, $validDays));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $tokenHint = substr($token, 0, 6) . '…' . substr($token, -4);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + ($validDays * 86400));

    $existing = db()->prepare(
        'SELECT id,status FROM pod_relationship_call_links
         WHERE relationship_id=:relationship_id AND direction="inbound" LIMIT 1'
    );
    $existing->execute(['relationship_id' => $relationshipId]);
    $previous = $existing->fetch() ?: null;

    db()->prepare(
        'INSERT INTO pod_relationship_call_links
            (relationship_id,direction,token_hash,token_hint,endpoint_origin,
             endpoint_path,status,expires_at,created_by_user_id,updated_by_user_id)
         VALUES
            (:relationship_id,"inbound",:token_hash,:token_hint,:endpoint_origin,
             "/pod-call.php","active",:expires_at,:actor_user_id,:actor_user_id)
         ON DUPLICATE KEY UPDATE
            token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),
            endpoint_origin=VALUES(endpoint_origin),endpoint_path=VALUES(endpoint_path),
            status="active",expires_at=VALUES(expires_at),last_used_at=NULL,
            use_count=0,updated_by_user_id=VALUES(updated_by_user_id)'
    )->execute([
        'relationship_id' => $relationshipId,
        'token_hash' => $tokenHash,
        'token_hint' => $tokenHint,
        'endpoint_origin' => pod_configured_origin(),
        'expires_at' => $expiresAt,
        'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
    ]);

    $select = db()->prepare(
        'SELECT id FROM pod_relationship_call_links
         WHERE relationship_id=:relationship_id AND direction="inbound" LIMIT 1'
    );
    $select->execute(['relationship_id' => $relationshipId]);
    $linkId = (int)$select->fetchColumn();
    $event = $previous ? 'link_rotated' : 'link_issued';

    pod_connected_call_event(
        $relationshipId,
        $linkId > 0 ? $linkId : null,
        $actorUserId,
        $event,
        [
            'remote_pod_id' => $relationship['remote_pod_uuid'],
            'token_hint' => $tokenHint,
            'expires_at' => $expiresAt,
        ]
    );
    log_activity('pod_connected_call_link_' . ($previous ? 'rotated' : 'issued'), 'pod_relationship', $relationshipId, [
        'token_hint' => $tokenHint,
        'expires_at' => $expiresAt,
    ]);

    $origin = pod_configured_origin();
    if ($origin === '') {
        throw new RuntimeException('Configure app.base_url before issuing connected POD call links.');
    }

    return $origin . '/pod-call.php?token=' . rawurlencode($token);
}

function pod_revoke_connected_call_link(int $relationshipId, int $actorUserId): void
{
    pod_require_connected_calling_schema();
    $statement = db()->prepare(
        'SELECT id FROM pod_relationship_call_links
         WHERE relationship_id=:relationship_id AND direction="inbound" LIMIT 1'
    );
    $statement->execute(['relationship_id' => $relationshipId]);
    $linkId = (int)($statement->fetchColumn() ?: 0);

    db()->prepare(
        'UPDATE pod_relationship_call_links
         SET status="revoked",token_hash=NULL,updated_by_user_id=:actor_user_id
         WHERE relationship_id=:relationship_id AND direction="inbound"'
    )->execute([
        'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
        'relationship_id' => $relationshipId,
    ]);

    pod_connected_call_event(
        $relationshipId,
        $linkId > 0 ? $linkId : null,
        $actorUserId,
        'link_revoked'
    );
    log_activity('pod_connected_call_link_revoked', 'pod_relationship', $relationshipId);
}

function pod_validate_remote_call_url(string $url): array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 1800 || !filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Enter a valid connected POD call link.');
    }

    $parts = parse_url($url);
    if (!is_array($parts)) throw new RuntimeException('The connected POD call link is invalid.');
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');

    if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
        throw new RuntimeException('Connected POD call links must use HTTP or HTTPS.');
    }
    if (!str_ends_with($path, '/pod-call.php') && $path !== '/pod-call.php') {
        throw new RuntimeException('The remote link must use the POD connected-call entry point.');
    }
    if (!isset($parts['query']) || !str_contains((string)$parts['query'], 'token=')) {
        throw new RuntimeException('The remote connected-call link is missing its scoped token.');
    }

    $origin = $scheme . '://' . $host;
    if (isset($parts['port'])) $origin .= ':' . (int)$parts['port'];

    return ['url' => $url, 'origin' => $origin, 'path' => $path];
}

function pod_save_remote_call_link(
    int $relationshipId,
    string $url,
    int $actorUserId
): void {
    pod_require_connected_calling_schema();
    pod_require_callable_relationship($relationshipId);
    $validated = pod_validate_remote_call_url($url);
    $encrypted = pod_encrypt_call_url($validated['url']);

    db()->prepare(
        'INSERT INTO pod_relationship_call_links
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
        'endpoint_origin' => $validated['origin'],
        'endpoint_path' => $validated['path'],
        'secret_ciphertext' => $encrypted['ciphertext'],
        'secret_iv' => $encrypted['iv'],
        'secret_tag' => $encrypted['tag'],
        'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
    ]);

    $select = db()->prepare(
        'SELECT id FROM pod_relationship_call_links
         WHERE relationship_id=:relationship_id AND direction="outbound" LIMIT 1'
    );
    $select->execute(['relationship_id' => $relationshipId]);
    $linkId = (int)$select->fetchColumn();

    pod_connected_call_event(
        $relationshipId,
        $linkId > 0 ? $linkId : null,
        $actorUserId,
        'remote_link_saved',
        ['endpoint_origin' => $validated['origin']]
    );
    log_activity('pod_remote_call_link_saved', 'pod_relationship', $relationshipId, [
        'endpoint_origin' => $validated['origin'],
    ]);
}

function pod_remove_remote_call_link(int $relationshipId, int $actorUserId): void
{
    pod_require_connected_calling_schema();
    $statement = db()->prepare(
        'SELECT id FROM pod_relationship_call_links
         WHERE relationship_id=:relationship_id AND direction="outbound" LIMIT 1'
    );
    $statement->execute(['relationship_id' => $relationshipId]);
    $linkId = (int)($statement->fetchColumn() ?: 0);

    db()->prepare(
        'UPDATE pod_relationship_call_links
         SET status="revoked",secret_ciphertext=NULL,secret_iv=NULL,secret_tag=NULL,
             updated_by_user_id=:actor_user_id
         WHERE relationship_id=:relationship_id AND direction="outbound"'
    )->execute([
        'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
        'relationship_id' => $relationshipId,
    ]);

    pod_connected_call_event(
        $relationshipId,
        $linkId > 0 ? $linkId : null,
        $actorUserId,
        'remote_link_removed'
    );
    log_activity('pod_remote_call_link_removed', 'pod_relationship', $relationshipId);
}

function pod_call_link_record(int $relationshipId, string $direction): ?array
{
    if (!in_array($direction, ['inbound', 'outbound'], true)) return null;
    $statement = db()->prepare(
        'SELECT * FROM pod_relationship_call_links
         WHERE relationship_id=:relationship_id AND direction=:direction LIMIT 1'
    );
    $statement->execute([
        'relationship_id' => $relationshipId,
        'direction' => $direction,
    ]);
    return $statement->fetch() ?: null;
}

function pod_remote_call_url(int $relationshipId): string
{
    if (!pod_connected_calling_schema_available()) return '';
    $record = pod_call_link_record($relationshipId, 'outbound');
    if (!$record || (string)$record['status'] !== 'active') return '';
    return pod_decrypt_call_url($record);
}

function pod_connected_contacts(): array
{
    pod_require_connected_calling_schema();
    $rows = db()->query(
        'SELECT relationship.*,
                identity.pod_uuid AS remote_pod_uuid,
                identity.display_name AS remote_pod_name,
                identity.identity_type AS remote_identity_type,
                identity.profile_url AS remote_profile_url,
                identity.agent_url AS remote_agent_url,
                identity.avatar_url AS remote_avatar_url,
                contact.display_name AS contact_name,
                contact.email AS contact_email,
                contact.phone AS contact_phone,
                contact.company AS contact_company,
                inbound_link.id AS inbound_link_id,
                inbound_link.status AS inbound_link_status,
                inbound_link.token_hint AS inbound_token_hint,
                inbound_link.expires_at AS inbound_expires_at,
                inbound_link.last_used_at AS inbound_last_used_at,
                inbound_link.use_count AS inbound_use_count,
                outbound_link.id AS outbound_link_id,
                outbound_link.status AS outbound_link_status,
                outbound_link.endpoint_origin AS outbound_origin,
                outbound_link.secret_ciphertext,
                outbound_link.secret_iv,
                outbound_link.secret_tag
         FROM pod_relationships relationship
         JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
         LEFT JOIN crm_contacts contact ON contact.id=relationship.crm_contact_id
         LEFT JOIN pod_relationship_call_links inbound_link
           ON inbound_link.relationship_id=relationship.id
          AND inbound_link.direction="inbound"
         LEFT JOIN pod_relationship_call_links outbound_link
           ON outbound_link.relationship_id=relationship.id
          AND outbound_link.direction="outbound"
         WHERE relationship.status IN ("connected","pending_inbound","pending_outbound")
         ORDER BY FIELD(relationship.status,"connected","pending_inbound","pending_outbound"),
                  COALESCE(contact.display_name,identity.display_name),identity.id'
    )->fetchAll();

    foreach ($rows as &$row) {
        $row['remote_call_url'] = '';
        if ((string)($row['outbound_link_status'] ?? '') === 'active') {
            $row['remote_call_url'] = pod_decrypt_call_url($row);
        }
        unset($row['secret_ciphertext'], $row['secret_iv'], $row['secret_tag']);
    }
    unset($row);

    return $rows;
}

function pod_authorize_connected_call_token(string $token): array
{
    pod_require_connected_calling_schema();
    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new RuntimeException('The connected POD call link is invalid.');
    }

    $hash = hash('sha256', $token);
    $statement = db()->prepare(
        'SELECT link.id AS call_link_id,link.relationship_id,link.status AS link_status,
                link.expires_at,relationship.status AS relationship_status,
                relationship.calling_permission,relationship.trust_status,
                relationship.crm_contact_id,
                identity.pod_uuid AS remote_pod_uuid,
                identity.display_name AS remote_pod_name,
                identity.identity_type AS remote_identity_type,
                identity.avatar_url AS remote_avatar_url,
                contact.display_name AS contact_name,
                contact.email AS contact_email,
                contact.phone AS contact_phone,
                contact.company AS contact_company
         FROM pod_relationship_call_links link
         JOIN pod_relationships relationship ON relationship.id=link.relationship_id
         JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
         LEFT JOIN crm_contacts contact ON contact.id=relationship.crm_contact_id
         WHERE link.direction="inbound" AND link.token_hash=:token_hash
         LIMIT 1'
    );
    $statement->execute(['token_hash' => $hash]);
    $context = $statement->fetch();

    if (!$context) throw new RuntimeException('The connected POD call link is unavailable.');
    if ((string)$context['link_status'] !== 'active') {
        throw new RuntimeException('The connected POD call link has been revoked.');
    }
    if (!empty($context['expires_at']) && strtotime((string)$context['expires_at']) < time()) {
        db()->prepare('UPDATE pod_relationship_call_links SET status="expired" WHERE id=:id')
            ->execute(['id' => (int)$context['call_link_id']]);
        throw new RuntimeException('The connected POD call link expired.');
    }
    if ((string)$context['relationship_status'] !== 'connected') {
        throw new RuntimeException('The POD relationship is no longer connected.');
    }
    if ((string)$context['calling_permission'] !== 'call') {
        throw new RuntimeException('Connected calling is not permitted for this relationship.');
    }
    if (in_array((string)$context['trust_status'], ['mismatch', 'revoked'], true)) {
        throw new RuntimeException('The connected POD identity cannot be verified.');
    }

    db()->prepare(
        'UPDATE pod_relationship_call_links
         SET last_used_at=UTC_TIMESTAMP(),use_count=use_count+1 WHERE id=:id'
    )->execute(['id' => (int)$context['call_link_id']]);

    $_SESSION['pod_connected_call_context'] = [
        'call_link_id' => (int)$context['call_link_id'],
        'relationship_id' => (int)$context['relationship_id'],
        'authorized_at' => time(),
    ];

    pod_connected_call_event(
        (int)$context['relationship_id'],
        (int)$context['call_link_id'],
        null,
        'call_context_opened',
        ['remote_pod_id' => $context['remote_pod_uuid']]
    );

    return $context;
}

function pod_connected_call_context(): ?array
{
    if (!pod_connected_calling_schema_available()) return null;
    $session = $_SESSION['pod_connected_call_context'] ?? null;
    if (!is_array($session)) return null;

    $authorizedAt = (int)($session['authorized_at'] ?? 0);
    if ($authorizedAt <= 0 || time() - $authorizedAt > 30 * 60) {
        unset($_SESSION['pod_connected_call_context']);
        return null;
    }

    $statement = db()->prepare(
        'SELECT link.id AS call_link_id,link.relationship_id,link.status AS link_status,
                link.expires_at,relationship.status AS relationship_status,
                relationship.calling_permission,relationship.trust_status,
                relationship.crm_contact_id,
                identity.pod_uuid AS remote_pod_uuid,
                identity.display_name AS remote_pod_name,
                identity.identity_type AS remote_identity_type,
                identity.avatar_url AS remote_avatar_url,
                contact.display_name AS contact_name,
                contact.email AS contact_email,
                contact.phone AS contact_phone,
                contact.company AS contact_company
         FROM pod_relationship_call_links link
         JOIN pod_relationships relationship ON relationship.id=link.relationship_id
         JOIN pod_identities identity ON identity.id=relationship.remote_identity_id
         LEFT JOIN crm_contacts contact ON contact.id=relationship.crm_contact_id
         WHERE link.id=:call_link_id
           AND link.relationship_id=:relationship_id
           AND link.direction="inbound"
         LIMIT 1'
    );
    $statement->execute([
        'call_link_id' => (int)($session['call_link_id'] ?? 0),
        'relationship_id' => (int)($session['relationship_id'] ?? 0),
    ]);
    $context = $statement->fetch();

    if (
        !$context
        || (string)$context['link_status'] !== 'active'
        || (string)$context['relationship_status'] !== 'connected'
        || (string)$context['calling_permission'] !== 'call'
        || in_array((string)$context['trust_status'], ['mismatch', 'revoked'], true)
        || (!empty($context['expires_at']) && strtotime((string)$context['expires_at']) < time())
    ) {
        unset($_SESSION['pod_connected_call_context']);
        return null;
    }

    return $context;
}

function pod_clear_connected_call_context(): void
{
    unset($_SESSION['pod_connected_call_context']);
}

function pod_connected_caller_values(array $context): array
{
    return [
        'display_name' => trim((string)($context['contact_name'] ?? ''))
            ?: (string)$context['remote_pod_name'],
        'email' => pod_relationship_contact_email($context),
        'phone' => trim((string)($context['contact_phone'] ?? '')),
        'company' => trim((string)($context['contact_company'] ?? '')),
        'subject' => 'Connected POD call · ' . (string)$context['remote_pod_name'],
    ];
}

function pod_record_outbound_call_launch(int $relationshipId, int $actorUserId): void
{
    $relationship = pod_require_callable_relationship($relationshipId);
    $link = pod_call_link_record($relationshipId, 'outbound');
    if (!$link || (string)$link['status'] !== 'active' || pod_decrypt_call_url($link) === '') {
        throw new RuntimeException('The remote connected-call link is unavailable.');
    }

    db()->prepare(
        'UPDATE pod_relationship_call_links
         SET last_used_at=UTC_TIMESTAMP(),use_count=use_count+1
         WHERE id=:id'
    )->execute(['id' => (int)$link['id']]);

    pod_connected_call_event(
        $relationshipId,
        (int)$link['id'],
        $actorUserId,
        'call_launched',
        ['remote_pod_id' => $relationship['remote_pod_uuid']]
    );
    log_activity('pod_connected_call_launched', 'pod_relationship', $relationshipId, [
        'remote_pod_id' => $relationship['remote_pod_uuid'],
    ]);
}

function pod_connected_calling_discovery(array $document): array
{
    $origin = (string)($document['canonical_origin'] ?? pod_configured_origin());
    $document['capabilities']['calling'] = [
        'version' => '1.1',
        'public_browser_calling' => true,
        'relationship_calling' => true,
        'direct_only' => true,
        'public_url' => $origin !== '' ? $origin . '/call-dave.php' : '',
        'relationship_entry' => $origin !== '' ? $origin . '/pod-call.php' : '',
        'modes' => [
            'public_browser_audio',
            'connected_pod_audio',
            'voicemail',
        ],
    ];

    return $document;
}

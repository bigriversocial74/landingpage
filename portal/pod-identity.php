<?php
declare(strict_types=1);

function pod_identity_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;

    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "pod_identities","pod_identity_origins",
                    "pod_relationships","pod_relationship_events"
               )'
        );
        $available = (int)$statement->fetchColumn() === 4;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function pod_require_identity_schema(): void
{
    if (!pod_identity_schema_available()) {
        throw new RuntimeException(
            'Import database/pod_identity_relationships_v63.sql before managing POD connections.'
        );
    }
}

function pod_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8), substr($hex, 8, 4),
        substr($hex, 12, 4), substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function pod_normalize_origin(string $origin): string
{
    $origin = trim($origin);
    if ($origin === '') return '';

    $parts = parse_url($origin);
    if (!is_array($parts)) {
        throw new RuntimeException('Enter a valid POD origin URL.');
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $port = isset($parts['port']) ? (int)$parts['port'] : null;

    if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
        throw new RuntimeException('POD origins must use an HTTP or HTTPS URL.');
    }

    $value = $scheme . '://' . $host;
    if ($port !== null && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
        $value .= ':' . $port;
    }

    return $value;
}

function pod_configured_origin(): string
{
    try {
        return pod_normalize_origin((string)(nmm_config('app')['base_url'] ?? ''));
    } catch (Throwable) {
        return '';
    }
}

function pod_public_username(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? '';
    $value = trim($value, '-._');
    return substr($value !== '' ? $value : 'pod-owner', 0, 120);
}

function pod_validate_public_url(string $url, string $origin, string $label): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException($label . ' must be a valid public URL.');
    }
    if ($origin !== '' && !hash_equals($origin, pod_normalize_origin($url))) {
        throw new RuntimeException($label . ' must use the POD canonical origin.');
    }
    return $url;
}

function pod_local_identity(bool $create = true): ?array
{
    if (!pod_identity_schema_available()) return null;

    $identity = db()->query(
        'SELECT * FROM pod_identities
         WHERE local_key="primary" AND is_local=1 LIMIT 1'
    )->fetch();

    if ($identity || !$create) return $identity ?: null;

    $admin = primary_admin_profile();
    $name = trim((string)($admin['display_name'] ?? setting('site_name', 'Personal POD')));
    $name = $name !== '' ? $name : 'Personal POD';
    $origin = pod_configured_origin();

    try {
        db()->prepare(
            'INSERT INTO pod_identities
                (pod_uuid,local_key,is_local,identity_type,owner_user_id,
                 display_name,public_username,canonical_origin,profile_url,
                 agent_url,main_feed_url,verification_status,status,
                 discovered_at,last_verified_at)
             VALUES
                (:pod_uuid,"primary",1,"personal_pod",:owner_user_id,
                 :display_name,:public_username,:canonical_origin,:profile_url,
                 :agent_url,:main_feed_url,"local","active",
                 UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        )->execute([
            'pod_uuid' => 'pod:' . pod_uuid_v4(),
            'owner_user_id' => (int)($admin['id'] ?? 0) ?: null,
            'display_name' => substr($name, 0, 190),
            'public_username' => pod_public_username($name),
            'canonical_origin' => $origin !== '' ? $origin : null,
            'profile_url' => $origin !== '' ? $origin . '/index.php' : null,
            'agent_url' => $origin !== '' ? $origin . '/index.php#chat' : null,
            'main_feed_url' => $origin !== '' ? $origin . '/blog-feed.php' : null,
        ]);
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() !== '23000') throw $exception;
    }

    $identity = db()->query(
        'SELECT * FROM pod_identities
         WHERE local_key="primary" AND is_local=1 LIMIT 1'
    )->fetch();

    if (!$identity) {
        throw new RuntimeException('The local POD identity could not be initialized.');
    }

    pod_sync_primary_origin((int)$identity['id'], (string)($identity['canonical_origin'] ?? ''));
    log_activity('pod_identity_initialized', 'pod_identity', (int)$identity['id'], [
        'pod_uuid' => $identity['pod_uuid'],
    ]);

    return $identity;
}

function pod_sync_primary_origin(int $identityId, string $origin): void
{
    if ($identityId <= 0 || trim($origin) === '') return;
    $origin = pod_normalize_origin($origin);

    db()->prepare(
        'UPDATE pod_identity_origins
         SET is_primary=0,
             status=CASE WHEN status="verified" THEN "previous" ELSE status END
         WHERE pod_identity_id=:identity_id AND origin<>:origin'
    )->execute(['identity_id' => $identityId, 'origin' => $origin]);

    db()->prepare(
        'INSERT INTO pod_identity_origins
            (pod_identity_id,origin,status,verification_method,verified_at,
             first_seen_at,last_seen_at,is_primary)
         VALUES
            (:identity_id,:origin,"verified","local_configuration",UTC_TIMESTAMP(),
             UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)
         ON DUPLICATE KEY UPDATE
            status="verified",verification_method="local_configuration",
            verified_at=COALESCE(verified_at,UTC_TIMESTAMP()),
            last_seen_at=UTC_TIMESTAMP(),is_primary=1'
    )->execute(['identity_id' => $identityId, 'origin' => $origin]);
}

function pod_save_local_identity(array $values, int $actorUserId): array
{
    pod_require_identity_schema();
    $identity = pod_local_identity(true);
    if (!$identity) throw new RuntimeException('The local POD identity is unavailable.');

    $types = [
        'personal_pod','business_pod','artist_pod','project_pod',
        'organization_pod','group_pod'
    ];
    $type = in_array((string)($values['identity_type'] ?? ''), $types, true)
        ? (string)$values['identity_type'] : 'personal_pod';
    $name = trim((string)($values['display_name'] ?? ''));
    $username = pod_public_username((string)($values['public_username'] ?? $name));
    $summary = trim((string)($values['summary'] ?? ''));
    $origin = pod_normalize_origin((string)($values['canonical_origin'] ?? ''));

    if ($name === '' || strlen($name) > 190) {
        throw new RuntimeException('Enter a POD display name up to 190 characters.');
    }
    if ($origin === '') {
        throw new RuntimeException('Configure the canonical POD origin.');
    }

    $profile = pod_validate_public_url((string)($values['profile_url'] ?? ''), $origin, 'Profile URL');
    $agent = pod_validate_public_url((string)($values['agent_url'] ?? ''), $origin, 'Agent URL');
    $feed = pod_validate_public_url((string)($values['main_feed_url'] ?? ''), $origin, 'Main feed URL');
    $avatar = pod_validate_public_url((string)($values['avatar_url'] ?? ''), $origin, 'Avatar URL');

    db()->prepare(
        'UPDATE pod_identities
         SET identity_type=:identity_type,display_name=:display_name,
             public_username=:public_username,summary=:summary,
             canonical_origin=:canonical_origin,profile_url=:profile_url,
             agent_url=:agent_url,main_feed_url=:main_feed_url,
             avatar_url=:avatar_url,verification_status="local",
             status="active",last_verified_at=UTC_TIMESTAMP()
         WHERE id=:id AND local_key="primary" AND is_local=1'
    )->execute([
        'identity_type' => $type,
        'display_name' => $name,
        'public_username' => $username,
        'summary' => $summary !== '' ? substr($summary, 0, 700) : null,
        'canonical_origin' => $origin,
        'profile_url' => $profile !== '' ? $profile : null,
        'agent_url' => $agent !== '' ? $agent : null,
        'main_feed_url' => $feed !== '' ? $feed : null,
        'avatar_url' => $avatar !== '' ? $avatar : null,
        'id' => (int)$identity['id'],
    ]);

    pod_sync_primary_origin((int)$identity['id'], $origin);
    log_activity('pod_identity_updated', 'pod_identity', (int)$identity['id'], [
        'actor_user_id' => $actorUserId,
        'identity_type' => $type,
        'canonical_origin' => $origin,
    ]);

    return pod_local_identity(false) ?? $identity;
}

function pod_discovery_document(): array
{
    pod_require_identity_schema();
    $identity = pod_local_identity(true);
    if (!$identity) throw new RuntimeException('POD identity is unavailable.');

    $origin = (string)($identity['canonical_origin'] ?? '');
    $profile = (string)($identity['profile_url'] ?? '');
    $agent = (string)($identity['agent_url'] ?? '');
    $feed = (string)($identity['main_feed_url'] ?? '');

    return [
        'protocol' => 'pod-1',
        'identity_schema_version' => 1,
        'pod_id' => (string)$identity['pod_uuid'],
        'identity_type' => (string)$identity['identity_type'],
        'name' => (string)$identity['display_name'],
        'username' => (string)$identity['public_username'],
        'summary' => (string)($identity['summary'] ?? ''),
        'canonical_origin' => $origin,
        'profile' => $profile,
        'agent' => $agent,
        'avatar' => (string)($identity['avatar_url'] ?? ''),
        'feeds' => [
            'main' => $feed,
            'blog' => $origin !== '' ? $origin . '/blog-feed.php' : '',
            'atom' => $origin !== '' ? $origin . '/blog-atom.php' : '',
            'json' => $origin !== '' ? $origin . '/blog-json-feed.php' : '',
            'podcast' => $origin !== '' ? $origin . '/podcast-feed.php' : '',
        ],
        'capabilities' => [
            'profile' => ['version' => '1', 'url' => $profile],
            'feeds' => ['version' => '1', 'formats' => ['rss', 'atom', 'json_feed', 'podcast_rss']],
            'activitypub' => [
                'version' => '1',
                'status' => setting('activitypub_enabled', '0') === '1'
                    ? 'available'
                    : 'disabled',
                'actor' => $origin !== '' ? $origin . '/activitypub-actor.php' : '',
                'inbox' => $origin !== '' ? $origin . '/activitypub-inbox.php' : '',
                'outbox' => $origin !== '' ? $origin . '/activitypub-outbox.php' : '',
            ],
            'public_agent' => [
                'version' => '1',
                'status' => $agent !== '' ? 'available' : 'unavailable',
                'url' => $agent,
            ],
            'messaging' => [
                'version' => '1',
                'relationship_messaging' => false,
                'status' => 'foundation',
            ],
            'calling' => [
                'version' => '1',
                'public_browser_calling' => true,
                'relationship_calling' => false,
                'direct_only' => true,
                'public_url' => $origin !== '' ? $origin . '/call-dave.php' : '',
                'modes' => ['public_browser_audio', 'voicemail'],
            ],
        ],
        'public_key' => !empty($identity['public_key']) ? [
            'algorithm' => (string)($identity['key_algorithm'] ?? ''),
            'value' => (string)$identity['public_key'],
        ] : null,
        'updated_at' => gmdate('c', strtotime((string)$identity['updated_at']) ?: time()),
    ];
}

function pod_remote_identities(): array
{
    pod_require_identity_schema();
    return db()->query(
        'SELECT identity.*,
                relationship.id AS relationship_id,
                relationship.relationship_type,
                relationship.direction,
                relationship.status AS relationship_status,
                relationship.trust_status,
                relationship.messaging_permission,
                relationship.calling_permission,
                relationship.agent_permission,
                relationship.crm_contact_id,
                relationship.notes AS relationship_notes,
                contact.display_name AS contact_name
         FROM pod_identities identity
         LEFT JOIN pod_relationships relationship
           ON relationship.remote_identity_id=identity.id
          AND relationship.local_identity_id=(
              SELECT local.id FROM pod_identities local
              WHERE local.local_key="primary" AND local.is_local=1 LIMIT 1
          )
         LEFT JOIN crm_contacts contact ON contact.id=relationship.crm_contact_id
         WHERE identity.is_local=0
         ORDER BY COALESCE(relationship.updated_at,identity.updated_at) DESC,
                  identity.display_name ASC'
    )->fetchAll();
}

function pod_crm_contacts(): array
{
    pod_require_identity_schema();
    return db()->query(
        'SELECT id,display_name,email,company,lifecycle_stage
         FROM crm_contacts ORDER BY display_name,id'
    )->fetchAll();
}

function pod_find_identity(int $identityId): ?array
{
    if ($identityId <= 0) return null;
    $statement = db()->prepare('SELECT * FROM pod_identities WHERE id=:id LIMIT 1');
    $statement->execute(['id' => $identityId]);
    return $statement->fetch() ?: null;
}

function pod_record_remote_origin(int $identityId, string $origin): void
{
    if ($identityId <= 0 || $origin === '') return;
    db()->prepare(
        'INSERT INTO pod_identity_origins
            (pod_identity_id,origin,status,verification_method,
             first_seen_at,last_seen_at,is_primary)
         VALUES
            (:identity_id,:origin,"pending","manual_discovery",
             UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)
         ON DUPLICATE KEY UPDATE
            last_seen_at=UTC_TIMESTAMP(),is_primary=1,
            status=CASE WHEN status="verified" THEN "verified" ELSE "pending" END'
    )->execute(['identity_id' => $identityId, 'origin' => $origin]);
}

function pod_upsert_remote_identity(array $values, int $actorUserId): array
{
    pod_require_identity_schema();
    $local = pod_local_identity(true);
    $podId = trim((string)($values['pod_uuid'] ?? ''));
    $name = trim((string)($values['display_name'] ?? ''));
    $origin = pod_normalize_origin((string)($values['canonical_origin'] ?? ''));

    if (!preg_match('/^pod:[A-Za-z0-9._:-]{8,76}$/', $podId)) {
        throw new RuntimeException('Enter a valid permanent POD ID beginning with pod:.');
    }
    if ($local && hash_equals((string)$local['pod_uuid'], $podId)) {
        throw new RuntimeException('A POD cannot create a remote identity for itself.');
    }
    if ($name === '' || strlen($name) > 190) {
        throw new RuntimeException('Enter the remote POD display name.');
    }

    $profile = pod_validate_public_url((string)($values['profile_url'] ?? ''), $origin, 'Remote profile URL');
    $agent = pod_validate_public_url((string)($values['agent_url'] ?? ''), $origin, 'Remote agent URL');
    $feed = pod_validate_public_url((string)($values['main_feed_url'] ?? ''), $origin, 'Remote feed URL');
    $types = ['personal_pod','business_pod','artist_pod','project_pod','organization_pod','group_pod'];
    $type = in_array((string)($values['identity_type'] ?? ''), $types, true)
        ? (string)$values['identity_type'] : 'personal_pod';

    db()->prepare(
        'INSERT INTO pod_identities
            (pod_uuid,is_local,identity_type,display_name,public_username,
             canonical_origin,profile_url,agent_url,main_feed_url,
             verification_status,status,discovered_at)
         VALUES
            (:pod_uuid,0,:identity_type,:display_name,:public_username,
             :canonical_origin,:profile_url,:agent_url,:main_feed_url,
             "discovered","active",UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            display_name=VALUES(display_name),
            public_username=VALUES(public_username),
            canonical_origin=VALUES(canonical_origin),
            profile_url=VALUES(profile_url),agent_url=VALUES(agent_url),
            main_feed_url=VALUES(main_feed_url),
            verification_status=CASE
                WHEN verification_status="verified" THEN "verified"
                ELSE "discovered"
            END,
            discovered_at=COALESCE(discovered_at,UTC_TIMESTAMP()),status="active"'
    )->execute([
        'pod_uuid' => $podId,
        'identity_type' => $type,
        'display_name' => $name,
        'public_username' => pod_public_username((string)($values['public_username'] ?? $name)),
        'canonical_origin' => $origin,
        'profile_url' => $profile !== '' ? $profile : null,
        'agent_url' => $agent !== '' ? $agent : null,
        'main_feed_url' => $feed !== '' ? $feed : null,
    ]);

    $select = db()->prepare('SELECT * FROM pod_identities WHERE pod_uuid=:pod_uuid LIMIT 1');
    $select->execute(['pod_uuid' => $podId]);
    $identity = $select->fetch();
    if (!$identity) throw new RuntimeException('The remote POD identity could not be saved.');

    pod_record_remote_origin((int)$identity['id'], $origin);
    log_activity('pod_remote_identity_saved', 'pod_identity', (int)$identity['id'], [
        'actor_user_id' => $actorUserId,
        'pod_uuid' => $podId,
        'canonical_origin' => $origin,
    ]);

    return $identity;
}

function pod_relationship_event(
    int $relationshipId,
    int $actorUserId,
    string $eventType,
    ?string $previousStatus,
    ?string $newStatus,
    array $metadata = []
): void {
    db()->prepare(
        'INSERT INTO pod_relationship_events
            (relationship_id,actor_user_id,event_type,previous_status,new_status,metadata_json)
         VALUES
            (:relationship_id,:actor_user_id,:event_type,:previous_status,:new_status,:metadata_json)'
    )->execute([
        'relationship_id' => $relationshipId,
        'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
        'event_type' => $eventType,
        'previous_status' => $previousStatus,
        'new_status' => $newStatus,
        'metadata_json' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
    ]);
}

function pod_save_relationship(array $values, int $actorUserId): array
{
    pod_require_identity_schema();
    $local = pod_local_identity(true);
    $remoteId = (int)($values['remote_identity_id'] ?? 0);
    $remote = pod_find_identity($remoteId);
    if (!$local || !$remote || (int)$remote['is_local'] === 1) {
        throw new RuntimeException('Select a valid remote POD identity.');
    }

    $allowed = [
        'relationship_type' => ['personal','family','friend','professional','client','prospect','collaborator','vendor','investor','community','other'],
        'direction' => ['inbound','outbound','mutual'],
        'status' => ['pending_inbound','pending_outbound','connected','blocked','disconnected'],
        'trust_status' => ['unverified','discovered','verified','mismatch','revoked'],
        'messaging_permission' => ['none','request','message'],
        'calling_permission' => ['none','request','call'],
        'agent_permission' => ['none','public','relationship'],
    ];
    $defaults = [
        'relationship_type' => 'professional', 'direction' => 'outbound',
        'status' => 'pending_outbound', 'trust_status' => 'discovered',
        'messaging_permission' => 'request', 'calling_permission' => 'request',
        'agent_permission' => 'public',
    ];
    $selected = [];
    foreach ($allowed as $key => $options) {
        $value = (string)($values[$key] ?? '');
        $selected[$key] = in_array($value, $options, true) ? $value : $defaults[$key];
    }

    $contactId = max(0, (int)($values['crm_contact_id'] ?? 0));
    if ($contactId > 0) {
        $check = db()->prepare('SELECT id FROM crm_contacts WHERE id=:id LIMIT 1');
        $check->execute(['id' => $contactId]);
        if (!$check->fetchColumn()) throw new RuntimeException('The selected CRM contact was not found.');
    }

    $existing = db()->prepare(
        'SELECT * FROM pod_relationships
         WHERE local_identity_id=:local_identity_id
           AND remote_identity_id=:remote_identity_id LIMIT 1'
    );
    $existing->execute([
        'local_identity_id' => (int)$local['id'],
        'remote_identity_id' => $remoteId,
    ]);
    $previous = $existing->fetch() ?: null;
    $status = $selected['status'];
    $trust = $selected['trust_status'];

    db()->prepare(
        'INSERT INTO pod_relationships
            (local_identity_id,remote_identity_id,crm_contact_id,
             relationship_type,direction,status,trust_status,
             messaging_permission,calling_permission,agent_permission,notes,
             requested_at,connected_at,blocked_at,disconnected_at,last_verified_at)
         VALUES
            (:local_identity_id,:remote_identity_id,:crm_contact_id,
             :relationship_type,:direction,:status,:trust_status,
             :messaging_permission,:calling_permission,:agent_permission,:notes,
             UTC_TIMESTAMP(),
             CASE WHEN :connected_status="connected" THEN UTC_TIMESTAMP() ELSE NULL END,
             CASE WHEN :blocked_status="blocked" THEN UTC_TIMESTAMP() ELSE NULL END,
             CASE WHEN :disconnected_status="disconnected" THEN UTC_TIMESTAMP() ELSE NULL END,
             CASE WHEN :verified_status="verified" THEN UTC_TIMESTAMP() ELSE NULL END)
         ON DUPLICATE KEY UPDATE
            crm_contact_id=VALUES(crm_contact_id),
            relationship_type=VALUES(relationship_type),direction=VALUES(direction),
            status=VALUES(status),trust_status=VALUES(trust_status),
            messaging_permission=VALUES(messaging_permission),
            calling_permission=VALUES(calling_permission),
            agent_permission=VALUES(agent_permission),notes=VALUES(notes),
            connected_at=CASE WHEN VALUES(status)="connected" THEN COALESCE(connected_at,UTC_TIMESTAMP()) ELSE connected_at END,
            blocked_at=CASE WHEN VALUES(status)="blocked" THEN UTC_TIMESTAMP() ELSE NULL END,
            disconnected_at=CASE WHEN VALUES(status)="disconnected" THEN UTC_TIMESTAMP() ELSE NULL END,
            last_verified_at=CASE WHEN VALUES(trust_status)="verified" THEN UTC_TIMESTAMP() ELSE last_verified_at END'
    )->execute([
        'local_identity_id' => (int)$local['id'],
        'remote_identity_id' => $remoteId,
        'crm_contact_id' => $contactId > 0 ? $contactId : null,
        'relationship_type' => $selected['relationship_type'],
        'direction' => $selected['direction'],
        'status' => $status,
        'trust_status' => $trust,
        'messaging_permission' => $selected['messaging_permission'],
        'calling_permission' => $selected['calling_permission'],
        'agent_permission' => $selected['agent_permission'],
        'notes' => trim((string)($values['notes'] ?? '')) ?: null,
        'connected_status' => $status,
        'blocked_status' => $status,
        'disconnected_status' => $status,
        'verified_status' => $trust,
    ]);

    $select = db()->prepare(
        'SELECT * FROM pod_relationships
         WHERE local_identity_id=:local_identity_id
           AND remote_identity_id=:remote_identity_id LIMIT 1'
    );
    $select->execute([
        'local_identity_id' => (int)$local['id'],
        'remote_identity_id' => $remoteId,
    ]);
    $relationship = $select->fetch();
    if (!$relationship) throw new RuntimeException('The POD relationship could not be saved.');

    $previousStatus = $previous ? (string)$previous['status'] : null;
    $event = !$previous ? 'created'
        : ($status === 'blocked' ? 'blocked'
        : ($status === 'disconnected' ? 'disconnected'
        : ($status === 'connected' && $previousStatus !== 'connected' ? 'connected' : 'permissions_updated')));

    pod_relationship_event(
        (int)$relationship['id'], $actorUserId, $event,
        $previousStatus, $status,
        [
            'remote_pod_id' => $remote['pod_uuid'],
            'crm_contact_id' => $contactId > 0 ? $contactId : null,
            'messaging_permission' => $selected['messaging_permission'],
            'calling_permission' => $selected['calling_permission'],
            'agent_permission' => $selected['agent_permission'],
        ]
    );
    log_activity('pod_relationship_saved', 'pod_relationship', (int)$relationship['id'], [
        'remote_pod_id' => $remote['pod_uuid'],
        'status' => $status,
        'calling_permission' => $selected['calling_permission'],
    ]);

    return $relationship;
}

function pod_relationship_events(int $relationshipId, int $limit = 20): array
{
    if ($relationshipId <= 0) return [];
    $limit = max(1, min(100, $limit));
    $statement = db()->prepare(
        'SELECT event.*,actor.display_name AS actor_name
         FROM pod_relationship_events event
         LEFT JOIN users actor ON actor.id=event.actor_user_id
         WHERE event.relationship_id=:relationship_id
         ORDER BY event.id DESC LIMIT ' . $limit
    );
    $statement->execute(['relationship_id' => $relationshipId]);
    return $statement->fetchAll();
}

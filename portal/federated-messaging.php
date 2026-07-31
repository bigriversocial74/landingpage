<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-federated-messaging-v66I */

require_once __DIR__ . '/homeserver-adapter.php';

function federated_messaging_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "activitypub_message_threads","activitypub_messages",
                    "activitypub_message_user_state","activitypub_message_events",
                    "activitypub_message_assistance"
               )'
        );
        return $available = (int)$statement->fetchColumn() === 5;
    } catch (Throwable) {
        return $available = false;
    }
}

function federated_messaging_require_schema(): void
{
    if (!federated_messaging_schema_available()) {
        throw new RuntimeException('Import database/federated_messaging_v66i.sql before using Federated Messages.');
    }
}

function federated_messaging_settings(): array
{
    $mode = activitypub_setting('activitypub_messages_accept_mode', 'requests');
    if (!in_array($mode, ['requests', 'trusted', 'none'], true)) $mode = 'requests';
    return [
        'enabled' => activitypub_setting('activitypub_messages_enabled', '0') === '1',
        'accept_mode' => $mode,
        'retention_days' => max(7, min(730, (int)activitypub_setting('activitypub_messages_retention_days', '180'))),
        'max_body' => max(500, min(20000, (int)activitypub_setting('activitypub_messages_max_body', '10000'))),
        'actor_hourly_limit' => max(3, min(120, (int)activitypub_setting('activitypub_messages_actor_hourly_limit', '30'))),
        'remote_media_mode' => 'link_only',
        'homeserver_assistance' => activitypub_setting('activitypub_messages_homeserver_assistance', '1') !== '0',
    ];
}

function federated_messaging_normalize_values(mixed $raw): array
{
    if (is_string($raw)) $raw = [$raw];
    if (!is_array($raw)) return [];
    if (!array_is_list($raw) && isset($raw['id'])) $raw = [$raw];
    $values = [];
    foreach ($raw as $value) {
        $candidate = is_array($value) ? trim((string)($value['id'] ?? $value['href'] ?? '')) : trim((string)$value);
        if ($candidate !== '') $values[] = $candidate;
    }
    return array_values(array_unique($values));
}

function federated_messaging_recipients(array $payload, array $object = []): array
{
    $values = [];
    foreach (['to', 'cc', 'bto', 'bcc', 'audience'] as $key) {
        $values = array_merge(
            $values,
            federated_messaging_normalize_values($payload[$key] ?? null),
            federated_messaging_normalize_values($object[$key] ?? null)
        );
    }
    return array_values(array_unique($values));
}

function federated_messaging_is_direct(array $payload, array $object = []): bool
{
    $recipients = array_map('activitypub_normalize_url', federated_messaging_recipients($payload, $object));
    $localActor = activitypub_normalize_url(activitypub_actor_url());
    $public = activitypub_normalize_url('https://www.w3.org/ns/activitystreams#Public');
    return in_array($localActor, $recipients, true) && !in_array($public, $recipients, true);
}

function federated_messaging_clean_text(string $html, ?int $limit = null): string
{
    $limit ??= federated_messaging_settings()['max_body'];
    $html = preg_replace('#<(script|style|iframe|object|embed|form|svg)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? '');
    return mb_substr($text, 0, max(1, $limit));
}

function federated_messaging_attachments(array $object): array
{
    $raw = $object['attachment'] ?? [];
    if (!is_array($raw)) return [];
    if (!array_is_list($raw) && isset($raw['type'])) $raw = [$raw];
    $attachments = [];
    foreach (array_slice($raw, 0, 8) as $item) {
        if (!is_array($item)) continue;
        $url = $item['url'] ?? '';
        if (is_array($url)) $url = (string)($url['href'] ?? $url['url'] ?? '');
        $url = trim((string)$url);
        if (!activitypub_https_url($url)) continue;
        $type = trim((string)($item['type'] ?? 'Document'));
        if (!in_array($type, ['Document', 'Image', 'Audio', 'Video'], true)) $type = 'Document';
        $attachments[] = [
            'type' => $type,
            'url' => $url,
            'media_type' => mb_substr(trim((string)($item['mediaType'] ?? '')), 0, 190),
            'name' => mb_substr(federated_messaging_clean_text((string)($item['name'] ?? ''), 500), 0, 500),
        ];
    }
    return $attachments;
}

function federated_messaging_iso_to_sql(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function federated_messaging_actor_trust(int $remoteActorId): string
{
    if ($remoteActorId <= 0 || !federated_interactions_schema_available()) return 'unknown';
    $follower = db()->prepare(
        'SELECT COUNT(*) FROM activitypub_followers
         WHERE remote_actor_id=:actor_id AND status="approved"'
    );
    $follower->execute(['actor_id' => $remoteActorId]);
    $following = db()->prepare(
        'SELECT COUNT(*) FROM activitypub_following
         WHERE remote_actor_id=:actor_id AND status="accepted"'
    );
    $following->execute(['actor_id' => $remoteActorId]);
    $isFollower = (int)$follower->fetchColumn() > 0;
    $isFollowing = (int)$following->fetchColumn() > 0;
    if ($isFollower && $isFollowing) return 'mutual';
    if ($isFollower) return 'follower';
    if ($isFollowing) return 'following';
    return 'unknown';
}

function federated_messaging_thread(int $threadId): ?array
{
    if (!federated_messaging_schema_available() || $threadId <= 0) return null;
    $statement = db()->prepare(
        'SELECT thread.*,actor.actor_uri,actor.preferred_username,actor.display_name,
                actor.profile_url,actor.status AS actor_status
         FROM activitypub_message_threads thread
         JOIN activitypub_remote_actors actor ON actor.id=thread.remote_actor_id
         WHERE thread.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $threadId]);
    return $statement->fetch() ?: null;
}

function federated_messaging_message(int $messageId): ?array
{
    if (!federated_messaging_schema_available() || $messageId <= 0) return null;
    $statement = db()->prepare(
        'SELECT message.*,thread.thread_uuid,thread.conversation_uri,
                actor.actor_uri,actor.preferred_username,actor.display_name
         FROM activitypub_messages message
         JOIN activitypub_message_threads thread ON thread.id=message.thread_id
         JOIN activitypub_remote_actors actor ON actor.id=message.remote_actor_id
         WHERE message.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $messageId]);
    return $statement->fetch() ?: null;
}

function federated_messaging_message_by_object(string $objectUri): ?array
{
    if (!federated_messaging_schema_available() || $objectUri === '') return null;
    $statement = db()->prepare('SELECT * FROM activitypub_messages WHERE object_uri=:uri LIMIT 1');
    $statement->execute(['uri' => $objectUri]);
    return $statement->fetch() ?: null;
}

function federated_messaging_thread_key(array $remoteActor, array $payload, array $object): array
{
    $context = $object['context'] ?? $object['conversation'] ?? $payload['context'] ?? '';
    if (is_array($context)) $context = (string)($context['id'] ?? '');
    $context = trim((string)$context);
    $reply = $object['inReplyTo'] ?? '';
    if (is_array($reply)) $reply = (string)($reply['id'] ?? '');
    $reply = trim((string)$reply);
    if ($reply !== '') {
        $existing = federated_messaging_message_by_object($reply);
        if ($existing && (int)$existing['remote_actor_id'] === (int)$remoteActor['id']) {
            $thread = federated_messaging_thread((int)$existing['thread_id']);
            if ($thread) return [(string)$thread['thread_key'], (string)($thread['conversation_uri'] ?? '')];
        }
    }
    if ($context !== '' && !activitypub_https_url($context)) $context = '';
    $basis = $context !== '' ? activitypub_normalize_url($context) : activitypub_normalize_url((string)$remoteActor['actor_uri']);
    return [hash('sha256', (string)$remoteActor['id'] . '|' . $basis), $context];
}

function federated_messaging_thread_for_actor(array $remoteActor, array $payload, array $object, int $riskScore): array
{
    [$threadKey, $conversationUri] = federated_messaging_thread_key($remoteActor, $payload, $object);
    $trust = federated_messaging_actor_trust((int)$remoteActor['id']);
    $status = $trust === 'unknown' ? 'request' : 'open';
    if (federated_interactions_actor_muted($remoteActor)) $status = 'muted';
    $uuid = pod_uuid_v4();
    db()->prepare(
        'INSERT INTO activitypub_message_threads
            (thread_uuid,remote_actor_id,thread_key,conversation_uri,status,trust_level,
             needs_response,risk_score,last_message_at)
         VALUES
            (:uuid,:actor_id,:thread_key,:conversation_uri,:status,:trust_level,1,:risk_score,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            id=LAST_INSERT_ID(id),
            trust_level=CASE WHEN trust_level="approved" THEN trust_level ELSE VALUES(trust_level) END,
            risk_score=GREATEST(risk_score,VALUES(risk_score)),last_message_at=UTC_TIMESTAMP(),
            needs_response=1,
            status=CASE WHEN status IN ("archived","closed") THEN status ELSE status END'
    )->execute([
        'uuid' => $uuid,
        'actor_id' => (int)$remoteActor['id'],
        'thread_key' => $threadKey,
        'conversation_uri' => $conversationUri !== '' ? $conversationUri : null,
        'status' => $status,
        'trust_level' => $trust,
        'risk_score' => $riskScore,
    ]);
    $id = (int)db()->lastInsertId();
    if ($id <= 0) {
        $find = db()->prepare('SELECT id FROM activitypub_message_threads WHERE remote_actor_id=:actor_id AND thread_key=:thread_key LIMIT 1');
        $find->execute(['actor_id' => (int)$remoteActor['id'], 'thread_key' => $threadKey]);
        $id = (int)($find->fetchColumn() ?: 0);
    }
    $thread = federated_messaging_thread($id);
    if (!$thread) throw new RuntimeException('The federated message thread could not be created.');
    return $thread;
}

function federated_messaging_event(int $threadId, ?int $messageId, string $type, ?string $note = null, ?array $evidence = null, ?int $userId = null): void
{
    if (!federated_messaging_schema_available() || $threadId <= 0 || $type === '') return;
    db()->prepare(
        'INSERT INTO activitypub_message_events
            (thread_id,message_id,event_type,event_note,evidence_json,actor_user_id)
         VALUES (:thread_id,:message_id,:event_type,:event_note,:evidence_json,:actor_user_id)'
    )->execute([
        'thread_id' => $threadId,
        'message_id' => ($messageId ?? 0) > 0 ? $messageId : null,
        'event_type' => mb_substr($type, 0, 80),
        'event_note' => $note !== null ? mb_substr($note, 0, 1000) : null,
        'evidence_json' => $evidence ? json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        'actor_user_id' => ($userId ?? 0) > 0 ? $userId : null,
    ]);
}

function federated_messaging_actor_hour_count(int $remoteActorId): int
{
    if (!federated_messaging_schema_available() || $remoteActorId <= 0) return 0;
    $statement = db()->prepare(
        'SELECT COUNT(*) FROM activitypub_messages
         WHERE remote_actor_id=:actor_id AND direction="inbound"
           AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)'
    );
    $statement->execute(['actor_id' => $remoteActorId]);
    return (int)$statement->fetchColumn();
}

function federated_messaging_risk_score(array $remoteActor, string $body, array $attachments, bool $trusted): int
{
    $score = $trusted ? 0 : 35;
    $urlCount = preg_match_all('#https?://#i', $body, $matches);
    if ($urlCount > 2) $score += min(25, ($urlCount - 2) * 5);
    if (mb_strlen($body) > 5000) $score += 10;
    if (count($attachments) > 3) $score += 15;
    if (preg_match('/(.)\1{12,}/u', $body)) $score += 10;
    if (preg_match('/\b(seed phrase|wallet recovery|urgent payment|wire transfer|gift card code)\b/i', $body)) $score += 25;
    if (federated_interactions_actor_muted($remoteActor)) $score += 20;
    return max(0, min(100, $score));
}

function federated_messaging_notify(string $title, string $body, string $entityType, int $entityId, string $priority = 'normal'): void
{
    notification_create_for_role(
        'admin',
        'message',
        $title,
        mb_substr($body, 0, 240),
        'portal/federated-messages.php?thread=' . $entityId,
        $entityType,
        $entityId,
        $priority
    );
}

function federated_messaging_ingest_create(int $inboxId, array $payload, array $remoteActor): bool
{
    $object = $payload['object'] ?? null;
    if (!is_array($object) || (string)($object['type'] ?? '') !== 'Note') return false;
    if (!federated_messaging_is_direct($payload, $object)) return false;
    if (!federated_interactions_payload_actor_matches($object, (string)$remoteActor['actor_uri'])) {
        throw new RuntimeException('The federated message attribution does not match the verified actor.');
    }
    $objectUri = trim((string)($object['id'] ?? ''));
    $activityUri = trim((string)($payload['id'] ?? ''));
    if (!activitypub_https_url($objectUri) || !activitypub_https_url($activityUri)) {
        throw new RuntimeException('The federated message activity or object ID is invalid.');
    }
    $existing = federated_messaging_message_by_object($objectUri);
    if ($existing) {
        if ((int)$existing['remote_actor_id'] !== (int)$remoteActor['id']) {
            throw new RuntimeException('A federated message object cannot change actor ownership.');
        }
        return true;
    }
    $settings = federated_messaging_settings();
    if (federated_messaging_actor_hour_count((int)$remoteActor['id']) >= $settings['actor_hourly_limit']) {
        throw new RuntimeException('The remote actor exceeded the federated message rate limit.');
    }
    $body = federated_messaging_clean_text((string)($object['content'] ?? $object['summary'] ?? $object['name'] ?? ''));
    if ($body === '') throw new RuntimeException('The federated message does not contain readable text.');
    $attachments = federated_messaging_attachments($object);
    $trust = federated_messaging_actor_trust((int)$remoteActor['id']);
    $trusted = $trust !== 'unknown';
    if ($settings['accept_mode'] === 'trusted' && !$trusted) return true;
    $risk = federated_messaging_risk_score($remoteActor, $body, $attachments, $trusted);
    $thread = federated_messaging_thread_for_actor($remoteActor, $payload, $object, $risk);
    $status = $risk >= 80 ? 'spam' : ((string)$thread['status'] === 'request' ? 'request' : 'visible');
    $reply = $object['inReplyTo'] ?? '';
    if (is_array($reply)) $reply = (string)($reply['id'] ?? '');
    $reply = trim((string)$reply);
    if ($reply !== '' && !activitypub_https_url($reply)) $reply = '';
    $uuid = activitypub_uuid_from_seed('federated-message-inbound|' . $objectUri);
    db()->prepare(
        'INSERT INTO activitypub_messages
            (message_uuid,thread_id,remote_actor_id,direction,activity_uri,object_uri,
             source_activity_uri,in_reply_to_uri,inbox_activity_id,body_text,body_hash,
             attachments_json,is_sensitive,risk_score,status,source_published_at,source_updated_at)
         VALUES
            (:uuid,:thread_id,:actor_id,"inbound",:activity_uri,:object_uri,
             :source_activity_uri,:in_reply_to_uri,:inbox_id,:body_text,:body_hash,
             :attachments_json,:is_sensitive,:risk_score,:status,:published_at,:updated_at)'
    )->execute([
        'uuid' => $uuid,
        'thread_id' => (int)$thread['id'],
        'actor_id' => (int)$remoteActor['id'],
        'activity_uri' => $activityUri,
        'object_uri' => $objectUri,
        'source_activity_uri' => $activityUri,
        'in_reply_to_uri' => $reply !== '' ? $reply : null,
        'inbox_id' => $inboxId,
        'body_text' => $body,
        'body_hash' => hash('sha256', $body),
        'attachments_json' => $attachments ? json_encode($attachments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        'is_sensitive' => !empty($object['sensitive']) ? 1 : 0,
        'risk_score' => $risk,
        'status' => $status,
        'published_at' => federated_messaging_iso_to_sql($object['published'] ?? $payload['published'] ?? null),
        'updated_at' => federated_messaging_iso_to_sql($object['updated'] ?? null),
    ]);
    $messageId = (int)db()->lastInsertId();
    db()->prepare(
        'UPDATE activitypub_message_threads
         SET last_message_at=UTC_TIMESTAMP(),needs_response=1,risk_score=GREATEST(risk_score,:risk_score),
             status=CASE WHEN :message_status="spam" THEN status ELSE status END
         WHERE id=:id'
    )->execute(['risk_score' => $risk, 'message_status' => $status, 'id' => (int)$thread['id']]);
    federated_messaging_event((int)$thread['id'], $messageId, 'message_received', null, [
        'risk_score' => $risk,
        'request' => $status === 'request',
        'link_only_attachments' => count($attachments),
    ]);
    if ($status === 'spam') {
        federated_messaging_notify('Federated message held as spam', $body, 'activitypub_message_thread', (int)$thread['id'], 'high');
    } elseif ($status === 'request') {
        federated_messaging_notify('New federated message request', $body, 'activitypub_message_thread', (int)$thread['id'], 'normal');
    } elseif (!federated_interactions_actor_muted($remoteActor)) {
        federated_messaging_notify('New federated message', $body, 'activitypub_message_thread', (int)$thread['id'], 'normal');
    }
    return true;
}

function federated_messaging_ingest_update(int $inboxId, array $payload, array $remoteActor): bool
{
    $object = $payload['object'] ?? null;
    if (!is_array($object) || (string)($object['type'] ?? '') !== 'Note') return false;
    if (!federated_messaging_is_direct($payload, $object)) return false;
    $objectUri = trim((string)($object['id'] ?? ''));
    $message = federated_messaging_message_by_object($objectUri);
    if (!$message) return false;
    if ((int)$message['remote_actor_id'] !== (int)$remoteActor['id'] || (string)$message['direction'] !== 'inbound') {
        throw new RuntimeException('The federated message update does not own the stored object.');
    }
    if (!federated_interactions_payload_actor_matches($object, (string)$remoteActor['actor_uri'])) {
        throw new RuntimeException('The federated message update attribution does not match the verified actor.');
    }
    $body = federated_messaging_clean_text((string)($object['content'] ?? $object['summary'] ?? ''));
    if ($body === '') throw new RuntimeException('The federated message update does not contain readable text.');
    $attachments = federated_messaging_attachments($object);
    $trust = federated_messaging_actor_trust((int)$remoteActor['id']);
    $risk = federated_messaging_risk_score($remoteActor, $body, $attachments, $trust !== 'unknown');
    $status = $risk >= 80 ? 'spam' : ((string)$message['status'] === 'request' ? 'request' : 'edited');
    db()->prepare(
        'UPDATE activitypub_messages
         SET source_activity_uri=:source_activity_uri,inbox_activity_id=:inbox_id,
             body_text=:body_text,body_hash=:body_hash,attachments_json=:attachments_json,
             is_sensitive=:is_sensitive,risk_score=:risk_score,status=:status,
             source_updated_at=:source_updated_at,edited_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute([
        'source_activity_uri' => trim((string)($payload['id'] ?? '')),
        'inbox_id' => $inboxId,
        'body_text' => $body,
        'body_hash' => hash('sha256', $body),
        'attachments_json' => $attachments ? json_encode($attachments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        'is_sensitive' => !empty($object['sensitive']) ? 1 : 0,
        'risk_score' => $risk,
        'status' => $status,
        'source_updated_at' => federated_messaging_iso_to_sql($object['updated'] ?? null),
        'id' => (int)$message['id'],
    ]);
    db()->prepare('UPDATE activitypub_message_threads SET needs_response=1,risk_score=GREATEST(risk_score,:risk) WHERE id=:id')
        ->execute(['risk' => $risk, 'id' => (int)$message['thread_id']]);
    federated_messaging_event((int)$message['thread_id'], (int)$message['id'], 'message_edited_remote', null, ['risk_score' => $risk]);
    return true;
}

function federated_messaging_ingest_delete(int $inboxId, array $payload, array $remoteActor): bool
{
    $object = $payload['object'] ?? null;
    $objectUri = is_string($object) ? trim($object) : (is_array($object) ? trim((string)($object['id'] ?? '')) : '');
    if ($objectUri === '') return false;
    $message = federated_messaging_message_by_object($objectUri);
    if (!$message) return false;
    if ((int)$message['remote_actor_id'] !== (int)$remoteActor['id'] || (string)$message['direction'] !== 'inbound') {
        throw new RuntimeException('The federated message deletion does not own the stored object.');
    }
    db()->prepare(
        'UPDATE activitypub_messages
         SET status="deleted",body_text=NULL,attachments_json=NULL,inbox_activity_id=:inbox_id,
             deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute(['inbox_id' => $inboxId, 'id' => (int)$message['id']]);
    federated_messaging_event((int)$message['thread_id'], (int)$message['id'], 'message_deleted_remote');
    return true;
}

function federated_messaging_ingest_undo(int $inboxId, array $payload, array $remoteActor): bool
{
    $object = $payload['object'] ?? null;
    $activityUri = is_string($object) ? trim($object) : (is_array($object) ? trim((string)($object['id'] ?? '')) : '');
    if ($activityUri === '') return false;
    $statement = db()->prepare(
        'SELECT * FROM activitypub_messages
         WHERE source_activity_uri=:activity_uri AND direction="inbound" LIMIT 1'
    );
    $statement->execute(['activity_uri' => $activityUri]);
    $message = $statement->fetch();
    if (!$message) return false;
    if ((int)$message['remote_actor_id'] !== (int)$remoteActor['id']) {
        throw new RuntimeException('The federated message Undo does not own the stored activity.');
    }
    db()->prepare(
        'UPDATE activitypub_messages
         SET status="deleted",body_text=NULL,attachments_json=NULL,inbox_activity_id=:inbox_id,
             deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute(['inbox_id' => $inboxId, 'id' => (int)$message['id']]);
    federated_messaging_event((int)$message['thread_id'], (int)$message['id'], 'message_undone_remote');
    return true;
}

function federated_messaging_process_inbound(int $inboxId, array $payload, array $remoteActor): bool
{
    $settings = federated_messaging_settings();
    if (!$settings['enabled'] || !federated_messaging_schema_available()) return false;
    $object = $payload['object'] ?? null;
    $objectArray = is_array($object) ? $object : [];
    $type = trim((string)($payload['type'] ?? ''));
    $looksDirect = $type === 'Create' || $type === 'Update'
        ? federated_messaging_is_direct($payload, $objectArray)
        : false;
    if (!federated_interactions_actor_allowed($remoteActor)) {
        return $looksDirect;
    }
    if ($settings['accept_mode'] === 'none' && $looksDirect) return true;
    if ($type === 'Create') return federated_messaging_ingest_create($inboxId, $payload, $remoteActor);
    if ($type === 'Update') return federated_messaging_ingest_update($inboxId, $payload, $remoteActor);
    if ($type === 'Delete') return federated_messaging_ingest_delete($inboxId, $payload, $remoteActor);
    if ($type === 'Undo') return federated_messaging_ingest_undo($inboxId, $payload, $remoteActor);
    return false;
}

function federated_messaging_threads(int $userId, string $filter = 'inbox', string $search = '', int $limit = 150): array
{
    if (!federated_messaging_schema_available() || $userId <= 0) return [];
    $limit = max(1, min(300, $limit));
    $where = ['thread.status<>"blocked"'];
    $params = ['user_id' => $userId];
    if ($filter === 'requests') $where[] = 'thread.status="request"';
    elseif ($filter === 'archived') $where[] = '(thread.status="archived" OR state.archived_at IS NOT NULL)';
    elseif ($filter === 'muted') $where[] = '(thread.status="muted" OR state.muted_at IS NOT NULL)';
    elseif ($filter === 'pinned') $where[] = 'state.pinned_at IS NOT NULL';
    elseif ($filter === 'unread') $where[] = '(state.last_read_message_id IS NULL OR state.last_read_message_id<latest.last_message_id)';
    else $where[] = 'thread.status IN ("open","request","muted") AND state.archived_at IS NULL';
    $search = trim($search);
    if ($search !== '') {
        $where[] = '(actor.display_name LIKE :search OR actor.preferred_username LIKE :search OR actor.actor_uri LIKE :search OR latest.body_text LIKE :search)';
        $params['search'] = '%' . mb_substr($search, 0, 190) . '%';
    }
    $sql =
        'SELECT thread.*,actor.actor_uri,actor.preferred_username,actor.display_name,actor.profile_url,
                state.last_read_message_id,state.read_at,state.archived_at,state.muted_at,state.pinned_at,
                latest.last_message_id,latest.body_text AS last_message_body,
                latest.direction AS last_message_direction,latest.status AS last_message_status,
                latest.created_at AS last_message_created_at
         FROM activitypub_message_threads thread
         JOIN activitypub_remote_actors actor ON actor.id=thread.remote_actor_id
         LEFT JOIN activitypub_message_user_state state
           ON state.thread_id=thread.id AND state.user_id=:user_id
         LEFT JOIN (
            SELECT message.thread_id,MAX(message.id) AS last_message_id
            FROM activitypub_messages message GROUP BY message.thread_id
         ) marker ON marker.thread_id=thread.id
         LEFT JOIN activitypub_messages latest ON latest.id=marker.last_message_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY (state.pinned_at IS NOT NULL) DESC,
                  FIELD(thread.status,"request","open","muted","archived","closed"),
                  COALESCE(thread.last_message_at,thread.created_at) DESC,thread.id DESC
         LIMIT ' . $limit;
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function federated_messaging_thread_messages(int $threadId, int $limit = 250): array
{
    if (!federated_messaging_schema_available() || $threadId <= 0) return [];
    $limit = max(1, min(500, $limit));
    $statement = db()->prepare(
        'SELECT message.*,creator.display_name AS creator_name
         FROM activitypub_messages message
         LEFT JOIN users creator ON creator.id=message.created_by_user_id
         WHERE message.thread_id=:thread_id
         ORDER BY message.created_at,message.id LIMIT ' . $limit
    );
    $statement->execute(['thread_id' => $threadId]);
    return $statement->fetchAll();
}

function federated_messaging_mark_read(int $threadId, int $userId): void
{
    if ($threadId <= 0 || $userId <= 0 || !federated_messaging_schema_available()) return;
    $statement = db()->prepare('SELECT MAX(id) FROM activitypub_messages WHERE thread_id=:thread_id');
    $statement->execute(['thread_id' => $threadId]);
    $messageId = (int)($statement->fetchColumn() ?: 0);
    db()->prepare(
        'INSERT INTO activitypub_message_user_state
            (thread_id,user_id,last_read_message_id,read_at)
         VALUES (:thread_id,:user_id,:message_id,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE last_read_message_id=VALUES(last_read_message_id),read_at=UTC_TIMESTAMP()'
    )->execute(['thread_id' => $threadId, 'user_id' => $userId, 'message_id' => $messageId > 0 ? $messageId : null]);
}

function federated_messaging_set_user_state(int $threadId, int $userId, string $action): void
{
    if (!in_array($action, ['archive','unarchive','mute','unmute','pin','unpin','hide','unhide'], true)) {
        throw new RuntimeException('Unsupported federated message state action.');
    }
    federated_messaging_require_schema();
    $column = match ($action) {
        'archive','unarchive' => 'archived_at',
        'mute','unmute' => 'muted_at',
        'pin','unpin' => 'pinned_at',
        default => 'hidden_at',
    };
    $value = str_starts_with($action, 'un') ? null : gmdate('Y-m-d H:i:s');
    db()->prepare(
        'INSERT INTO activitypub_message_user_state (thread_id,user_id,' . $column . ')
         VALUES (:thread_id,:user_id,:value)
         ON DUPLICATE KEY UPDATE ' . $column . '=VALUES(' . $column . ')'
    )->execute(['thread_id' => $threadId, 'user_id' => $userId, 'value' => $value]);
}

function federated_messaging_moderate_thread(int $threadId, string $decision, int $userId, string $note = ''): void
{
    federated_messaging_require_schema();
    $thread = federated_messaging_thread($threadId);
    if (!$thread) throw new RuntimeException('The federated message thread was not found.');
    if (!in_array($decision, ['accept','reject','reopen','close','block'], true)) {
        throw new RuntimeException('Unsupported federated message moderation decision.');
    }
    if ($decision === 'accept') {
        db()->prepare(
            'UPDATE activitypub_message_threads
             SET status="open",trust_level="approved",accepted_by_user_id=:user_id,
                 accepted_at=UTC_TIMESTAMP(),rejected_at=NULL WHERE id=:id'
        )->execute(['user_id' => $userId, 'id' => $threadId]);
        db()->prepare('UPDATE activitypub_messages SET status="visible" WHERE thread_id=:id AND status="request"')
            ->execute(['id' => $threadId]);
    } elseif ($decision === 'reject') {
        db()->prepare(
            'UPDATE activitypub_message_threads
             SET status="closed",needs_response=0,rejected_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute(['id' => $threadId]);
    } elseif ($decision === 'reopen') {
        db()->prepare('UPDATE activitypub_message_threads SET status="open",rejected_at=NULL WHERE id=:id')
            ->execute(['id' => $threadId]);
    } elseif ($decision === 'close') {
        db()->prepare('UPDATE activitypub_message_threads SET status="closed",needs_response=0 WHERE id=:id')
            ->execute(['id' => $threadId]);
    } else {
        db()->prepare(
            'INSERT INTO activitypub_actor_controls
                (remote_actor_id,moderation_status,moderation_note,updated_by_user_id)
             VALUES (:actor_id,"blocked",:note,:user_id)
             ON DUPLICATE KEY UPDATE moderation_status="blocked",moderation_note=VALUES(moderation_note),
                updated_by_user_id=VALUES(updated_by_user_id),updated_at=UTC_TIMESTAMP()'
        )->execute([
            'actor_id' => (int)$thread['remote_actor_id'],
            'note' => mb_substr(trim($note), 0, 1000) ?: 'Blocked from Federated Messages',
            'user_id' => $userId,
        ]);
        db()->prepare('UPDATE activitypub_message_threads SET status="blocked",needs_response=0 WHERE remote_actor_id=:actor_id')
            ->execute(['actor_id' => (int)$thread['remote_actor_id']]);
    }
    federated_messaging_event($threadId, null, 'thread_' . $decision, $note !== '' ? $note : null, null, $userId);
}

function federated_messaging_object_url(string $uuid): string
{
    return app_url('activitypub-message.php?id=' . rawurlencode($uuid));
}

function federated_messaging_context_uri(array $thread): string
{
    $context = trim((string)($thread['conversation_uri'] ?? ''));
    return $context !== '' ? $context : activitypub_actor_url() . '#federated-message-' . (string)$thread['thread_uuid'];
}

function federated_messaging_latest_reply_target(int $threadId): ?string
{
    $statement = db()->prepare(
        'SELECT object_uri FROM activitypub_messages
         WHERE thread_id=:thread_id AND direction="inbound" AND status NOT IN ("deleted","spam")
         ORDER BY id DESC LIMIT 1'
    );
    $statement->execute(['thread_id' => $threadId]);
    $value = trim((string)($statement->fetchColumn() ?: ''));
    return $value !== '' ? $value : null;
}

function federated_messaging_send(int $threadId, string $body, int $userId, ?string $inReplyTo = null): array
{
    federated_messaging_require_schema();
    $thread = federated_messaging_thread($threadId);
    if (!$thread || !in_array((string)$thread['status'], ['open','muted','archived'], true)) {
        throw new RuntimeException('Accept or reopen the federated conversation before replying.');
    }
    if (!federated_interactions_actor_allowed([
        'id' => (int)$thread['remote_actor_id'],
        'actor_uri' => (string)$thread['actor_uri'],
        'status' => (string)($thread['actor_status'] ?? 'active'),
    ])) {
        throw new RuntimeException('The remote actor or domain is blocked.');
    }
    $body = federated_messaging_clean_text($body);
    if ($body === '') throw new RuntimeException('Enter a message before sending.');
    $uuid = pod_uuid_v4();
    $objectUri = federated_messaging_object_url($uuid);
    $activityUuid = pod_uuid_v4();
    $replyTarget = trim((string)($inReplyTo ?? ''));
    if ($replyTarget === '') $replyTarget = (string)(federated_messaging_latest_reply_target($threadId) ?? '');
    if ($replyTarget !== '' && !activitypub_https_url($replyTarget)) $replyTarget = '';
    $object = [
        'id' => $objectUri,
        'type' => 'Note',
        'attributedTo' => activitypub_actor_url(),
        'to' => [(string)$thread['actor_uri']],
        'context' => federated_messaging_context_uri($thread),
        'content' => nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        'mediaType' => 'text/html',
        'published' => gmdate(DATE_ATOM),
    ];
    if ($replyTarget !== '') $object['inReplyTo'] = $replyTarget;
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url($activityUuid),
        'type' => 'Create',
        'actor' => activitypub_actor_url(),
        'to' => [(string)$thread['actor_uri']],
        'object' => $object,
    ];
    $outboxId = activitypub_store_outbox_activity($activity, 'Create', 'Note', $objectUri, null, $userId);
    activitypub_queue_delivery($outboxId, (int)$thread['remote_actor_id']);
    db()->prepare(
        'INSERT INTO activitypub_messages
            (message_uuid,thread_id,remote_actor_id,direction,activity_uri,object_uri,
             in_reply_to_uri,outbox_activity_id,body_text,body_hash,status,created_by_user_id,source_published_at)
         VALUES
            (:uuid,:thread_id,:actor_id,"outbound",:activity_uri,:object_uri,
             :in_reply_to_uri,:outbox_id,:body_text,:body_hash,"visible",:user_id,UTC_TIMESTAMP())'
    )->execute([
        'uuid' => $uuid,
        'thread_id' => $threadId,
        'actor_id' => (int)$thread['remote_actor_id'],
        'activity_uri' => (string)$activity['id'],
        'object_uri' => $objectUri,
        'in_reply_to_uri' => $replyTarget !== '' ? $replyTarget : null,
        'outbox_id' => $outboxId,
        'body_text' => $body,
        'body_hash' => hash('sha256', $body),
        'user_id' => $userId,
    ]);
    $messageId = (int)db()->lastInsertId();
    db()->prepare('UPDATE activitypub_message_threads SET status="open",needs_response=0,last_message_at=UTC_TIMESTAMP() WHERE id=:id')
        ->execute(['id' => $threadId]);
    federated_messaging_event($threadId, $messageId, 'message_sent', null, ['outbox_activity_id' => $outboxId], $userId);
    return federated_messaging_message($messageId) ?? [];
}

function federated_messaging_edit_outbound(int $messageId, string $body, int $userId): array
{
    federated_messaging_require_schema();
    $message = federated_messaging_message($messageId);
    if (!$message || (string)$message['direction'] !== 'outbound' || in_array((string)$message['status'], ['deleted','failed'], true)) {
        throw new RuntimeException('The outbound federated message cannot be edited.');
    }
    $body = federated_messaging_clean_text($body);
    if ($body === '') throw new RuntimeException('The edited message cannot be empty.');
    $object = [
        'id' => (string)$message['object_uri'],
        'type' => 'Note',
        'attributedTo' => activitypub_actor_url(),
        'to' => [(string)$message['actor_uri']],
        'context' => trim((string)($message['conversation_uri'] ?? '')) ?: activitypub_actor_url() . '#federated-message-' . (string)$message['thread_uuid'],
        'content' => nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        'mediaType' => 'text/html',
        'updated' => gmdate(DATE_ATOM),
    ];
    if (!empty($message['in_reply_to_uri'])) $object['inReplyTo'] = (string)$message['in_reply_to_uri'];
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url(pod_uuid_v4()),
        'type' => 'Update',
        'actor' => activitypub_actor_url(),
        'to' => [(string)$message['actor_uri']],
        'object' => $object,
    ];
    $outboxId = activitypub_store_outbox_activity($activity, 'Update', 'Note', (string)$message['object_uri'], null, $userId);
    activitypub_queue_delivery($outboxId, (int)$message['remote_actor_id']);
    db()->prepare(
        'UPDATE activitypub_messages
         SET activity_uri=:activity_uri,outbox_activity_id=:outbox_id,body_text=:body_text,
             body_hash=:body_hash,status="edited",edited_at=UTC_TIMESTAMP(),last_error=NULL,
             updated_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute([
        'activity_uri' => (string)$activity['id'],
        'outbox_id' => $outboxId,
        'body_text' => $body,
        'body_hash' => hash('sha256', $body),
        'id' => $messageId,
    ]);
    federated_messaging_event((int)$message['thread_id'], $messageId, 'message_edited_local', null, ['outbox_activity_id' => $outboxId], $userId);
    return federated_messaging_message($messageId) ?? [];
}

function federated_messaging_delete_outbound(int $messageId, int $userId): void
{
    federated_messaging_require_schema();
    $message = federated_messaging_message($messageId);
    if (!$message || (string)$message['direction'] !== 'outbound' || (string)$message['status'] === 'deleted') {
        throw new RuntimeException('The outbound federated message cannot be deleted.');
    }
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url(pod_uuid_v4()),
        'type' => 'Delete',
        'actor' => activitypub_actor_url(),
        'to' => [(string)$message['actor_uri']],
        'object' => [
            'id' => (string)$message['object_uri'],
            'type' => 'Tombstone',
            'formerType' => 'Note',
            'deleted' => gmdate(DATE_ATOM),
        ],
    ];
    $outboxId = activitypub_store_outbox_activity($activity, 'Delete', 'Tombstone', (string)$message['object_uri'], null, $userId);
    activitypub_queue_delivery($outboxId, (int)$message['remote_actor_id']);
    db()->prepare(
        'UPDATE activitypub_messages
         SET activity_uri=:activity_uri,outbox_activity_id=:outbox_id,status="deleted",
             body_text=NULL,attachments_json=NULL,deleted_at=UTC_TIMESTAMP(),last_error=NULL,
             updated_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute(['activity_uri' => (string)$activity['id'], 'outbox_id' => $outboxId, 'id' => $messageId]);
    federated_messaging_event((int)$message['thread_id'], $messageId, 'message_deleted_local', null, ['outbox_activity_id' => $outboxId], $userId);
}

function federated_messaging_message_object(string $uuid): ?array
{
    if (!federated_messaging_schema_available() || !activitypub_valid_uuid($uuid)) return null;
    $statement = db()->prepare(
        'SELECT message.*,thread.thread_uuid,thread.conversation_uri,actor.actor_uri
         FROM activitypub_messages message
         JOIN activitypub_message_threads thread ON thread.id=message.thread_id
         JOIN activitypub_remote_actors actor ON actor.id=message.remote_actor_id
         WHERE message.message_uuid=:uuid AND message.direction="outbound" LIMIT 1'
    );
    $statement->execute(['uuid' => strtolower($uuid)]);
    $message = $statement->fetch();
    if (!$message) return null;
    if ((string)$message['status'] === 'deleted') {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => (string)$message['object_uri'],
            'type' => 'Tombstone',
            'formerType' => 'Note',
            'deleted' => gmdate(DATE_ATOM, strtotime((string)($message['deleted_at'] ?? 'now')) ?: time()),
        ];
    }
    if (!in_array((string)$message['status'], ['visible','edited'], true)) return null;
    $object = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => (string)$message['object_uri'],
        'type' => 'Note',
        'attributedTo' => activitypub_actor_url(),
        'to' => [(string)$message['actor_uri']],
        'context' => trim((string)($message['conversation_uri'] ?? '')) ?: activitypub_actor_url() . '#federated-message-' . (string)$message['thread_uuid'],
        'content' => nl2br(htmlspecialchars((string)$message['body_text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        'mediaType' => 'text/html',
        'published' => gmdate(DATE_ATOM, strtotime((string)$message['created_at']) ?: time()),
    ];
    if (!empty($message['edited_at'])) $object['updated'] = gmdate(DATE_ATOM, strtotime((string)$message['edited_at']) ?: time());
    if (!empty($message['in_reply_to_uri'])) $object['inReplyTo'] = (string)$message['in_reply_to_uri'];
    return $object;
}

function federated_messaging_sync_delivery(array $delivery, array $result): void
{
    if (!federated_messaging_schema_available()) return;
    $outboxId = (int)($delivery['outbox_activity_id'] ?? 0);
    $remoteActorId = (int)($delivery['remote_actor_id'] ?? 0);
    if ($outboxId <= 0 || $remoteActorId <= 0) return;
    $statement = db()->prepare(
        'SELECT * FROM activitypub_messages
         WHERE outbox_activity_id=:outbox_id AND remote_actor_id=:actor_id
         ORDER BY id DESC LIMIT 1'
    );
    $statement->execute(['outbox_id' => $outboxId, 'actor_id' => $remoteActorId]);
    $message = $statement->fetch();
    if (!$message) return;
    if (!empty($result['ok'])) {
        db()->prepare('UPDATE activitypub_messages SET last_error=NULL,status=CASE WHEN status="failed" THEN "visible" ELSE status END WHERE id=:id')
            ->execute(['id' => (int)$message['id']]);
        federated_messaging_event((int)$message['thread_id'], (int)$message['id'], 'delivery_succeeded', null, ['delivery_id' => (int)($delivery['id'] ?? 0)]);
    } else {
        $error = mb_substr((string)($result['error'] ?? 'Federated message delivery failed.'), 0, 1000);
        db()->prepare('UPDATE activitypub_messages SET status="failed",last_error=:error WHERE id=:id')
            ->execute(['error' => $error, 'id' => (int)$message['id']]);
        db()->prepare('UPDATE activitypub_message_threads SET needs_response=1 WHERE id=:id')
            ->execute(['id' => (int)$message['thread_id']]);
        federated_messaging_event((int)$message['thread_id'], (int)$message['id'], 'delivery_failed', $error, ['delivery_id' => (int)($delivery['id'] ?? 0)]);
    }
}

function federated_messaging_reset_delivery(int $deliveryId): void
{
    if (!federated_messaging_schema_available() || $deliveryId <= 0) return;
    $statement = db()->prepare(
        'SELECT message.id,message.thread_id
         FROM activitypub_deliveries delivery
         JOIN activitypub_messages message ON message.outbox_activity_id=delivery.outbox_activity_id
         WHERE delivery.id=:id AND message.remote_actor_id=delivery.remote_actor_id LIMIT 1'
    );
    $statement->execute(['id' => $deliveryId]);
    $message = $statement->fetch();
    if (!$message) return;
    db()->prepare('UPDATE activitypub_messages SET status=CASE WHEN status="failed" THEN "visible" ELSE status END,last_error=NULL WHERE id=:id')
        ->execute(['id' => (int)$message['id']]);
    federated_messaging_event((int)$message['thread_id'], (int)$message['id'], 'delivery_retry', null, ['delivery_id' => $deliveryId]);
}

function federated_messaging_assist(int $threadId, ?int $sourceMessageId, string $kind, int $userId, string $language = ''): array
{
    federated_messaging_require_schema();
    $settings = federated_messaging_settings();
    if (!$settings['homeserver_assistance']) {
        throw new RuntimeException('HomeServer assistance is disabled for Federated Messages.');
    }
    if (!in_array($kind, ['summary','draft','translate'], true)) {
        throw new RuntimeException('Choose summary, draft, or translate assistance.');
    }
    $thread = federated_messaging_thread($threadId);
    if (!$thread) throw new RuntimeException('The federated message thread was not found.');
    $rows = array_slice(federated_messaging_thread_messages($threadId, 40), -12);
    $conversation = [];
    foreach ($rows as $row) {
        if (in_array((string)$row['status'], ['deleted','spam'], true)) continue;
        $conversation[] = [
            'role' => (string)$row['direction'] === 'outbound' ? 'owner' : 'remote',
            'text' => mb_substr((string)($row['body_text'] ?? ''), 0, 2000),
            'sent_at' => (string)$row['created_at'],
        ];
    }
    $capability = match ($kind) {
        'summary' => 'message_summary',
        'translate' => 'translate_message',
        default => 'suggest_reply',
    };
    $payload = [
        'authority' => [
            'wrapper' => 'rss-pod',
            'resource_type' => 'federated_message_thread',
            'resource_id' => (string)$thread['thread_uuid'],
            'requested_by_user_id' => $userId,
        ],
        'request' => [
            'capability' => $capability,
            'proposal_only' => true,
            'send_allowed' => false,
            'target_language' => mb_substr(trim($language), 0, 80),
        ],
        'remote_actor' => [
            'actor_uri' => (string)$thread['actor_uri'],
            'display_name' => (string)($thread['display_name'] ?: $thread['preferred_username']),
        ],
        'conversation' => $conversation,
    ];
    $inputJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $requestUuid = pod_uuid_v4();
    db()->prepare(
        'INSERT INTO activitypub_message_assistance
            (request_uuid,thread_id,source_message_id,capability,input_sha256,status,requested_by_user_id)
         VALUES (:uuid,:thread_id,:message_id,:capability,:input_sha256,"pending",:user_id)'
    )->execute([
        'uuid' => $requestUuid,
        'thread_id' => $threadId,
        'message_id' => ($sourceMessageId ?? 0) > 0 ? $sourceMessageId : null,
        'capability' => $kind,
        'input_sha256' => hash('sha256', $inputJson),
        'user_id' => $userId,
    ]);
    $requestId = (int)db()->lastInsertId();
    $result = homeserver_request($capability, $payload);
    $ok = !empty($result['ok']);
    $available = !empty($result['available']);
    $resultText = '';
    foreach (['draft','summary','translation','text','result'] as $key) {
        $candidate = $result[$key] ?? null;
        if (is_array($candidate)) {
            foreach (['text','draft','summary','translation'] as $nested) {
                if (isset($candidate[$nested]) && is_scalar($candidate[$nested])) {
                    $candidate = (string)$candidate[$nested];
                    break;
                }
            }
        }
        if (is_scalar($candidate) && trim((string)$candidate) !== '') {
            $resultText = mb_substr(trim((string)$candidate), 0, 10000);
            break;
        }
    }
    $receipt = [];
    foreach (['capability','receipt_id','job_id','completed_at','model','provider'] as $key) {
        if (isset($result[$key]) && is_scalar($result[$key])) $receipt[$key] = (string)$result[$key];
    }
    $status = $ok ? 'completed' : ($available ? 'failed' : 'unavailable');
    $error = $ok ? null : mb_substr((string)($result['message'] ?? 'HomeServer assistance was unavailable.'), 0, 1000);
    db()->prepare(
        'UPDATE activitypub_message_assistance
         SET status=:status,result_text=:result_text,receipt_json=:receipt_json,last_error=:last_error,
             completed_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute([
        'status' => $status,
        'result_text' => $resultText !== '' ? $resultText : null,
        'receipt_json' => $receipt ? json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        'last_error' => $error,
        'id' => $requestId,
    ]);
    federated_messaging_event($threadId, $sourceMessageId, 'assistance_' . $status, $error, [
        'request_uuid' => $requestUuid,
        'capability' => $capability,
        'proposal_only' => true,
        'send_allowed' => false,
    ], $userId);
    return [
        'ok' => $ok,
        'available' => $available,
        'status' => $status,
        'text' => $resultText,
        'message' => $error,
        'request_uuid' => $requestUuid,
    ];
}

function federated_messaging_inbox_items(int $userId): array
{
    if (!federated_messaging_schema_available() || $userId <= 0) return [];
    $statement = db()->prepare(
        'SELECT thread.id,thread.thread_uuid,thread.status,thread.needs_response,thread.risk_score,
                thread.last_message_at,actor.display_name,actor.preferred_username,actor.actor_uri,
                latest.id AS message_id,latest.body_text,latest.status AS message_status,
                latest.direction,latest.last_error,state.last_read_message_id
         FROM activitypub_message_threads thread
         JOIN activitypub_remote_actors actor ON actor.id=thread.remote_actor_id
         LEFT JOIN activitypub_message_user_state state
           ON state.thread_id=thread.id AND state.user_id=:user_id
         LEFT JOIN (
            SELECT thread_id,MAX(id) AS message_id FROM activitypub_messages GROUP BY thread_id
         ) marker ON marker.thread_id=thread.id
         LEFT JOIN activitypub_messages latest ON latest.id=marker.message_id
         WHERE thread.status IN ("request","open","muted")
           AND (thread.status="request" OR thread.needs_response=1 OR latest.status="failed"
                OR state.last_read_message_id IS NULL OR state.last_read_message_id<latest.id)
         ORDER BY FIELD(thread.status,"request","open","muted"),thread.last_message_at DESC,thread.id DESC
         LIMIT 150'
    );
    $statement->execute(['user_id' => $userId]);
    $items = [];
    foreach ($statement->fetchAll() as $row) {
        $name = trim((string)($row['display_name'] ?: $row['preferred_username'] ?: $row['actor_uri']));
        $request = (string)$row['status'] === 'request';
        $failed = (string)($row['message_status'] ?? '') === 'failed';
        $items[] = [
            'source_type' => 'federated_message',
            'source_id' => (int)$row['id'],
            'thread_key' => 'federated-message:' . (string)$row['thread_uuid'],
            'category' => 'messages',
            'title' => $request ? 'Message request from ' . $name : 'Federated message from ' . $name,
            'preview' => mb_substr((string)($row['body_text'] ?? $row['last_error'] ?? ''), 0, 240),
            'actor_name' => $name,
            'contact_id' => null,
            'occurred_at' => (string)($row['last_message_at'] ?? ''),
            'unread' => (int)($row['last_read_message_id'] ?? 0) < (int)($row['message_id'] ?? 0),
            'priority' => $failed || (int)$row['risk_score'] >= 70 ? 'high' : 'normal',
            'deep_link' => 'portal/federated-messages.php?thread=' . (int)$row['id'],
            'media_kind' => null,
            'needs_response' => $request || (int)$row['needs_response'] === 1 || $failed,
        ];
    }
    return $items;
}

function federated_messaging_cleanup(): int
{
    if (!federated_messaging_schema_available()) return 0;
    $days = federated_messaging_settings()['retention_days'];
    $statement = db()->prepare(
        'DELETE message FROM activitypub_messages message
         JOIN activitypub_message_threads thread ON thread.id=message.thread_id
         LEFT JOIN activitypub_message_user_state state ON state.thread_id=thread.id
         WHERE message.created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . (int)$days . ' DAY)
           AND message.status IN ("deleted","spam")
           AND thread.status IN ("closed","blocked")
           AND state.pinned_at IS NULL'
    );
    $statement->execute();
    return $statement->rowCount();
}

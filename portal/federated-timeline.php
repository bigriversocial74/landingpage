<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-federated-timeline-v66H */

function federated_timeline_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "activitypub_remote_posts",
                    "activitypub_timeline_user_state",
                    "activitypub_remote_post_actions"
               )'
        );
        return $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        return $available = false;
    }
}

function federated_timeline_require_schema(): void
{
    if (!federated_timeline_schema_available()) {
        throw new RuntimeException('Import database/federated_timeline_v66h.sql before using the federated timeline.');
    }
}

function federated_timeline_settings(): array
{
    return [
        'enabled' => activitypub_setting('activitypub_timeline_enabled', '0') === '1',
        'store_following' => activitypub_setting('activitypub_timeline_store_following', '1') !== '0',
        'receive_mentions' => activitypub_setting('activitypub_timeline_receive_mentions', '1') !== '0',
        'retention_days' => max(7, min(730, (int)activitypub_setting('activitypub_timeline_retention_days', '90'))),
        'remote_media_mode' => 'link_only',
    ];
}

function federated_timeline_following_accepted(int $remoteActorId): bool
{
    if (!federated_timeline_schema_available() || $remoteActorId <= 0) return false;
    $statement = db()->prepare(
        'SELECT COUNT(*) FROM activitypub_following
         WHERE remote_actor_id=:actor_id AND status="accepted"'
    );
    $statement->execute(['actor_id' => $remoteActorId]);
    return (int)$statement->fetchColumn() > 0;
}

function federated_timeline_recipients(array $payload, array $object = []): array
{
    $values = [];
    foreach (['to', 'cc', 'bto', 'bcc', 'audience'] as $key) {
        foreach ([$payload[$key] ?? null, $object[$key] ?? null] as $raw) {
            if (is_string($raw)) $raw = [$raw];
            if (!is_array($raw)) continue;
            foreach ($raw as $value) {
                $candidate = is_array($value) ? trim((string)($value['id'] ?? '')) : trim((string)$value);
                if ($candidate !== '') $values[] = $candidate;
            }
        }
    }
    return array_values(array_unique($values));
}

function federated_timeline_mentions_local(array $payload, array $object = []): bool
{
    $actor = activitypub_normalize_url(activitypub_actor_url());
    foreach (federated_timeline_recipients($payload, $object) as $recipient) {
        if (activitypub_normalize_url($recipient) === $actor) return true;
    }
    foreach ([$payload['tag'] ?? null, $object['tag'] ?? null] as $tags) {
        if (!is_array($tags)) continue;
        if (array_is_list($tags) === false && isset($tags['type'])) $tags = [$tags];
        foreach ($tags as $tag) {
            if (!is_array($tag) || (string)($tag['type'] ?? '') !== 'Mention') continue;
            if (activitypub_normalize_url((string)($tag['href'] ?? '')) === $actor) return true;
        }
    }
    return false;
}

function federated_timeline_visibility(array $payload, array $object = []): string
{
    $recipients = array_map('activitypub_normalize_url', federated_timeline_recipients($payload, $object));
    $public = activitypub_normalize_url('https://www.w3.org/ns/activitystreams#Public');
    $to = $payload['to'] ?? ($object['to'] ?? []);
    if (is_string($to)) $to = [$to];
    $normalizedTo = is_array($to)
        ? array_map(static fn(mixed $value): string => activitypub_normalize_url(
            is_array($value) ? (string)($value['id'] ?? '') : (string)$value
        ), $to)
        : [];
    if (in_array($public, $normalizedTo, true)) return 'public';
    if (in_array($public, $recipients, true)) return 'unlisted';
    if (in_array(activitypub_normalize_url(activitypub_actor_url()), $recipients, true)) return 'direct';
    return 'followers';
}

function federated_timeline_clean_text(string $html, int $limit = 20000): string
{
    $html = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_substr($text, 0, $limit);
}

function federated_timeline_attachments(array $object): array
{
    $attachments = $object['attachment'] ?? [];
    if (!is_array($attachments)) return [];
    if (!array_is_list($attachments) && isset($attachments['type'])) $attachments = [$attachments];
    $result = [];
    foreach (array_slice($attachments, 0, 12) as $attachment) {
        if (!is_array($attachment)) continue;
        $url = $attachment['url'] ?? '';
        if (is_array($url)) $url = (string)($url['href'] ?? $url['url'] ?? '');
        $url = trim((string)$url);
        if (!activitypub_https_url($url)) continue;
        $type = trim((string)($attachment['type'] ?? 'Document'));
        if (!in_array($type, ['Document', 'Image', 'Audio', 'Video'], true)) $type = 'Document';
        $result[] = [
            'type' => $type,
            'url' => $url,
            'media_type' => mb_substr(trim((string)($attachment['mediaType'] ?? '')), 0, 190),
            'name' => mb_substr(federated_timeline_clean_text((string)($attachment['name'] ?? ''), 500), 0, 500),
        ];
    }
    return $result;
}

function federated_timeline_tags(array $object): array
{
    $tags = $object['tag'] ?? [];
    if (!is_array($tags)) return [];
    if (!array_is_list($tags) && isset($tags['type'])) $tags = [$tags];
    $result = [];
    foreach (array_slice($tags, 0, 40) as $tag) {
        if (!is_array($tag)) continue;
        $type = (string)($tag['type'] ?? '');
        if (!in_array($type, ['Hashtag', 'Mention'], true)) continue;
        $href = trim((string)($tag['href'] ?? ''));
        if ($href !== '' && !activitypub_https_url($href)) $href = '';
        $result[] = [
            'type' => $type,
            'name' => mb_substr(federated_timeline_clean_text((string)($tag['name'] ?? ''), 190), 0, 190),
            'href' => $href,
        ];
    }
    return $result;
}

function federated_timeline_iso_to_sql(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function federated_timeline_object_actor(array $object): string
{
    $actor = $object['attributedTo'] ?? $object['actor'] ?? '';
    if (is_string($actor)) return trim($actor);
    if (is_array($actor)) {
        if (!array_is_list($actor)) return trim((string)($actor['id'] ?? ''));
        foreach ($actor as $value) {
            $candidate = is_array($value) ? trim((string)($value['id'] ?? '')) : trim((string)$value);
            if ($candidate !== '') return $candidate;
        }
    }
    return '';
}

function federated_timeline_existing(string $entryUri): ?array
{
    if (!federated_timeline_schema_available() || $entryUri === '') return null;
    $statement = db()->prepare('SELECT * FROM activitypub_remote_posts WHERE entry_uri=:entry_uri LIMIT 1');
    $statement->execute(['entry_uri' => $entryUri]);
    return $statement->fetch() ?: null;
}

function federated_timeline_ingest(int $inboxId, array $payload, array $remoteActor): bool
{
    $settings = federated_timeline_settings();
    if (!$settings['enabled'] || !federated_timeline_schema_available()) return false;
    if (!federated_interactions_actor_allowed($remoteActor)) return true;

    $activityType = trim((string)($payload['type'] ?? ''));
    if (!in_array($activityType, ['Create', 'Update', 'Announce'], true)) return false;
    $rawObject = $payload['object'] ?? null;
    $object = is_array($rawObject) ? $rawObject : [];
    $following = federated_timeline_following_accepted((int)$remoteActor['id']);
    $mentionsLocal = federated_timeline_mentions_local($payload, $object);
    if ((!$following || !$settings['store_following']) && (!$mentionsLocal || !$settings['receive_mentions'])) {
        return false;
    }

    $entryType = 'announce';
    $entryUri = trim((string)($payload['id'] ?? ''));
    $objectUri = is_string($rawObject) ? trim($rawObject) : trim((string)($object['id'] ?? ''));
    $boostedObjectUri = $activityType === 'Announce' ? $objectUri : '';
    if ($activityType !== 'Announce') {
        $type = trim((string)($object['type'] ?? ''));
        if (!in_array($type, ['Note', 'Article'], true)) return false;
        $entryType = strtolower($type);
        $entryUri = $objectUri;
        $attributedTo = federated_timeline_object_actor($object);
        if (activitypub_normalize_url($attributedTo) !== activitypub_normalize_url((string)$remoteActor['actor_uri'])) {
            throw new RuntimeException('The remote timeline object attribution does not match the verified actor.');
        }
    }
    if (!activitypub_https_url($entryUri) || !activitypub_https_url($objectUri)) return false;

    $existing = federated_timeline_existing($entryUri);
    if ($existing && (int)$existing['remote_actor_id'] !== (int)$remoteActor['id']) {
        throw new RuntimeException('A remote timeline entry cannot change actor ownership.');
    }
    if ($existing && activitypub_normalize_url((string)$existing['object_uri']) !== activitypub_normalize_url($objectUri)) {
        throw new RuntimeException('A remote timeline entry cannot change its object target.');
    }

    $title = federated_timeline_clean_text((string)($object['name'] ?? ''), 500);
    $summary = federated_timeline_clean_text((string)($object['summary'] ?? ''), 4000);
    $body = federated_timeline_clean_text((string)($object['content'] ?? $object['contentMap']['en'] ?? ''), 20000);
    if ($entryType !== 'announce' && $title === '' && $summary === '' && $body === '') {
        throw new RuntimeException('The remote timeline entry has no readable content.');
    }
    $sourceUrl = trim((string)($object['url'] ?? $objectUri));
    if (!activitypub_https_url($sourceUrl)) $sourceUrl = $objectUri;
    $inReplyTo = $object['inReplyTo'] ?? '';
    if (is_array($inReplyTo)) $inReplyTo = (string)($inReplyTo['id'] ?? '');
    $inReplyTo = trim((string)$inReplyTo);
    if ($inReplyTo !== '' && !activitypub_https_url($inReplyTo)) $inReplyTo = '';
    $status = $following ? 'active' : 'pending';
    if ($existing && in_array((string)$existing['status'], ['hidden', 'deleted'], true)) {
        $status = (string)$existing['status'];
    }
    $attachments = federated_timeline_attachments($object);
    $tags = federated_timeline_tags($object);
    $bodyHash = hash('sha256', implode('|', [$title, $summary, $body, $objectUri]));

    db()->prepare(
        'INSERT INTO activitypub_remote_posts
            (inbox_activity_id,remote_actor_id,entry_uri,source_activity_uri,object_uri,
             entry_type,boosted_object_uri,in_reply_to_uri,source_url,title,summary,
             body_text,body_hash,content_warning,is_sensitive,language_code,visibility,
             attachments_json,tags_json,mentions_local,status,source_published_at,source_updated_at)
         VALUES
            (:inbox_id,:actor_id,:entry_uri,:activity_uri,:object_uri,:entry_type,
             :boosted_object_uri,:in_reply_to_uri,:source_url,:title,:summary,:body_text,
             :body_hash,:content_warning,:sensitive,:language_code,:visibility,
             :attachments_json,:tags_json,:mentions_local,:status,:published_at,:updated_at)
         ON DUPLICATE KEY UPDATE
             inbox_activity_id=VALUES(inbox_activity_id),source_activity_uri=VALUES(source_activity_uri),
             in_reply_to_uri=VALUES(in_reply_to_uri),source_url=VALUES(source_url),title=VALUES(title),
             summary=VALUES(summary),body_text=VALUES(body_text),body_hash=VALUES(body_hash),
             content_warning=VALUES(content_warning),is_sensitive=VALUES(is_sensitive),
             language_code=VALUES(language_code),visibility=VALUES(visibility),
             attachments_json=VALUES(attachments_json),tags_json=VALUES(tags_json),
             mentions_local=VALUES(mentions_local),
             status=CASE WHEN status IN ("hidden","deleted") THEN status ELSE VALUES(status) END,
             source_updated_at=VALUES(source_updated_at),updated_at=UTC_TIMESTAMP()'
    )->execute([
        'inbox_id' => $inboxId,
        'actor_id' => (int)$remoteActor['id'],
        'entry_uri' => $entryUri,
        'activity_uri' => trim((string)$payload['id']),
        'object_uri' => $objectUri,
        'entry_type' => $entryType,
        'boosted_object_uri' => $boostedObjectUri !== '' ? $boostedObjectUri : null,
        'in_reply_to_uri' => $inReplyTo !== '' ? $inReplyTo : null,
        'source_url' => $sourceUrl,
        'title' => $title !== '' ? $title : null,
        'summary' => $summary !== '' ? $summary : null,
        'body_text' => $body !== '' ? $body : null,
        'body_hash' => $bodyHash,
        'content_warning' => $summary !== '' && (int)($object['sensitive'] ?? 0) === 1 ? mb_substr($summary, 0, 1000) : null,
        'sensitive' => !empty($object['sensitive']) ? 1 : 0,
        'language_code' => mb_substr(trim((string)($object['language'] ?? '')), 0, 35) ?: null,
        'visibility' => federated_timeline_visibility($payload, $object),
        'attachments_json' => $attachments ? json_encode($attachments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        'tags_json' => $tags ? json_encode($tags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        'mentions_local' => $mentionsLocal ? 1 : 0,
        'status' => $status,
        'published_at' => federated_timeline_iso_to_sql($object['published'] ?? $payload['published'] ?? null),
        'updated_at' => federated_timeline_iso_to_sql($object['updated'] ?? $payload['updated'] ?? null),
    ]);
    $statement = db()->prepare('SELECT id FROM activitypub_remote_posts WHERE entry_uri=:entry_uri LIMIT 1');
    $statement->execute(['entry_uri' => $entryUri]);
    $postId = (int)($statement->fetchColumn() ?: 0);
    if ($status === 'pending' && $mentionsLocal && !federated_interactions_actor_muted($remoteActor)) {
        notification_create_for_role(
            'admin', 'message', 'Federated mention awaiting review',
            mb_substr($body !== '' ? $body : ($title ?: $objectUri), 0, 240),
            'portal/federated-timeline.php?queue=mentions', 'federated_post', $postId, 'normal'
        );
    }
    log_activity('federated_timeline_received', 'federated_post', $postId, [
        'actor_uri' => (string)$remoteActor['actor_uri'],
        'entry_type' => $entryType,
        'status' => $status,
    ]);
    return true;
}

function federated_timeline_delete(string $objectUri, array $remoteActor): bool
{
    if (!federated_timeline_schema_available() || $objectUri === '') return false;
    $statement = db()->prepare(
        'UPDATE activitypub_remote_posts
         SET status="deleted",deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()
         WHERE remote_actor_id=:actor_id
           AND (entry_uri=:entry_uri OR object_uri=:object_uri OR source_activity_uri=:activity_uri)
           AND status<>"deleted"'
    );
    $statement->execute([
        'actor_id' => (int)$remoteActor['id'],
        'entry_uri' => $objectUri,
        'object_uri' => $objectUri,
        'activity_uri' => $objectUri,
    ]);
    return $statement->rowCount() > 0;
}

function federated_timeline_undo(array $payload, array $remoteActor): bool
{
    $object = $payload['object'] ?? null;
    $activityUri = is_array($object) ? trim((string)($object['id'] ?? '')) : trim((string)$object);
    if ($activityUri === '') return false;
    return federated_timeline_delete($activityUri, $remoteActor);
}

function federated_timeline_process_inbound(int $inboxId, array $payload, array $remoteActor): bool
{
    $type = trim((string)($payload['type'] ?? ''));
    if (in_array($type, ['Create', 'Update', 'Announce'], true)) {
        return federated_timeline_ingest($inboxId, $payload, $remoteActor);
    }
    if ($type === 'Undo') return federated_timeline_undo($payload, $remoteActor);
    if ($type === 'Delete') {
        $object = $payload['object'] ?? '';
        $uri = is_array($object) ? trim((string)($object['id'] ?? '')) : trim((string)$object);
        if (activitypub_normalize_url($uri) === activitypub_normalize_url((string)$remoteActor['actor_uri'])) return false;
        return federated_timeline_delete($uri, $remoteActor);
    }
    return false;
}

function federated_timeline_post(int $id): ?array
{
    if (!federated_timeline_schema_available() || $id <= 0) return null;
    $statement = db()->prepare(
        'SELECT post.*,actor.actor_uri,actor.preferred_username,actor.display_name,
                actor.profile_url,actor.status AS actor_status,
                COALESCE(control.moderation_status,"active") AS moderation_status
         FROM activitypub_remote_posts post
         JOIN activitypub_remote_actors actor ON actor.id=post.remote_actor_id
         LEFT JOIN activitypub_actor_controls control ON control.remote_actor_id=actor.id
         WHERE post.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function federated_timeline_set_state(int $postId, int $userId, string $action): void
{
    federated_timeline_require_schema();
    if (!in_array($action, ['read', 'unread', 'save', 'unsave', 'hide', 'unhide'], true)) {
        throw new RuntimeException('The timeline state action is invalid.');
    }
    $columns = [
        'read' => ['read_at', 'UTC_TIMESTAMP()'],
        'unread' => ['read_at', 'NULL'],
        'save' => ['saved_at', 'UTC_TIMESTAMP()'],
        'unsave' => ['saved_at', 'NULL'],
        'hide' => ['hidden_at', 'UTC_TIMESTAMP()'],
        'unhide' => ['hidden_at', 'NULL'],
    ];
    [$column, $value] = $columns[$action];
    db()->prepare(
        'INSERT INTO activitypub_timeline_user_state (remote_post_id,user_id,' . $column . ')
         VALUES (:post_id,:user_id,' . $value . ')
         ON DUPLICATE KEY UPDATE ' . $column . '=' . $value . ',updated_at=UTC_TIMESTAMP()'
    )->execute(['post_id' => $postId, 'user_id' => $userId]);
}

function federated_timeline_moderate(int $postId, string $status, int $userId, string $note = ''): void
{
    federated_timeline_require_schema();
    if (!in_array($status, ['active', 'hidden', 'deleted'], true)) {
        throw new RuntimeException('Choose Approve, Hide, or Delete.');
    }
    db()->prepare(
        'UPDATE activitypub_remote_posts
         SET status=:status,moderation_note=:note,moderated_by_user_id=:user_id,
             moderated_at=UTC_TIMESTAMP(),
             deleted_at=CASE WHEN :deleted="deleted" THEN UTC_TIMESTAMP() ELSE deleted_at END
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'note' => mb_substr(trim($note), 0, 1000) ?: null,
        'user_id' => $userId,
        'deleted' => $status,
        'id' => $postId,
    ]);
}

function federated_timeline_query(int $userId, array $filters = [], int $limit = 100): array
{
    if (!federated_timeline_schema_available()) return [];
    $limit = max(1, min(250, $limit));
    $queue = trim((string)($filters['queue'] ?? 'following'));
    $search = trim((string)($filters['q'] ?? ''));
    $actorId = max(0, (int)($filters['actor_id'] ?? 0));
    $where = ['COALESCE(control.moderation_status,"active")<>"blocked"'];
    $params = ['user_id' => $userId];
    if ($queue === 'mentions') $where[] = 'post.mentions_local=1 AND post.status IN ("pending","active")';
    elseif ($queue === 'boosts') $where[] = 'post.entry_type="announce" AND post.status="active"';
    elseif ($queue === 'unread') $where[] = 'post.status="active" AND state.read_at IS NULL';
    elseif ($queue === 'saved') $where[] = 'post.status<>"deleted" AND state.saved_at IS NOT NULL';
    elseif ($queue === 'hidden') $where[] = '(post.status="hidden" OR state.hidden_at IS NOT NULL)';
    elseif ($queue === 'all') $where[] = 'post.status<>"deleted"';
    else $where[] = 'post.status="active" AND state.hidden_at IS NULL';
    if ($search !== '') {
        $where[] = '(post.title LIKE :search OR post.summary LIKE :search OR post.body_text LIKE :search OR actor.display_name LIKE :search OR actor.preferred_username LIKE :search)';
        $params['search'] = '%' . mb_substr($search, 0, 190) . '%';
    }
    if ($actorId > 0) {
        $where[] = 'post.remote_actor_id=:actor_id';
        $params['actor_id'] = $actorId;
    }
    $statement = db()->prepare(
        'SELECT post.*,actor.actor_uri,actor.preferred_username,actor.display_name,
                actor.profile_url,state.read_at,state.saved_at,state.hidden_at,
                COALESCE(control.moderation_status,"active") AS moderation_status,
                (SELECT COUNT(*) FROM activitypub_remote_post_actions action
                 WHERE action.remote_post_id=post.id AND action.action_type="like" AND action.status="active") AS liked,
                (SELECT COUNT(*) FROM activitypub_remote_post_actions action
                 WHERE action.remote_post_id=post.id AND action.action_type="announce" AND action.status="active") AS boosted
         FROM activitypub_remote_posts post
         JOIN activitypub_remote_actors actor ON actor.id=post.remote_actor_id
         LEFT JOIN activitypub_timeline_user_state state
           ON state.remote_post_id=post.id AND state.user_id=:user_id
         LEFT JOIN activitypub_actor_controls control ON control.remote_actor_id=actor.id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY COALESCE(post.source_published_at,post.created_at) DESC,post.id DESC
         LIMIT ' . $limit
    );
    $statement->execute($params);
    return $statement->fetchAll();
}

function federated_timeline_active_action(int $postId, string $type): ?array
{
    $statement = db()->prepare(
        'SELECT * FROM activitypub_remote_post_actions
         WHERE remote_post_id=:post_id AND action_type=:action_type AND status="active"
         ORDER BY id DESC LIMIT 1'
    );
    $statement->execute(['post_id' => $postId, 'action_type' => $type]);
    return $statement->fetch() ?: null;
}

function federated_timeline_store_action(
    int $postId,
    string $type,
    array $activity,
    int $outboxId,
    int $userId,
    ?array $replyObject = null,
    string $replyText = ''
): int {
    $uuid = pod_uuid_v4();
    $rawObject = $activity['object'] ?? '';
    $actionObjectUri = is_array($rawObject)
        ? trim((string)($rawObject['inReplyTo'] ?? $rawObject['id'] ?? ''))
        : trim((string)$rawObject);
    db()->prepare(
        'INSERT INTO activitypub_remote_post_actions
            (action_uuid,remote_post_id,action_type,activity_uri,outbox_activity_id,
             object_uri,reply_object_uri,reply_text,object_json,status,created_by_user_id)
         VALUES
            (:action_uuid,:post_id,:action_type,:activity_uri,:outbox_id,:object_uri,
             :reply_object_uri,:reply_text,:object_json,"active",:user_id)'
    )->execute([
        'action_uuid' => $uuid,
        'post_id' => $postId,
        'action_type' => $type,
        'activity_uri' => (string)$activity['id'],
        'outbox_id' => $outboxId,
        'object_uri' => $actionObjectUri,
        'reply_object_uri' => $replyObject ? (string)$replyObject['id'] : null,
        'reply_text' => $replyText !== '' ? $replyText : null,
        'object_json' => $replyObject ? json_encode($replyObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        'user_id' => $userId,
    ]);
    return (int)db()->lastInsertId();
}

function federated_timeline_action(int $postId, string $type, int $userId, string $replyText = ''): int
{
    federated_timeline_require_schema();
    if (!activitypub_settings()['enabled']) throw new RuntimeException('Enable ActivityPub before sending remote timeline actions.');
    if (!in_array($type, ['like', 'announce', 'reply'], true)) throw new RuntimeException('The remote timeline action is invalid.');
    $post = federated_timeline_post($postId);
    if (!$post || (string)$post['status'] !== 'active' || (string)$post['moderation_status'] === 'blocked') {
        throw new RuntimeException('The remote timeline post is unavailable.');
    }
    if (in_array($type, ['like', 'announce'], true) && federated_timeline_active_action($postId, $type)) {
        throw new RuntimeException('That remote timeline action is already active.');
    }
    $remoteActorId = (int)$post['remote_actor_id'];
    $objectUri = (string)$post['object_uri'];
    if ($type === 'reply') {
        $replyText = trim($replyText);
        if ($replyText === '' || mb_strlen($replyText) > 4000) throw new RuntimeException('Enter a reply of 1 to 4,000 characters.');
        $actionUuid = pod_uuid_v4();
        $replyObjectUri = publishing_absolute_url('activitypub-timeline-reply.php?id=' . $actionUuid);
        $replyObject = [
            'id' => $replyObjectUri,
            'type' => 'Note',
            'attributedTo' => activitypub_actor_url(),
            'inReplyTo' => $objectUri,
            'content' => '<p>' . nl2br(e($replyText)) . '</p>',
            'published' => gmdate(DATE_ATOM),
            'to' => [(string)$post['actor_uri']],
            'cc' => ['https://www.w3.org/ns/activitystreams#Public', activitypub_followers_url()],
        ];
        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => activitypub_activity_url(pod_uuid_v4()),
            'type' => 'Create',
            'actor' => activitypub_actor_url(),
            'object' => $replyObject,
            'to' => [(string)$post['actor_uri']],
            'cc' => ['https://www.w3.org/ns/activitystreams#Public', activitypub_followers_url()],
        ];
        $outboxId = activitypub_store_outbox_activity($activity, 'Create', 'Note', $replyObjectUri, null, $userId);
        activitypub_queue_delivery($outboxId, $remoteActorId);
        activitypub_queue_approved_followers($outboxId);
        db()->prepare(
            'INSERT INTO activitypub_remote_post_actions
                (action_uuid,remote_post_id,action_type,activity_uri,outbox_activity_id,
                 object_uri,reply_object_uri,reply_text,object_json,status,created_by_user_id)
             VALUES
                (:action_uuid,:post_id,"reply",:activity_uri,:outbox_id,:object_uri,
                 :reply_object_uri,:reply_text,:object_json,"active",:user_id)'
        )->execute([
            'action_uuid' => $actionUuid,
            'post_id' => $postId,
            'activity_uri' => (string)$activity['id'],
            'outbox_id' => $outboxId,
            'object_uri' => $objectUri,
            'reply_object_uri' => $replyObjectUri,
            'reply_text' => $replyText,
            'object_json' => json_encode($replyObject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'user_id' => $userId,
        ]);
        return (int)db()->lastInsertId();
    }

    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url(pod_uuid_v4()),
        'type' => $type === 'like' ? 'Like' : 'Announce',
        'actor' => activitypub_actor_url(),
        'object' => $objectUri,
        'to' => $type === 'like' ? [(string)$post['actor_uri']] : ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => $type === 'like' ? [] : [activitypub_followers_url()],
    ];
    $outboxId = activitypub_store_outbox_activity(
        $activity,
        $type === 'like' ? 'Like' : 'Announce',
        ucfirst($type),
        $objectUri,
        null,
        $userId
    );
    activitypub_queue_delivery($outboxId, $remoteActorId);
    if ($type === 'announce') activitypub_queue_approved_followers($outboxId);
    return federated_timeline_store_action($postId, $type, $activity, $outboxId, $userId);
}

function federated_timeline_undo_action(int $actionId, int $userId): void
{
    federated_timeline_require_schema();
    $statement = db()->prepare(
        'SELECT action.*,post.remote_actor_id,actor.actor_uri
         FROM activitypub_remote_post_actions action
         JOIN activitypub_remote_posts post ON post.id=action.remote_post_id
         JOIN activitypub_remote_actors actor ON actor.id=post.remote_actor_id
         WHERE action.id=:id AND action.status="active" AND action.action_type IN ("like","announce") LIMIT 1'
    );
    $statement->execute(['id' => $actionId]);
    $action = $statement->fetch();
    if (!$action) throw new RuntimeException('The active timeline action was not found.');
    $payloadStatement = db()->prepare('SELECT payload_json FROM activitypub_outbox_activities WHERE activity_uri=:uri LIMIT 1');
    $payloadStatement->execute(['uri' => (string)$action['activity_uri']]);
    $original = json_decode((string)($payloadStatement->fetchColumn() ?: ''), true);
    $undo = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url(pod_uuid_v4()),
        'type' => 'Undo',
        'actor' => activitypub_actor_url(),
        'object' => is_array($original) ? $original : (string)$action['activity_uri'],
        'to' => [(string)$action['actor_uri']],
    ];
    $outboxId = activitypub_store_outbox_activity($undo, 'Undo', ucfirst((string)$action['action_type']), (string)$action['object_uri'], null, $userId);
    activitypub_queue_delivery($outboxId, (int)$action['remote_actor_id']);
    if ((string)$action['action_type'] === 'announce') activitypub_queue_approved_followers($outboxId);
    db()->prepare('UPDATE activitypub_remote_post_actions SET status="undone",updated_at=UTC_TIMESTAMP() WHERE id=:id')
        ->execute(['id' => $actionId]);
}

function federated_timeline_delete_reply(int $actionId, int $userId): void
{
    federated_timeline_require_schema();
    $statement = db()->prepare(
        'SELECT action.*,post.remote_actor_id,actor.actor_uri
         FROM activitypub_remote_post_actions action
         JOIN activitypub_remote_posts post ON post.id=action.remote_post_id
         JOIN activitypub_remote_actors actor ON actor.id=post.remote_actor_id
         WHERE action.id=:id AND action.status="active" AND action.action_type="reply" LIMIT 1'
    );
    $statement->execute(['id' => $actionId]);
    $action = $statement->fetch();
    if (!$action) throw new RuntimeException('The active federated reply was not found.');
    $tombstone = [
        'id' => (string)$action['reply_object_uri'],
        'type' => 'Tombstone',
        'formerType' => 'Note',
        'deleted' => gmdate(DATE_ATOM),
    ];
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url(pod_uuid_v4()),
        'type' => 'Delete',
        'actor' => activitypub_actor_url(),
        'object' => $tombstone,
        'to' => [(string)$action['actor_uri']],
        'cc' => ['https://www.w3.org/ns/activitystreams#Public', activitypub_followers_url()],
    ];
    $outboxId = activitypub_store_outbox_activity($activity, 'Delete', 'Tombstone', (string)$action['reply_object_uri'], null, $userId);
    activitypub_queue_delivery($outboxId, (int)$action['remote_actor_id']);
    activitypub_queue_approved_followers($outboxId);
    db()->prepare('UPDATE activitypub_remote_post_actions SET status="deleted",object_json=:object_json,updated_at=UTC_TIMESTAMP() WHERE id=:id')
        ->execute([
            'object_json' => json_encode($tombstone, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'id' => $actionId,
        ]);
}

function federated_timeline_reply_object(string $uuid): ?array
{
    if (!federated_timeline_schema_available() || !activitypub_valid_uuid($uuid)) return null;
    $statement = db()->prepare(
        'SELECT object_json FROM activitypub_remote_post_actions
         WHERE action_uuid=:uuid AND action_type="reply" LIMIT 1'
    );
    $statement->execute(['uuid' => strtolower($uuid)]);
    $object = json_decode((string)($statement->fetchColumn() ?: ''), true);
    return is_array($object) ? $object : null;
}

function federated_timeline_resolve_actor_input(string $input): array
{
    $input = trim($input);
    if (activitypub_https_url($input)) return activitypub_remote_actor($input, false);
    $handle = ltrim($input, '@');
    if (!preg_match('/^([A-Za-z0-9_.-]{1,190})@([A-Za-z0-9.-]{1,253})$/', $handle, $matches)) {
        throw new RuntimeException('Enter an ActivityPub actor URL or handle such as @name@example.social.');
    }
    $username = $matches[1];
    $domain = federated_interactions_normalize_domain($matches[2]);
    $resource = 'acct:' . $username . '@' . $domain;
    $response = activitypub_fetch_json(
        'https://' . $domain . '/.well-known/webfinger?resource=' . rawurlencode($resource)
    );
    $document = $response['json'];
    if (strtolower((string)($document['subject'] ?? '')) !== strtolower($resource)) {
        throw new RuntimeException('The WebFinger subject does not match the requested handle.');
    }
    $actorUri = '';
    foreach ((array)($document['links'] ?? []) as $link) {
        if (!is_array($link) || (string)($link['rel'] ?? '') !== 'self') continue;
        $href = trim((string)($link['href'] ?? ''));
        $type = strtolower((string)($link['type'] ?? ''));
        if (activitypub_https_url($href) && ($type === '' || str_contains($type, 'activity+json') || str_contains($type, 'ld+json'))) {
            $actorUri = $href;
            break;
        }
    }
    if ($actorUri === '') throw new RuntimeException('WebFinger did not expose an ActivityPub actor.');
    return activitypub_remote_actor($actorUri, false);
}

function federated_timeline_cleanup(): int
{
    if (!federated_timeline_schema_available()) return 0;
    $days = federated_timeline_settings()['retention_days'];
    $statement = db()->prepare(
        'DELETE post FROM activitypub_remote_posts post
         WHERE COALESCE(post.source_published_at,post.created_at)<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . $days . ' DAY)
           AND NOT EXISTS (
                SELECT 1 FROM activitypub_timeline_user_state state
                WHERE state.remote_post_id=post.id AND state.saved_at IS NOT NULL
           )
           AND NOT EXISTS (
                SELECT 1 FROM activitypub_remote_post_actions action
                WHERE action.remote_post_id=post.id
           )'
    );
    $statement->execute();
    return $statement->rowCount();
}


function federated_timeline_sync_delivery(array $delivery, array $result): void
{
    if (!federated_timeline_schema_available()) return;
    $outboxId = (int)($delivery['outbox_activity_id'] ?? 0);
    $remoteActorId = (int)($delivery['remote_actor_id'] ?? 0);
    if ($outboxId <= 0 || $remoteActorId <= 0) return;
    $statement = db()->prepare(
        'UPDATE activitypub_remote_post_actions action
         JOIN activitypub_remote_posts post ON post.id=action.remote_post_id
         SET action.status=CASE WHEN :delivered=1 THEN "active" ELSE "failed" END,
             action.last_error=CASE WHEN :delivered2=1 THEN NULL ELSE :last_error END,
             action.updated_at=UTC_TIMESTAMP()
         WHERE action.outbox_activity_id=:outbox_id
           AND post.remote_actor_id=:remote_actor_id
           AND action.status NOT IN ("undone","deleted")'
    );
    $statement->execute([
        'delivered' => !empty($result['ok']) ? 1 : 0,
        'delivered2' => !empty($result['ok']) ? 1 : 0,
        'last_error' => !empty($result['ok']) ? null : mb_substr((string)($result['error'] ?? 'Delivery failed.'), 0, 1000),
        'outbox_id' => $outboxId,
        'remote_actor_id' => $remoteActorId,
    ]);
}

function federated_timeline_reset_delivery(int $deliveryId): void
{
    if (!federated_timeline_schema_available() || $deliveryId <= 0) return;
    db()->prepare(
        'UPDATE activitypub_remote_post_actions action
         JOIN activitypub_deliveries delivery ON delivery.outbox_activity_id=action.outbox_activity_id
         JOIN activitypub_remote_posts post ON post.id=action.remote_post_id
         SET action.status="active",action.last_error=NULL,action.updated_at=UTC_TIMESTAMP()
         WHERE delivery.id=:delivery_id
           AND delivery.remote_actor_id=post.remote_actor_id
           AND action.status="failed"'
    )->execute(['delivery_id' => $deliveryId]);
}

function federated_timeline_actions_for_posts(array $postIds): array
{
    if (!federated_timeline_schema_available()) return [];
    $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds), static fn(int $id): bool => $id > 0)));
    if (!$postIds) return [];
    $rows = db()->query(
        'SELECT id,remote_post_id,action_type,reply_text,reply_object_uri,status,created_at
         FROM activitypub_remote_post_actions
         WHERE remote_post_id IN (' . implode(',', $postIds) . ')
         ORDER BY remote_post_id,id DESC'
    )->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $postId = (int)$row['remote_post_id'];
        $result[$postId] ??= ['like' => null, 'announce' => null, 'replies' => []];
        $type = (string)$row['action_type'];
        if ($type === 'reply') {
            if (count($result[$postId]['replies']) < 20) $result[$postId]['replies'][] = $row;
        } elseif (in_array($type, ['like', 'announce'], true) && $result[$postId][$type] === null && (string)$row['status'] === 'active') {
            $result[$postId][$type] = $row;
        }
    }
    return $result;
}

function federated_timeline_inbox_items(): array
{
    if (!federated_timeline_schema_available() || !function_exists('unified_inbox_item')) return [];
    $items = [];
    $mentions = db()->query(
        'SELECT post.*,actor.display_name,actor.preferred_username
         FROM activitypub_remote_posts post
         JOIN activitypub_remote_actors actor ON actor.id=post.remote_actor_id
         WHERE post.mentions_local=1 AND post.status="pending"
         ORDER BY post.created_at DESC,post.id DESC LIMIT 100'
    )->fetchAll();
    foreach ($mentions as $row) {
        $items[] = unified_inbox_item([
            'source_type' => 'federated_post',
            'source_id' => (int)$row['id'],
            'source_label' => 'Federated Mention',
            'category' => 'social',
            'icon' => '@',
            'title' => 'Federated mention awaiting review',
            'participant' => (string)($row['display_name'] ?: $row['preferred_username'] ?: 'Remote actor'),
            'preview' => (string)($row['body_text'] ?: $row['title'] ?: $row['object_uri']),
            'occurred_at' => (string)($row['source_published_at'] ?: $row['created_at']),
            'native_unread' => true,
            'native_status' => 'pending',
            'native_needs_response' => true,
            'href' => app_url('portal/federated-timeline.php?queue=mentions'),
        ]);
    }
    $failed = db()->query(
        'SELECT action.*,post.object_uri,actor.display_name,actor.preferred_username
         FROM activitypub_remote_post_actions action
         JOIN activitypub_remote_posts post ON post.id=action.remote_post_id
         JOIN activitypub_remote_actors actor ON actor.id=post.remote_actor_id
         WHERE action.status="failed"
         ORDER BY action.updated_at DESC,action.id DESC LIMIT 50'
    )->fetchAll();
    foreach ($failed as $row) {
        $items[] = unified_inbox_item([
            'source_type' => 'federated_timeline_action',
            'source_id' => (int)$row['id'],
            'source_label' => 'Federated Action',
            'category' => 'social',
            'icon' => '↗',
            'title' => 'Federated ' . status_label((string)$row['action_type']) . ' failed',
            'participant' => (string)($row['display_name'] ?: $row['preferred_username'] ?: 'Remote actor'),
            'preview' => (string)($row['last_error'] ?: $row['object_uri']),
            'occurred_at' => (string)$row['updated_at'],
            'native_unread' => true,
            'native_status' => 'failed',
            'native_priority' => 'high',
            'native_needs_response' => true,
            'href' => app_url('portal/federated-timeline.php?queue=all'),
        ]);
    }
    return $items;
}

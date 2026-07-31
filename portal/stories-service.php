<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-followed-feed-stories-v66O */

function stories_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN ("pod_stories","pod_story_views","pod_story_events")'
        );
        return $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        return $available = false;
    }
}

function stories_require_schema(): void
{
    if (!stories_schema_available()) {
        throw new RuntimeException(
            'Import database/stories_v66o.sql before using Stories.'
        );
    }
}

function stories_settings(): array
{
    return [
        'enabled' => (string)setting('stories_enabled', '1') !== '0',
        'receive_remote' => (string)setting('stories_receive_remote', '1') !== '0',
        'duration_hours' => max(
            1,
            min(48, (int)setting('stories_duration_hours', '24'))
        ),
        'max_active' => max(
            1,
            min(50, (int)setting('stories_max_active', '10'))
        ),
        'remote_media_mode' => 'link_only',
    ];
}

function stories_clean_text(string $value, int $limit): string
{
    $value = html_entity_decode(
        strip_tags($value),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, $limit);
}

function stories_sql_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false
        ? null
        : gmdate('Y-m-d H:i:s', $timestamp);
}

function stories_local_media_url(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (str_starts_with($value, '/')) {
        $value = app_url(ltrim($value, '/'));
    }
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('The story media URL is invalid.');
    }
    $base = parse_url(app_url());
    $candidate = parse_url($value);
    if (!is_array($base) || !is_array($candidate)) {
        throw new RuntimeException('The story media URL is invalid.');
    }
    $baseScheme = strtolower((string)($base['scheme'] ?? ''));
    $baseHost = strtolower((string)($base['host'] ?? ''));
    $candidateScheme = strtolower((string)($candidate['scheme'] ?? ''));
    $candidateHost = strtolower((string)($candidate['host'] ?? ''));
    $basePort = (int)($base['port'] ?? ($baseScheme === 'https' ? 443 : 80));
    $candidatePort = (int)($candidate['port'] ?? ($candidateScheme === 'https' ? 443 : 80));
    if (
        $candidateScheme !== $baseScheme
        || $candidateHost !== $baseHost
        || $candidatePort !== $basePort
        || !in_array($candidateScheme, ['http', 'https'], true)
    ) {
        throw new RuntimeException(
            'Story media must use protected same-origin storage.'
        );
    }
    if (isset($candidate['user']) || isset($candidate['pass'])) {
        throw new RuntimeException('Story media URLs cannot contain credentials.');
    }
    return mb_substr($value, 0, 2048);
}

function stories_remote_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || !activitypub_https_url($value)) return '';
    return mb_substr($value, 0, 2048);
}

function stories_record_event(
    int $storyId,
    string $eventType,
    string $eventKey,
    ?int $actorUserId = null,
    ?int $remoteActorId = null,
    array $metadata = []
): void {
    if ($storyId <= 0 || !stories_schema_available()) return;
    $eventType = mb_substr(trim($eventType), 0, 80);
    if ($eventType === '') return;
    $hash = hash(
        'sha256',
        implode('|', [
            'stories-v66o',
            (string)$storyId,
            $eventType,
            $eventKey,
        ])
    );
    $json = $metadata
        ? json_encode(
            $metadata,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        )
        : null;
    db()->prepare(
        'INSERT IGNORE INTO pod_story_events
            (story_id,event_type,actor_user_id,remote_actor_id,event_sha256,metadata_json)
         VALUES
            (:story_id,:event_type,:actor_user_id,:remote_actor_id,:event_sha256,:metadata_json)'
    )->execute([
        'story_id' => $storyId,
        'event_type' => $eventType,
        'actor_user_id' => ($actorUserId ?? 0) > 0 ? $actorUserId : null,
        'remote_actor_id' => ($remoteActorId ?? 0) > 0 ? $remoteActorId : null,
        'event_sha256' => $hash,
        'metadata_json' => $json,
    ]);
}

function stories_find(int $storyId): ?array
{
    if ($storyId <= 0 || !stories_schema_available()) return null;
    $statement = db()->prepare('SELECT * FROM pod_stories WHERE id=:id LIMIT 1');
    $statement->execute(['id' => $storyId]);
    return $statement->fetch() ?: null;
}

function stories_find_local_uuid(string $uuid): ?array
{
    if (!stories_schema_available() || !activitypub_valid_uuid($uuid)) return null;
    $statement = db()->prepare(
        'SELECT * FROM pod_stories
         WHERE story_uuid=:uuid AND direction="local" LIMIT 1'
    );
    $statement->execute(['uuid' => strtolower($uuid)]);
    return $statement->fetch() ?: null;
}

function stories_object_url(array $story): string
{
    return app_url(
        'activitypub-story.php?id=' . rawurlencode((string)$story['story_uuid'])
    );
}

function stories_activity_object(array $story): array
{
    $object = [
        'id' => stories_object_url($story),
        'type' => 'Note',
        'attributedTo' => activitypub_actor_url(),
        'published' => gmdate(DATE_ATOM, strtotime((string)$story['published_at']) ?: time()),
        'endTime' => gmdate(DATE_ATOM, strtotime((string)$story['expires_at']) ?: time()),
        'to' => [activitypub_followers_url()],
        'content' => nl2br(e((string)($story['body_text'] ?? ''))),
        'summary' => (string)($story['title'] ?? ''),
        'tag' => [[
            'type' => 'Hashtag',
            'name' => '#story',
        ]],
        'sensitive' => false,
    ];
    if (!empty($story['media_url'])) {
        $type = match ((string)$story['media_kind']) {
            'image' => 'Image',
            'audio' => 'Audio',
            'video' => 'Video',
            default => 'Link',
        };
        $object['attachment'] = [[
            'type' => $type,
            'url' => (string)$story['media_url'],
            'name' => (string)($story['media_alt'] ?? ''),
        ]];
    }
    if (!empty($story['link_url'])) {
        $object['url'] = (string)$story['link_url'];
    }
    return $object;
}

function stories_publish_activity(
    array $story,
    string $activityType,
    ?int $actorUserId
): int {
    if (
        !in_array($activityType, ['Create', 'Delete'], true)
        || !activitypub_schema_available()
        || !activitypub_settings()['enabled']
        || (string)($story['direction'] ?? '') !== 'local'
    ) {
        return 0;
    }
    $objectUri = stories_object_url($story);
    $version = (string)($story['updated_at'] ?? $story['published_at'] ?? '');
    if ($activityType === 'Delete') {
        $version .= '|' . (string)($story['deleted_at'] ?? gmdate('Y-m-d H:i:s'));
    }
    $uuid = activitypub_uuid_from_seed(
        'stories-v66o|' . $activityType . '|'
        . (string)$story['story_uuid'] . '|' . $version
    );
    $object = $activityType === 'Delete'
        ? [
            'id' => $objectUri,
            'type' => 'Tombstone',
            'formerType' => 'Note',
            'deleted' => gmdate(DATE_ATOM),
          ]
        : stories_activity_object($story);
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url($uuid),
        'type' => $activityType,
        'actor' => activitypub_actor_url(),
        'to' => [activitypub_followers_url()],
        'object' => $object,
    ];
    $outboxId = activitypub_store_outbox_activity(
        $activity,
        $activityType,
        $activityType === 'Delete' ? 'Tombstone' : 'Note',
        $objectUri,
        null,
        $actorUserId
    );
    $queued = activitypub_queue_approved_followers($outboxId);
    stories_record_event(
        (int)$story['id'],
        'federation_' . strtolower($activityType),
        (string)$outboxId,
        $actorUserId,
        null,
        ['outbox_activity_id' => $outboxId, 'queued_deliveries' => $queued]
    );
    return $outboxId;
}

function stories_create_local(array $input, int $userId): int
{
    stories_require_schema();
    $settings = stories_settings();
    if (!$settings['enabled']) {
        throw new RuntimeException('Stories are disabled.');
    }
    if ($userId <= 0) throw new RuntimeException('A valid story owner is required.');
    $title = stories_clean_text((string)($input['title'] ?? ''), 200);
    $body = stories_clean_text((string)($input['body_text'] ?? ''), 4000);
    $mediaKind = strtolower(trim((string)($input['media_kind'] ?? 'none')));
    if (!in_array($mediaKind, ['none', 'image', 'audio', 'video', 'link'], true)) {
        $mediaKind = 'none';
    }
    $mediaUrl = stories_local_media_url((string)($input['media_url'] ?? ''));
    $mediaAlt = stories_clean_text((string)($input['media_alt'] ?? ''), 500);
    $linkUrl = stories_local_media_url((string)($input['link_url'] ?? ''));
    if ($mediaUrl === '') $mediaKind = 'none';
    if ($title === '' && $body === '' && $mediaUrl === '' && $linkUrl === '') {
        throw new RuntimeException('Add text, same-origin media, or a link to the story.');
    }
    $activeStatement = db()->prepare(
        'SELECT COUNT(*) FROM pod_stories
         WHERE direction="local" AND owner_user_id=:user_id
           AND status="active" AND expires_at>UTC_TIMESTAMP()'
    );
    $activeStatement->execute(['user_id' => $userId]);
    if ((int)$activeStatement->fetchColumn() >= $settings['max_active']) {
        throw new RuntimeException('The active story limit has been reached.');
    }
    $uuid = strtolower(pod_uuid_v4());
    $publishedAt = gmdate('Y-m-d H:i:s');
    $expiresAt = gmdate(
        'Y-m-d H:i:s',
        time() + ($settings['duration_hours'] * 3600)
    );
    $bodyHash = hash(
        'sha256',
        implode('|', [$title, $body, $mediaKind, $mediaUrl, $linkUrl, $expiresAt])
    );
    db()->beginTransaction();
    try {
        db()->prepare(
            'INSERT INTO pod_stories
                (story_uuid,direction,owner_user_id,title,body_text,body_sha256,
                 media_kind,media_url,media_alt,link_url,visibility,status,
                 published_at,expires_at)
             VALUES
                (:uuid,"local",:owner_user_id,:title,:body_text,:body_sha256,
                 :media_kind,:media_url,:media_alt,:link_url,"followers","active",
                 :published_at,:expires_at)'
        )->execute([
            'uuid' => $uuid,
            'owner_user_id' => $userId,
            'title' => $title !== '' ? $title : null,
            'body_text' => $body !== '' ? $body : null,
            'body_sha256' => $bodyHash,
            'media_kind' => $mediaKind,
            'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
            'media_alt' => $mediaAlt !== '' ? $mediaAlt : null,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
            'published_at' => $publishedAt,
            'expires_at' => $expiresAt,
        ]);
        $storyId = (int)db()->lastInsertId();
        stories_record_event(
            $storyId,
            'created',
            $uuid,
            $userId,
            null,
            ['expires_at' => $expiresAt]
        );
        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        throw $exception;
    }
    $story = stories_find($storyId);
    if ($story) {
        try {
            stories_publish_activity($story, 'Create', $userId);
        } catch (Throwable $exception) {
            log_activity('story_federation_failed', 'story', $storyId, [
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
        }
    }
    log_activity('story_created', 'story', $storyId, ['expires_at' => $expiresAt]);
    return $storyId;
}

function stories_delete_local(int $storyId, int $userId): void
{
    stories_require_schema();
    $story = stories_find($storyId);
    if (!$story || (string)$story['direction'] !== 'local') {
        throw new RuntimeException('The local story was not found.');
    }
    if ((int)($story['owner_user_id'] ?? 0) !== $userId) {
        throw new RuntimeException('You do not own this story.');
    }
    if (in_array((string)$story['status'], ['deleted', 'expired'], true)) return;
    db()->prepare(
        'UPDATE pod_stories
         SET status="deleted",deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute(['id' => $storyId]);
    $story = stories_find($storyId) ?? $story;
    stories_record_event($storyId, 'deleted', (string)$story['updated_at'], $userId);
    try {
        stories_publish_activity($story, 'Delete', $userId);
    } catch (Throwable $exception) {
        log_activity('story_delete_federation_failed', 'story', $storyId, [
            'error' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
    log_activity('story_deleted', 'story', $storyId);
}

function stories_has_story_tag(array $object): bool
{
    $tags = $object['tag'] ?? [];
    if (!is_array($tags)) return false;
    if (!array_is_list($tags) && isset($tags['type'])) $tags = [$tags];
    foreach ($tags as $tag) {
        if (!is_array($tag)) continue;
        if ((string)($tag['type'] ?? '') !== 'Hashtag') continue;
        $name = strtolower(ltrim(trim((string)($tag['name'] ?? '')), '#'));
        if ($name === 'story' || $name === 'stories') return true;
    }
    return false;
}

function stories_object_actor(array $object): string
{
    $actor = $object['attributedTo'] ?? $object['actor'] ?? '';
    if (is_string($actor)) return trim($actor);
    if (is_array($actor)) {
        if (!array_is_list($actor)) return trim((string)($actor['id'] ?? ''));
        foreach ($actor as $item) {
            $candidate = is_array($item)
                ? trim((string)($item['id'] ?? ''))
                : trim((string)$item);
            if ($candidate !== '') return $candidate;
        }
    }
    return '';
}

function stories_remote_attachment(array $object): array
{
    $attachments = $object['attachment'] ?? [];
    if (!is_array($attachments)) return ['kind' => 'none', 'url' => '', 'alt' => ''];
    if (!array_is_list($attachments) && isset($attachments['type'])) {
        $attachments = [$attachments];
    }
    foreach (array_slice($attachments, 0, 4) as $attachment) {
        if (!is_array($attachment)) continue;
        $url = $attachment['url'] ?? '';
        if (is_array($url)) $url = $url['href'] ?? $url['url'] ?? '';
        $url = stories_remote_url((string)$url);
        if ($url === '') continue;
        $kind = match ((string)($attachment['type'] ?? 'Link')) {
            'Image' => 'image',
            'Audio' => 'audio',
            'Video' => 'video',
            default => 'link',
        };
        return [
            'kind' => $kind,
            'url' => $url,
            'alt' => stories_clean_text((string)($attachment['name'] ?? ''), 500),
        ];
    }
    return ['kind' => 'none', 'url' => '', 'alt' => ''];
}

function stories_process_inbound(
    int $inboxId,
    array $payload,
    array $remoteActor
): bool {
    $settings = stories_settings();
    if (
        !$settings['enabled']
        || !$settings['receive_remote']
        || !stories_schema_available()
    ) {
        return false;
    }
    $activityType = trim((string)($payload['type'] ?? ''));
    if (!in_array($activityType, ['Create', 'Update', 'Delete'], true)) return false;
    $rawObject = $payload['object'] ?? null;
    $object = is_array($rawObject) ? $rawObject : [];
    $objectUri = is_string($rawObject)
        ? trim($rawObject)
        : trim((string)($object['id'] ?? ''));
    if (!activitypub_https_url($objectUri)) return false;
    $objectHash = hash('sha256', activitypub_normalize_url($objectUri));

    if ($activityType === 'Delete') {
        $statement = db()->prepare(
            'SELECT id,remote_actor_id FROM pod_stories
             WHERE source_object_sha256=:object_hash AND direction="remote" LIMIT 1'
        );
        $statement->execute(['object_hash' => $objectHash]);
        $story = $statement->fetch();
        if (!$story) return false;
        if ((int)$story['remote_actor_id'] !== (int)$remoteActor['id']) {
            throw new RuntimeException('A remote story cannot change actor ownership.');
        }
        db()->prepare(
            'UPDATE pod_stories
             SET status="deleted",deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute(['id' => (int)$story['id']]);
        stories_record_event(
            (int)$story['id'],
            'remote_deleted',
            (string)($payload['id'] ?? $objectUri),
            null,
            (int)$remoteActor['id']
        );
        return true;
    }

    if ((string)($object['type'] ?? '') !== 'Note' || !stories_has_story_tag($object)) {
        return false;
    }
    if (!federated_timeline_following_accepted((int)$remoteActor['id'])) {
        return false;
    }
    $attributedTo = stories_object_actor($object);
    if (
        activitypub_normalize_url($attributedTo)
        !== activitypub_normalize_url((string)$remoteActor['actor_uri'])
    ) {
        throw new RuntimeException(
            'The remote story attribution does not match the verified actor.'
        );
    }
    $publishedAt = stories_sql_datetime(
        $object['published'] ?? $payload['published'] ?? gmdate(DATE_ATOM)
    ) ?? gmdate('Y-m-d H:i:s');
    $expiresAt = stories_sql_datetime($object['endTime'] ?? null);
    if ($expiresAt === null) return false;
    $publishedTs = strtotime($publishedAt) ?: time();
    $expiresTs = strtotime($expiresAt) ?: 0;
    if (
        $expiresTs <= time()
        || $expiresTs <= $publishedTs
        || ($expiresTs - $publishedTs) > 172800
        || $expiresTs > time() + 172800
    ) {
        throw new RuntimeException('The remote story expiration is invalid.');
    }
    $title = stories_clean_text((string)($object['summary'] ?? $object['name'] ?? ''), 200);
    $body = stories_clean_text((string)($object['content'] ?? ''), 4000);
    $attachment = stories_remote_attachment($object);
    $rawLinkUrl = $object['url'] ?? '';
    if (is_array($rawLinkUrl)) {
        $rawLinkUrl = $rawLinkUrl['href'] ?? $rawLinkUrl['url'] ?? '';
    }
    $linkUrl = stories_remote_url((string)$rawLinkUrl);
    if ($title === '' && $body === '' && $attachment['url'] === '' && $linkUrl === '') {
        throw new RuntimeException('The remote story has no readable content.');
    }
    $existing = db()->prepare(
        'SELECT id,remote_actor_id,status FROM pod_stories
         WHERE source_object_sha256=:object_hash LIMIT 1'
    );
    $existing->execute(['object_hash' => $objectHash]);
    $current = $existing->fetch();
    if ($current && (int)$current['remote_actor_id'] !== (int)$remoteActor['id']) {
        throw new RuntimeException('A remote story cannot change actor ownership.');
    }
    $visibility = federated_timeline_visibility($payload, $object);
    if (!in_array($visibility, ['followers', 'public'], true)) {
        $visibility = 'followers';
    }
    $bodyHash = hash(
        'sha256',
        implode('|', [
            $title,
            $body,
            $attachment['kind'],
            $attachment['url'],
            $linkUrl,
            $expiresAt,
        ])
    );
    $uuid = activitypub_uuid_from_seed('remote-story-v66o|' . $objectUri);
    db()->prepare(
        'INSERT INTO pod_stories
            (story_uuid,direction,remote_actor_id,source_activity_uri,
             source_object_uri,source_object_sha256,title,body_text,body_sha256,
             media_kind,media_url,media_alt,link_url,visibility,status,
             published_at,expires_at)
         VALUES
            (:uuid,"remote",:remote_actor_id,:source_activity_uri,
             :source_object_uri,:source_object_sha256,:title,:body_text,:body_sha256,
             :media_kind,:media_url,:media_alt,:link_url,:visibility,"active",
             :published_at,:expires_at)
         ON DUPLICATE KEY UPDATE
             source_activity_uri=VALUES(source_activity_uri),
             title=VALUES(title),body_text=VALUES(body_text),body_sha256=VALUES(body_sha256),
             media_kind=VALUES(media_kind),media_url=VALUES(media_url),
             media_alt=VALUES(media_alt),link_url=VALUES(link_url),
             visibility=VALUES(visibility),published_at=VALUES(published_at),
             expires_at=VALUES(expires_at),
             status=CASE WHEN status IN ("hidden","deleted") THEN status ELSE "active" END,
             updated_at=UTC_TIMESTAMP()'
    )->execute([
        'uuid' => $uuid,
        'remote_actor_id' => (int)$remoteActor['id'],
        'source_activity_uri' => mb_substr(trim((string)($payload['id'] ?? '')), 0, 2048) ?: null,
        'source_object_uri' => mb_substr($objectUri, 0, 2048),
        'source_object_sha256' => $objectHash,
        'title' => $title !== '' ? $title : null,
        'body_text' => $body !== '' ? $body : null,
        'body_sha256' => $bodyHash,
        'media_kind' => $attachment['kind'],
        'media_url' => $attachment['url'] !== '' ? $attachment['url'] : null,
        'media_alt' => $attachment['alt'] !== '' ? $attachment['alt'] : null,
        'link_url' => $linkUrl !== '' ? $linkUrl : null,
        'visibility' => $visibility,
        'published_at' => $publishedAt,
        'expires_at' => $expiresAt,
    ]);
    $statement = db()->prepare(
        'SELECT id FROM pod_stories WHERE source_object_sha256=:object_hash LIMIT 1'
    );
    $statement->execute(['object_hash' => $objectHash]);
    $storyId = (int)($statement->fetchColumn() ?: 0);
    stories_record_event(
        $storyId,
        $activityType === 'Create' ? 'remote_created' : 'remote_updated',
        (string)($payload['id'] ?? $objectUri),
        null,
        (int)$remoteActor['id'],
        ['inbox_activity_id' => $inboxId]
    );
    return true;
}

function stories_feed(int $userId, int $limit = 50): array
{
    if (!stories_schema_available() || $userId <= 0) return [];
    $limit = max(1, min(200, $limit));
    $statement = db()->prepare(
        'SELECT story.*,
                owner.display_name AS owner_name,
                actor.display_name AS remote_display_name,
                actor.preferred_username AS remote_username,
                actor.actor_uri,actor.profile_url,actor.avatar_url,
                view.first_viewed_at,view.last_viewed_at,view.view_count
         FROM pod_stories story
         LEFT JOIN users owner ON owner.id=story.owner_user_id
         LEFT JOIN activitypub_remote_actors actor ON actor.id=story.remote_actor_id
         LEFT JOIN pod_story_views view
           ON view.story_id=story.id AND view.viewer_user_id=:viewer_user_id
         WHERE story.status="active" AND story.expires_at>UTC_TIMESTAMP()
           AND (
                story.direction="local"
                OR EXISTS (
                    SELECT 1 FROM activitypub_following following
                    WHERE following.remote_actor_id=story.remote_actor_id
                      AND following.status="accepted"
                )
           )
         ORDER BY story.published_at DESC,story.id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['viewer_user_id' => $userId]);
    return $statement->fetchAll();
}

function stories_mark_viewed(int $storyId, int $userId): bool
{
    if ($storyId <= 0 || $userId <= 0 || !stories_schema_available()) return false;
    $statement = db()->prepare(
        'SELECT id FROM pod_stories
         WHERE id=:id AND status="active" AND expires_at>UTC_TIMESTAMP() LIMIT 1'
    );
    $statement->execute(['id' => $storyId]);
    if (!(int)$statement->fetchColumn()) return false;
    db()->prepare(
        'INSERT INTO pod_story_views
            (story_id,viewer_user_id,first_viewed_at,last_viewed_at,view_count)
         VALUES (:story_id,:viewer_user_id,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)
         ON DUPLICATE KEY UPDATE
            last_viewed_at=UTC_TIMESTAMP(),view_count=view_count+1'
    )->execute(['story_id' => $storyId, 'viewer_user_id' => $userId]);
    stories_record_event(
        $storyId,
        'viewed',
        $userId . '|' . gmdate('Y-m-d'),
        $userId
    );
    return true;
}

function stories_moderate_remote(int $storyId, string $decision, int $userId): void
{
    stories_require_schema();
    if (!in_array($decision, ['hide', 'unhide'], true)) {
        throw new RuntimeException('Choose Hide or Unhide.');
    }
    $story = stories_find($storyId);
    if (!$story || (string)$story['direction'] !== 'remote') {
        throw new RuntimeException('The remote story was not found.');
    }
    if ($decision === 'unhide' && strtotime((string)$story['expires_at']) <= time()) {
        throw new RuntimeException('Expired stories cannot be restored.');
    }
    $status = $decision === 'hide' ? 'hidden' : 'active';
    db()->prepare(
        'UPDATE pod_stories SET status=:status,updated_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute(['status' => $status, 'id' => $storyId]);
    stories_record_event($storyId, 'moderated_' . $decision, $userId . '|' . gmdate('c'), $userId);
    log_activity('story_' . $decision, 'story', $storyId);
}

function stories_expire_due(int $limit = 100): array
{
    if (!stories_schema_available()) return [];
    $limit = max(1, min(500, $limit));
    $rows = db()->query(
        'SELECT * FROM pod_stories
         WHERE status="active" AND expires_at<=UTC_TIMESTAMP()
         ORDER BY expires_at,id LIMIT ' . $limit
    )->fetchAll();
    $processed = [];
    foreach ($rows as $story) {
        db()->prepare(
            'UPDATE pod_stories
             SET status="expired",updated_at=UTC_TIMESTAMP()
             WHERE id=:id AND status="active"'
        )->execute(['id' => (int)$story['id']]);
        stories_record_event(
            (int)$story['id'],
            'expired',
            (string)$story['expires_at'],
            (int)($story['owner_user_id'] ?? 0) ?: null,
            (int)($story['remote_actor_id'] ?? 0) ?: null
        );
        if ((string)$story['direction'] === 'local') {
            $story = stories_find((int)$story['id']) ?? $story;
            try {
                stories_publish_activity(
                    $story,
                    'Delete',
                    (int)($story['owner_user_id'] ?? 0) ?: null
                );
            } catch (Throwable $exception) {
                log_activity('story_expiry_federation_failed', 'story', (int)$story['id'], [
                    'error' => mb_substr($exception->getMessage(), 0, 500),
                ]);
            }
        }
        $processed[] = (int)$story['id'];
    }
    return $processed;
}

function stories_render_rail(int $userId, int $limit = 24): void
{
    if (!stories_schema_available() || !stories_settings()['enabled']) return;
    $stories = stories_feed($userId, $limit);
?>
<section class="stories-rail-panel" aria-labelledby="storiesRailTitle">
<header>
<div><span>Following feed</span><h2 id="storiesRailTitle">Stories</h2></div>
<a href="<?=e(app_url('portal/stories.php'))?>">Open Stories</a>
</header>
<div class="stories-rail" data-stories-rail>
<a class="story-rail-create" href="<?=e(app_url('portal/stories.php#storyComposer'))?>"><b>＋</b><span>Add story</span></a>
<?php foreach($stories as $story):
    $author = (string)($story['direction'] === 'local'
        ? ($story['owner_name'] ?: 'Your POD')
        : ($story['remote_display_name'] ?: $story['remote_username'] ?: 'Remote actor'));
    $payload = [
        'id' => (int)$story['id'],
        'title' => (string)($story['title'] ?? ''),
        'body' => (string)($story['body_text'] ?? ''),
        'author' => $author,
        'published' => (string)$story['published_at'],
        'expires' => (string)$story['expires_at'],
        'media_kind' => (string)$story['media_kind'],
        'media_url' => (string)($story['media_url'] ?? ''),
        'media_alt' => (string)($story['media_alt'] ?? ''),
        'link_url' => (string)($story['link_url'] ?? ''),
        'direction' => (string)$story['direction'],
        'load_media' => (string)$story['direction'] === 'local',
    ];
?>
<button class="story-rail-card <?=empty($story['first_viewed_at'])?'unviewed':'viewed'?>" type="button" data-story-open data-story="<?=e(json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))?>">
<span class="story-rail-ring"><i><?=e(mb_strtoupper(mb_substr($author,0,1)))?></i></span>
<strong><?=e($author)?></strong>
<small><?=e($story['title']?:'New story')?></small>
</button>
<?php endforeach;?>
<?php if(!$stories):?><div class="stories-rail-empty">Follow an ActivityPub actor to see their active stories here.</div><?php endif;?>
</div>
</section>
<?php
}

function stories_render_viewer(): void
{
?>
<dialog class="story-viewer" data-story-dialog aria-label="Story viewer">
<div class="story-viewer-progress"><i data-story-progress></i></div>
<header><div><strong data-story-author></strong><span data-story-time></span></div><button type="button" data-story-close aria-label="Close story">×</button></header>
<main>
<div class="story-viewer-media" data-story-media hidden></div>
<span class="story-viewer-type" data-story-type></span>
<h2 data-story-title></h2>
<p data-story-body></p>
<a data-story-link target="_blank" rel="noopener noreferrer nofollow" hidden>Open story link</a>
</main>
<footer><button type="button" data-story-previous aria-label="Previous story">‹</button><button type="button" data-story-next aria-label="Next story">›</button></footer>
</dialog>
<?php
}

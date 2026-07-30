<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-activitypub-service-v66F */

require_once __DIR__ . '/activitypub-http.php';
require_once __DIR__ . '/notifications.php';

function activitypub_valid_uuid(string $value): bool
{
    return (bool)preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        trim($value)
    );
}

function activitypub_uuid_from_seed(string $seed): string
{
    $hex = substr(hash('sha256', $seed), 0, 32);
    $hex[12] = '5';
    $variant = hexdec($hex[16]);
    $hex[16] = dechex(($variant & 0x3) | 0x8);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function activitypub_activity_uuid_from_uri(string $uri): string
{
    $query = [];
    parse_str((string)parse_url($uri, PHP_URL_QUERY), $query);
    $uuid = trim((string)($query['id'] ?? ''));
    return activitypub_valid_uuid($uuid) ? strtolower($uuid) : '';
}

function activitypub_store_outbox_activity(
    array $activity,
    string $activityType,
    string $objectType,
    string $objectUri,
    ?int $blogPostId,
    ?int $actorUserId
): int {
    activitypub_require_schema();
    $activityUri = trim((string)($activity['id'] ?? ''));
    $uuid = activitypub_activity_uuid_from_uri($activityUri);
    if ($uuid === '') throw new RuntimeException('The ActivityPub activity ID is invalid.');
    if (!in_array($activityType, ['Create', 'Update', 'Delete', 'Accept', 'Reject'], true)) {
        throw new RuntimeException('The ActivityPub activity type is not supported.');
    }
    $payload = json_encode(
        $activity,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    $hash = hash('sha256', $payload);
    db()->prepare(
        'INSERT IGNORE INTO activitypub_outbox_activities
            (activity_uuid,activity_uri,activity_type,object_type,object_uri,
             blog_post_id,payload_json,payload_sha256,created_by_user_id,published_at)
         VALUES
            (:activity_uuid,:activity_uri,:activity_type,:object_type,:object_uri,
             :blog_post_id,:payload_json,:payload_sha256,:created_by_user_id,UTC_TIMESTAMP())'
    )->execute([
        'activity_uuid' => $uuid,
        'activity_uri' => $activityUri,
        'activity_type' => $activityType,
        'object_type' => mb_substr($objectType, 0, 80),
        'object_uri' => $objectUri,
        'blog_post_id' => ($blogPostId ?? 0) > 0 ? $blogPostId : null,
        'payload_json' => $payload,
        'payload_sha256' => $hash,
        'created_by_user_id' => ($actorUserId ?? 0) > 0 ? $actorUserId : null,
    ]);
    $statement = db()->prepare(
        'SELECT id FROM activitypub_outbox_activities WHERE activity_uri=:activity_uri LIMIT 1'
    );
    $statement->execute(['activity_uri' => $activityUri]);
    $id = (int)($statement->fetchColumn() ?: 0);
    if ($id <= 0) throw new RuntimeException('The ActivityPub activity could not be stored.');
    return $id;
}

function activitypub_queue_delivery(int $outboxActivityId, int $remoteActorId): int
{
    if ($outboxActivityId <= 0 || $remoteActorId <= 0 || !activitypub_schema_available()) return 0;
    $statement = db()->prepare(
        'SELECT id,COALESCE(NULLIF(shared_inbox_url,""),inbox_url) AS delivery_inbox
         FROM activitypub_remote_actors
         WHERE id=:id AND status="active" LIMIT 1'
    );
    $statement->execute(['id' => $remoteActorId]);
    $actor = $statement->fetch();
    if (!$actor || !activitypub_https_url((string)$actor['delivery_inbox'])) return 0;
    db()->prepare(
        'INSERT IGNORE INTO activitypub_deliveries
            (outbox_activity_id,remote_actor_id,inbox_url,status,next_attempt_at)
         VALUES
            (:activity_id,:actor_id,:inbox_url,"pending",UTC_TIMESTAMP())'
    )->execute([
        'activity_id' => $outboxActivityId,
        'actor_id' => $remoteActorId,
        'inbox_url' => (string)$actor['delivery_inbox'],
    ]);
    return db()->lastInsertId() !== '0' ? 1 : 0;
}

function activitypub_queue_approved_followers(int $outboxActivityId): int
{
    if ($outboxActivityId <= 0 || !activitypub_schema_available()) return 0;
    $rows = db()->query(
        'SELECT follower.remote_actor_id
         FROM activitypub_followers follower
         JOIN activitypub_remote_actors actor ON actor.id=follower.remote_actor_id
         WHERE follower.status="approved" AND actor.status="active"'
    )->fetchAll();
    $queued = 0;
    foreach ($rows as $row) {
        $queued += activitypub_queue_delivery($outboxActivityId, (int)$row['remote_actor_id']);
    }
    return $queued;
}

function activitypub_blog_event(
    int $postId,
    string $eventType,
    ?int $actorUserId = null,
    ?array $postSnapshot = null
): int {
    $settings = activitypub_settings();
    if (
        !$settings['enabled']
        || !$settings['federate_blog_posts']
        || !activitypub_schema_available()
        || $postId <= 0
    ) {
        return 0;
    }
    $post = $postSnapshot ?? activitypub_blog_post($postId);
    if (!$post) return 0;
    if (!in_array($eventType, ['Create', 'Update', 'Delete'], true)) $eventType = 'Update';
    if ($eventType !== 'Delete') {
        if ((string)($post['status'] ?? '') !== 'published') return 0;
        $publishedAt = strtotime((string)($post['published_at'] ?? '')) ?: 0;
        if ($publishedAt > time()) return 0;
    }
    $objectUri = activitypub_object_url($postId);
    $version = (string)($post['updated_at'] ?? $post['published_at'] ?? gmdate('Y-m-d H:i:s'));
    if ($eventType === 'Delete') $version .= '|' . gmdate('Y-m-d H:i:s');
    $uuid = activitypub_uuid_from_seed(
        'activitypub-v66f|' . $eventType . '|' . $postId . '|' . $version
    );
    $activityUri = activitypub_activity_url($uuid);
    $published = syndication_iso_date(
        $eventType === 'Create'
            ? (string)($post['published_at'] ?? '')
            : (string)($post['updated_at'] ?? '')
    ) ?? gmdate(DATE_ATOM);
    $object = $eventType === 'Delete'
        ? [
            'id' => $objectUri,
            'type' => 'Tombstone',
            'formerType' => 'Article',
            'deleted' => gmdate(DATE_ATOM),
          ]
        : activitypub_article_object($post);
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $activityUri,
        'type' => $eventType,
        'actor' => activitypub_actor_url(),
        'published' => $published,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [activitypub_followers_url()],
        'object' => $object,
    ];
    $outboxId = activitypub_store_outbox_activity(
        $activity,
        $eventType,
        $eventType === 'Delete' ? 'Tombstone' : 'Article',
        $objectUri,
        $postId,
        $actorUserId
    );
    activitypub_queue_approved_followers($outboxId);
    log_activity('activitypub_blog_' . strtolower($eventType), 'blog_post', $postId, [
        'outbox_activity_id' => $outboxId,
    ]);
    return $outboxId;
}

function activitypub_follow_activity_payload(string $activityId): array
{
    $statement = db()->prepare(
        'SELECT payload_json FROM activitypub_inbox_activities
         WHERE activity_id=:activity_id LIMIT 1'
    );
    $statement->execute(['activity_id' => $activityId]);
    $payload = json_decode((string)($statement->fetchColumn() ?: ''), true);
    return is_array($payload) ? $payload : [];
}

function activitypub_response_activity(
    string $type,
    array $followActivity,
    array $remoteActor,
    ?int $actorUserId
): int {
    if (!in_array($type, ['Accept', 'Reject'], true)) {
        throw new RuntimeException('The follower response is invalid.');
    }
    $uuid = pod_uuid_v4();
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url($uuid),
        'type' => $type,
        'actor' => activitypub_actor_url(),
        'to' => [(string)$remoteActor['actor_uri']],
        'object' => $followActivity,
    ];
    $outboxId = activitypub_store_outbox_activity(
        $activity,
        $type,
        'Follow',
        trim((string)($followActivity['id'] ?? $remoteActor['actor_uri'])),
        null,
        $actorUserId
    );
    activitypub_queue_delivery($outboxId, (int)$remoteActor['id']);
    return $outboxId;
}

function activitypub_moderate_follower(
    int $followerId,
    string $decision,
    int $actorUserId
): void {
    activitypub_require_schema();
    if (!in_array($decision, ['approved', 'rejected', 'removed'], true)) {
        throw new RuntimeException('Choose Approve, Reject, or Remove.');
    }
    $statement = db()->prepare(
        'SELECT follower.*,actor.actor_uri,actor.id AS actor_id,actor.display_name,
                actor.preferred_username,actor.inbox_url,actor.shared_inbox_url
         FROM activitypub_followers follower
         JOIN activitypub_remote_actors actor ON actor.id=follower.remote_actor_id
         WHERE follower.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $followerId]);
    $follower = $statement->fetch();
    if (!$follower) throw new RuntimeException('The ActivityPub follower was not found.');
    $oldStatus = (string)$follower['status'];
    db()->prepare(
        'UPDATE activitypub_followers
         SET status=:status,moderated_by_user_id=:user_id,
             moderated_at=UTC_TIMESTAMP(),
             followed_at=CASE WHEN :approved="approved" THEN COALESCE(followed_at,UTC_TIMESTAMP()) ELSE followed_at END
         WHERE id=:id'
    )->execute([
        'status' => $decision,
        'user_id' => $actorUserId > 0 ? $actorUserId : null,
        'approved' => $decision,
        'id' => $followerId,
    ]);
    if ($decision !== 'removed' && $oldStatus !== $decision) {
        $follow = activitypub_follow_activity_payload((string)$follower['follow_activity_id']);
        if ($follow) {
            activitypub_response_activity(
                $decision === 'approved' ? 'Accept' : 'Reject',
                $follow,
                [
                    'id' => (int)$follower['actor_id'],
                    'actor_uri' => (string)$follower['actor_uri'],
                ],
                $actorUserId
            );
        }
    }
    log_activity('activitypub_follower_' . $decision, 'activitypub_follower', $followerId, [
        'actor_uri' => (string)$follower['actor_uri'],
        'previous_status' => $oldStatus,
    ]);
}

function activitypub_update_inbox_status(int $id, string $status, ?string $error = null): void
{
    if (!in_array($status, ['pending', 'accepted', 'ignored', 'rejected', 'error'], true)) {
        $status = 'error';
    }
    db()->prepare(
        'UPDATE activitypub_inbox_activities
         SET status=:status,error_message=:error_message,processed_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'error_message' => $error !== null ? mb_substr($error, 0, 1000) : null,
        'id' => $id,
    ]);
}

function activitypub_receive_inbox(
    string $rawBody,
    array $headers,
    string $method,
    string $requestTarget
): int {
    activitypub_require_schema();
    $settings = activitypub_settings();
    if (!$settings['enabled']) throw new RuntimeException('ActivityPub federation is disabled.');
    if (strtoupper($method) !== 'POST') throw new RuntimeException('ActivityPub inbox delivery requires POST.');
    if ($rawBody === '' || strlen($rawBody) > 1024 * 1024) {
        throw new RuntimeException('The ActivityPub activity body is empty or exceeds 1 MB.');
    }
    try {
        $payload = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('The ActivityPub activity body is invalid JSON.');
    }
    if (!is_array($payload)) throw new RuntimeException('The ActivityPub activity must be a JSON object.');
    $activityId = trim((string)($payload['id'] ?? ''));
    $activityType = trim((string)($payload['type'] ?? ''));
    $actorUri = activitypub_payload_actor($payload);
    if (!activitypub_https_url($activityId) || $activityType === '' || !activitypub_https_url($actorUri)) {
        throw new RuntimeException('The ActivityPub activity ID, type, or actor is invalid.');
    }
    $remote = activitypub_verify_inbound_request(
        $rawBody,
        $payload,
        $headers,
        $method,
        $requestTarget
    );
    if (activitypub_normalize_url((string)$remote['actor_uri']) !== activitypub_normalize_url($actorUri)) {
        throw new RuntimeException('The ActivityPub activity actor does not match the verified signer.');
    }
    $object = $payload['object'] ?? null;
    $objectUri = is_string($object)
        ? trim($object)
        : (is_array($object) ? trim((string)($object['id'] ?? '')) : '');
    $digest = hash('sha256', $rawBody);
    try {
        db()->prepare(
            'INSERT INTO activitypub_inbox_activities
                (activity_id,actor_uri,activity_type,object_uri,request_digest,
                 signature_key_id,status,payload_json)
             VALUES
                (:activity_id,:actor_uri,:activity_type,:object_uri,:request_digest,
                 :signature_key_id,"pending",:payload_json)'
        )->execute([
            'activity_id' => $activityId,
            'actor_uri' => $actorUri,
            'activity_type' => mb_substr($activityType, 0, 80),
            'object_uri' => $objectUri !== '' ? $objectUri : null,
            'request_digest' => $digest,
            'signature_key_id' => (string)$remote['public_key_id'],
            'payload_json' => $rawBody,
        ]);
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() !== '23000') throw $exception;
        $existing = db()->prepare(
            'SELECT id FROM activitypub_inbox_activities
             WHERE activity_id=:activity_id OR request_digest=:request_digest
             ORDER BY id LIMIT 1'
        );
        $existing->execute(['activity_id' => $activityId, 'request_digest' => $digest]);
        return (int)($existing->fetchColumn() ?: 0);
    }
    $inboxId = (int)db()->lastInsertId();
    try {
        if ($activityType === 'Follow') {
            if (activitypub_normalize_url($objectUri) !== activitypub_normalize_url(activitypub_actor_url())) {
                throw new RuntimeException('The Follow activity does not target this POD actor.');
            }
            $status = $settings['manual_follow_approval'] ? 'pending' : 'approved';
            db()->prepare(
                'INSERT INTO activitypub_followers
                    (remote_actor_id,follow_activity_id,status,followed_at)
                 VALUES
                    (:actor_id,:follow_activity_id,:status,
                     CASE WHEN :approved="approved" THEN UTC_TIMESTAMP() ELSE NULL END)
                 ON DUPLICATE KEY UPDATE
                    follow_activity_id=VALUES(follow_activity_id),status=VALUES(status),
                    moderated_by_user_id=NULL,moderated_at=NULL,
                    followed_at=CASE WHEN VALUES(status)="approved"
                        THEN COALESCE(followed_at,UTC_TIMESTAMP()) ELSE followed_at END'
            )->execute([
                'actor_id' => (int)$remote['id'],
                'follow_activity_id' => $activityId,
                'status' => $status,
                'approved' => $status,
            ]);
            $followStatement = db()->prepare(
                'SELECT id FROM activitypub_followers WHERE remote_actor_id=:actor_id LIMIT 1'
            );
            $followStatement->execute(['actor_id' => (int)$remote['id']]);
            $followerId = (int)($followStatement->fetchColumn() ?: 0);
            if ($status === 'approved' && $followerId > 0) {
                activitypub_response_activity('Accept', $payload, $remote, null);
            } else {
                notification_create_for_role(
                    'admin',
                    'system',
                    'New federated follower request',
                    trim((string)($remote['display_name'] ?: $remote['preferred_username'] ?: $remote['actor_uri'])),
                    'portal/admin.php?view=federation',
                    'activitypub_follower',
                    $followerId,
                    'normal'
                );
            }
            activitypub_update_inbox_status($inboxId, 'accepted');
        } elseif ($activityType === 'Undo') {
            $undo = is_array($object) ? $object : [];
            $undoType = trim((string)($undo['type'] ?? ''));
            $undoActor = activitypub_payload_actor($undo);
            if ($undoType === 'Follow' && activitypub_normalize_url($undoActor) === activitypub_normalize_url($actorUri)) {
                db()->prepare(
                    'UPDATE activitypub_followers
                     SET status="removed",moderated_at=UTC_TIMESTAMP()
                     WHERE remote_actor_id=:actor_id'
                )->execute(['actor_id' => (int)$remote['id']]);
                activitypub_update_inbox_status($inboxId, 'accepted');
            } else {
                activitypub_update_inbox_status($inboxId, 'ignored');
            }
        } elseif ($activityType === 'Delete') {
            if (activitypub_normalize_url($objectUri) !== activitypub_normalize_url($actorUri)) {
                throw new RuntimeException('The Delete activity may only delete the verified remote actor.');
            }
            db()->prepare(
                'UPDATE activitypub_remote_actors SET status="deleted" WHERE id=:id'
            )->execute(['id' => (int)$remote['id']]);
            db()->prepare(
                'UPDATE activitypub_followers SET status="removed",moderated_at=UTC_TIMESTAMP()
                 WHERE remote_actor_id=:actor_id'
            )->execute(['actor_id' => (int)$remote['id']]);
            activitypub_update_inbox_status($inboxId, 'accepted');
        } elseif (in_array($activityType, ['Like', 'Announce', 'Create', 'Update', 'Accept', 'Reject'], true)) {
            activitypub_update_inbox_status($inboxId, 'accepted');
        } else {
            activitypub_update_inbox_status($inboxId, 'ignored');
        }
    } catch (Throwable $exception) {
        activitypub_update_inbox_status($inboxId, 'error', $exception->getMessage());
        throw $exception;
    }
    log_activity('activitypub_inbox_received', 'activitypub_inbox', $inboxId, [
        'type' => $activityType,
        'actor_uri' => $actorUri,
    ]);
    return $inboxId;
}

function activitypub_process_delivery_queue(int $limit = 10): array
{
    if (!activitypub_schema_available()) return [];
    $limit = max(1, min(50, $limit));
    $processed = [];
    for ($index = 0; $index < $limit; $index++) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->query(
                'SELECT delivery.*,activity.payload_json,actor.actor_uri
                 FROM activitypub_deliveries delivery
                 JOIN activitypub_outbox_activities activity
                   ON activity.id=delivery.outbox_activity_id
                 JOIN activitypub_remote_actors actor
                   ON actor.id=delivery.remote_actor_id
                 WHERE delivery.status IN ("pending","failed")
                   AND (delivery.next_attempt_at IS NULL OR delivery.next_attempt_at<=UTC_TIMESTAMP())
                   AND delivery.attempt_count<6
                   AND actor.status="active"
                 ORDER BY delivery.created_at,delivery.id
                 LIMIT 1 FOR UPDATE'
            );
            $delivery = $statement->fetch();
            if (!$delivery) {
                $pdo->commit();
                break;
            }
            $pdo->prepare(
                'UPDATE activitypub_deliveries
                 SET status="delivering",attempt_count=attempt_count+1,last_error=NULL
                 WHERE id=:id'
            )->execute(['id' => (int)$delivery['id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        $delivery['attempt_count'] = (int)$delivery['attempt_count'] + 1;
        $payload = json_decode((string)$delivery['payload_json'], true);
        $result = is_array($payload)
            ? activitypub_deliver_json((string)$delivery['inbox_url'], $payload)
            : ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'The stored ActivityPub payload is invalid.'];
        if ($result['ok']) {
            db()->prepare(
                'UPDATE activitypub_deliveries
                 SET status="delivered",response_code=:response_code,
                     response_excerpt=:response_excerpt,last_error=NULL,
                     next_attempt_at=NULL,delivered_at=UTC_TIMESTAMP()
                 WHERE id=:id'
            )->execute([
                'response_code' => $result['status'],
                'response_excerpt' => $result['body'] !== '' ? $result['body'] : null,
                'id' => (int)$delivery['id'],
            ]);
        } else {
            $delayMinutes = min(1440, 5 * (2 ** max(0, (int)$delivery['attempt_count'] - 1)));
            db()->prepare(
                'UPDATE activitypub_deliveries
                 SET status="failed",response_code=:response_code,
                     response_excerpt=:response_excerpt,last_error=:last_error,
                     next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ' . $delayMinutes . ' MINUTE)
                 WHERE id=:id'
            )->execute([
                'response_code' => $result['status'] > 0 ? $result['status'] : null,
                'response_excerpt' => $result['body'] !== '' ? $result['body'] : null,
                'last_error' => mb_substr((string)$result['error'], 0, 1000),
                'id' => (int)$delivery['id'],
            ]);
            if (in_array((int)$result['status'], [404, 410], true)) {
                db()->prepare(
                    'UPDATE activitypub_remote_actors SET status="unavailable",last_error=:error WHERE id=:id'
                )->execute([
                    'error' => mb_substr((string)$result['error'], 0, 1000),
                    'id' => (int)$delivery['remote_actor_id'],
                ]);
            }
        }
        $processed[] = ['id' => (int)$delivery['id']] + $result;
    }
    return $processed;
}

function activitypub_retry_delivery(int $deliveryId): void
{
    if ($deliveryId <= 0 || !activitypub_schema_available()) return;
    db()->prepare(
        'UPDATE activitypub_deliveries
         SET status="pending",attempt_count=0,next_attempt_at=UTC_TIMESTAMP(),
             response_code=NULL,response_excerpt=NULL,last_error=NULL,delivered_at=NULL
         WHERE id=:id'
    )->execute(['id' => $deliveryId]);
}

function activitypub_followers(bool $all = true, int $limit = 100): array
{
    if (!activitypub_schema_available()) return [];
    $limit = max(1, min(500, $limit));
    $where = $all ? '' : 'WHERE follower.status="approved"';
    return db()->query(
        'SELECT follower.*,actor.actor_uri,actor.preferred_username,actor.display_name,
                actor.profile_url,actor.avatar_url,actor.status AS actor_status,
                moderator.display_name AS moderator_name
         FROM activitypub_followers follower
         JOIN activitypub_remote_actors actor ON actor.id=follower.remote_actor_id
         LEFT JOIN users moderator ON moderator.id=follower.moderated_by_user_id
         ' . $where . '
         ORDER BY FIELD(follower.status,"pending","approved","rejected","removed"),
                  follower.created_at DESC,follower.id DESC
         LIMIT ' . $limit
    )->fetchAll();
}

function activitypub_recent_inbox(int $limit = 50): array
{
    if (!activitypub_schema_available()) return [];
    $limit = max(1, min(200, $limit));
    return db()->query(
        'SELECT * FROM activitypub_inbox_activities
         ORDER BY received_at DESC,id DESC LIMIT ' . $limit
    )->fetchAll();
}

function activitypub_recent_deliveries(int $limit = 50): array
{
    if (!activitypub_schema_available()) return [];
    $limit = max(1, min(200, $limit));
    return db()->query(
        'SELECT delivery.*,activity.activity_type,activity.activity_uri,
                actor.actor_uri,actor.display_name,actor.preferred_username
         FROM activitypub_deliveries delivery
         JOIN activitypub_outbox_activities activity ON activity.id=delivery.outbox_activity_id
         JOIN activitypub_remote_actors actor ON actor.id=delivery.remote_actor_id
         ORDER BY delivery.created_at DESC,delivery.id DESC LIMIT ' . $limit
    )->fetchAll();
}

function activitypub_outbox_document(): array
{
    activitypub_require_schema();
    $count = (int)db()->query(
        'SELECT COUNT(*) FROM activitypub_outbox_activities
         WHERE activity_type IN ("Create","Update","Delete")'
    )->fetchColumn();
    $rows = db()->query(
        'SELECT payload_json FROM activitypub_outbox_activities
         WHERE activity_type IN ("Create","Update","Delete")
         ORDER BY published_at DESC,id DESC LIMIT 50'
    )->fetchAll();
    $items = [];
    foreach ($rows as $row) {
        $payload = json_decode((string)$row['payload_json'], true);
        if (is_array($payload)) $items[] = $payload;
    }
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_outbox_url(),
        'type' => 'OrderedCollection',
        'totalItems' => $count,
        'orderedItems' => $items,
    ];
}

function activitypub_followers_document(): array
{
    activitypub_require_schema();
    $settings = activitypub_settings();
    $followers = activitypub_followers(false, 500);
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_followers_url(),
        'type' => 'OrderedCollection',
        'totalItems' => count($followers),
        'orderedItems' => $settings['show_followers']
            ? array_values(array_map(static fn(array $row): string => (string)$row['actor_uri'], $followers))
            : [],
    ];
}

function activitypub_following_document(): array
{
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_following_url(),
        'type' => 'OrderedCollection',
        'totalItems' => 0,
        'orderedItems' => [],
    ];
}

function activitypub_activity_document(string $uuid): ?array
{
    if (!activitypub_schema_available() || !activitypub_valid_uuid($uuid)) return null;
    $statement = db()->prepare(
        'SELECT payload_json FROM activitypub_outbox_activities
         WHERE activity_uuid=:uuid LIMIT 1'
    );
    $statement->execute(['uuid' => strtolower($uuid)]);
    $payload = json_decode((string)($statement->fetchColumn() ?: ''), true);
    return is_array($payload) ? $payload : null;
}

function activitypub_object_document(int $postId): ?array
{
    if ($postId <= 0 || !activitypub_schema_available()) return null;
    $post = activitypub_blog_post($postId);
    if (
        $post
        && (string)$post['status'] === 'published'
        && (strtotime((string)$post['published_at']) ?: 0) <= time()
    ) {
        return activitypub_article_object($post);
    }
    $statement = db()->prepare(
        'SELECT payload_json FROM activitypub_outbox_activities
         WHERE object_uri=:object_uri AND activity_type="Delete"
         ORDER BY published_at DESC,id DESC LIMIT 1'
    );
    $statement->execute(['object_uri' => activitypub_object_url($postId)]);
    $activity = json_decode((string)($statement->fetchColumn() ?: ''), true);
    return is_array($activity) && is_array($activity['object'] ?? null)
        ? $activity['object']
        : null;
}

function activitypub_backfill_published_posts(?int $actorUserId = null, int $limit = 100): int
{
    if (!activitypub_schema_available() || !activitypub_settings()['enabled']) return 0;
    $limit = max(1, min(500, $limit));
    $rows = db()->query(
        'SELECT id FROM blog_posts
         WHERE status="published"
           AND (published_at IS NULL OR published_at<=UTC_TIMESTAMP())
         ORDER BY COALESCE(published_at,created_at),id LIMIT ' . $limit
    )->fetchAll();
    $count = 0;
    foreach ($rows as $row) {
        if (activitypub_blog_event((int)$row['id'], 'Create', $actorUserId) > 0) $count++;
    }
    return $count;
}

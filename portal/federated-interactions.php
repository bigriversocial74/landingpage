<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-federated-interactions-v66G */

function federated_interactions_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "activitypub_remote_comments","activitypub_remote_reactions",
                    "activitypub_following","activitypub_actor_controls",
                    "activitypub_domain_blocks","activitypub_local_objects"
               )'
        );
        return $available = (int)$statement->fetchColumn() === 6;
    } catch (Throwable) {
        return $available = false;
    }
}

function federated_interactions_require_schema(): void
{
    if (!federated_interactions_schema_available()) {
        throw new RuntimeException('Import database/federated_interactions_v66g.sql before using federated interactions.');
    }
}

function federated_interactions_settings(): array
{
    return [
        'federate_comments' => activitypub_setting('activitypub_federate_comments', '1') !== '0',
        'federate_reactions' => activitypub_setting('activitypub_federate_reactions', '1') !== '0',
        'allow_remote_replies' => activitypub_setting('activitypub_allow_remote_replies', '1') !== '0',
        'allow_remote_reactions' => activitypub_setting('activitypub_allow_remote_reactions', '1') !== '0',
        'remote_reply_moderation' => activitypub_setting('activitypub_remote_reply_moderation', 'pre_moderated'),
        'show_following' => activitypub_setting('activitypub_show_following', '1') !== '0',
    ];
}

function federated_interactions_normalize_domain(string $domain): string
{
    $domain = strtolower(trim($domain));
    $domain = trim($domain, ". \t\n\r\0\x0B");
    if ($domain === '' || strlen($domain) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domain)) {
        throw new RuntimeException('Enter a valid federation domain.');
    }
    return $domain;
}

function federated_interactions_actor_domain(string $actorUri): string
{
    return strtolower((string)parse_url($actorUri, PHP_URL_HOST));
}

function federated_interactions_domain_blocked(string $host): bool
{
    if (!federated_interactions_schema_available()) return false;
    $host = strtolower(trim($host));
    if ($host === '') return true;
    $rows = db()->query('SELECT domain_name FROM activitypub_domain_blocks')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $domain) {
        $domain = strtolower((string)$domain);
        if ($host === $domain || str_ends_with($host, '.' . $domain)) return true;
    }
    return false;
}

function federated_interactions_actor_control(int $remoteActorId): array
{
    if (!federated_interactions_schema_available() || $remoteActorId <= 0) {
        return ['moderation_status' => 'active', 'moderation_note' => null];
    }
    $statement = db()->prepare('SELECT * FROM activitypub_actor_controls WHERE remote_actor_id=:id LIMIT 1');
    $statement->execute(['id' => $remoteActorId]);
    return $statement->fetch() ?: ['moderation_status' => 'active', 'moderation_note' => null];
}

function federated_interactions_actor_allowed(array $remoteActor): bool
{
    if (($remoteActor['status'] ?? 'active') !== 'active') return false;
    if (federated_interactions_domain_blocked(federated_interactions_actor_domain((string)$remoteActor['actor_uri']))) return false;
    return (string)(federated_interactions_actor_control((int)$remoteActor['id'])['moderation_status'] ?? 'active') !== 'blocked';
}

function federated_interactions_actor_muted(array $remoteActor): bool
{
    return (string)(federated_interactions_actor_control((int)$remoteActor['id'])['moderation_status'] ?? 'active') === 'muted';
}

function federated_interactions_clean_remote_text(string $html): string
{
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_substr($text, 0, 4000);
}

function federated_interactions_iso_to_sql(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
}

function federated_interactions_payload_actor_matches(array $object, string $actorUri): bool
{
    $attributed = $object['attributedTo'] ?? $object['actor'] ?? null;
    if (is_string($attributed)) {
        return activitypub_normalize_url($attributed) === activitypub_normalize_url($actorUri);
    }
    if (is_array($attributed)) {
        foreach ($attributed as $value) {
            $candidate = is_array($value) ? (string)($value['id'] ?? '') : (string)$value;
            if ($candidate !== '' && activitypub_normalize_url($candidate) === activitypub_normalize_url($actorUri)) return true;
        }
    }
    return false;
}

function federated_interactions_post_from_uri(string $uri): ?array
{
    if ($uri === '' || !activitypub_https_url($uri)) return null;
    $parts = parse_url($uri);
    if (!is_array($parts) || strtolower((string)($parts['host'] ?? '')) !== activitypub_host()) return null;
    $path = basename((string)($parts['path'] ?? ''));
    parse_str((string)($parts['query'] ?? ''), $query);
    if ($path === 'activitypub-object.php') {
        $id = max(0, (int)($query['id'] ?? 0));
        return $id > 0 ? content_interactions_blog_post($id, true) : null;
    }
    if ($path === 'blog-post.php') {
        $slug = trim((string)($query['slug'] ?? ''));
        if ($slug === '') return null;
        $statement = db()->prepare(
            'SELECT id,title,slug,status,author_user_id,published_at FROM blog_posts
             WHERE slug=:slug AND status="published"
               AND (published_at IS NULL OR published_at<=UTC_TIMESTAMP()) LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        return $statement->fetch() ?: null;
    }
    return null;
}

function federated_interactions_local_comment_id_from_uri(string $uri): int
{
    if ($uri === '' || !activitypub_https_url($uri)) return 0;
    $parts = parse_url($uri);
    if (!is_array($parts) || strtolower((string)($parts['host'] ?? '')) !== activitypub_host()) return 0;
    if (basename((string)($parts['path'] ?? '')) !== 'activitypub-comment.php') return 0;
    parse_str((string)($parts['query'] ?? ''), $query);
    $id = max(0, (int)($query['id'] ?? 0));
    $comment = $id > 0 ? content_interactions_comment($id) : null;
    return $comment && ($comment['status'] ?? '') === 'approved' ? $id : 0;
}

function federated_interactions_remote_comment_from_uri(string $uri): ?array
{
    if (!federated_interactions_schema_available() || $uri === '') return null;
    $statement = db()->prepare('SELECT * FROM activitypub_remote_comments WHERE object_uri=:uri LIMIT 1');
    $statement->execute(['uri' => $uri]);
    return $statement->fetch() ?: null;
}

function federated_interactions_resolve_target(string $uri): ?array
{
    $post = federated_interactions_post_from_uri($uri);
    if ($post) return ['blog_post_id' => (int)$post['id'], 'local_comment_id' => null, 'remote_comment_id' => null];

    $localCommentId = federated_interactions_local_comment_id_from_uri($uri);
    if ($localCommentId > 0) {
        $comment = content_interactions_comment($localCommentId);
        return $comment ? [
            'blog_post_id' => (int)$comment['content_id'],
            'local_comment_id' => $localCommentId,
            'remote_comment_id' => null,
        ] : null;
    }

    $remoteComment = federated_interactions_remote_comment_from_uri($uri);
    if ($remoteComment && ($remoteComment['status'] ?? '') === 'approved') {
        return [
            'blog_post_id' => (int)$remoteComment['blog_post_id'],
            'local_comment_id' => null,
            'remote_comment_id' => (int)$remoteComment['id'],
        ];
    }
    return null;
}

function federated_interactions_notify_admin(string $title, string $body, string $entityType, int $entityId): void
{
    notification_create_for_role(
        'admin',
        'message',
        $title,
        mb_substr($body, 0, 240),
        'portal/admin.php?view=federation#interactions',
        $entityType,
        $entityId,
        'normal'
    );
}

function federated_interactions_ingest_comment(int $inboxId, array $payload, array $remoteActor): bool
{
    $object = $payload['object'] ?? null;
    if (!is_array($object) || !in_array((string)($object['type'] ?? ''), ['Note', 'Article'], true)) return false;
    $objectUri = trim((string)($object['id'] ?? ''));
    $inReplyTo = $object['inReplyTo'] ?? '';
    if (is_array($inReplyTo)) $inReplyTo = (string)($inReplyTo['id'] ?? '');
    $inReplyTo = trim((string)$inReplyTo);
    if (!activitypub_https_url($objectUri) || !activitypub_https_url($inReplyTo)) return false;
    if (!federated_interactions_payload_actor_matches($object, (string)$remoteActor['actor_uri'])) {
        throw new RuntimeException('The federated reply attribution does not match the verified actor.');
    }
    $target = federated_interactions_resolve_target($inReplyTo);
    if (!$target) return false;
    $body = federated_interactions_clean_remote_text((string)($object['content'] ?? $object['summary'] ?? $object['name'] ?? ''));
    if ($body === '') throw new RuntimeException('The federated reply does not contain readable text.');
    $activityUri = trim((string)($payload['id'] ?? ''));
    $sourceUrl = trim((string)($object['url'] ?? $objectUri));
    if (!activitypub_https_url($sourceUrl)) $sourceUrl = $objectUri;
    $existing = federated_interactions_remote_comment_from_uri($objectUri);
    if ($existing && (int)$existing['remote_actor_id'] !== (int)$remoteActor['id']) {
        throw new RuntimeException('A federated reply object cannot change ownership.');
    }
    $status = $existing && in_array((string)$existing['status'], ['hidden', 'spam', 'deleted'], true)
        ? (string)$existing['status'] : 'pending';
    if ($existing) {
        db()->prepare(
            'UPDATE activitypub_remote_comments
             SET inbox_activity_id=:inbox_id,source_activity_uri=:activity_uri,
                 in_reply_to_uri=:in_reply_to_uri,source_url=:source_url,
                 body_text=:body_text,body_hash=:body_hash,status=:status,
                 source_updated_at=:source_updated_at,updated_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute([
            'inbox_id' => $inboxId,
            'activity_uri' => $activityUri,
            'in_reply_to_uri' => $inReplyTo,
            'source_url' => $sourceUrl,
            'body_text' => $body,
            'body_hash' => hash('sha256', mb_strtolower($body)),
            'status' => $status,
            'source_updated_at' => federated_interactions_iso_to_sql($object['updated'] ?? $payload['published'] ?? null),
            'id' => (int)$existing['id'],
        ]);
        $commentId = (int)$existing['id'];
    } else {
        db()->prepare(
            'INSERT INTO activitypub_remote_comments
                (inbox_activity_id,remote_actor_id,blog_post_id,parent_remote_comment_id,
                 parent_local_comment_id,object_uri,source_activity_uri,in_reply_to_uri,
                 source_url,body_text,body_hash,status,source_published_at,source_updated_at)
             VALUES
                (:inbox_id,:actor_id,:post_id,:parent_remote_id,:parent_local_id,
                 :object_uri,:activity_uri,:in_reply_to_uri,:source_url,:body_text,
                 :body_hash,"pending",:published_at,:updated_at)'
        )->execute([
            'inbox_id' => $inboxId,
            'actor_id' => (int)$remoteActor['id'],
            'post_id' => (int)$target['blog_post_id'],
            'parent_remote_id' => $target['remote_comment_id'],
            'parent_local_id' => $target['local_comment_id'],
            'object_uri' => $objectUri,
            'activity_uri' => $activityUri,
            'in_reply_to_uri' => $inReplyTo,
            'source_url' => $sourceUrl,
            'body_text' => $body,
            'body_hash' => hash('sha256', mb_strtolower($body)),
            'published_at' => federated_interactions_iso_to_sql($object['published'] ?? $payload['published'] ?? null),
            'updated_at' => federated_interactions_iso_to_sql($object['updated'] ?? null),
        ]);
        $commentId = (int)db()->lastInsertId();
    }
    if (!federated_interactions_actor_muted($remoteActor)) {
        federated_interactions_notify_admin(
            $existing ? 'Federated reply edited and awaiting review' : 'Federated reply awaiting moderation',
            $body,
            'federated_comment',
            $commentId
        );
    }
    log_activity('federated_comment_received', 'federated_comment', $commentId, [
        'remote_actor_id' => (int)$remoteActor['id'],
        'blog_post_id' => (int)$target['blog_post_id'],
        'updated' => (bool)$existing,
    ]);
    return true;
}

function federated_interactions_ingest_reaction(int $inboxId, array $payload, array $remoteActor): bool
{
    $type = (string)($payload['type'] ?? '');
    if (!in_array($type, ['Like', 'Announce'], true)) return false;
    $object = $payload['object'] ?? '';
    $objectUri = is_array($object) ? trim((string)($object['id'] ?? '')) : trim((string)$object);
    if (!activitypub_https_url($objectUri)) return false;
    $target = federated_interactions_resolve_target($objectUri);
    if (!$target) return false;
    $activityUri = trim((string)($payload['id'] ?? ''));
    db()->prepare(
        'INSERT INTO activitypub_remote_reactions
            (inbox_activity_id,remote_actor_id,blog_post_id,local_comment_id,
             remote_comment_id,activity_uri,object_uri,reaction_type,status)
         VALUES
            (:inbox_id,:actor_id,:post_id,:local_comment_id,:remote_comment_id,
             :activity_uri,:object_uri,:reaction_type,"active")
         ON DUPLICATE KEY UPDATE inbox_activity_id=VALUES(inbox_activity_id),
             status="active",updated_at=UTC_TIMESTAMP()'
    )->execute([
        'inbox_id' => $inboxId,
        'actor_id' => (int)$remoteActor['id'],
        'post_id' => (int)$target['blog_post_id'],
        'local_comment_id' => $target['local_comment_id'],
        'remote_comment_id' => $target['remote_comment_id'],
        'activity_uri' => $activityUri,
        'object_uri' => $objectUri,
        'reaction_type' => strtolower($type),
    ]);
    $statement = db()->prepare('SELECT id FROM activitypub_remote_reactions WHERE activity_uri=:uri LIMIT 1');
    $statement->execute(['uri' => $activityUri]);
    $reactionId = (int)($statement->fetchColumn() ?: 0);
    if (!federated_interactions_actor_muted($remoteActor)) {
        federated_interactions_notify_admin(
            $type === 'Like' ? 'Federated like received' : 'Federated boost received',
            (string)($remoteActor['display_name'] ?: $remoteActor['preferred_username'] ?: $remoteActor['actor_uri']),
            'federated_reaction',
            $reactionId
        );
    }
    return true;
}

function federated_interactions_undo_reaction(array $payload, array $remoteActor): bool
{
    $object = $payload['object'] ?? null;
    $activityUri = is_array($object) ? trim((string)($object['id'] ?? '')) : trim((string)$object);
    if ($activityUri === '') return false;
    $statement = db()->prepare(
        'UPDATE activitypub_remote_reactions SET status="undone",updated_at=UTC_TIMESTAMP()
         WHERE remote_actor_id=:actor_id AND activity_uri=:activity_uri AND status="active"'
    );
    $statement->execute(['actor_id' => (int)$remoteActor['id'], 'activity_uri' => $activityUri]);
    return $statement->rowCount() > 0;
}

function federated_interactions_delete_remote_object(string $objectUri, array $remoteActor): bool
{
    if ($objectUri === '') return false;
    $comment = db()->prepare(
        'UPDATE activitypub_remote_comments SET status="deleted",deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()
         WHERE remote_actor_id=:actor_id AND object_uri=:object_uri AND status<>"deleted"'
    );
    $comment->execute(['actor_id' => (int)$remoteActor['id'], 'object_uri' => $objectUri]);
    $reaction = db()->prepare(
        'UPDATE activitypub_remote_reactions SET status="deleted",updated_at=UTC_TIMESTAMP()
         WHERE remote_actor_id=:actor_id AND (activity_uri=:object_uri OR object_uri=:object_uri) AND status<>"deleted"'
    );
    $reaction->execute(['actor_id' => (int)$remoteActor['id'], 'object_uri' => $objectUri]);
    return $comment->rowCount() > 0 || $reaction->rowCount() > 0;
}

function federated_interactions_process_follow_response(array $payload): bool
{
    $type = (string)($payload['type'] ?? '');
    if (!in_array($type, ['Accept', 'Reject'], true) || !federated_interactions_schema_available()) return false;
    $object = $payload['object'] ?? null;
    $followUri = is_array($object) ? trim((string)($object['id'] ?? '')) : trim((string)$object);
    if ($followUri === '') return false;
    $status = $type === 'Accept' ? 'accepted' : 'rejected';
    $statement = db()->prepare(
        'UPDATE activitypub_following
         SET status=:status,accepted_at=CASE WHEN :accepted="accepted" THEN UTC_TIMESTAMP() ELSE accepted_at END,
             updated_at=UTC_TIMESTAMP()
         WHERE follow_activity_uri=:follow_uri AND status="pending"'
    );
    $statement->execute(['status' => $status, 'accepted' => $status, 'follow_uri' => $followUri]);
    return $statement->rowCount() > 0;
}

function federated_interactions_process_inbound(int $inboxId, array $payload, array $remoteActor): bool
{
    if (!federated_interactions_schema_available()) return false;
    if (!federated_interactions_actor_allowed($remoteActor)) return true;
    $settings = federated_interactions_settings();
    $type = trim((string)($payload['type'] ?? ''));
    if (in_array($type, ['Create', 'Update'], true) && $settings['allow_remote_replies']) {
        return federated_interactions_ingest_comment($inboxId, $payload, $remoteActor);
    }
    if (in_array($type, ['Like', 'Announce'], true) && $settings['allow_remote_reactions']) {
        return federated_interactions_ingest_reaction($inboxId, $payload, $remoteActor);
    }
    if ($type === 'Undo' && federated_interactions_undo_reaction($payload, $remoteActor)) return true;
    if ($type === 'Delete') {
        $object = $payload['object'] ?? '';
        $objectUri = is_array($object) ? trim((string)($object['id'] ?? '')) : trim((string)$object);
        if (activitypub_normalize_url($objectUri) === activitypub_normalize_url((string)$remoteActor['actor_uri'])) return false;
        return federated_interactions_delete_remote_object($objectUri, $remoteActor);
    }
    if (in_array($type, ['Accept', 'Reject'], true)) return federated_interactions_process_follow_response($payload);
    return false;
}

function federated_interactions_moderate_remote_comment(int $commentId, string $status, int $userId, string $note = ''): void
{
    federated_interactions_require_schema();
    if (!in_array($status, ['approved', 'hidden', 'spam', 'deleted'], true)) {
        throw new RuntimeException('Choose Approve, Hide, Spam, or Delete.');
    }
    $statement = db()->prepare('SELECT * FROM activitypub_remote_comments WHERE id=:id LIMIT 1');
    $statement->execute(['id' => $commentId]);
    $comment = $statement->fetch();
    if (!$comment) throw new RuntimeException('The federated comment was not found.');
    db()->prepare(
        'UPDATE activitypub_remote_comments
         SET status=:status,moderation_note=:note,moderated_by_user_id=:user_id,
             moderated_at=UTC_TIMESTAMP(),
             deleted_at=CASE WHEN :deleted="deleted" THEN UTC_TIMESTAMP() ELSE deleted_at END
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'note' => mb_substr(trim($note), 0, 1000) ?: null,
        'user_id' => $userId,
        'deleted' => $status,
        'id' => $commentId,
    ]);
    if ($status === 'approved' && (string)$comment['status'] !== 'approved') {
        $post = content_interactions_blog_post((int)$comment['blog_post_id'], false);
        if ($post && (int)$post['author_user_id'] > 0) {
            notification_create(
                (int)$post['author_user_id'],
                'message',
                'Federated reply approved on your Blog post',
                mb_substr((string)$comment['body_text'], 0, 240),
                'blog-post.php?slug=' . rawurlencode((string)$post['slug']) . '#federated-comment-' . $commentId,
                'federated_comment',
                $commentId,
                'normal'
            );
        }
    }
    log_activity('federated_comment_moderated', 'federated_comment', $commentId, [
        'status' => $status,
        'previous_status' => (string)$comment['status'],
    ]);
}

function federated_interactions_remote_counts(int $postId): array
{
    $counts = ['comments' => 0, 'likes' => 0, 'boosts' => 0];
    if (!federated_interactions_schema_available() || $postId <= 0) return $counts;
    $statement = db()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM activitypub_remote_comments comment
             LEFT JOIN activitypub_actor_controls control ON control.remote_actor_id=comment.remote_actor_id
             WHERE comment.blog_post_id=:post_id AND comment.status="approved"
               AND COALESCE(control.moderation_status,"active")<>"blocked") AS comments,
            (SELECT COUNT(*) FROM activitypub_remote_reactions reaction
             LEFT JOIN activitypub_actor_controls control ON control.remote_actor_id=reaction.remote_actor_id
             WHERE reaction.blog_post_id=:post_id2 AND reaction.status="active" AND reaction.reaction_type="like"
               AND COALESCE(control.moderation_status,"active")<>"blocked") AS likes,
            (SELECT COUNT(*) FROM activitypub_remote_reactions reaction
             LEFT JOIN activitypub_actor_controls control ON control.remote_actor_id=reaction.remote_actor_id
             WHERE reaction.blog_post_id=:post_id3 AND reaction.status="active" AND reaction.reaction_type="announce"
               AND COALESCE(control.moderation_status,"active")<>"blocked") AS boosts'
    );
    $statement->execute(['post_id' => $postId, 'post_id2' => $postId, 'post_id3' => $postId]);
    return array_map('intval', $statement->fetch() ?: $counts);
}

function federated_interactions_remote_comments(int $postId, bool $all = false, int $limit = 250): array
{
    if (!federated_interactions_schema_available() || $postId <= 0) return [];
    $limit = max(1, min(500, $limit));
    $where = $all ? '' : 'AND comment.status="approved" AND COALESCE(control.moderation_status,"active")<>"blocked"';
    $statement = db()->prepare(
        'SELECT comment.*,actor.actor_uri,actor.preferred_username,actor.display_name,
                actor.profile_url,control.moderation_status
         FROM activitypub_remote_comments comment
         JOIN activitypub_remote_actors actor ON actor.id=comment.remote_actor_id
         LEFT JOIN activitypub_actor_controls control ON control.remote_actor_id=actor.id
         WHERE comment.blog_post_id=:post_id ' . $where . '
         ORDER BY COALESCE(comment.source_published_at,comment.created_at),comment.id
         LIMIT ' . $limit
    );
    $statement->execute(['post_id' => $postId]);
    return $statement->fetchAll();
}

function federated_interactions_render_public(array $post): void
{
    if (!federated_interactions_schema_available()) return;
    $counts = federated_interactions_remote_counts((int)$post['id']);
    $comments = federated_interactions_remote_comments((int)$post['id']);
    if (!$comments && array_sum($counts) === 0) return;
    $children = [];
    $roots = [];
    foreach ($comments as $comment) {
        $parent = (int)($comment['parent_remote_comment_id'] ?? 0);
        if ($parent > 0) $children[$parent][] = $comment;
        else $roots[] = $comment;
    }
    $render = static function (array $comment, int $depth = 0) use (&$render, $children): string {
        $id = (int)$comment['id'];
        $name = trim((string)($comment['display_name'] ?: $comment['preferred_username'] ?: 'Remote participant'));
        $profile = trim((string)($comment['profile_url'] ?: $comment['actor_uri']));
        $source = trim((string)($comment['source_url'] ?: $comment['object_uri']));
        $html = '<article class="federated-comment depth-' . min(1, $depth) . '" id="federated-comment-' . $id . '">';
        $html .= '<header><a href="' . e($profile) . '" target="_blank" rel="noopener noreferrer">' . e($name) . '</a>';
        $html .= '<time datetime="' . e((string)($comment['source_published_at'] ?: $comment['created_at'])) . '">' . e(format_datetime((string)($comment['source_published_at'] ?: $comment['created_at']))) . '</time></header>';
        $html .= '<p>' . nl2br(e((string)$comment['body_text'])) . '</p>';
        $html .= '<footer><a href="' . e($source) . '" target="_blank" rel="noopener noreferrer">View federated source</a></footer>';
        foreach ($children[$id] ?? [] as $reply) $html .= $render($reply, $depth + 1);
        return $html . '</article>';
    };
    echo '<section class="federated-conversation" aria-labelledby="federated-conversation-title">';
    echo '<header><div><span>Open social web</span><h2 id="federated-conversation-title">Federated conversation</h2></div>';
    echo '<div class="federated-counts"><span>' . $counts['comments'] . ' replies</span><span>' . $counts['likes'] . ' likes</span><span>' . $counts['boosts'] . ' boosts</span></div></header>';
    foreach ($roots as $comment) echo $render($comment);
    if (!$roots) echo '<p class="federated-empty">This article has federated reactions but no approved remote replies.</p>';
    echo '</section>';
}

function federated_interactions_comment_object_url(int $commentId): string
{
    return publishing_absolute_url('activitypub-comment.php?id=' . $commentId);
}

function federated_interactions_local_map(string $entityType, string $entityKey): ?array
{
    if (!federated_interactions_schema_available()) return null;
    $statement = db()->prepare(
        'SELECT * FROM activitypub_local_objects WHERE entity_type=:entity_type AND entity_key=:entity_key LIMIT 1'
    );
    $statement->execute(['entity_type' => $entityType, 'entity_key' => $entityKey]);
    return $statement->fetch() ?: null;
}

function federated_interactions_save_local_map(
    string $entityType,
    string $entityKey,
    int $postId,
    ?int $commentId,
    string $objectUri,
    ?string $createActivityUri,
    string $lastActivityUri,
    string $payloadHash,
    string $status,
    ?int $userId
): void {
    db()->prepare(
        'INSERT INTO activitypub_local_objects
            (entity_type,entity_key,blog_post_id,local_comment_id,object_uri,
             create_activity_uri,last_activity_uri,last_payload_hash,status,created_by_user_id)
         VALUES
            (:entity_type,:entity_key,:post_id,:comment_id,:object_uri,
             :create_activity_uri,:last_activity_uri,:payload_hash,:status,:user_id)
         ON DUPLICATE KEY UPDATE blog_post_id=VALUES(blog_post_id),local_comment_id=VALUES(local_comment_id),
             object_uri=VALUES(object_uri),create_activity_uri=COALESCE(VALUES(create_activity_uri),create_activity_uri),
             last_activity_uri=VALUES(last_activity_uri),last_payload_hash=VALUES(last_payload_hash),
             status=VALUES(status),updated_at=UTC_TIMESTAMP()'
    )->execute([
        'entity_type' => $entityType,
        'entity_key' => $entityKey,
        'post_id' => $postId > 0 ? $postId : null,
        'comment_id' => ($commentId ?? 0) > 0 ? $commentId : null,
        'object_uri' => $objectUri,
        'create_activity_uri' => $createActivityUri,
        'last_activity_uri' => $lastActivityUri,
        'payload_hash' => $payloadHash,
        'status' => $status,
        'user_id' => ($userId ?? 0) > 0 ? $userId : null,
    ]);
}

function federated_interactions_comment_object(int $commentId, ?array $snapshot = null): ?array
{
    $comment = $snapshot ?? content_interactions_comment($commentId);
    if (!$comment) return null;
    $map = federated_interactions_local_map('comment', (string)$commentId);
    if (($comment['status'] ?? '') !== 'approved') {
        return $map ? [
            'id' => (string)$map['object_uri'],
            'type' => 'Tombstone',
            'formerType' => 'Note',
            'deleted' => gmdate(DATE_ATOM),
        ] : null;
    }
    $post = content_interactions_blog_post((int)$comment['content_id'], false);
    if (!$post) return null;
    $parentId = (int)($comment['parent_id'] ?? 0);
    return [
        'id' => federated_interactions_comment_object_url($commentId),
        'type' => 'Note',
        'attributedTo' => activitypub_actor_url(),
        'inReplyTo' => $parentId > 0
            ? federated_interactions_comment_object_url($parentId)
            : activitypub_object_url((int)$post['id']),
        'content' => '<p>' . nl2br(e((string)$comment['body'])) . '</p>',
        'published' => syndication_iso_date((string)$comment['created_at']) ?? gmdate(DATE_ATOM),
        'updated' => syndication_iso_date((string)($comment['edited_at'] ?: $comment['updated_at'])) ?? gmdate(DATE_ATOM),
        'url' => publishing_absolute_url('blog-post.php?slug=' . rawurlencode((string)$post['slug']) . '#comment-' . $commentId),
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [activitypub_followers_url()],
    ];
}

function federated_interactions_local_comment_event(int $commentId, string $eventType, ?int $actorUserId = null, ?array $snapshot = null): int
{
    if (!federated_interactions_schema_available()) return 0;
    $settings = activitypub_settings();
    if (!$settings['enabled'] || !federated_interactions_settings()['federate_comments']) return 0;
    if (!in_array($eventType, ['Create', 'Update', 'Delete'], true)) return 0;
    $comment = $snapshot ?? content_interactions_comment($commentId);
    if (!$comment || (string)($comment['content_type'] ?? '') !== 'blog_post') return 0;
    if ($eventType !== 'Delete' && (string)$comment['status'] !== 'approved') return 0;
    $postId = (int)$comment['content_id'];
    $objectUri = federated_interactions_comment_object_url($commentId);
    $object = $eventType === 'Delete'
        ? ['id' => $objectUri, 'type' => 'Tombstone', 'formerType' => 'Note', 'deleted' => gmdate(DATE_ATOM)]
        : federated_interactions_comment_object($commentId, $comment);
    if (!$object) return 0;
    $version = (string)($comment['edited_at'] ?: $comment['updated_at'] ?: $comment['created_at']);
    if ($eventType === 'Delete') $version .= '|' . gmdate('Y-m-d H:i:s');
    $uuid = activitypub_uuid_from_seed('activitypub-v66g|comment|' . $eventType . '|' . $commentId . '|' . $version);
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url($uuid),
        'type' => $eventType,
        'actor' => activitypub_actor_url(),
        'published' => gmdate(DATE_ATOM),
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [activitypub_followers_url()],
        'object' => $object,
    ];
    $outboxId = activitypub_store_outbox_activity(
        $activity,
        $eventType,
        $eventType === 'Delete' ? 'Tombstone' : 'Note',
        $objectUri,
        null,
        $actorUserId
    );
    activitypub_queue_approved_followers($outboxId);
    $payload = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $existing = federated_interactions_local_map('comment', (string)$commentId);
    federated_interactions_save_local_map(
        'comment',
        (string)$commentId,
        $postId,
        $commentId,
        $objectUri,
        $eventType === 'Create' ? (string)$activity['id'] : ($existing['create_activity_uri'] ?? null),
        (string)$activity['id'],
        hash('sha256', $payload),
        $eventType === 'Delete' ? 'deleted' : 'active',
        $actorUserId
    );
    return $outboxId;
}

function federated_interactions_target_uri(string $targetType, string $contentType, int $targetId): string
{
    if ($contentType !== 'blog_post' || $targetId <= 0) return '';
    return $targetType === 'comment'
        ? federated_interactions_comment_object_url($targetId)
        : activitypub_object_url($targetId);
}

function federated_interactions_store_reaction_activity(array $activity, string $activityType, string $objectUri, ?int $userId): int
{
    $outboxId = activitypub_store_outbox_activity($activity, $activityType, 'Reaction', $objectUri, null, $userId);
    activitypub_queue_approved_followers($outboxId);
    return $outboxId;
}

function federated_interactions_local_reaction_event(
    int $userId,
    string $targetType,
    string $contentType,
    int $targetId,
    string $previousReaction,
    string $activeReaction
): void {
    if (!federated_interactions_schema_available()) return;
    if (!activitypub_settings()['enabled'] || !federated_interactions_settings()['federate_reactions']) return;
    $objectUri = federated_interactions_target_uri($targetType, $contentType, $targetId);
    if ($objectUri === '') return;
    $entityKey = implode(':', [$userId, $targetType, $contentType, $targetId]);
    $map = federated_interactions_local_map('reaction', $entityKey);
    if ($previousReaction !== '' && ($activeReaction === '' || $previousReaction !== $activeReaction) && $map) {
        $previous = null;
        if (!empty($map['create_activity_uri'])) {
            $statement = db()->prepare('SELECT payload_json FROM activitypub_outbox_activities WHERE activity_uri=:uri LIMIT 1');
            $statement->execute(['uri' => (string)$map['create_activity_uri']]);
            $decoded = json_decode((string)($statement->fetchColumn() ?: ''), true);
            if (is_array($decoded)) $previous = $decoded;
        }
        $undo = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => activitypub_activity_url(pod_uuid_v4()),
            'type' => 'Undo',
            'actor' => activitypub_actor_url(),
            'object' => $previous ?: (string)($map['create_activity_uri'] ?? ''),
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [activitypub_followers_url()],
        ];
        federated_interactions_store_reaction_activity($undo, 'Undo', $objectUri, $userId);
        $payload = json_encode($undo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        federated_interactions_save_local_map(
            'reaction', $entityKey, $targetType === 'content' ? $targetId : 0,
            $targetType === 'comment' ? $targetId : null, $objectUri,
            (string)($map['create_activity_uri'] ?? ''), (string)$undo['id'], hash('sha256', $payload),
            $activeReaction === '' ? 'deleted' : 'active', $userId
        );
    }
    if ($activeReaction === '') return;
    $like = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url(pod_uuid_v4()),
        'type' => 'Like',
        'actor' => activitypub_actor_url(),
        'object' => $objectUri,
        'content' => status_label($activeReaction),
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [activitypub_followers_url()],
    ];
    federated_interactions_store_reaction_activity($like, 'Like', $objectUri, $userId);
    $payload = json_encode($like, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    federated_interactions_save_local_map(
        'reaction', $entityKey, $targetType === 'content' ? $targetId : 0,
        $targetType === 'comment' ? $targetId : null, $objectUri,
        (string)$like['id'], (string)$like['id'], hash('sha256', $payload), 'active', $userId
    );
}

function federated_interactions_follow_actor(string $actorUri, int $userId): int
{
    federated_interactions_require_schema();
    if (!activitypub_settings()['enabled']) throw new RuntimeException('Enable ActivityPub before following a remote actor.');
    $actorUri = trim($actorUri);
    if (activitypub_normalize_url($actorUri) === activitypub_normalize_url(activitypub_actor_url())) {
        throw new RuntimeException('The POD cannot follow its own ActivityPub actor.');
    }
    $remote = activitypub_remote_actor($actorUri, true);
    if (!$remote || !federated_interactions_actor_allowed($remote)) throw new RuntimeException('The remote actor is unavailable or blocked.');
    $existing = db()->prepare('SELECT * FROM activitypub_following WHERE remote_actor_id=:actor_id LIMIT 1');
    $existing->execute(['actor_id' => (int)$remote['id']]);
    $following = $existing->fetch();
    if ($following && in_array((string)$following['status'], ['pending', 'accepted'], true)) return (int)$following['id'];
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url(pod_uuid_v4()),
        'type' => 'Follow',
        'actor' => activitypub_actor_url(),
        'object' => (string)$remote['actor_uri'],
    ];
    $outboxId = activitypub_store_outbox_activity($activity, 'Follow', 'Actor', (string)$remote['actor_uri'], null, $userId);
    activitypub_queue_delivery($outboxId, (int)$remote['id']);
    db()->prepare(
        'INSERT INTO activitypub_following
            (remote_actor_id,follow_activity_uri,follow_outbox_activity_id,status,created_by_user_id)
         VALUES (:actor_id,:activity_uri,:outbox_id,"pending",:user_id)
         ON DUPLICATE KEY UPDATE follow_activity_uri=VALUES(follow_activity_uri),
             follow_outbox_activity_id=VALUES(follow_outbox_activity_id),status="pending",
             created_by_user_id=VALUES(created_by_user_id),accepted_at=NULL,removed_at=NULL,updated_at=UTC_TIMESTAMP()'
    )->execute([
        'actor_id' => (int)$remote['id'],
        'activity_uri' => (string)$activity['id'],
        'outbox_id' => $outboxId,
        'user_id' => $userId,
    ]);
    $statement = db()->prepare('SELECT id FROM activitypub_following WHERE remote_actor_id=:actor_id LIMIT 1');
    $statement->execute(['actor_id' => (int)$remote['id']]);
    return (int)($statement->fetchColumn() ?: 0);
}

function federated_interactions_unfollow_actor(int $followingId, int $userId): void
{
    federated_interactions_require_schema();
    $statement = db()->prepare(
        'SELECT following.*,actor.actor_uri,actor.id AS actor_id
         FROM activitypub_following following
         JOIN activitypub_remote_actors actor ON actor.id=following.remote_actor_id
         WHERE following.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $followingId]);
    $following = $statement->fetch();
    if (!$following) throw new RuntimeException('The followed actor was not found.');
    $followPayload = null;
    $payloadStatement = db()->prepare('SELECT payload_json FROM activitypub_outbox_activities WHERE activity_uri=:uri LIMIT 1');
    $payloadStatement->execute(['uri' => (string)$following['follow_activity_uri']]);
    $decoded = json_decode((string)($payloadStatement->fetchColumn() ?: ''), true);
    if (is_array($decoded)) $followPayload = $decoded;
    $undo = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url(pod_uuid_v4()),
        'type' => 'Undo',
        'actor' => activitypub_actor_url(),
        'object' => $followPayload ?: (string)$following['follow_activity_uri'],
    ];
    $outboxId = activitypub_store_outbox_activity($undo, 'Undo', 'Follow', (string)$following['actor_uri'], null, $userId);
    activitypub_queue_delivery($outboxId, (int)$following['actor_id']);
    db()->prepare(
        'UPDATE activitypub_following SET status="removed",removed_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute(['id' => $followingId]);
}

function federated_interactions_following(bool $all = true, int $limit = 200): array
{
    if (!federated_interactions_schema_available()) return [];
    $limit = max(1, min(500, $limit));
    $where = $all ? '' : 'WHERE following.status="accepted" AND COALESCE(control.moderation_status,"active")<>"blocked"';
    return db()->query(
        'SELECT following.*,actor.actor_uri,actor.preferred_username,actor.display_name,
                actor.profile_url,actor.status AS actor_status,control.moderation_status
         FROM activitypub_following following
         JOIN activitypub_remote_actors actor ON actor.id=following.remote_actor_id
         LEFT JOIN activitypub_actor_controls control ON control.remote_actor_id=actor.id
         ' . $where . '
         ORDER BY FIELD(following.status,"pending","accepted","rejected","removed","blocked"),
                  following.created_at DESC,following.id DESC LIMIT ' . $limit
    )->fetchAll();
}

function federated_interactions_following_document(): array
{
    $following = federated_interactions_following(false, 500);
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_following_url(),
        'type' => 'OrderedCollection',
        'totalItems' => count($following),
        'orderedItems' => federated_interactions_settings()['show_following']
            ? array_values(array_map(static fn(array $row): string => (string)$row['actor_uri'], $following))
            : [],
    ];
}

function federated_interactions_set_actor_control(int $remoteActorId, string $status, string $note, int $userId): void
{
    federated_interactions_require_schema();
    if (!in_array($status, ['active', 'muted', 'blocked'], true)) throw new RuntimeException('Choose Active, Muted, or Blocked.');
    db()->prepare(
        'INSERT INTO activitypub_actor_controls (remote_actor_id,moderation_status,moderation_note,updated_by_user_id)
         VALUES (:actor_id,:status,:note,:user_id)
         ON DUPLICATE KEY UPDATE moderation_status=VALUES(moderation_status),
             moderation_note=VALUES(moderation_note),updated_by_user_id=VALUES(updated_by_user_id),updated_at=UTC_TIMESTAMP()'
    )->execute([
        'actor_id' => $remoteActorId,
        'status' => $status,
        'note' => mb_substr(trim($note), 0, 1000) ?: null,
        'user_id' => $userId,
    ]);
    if ($status === 'blocked') {
        db()->prepare('UPDATE activitypub_remote_actors SET status="blocked" WHERE id=:id')->execute(['id' => $remoteActorId]);
        db()->prepare('UPDATE activitypub_followers SET status="removed",moderated_at=UTC_TIMESTAMP() WHERE remote_actor_id=:id')->execute(['id' => $remoteActorId]);
        db()->prepare('UPDATE activitypub_following SET status="blocked",removed_at=UTC_TIMESTAMP() WHERE remote_actor_id=:id')->execute(['id' => $remoteActorId]);
        db()->prepare('UPDATE activitypub_remote_comments SET status="hidden",moderated_at=UTC_TIMESTAMP(),moderated_by_user_id=:user_id WHERE remote_actor_id=:id AND status IN ("pending","approved")')->execute(['user_id' => $userId, 'id' => $remoteActorId]);
        db()->prepare('UPDATE activitypub_remote_reactions SET status="undone",updated_at=UTC_TIMESTAMP() WHERE remote_actor_id=:id AND status="active"')->execute(['id' => $remoteActorId]);
    } elseif ($status === 'active') {
        db()->prepare('UPDATE activitypub_remote_actors SET status="active" WHERE id=:id AND status="blocked"')->execute(['id' => $remoteActorId]);
        db()->prepare('UPDATE activitypub_following SET status="removed" WHERE remote_actor_id=:id AND status="blocked"')->execute(['id' => $remoteActorId]);
    }
    log_activity('activitypub_actor_control_updated', 'activitypub_remote_actor', $remoteActorId, ['status' => $status]);
}

function federated_interactions_block_domain(string $domain, string $reason, int $userId): void
{
    federated_interactions_require_schema();
    $domain = federated_interactions_normalize_domain($domain);
    db()->prepare(
        'INSERT INTO activitypub_domain_blocks (domain_name,reason,created_by_user_id)
         VALUES (:domain,:reason,:user_id)
         ON DUPLICATE KEY UPDATE reason=VALUES(reason),created_by_user_id=VALUES(created_by_user_id)'
    )->execute(['domain' => $domain, 'reason' => mb_substr(trim($reason), 0, 1000) ?: null, 'user_id' => $userId]);
    $actors = db()->query('SELECT id,actor_uri FROM activitypub_remote_actors')->fetchAll();
    foreach ($actors as $actor) {
        $host = federated_interactions_actor_domain((string)$actor['actor_uri']);
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            federated_interactions_set_actor_control((int)$actor['id'], 'blocked', 'Domain block: ' . $domain, $userId);
        }
    }
}

function federated_interactions_unblock_domain(int $blockId): void
{
    if (!federated_interactions_schema_available()) return;
    db()->prepare('DELETE FROM activitypub_domain_blocks WHERE id=:id')->execute(['id' => $blockId]);
}

function federated_interactions_inbox_items(): array
{
    if (!federated_interactions_schema_available()) return [];
    $items = [];
    $comments = db()->query(
        'SELECT comment.*,actor.display_name,actor.preferred_username,post.title AS post_title
         FROM activitypub_remote_comments comment
         JOIN activitypub_remote_actors actor ON actor.id=comment.remote_actor_id
         JOIN blog_posts post ON post.id=comment.blog_post_id
         WHERE comment.status<>"deleted"
         ORDER BY comment.updated_at DESC,comment.id DESC LIMIT 100'
    )->fetchAll();
    foreach ($comments as $row) {
        $items[] = unified_inbox_item([
            'source_type' => 'federated_comment',
            'source_id' => (int)$row['id'],
            'source_label' => 'Federated Reply',
            'category' => 'social',
            'icon' => '◌',
            'title' => 'Federated reply on ' . (string)$row['post_title'],
            'participant' => (string)($row['display_name'] ?: $row['preferred_username'] ?: 'Remote actor'),
            'preview' => (string)$row['body_text'],
            'occurred_at' => (string)$row['updated_at'],
            'native_unread' => (string)$row['status'] === 'pending',
            'native_status' => (string)$row['status'],
            'native_priority' => (string)$row['status'] === 'spam' ? 'high' : 'normal',
            'native_needs_response' => (string)$row['status'] === 'pending',
            'href' => app_url('portal/admin.php?view=federation#interactions'),
        ]);
    }
    $reactions = db()->query(
        'SELECT reaction.*,actor.display_name,actor.preferred_username,post.title AS post_title
         FROM activitypub_remote_reactions reaction
         JOIN activitypub_remote_actors actor ON actor.id=reaction.remote_actor_id
         LEFT JOIN blog_posts post ON post.id=reaction.blog_post_id
         WHERE reaction.status="active"
         ORDER BY reaction.updated_at DESC,reaction.id DESC LIMIT 80'
    )->fetchAll();
    foreach ($reactions as $row) {
        $items[] = unified_inbox_item([
            'source_type' => 'federated_reaction',
            'source_id' => (int)$row['id'],
            'source_label' => (string)$row['reaction_type'] === 'announce' ? 'Federated Boost' : 'Federated Like',
            'category' => 'social',
            'icon' => (string)$row['reaction_type'] === 'announce' ? '↻' : '♥',
            'title' => status_label((string)$row['reaction_type']) . ' on ' . ((string)$row['post_title'] ?: 'federated content'),
            'participant' => (string)($row['display_name'] ?: $row['preferred_username'] ?: 'Remote actor'),
            'preview' => (string)$row['object_uri'],
            'occurred_at' => (string)$row['updated_at'],
            'native_unread' => true,
            'native_status' => 'active',
            'native_needs_response' => false,
            'href' => app_url('portal/admin.php?view=federation#interactions'),
        ]);
    }
    foreach (federated_interactions_following(true, 80) as $row) {
        if (!in_array((string)$row['status'], ['pending', 'rejected'], true)) continue;
        $items[] = unified_inbox_item([
            'source_type' => 'federated_follow',
            'source_id' => (int)$row['id'],
            'source_label' => 'Outbound Follow',
            'category' => 'social',
            'icon' => '◎',
            'title' => 'Outbound follow ' . status_label((string)$row['status']),
            'participant' => (string)($row['display_name'] ?: $row['preferred_username'] ?: $row['actor_uri']),
            'preview' => (string)$row['actor_uri'],
            'occurred_at' => (string)$row['updated_at'],
            'native_unread' => (string)$row['status'] === 'rejected',
            'native_status' => (string)$row['status'],
            'native_priority' => (string)$row['status'] === 'rejected' ? 'high' : 'normal',
            'native_needs_response' => (string)$row['status'] === 'rejected',
            'href' => app_url('portal/admin.php?view=federation#following'),
        ]);
    }
    return $items;
}

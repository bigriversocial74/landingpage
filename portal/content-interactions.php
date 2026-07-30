<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-content-interactions-v66C */

require_once __DIR__ . '/notifications.php';
if (defined('NMM_BOOTSTRAPPED') && is_file(__DIR__ . '/activitypub-service.php')) {
    require_once __DIR__ . '/activitypub-service.php';
}

function content_interactions_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                 "content_interaction_settings","content_comments",
                 "content_comment_edits","content_reactions",
                 "content_comment_reports","content_moderation_events"
               )'
        );
        $available = (int)$statement->fetchColumn() === 6;
    } catch (Throwable) {
        $available = false;
    }
    return $available;
}

function content_interactions_reaction_types(): array
{
    return [
        'like' => ['label' => 'Like', 'icon' => '♥'],
        'support' => ['label' => 'Support', 'icon' => '◆'],
        'insightful' => ['label' => 'Insightful', 'icon' => '✦'],
    ];
}

function content_interactions_clean_body(string $body): string
{
    $body = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body) ?? '');
    $body = preg_replace('/[ \t]+\n/u', "\n", $body) ?? $body;
    $body = preg_replace('/\n{4,}/u', "\n\n\n", $body) ?? $body;
    return mb_substr($body, 0, 4000);
}

function content_interactions_render_text(string $body): string
{
    return nl2br(e(content_interactions_clean_body($body)));
}

function content_interactions_settings(string $contentType, int $contentId): array
{
    $defaults = [
        'content_type' => $contentType,
        'content_id' => $contentId,
        'comments_enabled' => 1,
        'replies_enabled' => 1,
        'reactions_enabled' => 1,
        'moderation_mode' => 'pre_moderated',
        'comments_closed_at' => null,
    ];
    if (!content_interactions_schema_available() || $contentId <= 0) return $defaults;
    $statement = db()->prepare(
        'SELECT * FROM content_interaction_settings
         WHERE content_type=:content_type AND content_id=:content_id LIMIT 1'
    );
    $statement->execute(['content_type' => $contentType, 'content_id' => $contentId]);
    return array_replace($defaults, $statement->fetch() ?: []);
}

function content_interactions_blog_post(int $postId, bool $publishedOnly = true): ?array
{
    if ($postId <= 0) return null;
    $sql = 'SELECT id,title,slug,status,author_user_id,published_at FROM blog_posts WHERE id=:id';
    if ($publishedOnly) $sql .= ' AND status="published" AND (published_at IS NULL OR published_at<=UTC_TIMESTAMP())';
    $sql .= ' LIMIT 1';
    $statement = db()->prepare($sql);
    $statement->execute(['id' => $postId]);
    return $statement->fetch() ?: null;
}

function content_interactions_comment(int $commentId): ?array
{
    if (!content_interactions_schema_available() || $commentId <= 0) return null;
    $statement = db()->prepare(
        'SELECT comment.*,user.display_name AS author_name,user.role AS author_role
         FROM content_comments comment
         JOIN users user ON user.id=comment.author_user_id
         WHERE comment.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $commentId]);
    return $statement->fetch() ?: null;
}

function content_interactions_reaction_summary(string $targetType, string $contentType, int $targetId): array
{
    $summary = array_fill_keys(array_keys(content_interactions_reaction_types()), 0);
    if (!content_interactions_schema_available() || $targetId <= 0) return $summary;
    $statement = db()->prepare(
        'SELECT reaction_type,COUNT(*) AS total FROM content_reactions
         WHERE target_type=:target_type AND content_type=:content_type AND target_id=:target_id
         GROUP BY reaction_type'
    );
    $statement->execute([
        'target_type' => $targetType,
        'content_type' => $contentType,
        'target_id' => $targetId,
    ]);
    foreach ($statement->fetchAll() as $row) {
        if (array_key_exists((string)$row['reaction_type'], $summary)) {
            $summary[(string)$row['reaction_type']] = (int)$row['total'];
        }
    }
    return $summary;
}

function content_interactions_viewer_reaction(
    int $userId,
    string $targetType,
    string $contentType,
    int $targetId
): string {
    if (!content_interactions_schema_available() || $userId <= 0 || $targetId <= 0) return '';
    $statement = db()->prepare(
        'SELECT reaction_type FROM content_reactions
         WHERE user_id=:user_id AND target_type=:target_type
           AND content_type=:content_type AND target_id=:target_id LIMIT 1'
    );
    $statement->execute([
        'user_id' => $userId,
        'target_type' => $targetType,
        'content_type' => $contentType,
        'target_id' => $targetId,
    ]);
    return (string)($statement->fetchColumn() ?: '');
}

function content_interactions_comments(
    string $contentType,
    int $contentId,
    ?array $viewer = null
): array {
    if (!content_interactions_schema_available() || $contentId <= 0) return [];
    $isAdmin = ($viewer['role'] ?? '') === 'admin';
    $viewerId = (int)($viewer['id'] ?? 0);
    $where = ['comment.content_type=:content_type', 'comment.content_id=:content_id'];
    $parameters = ['content_type' => $contentType, 'content_id' => $contentId];
    if (!$isAdmin) {
        if ($viewerId > 0) {
            $where[] = '(comment.status="approved" OR comment.author_user_id=:viewer_id OR comment.status="deleted")';
            $parameters['viewer_id'] = $viewerId;
        } else {
            $where[] = '(comment.status="approved" OR comment.status="deleted")';
        }
    }
    $statement = db()->prepare(
        'SELECT comment.*,user.display_name AS author_name,user.role AS author_role
         FROM content_comments comment
         JOIN users user ON user.id=comment.author_user_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY COALESCE(comment.parent_id,comment.id),comment.depth,comment.created_at,comment.id
         LIMIT 500'
    );
    $statement->execute($parameters);
    $rows = $statement->fetchAll();
    $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    $reactionMap = [];
    $viewerReactionMap = [];
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $reaction = db()->prepare(
            'SELECT target_id,reaction_type,COUNT(*) AS total FROM content_reactions
             WHERE target_type="comment" AND content_type=? AND target_id IN (' . $placeholders . ')
             GROUP BY target_id,reaction_type'
        );
        $reaction->execute([$contentType, ...$ids]);
        foreach ($reaction->fetchAll() as $row) {
            $reactionMap[(int)$row['target_id']][(string)$row['reaction_type']] = (int)$row['total'];
        }
        if ($viewerId > 0) {
            $viewerReaction = db()->prepare(
                'SELECT target_id,reaction_type FROM content_reactions
                 WHERE user_id=? AND target_type="comment" AND content_type=? AND target_id IN (' . $placeholders . ')'
            );
            $viewerReaction->execute([$viewerId, $contentType, ...$ids]);
            foreach ($viewerReaction->fetchAll() as $row) {
                $viewerReactionMap[(int)$row['target_id']] = (string)$row['reaction_type'];
            }
        }
    }
    $roots = [];
    $children = [];
    foreach ($rows as &$row) {
        $row['reactions'] = array_fill_keys(array_keys(content_interactions_reaction_types()), 0);
        foreach ($reactionMap[(int)$row['id']] ?? [] as $type => $count) $row['reactions'][$type] = $count;
        $row['viewer_reaction'] = $viewerReactionMap[(int)$row['id']] ?? '';
        if ((int)$row['depth'] === 0) $roots[(int)$row['id']] = $row + ['replies' => []];
        else $children[(int)$row['parent_id']][] = $row;
    }
    unset($row);
    foreach ($children as $parentId => $replies) {
        if (isset($roots[$parentId])) $roots[$parentId]['replies'] = $replies;
    }
    return array_values($roots);
}

function content_interactions_context(string $contentType, int $contentId, ?array $viewer = null): array
{
    $settings = content_interactions_settings($contentType, $contentId);
    $comments = content_interactions_comments($contentType, $contentId, $viewer);
    $approved = 0;
    foreach ($comments as $comment) {
        if ($comment['status'] === 'approved') $approved++;
        foreach ($comment['replies'] as $reply) if ($reply['status'] === 'approved') $approved++;
    }
    $viewerId = (int)($viewer['id'] ?? 0);
    return [
        'schema_ready' => content_interactions_schema_available(),
        'settings' => $settings,
        'comments' => $comments,
        'comment_count' => $approved,
        'reactions' => content_interactions_reaction_summary('content', $contentType, $contentId),
        'viewer_reaction' => $viewerId > 0
            ? content_interactions_viewer_reaction($viewerId, 'content', $contentType, $contentId)
            : '',
    ];
}

function content_interactions_validate_comment_body(int $userId, string $body, int $exclude_comment_id = 0): string
{
    $body = content_interactions_clean_body($body);
    if (mb_strlen($body) < 2) throw new RuntimeException('Enter a comment with at least two characters.');
    if (preg_match_all('#https?://#i', $body) > 3) throw new RuntimeException('Comments may contain no more than three links.');
    $hash = hash('sha256', mb_strtolower($body));
    $duplicate = db()->prepare(
        'SELECT id FROM content_comments
         WHERE author_user_id=:user_id AND body_hash=:body_hash
           AND id<>:exclude_comment_id
           AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)
           AND status<>"deleted" LIMIT 1'
    );
    $duplicate->execute(['user_id' => $userId, 'body_hash' => $hash, 'exclude_comment_id' => $exclude_comment_id]);
    if ($duplicate->fetchColumn()) throw new RuntimeException('That comment was already submitted recently.');
    return $body;
}

function content_interactions_notify_admins(string $title, string $body, string $link, int $commentId): void
{
    notification_create_for_role('admin', 'message', $title, $body, $link, 'content_comment', $commentId, 'normal');
}

function content_interactions_notify_participants(array $comment): void
{
    if (($comment['status'] ?? '') !== 'approved') return;
    $post = content_interactions_blog_post((int)$comment['content_id'], false);
    if (!$post) return;
    $recipients = [];
    if ((int)$post['author_user_id'] > 0) $recipients[] = (int)$post['author_user_id'];
    if ((int)($comment['parent_id'] ?? 0) > 0) {
        $parent = content_interactions_comment((int)$comment['parent_id']);
        if ($parent) $recipients[] = (int)$parent['author_user_id'];
    }
    $recipients = array_values(array_unique(array_filter($recipients, static fn(int $id): bool => $id > 0 && $id !== (int)$comment['author_user_id'])));
    $link = 'blog-post.php?slug=' . rawurlencode((string)$post['slug']) . '#comment-' . (int)$comment['id'];
    foreach ($recipients as $recipientId) {
        notification_create(
            $recipientId,
            'message',
            (int)($comment['parent_id'] ?? 0) > 0 ? 'New reply on your Blog conversation' : 'New comment on your Blog post',
            mb_substr((string)$comment['body'], 0, 240),
            $link,
            'content_comment',
            (int)$comment['id'],
            'normal'
        );
    }
}

function content_interactions_notify_reaction(
    int $actorUserId,
    string $targetType,
    string $contentType,
    int $targetId,
    string $reactionType
): void {
    if ($contentType !== 'blog_post') return;
    $recipientId = 0;
    $post = null;
    if ($targetType === 'content') {
        $post = content_interactions_blog_post($targetId, false);
        $recipientId = (int)($post['author_user_id'] ?? 0);
    } else {
        $comment = content_interactions_comment($targetId);
        if (!$comment) return;
        $recipientId = (int)$comment['author_user_id'];
        $post = content_interactions_blog_post((int)$comment['content_id'], false);
    }
    if (!$post || $recipientId <= 0 || $recipientId === $actorUserId) return;
    $label = content_interactions_reaction_types()[$reactionType]['label'] ?? 'Reaction';
    notification_create(
        $recipientId,
        'message',
        $targetType === 'content' ? 'New reaction on your Blog post' : 'New reaction on your Blog comment',
        $label . ': ' . (string)$post['title'],
        'blog-post.php?slug=' . rawurlencode((string)$post['slug']) . ($targetType === 'comment' ? '#comment-' . $targetId : ''),
        'content_reaction',
        $targetId,
        'normal'
    );
}

function content_interactions_create_comment(
    int $userId,
    string $contentType,
    int $contentId,
    int $parentId,
    string $body,
    string $userRole = 'client'
): array {
    if (!content_interactions_schema_available()) throw new RuntimeException('Content interaction migration is required.');
    if ($contentType !== 'blog_post' || !content_interactions_blog_post($contentId, true)) {
        throw new RuntimeException('The article is not available for comments.');
    }
    $settings = content_interactions_settings($contentType, $contentId);
    if (!(int)$settings['comments_enabled']) throw new RuntimeException('Comments are disabled for this article.');
    if ($settings['comments_closed_at'] && strtotime((string)$settings['comments_closed_at']) <= time()) {
        throw new RuntimeException('Comments are closed for this article.');
    }
    $body = content_interactions_validate_comment_body($userId, $body);
    $depth = 0;
    if ($parentId > 0) {
        if (!(int)$settings['replies_enabled']) throw new RuntimeException('Replies are disabled for this article.');
        $parent = content_interactions_comment($parentId);
        if (!$parent || $parent['content_type'] !== $contentType || (int)$parent['content_id'] !== $contentId || (int)$parent['depth'] !== 0 || $parent['status'] !== 'approved') {
            throw new RuntimeException('The parent comment is not available for replies.');
        }
        $depth = 1;
    }
    $status = $userRole === 'admin' || $settings['moderation_mode'] === 'registered_auto' ? 'approved' : 'pending';
    $statement = db()->prepare(
        'INSERT INTO content_comments
           (content_type,content_id,parent_id,author_user_id,body,body_hash,status,depth)
         VALUES
           (:content_type,:content_id,:parent_id,:author_user_id,:body,:body_hash,:status,:depth)'
    );
    $statement->execute([
        'content_type' => $contentType,
        'content_id' => $contentId,
        'parent_id' => $parentId > 0 ? $parentId : null,
        'author_user_id' => $userId,
        'body' => $body,
        'body_hash' => hash('sha256', mb_strtolower($body)),
        'status' => $status,
        'depth' => $depth,
    ]);
    $commentId = (int)db()->lastInsertId();
    $comment = content_interactions_comment($commentId) ?: [];
    if ($status === 'pending') {
        content_interactions_notify_admins(
            'Blog comment awaiting moderation',
            mb_substr($body, 0, 240),
            'portal/admin.php?view=blog&moderation=1',
            $commentId
        );
    } else {
        content_interactions_notify_participants($comment);
        if (function_exists('federated_interactions_local_comment_event')) {
            federated_interactions_local_comment_event($commentId, 'Create', $userId);
        }
    }
    log_activity('content_comment_created', 'content_comment', $commentId, ['content_type' => $contentType, 'content_id' => $contentId, 'status' => $status]);
    return ['id' => $commentId, 'status' => $status, 'message' => $status === 'approved' ? 'Comment published.' : 'Comment submitted for moderation.'];
}

function content_interactions_can_edit(array $comment, array $user): bool
{
    if (($user['role'] ?? '') === 'admin') return true;
    if ((int)($comment['author_user_id'] ?? 0) !== (int)($user['id'] ?? 0)) return false;
    $status = (string)($comment['status'] ?? '');
    if (!in_array($status, ['pending', 'approved'], true)) return false;
    if ($status === 'pending') return true;
    $created = strtotime((string)($comment['created_at'] ?? '')) ?: 0;
    return $created > 0 && $created >= time() - 900;
}

function content_interactions_edit_comment(int $commentId, array $user, string $body): array
{
    $comment = content_interactions_comment($commentId);
    if (!$comment || !content_interactions_can_edit($comment, $user)) throw new RuntimeException('This comment can no longer be edited.');
    $body = content_interactions_validate_comment_body((int)$user['id'], $body, $commentId);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO content_comment_edits (comment_id,editor_user_id,previous_body)
             VALUES (:comment_id,:editor_user_id,:previous_body)'
        )->execute(['comment_id' => $commentId, 'editor_user_id' => (int)$user['id'], 'previous_body' => (string)$comment['body']]);
        $status = ($user['role'] ?? '') === 'admin' ? (string)$comment['status'] : 'pending';
        $pdo->prepare(
            'UPDATE content_comments SET body=:body,body_hash=:body_hash,status=:status,edited_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute(['body' => $body, 'body_hash' => hash('sha256', mb_strtolower($body)), 'status' => $status, 'id' => $commentId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    if (($user['role'] ?? '') !== 'admin') {
        content_interactions_notify_admins('Edited Blog comment awaiting moderation', mb_substr($body, 0, 240), 'portal/admin.php?view=blog&moderation=1', $commentId);
    } elseif ((string)$comment['status'] === 'approved' && function_exists('federated_interactions_local_comment_event')) {
        federated_interactions_local_comment_event($commentId, 'Update', (int)$user['id']);
    }
    return ['id' => $commentId, 'status' => ($user['role'] ?? '') === 'admin' ? (string)$comment['status'] : 'pending'];
}

function content_interactions_delete_comment(int $commentId, array $user): void
{
    $comment = content_interactions_comment($commentId);
    if (!$comment) throw new RuntimeException('Comment not found.');
    $isAdmin = ($user['role'] ?? '') === 'admin';
    if (!$isAdmin && (int)$comment['author_user_id'] !== (int)$user['id']) throw new RuntimeException('You cannot delete this comment.');
    db()->prepare(
        'UPDATE content_comments SET status="deleted",body="",deleted_at=UTC_TIMESTAMP(),deleted_by=:deleted_by WHERE id=:id'
    )->execute(['deleted_by' => (int)$user['id'], 'id' => $commentId]);
    if ((string)$comment['status'] === 'approved' && function_exists('federated_interactions_local_comment_event')) {
        federated_interactions_local_comment_event($commentId, 'Delete', (int)$user['id'], $comment);
    }
    log_activity('content_comment_deleted', 'content_comment', $commentId, ['admin' => $isAdmin]);
}

function content_interactions_toggle_reaction(
    int $userId,
    string $targetType,
    string $contentType,
    int $targetId,
    string $reactionType
): array {
    if (!content_interactions_schema_available()) throw new RuntimeException('Content interaction migration is required.');
    if (!isset(content_interactions_reaction_types()[$reactionType])) throw new RuntimeException('Unsupported reaction.');
    if (!in_array($targetType, ['content', 'comment'], true)) throw new RuntimeException('Unsupported reaction target.');
    if ($targetType === 'content') {
        if ($contentType !== 'blog_post' || !content_interactions_blog_post($targetId, true)) throw new RuntimeException('The article is unavailable.');
        if (!(int)content_interactions_settings($contentType, $targetId)['reactions_enabled']) throw new RuntimeException('Reactions are disabled for this article.');
    } else {
        $comment = content_interactions_comment($targetId);
        if (!$comment || $comment['status'] !== 'approved' || $comment['content_type'] !== $contentType) throw new RuntimeException('The comment is unavailable for reactions.');
    }
    $existing = content_interactions_viewer_reaction($userId, $targetType, $contentType, $targetId);
    $isFirstReaction = $existing === '';
    if ($existing === $reactionType) {
        db()->prepare(
            'DELETE FROM content_reactions WHERE user_id=:user_id AND target_type=:target_type AND content_type=:content_type AND target_id=:target_id'
        )->execute(['user_id' => $userId, 'target_type' => $targetType, 'content_type' => $contentType, 'target_id' => $targetId]);
        $active = '';
    } else {
        db()->prepare(
            'INSERT INTO content_reactions (target_type,content_type,target_id,user_id,reaction_type)
             VALUES (:target_type,:content_type,:target_id,:user_id,:reaction_type)
             ON DUPLICATE KEY UPDATE reaction_type=VALUES(reaction_type),updated_at=UTC_TIMESTAMP()'
        )->execute(['target_type' => $targetType, 'content_type' => $contentType, 'target_id' => $targetId, 'user_id' => $userId, 'reaction_type' => $reactionType]);
        $active = $reactionType;
        if ($isFirstReaction) {
            content_interactions_notify_reaction($userId, $targetType, $contentType, $targetId, $reactionType);
        }
    }
    if (function_exists('federated_interactions_local_reaction_event')) {
        federated_interactions_local_reaction_event(
            $userId, $targetType, $contentType, $targetId, $existing, $active
        );
    }
    return ['active' => $active, 'counts' => content_interactions_reaction_summary($targetType, $contentType, $targetId)];
}

function content_interactions_report_comment(int $commentId, int $userId, string $reason): array
{
    $comment = content_interactions_comment($commentId);
    if (!$comment || $comment['status'] !== 'approved') throw new RuntimeException('The comment is unavailable for reporting.');
    if ((int)$comment['author_user_id'] === $userId) throw new RuntimeException('You cannot report your own comment.');
    $reason = mb_substr(trim($reason), 0, 1000);
    if ($reason === '') $reason = 'Reported by reader';
    $existing = db()->prepare(
        'SELECT id,status FROM content_comment_reports
         WHERE comment_id=:comment_id AND reporter_user_id=:reporter_user_id LIMIT 1'
    );
    $existing->execute(['comment_id' => $commentId, 'reporter_user_id' => $userId]);
    $report = $existing->fetch();
    if ($report && $report['status'] === 'open') return ['reported' => true, 'duplicate' => true];
    if ($report) {
        db()->prepare(
            'UPDATE content_comment_reports SET reason=:reason,status="open",resolved_at=NULL,resolved_by=NULL,created_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute(['reason' => $reason, 'id' => (int)$report['id']]);
    } else {
        db()->prepare(
            'INSERT INTO content_comment_reports (comment_id,reporter_user_id,reason,status)
             VALUES (:comment_id,:reporter_user_id,:reason,"open")'
        )->execute(['comment_id' => $commentId, 'reporter_user_id' => $userId, 'reason' => $reason]);
    }
    db()->prepare('UPDATE content_comments SET report_count=report_count+1 WHERE id=:id')->execute(['id' => $commentId]);
    $countStatement = db()->prepare('SELECT report_count,status FROM content_comments WHERE id=:id LIMIT 1');
    $countStatement->execute(['id' => $commentId]);
    $reported = $countStatement->fetch() ?: ['report_count' => 0, 'status' => ''];
    $count = (int)$reported['report_count'];
    if ($count >= 5 && $reported['status'] === 'approved') {
        db()->prepare('UPDATE content_comments SET status="hidden",moderated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $commentId]);
        db()->prepare(
            'INSERT INTO content_moderation_events (comment_id,moderator_user_id,action,note,previous_status,new_status)
             VALUES (:comment_id,NULL,"auto_hidden",:note,"approved","hidden")'
        )->execute(['comment_id' => $commentId, 'note' => 'Automatically hidden after five open reader reports.']);
        log_activity('content_comment_auto_hidden', 'content_comment', $commentId, ['open_reports' => $count]);
    }
    content_interactions_notify_admins('Blog comment reported', $reason, 'portal/admin.php?view=blog&moderation=1', $commentId);
    return ['reported' => true, 'duplicate' => false, 'open_reports' => $count];
}

function content_interactions_save_settings(string $contentType, int $contentId, array $values, int $userId): void
{
    if (!content_interactions_schema_available()) throw new RuntimeException('Content interaction migration is required.');
    $mode = in_array((string)($values['moderation_mode'] ?? ''), ['pre_moderated', 'registered_auto'], true)
        ? (string)$values['moderation_mode'] : 'pre_moderated';
    $closedAt = trim((string)($values['comments_closed_at'] ?? ''));
    if ($closedAt !== '') {
        $closedTimestamp = strtotime($closedAt);
        if ($closedTimestamp === false) throw new RuntimeException('Enter a valid comment closing date.');
        $closedAt = gmdate('Y-m-d H:i:s', $closedTimestamp);
    } else {
        $closedAt = null;
    }
    db()->prepare(
        'INSERT INTO content_interaction_settings
           (content_type,content_id,comments_enabled,replies_enabled,reactions_enabled,moderation_mode,comments_closed_at,updated_by)
         VALUES
           (:content_type,:content_id,:comments_enabled,:replies_enabled,:reactions_enabled,:moderation_mode,:comments_closed_at,:updated_by)
         ON DUPLICATE KEY UPDATE comments_enabled=VALUES(comments_enabled),replies_enabled=VALUES(replies_enabled),
           reactions_enabled=VALUES(reactions_enabled),moderation_mode=VALUES(moderation_mode),
           comments_closed_at=VALUES(comments_closed_at),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()'
    )->execute([
        'content_type' => $contentType,
        'content_id' => $contentId,
        'comments_enabled' => !empty($values['comments_enabled']) ? 1 : 0,
        'replies_enabled' => !empty($values['replies_enabled']) ? 1 : 0,
        'reactions_enabled' => !empty($values['reactions_enabled']) ? 1 : 0,
        'moderation_mode' => $mode,
        'comments_closed_at' => $closedAt,
        'updated_by' => $userId,
    ]);
}

function content_interactions_moderate_comment(int $commentId, string $status, int $moderatorId, string $note = ''): void
{
    if (!in_array($status, ['approved', 'hidden', 'spam', 'deleted'], true)) throw new RuntimeException('Unsupported moderation status.');
    $comment = content_interactions_comment($commentId);
    if (!$comment) throw new RuntimeException('Comment not found.');
    $transitionedToApproved = $status === 'approved' && $comment['status'] !== 'approved';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE content_comments SET status=:status,report_count=0,moderated_at=UTC_TIMESTAMP(),moderated_by=:moderated_by,
               deleted_at=CASE WHEN :deleted_status_at="deleted" THEN UTC_TIMESTAMP() ELSE deleted_at END,
               deleted_by=CASE WHEN :deleted_status_by="deleted" THEN :deleted_by_user ELSE deleted_by END
             WHERE id=:id'
        )->execute([
            'status' => $status,
            'deleted_status_at' => $status,
            'deleted_status_by' => $status,
            'deleted_by_user' => $moderatorId,
            'moderated_by' => $moderatorId,
            'id' => $commentId,
        ]);
        $pdo->prepare(
            'UPDATE content_comment_reports SET status="resolved",resolved_at=UTC_TIMESTAMP(),resolved_by=:resolved_by
             WHERE comment_id=:comment_id AND status="open"'
        )->execute(['resolved_by' => $moderatorId, 'comment_id' => $commentId]);
        $pdo->prepare(
            'INSERT INTO content_moderation_events (comment_id,moderator_user_id,action,note,previous_status,new_status)
             VALUES (:comment_id,:moderator_user_id,:action,:note,:previous_status,:new_status)'
        )->execute([
            'comment_id' => $commentId,
            'moderator_user_id' => $moderatorId,
            'action' => $status,
            'note' => mb_substr(trim($note), 0, 1000) ?: null,
            'previous_status' => (string)$comment['status'],
            'new_status' => $status,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    if ($transitionedToApproved) {
        $approved = content_interactions_comment($commentId);
        if ($approved) {
            content_interactions_notify_participants($approved);
            notification_create(
                (int)$approved['author_user_id'],
                'message',
                'Your Blog comment was approved',
                mb_substr((string)$approved['body'], 0, 240),
                'blog-post.php?slug=' . rawurlencode((string)(content_interactions_blog_post((int)$approved['content_id'], false)['slug'] ?? '')) . '#comment-' . $commentId,
                'content_comment',
                $commentId,
                'normal'
            );
        }
    }
    if (function_exists('federated_interactions_local_comment_event')) {
        if ($status === 'approved') {
            $event = federated_interactions_local_map('comment', (string)$commentId) ? 'Update' : 'Create';
            federated_interactions_local_comment_event($commentId, $event, $moderatorId);
        } elseif ((string)$comment['status'] === 'approved') {
            federated_interactions_local_comment_event($commentId, 'Delete', $moderatorId, $comment);
        }
    }
    log_activity('content_comment_moderated', 'content_comment', $commentId, ['status' => $status]);
}

function content_interactions_admin_counts(): array
{
    if (!content_interactions_schema_available()) return ['pending' => 0, 'approved' => 0, 'reported' => 0, 'reactions' => 0];
    $row = db()->query(
        'SELECT COUNT(CASE WHEN status="pending" THEN 1 END) AS pending,
                COUNT(CASE WHEN status="approved" THEN 1 END) AS approved,
                COUNT(CASE WHEN report_count>0 THEN 1 END) AS reported
         FROM content_comments WHERE content_type="blog_post"'
    )->fetch() ?: [];
    $row['reactions'] = (int)db()->query('SELECT COUNT(*) FROM content_reactions WHERE content_type="blog_post"')->fetchColumn();
    return array_map('intval', $row + ['pending' => 0, 'approved' => 0, 'reported' => 0, 'reactions' => 0]);
}

function content_interactions_moderation_queue(int $limit = 40): array
{
    if (!content_interactions_schema_available()) return [];
    $limit = max(1, min(100, $limit));
    return db()->query(
        'SELECT comment.*,user.display_name AS author_name,post.title AS content_title,post.slug AS content_slug
         FROM content_comments comment
         JOIN users user ON user.id=comment.author_user_id
         LEFT JOIN blog_posts post ON comment.content_type="blog_post" AND post.id=comment.content_id
         WHERE comment.content_type="blog_post" AND (comment.status="pending" OR comment.report_count>0)
         ORDER BY comment.status="pending" DESC,comment.report_count DESC,comment.created_at DESC,comment.id DESC
         LIMIT ' . $limit
    )->fetchAll();
}

function content_interactions_cleanup(string $contentType, int $contentId): void
{
    if (!content_interactions_schema_available()) return;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $commentIds = $pdo->prepare('SELECT id FROM content_comments WHERE content_type=:content_type AND content_id=:content_id');
        $commentIds->execute(['content_type' => $contentType, 'content_id' => $contentId]);
        $ids = array_map('intval', $commentIds->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare('DELETE FROM content_reactions WHERE target_type="comment" AND target_id IN (' . $placeholders . ')')->execute($ids);
        }
        $pdo->prepare('DELETE FROM content_comments WHERE content_type=:content_type AND content_id=:content_id')->execute(['content_type' => $contentType, 'content_id' => $contentId]);
        $pdo->prepare('DELETE FROM content_reactions WHERE target_type="content" AND content_type=:content_type AND target_id=:content_id')->execute(['content_type' => $contentType, 'content_id' => $contentId]);
        $pdo->prepare('DELETE FROM content_interaction_settings WHERE content_type=:content_type AND content_id=:content_id')->execute(['content_type' => $contentType, 'content_id' => $contentId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function content_interactions_comment_markup(array $comment, array $viewer, array $settings): string
{
    $viewerId = (int)($viewer['id'] ?? 0);
    $isOwn = $viewerId > 0 && $viewerId === (int)$comment['author_user_id'];
    $isAdmin = ($viewer['role'] ?? '') === 'admin';
    $status = (string)$comment['status'];
    $deleted = $status === 'deleted';
    $body = $deleted ? '<em>Comment deleted.</em>' : content_interactions_render_text((string)$comment['body']);
    $html = '<article class="content-comment status-' . e($status) . '" id="comment-' . (int)$comment['id'] . '" data-comment-id="' . (int)$comment['id'] . '">';
    $html .= '<header><strong>' . e((string)$comment['author_name']) . '</strong><time datetime="' . e((string)$comment['created_at']) . '">' . e(format_datetime((string)$comment['created_at'])) . '</time>';
    if ($status !== 'approved') $html .= '<span class="content-comment-status">' . e(status_label($status)) . '</span>';
    $html .= '</header><div class="content-comment-body">' . $body . '</div>';
    if (!$deleted) {
        $html .= '<footer>';
        if ($status === 'approved') {
            $html .= '<div class="content-comment-reactions" data-reaction-target="comment" data-target-id="' . (int)$comment['id'] . '">';
            foreach (content_interactions_reaction_types() as $type => $meta) {
                $active = $comment['viewer_reaction'] === $type;
                $html .= '<button type="button" data-content-reaction="' . e($type) . '" class="' . ($active ? 'active' : '') . '" aria-pressed="' . ($active ? 'true' : 'false') . '"><span>' . e($meta['icon']) . '</span><b data-reaction-count="' . e($type) . '">' . (int)($comment['reactions'][$type] ?? 0) . '</b></button>';
            }
            $html .= '</div>';
        }
        $html .= '<div class="content-comment-actions">';
        if ($viewerId > 0 && (int)$comment['depth'] === 0 && (int)$settings['replies_enabled'] && $status === 'approved') $html .= '<button type="button" data-comment-reply-toggle>Reply</button>';
        if ($isOwn || $isAdmin) {
            if (content_interactions_can_edit($comment, $viewer)) $html .= '<button type="button" data-comment-edit data-comment-body="' . e((string)$comment['body']) . '">Edit</button>';
            $html .= '<button type="button" data-comment-delete>Delete</button>';
        } elseif ($viewerId > 0 && $status === 'approved') {
            $html .= '<button type="button" data-comment-report>Report</button>';
        }
        $html .= '</div></footer>';
        if ($viewerId > 0 && (int)$comment['depth'] === 0 && (int)$settings['replies_enabled'] && $status === 'approved') {
            $html .= '<form class="content-reply-form" data-comment-form data-parent-id="' . (int)$comment['id'] . '" hidden><textarea maxlength="4000" required placeholder="Write a reply"></textarea><div><button type="submit">Submit reply</button><button type="button" data-comment-reply-toggle>Cancel</button></div></form>';
        }
    }
    if (!empty($comment['replies'])) {
        $html .= '<div class="content-comment-replies">';
        foreach ($comment['replies'] as $reply) $html .= content_interactions_comment_markup($reply, $viewer, $settings);
        $html .= '</div>';
    }
    return $html . '</article>';
}

function content_interactions_render_public(array $post, ?array $viewer, array $context): void
{
    $viewer = $viewer ?: [];
    $settings = $context['settings'];
    $types = content_interactions_reaction_types();
    if (!$context['schema_ready']) {
        echo '<section class="content-interactions"><div class="content-interaction-unavailable">Comments and reactions are being configured.</div></section>';
        return;
    }
    ?>
    <section class="content-interactions" data-content-interactions data-api="<?=e(app_url('content-interactions-api.php'))?>" data-csrf="<?=e(csrf_token())?>" data-content-type="blog_post" data-content-id="<?=(int)$post['id']?>" data-authenticated="<?=$viewer?'1':'0'?>">
        <?php if((int)$settings['reactions_enabled']):?>
        <div class="content-reaction-bar" data-reaction-target="content" data-target-id="<?=(int)$post['id']?>">
            <div><span>React to this article</span><strong data-total-reactions><?=array_sum($context['reactions'])?></strong></div>
            <div><?php foreach($types as $type=>$meta):?><?php $active=$context['viewer_reaction']===$type;?><button type="button" data-content-reaction="<?=e($type)?>" class="<?=$active?'active':''?>" aria-pressed="<?=$active?'true':'false'?>"><span><?=e($meta['icon'])?></span><?=e($meta['label'])?><b data-reaction-count="<?=e($type)?>"><?=(int)$context['reactions'][$type]?></b></button><?php endforeach;?></div>
        </div>
        <?php endif;?>
        <header class="content-comments-header"><div><span>Conversation</span><h2><?=$context['comment_count']?> comment<?=$context['comment_count']===1?'':'s'?></h2></div></header>
        <?php if(!(int)$settings['comments_enabled']):?><p class="content-comments-closed">Comments are disabled for this article.</p>
        <?php elseif($settings['comments_closed_at']&&strtotime((string)$settings['comments_closed_at'])<=time()):?><p class="content-comments-closed">This conversation is closed.</p>
        <?php elseif(!$viewer):?><div class="content-comment-signin"><p>Sign in with your portal account to react, comment, or reply. Anonymous posting is disabled.</p><a href="<?=e(app_url('portal/login.php'))?>">Sign in</a></div>
        <?php else:?><form class="content-comment-form" data-comment-form data-parent-id="0"><label for="content-comment-body">Add to the conversation</label><textarea id="content-comment-body" maxlength="4000" required placeholder="Write a thoughtful comment"></textarea><div><button type="submit">Submit comment</button><small><?=e($settings['moderation_mode']==='pre_moderated'?'Comments are reviewed before publication.':'Registered comments publish immediately.')?></small></div></form><?php endif;?>
        <div class="content-comment-status-message" data-interaction-status role="status" aria-live="polite"></div>
        <div class="content-comments-list"><?php foreach($context['comments'] as $comment) echo content_interactions_comment_markup($comment,$viewer,$settings);?><?php if(!$context['comments']):?><p class="content-comments-empty">No comments yet. Start the conversation.</p><?php endif;?></div>
    </section>
    <?php
    if (function_exists('federated_interactions_render_public')) {
        federated_interactions_render_public($post);
    }
}

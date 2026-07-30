from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]

def read(path): return (ROOT/path).read_text(encoding='utf-8')
def write(path,content): (ROOT/path).write_text(content,encoding='utf-8')
def replace_once(path,old,new):
    source=read(path);count=source.count(old)
    if count!=1: raise SystemExit(f'Expected one match in {path}, found {count}: {old[:140]!r}')
    write(path,source.replace(old,new,1))

replace_once('portal/content-interactions.php',
'''         ORDER BY COALESCE(comment.parent_id,comment.id),comment.depth,comment.created_at,comment.id'
    );''',
'''         ORDER BY COALESCE(comment.parent_id,comment.id),comment.depth,comment.created_at,comment.id
         LIMIT 500'
    );''')
replace_once('portal/content-interactions.php',
'''    $reactionMap = [];
    if ($ids) {''',
'''    $reactionMap = [];
    $viewerReactionMap = [];
    if ($ids) {''')
replace_once('portal/content-interactions.php',
'''        foreach ($reaction->fetchAll() as $row) {
            $reactionMap[(int)$row['target_id']][(string)$row['reaction_type']] = (int)$row['total'];
        }
    }
    $roots = [];''',
'''        foreach ($reaction->fetchAll() as $row) {
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
    $roots = [];''')
replace_once('portal/content-interactions.php',
'''        $row['viewer_reaction'] = $viewerId > 0
            ? content_interactions_viewer_reaction($viewerId, 'comment', $contentType, (int)$row['id'])
            : '';''',
'''        $row['viewer_reaction'] = $viewerReactionMap[(int)$row['id']] ?? '';''')
replace_once('portal/content-interactions.php',
'''    if (($user['role'] ?? '') === 'admin') return true;
    if ((int)($comment['author_user_id'] ?? 0) !== (int)($user['id'] ?? 0)) return false;
    if (($comment['status'] ?? '') === 'pending') return true;
    $created = strtotime((string)($comment['created_at'] ?? '')) ?: 0;''',
'''    if (($user['role'] ?? '') === 'admin') return true;
    if ((int)($comment['author_user_id'] ?? 0) !== (int)($user['id'] ?? 0)) return false;
    $status = (string)($comment['status'] ?? '');
    if (!in_array($status, ['pending', 'approved'], true)) return false;
    if ($status === 'pending') return true;
    $created = strtotime((string)($comment['created_at'] ?? '')) ?: 0;''')

replace_once('portal/content-interactions.php',
'''function content_interactions_create_comment(
''',
'''function content_interactions_notify_reaction(
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
''')
replace_once('portal/content-interactions.php',
'''    $existing = content_interactions_viewer_reaction($userId, $targetType, $contentType, $targetId);
    if ($existing === $reactionType) {''',
'''    $existing = content_interactions_viewer_reaction($userId, $targetType, $contentType, $targetId);
    $isFirstReaction = $existing === '';
    if ($existing === $reactionType) {''')
replace_once('portal/content-interactions.php',
'''        $active = $reactionType;
    }
    return ['active' => $active, 'counts' => content_interactions_reaction_summary($targetType, $contentType, $targetId)];''',
'''        $active = $reactionType;
        if ($isFirstReaction) {
            content_interactions_notify_reaction($userId, $targetType, $contentType, $targetId, $reactionType);
        }
    }
    return ['active' => $active, 'counts' => content_interactions_reaction_summary($targetType, $contentType, $targetId)];''')

replace_once('portal/content-interactions.php',
'''    $statement = db()->prepare(
        'INSERT IGNORE INTO content_comment_reports (comment_id,reporter_user_id,reason)
         VALUES (:comment_id,:reporter_user_id,:reason)'
    );
    $statement->execute(['comment_id' => $commentId, 'reporter_user_id' => $userId, 'reason' => $reason]);
    if ($statement->rowCount() > 0) {
        db()->prepare('UPDATE content_comments SET report_count=report_count+1 WHERE id=:id')->execute(['id' => $commentId]);
        $count = (int)db()->query('SELECT report_count FROM content_comments WHERE id=' . $commentId)->fetchColumn();
        if ($count >= 5) {
            db()->prepare('UPDATE content_comments SET status="hidden" WHERE id=:id AND status="approved"')->execute(['id' => $commentId]);
        }
        content_interactions_notify_admins('Blog comment reported', $reason, 'portal/admin.php?view=blog&moderation=1', $commentId);
    }
    return ['reported' => true];''',
'''    $existing = db()->prepare(
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
    return ['reported' => true, 'duplicate' => false, 'open_reports' => $count];''')

old_moderate='''function content_interactions_moderate_comment(int $commentId, string $status, int $moderatorId, string $note = ''): void
{
    if (!in_array($status, ['approved', 'hidden', 'spam', 'deleted'], true)) throw new RuntimeException('Unsupported moderation status.');
    $comment = content_interactions_comment($commentId);
    if (!$comment) throw new RuntimeException('Comment not found.');
    db()->prepare(
        'UPDATE content_comments SET status=:status,moderated_at=UTC_TIMESTAMP(),moderated_by=:moderated_by,
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
    db()->prepare(
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
    if ($status === 'approved') {
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
    log_activity('content_comment_moderated', 'content_comment', $commentId, ['status' => $status]);
}'''
new_moderate='''function content_interactions_moderate_comment(int $commentId, string $status, int $moderatorId, string $note = ''): void
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
    log_activity('content_comment_moderated', 'content_comment', $commentId, ['status' => $status]);
}'''
replace_once('portal/content-interactions.php',old_moderate,new_moderate)

old_markup='''    if (!$deleted) {
        $html .= '<footer><div class="content-comment-reactions" data-reaction-target="comment" data-target-id="' . (int)$comment['id'] . '">';
        foreach (content_interactions_reaction_types() as $type => $meta) {
            $active = $comment['viewer_reaction'] === $type;
            $html .= '<button type="button" data-content-reaction="' . e($type) . '" class="' . ($active ? 'active' : '') . '" aria-pressed="' . ($active ? 'true' : 'false') . '"><span>' . e($meta['icon']) . '</span><b data-reaction-count="' . e($type) . '">' . (int)($comment['reactions'][$type] ?? 0) . '</b></button>';
        }
        $html .= '</div><div class="content-comment-actions">';
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
    }'''
new_markup='''    if (!$deleted) {
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
    }'''
replace_once('portal/content-interactions.php',old_markup,new_markup)

replace_once('content-interactions-api.php',
'''    json_response(['ok' => false, 'message' => 'Unsupported interaction action.'], 400);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 422);
}''',
'''    json_response(['ok' => false, 'message' => 'Unsupported interaction action.'], 400);
} catch (RuntimeException $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log('Content interaction API failure: ' . $exception->getMessage());
    json_response(['ok' => false, 'message' => 'The interaction could not be saved.'], 500);
}''')

for path in ['database/content_interactions_v66c.sql','database/north_mountain_portal.sql']:
    replace_once(path,
'''    reason VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_comment_reporter (comment_id,reporter_user_id),
    KEY idx_content_comment_reports_created (created_at,id),
    KEY idx_content_comment_reports_reporter (reporter_user_id,created_at,id),
    CONSTRAINT fk_content_comment_reports_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comment_reports_user FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE''',
'''    reason VARCHAR(1000) NOT NULL,
    status ENUM('open','resolved') NOT NULL DEFAULT 'open',
    resolved_at DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_comment_reporter (comment_id,reporter_user_id),
    KEY idx_content_comment_reports_status (status,created_at,id),
    KEY idx_content_comment_reports_reporter (reporter_user_id,created_at,id),
    KEY idx_content_comment_reports_resolved_by (resolved_by),
    CONSTRAINT fk_content_comment_reports_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comment_reports_user FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comment_reports_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL''')
    replace_once(path,
'''    moderator_user_id BIGINT UNSIGNED NOT NULL,
    action ENUM('approved','hidden','spam','deleted') NOT NULL,''',
'''    moderator_user_id BIGINT UNSIGNED NULL,
    action ENUM('approved','hidden','spam','deleted','auto_hidden') NOT NULL,''')
    replace_once(path,
'''    CONSTRAINT fk_content_moderation_user FOREIGN KEY (moderator_user_id) REFERENCES users(id) ON DELETE CASCADE''',
'''    CONSTRAINT fk_content_moderation_user FOREIGN KEY (moderator_user_id) REFERENCES users(id) ON DELETE SET NULL''')

replace_once('tests/content-interactions-v66c.php',
'''if (!content_interactions_can_edit(['author_user_id'=>7,'status'=>'approved','created_at'=>'2020-01-01 00:00:00'], ['id'=>1,'role'=>'admin'])) { fwrite(STDERR, "Administrator edit override failed.\n"); exit(1); }''',
'''if (!content_interactions_can_edit(['author_user_id'=>7,'status'=>'approved','created_at'=>'2020-01-01 00:00:00'], ['id'=>1,'role'=>'admin'])) { fwrite(STDERR, "Administrator edit override failed.\n"); exit(1); }
if (content_interactions_can_edit(['author_user_id'=>7,'status'=>'hidden','created_at'=>$now], ['id'=>7,'role'=>'client'])) { fwrite(STDERR, "Hidden comment edit bypass detected.\n"); exit(1); }
if (content_interactions_can_edit(['author_user_id'=>7,'status'=>'spam','created_at'=>$now], ['id'=>7,'role'=>'client'])) { fwrite(STDERR, "Spam comment edit bypass detected.\n"); exit(1); }''')
replace_once('tests/content-interactions-v66c.php',
''' ['participant notifications','content_interactions_notify_participants',$source['core']],''',
''' ['participant notifications','content_interactions_notify_participants',$source['core']],
 ['reaction notifications','content_interactions_notify_reaction',$source['core']],
 ['batch viewer reactions','$viewerReactionMap',$source['core']],
 ['resolved report evidence','status="resolved"',$source['core']],
 ['automatic moderation evidence','auto_hidden',$source['core'].$source['migration']],
 ['approved-only reaction controls',"$status === 'approved'",$source['core']],
 ['generic internal error boundary','Content interaction API failure',$source['api']],''')
replace_once('tests/content-interactions-v66c.php',
''' ['reports','content_comment_reports',$source['core'].$source['migration']],''',
''' ['reports','content_comment_reports',$source['core'].$source['migration']],
 ['report resolution fields','resolved_at',$source['migration'].$source['schema']],''')

replace_once('V66C-SCORECARD.md',
'''- Notifications for pending moderation, approvals, post comments, and replies.''',
'''- Notifications for pending moderation, approvals, post comments, replies, and first reactions.''')
replace_once('V66C-SCORECARD.md',
'''- Reader reports, unique reporter enforcement, and five-report automatic hiding.''',
'''- Reader reports, unique reporter enforcement, durable resolution evidence, and five-report automatic hiding.''')

print('Content Interactions v66C hardening fixes applied.')

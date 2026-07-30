from pathlib import Path

path = Path(__file__).resolve().parents[1] / 'portal/unified-inbox.php'
text = path.read_text(encoding='utf-8')

def replace_once(old, new, label):
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'Expected one {label} boundary, found {count}')
    text = text.replace(old, new, 1)

replace_once(
    'function unified_inbox_comment_items(): array',
    'function unified_inbox_comment_items(int $userId): array',
    'comment adapter signature'
)
replace_once(
'''    try {
        $rows = db()->query(
            'SELECT comment.id,comment.parent_id,comment.body,comment.status,comment.report_count,
                    comment.created_at,comment.updated_at,user.display_name AS author_name,
                    post.title AS post_title,post.slug AS post_slug
             FROM content_comments comment
             JOIN users user ON user.id=comment.author_user_id
             LEFT JOIN blog_posts post ON post.id=comment.content_id
             WHERE comment.content_type="blog_post" AND comment.status<>"deleted"
             ORDER BY COALESCE(comment.updated_at,comment.created_at) DESC,comment.id DESC
             LIMIT 150'
        )->fetchAll();
    } catch (Throwable) {''',
'''    try {
        $statement = db()->prepare(
            'SELECT comment.id,comment.parent_id,comment.body,comment.status,comment.report_count,
                    comment.created_at,comment.updated_at,user.display_name AS author_name,
                    post.title AS post_title,post.slug AS post_slug,
                    (SELECT COUNT(*) FROM portal_notifications notification
                     WHERE notification.recipient_user_id=:viewer_id
                       AND notification.entity_type="content_comment"
                       AND notification.entity_id=comment.id
                       AND notification.is_read=0) AS notification_unread
             FROM content_comments comment
             JOIN users user ON user.id=comment.author_user_id
             LEFT JOIN blog_posts post ON post.id=comment.content_id
             WHERE comment.content_type="blog_post" AND comment.status<>"deleted"
             ORDER BY COALESCE(comment.updated_at,comment.created_at) DESC,comment.id DESC
             LIMIT 150'
        );
        $statement->execute(['viewer_id' => $userId]);
        $rows = $statement->fetchAll();
    } catch (Throwable) {''',
    'comment query'
)
replace_once(
    "            'native_unread' => $pending || $reported,",
    "            'native_unread' => $pending || $reported || (int)($row['notification_unread'] ?? 0) > 0,",
    'comment unread state'
)
replace_once(
    '        unified_inbox_comment_items(),',
    "        unified_inbox_comment_items((int)$user['id']),",
    'comment adapter call'
)
path.write_text(text, encoding='utf-8')
print('Unified Inbox Blog comment unread-state repair applied.')

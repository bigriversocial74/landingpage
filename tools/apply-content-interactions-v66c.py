from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]

def read(path): return (ROOT/path).read_text(encoding='utf-8')
def write(path,content): (ROOT/path).write_text(content,encoding='utf-8')
def replace_once(path,old,new):
    source=read(path);count=source.count(old)
    if count!=1: raise SystemExit(f'Expected one match in {path}, found {count}: {old[:140]!r}')
    write(path,source.replace(old,new,1))

def append_once(path,marker,content):
    source=read(path)
    if marker in source: raise SystemExit(f'Marker already exists in {path}: {marker}')
    write(path,source.rstrip()+'\n\n'+content.rstrip()+'\n')

replace_once('portal/content-interactions.php',
    "    return $defaults + ($statement->fetch() ?: []);",
    "    return array_replace($defaults, $statement->fetch() ?: []);")
replace_once('portal/content-interactions.php',
    "function content_interactions_validate_comment_body(int $userId, string $body): string",
    "function content_interactions_validate_comment_body(int $userId, string $body, int $exclude_comment_id = 0): string")
replace_once('portal/content-interactions.php',
    '''         WHERE author_user_id=:user_id AND body_hash=:body_hash
           AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)
           AND status<>"deleted" LIMIT 1'
    );
    $duplicate->execute(['user_id' => $userId, 'body_hash' => $hash]);''',
    '''         WHERE author_user_id=:user_id AND body_hash=:body_hash
           AND id<>:exclude_comment_id
           AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)
           AND status<>"deleted" LIMIT 1'
    );
    $duplicate->execute(['user_id' => $userId, 'body_hash' => $hash, 'exclude_comment_id' => $exclude_comment_id]);''')
replace_once('portal/content-interactions.php',
    "    $body = content_interactions_validate_comment_body((int)$user['id'], $body);",
    "    $body = content_interactions_validate_comment_body((int)$user['id'], $body, $commentId);")
replace_once('portal/content-interactions.php',
    '''           deleted_at=CASE WHEN :status_deleted="deleted" THEN UTC_TIMESTAMP() ELSE deleted_at END,
           deleted_by=CASE WHEN :status_deleted="deleted" THEN :moderated_by ELSE deleted_by END
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'status_deleted' => $status,
        'moderated_by' => $moderatorId,
        'id' => $commentId,
    ]);''',
    '''           deleted_at=CASE WHEN :deleted_status_at="deleted" THEN UTC_TIMESTAMP() ELSE deleted_at END,
           deleted_by=CASE WHEN :deleted_status_by="deleted" THEN :deleted_by_user ELSE deleted_by END
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'deleted_status_at' => $status,
        'deleted_status_by' => $status,
        'deleted_by_user' => $moderatorId,
        'moderated_by' => $moderatorId,
        'id' => $commentId,
    ]);''')
replace_once('portal/content-interactions.php',
    '''    $closedAt = trim((string)($values['comments_closed_at'] ?? ''));
    $closedAt = $closedAt !== '' ? date('Y-m-d H:i:s', strtotime($closedAt) ?: time()) : null;''',
    '''    $closedAt = trim((string)($values['comments_closed_at'] ?? ''));
    if ($closedAt !== '') {
        $closedTimestamp = strtotime($closedAt);
        if ($closedTimestamp === false) throw new RuntimeException('Enter a valid comment closing date.');
        $closedAt = gmdate('Y-m-d H:i:s', $closedTimestamp);
    } else {
        $closedAt = null;
    }''')
replace_once('portal/content-interactions.php',
    '''    ?>
    <section class="content-interactions" data-content-interactions data-api="<?=e(app_url('content-interactions-api.php'))?>" data-csrf="<?=e(csrf_token())?>" data-content-type="blog_post" data-content-id="<?=(int)$post['id']?>" data-authenticated="<?=$viewer?'1':'0'?>">
        <?php if(!$context['schema_ready']):?><div class="content-interaction-unavailable">Comments and reactions are being configured.</div><?php return; endif;?>''',
    '''    if (!$context['schema_ready']) {
        echo '<section class="content-interactions"><div class="content-interaction-unavailable">Comments and reactions are being configured.</div></section>';
        return;
    }
    ?>
    <section class="content-interactions" data-content-interactions data-api="<?=e(app_url('content-interactions-api.php'))?>" data-csrf="<?=e(csrf_token())?>" data-content-type="blog_post" data-content-id="<?=(int)$post['id']?>" data-authenticated="<?=$viewer?'1':'0'?>">''')

replace_once('content-interactions-api.php',
    '''$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);

try {''',
    '''$ip = client_ip();

$enforceLimit = static function(string $actionKey, string $identity, int $limit, int $window): void {
    if (rate_limit_exceeded($actionKey, $identity, $limit, $window)) {
        throw new RuntimeException('Too many interaction requests. Try again later.');
    }
};

try {''')
for old,new in [
("        rate_limit('content-comment-user:' . $userId, 8, 300);","        $enforceLimit('content_comment_user', (string)$userId, 8, 300);"),
("        rate_limit('content-comment-ip:' . $ip, 20, 3600);","        $enforceLimit('content_comment_ip', $ip, 20, 3600);"),
("        rate_limit('content-comment-edit:' . $userId, 12, 600);","        $enforceLimit('content_comment_edit', (string)$userId, 12, 600);"),
("        rate_limit('content-reaction-user:' . $userId, 60, 3600);","        $enforceLimit('content_reaction_user', (string)$userId, 60, 3600);"),
("        rate_limit('content-report-user:' . $userId, 10, 86400);","        $enforceLimit('content_report_user', (string)$userId, 10, 86400);")]: replace_once('content-interactions-api.php',old,new)

replace_once('blog-post.php',
    "require_once __DIR__ . '/portal/public-music-shell.php';",
    "require_once __DIR__ . '/portal/public-music-shell.php';\nrequire_once __DIR__ . '/portal/content-interactions.php';")
replace_once('blog-post.php',
    "$viewer = $previewRequested ? current_user() : null;",
    "$viewer = current_user();")
replace_once('blog-post.php',
    "$shell = music_public_shell_context();",
    "$shell = music_public_shell_context();\n$interactionContext = $post && !$isAdminPreview\n    ? content_interactions_context('blog_post', (int)$post['id'], $viewer)\n    : ['schema_ready'=>false,'settings'=>[],'comments'=>[],'comment_count'=>0,'reactions'=>[],'viewer_reaction'=>''];")
replace_once('blog-post.php',
    "        'hasPart' => blog_rich_media_structured_objects((string)$post['body']) ?: null,",
    "        'hasPart' => blog_rich_media_structured_objects((string)$post['body']) ?: null,\n        'commentCount' => (int)$interactionContext['comment_count'],\n        'interactionStatistic' => [\n            '@type' => 'InteractionCounter',\n            'interactionType' => 'https://schema.org/LikeAction',\n            'userInteractionCount' => array_sum($interactionContext['reactions']),\n        ],")
replace_once('blog-post.php',
    '<link rel="stylesheet" href="<?=e(app_url(\'assets/css/blog-rich-media.css?v=20260730-v66A\'))?>">',
    '<link rel="stylesheet" href="<?=e(app_url(\'assets/css/blog-rich-media.css?v=20260730-v66A\'))?>">\n<link rel="stylesheet" href="<?=e(app_url(\'assets/css/content-interactions.css?v=20260730-v66C\'))?>">')
replace_once('blog-post.php',
    '''<footer class="blog-post-footer">
<a href="<?=e(app_url('blog.php'))?>">← Back to Blog</a>
</footer>''',
    '''<?php if(!$isAdminPreview):?><?php content_interactions_render_public($post,$viewer,$interactionContext);?><?php endif;?>

<footer class="blog-post-footer">
<a href="<?=e(app_url('blog.php'))?>">← Back to Blog</a>
</footer>''')
replace_once('blog-post.php',
    '<script src="<?=e(app_url(\'assets/js/blog-rich-media.js?v=20260730-v66A\'))?>"></script>',
    '<script src="<?=e(app_url(\'assets/js/blog-rich-media.js?v=20260730-v66A\'))?>"></script>\n<script src="<?=e(app_url(\'assets/js/content-interactions.js?v=20260730-v66C\'))?>"></script>')

replace_once('portal/publishing-admin.php',
    "/* North Mountain Media build: 20260727-site-controls-landing-v60 */",
    "/* North Mountain Media build: 20260730-content-interactions-v66C */\n\nrequire_once __DIR__ . '/content-interactions-admin.php';")
replace_once('portal/publishing-admin.php',
    "        'restore_blog_revision',",
    "        'restore_blog_revision',\n        'save_content_interaction_settings',\n        'moderate_content_comment',")
replace_once('portal/publishing-admin.php',
    '''    if (!publishing_schema_available()) {
        throw new RuntimeException(''',
    '''    if (!publishing_schema_available()) {
        throw new RuntimeException(''')
replace_once('portal/publishing-admin.php',
    '''    }

    if ($action === 'save_blog_post') {''',
    '''    }

    if (content_interactions_handle_admin_action($action, $user)) {
        return true;
    }

    if ($action === 'save_blog_post') {''')
replace_once('portal/publishing-admin.php',
    '''        db()->prepare(
            'DELETE FROM blog_posts
             WHERE id=:id'
        )->execute(['id' => $id]);''',
    '''        content_interactions_cleanup('blog_post', $id);
        db()->prepare(
            'DELETE FROM blog_posts
             WHERE id=:id'
        )->execute(['id' => $id]);''')
replace_once('portal/publishing-admin.php',
    "<?php publishing_render_analytics_panel('blog',30);?>",
    "<?php publishing_render_analytics_panel('blog',30);?>\n<?php content_interactions_render_admin_summary();?>")
replace_once('portal/publishing-admin.php',
    '''<?php if($selected):?>
<?php publishing_render_autosave_banner(
    $selected,
    'blog'
);?>
<?php endif;?>''',
    '''<?php if($selected):?>
<?php publishing_render_autosave_banner(
    $selected,
    'blog'
);?>
<?php content_interactions_render_post_settings($selected);?>
<?php endif;?>''')

replace_once('portal/publishing-workflow-view.php',
    '<link rel="stylesheet" href="<?=e(app_url(\'assets/css/blog-rich-media.css?v=20260730-v66A\'))?>">',
    '<link rel="stylesheet" href="<?=e(app_url(\'assets/css/blog-rich-media.css?v=20260730-v66A\'))?>">\n<link rel="stylesheet" href="<?=e(app_url(\'assets/css/content-interactions.css?v=20260730-v66C\'))?>">')

migration=read('database/content_interactions_v66c.sql')
start=migration.index('CREATE TABLE IF NOT EXISTS content_interaction_settings')
end=migration.index("SELECT 'North Mountain Media Content Interactions")
append_once('database/north_mountain_portal.sql','-- Content Interactions v66C','-- Content Interactions v66C\n'+migration[start:end].strip())

print('Content Interactions v66C integration applied.')

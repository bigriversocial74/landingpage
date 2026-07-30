from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8')


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected one anchor, found {count}')
    return content.replace(old, new, 1)


# Apache well-known federation discovery and cache handling.
htaccess = read('.htaccess')
htaccess = replace_once(
    htaccess,
    '''    # POD Protocol 1 public identity discovery.
    RewriteRule ^\\.well-known/pod\\.json$ pod-discovery.php [L,NC]
''',
    '''    # POD Protocol 1 public identity discovery.
    RewriteRule ^\\.well-known/pod\\.json$ pod-discovery.php [L,NC]

    # ActivityPub and fediverse discovery.
    RewriteRule ^\\.well-known/webfinger$ webfinger.php [L,NC,QSA]
    RewriteRule ^\\.well-known/nodeinfo$ nodeinfo-discovery.php [L,NC]
''',
    'well-known federation routes',
)
htaccess = replace_once(
    htaccess,
    '''    <FilesMatch "^(blog-feed|sitemap|events-calendar|event-cover|pod-discovery)\\.php$">
''',
    '''    <FilesMatch "^(blog-feed|sitemap|events-calendar|event-cover|pod-discovery|webfinger|nodeinfo|nodeinfo-discovery|activitypub-actor|activitypub-outbox|activitypub-followers|activitypub-following|activitypub-activity|activitypub-object)\\.php$">
''',
    'public federation cache surfaces',
)
write('.htaccess', htaccess)

# Portal routing, actions, rendering, navigation, and CSS.
admin = read('portal/admin.php')
admin = replace_once(
    admin,
    "require_once __DIR__ . '/syndication-admin.php';\n",
    "require_once __DIR__ . '/syndication-admin.php';\nrequire_once __DIR__ . '/activitypub-admin.php';\n",
    'admin federation dependency',
)
admin = replace_once(
    admin,
    "'blog','syndication','events'",
    "'blog','syndication','federation','events'",
    'admin federation route',
)
admin = replace_once(
    admin,
    '''    try{
        if(syndication_handle_admin_action($action,$user)){
''',
    '''    try{
        if(activitypub_handle_admin_action($action,$user)){
            exit;
        }
        if(syndication_handle_admin_action($action,$user)){
''',
    'admin federation action router',
)
admin = replace_once(
    admin,
    '''if($view==='syndication'){
    syndication_render_admin($user);
    portal_footer();
    exit;
}

if($view==='feeds'){
''',
    '''if($view==='syndication'){
    syndication_render_admin($user);
    portal_footer();
    exit;
}

if($view==='federation'){
    activitypub_render_admin($user);
    portal_footer();
    exit;
}

if($view==='feeds'){
''',
    'admin federation rendering',
)
write('portal/admin.php', admin)

bootstrap = read('portal/bootstrap.php')
bootstrap = replace_once(
    bootstrap,
    "            'syndication' => 'Syndication',\n            'feeds' => 'Feed Reader',\n",
    "            'syndication' => 'Syndication',\n            'federation' => 'Federation',\n            'feeds' => 'Feed Reader',\n",
    'portal federation navigation',
)
bootstrap = replace_once(
    bootstrap,
    '''    <?php if($active==='syndication'):?><link rel="stylesheet" href="<?= e(app_url('assets/css/syndication-admin.css?v=20260730-v66E')) ?>"><?php endif;?>
''',
    '''    <?php if($active==='syndication'):?><link rel="stylesheet" href="<?= e(app_url('assets/css/syndication-admin.css?v=20260730-v66E')) ?>"><?php endif;?>
    <?php if($active==='federation'):?><link rel="stylesheet" href="<?= e(app_url('assets/css/activitypub-admin.css?v=20260730-v66F')) ?>"><?php endif;?>
''',
    'portal federation stylesheet',
)
write('portal/bootstrap.php', bootstrap)

# Blog publication lifecycle hooks.
publishing = read('portal/publishing-admin.php')
publishing = replace_once(
    publishing,
    "require_once __DIR__ . '/websub-service.php';\n",
    "require_once __DIR__ . '/websub-service.php';\nrequire_once __DIR__ . '/activitypub-service.php';\n",
    'publishing federation dependency',
)
publishing = replace_once(
    publishing,
    '''    if ($action === 'save_blog_post') {
        $id = int_input('id');
        $title = input('title');
''',
    '''    if ($action === 'save_blog_post') {
        $id = int_input('id');
        $existingPost = $id > 0 ? blog_admin_post($id) : null;
        $title = input('title');
''',
    'blog existing post snapshot',
)
publishing = replace_once(
    publishing,
    '''        if ($status === 'published') {
            syndication_queue_websub(
                $event === 'blog_post_created' ? 'publish' : 'update',
                (int)$user['id'],
                $id
            );
        }
        flash('success', $message);
''',
    '''        if ($status === 'published') {
            syndication_queue_websub(
                $event === 'blog_post_created' ? 'publish' : 'update',
                (int)$user['id'],
                $id
            );
            activitypub_blog_event(
                $id,
                $existingPost && (string)$existingPost['status'] === 'published'
                    ? 'Update'
                    : 'Create',
                (int)$user['id']
            );
        } elseif ($existingPost && (string)$existingPost['status'] === 'published') {
            activitypub_blog_event($id, 'Delete', (int)$user['id'], $existingPost);
        }
        flash('success', $message);
''',
    'blog federation save hook',
)
publishing = replace_once(
    publishing,
    '''    if ($action === 'archive_blog_post') {
        $id = int_input('id');

        db()->prepare(
''',
    '''    if ($action === 'archive_blog_post') {
        $id = int_input('id');
        $existingPost = blog_admin_post($id);

        db()->prepare(
''',
    'blog archive snapshot',
)
publishing = replace_once(
    publishing,
    '''        log_activity(
            'blog_post_archived',
            'blog_post',
            $id
        );
        flash('success', 'Blog post archived.');
''',
    '''        log_activity(
            'blog_post_archived',
            'blog_post',
            $id
        );
        if ($existingPost && (string)$existingPost['status'] === 'published') {
            activitypub_blog_event($id, 'Delete', (int)$user['id'], $existingPost);
        }
        flash('success', 'Blog post archived.');
''',
    'blog federation archive hook',
)
publishing = replace_once(
    publishing,
    '''        content_interactions_cleanup('blog_post', $id);
        db()->prepare(
''',
    '''        if ((string)($post['status'] ?? '') === 'published') {
            activitypub_blog_event($id, 'Delete', (int)$user['id'], $post);
        }
        content_interactions_cleanup('blog_post', $id);
        db()->prepare(
''',
    'blog federation delete hook',
)
publishing = replace_once(
    publishing,
    '''            'SELECT id,title
             FROM blog_posts
''',
    '''            'SELECT post.*,user.display_name AS author_name
             FROM blog_posts post
             LEFT JOIN users user ON user.id=post.author_user_id
''',
    'blog delete full snapshot query',
)
publishing = replace_once(
    publishing,
    '''             WHERE id=:id
''',
    '''             WHERE post.id=:id
''',
    'blog delete snapshot query identifier',
)
write('portal/publishing-admin.php', publishing)

# Public ActivityPub discovery metadata.
blog = read('blog.php')
blog = replace_once(
    blog,
    "require_once __DIR__ . '/portal/public-syndication.php';\n",
    "require_once __DIR__ . '/portal/public-syndication.php';\nrequire_once __DIR__ . '/portal/activitypub.php';\n",
    'blog ActivityPub dependency',
)
blog = replace_once(
    blog,
    "<?=syndication_discovery_links(['category'=>$category,'tag'=>'','author'=>''])?>\n",
    "<?=syndication_discovery_links(['category'=>$category,'tag'=>'','author'=>''])?>\n<?=activitypub_discovery_links()?>\n",
    'blog ActivityPub discovery link',
)
write('blog.php', blog)

post = read('blog-post.php')
post = replace_once(
    post,
    "require_once __DIR__ . '/portal/webmention-service.php';\n",
    "require_once __DIR__ . '/portal/webmention-service.php';\nrequire_once __DIR__ . '/portal/activitypub.php';\n",
    'post ActivityPub dependency',
)
post = replace_once(
    post,
    "<?=syndication_discovery_links(['category'=>'','tag'=>'','author'=>''], !$isAdminPreview)?>\n",
    "<?=syndication_discovery_links(['category'=>'','tag'=>'','author'=>''], !$isAdminPreview)?>\n<?php if(!$isAdminPreview):?><?=activitypub_discovery_links()?><?php endif;?>\n",
    'post ActivityPub discovery link',
)
write('blog-post.php', post)

# POD discovery advertises federation without making ActivityPub a POD protocol dependency.
pod = read('portal/pod-identity.php')
pod = replace_once(
    pod,
    '''            'feeds' => ['version' => '1', 'formats' => ['rss', 'atom']],
            'public_agent' => [
''',
    '''            'feeds' => ['version' => '1', 'formats' => ['rss', 'atom', 'json_feed', 'podcast_rss']],
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
''',
    'POD federation capability',
)
pod = replace_once(
    pod,
    '''            'atom' => $origin !== '' ? $origin . '/blog-atom.php' : '',
        ],
''',
    '''            'atom' => $origin !== '' ? $origin . '/blog-atom.php' : '',
            'json' => $origin !== '' ? $origin . '/blog-json-feed.php' : '',
            'podcast' => $origin !== '' ? $origin . '/podcast-feed.php' : '',
        ],
''',
    'POD expanded feed discovery',
)
write('portal/pod-identity.php', pod)

# Dedicated private encryption secret.
config = read('config-example.php')
config = replace_once(
    config,
    "        'vp3_license_local_secret' => 'replace-with-a-long-random-vp3-license-local-secret',\n",
    "        'vp3_license_local_secret' => 'replace-with-a-long-random-vp3-license-local-secret',\n        // Encrypts the local ActivityPub RSA private key. Keep this private and stable.\n        'activitypub_secret' => 'replace-with-a-long-random-activitypub-private-key-secret',\n",
    'ActivityPub configuration secret',
)
write('config-example.php', config)

# Remove dependency on the POD messaging module for UUID validation.
service = read('portal/activitypub-service.php')
service = replace_once(
    service,
    '''require_once __DIR__ . '/notifications.php';

function activitypub_uuid_from_seed(string $seed): string
''',
    '''require_once __DIR__ . '/notifications.php';

function activitypub_valid_uuid(string $value): bool
{
    return (bool)preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        trim($value)
    );
}

function activitypub_uuid_from_seed(string $seed): string
''',
    'ActivityPub UUID validator',
)
service = service.replace('pod_message_valid_uuid(', 'activitypub_valid_uuid(')
write('portal/activitypub-service.php', service)

# Fresh-install schema includes the additive federation migration once.
schema = read('database/north_mountain_portal.sql')
migration = read('database/activitypub_federation_v66f.sql')
marker = '-- North Mountain Media ActivityPub Federation v66F'
if marker in schema:
    raise SystemExit('Fresh schema already contains the v66F migration marker')
section = migration
section = section.replace("SELECT 'North Mountain Media ActivityPub Federation v66F migration complete' AS migration_status;", '')
schema = schema.rstrip() + '\n\n' + section.strip() + '\n'
write('database/north_mountain_portal.sql', schema)

print('ActivityPub Federation v66F application integration applied.')

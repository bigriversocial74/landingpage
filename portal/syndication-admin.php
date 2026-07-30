<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-syndication-admin-v66E */

require_once __DIR__ . '/websub-service.php';

function syndication_handle_admin_action(string $action, array $user): bool
{
    $actions = [
        'save_syndication_settings','moderate_webmention','queue_websub_publish',
        'process_websub_queue','retry_websub_delivery',
    ];
    if (!in_array($action, $actions, true)) return false;
    if (!syndication_schema_available()) {
        throw new RuntimeException('Import database/public_syndication_v66e.sql before managing syndication.');
    }
    if ($action === 'save_syndication_settings') {
        $hub = mb_substr(trim(input('blog_websub_hub_url')), 0, 1000);
        $websubEnabled = isset($_POST['blog_websub_enabled']);
        if ($hub !== '' && (!syndication_http_url($hub) || !syndication_public_url_host($hub))) {
            throw new RuntimeException('Enter a public HTTP or HTTPS WebSub hub URL.');
        }
        if ($websubEnabled && $hub === '') {
            throw new RuntimeException('A public hub URL is required before enabling WebSub.');
        }
        $ownerEmail = strtolower(mb_substr(trim(input('blog_podcast_owner_email')), 0, 190));
        if ($ownerEmail !== '' && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid podcast owner email or leave it blank.');
        }
        $image = mb_substr(trim(input('blog_podcast_image_url')), 0, 1000);
        if ($image !== '' && !syndication_http_url($image)) {
            throw new RuntimeException('Enter a valid public podcast image URL or leave it blank.');
        }
        $type = input('blog_podcast_type');
        if (!in_array($type, ['episodic','serial'], true)) $type = 'episodic';
        $pairs = [
            'blog_json_feed_enabled'=>isset($_POST['blog_json_feed_enabled'])?'1':'0',
            'blog_podcast_feed_enabled'=>isset($_POST['blog_podcast_feed_enabled'])?'1':'0',
            'blog_webmention_enabled'=>isset($_POST['blog_webmention_enabled'])?'1':'0',
            'blog_websub_enabled'=>$websubEnabled?'1':'0',
            'blog_websub_hub_url'=>$hub,
            'blog_podcast_title'=>mb_substr(trim(input('blog_podcast_title')) ?: 'North Mountain Media Podcast', 0, 190),
            'blog_podcast_author'=>mb_substr(trim(input('blog_podcast_author')) ?: 'North Mountain Media', 0, 190),
            'blog_podcast_owner_name'=>mb_substr(trim(input('blog_podcast_owner_name')) ?: 'David Evans', 0, 190),
            'blog_podcast_owner_email'=>$ownerEmail,
            'blog_podcast_category'=>mb_substr(trim(input('blog_podcast_category')) ?: 'Technology', 0, 120),
            'blog_podcast_explicit'=>isset($_POST['blog_podcast_explicit'])?'1':'0',
            'blog_podcast_type'=>$type,
            'blog_podcast_image_url'=>$image,
        ];
        foreach ($pairs as $key=>$value) publishing_save_setting($key, $value);
        log_activity('syndication_settings_updated', 'settings', null, [
            'json_feed'=>$pairs['blog_json_feed_enabled']==='1',
            'podcast'=>$pairs['blog_podcast_feed_enabled']==='1',
            'webmention'=>$pairs['blog_webmention_enabled']==='1',
            'websub'=>$pairs['blog_websub_enabled']==='1',
        ]);
        flash('success', 'Syndication settings updated.');
        redirect('portal/admin.php?view=syndication');
    }
    if ($action === 'moderate_webmention') {
        $id = int_input('id');
        $status = input('status');
        if ($id <= 0 || !in_array($status, ['pending','approved','hidden','spam','rejected'], true)) {
            throw new RuntimeException('Select a valid Webmention moderation action.');
        }
        db()->prepare(
            'UPDATE syndication_webmentions SET status=:status,
             moderated_by_user_id=:user_id,moderated_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute(['status'=>$status,'user_id'=>(int)$user['id'],'id'=>$id]);
        log_activity('webmention_moderated', 'syndication_webmention', $id, ['status'=>$status]);
        flash('success', 'Webmention moderation updated.');
        redirect('portal/admin.php?view=syndication#webmentions');
    }
    if ($action === 'queue_websub_publish') {
        $queued = syndication_queue_websub('manual', (int)$user['id']);
        flash($queued > 0 ? 'success' : 'warning', $queued > 0
            ? $queued . ' WebSub topic deliveries were queued.'
            : 'No WebSub deliveries were queued. Confirm that WebSub and a public hub are enabled.');
        redirect('portal/admin.php?view=syndication#websub');
    }
    if ($action === 'process_websub_queue') {
        $results = syndication_process_websub_queue(10);
        $passed = count(array_filter($results, static fn(array $item): bool => !empty($item['ok'])));
        flash($results ? 'success' : 'warning', $results
            ? 'Processed ' . count($results) . ' WebSub deliveries; ' . $passed . ' succeeded.'
            : 'No WebSub deliveries were ready to process.');
        redirect('portal/admin.php?view=syndication#websub');
    }
    if ($action === 'retry_websub_delivery') {
        $id = int_input('id');
        db()->prepare(
            'UPDATE syndication_websub_deliveries SET status="pending",next_attempt_at=UTC_TIMESTAMP(),
             last_error=NULL WHERE id=:id AND status="failed"'
        )->execute(['id'=>$id]);
        flash('success', 'WebSub delivery queued for retry.');
        redirect('portal/admin.php?view=syndication#websub');
    }
    return true;
}

function syndication_admin_mentions(int $limit = 80): array
{
    if (!syndication_schema_available()) return [];
    return db()->query(
        'SELECT mention.*,post.title AS post_title,post.slug AS post_slug,
                moderator.display_name AS moderator_name
         FROM syndication_webmentions mention
         LEFT JOIN blog_posts post ON post.id=mention.target_post_id
         LEFT JOIN users moderator ON moderator.id=mention.moderated_by_user_id
         ORDER BY CASE mention.status WHEN "pending" THEN 0 ELSE 1 END,
                  mention.received_at DESC,mention.id DESC LIMIT ' . max(1, min(200, $limit))
    )->fetchAll();
}

function syndication_render_admin(array $user): void
{
    $ready = syndication_schema_available();
    $settings = syndication_settings();
    $mentions = $ready ? syndication_admin_mentions() : [];
    $deliveries = $ready ? syndication_websub_recent() : [];
    $pending = count(array_filter($mentions, static fn(array $item): bool => $item['status'] === 'pending'));
    $failed = count(array_filter($deliveries, static fn(array $item): bool => $item['status'] === 'failed'));
    ?>
<div class="syndication-admin">
<section class="syndication-admin-hero">
<div><span>Section 66E</span><h2>Public Syndication &amp; Feed Discovery</h2><p>Distribute published work through open feeds, podcast clients, WebSub hubs, and moderated peer-to-peer Webmentions.</p></div>
<div class="syndication-admin-health <?=$ready?'ready':'missing'?>"><strong><?=$ready?'Syndication ready':'Migration required'?></strong><span><?=$ready?'RSS, Atom, JSON, podcast, Webmention, and WebSub controls are available.':'Import database/public_syndication_v66e.sql.'?></span></div>
</section>

<div class="syndication-admin-stats">
<article><span>Feed formats</span><strong><?=($settings['rss_enabled']?1:0)+($settings['atom_enabled']?1:0)+($settings['json_enabled']?1:0)+($settings['podcast_enabled']?1:0)?></strong></article>
<article><span>Pending mentions</span><strong><?=$pending?></strong></article>
<article><span>Failed deliveries</span><strong><?=$failed?></strong></article>
<article><span>WebSub</span><strong><?=$settings['websub_enabled']?'On':'Off'?></strong></article>
</div>

<?php if(!$ready):?><div class="notice warning">Import <code>database/public_syndication_v66e.sql</code> before using these controls.</div><?php endif;?>

<section class="panel syndication-settings-panel">
<header class="panel-header"><div><span>Publishing configuration</span><h2>Feeds, podcast &amp; protocols</h2></div><a href="<?=e(app_url('blog-feeds.php'))?>" target="_blank" rel="noopener">Open feed directory</a></header>
<form method="post" class="syndication-settings-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_syndication_settings">
<div class="syndication-toggle-grid">
<label><input type="checkbox" name="blog_json_feed_enabled" <?=$settings['json_enabled']?'checked':''?>><span><strong>JSON Feed 1.1</strong><small>Advertise application/feed+json.</small></span></label>
<label><input type="checkbox" name="blog_podcast_feed_enabled" <?=$settings['podcast_enabled']?'checked':''?>><span><strong>Podcast RSS</strong><small>Include posts with audio enclosures.</small></span></label>
<label><input type="checkbox" name="blog_webmention_enabled" <?=$settings['webmention_enabled']?'checked':''?>><span><strong>Webmention receiver</strong><small>Verify and moderate peer responses.</small></span></label>
<label><input type="checkbox" name="blog_websub_enabled" <?=$settings['websub_enabled']?'checked':''?>><span><strong>WebSub publishing</strong><small>Queue nonblocking hub notifications.</small></span></label>
</div>
<div class="syndication-field-grid">
<label><span>WebSub hub URL</span><input type="url" name="blog_websub_hub_url" value="<?=e($settings['websub_hub_url'])?>" placeholder="https://hub.example.com/"></label>
<label><span>Podcast title</span><input name="blog_podcast_title" value="<?=e($settings['podcast_title'])?>" maxlength="190"></label>
<label><span>Podcast author</span><input name="blog_podcast_author" value="<?=e($settings['podcast_author'])?>" maxlength="190"></label>
<label><span>Owner name</span><input name="blog_podcast_owner_name" value="<?=e($settings['podcast_owner_name'])?>" maxlength="190"></label>
<label><span>Owner email</span><input type="email" name="blog_podcast_owner_email" value="<?=e($settings['podcast_owner_email'])?>" maxlength="190"></label>
<label><span>Podcast category</span><input name="blog_podcast_category" value="<?=e($settings['podcast_category'])?>" maxlength="120"></label>
<label><span>Podcast type</span><select name="blog_podcast_type"><option value="episodic" <?=$settings['podcast_type']==='episodic'?'selected':''?>>Episodic</option><option value="serial" <?=$settings['podcast_type']==='serial'?'selected':''?>>Serial</option></select></label>
<label><span>Podcast image URL</span><input type="url" name="blog_podcast_image_url" value="<?=e($settings['podcast_image_url'])?>" maxlength="1000"></label>
</div>
<label class="syndication-explicit"><input type="checkbox" name="blog_podcast_explicit" <?=$settings['podcast_explicit']?'checked':''?>> Podcast contains explicit content</label>
<button type="submit" <?=$ready?'':'disabled'?>>Save syndication settings</button>
</form>
<div class="syndication-feed-links">
<?php foreach(['RSS'=>'blog-feed.php','Atom'=>'blog-atom.php','JSON'=>'blog-json-feed.php','Podcast'=>'podcast-feed.php'] as $label=>$path):?>
<a href="<?=e(publishing_absolute_url($path))?>" target="_blank" rel="noopener"><span><?=e($label)?></span><code><?=e(publishing_absolute_url($path))?></code></a>
<?php endforeach;?>
</div>
</section>

<section class="panel" id="webmentions">
<header class="panel-header"><div><span>Inbound independent-web activity</span><h2>Webmention moderation</h2></div><strong><?=$pending?> pending</strong></header>
<?php if(!$mentions):?><div class="empty-state">Verified replies, likes, reposts, and mentions will appear here for moderation.</div><?php else:?><div class="syndication-mention-list">
<?php foreach($mentions as $mention):?>
<article class="syndication-mention status-<?=e($mention['status'])?>">
<header><div><span><?=e(status_label($mention['mention_type']))?></span><h3><?=e($mention['source_title']?:$mention['author_name']?:'Webmention')?></h3><p><?=e($mention['author_name']?:parse_url((string)$mention['source_url'],PHP_URL_HOST)?:'External website')?> · <?=e(format_datetime($mention['received_at']))?></p></div><b><?=e(status_label($mention['status']))?></b></header>
<?php if($mention['source_excerpt']):?><p><?=e($mention['source_excerpt'])?></p><?php endif;?>
<div class="syndication-mention-links"><a href="<?=e($mention['source_url'])?>" target="_blank" rel="noopener noreferrer">Open source</a><?php if($mention['post_slug']):?><a href="<?=e(app_url('blog-post.php?slug='.rawurlencode($mention['post_slug'])))?>" target="_blank" rel="noopener">Target: <?=e($mention['post_title'])?></a><?php endif;?></div>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="moderate_webmention"><input type="hidden" name="id" value="<?=(int)$mention['id']?>"><select name="status"><?php foreach(['pending','approved','hidden','spam','rejected'] as $status):?><option value="<?=e($status)?>" <?=$mention['status']===$status?'selected':''?>><?=e(status_label($status))?></option><?php endforeach;?></select><button>Update</button></form>
</article>
<?php endforeach;?>
</div><?php endif;?>
</section>

<section class="panel" id="websub">
<header class="panel-header"><div><span>Nonblocking distribution</span><h2>WebSub delivery receipts</h2></div><div class="syndication-header-actions"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="queue_websub_publish"><button>Queue publish</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="process_websub_queue"><button>Process queue</button></form></div></header>
<?php if(!$deliveries):?><div class="empty-state">WebSub delivery receipts will appear after a hub is configured and a publish event is queued.</div><?php else:?><div class="syndication-delivery-table"><table><thead><tr><th>Status</th><th>Topic</th><th>Event</th><th>Attempts</th><th>Response</th><th>Updated</th><th></th></tr></thead><tbody><?php foreach($deliveries as $delivery):?><tr><td><span class="status status-<?=e($delivery['status']==='delivered'?'active':($delivery['status']==='failed'?'on_hold':'planning'))?>"><?=e(status_label($delivery['status']))?></span></td><td><code><?=e($delivery['topic_url'])?></code></td><td><?=e(status_label($delivery['event_type']))?></td><td><?=(int)$delivery['attempt_count']?></td><td><?=e($delivery['last_error']?:($delivery['response_code']?'HTTP '.$delivery['response_code']:'—'))?></td><td><?=e(format_datetime($delivery['updated_at']))?></td><td><?php if($delivery['status']==='failed'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="retry_websub_delivery"><input type="hidden" name="id" value="<?=(int)$delivery['id']?>"><button>Retry</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
</div>
<?php
}

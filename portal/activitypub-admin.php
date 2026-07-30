<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-activitypub-admin-v66F */

require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/federated-interactions-admin.php';

function activitypub_handle_admin_action(string $action, array $user): bool
{
    if (federated_interactions_handle_admin_action($action, $user)) return true;
    $actions = [
        'save_activitypub_settings','rotate_activitypub_key','moderate_activitypub_follower',
        'process_activitypub_queue','retry_activitypub_delivery','backfill_activitypub_posts',
        'refresh_activitypub_actor',
    ];
    if (!in_array($action, $actions, true)) return false;
    activitypub_require_schema();

    if ($action === 'save_activitypub_settings') {
        $enabled = isset($_POST['activitypub_enabled']);
        $origin = pod_configured_origin();
        if ($enabled && !str_starts_with(strtolower($origin), 'https://')) {
            throw new RuntimeException('Configure an HTTPS app.base_url before enabling ActivityPub federation.');
        }
        if ($enabled) {
            activitypub_secret_key();
            activitypub_active_key(true, (int)$user['id']);
        }
        $username = pod_public_username(input('activitypub_username'));
        $displayName = mb_substr(trim(input('activitypub_display_name')), 0, 190);
        $summary = mb_substr(trim((string)($_POST['activitypub_summary'] ?? '')), 0, 1200);
        if ($displayName === '') {
            throw new RuntimeException('Enter a federated display name.');
        }
        $pairs = [
            'activitypub_enabled' => $enabled ? '1' : '0',
            'activitypub_federate_blog_posts' => isset($_POST['activitypub_federate_blog_posts']) ? '1' : '0',
            'activitypub_manual_follow_approval' => isset($_POST['activitypub_manual_follow_approval']) ? '1' : '0',
            'activitypub_show_followers' => isset($_POST['activitypub_show_followers']) ? '1' : '0',
            'activitypub_username' => $username,
            'activitypub_display_name' => $displayName,
            'activitypub_summary' => $summary,
        ];
        foreach ($pairs as $key => $value) publishing_save_setting($key, $value);
        log_activity('activitypub_settings_updated', 'settings', null, [
            'enabled' => $enabled,
            'federate_blog_posts' => $pairs['activitypub_federate_blog_posts'] === '1',
            'manual_follow_approval' => $pairs['activitypub_manual_follow_approval'] === '1',
        ]);
        flash('success', $enabled
            ? 'ActivityPub federation is enabled.'
            : 'ActivityPub federation is disabled. The POD remains fully operational.');
        redirect('portal/admin.php?view=federation');
    }

    if ($action === 'rotate_activitypub_key') {
        activitypub_rotate_key((int)$user['id']);
        flash('success', 'The ActivityPub signing key was rotated. Existing deliveries now use the new public key.');
        redirect('portal/admin.php?view=federation#identity');
    }

    if ($action === 'moderate_activitypub_follower') {
        activitypub_moderate_follower(
            int_input('id'),
            input('decision'),
            (int)$user['id']
        );
        flash('success', 'Federated follower status updated and the signed response was queued when required.');
        redirect('portal/admin.php?view=federation#followers');
    }

    if ($action === 'process_activitypub_queue') {
        $results = activitypub_process_delivery_queue(10);
        $passed = count(array_filter($results, static fn(array $item): bool => !empty($item['ok'])));
        flash($results ? 'success' : 'warning', $results
            ? 'Processed ' . count($results) . ' ActivityPub deliveries; ' . $passed . ' succeeded.'
            : 'No ActivityPub deliveries were ready to process.');
        redirect('portal/admin.php?view=federation#deliveries');
    }

    if ($action === 'retry_activitypub_delivery') {
        activitypub_retry_delivery(int_input('id'));
        flash('success', 'The ActivityPub delivery was queued for a fresh retry.');
        redirect('portal/admin.php?view=federation#deliveries');
    }

    if ($action === 'backfill_activitypub_posts') {
        $count = activitypub_backfill_published_posts((int)$user['id'], 250);
        flash($count > 0 ? 'success' : 'warning', $count > 0
            ? $count . ' published Blog posts were added to the federated outbox.'
            : 'No published Blog posts were available to backfill, or federation is disabled.');
        redirect('portal/admin.php?view=federation#publishing');
    }

    if ($action === 'refresh_activitypub_actor') {
        $actorId = int_input('actor_id');
        $statement = db()->prepare(
            'SELECT actor_uri FROM activitypub_remote_actors WHERE id=:id LIMIT 1'
        );
        $statement->execute(['id' => $actorId]);
        $actorUri = (string)($statement->fetchColumn() ?: '');
        if ($actorUri === '') throw new RuntimeException('The remote ActivityPub actor was not found.');
        activitypub_remote_actor($actorUri, true);
        flash('success', 'The remote ActivityPub actor profile and signing key were refreshed.');
        redirect('portal/admin.php?view=federation#followers');
    }

    return true;
}

function activitypub_admin_stats(): array
{
    if (!activitypub_schema_available()) {
        return ['followers' => 0, 'pending' => 0, 'inbox' => 0, 'failed' => 0, 'outbox' => 0];
    }
    return [
        'followers' => (int)db()->query('SELECT COUNT(*) FROM activitypub_followers WHERE status="approved"')->fetchColumn(),
        'pending' => (int)db()->query('SELECT COUNT(*) FROM activitypub_followers WHERE status="pending"')->fetchColumn(),
        'inbox' => (int)db()->query('SELECT COUNT(*) FROM activitypub_inbox_activities')->fetchColumn(),
        'failed' => (int)db()->query('SELECT COUNT(*) FROM activitypub_deliveries WHERE status="failed"')->fetchColumn(),
        'outbox' => (int)db()->query('SELECT COUNT(*) FROM activitypub_outbox_activities')->fetchColumn(),
    ];
}

function activitypub_render_admin(array $user): void
{
    $ready = activitypub_schema_available();
    $settings = activitypub_settings();
    $stats = activitypub_admin_stats();
    $followers = $ready ? activitypub_followers(true, 100) : [];
    $inbox = $ready ? activitypub_recent_inbox(60) : [];
    $deliveries = $ready ? activitypub_recent_deliveries(60) : [];
    $key = $ready ? activitypub_active_key(false) : null;
    $secretReady = false;
    try {
        activitypub_secret_key();
        $secretReady = true;
    } catch (Throwable) {
    }
    ?>
<div class="activitypub-admin">
<section class="activitypub-hero">
<div><span>Sections 66F–66H</span><h2>ActivityPub Federation</h2><p>Publish Blog articles, manage federated relationships, and operate a private followed-network timeline through signed delivery and durable receipts.</p><a class="activitypub-timeline-link" href="<?=e(app_url('portal/federated-feed.php'))?>">Open Federated Timeline</a></div>
<div class="activitypub-health <?=$settings['enabled']?'ready':($ready?'disabled':'missing')?>"><strong><?=$settings['enabled']?'Federation active':($ready?'Federation off':'Migration required')?></strong><span><?=$settings['enabled']?'Actor discovery and signed federation are available.':($ready?'Standalone POD operation is unchanged.':'Import database/activitypub_federation_v66f.sql.')?></span></div>
</section>

<div class="activitypub-stats">
<article><span>Approved followers</span><strong><?=$stats['followers']?></strong></article>
<article><span>Pending requests</span><strong><?=$stats['pending']?></strong></article>
<article><span>Outbox activities</span><strong><?=$stats['outbox']?></strong></article>
<article><span>Failed deliveries</span><strong><?=$stats['failed']?></strong></article>
</div>

<?php if(!$ready):?><div class="notice warning">Import <code>database/activitypub_federation_v66f.sql</code> before using federation.</div><?php endif;?>
<?php if($ready&&!$settings['https_ready']):?><div class="notice warning">The canonical POD origin must use HTTPS before federation can be enabled.</div><?php endif;?>
<?php if($ready&&!$secretReady):?><div class="notice warning">Add a private <code>security.activitypub_secret</code> value of at least 32 characters to live <code>config.php</code>.</div><?php endif;?>

<section class="panel" id="identity">
<header class="panel-header"><div><span>Local actor</span><h2>Identity &amp; federation policy</h2></div><?php if($settings['enabled']):?><a href="<?=e(activitypub_actor_url())?>" target="_blank" rel="noopener">Open actor JSON</a><?php endif;?></header>
<form method="post" class="activitypub-settings-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_activitypub_settings">
<div class="activitypub-toggle-grid">
<label><input type="checkbox" name="activitypub_enabled" <?=$settings['configured_enabled']?'checked':''?>><span><strong>Enable ActivityPub</strong><small>Requires HTTPS, SQL, and the private configuration secret.</small></span></label>
<label><input type="checkbox" name="activitypub_federate_blog_posts" <?=$settings['federate_blog_posts']?'checked':''?>><span><strong>Federate Blog posts</strong><small>Queue Create, Update, and Delete activities.</small></span></label>
<label><input type="checkbox" name="activitypub_manual_follow_approval" <?=$settings['manual_follow_approval']?'checked':''?>><span><strong>Approve followers manually</strong><small>Recommended default for a personal or business POD.</small></span></label>
<label><input type="checkbox" name="activitypub_show_followers" <?=$settings['show_followers']?'checked':''?>><span><strong>Publish follower list</strong><small>Expose approved actor URLs in the public collection.</small></span></label>
</div>
<div class="activitypub-field-grid">
<label><span>Federated username</span><input name="activitypub_username" value="<?=e($settings['username'])?>" maxlength="120"><small>@<?=e($settings['username'])?>@<?=e(activitypub_host())?></small></label>
<label><span>Display name</span><input name="activitypub_display_name" value="<?=e($settings['display_name'])?>" maxlength="190"></label>
<label class="wide"><span>Public summary</span><textarea name="activitypub_summary" rows="4" maxlength="1200"><?=e($settings['summary'])?></textarea></label>
</div>
<button type="submit" <?=$ready?'':'disabled'?>>Save federation settings</button>
</form>
<div class="activitypub-key-card"><div><span>Signing key</span><strong><?=$key?'Active RSA key':'Not initialized'?></strong><?php if($key):?><code><?=e($key['key_id'])?></code><small>Created <?=e(format_datetime($key['created_at']))?></small><?php endif;?></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="rotate_activitypub_key"><button <?=$ready&&$secretReady?'':'disabled'?>>Rotate signing key</button></form></div>
</section>

<section class="panel" id="publishing">
<header class="panel-header"><div><span>Federated publishing</span><h2>Blog outbox</h2></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="backfill_activitypub_posts"><button <?=$settings['enabled']?'':'disabled'?>>Backfill published posts</button></form></header>
<div class="activitypub-endpoints">
<?php foreach(['Actor'=>activitypub_actor_url(),'Inbox'=>activitypub_inbox_url(),'Outbox'=>activitypub_outbox_url(),'Followers'=>activitypub_followers_url()] as $label=>$url):?><div><span><?=e($label)?></span><code><?=e($url)?></code></div><?php endforeach;?>
</div>
</section>

<section class="panel" id="followers">
<header class="panel-header"><div><span>Moderated relationships</span><h2>Federated followers</h2></div><strong><?=$stats['pending']?> pending</strong></header>
<?php if(!$followers):?><div class="empty-state">Follow requests and approved fediverse actors will appear here.</div><?php else:?><div class="activitypub-follower-list">
<?php foreach($followers as $follower):?>
<article class="activitypub-follower status-<?=e($follower['status'])?>">
<header><div><div><h3><?=e($follower['display_name']?:$follower['preferred_username']?:'Remote actor')?></h3><a href="<?=e($follower['profile_url']?:$follower['actor_uri'])?>" target="_blank" rel="noopener noreferrer"><?=e($follower['actor_uri'])?></a></div></div><b><?=e(status_label($follower['status']))?></b></header>
<div class="activitypub-follower-actions">
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="moderate_activitypub_follower"><input type="hidden" name="id" value="<?=(int)$follower['id']?>"><button name="decision" value="approved">Approve</button><button name="decision" value="rejected">Reject</button><button name="decision" value="removed">Remove</button></form>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="refresh_activitypub_actor"><input type="hidden" name="actor_id" value="<?=(int)$follower['remote_actor_id']?>"><button>Refresh actor</button></form>
</div>
</article>
<?php endforeach;?></div><?php endif;?>
</section>

<?php federated_interactions_render_admin($user); ?>

<section class="panel" id="deliveries">
<header class="panel-header"><div><span>Signed asynchronous delivery</span><h2>Delivery receipts</h2></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="process_activitypub_queue"><button>Process queue</button></form></header>
<?php if(!$deliveries):?><div class="empty-state">Federated Create, Update, Delete, Accept, and Reject deliveries will appear here.</div><?php else:?><div class="activitypub-receipt-list">
<?php foreach($deliveries as $delivery):?><article><div><span><?=e($delivery['activity_type'])?></span><strong><?=e($delivery['display_name']?:$delivery['preferred_username']?:$delivery['actor_uri'])?></strong><small><?=e(format_datetime($delivery['created_at']))?> · <?=e(status_label($delivery['status']))?> · attempt <?=(int)$delivery['attempt_count']?></small><?php if($delivery['last_error']):?><p><?=e($delivery['last_error'])?></p><?php endif;?></div><?php if($delivery['status']==='failed'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="retry_activitypub_delivery"><input type="hidden" name="id" value="<?=(int)$delivery['id']?>"><button>Retry</button></form><?php endif;?></article><?php endforeach;?>
</div><?php endif;?>
</section>

<section class="panel" id="inbox-evidence">
<header class="panel-header"><div><span>Verified inbound evidence</span><h2>Federation inbox</h2></div><strong><?=$stats['inbox']?> received</strong></header>
<?php if(!$inbox):?><div class="empty-state">Verified Follow, Undo, Delete, Like, Announce, and other activities will appear here.</div><?php else:?><div class="activitypub-inbox-list">
<?php foreach($inbox as $activity):?><article><span><?=e($activity['activity_type'])?></span><div><strong><?=e($activity['actor_uri'])?></strong><small><?=e(format_datetime($activity['received_at']))?> · <?=e(status_label($activity['status']))?></small><?php if($activity['error_message']):?><p><?=e($activity['error_message'])?></p><?php endif;?></div></article><?php endforeach;?>
</div><?php endif;?>
</section>
</div>
<?php
}

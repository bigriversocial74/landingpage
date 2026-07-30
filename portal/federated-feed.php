<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/federated-timeline.php';

$user = require_role('admin');
$userId = (int)$user['id'];

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action = input('action');
    try {
        if ($action === 'save_timeline_settings') {
            $retention = max(7, min(730, int_input('retention_days', 90)));
            foreach ([
                'activitypub_timeline_enabled' => isset($_POST['timeline_enabled']) ? '1' : '0',
                'activitypub_timeline_store_following' => isset($_POST['store_following']) ? '1' : '0',
                'activitypub_timeline_receive_mentions' => isset($_POST['receive_mentions']) ? '1' : '0',
                'activitypub_timeline_retention_days' => (string)$retention,
                'activitypub_timeline_remote_media_mode' => 'link_only',
            ] as $key => $value) {
                publishing_save_setting($key, $value);
            }
            flash('success', 'Federated timeline settings were updated.');
        } elseif ($action === 'timeline_state') {
            federated_timeline_set_state(int_input('post_id'), $userId, input('state_action'));
            flash('success', 'Timeline state was updated.');
        } elseif ($action === 'moderate_timeline_post') {
            federated_timeline_moderate(
                int_input('post_id'),
                input('decision'),
                $userId,
                input('note')
            );
            flash('success', 'The federated post moderation state was updated.');
        } elseif ($action === 'timeline_action') {
            $type = input('timeline_action');
            federated_timeline_action(int_input('post_id'), $type, $userId, input('reply_text'));
            flash('success', 'The signed federated ' . status_label($type) . ' activity was queued.');
        } elseif ($action === 'undo_timeline_action') {
            federated_timeline_undo_action(int_input('action_id'), $userId);
            flash('success', 'The signed Undo activity was queued.');
        } elseif ($action === 'delete_timeline_reply') {
            federated_timeline_delete_reply(int_input('action_id'), $userId);
            flash('success', 'The federated reply deletion and Tombstone were queued.');
        } elseif ($action === 'discover_actor') {
            $actor = federated_timeline_resolve_actor_input(input('actor_input'));
            $_SESSION['federated_actor_discovery'] = [
                'actor_uri' => (string)$actor['actor_uri'],
                'display_name' => (string)($actor['display_name'] ?: $actor['preferred_username'] ?: 'Remote actor'),
                'username' => (string)($actor['preferred_username'] ?? ''),
                'summary' => (string)($actor['summary'] ?? ''),
                'profile_url' => (string)($actor['profile_url'] ?: $actor['actor_uri']),
                'created_at' => time(),
            ];
            flash('success', 'The remote ActivityPub actor was verified.');
        } elseif ($action === 'follow_discovered_actor') {
            federated_interactions_follow_actor(input('actor_uri'), $userId);
            unset($_SESSION['federated_actor_discovery']);
            flash('success', 'The signed Follow activity was queued.');
        } elseif ($action === 'cleanup_timeline') {
            $deleted = federated_timeline_cleanup();
            flash('success', $deleted . ' expired unsaved timeline entries were removed.');
        } else {
            throw new RuntimeException('Unsupported federated timeline action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    $query = [];
    foreach (['queue', 'q', 'actor_id'] as $key) {
        $value = trim((string)($_POST['return_' . $key] ?? ''));
        if ($value !== '') $query[$key] = $value;
    }
    redirect('portal/federated-feed.php' . ($query ? '?' . http_build_query($query) : ''));
}

$schemaAvailable = federated_timeline_schema_available();
$settings = federated_timeline_settings();
$queue = trim((string)($_GET['queue'] ?? 'following'));
if (!in_array($queue, ['following', 'mentions', 'boosts', 'unread', 'saved', 'hidden', 'all'], true)) {
    $queue = 'following';
}
$search = trim((string)($_GET['q'] ?? ''));
$actorId = query_int('actor_id');
$posts = $schemaAvailable ? federated_timeline_query($userId, [
    'queue' => $queue,
    'q' => $search,
    'actor_id' => $actorId,
], 150) : [];
$actors = $schemaAvailable ? db()->query(
    'SELECT DISTINCT actor.id,actor.actor_uri,actor.preferred_username,actor.display_name
     FROM activitypub_remote_posts post
     JOIN activitypub_remote_actors actor ON actor.id=post.remote_actor_id
     ORDER BY COALESCE(actor.display_name,actor.preferred_username,actor.actor_uri),actor.id'
)->fetchAll() : [];
$counts = $schemaAvailable ? db()->query(
    'SELECT
        SUM(status="active") AS active_count,
        SUM(status="pending" AND mentions_local=1) AS pending_mentions,
        SUM(status="active" AND entry_type="announce") AS boost_count,
        SUM(status="hidden") AS hidden_count
     FROM activitypub_remote_posts'
)->fetch() : [];
$discovery = $_SESSION['federated_actor_discovery'] ?? null;
if (!is_array($discovery) || time() - (int)($discovery['created_at'] ?? 0) > 900) {
    $discovery = null;
    unset($_SESSION['federated_actor_discovery']);
}

portal_header('Federated Timeline', 'communications', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/federated-timeline.css?v=20260730-v66H'))?>">
<div class="ft-shell">
<section class="ft-panel ft-hero">
<div><span class="ft-kicker">Private open-social workspace · v66H</span><h2>Your followed network, on your POD.</h2><p>Read verified posts, review direct mentions, save useful entries, and send signed replies, likes, boosts, and Undo activities. Remote media stays link-only.</p></div>
<div class="ft-hero-actions"><a class="ft-button secondary" href="<?=e(app_url('portal/admin.php?view=federation'))?>">Federation controls</a><a class="ft-button secondary" href="<?=e(app_url('portal/admin.php?view=inbox'))?>">Unified Inbox</a></div>
</section>

<?php if(!$schemaAvailable):?>
<section class="ft-warning"><strong>Federated timeline migration required.</strong> Import <code>database/federated_timeline_v66h.sql</code>.</section>
<?php else:?>
<div class="ft-stats">
<article><span>Active entries</span><strong><?=(int)($counts['active_count']??0)?></strong></article>
<article><span>Pending mentions</span><strong><?=(int)($counts['pending_mentions']??0)?></strong></article>
<article><span>Boost entries</span><strong><?=(int)($counts['boost_count']??0)?></strong></article>
<article><span>Hidden entries</span><strong><?=(int)($counts['hidden_count']??0)?></strong></article>
</div>

<section class="ft-panel ft-settings">
<header><div><span class="ft-kicker">Privacy policy</span><h2>Timeline controls</h2></div><strong><?=$settings['enabled']?'Enabled':'Disabled'?></strong></header>
<form method="post" class="ft-settings-form"><?=csrf_field()?><input type="hidden" name="action" value="save_timeline_settings">
<label><input type="checkbox" name="timeline_enabled" <?=$settings['enabled']?'checked':''?>><span><strong>Enable private timeline ingestion</strong><small>Store verified activities only after signature validation.</small></span></label>
<label><input type="checkbox" name="store_following" <?=$settings['store_following']?'checked':''?>><span><strong>Store accepted Following posts</strong><small>Unfollowed actors cannot populate the home timeline.</small></span></label>
<label><input type="checkbox" name="receive_mentions" <?=$settings['receive_mentions']?'checked':''?>><span><strong>Quarantine direct mentions</strong><small>Unsolicited mentions remain pending until reviewed.</small></span></label>
<label class="ft-retention"><span>Unsaved retention</span><input type="number" name="retention_days" min="7" max="730" value="<?=$settings['retention_days']?>"><small>Saved posts and posts with local actions are preserved.</small></label>
<div class="ft-media-policy"><strong>Remote media: Link only</strong><span>The POD never auto-loads remote images, audio, video, or tracking pixels.</span></div>
<button class="ft-button" type="submit">Save timeline policy</button>
</form>
</section>

<section class="ft-panel ft-discovery">
<header><div><span class="ft-kicker">WebFinger and actor discovery</span><h2>Find a remote actor</h2></div></header>
<form method="post" class="ft-discovery-form"><?=csrf_field()?><input type="hidden" name="action" value="discover_actor"><input name="actor_input" required placeholder="@name@example.social or https://example.social/users/name"><button class="ft-button">Verify actor</button></form>
<?php if($discovery):?><article class="ft-discovery-result"><div><strong><?=e($discovery['display_name'])?></strong><span><?=e($discovery['actor_uri'])?></span><p><?=e($discovery['summary'])?></p><a href="<?=e($discovery['profile_url'])?>" target="_blank" rel="noopener noreferrer">Open remote profile</a></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="follow_discovered_actor"><input type="hidden" name="actor_uri" value="<?=e($discovery['actor_uri'])?>"><button class="ft-button">Follow actor</button></form></article><?php endif;?>
</section>

<section class="ft-panel ft-toolbar">
<nav aria-label="Timeline views">
<?php foreach(['following'=>'Following','mentions'=>'Mentions','boosts'=>'Boosts','unread'=>'Unread','saved'=>'Saved','hidden'=>'Hidden','all'=>'All'] as $key=>$label):?><a class="<?=$queue===$key?'active':''?>" href="<?=e(app_url('portal/federated-feed.php?'.http_build_query(['queue'=>$key,'q'=>$search,'actor_id'=>$actorId])))?>"><?=e($label)?></a><?php endforeach;?>
</nav>
<form method="get"><input type="hidden" name="queue" value="<?=e($queue)?>"><input name="q" value="<?=e($search)?>" placeholder="Search remote posts or actors"><select name="actor_id"><option value="0">All actors</option><?php foreach($actors as $actor):?><option value="<?=(int)$actor['id']?>" <?=(int)$actor['id']===$actorId?'selected':''?>><?=e($actor['display_name']?:$actor['preferred_username']?:$actor['actor_uri'])?></option><?php endforeach;?></select><button class="ft-button secondary">Filter</button></form>
<form method="post" class="ft-cleanup-form"><?=csrf_field()?><input type="hidden" name="action" value="cleanup_timeline"><input type="hidden" name="return_queue" value="<?=e($queue)?>"><button class="ft-button secondary">Run retention cleanup</button></form>
</section>

<section class="ft-stream" aria-label="Federated timeline">
<?php if(!$posts):?><div class="ft-empty">No timeline entries match this view. Follow an actor, enable ingestion, or adjust the filters.</div><?php endif;?>
<?php foreach($posts as $post):
    $attachments=json_decode((string)($post['attachments_json']??''),true); if(!is_array($attachments))$attachments=[];
    $tags=json_decode((string)($post['tags_json']??''),true); if(!is_array($tags))$tags=[];
    $likeAction=federated_timeline_active_action((int)$post['id'],'like');
    $boostAction=federated_timeline_active_action((int)$post['id'],'announce');
    $replyActions=db()->prepare('SELECT id,reply_text,reply_object_uri,status,created_at FROM activitypub_remote_post_actions WHERE remote_post_id=:post_id AND action_type="reply" ORDER BY id DESC LIMIT 20');
    $replyActions->execute(['post_id'=>(int)$post['id']]); $replies=$replyActions->fetchAll();
?>
<article class="ft-card status-<?=e($post['status'])?> <?=empty($post['read_at'])?'unread':''?>" id="remote-post-<?=(int)$post['id']?>">
<header><div><span class="ft-type"><?=e(status_label($post['entry_type']))?></span><h2><?=e($post['display_name']?:$post['preferred_username']?:'Remote actor')?></h2><a href="<?=e($post['profile_url']?:$post['actor_uri'])?>" target="_blank" rel="noopener noreferrer"><?=e($post['actor_uri'])?></a></div><time datetime="<?=e((string)($post['source_published_at']?:$post['created_at']))?>"><?=e(format_datetime((string)($post['source_published_at']?:$post['created_at'])))?></time></header>
<?php if($post['entry_type']==='announce'):?><div class="ft-boost"><strong>Boosted remote object</strong><a href="<?=e($post['boosted_object_uri']?:$post['object_uri'])?>" target="_blank" rel="noopener noreferrer"><?=e($post['boosted_object_uri']?:$post['object_uri'])?></a></div><?php else:?>
<?php if(!empty($post['content_warning'])):?><details class="ft-warning-content"><summary><?=e($post['content_warning'])?></summary><?php endif;?>
<?php if(!empty($post['title'])):?><h3><?=e($post['title'])?></h3><?php endif;?>
<?php if(!empty($post['body_text'])):?><p class="ft-body"><?=nl2br(e($post['body_text']))?></p><?php elseif(!empty($post['summary'])):?><p class="ft-body"><?=nl2br(e($post['summary']))?></p><?php endif;?>
<?php if(!empty($post['content_warning'])):?></details><?php endif;?>
<?php endif;?>
<?php if($attachments):?><div class="ft-attachments"><strong>Remote media links</strong><?php foreach($attachments as $attachment):?><a href="<?=e((string)$attachment['url'])?>" target="_blank" rel="noopener noreferrer nofollow"><?=e((string)($attachment['name']?:$attachment['type']?:'Attachment'))?><?php if(!empty($attachment['media_type'])):?> · <?=e($attachment['media_type'])?><?php endif;?></a><?php endforeach;?></div><?php endif;?>
<?php if($tags):?><div class="ft-tags"><?php foreach($tags as $tag):?><?php if(!empty($tag['href'])):?><a href="<?=e((string)$tag['href'])?>" target="_blank" rel="noopener noreferrer nofollow"><?=e((string)$tag['name'])?></a><?php else:?><span><?=e((string)$tag['name'])?></span><?php endif;?><?php endforeach;?></div><?php endif;?>
<footer><a href="<?=e($post['source_url']?:$post['object_uri'])?>" target="_blank" rel="noopener noreferrer">View original</a><span><?=e(status_label($post['visibility']))?></span><?php if(!empty($post['mentions_local'])):?><b>Mentions this POD</b><?php endif;?></footer>
<div class="ft-state-actions">
<?php foreach([empty($post['read_at'])?'read':'unread'=>empty($post['read_at'])?'Mark read':'Mark unread',empty($post['saved_at'])?'save':'unsave'=>empty($post['saved_at'])?'Save':'Unsave',empty($post['hidden_at'])?'hide':'unhide'=>empty($post['hidden_at'])?'Hide':'Unhide'] as $stateAction=>$label):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="timeline_state"><input type="hidden" name="post_id" value="<?=(int)$post['id']?>"><input type="hidden" name="state_action" value="<?=e($stateAction)?>"><input type="hidden" name="return_queue" value="<?=e($queue)?>"><input type="hidden" name="return_q" value="<?=e($search)?>"><input type="hidden" name="return_actor_id" value="<?=$actorId?>"><button><?=e($label)?></button></form><?php endforeach;?>
</div>
<?php if($post['status']==='pending'):?><form method="post" class="ft-moderation"><?=csrf_field()?><input type="hidden" name="action" value="moderate_timeline_post"><input type="hidden" name="post_id" value="<?=(int)$post['id']?>"><input name="note" maxlength="1000" placeholder="Private moderation note"><button name="decision" value="active">Approve mention</button><button name="decision" value="hidden">Hide</button><button name="decision" value="deleted">Delete</button></form><?php endif;?>
<?php if($post['status']==='active'):?><div class="ft-social-actions">
<?php if($likeAction):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="undo_timeline_action"><input type="hidden" name="action_id" value="<?=(int)$likeAction['id']?>"><button>Undo like</button></form><?php else:?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="timeline_action"><input type="hidden" name="timeline_action" value="like"><input type="hidden" name="post_id" value="<?=(int)$post['id']?>"><button>Like</button></form><?php endif;?>
<?php if($boostAction):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="undo_timeline_action"><input type="hidden" name="action_id" value="<?=(int)$boostAction['id']?>"><button>Undo boost</button></form><?php else:?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="timeline_action"><input type="hidden" name="timeline_action" value="announce"><input type="hidden" name="post_id" value="<?=(int)$post['id']?>"><button>Boost</button></form><?php endif;?>
</div><form method="post" class="ft-reply-form"><?=csrf_field()?><input type="hidden" name="action" value="timeline_action"><input type="hidden" name="timeline_action" value="reply"><input type="hidden" name="post_id" value="<?=(int)$post['id']?>"><textarea name="reply_text" maxlength="4000" required placeholder="Write a signed federated reply"></textarea><button class="ft-button">Reply</button></form><?php endif;?>
<?php if($replies):?><div class="ft-local-replies"><strong>Your federated replies</strong><?php foreach($replies as $reply):?><article><p><?=nl2br(e($reply['reply_text']))?></p><small><?=e(status_label($reply['status']))?> · <?=e(format_datetime($reply['created_at']))?></small><?php if($reply['status']==='active'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_timeline_reply"><input type="hidden" name="action_id" value="<?=(int)$reply['id']?>"><button>Delete reply</button></form><?php endif;?></article><?php endforeach;?></div><?php endif;?>
</article>
<?php endforeach;?>
</section>
<?php endif;?>
</div>
<?php portal_footer(); ?>

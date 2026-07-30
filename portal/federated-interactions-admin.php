<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-federated-interactions-admin-v66G */

require_once __DIR__ . '/federated-interactions.php';

function federated_interactions_handle_admin_action(string $action, array $user): bool
{
    $actions = [
        'save_federated_interaction_settings',
        'moderate_federated_comment',
        'follow_federated_actor',
        'unfollow_federated_actor',
        'set_federated_actor_control',
        'block_federated_domain',
        'unblock_federated_domain',
    ];
    if (!in_array($action, $actions, true)) return false;
    federated_interactions_require_schema();
    $userId = (int)$user['id'];

    if ($action === 'save_federated_interaction_settings') {
        $mode = input('activitypub_remote_reply_moderation');
        if (!in_array($mode, ['pre_moderated'], true)) $mode = 'pre_moderated';
        $pairs = [
            'activitypub_federate_comments' => isset($_POST['activitypub_federate_comments']) ? '1' : '0',
            'activitypub_federate_reactions' => isset($_POST['activitypub_federate_reactions']) ? '1' : '0',
            'activitypub_allow_remote_replies' => isset($_POST['activitypub_allow_remote_replies']) ? '1' : '0',
            'activitypub_allow_remote_reactions' => isset($_POST['activitypub_allow_remote_reactions']) ? '1' : '0',
            'activitypub_remote_reply_moderation' => $mode,
            'activitypub_show_following' => isset($_POST['activitypub_show_following']) ? '1' : '0',
        ];
        foreach ($pairs as $key => $value) publishing_save_setting($key, $value);
        log_activity('federated_interaction_settings_updated', 'settings', null, $pairs);
        flash('success', 'Federated interaction and social-graph settings were updated.');
        redirect('portal/admin.php?view=federation#interactions');
    }

    if ($action === 'moderate_federated_comment') {
        federated_interactions_moderate_remote_comment(
            int_input('id'),
            input('decision'),
            $userId,
            input('note')
        );
        flash('success', 'The federated reply moderation state was updated.');
        redirect('portal/admin.php?view=federation#interactions');
    }

    if ($action === 'follow_federated_actor') {
        $id = federated_interactions_follow_actor(input('actor_uri'), $userId);
        flash('success', $id > 0
            ? 'The signed Follow activity was queued.'
            : 'The remote actor could not be followed.');
        redirect('portal/admin.php?view=federation#following');
    }

    if ($action === 'unfollow_federated_actor') {
        federated_interactions_unfollow_actor(int_input('id'), $userId);
        flash('success', 'The signed Undo Follow activity was queued and the actor was removed from Following.');
        redirect('portal/admin.php?view=federation#following');
    }

    if ($action === 'set_federated_actor_control') {
        federated_interactions_set_actor_control(
            int_input('actor_id'),
            input('moderation_status'),
            input('moderation_note'),
            $userId
        );
        flash('success', 'Remote actor moderation controls were updated.');
        redirect('portal/admin.php?view=federation#moderation');
    }

    if ($action === 'block_federated_domain') {
        federated_interactions_block_domain(input('domain_name'), input('reason'), $userId);
        flash('success', 'The federation domain was blocked and matching actors were contained.');
        redirect('portal/admin.php?view=federation#moderation');
    }

    if ($action === 'unblock_federated_domain') {
        federated_interactions_unblock_domain(int_input('id'));
        flash('success', 'The federation domain block was removed. Actor blocks remain until explicitly changed.');
        redirect('portal/admin.php?view=federation#moderation');
    }

    return true;
}

function federated_interactions_admin_stats(): array
{
    if (!federated_interactions_schema_available()) {
        return ['pending_comments' => 0, 'approved_comments' => 0, 'reactions' => 0, 'following' => 0, 'blocked' => 0];
    }
    return [
        'pending_comments' => (int)db()->query('SELECT COUNT(*) FROM activitypub_remote_comments WHERE status="pending"')->fetchColumn(),
        'approved_comments' => (int)db()->query('SELECT COUNT(*) FROM activitypub_remote_comments WHERE status="approved"')->fetchColumn(),
        'reactions' => (int)db()->query('SELECT COUNT(*) FROM activitypub_remote_reactions WHERE status="active"')->fetchColumn(),
        'following' => (int)db()->query('SELECT COUNT(*) FROM activitypub_following WHERE status="accepted"')->fetchColumn(),
        'blocked' => (int)db()->query('SELECT COUNT(*) FROM activitypub_actor_controls WHERE moderation_status="blocked"')->fetchColumn(),
    ];
}

function federated_interactions_admin_actors(): array
{
    if (!federated_interactions_schema_available()) return [];
    return db()->query(
        'SELECT actor.*,control.moderation_status,control.moderation_note,
                follower.status AS follower_status,following.status AS following_status
         FROM activitypub_remote_actors actor
         LEFT JOIN activitypub_actor_controls control ON control.remote_actor_id=actor.id
         LEFT JOIN activitypub_followers follower ON follower.remote_actor_id=actor.id
         LEFT JOIN activitypub_following following ON following.remote_actor_id=actor.id
         WHERE follower.id IS NOT NULL OR following.id IS NOT NULL
            OR EXISTS (SELECT 1 FROM activitypub_remote_comments comment WHERE comment.remote_actor_id=actor.id)
            OR EXISTS (SELECT 1 FROM activitypub_remote_reactions reaction WHERE reaction.remote_actor_id=actor.id)
         ORDER BY COALESCE(control.updated_at,actor.updated_at) DESC,actor.id DESC
         LIMIT 150'
    )->fetchAll();
}

function federated_interactions_render_admin(array $user): void
{
    $ready = federated_interactions_schema_available();
    $settings = federated_interactions_settings();
    $stats = federated_interactions_admin_stats();
    $comments = $ready ? db()->query(
        'SELECT comment.*,actor.display_name,actor.preferred_username,actor.actor_uri,
                actor.profile_url,post.title AS post_title,post.slug AS post_slug
         FROM activitypub_remote_comments comment
         JOIN activitypub_remote_actors actor ON actor.id=comment.remote_actor_id
         JOIN blog_posts post ON post.id=comment.blog_post_id
         WHERE comment.status IN ("pending","approved","hidden","spam")
         ORDER BY FIELD(comment.status,"pending","spam","hidden","approved"),comment.updated_at DESC,comment.id DESC
         LIMIT 100'
    )->fetchAll() : [];
    $following = $ready ? federated_interactions_following(true, 100) : [];
    $actors = $ready ? federated_interactions_admin_actors() : [];
    $domains = $ready ? db()->query(
        'SELECT block.*,user.display_name AS created_by_name
         FROM activitypub_domain_blocks block
         LEFT JOIN users user ON user.id=block.created_by_user_id
         ORDER BY block.created_at DESC,block.id DESC LIMIT 100'
    )->fetchAll() : [];
    ?>
<section class="panel federated-interactions-panel" id="interactions">
<header class="panel-header"><div><span>Section 66G</span><h2>Federated interactions</h2><p>Moderated remote replies, likes, boosts, and local interaction delivery without creating fake local accounts.</p></div><strong><?=$ready?'Social bridge ready':'Migration required'?></strong></header>
<?php if(!$ready):?><div class="notice warning">Import <code>database/federated_interactions_v66g.sql</code> before enabling two-way federation.</div><?php else:?>
<div class="federated-social-stats">
<article><span>Pending replies</span><strong><?=$stats['pending_comments']?></strong></article>
<article><span>Approved replies</span><strong><?=$stats['approved_comments']?></strong></article>
<article><span>Remote reactions</span><strong><?=$stats['reactions']?></strong></article>
<article><span>Following</span><strong><?=$stats['following']?></strong></article>
<article><span>Blocked actors</span><strong><?=$stats['blocked']?></strong></article>
</div>
<form method="post" class="activitypub-settings-form federated-policy-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_federated_interaction_settings">
<div class="activitypub-toggle-grid">
<label><input type="checkbox" name="activitypub_federate_comments" <?=$settings['federate_comments']?'checked':''?>><span><strong>Federate local comments</strong><small>Send approved local comments, edits, and deletions as signed Note activities.</small></span></label>
<label><input type="checkbox" name="activitypub_federate_reactions" <?=$settings['federate_reactions']?'checked':''?>><span><strong>Federate local reactions</strong><small>Send Like and Undo activities for Blog reactions.</small></span></label>
<label><input type="checkbox" name="activitypub_allow_remote_replies" <?=$settings['allow_remote_replies']?'checked':''?>><span><strong>Receive remote replies</strong><small>Verified remote replies always enter moderation before publication.</small></span></label>
<label><input type="checkbox" name="activitypub_allow_remote_reactions" <?=$settings['allow_remote_reactions']?'checked':''?>><span><strong>Receive likes and boosts</strong><small>Store verified Like, Announce, Undo, and Delete evidence.</small></span></label>
<label><input type="checkbox" name="activitypub_show_following" <?=$settings['show_following']?'checked':''?>><span><strong>Publish Following collection</strong><small>Expose accepted outbound relationships in the public collection.</small></span></label>
</div>
<input type="hidden" name="activitypub_remote_reply_moderation" value="pre_moderated">
<button type="submit">Save social federation policy</button>
</form>
<?php endif;?>
</section>

<?php if($ready):?>
<section class="panel" id="federated-replies">
<header class="panel-header"><div><span>Moderation</span><h2>Remote replies</h2></div><strong><?=$stats['pending_comments']?> pending</strong></header>
<?php if(!$comments):?><div class="empty-state">Verified remote replies will appear here before public display.</div><?php else:?><div class="federated-reply-admin-list">
<?php foreach($comments as $comment):?>
<article class="status-<?=e($comment['status'])?>" id="remote-comment-<?=(int)$comment['id']?>">
<header><div><strong><?=e($comment['display_name']?:$comment['preferred_username']?:'Remote actor')?></strong><a href="<?=e($comment['profile_url']?:$comment['actor_uri'])?>" target="_blank" rel="noopener noreferrer"><?=e($comment['actor_uri'])?></a></div><b><?=e(status_label($comment['status']))?></b></header>
<p><?=nl2br(e($comment['body_text']))?></p>
<small>On <a href="<?=e(app_url('blog-post.php?slug='.rawurlencode((string)$comment['post_slug'])))?>" target="_blank" rel="noopener"><?=e($comment['post_title'])?></a> · <?=e(format_datetime((string)($comment['source_published_at']?:$comment['created_at'])))?></small>
<form method="post" class="federated-moderation-form"><?=csrf_field()?><input type="hidden" name="action" value="moderate_federated_comment"><input type="hidden" name="id" value="<?=(int)$comment['id']?>"><input name="note" maxlength="1000" placeholder="Moderation note"><button name="decision" value="approved">Approve</button><button name="decision" value="hidden">Hide</button><button name="decision" value="spam">Spam</button><button name="decision" value="deleted">Delete</button></form>
</article>
<?php endforeach;?></div><?php endif;?>
</section>

<section class="panel" id="following">
<header class="panel-header"><div><span>Outbound social graph</span><h2>Following</h2></div><strong><?=$stats['following']?> accepted</strong></header>
<form method="post" class="federated-follow-form"><?=csrf_field()?><input type="hidden" name="action" value="follow_federated_actor"><label><span>Remote actor URL</span><input name="actor_uri" type="url" required placeholder="https://social.example/users/name"></label><button>Follow actor</button></form>
<?php if(!$following):?><div class="empty-state">Follow a verified HTTPS ActivityPub actor to build the POD’s Following collection.</div><?php else:?><div class="federated-following-list">
<?php foreach($following as $row):?><article><div><strong><?=e($row['display_name']?:$row['preferred_username']?:'Remote actor')?></strong><a href="<?=e($row['profile_url']?:$row['actor_uri'])?>" target="_blank" rel="noopener noreferrer"><?=e($row['actor_uri'])?></a><small><?=e(status_label($row['status']))?> · <?=e(format_datetime($row['updated_at']))?></small></div><?php if(in_array($row['status'],['pending','accepted','rejected'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="unfollow_federated_actor"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button>Unfollow</button></form><?php endif;?></article><?php endforeach;?>
</div><?php endif;?>
</section>

<section class="panel" id="moderation">
<header class="panel-header"><div><span>Trust controls</span><h2>Actors and domains</h2></div><strong>Owner controlled</strong></header>
<form method="post" class="federated-domain-form"><?=csrf_field()?><input type="hidden" name="action" value="block_federated_domain"><label><span>Block domain</span><input name="domain_name" required placeholder="example.social"></label><label><span>Reason</span><input name="reason" maxlength="1000"></label><button>Block domain</button></form>
<?php if($domains):?><div class="federated-domain-list"><?php foreach($domains as $block):?><article><div><strong><?=e($block['domain_name'])?></strong><span><?=e($block['reason']?:'No reason recorded')?></span><small><?=e(format_datetime($block['created_at']))?></small></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="unblock_federated_domain"><input type="hidden" name="id" value="<?=(int)$block['id']?>"><button>Remove block</button></form></article><?php endforeach;?></div><?php endif;?>
<?php if(!$actors):?><div class="empty-state">Remote actors appear after follows, follower requests, replies, likes, or boosts.</div><?php else:?><div class="federated-actor-control-list">
<?php foreach($actors as $actor):?>
<article><header><div><strong><?=e($actor['display_name']?:$actor['preferred_username']?:'Remote actor')?></strong><a href="<?=e($actor['profile_url']?:$actor['actor_uri'])?>" target="_blank" rel="noopener noreferrer"><?=e($actor['actor_uri'])?></a></div><b><?=e(status_label($actor['moderation_status']?:'active'))?></b></header><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="set_federated_actor_control"><input type="hidden" name="actor_id" value="<?=(int)$actor['id']?>"><select name="moderation_status"><option value="active" <?=($actor['moderation_status']?:'active')==='active'?'selected':''?>>Active</option><option value="muted" <?=$actor['moderation_status']==='muted'?'selected':''?>>Muted</option><option value="blocked" <?=$actor['moderation_status']==='blocked'?'selected':''?>>Blocked</option></select><input name="moderation_note" maxlength="1000" value="<?=e($actor['moderation_note']??'')?>" placeholder="Private moderation note"><button>Save control</button></form></article>
<?php endforeach;?></div><?php endif;?>
</section>
<?php endif;?>
<?php
}

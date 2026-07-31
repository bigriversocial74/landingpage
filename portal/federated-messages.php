<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/federated-messaging.php';

$user = require_role('admin');

$saveSetting = static function (string $key, string $value): void {
    db()->prepare(
        'INSERT INTO settings(setting_key,setting_value) VALUES(:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    )->execute(['setting_key' => $key, 'setting_value' => $value]);
};

if (is_post()) {
    verify_csrf();
    if (function_exists('same_origin_request') && !same_origin_request()) {
        http_response_code(403);
        exit;
    }
    enforce_authenticated_action_limit($user);
    $action = input('action');
    $threadId = int_input('thread_id');
    try {
        if ($action === 'save_settings') {
            $enabled = input('messages_enabled') === '1' ? '1' : '0';
            $mode = input('accept_mode');
            if (!in_array($mode, ['requests','trusted','none'], true)) $mode = 'requests';
            $retention = (string)max(7, min(730, int_input('retention_days', 180)));
            $limit = (string)max(3, min(120, int_input('actor_hourly_limit', 30)));
            $assist = input('homeserver_assistance') === '1' ? '1' : '0';
            $saveSetting('activitypub_messages_enabled', $enabled);
            $saveSetting('activitypub_messages_accept_mode', $mode);
            $saveSetting('activitypub_messages_retention_days', $retention);
            $saveSetting('activitypub_messages_actor_hourly_limit', $limit);
            $saveSetting('activitypub_messages_remote_media_mode', 'link_only');
            $saveSetting('activitypub_messages_homeserver_assistance', $assist);
            flash('success', 'Federated Message settings saved.');
        } elseif (in_array($action, ['archive','unarchive','mute','unmute','pin','unpin','hide','unhide'], true)) {
            federated_messaging_set_user_state($threadId, (int)$user['id'], $action);
            flash('success', 'Conversation state updated.');
        } elseif ($action === 'mark_unread') {
            federated_messaging_mark_unread($threadId, (int)$user['id']);
            $_SESSION['federated_message_keep_unread_once'] = $threadId;
            flash('success', 'Conversation marked unread.');
        } elseif (in_array($action, ['accept','reject','reopen','close','block','report','delete_local'], true)) {
            federated_messaging_moderate_thread($threadId, $action, (int)$user['id'], input('moderation_note'));
            if ($action === 'delete_local') $threadId = 0;
            flash('success', $action === 'delete_local' ? 'The local federated conversation copy was deleted.' : 'Federated conversation updated.');
        } elseif ($action === 'send_message') {
            federated_messaging_send($threadId, input('body'), (int)$user['id'], input('in_reply_to') ?: null);
            unset($_SESSION['federated_message_assist_once']);
            flash('success', 'Federated message queued for signed delivery.');
        } elseif ($action === 'edit_message') {
            $message = federated_messaging_edit_outbound(int_input('message_id'), input('body'), (int)$user['id']);
            $threadId = (int)($message['thread_id'] ?? $threadId);
            flash('success', 'Federated message update queued.');
        } elseif ($action === 'delete_message') {
            $message = federated_messaging_message(int_input('message_id'));
            if (!$message) throw new RuntimeException('The federated message was not found.');
            $threadId = (int)$message['thread_id'];
            federated_messaging_delete_outbound((int)$message['id'], (int)$user['id']);
            flash('success', 'Federated message deletion queued.');
        } elseif ($action === 'retry_delivery') {
            $deliveryId = int_input('delivery_id');
            activitypub_retry_delivery($deliveryId);
            flash('success', 'Federated message delivery reset for retry.');
        } elseif ($action === 'assist') {
            $result = federated_messaging_assist(
                $threadId,
                int_input('message_id') ?: null,
                input('assist_kind'),
                (int)$user['id'],
                input('target_language')
            );
            $_SESSION['federated_message_assist_once'] = [
                'thread_id' => $threadId,
                'kind' => input('assist_kind'),
                'text' => (string)($result['text'] ?? ''),
                'message' => (string)($result['message'] ?? ''),
                'status' => (string)($result['status'] ?? ''),
                'created_at' => time(),
            ];
            flash(!empty($result['ok']) ? 'success' : 'error', !empty($result['ok'])
                ? 'HomeServer returned a private proposal. Review it before sending.'
                : ((string)($result['message'] ?? 'HomeServer assistance was unavailable.')));
        } else {
            throw new RuntimeException('Unsupported Federated Messages action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    $query = $threadId > 0 ? '?thread=' . $threadId : '';
    redirect('portal/federated-messages.php' . $query);
}

$schemaAvailable = federated_messaging_schema_available();
$settings = federated_messaging_settings();
$filter = trim((string)($_GET['filter'] ?? 'inbox'));
if (!in_array($filter, ['inbox','requests','unread','pinned','muted','archived'], true)) $filter = 'inbox';
$search = trim((string)($_GET['q'] ?? ''));
$threads = $schemaAvailable ? federated_messaging_threads((int)$user['id'], $filter, $search) : [];
$selectedThreadId = query_int('thread');
$selectedThread = $schemaAvailable && $selectedThreadId > 0 ? federated_messaging_thread($selectedThreadId) : null;
if (!$selectedThread && $threads) {
    $selectedThread = $threads[0];
    $selectedThreadId = (int)$selectedThread['id'];
}
$messages = $selectedThread ? federated_messaging_thread_messages((int)$selectedThread['id']) : [];
$keepUnread = (int)($_SESSION['federated_message_keep_unread_once'] ?? 0) === $selectedThreadId;
unset($_SESSION['federated_message_keep_unread_once']);
if ($selectedThread && !$keepUnread) federated_messaging_mark_read((int)$selectedThread['id'], (int)$user['id']);
$selectedState = null;
if ($selectedThread) {
    $stateStatement = db()->prepare(
        'SELECT * FROM activitypub_message_user_state WHERE thread_id=:thread_id AND user_id=:user_id LIMIT 1'
    );
    $stateStatement->execute(['thread_id' => $selectedThreadId, 'user_id' => (int)$user['id']]);
    $selectedState = $stateStatement->fetch() ?: [];
}
$homeServer = homeserver_adapter_status();
$assistOnce = $_SESSION['federated_message_assist_once'] ?? null;
unset($_SESSION['federated_message_assist_once']);
if (!is_array($assistOnce) || (int)($assistOnce['thread_id'] ?? 0) !== $selectedThreadId || time() - (int)($assistOnce['created_at'] ?? 0) > 900) {
    $assistOnce = null;
}
$draftText = is_array($assistOnce) && in_array((string)($assistOnce['kind'] ?? ''), ['draft','translate'], true)
    ? (string)($assistOnce['text'] ?? '') : '';

portal_header('Federated Messages', 'communications', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/federated-messaging.css?v=20260730-v66i'))?>">
<div class="fm-shell">
<section class="fm-panel fm-hero">
    <div><span class="fm-kicker">ActivityPub social messaging · v66I</span><h2>Federated Messages</h2><p class="fm-muted">Signed social messages remain separate from trusted POD Messages. Unknown senders enter requests. Remote media stays link-only.</p></div>
    <div class="fm-actions"><a class="fm-button secondary" href="<?=e(app_url('portal/pod-messages.php'))?>">Private POD Messages</a><a class="fm-button secondary" href="<?=e(app_url('portal/federated-feed.php'))?>">Federated Timeline</a><a class="fm-button secondary" href="<?=e(app_url('portal/admin.php?view=federation'))?>">Federation controls</a><a class="fm-button secondary" href="<?=e(app_url('portal/admin.php?view=delivery'))?>Notification Delivery</a></div>
</section>

<?php if(!$schemaAvailable):?>
<section class="fm-warning"><strong>Federated Messaging migration required.</strong> Import <code>database/federated_messaging_v66i.sql</code>.</section>
<?php else:?>
<section class="fm-panel">
    <span class="fm-kicker">Channel policy</span><h2>Safety and assistance</h2>
    <form class="fm-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_settings">
        <div class="fm-settings">
            <label><span>Message channel</span><select name="messages_enabled"><option value="1" <?=$settings['enabled']?'selected':''?>>Enabled</option><option value="0" <?=!$settings['enabled']?'selected':''?>>Disabled</option></select></label>
            <label><span>Unknown senders</span><select name="accept_mode"><option value="requests" <?=$settings['accept_mode']==='requests'?'selected':''?>>Message requests</option><option value="trusted" <?=$settings['accept_mode']==='trusted'?'selected':''?>>Trusted relationships only</option><option value="none" <?=$settings['accept_mode']==='none'?'selected':''?>>Do not receive</option></select></label>
            <label><span>Retention days</span><input type="number" min="7" max="730" name="retention_days" value="<?=$settings['retention_days']?>"></label>
            <label><span>Per-actor hourly limit</span><input type="number" min="3" max="120" name="actor_hourly_limit" value="<?=$settings['actor_hourly_limit']?>"></label>
            <label><span>Remote media</span><input value="Link only" disabled></label>
            <label><span>HomeServer</span><select name="homeserver_assistance"><option value="1" <?=$settings['homeserver_assistance']?'selected':''?>>Owner-approved assistance</option><option value="0" <?=!$settings['homeserver_assistance']?'selected':''?>>Disabled</option></select></label>
        </div>
        <div class="fm-actions"><button class="fm-button" type="submit">Save policy</button><span class="fm-badge <?=$homeServer['mode']==='connected'?'safe':''?>">HomeServer: <?=e((string)$homeServer['mode'])?></span></div>
    </form>
</section>

<div class="fm-layout">
<aside class="fm-panel fm-nav">
    <span class="fm-kicker">Queues</span><h2>Views</h2>
    <nav class="fm-filters">
        <?php foreach(['inbox'=>'Inbox','requests'=>'Requests','unread'=>'Unread','pinned'=>'Pinned','muted'=>'Muted','archived'=>'Archived'] as $key=>$label):?>
        <a class="<?=$filter===$key?'active':''?>" href="<?=e(app_url('portal/federated-messages.php?filter='.$key))?>"><span><?=e($label)?></span></a>
        <?php endforeach;?>
    </nav>
    <form class="fm-search" method="get"><input type="hidden" name="filter" value="<?=e($filter)?>"><label><span class="fm-kicker">Search</span><input name="q" value="<?=e($search)?>" placeholder="Actor or message"></label><button class="fm-button secondary" type="submit">Search</button></form>
</aside>

<section class="fm-panel fm-threads">
    <span class="fm-kicker">Conversations</span><h2><?=e(ucfirst($filter))?></h2>
    <div class="fm-list">
    <?php if(!$threads):?><div class="fm-empty">No federated conversations match this view.</div><?php endif;?>
    <?php foreach($threads as $thread):
        $name=(string)($thread['display_name']?:$thread['preferred_username']?:$thread['actor_uri']);
        $unread=(int)($thread['last_read_message_id']??0)<(int)($thread['last_message_id']??0);
    ?>
        <a class="fm-item <?=(int)$thread['id']===$selectedThreadId?'active':''?>" href="<?=e(app_url('portal/federated-messages.php?filter='.$filter.'&thread='.(int)$thread['id']))?>">
            <header><h3><?=e($name)?></h3><span class="fm-badge <?=e((string)$thread['status'])?>"><?=e(status_label((string)$thread['status']))?></span></header>
            <p><?=e(mb_substr((string)($thread['last_message_body']??''),0,130))?></p>
            <div class="fm-actions"><?php if($unread):?><span class="fm-badge unread">Unread</span><?php endif;?><?php if((int)$thread['needs_response']===1):?><span class="fm-badge request">Needs response</span><?php endif;?><?php if(!empty($thread['pinned_at'])):?><span class="fm-badge">Pinned</span><?php endif;?></div>
        </a>
    <?php endforeach;?>
    </div>
</section>

<main class="fm-panel fm-conversation">
<?php if(!$selectedThread):?><div class="fm-empty">Select a federated conversation.</div><?php else:
    $name=(string)($selectedThread['display_name']?:$selectedThread['preferred_username']?:$selectedThread['actor_uri']);
?>
    <div class="fm-thread-head"><div><span class="fm-kicker">Federated conversation</span><h2><?=e($name)?></h2><div class="fm-id"><?=e((string)$selectedThread['actor_uri'])?></div><div class="fm-actions"><span class="fm-badge <?=e((string)$selectedThread['status'])?>"><?=e(status_label((string)$selectedThread['status']))?></span><span class="fm-badge">Trust: <?=e(status_label((string)$selectedThread['trust_level']))?></span><span class="fm-badge">Risk: <?=(int)$selectedThread['risk_score']?></span></div></div>
    <div class="fm-actions">
        <?php $pinAction=!empty($selectedState['pinned_at'])?'unpin':'pin';$archiveAction=!empty($selectedState['archived_at'])?'unarchive':'archive';$muteAction=!empty($selectedState['muted_at'])?'unmute':'mute';?>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="<?=e($pinAction)?>"><button class="fm-button secondary" type="submit"><?=e(ucfirst($pinAction))?></button></form>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="<?=e($archiveAction)?>"><button class="fm-button secondary" type="submit"><?=e(ucfirst($archiveAction))?></button></form>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="<?=e($muteAction)?>"><button class="fm-button secondary" type="submit"><?=e(ucfirst($muteAction))?></button></form>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="mark_unread"><button class="fm-button secondary" type="submit">Mark unread</button></form>
    </div></div>

    <details><summary>Conversation safety</summary><div class="fm-request-controls"><form class="fm-form" method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="report"><label><span>Report note</span><input name="moderation_note" maxlength="1000" placeholder="Reason for the local report" required></label><button class="fm-button secondary" type="submit">Record report</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="delete_local"><input type="hidden" name="moderation_note" value="Deleted by the POD owner"><button class="fm-button danger" type="submit">Delete local copy</button></form></div></details>

    <?php if((string)$selectedThread['status']==='request'):?><div class="fm-request-controls"><strong>Message request</strong><span>This sender cannot receive a reply until you accept the conversation.</span><form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="accept"><button class="fm-button" type="submit">Accept</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="reject"><button class="fm-button secondary" type="submit">Reject</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="block"><input type="hidden" name="moderation_note" value="Blocked from a Federated Message request"><button class="fm-button danger" type="submit">Block actor</button></form></div><?php endif;?>

    <div class="fm-timeline">
    <?php if(!$messages):?><div class="fm-empty">No messages are stored in this conversation.</div><?php endif;?>
    <?php foreach($messages as $message):
        $classes=['fm-message',(string)$message['direction']];
        if(in_array((string)$message['status'],['request','failed','deleted'],true))$classes[]=(string)$message['status'];
        $attachments=json_decode((string)($message['attachments_json']??''),true); if(!is_array($attachments))$attachments=[];
    ?>
        <article class="<?=e(implode(' ',$classes))?>">
            <header><strong><?=e((string)$message['direction']==='outbound'?'You':$name)?></strong><span><?=e(format_datetime((string)$message['created_at']))?></span></header>
            <?php if((string)$message['status']==='deleted'):?><p>Message deleted.</p><?php else:?><p><?=e((string)($message['body_text']??''))?></p><?php endif;?>
            <?php if($attachments):?><div class="fm-attachments"><?php foreach($attachments as $attachment):?><a href="<?=e((string)$attachment['url'])?>" target="_blank" rel="noopener noreferrer nofollow">Open <?=e((string)($attachment['name']?:$attachment['type']?:'attachment'))?> externally</a><?php endforeach;?></div><?php endif;?>
            <footer><span><?=e(status_label((string)$message['status']))?></span><?php if(!empty($message['edited_at'])):?><span>Edited</span><?php endif;?><?php if(!empty($message['last_error'])):?><span><?=e((string)$message['last_error'])?></span><?php endif;?></footer>
            <?php if((string)$message['direction']==='outbound'&&!in_array((string)$message['status'],['deleted'],true)):?><details><summary>Message actions</summary><form class="fm-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="edit_message"><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="message_id" value="<?=(int)$message['id']?>"><textarea name="body" required><?=e((string)$message['body_text'])?></textarea><button class="fm-button secondary" type="submit">Send edit</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_message"><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="message_id" value="<?=(int)$message['id']?>"><button class="fm-button danger" type="submit">Delete message</button></form><?php if((string)$message['status']==='failed'&&($message['outbox_activity_id']??0)):?><?php $delivery=db()->prepare('SELECT id FROM activitypub_deliveries WHERE outbox_activity_id=:outbox_id AND remote_actor_id=:actor_id ORDER BY id DESC LIMIT 1');$delivery->execute(['outbox_id'=>(int)$message['outbox_activity_id'],'actor_id'=>(int)$message['remote_actor_id']]);$deliveryId=(int)($delivery->fetchColumn()?:0);?><?php if($deliveryId>0):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="retry_delivery"><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="delivery_id" value="<?=$deliveryId?>"><button class="fm-button secondary" type="submit">Retry delivery</button></form><?php endif;?><?php endif;?></details><?php endif;?>
        </article>
    <?php endforeach;?>
    </div>

    <section class="fm-compose">
        <div class="fm-assist"><div><strong>Private HomeServer assistance</strong><p class="fm-muted">Only a bounded conversation excerpt and explicit RSS-POD authority are sent. Results are proposals. Nothing is sent automatically.</p></div><div class="fm-actions"><?php foreach(['summary'=>'Summarize','draft'=>'Suggest reply','translate'=>'Translate'] as $kind=>$label):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="assist"><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="assist_kind" value="<?=$kind?>"><?php if($kind==='translate'):?><input name="target_language" placeholder="Language" required><?php endif;?><button class="fm-button secondary" type="submit" <?=(!$settings['homeserver_assistance']||$homeServer['mode']!=='connected')?'disabled':''?>><?=e($label)?></button></form><?php endforeach;?></div><?php if($assistOnce):?><div class="fm-draft"><?=e((string)($assistOnce['text']?:$assistOnce['message']))?></div><?php endif;?></div>
        <form class="fm-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="send_message"><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><label><span>Reply</span><textarea name="body" required placeholder="Write a direct federated reply"><?=e($draftText)?></textarea></label><button class="fm-button" type="submit" <?=!in_array((string)$selectedThread['status'],['open','muted','archived'],true)?'disabled':''?>>Review complete — send reply</button></form>
    </section>
<?php endif;?>
</main>
</div>
<?php endif;?>
</div>
<?php portal_footer(); ?>

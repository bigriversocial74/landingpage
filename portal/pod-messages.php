<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-messaging.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action = input('action');
    $relationshipId = int_input('relationship_id');
    $threadId = int_input('thread_id');

    try {
        if ($action === 'issue_message_link') {
            $link = pod_issue_message_link(
                $relationshipId,
                (int)$user['id'],
                max(1, min(365, int_input('valid_days', 180)))
            );
            $_SESSION['pod_message_link_once'] = [
                'relationship_id' => $relationshipId,
                'url' => $link,
                'created_at' => time(),
            ];
            flash('success', 'A scoped POD message link was issued. Copy it now; it is shown once.');
        } elseif ($action === 'revoke_message_link') {
            pod_revoke_message_link($relationshipId, (int)$user['id']);
            flash('success', 'The inbound POD message link was revoked.');
        } elseif ($action === 'save_remote_message_link') {
            pod_save_remote_message_link(
                $relationshipId,
                input('remote_message_link'),
                (int)$user['id']
            );
            flash('success', 'The remote POD messaging credential was encrypted and saved.');
        } elseif ($action === 'remove_remote_message_link') {
            pod_remove_remote_message_link($relationshipId, (int)$user['id']);
            flash('success', 'The remote POD messaging credential was removed.');
        } elseif ($action === 'send_message') {
            $thread = $threadId > 0 ? pod_message_thread($threadId) : null;
            if ($thread && (int)$thread['relationship_id'] !== $relationshipId) {
                throw new RuntimeException('The selected conversation does not belong to this POD relationship.');
            }
            $message = pod_create_outbound_message(
                $relationshipId,
                $thread ? (string)$thread['subject'] : input('subject'),
                input('body'),
                (int)$user['id'],
                $thread ? (string)$thread['conversation_uuid'] : null,
                input('in_reply_to') ?: null
            );
            $threadId = (int)$message['thread_id'];
            flash('success', 'POD message delivered.');
        } elseif ($action === 'retry_message') {
            $message = pod_retry_outbound_message(
                int_input('message_id'),
                (int)$user['id']
            );
            $threadId = (int)$message['thread_id'];
            $relationshipId = (int)$message['relationship_id'];
            flash('success', 'POD message delivered on retry.');
        } elseif ($action === 'archive_thread') {
            $thread = pod_message_thread($threadId);
            if (!$thread || (int)$thread['relationship_id'] !== $relationshipId) {
                throw new RuntimeException('The POD conversation was not found.');
            }
            db()->prepare(
                'UPDATE pod_message_threads SET status="archived" WHERE id=:id'
            )->execute(['id' => $threadId]);
            pod_message_event(
                $relationshipId,
                'thread_archived',
                $threadId,
                null,
                null,
                (int)$user['id']
            );
            flash('success', 'POD conversation archived.');
            $threadId = 0;
        } else {
            throw new RuntimeException('Unsupported POD messaging action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    $query = [];
    if ($relationshipId > 0) $query[] = 'relationship=' . $relationshipId;
    if ($threadId > 0) $query[] = 'thread=' . $threadId;
    redirect('portal/pod-messages.php' . ($query ? '?' . implode('&', $query) : ''));
}

$schemaAvailable = pod_messaging_schema_available();
$contacts = $schemaAvailable ? pod_message_contacts() : [];
$selectedRelationshipId = query_int('relationship');
$selectedThreadId = query_int('thread');
$selectedThread = $schemaAvailable && $selectedThreadId > 0
    ? pod_message_thread($selectedThreadId)
    : null;
if ($selectedThread) $selectedRelationshipId = (int)$selectedThread['relationship_id'];

$selectedContact = null;
foreach ($contacts as $contact) {
    if ((int)$contact['id'] === $selectedRelationshipId) {
        $selectedContact = $contact;
        break;
    }
}
if (!$selectedContact && $contacts) {
    $selectedContact = $contacts[0];
    $selectedRelationshipId = (int)$selectedContact['id'];
}

$threads = $schemaAvailable && $selectedRelationshipId > 0
    ? pod_message_threads($selectedRelationshipId)
    : [];
if (!$selectedThread && $threads && query_int('new') !== 1) {
    $selectedThread = $threads[0];
    $selectedThreadId = (int)$selectedThread['id'];
}
$messages = $selectedThread ? pod_message_thread_messages((int)$selectedThread['id']) : [];
if ($selectedThread) pod_mark_message_thread_read((int)$selectedThread['id']);

$oneTimeLink = $_SESSION['pod_message_link_once'] ?? null;
unset($_SESSION['pod_message_link_once']);
if (
    !is_array($oneTimeLink)
    || (int)($oneTimeLink['relationship_id'] ?? 0) !== $selectedRelationshipId
    || time() - (int)($oneTimeLink['created_at'] ?? 0) > 600
) {
    $oneTimeLink = null;
}

portal_header('POD Messages', 'communications', $user);
?>
<style>
.pm-shell{display:grid;gap:20px}.pm-panel{background:#fff;border:1px solid #dfe5eb;border-radius:20px;padding:21px;box-shadow:0 12px 36px rgba(20,31,48,.055)}.pm-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.pm-hero h2,.pm-panel h2{margin:.3rem 0 .55rem}.pm-kicker{display:block;color:#667085;font-size:.74rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.pm-layout{display:grid;grid-template-columns:minmax(250px,.66fr) minmax(260px,.78fr) minmax(0,1.56fr);gap:16px;align-items:start}.pm-list{display:grid;gap:9px}.pm-item{display:grid;gap:7px;padding:13px;border:1px solid #e0e6ed;border-radius:14px;color:inherit;text-decoration:none}.pm-item:hover,.pm-item.active{border-color:#697386;box-shadow:0 0 0 2px rgba(105,115,134,.11)}.pm-item header{display:flex;justify-content:space-between;gap:10px}.pm-item h3,.pm-item p{margin:0}.pm-item h3{font-size:.98rem}.pm-item p{color:#667085;font-size:.87rem}.pm-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;background:#edf2f6;color:#344054;font-size:.7rem;font-weight:820}.pm-badge.ready{background:#e8f7ee;color:#17663a}.pm-badge.unread{background:#111827;color:#fff}.pm-id{font:11px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;overflow-wrap:anywhere}.pm-actions{display:flex;gap:8px;flex-wrap:wrap}.pm-button{display:inline-flex;align-items:center;justify-content:center;padding:10px 15px;border:0;border-radius:999px;background:#111827;color:#fff;text-decoration:none;font:inherit;font-weight:820;cursor:pointer}.pm-button.secondary{background:#edf1f6;color:#263246}.pm-button.danger{background:#fff0f0;color:#a32b2b}.pm-button:disabled{opacity:.48;cursor:not-allowed}.pm-timeline{display:grid;gap:11px;max-height:52vh;overflow:auto;padding:3px}.pm-message{max-width:84%;padding:12px 14px;border-radius:16px;background:#f1f4f7}.pm-message.outbound{margin-left:auto;background:#172033;color:#fff}.pm-message header{display:flex;justify-content:space-between;gap:14px;margin-bottom:5px;font-size:.76rem}.pm-message p{margin:0;white-space:pre-wrap;overflow-wrap:anywhere}.pm-message footer{margin-top:7px;font-size:.72rem;opacity:.76}.pm-message.failed{border:1px solid #dc9292;background:#fff1f1;color:#7d2525}.pm-compose,.pm-form,.pm-settings{display:grid;gap:12px}.pm-compose textarea,.pm-form input,.pm-form select{width:100%;box-sizing:border-box;border:1px solid #ccd5e1;border-radius:11px;padding:11px 12px;background:#fff;color:#172033;font:inherit}.pm-compose textarea{min-height:110px;resize:vertical}.pm-form label{display:grid;gap:6px;font-weight:760}.pm-section{display:grid;gap:12px;padding-top:17px;margin-top:17px;border-top:1px solid #e5e9ef}.pm-secret{display:grid;gap:8px;padding:13px;border:1px solid #c8dfcf;border-radius:13px;background:#effaf2}.pm-secret code{padding:8px;border-radius:8px;background:#fff;overflow-wrap:anywhere}.pm-note,.pm-empty,.pm-warning{padding:14px;border-radius:13px;background:#f6f8fb;color:#667085}.pm-warning{border:1px solid #f0d995;background:#fff6df;color:#6c5013}.pm-contact-head{display:flex;align-items:center;gap:12px}.pm-contact-head img{width:48px;height:48px;border-radius:14px;object-fit:cover;background:#eef2f6}.pm-contact-head strong,.pm-contact-head span{display:block}.pm-contact-head span{color:#667085}.pm-muted{color:#667085}.pm-thread-title{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}@media(max-width:1180px){.pm-layout{grid-template-columns:minmax(240px,.8fr) minmax(0,1.2fr)}.pm-conversations{grid-column:1}.pm-chat{grid-column:2;grid-row:1/3}}@media(max-width:760px){.pm-layout{grid-template-columns:1fr}.pm-conversations,.pm-chat{grid-column:auto;grid-row:auto}.pm-hero{display:grid}.pm-message{max-width:94%}}
</style>
<div class="pm-shell">
<section class="pm-panel pm-hero"><div><span class="pm-kicker">Connected POD messaging · v63.2</span><h2>Private relationship conversations.</h2><p class="pm-muted">Messages are delivered directly between approved POD servers and remain linked to the relationship and CRM contact.</p></div><div class="pm-actions"><a class="pm-button secondary" href="<?=e(app_url('portal/pod-contacts.php'))?>">POD contacts</a><a class="pm-button secondary" href="<?=e(app_url('portal/admin.php?view=communications'))?>">Local communications</a></div></section>

<?php if(!$schemaAvailable):?><section class="pm-warning"><strong>Messaging migration required.</strong> Import <code>database/pod_connected_messaging_v63_2.sql</code>.</section><?php else:?>
<div class="pm-layout">
<section class="pm-panel"><span class="pm-kicker">Contacts</span><h2>Connected PODs</h2><div class="pm-list">
<?php if(!$contacts):?><div class="pm-empty">No connected POD relationships are available.</div><?php endif;?>
<?php foreach($contacts as $contact):?><a class="pm-item <?=(int)$contact['id']===$selectedRelationshipId?'active':''?>" href="<?=e(app_url('portal/pod-messages.php?relationship='.(int)$contact['id']))?>"><header><h3><?=e((string)($contact['contact_name']?:$contact['remote_pod_name']))?></h3><span class="pm-badge <?=!empty($contact['message_ready'])?'ready':''?>"><?=!empty($contact['message_ready'])?'Ready':'Setup'?></span></header><p class="pm-id"><?=e((string)$contact['remote_pod_uuid'])?></p><div class="pm-actions"><?php if((int)$contact['unread_count']>0):?><span class="pm-badge unread"><?=(int)$contact['unread_count']?> unread</span><?php endif;?><span class="pm-badge"><?=e(status_label((string)$contact['messaging_permission']))?></span></div></a><?php endforeach;?>
</div></section>

<section class="pm-panel pm-conversations"><div class="pm-thread-title"><div><span class="pm-kicker">Conversations</span><h2><?=e((string)($selectedContact['contact_name']??$selectedContact['remote_pod_name']??'POD contact'))?></h2></div><?php if($selectedContact):?><a class="pm-button secondary" href="<?=e(app_url('portal/pod-messages.php?relationship='.$selectedRelationshipId.'&new=1'))?>">New</a><?php endif;?></div><div class="pm-list">
<?php if(!$threads):?><div class="pm-empty">No POD conversations yet.</div><?php endif;?>
<?php foreach($threads as $thread):?><a class="pm-item <?=(int)$thread['id']===$selectedThreadId?'active':''?>" href="<?=e(app_url('portal/pod-messages.php?relationship='.$selectedRelationshipId.'&thread='.(int)$thread['id']))?>"><header><h3><?=e((string)$thread['subject'])?></h3><?php if((int)$thread['unread_count']>0):?><span class="pm-badge unread"><?=(int)$thread['unread_count']?></span><?php endif;?></header><p><?=e(substr((string)($thread['last_message_body']??''),0,120))?></p><p><?=e(format_datetime((string)($thread['last_message_at']??$thread['created_at'])))?> · <?=e(status_label((string)($thread['last_delivery_status']??'open')))?></p></a><?php endforeach;?>
</div>
<?php if($selectedContact):?><div class="pm-section"><span class="pm-kicker">Connection setup</span><?php if($oneTimeLink):?><div class="pm-secret"><strong>Copy this message link now</strong><code><?=e((string)$oneTimeLink['url'])?></code><button class="pm-button secondary" type="button" data-copy-pod-call-link data-call-link="<?=e((string)$oneTimeLink['url'])?>">Copy link</button></div><?php endif;?><form class="pm-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="issue_message_link"><input type="hidden" name="relationship_id" value="<?=$selectedRelationshipId?>"><label><span>Inbound link validity</span><select name="valid_days"><option value="30">30 days</option><option value="90">90 days</option><option value="180" selected>180 days</option><option value="365">365 days</option></select></label><button class="pm-button" type="submit" <?=((string)$selectedContact['status']!=='connected'||(string)$selectedContact['messaging_permission']!=='message')?'disabled':''?>><?=((string)($selectedContact['inbound_link_status']??'')==='active')?'Rotate inbound link':'Issue inbound link'?></button></form><?php if((string)($selectedContact['inbound_link_status']??'')==='active'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="revoke_message_link"><input type="hidden" name="relationship_id" value="<?=$selectedRelationshipId?>"><button class="pm-button danger" type="submit">Revoke inbound link</button></form><?php endif;?><form class="pm-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_remote_message_link"><input type="hidden" name="relationship_id" value="<?=$selectedRelationshipId?>"><label><span>Remote POD message link</span><input name="remote_message_link" type="url" autocomplete="off" placeholder="https://their-pod.example/api/pod-message.php#access=..." required></label><button class="pm-button" type="submit">Encrypt and save</button></form><?php if((string)($selectedContact['outbound_link_status']??'')==='active'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove_remote_message_link"><input type="hidden" name="relationship_id" value="<?=$selectedRelationshipId?>"><button class="pm-button danger" type="submit">Remove remote credential</button></form><?php endif;?></div><?php endif;?>
</section>

<section class="pm-panel pm-chat">
<?php if(!$selectedContact):?><div class="pm-empty">Select a connected POD contact.</div><?php else:?>
<div class="pm-contact-head"><?php if(!empty($selectedContact['remote_avatar_url'])):?><img src="<?=e((string)$selectedContact['remote_avatar_url'])?>" alt=""><?php endif;?><div><strong><?=e((string)($selectedContact['contact_name']?:$selectedContact['remote_pod_name']))?></strong><span><?=e((string)$selectedContact['remote_pod_uuid'])?></span></div></div>
<?php if($selectedThread):?><div class="pm-thread-title"><div><span class="pm-kicker">Conversation</span><h2><?=e((string)$selectedThread['subject'])?></h2></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="archive_thread"><input type="hidden" name="relationship_id" value="<?=$selectedRelationshipId?>"><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><button class="pm-button secondary" type="submit">Archive</button></form></div><div class="pm-timeline"><?php foreach($messages as $message):?><article class="pm-message <?=e((string)$message['direction'])?> <?=($message['delivery_status']==='failed')?'failed':''?>"><header><strong><?=e((string)$message['sender_display_name'])?></strong><span><?=e(format_datetime((string)$message['sent_at']))?></span></header><p><?=e((string)$message['body'])?></p><footer><?=e(status_label((string)$message['delivery_status']))?><?php if(!empty($message['failure_message'])):?> · <?=e((string)$message['failure_message'])?><?php endif;?></footer><?php if($message['direction']==='outbound'&&in_array($message['delivery_status'],['failed','queued'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="retry_message"><input type="hidden" name="relationship_id" value="<?=$selectedRelationshipId?>"><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="message_id" value="<?=(int)$message['id']?>"><button class="pm-button secondary" type="submit">Retry</button></form><?php endif;?></article><?php endforeach;?></div><?php endif;?>
<div class="pm-section"><span class="pm-kicker"><?=$selectedThread?'Reply':'New conversation'?></span><form class="pm-compose" method="post"><?=csrf_field()?><input type="hidden" name="action" value="send_message"><input type="hidden" name="relationship_id" value="<?=$selectedRelationshipId?>"><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><?php if(!$selectedThread):?><input name="subject" maxlength="190" placeholder="Conversation subject" required><?php endif;?><textarea name="body" maxlength="12000" placeholder="Write a private POD message" required></textarea><button class="pm-button" type="submit" <?=empty($selectedContact['message_ready'])?'disabled':''?>>Send message</button><?php if(empty($selectedContact['message_ready'])):?><div class="pm-note">Connect the relationship, set Messaging to Message, and exchange scoped message links before sending.</div><?php endif;?></form></div>
<div class="pm-actions"><a class="pm-button secondary" href="<?=e(app_url('portal/pod-contacts.php?relationship='.$selectedRelationshipId))?>">Call contact</a><?php if(!empty($selectedContact['remote_profile_url'])):?><a class="pm-button secondary" href="<?=e((string)$selectedContact['remote_profile_url'])?>" target="_blank" rel="noopener">View profile</a><?php endif;?></div>
<?php endif;?>
</section>
</div>
<?php endif;?>
</div>
<script src="<?=e(app_url('assets/js/pod-contacts-v63-1.js?v=20260728-v63-2'))?>"></script>
<?php portal_footer(); ?>

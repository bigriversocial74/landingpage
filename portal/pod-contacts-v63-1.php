<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-connected-calling.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action = input('action');
    $relationshipId = int_input('relationship_id');

    try {
        if ($action === 'issue_connected_call_link') {
            $url = pod_issue_connected_call_link(
                $relationshipId,
                (int)$user['id'],
                max(1, min(365, int_input('valid_days', 180)))
            );
            $_SESSION['pod_call_link_once'] = [
                'relationship_id' => $relationshipId,
                'url' => $url,
                'created_at' => time(),
            ];
            flash('success', 'A scoped call link was issued. Copy it now; the secret is shown once.');
        } elseif ($action === 'revoke_connected_call_link') {
            pod_revoke_connected_call_link($relationshipId, (int)$user['id']);
            flash('success', 'The inbound call link was revoked.');
        } elseif ($action === 'save_remote_call_link') {
            pod_save_remote_call_link(
                $relationshipId,
                input('remote_call_url'),
                (int)$user['id']
            );
            flash('success', 'The remote call link was encrypted and saved.');
        } elseif ($action === 'remove_remote_call_link') {
            pod_remove_remote_call_link($relationshipId, (int)$user['id']);
            flash('success', 'The stored remote call link was removed.');
        } else {
            throw new RuntimeException('Unsupported connected calling action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('portal/pod-contacts.php?relationship=' . $relationshipId);
}

$schemaAvailable = pod_connected_calling_schema_available();
$contacts = $schemaAvailable ? pod_connected_contacts() : [];
$selectedId = query_int('relationship');
$selected = null;
foreach ($contacts as $record) {
    if ((int)$record['id'] === $selectedId) {
        $selected = $record;
        break;
    }
}
if (!$selected && $contacts) {
    $selected = $contacts[0];
    $selectedId = (int)$selected['id'];
}

$oneTimeLink = $_SESSION['pod_call_link_once'] ?? null;
unset($_SESSION['pod_call_link_once']);
if (
    !is_array($oneTimeLink)
    || (int)($oneTimeLink['relationship_id'] ?? 0) !== $selectedId
    || time() - (int)($oneTimeLink['created_at'] ?? 0) > 600
) {
    $oneTimeLink = null;
}

portal_header('POD Contacts', 'crm', $user);
?>
<style>
.pc-shell{display:grid;gap:20px}.pc-panel{padding:22px;border:1px solid #dfe5eb;border-radius:20px;background:#fff;box-shadow:0 12px 36px rgba(20,31,48,.06)}.pc-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.pc-hero h2,.pc-panel h2{margin:.3rem 0 .55rem}.pc-kicker{display:block;color:#667085;font-size:.75rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.pc-grid{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr);gap:20px}.pc-list{display:grid;gap:10px}.pc-card{display:grid;gap:8px;padding:15px;border:1px solid #dfe5eb;border-radius:15px;color:inherit;text-decoration:none}.pc-card:hover,.pc-card.active{border-color:#697386;box-shadow:0 0 0 2px rgba(105,115,134,.12)}.pc-card header{display:flex;justify-content:space-between;gap:12px}.pc-card h3,.pc-card p{margin:0}.pc-card p{color:#667085}.pc-id{font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;overflow-wrap:anywhere}.pc-badges,.pc-actions{display:flex;gap:7px;flex-wrap:wrap}.pc-badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef2f6;color:#344054;font-size:.72rem;font-weight:820}.pc-badge.ready{background:#e9f8ef;color:#17663a}.pc-badge.warn{background:#fff4d9;color:#805900}.pc-button{display:inline-flex;align-items:center;justify-content:center;padding:11px 17px;border:0;border-radius:999px;background:#111827;color:#fff;text-decoration:none;font:inherit;font-weight:820;cursor:pointer}.pc-button.secondary{background:#edf1f6;color:#263246}.pc-button.danger{background:#fff0f0;color:#a32b2b}.pc-button:disabled{opacity:.5;cursor:not-allowed}.pc-profile{display:flex;align-items:center;gap:13px}.pc-profile img{width:54px;height:54px;border-radius:15px;object-fit:cover;background:#eef2f6}.pc-profile strong,.pc-profile span{display:block}.pc-profile span,.pc-help{color:#667085}.pc-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.pc-stats div,.pc-note{padding:12px;border-radius:13px;background:#f6f8fb}.pc-stats small,.pc-stats strong{display:block}.pc-stats small{color:#667085}.pc-section{display:grid;gap:13px;padding-top:19px;margin-top:19px;border-top:1px solid #e5e9ef}.pc-form{display:grid;gap:13px}.pc-form label{display:grid;gap:6px;font-weight:760}.pc-form input,.pc-form select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #ccd5e1;border-radius:11px;background:#fff;font:inherit}.pc-secret{display:grid;gap:9px;padding:14px;border:1px solid #c8dfcf;border-radius:14px;background:#effaf2}.pc-secret code{padding:9px;border-radius:8px;background:#fff;overflow-wrap:anywhere}.pc-empty,.pc-warning{padding:17px;border:1px dashed #cbd5e1;border-radius:14px;color:#667085}.pc-warning{border-style:solid;border-color:#f0d995;background:#fff6df;color:#6c5013}@media(max-width:900px){.pc-grid{grid-template-columns:1fr}.pc-hero{display:grid}.pc-stats{grid-template-columns:1fr}}
</style>
<div class="pc-shell">
<section class="pc-panel pc-hero">
    <div><span class="pc-kicker">Connected POD communications · v63.1</span><h2>Call connected contacts from your POD.</h2><p class="pc-help">The public Call Us page remains available. Connected relationships gain a direct launcher into the same browser-call engine.</p></div>
    <div class="pc-actions"><a class="pc-button secondary" href="<?=e(app_url('portal/pod-connections.php'))?>">Manage relationships</a><a class="pc-button secondary" href="<?=e(app_url('call-dave.php'))?>" target="_blank" rel="noopener">Public Call Us</a></div>
</section>

<?php if(!$schemaAvailable):?>
<section class="pc-warning"><strong>Migration required.</strong> Import <code>database/pod_connected_calling_v63_1.sql</code>.</section>
<?php else:?>
<div class="pc-grid">
<section class="pc-panel"><span class="pc-kicker">Contact list</span><h2>POD relationships</h2><div class="pc-list">
<?php if(!$contacts):?><div class="pc-empty">No POD relationships are available.</div><?php endif;?>
<?php foreach($contacts as $record):$ready=(string)$record['status']==='connected'&&(string)$record['calling_permission']==='call'&&(string)($record['outbound_link_status']??'')==='active'&&(string)($record['remote_call_url']??'')!=='';?>
<a class="pc-card <?=(int)$record['id']===$selectedId?'active':''?>" href="<?=e(app_url('portal/pod-contacts.php?relationship='.(int)$record['id']))?>"><header><div><h3><?=e((string)($record['contact_name']?:$record['remote_pod_name']))?></h3><p><?=e(status_label((string)$record['remote_identity_type']))?></p></div><span class="pc-badge <?=$ready?'ready':'warn'?>"><?=$ready?'Call ready':'Setup needed'?></span></header><p class="pc-id"><?=e((string)$record['remote_pod_uuid'])?></p><div class="pc-badges"><span class="pc-badge"><?=e(status_label((string)$record['status']))?></span><span class="pc-badge">Call: <?=e(status_label((string)$record['calling_permission']))?></span><span class="pc-badge">Trust: <?=e(status_label((string)$record['trust_status']))?></span></div></a>
<?php endforeach;?>
</div></section>

<section class="pc-panel">
<?php if(!$selected):?><div class="pc-empty">Create and connect a POD relationship first.</div><?php else:
$connected=(string)$selected['status']==='connected';$permitted=(string)$selected['calling_permission']==='call';$remoteReady=$connected&&$permitted&&(string)($selected['outbound_link_status']??'')==='active'&&(string)($selected['remote_call_url']??'')!=='';$inboundActive=(string)($selected['inbound_link_status']??'')==='active'&&(empty($selected['inbound_expires_at'])||strtotime((string)$selected['inbound_expires_at'])>=time());?>
<div class="pc-profile"><?php if(!empty($selected['remote_avatar_url'])):?><img src="<?=e((string)$selected['remote_avatar_url'])?>" alt=""><?php endif;?><div><strong><?=e((string)($selected['contact_name']?:$selected['remote_pod_name']))?></strong><span><?=e((string)$selected['remote_pod_uuid'])?></span></div></div>
<div class="pc-stats"><div><small>Relationship</small><strong><?=e(status_label((string)$selected['status']))?></strong></div><div><small>Calling</small><strong><?=e(status_label((string)$selected['calling_permission']))?></strong></div><div><small>Trust</small><strong><?=e(status_label((string)$selected['trust_status']))?></strong></div></div>

<div class="pc-section"><span class="pc-kicker">Call now</span>
<?php if($remoteReady):?><form method="post" action="<?=e(app_url('portal/pod-call-launch.php'))?>" target="_blank"><?=csrf_field()?><input type="hidden" name="relationship_id" value="<?=(int)$selected['id']?>"><button class="pc-button" type="submit">Call <?=e((string)($selected['contact_name']?:$selected['remote_pod_name']))?></button></form><p class="pc-help">The recipient POD opens its connected call page. Browser microphone permission remains required.</p>
<?php else:?><div class="pc-note"><?php if(!$connected):?>Connect this relationship first.<?php elseif(!$permitted):?>Set Calling permission to Call.<?php else:?>Save the scoped link issued by the remote POD.<?php endif;?></div><?php endif;?>
<div class="pc-actions"><?php if(!empty($selected['remote_profile_url'])):?><a class="pc-button secondary" href="<?=e((string)$selected['remote_profile_url'])?>" target="_blank" rel="noopener">View profile</a><?php endif;?><?php if(!empty($selected['remote_agent_url'])):?><a class="pc-button secondary" href="<?=e((string)$selected['remote_agent_url'])?>" target="_blank" rel="noopener">Public agent</a><?php endif;?></div></div>

<div class="pc-section"><span class="pc-kicker">Their access</span><h2>Save remote call link</h2><p class="pc-help">The bearer URL is encrypted at rest and never displayed again by this POD.</p><form class="pc-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_remote_call_link"><input type="hidden" name="relationship_id" value="<?=(int)$selected['id']?>"><label><span>Remote connected-call link</span><input name="remote_call_url" type="url" autocomplete="off" placeholder="https://their-pod.example/pod-call.php?token=..." required></label><button class="pc-button" type="submit">Encrypt and save</button></form><?php if((string)($selected['outbound_link_status']??'')==='active'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove_remote_call_link"><input type="hidden" name="relationship_id" value="<?=(int)$selected['id']?>"><button class="pc-button danger" type="submit">Remove stored link</button></form><?php endif;?></div>

<div class="pc-section"><span class="pc-kicker">Your access</span><h2>Issue inbound call link</h2><p class="pc-help">Send this link to the connected contact so their POD can call from its contact list.</p><?php if($oneTimeLink):?><div class="pc-secret"><strong>Copy this link now</strong><code><?=e((string)$oneTimeLink['url'])?></code><button class="pc-button secondary" type="button" data-copy-pod-call-link data-call-link="<?=e((string)$oneTimeLink['url'])?>">Copy link</button></div><?php endif;?><form class="pc-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="issue_connected_call_link"><input type="hidden" name="relationship_id" value="<?=(int)$selected['id']?>"><label><span>Valid for</span><select name="valid_days"><option value="30">30 days</option><option value="90">90 days</option><option value="180" selected>180 days</option><option value="365">365 days</option></select></label><button class="pc-button" type="submit" <?=(!$connected||!$permitted)?'disabled':''?>><?=$inboundActive?'Rotate link':'Issue link'?></button></form><?php if($inboundActive):?><div class="pc-note">Active <?=e((string)($selected['inbound_token_hint']??''))?> · Expires <?=e(format_datetime((string)$selected['inbound_expires_at']))?> · Used <?=(int)($selected['inbound_use_count']??0)?> time<?=((int)($selected['inbound_use_count']??0)===1)?'':'s'?>.</div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="revoke_connected_call_link"><input type="hidden" name="relationship_id" value="<?=(int)$selected['id']?>"><button class="pc-button danger" type="submit">Revoke inbound link</button></form><?php endif;?></div>
<?php endif;?>
</section>
</div>
<?php endif;?>
</div>
<script src="<?=e(app_url('assets/js/pod-contacts-v63-1.js?v=20260728-v63-1'))?>"></script>
<?php portal_footer(); ?>

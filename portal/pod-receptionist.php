<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-messaging.php';
require_once __DIR__ . '/pod-agent-receptionist.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    try {
        if (input('action') !== 'save_settings') {
            throw new RuntimeException('Unsupported receptionist action.');
        }
        pod_save_receptionist_settings($_POST, (int)$user['id']);
        flash('success', 'POD receptionist routing and permissions updated.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('portal/pod-receptionist.php');
}

$schemaAvailable = pod_receptionist_schema_available();
$settings = $schemaAvailable ? pod_receptionist_settings(true) : null;
$sessions = $schemaAvailable ? pod_receptionist_admin_sessions(100) : [];
$selectedSessionId = query_int('session');
$selectedSession = $schemaAvailable && $selectedSessionId > 0
    ? pod_receptionist_session($selectedSessionId)
    : null;
$messages = $selectedSession
    ? pod_receptionist_session_messages((int)$selectedSession['id'])
    : [];

portal_header('POD Receptionist', 'communications', $user);
?>
<style>
.ra-shell{display:grid;gap:20px}.ra-panel{padding:22px;border:1px solid #dfe5eb;border-radius:20px;background:#fff;box-shadow:0 12px 36px rgba(20,31,48,.055)}.ra-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.ra-hero h2,.ra-panel h2{margin:.3rem 0 .55rem}.ra-kicker{display:block;color:#667085;font-size:.75rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.ra-grid{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(320px,.92fr);gap:20px}.ra-form{display:grid;gap:15px}.ra-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ra-form-grid .full{grid-column:1/-1}.ra-form label{display:grid;gap:6px;font-weight:760;color:#2b3649}.ra-form input,.ra-form select,.ra-form textarea{width:100%;box-sizing:border-box;border:1px solid #ccd5e1;border-radius:11px;padding:11px 12px;background:#fff;color:#172033;font:inherit}.ra-form textarea{min-height:105px;resize:vertical}.ra-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ra-check{display:flex!important;grid-template-columns:auto 1fr!important;align-items:flex-start;gap:9px!important;padding:11px;border-radius:12px;background:#f6f8fb}.ra-check input{width:auto;margin-top:3px}.ra-button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;padding:10px 16px;background:#111827;color:#fff;text-decoration:none;font:inherit;font-weight:820;cursor:pointer}.ra-button.secondary{background:#edf1f6;color:#263246}.ra-actions{display:flex;gap:9px;flex-wrap:wrap}.ra-list{display:grid;gap:10px}.ra-session{display:grid;gap:7px;padding:14px;border:1px solid #dfe5eb;border-radius:14px;color:inherit;text-decoration:none}.ra-session:hover,.ra-session.active{border-color:#667085;box-shadow:0 0 0 2px rgba(102,112,133,.11)}.ra-session header{display:flex;justify-content:space-between;gap:10px}.ra-session h3,.ra-session p{margin:0}.ra-session p{color:#667085}.ra-badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eef2f6;color:#344054;font-size:.71rem;font-weight:820}.ra-transcript{display:grid;gap:9px;max-height:420px;overflow:auto}.ra-message{padding:11px 13px;border-radius:14px;background:#f1f4f7}.ra-message.agent{border-left:3px solid #4d6585}.ra-message.caller{border-left:3px solid #1f7a4d}.ra-message strong{display:block;margin-bottom:3px;font-size:.76rem}.ra-message p{margin:0;white-space:pre-wrap}.ra-empty,.ra-warning{padding:16px;border:1px dashed #cbd5e1;border-radius:14px;color:#667085}.ra-warning{border-style:solid;border-color:#f0d995;background:#fff6df;color:#6c5013}.ra-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.ra-meta div{padding:11px;border-radius:12px;background:#f6f8fb}.ra-meta small,.ra-meta strong{display:block}.ra-meta small{color:#667085}.ra-muted{color:#667085}@media(max-width:900px){.ra-grid{grid-template-columns:1fr}.ra-hero{display:grid}.ra-form-grid,.ra-checks,.ra-meta{grid-template-columns:1fr}.ra-form-grid .full{grid-column:auto}}
</style>
<div class="ra-shell">
<section class="ra-panel ra-hero"><div><span class="ra-kicker">Agent receptionist routing · v63.3</span><h2>Control how connected calls are answered.</h2><p class="ra-muted">The receptionist uses approved public profile, portfolio, and blog sources. It can transfer, take messages, and request callbacks without accessing private owner knowledge.</p></div><div class="ra-actions"><a class="ra-button secondary" href="<?=e(app_url('portal/pod-contacts.php'))?>">POD contacts</a><a class="ra-button secondary" href="<?=e(app_url('portal/pod-messages.php'))?>">POD messages</a></div></section>

<?php if(!$schemaAvailable):?><section class="ra-warning"><strong>Receptionist migration required.</strong> Import <code>database/pod_agent_receptionist_v63_3.sql</code>.</section><?php else:?>
<div class="ra-grid">
<div class="ra-shell">
<section class="ra-panel"><span class="ra-kicker">Routing policies</span><h2>Receptionist settings</h2><form class="ra-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_settings"><div class="ra-form-grid"><label class="full"><span>Agent name</span><input name="agent_name" maxlength="120" value="<?=e((string)$settings['agent_name'])?>" required></label><label class="full"><span>Greeting</span><textarea name="greeting" maxlength="700" required><?=e((string)$settings['greeting'])?></textarea></label><label><span>When available</span><select name="available_route"><?php foreach(['owner_first','agent_first','agent_only'] as $route):?><option value="<?=e($route)?>" <?=($settings['available_route']===$route)?'selected':''?>><?=e(status_label($route))?></option><?php endforeach;?></select></label><label><span>When busy</span><select name="busy_route"><?php foreach(['agent_first','agent_only','voicemail','callback'] as $route):?><option value="<?=e($route)?>" <?=($settings['busy_route']===$route)?'selected':''?>><?=e(status_label($route))?></option><?php endforeach;?></select></label><label><span>When offline</span><select name="offline_route"><?php foreach(['agent_first','agent_only','voicemail','callback'] as $route):?><option value="<?=e($route)?>" <?=($settings['offline_route']===$route)?'selected':''?>><?=e(status_label($route))?></option><?php endforeach;?></select></label><label><span>Maximum questions</span><input name="maximum_questions" type="number" min="1" max="100" value="<?=(int)$settings['maximum_questions']?>"></label><label><span>Session minutes</span><input name="session_minutes" type="number" min="5" max="120" value="<?=(int)$settings['session_minutes']?>"></label></div><div class="ra-checks"><?php foreach(['enabled'=>'Enable receptionist','allow_transfer'=>'Allow human transfer','allow_callback'=>'Allow callback requests','allow_message'=>'Allow message taking','allow_public_profile'=>'Use public profile','allow_public_portfolio'=>'Use public portfolio','allow_public_blog'=>'Use public blog'] as $field=>$label):?><label class="ra-check"><input type="checkbox" name="<?=e($field)?>" value="1" <?=((int)$settings[$field]===1)?'checked':''?>><span><?=e($label)?></span></label><?php endforeach;?></div><button class="ra-button" type="submit">Save receptionist policies</button></form></section>

<?php if($selectedSession):?><section class="ra-panel"><span class="ra-kicker">Session review</span><h2><?=e((string)$selectedSession['caller_display_name'])?></h2><div class="ra-meta"><div><small>Status</small><strong><?=e(status_label((string)$selectedSession['status']))?></strong></div><div><small>Route</small><strong><?=e(status_label((string)$selectedSession['route_decision']))?></strong></div><div><small>Line</small><strong><?=e(status_label((string)$selectedSession['line_status']))?></strong></div></div><?php if(!empty($selectedSession['summary'])):?><p><?=e((string)$selectedSession['summary'])?></p><?php endif;?><div class="ra-transcript"><?php foreach($messages as $message):?><article class="ra-message <?=e((string)$message['sender_role'])?>"><strong><?=e(status_label((string)$message['sender_role']))?> · <?=e(format_datetime((string)$message['created_at']))?></strong><p><?=e((string)$message['body'])?></p></article><?php endforeach;?></div></section><?php endif;?>
</div>

<section class="ra-panel"><span class="ra-kicker">Receptionist history</span><h2>Connected caller sessions</h2><div class="ra-list"><?php if(!$sessions):?><div class="ra-empty">No receptionist sessions have been recorded.</div><?php endif;?><?php foreach($sessions as $session):?><a class="ra-session <?=(int)$session['id']===$selectedSessionId?'active':''?>" href="<?=e(app_url('portal/pod-receptionist.php?session='.(int)$session['id']))?>"><header><div><h3><?=e((string)($session['contact_name']?:$session['caller_display_name']))?></h3><p><?=e((string)$session['remote_pod_uuid'])?></p></div><span class="ra-badge"><?=e(status_label((string)$session['status']))?></span></header><p><?=e(status_label((string)$session['route_decision']))?> · <?=(int)$session['question_count']?> question<?=((int)$session['question_count']===1)?'':'s'?></p><p><?=e(format_datetime((string)$session['last_activity_at']))?></p></a><?php endforeach;?></div></section>
</div>
<?php endif;?>
</div>
<?php portal_footer(); ?>

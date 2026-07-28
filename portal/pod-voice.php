<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-messaging.php';
require_once __DIR__ . '/pod-agent-voice.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    try {
        if (input('action') !== 'save_voice_settings') {
            throw new RuntimeException('Unsupported browser voice action.');
        }
        pod_voice_save_settings($_POST, (int)$user['id']);
        flash('success', 'Browser voice receptionist settings updated.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('portal/pod-voice.php');
}

$schemaAvailable = pod_voice_schema_available();
$settings = $schemaAvailable ? pod_voice_settings(true) : null;
$sessions = $schemaAvailable ? pod_voice_admin_sessions(100) : [];
$selectedSessionId = query_int('session');
$selectedSession = $schemaAvailable && $selectedSessionId > 0
    ? pod_voice_session($selectedSessionId)
    : null;
$events = $selectedSession
    ? pod_voice_session_events((int)$selectedSession['id'])
    : [];

portal_header('POD Voice', 'communications', $user);
?>
<style>
.pv-shell{display:grid;gap:20px}.pv-panel{padding:22px;border:1px solid #dfe5eb;border-radius:20px;background:#fff;box-shadow:0 12px 36px rgba(20,31,48,.055)}.pv-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.pv-hero h2,.pv-panel h2{margin:.3rem 0 .55rem}.pv-kicker{display:block;color:#667085;font-size:.75rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.pv-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr);gap:20px}.pv-form{display:grid;gap:15px}.pv-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.pv-form-grid .full{grid-column:1/-1}.pv-form label{display:grid;gap:6px;font-weight:760;color:#2b3649}.pv-form input,.pv-form select,.pv-form textarea{width:100%;box-sizing:border-box;border:1px solid #ccd5e1;border-radius:11px;padding:11px 12px;background:#fff;color:#172033;font:inherit}.pv-form textarea{min-height:100px;resize:vertical}.pv-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.pv-check{display:flex!important;grid-template-columns:auto 1fr!important;align-items:flex-start;gap:9px!important;padding:11px;border-radius:12px;background:#f6f8fb}.pv-check input{width:auto;margin-top:3px}.pv-button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;padding:10px 16px;background:#111827;color:#fff;text-decoration:none;font:inherit;font-weight:820;cursor:pointer}.pv-button.secondary{background:#edf1f6;color:#263246}.pv-actions{display:flex;gap:9px;flex-wrap:wrap}.pv-list{display:grid;gap:10px}.pv-session{display:grid;gap:7px;padding:14px;border:1px solid #dfe5eb;border-radius:14px;color:inherit;text-decoration:none}.pv-session:hover,.pv-session.active{border-color:#667085;box-shadow:0 0 0 2px rgba(102,112,133,.11)}.pv-session header{display:flex;justify-content:space-between;gap:10px}.pv-session h3,.pv-session p{margin:0}.pv-session p{color:#667085}.pv-badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eef2f6;color:#344054;font-size:.71rem;font-weight:820}.pv-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.pv-meta div{padding:11px;border-radius:12px;background:#f6f8fb}.pv-meta small,.pv-meta strong{display:block}.pv-meta small{color:#667085}.pv-events{display:grid;gap:9px}.pv-event{padding:11px 13px;border-left:3px solid #8090a4;border-radius:10px;background:#f6f8fb}.pv-event strong,.pv-event span{display:block}.pv-event span{color:#667085;font-size:.8rem}.pv-empty,.pv-warning,.pv-note{padding:16px;border:1px dashed #cbd5e1;border-radius:14px;color:#667085}.pv-warning{border-style:solid;border-color:#f0d995;background:#fff6df;color:#6c5013}.pv-note{border-style:solid;background:#f6f8fb}.pv-muted{color:#667085}@media(max-width:950px){.pv-grid{grid-template-columns:1fr}.pv-hero{display:grid}.pv-form-grid,.pv-checks,.pv-meta{grid-template-columns:1fr}.pv-form-grid .full{grid-column:auto}}
</style>
<div class="pv-shell">
<section class="pv-panel pv-hero"><div><span class="pv-kicker">Browser voice receptionist · v63.4</span><h2>Add speech without replacing text or human calling.</h2><p class="pv-muted">The browser can transcribe a caller’s question and speak the certified receptionist’s text reply when supported. This POD does not upload or store raw live audio.</p></div><div class="pv-actions"><a class="pv-button secondary" href="<?=e(app_url('portal/pod-receptionist.php'))?>">Receptionist routing</a><a class="pv-button secondary" href="<?=e(app_url('portal/pod-contacts.php'))?>">POD contacts</a></div></section>

<?php if(!$schemaAvailable):?><section class="pv-warning"><strong>Voice migration required.</strong> Import <code>database/pod_agent_voice_receptionist_v63_4.sql</code>.</section><?php else:?>
<div class="pv-grid">
<div class="pv-shell">
<section class="pv-panel"><span class="pv-kicker">Owner voice policy</span><h2>Browser speech settings</h2><form class="pv-form" method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_voice_settings"><div class="pv-form-grid"><label><span>Recognition language</span><input name="recognition_language" maxlength="20" value="<?=e((string)$settings['recognition_language'])?>" placeholder="en-US" required></label><label><span>Preferred browser voice <small>optional</small></span><input name="preferred_voice_name" maxlength="190" value="<?=e((string)($settings['preferred_voice_name']??''))?>" placeholder="Exact voice name exposed by the browser"></label><label><span>Speech rate</span><input name="speech_rate" type="number" min="0.5" max="2" step="0.05" value="<?=e((string)$settings['speech_rate'])?>"></label><label><span>Speech pitch</span><input name="speech_pitch" type="number" min="0.5" max="2" step="0.05" value="<?=e((string)$settings['speech_pitch'])?>"></label><label><span>Maximum voice turns</span><input name="maximum_voice_turns" type="number" min="1" max="100" value="<?=(int)$settings['maximum_voice_turns']?>"></label><label class="full"><span>Caller privacy notice</span><textarea name="privacy_notice" maxlength="700" required><?=e((string)$settings['privacy_notice'])?></textarea></label></div><div class="pv-checks"><?php foreach(['enabled'=>'Enable browser voice','auto_speak'=>'Speak replies by default','allow_hands_free'=>'Allow hands-free turns','hands_free_default'=>'Default to hands-free mode'] as $field=>$label):?><label class="pv-check"><input type="checkbox" name="<?=e($field)?>" value="1" <?=((int)$settings[$field]===1)?'checked':''?>><span><?=e($label)?></span></label><?php endforeach;?></div><button class="pv-button" type="submit">Save voice settings</button></form><div class="pv-note"><strong>Browser boundary</strong><p>Speech recognition and speech synthesis availability depends on the caller’s browser and operating system. Some browsers may use their own speech service. The POD records capability state and counters only—not raw live audio or recognized transcript text.</p></div></section>

<?php if($selectedSession):?><section class="pv-panel"><span class="pv-kicker">Voice session review</span><h2><?=e((string)$selectedSession['caller_display_name'])?></h2><div class="pv-meta"><div><small>Mode</small><strong><?=e(status_label((string)$selectedSession['capability_mode']))?></strong></div><div><small>Recognized</small><strong><?=(int)$selectedSession['recognized_turns']?></strong></div><div><small>Spoken</small><strong><?=(int)$selectedSession['spoken_turns']?></strong></div><div><small>Errors</small><strong><?=(int)$selectedSession['error_count']?></strong></div></div><div class="pv-events"><?php if(!$events):?><div class="pv-empty">No browser voice events recorded.</div><?php endif;?><?php foreach($events as $event):?><article class="pv-event"><strong><?=e(status_label((string)$event['event_type']))?></strong><span><?=e(format_datetime((string)$event['created_at']))?></span></article><?php endforeach;?></div></section><?php endif;?>
</div>

<section class="pv-panel"><span class="pv-kicker">Capability history</span><h2>Browser voice sessions</h2><div class="pv-list"><?php if(!$sessions):?><div class="pv-empty">No browser voice sessions have been recorded.</div><?php endif;?><?php foreach($sessions as $session):?><a class="pv-session <?=(int)$session['id']===$selectedSessionId?'active':''?>" href="<?=e(app_url('portal/pod-voice.php?session='.(int)$session['id']))?>"><header><div><h3><?=e((string)($session['contact_name']?:$session['caller_display_name']))?></h3><p><?=e((string)$session['caller_pod_uuid'])?></p></div><span class="pv-badge"><?=e(status_label((string)$session['status']))?></span></header><p><?=e(status_label((string)$session['capability_mode']))?> · <?=e((string)$session['recognition_language'])?></p><p><?=(int)$session['recognized_turns']?> recognized · <?=(int)$session['spoken_turns']?> spoken · <?=(int)$session['error_count']?> errors</p><p><?=e(format_datetime((string)$session['last_activity_at']))?></p></a><?php endforeach;?></div></section>
</div>
<?php endif;?>
</div>
<?php portal_footer(); ?>

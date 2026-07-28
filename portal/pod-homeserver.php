<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-homeserver-provider.php';

$user = require_role('admin');

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action = input('action');
    $connectionId = int_input('connection_id');

    try {
        if ($action === 'issue_sync_code') {
            $code = pod_homeserver_pairing_code((int)$user['id']);
            $_SESSION['pod_homeserver_sync_code_once'] = $code + ['created_at' => time()];
            flash('success', 'A one-time POD HomeServer Sync Code was issued. Copy it now.');
        } elseif ($action === 'revoke_sync_code') {
            pod_homeserver_revoke_pairing_code(int_input('code_id'));
            flash('success', 'The unused POD HomeServer Sync Code was revoked.');
        } elseif ($action === 'revoke_connection') {
            pod_homeserver_revoke_connection($connectionId, (int)$user['id']);
            flash('success', 'The POD HomeServer connection and active job leases were revoked.');
        } elseif ($action === 'cleanup_artifacts') {
            $count = pod_homeserver_cleanup_artifacts();
            flash('success', $count . ' expired encrypted artifact' . ($count === 1 ? '' : 's') . ' removed.');
        } elseif ($action === 'queue_capability_test') {
            $job = pod_homeserver_queue_job(
                $connectionId,
                'capability_test',
                [
                    'contract' => 'pod-homeserver-voice-1',
                    'requested_checks' => [
                        'runtime_health',
                        'transcription_models',
                        'synthesis_models',
                    ],
                    'requested_at' => gmdate('c'),
                ],
                null,
                null,
                (int)$user['id'],
                null,
                'high'
            );
            flash('success', 'Capability-test job queued for ' . (string)$job['device_display_name'] . '.');
        } elseif ($action === 'queue_tts') {
            $text = trim(input('text'));
            if ($text === '' || strlen($text) > 6000) {
                throw new RuntimeException('Enter text to synthesize up to 6,000 characters.');
            }
            $format = input('audio_format');
            if (!in_array($format, ['mp3','wav','ogg','webm'], true)) $format = 'mp3';
            $job = pod_homeserver_queue_job(
                $connectionId,
                'text_to_speech',
                [
                    'text' => $text,
                    'language' => substr(trim(input('language', 'en-US')), 0, 20),
                    'voice' => substr(trim(input('voice')), 0, 190),
                    'audio_format' => $format,
                    'purpose' => 'pod_receptionist_voice_test',
                ],
                null,
                null,
                (int)$user['id']
            );
            flash('success', 'Text-to-speech job queued: ' . (string)$job['job_uuid'] . '.');
        } elseif ($action === 'queue_stt') {
            $upload = $_FILES['audio_file'] ?? null;
            if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Choose a valid audio file for the transcription contract test.');
            }
            $tmp = (string)($upload['tmp_name'] ?? '');
            $bytes = is_file($tmp) ? (int)filesize($tmp) : 0;
            $max = max(256 * 1024, min(16 * 1024 * 1024, (int)(pod_homeserver_config()['max_audio_bytes'] ?? 8 * 1024 * 1024)));
            if ($bytes <= 0 || $bytes > $max) {
                throw new RuntimeException('The uploaded audio file is empty or exceeds the configured limit.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = strtolower((string)$finfo->file($tmp));
            $allowed = ['audio/mpeg','audio/wav','audio/x-wav','audio/ogg','audio/webm','audio/mp4'];
            if (!in_array($mime, $allowed, true)) {
                throw new RuntimeException('Use MP3, WAV, OGG, WebM, or M4A audio for the contract test.');
            }
            $content = file_get_contents($tmp);
            if (!is_string($content) || $content === '') {
                throw new RuntimeException('The uploaded audio file could not be read.');
            }
            $artifact = pod_homeserver_store_artifact(
                $connectionId,
                null,
                'input',
                'audio',
                $mime,
                $content
            );
            $job = pod_homeserver_queue_job(
                $connectionId,
                'speech_to_text',
                [
                    'language' => substr(trim(input('language', 'en-US')), 0, 20),
                    'purpose' => 'pod_receptionist_transcription_test',
                    'artifact_uuid' => (string)$artifact['artifact_uuid'],
                    'mime_type' => $mime,
                    'content_hash' => (string)$artifact['content_hash'],
                ],
                null,
                null,
                (int)$user['id'],
                (int)$artifact['id']
            );
            flash('success', 'Speech-to-text job queued: ' . (string)$job['job_uuid'] . '.');
        } elseif ($action === 'cancel_job') {
            $jobId = int_input('job_id');
            $job = pod_homeserver_job($jobId);
            if (!$job || !in_array((string)$job['status'], ['queued','leased','processing'], true)) {
                throw new RuntimeException('Only active POD HomeServer voice jobs can be cancelled.');
            }
            db()->prepare(
                'UPDATE pod_homeserver_voice_jobs
                 SET status="cancelled",lease_token_hash=NULL,lease_expires_at=NULL,
                     failure_code="owner_cancelled",failure_message="Cancelled by the POD owner."
                 WHERE id=:id'
            )->execute(['id' => $jobId]);
            flash('success', 'The POD HomeServer voice job was cancelled.');
        } else {
            throw new RuntimeException('Unsupported POD HomeServer provider action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    $query = $connectionId > 0 ? '?connection=' . $connectionId : '';
    redirect('portal/pod-homeserver.php' . $query);
}

$schemaAvailable = pod_homeserver_schema_available();
$enabled = $schemaAvailable && (bool)(pod_homeserver_config()['enabled'] ?? false);
$connections = $schemaAvailable ? pod_homeserver_connections() : [];
$jobs = $schemaAvailable ? pod_homeserver_jobs(100) : [];
$receipts = $schemaAvailable ? pod_homeserver_receipts(60) : [];
$selectedConnectionId = query_int('connection');
$selectedConnection = null;
foreach ($connections as $connection) {
    if ((int)$connection['id'] === $selectedConnectionId) {
        $selectedConnection = $connection;
        break;
    }
}
if (!$selectedConnection && $connections) {
    $selectedConnection = $connections[0];
    $selectedConnectionId = (int)$selectedConnection['id'];
}
$selectedJobId = query_int('job');
$selectedJob = $schemaAvailable && $selectedJobId > 0 ? pod_homeserver_job($selectedJobId) : null;
$selectedResult = $selectedJob ? pod_homeserver_result((int)$selectedJob['id']) : [];
$activeCodes = [];
if ($schemaAvailable) {
    db()->prepare(
        'UPDATE pod_homeserver_pairing_codes
         SET status="expired" WHERE status="active" AND expires_at<UTC_TIMESTAMP()'
    )->execute();
    $activeCodes = db()->query(
        'SELECT * FROM pod_homeserver_pairing_codes
         WHERE status="active" ORDER BY id DESC LIMIT 20'
    )->fetchAll();
}
$oneTimeCode = $_SESSION['pod_homeserver_sync_code_once'] ?? null;
unset($_SESSION['pod_homeserver_sync_code_once']);
if (!is_array($oneTimeCode) || time() - (int)($oneTimeCode['created_at'] ?? 0) > 600) {
    $oneTimeCode = null;
}

portal_header('POD HomeServer', 'communications', $user);
?>
<style>
.hs-shell{display:grid;gap:20px}.hs-panel{padding:22px;border:1px solid #dfe5eb;border-radius:20px;background:#fff;box-shadow:0 12px 36px rgba(20,31,48,.055)}.hs-hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.hs-hero h2,.hs-panel h2{margin:.3rem 0 .55rem}.hs-kicker{display:block;color:#667085;font-size:.74rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase}.hs-grid{display:grid;grid-template-columns:minmax(300px,.86fr) minmax(0,1.14fr);gap:20px}.hs-button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;padding:10px 16px;background:#111827;color:#fff;text-decoration:none;font:inherit;font-weight:820;cursor:pointer}.hs-button.secondary{background:#edf1f6;color:#263246}.hs-button.danger{background:#fff0f0;color:#a32b2b}.hs-actions,.hs-badges{display:flex;gap:8px;flex-wrap:wrap}.hs-list{display:grid;gap:10px}.hs-card{display:grid;gap:8px;padding:14px;border:1px solid #dfe5eb;border-radius:14px;color:inherit;text-decoration:none}.hs-card:hover,.hs-card.active{border-color:#667085;box-shadow:0 0 0 2px rgba(102,112,133,.11)}.hs-card header{display:flex;justify-content:space-between;gap:10px}.hs-card h3,.hs-card p{margin:0}.hs-card p{color:#667085}.hs-badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eef2f6;color:#344054;font-size:.71rem;font-weight:820}.hs-badge.good{background:#e8f7ee;color:#17663a}.hs-badge.warn{background:#fff4d9;color:#805900}.hs-id{font:11px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;overflow-wrap:anywhere}.hs-form{display:grid;gap:13px}.hs-form label{display:grid;gap:6px;font-weight:760}.hs-form input,.hs-form select,.hs-form textarea{width:100%;box-sizing:border-box;border:1px solid #ccd5e1;border-radius:11px;padding:11px 12px;background:#fff;color:#172033;font:inherit}.hs-form textarea{min-height:110px;resize:vertical}.hs-section{display:grid;gap:13px;padding-top:18px;margin-top:18px;border-top:1px solid #e5e9ef}.hs-secret{display:grid;gap:8px;padding:14px;border:1px solid #c8dfcf;border-radius:14px;background:#effaf2}.hs-secret code,.hs-contract code{padding:9px;border-radius:8px;background:#fff;overflow-wrap:anywhere}.hs-warning,.hs-note,.hs-empty{padding:15px;border-radius:13px;background:#f6f8fb;color:#667085}.hs-warning{border:1px solid #f0d995;background:#fff6df;color:#6c5013}.hs-contract{display:grid;gap:8px}.hs-result{padding:13px;border-radius:13px;background:#f6f8fb;white-space:pre-wrap;overflow-wrap:anywhere}.hs-table{width:100%;border-collapse:collapse}.hs-table th,.hs-table td{padding:10px;border-bottom:1px solid #e5e9ef;text-align:left;vertical-align:top}.hs-table th{color:#667085;font-size:.74rem;text-transform:uppercase;letter-spacing:.06em}.hs-muted{color:#667085}@media(max-width:900px){.hs-grid{grid-template-columns:1fr}.hs-hero{display:grid}.hs-table{display:block;overflow:auto}}
</style>
<div class="hs-shell">
<section class="hs-panel hs-hero"><div><span class="hs-kicker">POD provider foundation · v63.5</span><h2>Pair a HomeServer through a signed provider contract.</h2><p class="hs-muted">This POD supplies pairing, signed requests, pull-based voice jobs, encrypted artifacts, receipts, and status. A coordinated HomeServer POD adapter is still required before production voice processing is live.</p></div><div class="hs-actions"><a class="hs-button secondary" href="<?=e(app_url('portal/pod-voice.php'))?>">Browser voice</a><a class="hs-button secondary" href="<?=e(app_url('.well-known/pod.json'))?>" target="_blank" rel="noopener">Discovery</a></div></section>

<?php if(!$schemaAvailable):?><section class="hs-warning"><strong>Provider migration required.</strong> Import <code>database/pod_homeserver_voice_provider_v63_5.sql</code>.</section><?php else:?>
<?php if(!$enabled):?><section class="hs-warning"><strong>Provider disabled.</strong> Add a private <code>security.pod_homeserver_bridge_secret</code> and set <code>pod_homeserver.enabled</code> to true in live <code>config.php</code> before issuing Sync Codes.</section><?php endif;?>

<div class="hs-grid">
<div class="hs-shell">
<section class="hs-panel"><span class="hs-kicker">Pairing</span><h2>One-time Sync Code</h2><?php if($oneTimeCode):?><div class="hs-secret"><strong>Copy this code now</strong><code><?=e((string)$oneTimeCode['code'])?></code><span>Expires <?=e(format_datetime((string)$oneTimeCode['expires_at']))?></span></div><?php endif;?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="issue_sync_code"><button class="hs-button" type="submit" <?=$enabled?'':'disabled'?>>Issue POD Sync Code</button></form><div class="hs-list"><?php foreach($activeCodes as $code):?><div class="hs-card"><header><strong><?=e((string)$code['code_hint'])?></strong><span class="hs-badge">Active</span></header><p>Expires <?=e(format_datetime((string)$code['expires_at']))?></p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="revoke_sync_code"><input type="hidden" name="code_id" value="<?=(int)$code['id']?>"><button class="hs-button danger" type="submit">Revoke</button></form></div><?php endforeach;?></div></section>

<section class="hs-panel"><span class="hs-kicker">Connections</span><h2>Paired HomeServers</h2><div class="hs-list"><?php if(!$connections):?><div class="hs-empty">No HomeServer is paired with this POD.</div><?php endif;?><?php foreach($connections as $connection):?><a class="hs-card <?=(int)$connection['id']===$selectedConnectionId?'active':''?>" href="<?=e(app_url('portal/pod-homeserver.php?connection='.(int)$connection['id']))?>"><header><div><h3><?=e((string)$connection['device_display_name'])?></h3><p><?=e((string)$connection['homeserver_version'])?></p></div><span class="hs-badge <?=($connection['lifecycle_state']==='active')?'good':'warn'?>"><?=e(status_label((string)$connection['lifecycle_state']))?></span></header><p class="hs-id"><?=e((string)$connection['device_id'])?></p><div class="hs-badges"><span class="hs-badge"><?=(int)$connection['queued_jobs']?> queued</span><span class="hs-badge"><?=(int)$connection['failed_jobs']?> failed</span></div></a><?php endforeach;?></div></section>
</div>

<div class="hs-shell">
<?php if($selectedConnection):$capabilities=json_decode((string)$selectedConnection['granted_capabilities_json'],true)?:[];?>
<section class="hs-panel"><span class="hs-kicker">Selected device</span><h2><?=e((string)$selectedConnection['device_display_name'])?></h2><p class="hs-id"><?=e((string)$selectedConnection['connection_uuid'])?></p><div class="hs-badges"><?php foreach($capabilities as $capability):?><span class="hs-badge"><?=e((string)$capability)?></span><?php endforeach;?></div><p class="hs-muted">Last heartbeat: <?=e(format_datetime((string)($selectedConnection['last_heartbeat_at']??'')))?> · Token <?=e((string)$selectedConnection['token_hint'])?></p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="revoke_connection"><input type="hidden" name="connection_id" value="<?=(int)$selectedConnection['id']?>"><button class="hs-button danger" type="submit">Revoke connection</button></form></section>

<section class="hs-panel"><span class="hs-kicker">Contract tests</span><h2>Queue a provider job</h2><form method="post" class="hs-form"><?=csrf_field()?><input type="hidden" name="action" value="queue_capability_test"><input type="hidden" name="connection_id" value="<?=(int)$selectedConnection['id']?>"><button class="hs-button" type="submit">Queue capability test</button></form><div class="hs-section"><form method="post" class="hs-form"><?=csrf_field()?><input type="hidden" name="action" value="queue_tts"><input type="hidden" name="connection_id" value="<?=(int)$selectedConnection['id']?>"><label><span>Text-to-speech text</span><textarea name="text" maxlength="6000" required></textarea></label><label><span>Language</span><input name="language" value="en-US" maxlength="20"></label><label><span>Preferred voice <small>optional</small></span><input name="voice" maxlength="190"></label><label><span>Audio format</span><select name="audio_format"><option value="mp3">MP3</option><option value="wav">WAV</option><option value="ogg">OGG</option><option value="webm">WebM</option></select></label><button class="hs-button" type="submit">Queue synthesis test</button></form></div><div class="hs-section"><form method="post" enctype="multipart/form-data" class="hs-form"><?=csrf_field()?><input type="hidden" name="action" value="queue_stt"><input type="hidden" name="connection_id" value="<?=(int)$selectedConnection['id']?>"><label><span>Speech-to-text audio</span><input name="audio_file" type="file" accept="audio/mpeg,audio/wav,audio/ogg,audio/webm,audio/mp4" required></label><label><span>Language</span><input name="language" value="en-US" maxlength="20"></label><button class="hs-button" type="submit">Encrypt and queue transcription test</button></form></div></section>
<?php endif;?>

<section class="hs-panel"><span class="hs-kicker">Provider contract</span><h2>Versioned endpoints</h2><div class="hs-contract"><?php foreach(pod_homeserver_endpoint_contract() as $name=>$endpoint):?><strong><?=e(status_label((string)$name))?></strong><code><?=e((string)$endpoint)?></code><?php endforeach;?></div><form method="post" class="hs-section"><?=csrf_field()?><input type="hidden" name="action" value="cleanup_artifacts"><button class="hs-button secondary" type="submit">Remove expired artifacts</button></form></section>
</div>
</div>

<section class="hs-panel"><span class="hs-kicker">Voice jobs</span><h2>Queue and results</h2><div style="overflow:auto"><table class="hs-table"><thead><tr><th>Job</th><th>Device</th><th>Type</th><th>Status</th><th>Attempts</th><th>Created</th><th></th></tr></thead><tbody><?php foreach($jobs as $job):?><tr><td><a href="<?=e(app_url('portal/pod-homeserver.php?connection='.(int)$job['connection_id'].'&job='.(int)$job['id']))?>" class="hs-id"><?=e((string)$job['job_uuid'])?></a></td><td><?=e((string)$job['device_display_name'])?></td><td><?=e(status_label((string)$job['job_type']))?></td><td><?=e(status_label((string)$job['status']))?></td><td><?=(int)$job['attempt_count']?>/<?=(int)$job['max_attempts']?></td><td><?=e(format_datetime((string)$job['created_at']))?></td><td><?php if(in_array($job['status'],['queued','leased','processing'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="cancel_job"><input type="hidden" name="connection_id" value="<?=(int)$job['connection_id']?>"><input type="hidden" name="job_id" value="<?=(int)$job['id']?>"><button class="hs-button danger" type="submit">Cancel</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php if($selectedJob):?><div class="hs-section"><h3><?=e((string)$selectedJob['job_uuid'])?> · <?=e(status_label((string)$selectedJob['status']))?></h3><?php if($selectedResult):?><pre class="hs-result"><?=e(json_encode($selectedResult,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))?></pre><?php elseif(!empty($selectedJob['failure_message'])):?><div class="hs-warning"><?=e((string)$selectedJob['failure_code'])?> · <?=e((string)$selectedJob['failure_message'])?></div><?php else:?><div class="hs-note">No result has been submitted.</div><?php endif;?></div><?php endif;?></section>

<section class="hs-panel"><span class="hs-kicker">Receipts</span><h2>Provider audit trail</h2><div class="hs-list"><?php foreach($receipts as $receipt):?><div class="hs-card"><header><strong><?=e(status_label((string)$receipt['receipt_type']))?></strong><span class="hs-badge"><?=e((string)($receipt['status_code']??'recorded'))?></span></header><p><?=e((string)$receipt['device_display_name'])?><?php if(!empty($receipt['job_uuid'])):?> · <span class="hs-id"><?=e((string)$receipt['job_uuid'])?></span><?php endif;?></p><p><?=e(format_datetime((string)$receipt['created_at']))?></p></div><?php endforeach;?></div></section>
<?php endif;?>
</div>
<?php portal_footer(); ?>

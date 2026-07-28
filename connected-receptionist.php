<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/pod-messaging.php';
require_once __DIR__ . '/portal/pod-agent-receptionist.php';
require_once __DIR__ . '/portal/pod-agent-voice.php';

$connectedContext = pod_connected_call_context();
if (!$connectedContext) {
    http_response_code(403);
    header('Cache-Control: no-store, private, max-age=0');
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Receptionist unavailable</title><style>body{margin:0;background:#f4f6f8;color:#17202c;font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}main{width:min(100% - 32px,680px);margin:10vh auto;padding:30px;border:1px solid #dde3ea;border-radius:22px;background:#fff}a{display:inline-flex;padding:11px 17px;border-radius:999px;background:#111827;color:#fff;text-decoration:none;font-weight:800}</style></head><body><main><h1>Connected receptionist unavailable</h1><p>The relationship call session expired or was revoked.</p><a href="<?=e(app_url('call-dave.php'))?>">Use public Call Us</a></main></body></html>
    <?php
    exit;
}

$config = pod_receptionist_client_config($connectedContext);
if (empty($config['enabled'])) {
    redirect('connected-call.php');
}
$voice = pod_voice_schema_available()
    ? pod_voice_client_config()
    : ['enabled' => false];
$callerName = trim((string)($connectedContext['contact_name'] ?? ''))
    ?: (string)$connectedContext['remote_pod_name'];
$callerImage = trim((string)($connectedContext['remote_avatar_url'] ?? ''));
$recipientName = public_profile_name();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?=e(csrf_token())?>">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?=e((string)$config['agent_name'])?> — <?=e($recipientName)?></title>
    <link rel="stylesheet" href="<?=e(app_url('assets/css/pod-receptionist-v63-3.css?v=20260728-v63-3'))?>">
    <?php if(!empty($voice['enabled'])):?><link rel="stylesheet" href="<?=e(app_url('assets/css/pod-receptionist-voice-v63-4.css?v=20260728-v63-4'))?>"><?php endif;?>
</head>
<body
    class="pr-body"
    data-receptionist-api="<?=e(app_url('api/pod-receptionist.php'))?>"
    data-live-call-url="<?=e(app_url('connected-call.php'))?>"
    data-receptionist-name="<?=e((string)$config['agent_name'])?>"
    data-voice-enabled="<?=!empty($voice['enabled'])?'1':'0'?>"
    data-voice-api="<?=e(app_url('api/pod-agent-voice.php'))?>"
    data-voice-language="<?=e((string)($voice['recognition_language']??'en-US'))?>"
    data-voice-name="<?=e((string)($voice['preferred_voice_name']??''))?>"
    data-voice-rate="<?=e((string)($voice['speech_rate']??1))?>"
    data-voice-pitch="<?=e((string)($voice['speech_pitch']??1))?>"
    data-voice-auto-speak="<?=!empty($voice['auto_speak'])?'1':'0'?>"
    data-voice-allow-hands-free="<?=!empty($voice['allow_hands_free'])?'1':'0'?>"
    data-voice-hands-free="<?=!empty($voice['hands_free_default'])?'1':'0'?>"
>
<header class="pr-header"><div class="pr-header-inner"><a class="pr-brand" href="<?=e(app_url('index.php'))?>"><img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>"><span><?=e($recipientName)?> POD</span></a><nav class="pr-nav" aria-label="Receptionist navigation"><a href="<?=e(app_url('connected-call.php'))?>">Live call</a><a href="<?=e(app_url('call-dave.php'))?>">Public Call Us</a></nav></div></header>
<main class="pr-main">
<section class="pr-identity"><div class="pr-identity-main"><?php if($callerImage!==''):?><img src="<?=e($callerImage)?>" alt=""><?php endif;?><div><span class="pr-kicker">Connected POD caller</span><h1><?=e($callerName)?></h1><small><?=e((string)$connectedContext['remote_pod_uuid'])?></small></div></div><div class="pr-status <?=e((string)$config['line_status'])?>"><?=e(status_label((string)$config['line_status']))?></div></section>
<section class="pr-card">
<header class="pr-card-header"><div><span class="pr-kicker">Automated POD receptionist</span><h2><?=e((string)$config['agent_name'])?></h2><p>I use approved public information and communication-routing policies. I am not the owner.</p></div><span class="pr-route"><?=e(status_label((string)$config['route_decision']))?></span></header>
<div class="pr-chat" data-pr-chat aria-live="polite"></div>
<div class="pr-suggestions" data-pr-suggestions></div>
<?php if(!empty($voice['enabled'])):?>
<section class="pr-voice" data-pr-voice hidden aria-label="Browser voice controls">
    <header class="pr-voice-head"><div><span class="pr-kicker">Browser voice</span><h3>Speak with the receptionist</h3><p>Push to talk is the default. Text remains available at all times.</p></div><span class="pr-voice-mode" data-pr-voice-mode>Checking browser</span></header>
    <div class="pr-voice-controls"><button class="pr-voice-button" type="button" data-pr-voice-listen>Start listening</button><button class="pr-voice-button secondary" type="button" data-pr-voice-stop>Stop listening</button><button class="pr-voice-button secondary" type="button" data-pr-voice-speak>Speak last reply</button><button class="pr-voice-button danger" type="button" data-pr-voice-cancel-speech>Stop speaking</button></div>
    <div class="pr-voice-options"><label class="pr-voice-option"><input type="checkbox" data-pr-voice-spoken-replies <?=!empty($voice['auto_speak'])?'checked':''?>><span>Speak replies</span></label><label class="pr-voice-option"><input type="checkbox" data-pr-voice-hands-free <?=!empty($voice['hands_free_default'])?'checked':''?>><span>Hands-free turns</span></label></div>
    <div class="pr-voice-live" data-pr-voice-status aria-live="polite">Checking browser speech capabilities.</div>
    <div class="pr-voice-fallback" data-pr-voice-fallback hidden></div>
    <p class="pr-voice-privacy"><?=e((string)$voice['privacy_notice'])?> Your browser or operating system may use its own speech-processing service.</p>
</section>
<?php endif;?>
<form class="pr-compose" data-pr-form><input data-pr-input maxlength="1500" autocomplete="off" placeholder="Ask about public projects, services, posts, or availability" required><button class="pr-button" type="submit">Ask</button></form>
<div class="pr-actions"><button class="pr-button" type="button" data-pr-transfer <?=empty($config['actions']['transfer'])?'hidden':''?>>Transfer to human</button><button class="pr-button secondary" type="button" data-pr-callback <?=empty($config['actions']['callback'])?'hidden':''?>>Request callback</button><button class="pr-button secondary" type="button" data-pr-message <?=empty($config['actions']['message'])?'hidden':''?>>Leave message</button><button class="pr-button secondary" type="button" data-pr-end>End session</button></div>
<form class="pr-action-panel" data-pr-callback-panel data-pr-callback-form hidden><h3>Request a callback</h3><label><span>Reason</span><textarea name="message" maxlength="5000" required></textarea></label><label><span>Preferred time <small>optional</small></span><input name="preferred_at" type="datetime-local"></label><button class="pr-button" type="submit">Send callback request</button></form>
<form class="pr-action-panel" data-pr-message-panel data-pr-message-form hidden><h3>Leave a private message</h3><label><span>Message</span><textarea name="message" maxlength="5000" required></textarea></label><button class="pr-button" type="submit">Send message</button></form>
<div class="pr-result" data-pr-result hidden></div>
<footer class="pr-footer">The receptionist cannot access private CRM notes, owner-only knowledge, credentials, or private conversations.</footer>
</section>
</main>
<script src="<?=e(app_url('assets/js/pod-receptionist-v63-3.js?v=20260728-v63-4'))?>"></script>
<?php if(!empty($voice['enabled'])):?><script src="<?=e(app_url('assets/js/pod-receptionist-voice-v63-4.js?v=20260728-v63-4'))?>"></script><?php endif;?>
</body>
</html>

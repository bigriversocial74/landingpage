<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
define('NMM_PUBLIC_MICROPHONE_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('call_us');
require_once __DIR__ . '/portal/call-center.php';
require_once __DIR__ . '/portal/pod-connected-calling.php';

$connectedContext = pod_connected_call_context();
if (!$connectedContext) {
    http_response_code(403);
    header('Cache-Control: no-store, private');
    ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connected call unavailable</title><style>body{margin:0;background:#f4f6f8;color:#17202c;font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}main{width:min(100% - 32px,680px);margin:10vh auto;padding:30px;border:1px solid #dde3ea;border-radius:22px;background:#fff}a{display:inline-flex;border-radius:999px;padding:11px 17px;background:#111827;color:#fff;text-decoration:none;font-weight:800}</style></head><body><main><h1>Connected POD call unavailable</h1><p>The relationship call session expired or was revoked.</p><a href="<?= e(app_url('call-dave.php')) ?>">Use public Call Us</a></main></body></html>
    <?php
    exit;
}

$caller = pod_connected_caller_values($connectedContext);
$status = call_center_public_status();
$statusMessage = call_center_public_status_message();
$recipientProfile = primary_admin_profile();
$recipientName = public_profile_name();
$recipientImage = user_profile_image_url($recipientProfile);
$callerImage = trim((string)($connectedContext['remote_avatar_url'] ?? ''));
$greetingUrl = call_center_public_greeting_url();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="build-version" content="20260728-pod-connected-calling-v63-1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Connected call with <?= e($recipientName) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/call-dave.css?v=20260727-site-controls-landing-v60')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/pod-connected-call-v63-1.css?v=20260728-v63-1')) ?>">
</head>
<body
    class="public-call-body"
    data-public-call-api="<?= e(app_url('api/public-call.php')) ?>"
    data-public-voicemail-api="<?= e(app_url('api/public-voicemail.php')) ?>"
    data-public-line-status="<?= e($status) ?>"
    data-public-call-embedded="0"
    data-voicemail-greeting-url="<?= e($greetingUrl ?? '') ?>"
    data-public-max-rings="<?= (int)call_center_max_rings() ?>"
>
    <header class="public-main-header">
        <div class="public-main-header-inner">
            <a href="<?= e(app_url('index.php')) ?>" class="public-main-brand" aria-label="Public POD profile">
                <img src="<?= e(nmm_site_logo_url()) ?>" alt="<?= e(nmm_site_logo_alt()) ?>">
            </a>
            <nav class="public-main-nav" aria-label="Connected call navigation">
                <a href="<?= e(app_url('index.php')) ?>">Public profile</a>
                <a href="<?= e(app_url('call-dave.php')) ?>">Public Call Us</a>
            </nav>
        </div>
    </header>

    <main class="public-call-main">
        <section class="public-call-intro">
            <div class="public-call-status status-<?= e($status) ?>">
                <span></span>
                <?= e(status_label($status)) ?>
            </div>
            <span class="public-call-kicker">Connected POD audio and voicemail</span>
            <h1>Call <?= e($recipientName) ?></h1>
            <div class="public-call-profile-message">
                <img src="<?= e($recipientImage) ?>" alt="<?= e($recipientName) ?> profile photo">
                <p><?= e($statusMessage) ?></p>
            </div>
        </section>

        <section class="public-call-card">
            <div class="connected-pod-banner">
                <?php if ($callerImage !== ''): ?><img src="<?= e($callerImage) ?>" alt=""><?php endif; ?>
                <div>
                    <span>Connected caller</span>
                    <strong><?= e((string)$caller['display_name']) ?></strong>
                    <small><?= e((string)$connectedContext['remote_pod_uuid']) ?></small>
                </div>
            </div>

            <nav class="public-call-tabs" aria-label="Connected contact options">
                <button class="<?= $status === 'available' ? 'active' : '' ?>" type="button" data-call-mode="live" <?= $status === 'available' ? '' : 'disabled' ?>>Call now</button>
                <button class="<?= $status !== 'available' ? 'active' : '' ?>" type="button" data-call-mode="voicemail">Leave voicemail</button>
            </nav>

            <form data-public-call-form>
                <input type="hidden" name="mode" value="<?= $status === 'available' ? 'live' : 'voicemail' ?>">
                <input type="hidden" name="name" value="<?= e((string)$caller['display_name']) ?>">
                <input type="hidden" name="email" value="<?= e((string)$caller['email']) ?>">
                <input type="hidden" name="phone" value="<?= e((string)$caller['phone']) ?>">
                <input type="hidden" name="company" value="<?= e((string)$caller['company']) ?>">
                <input aria-hidden="true" autocomplete="off" name="website" tabindex="-1" class="public-call-honeypot">

                <div class="public-call-form-grid">
                    <label class="full">
                        <span>Call topic <em>optional</em></span>
                        <input name="subject" maxlength="190" value="<?= e((string)$caller['subject']) ?>">
                    </label>
                </div>

                <label class="public-call-consent" <?= $status === 'available' ? '' : 'hidden' ?>>
                    <input type="checkbox" name="microphone_consent" value="1">
                    <span>I understand that the browser will request microphone access for this live audio call. This call is not recorded automatically.</span>
                </label>

                <section class="public-voicemail-recorder" data-voicemail-recorder <?= $status === 'available' ? 'hidden' : '' ?>>
                    <header>
                        <div>
                            <span>Private voice message</span>
                            <strong>Record a voicemail for <?= e($recipientName) ?></strong>
                            <small>Maximum <?= (int)(call_center_config()['voicemail_max_seconds'] ?? 180) ?> seconds. Playback is available before sending.</small>
                        </div>
                        <time data-voicemail-duration>00:00</time>
                    </header>
                    <div class="public-voicemail-meter" data-voicemail-meter>
                        <span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <div class="public-voicemail-controls">
                        <button type="button" data-voicemail-record>Record voicemail</button>
                        <button type="button" data-voicemail-stop disabled>Stop</button>
                        <button type="button" data-voicemail-reset disabled>Record again</button>
                    </div>
                    <audio data-voicemail-preview controls preload="metadata" hidden></audio>
                    <label class="public-call-consent">
                        <input type="checkbox" name="voicemail_consent" value="1">
                        <span>I consent to recording and securely storing this voicemail in the recipient’s private Call Center CRM.</span>
                    </label>
                    <audio data-voicemail-greeting preload="auto" <?= $greetingUrl ? 'src="' . e($greetingUrl) . '"' : '' ?> hidden></audio>
                    <p data-voicemail-status><?= $greetingUrl ? e($recipientName) . '’s greeting will play before recording.' : 'Select Record voicemail when you are ready.' ?></p>
                </section>

                <div class="public-call-actions">
                    <button class="public-call-primary" type="submit" data-public-submit><?= $status === 'available' ? 'Start connected call' : 'Send voicemail' ?></button>
                </div>
            </form>

            <section class="public-call-session" data-public-call-session hidden>
                <div class="public-call-orb"><span></span></div>
                <small data-public-call-status>Notifying <?= e($recipientName) ?>…</small>
                <h2 data-public-call-title>Your call is ringing</h2>
                <p data-public-call-copy>Keep this page open while the recipient answers.</p>
                <time data-public-call-duration>00:00</time>
                <div class="public-call-controls">
                    <button type="button" data-public-mute>Mute</button>
                    <button type="button" class="end" data-public-end>End call</button>
                </div>
                <audio data-public-remote-audio autoplay playsinline hidden></audio>
            </section>

            <div class="public-call-result" data-public-call-result hidden></div>
            <div class="public-call-recovery" data-public-call-recovery hidden>
                <button type="button" data-microphone-retry>Retry microphone</button>
                <button type="button" data-switch-voicemail>Leave a voicemail instead</button>
            </div>
            <p class="connected-call-privacy">This connected link identifies the approved POD relationship. Live media still uses the existing direct-only browser calling engine.</p>
            <a class="connected-call-back" href="<?= e(app_url('call-dave.php')) ?>">Use the public call page instead</a>
        </section>
    </main>

    <footer class="public-call-footer"><span><?= e((string)$recipientName) ?> · Connected POD calling</span></footer>
    <script src="<?= e(app_url('assets/js/visitor-activity.js?v=20260727-site-controls-landing-v60')) ?>"></script>
    <script src="<?= e(app_url('assets/js/public-call.js?v=20260727-site-controls-landing-v60')) ?>"></script>
</body>
</html>

<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
define('NMM_PUBLIC_MICROPHONE_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('call_us');
require_once __DIR__ . '/portal/call-center.php';

$status = call_center_public_status();
$statusMessage = call_center_public_status_message();
$publicProfile = primary_admin_profile();
$publicProfileName = public_profile_name();
$contactEmail = public_contact_email();
$contactPhone = public_contact_phone();
$publicProfileImageUrl = user_profile_image_url($publicProfile);
$embedded = (string)($_GET['embed'] ?? '') === '1';
$portalUser = current_user();
$portalDashboardUrl = app_url('portal/login.php?role=client');
$portalAccountUrl = app_url('portal/login.php?role=client');
$portalRoleLabel = 'Visitor';
$portalProfileImageUrl = app_url('assets/images/david-evans-profile.jpg');
$greetingUrl = call_center_public_greeting_url();

if ($portalUser) {
    $portalScript = $portalUser['role'] === 'admin'
        ? 'admin.php'
        : 'client.php';
    $portalDashboardUrl = app_url('portal/' . $portalScript);
    $portalAccountUrl = app_url(
        'portal/' . $portalScript . '?view=account'
    );
    $portalRoleLabel = $portalUser['role'] === 'admin'
        ? 'Administrator'
        : 'Client';
    $portalProfileImageUrl = user_profile_image_url($portalUser);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="build-version" content="20260727-site-controls-landing-v60">
    <title>Call Us — North Mountain Media</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/call-dave.css?v=20260727-site-controls-landing-v60')) ?>">
</head>
<body
    class="public-call-body <?= $embedded ? 'public-call-embed' : '' ?>"
    data-public-call-api="<?= e(app_url('api/public-call.php')) ?>"
    data-public-voicemail-api="<?= e(app_url('api/public-voicemail.php')) ?>"
    data-public-line-status="<?= e($status) ?>"
    data-public-call-embedded="<?= $embedded ? '1' : '0' ?>"
    data-voicemail-greeting-url="<?= e($greetingUrl ?? '') ?>"
    data-public-max-rings="<?= (int)call_center_max_rings() ?>"
>
    <?php if (!$embedded): ?>
    <header class="public-main-header">
        <div class="public-main-header-inner">
            <a
                href="<?= e(app_url('index.php')) ?>"
                class="public-main-brand"
                aria-label="North Mountain Media portfolio"
            >
                <img
                    src="<?= e(nmm_site_logo_url()) ?>"
                    alt="<?= e(nmm_site_logo_alt()) ?>"
                >
            </a>

            <nav
                class="public-main-nav"
                aria-label="Main navigation"
            >
                <a href="<?= e(app_url('index.php')) ?>">
                    Home
                </a>

                <?php if ($portalUser): ?>
                    <div class="public-account" data-public-account>
                        <button
                            class="public-account-toggle"
                            type="button"
                            data-public-account-toggle
                            aria-expanded="false"
                        >
                            <img src="<?= e($portalProfileImageUrl) ?>" alt="">
                            <span>
                                <strong><?= e($portalUser['display_name']) ?></strong>
                                <small><?= e($portalRoleLabel) ?></small>
                            </span>
                            <em aria-hidden="true">⌄</em>
                        </button>

                        <div class="public-account-menu" data-public-account-menu hidden>

                            <a href="<?= e($portalDashboardUrl) ?>">Dashboard</a>
                            <a href="<?= e($portalAccountUrl) ?>">Account settings</a>
                            <a href="<?= e(app_url('portal/logout.php')) ?>">Sign out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a
                        class="primary"
                        href="<?= e(app_url('portal/login.php?role=client')) ?>"
                    >
                        Client Login
                    </a>
                    <a
                        href="<?= e(app_url('portal/login.php?role=admin')) ?>"
                    >
                        Admin Login
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <?php endif; ?>

    <main class="public-call-main">
        <?php if (!$embedded): ?>
        <section class="public-call-intro">
            <div class="public-call-status status-<?= e($status) ?>">
                <span></span>
                <?= e(status_label($status)) ?>
            </div>

            <span class="public-call-kicker">Browser audio and voicemail</span>
            <h1>Call Us</h1>

            <div class="public-call-profile-message">
                <img
                    src="<?= e($publicProfileImageUrl) ?>"
                    alt="<?= e($publicProfileName) ?> profile photo"
                >
                <p><?= e($statusMessage) ?></p>
            </div>

            <?php if ($contactEmail !== ''): ?>
            <div class="public-call-direct">
                <a href="mailto:<?= e($contactEmail) ?>">
                    Email <?= e(explode(' ', $publicProfileName)[0] ?: 'Dave') ?>
                </a>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="public-call-card">
            <nav class="public-call-tabs" aria-label="Contact options">
                <button
                    class="<?= $status === 'available' ? 'active' : '' ?>"
                    type="button"
                    data-call-mode="live"
                    <?= $status === 'available' ? '' : 'disabled' ?>
                >Call Us</button>
                <button
                    class="<?= $status !== 'available' ? 'active' : '' ?>"
                    type="button"
                    data-call-mode="voicemail"
                >Leave voicemail</button>
            </nav>

            <form data-public-call-form>
                <input type="hidden" name="mode" value="<?= $status === 'available' ? 'live' : 'voicemail' ?>">
                <input
                    aria-hidden="true"
                    autocomplete="off"
                    name="website"
                    tabindex="-1"
                    class="public-call-honeypot"
                >

                <div class="public-call-form-grid">
                    <label>
                        <span>Name</span>
                        <input name="name" autocomplete="name" value="<?= e($portalUser['display_name'] ?? '') ?>" required>
                    </label>

                    <label>
                        <span>Email <em>optional</em></span>
                        <input name="email" type="email" autocomplete="email" value="<?= e($portalUser['email'] ?? '') ?>" placeholder="Optional">
                    </label>

                    <label>
                        <span>Phone <em>optional</em></span>
                        <input name="phone" type="tel" autocomplete="tel" value="<?= e($portalUser['phone'] ?? '') ?>">
                    </label>

                    <label>
                        <span>Company <em>optional</em></span>
                        <input name="company" autocomplete="organization" value="<?= e($portalUser['company'] ?? '') ?>">
                    </label>

                    <label class="full">
                        <span>Call topic <em>optional</em></span>
                        <input name="subject" maxlength="190" placeholder="Optional">
                    </label>
</div>

                <label class="public-call-consent" <?= $status === 'available' ? '' : 'hidden' ?>>
                    <input type="checkbox" name="microphone_consent" value="1">
                    <span>I understand that the browser will request microphone access for a live audio call. This call is not recorded automatically.</span>
                </label>
<section
                    class="public-voicemail-recorder"
                    data-voicemail-recorder
                    <?= $status === 'available' ? 'hidden' : '' ?>
                >
                    <header>
                        <div>
                            <span>Private voice message</span>
                            <strong>Record a voicemail for Dave</strong>
                            <small>Maximum <?= (int)(call_center_config()['voicemail_max_seconds'] ?? 180) ?> seconds. Playback is available before sending.</small>
                        </div>
                        <time data-voicemail-duration>00:00</time>
                    </header>

                    <div class="public-voicemail-meter" data-voicemail-meter>
                        <span></span><span></span><span></span><span></span>
                        <span></span><span></span><span></span><span></span>
                    </div>

                    <div class="public-voicemail-controls">
                        <button type="button" data-voicemail-record>
                            Record voicemail
                        </button>
                        <button type="button" data-voicemail-stop disabled>
                            Stop
                        </button>
                        <button type="button" data-voicemail-reset disabled>
                            Record again
                        </button>
                    </div>

                    <audio
                        data-voicemail-preview
                        controls
                        preload="metadata"
                        hidden
                    ></audio>

                    <label class="public-call-consent">
                        <input
                            type="checkbox"
                            name="voicemail_consent"
                            value="1"
                        >
                        <span>I consent to recording and securely storing this voicemail in Dave’s private Call Center CRM.</span>
                    </label>

                    <audio
                        data-voicemail-greeting
                        preload="auto"
                        <?= $greetingUrl ? 'src="' . e($greetingUrl) . '"' : '' ?>
                        hidden
                    ></audio>

                    <p data-voicemail-status>
                        <?php if ($greetingUrl): ?>
                            Dave’s greeting will play before recording.
                        <?php else: ?>
                            Select Record voicemail when you are ready.
                        <?php endif; ?>
                    </p>
                </section>

                <div class="public-call-actions">
                    <button class="public-call-primary" type="submit" data-public-submit>
                        <?= $status === 'available' ? 'Start browser call' : 'Send voicemail' ?>
                    </button>
</div>
            </form>

            <section class="public-call-session" data-public-call-session hidden>
                <div class="public-call-orb"><span></span></div>
                <small data-public-call-status>Notifying Dave…</small>
                <h2 data-public-call-title>Your call is ringing</h2>
                <p data-public-call-copy>Keep this page open while Dave answers.</p>
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
                <?php if ($embedded): ?>
                    <a href="<?= e(app_url('call-dave.php')) ?>" target="_blank" rel="noopener">Open full call page</a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php if (!$embedded): ?>
    <footer class="public-call-footer">
        <span>North Mountain Media · Phoenix, Arizona</span>
</footer>
    <?php endif; ?>

    <script src="<?= e(app_url('assets/js/visitor-activity.js?v=20260727-site-controls-landing-v60')) ?>"></script>
    <script src="<?= e(app_url('assets/js/public-call.js?v=20260727-site-controls-landing-v60')) ?>"></script>
</body>
</html>

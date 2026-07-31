<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';

$settings = activitypub_settings();
$error = '';

if (is_post()) {
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Cross-origin request denied.');
    }
    verify_csrf();
    if (rate_limit_exceeded('remote_follow_redirect', request_ip(), 20, 3600)) {
        http_response_code(429);
        exit('Too many remote follow attempts. Try again later.');
    }
    $handle = ltrim(strtolower(input('fediverse_handle')), '@');
    if (!preg_match('/^([a-z0-9_.-]{1,64})@([a-z0-9.-]{1,253})$/', $handle, $matches)) {
        $error = 'Enter a Fediverse address such as name@example.social.';
    } else {
        $domain = trim($matches[2], '.');
        $validDomain = $domain !== ''
            && !str_contains($domain, '..')
            && (bool)preg_match(
                '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                $domain
            );
        if (!$validDomain) {
            $error = 'Enter a valid Fediverse server domain.';
        } else {
            $target = 'https://' . $domain . '/authorize_interaction?uri='
                . rawurlencode(activitypub_actor_url());
            header('Location: ' . $target, true, 303);
            exit;
        }
    }
}

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,follow">
<title>Follow <?=e($settings['display_name'])?></title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">
</head>
<body class="pod-follow-body">
<main class="pod-follow-card">
<a class="pod-follow-back" href="<?=e(app_url('index.php'))?>">← Back to the POD</a>
<div class="pod-social-profile-avatar" aria-hidden="true"><?=e(mb_strtoupper(mb_substr((string)$settings['display_name'],0,1)))?></div>
<span>Open social follow</span>
<h1>Follow <?=e($settings['display_name'])?></h1>
<p>Enter the address you use on Mastodon or another compatible Fediverse service. You will be sent to that service to review and approve the follow.</p>
<div class="pod-follow-identity"><strong><?=e('@'.activitypub_account())?></strong><button type="button" data-copy-value="<?=e('@'.activitypub_account())?>">Copy address</button></div>
<?php if(!$settings['enabled']):?>
<div class="pod-social-warning">ActivityPub is not currently active for this POD.</div>
<?php else:?>
<?php if($error!==''):?><div class="pod-social-warning"><?=e($error)?></div><?php endif;?>
<form method="post" class="pod-follow-form">
<?=csrf_field()?>
<label><span>Your Fediverse address</span><input name="fediverse_handle" required maxlength="320" placeholder="name@example.social" autocomplete="username"></label>
<button class="pod-follow-button" type="submit">Continue to your server</button>
</form>
<p class="pod-follow-note">The POD does not receive your password. Your server completes the signed ActivityPub Follow request.</p>
<?php endif;?>
</main>
<script src="<?=e(app_url('assets/js/social-posts-v66p.js?v=20260731-v66P'))?>"></script>
</body>
</html>

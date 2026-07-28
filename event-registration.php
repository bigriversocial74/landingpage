<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('events');
require_once __DIR__ . '/portal/events-calendar.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$token = trim((string)($_GET['token'] ?? ''));
$registration = events_registration_by_token($token);
$shell = music_public_shell_context();

if (!$registration) {
    http_response_code(404);
}

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; connect-src 'self'; form-action 'self'; "
    . "base-uri 'self'; object-src 'none'; frame-ancestors 'self'"
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="build-version" content="20260728-content-controls-v62.1">
<title><?=e($registration?$registration['title']:'Registration unavailable')?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260728-content-controls-v62.1'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260728-content-controls-v62.1'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/events.css?v=20260728-content-controls-v62.1'))?>">
</head>
<body class="events-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'events');?>
<section class="music-public-workspace">
<?php music_render_public_header($shell);?>
<main class="event-confirmation-canvas">
<?php if(!$registration):?>
<div class="events-empty">This registration link is unavailable.</div>
<?php else:?>
<article class="event-confirmation-card">
<div class="event-confirmation-mark" aria-hidden="true">✓</div>
<span><?=e(events_registration_statuses()[$registration['status']]??status_label($registration['status']))?></span>
<h1><?=e($registration['title'])?></h1>
<p><?=e($registration['date_label'])?></p>
<div class="event-confirmation-grid">
<article><span>Guest</span><strong><?=e($registration['display_name'])?></strong><small><?=e($registration['email'])?></small></article>
<article><span>Party size</span><strong><?=(int)$registration['party_size']?></strong><small><?=e($registration['location_label'])?></small></article>
</div>
<?php if(in_array($registration['format_type'],['virtual','hybrid'],true)&&$registration['virtual_url']&&in_array($registration['status'],['registered','confirmed','attended'],true)):?>
<a class="event-register-primary" href="<?=e($registration['virtual_url'])?>" target="_blank" rel="noopener">Open online event link ↗</a>
<?php endif;?>
<div class="event-confirmation-actions">
<a href="<?=e(app_url('event.php?slug='.rawurlencode((string)$registration['slug'])))?>">View event</a>
<a href="<?=e(app_url('events-calendar.php?event='.rawurlencode((string)$registration['slug'])))?>">Add to calendar</a>
</div>
<?php if(!in_array($registration['status'],['cancelled','attended','no_show'],true)):?>
<form method="post" action="<?=e(app_url('api/event-register.php'))?>" class="event-cancel-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="cancel">
<input type="hidden" name="token" value="<?=e($token)?>">
<button type="submit">Cancel registration</button>
</form>
<?php elseif($registration['status']==='cancelled'):?>
<div class="event-registration-closed">This registration has been cancelled.</div>
<?php endif;?>
</article>
<?php endif;?>
</main>
</section>
</div>
<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260728-content-controls-v62.1'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260728-content-controls-v62.1'))?>"></script>
</body>
</html>

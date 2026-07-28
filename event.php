<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('events');
require_once __DIR__ . '/portal/visitor-intelligence.php';
require_once __DIR__ . '/portal/publishing.php';
require_once __DIR__ . '/portal/events-calendar.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$previewRequested = !empty($_GET['preview']);
$previewId = max(0, (int)($_GET['id'] ?? 0));
$viewer = $previewRequested ? current_user() : null;
$isAdminPreview = (
    $previewRequested
    && $previewId > 0
    && $viewer
    && ($viewer['role'] ?? '') === 'admin'
);
$event = $isAdminPreview
    ? events_public_preview($previewId)
    : events_public_event_by_slug($slug, true);
$shell = music_public_shell_context();

if (!$event) {
    http_response_code(404);
}

$title = $event
    ? ($event['seo_title'] ?: $event['title'])
    : 'Event unavailable';
$description = $event
    ? ($event['seo_description'] ?: $event['summary'] ?: 'North Mountain Media event details.')
    : 'The requested event is unavailable.';
$canonicalUrl = $event
    ? events_absolute_url('event.php?slug=' . rawurlencode((string)$event['slug']))
    : events_absolute_url('events.php');
$coverUrl = $event && $event['cover_url'] !== ''
    ? events_absolute_url('event-cover.php?id=' . (int)$event['id'])
    : '';
$startLocal = $event
    ? events_local_datetime($event['start_at'], $event['timezone'])
    : null;
$endLocal = $event
    ? events_local_datetime($event['end_at'], $event['timezone'])
    : null;
$structuredData = $event
    ? [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event['title'],
        'description' => $description,
        'startDate' => $startLocal?->format(DATE_ATOM),
        'endDate' => $endLocal?->format(DATE_ATOM),
        'eventStatus' => $event['status'] === 'cancelled'
            ? 'https://schema.org/EventCancelled'
            : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => match ($event['format_type']) {
            'virtual' => 'https://schema.org/OnlineEventAttendanceMode',
            'hybrid' => 'https://schema.org/MixedEventAttendanceMode',
            default => 'https://schema.org/OfflineEventAttendanceMode',
        },
        'location' => $event['format_type'] === 'virtual'
            ? [
                '@type' => 'VirtualLocation',
                'url' => $canonicalUrl,
            ]
            : [
                '@type' => 'Place',
                'name' => $event['location_name'] ?: $event['location_label'],
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $event['address_line'] ?: null,
                    'addressLocality' => $event['city'] ?: null,
                    'addressRegion' => $event['region'] ?: null,
                    'postalCode' => $event['postal_code'] ?: null,
                ],
            ],
        'image' => $coverUrl !== '' ? [$coverUrl] : null,
        'url' => $canonicalUrl,
        'organizer' => [
            '@type' => 'Organization',
            'name' => 'North Mountain Media',
            'url' => events_absolute_url('index.php'),
        ],
        'offers' => [
            '@type' => 'Offer',
            'url' => $canonicalUrl . '#register',
            'price' => number_format(((int)$event['price_cents']) / 100, 2, '.', ''),
            'priceCurrency' => $event['currency'] ?: 'USD',
            'availability' => $event['registration_state']['open']
                ? 'https://schema.org/InStock'
                : 'https://schema.org/SoldOut',
        ],
    ]
    : null;
$registrationError = trim((string)($_GET['registration_error'] ?? ''));
$portalUser = current_user();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; connect-src 'self'; "
    . "form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'"
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=e($description)?>">
<meta name="build-version" content="20260728-content-controls-v62.1">
<link rel="canonical" href="<?=e($canonicalUrl)?>">
<?php if($isAdminPreview):?><meta name="robots" content="noindex,nofollow"><?php endif;?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?=e($title)?>">
<meta property="og:description" content="<?=e($description)?>">
<meta property="og:url" content="<?=e($canonicalUrl)?>">
<meta property="og:site_name" content="North Mountain Media">
<?php if($coverUrl!==''):?><meta property="og:image" content="<?=e($coverUrl)?>"><?php endif;?>
<meta name="twitter:card" content="<?=$coverUrl!==''?'summary_large_image':'summary'?>">
<meta name="twitter:title" content="<?=e($title)?>">
<meta name="twitter:description" content="<?=e($description)?>">
<?php if($structuredData):?>
<script type="application/ld+json"><?=json_encode(
    $structuredData,
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
)?></script>
<?php endif;?>
<title><?=e($title)?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260728-content-controls-v62.1'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260728-content-controls-v62.1'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/events.css?v=20260728-content-controls-v62.1'))?>">
</head>
<body class="events-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'events');?>
<section class="music-public-workspace">
<?php music_render_public_header($shell);?>

<main class="event-detail-canvas">
<?php if($isAdminPreview&&$event):?>
<div class="events-preview-banner">Administrator preview · <?=e(events_statuses()[$event['status']]??status_label($event['status']))?></div>
<?php endif;?>
<?php if(!$event):?>
<div class="events-empty">The requested event is not available.</div>
<?php else:?>
<nav class="events-breadcrumbs" aria-label="Breadcrumb">
<a href="<?=e(app_url('events.php'))?>">Events</a><span>›</span><strong><?=e($event['title'])?></strong>
</nav>

<?php if($event['status']==='cancelled'):?>
<div class="events-cancelled-banner"><strong>Event cancelled</strong><span>This event is no longer scheduled.</span></div>
<?php endif;?>

<article class="event-detail-layout" style="--event-color:<?=e($event['color_hex'])?>">
<div class="event-detail-main">
<?php if($event['cover_url']!==''):?>
<figure class="event-detail-cover">
<img src="<?=e($event['cover_url'])?>" alt="<?=e($event['cover_alt_text']?:$event['title'])?>">
<?php if($event['cover_caption']):?><figcaption><?=e($event['cover_caption'])?></figcaption><?php endif;?>
</figure>
<?php endif;?>
<header class="event-detail-header">
<div class="event-detail-kicker"><span><?=e(events_types()[$event['event_type']]??'Event')?></span><span><?=e(events_formats()[$event['format_type']]??'')?></span></div>
<h1><?=e($event['title'])?></h1>
<?php if($event['summary']):?><p><?=e($event['summary'])?></p><?php endif;?>
</header>

<div class="event-detail-facts">
<article><span>Date &amp; time</span><strong><?=e($event['date_label'])?></strong><small><?=e($event['timezone'])?></small></article>
<article><span>Location</span><strong><?=e($event['location_label'])?></strong><?php if($event['address_line']):?><small><?=e(implode(', ',array_filter([$event['address_line'],$event['city'],$event['region'],$event['postal_code']])))?></small><?php endif;?></article>
<article><span>Admission</span><strong><?=(int)$event['price_cents']>0?'$'.e(number_format(((int)$event['price_cents'])/100,2)):'Free'?></strong><small><?=e($event['capacity_summary']['label'])?></small></article>
</div>

<?php if($event['description']):?>
<article class="event-description">
<?=publishing_render_body((string)$event['description'])?>
</article>
<?php endif;?>

<?php if($event['tags_list']):?>
<div class="event-tags" aria-label="Event tags"><?php foreach($event['tags_list'] as $tag):?><span># <?=e($tag)?></span><?php endforeach;?></div>
<?php endif;?>
</div>

<aside class="event-detail-sidebar" id="register">
<section class="event-registration-card">
<header>
<span><?=e($event['registration_state']['label'])?></span>
<h2><?=e($event['short_date_label'])?></h2>
<p><?=e($event['location_label'])?></p>
</header>
<?php if($registrationError!==''):?><div class="event-form-message is-error"><?=e($registrationError)?></div><?php endif;?>
<div class="event-form-message" data-event-form-message hidden></div>

<?php if($event['external_registration_url']):?>
<a class="event-register-primary" href="<?=e($event['external_registration_url'])?>" target="_blank" rel="noopener">Register on external site ↗</a>
<?php elseif($event['registration_state']['open']):?>
<form class="event-registration-form" action="<?=e(app_url('api/event-register.php'))?>" method="post" data-event-registration-form>
<?=csrf_field()?>
<input type="hidden" name="action" value="register">
<input type="hidden" name="event_slug" value="<?=e($event['slug'])?>">
<label><span>Name</span><input name="display_name" required maxlength="160" autocomplete="name" value="<?=e($portalUser['display_name']??'')?>"></label>
<label><span>Email</span><input type="email" name="email" required maxlength="190" autocomplete="email" value="<?=e($portalUser['email']??'')?>"></label>
<div class="event-registration-grid">
<label><span>Phone</span><input name="phone" maxlength="60" autocomplete="tel" value="<?=e($portalUser['phone']??'')?>"></label>
<label><span>Company</span><input name="company" maxlength="190" autocomplete="organization" value="<?=e($portalUser['company']??'')?>"></label>
</div>
<label><span>Party size</span><input type="number" name="party_size" min="1" max="20" value="1"></label>
<label><span>Notes</span><textarea name="notes" rows="3" maxlength="4000" placeholder="Accessibility, scheduling, or participation notes"></textarea></label>
<label class="event-reminder-choice"><input type="checkbox" name="reminder_opt_in" value="1" checked><span>Queue an event reminder.</span></label>
<button class="event-register-primary" type="submit"><?=e($event['registration_state']['status']==='waitlist'?'Join waitlist':'Register')?></button>
</form>
<?php else:?>
<div class="event-registration-closed"><?=e($event['registration_state']['label'])?></div>
<?php endif;?>

<div class="event-registration-actions">
<?php if(events_settings()['ics_enabled']):?><a href="<?=e(app_url('events-calendar.php?event='.rawurlencode((string)$event['slug'])))?>" data-event-ics>Add to calendar</a><?php endif;?>
<a href="<?=e(app_url('events.php'))?>">All events</a>
</div>
</section>
</aside>
</article>
<?php endif;?>
</main>
</section>
</div>

<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260728-content-controls-v62.1'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260728-content-controls-v62.1'))?>"></script>
<script src="<?=e(app_url('assets/js/visitor-activity.js?v=20260728-content-controls-v62.1'))?>"></script>
<script src="<?=e(app_url('assets/js/events-calendar.js?v=20260728-content-controls-v62.1'))?>"></script>
<?php if($event&&!$isAdminPreview):?>
<script>
window.NMMVisitorActivity?.track(
  'event_detail_view',
  {
    event_label: <?=json_encode($event['title'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,
    metadata: {
      event_id: <?=(int)$event['id']?>,
      event_slug: <?=json_encode($event['slug'])?>,
      event_type: <?=json_encode($event['event_type'])?>,
      format_type: <?=json_encode($event['format_type'])?>
    },
    deduplicate: false
  }
);
</script>
<?php endif;?>
</body>
</html>

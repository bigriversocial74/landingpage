<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('events');
require_once __DIR__ . '/portal/visitor-intelligence.php';
require_once __DIR__ . '/portal/publishing.php';
require_once __DIR__ . '/portal/events-calendar.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$settings = events_settings();
$month = events_month_context((string)($_GET['month'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)$settings['posts_per_page'];

if (!isset(events_types()[$type])) {
    $type = '';
}

$totalUpcoming = events_public_count([
    'type' => $type,
    'search' => $search,
]);
$totalPages = max(1, (int)ceil($totalUpcoming / max(1, $perPage)));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$calendarEvents = events_public_events([
    'type' => $type,
    'search' => $search,
    'from' => $month['utc_start'],
    'to' => $month['utc_end'],
    'include_past' => true,
    'limit' => 250,
]);
$calendarDays = events_calendar_days($month, $calendarEvents);
$upcomingEvents = events_public_events([
    'type' => $type,
    'search' => $search,
    'limit' => $perPage,
    'offset' => $offset,
]);
$shell = music_public_shell_context();
$weekdays = $month['week_start'] === 1
    ? ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']
    : ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$queryBase = array_filter([
    'type' => $type !== '' ? $type : null,
    'q' => $search !== '' ? $search : null,
]);
$canonicalQuery = $queryBase + [
    'month' => $month['month'],
    'page' => $page > 1 ? $page : null,
];
$canonicalQuery = array_filter(
    $canonicalQuery,
    static fn(mixed $value): bool => $value !== null && $value !== ''
);
$canonicalUrl = events_absolute_url(
    'events.php' . ($canonicalQuery ? '?' . http_build_query($canonicalQuery) : '')
);

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
    . "base-uri 'self'; object-src 'none'; frame-ancestors 'self'"
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=e($settings['description'])?>">
<meta name="build-version" content="20260728-content-controls-v62.1">
<link rel="canonical" href="<?=e($canonicalUrl)?>">
<?php if($settings['ics_enabled']):?>
<link
    rel="alternate"
    type="text/calendar"
    title="<?=e($settings['title'])?> calendar"
    href="<?=e(events_absolute_url('events-calendar.php'))?>"
>
<?php endif;?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?=e($settings['intro'])?>">
<meta property="og:description" content="<?=e($settings['description'])?>">
<meta property="og:url" content="<?=e($canonicalUrl)?>">
<meta property="og:site_name" content="North Mountain Media">
<title><?=e($settings['title'])?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260728-content-controls-v62.1'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260728-content-controls-v62.1'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/events.css?v=20260728-content-controls-v62.1'))?>">
</head>
<body class="events-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'events');?>

<section class="music-public-workspace">
<?php music_render_public_header($shell);?>

<main class="events-canvas">
<header class="events-archive-header">
<div>
<span><?=e($settings['title'])?></span>
<h1><?=e($settings['intro'])?></h1>
<p><?=e($settings['description'])?></p>
</div>
<form class="events-filter-form" method="get">
<input type="hidden" name="month" value="<?=e($month['month'])?>">
<select name="type" aria-label="Event type">
<option value="">All event types</option>
<?php foreach(events_types() as $value=>$label):?>
<option value="<?=e($value)?>" <?=$type===$value?'selected':''?>><?=e($label)?></option>
<?php endforeach;?>
</select>
<input type="search" name="q" value="<?=e($search)?>" placeholder="Search events" aria-label="Search events">
<button type="submit">Filter</button>
</form>
</header>

<div class="events-view-toolbar">
<div class="events-view-toggle" role="group" aria-label="Event view">
<button type="button" class="active" data-events-view="calendar">Calendar</button>
<button type="button" data-events-view="list">Upcoming</button>
</div>
<?php if($settings['ics_enabled']):?>
<a href="<?=e(app_url('events-calendar.php'))?>" data-event-ics>Subscribe / download calendar</a>
<?php endif;?>
</div>

<section class="events-calendar-panel" data-events-panel="calendar">
<header class="events-calendar-header">
<a href="<?=e(app_url('events.php?'.http_build_query($queryBase+['month'=>$month['previous']])))?>">← Previous</a>
<div><span>Calendar</span><h2><?=e($month['label'])?></h2></div>
<a href="<?=e(app_url('events.php?'.http_build_query($queryBase+['month'=>$month['next']])))?>">Next →</a>
</header>
<div class="events-calendar-weekdays" aria-hidden="true">
<?php foreach($weekdays as $weekday):?><span><?=e($weekday)?></span><?php endforeach;?>
</div>
<div class="events-calendar-grid">
<?php foreach($calendarDays as $day):?>
<article class="events-calendar-day <?=$day['current_month']?'':'is-outside'?> <?=$day['today']?'is-today':''?>">
<header><span><?=$day['date']->format('j')?></span></header>
<div>
<?php foreach(array_slice($day['events'],0,3) as $event):?>
<a
    class="events-calendar-chip"
    href="<?=e($event['url'])?>"
    style="--event-color:<?=e($event['color_hex'])?>"
>
<span><?=e(events_local_datetime($event['start_at'],$event['timezone'])?->format(!empty($event['all_day'])?'All day':'g:i A')??'')?></span>
<strong><?=e($event['title'])?></strong>
</a>
<?php endforeach;?>
<?php if(count($day['events'])>3):?><small>+<?=count($day['events'])-3?> more</small><?php endif;?>
</div>
</article>
<?php endforeach;?>
</div>
</section>

<section class="events-upcoming-panel" data-events-panel="list">
<header class="events-section-heading">
<div><span>Upcoming schedule</span><h2><?=count($upcomingEvents)?> events on this page</h2></div>
</header>
<?php if($upcomingEvents):?>
<div class="events-card-grid">
<?php foreach($upcomingEvents as $event):?>
<article class="events-card" style="--event-color:<?=e($event['color_hex'])?>">
<div class="events-card-media">
<?php if($event['cover_url']!==''):?>
<a href="<?=e($event['url'])?>"><img src="<?=e($event['cover_url'])?>" alt="<?=e($event['cover_alt_text']?:$event['title'])?>" loading="lazy"></a>
<?php else:?>
<a class="events-card-placeholder" href="<?=e($event['url'])?>"><span><?=e(events_local_datetime($event['start_at'],$event['timezone'])?->format('M')??'')?></span><strong><?=e(events_local_datetime($event['start_at'],$event['timezone'])?->format('j')??'')?></strong></a>
<?php endif;?>
</div>
<div class="events-card-copy">
<div class="events-card-kicker"><span><?=e(events_types()[$event['event_type']]??'Event')?></span><span><?=e(events_formats()[$event['format_type']]??'')?></span></div>
<h2><a href="<?=e($event['url'])?>"><?=e($event['title'])?></a></h2>
<p><?=e($event['summary']?:$event['location_label'])?></p>
<div class="events-card-meta"><span><?=e($event['date_label'])?></span><span><?=e($event['location_label'])?></span></div>
<footer>
<a href="<?=e($event['url'])?>">View event →</a>
<span class="events-registration-state is-<?=e($event['registration_state']['status'])?>"><?=e($event['registration_state']['label'])?></span>
</footer>
</div>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="events-empty">No upcoming events matched the selected filters.</div>
<?php endif;?>

<?php if($totalPages>1):?>
<nav class="events-pagination" aria-label="Events pagination">
<?php if($page>1):?><a href="<?=e(app_url('events.php?'.http_build_query($queryBase+['month'=>$month['month'],'page'=>$page-1])))?>">← Previous</a><?php endif;?>
<span>Page <?=$page?> of <?=$totalPages?></span>
<?php if($page<$totalPages):?><a href="<?=e(app_url('events.php?'.http_build_query($queryBase+['month'=>$month['month'],'page'=>$page+1])))?>">Next →</a><?php endif;?>
</nav>
<?php endif;?>
</section>
</main>
</section>
</div>

<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260728-content-controls-v62.1'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260728-content-controls-v62.1'))?>"></script>
<script src="<?=e(app_url('assets/js/visitor-activity.js?v=20260728-content-controls-v62.1'))?>"></script>
<script src="<?=e(app_url('assets/js/events-calendar.js?v=20260728-content-controls-v62.1'))?>"></script>
<script>
window.NMMVisitorActivity?.track(
  'events_calendar_view',
  {
    event_label: <?=json_encode($month['label'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,
    metadata: {
      month: <?=json_encode($month['month'])?>,
      event_type: <?=json_encode($type)?>,
      search: <?=json_encode($search)?>
    },
    deduplicate: false
  }
);
</script>
</body>
</html>

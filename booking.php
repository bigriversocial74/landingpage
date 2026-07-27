<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('bookings');
require_once __DIR__ . '/portal/visitor-intelligence.php';
require_once __DIR__ . '/portal/appointments-booking.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$settings = booking_settings();
$types = booking_types(true);
$timezone = booking_valid_timezone(
    (string)($_GET['timezone'] ?? $settings['default_timezone'])
);
$typeSlug = trim((string)($_GET['type'] ?? ''));
$selectedType = $typeSlug !== ''
    ? booking_type_by_slug($typeSlug, true)
    : ($types[0] ?? null);

if (!$selectedType && $types) {
    $selectedType = $types[0];
}

$availableDates = $selectedType
    ? booking_next_available_dates(
        $selectedType,
        $timezone,
        min(
            $settings['public_window_days'],
            (int)$selectedType['maximum_days_ahead']
        ),
        14
    )
    : [];
$date = trim((string)($_GET['date'] ?? ''));

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    || !in_array(
        $date,
        array_column($availableDates, 'value'),
        true
    )
) {
    $date = (string)($availableDates[0]['value'] ?? '');
}

$slots = $selectedType && $date !== ''
    ? booking_slots_for_date(
        $selectedType,
        $date,
        $timezone,
        0,
        60
    )
    : [];
$selectedSlotToken = trim((string)($_GET['slot'] ?? ''));
$selectedSlot = null;

if ($selectedSlotToken !== '') {
    $parsed = booking_parse_slot_token($selectedSlotToken);

    if (
        $parsed
        && $selectedType
        && $parsed['type_id'] === (int)$selectedType['id']
    ) {
        $selectedSlot = booking_slot_is_available(
            $selectedType,
            $parsed['start_utc'],
            $parsed['timezone']
        );
    }
}

$shell = music_public_shell_context();
$portalUser = current_user();
$bookingAvailable = (
    booking_schema_available()
    && $settings['enabled']
    && $selectedType
    && $availableDates
);
$canonicalUrl = booking_absolute_url('booking.php');
$pageTitle = $settings['title'] ?: 'Book a Meeting';
$bookingError = trim((string)($_GET['booking_error'] ?? ''));

try {
    visitor_intelligence_track(
        'booking_page_view',
        [
            'event_label' => $selectedType
                ? (string)$selectedType['name']
                : $pageTitle,
            'page_path' => 'booking.php',
            'metadata' => [
                'booking_type_id' => $selectedType
                    ? (int)$selectedType['id']
                    : null,
                'booking_type_slug' => $selectedType['slug'] ?? null,
                'timezone' => $timezone,
                'date' => $date !== '' ? $date : null,
                'available' => $bookingAvailable,
            ],
        ]
    );

    if ($slots) {
        visitor_intelligence_track(
            'booking_slot_view',
            [
                'event_label' => (string)$selectedType['name'],
                'page_path' => 'booking.php',
                'metadata' => [
                    'booking_type_id' => (int)$selectedType['id'],
                    'date' => $date,
                    'timezone' => $timezone,
                    'slot_count' => count($slots),
                ],
            ]
        );
    }
} catch (Throwable $exception) {
    error_log(
        'North Mountain Media booking tracking failed: '
        . $exception->getMessage()
    );
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self'; style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; connect-src 'self'; "
    . "form-action 'self'; base-uri 'self'; object-src 'none'; "
    . "frame-ancestors 'self'"
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=e($settings['description'])?>">
<meta name="build-version" content="20260727-site-controls-landing-v60">
<link rel="canonical" href="<?=e($canonicalUrl)?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?=e($pageTitle)?>">
<meta property="og:description" content="<?=e($settings['description'])?>">
<meta property="og:url" content="<?=e($canonicalUrl)?>">
<meta property="og:site_name" content="North Mountain Media">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?=e($pageTitle)?>">
<meta name="twitter:description" content="<?=e($settings['description'])?>">
<title><?=e($pageTitle)?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/bookings.css?v=20260727-site-controls-landing-v60'))?>">
</head>
<body class="bookings-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'bookings');?>
<section class="music-public-workspace">
<?php music_render_public_header($shell);?>
<main class="booking-canvas">
<?php if($bookingError!==''):?><div class="booking-public-alert is-error"><?=e($bookingError)?></div><?php endif;?>
<header class="booking-hero">
<div>
<span>Appointments &amp; Booking</span>
<h1><?=e($settings['intro'])?></h1>
<p><?=e($settings['description'])?></p>
</div>
<div class="booking-availability-status <?=$bookingAvailable?'is-available':'is-unavailable'?>">
<strong><?=$bookingAvailable?'Times available':'No times available'?></strong>
<span><?=$bookingAvailable?'Choose a meeting type, date, and time.':'Events remain available in the sidebar. Booking appears there only when a real future time can be selected.'?></span>
</div>
</header>

<?php if(!booking_schema_available()):?>
<section class="booking-empty">
<h2>Booking setup is not complete.</h2>
<p>The appointment database migration has not been imported.</p>
<a href="<?=e(app_url('events.php'))?>">View Events</a>
</section>
<?php elseif(!$settings['enabled']||!$types):?>
<section class="booking-empty">
<h2>Online booking is currently unavailable.</h2>
<p>Browse upcoming events or use Call Us to get in touch.</p>
<div><a href="<?=e(app_url('events.php'))?>">View Events</a><a href="<?=e(app_url('call-dave.php'))?>">Call Us</a></div>
</section>
<?php elseif(!$availableDates||!$selectedType):?>
<section class="booking-empty">
<h2>No appointment times are currently available.</h2>
<p>The Bookings sidebar item remains hidden until an active appointment type has an open future slot.</p>
<a href="<?=e(app_url('events.php'))?>">View Events</a>
</section>
<?php else:?>
<div class="booking-layout">
<section class="booking-flow">
<section class="booking-step">
<header><span>01</span><div><small>Meeting type</small><h2>What would you like to discuss?</h2></div></header>
<div class="booking-type-grid">
<?php foreach($types as $type):?>
<a
    class="<?=$selectedType&&$selectedType['id']===$type['id']?'active':''?>"
    style="--booking-color:<?=e($type['color_hex'])?>"
    href="?<?=e(http_build_query([
        'type' => $type['slug'],
        'timezone' => $timezone,
    ]))?>"
>
<span><?=e($type['duration_minutes'])?> minutes</span>
<strong><?=e($type['name'])?></strong>
<p><?=e($type['description']?:'Choose this appointment type.')?></p>
<small><?=e(booking_location_modes()[$type['location_mode']]??status_label($type['location_mode']))?></small>
</a>
<?php endforeach;?>
</div>
</section>

<section class="booking-step">
<header><span>02</span><div><small>Timezone</small><h2>Where will you be joining from?</h2></div></header>
<form class="booking-timezone-form" method="get">
<input type="hidden" name="type" value="<?=e($selectedType['slug'])?>">
<select name="timezone" aria-label="Display timezone">
<?php foreach(booking_timezones() as $value=>$label):?>
<option value="<?=e($value)?>" <?=$timezone===$value?'selected':''?>><?=e($label)?></option>
<?php endforeach;?>
</select>
<button type="submit">Update timezone</button>
</form>
<p class="booking-timezone-note">All appointment times below are shown in <strong><?=e($timezone)?></strong>.</p>
</section>

<section class="booking-step">
<header><span>03</span><div><small>Date</small><h2>Choose an available day.</h2></div></header>
<div class="booking-date-strip">
<?php foreach($availableDates as $availableDate):?>
<a
    class="<?=$date===$availableDate['value']?'active':''?>"
    href="?<?=e(http_build_query([
        'type' => $selectedType['slug'],
        'timezone' => $timezone,
        'date' => $availableDate['value'],
    ]))?>"
>
<span><?=e(substr($availableDate['label'],0,3))?></span>
<strong><?=e(substr($availableDate['label'],5))?></strong>
</a>
<?php endforeach;?>
</div>
</section>

<section class="booking-step">
<header><span>04</span><div><small>Time</small><h2><?=e((new DateTimeImmutable($date.' 12:00:00',new DateTimeZone($timezone)))->format('l, F j'))?></h2></div></header>
<?php if($slots):?>
<div class="booking-slot-grid">
<?php foreach($slots as $slot):?>
<a
    class="<?=$selectedSlot&&$selectedSlot['start_utc']===$slot['start_utc']?'active':''?>"
    href="?<?=e(http_build_query([
        'type' => $selectedType['slug'],
        'timezone' => $timezone,
        'date' => $date,
        'slot' => $slot['token'],
    ]))?>#details"
>
<strong><?=e($slot['short_time_label'])?></strong>
<span><?=e($selectedType['duration_minutes'])?> min</span>
</a>
<?php endforeach;?>
</div>
<?php else:?>
<div class="booking-inline-empty">Those times were just booked. Choose another available date.</div>
<?php endif;?>
</section>

<?php if($selectedSlot):?>
<section class="booking-step booking-details-step" id="details">
<header><span>05</span><div><small>Your details</small><h2>Request this appointment.</h2></div></header>
<div class="booking-selected-summary">
<div style="--booking-color:<?=e($selectedType['color_hex'])?>">
<span><?=e($selectedType['name'])?></span>
<strong><?=e($selectedSlot['date_label'])?></strong>
<small><?=e($selectedSlot['time_label'])?> · <?=e($timezone)?></small>
</div>
</div>
<form class="booking-form" action="<?=e(app_url('api/booking-submit.php'))?>" method="post" data-booking-form>
<?=csrf_field()?>
<input type="hidden" name="action" value="create">
<input type="hidden" name="slot_token" value="<?=e($selectedSlot['token'])?>">
<div class="booking-form-message" data-booking-form-message hidden></div>
<div class="booking-form-grid">
<label><span>Name</span><input name="display_name" required maxlength="160" autocomplete="name" value="<?=e($portalUser['display_name']??'')?>"></label>
<label><span>Email</span><input type="email" name="email" required maxlength="190" autocomplete="email" value="<?=e($portalUser['email']??'')?>"></label>
<label><span>Phone</span><input name="phone" maxlength="60" autocomplete="tel" value="<?=e($portalUser['phone']??'')?>"></label>
<label><span>Company</span><input name="company" maxlength="190" autocomplete="organization" value="<?=e($portalUser['company']??'')?>"></label>
<label class="full"><span>What would you like to discuss?</span><input name="subject" maxlength="190" placeholder="Project, product, support need, or meeting goal"></label>
<?php if($selectedType['location_mode']==='client_choice'):?>
<label><span>Meeting format</span><select name="location_mode"><option value="video">Video</option><option value="phone">Phone</option><option value="in_person">In person</option></select></label>
<?php endif;?>
<label class="full"><span>Notes</span><textarea name="notes" rows="5" maxlength="5000" placeholder="Share useful context before the meeting."></textarea></label>
<label class="booking-check full"><input type="checkbox" name="reminder_opt_in" value="1" checked><span>Create an appointment reminder.</span></label>
</div>
<button class="booking-submit" type="submit"><?=$selectedType['confirmation_mode']==='automatic'?'Confirm appointment':'Request appointment'?></button>
<p class="booking-form-note">You will receive a secure confirmation page where you can review, reschedule, cancel, and download the calendar file.</p>
</form>
</section>
<?php endif;?>
</section>

<aside class="booking-sidebar-card">
<span>Selected appointment</span>
<h2><?=e($selectedType['name'])?></h2>
<p><?=e($selectedType['description'])?></p>
<dl>
<div><dt>Duration</dt><dd><?=e($selectedType['duration_minutes'])?> minutes</dd></div>
<div><dt>Format</dt><dd><?=e(booking_location_modes()[$selectedType['location_mode']]??status_label($selectedType['location_mode']))?></dd></div>
<div><dt>Notice</dt><dd><?=e($selectedType['minimum_notice_hours'])?> hours</dd></div>
<div><dt>Confirmation</dt><dd><?=e(booking_confirmation_modes()[$selectedType['confirmation_mode']]??status_label($selectedType['confirmation_mode']))?></dd></div>
</dl>
<?php if($selectedSlot):?><div class="booking-side-selection"><strong><?=e($selectedSlot['date_label'])?></strong><span><?=e($selectedSlot['time_label'])?></span></div><?php endif;?>
<a href="<?=e(app_url('events.php'))?>">View Events</a>
</aside>
</div>
<?php endif;?>
</main>
</section>
</div>
<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/bookings.js?v=20260727-site-controls-landing-v60'))?>"></script>
</body>
</html>

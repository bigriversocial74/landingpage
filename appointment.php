<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('bookings');
require_once __DIR__ . '/portal/visitor-intelligence.php';
require_once __DIR__ . '/portal/appointments-booking.php';
require_once __DIR__ . '/portal/proposals-intake.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$token = strtolower(trim((string)($_GET['token'] ?? '')));
$appointment = booking_appointment_by_token($token);
$shell = music_public_shell_context();
$timezone = booking_valid_timezone(
    (string)(
        $_GET['timezone']
        ?? $appointment['timezone']
        ?? booking_settings()['default_timezone']
    )
);
$rescheduleMode = !empty($_GET['reschedule']);
$bookingError = trim((string)($_GET['booking_error'] ?? ''));
$notice = '';

if (!empty($_GET['rescheduled'])) {
    $notice = 'Your appointment was rescheduled.';
} elseif (!empty($_GET['cancelled'])) {
    $notice = 'Your appointment was cancelled.';
}

$type = $appointment
    ? booking_type_by_id((int)$appointment['booking_type_id'])
    : null;
$intakeTemplate = $appointment
    ? intake_template_for_booking_type((int)$appointment['booking_type_id'])
    : null;
$availableDates = [];
$rescheduleDate = trim((string)($_GET['date'] ?? ''));
$rescheduleSlots = [];

if (
    $appointment
    && $type
    && $rescheduleMode
    && in_array($appointment['status'], ['requested','confirmed'], true)
) {
    $availableDates = booking_next_available_dates(
        $type,
        $timezone,
        min(
            booking_settings()['public_window_days'],
            (int)$type['maximum_days_ahead']
        ),
        14,
        (int)$appointment['id']
    );

    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rescheduleDate)
        || !in_array(
            $rescheduleDate,
            array_column($availableDates, 'value'),
            true
        )
    ) {
        $rescheduleDate = (string)($availableDates[0]['value'] ?? '');
    }

    if ($rescheduleDate !== '') {
        $rescheduleSlots = booking_slots_for_date(
            $type,
            $rescheduleDate,
            $timezone,
            (int)$appointment['id'],
            60
        );
    }
}

if (!$appointment) {
    http_response_code(404);
}

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self'; style-src 'self' 'unsafe-inline'; "
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
<meta name="build-version" content="20260727-site-controls-landing-v60">
<title><?=e($appointment?$appointment['booking_type_name']:'Appointment unavailable')?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/bookings.css?v=20260727-site-controls-landing-v60'))?>">
</head>
<body class="bookings-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'bookings');?>
<section class="music-public-workspace">
<?php music_render_public_header($shell);?>
<main class="appointment-canvas">
<?php if(!$appointment):?>
<section class="booking-empty">
<h1>This appointment link is unavailable.</h1>
<p>The secure confirmation token could not be verified.</p>
<a href="<?=e(app_url('events.php'))?>">View Events</a>
</section>
<?php else:?>
<?php if($notice!==''):?><div class="booking-public-alert is-success"><?=e($notice)?></div><?php endif;?>
<?php if($bookingError!==''):?><div class="booking-public-alert is-error"><?=e($bookingError)?></div><?php endif;?>

<article class="appointment-card">
<header style="--booking-color:<?=e($appointment['color_hex'])?>">
<div class="appointment-status-mark" aria-hidden="true"><?=in_array($appointment['status'],['confirmed','completed'],true)?'✓':'•'?></div>
<span><?=e(booking_statuses()[$appointment['status']]??status_label($appointment['status']))?></span>
<h1><?=e($appointment['booking_type_name'])?></h1>
<p><?=e($appointment['date_label'])?> · <?=e($appointment['time_label'])?></p>
<small><?=e($appointment['timezone'])?></small>
</header>

<div class="appointment-detail-grid">
<article><span>Guest</span><strong><?=e($appointment['display_name'])?></strong><small><?=e($appointment['email'])?></small></article>
<article><span>Meeting format</span><strong><?=e(booking_location_modes()[$appointment['location_mode']]??status_label($appointment['location_mode']))?></strong><small><?=e($appointment['location_details']?:booking_settings()['default_location'])?></small></article>
<article><span>Subject</span><strong><?=e($appointment['subject']?:'General meeting')?></strong><small><?=e($appointment['company']?:'North Mountain Media appointment')?></small></article>
</div>

<?php if($appointment['status']==='requested'):?>
<div class="appointment-state-note">
<strong>Request received</strong>
<span>This time is reserved while the appointment is reviewed.</span>
</div>
<?php elseif($appointment['status']==='confirmed'):?>
<div class="appointment-state-note is-confirmed">
<strong>Appointment confirmed</strong>
<span>The meeting time is reserved.</span>
</div>
<?php elseif($appointment['status']==='cancelled'):?>
<div class="appointment-state-note is-cancelled">
<strong>Appointment cancelled</strong>
<span>This time is no longer reserved.</span>
</div>
<?php elseif($appointment['status']==='completed'):?>
<div class="appointment-state-note is-confirmed">
<strong>Appointment completed</strong>
<span>This meeting has been marked complete.</span>
</div>
<?php endif;?>

<?php if($appointment['status']==='confirmed'&&$appointment['meeting_url']):?>
<a class="booking-submit appointment-meeting-link" href="<?=e($appointment['meeting_url'])?>" target="_blank" rel="noopener">Join meeting ↗</a>
<?php endif;?>

<?php if($appointment['notes']):?>
<section class="appointment-notes">
<span>Your notes</span>
<p><?=nl2br(e($appointment['notes']))?></p>
</section>
<?php endif;?>

<div class="appointment-actions">
<a href="<?=e(app_url('appointment-calendar.php?token='.rawurlencode($token)))?>">Add to calendar</a>
<?php if($intakeTemplate):?><a href="<?=e(app_url('intake.php?appointment_token='.rawurlencode($token)))?>">Project intake</a><?php endif;?>
<?php if(in_array($appointment['status'],['requested','confirmed'],true)):?>
<a href="?<?=e(http_build_query([
    'token' => $token,
    'reschedule' => 1,
    'timezone' => $timezone,
]))?>#reschedule">Reschedule</a>
<?php endif;?>
<a href="<?=e(app_url('events.php'))?>">View Events</a>
</div>

<?php if(in_array($appointment['status'],['requested','confirmed'],true)):?>
<form method="post" action="<?=e(app_url('api/booking-submit.php'))?>" class="appointment-cancel-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="cancel">
<input type="hidden" name="token" value="<?=e($token)?>">
<button type="submit">Cancel appointment</button>
</form>
<?php endif;?>
</article>

<?php if($rescheduleMode&&in_array($appointment['status'],['requested','confirmed'],true)):?>
<section class="appointment-reschedule" id="reschedule">
<header>
<span>Reschedule</span>
<h2>Choose a new appointment time.</h2>
<p>The current appointment remains reserved until a replacement time is successfully saved.</p>
</header>

<form class="booking-timezone-form" method="get">
<input type="hidden" name="token" value="<?=e($token)?>">
<input type="hidden" name="reschedule" value="1">
<select name="timezone" aria-label="Display timezone">
<?php foreach(booking_timezones() as $value=>$label):?>
<option value="<?=e($value)?>" <?=$timezone===$value?'selected':''?>><?=e($label)?></option>
<?php endforeach;?>
</select>
<button type="submit">Update timezone</button>
</form>

<?php if($availableDates):?>
<div class="booking-date-strip">
<?php foreach($availableDates as $availableDate):?>
<a
    class="<?=$rescheduleDate===$availableDate['value']?'active':''?>"
    href="?<?=e(http_build_query([
        'token' => $token,
        'reschedule' => 1,
        'timezone' => $timezone,
        'date' => $availableDate['value'],
    ]))?>#reschedule"
>
<span><?=e(substr($availableDate['label'],0,3))?></span>
<strong><?=e(substr($availableDate['label'],5))?></strong>
</a>
<?php endforeach;?>
</div>

<?php if($rescheduleSlots):?>
<div class="appointment-reschedule-slots">
<?php foreach($rescheduleSlots as $slot):?>
<form method="post" action="<?=e(app_url('api/booking-submit.php'))?>">
<?=csrf_field()?>
<input type="hidden" name="action" value="reschedule">
<input type="hidden" name="token" value="<?=e($token)?>">
<input type="hidden" name="slot_token" value="<?=e($slot['token'])?>">
<button type="submit"><strong><?=e($slot['short_time_label'])?></strong><span><?=e($slot['date_label'])?></span></button>
</form>
<?php endforeach;?>
</div>
<?php else:?>
<div class="booking-inline-empty">No replacement times remain on this date.</div>
<?php endif;?>
<?php else:?>
<div class="booking-inline-empty">No replacement times are currently available.</div>
<?php endif;?>
</section>
<?php endif;?>
<?php endif;?>
</main>
</section>
</div>
<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/bookings.js?v=20260727-site-controls-landing-v60'))?>"></script>
</body>
</html>

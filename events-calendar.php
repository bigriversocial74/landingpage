<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('events');
require_once __DIR__ . '/portal/visitor-intelligence.php';
require_once __DIR__ . '/portal/events-calendar.php';

$settings = events_settings();

if (!$settings['ics_enabled']) {
    http_response_code(404);
    exit('Calendar feed is disabled.');
}

$slug = trim((string)($_GET['event'] ?? ''));
$events = [];
$filename = 'north-mountain-media-events.ics';

if ($slug !== '') {
    $event = events_public_event_by_slug($slug, true);

    if (!$event) {
        http_response_code(404);
        exit('Event unavailable.');
    }

    $events = [$event];
    $filename = $event['slug'] . '.ics';
} else {
    $events = events_public_events([
        'limit' => 250,
    ]);
}

try {
    visitor_intelligence_track(
        'event_ics_download',
        [
            'event_label' => $slug !== ''
                ? (string)$events[0]['title']
                : 'Events calendar',
            'page_path' => 'events-calendar.php',
            'metadata' => [
                'event_id' => $slug !== '' ? (int)$events[0]['id'] : null,
                'event_slug' => $slug !== '' ? (string)$events[0]['slug'] : null,
                'event_count' => count($events),
            ],
        ]
    );
} catch (Throwable $exception) {
    error_log(
        'North Mountain Media calendar tracking failed: '
        . $exception->getMessage()
    );
}

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//North Mountain Media//Events Calendar v57//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:' . events_ics_escape($settings['title']),
];

foreach ($events as $event) {
    $allDay = !empty($event['all_day']);
    $start = new DateTimeImmutable(
        (string)$event['start_at'],
        new DateTimeZone('UTC')
    );
    $end = !empty($event['end_at'])
        ? new DateTimeImmutable((string)$event['end_at'], new DateTimeZone('UTC'))
        : ($allDay ? $start->modify('+1 day') : $start->modify('+1 hour'));
    $uid = 'event-' . (int)$event['id'] . '@northmountainmedia.local';
    $description = trim((string)($event['summary'] ?: $event['description']));
    $location = events_location_label($event);

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . events_ics_escape($uid);
    $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');

    if ($allDay) {
        $localStart = events_local_datetime(
            (string)$event['start_at'],
            (string)$event['timezone']
        ) ?: $start;
        $localEnd = !empty($event['end_at'])
            ? events_local_datetime(
                (string)$event['end_at'],
                (string)$event['timezone']
            )
            : null;
        $localEnd = $localEnd ?: $localStart->modify('+1 day');
        $lines[] = 'DTSTART;VALUE=DATE:' . $localStart->format('Ymd');
        $lines[] = 'DTEND;VALUE=DATE:' . $localEnd->format('Ymd');
    } else {
        $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
        $lines[] = 'DTEND:' . $end->format('Ymd\THis\Z');
    }

    $lines[] = 'SUMMARY:' . events_ics_escape((string)$event['title']);
    $lines[] = 'DESCRIPTION:' . events_ics_escape($description);
    $lines[] = 'LOCATION:' . events_ics_escape($location);
    $lines[] = 'URL:' . events_ics_escape(events_absolute_url(
        'event.php?slug=' . rawurlencode((string)$event['slug'])
    ));

    if ($event['status'] === 'cancelled') {
        $lines[] = 'STATUS:CANCELLED';
    } else {
        $lines[] = 'STATUS:CONFIRMED';
    }

    $lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';
$content = implode("\r\n", $lines) . "\r\n";

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . strlen($content));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=900, must-revalidate');
echo $content;

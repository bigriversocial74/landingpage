<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('bookings');
require_once __DIR__ . '/portal/visitor-intelligence.php';
require_once __DIR__ . '/portal/appointments-booking.php';

$token = strtolower(trim((string)($_GET['token'] ?? '')));
$appointment = booking_appointment_by_token($token);

if (!$appointment) {
    http_response_code(404);
    exit('Appointment unavailable.');
}

try {
    if ((int)($appointment['crm_contact_id'] ?? 0) > 0) {
        visitor_intelligence_attach_contact(
            (int)$appointment['crm_contact_id'],
            'appointment_ics_download',
            [
                'event_label' => (string)$appointment['booking_type_name'],
                'page_path' => 'appointment-calendar.php',
                'crm_opportunity_id' =>
                    (int)($appointment['crm_opportunity_id'] ?? 0) ?: null,
                'metadata' => [
                    'appointment_id' => (int)$appointment['id'],
                    'booking_type_id' =>
                        (int)$appointment['booking_type_id'],
                ],
            ]
        );
    }
} catch (Throwable $exception) {
    error_log(
        'Appointment calendar tracking failed: '
        . $exception->getMessage()
    );
}

$start = new DateTimeImmutable(
    (string)$appointment['start_at'],
    new DateTimeZone('UTC')
);
$end = new DateTimeImmutable(
    (string)$appointment['end_at'],
    new DateTimeZone('UTC')
);
$description = implode('\n', array_filter([
    (string)($appointment['subject'] ?? ''),
    (string)($appointment['notes'] ?? ''),
    'Manage appointment: ' . booking_absolute_url(
        'appointment.php?token=' . rawurlencode($token)
    ),
]));
$location = trim((string)($appointment['location_details'] ?? ''));

if ($location === '') {
    $location = booking_location_modes()[$appointment['location_mode']]
        ?? 'North Mountain Media';
}

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//North Mountain Media//Appointments v58//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:appointment-' . (int)$appointment['id']
        . '@northmountainmedia.local',
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $start->format('Ymd\THis\Z'),
    'DTEND:' . $end->format('Ymd\THis\Z'),
    'SUMMARY:' . booking_ics_escape(
        (string)$appointment['booking_type_name']
        . ' — North Mountain Media'
    ),
    'DESCRIPTION:' . booking_ics_escape($description),
    'LOCATION:' . booking_ics_escape($location),
    'URL:' . booking_ics_escape(
        booking_absolute_url(
            'appointment.php?token=' . rawurlencode($token)
        )
    ),
];

if ($appointment['status'] === 'cancelled') {
    $lines[] = 'STATUS:CANCELLED';
} elseif ($appointment['status'] === 'confirmed') {
    $lines[] = 'STATUS:CONFIRMED';
} else {
    $lines[] = 'STATUS:TENTATIVE';
}

$lines[] = 'END:VEVENT';
$lines[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=UTF-8');
header(
    'Content-Disposition: attachment; filename="appointment-'
    . (int)$appointment['id']
    . '.ics"'
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

echo implode("\r\n", $lines) . "\r\n";

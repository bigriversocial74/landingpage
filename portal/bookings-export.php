<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/appointments-booking.php';

$user = require_role('admin');
$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));

if (!in_array($format, ['csv','ics'], true)) {
    $format = 'csv';
}

$appointments = booking_appointments([
    'from' => gmdate('Y-m-d H:i:s', time() - 180 * 86400),
    'to' => gmdate('Y-m-d H:i:s', time() + 730 * 86400),
    'limit' => 1000,
]);

if ($format === 'ics') {
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//North Mountain Media//Administrator Booking Calendar v58//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:North Mountain Media Appointments',
    ];

    foreach ($appointments as $appointment) {
        $start = new DateTimeImmutable(
            (string)$appointment['start_at'],
            new DateTimeZone('UTC')
        );
        $end = new DateTimeImmutable(
            (string)$appointment['end_at'],
            new DateTimeZone('UTC')
        );
        $description = implode('\n', array_filter([
            'Guest: ' . (string)$appointment['display_name'],
            'Email: ' . (string)$appointment['email'],
            !empty($appointment['phone'])
                ? 'Phone: ' . (string)$appointment['phone']
                : '',
            !empty($appointment['company'])
                ? 'Company: ' . (string)$appointment['company']
                : '',
            !empty($appointment['subject'])
                ? 'Subject: ' . (string)$appointment['subject']
                : '',
            !empty($appointment['notes'])
                ? 'Notes: ' . (string)$appointment['notes']
                : '',
            'Admin: ' . booking_absolute_url(
                'portal/admin.php?view=bookings&appointment='
                . (int)$appointment['id']
            ),
        ]));
        $location = trim(
            (string)($appointment['location_details'] ?? '')
        );

        if ($location === '') {
            $location = booking_location_modes()[
                $appointment['location_mode']
            ] ?? 'North Mountain Media';
        }

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:appointment-' . (int)$appointment['id']
            . '@northmountainmedia.local';
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
        $lines[] = 'DTEND:' . $end->format('Ymd\THis\Z');
        $lines[] = 'SUMMARY:' . booking_ics_escape(
            (string)$appointment['booking_type_name']
            . ' — '
            . (string)$appointment['display_name']
        );
        $lines[] = 'DESCRIPTION:' . booking_ics_escape($description);
        $lines[] = 'LOCATION:' . booking_ics_escape($location);
        $lines[] = 'URL:' . booking_ics_escape(
            booking_absolute_url(
                'portal/admin.php?view=bookings&appointment='
                . (int)$appointment['id']
            )
        );

        if ($appointment['status'] === 'cancelled') {
            $lines[] = 'STATUS:CANCELLED';
        } elseif (
            in_array(
                $appointment['status'],
                ['confirmed','completed'],
                true
            )
        ) {
            $lines[] = 'STATUS:CONFIRMED';
        } else {
            $lines[] = 'STATUS:TENTATIVE';
        }

        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    header('Content-Type: text/calendar; charset=UTF-8');
    header(
        'Content-Disposition: attachment; '
        . 'filename="north-mountain-media-appointments.ics"'
    );
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');
    echo implode("\r\n", $lines) . "\r\n";

    log_activity(
        'appointments_calendar_exported',
        'appointments',
        null,
        ['appointment_count' => count($appointments)]
    );
    exit;
}

header('Content-Type: text/csv; charset=UTF-8');
header(
    'Content-Disposition: attachment; '
    . 'filename="north-mountain-media-appointments.csv"'
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

$output = fopen('php://output', 'wb');

if ($output === false) {
    http_response_code(500);
    exit('Export unavailable.');
}

fputcsv($output, [
    'Appointment ID',
    'Appointment type',
    'Status',
    'Start UTC',
    'End UTC',
    'Timezone',
    'Name',
    'Email',
    'Phone',
    'Company',
    'Subject',
    'Location mode',
    'Location details',
    'CRM contact ID',
    'CRM opportunity ID',
    'Reschedule count',
    'Created at',
]);

foreach ($appointments as $appointment) {
    fputcsv($output, [
        $appointment['id'],
        $appointment['booking_type_name'],
        booking_statuses()[$appointment['status']]
            ?? status_label($appointment['status']),
        $appointment['start_at'],
        $appointment['end_at'],
        $appointment['timezone'],
        $appointment['display_name'],
        $appointment['email'],
        $appointment['phone'],
        $appointment['company'],
        $appointment['subject'],
        booking_location_modes()[$appointment['location_mode']]
            ?? status_label($appointment['location_mode']),
        $appointment['location_details'],
        $appointment['crm_contact_id'],
        $appointment['crm_opportunity_id'],
        $appointment['reschedule_count'],
        $appointment['created_at'],
    ]);
}

fclose($output);

log_activity(
    'appointments_csv_exported',
    'appointments',
    null,
    ['appointment_count' => count($appointments)]
);

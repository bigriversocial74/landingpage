<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/events-calendar.php';

$user = require_role('admin');
$eventId = max(0, (int)($_GET['event_id'] ?? 0));
$event = events_admin_event($eventId);

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$registrations = events_registrations($eventId);
$filename = 'event-registrations-' . slugify((string)$event['slug']) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

$output = fopen('php://output', 'wb');

if ($output === false) {
    http_response_code(500);
    exit('Export unavailable.');
}

fputcsv($output, [
    'Event',
    'Event date',
    'Name',
    'Email',
    'Phone',
    'Company',
    'Party size',
    'Status',
    'Registered at',
    'CRM contact ID',
    'Notes',
]);

foreach ($registrations as $registration) {
    fputcsv($output, [
        $event['title'],
        events_format_date($event),
        $registration['display_name'],
        $registration['email'],
        $registration['phone'],
        $registration['company'],
        $registration['party_size'],
        events_registration_statuses()[$registration['status']]
            ?? status_label($registration['status']),
        $registration['registered_at'],
        $registration['crm_contact_id'],
        $registration['notes'],
    ]);
}

fclose($output);
log_activity(
    'event_registrations_exported',
    'calendar_event',
    $eventId,
    ['registration_count' => count($registrations)]
);

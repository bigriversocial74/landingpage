<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('events');
require_once __DIR__ . '/portal/events-calendar.php';

$eventId = max(0, (int)($_GET['id'] ?? 0));
$event = events_admin_event($eventId);

if (!$event || empty($event['cover_stored_name'])) {
    http_response_code(404);
    exit;
}

$isPublic = in_array(
    (string)$event['status'],
    ['published', 'cancelled', 'completed'],
    true
) && in_array(
    (string)$event['visibility'],
    ['public', 'unlisted'],
    true
);
$user = $isPublic ? null : current_user();

if (!$isPublic && (!$user || ($user['role'] ?? '') !== 'admin')) {
    http_response_code(404);
    exit;
}

$path = events_cover_storage_directory()
    . '/'
    . basename((string)$event['cover_stored_name']);

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = (string)($event['cover_mime_type'] ?: 'application/octet-stream');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400, immutable');
readfile($path);

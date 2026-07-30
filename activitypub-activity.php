<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';

try {
    if (!activitypub_settings()['enabled']) throw new RuntimeException('Federation disabled.');
    $uuid = strtolower(mb_substr(trim((string)($_GET['id'] ?? '')), 0, 36));
    $activity = activitypub_activity_document($uuid);
    if (!$activity) throw new RuntimeException('Activity not found.');
    activitypub_json_response($activity);
} catch (Throwable) {
    http_response_code(404);
    exit;
}

<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';

try {
    if (!activitypub_settings()['enabled']) throw new RuntimeException('Federation disabled.');
    $postId = max(0, (int)($_GET['id'] ?? 0));
    $object = activitypub_object_document($postId);
    if (!$object) throw new RuntimeException('Object not found.');
    activitypub_json_response($object);
} catch (Throwable) {
    http_response_code(404);
    exit;
}

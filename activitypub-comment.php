<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';

try {
    if (!activitypub_settings()['enabled']) throw new RuntimeException('Federation disabled.');
    if (!federated_interactions_schema_available()) throw new RuntimeException('Federated interactions unavailable.');
    $commentId = max(0, (int)($_GET['id'] ?? 0));
    $object = federated_interactions_comment_object($commentId);
    if (!$object) throw new RuntimeException('Comment object not found.');
    activitypub_json_response($object);
} catch (Throwable) {
    http_response_code(404);
    exit;
}

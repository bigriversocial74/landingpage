<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';
require_once __DIR__ . '/portal/federated-messaging.php';

try {
    if (!activitypub_settings()['enabled']) throw new RuntimeException('Federation disabled.');
    if (!federated_messaging_schema_available()) throw new RuntimeException('Federated messaging unavailable.');
    $uuid = strtolower(trim((string)($_GET['id'] ?? '')));
    $object = federated_messaging_message_object($uuid);
    if (!$object) throw new RuntimeException('Federated message object not found.');
    activitypub_json_response($object);
} catch (Throwable) {
    http_response_code(404);
    exit;
}

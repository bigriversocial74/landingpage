<?php
declare(strict_types=1);

function db(): PDO { throw new RuntimeException('Database access is not expected in the pure v66I test.'); }
function activitypub_setting(string $key, string $default = ''): string { return $default; }
function activitypub_actor_url(): string { return 'https://pod.example/activitypub-actor.php'; }
function activitypub_normalize_url(string $url): string { return strtolower(rtrim(trim($url), '/')); }
function activitypub_https_url(string $url): bool {
    $parts = parse_url($url);
    return is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
}
function federated_interactions_schema_available(): bool { return false; }
function federated_interactions_actor_muted(array $actor): bool { return false; }

$root = dirname(__DIR__);
require_once $root . '/portal/federated-messaging.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$direct = federated_messaging_is_direct([
    'to' => [activitypub_actor_url()],
], [
    'to' => [activitypub_actor_url()],
]);
$public = federated_messaging_is_direct([
    'to' => [activitypub_actor_url(), 'https://www.w3.org/ns/activitystreams#Public'],
], []);
if (!$direct || $public) $fail('Direct-message visibility classification failed.');

$clean = federated_messaging_clean_text('<p>Hello <strong>owner</strong>.</p><script>alert(1)</script><img src="https://tracker.example/pixel">', 500);
if ($clean !== 'Hello owner.') $fail('Federated message text sanitization failed.');

$attachments = federated_messaging_attachments([
    'attachment' => [
        ['type' => 'Image', 'url' => 'https://media.example/photo.jpg', 'name' => 'Photo'],
        ['type' => 'Audio', 'url' => 'http://unsafe.example/audio.mp3', 'name' => 'Unsafe'],
        ['type' => 'Unknown', 'url' => 'https://media.example/file.bin', 'name' => 'File'],
    ],
]);
if (count($attachments) !== 2 || $attachments[0]['url'] !== 'https://media.example/photo.jpg') {
    $fail('Link-only attachment normalization failed.');
}
if ($attachments[1]['type'] !== 'Document') $fail('Unknown attachment type was not downgraded.');

$risk = federated_messaging_risk_score(
    ['id' => 1, 'actor_uri' => 'https://remote.example/users/test', 'status' => 'active'],
    'Urgent payment by wire transfer. Send gift card code. https://a.example https://b.example https://c.example',
    $attachments,
    false
);
if ($risk < 60 || $risk > 100) $fail('Federated message risk scoring failed.');

$paths = [
    'core' => 'portal/federated-messaging.php',
    'page' => 'portal/federated-messages.php',
    'object' => 'activitypub-message.php',
    'service' => 'portal/activitypub-service.php',
    'inbox' => 'portal/unified-inbox.php',
    'admin' => 'portal/activitypub-admin.php',
    'cron' => 'cron/process-activitypub.php',
    'migration' => 'database/federated_messaging_v66i.sql',
    'schema' => 'database/north_mountain_portal.sql',
    'css' => 'assets/css/federated-messaging.css',
];
$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') $fail('Missing v66I source: ' . $path);
    $source[$key] = $content;
}

$checks = [
    ['thread table', 'activitypub_message_threads', $source['migration']],
    ['message table', 'activitypub_messages', $source['migration']],
    ['per-user state', 'activitypub_message_user_state', $source['migration']],
    ['immutable event evidence', 'activitypub_message_events', $source['migration']],
    ['HomeServer receipts', 'activitypub_message_assistance', $source['migration']],
    ['disabled default', "('activitypub_messages_enabled','0')", $source['migration']],
    ['link-only default', "('activitypub_messages_remote_media_mode','link_only')", $source['migration']],
    ['direct-only classifier', 'function federated_messaging_is_direct', $source['core']],
    ['unknown sender request', "'request'", $source['core']],
    ['actor hourly limit', 'federated_messaging_actor_hour_count', $source['core']],
    ['domain hourly limit', 'federated_messaging_domain_hour_count', $source['core']],
    ['risk scoring', 'federated_messaging_risk_score', $source['core']],
    ['actor ownership', 'cannot change actor ownership', $source['core']],
    ['signed Create', "'type' => 'Create'", $source['core']],
    ['signed Update', "'type' => 'Update'", $source['core']],
    ['signed Delete', "'type' => 'Delete'", $source['core']],
    ['Tombstone object', "'type' => 'Tombstone'", $source['core']],
    ['delivery synchronization', 'federated_messaging_sync_delivery', $source['service']],
    ['delivery retry reset', 'federated_messaging_reset_delivery', $source['service']],
    ['ActivityPub inbound bridge', 'federated_messaging_process_inbound', $source['service']],
    ['Unified Inbox source', "'federated_message'", $source['inbox']],
    ['Federation navigation', 'Open Federated Messages', $source['admin']],
    ['retention worker', 'federated_messaging_cleanup', $source['cron']],
    ['same-origin actions', 'same_origin_request()', $source['page']],
    ['durable mark unread', 'federated_messaging_mark_unread', $source['core'] . $source['page']],
    ['local report evidence', "'thread_reported'", $source['core']],
    ['owner local deletion', "'delete_local'", $source['core'] . $source['page']],
    ['summary prefill boundary', "['draft', 'translate']", $source['page']],
    ['explicit wrapper authority', "'wrapper' => 'rss-pod'", $source['core']],
    ['resource authority', "'resource_type' => 'federated_message_thread'", $source['core']],
    ['proposal-only handoff', "'proposal_only' => true", $source['core']],
    ['send denied to HomeServer', "'send_allowed' => false", $source['core']],
    ['HomeServer capability request', 'homeserver_request($capability, $payload)', $source['core']],
    ['owner send action', 'Send signed message', $source['page']],
];
foreach ($checks as [$label, $needle, $haystack]) {
    if (!str_contains($haystack, $needle)) $fail('Missing ' . $label . ': ' . $needle);
}

$assistStart = strpos($source['core'], 'function federated_messaging_assist');
$assistEnd = strpos($source['core'], 'function federated_messaging_inbox_items');
if ($assistStart === false || $assistEnd === false || $assistEnd <= $assistStart) {
    $fail('HomeServer assistance function boundary was not found.');
}
$assistSource = substr($source['core'], $assistStart, $assistEnd - $assistStart);
if (str_contains($assistSource, 'federated_messaging_send(')) {
    $fail('HomeServer assistance may not automatically send a federated message.');
}
foreach (['private_key', 'password', 'credential', 'hidden_prompt', 'source_document'] as $forbiddenReceiptField) {
    if (str_contains($assistSource, "receipt[$forbiddenReceiptField]")) {
        $fail('Unsafe HomeServer receipt field detected: ' . $forbiddenReceiptField);
    }
}

foreach (['<img', '<audio', '<video', '<iframe', 'background-image:'] as $remoteMediaMarkup) {
    if (str_contains(strtolower($source['page']), strtolower($remoteMediaMarkup))) {
        $fail('Remote media is auto-rendered in Federated Messages: ' . $remoteMediaMarkup);
    }
}
if (!str_contains($source['page'], 'noopener noreferrer nofollow')) {
    $fail('External attachment links are missing privacy attributes.');
}

foreach (['activitypub_message_threads','activitypub_messages','activitypub_message_user_state','activitypub_message_events','activitypub_message_assistance'] as $table) {
    if (substr_count($source['migration'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        $fail('Additive migration must define ' . $table . ' exactly once.');
    }
    if (substr_count($source['schema'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        $fail('Fresh schema must define ' . $table . ' exactly once.');
    }
}

foreach ([
    'tools/apply-federated-messaging-v66i.py',
    '.github/workflows/apply-federated-messaging-v66i.yml',
] as $temporary) {
    if (file_exists($root . '/' . $temporary)) $fail('Temporary v66I integration file remains: ' . $temporary);
}

echo "Federated Messaging v66I source, privacy, and security regression passed.\n";

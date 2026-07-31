<?php
declare(strict_types=1);

function nmm_config(?string $section = null): array
{
    $config = [
        'app' => ['base_url' => 'https://pod.example.test', 'timezone' => 'America/Phoenix'],
        'security' => ['notification_delivery_secret' => str_repeat('n', 64)],
        'homeserver' => [],
    ];
    if ($section === null) return $config;
    return is_array($config[$section] ?? null) ? $config[$section] : [];
}

function setting(string $key, ?string $fallback = null): ?string
{
    return $fallback;
}

function app_url(string $path = ''): string
{
    return 'https://pod.example.test' . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function status_label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

require dirname(__DIR__) . '/portal/notification-delivery.php';

function v66j_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$core = file_get_contents($root . '/portal/notification-delivery.php');
$admin = file_get_contents($root . '/portal/notification-delivery-admin.php');
$api = file_get_contents($root . '/portal/notification-push-api.php');
$notifications = file_get_contents($root . '/portal/notifications.php');
$serviceWorker = file_get_contents($root . '/notification-service-worker.js');
$browser = file_get_contents($root . '/assets/js/notification-delivery.js');
$config = file_get_contents($root . '/config-example.php');
$fresh = file_get_contents($root . '/database/north_mountain_portal.sql');
$migration = file_get_contents($root . '/database/notification_delivery_v66j.sql');

foreach ([$core, $admin, $api, $notifications, $serviceWorker, $browser, $config, $fresh, $migration] as $source) {
    v66j_assert(is_string($source) && $source !== '', 'A required v66J source file is empty.');
}

$tables = [
    'notification_delivery_preferences',
    'notification_quiet_hours',
    'notification_delivery_keys',
    'notification_push_subscriptions',
    'notification_delivery_queue',
    'notification_delivery_attempts',
    'notification_digest_batches',
];
foreach ($tables as $table) {
    v66j_assert(substr_count($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'The additive migration must define ' . $table . ' exactly once.');
    v66j_assert(substr_count($fresh, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'The fresh schema must define ' . $table . ' exactly once.');
}

v66j_assert(str_contains($notifications, "require_once __DIR__ . '/notification-delivery.php';"), 'Canonical notifications must load the delivery adapter.');
v66j_assert(str_contains($notifications, 'notification_delivery_enqueue_notification($notificationId);'), 'Canonical notifications must enqueue external delivery after persistence.');
v66j_assert(str_contains($notifications, 'external notification enqueue failed'), 'External enqueue must fail without invalidating in-app notifications.');
v66j_assert(str_contains($core, "'wrapper' => 'rss-pod'"), 'HomeServer alerts require explicit RSS-POD wrapper authority.');
v66j_assert(str_contains($core, "'resource_authority' => 'notification_metadata'"), 'HomeServer alerts require notification metadata authority.');
v66j_assert(str_contains($core, "'proposal_only' => true"), 'HomeServer alerts must remain proposal-only.');
v66j_assert(str_contains($core, "'send_allowed' => false"), 'HomeServer alert requests must deny send authority.');
v66j_assert(str_contains($core, '\'content_authorized\' => $contentAllowed'), 'HomeServer content sharing must be explicit.');
v66j_assert(str_contains($core, "openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC"), 'Push payload encryption must use an ephemeral P-256 key.');
v66j_assert(str_contains($core, "'retry_at' => gmdate"), 'Retry scheduling must bind a portable UTC timestamp.');
v66j_assert(str_contains($core, 'if (empty($preference[\'configured\'])) return 0;'), 'External delivery must require a saved event preference.');
v66j_assert(str_contains($core, 'notification_delivery_runtime_authorization'), 'Queued delivery must re-check current authorization.');
v66j_assert(str_contains($core, 'AND status="leased" AND lease_token=:lease_token'), 'Digest batching must be isolated to one worker lease.');
v66j_assert(str_contains($core, "'reference' => 'already-processed'"), 'Already-consumed queue rows must be skipped safely.');
v66j_assert(!str_contains($core, 'INTERVAL :delay SECOND'), 'Native PDO must not bind an INTERVAL operand.');
v66j_assert(str_contains($api, 'same_origin_request()'), 'The push API must enforce same-origin requests.');
v66j_assert(str_contains($api, 'verify_csrf();'), 'The push API must enforce CSRF protection.');
v66j_assert(str_contains($api, "require_role('admin')"), 'The push API must require an authenticated administrator.');
v66j_assert(str_contains($serviceWorker, 'url.origin === self.location.origin'), 'Notification clicks must remain on the POD origin.');
v66j_assert(str_contains($serviceWorker, "self.addEventListener('push'"), 'The service worker must handle push events.');
v66j_assert(str_contains($browser, 'Notification.requestPermission()'), 'Browser notification permission must follow an explicit user action.');
v66j_assert(str_contains($browser, 'applicationServerKey'), 'Browser subscriptions must use the configured VAPID public key.');
v66j_assert(!preg_match("#https?://[^\\s\"']+\\.js#i", $serviceWorker . "\n" . $browser), 'Notification JavaScript must not load third-party scripts.');
v66j_assert(str_contains($config, "'notification_delivery_secret'"), 'The deployment template must expose the private notification delivery secret.');
v66j_assert(!is_file($root . '/tools/apply-notification-delivery-v66j.py'), 'The temporary v66J integration script must be removed.');
v66j_assert(!is_file($root . '/.github/workflows/apply-notification-delivery-v66j.yml'), 'The temporary v66J integration workflow must be removed.');

$encoded = notification_delivery_b64url_encode("\x00test\xff");
v66j_assert(notification_delivery_b64url_decode($encoded) === "\x00test\xff", 'Base64url encoding must round-trip binary values.');
$encrypted = notification_delivery_encrypt(['endpoint' => 'https://push.example.test/subscription', 'private' => 'bounded']);
v66j_assert(notification_delivery_decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag']) === ['endpoint' => 'https://push.example.test/subscription', 'private' => 'bounded'], 'Encrypted delivery data must round-trip.');
v66j_assert(notification_delivery_priority_rank('urgent') > notification_delivery_priority_rank('high'), 'Urgent priority must sort above high.');
v66j_assert(notification_delivery_priority_rank('high') > notification_delivery_priority_rank('normal'), 'High priority must sort above normal.');

$catalog = notification_delivery_event_catalog();
foreach (['federated_message_request', 'voicemail', 'moderation', 'delivery_failure', 'homeserver_issue'] as $eventKey) {
    v66j_assert(isset($catalog[$eventKey]), 'The event catalog is missing ' . $eventKey . '.');
}

v66j_assert(notification_delivery_event_key([
    'entity_type' => 'activitypub_message_thread',
    'title' => 'New federated message request',
    'category' => 'message',
]) === 'federated_message_request', 'Federated requests must map to their own delivery event.');
v66j_assert(notification_delivery_event_key([
    'entity_type' => 'call_center_voicemail',
    'title' => 'New voicemail',
    'category' => 'call',
]) === 'voicemail', 'Voicemail must map to its dedicated event.');
v66j_assert(notification_delivery_event_key([
    'entity_type' => 'activitypub_delivery',
    'title' => 'Delivery failed',
    'category' => 'system',
]) === 'delivery_failure', 'Failed federation deliveries must map to escalation events.');

fwrite(STDOUT, "Notification Delivery v66J source/privacy regression passed.\n");

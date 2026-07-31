<?php
declare(strict_types=1);

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: '127.0.0.1',
    (int)(getenv('DB_PORT') ?: 3306),
    getenv('DB_NAME') ?: 'nmm'
);
$GLOBALS['v66i_pdo'] = new PDO(
    $dsn,
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: 'root',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

function db(): PDO { return $GLOBALS['v66i_pdo']; }
function nmm_config(?string $section = null): array {
    $config = [
        'app' => ['base_url' => 'https://pod.example'],
        'homeserver' => ['paired' => true, 'endpoint' => 'https://127.0.0.1:47831'],
    ];
    return $section === null ? $config : ($config[$section] ?? []);
}
function activitypub_setting(string $key, string $default = ''): string {
    $statement = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=:setting_key LIMIT 1');
    $statement->execute(['setting_key' => $key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string)$value;
}
function activitypub_actor_url(): string { return 'https://pod.example/activitypub-actor.php'; }
function activitypub_normalize_url(string $url): string { return strtolower(rtrim(trim($url), '/')); }
function activitypub_https_url(string $url): bool {
    $parts = parse_url($url);
    return is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
}
function activitypub_valid_uuid(string $value): bool {
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
}
function activitypub_uuid_from_seed(string $seed): string {
    $hex = substr(hash('sha256', $seed), 0, 32);
    $hex[12] = '5';
    $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
    return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20,12));
}
function pod_uuid_v4(): string {
    static $counter = 0;
    $counter++;
    return sprintf('00000000-0000-4000-8000-%012d', $counter);
}
function app_url(string $path = ''): string { return 'https://pod.example' . ($path !== '' ? '/' . ltrim($path, '/') : ''); }
function activitypub_activity_url(string $uuid): string { return app_url('activitypub-activity.php?id=' . rawurlencode($uuid)); }
function federated_interactions_schema_available(): bool { return true; }
function federated_interactions_actor_domain(string $actorUri): string { return strtolower((string)parse_url($actorUri, PHP_URL_HOST)); }
function federated_interactions_domain_blocked(string $host): bool {
    $statement = db()->prepare('SELECT COUNT(*) FROM activitypub_domain_blocks WHERE domain_name=:domain_name');
    $statement->execute(['domain_name' => strtolower($host)]);
    return (int)$statement->fetchColumn() > 0;
}
function federated_interactions_actor_control(int $remoteActorId): array {
    $statement = db()->prepare('SELECT * FROM activitypub_actor_controls WHERE remote_actor_id=:actor_id LIMIT 1');
    $statement->execute(['actor_id' => $remoteActorId]);
    return $statement->fetch() ?: ['moderation_status' => 'active'];
}
function federated_interactions_actor_allowed(array $remoteActor): bool {
    if ((string)($remoteActor['status'] ?? 'active') !== 'active') return false;
    if (federated_interactions_domain_blocked(federated_interactions_actor_domain((string)$remoteActor['actor_uri']))) return false;
    return (string)(federated_interactions_actor_control((int)$remoteActor['id'])['moderation_status'] ?? 'active') !== 'blocked';
}
function federated_interactions_actor_muted(array $remoteActor): bool {
    return (string)(federated_interactions_actor_control((int)$remoteActor['id'])['moderation_status'] ?? 'active') === 'muted';
}
function federated_interactions_payload_actor_matches(array $object, string $actorUri): bool {
    $value = $object['attributedTo'] ?? $object['actor'] ?? '';
    if (is_array($value)) $value = (string)($value['id'] ?? '');
    return activitypub_normalize_url((string)$value) === activitypub_normalize_url($actorUri);
}
function notification_create_for_role(string $role, string $category, string $title, string $body, string $link, string $entityType, int $entityId, string $priority): void {}
function homeserver_connector_status(): array {
    return [
        'paired' => true,
        'online' => true,
        'endpoint' => 'https://127.0.0.1:47831',
        'last_seen_at' => gmdate('Y-m-d H:i:s'),
        'capabilities' => ['message_summary','suggest_reply','translate_message'],
    ];
}
function homeserver_connector_capability_available(string $capability): bool {
    return in_array($capability, ['message_summary','suggest_reply','translate_message'], true);
}
function homeserver_connector_request(string $capability, array $payload): array {
    if (($payload['authority']['wrapper'] ?? '') !== 'rss-pod') throw new RuntimeException('Wrapper authority missing.');
    if (($payload['request']['send_allowed'] ?? true) !== false) throw new RuntimeException('HomeServer send authority must be denied.');
    return [
        'ok' => true,
        'available' => true,
        'capability' => $capability,
        'draft' => $capability === 'suggest_reply' ? 'Owner-reviewed suggested reply.' : 'Safe private result.',
        'receipt_id' => 'receipt-v66i-1',
        'job_id' => 'job-v66i-1',
    ];
}
function activitypub_store_outbox_activity(array $activity, string $activityType, string $objectType, string $objectUri, ?int $blogPostId, ?int $actorUserId): int {
    $uri = (string)$activity['id'];
    parse_str((string)parse_url($uri, PHP_URL_QUERY), $query);
    $uuid = (string)($query['id'] ?? pod_uuid_v4());
    $payload = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    db()->prepare(
        'INSERT INTO activitypub_outbox_activities
            (activity_uuid,activity_uri,activity_type,object_type,object_uri,blog_post_id,payload_json,payload_sha256,created_by_user_id,published_at)
         VALUES (:uuid,:activity_uri,:activity_type,:object_type,:object_uri,NULL,:payload_json,:payload_hash,:user_id,UTC_TIMESTAMP())'
    )->execute([
        'uuid' => $uuid,
        'activity_uri' => $uri,
        'activity_type' => $activityType,
        'object_type' => $objectType,
        'object_uri' => $objectUri,
        'payload_json' => $payload,
        'payload_hash' => hash('sha256', $payload . '|' . $uri),
        'user_id' => ($actorUserId ?? 0) > 0 ? $actorUserId : null,
    ]);
    return (int)db()->lastInsertId();
}
function activitypub_queue_delivery(int $outboxActivityId, int $remoteActorId): int {
    $statement = db()->prepare('SELECT COALESCE(NULLIF(shared_inbox_url,""),inbox_url) FROM activitypub_remote_actors WHERE id=:actor_id');
    $statement->execute(['actor_id' => $remoteActorId]);
    $inbox = (string)$statement->fetchColumn();
    db()->prepare(
        'INSERT INTO activitypub_deliveries(outbox_activity_id,remote_actor_id,inbox_url,status,next_attempt_at)
         VALUES (:outbox_id,:actor_id,:inbox_url,"pending",UTC_TIMESTAMP())'
    )->execute(['outbox_id' => $outboxActivityId, 'actor_id' => $remoteActorId, 'inbox_url' => $inbox]);
    return 1;
}

require_once dirname(__DIR__) . '/portal/federated-messaging.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$assert = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) $fail($message);
};

$pdo = db();
$pdo->exec("INSERT INTO users(role,email,password_hash,display_name,status) VALUES ('admin','v66i@example.test','x','V66I Admin','active')");
$userId = (int)$pdo->lastInsertId();

$insertActor = $pdo->prepare(
    'INSERT INTO activitypub_remote_actors
        (actor_uri,preferred_username,display_name,profile_url,inbox_url,public_key_id,public_key_pem,status,fetched_at)
     VALUES (:actor_uri,:username,:display_name,:profile_url,:inbox_url,:key_id,"test-public-key","active",UTC_TIMESTAMP())'
);
$insertActor->execute([
    'actor_uri' => 'https://remote.example/users/alice',
    'username' => 'alice',
    'display_name' => 'Alice Remote',
    'profile_url' => 'https://remote.example/@alice',
    'inbox_url' => 'https://remote.example/users/alice/inbox',
    'key_id' => 'https://remote.example/users/alice#main-key',
]);
$aliceId = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO activitypub_followers(remote_actor_id,follow_activity_id,status,followed_at) VALUES (:actor_id,:activity_id,"approved",UTC_TIMESTAMP())')
    ->execute(['actor_id' => $aliceId, 'activity_id' => 'https://remote.example/activities/follow-alice']);
$alice = $pdo->query('SELECT * FROM activitypub_remote_actors WHERE id=' . $aliceId)->fetch();

$insertInbox = static function (string $id, string $actor, string $type, string $object): int {
    db()->prepare(
        'INSERT INTO activitypub_inbox_activities
            (activity_id,actor_uri,activity_type,object_uri,request_digest,signature_key_id,status,payload_json)
         VALUES (:activity_id,:actor_uri,:activity_type,:object_uri,:digest,:key_id,"accepted","{}")'
    )->execute([
        'activity_id' => $id,
        'actor_uri' => $actor,
        'activity_type' => $type,
        'object_uri' => $object,
        'digest' => hash('sha256', $id),
        'key_id' => $actor . '#main-key',
    ]);
    return (int)db()->lastInsertId();
};

$aliceObject = 'https://remote.example/messages/alice-1';
$aliceActivity = 'https://remote.example/activities/alice-create-1';
$aliceInboxId = $insertInbox($aliceActivity, (string)$alice['actor_uri'], 'Create', $aliceObject);
$alicePayload = [
    'id' => $aliceActivity,
    'type' => 'Create',
    'actor' => (string)$alice['actor_uri'],
    'to' => [activitypub_actor_url()],
    'object' => [
        'id' => $aliceObject,
        'type' => 'Note',
        'attributedTo' => (string)$alice['actor_uri'],
        'to' => [activitypub_actor_url()],
        'content' => '<p>Hello from Alice.</p>',
        'published' => gmdate(DATE_ATOM),
        'attachment' => [['type' => 'Image', 'url' => 'https://remote.example/media/photo.jpg', 'name' => 'Photo']],
    ],
];
$assert(federated_messaging_process_inbound($aliceInboxId, $alicePayload, $alice), 'Trusted inbound message was not handled.');
$aliceThread = $pdo->query('SELECT * FROM activitypub_message_threads WHERE remote_actor_id=' . $aliceId)->fetch();
$assert((string)$aliceThread['status'] === 'open', 'Approved follower did not receive an open thread.');
$aliceMessage = $pdo->query('SELECT * FROM activitypub_messages WHERE thread_id=' . (int)$aliceThread['id'])->fetch();
$assert((string)$aliceMessage['status'] === 'visible' && (string)$aliceMessage['direction'] === 'inbound', 'Trusted inbound message was not visible.');
$assert(str_contains((string)$aliceMessage['attachments_json'], 'https://remote.example/media/photo.jpg'), 'Link-only attachment evidence was not stored.');

$insertActor->execute([
    'actor_uri' => 'https://unknown.example/users/bob',
    'username' => 'bob',
    'display_name' => 'Bob Unknown',
    'profile_url' => 'https://unknown.example/@bob',
    'inbox_url' => 'https://unknown.example/users/bob/inbox',
    'key_id' => 'https://unknown.example/users/bob#main-key',
]);
$bobId = (int)$pdo->lastInsertId();
$bob = $pdo->query('SELECT * FROM activitypub_remote_actors WHERE id=' . $bobId)->fetch();
$bobObject = 'https://unknown.example/messages/bob-1';
$bobActivity = 'https://unknown.example/activities/bob-create-1';
$bobInboxId = $insertInbox($bobActivity, (string)$bob['actor_uri'], 'Create', $bobObject);
$bobPayload = [
    'id' => $bobActivity,
    'type' => 'Create',
    'actor' => (string)$bob['actor_uri'],
    'to' => [activitypub_actor_url()],
    'object' => [
        'id' => $bobObject,
        'type' => 'Note',
        'attributedTo' => (string)$bob['actor_uri'],
        'to' => [activitypub_actor_url()],
        'content' => 'Unknown sender request.',
        'published' => gmdate(DATE_ATOM),
    ],
];
$assert(federated_messaging_process_inbound($bobInboxId, $bobPayload, $bob), 'Unknown inbound message was not handled.');
$bobThread = $pdo->query('SELECT * FROM activitypub_message_threads WHERE remote_actor_id=' . $bobId)->fetch();
$assert((string)$bobThread['status'] === 'request', 'Unknown sender did not enter message requests.');
$bobMessage = $pdo->query('SELECT * FROM activitypub_messages WHERE thread_id=' . (int)$bobThread['id'])->fetch();
$assert((string)$bobMessage['status'] === 'request', 'Unknown sender message did not retain request status.');

federated_messaging_moderate_thread((int)$bobThread['id'], 'accept', $userId, 'Approved by owner');
$bobThread = federated_messaging_thread((int)$bobThread['id']);
$assert((string)$bobThread['status'] === 'open' && (string)$bobThread['trust_level'] === 'approved', 'Message request acceptance failed.');

$bobUpdateActivity = 'https://unknown.example/activities/bob-update-1';
$bobUpdateInbox = $insertInbox($bobUpdateActivity, (string)$bob['actor_uri'], 'Update', $bobObject);
$bobUpdate = $bobPayload;
$bobUpdate['id'] = $bobUpdateActivity;
$bobUpdate['type'] = 'Update';
$bobUpdate['object']['content'] = 'Edited unknown sender message.';
$bobUpdate['object']['updated'] = gmdate(DATE_ATOM);
$assert(federated_messaging_process_inbound($bobUpdateInbox, $bobUpdate, $bob), 'Remote message Update was not handled.');
$bobMessage = $pdo->query('SELECT * FROM activitypub_messages WHERE id=' . (int)$bobMessage['id'])->fetch();
$assert((string)$bobMessage['status'] === 'edited' && str_contains((string)$bobMessage['body_text'], 'Edited'), 'Remote message Update did not persist.');

$outbound = federated_messaging_send((int)$aliceThread['id'], 'Owner reply to Alice.', $userId);
$assert((string)$outbound['direction'] === 'outbound' && (int)$outbound['outbox_activity_id'] > 0, 'Signed outbound message was not stored.');
$delivery = $pdo->query('SELECT * FROM activitypub_deliveries WHERE outbox_activity_id=' . (int)$outbound['outbox_activity_id'])->fetch();
$assert((bool)$delivery, 'Outbound delivery receipt was not queued.');

$edited = federated_messaging_edit_outbound((int)$outbound['id'], 'Edited owner reply.', $userId);
$assert((string)$edited['status'] === 'edited' && str_contains((string)$edited['body_text'], 'Edited'), 'Outbound message edit failed.');
$editedDelivery = $pdo->query('SELECT * FROM activitypub_deliveries WHERE outbox_activity_id=' . (int)$edited['outbox_activity_id'])->fetch();
federated_messaging_sync_delivery($editedDelivery, ['ok' => false, 'error' => 'Synthetic delivery failure']);
$failed = federated_messaging_message((int)$edited['id']);
$assert((string)$failed['status'] === 'failed' && str_contains((string)$failed['last_error'], 'Synthetic'), 'Delivery failure did not synchronize to the message.');
federated_messaging_reset_delivery((int)$editedDelivery['id']);
$reset = federated_messaging_message((int)$edited['id']);
$assert((string)$reset['status'] === 'visible' && empty($reset['last_error']), 'Delivery retry reset failed.');

$assist = federated_messaging_assist((int)$bobThread['id'], (int)$bobMessage['id'], 'draft', $userId);
$assert(!empty($assist['ok']) && (string)$assist['text'] === 'Owner-reviewed suggested reply.', 'HomeServer suggested-reply handoff failed.');
$assistance = $pdo->query('SELECT * FROM activitypub_message_assistance ORDER BY id DESC LIMIT 1')->fetch();
$assert((string)$assistance['status'] === 'completed' && strlen((string)$assistance['input_sha256']) === 64, 'HomeServer request hash or safe result was not stored.');
$assert(!str_contains((string)$assistance['receipt_json'], 'private_key'), 'Unsafe HomeServer receipt material was stored.');

federated_messaging_set_user_state((int)$aliceThread['id'], $userId, 'pin');
federated_messaging_set_user_state((int)$aliceThread['id'], $userId, 'archive');
$state = $pdo->query('SELECT * FROM activitypub_message_user_state WHERE thread_id=' . (int)$aliceThread['id'] . ' AND user_id=' . $userId)->fetch();
$assert(!empty($state['pinned_at']) && !empty($state['archived_at']), 'Per-user conversation state failed.');

federated_messaging_delete_outbound((int)$edited['id'], $userId);
$tombstone = federated_messaging_message_object((string)$edited['message_uuid']);
$assert(is_array($tombstone) && (string)$tombstone['type'] === 'Tombstone', 'Deleted outbound message did not dereference as a Tombstone.');

$bobDeleteActivity = 'https://unknown.example/activities/bob-delete-1';
$bobDeleteInbox = $insertInbox($bobDeleteActivity, (string)$bob['actor_uri'], 'Delete', $bobObject);
$assert(federated_messaging_process_inbound($bobDeleteInbox, [
    'id' => $bobDeleteActivity,
    'type' => 'Delete',
    'actor' => (string)$bob['actor_uri'],
    'object' => $bobObject,
], $bob), 'Remote message Delete was not handled.');
$deletedBob = $pdo->query('SELECT * FROM activitypub_messages WHERE id=' . (int)$bobMessage['id'])->fetch();
$assert((string)$deletedBob['status'] === 'deleted' && $deletedBob['body_text'] === null, 'Remote message Delete did not remove the stored body.');

$items = federated_messaging_inbox_items($userId);
$assert(is_array($items), 'Unified Inbox adapter did not return an array.');

$pdo->prepare('UPDATE activitypub_message_threads SET status="closed" WHERE id=:id')->execute(['id' => (int)$bobThread['id']]);
$pdo->prepare('UPDATE activitypub_messages SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 400 DAY) WHERE thread_id=:thread_id')->execute(['thread_id' => (int)$bobThread['id']]);
$removed = federated_messaging_cleanup();
$assert($removed >= 1, 'Bounded closed-message retention did not remove expired deleted evidence.');

echo "Federated Messaging v66I live database integration passed.\n";

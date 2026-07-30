<?php
declare(strict_types=1);

define('NMM_ROOT', dirname(__DIR__));

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('DB_PORT') ?: 3306);
    $name = getenv('DB_NAME') ?: 'nmm';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'root';
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}
function setting(string $key, mixed $default = null): mixed
{
    $statement = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=:key LIMIT 1');
    $statement->execute(['key' => $key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : $value;
}
function nmm_config(?string $section = null): array
{
    return match ($section) {
        'app' => ['base_url' => 'https://pod.example'],
        'security' => ['activitypub_secret' => 'test-only-activitypub-secret-66f-0123456789'],
        default => [],
    };
}
function app_url(string $path = ''): string { return 'https://pod.example/' . ltrim($path, '/'); }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function format_datetime(?string $value): string { return (string)$value; }
function status_label(string $value): string { return ucwords(str_replace('_', ' ', $value)); }
function log_activity(...$arguments): void {}
function current_user(): ?array { return null; }
function primary_admin_profile(): ?array { return null; }

require_once NMM_ROOT . '/portal/activitypub-service.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$pdo = db();
$pdo->exec('SET time_zone = "+00:00"');
$pdo->exec('DELETE FROM activitypub_deliveries');
$pdo->exec('DELETE FROM activitypub_outbox_activities');
$pdo->exec('DELETE FROM activitypub_inbox_activities');
$pdo->exec('DELETE FROM activitypub_followers');
$pdo->exec('DELETE FROM activitypub_remote_actors');
$pdo->exec('DELETE FROM activitypub_actor_keys');
$pdo->exec('DELETE FROM pod_identity_origins WHERE pod_identity_id IN (SELECT id FROM pod_identities WHERE local_key="primary")');
$pdo->exec('DELETE FROM pod_identities WHERE local_key="primary"');

$email = 'activitypub-v66f@example.test';
$pdo->prepare('DELETE FROM users WHERE email=:email')->execute(['email' => $email]);
$pdo->prepare(
    'INSERT INTO users(role,email,password_hash,display_name,status,must_change_password)
     VALUES("admin",:email,:password_hash,"Federation Owner","active",0)'
)->execute([
    'email' => $email,
    'password_hash' => password_hash('Test-only-Password-66F!', PASSWORD_DEFAULT),
]);
$userId = (int)$pdo->lastInsertId();
$postId = 0;
$identityId = 0;
$remoteActorId = 0;

try {
    $pdo->prepare(
        'INSERT INTO pod_identities
            (pod_uuid,local_key,is_local,identity_type,owner_user_id,display_name,
             public_username,summary,canonical_origin,profile_url,agent_url,
             main_feed_url,verification_status,status,discovered_at,last_verified_at)
         VALUES
            (:pod_uuid,"primary",1,"personal_pod",:owner_user_id,"Federation Owner",
             "owner","Owner-controlled POD actor.","https://pod.example",
             "https://pod.example/index.php","https://pod.example/index.php#chat",
             "https://pod.example/blog-feed.php","local","active",UTC_TIMESTAMP(),UTC_TIMESTAMP())'
    )->execute([
        'pod_uuid' => 'pod:' . pod_uuid_v4(),
        'owner_user_id' => $userId,
    ]);
    $identityId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO pod_identity_origins
            (pod_identity_id,origin,status,verification_method,verified_at,is_primary)
         VALUES
            (:identity_id,"https://pod.example","verified","test",UTC_TIMESTAMP(),1)'
    )->execute(['identity_id' => $identityId]);

    $settings = [
        'activitypub_enabled' => '1',
        'activitypub_federate_blog_posts' => '1',
        'activitypub_manual_follow_approval' => '1',
        'activitypub_username' => 'owner',
        'activitypub_display_name' => 'Federation Owner',
        'activitypub_summary' => 'Owner-controlled POD actor.',
        'activitypub_show_followers' => '1',
    ];
    $saveSetting = $pdo->prepare(
        'INSERT INTO settings(setting_key,setting_value) VALUES(:key,:value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ($settings as $key => $value) $saveSetting->execute(['key' => $key, 'value' => $value]);

    $firstKey = activitypub_rotate_key($userId);
    if (!str_contains((string)$firstKey['public_key_pem'], 'BEGIN PUBLIC KEY')) $fail('ActivityPub RSA public key generation failed.');
    if (str_contains((string)$firstKey['private_key_ciphertext'], 'BEGIN PRIVATE KEY')) $fail('ActivityPub private key was stored in plaintext.');
    if (!str_contains(activitypub_decrypt_private_key($firstKey), 'PRIVATE KEY')) $fail('ActivityPub encrypted private key could not be recovered.');
    $secondKey = activitypub_rotate_key($userId);
    if ((string)$firstKey['key_id'] === (string)$secondKey['key_id']) $fail('ActivityPub key rotation did not version the key ID.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_actor_keys WHERE status="active"')->fetchColumn() !== 1) $fail('ActivityPub key rotation left multiple active keys.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_actor_keys WHERE status="retired"')->fetchColumn() !== 1) $fail('ActivityPub key rotation did not retain retirement evidence.');

    $actor = activitypub_actor_document();
    if (($actor['type'] ?? '') !== 'Person' || ($actor['preferredUsername'] ?? '') !== 'owner') $fail('ActivityPub actor rendering failed.');
    if (($actor['publicKey']['id'] ?? '') !== $secondKey['key_id']) $fail('ActivityPub actor does not advertise the active key.');
    $webfinger = activitypub_webfinger_document('acct:owner@pod.example');
    if (($webfinger['links'][0]['href'] ?? '') !== activitypub_actor_url()) $fail('WebFinger actor discovery failed.');
    $nodeinfo = activitypub_nodeinfo_document();
    if (($nodeinfo['protocols'][0] ?? '') !== 'activitypub') $fail('NodeInfo ActivityPub protocol discovery failed.');

    $slug = 'activitypub-v66f-post';
    $pdo->prepare('DELETE FROM blog_posts WHERE slug=:slug')->execute(['slug' => $slug]);
    $pdo->prepare(
        'INSERT INTO blog_posts
            (author_user_id,title,slug,status,featured,category,excerpt,body,tags,published_at)
         VALUES
            (:author_user_id,"Federated article",:slug,"published",1,"Open Web",
             "A federated article.","## Federated article\nPublic body.","ActivityPub, POD",UTC_TIMESTAMP())'
    )->execute(['author_user_id' => $userId, 'slug' => $slug]);
    $postId = (int)$pdo->lastInsertId();

    $createId = activitypub_blog_event($postId, 'Create', $userId);
    if ($createId <= 0) $fail('ActivityPub Create activity was not stored.');
    if (activitypub_blog_event($postId, 'Create', $userId) !== $createId) $fail('ActivityPub Create activity was not idempotent.');
    $createPayload = json_decode((string)$pdo->query('SELECT payload_json FROM activitypub_outbox_activities WHERE id=' . $createId)->fetchColumn(), true);
    if (($createPayload['type'] ?? '') !== 'Create' || ($createPayload['object']['type'] ?? '') !== 'Article') $fail('ActivityPub Create payload failed.');

    $remoteResource = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    if ($remoteResource === false) $fail('Remote test key generation failed.');
    $remoteDetails = openssl_pkey_get_details($remoteResource);
    $remotePublicKey = is_array($remoteDetails) ? (string)($remoteDetails['key'] ?? '') : '';
    if ($remotePublicKey === '') $fail('Remote test public key export failed.');
    $remoteActorUri = 'https://remote.example/users/alex';
    $pdo->prepare(
        'INSERT INTO activitypub_remote_actors
            (actor_uri,preferred_username,display_name,summary,profile_url,inbox_url,
             shared_inbox_url,public_key_id,public_key_pem,status,fetched_at)
         VALUES
            (:actor_uri,"alex","Alex Rivera","Remote follower.",:profile_url,
             "https://remote.example/users/alex/inbox","https://remote.example/inbox",
             "https://remote.example/users/alex#main-key",:public_key,"active",UTC_TIMESTAMP())'
    )->execute([
        'actor_uri' => $remoteActorUri,
        'profile_url' => $remoteActorUri,
        'public_key' => $remotePublicKey,
    ]);
    $remoteActorId = (int)$pdo->lastInsertId();

    $followId = 'https://remote.example/activities/follow-v66f';
    $follow = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $followId,
        'type' => 'Follow',
        'actor' => $remoteActorUri,
        'object' => activitypub_actor_url(),
    ];
    $followJson = json_encode($follow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $pdo->prepare(
        'INSERT INTO activitypub_inbox_activities
            (activity_id,actor_uri,activity_type,object_uri,request_digest,
             signature_key_id,status,payload_json,processed_at)
         VALUES
            (:activity_id,:actor_uri,"Follow",:object_uri,:request_digest,
             :key_id,"accepted",:payload_json,UTC_TIMESTAMP())'
    )->execute([
        'activity_id' => $followId,
        'actor_uri' => $remoteActorUri,
        'object_uri' => activitypub_actor_url(),
        'request_digest' => hash('sha256', $followJson),
        'key_id' => 'https://remote.example/users/alex#main-key',
        'payload_json' => $followJson,
    ]);
    $pdo->prepare(
        'INSERT INTO activitypub_followers(remote_actor_id,follow_activity_id,status)
         VALUES(:actor_id,:follow_id,"pending")'
    )->execute(['actor_id' => $remoteActorId, 'follow_id' => $followId]);
    $followerId = (int)$pdo->lastInsertId();
    activitypub_moderate_follower($followerId, 'approved', $userId);
    $followerStatus = (string)$pdo->query('SELECT status FROM activitypub_followers WHERE id=' . $followerId)->fetchColumn();
    if ($followerStatus !== 'approved') $fail('ActivityPub follower approval failed.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_outbox_activities WHERE activity_type="Accept"')->fetchColumn() !== 1) $fail('ActivityPub Accept activity was not stored.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_deliveries WHERE remote_actor_id=' . $remoteActorId)->fetchColumn() !== 1) $fail('ActivityPub Accept delivery was not queued.');

    $pdo->prepare(
        'UPDATE blog_posts SET title="Federated article updated",updated_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 SECOND)
         WHERE id=:id'
    )->execute(['id' => $postId]);
    $updateId = activitypub_blog_event($postId, 'Update', $userId);
    if ($updateId <= 0 || $updateId === $createId) $fail('ActivityPub Update activity failed.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_deliveries WHERE outbox_activity_id=' . $updateId)->fetchColumn() !== 1) $fail('ActivityPub follower delivery was not queued.');

    $followersDocument = activitypub_followers_document();
    if (($followersDocument['totalItems'] ?? 0) !== 1 || ($followersDocument['orderedItems'][0] ?? '') !== $remoteActorUri) $fail('ActivityPub followers collection failed.');
    $outbox = activitypub_outbox_document();
    if (($outbox['totalItems'] ?? 0) < 2) $fail('ActivityPub outbox collection failed.');
    $object = activitypub_object_document($postId);
    if (($object['type'] ?? '') !== 'Article') $fail('ActivityPub public Article object failed.');

    $deliveryId = (int)$pdo->query('SELECT id FROM activitypub_deliveries ORDER BY id DESC LIMIT 1')->fetchColumn();
    $pdo->prepare('UPDATE activitypub_deliveries SET status="failed",attempt_count=5,last_error="test" WHERE id=:id')->execute(['id' => $deliveryId]);
    activitypub_retry_delivery($deliveryId);
    $retry = $pdo->query('SELECT status,attempt_count,last_error FROM activitypub_deliveries WHERE id=' . $deliveryId)->fetch();
    if (($retry['status'] ?? '') !== 'pending' || (int)($retry['attempt_count'] ?? -1) !== 0 || $retry['last_error'] !== null) $fail('ActivityPub delivery retry reset failed.');

    $snapshot = activitypub_blog_post($postId);
    $pdo->prepare('UPDATE blog_posts SET status="archived" WHERE id=:id')->execute(['id' => $postId]);
    $deleteId = activitypub_blog_event($postId, 'Delete', $userId, $snapshot);
    if ($deleteId <= 0) $fail('ActivityPub Delete activity failed.');
    $tombstone = activitypub_object_document($postId);
    if (($tombstone['type'] ?? '') !== 'Tombstone' || ($tombstone['formerType'] ?? '') !== 'Article') $fail('ActivityPub Tombstone rendering failed.');

    $pdo->prepare('UPDATE activitypub_followers SET status="removed" WHERE id=:id')->execute(['id' => $followerId]);
    if ((activitypub_followers_document()['totalItems'] ?? -1) !== 0) $fail('Removed ActivityPub follower remained public.');
} finally {
    $pdo->exec('DELETE FROM activitypub_deliveries');
    $pdo->exec('DELETE FROM activitypub_outbox_activities');
    $pdo->exec('DELETE FROM activitypub_inbox_activities');
    $pdo->exec('DELETE FROM activitypub_followers');
    $pdo->exec('DELETE FROM activitypub_remote_actors');
    $pdo->exec('DELETE FROM activitypub_actor_keys');
    if ($postId > 0) $pdo->prepare('DELETE FROM blog_posts WHERE id=:id')->execute(['id' => $postId]);
    if ($identityId > 0) $pdo->prepare('DELETE FROM pod_identities WHERE id=:id')->execute(['id' => $identityId]);
    if ($userId > 0) $pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $userId]);
}

echo "ActivityPub Federation v66F database integration passed.\n";

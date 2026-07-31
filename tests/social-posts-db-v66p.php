<?php
declare(strict_types=1);

define('NMM_ROOT', dirname(__DIR__));

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'nmm'
        ),
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: 'root',
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
        'security' => ['activitypub_secret' => 'test-only-social-posts-v66p-secret-0123456789'],
        default => [],
    };
}
function app_url(string $path = ''): string { return $path === '' ? 'https://pod.example' : 'https://pod.example/' . ltrim($path, '/'); }
function e(null|string|int|float $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function format_datetime(?string $value): string { return (string)$value; }
function status_label(string $value): string { return ucwords(str_replace('_', ' ', $value)); }
function log_activity(...$arguments): void {}
function current_user(): ?array { return null; }
function primary_admin_profile(): ?array { return null; }
function nmm_module_enabled(string $module, bool $fallback = true): bool { return $fallback; }

require_once NMM_ROOT . '/portal/activitypub-service.php';
require_once NMM_ROOT . '/portal/social-posts-service.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = db();
$pdo->exec('SET time_zone = "+00:00"');

$cleanup = static function () use ($pdo): void {
    foreach ([
        'pod_social_post_events','pod_social_posts',
        'activitypub_deliveries','activitypub_outbox_activities',
        'activitypub_followers','activitypub_following',
        'activitypub_inbox_activities','activitypub_remote_actors',
        'activitypub_actor_keys'
    ] as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
    $pdo->exec('DELETE FROM users WHERE email="social-owner-v66p@example.test"');
};
$cleanup();

try {
    $pdo->prepare(
        'INSERT INTO users(role,email,password_hash,display_name,status,must_change_password)
         VALUES("admin",:email,:password_hash,"Social Post Owner","active",0)'
    )->execute([
        'email' => 'social-owner-v66p@example.test',
        'password_hash' => password_hash('Test-only-Social-66P!', PASSWORD_DEFAULT),
    ]);
    $ownerId = (int)$pdo->lastInsertId();

    $resource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);
    if ($resource === false) $fail('Remote key generation failed.');
    $details = openssl_pkey_get_details($resource);
    $publicKey = is_array($details) ? (string)($details['key'] ?? '') : '';
    if ($publicKey === '') $fail('Remote public key export failed.');

    $followerUri = 'https://follower.example/users/alex';
    $pdo->prepare(
        'INSERT INTO activitypub_remote_actors
            (actor_uri,preferred_username,display_name,summary,profile_url,inbox_url,
             shared_inbox_url,public_key_id,public_key_pem,status,fetched_at)
         VALUES
            (:actor_uri,"alex","Alex Follower","Follower actor.",:profile_url,:inbox_url,
             :shared_inbox_url,:key_id,:public_key,"active",UTC_TIMESTAMP())'
    )->execute([
        'actor_uri' => $followerUri,
        'profile_url' => $followerUri,
        'inbox_url' => $followerUri . '/inbox',
        'shared_inbox_url' => 'https://follower.example/inbox',
        'key_id' => $followerUri . '#main-key',
        'public_key' => $publicKey,
    ]);
    $followerId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO activitypub_followers
            (remote_actor_id,follow_activity_id,status,followed_at)
         VALUES(:actor_id,:follow_id,"approved",UTC_TIMESTAMP())'
    )->execute([
        'actor_id' => $followerId,
        'follow_id' => 'https://follower.example/activities/follow-pod',
    ]);

    $settings = [
        'activitypub_enabled' => '1',
        'activitypub_username' => 'owner',
        'activitypub_display_name' => 'Social Post Owner',
        'activitypub_summary' => 'Social publishing test actor.',
        'social_posts_enabled' => '1',
        'social_posts_default_visibility' => 'public',
        'social_posts_allow_public' => '1',
        'social_posts_landing_mode' => 'tabs',
        'social_posts_landing_limit' => '6',
        'social_posts_show_follow_button' => '1',
    ];
    $save = $pdo->prepare(
        'INSERT INTO settings(setting_key,setting_value) VALUES(:key,:value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ($settings as $key => $value) $save->execute(['key' => $key, 'value' => $value]);

    $publicId = social_posts_create([
        'body_text' => "Public launch update.\n\n#POD #OpenSocial",
        'visibility' => 'public',
        'link_url' => 'https://example.com/launch',
    ], $ownerId, true);
    $public = social_posts_find($publicId);
    if (!$public || $public['status'] !== 'published' || $public['visibility'] !== 'public') {
        $fail('Public social post was not created correctly.');
    }
    $create = $pdo->query(
        'SELECT * FROM activitypub_outbox_activities
         WHERE activity_type="Create" AND object_type="Note"
         ORDER BY id LIMIT 1'
    )->fetch();
    if (!$create) $fail('Public Create/Note activity was not stored.');
    $payload = json_decode((string)$create['payload_json'], true);
    if (!is_array($payload) || ($payload['object']['type'] ?? '') !== 'Note') {
        $fail('Public ActivityPub object is not a Note.');
    }
    if (!in_array('https://www.w3.org/ns/activitystreams#Public', $payload['to'] ?? [], true)) {
        $fail('Public post is missing the Public audience.');
    }
    if (!in_array(activitypub_followers_url(), $payload['cc'] ?? [], true)) {
        $fail('Public post is missing the followers collection in cc.');
    }
    if (count($payload['object']['tag'] ?? []) !== 2) {
        $fail('Public post hashtags were not serialized.');
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_deliveries')->fetchColumn() !== 1) {
        $fail('Public post was not queued to the approved follower.');
    }

    $followersId = social_posts_create([
        'body_text' => 'Followers-only product note.',
        'visibility' => 'followers',
    ], $ownerId, true);
    $followers = social_posts_find($followersId);
    if (!$followers || $followers['visibility'] !== 'followers') {
        $fail('Followers-only post was not created correctly.');
    }
    $followersCreate = $pdo->prepare(
        'SELECT payload_json FROM activitypub_outbox_activities
         WHERE object_uri=:object_uri AND activity_type="Create" LIMIT 1'
    );
    $followersCreate->execute(['object_uri' => social_posts_object_url($followers)]);
    $followersPayload = json_decode((string)$followersCreate->fetchColumn(), true);
    if (($followersPayload['to'] ?? []) !== [activitypub_followers_url()]) {
        $fail('Followers-only post has the wrong ActivityPub audience.');
    }
    if (($followersPayload['cc'] ?? []) !== []) {
        $fail('Followers-only post unexpectedly has a public cc audience.');
    }

    $draftId = social_posts_create([
        'body_text' => 'Draft social post.',
        'visibility' => 'public',
    ], $ownerId, false);
    $draft = social_posts_find($draftId);
    if (!$draft || $draft['status'] !== 'draft' || $draft['published_at'] !== null) {
        $fail('Draft social post state is invalid.');
    }
    $draftActivities = $pdo->prepare(
        'SELECT COUNT(*) FROM activitypub_outbox_activities WHERE object_uri=:object_uri'
    );
    $draftActivities->execute(['object_uri' => social_posts_object_url($draft)]);
    if ((int)$draftActivities->fetchColumn() !== 0) $fail('Draft post was federated.');

    social_posts_update($draftId, [
        'body_text' => 'Draft is now published.',
        'visibility' => 'public',
    ], $ownerId, true);
    $draft = social_posts_find($draftId);
    if (!$draft || $draft['status'] !== 'published' || $draft['published_at'] === null) {
        $fail('Draft-to-published transition failed.');
    }

    social_posts_update($publicId, [
        'body_text' => 'Public launch update, edited.',
        'visibility' => 'public',
    ], $ownerId, true);
    $updated = social_posts_find($publicId);
    if (!$updated || $updated['edited_at'] === null) $fail('Published edit timestamp is missing.');
    $updateCount = $pdo->prepare(
        'SELECT COUNT(*) FROM activitypub_outbox_activities
         WHERE object_uri=:object_uri AND activity_type="Update"'
    );
    $updateCount->execute(['object_uri' => social_posts_object_url($updated)]);
    if ((int)$updateCount->fetchColumn() !== 1) $fail('Signed Update activity was not stored.');

    $publicRows = social_posts_public_posts(20);
    $publicIds = array_map(static fn(array $row): int => (int)$row['id'], $publicRows);
    if (!in_array($publicId, $publicIds, true) || !in_array($draftId, $publicIds, true)) {
        $fail('Public feed is missing public posts.');
    }
    if (in_array($followersId, $publicIds, true)) {
        $fail('Followers-only post leaked into the public feed.');
    }

    $rejectedMedia = false;
    try {
        social_posts_create([
            'body_text' => 'Unsafe media.',
            'visibility' => 'public',
            'media_kind' => 'image',
            'media_url' => 'https://remote.example/tracker.png',
        ], $ownerId, true);
    } catch (RuntimeException) {
        $rejectedMedia = true;
    }
    if (!$rejectedMedia) $fail('Cross-origin post media was accepted.');

    $rejectedLink = false;
    try {
        social_posts_create([
            'body_text' => 'Unsafe link.',
            'visibility' => 'public',
            'link_url' => 'http://remote.example/plaintext',
        ], $ownerId, true);
    } catch (RuntimeException) {
        $rejectedLink = true;
    }
    if (!$rejectedLink) $fail('Plain HTTP external link was accepted.');

    social_posts_delete($publicId, $ownerId);
    $deleted = social_posts_find($publicId);
    if (!$deleted || $deleted['status'] !== 'deleted' || $deleted['deleted_at'] === null) {
        $fail('Published post deletion state is invalid.');
    }
    $deleteCount = $pdo->prepare(
        'SELECT COUNT(*) FROM activitypub_outbox_activities
         WHERE object_uri=:object_uri AND activity_type="Delete" AND object_type="Tombstone"'
    );
    $deleteCount->execute(['object_uri' => social_posts_object_url($deleted)]);
    if ((int)$deleteCount->fetchColumn() !== 1) {
        $fail('Signed Delete/Tombstone activity was not stored.');
    }

    $eventTypes = $pdo->query(
        'SELECT DISTINCT event_type FROM pod_social_post_events ORDER BY event_type'
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ([
        'created_published','created_draft','draft_published','updated_published',
        'deleted','federation_create','federation_update','federation_delete'
    ] as $expected) {
        if (!in_array($expected, $eventTypes, true)) {
            $fail('Missing social post event: ' . $expected);
        }
    }

    echo "Social Posts v66P live database and ActivityPub integration passed.\n";
} finally {
    $cleanup();
}

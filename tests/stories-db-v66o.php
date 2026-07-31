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
        'security' => ['activitypub_secret' => 'test-only-stories-secret-v66o-0123456789'],
        default => [],
    };
}
function app_url(string $path = ''): string { return 'https://pod.example/' . ltrim($path, '/'); }
function e(null|string|int|float $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function format_datetime(?string $value): string { return (string)$value; }
function status_label(string $value): string { return ucwords(str_replace('_', ' ', $value)); }
function log_activity(...$arguments): void {}
function current_user(): ?array { return null; }
function primary_admin_profile(): ?array { return null; }

require_once NMM_ROOT . '/portal/activitypub-service.php';
require_once NMM_ROOT . '/portal/stories-service.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = db();
$pdo->exec('SET time_zone = "+00:00"');

$cleanup = static function () use ($pdo): void {
    foreach ([
        'pod_story_events','pod_story_views','pod_stories',
        'activitypub_deliveries','activitypub_outbox_activities',
        'activitypub_followers','activitypub_following',
        'activitypub_inbox_activities','activitypub_remote_actors',
        'activitypub_actor_keys'
    ] as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
    $pdo->exec('DELETE FROM users WHERE email IN ("stories-owner-v66o@example.test","stories-viewer-v66o@example.test")');
};
$cleanup();

try {
    $insertUser = $pdo->prepare(
        'INSERT INTO users(role,email,password_hash,display_name,status,must_change_password)
         VALUES(:role,:email,:password_hash,:display_name,"active",0)'
    );
    $insertUser->execute([
        'role' => 'admin',
        'email' => 'stories-owner-v66o@example.test',
        'password_hash' => password_hash('Test-only-Stories-66O!', PASSWORD_DEFAULT),
        'display_name' => 'Stories Owner',
    ]);
    $ownerId = (int)$pdo->lastInsertId();
    $insertUser->execute([
        'role' => 'client',
        'email' => 'stories-viewer-v66o@example.test',
        'password_hash' => password_hash('Test-only-Viewer-66O!', PASSWORD_DEFAULT),
        'display_name' => 'Stories Viewer',
    ]);
    $viewerId = (int)$pdo->lastInsertId();

    $resource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);
    if ($resource === false) $fail('Remote key generation failed.');
    $details = openssl_pkey_get_details($resource);
    $publicKey = is_array($details) ? (string)($details['key'] ?? '') : '';
    if ($publicKey === '') $fail('Remote public key export failed.');

    $insertActor = $pdo->prepare(
        'INSERT INTO activitypub_remote_actors
            (actor_uri,preferred_username,display_name,summary,profile_url,inbox_url,
             shared_inbox_url,public_key_id,public_key_pem,status,fetched_at)
         VALUES
            (:actor_uri,:username,:display_name,"Stories actor.",:profile_url,:inbox_url,
             :shared_inbox_url,:key_id,:public_key,"active",UTC_TIMESTAMP())'
    );
    $followedUri = 'https://followed.example/users/alex';
    $insertActor->execute([
        'actor_uri' => $followedUri,
        'username' => 'alex',
        'display_name' => 'Alex Followed',
        'profile_url' => $followedUri,
        'inbox_url' => $followedUri . '/inbox',
        'shared_inbox_url' => 'https://followed.example/inbox',
        'key_id' => $followedUri . '#main-key',
        'public_key' => $publicKey,
    ]);
    $followedId = (int)$pdo->lastInsertId();
    $outsideUri = 'https://outside.example/users/sam';
    $insertActor->execute([
        'actor_uri' => $outsideUri,
        'username' => 'sam',
        'display_name' => 'Sam Outside',
        'profile_url' => $outsideUri,
        'inbox_url' => $outsideUri . '/inbox',
        'shared_inbox_url' => 'https://outside.example/inbox',
        'key_id' => $outsideUri . '#main-key',
        'public_key' => $publicKey,
    ]);
    $outsideId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO activitypub_following
            (remote_actor_id,follow_activity_uri,status,created_by_user_id,accepted_at)
         VALUES(:actor_id,:follow_uri,"accepted",:user_id,UTC_TIMESTAMP())'
    )->execute([
        'actor_id' => $followedId,
        'follow_uri' => 'https://pod.example/activitypub-activity.php?id=' . pod_uuid_v4(),
        'user_id' => $ownerId,
    ]);
    $pdo->prepare(
        'INSERT INTO activitypub_followers
            (remote_actor_id,follow_activity_id,status,followed_at)
         VALUES(:actor_id,:follow_id,"approved",UTC_TIMESTAMP())'
    )->execute([
        'actor_id' => $followedId,
        'follow_id' => 'https://followed.example/activities/follow-pod',
    ]);

    $settings = [
        'activitypub_enabled' => '1',
        'activitypub_username' => 'owner',
        'activitypub_display_name' => 'Stories Owner',
        'activitypub_summary' => 'Stories test actor.',
        'stories_enabled' => '1',
        'stories_receive_remote' => '1',
        'stories_duration_hours' => '24',
        'stories_max_active' => '10',
        'stories_remote_media_mode' => 'link_only',
    ];
    $save = $pdo->prepare(
        'INSERT INTO settings(setting_key,setting_value) VALUES(:key,:value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ($settings as $key => $value) $save->execute(['key' => $key, 'value' => $value]);

    $localId = stories_create_local([
        'title' => 'Local launch story',
        'body_text' => 'A follower-only update.',
        'media_kind' => 'none',
    ], $ownerId);
    $local = stories_find($localId);
    if (!$local || $local['status'] !== 'active' || $local['visibility'] !== 'followers') {
        $fail('Local follower story was not created correctly.');
    }
    $duration = (strtotime((string)$local['expires_at']) ?: 0)
        - (strtotime((string)$local['published_at']) ?: 0);
    if ($duration < 86300 || $duration > 86500) $fail('Local story duration is not 24 hours.');
    $createCount = (int)$pdo->query(
        'SELECT COUNT(*) FROM activitypub_outbox_activities
         WHERE activity_type="Create" AND object_type="Note"'
    )->fetchColumn();
    if ($createCount !== 1) $fail('Signed local Story Create activity was not stored.');
    $deliveryCount = (int)$pdo->query('SELECT COUNT(*) FROM activitypub_deliveries')->fetchColumn();
    if ($deliveryCount !== 1) $fail('The local story was not queued to approved followers.');

    if (!stories_mark_viewed($localId, $viewerId) || !stories_mark_viewed($localId, $viewerId)) {
        $fail('Story view receipt failed.');
    }
    $views = $pdo->prepare(
        'SELECT view_count FROM pod_story_views WHERE story_id=:story_id AND viewer_user_id=:viewer_id'
    );
    $views->execute(['story_id' => $localId, 'viewer_id' => $viewerId]);
    if ((int)$views->fetchColumn() !== 2) $fail('Story view count is not durable.');

    $remoteObject = 'https://followed.example/stories/launch';
    $remotePayload = [
        'id' => 'https://followed.example/activities/story-create',
        'type' => 'Create',
        'actor' => $followedUri,
        'to' => [$followedUri . '/followers'],
        'object' => [
            'id' => $remoteObject,
            'type' => 'Note',
            'attributedTo' => $followedUri,
            'published' => gmdate(DATE_ATOM),
            'endTime' => gmdate(DATE_ATOM, time() + 23 * 3600),
            'content' => '<p>Remote followed story.</p>',
            'tag' => [['type' => 'Hashtag', 'name' => '#story']],
            'attachment' => [[
                'type' => 'Image',
                'url' => 'https://cdn.followed.example/story.jpg',
                'name' => 'Remote image',
            ]],
        ],
    ];
    if (!stories_process_inbound(1001, $remotePayload, [
        'id' => $followedId,
        'actor_uri' => $followedUri,
    ])) {
        $fail('Accepted Following story was not ingested.');
    }
    $remote = $pdo->query(
        'SELECT * FROM pod_stories WHERE direction="remote" ORDER BY id DESC LIMIT 1'
    )->fetch();
    if (!$remote || $remote['media_kind'] !== 'image' || $remote['media_url'] === '') {
        $fail('Remote story link metadata was not stored.');
    }

    $outsidePayload = $remotePayload;
    $outsidePayload['id'] = 'https://outside.example/activities/story-create';
    $outsidePayload['actor'] = $outsideUri;
    $outsidePayload['object']['id'] = 'https://outside.example/stories/blocked';
    $outsidePayload['object']['attributedTo'] = $outsideUri;
    if (stories_process_inbound(1002, $outsidePayload, [
        'id' => $outsideId,
        'actor_uri' => $outsideUri,
    ])) {
        $fail('An unfollowed actor populated Stories.');
    }

    $feed = stories_feed($viewerId, 20);
    if (count($feed) !== 2) $fail('Stories feed did not include local and followed stories.');
    stories_moderate_remote((int)$remote['id'], 'hide', $ownerId);
    if (count(stories_feed($viewerId, 20)) !== 1) $fail('Hidden remote story remained in the feed.');
    stories_moderate_remote((int)$remote['id'], 'unhide', $ownerId);

    stories_delete_local($localId, $ownerId);
    $deleteCount = (int)$pdo->query(
        'SELECT COUNT(*) FROM activitypub_outbox_activities
         WHERE activity_type="Delete" AND object_type="Tombstone"'
    )->fetchColumn();
    if ($deleteCount !== 1) $fail('Signed Story Delete/Tombstone was not stored.');

    $pdo->prepare(
        'UPDATE pod_stories SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE)
         WHERE id=:id'
    )->execute(['id' => (int)$remote['id']]);
    $expired = stories_expire_due(10);
    if (!in_array((int)$remote['id'], $expired, true)) $fail('Due remote story was not expired.');

    $eventTypes = $pdo->query(
        'SELECT DISTINCT event_type FROM pod_story_events ORDER BY event_type'
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach (['created','federation_create','remote_created','viewed','moderated_hide','moderated_unhide','deleted','federation_delete','expired'] as $expected) {
        if (!in_array($expected, $eventTypes, true)) $fail('Missing story event: ' . $expected);
    }

    echo "Stories v66O live database integration passed.\n";
} finally {
    $cleanup();
}

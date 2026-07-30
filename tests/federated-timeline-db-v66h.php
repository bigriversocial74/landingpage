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
        'security' => ['activitypub_secret' => 'test-only-activitypub-secret-66h-0123456789'],
        default => [],
    };
}
function app_url(string $path = ''): string { return 'https://pod.example/' . ltrim($path, '/'); }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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

$cleanup = static function () use ($pdo): void {
    foreach ([
        'activitypub_timeline_user_state',
        'activitypub_remote_post_actions',
        'activitypub_remote_posts',
        'activitypub_deliveries',
        'activitypub_local_objects',
        'activitypub_remote_reactions',
        'activitypub_remote_comments',
        'activitypub_following',
        'activitypub_actor_controls',
        'activitypub_domain_blocks',
        'activitypub_outbox_activities',
        'activitypub_inbox_activities',
        'activitypub_followers',
        'activitypub_remote_actors',
        'activitypub_actor_keys',
    ] as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
    $pdo->exec('DELETE FROM content_comment_reports');
    $pdo->exec('DELETE FROM content_moderation_events');
    $pdo->exec('DELETE FROM content_comment_edits');
    $pdo->exec('DELETE FROM content_reactions');
    $pdo->exec('DELETE FROM content_comments');
    $pdo->exec('DELETE FROM content_interaction_settings');
    $pdo->exec('DELETE FROM portal_notifications');
    $pdo->exec('DELETE FROM pod_identity_origins WHERE pod_identity_id IN (SELECT id FROM pod_identities WHERE local_key="primary")');
    $pdo->exec('DELETE FROM pod_identities WHERE local_key="primary"');
    $pdo->exec('DELETE FROM users WHERE email IN ("timeline-owner-v66h@example.test","timeline-reader-v66h@example.test")');
};

$cleanup();
$userId = 0;
$readerId = 0;
$identityId = 0;
$followedActorId = 0;
$unfollowedActorId = 0;

$insertInbox = static function (array $payload, string $actorUri): int {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $object = $payload['object'] ?? '';
    $objectUri = is_array($object) ? (string)($object['id'] ?? '') : (string)$object;
    db()->prepare(
        'INSERT INTO activitypub_inbox_activities
            (activity_id,actor_uri,activity_type,object_uri,request_digest,
             signature_key_id,status,payload_json,processed_at)
         VALUES
            (:activity_id,:actor_uri,:activity_type,:object_uri,:request_digest,
             :key_id,"accepted",:payload_json,UTC_TIMESTAMP())'
    )->execute([
        'activity_id' => (string)$payload['id'],
        'actor_uri' => $actorUri,
        'activity_type' => (string)$payload['type'],
        'object_uri' => $objectUri !== '' ? $objectUri : null,
        'request_digest' => hash('sha256', $json),
        'key_id' => $actorUri . '#main-key',
        'payload_json' => $json,
    ]);
    return (int)db()->lastInsertId();
};

$insertActor = static function (string $actorUri, string $username, string $displayName): int {
    $resource = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    if ($resource === false) throw new RuntimeException('Remote key generation failed.');
    $details = openssl_pkey_get_details($resource);
    $publicKey = is_array($details) ? (string)($details['key'] ?? '') : '';
    if ($publicKey === '') throw new RuntimeException('Remote public key export failed.');
    db()->prepare(
        'INSERT INTO activitypub_remote_actors
            (actor_uri,preferred_username,display_name,summary,profile_url,inbox_url,
             shared_inbox_url,public_key_id,public_key_pem,status,fetched_at)
         VALUES
            (:actor_uri,:username,:display_name,"Timeline test actor.",:profile_url,
             :inbox_url,:shared_inbox_url,:key_id,:public_key,"active",UTC_TIMESTAMP())'
    )->execute([
        'actor_uri' => $actorUri,
        'username' => $username,
        'display_name' => $displayName,
        'profile_url' => $actorUri,
        'inbox_url' => $actorUri . '/inbox',
        'shared_inbox_url' => 'https://' . parse_url($actorUri, PHP_URL_HOST) . '/inbox',
        'key_id' => $actorUri . '#main-key',
        'public_key' => $publicKey,
    ]);
    return (int)db()->lastInsertId();
};

try {
    $pdo->prepare(
        'INSERT INTO users(role,email,password_hash,display_name,status,must_change_password)
         VALUES("admin",:email,:password_hash,"Timeline Owner","active",0)'
    )->execute([
        'email' => 'timeline-owner-v66h@example.test',
        'password_hash' => password_hash('Test-only-Timeline-66H!', PASSWORD_DEFAULT),
    ]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO users(role,email,password_hash,display_name,status,must_change_password)
         VALUES("client",:email,:password_hash,"Timeline Reader","active",0)'
    )->execute([
        'email' => 'timeline-reader-v66h@example.test',
        'password_hash' => password_hash('Test-only-Reader-66H!', PASSWORD_DEFAULT),
    ]);
    $readerId = (int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO pod_identities
            (pod_uuid,local_key,is_local,identity_type,owner_user_id,display_name,
             public_username,summary,canonical_origin,profile_url,agent_url,
             main_feed_url,verification_status,status,discovered_at,last_verified_at)
         VALUES
            (:pod_uuid,"primary",1,"personal_pod",:owner_user_id,"Timeline Owner",
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
         VALUES (:identity_id,"https://pod.example","verified","test",UTC_TIMESTAMP(),1)'
    )->execute(['identity_id' => $identityId]);

    $settings = [
        'activitypub_enabled' => '1',
        'activitypub_federate_blog_posts' => '1',
        'activitypub_username' => 'owner',
        'activitypub_display_name' => 'Timeline Owner',
        'activitypub_summary' => 'Owner-controlled POD actor.',
        'activitypub_timeline_enabled' => '1',
        'activitypub_timeline_store_following' => '1',
        'activitypub_timeline_receive_mentions' => '1',
        'activitypub_timeline_retention_days' => '30',
        'activitypub_timeline_remote_media_mode' => 'link_only',
    ];
    $saveSetting = $pdo->prepare(
        'INSERT INTO settings(setting_key,setting_value) VALUES(:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ($settings as $key => $value) {
        $saveSetting->execute(['setting_key' => $key, 'setting_value' => $value]);
    }
    activitypub_rotate_key($userId);

    $followedUri = 'https://followed.example/users/alex';
    $unfollowedUri = 'https://outside.example/users/sam';
    $followedActorId = $insertActor($followedUri, 'alex', 'Alex Followed');
    $unfollowedActorId = $insertActor($unfollowedUri, 'sam', 'Sam Outside');
    $pdo->prepare(
        'INSERT INTO activitypub_following
            (remote_actor_id,follow_activity_uri,status,created_by_user_id,accepted_at)
         VALUES (:actor_id,:follow_uri,"accepted",:user_id,UTC_TIMESTAMP())'
    )->execute([
        'actor_id' => $followedActorId,
        'follow_uri' => 'https://pod.example/activitypub-activity.php?id=' . pod_uuid_v4(),
        'user_id' => $userId,
    ]);
    $followedActor = $pdo->query('SELECT * FROM activitypub_remote_actors WHERE id=' . $followedActorId)->fetch();
    $unfollowedActor = $pdo->query('SELECT * FROM activitypub_remote_actors WHERE id=' . $unfollowedActorId)->fetch();
    if (!$followedActor || !$unfollowedActor) $fail('Timeline actor fixtures failed.');

    $objectUri = 'https://followed.example/notes/timeline-v66h';
    $create = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://followed.example/activities/create-timeline-v66h',
        'type' => 'Create',
        'actor' => $followedUri,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'object' => [
            'id' => $objectUri,
            'type' => 'Note',
            'attributedTo' => $followedUri,
            'content' => '<p>Hello <strong>timeline</strong>.</p><script>alert(1)</script>',
            'published' => '2026-07-30T20:00:00Z',
            'url' => $objectUri,
            'attachment' => [
                ['type' => 'Image', 'url' => 'https://media.followed.example/photo.jpg', 'name' => 'Photo'],
                ['type' => 'Image', 'url' => 'http://unsafe.example/pixel.jpg', 'name' => 'Unsafe'],
            ],
            'tag' => [['type' => 'Hashtag', 'name' => '#POD', 'href' => 'https://followed.example/tags/pod']],
        ],
    ];
    $inboxId = $insertInbox($create, $followedUri);
    if (!federated_timeline_ingest($inboxId, $create, $followedActor)) $fail('Followed timeline Create was not stored.');
    $timelinePost = $pdo->query('SELECT * FROM activitypub_remote_posts WHERE entry_uri=' . $pdo->quote($objectUri))->fetch();
    if (!$timelinePost || $timelinePost['status'] !== 'active' || $timelinePost['body_text'] !== 'Hello timeline.') {
        $fail('Followed timeline post status or sanitization failed.');
    }
    $postId = (int)$timelinePost['id'];
    $attachments = json_decode((string)$timelinePost['attachments_json'], true);
    if (!is_array($attachments) || count($attachments) !== 1 || $attachments[0]['url'] !== 'https://media.followed.example/photo.jpg') {
        $fail('Link-only timeline attachment storage failed.');
    }

    federated_timeline_set_state($postId, $userId, 'read');
    federated_timeline_set_state($postId, $userId, 'save');
    federated_timeline_set_state($postId, $userId, 'hide');
    $state = $pdo->query('SELECT * FROM activitypub_timeline_user_state WHERE remote_post_id=' . $postId . ' AND user_id=' . $userId)->fetch();
    if (!$state || !$state['read_at'] || !$state['saved_at'] || !$state['hidden_at']) $fail('Private timeline user state failed.');
    federated_timeline_set_state($postId, $userId, 'unhide');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_timeline_user_state WHERE remote_post_id=' . $postId . ' AND hidden_at IS NULL')->fetchColumn() !== 1) {
        $fail('Timeline unhide state failed.');
    }

    $update = $create;
    $update['id'] = 'https://followed.example/activities/update-timeline-v66h';
    $update['type'] = 'Update';
    $update['object']['content'] = '<p>Updated followed timeline post.</p>';
    $update['object']['updated'] = '2026-07-30T20:15:00Z';
    $updateInbox = $insertInbox($update, $followedUri);
    if (!federated_timeline_ingest($updateInbox, $update, $followedActor)) $fail('Followed timeline Update failed.');
    if ((string)$pdo->query('SELECT body_text FROM activitypub_remote_posts WHERE id=' . $postId)->fetchColumn() !== 'Updated followed timeline post.') {
        $fail('Timeline Update content failed.');
    }

    $announce = [
        'id' => 'https://followed.example/activities/announce-v66h',
        'type' => 'Announce',
        'actor' => $followedUri,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'object' => 'https://other.example/notes/boosted-v66h',
        'published' => '2026-07-30T20:20:00Z',
    ];
    if (!federated_timeline_ingest($insertInbox($announce, $followedUri), $announce, $followedActor)) {
        $fail('Followed Announce was not stored.');
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_remote_posts WHERE entry_type="announce" AND status="active"')->fetchColumn() !== 1) {
        $fail('Timeline boost entry failed.');
    }

    $ignored = [
        'id' => 'https://outside.example/activities/ignored-v66h',
        'type' => 'Create',
        'actor' => $unfollowedUri,
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'object' => [
            'id' => 'https://outside.example/notes/ignored-v66h',
            'type' => 'Note',
            'attributedTo' => $unfollowedUri,
            'content' => '<p>Unsolicited nonmention.</p>',
        ],
    ];
    if (federated_timeline_ingest($insertInbox($ignored, $unfollowedUri), $ignored, $unfollowedActor)) {
        $fail('Unfollowed nonmention was stored.');
    }

    $mention = $ignored;
    $mention['id'] = 'https://outside.example/activities/mention-v66h';
    $mention['to'] = [activitypub_actor_url()];
    $mention['object']['id'] = 'https://outside.example/notes/mention-v66h';
    $mention['object']['content'] = '<p>Hello @owner, review this mention.</p>';
    $mention['object']['to'] = [activitypub_actor_url()];
    if (!federated_timeline_ingest($insertInbox($mention, $unfollowedUri), $mention, $unfollowedActor)) {
        $fail('Direct federated mention was not quarantined.');
    }
    $mentionPost = $pdo->query('SELECT * FROM activitypub_remote_posts WHERE entry_uri="https://outside.example/notes/mention-v66h"')->fetch();
    if (!$mentionPost || $mentionPost['status'] !== 'pending' || (int)$mentionPost['mentions_local'] !== 1) {
        $fail('Unsolicited mention quarantine failed.');
    }
    federated_timeline_moderate((int)$mentionPost['id'], 'active', $userId, 'Approved mention.');
    if ((string)$pdo->query('SELECT status FROM activitypub_remote_posts WHERE id=' . (int)$mentionPost['id'])->fetchColumn() !== 'active') {
        $fail('Federated mention approval failed.');
    }

    $all = federated_timeline_query($userId, ['queue' => 'all'], 100);
    $saved = federated_timeline_query($userId, ['queue' => 'saved'], 100);
    $boosts = federated_timeline_query($userId, ['queue' => 'boosts'], 100);
    if (count($all) < 3 || count($saved) !== 1 || count($boosts) !== 1) {
        $fail('Federated timeline queue filtering failed.');
    }

    $likeActionId = federated_timeline_action($postId, 'like', $userId);
    $boostActionId = federated_timeline_action($postId, 'announce', $userId);
    $replyActionId = federated_timeline_action($postId, 'reply', $userId, 'A signed local reply.');
    if ($likeActionId <= 0 || $boostActionId <= 0 || $replyActionId <= 0) $fail('Signed timeline actions failed.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_remote_post_actions WHERE status="active"')->fetchColumn() !== 3) {
        $fail('Timeline action receipts failed.');
    }
    $replyAction = $pdo->query('SELECT * FROM activitypub_remote_post_actions WHERE id=' . $replyActionId)->fetch();
    $replyObject = federated_timeline_reply_object((string)$replyAction['action_uuid']);
    if (($replyObject['type'] ?? '') !== 'Note' || ($replyObject['inReplyTo'] ?? '') !== $objectUri) {
        $fail('Dereferenceable timeline reply object failed.');
    }

    federated_timeline_undo_action($likeActionId, $userId);
    federated_timeline_undo_action($boostActionId, $userId);
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_remote_post_actions WHERE id IN (' . $likeActionId . ',' . $boostActionId . ') AND status="undone"')->fetchColumn() !== 2) {
        $fail('Signed timeline Undo failed.');
    }
    federated_timeline_delete_reply($replyActionId, $userId);
    $deletedReply = $pdo->query('SELECT status,object_json FROM activitypub_remote_post_actions WHERE id=' . $replyActionId)->fetch();
    $deletedObject = json_decode((string)$deletedReply['object_json'], true);
    if (($deletedReply['status'] ?? '') !== 'deleted' || ($deletedObject['type'] ?? '') !== 'Tombstone') {
        $fail('Timeline reply Delete/Tombstone failed.');
    }

    $deliveryActionId = federated_timeline_action((int)$mentionPost['id'], 'like', $userId);
    $deliveryAction = $pdo->query('SELECT * FROM activitypub_remote_post_actions WHERE id=' . $deliveryActionId)->fetch();
    $delivery = $pdo->query(
        'SELECT * FROM activitypub_deliveries WHERE outbox_activity_id=' . (int)$deliveryAction['outbox_activity_id'] .
        ' AND remote_actor_id=' . $unfollowedActorId . ' ORDER BY id DESC LIMIT 1'
    )->fetch();
    if (!$delivery) $fail('Primary timeline delivery receipt was not queued.');
    federated_timeline_sync_delivery($delivery, ['ok' => false, 'error' => 'Synthetic delivery failure.']);
    $failedAction = $pdo->query('SELECT status,last_error FROM activitypub_remote_post_actions WHERE id=' . $deliveryActionId)->fetch();
    if (($failedAction['status'] ?? '') !== 'failed' || !str_contains((string)$failedAction['last_error'], 'Synthetic')) {
        $fail('Timeline delivery failure synchronization failed.');
    }
    federated_timeline_reset_delivery((int)$delivery['id']);
    if ((string)$pdo->query('SELECT status FROM activitypub_remote_post_actions WHERE id=' . $deliveryActionId)->fetchColumn() !== 'active') {
        $fail('Timeline delivery retry reset failed.');
    }

    $oldUnsavedUri = 'https://followed.example/notes/old-unsaved-v66h';
    $oldSavedUri = 'https://followed.example/notes/old-saved-v66h';
    foreach ([[$oldUnsavedUri, 'Old unsaved'], [$oldSavedUri, 'Old saved']] as [$uri, $body]) {
        $pdo->prepare(
            'INSERT INTO activitypub_remote_posts
                (remote_actor_id,entry_uri,source_activity_uri,object_uri,entry_type,source_url,
                 body_text,body_hash,status,source_published_at,created_at)
             VALUES
                (:actor_id,:entry_uri,:activity_uri,:object_uri,"note",:source_url,
                 :body_text,:body_hash,"active",DATE_SUB(UTC_TIMESTAMP(),INTERVAL 120 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 120 DAY))'
        )->execute([
            'actor_id' => $followedActorId,
            'entry_uri' => $uri,
            'activity_uri' => $uri . '/activity',
            'object_uri' => $uri,
            'source_url' => $uri,
            'body_text' => $body,
            'body_hash' => hash('sha256', $body),
        ]);
    }
    $oldSavedId = (int)$pdo->query('SELECT id FROM activitypub_remote_posts WHERE entry_uri=' . $pdo->quote($oldSavedUri))->fetchColumn();
    federated_timeline_set_state($oldSavedId, $userId, 'save');
    $deleted = federated_timeline_cleanup();
    if ($deleted < 1) $fail('Timeline retention did not remove expired unsaved entries.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_remote_posts WHERE entry_uri=' . $pdo->quote($oldSavedUri))->fetchColumn() !== 1) {
        $fail('Timeline retention removed a saved entry.');
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_remote_posts WHERE id=' . $postId)->fetchColumn() !== 1) {
        $fail('Timeline retention removed a post with action evidence.');
    }

    federated_interactions_set_actor_control($followedActorId, 'blocked', 'Timeline containment test.', $userId);
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_remote_posts WHERE remote_actor_id=' . $followedActorId . ' AND status="active"')->fetchColumn() !== 0) {
        $fail('Actor block did not contain active timeline posts.');
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_remote_post_actions action JOIN activitypub_remote_posts post ON post.id=action.remote_post_id WHERE post.remote_actor_id=' . $followedActorId . ' AND action.status="active"')->fetchColumn() !== 0) {
        $fail('Actor block did not contain timeline actions.');
    }

    $deletePayload = [
        'id' => 'https://outside.example/activities/delete-mention-v66h',
        'type' => 'Delete',
        'actor' => $unfollowedUri,
        'object' => 'https://outside.example/notes/mention-v66h',
    ];
    if (!federated_timeline_process_inbound($insertInbox($deletePayload, $unfollowedUri), $deletePayload, $unfollowedActor)) {
        $fail('Remote timeline Delete was not processed.');
    }
    if ((string)$pdo->query('SELECT status FROM activitypub_remote_posts WHERE id=' . (int)$mentionPost['id'])->fetchColumn() !== 'deleted') {
        $fail('Remote timeline Delete state failed.');
    }

    echo "Federated Timeline v66H database integration passed.\n";
} finally {
    $cleanup();
}

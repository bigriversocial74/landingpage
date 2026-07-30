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
        'security' => ['activitypub_secret' => 'test-only-activitypub-secret-66g-0123456789'],
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
    $pdo->exec('DELETE FROM blog_posts WHERE slug="federated-interactions-v66g"');
    $pdo->exec('DELETE FROM users WHERE email IN ("federation-owner-v66g@example.test","federation-reader-v66g@example.test")');
};

$cleanup();
$userId = 0;
$readerId = 0;
$postId = 0;
$identityId = 0;
$remoteActorId = 0;
$localCommentId = 0;

try {
    $pdo->prepare(
        'INSERT INTO users(role,email,password_hash,display_name,status,must_change_password)
         VALUES("admin",:email,:password_hash,"Federation Owner","active",0)'
    )->execute([
        'email' => 'federation-owner-v66g@example.test',
        'password_hash' => password_hash('Test-only-Password-66G!', PASSWORD_DEFAULT),
    ]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO users(role,email,password_hash,display_name,status,must_change_password)
         VALUES("client",:email,:password_hash,"Local Reader","active",0)'
    )->execute([
        'email' => 'federation-reader-v66g@example.test',
        'password_hash' => password_hash('Test-only-Reader-66G!', PASSWORD_DEFAULT),
    ]);
    $readerId = (int)$pdo->lastInsertId();

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
         VALUES (:identity_id,"https://pod.example","verified","test",UTC_TIMESTAMP(),1)'
    )->execute(['identity_id' => $identityId]);

    $settings = [
        'activitypub_enabled' => '1',
        'activitypub_federate_blog_posts' => '1',
        'activitypub_manual_follow_approval' => '1',
        'activitypub_username' => 'owner',
        'activitypub_display_name' => 'Federation Owner',
        'activitypub_summary' => 'Owner-controlled POD actor.',
        'activitypub_show_followers' => '1',
        'activitypub_federate_comments' => '1',
        'activitypub_federate_reactions' => '1',
        'activitypub_allow_remote_replies' => '1',
        'activitypub_allow_remote_reactions' => '1',
        'activitypub_remote_reply_moderation' => 'pre_moderated',
        'activitypub_show_following' => '1',
    ];
    $saveSetting = $pdo->prepare(
        'INSERT INTO settings(setting_key,setting_value) VALUES(:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ($settings as $key => $value) {
        $saveSetting->execute(['setting_key' => $key, 'setting_value' => $value]);
    }
    activitypub_rotate_key($userId);

    $pdo->prepare(
        'INSERT INTO blog_posts
            (author_user_id,title,slug,status,featured,category,excerpt,body,tags,published_at)
         VALUES
            (:author_user_id,"Federated interactions",:slug,"published",1,"Open Web",
             "A two-way federation test.","## Federated interactions\nPublic body.",
             "ActivityPub, Federation",UTC_TIMESTAMP())'
    )->execute(['author_user_id' => $userId, 'slug' => 'federated-interactions-v66g']);
    $postId = (int)$pdo->lastInsertId();

    $remoteKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    if ($remoteKey === false) $fail('Remote actor key generation failed.');
    $remoteDetails = openssl_pkey_get_details($remoteKey);
    $remotePublicKey = is_array($remoteDetails) ? (string)($remoteDetails['key'] ?? '') : '';
    if ($remotePublicKey === '') $fail('Remote actor public key export failed.');
    $remoteActorUri = 'https://remote.example/users/alex';
    $pdo->prepare(
        'INSERT INTO activitypub_remote_actors
            (actor_uri,preferred_username,display_name,summary,profile_url,inbox_url,
             shared_inbox_url,public_key_id,public_key_pem,status,fetched_at)
         VALUES
            (:actor_uri,"alex","Alex Rivera","Remote participant.",:profile_url,
             "https://remote.example/users/alex/inbox","https://remote.example/inbox",
             "https://remote.example/users/alex#main-key",:public_key,"active",UTC_TIMESTAMP())'
    )->execute([
        'actor_uri' => $remoteActorUri,
        'profile_url' => $remoteActorUri,
        'public_key' => $remotePublicKey,
    ]);
    $remoteActorId = (int)$pdo->lastInsertId();
    $remoteActor = $pdo->query('SELECT * FROM activitypub_remote_actors WHERE id=' . $remoteActorId)->fetch();
    if (!$remoteActor) $fail('Remote actor fixture failed.');

    $remoteActivityId = 'https://remote.example/activities/reply-v66g';
    $remoteObjectId = 'https://remote.example/notes/reply-v66g';
    $createPayload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $remoteActivityId,
        'type' => 'Create',
        'actor' => $remoteActorUri,
        'object' => [
            'id' => $remoteObjectId,
            'type' => 'Note',
            'attributedTo' => $remoteActorUri,
            'inReplyTo' => activitypub_object_url($postId),
            'content' => '<p>Hello <strong>POD</strong>.</p><script>alert(1)</script>',
            'published' => '2026-07-30T20:00:00Z',
            'url' => $remoteObjectId,
        ],
    ];
    $createJson = json_encode($createPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdo->prepare(
        'INSERT INTO activitypub_inbox_activities
            (activity_id,actor_uri,activity_type,object_uri,request_digest,signature_key_id,status,payload_json,processed_at)
         VALUES
            (:activity_id,:actor_uri,"Create",:object_uri,:digest,:key_id,"accepted",:payload_json,UTC_TIMESTAMP())'
    )->execute([
        'activity_id' => $remoteActivityId,
        'actor_uri' => $remoteActorUri,
        'object_uri' => $remoteObjectId,
        'digest' => hash('sha256', $createJson),
        'key_id' => 'https://remote.example/users/alex#main-key',
        'payload_json' => $createJson,
    ]);
    $inboxId = (int)$pdo->lastInsertId();
    if (!federated_interactions_ingest_comment($inboxId, $createPayload, $remoteActor)) {
        $fail('Remote Create reply was not ingested.');
    }
    $remoteComment = $pdo->query('SELECT * FROM activitypub_remote_comments LIMIT 1')->fetch();
    if (!$remoteComment || $remoteComment['status'] !== 'pending' || $remoteComment['body_text'] !== 'Hello POD.') {
        $fail('Remote reply moderation or sanitization failed.');
    }
    $remoteCommentId = (int)$remoteComment['id'];
    federated_interactions_moderate_remote_comment($remoteCommentId, 'approved', $userId, 'Verified test reply.');
    if ((string)$pdo->query('SELECT status FROM activitypub_remote_comments WHERE id=' . $remoteCommentId)->fetchColumn() !== 'approved') {
        $fail('Remote reply approval failed.');
    }
    $counts = federated_interactions_remote_counts($postId);
    if ($counts['comments'] !== 1) $fail('Approved remote reply count failed.');
    ob_start();
    federated_interactions_render_public(['id' => $postId]);
    $markup = (string)ob_get_clean();
    if (!str_contains($markup, 'Federated conversation') || !str_contains($markup, 'Hello POD.')) {
        $fail('Approved remote reply public rendering failed.');
    }

    $updatePayload = $createPayload;
    $updatePayload['id'] = 'https://remote.example/activities/reply-update-v66g';
    $updatePayload['type'] = 'Update';
    $updatePayload['object']['content'] = '<p>Hello updated federation.</p>';
    $updatePayload['object']['updated'] = '2026-07-30T20:10:00Z';
    if (!federated_interactions_ingest_comment($inboxId, $updatePayload, $remoteActor)) {
        $fail('Remote Update reply was not ingested.');
    }
    $updated = $pdo->query('SELECT body_text,status FROM activitypub_remote_comments WHERE id=' . $remoteCommentId)->fetch();
    if (($updated['body_text'] ?? '') !== 'Hello updated federation.' || ($updated['status'] ?? '') !== 'pending') {
        $fail('Remote reply edit did not return to moderation.');
    }
    federated_interactions_moderate_remote_comment($remoteCommentId, 'approved', $userId, 'Approved update.');

    $likePayload = [
        'id' => 'https://remote.example/activities/like-v66g',
        'type' => 'Like',
        'actor' => $remoteActorUri,
        'object' => activitypub_object_url($postId),
    ];
    $announcePayload = [
        'id' => 'https://remote.example/activities/announce-v66g',
        'type' => 'Announce',
        'actor' => $remoteActorUri,
        'object' => activitypub_object_url($postId),
    ];
    if (!federated_interactions_ingest_reaction($inboxId, $likePayload, $remoteActor)
        || !federated_interactions_ingest_reaction($inboxId, $announcePayload, $remoteActor)) {
        $fail('Remote Like or Announce ingestion failed.');
    }
    $counts = federated_interactions_remote_counts($postId);
    if ($counts['likes'] !== 1 || $counts['boosts'] !== 1) $fail('Remote reaction counts failed.');
    $undoLike = [
        'id' => 'https://remote.example/activities/undo-like-v66g',
        'type' => 'Undo',
        'actor' => $remoteActorUri,
        'object' => $likePayload,
    ];
    if (!federated_interactions_undo_reaction($undoLike, $remoteActor)) $fail('Remote reaction Undo failed.');
    if (federated_interactions_remote_counts($postId)['likes'] !== 0) $fail('Undone remote Like remained active.');

    $local = content_interactions_create_comment(
        $userId,
        'blog_post',
        $postId,
        0,
        'Local comment sent to the fediverse.',
        'admin'
    );
    $localCommentId = (int)$local['id'];
    $localMap = federated_interactions_local_map('comment', (string)$localCommentId);
    if (!$localMap || $localMap['status'] !== 'active') $fail('Local comment Create federation map failed.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_outbox_activities WHERE activity_type="Create" AND object_type="Note"')->fetchColumn() !== 1) {
        $fail('Local comment Create outbox activity failed.');
    }
    content_interactions_edit_comment(
        $localCommentId,
        ['id' => $userId, 'role' => 'admin'],
        'Local comment edited for federation.'
    );
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_outbox_activities WHERE activity_type="Update" AND object_type="Note"')->fetchColumn() !== 1) {
        $fail('Local comment Update outbox activity failed.');
    }

    $reaction = content_interactions_toggle_reaction($readerId, 'content', 'blog_post', $postId, 'like');
    if (($reaction['active'] ?? '') !== 'like') $fail('Local reaction activation failed.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_outbox_activities WHERE activity_type="Like"')->fetchColumn() !== 1) {
        $fail('Local Like federation failed.');
    }
    $reaction = content_interactions_toggle_reaction($readerId, 'content', 'blog_post', $postId, 'like');
    if (($reaction['active'] ?? 'x') !== '') $fail('Local reaction removal failed.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_outbox_activities WHERE activity_type="Undo" AND object_type="Reaction"')->fetchColumn() !== 1) {
        $fail('Local reaction Undo federation failed.');
    }

    $followingId = federated_interactions_follow_actor($remoteActorUri, $userId);
    if ($followingId <= 0) $fail('Outbound Follow was not stored.');
    $following = $pdo->query('SELECT * FROM activitypub_following WHERE id=' . $followingId)->fetch();
    if (!$following || $following['status'] !== 'pending') $fail('Outbound Follow state failed.');
    $accept = [
        'id' => 'https://remote.example/activities/accept-v66g',
        'type' => 'Accept',
        'actor' => $remoteActorUri,
        'object' => (string)$following['follow_activity_uri'],
    ];
    if (!federated_interactions_process_follow_response($accept, $remoteActor)) {
        $fail('Outbound Follow Accept processing failed.');
    }
    $followingDocument = federated_interactions_following_document();
    if (($followingDocument['totalItems'] ?? 0) !== 1
        || ($followingDocument['orderedItems'][0] ?? '') !== $remoteActorUri) {
        $fail('Public Following collection failed.');
    }
    federated_interactions_unfollow_actor($followingId, $userId);
    if ((string)$pdo->query('SELECT status FROM activitypub_following WHERE id=' . $followingId)->fetchColumn() !== 'removed') {
        $fail('Outbound Unfollow state failed.');
    }

    $deletePayload = [
        'id' => 'https://remote.example/activities/delete-v66g',
        'type' => 'Delete',
        'actor' => $remoteActorUri,
        'object' => $remoteObjectId,
    ];
    if (!federated_interactions_process_inbound($inboxId, $deletePayload, $remoteActor)) {
        $fail('Remote object Delete was not processed.');
    }
    if ((string)$pdo->query('SELECT status FROM activitypub_remote_comments WHERE id=' . $remoteCommentId)->fetchColumn() !== 'deleted') {
        $fail('Remote comment Delete state failed.');
    }

    $pdo->prepare(
        'INSERT INTO activitypub_remote_comments
            (remote_actor_id,blog_post_id,object_uri,source_activity_uri,in_reply_to_uri,
             source_url,body_text,body_hash,status)
         VALUES
            (:actor_id,:post_id,:object_uri,:activity_uri,:reply_uri,:source_url,
             "Block containment reply",:body_hash,"approved")'
    )->execute([
        'actor_id' => $remoteActorId,
        'post_id' => $postId,
        'object_uri' => 'https://remote.example/notes/block-v66g',
        'activity_uri' => 'https://remote.example/activities/block-v66g',
        'reply_uri' => activitypub_object_url($postId),
        'source_url' => 'https://remote.example/notes/block-v66g',
        'body_hash' => hash('sha256', 'block containment reply'),
    ]);
    $blockCommentId = (int)$pdo->lastInsertId();
    federated_interactions_ingest_reaction($inboxId, [
        'id' => 'https://remote.example/activities/block-like-v66g',
        'type' => 'Like',
        'actor' => $remoteActorUri,
        'object' => activitypub_object_url($postId),
    ], $remoteActor);
    federated_interactions_set_actor_control($remoteActorId, 'blocked', 'Test actor containment.', $userId);
    if ((string)$pdo->query('SELECT status FROM activitypub_remote_actors WHERE id=' . $remoteActorId)->fetchColumn() !== 'blocked') {
        $fail('Remote actor block status failed.');
    }
    if ((string)$pdo->query('SELECT status FROM activitypub_remote_comments WHERE id=' . $blockCommentId)->fetchColumn() !== 'hidden') {
        $fail('Remote actor block did not hide public replies.');
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM activitypub_remote_reactions WHERE remote_actor_id=' . $remoteActorId . ' AND status="active"')->fetchColumn() !== 0) {
        $fail('Remote actor block did not contain reactions.');
    }
    federated_interactions_block_domain('remote.example', 'Test domain block.', $userId);
    if (!federated_interactions_domain_blocked('sub.remote.example')) $fail('Federation subdomain block failed.');
    $blockId = (int)$pdo->query('SELECT id FROM activitypub_domain_blocks WHERE domain_name="remote.example"')->fetchColumn();
    federated_interactions_unblock_domain($blockId);
    if (federated_interactions_domain_blocked('remote.example')) $fail('Federation domain unblock failed.');

    content_interactions_delete_comment($localCommentId, ['id' => $userId, 'role' => 'admin']);
    if ((string)(federated_interactions_local_map('comment', (string)$localCommentId)['status'] ?? '') !== 'deleted') {
        $fail('Local comment Delete/Tombstone mapping failed.');
    }

    echo "Federated Interactions v66G database integration passed.\n";
} finally {
    $cleanup();
}

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8')


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected one anchor, found {count}')
    return content.replace(old, new, 1)


core = read('portal/activitypub.php')
core = replace_once(
    core,
    "    $keyId = activitypub_actor_url() . '#main-key';\n",
    "    $keyId = activitypub_actor_url() . '#main-key-' . substr(hash('sha256', $publicKey), 0, 16);\n",
    'versioned key identifier',
)
core = replace_once(
    core,
    '''    $key = activitypub_active_key(true);
    if (!$identity || !$key) throw new RuntimeException('The ActivityPub actor is not initialized.');
''',
    '''    $key = activitypub_active_key(false);
    if (!$identity || !$key) throw new RuntimeException('Initialize the ActivityPub actor from the administrator workspace first.');
''',
    'no public key generation',
)
write('portal/activitypub.php', core)

http = read('portal/activitypub-http.php')
http = replace_once(
    http,
    '''function activitypub_digest_header(string $body): string
{
    return 'SHA-256=' . base64_encode(hash('sha256', $body, true));
}

function activitypub_header_map''',
    '''function activitypub_digest_header(string $body): string
{
    return 'SHA-256=' . base64_encode(hash('sha256', $body, true));
}

function activitypub_digest_matches(string $header, string $body): bool
{
    $expected = hash('sha256', $body, true);
    foreach (explode(',', $header) as $candidate) {
        $parts = explode('=', trim($candidate), 2);
        if (count($parts) !== 2 || strtolower(trim($parts[0])) !== 'sha-256') continue;
        $decoded = base64_decode(trim($parts[1]), true);
        if (is_string($decoded) && hash_equals($expected, $decoded)) return true;
    }
    return false;
}

function activitypub_header_map''',
    'digest parser',
)
http = replace_once(
    http,
    '''    $key = activitypub_active_key(true);
    if (!$key) throw new RuntimeException('The local ActivityPub signing key is unavailable.');
''',
    '''    $key = activitypub_active_key(false);
    if (!$key) throw new RuntimeException('The local ActivityPub signing key is unavailable. Initialize it from the administrator workspace.');
''',
    'no network-triggered key generation',
)
http = replace_once(
    http,
    '''    $digest = (string)($headers['digest'] ?? '');
    if ($digest === '' || !hash_equals(activitypub_digest_header($body), $digest)) {
        throw new RuntimeException('The ActivityPub request Digest header is invalid.');
    }
''',
    '''    $digest = (string)($headers['digest'] ?? '');
    if ($digest === '' || !activitypub_digest_matches($digest, $body)) {
        throw new RuntimeException('The ActivityPub request Digest header is invalid.');
    }
''',
    'case-insensitive digest verification',
)
http = replace_once(
    http,
    '''    $remote = activitypub_remote_actor($actorUri, true);
    if (!hash_equals((string)$remote['public_key_id'], $keyId)) {
        throw new RuntimeException('The ActivityPub signature key does not belong to the activity actor.');
    }
''',
    '''    $remote = activitypub_remote_actor($actorUri, false);
    if (!hash_equals((string)$remote['public_key_id'], $keyId)) {
        $remote = activitypub_remote_actor($actorUri, true);
    }
    if (!hash_equals((string)$remote['public_key_id'], $keyId)) {
        throw new RuntimeException('The ActivityPub signature key does not belong to the activity actor.');
    }
''',
    'cached actor key refresh',
)
write('portal/activitypub-http.php', http)

service = read('portal/activitypub-service.php')
service = replace_once(
    service,
    '''        } elseif ($activityType === 'Undo') {
            $undo = is_array($object) ? $object : [];
            $undoType = trim((string)($undo['type'] ?? ''));
            $undoActor = activitypub_payload_actor($undo);
            if ($undoType === 'Follow' && activitypub_normalize_url($undoActor) === activitypub_normalize_url($actorUri)) {
                db()->prepare(
                    'UPDATE activitypub_followers
                     SET status="removed",moderated_at=UTC_TIMESTAMP()
                     WHERE remote_actor_id=:actor_id'
                )->execute(['actor_id' => (int)$remote['id']]);
                activitypub_update_inbox_status($inboxId, 'accepted');
            } else {
                activitypub_update_inbox_status($inboxId, 'ignored');
            }
''',
    '''        } elseif ($activityType === 'Undo') {
            $undo = is_array($object) ? $object : [];
            $undoType = trim((string)($undo['type'] ?? ''));
            $undoActor = activitypub_payload_actor($undo);
            $undoId = is_string($object) ? trim($object) : trim((string)($undo['id'] ?? ''));
            $matchesEmbeddedFollow = $undoType === 'Follow'
                && activitypub_normalize_url($undoActor) === activitypub_normalize_url($actorUri);
            $matchesStoredFollow = false;
            if ($undoId !== '') {
                $followCheck = db()->prepare(
                    'SELECT COUNT(*) FROM activitypub_followers
                     WHERE remote_actor_id=:actor_id AND follow_activity_id=:follow_activity_id'
                );
                $followCheck->execute([
                    'actor_id' => (int)$remote['id'],
                    'follow_activity_id' => $undoId,
                ]);
                $matchesStoredFollow = (int)$followCheck->fetchColumn() > 0;
            }
            if ($matchesEmbeddedFollow || $matchesStoredFollow) {
                db()->prepare(
                    'UPDATE activitypub_followers
                     SET status="removed",moderated_at=UTC_TIMESTAMP()
                     WHERE remote_actor_id=:actor_id'
                )->execute(['actor_id' => (int)$remote['id']]);
                activitypub_update_inbox_status($inboxId, 'accepted');
            } else {
                activitypub_update_inbox_status($inboxId, 'ignored');
            }
''',
    'Undo Follow forms',
)
service = replace_once(
    service,
    '''    $rows = db()->query(
        'SELECT id FROM blog_posts
         WHERE status="published"
           AND (published_at IS NULL OR published_at<=UTC_TIMESTAMP())
         ORDER BY COALESCE(published_at,created_at),id LIMIT ' . $limit
    )->fetchAll();
''',
    '''    $rows = db()->query(
        'SELECT post.id FROM blog_posts post
         WHERE post.status="published"
           AND (post.published_at IS NULL OR post.published_at<=UTC_TIMESTAMP())
           AND NOT EXISTS (
                SELECT 1 FROM activitypub_outbox_activities activity
                WHERE activity.blog_post_id=post.id AND activity.activity_type="Create"
           )
         ORDER BY COALESCE(post.published_at,post.created_at),post.id LIMIT ' . $limit
    )->fetchAll();
''',
    'missing-only Blog backfill',
)
write('portal/activitypub-service.php', service)

admin = read('portal/activitypub-admin.php')
admin = replace_once(
    admin,
    '''        if ($enabled) {
            activitypub_secret_key();
        }
        $username = pod_public_username(input('activitypub_username'));
''',
    '''        if ($enabled) {
            activitypub_secret_key();
            activitypub_active_key(true, (int)$user['id']);
        }
        $username = pod_public_username(input('activitypub_username'));
''',
    'key initialization before settings mutation',
)
admin = replace_once(
    admin,
    '''        foreach ($pairs as $key => $value) publishing_save_setting($key, $value);
        if ($enabled) activitypub_active_key(true, (int)$user['id']);
        log_activity''',
    '''        foreach ($pairs as $key => $value) publishing_save_setting($key, $value);
        log_activity''',
    'remove post-mutation key generation',
)
admin = replace_once(
    admin,
    '''<header><div><?php if($follower['avatar_url']):?><img src="<?=e($follower['avatar_url'])?>" alt="" loading="lazy"><?php endif;?><div><h3><?=e($follower['display_name']?:$follower['preferred_username']?:'Remote actor')?></h3><a href="<?=e($follower['profile_url']?:$follower['actor_uri'])?>" target="_blank" rel="noopener noreferrer"><?=e($follower['actor_uri'])?></a></div></div><b><?=e(status_label($follower['status']))?></b></header>
''',
    '''<header><div><div><h3><?=e($follower['display_name']?:$follower['preferred_username']?:'Remote actor')?></h3><a href="<?=e($follower['profile_url']?:$follower['actor_uri'])?>" target="_blank" rel="noopener noreferrer"><?=e($follower['actor_uri'])?></a></div></div><b><?=e(status_label($follower['status']))?></b></header>
''',
    'no remote avatar loading',
)
write('portal/activitypub-admin.php', admin)

publishing = read('portal/publishing-admin.php')
publishing = replace_once(
    publishing,
    '''        log_activity(
            'blog_revision_restored',
            'blog_post',
            $postId
        );
        flash('success', 'Blog revision restored.');
''',
    '''        log_activity(
            'blog_revision_restored',
            'blog_post',
            $postId
        );
        $restoredPost = activitypub_blog_post($postId);
        if ($restoredPost && (string)$restoredPost['status'] === 'published') {
            syndication_queue_websub('update', (int)$user['id'], $postId);
            activitypub_blog_event($postId, 'Update', (int)$user['id']);
        }
        flash('success', 'Blog revision restored.');
''',
    'revision restore federation hook',
)
write('portal/publishing-admin.php', publishing)

cron = read('cron/process-activitypub.php')
cron = replace_once(
    cron,
    '''$limit = max(1, min(50, (int)($argv[1] ?? 20)));
$results = activitypub_process_delivery_queue($limit);
''',
    '''$limit = max(1, min(50, (int)($argv[1] ?? 20)));
$backfilled = activitypub_backfill_published_posts(null, 100);
$results = activitypub_process_delivery_queue($limit);
''',
    'scheduled Blog backfill',
)
cron = replace_once(
    cron,
    '''echo 'Processed ' . count($results) . ' ActivityPub deliveries.' . PHP_EOL;
''',
    '''echo 'Backfilled ' . $backfilled . ' newly public Blog posts.' . PHP_EOL;
echo 'Processed ' . count($results) . ' ActivityPub deliveries.' . PHP_EOL;
''',
    'scheduled Blog backfill receipt',
)
write('cron/process-activitypub.php', cron)

print('ActivityPub v66F security and lifecycle hardening applied.')

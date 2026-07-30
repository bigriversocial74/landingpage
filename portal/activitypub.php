<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-activitypub-v66F */

require_once __DIR__ . '/pod-identity.php';
require_once __DIR__ . '/public-syndication.php';

function activitypub_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    if (!pod_identity_schema_available()) return $available = false;

    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "activitypub_actor_keys","activitypub_remote_actors",
                    "activitypub_followers","activitypub_inbox_activities",
                    "activitypub_outbox_activities","activitypub_deliveries"
               )'
        );
        $available = (int)$statement->fetchColumn() === 6;
    } catch (Throwable) {
        $available = false;
    }
    return $available;
}

function activitypub_require_schema(): void
{
    if (!activitypub_schema_available()) {
        throw new RuntimeException(
            'Import database/activitypub_federation_v66f.sql before using ActivityPub federation.'
        );
    }
}

function activitypub_setting(string $key, string $fallback = ''): string
{
    return trim((string)(setting($key, $fallback) ?? $fallback));
}

function activitypub_settings(): array
{
    $identity = pod_local_identity(false);
    $username = activitypub_setting(
        'activitypub_username',
        (string)($identity['public_username'] ?? 'pod-owner')
    );
    $username = pod_public_username($username);
    $displayName = activitypub_setting(
        'activitypub_display_name',
        (string)($identity['display_name'] ?? setting('site_name', 'Personal POD'))
    );
    $summary = activitypub_setting(
        'activitypub_summary',
        (string)($identity['summary'] ?? '')
    );
    $origin = pod_configured_origin();
    $httpsReady = str_starts_with(strtolower($origin), 'https://');

    return [
        'enabled' => activitypub_setting('activitypub_enabled', '0') === '1'
            && $httpsReady,
        'configured_enabled' => activitypub_setting('activitypub_enabled', '0') === '1',
        'https_ready' => $httpsReady,
        'federate_blog_posts' => activitypub_setting(
            'activitypub_federate_blog_posts',
            '1'
        ) !== '0',
        'manual_follow_approval' => activitypub_setting(
            'activitypub_manual_follow_approval',
            '1'
        ) !== '0',
        'show_followers' => activitypub_setting(
            'activitypub_show_followers',
            '1'
        ) !== '0',
        'username' => $username,
        'display_name' => mb_substr($displayName !== '' ? $displayName : 'Personal POD', 0, 190),
        'summary' => mb_substr($summary, 0, 1200),
        'origin' => $origin,
    ];
}

function activitypub_origin(): string
{
    return (string)activitypub_settings()['origin'];
}

function activitypub_actor_url(): string
{
    return publishing_absolute_url('activitypub-actor.php');
}

function activitypub_inbox_url(): string
{
    return publishing_absolute_url('activitypub-inbox.php');
}

function activitypub_outbox_url(): string
{
    return publishing_absolute_url('activitypub-outbox.php');
}

function activitypub_followers_url(): string
{
    return publishing_absolute_url('activitypub-followers.php');
}

function activitypub_following_url(): string
{
    return publishing_absolute_url('activitypub-following.php');
}

function activitypub_activity_url(string $uuid): string
{
    return publishing_absolute_url('activitypub-activity.php?id=' . rawurlencode($uuid));
}

function activitypub_object_url(int $postId): string
{
    return publishing_absolute_url('activitypub-object.php?id=' . $postId);
}

function activitypub_host(): string
{
    return strtolower((string)parse_url(activitypub_actor_url(), PHP_URL_HOST));
}

function activitypub_account(): string
{
    $settings = activitypub_settings();
    return $settings['username'] . '@' . activitypub_host();
}

function activitypub_context(): array
{
    return [
        'https://www.w3.org/ns/activitystreams',
        'https://w3id.org/security/v1',
        [
            'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
            'discoverable' => 'toot:discoverable',
            'indexable' => 'toot:indexable',
            'toot' => 'http://joinmastodon.org/ns#',
        ],
    ];
}

function activitypub_actor_type(array $identity): string
{
    return match ((string)($identity['identity_type'] ?? 'personal_pod')) {
        'business_pod', 'organization_pod' => 'Organization',
        'group_pod', 'project_pod' => 'Group',
        default => 'Person',
    };
}

function activitypub_active_key(bool $create = false, ?int $actorUserId = null): ?array
{
    if (!activitypub_schema_available()) return null;
    $identity = pod_local_identity($create);
    if (!$identity) return null;

    $statement = db()->prepare(
        'SELECT * FROM activitypub_actor_keys
         WHERE local_identity_id=:identity_id AND status="active"
         ORDER BY created_at DESC,id DESC LIMIT 1'
    );
    $statement->execute(['identity_id' => (int)$identity['id']]);
    $key = $statement->fetch();
    if ($key || !$create) return $key ?: null;

    activitypub_rotate_key((int)($actorUserId ?? 0));
    $statement->execute(['identity_id' => (int)$identity['id']]);
    return $statement->fetch() ?: null;
}

function activitypub_secret_key(): string
{
    $security = nmm_config('security');
    $secret = trim((string)($security['activitypub_secret'] ?? ''));
    if (
        strlen($secret) < 32
        || str_contains($secret, 'replace-with')
        || str_contains($secret, 'change-this')
    ) {
        throw new RuntimeException(
            'Configure security.activitypub_secret with a private value of at least 32 characters.'
        );
    }
    return hash('sha256', 'activitypub-v66f|' . $secret, true);
}

function activitypub_encrypt_private_key(string $privateKey): array
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required for ActivityPub key protection.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $privateKey,
        'aes-256-gcm',
        activitypub_secret_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'activitypub-private-key-v1'
    );
    if (!is_string($ciphertext) || $ciphertext === '' || $tag === '') {
        throw new RuntimeException('The ActivityPub private key could not be encrypted.');
    }
    return [
        'ciphertext' => base64_encode($ciphertext),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
    ];
}

function activitypub_decrypt_private_key(array $record): string
{
    $ciphertext = base64_decode((string)($record['private_key_ciphertext'] ?? ''), true);
    $iv = base64_decode((string)($record['private_key_iv'] ?? ''), true);
    $tag = base64_decode((string)($record['private_key_tag'] ?? ''), true);
    if (!is_string($ciphertext) || !is_string($iv) || !is_string($tag)) return '';
    try {
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            activitypub_secret_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'activitypub-private-key-v1'
        );
    } catch (Throwable) {
        return '';
    }
    return is_string($plaintext) ? $plaintext : '';
}

function activitypub_rotate_key(int $actorUserId): array
{
    activitypub_require_schema();
    $identity = pod_local_identity(true);
    if (!$identity) throw new RuntimeException('The primary POD identity is unavailable.');
    if (!function_exists('openssl_pkey_new')) {
        throw new RuntimeException('OpenSSL key generation is required for ActivityPub.');
    }

    $resource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);
    if ($resource === false) {
        throw new RuntimeException('The ActivityPub RSA key pair could not be generated.');
    }
    $privateKey = '';
    if (!openssl_pkey_export($resource, $privateKey) || $privateKey === '') {
        throw new RuntimeException('The ActivityPub private key could not be exported.');
    }
    $details = openssl_pkey_get_details($resource);
    $publicKey = is_array($details) ? (string)($details['key'] ?? '') : '';
    if ($publicKey === '') {
        throw new RuntimeException('The ActivityPub public key could not be exported.');
    }
    $encrypted = activitypub_encrypt_private_key($privateKey);
    $keyId = activitypub_actor_url() . '#main-key-' . substr(hash('sha256', $publicKey), 0, 16);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE activitypub_actor_keys
             SET status="retired",retired_at=UTC_TIMESTAMP()
             WHERE local_identity_id=:identity_id AND status="active"'
        )->execute(['identity_id' => (int)$identity['id']]);
        $pdo->prepare(
            'INSERT INTO activitypub_actor_keys
                (local_identity_id,key_id,algorithm,public_key_pem,
                 private_key_ciphertext,private_key_iv,private_key_tag,
                 status,created_by_user_id)
             VALUES
                (:identity_id,:key_id,"rsa-sha256",:public_key_pem,
                 :ciphertext,:iv,:tag,"active",:created_by_user_id)'
        )->execute([
            'identity_id' => (int)$identity['id'],
            'key_id' => $keyId,
            'public_key_pem' => $publicKey,
            'ciphertext' => $encrypted['ciphertext'],
            'iv' => $encrypted['iv'],
            'tag' => $encrypted['tag'],
            'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    log_activity('activitypub_key_rotated', 'pod_identity', (int)$identity['id'], [
        'key_id' => $keyId,
        'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
    ]);
    return activitypub_active_key(false) ?? [];
}

function activitypub_actor_document(): array
{
    activitypub_require_schema();
    $settings = activitypub_settings();
    if (!$settings['enabled']) {
        throw new RuntimeException('ActivityPub federation is disabled or the canonical origin is not HTTPS.');
    }
    $identity = pod_local_identity(true);
    $key = activitypub_active_key(false);
    if (!$identity || !$key) throw new RuntimeException('Initialize the ActivityPub actor from the administrator workspace first.');

    $profile = trim((string)($identity['profile_url'] ?? ''));
    if ($profile === '') $profile = publishing_absolute_url('index.php');
    $actor = [
        '@context' => activitypub_context(),
        'id' => activitypub_actor_url(),
        'type' => activitypub_actor_type($identity),
        'preferredUsername' => $settings['username'],
        'name' => $settings['display_name'],
        'summary' => $settings['summary'],
        'url' => $profile,
        'inbox' => activitypub_inbox_url(),
        'outbox' => activitypub_outbox_url(),
        'followers' => activitypub_followers_url(),
        'following' => activitypub_following_url(),
        'manuallyApprovesFollowers' => $settings['manual_follow_approval'],
        'discoverable' => true,
        'indexable' => true,
        'endpoints' => [
            'sharedInbox' => activitypub_inbox_url(),
        ],
        'publicKey' => [
            'id' => (string)$key['key_id'],
            'owner' => activitypub_actor_url(),
            'publicKeyPem' => (string)$key['public_key_pem'],
        ],
    ];
    $avatar = trim((string)($identity['avatar_url'] ?? ''));
    if ($avatar !== '' && syndication_http_url($avatar)) {
        $actor['icon'] = [
            'type' => 'Image',
            'mediaType' => 'image/*',
            'url' => $avatar,
        ];
    }
    return $actor;
}

function activitypub_webfinger_document(string $resource): array
{
    $settings = activitypub_settings();
    if (!$settings['enabled']) throw new RuntimeException('ActivityPub federation is disabled.');
    $resource = strtolower(trim($resource));
    $account = strtolower('acct:' . activitypub_account());
    $actor = strtolower(activitypub_actor_url());
    if (!in_array($resource, [$account, $actor], true)) {
        throw new RuntimeException('The requested ActivityPub account was not found.');
    }
    $profile = (string)(pod_local_identity(true)['profile_url'] ?? publishing_absolute_url('index.php'));
    return [
        'subject' => $account,
        'aliases' => [activitypub_actor_url(), $profile],
        'links' => [
            [
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => activitypub_actor_url(),
            ],
            [
                'rel' => 'http://webfinger.net/rel/profile-page',
                'type' => 'text/html',
                'href' => $profile,
            ],
        ],
    ];
}

function activitypub_nodeinfo_document(): array
{
    $settings = activitypub_settings();
    if (!$settings['enabled']) throw new RuntimeException('ActivityPub federation is disabled.');
    $posts = 0;
    try {
        $posts = (int)db()->query(
            'SELECT COUNT(*) FROM blog_posts
             WHERE status="published"
               AND (published_at IS NULL OR published_at<=UTC_TIMESTAMP())'
        )->fetchColumn();
    } catch (Throwable) {
    }
    return [
        'version' => '2.1',
        'software' => [
            'name' => 'north-mountain-media-pod',
            'version' => '66F',
            'repository' => 'https://github.com/bigriversocial74/landingpage',
        ],
        'protocols' => ['activitypub'],
        'services' => ['inbound' => [], 'outbound' => []],
        'openRegistrations' => false,
        'usage' => [
            'users' => ['total' => 1, 'activeMonth' => 1, 'activeHalfyear' => 1],
            'localPosts' => $posts,
        ],
        'metadata' => [
            'nodeName' => $settings['display_name'],
            'federationDisabledByDefault' => true,
        ],
    ];
}

function activitypub_blog_post(int $postId): ?array
{
    if ($postId <= 0 || !publishing_schema_available()) return null;
    try {
        $statement = db()->prepare(
            'SELECT post.*,user.display_name AS author_name
             FROM blog_posts post
             LEFT JOIN users user ON user.id=post.author_user_id
             WHERE post.id=:id LIMIT 1'
        );
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch();
        return $row ? blog_post_payload($row, blog_post_media($postId)) : null;
    } catch (Throwable) {
        return null;
    }
}

function activitypub_article_object(array $post): array
{
    $published = syndication_iso_date((string)($post['published_at'] ?? ''));
    $updated = syndication_iso_date((string)($post['updated_at'] ?? ''));
    $object = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_object_url((int)$post['id']),
        'type' => 'Article',
        'attributedTo' => activitypub_actor_url(),
        'name' => (string)$post['title'],
        'summary' => (string)($post['excerpt'] ?? ''),
        'content' => (string)($post['body_html'] ?? ''),
        'mediaType' => 'text/html',
        'published' => $published,
        'updated' => $updated,
        'url' => syndication_post_url($post),
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [activitypub_followers_url()],
        'tag' => [],
    ];
    $tags = array_values(array_unique(array_filter(array_merge(
        !empty($post['category']) ? [(string)$post['category']] : [],
        is_array($post['tags'] ?? null) ? $post['tags'] : []
    ))));
    foreach ($tags as $tag) {
        $name = '#' . ltrim(trim((string)$tag), '#');
        if ($name !== '#') {
            $object['tag'][] = ['type' => 'Hashtag', 'name' => $name];
        }
    }
    if (!$object['tag']) unset($object['tag']);
    if (!empty($post['cover']['id'])) {
        $object['attachment'] = [[
            'type' => 'Image',
            'mediaType' => (string)($post['cover']['mime_type'] ?? 'image/jpeg'),
            'url' => publishing_absolute_url('blog-media.php?id=' . (int)$post['cover']['id']),
            'name' => (string)($post['cover']['alt_text'] ?? $post['title']),
        ]];
    }
    return array_filter($object, static fn($value): bool => $value !== null && $value !== '');
}

function activitypub_discovery_links(): string
{
    $settings = activitypub_settings();
    if (!$settings['enabled']) return '';
    return '<link rel="alternate" type="application/activity+json" href="'
        . e(activitypub_actor_url()) . '">';
}

function activitypub_json_response(array $payload, string $contentType = 'application/activity+json'): never
{
    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    echo "\n";
    exit;
}

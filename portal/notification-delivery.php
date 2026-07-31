<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-notification-delivery-v66J */

require_once __DIR__ . '/homeserver-adapter.php';

function notification_delivery_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "notification_delivery_preferences","notification_quiet_hours",
                    "notification_delivery_keys","notification_push_subscriptions",
                    "notification_delivery_queue","notification_delivery_attempts",
                    "notification_digest_batches"
               )'
        );
        return $available = (int)$statement->fetchColumn() === 7;
    } catch (Throwable) {
        return $available = false;
    }
}

function notification_delivery_require_schema(): void
{
    if (!notification_delivery_schema_available()) {
        throw new RuntimeException('Import database/notification_delivery_v66j.sql before using Notification Delivery.');
    }
}

function notification_delivery_setting(string $key, string $default = ''): string
{
    try {
        if (function_exists('nmm_site_setting')) {
            $value = nmm_site_setting($key);
            if ($value !== null && trim((string)$value) !== '') return trim((string)$value);
        }
        if (function_exists('setting')) {
            $value = setting($key, null);
            if ($value !== null && trim((string)$value) !== '') return trim((string)$value);
        }
    } catch (Throwable) {
    }
    return $default;
}

function notification_delivery_settings(): array
{
    return [
        'enabled' => notification_delivery_setting('notification_delivery_enabled', '0') === '1',
        'email_enabled' => notification_delivery_setting('notification_email_enabled', '0') === '1',
        'push_enabled' => notification_delivery_setting('notification_push_enabled', '0') === '1',
        'homeserver_enabled' => notification_delivery_setting('notification_homeserver_enabled', '0') === '1',
        'email_from' => strtolower(notification_delivery_setting('notification_email_from', '')),
        'email_from_name' => mb_substr(notification_delivery_setting('notification_email_from_name', 'North Mountain Media'), 0, 120),
        'vapid_subject' => notification_delivery_setting('notification_vapid_subject', ''),
        'worker_batch_size' => max(1, min(100, (int)notification_delivery_setting('notification_worker_batch_size', '25'))),
        'max_attempts' => max(1, min(10, (int)notification_delivery_setting('notification_max_attempts', '5'))),
        'digest_retention_days' => max(7, min(730, (int)notification_delivery_setting('notification_digest_retention_days', '90'))),
        'delivery_retention_days' => max(30, min(1095, (int)notification_delivery_setting('notification_delivery_retention_days', '180'))),
    ];
}

function notification_delivery_secret(): string
{
    $security = function_exists('nmm_config') ? nmm_config('security') : [];
    $secret = trim((string)($security['notification_delivery_secret'] ?? ''));
    return strlen($secret) >= 32 ? $secret : '';
}

function notification_delivery_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function notification_delivery_b64url_decode(string $value): string
{
    $value = strtr(trim($value), '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding) $value .= str_repeat('=', 4 - $padding);
    $decoded = base64_decode($value, true);
    return is_string($decoded) ? $decoded : '';
}

function notification_delivery_encrypt(array $payload): array
{
    $secret = notification_delivery_secret();
    if ($secret === '') throw new RuntimeException('Configure security.notification_delivery_secret with at least 32 private characters.');
    $plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $key = hash('sha256', 'notification-delivery-v66j|' . $secret, true);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'notification-delivery-v66j');
    if (!is_string($ciphertext) || strlen($tag) !== 16) throw new RuntimeException('Notification delivery encryption failed.');
    return [
        'ciphertext' => base64_encode($ciphertext),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
    ];
}

function notification_delivery_decrypt(string $ciphertext, string $iv, string $tag): array
{
    $secret = notification_delivery_secret();
    if ($secret === '') return [];
    $decodedCipher = base64_decode($ciphertext, true);
    $decodedIv = base64_decode($iv, true);
    $decodedTag = base64_decode($tag, true);
    if (!is_string($decodedCipher) || !is_string($decodedIv) || !is_string($decodedTag)) return [];
    $key = hash('sha256', 'notification-delivery-v66j|' . $secret, true);
    $plaintext = openssl_decrypt($decodedCipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $decodedIv, $decodedTag, 'notification-delivery-v66j');
    if (!is_string($plaintext)) return [];
    try {
        $payload = json_decode($plaintext, true, 32, JSON_THROW_ON_ERROR);
        return is_array($payload) ? $payload : [];
    } catch (JsonException) {
        return [];
    }
}

function notification_delivery_event_catalog(): array
{
    return [
        'system' => ['label' => 'System notices', 'email' => 'off', 'push' => false, 'homeserver' => false, 'minimum' => 'high'],
        'call' => ['label' => 'Calls', 'email' => 'off', 'push' => true, 'homeserver' => true, 'minimum' => 'normal'],
        'voicemail' => ['label' => 'Voicemail', 'email' => 'immediate', 'push' => true, 'homeserver' => true, 'minimum' => 'normal'],
        'message' => ['label' => 'POD and client messages', 'email' => 'immediate', 'push' => true, 'homeserver' => true, 'minimum' => 'normal'],
        'federated_message_request' => ['label' => 'Federated message requests', 'email' => 'immediate', 'push' => true, 'homeserver' => true, 'minimum' => 'normal'],
        'federated_message' => ['label' => 'Federated messages', 'email' => 'immediate', 'push' => true, 'homeserver' => true, 'minimum' => 'normal'],
        'social' => ['label' => 'Mentions, follows, reactions, and boosts', 'email' => 'digest', 'push' => false, 'homeserver' => false, 'minimum' => 'normal'],
        'moderation' => ['label' => 'Moderation and reports', 'email' => 'immediate', 'push' => true, 'homeserver' => true, 'minimum' => 'high'],
        'inquiry' => ['label' => 'Website inquiries and CRM activity', 'email' => 'immediate', 'push' => true, 'homeserver' => true, 'minimum' => 'normal'],
        'project' => ['label' => 'Projects and client updates', 'email' => 'digest', 'push' => false, 'homeserver' => false, 'minimum' => 'normal'],
        'delivery_failure' => ['label' => 'Delivery and federation failures', 'email' => 'off', 'push' => true, 'homeserver' => true, 'minimum' => 'high'],
        'homeserver_issue' => ['label' => 'HomeServer connection and authorization', 'email' => 'off', 'push' => true, 'homeserver' => false, 'minimum' => 'high'],
    ];
}

function notification_delivery_event_key(array $notification): string
{
    $entity = strtolower(trim((string)($notification['entity_type'] ?? '')));
    $title = strtolower(trim((string)($notification['title'] ?? '')));
    $category = strtolower(trim((string)($notification['category'] ?? 'system')));
    if (str_contains($entity, 'activitypub_message')) {
        return str_contains($title, 'request') ? 'federated_message_request' : 'federated_message';
    }
    if (str_contains($entity, 'delivery') || str_contains($entity, 'websub')) return 'delivery_failure';
    if (str_contains($entity, 'homeserver')) return 'homeserver_issue';
    if (str_contains($entity, 'comment_report') || str_contains($entity, 'moderation')) return 'moderation';
    if (str_contains($entity, 'comment') || str_contains($entity, 'reaction') || str_contains($entity, 'follow')) return 'social';
    if (str_contains($entity, 'voicemail')) return 'voicemail';
    if (str_contains($entity, 'call')) return 'call';
    if (str_contains($entity, 'lead') || str_contains($entity, 'crm') || str_contains($entity, 'inquiry')) return 'inquiry';
    if (str_contains($entity, 'project')) return 'project';
    return match ($category) {
        'call' => 'call',
        'message' => 'message',
        'contact' => 'inquiry',
        'project' => 'project',
        default => 'system',
    };
}

function notification_delivery_priority_rank(string $priority): int
{
    return match ($priority) {
        'urgent' => 4,
        'high' => 3,
        'normal' => 2,
        default => 1,
    };
}

function notification_delivery_default_preference(int $userId, string $eventKey): array
{
    $catalog = notification_delivery_event_catalog();
    $default = $catalog[$eventKey] ?? $catalog['system'];
    return [
        'user_id' => $userId,
        'event_key' => $eventKey,
        'configured' => 0,
        'in_app_enabled' => 1,
        'email_mode' => $default['email'],
        'push_enabled' => $default['push'] ? 1 : 0,
        'homeserver_enabled' => $default['homeserver'] ? 1 : 0,
        'include_content_email' => 0,
        'include_content_push' => 0,
        'include_content_homeserver' => 0,
        'minimum_priority' => $default['minimum'],
        'digest_frequency' => 'daily',
    ];
}

function notification_delivery_preference(int $userId, string $eventKey): array
{
    $default = notification_delivery_default_preference($userId, $eventKey);
    if (!notification_delivery_schema_available() || $userId <= 0) return $default;
    $statement = db()->prepare(
        'SELECT * FROM notification_delivery_preferences
         WHERE user_id=:user_id AND event_key=:event_key LIMIT 1'
    );
    $statement->execute(['user_id' => $userId, 'event_key' => $eventKey]);
    $row = $statement->fetch();
    return $row ? array_replace($default, $row, ['configured' => 1]) : $default;
}

function notification_delivery_quiet_hours(int $userId): array
{
    $fallback = [
        'user_id' => $userId,
        'enabled' => 0,
        'timezone_name' => (string)(nmm_config('app')['timezone'] ?? 'America/Phoenix'),
        'start_time' => '21:00:00',
        'end_time' => '07:00:00',
        'weekday_mask' => 127,
        'allow_high_priority' => 0,
        'allow_urgent_priority' => 1,
        'digest_local_time' => '08:00:00',
    ];
    if (!notification_delivery_schema_available() || $userId <= 0) return $fallback;
    $statement = db()->prepare('SELECT * FROM notification_quiet_hours WHERE user_id=:user_id LIMIT 1');
    $statement->execute(['user_id' => $userId]);
    $row = $statement->fetch();
    return $row ? array_replace($fallback, $row) : $fallback;
}

function notification_delivery_timezone(string $name): DateTimeZone
{
    try {
        return new DateTimeZone($name);
    } catch (Throwable) {
        return new DateTimeZone('UTC');
    }
}

function notification_delivery_quiet_release_at(int $userId, string $priority, ?DateTimeImmutable $nowUtc = null): DateTimeImmutable
{
    $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $quiet = notification_delivery_quiet_hours($userId);
    if (empty($quiet['enabled'])) return $nowUtc;
    if ($priority === 'urgent' && !empty($quiet['allow_urgent_priority'])) return $nowUtc;
    if ($priority === 'high' && !empty($quiet['allow_high_priority'])) return $nowUtc;
    $zone = notification_delivery_timezone((string)$quiet['timezone_name']);
    $local = $nowUtc->setTimezone($zone);
    $start = substr((string)$quiet['start_time'], 0, 8);
    $end = substr((string)$quiet['end_time'], 0, 8);
    $time = $local->format('H:i:s');
    $mask = max(0, min(127, (int)$quiet['weekday_mask']));
    $day = (int)$local->format('N');
    $todayEnabled = ($mask & (1 << ($day - 1))) !== 0;
    $crosses = $start >= $end;
    $inQuiet = false;
    $release = $local;
    if (!$crosses) {
        $inQuiet = $todayEnabled && $time >= $start && $time < $end;
        if ($inQuiet) $release = new DateTimeImmutable($local->format('Y-m-d') . ' ' . $end, $zone);
    } else {
        if ($time >= $start && $todayEnabled) {
            $inQuiet = true;
            $release = new DateTimeImmutable($local->modify('+1 day')->format('Y-m-d') . ' ' . $end, $zone);
        } elseif ($time < $end) {
            $previousDay = $day === 1 ? 7 : $day - 1;
            if (($mask & (1 << ($previousDay - 1))) !== 0) {
                $inQuiet = true;
                $release = new DateTimeImmutable($local->format('Y-m-d') . ' ' . $end, $zone);
            }
        }
    }
    return $inQuiet ? $release->setTimezone(new DateTimeZone('UTC')) : $nowUtc;
}

function notification_delivery_next_digest_at(int $userId, string $frequency, ?DateTimeImmutable $nowUtc = null): DateTimeImmutable
{
    $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $quiet = notification_delivery_quiet_hours($userId);
    $zone = notification_delivery_timezone((string)$quiet['timezone_name']);
    $local = $nowUtc->setTimezone($zone);
    $time = substr((string)$quiet['digest_local_time'], 0, 8);
    if ($frequency === 'hourly') {
        return $local->modify('+1 hour')->setTime((int)$local->modify('+1 hour')->format('H'), 0)->setTimezone(new DateTimeZone('UTC'));
    }
    if ($frequency === 'weekly') {
        $candidate = new DateTimeImmutable($local->modify('next monday')->format('Y-m-d') . ' ' . $time, $zone);
        return $candidate->setTimezone(new DateTimeZone('UTC'));
    }
    $candidate = new DateTimeImmutable($local->format('Y-m-d') . ' ' . $time, $zone);
    if ($candidate <= $local) $candidate = $candidate->modify('+1 day');
    return $candidate->setTimezone(new DateTimeZone('UTC'));
}

function notification_delivery_safe_link(?string $link): string
{
    $link = trim((string)$link);
    if ($link === '' || preg_match('#^https?://#i', $link)) return app_url('portal/admin.php?view=notifications');
    return app_url(ltrim($link, '/'));
}

function notification_delivery_active_push_count(int $userId): int
{
    if (!notification_delivery_schema_available()) return 0;
    $statement = db()->prepare(
        'SELECT COUNT(*) FROM notification_push_subscriptions
         WHERE user_id=:user_id AND status="active"
           AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())'
    );
    $statement->execute(['user_id' => $userId]);
    return (int)$statement->fetchColumn();
}

function notification_delivery_queue_insert(array $notification, string $eventKey, string $channel, bool $includeContent, DateTimeImmutable $availableAt, string $frequency = 'daily'): int
{
    $recipient = (int)$notification['recipient_user_id'];
    $bucket = gmdate('Y-m-d H:i', intdiv(time(), 900) * 900);
    $dedupe = hash('sha256', implode('|', [
        $eventKey,
        (string)($notification['entity_type'] ?? ''),
        (string)($notification['entity_id'] ?? ''),
        (string)$notification['title'],
        hash('sha256', (string)($notification['body'] ?? '')),
        $bucket,
    ]));
    $payload = [
        'notification_id' => (int)$notification['id'],
        'event_key' => $eventKey,
        'title' => mb_substr((string)$notification['title'], 0, 190),
        'body' => $includeContent ? mb_substr((string)($notification['body'] ?? ''), 0, 2000) : '',
        'content_included' => $includeContent,
        'link_url' => notification_delivery_safe_link((string)($notification['link_url'] ?? '')),
        'entity_type' => mb_substr((string)($notification['entity_type'] ?? ''), 0, 80),
        'entity_id' => (int)($notification['entity_id'] ?? 0),
        'priority' => (string)$notification['priority'],
        'digest_frequency' => $frequency,
        'created_at' => (string)$notification['created_at'],
    ];
    $settings = notification_delivery_settings();
    db()->prepare(
        'INSERT IGNORE INTO notification_delivery_queue
            (notification_id,recipient_user_id,event_key,channel,priority,dedupe_key,
             payload_json,include_content,status,available_at,max_attempts)
         VALUES
            (:notification_id,:recipient_user_id,:event_key,:channel,:priority,:dedupe_key,
             :payload_json,:include_content,"pending",:available_at,:max_attempts)'
    )->execute([
        'notification_id' => (int)$notification['id'],
        'recipient_user_id' => $recipient,
        'event_key' => $eventKey,
        'channel' => $channel,
        'priority' => (string)$notification['priority'],
        'dedupe_key' => $dedupe,
        'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'include_content' => $includeContent ? 1 : 0,
        'available_at' => $availableAt->format('Y-m-d H:i:s'),
        'max_attempts' => $settings['max_attempts'],
    ]);
    return db()->lastInsertId() !== '0' ? (int)db()->lastInsertId() : 0;
}

function notification_delivery_enqueue_notification(int $notificationId): int
{
    $settings = notification_delivery_settings();
    if (!$settings['enabled'] || !notification_delivery_schema_available() || $notificationId <= 0) return 0;
    $statement = db()->prepare(
        'SELECT notification.*,user.email,user.status AS user_status
         FROM portal_notifications notification
         JOIN users user ON user.id=notification.recipient_user_id
         WHERE notification.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $notificationId]);
    $notification = $statement->fetch();
    if (!$notification || (string)$notification['user_status'] !== 'active') return 0;
    $eventKey = notification_delivery_event_key($notification);
    $preference = notification_delivery_preference((int)$notification['recipient_user_id'], $eventKey);
    if (empty($preference['configured'])) return 0;
    if (notification_delivery_priority_rank((string)$notification['priority']) < notification_delivery_priority_rank((string)$preference['minimum_priority'])) return 0;
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $available = notification_delivery_quiet_release_at((int)$notification['recipient_user_id'], (string)$notification['priority'], $now);
    $queued = 0;
    if ($settings['email_enabled'] && filter_var((string)$notification['email'], FILTER_VALIDATE_EMAIL)) {
        if ((string)$preference['email_mode'] === 'immediate') {
            $queued += notification_delivery_queue_insert($notification, $eventKey, 'email', !empty($preference['include_content_email']), $available) > 0 ? 1 : 0;
        } elseif ((string)$preference['email_mode'] === 'digest') {
            $frequency = (string)$preference['digest_frequency'];
            $digestAt = notification_delivery_next_digest_at((int)$notification['recipient_user_id'], $frequency, $now);
            $queued += notification_delivery_queue_insert($notification, $eventKey, 'digest', !empty($preference['include_content_email']), $digestAt, $frequency) > 0 ? 1 : 0;
        }
    }
    if ($settings['push_enabled'] && !empty($preference['push_enabled']) && notification_delivery_active_push_count((int)$notification['recipient_user_id']) > 0) {
        $queued += notification_delivery_queue_insert($notification, $eventKey, 'push', !empty($preference['include_content_push']), $available) > 0 ? 1 : 0;
    }
    if ($settings['homeserver_enabled'] && !empty($preference['homeserver_enabled'])) {
        $queued += notification_delivery_queue_insert($notification, $eventKey, 'homeserver', !empty($preference['include_content_homeserver']), $available) > 0 ? 1 : 0;
    }
    return $queued;
}

function notification_delivery_initialize_vapid(int $userId): array
{
    notification_delivery_require_schema();
    if (notification_delivery_secret() === '') throw new RuntimeException('Configure security.notification_delivery_secret before initializing Web Push.');
    $existing = notification_delivery_active_vapid_key();
    if ($existing) return $existing;
    $resource = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$resource) throw new RuntimeException('OpenSSL could not create the Web Push signing key.');
    $privatePem = '';
    if (!openssl_pkey_export($resource, $privatePem)) throw new RuntimeException('The Web Push private key could not be exported.');
    $details = openssl_pkey_get_details($resource);
    $x = $details['ec']['x'] ?? null;
    $y = $details['ec']['y'] ?? null;
    if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) throw new RuntimeException('The Web Push public key is invalid.');
    $public = notification_delivery_b64url_encode("\x04" . $x . $y);
    $encrypted = notification_delivery_encrypt(['private_key_pem' => $privatePem]);
    $version = (int)(db()->query('SELECT COALESCE(MAX(key_version),0)+1 FROM notification_delivery_keys WHERE key_type="vapid"')->fetchColumn() ?: 1);
    db()->prepare(
        'INSERT INTO notification_delivery_keys
            (key_type,key_version,public_key,private_key_ciphertext,private_key_iv,private_key_tag,status,created_by)
         VALUES ("vapid",:version,:public_key,:ciphertext,:iv,:tag,"active",:created_by)'
    )->execute([
        'version' => $version,
        'public_key' => $public,
        'ciphertext' => $encrypted['ciphertext'],
        'iv' => $encrypted['iv'],
        'tag' => $encrypted['tag'],
        'created_by' => $userId > 0 ? $userId : null,
    ]);
    return notification_delivery_active_vapid_key() ?? [];
}

function notification_delivery_active_vapid_key(): ?array
{
    if (!notification_delivery_schema_available()) return null;
    $row = db()->query(
        'SELECT * FROM notification_delivery_keys
         WHERE key_type="vapid" AND status="active"
         ORDER BY key_version DESC,id DESC LIMIT 1'
    )->fetch();
    return $row ?: null;
}

function notification_delivery_vapid_private_key(array $key): string
{
    $payload = notification_delivery_decrypt(
        (string)$key['private_key_ciphertext'],
        (string)$key['private_key_iv'],
        (string)$key['private_key_tag']
    );
    return trim((string)($payload['private_key_pem'] ?? ''));
}

function notification_delivery_validate_subscription(array $subscription): array
{
    $endpoint = trim((string)($subscription['endpoint'] ?? ''));
    $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
    $p256dh = trim((string)($keys['p256dh'] ?? ''));
    $auth = trim((string)($keys['auth'] ?? ''));
    if (!notification_delivery_https_public_url($endpoint)) throw new RuntimeException('The push endpoint must use public HTTPS on port 443.');
    $public = notification_delivery_b64url_decode($p256dh);
    $authBytes = notification_delivery_b64url_decode($auth);
    if (strlen($public) !== 65 || $public[0] !== "\x04" || strlen($authBytes) < 16) throw new RuntimeException('The browser push keys are invalid.');
    $expiration = $subscription['expirationTime'] ?? null;
    $expiresAt = null;
    if (is_numeric($expiration) && (float)$expiration > 0) $expiresAt = gmdate('Y-m-d H:i:s', (int)floor((float)$expiration / 1000));
    return [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => $p256dh, 'auth' => $auth],
        'expires_at' => $expiresAt,
    ];
}

function notification_delivery_register_subscription(int $userId, array $subscription, string $userAgent = ''): string
{
    notification_delivery_require_schema();
    if ($userId <= 0) throw new RuntimeException('A user is required.');
    $key = notification_delivery_active_vapid_key();
    if (!$key) throw new RuntimeException('Initialize the Web Push key before subscribing this browser.');
    $normalized = notification_delivery_validate_subscription($subscription);
    $encrypted = notification_delivery_encrypt($normalized);
    $uuid = function_exists('pod_uuid_v4') ? pod_uuid_v4() : sprintf('%s-%s-4%s-%s%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), substr(bin2hex(random_bytes(2)), 1), dechex(random_int(8, 11)), substr(bin2hex(random_bytes(2)), 1), bin2hex(random_bytes(6)));
    $hash = hash('sha256', $normalized['endpoint']);
    db()->prepare(
        'INSERT INTO notification_push_subscriptions
            (subscription_uuid,user_id,endpoint_hash,subscription_ciphertext,
             subscription_iv,subscription_tag,vapid_key_version,user_agent_hash,status,expires_at)
         VALUES
            (:uuid,:user_id,:endpoint_hash,:ciphertext,:iv,:tag,:version,:agent_hash,"active",:expires_at)
         ON DUPLICATE KEY UPDATE
            subscription_ciphertext=VALUES(subscription_ciphertext),subscription_iv=VALUES(subscription_iv),
            subscription_tag=VALUES(subscription_tag),vapid_key_version=VALUES(vapid_key_version),
            user_agent_hash=VALUES(user_agent_hash),status="active",failure_count=0,
            expires_at=VALUES(expires_at),updated_at=UTC_TIMESTAMP()'
    )->execute([
        'uuid' => $uuid,
        'user_id' => $userId,
        'endpoint_hash' => $hash,
        'ciphertext' => $encrypted['ciphertext'],
        'iv' => $encrypted['iv'],
        'tag' => $encrypted['tag'],
        'version' => (int)$key['key_version'],
        'agent_hash' => $userAgent !== '' ? hash('sha256', mb_substr($userAgent, 0, 500)) : null,
        'expires_at' => $normalized['expires_at'],
    ]);
    return $uuid;
}

function notification_delivery_revoke_subscription(int $userId, string $uuid = '', string $endpoint = ''): int
{
    if (!notification_delivery_schema_available() || $userId <= 0) return 0;
    if ($uuid !== '') {
        $statement = db()->prepare('UPDATE notification_push_subscriptions SET status="revoked" WHERE user_id=:user_id AND subscription_uuid=:uuid');
        $statement->execute(['user_id' => $userId, 'uuid' => $uuid]);
        return $statement->rowCount();
    }
    if ($endpoint !== '') {
        $statement = db()->prepare('UPDATE notification_push_subscriptions SET status="revoked" WHERE user_id=:user_id AND endpoint_hash=:hash');
        $statement->execute(['user_id' => $userId, 'hash' => hash('sha256', $endpoint)]);
        return $statement->rowCount();
    }
    return 0;
}

function notification_delivery_https_public_url(string $url): bool
{
    try {
        notification_delivery_public_resolution($url);
        return true;
    } catch (Throwable) {
        return false;
    }
}

function notification_delivery_public_resolution(string $url): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
        throw new RuntimeException('The push endpoint must use HTTPS.');
    }
    if (isset($parts['user']) || isset($parts['pass']) || (int)($parts['port'] ?? 443) !== 443) {
        throw new RuntimeException('The push endpoint must use public HTTPS on port 443 without credentials.');
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
        throw new RuntimeException('The push endpoint host is not public.');
    }
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (!is_array($records) || !$records) {
        throw new RuntimeException('The push endpoint did not resolve.');
    }
    $addresses = [];
    foreach ($records as $record) {
        $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('The push endpoint resolved to a private or reserved address.');
        }
        $addresses[] = $ip;
    }
    return ['host' => $host, 'port' => 443, 'addresses' => array_values(array_unique($addresses))];
}

function notification_delivery_curl_resolve(array $resolution): array
{
    $values = [];
    foreach ($resolution['addresses'] as $ip) {
        $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
        $values[] = $resolution['host'] . ':443:' . $address;
    }
    return $values;
}

function notification_delivery_hkdf_extract(string $salt, string $ikm): string
{
    return hash_hmac('sha256', $ikm, $salt, true);
}

function notification_delivery_hkdf_expand(string $prk, string $info, int $length): string
{
    $result = '';
    $previous = '';
    for ($counter = 1; strlen($result) < $length; $counter++) {
        $previous = hash_hmac('sha256', $previous . $info . chr($counter), $prk, true);
        $result .= $previous;
    }
    return substr($result, 0, $length);
}

function notification_delivery_public_key_pem(string $raw): string
{
    if (strlen($raw) !== 65 || $raw[0] !== "\x04") return '';
    $der = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200') . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function notification_delivery_der_length(string $der, int &$offset): int
{
    $length = ord($der[$offset++]);
    if (($length & 0x80) === 0) return $length;
    $count = $length & 0x7f;
    $length = 0;
    for ($i = 0; $i < $count; $i++) $length = ($length << 8) | ord($der[$offset++]);
    return $length;
}

function notification_delivery_es256_jose(string $der): string
{
    $offset = 0;
    if ($der === '' || ord($der[$offset++]) !== 0x30) throw new RuntimeException('The VAPID signature is invalid.');
    notification_delivery_der_length($der, $offset);
    if (ord($der[$offset++]) !== 0x02) throw new RuntimeException('The VAPID signature is invalid.');
    $rLength = notification_delivery_der_length($der, $offset);
    $r = substr($der, $offset, $rLength);
    $offset += $rLength;
    if (ord($der[$offset++]) !== 0x02) throw new RuntimeException('The VAPID signature is invalid.');
    $sLength = notification_delivery_der_length($der, $offset);
    $s = substr($der, $offset, $sLength);
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return substr($r, -32) . substr($s, -32);
}

function notification_delivery_vapid_jwt(string $endpoint, array $key, string $privatePem): string
{
    $parts = parse_url($endpoint);
    $audience = strtolower((string)$parts['scheme']) . '://' . strtolower((string)$parts['host']);
    $subject = notification_delivery_settings()['vapid_subject'];
    if (!preg_match('#^(mailto:|https://)#i', $subject)) $subject = 'mailto:' . (filter_var(notification_delivery_settings()['email_from'], FILTER_VALIDATE_EMAIL) ? notification_delivery_settings()['email_from'] : 'admin@localhost.invalid');
    $header = notification_delivery_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
    $claims = notification_delivery_b64url_encode(json_encode(['aud' => $audience, 'exp' => time() + 12 * 60 * 60, 'sub' => $subject], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $signing = $header . '.' . $claims;
    $signature = '';
    if (!openssl_sign($signing, $signature, $privatePem, OPENSSL_ALGO_SHA256)) throw new RuntimeException('The VAPID token could not be signed.');
    return $signing . '.' . notification_delivery_b64url_encode(notification_delivery_es256_jose($signature));
}

function notification_delivery_encrypt_push_payload(string $payload, string $clientPublicB64, string $authB64): array
{
    $clientRaw = notification_delivery_b64url_decode($clientPublicB64);
    $auth = notification_delivery_b64url_decode($authB64);
    $clientPem = notification_delivery_public_key_pem($clientRaw);
    $clientKey = $clientPem !== '' ? openssl_pkey_get_public($clientPem) : false;
    $serverKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$clientKey || !$serverKey) throw new RuntimeException('The Web Push encryption keys are invalid.');
    $secret = openssl_pkey_derive($clientKey, $serverKey, 32);
    $details = openssl_pkey_get_details($serverKey);
    $x = $details['ec']['x'] ?? null;
    $y = $details['ec']['y'] ?? null;
    if (!is_string($secret) || !is_string($x) || !is_string($y)) throw new RuntimeException('The Web Push shared secret could not be derived.');
    $serverRaw = "\x04" . $x . $y;
    $prkKey = notification_delivery_hkdf_extract($auth, $secret);
    $ikm = notification_delivery_hkdf_expand($prkKey, "WebPush: info\x00" . $clientRaw . $serverRaw, 32);
    $salt = random_bytes(16);
    $prk = notification_delivery_hkdf_extract($salt, $ikm);
    $cek = notification_delivery_hkdf_expand($prk, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = notification_delivery_hkdf_expand($prk, "Content-Encoding: nonce\x00", 12);
    $tag = '';
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '');
    if (!is_string($cipher) || strlen($tag) !== 16) throw new RuntimeException('The Web Push payload could not be encrypted.');
    return ['body' => $salt . pack('N', 4096) . chr(65) . $serverRaw . $cipher . $tag, 'server_public' => $serverRaw];
}

function notification_delivery_send_web_push(array $subscription, array $payload, array $vapidKey): array
{
    if (!function_exists('curl_init')) return ['ok' => false, 'permanent' => false, 'code' => 'curl_missing', 'message' => 'The cURL extension is unavailable.'];
    $endpoint = (string)$subscription['endpoint'];
    $privatePem = notification_delivery_vapid_private_key($vapidKey);
    if ($privatePem === '') return ['ok' => false, 'permanent' => true, 'code' => 'vapid_key_unavailable', 'message' => 'The Web Push private key is unavailable.'];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($json) > 3500) $json = json_encode(['title' => mb_substr((string)($payload['title'] ?? 'Notification'), 0, 100), 'body' => '', 'url' => (string)($payload['url'] ?? app_url('portal/admin.php?view=notifications'))], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $encrypted = notification_delivery_encrypt_push_payload($json, (string)$subscription['keys']['p256dh'], (string)$subscription['keys']['auth']);
    $jwt = notification_delivery_vapid_jwt($endpoint, $vapidKey, $privatePem);
    $resolution = notification_delivery_public_resolution($endpoint);
    $handle = curl_init($endpoint);
    if (!$handle) return ['ok' => false, 'permanent' => false, 'code' => 'curl_init_failed', 'message' => 'The push endpoint could not be opened.'];
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encrypted['body'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_RESOLVE => notification_delivery_curl_resolve($resolution),
        CURLOPT_PROXY => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 3600',
            'Urgency: normal',
            'Authorization: vapid t=' . $jwt . ', k=' . (string)$vapidKey['public_key'],
            'Content-Length: ' . strlen($encrypted['body']),
        ],
    ]);
    $response = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($response === false) return ['ok' => false, 'permanent' => false, 'code' => 'push_transport_failed', 'message' => $error ?: 'The push transport failed.'];
    if ($status >= 200 && $status < 300) return ['ok' => true, 'status' => $status, 'reference' => hash('sha256', $endpoint . '|' . microtime(true))];
    return [
        'ok' => false,
        'permanent' => in_array($status, [404, 410], true),
        'status' => $status,
        'code' => 'push_http_' . $status,
        'message' => 'The push endpoint returned HTTP ' . $status . '.',
    ];
}

function notification_delivery_claim(int $limit): array
{
    notification_delivery_require_schema();
    $limit = max(1, min(100, $limit));
    db()->exec(
        'UPDATE notification_delivery_attempts attempt
         JOIN notification_delivery_queue queue ON queue.id=attempt.queue_id
         SET attempt.status="permanent_failure",attempt.error_code="lease_expired",
             attempt.error_message="The delivery worker lease expired at the attempt limit.",
             attempt.completed_at=UTC_TIMESTAMP()
         WHERE queue.status="leased" AND queue.leased_until<UTC_TIMESTAMP()
           AND queue.attempt_count>=queue.max_attempts AND attempt.status="started"'
    );
    db()->exec(
        'UPDATE notification_delivery_queue
         SET status="failed",lease_token=NULL,leased_until=NULL,
             last_error_code="lease_expired",
             last_error_message="The delivery worker lease expired at the attempt limit."
         WHERE status="leased" AND leased_until<UTC_TIMESTAMP()
           AND attempt_count>=max_attempts'
    );
    db()->exec(
        'UPDATE notification_delivery_queue
         SET status="pending",lease_token=NULL,leased_until=NULL
         WHERE status="leased" AND leased_until<UTC_TIMESTAMP()
           AND attempt_count<max_attempts'
    );
    $token = hash('sha256', random_bytes(32));
    db()->beginTransaction();
    try {
        $ids = db()->query(
            'SELECT id FROM notification_delivery_queue
             WHERE status="pending" AND available_at<=UTC_TIMESTAMP()
               AND attempt_count<max_attempts
             ORDER BY FIELD(priority,"urgent","high","normal","low"),available_at,id
             LIMIT ' . $limit . ' FOR UPDATE'
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $statement = db()->prepare(
                'UPDATE notification_delivery_queue
                 SET status="leased",lease_token=?,leased_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)
                 WHERE id IN (' . $placeholders . ')'
            );
            $statement->execute(array_merge([$token], array_map('intval', $ids)));
        }
        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        throw $exception;
    }
    if (!$ids) return [];
    $statement = db()->prepare(
        'SELECT queue.*,user.email,user.display_name,user.status AS user_status
         FROM notification_delivery_queue queue
         JOIN users user ON user.id=queue.recipient_user_id
         WHERE queue.lease_token=:token ORDER BY queue.id'
    );
    $statement->execute(['token' => $token]);
    return $statement->fetchAll();
}

function notification_delivery_email_headers(): string
{
    $settings = notification_delivery_settings();
    $from = filter_var($settings['email_from'], FILTER_VALIDATE_EMAIL) ? $settings['email_from'] : '';
    if ($from === '') throw new RuntimeException('Configure a valid notification email sender address.');
    $name = preg_replace('/[\r\n]+/', ' ', $settings['email_from_name']) ?: 'North Mountain Media';
    return implode("\r\n", [
        'From: ' . $name . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Auto-Response-Suppress: All',
    ]);
}

function notification_delivery_send_email(array $item, array $payload): array
{
    $email = strtolower(trim((string)$item['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'permanent' => true, 'code' => 'recipient_invalid', 'message' => 'The recipient email is invalid.'];
    $subject = preg_replace('/[\r\n]+/', ' ', (string)($payload['title'] ?? 'Notification')) ?: 'Notification';
    $body = trim((string)($payload['body'] ?? ''));
    $lines = [$subject, str_repeat('=', min(72, max(8, strlen($subject)))), ''];
    if ($body !== '') $lines[] = $body;
    $lines[] = '';
    $lines[] = 'Open in your POD: ' . (string)($payload['link_url'] ?? app_url('portal/admin.php?view=notifications'));
    $lines[] = '';
    $lines[] = 'Notification type: ' . status_label((string)($payload['event_key'] ?? 'system'));
    $sent = @mail($email, mb_substr($subject, 0, 190), implode("\n", $lines), notification_delivery_email_headers());
    return $sent
        ? ['ok' => true, 'reference' => hash('sha256', $email . '|' . microtime(true))]
        : ['ok' => false, 'permanent' => false, 'code' => 'php_mail_failed', 'message' => 'The PHP mail transport rejected the message.'];
}

function notification_delivery_send_digest(array $item, array $payload): array
{
    $frequency = in_array((string)($payload['digest_frequency'] ?? ''), ['hourly', 'daily', 'weekly'], true) ? (string)$payload['digest_frequency'] : 'daily';
    $statement = db()->prepare(
        'SELECT * FROM notification_delivery_queue
         WHERE recipient_user_id=:user_id AND channel="digest"
           AND status="leased" AND lease_token=:lease_token
           AND available_at<=UTC_TIMESTAMP()
         ORDER BY created_at,id LIMIT 100'
    );
    $statement->execute([
        'user_id' => (int)$item['recipient_user_id'],
        'lease_token' => (string)$item['lease_token'],
    ]);
    $rows = $statement->fetchAll();
    if (!$rows) return ['ok' => true, 'batch_ids' => [(int)$item['id']], 'reference' => 'empty-digest'];
    $items = [];
    foreach ($rows as $row) {
        $entry = json_decode((string)$row['payload_json'], true);
        if (!is_array($entry) || (string)($entry['digest_frequency'] ?? 'daily') !== $frequency) continue;
        $items[] = ['id' => (int)$row['id'], 'title' => (string)($entry['title'] ?? 'Notification'), 'body' => (string)($entry['body'] ?? ''), 'link_url' => (string)($entry['link_url'] ?? '')];
    }
    if (!$items) return ['ok' => true, 'batch_ids' => [(int)$item['id']], 'reference' => 'empty-digest'];
    $digestPayload = [
        'title' => status_label($frequency) . ' POD notification digest',
        'body' => implode("\n\n", array_map(static function (array $entry): string {
            return '• ' . $entry['title'] . ($entry['body'] !== '' ? "\n  " . $entry['body'] : '') . "\n  " . $entry['link_url'];
        }, $items)),
        'link_url' => app_url('portal/admin.php?view=inbox'),
        'event_key' => 'digest',
    ];
    $result = notification_delivery_send_email($item, $digestPayload);
    if (!$result['ok']) return $result;
    $windowEnd = gmdate('Y-m-d H:i:s');
    $windowStart = gmdate('Y-m-d H:i:s', min(array_map(static fn(array $row): int => strtotime((string)$row['created_at']) ?: time(), $rows)));
    $uuid = function_exists('pod_uuid_v4') ? pod_uuid_v4() : bin2hex(random_bytes(16));
    $hash = hash('sha256', json_encode($items, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    db()->prepare(
        'INSERT IGNORE INTO notification_digest_batches
            (batch_uuid,user_id,frequency,window_started_at,window_ended_at,item_count,payload_sha256,status,queue_id,sent_at)
         VALUES (:uuid,:user_id,:frequency,:window_start,:window_end,:item_count,:hash,"sent",:queue_id,UTC_TIMESTAMP())'
    )->execute([
        'uuid' => $uuid,
        'user_id' => (int)$item['recipient_user_id'],
        'frequency' => $frequency,
        'window_start' => $windowStart,
        'window_end' => $windowEnd,
        'item_count' => count($items),
        'hash' => $hash,
        'queue_id' => (int)$item['id'],
    ]);
    $result['batch_ids'] = array_column($items, 'id');
    return $result;
}

function notification_delivery_send_push(array $item, array $payload): array
{
    $vapid = notification_delivery_active_vapid_key();
    if (!$vapid) return ['ok' => false, 'permanent' => true, 'code' => 'vapid_uninitialized', 'message' => 'Web Push has not been initialized.'];
    $statement = db()->prepare(
        'SELECT * FROM notification_push_subscriptions
         WHERE user_id=:user_id AND status="active"
           AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())'
    );
    $statement->execute(['user_id' => (int)$item['recipient_user_id']]);
    $subscriptions = $statement->fetchAll();
    if (!$subscriptions) return ['ok' => false, 'permanent' => true, 'code' => 'no_push_subscriptions', 'message' => 'No active browser push subscription exists.'];
    $pushPayload = [
        'title' => mb_substr((string)($payload['title'] ?? 'POD notification'), 0, 100),
        'body' => mb_substr((string)($payload['body'] ?? ''), 0, 220),
        'url' => (string)($payload['link_url'] ?? app_url('portal/admin.php?view=notifications')),
        'tag' => 'nmm-' . (string)($payload['event_key'] ?? 'system'),
        'priority' => (string)($payload['priority'] ?? 'normal'),
    ];
    $successes = 0;
    $transient = 0;
    foreach ($subscriptions as $subscriptionRow) {
        $subscription = notification_delivery_decrypt(
            (string)$subscriptionRow['subscription_ciphertext'],
            (string)$subscriptionRow['subscription_iv'],
            (string)$subscriptionRow['subscription_tag']
        );
        if (!$subscription) {
            db()->prepare('UPDATE notification_push_subscriptions SET status="failed",failure_count=failure_count+1,last_failure_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => (int)$subscriptionRow['id']]);
            continue;
        }
        try {
            $result = notification_delivery_send_web_push($subscription, $pushPayload, $vapid);
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'permanent' => false, 'code' => 'push_exception', 'message' => $exception->getMessage()];
        }
        if (!empty($result['ok'])) {
            $successes++;
            db()->prepare('UPDATE notification_push_subscriptions SET failure_count=0,last_success_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => (int)$subscriptionRow['id']]);
        } elseif (!empty($result['permanent'])) {
            db()->prepare('UPDATE notification_push_subscriptions SET status="expired",failure_count=failure_count+1,last_failure_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => (int)$subscriptionRow['id']]);
        } else {
            $transient++;
            db()->prepare('UPDATE notification_push_subscriptions SET failure_count=failure_count+1,last_failure_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => (int)$subscriptionRow['id']]);
        }
    }
    if ($successes > 0) return ['ok' => true, 'reference' => 'push:' . $successes];
    return ['ok' => false, 'permanent' => $transient === 0, 'code' => 'all_push_deliveries_failed', 'message' => 'No browser accepted the push notification.'];
}

function notification_delivery_send_homeserver(array $item, array $payload): array
{
    if (!homeserver_capability_available('notification_alert')) {
        return ['ok' => false, 'permanent' => false, 'code' => 'homeserver_unavailable', 'message' => 'The paired HomeServer notification capability is offline or unauthorized.'];
    }
    $contentAllowed = !empty($item['include_content']);
    $request = [
        'wrapper' => 'rss-pod',
        'resource_authority' => 'notification_metadata',
        'proposal_only' => true,
        'send_allowed' => false,
        'notification' => [
            'id' => (int)$item['notification_id'],
            'event_key' => (string)$item['event_key'],
            'priority' => (string)$item['priority'],
            'title' => (string)($payload['title'] ?? ''),
            'body' => $contentAllowed ? (string)($payload['body'] ?? '') : '',
            'content_authorized' => $contentAllowed,
            'link_url' => (string)($payload['link_url'] ?? ''),
            'entity_type' => (string)($payload['entity_type'] ?? ''),
            'entity_id' => (int)($payload['entity_id'] ?? 0),
        ],
    ];
    $result = homeserver_request('notification_alert', $request);
    return !empty($result['ok'])
        ? ['ok' => true, 'reference' => mb_substr((string)($result['receipt_id'] ?? $result['job_id'] ?? 'homeserver'), 0, 255)]
        : ['ok' => false, 'permanent' => empty($result['available']), 'code' => (string)($result['error_code'] ?? 'homeserver_failed'), 'message' => (string)($result['message'] ?? 'The HomeServer alert failed.')];
}

function notification_delivery_runtime_authorization(array $item): array
{
    $settings = notification_delivery_settings();
    $preference = notification_delivery_preference((int)$item['recipient_user_id'], (string)$item['event_key']);
    if (empty($preference['configured'])) return ['allowed' => false, 'include_content' => false, 'code' => 'preference_not_configured'];
    if (notification_delivery_priority_rank((string)$item['priority']) < notification_delivery_priority_rank((string)$preference['minimum_priority'])) {
        return ['allowed' => false, 'include_content' => false, 'code' => 'priority_suppressed'];
    }
    return match ((string)$item['channel']) {
        'email' => [
            'allowed' => $settings['email_enabled'] && (string)$preference['email_mode'] === 'immediate',
            'include_content' => !empty($preference['include_content_email']),
            'code' => 'email_disabled',
        ],
        'digest' => [
            'allowed' => $settings['email_enabled'] && (string)$preference['email_mode'] === 'digest',
            'include_content' => !empty($preference['include_content_email']),
            'code' => 'digest_disabled',
        ],
        'push' => [
            'allowed' => $settings['push_enabled'] && !empty($preference['push_enabled']),
            'include_content' => !empty($preference['include_content_push']),
            'code' => 'push_disabled',
        ],
        'homeserver' => [
            'allowed' => $settings['homeserver_enabled'] && !empty($preference['homeserver_enabled']),
            'include_content' => !empty($preference['include_content_homeserver']),
            'code' => 'homeserver_disabled',
        ],
        default => ['allowed' => false, 'include_content' => false, 'code' => 'channel_invalid'],
    };
}

function notification_delivery_attempt_started(array $item): int
{
    $number = (int)$item['attempt_count'] + 1;
    db()->prepare(
        'INSERT INTO notification_delivery_attempts (queue_id,attempt_number,status)
         VALUES (:queue_id,:attempt_number,"started")'
    )->execute(['queue_id' => (int)$item['id'], 'attempt_number' => $number]);
    return (int)db()->lastInsertId();
}

function notification_delivery_process_item(array $item): array
{
    $stateStatement = db()->prepare('SELECT status,lease_token FROM notification_delivery_queue WHERE id=:id LIMIT 1');
    $stateStatement->execute(['id' => (int)$item['id']]);
    $state = $stateStatement->fetch();
    if (
        !$state
        || (string)$state['status'] !== 'leased'
        || (string)$state['lease_token'] === ''
        || !hash_equals((string)$state['lease_token'], (string)$item['lease_token'])
    ) {
        return ['ok' => true, 'skipped' => true, 'reference' => 'already-processed'];
    }

    $attemptId = notification_delivery_attempt_started($item);
    $payload = json_decode((string)$item['payload_json'], true);
    if (!is_array($payload)) $payload = [];
    $authorization = notification_delivery_runtime_authorization($item);
    if (empty($authorization['include_content'])) $payload['body'] = '';
    $item['include_content'] = !empty($authorization['include_content']) ? 1 : 0;

    if ((string)$item['user_status'] !== 'active') {
        $result = ['ok' => false, 'permanent' => true, 'suppressed' => true, 'code' => 'recipient_inactive', 'message' => 'The recipient account is inactive.'];
    } elseif (empty($authorization['allowed'])) {
        $result = ['ok' => false, 'permanent' => true, 'suppressed' => true, 'code' => (string)$authorization['code'], 'message' => 'The current notification preference no longer authorizes this delivery.'];
    } else {
        try {
            $result = match ((string)$item['channel']) {
                'email' => notification_delivery_send_email($item, $payload),
                'digest' => notification_delivery_send_digest($item, $payload),
                'push' => notification_delivery_send_push($item, $payload),
                'homeserver' => notification_delivery_send_homeserver($item, $payload),
                default => ['ok' => false, 'permanent' => true, 'code' => 'channel_invalid', 'message' => 'The delivery channel is invalid.'],
            };
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'permanent' => false, 'code' => 'delivery_exception', 'message' => $exception->getMessage()];
        }
    }
    $attemptNumber = (int)$item['attempt_count'] + 1;
    if (!empty($result['ok'])) {
        $ids = !empty($result['batch_ids']) && is_array($result['batch_ids']) ? array_values(array_filter(array_map('intval', $result['batch_ids']))) : [(int)$item['id']];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        foreach ($ids as $batchId) {
            if ($batchId === (int)$item['id']) continue;
            $attemptNumberStatement = db()->prepare('SELECT attempt_count+1 FROM notification_delivery_queue WHERE id=:id');
            $attemptNumberStatement->execute(['id' => $batchId]);
            $batchAttemptNumber = (int)($attemptNumberStatement->fetchColumn() ?: 1);
            db()->prepare(
                'INSERT INTO notification_delivery_attempts
                    (queue_id,attempt_number,status,provider_reference,receipt_json,started_at,completed_at)
                 VALUES (:queue_id,:attempt_number,"sent",:reference,:receipt,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
            )->execute([
                'queue_id' => $batchId,
                'attempt_number' => $batchAttemptNumber,
                'reference' => mb_substr((string)($result['reference'] ?? ''), 0, 255),
                'receipt' => json_encode(['channel' => 'digest', 'batch_count' => count($ids)], JSON_THROW_ON_ERROR),
            ]);
        }
        db()->prepare('UPDATE notification_delivery_queue SET status="sent",attempt_count=attempt_count+1,lease_token=NULL,leased_until=NULL,provider_reference=?,sent_at=UTC_TIMESTAMP() WHERE id IN (' . $placeholders . ')')
            ->execute(array_merge([mb_substr((string)($result['reference'] ?? ''), 0, 255)], $ids));
        db()->prepare('UPDATE notification_delivery_attempts SET status="sent",response_code=:response_code,provider_reference=:reference,receipt_json=:receipt,completed_at=UTC_TIMESTAMP() WHERE id=:id')
            ->execute([
                'response_code' => isset($result['status']) ? (int)$result['status'] : null,
                'reference' => mb_substr((string)($result['reference'] ?? ''), 0, 255),
                'receipt' => json_encode(['channel' => (string)$item['channel'], 'batch_count' => count($ids)], JSON_THROW_ON_ERROR),
                'id' => $attemptId,
            ]);
        return $result;
    }
    $permanent = !empty($result['permanent']) || $attemptNumber >= (int)$item['max_attempts'];
    $suppressed = !empty($result['suppressed']);
    $status = $suppressed ? 'suppressed' : ($permanent ? 'failed' : 'pending');
    $delay = min(3600, 60 * (2 ** max(0, $attemptNumber - 1)));
    db()->prepare(
        'UPDATE notification_delivery_queue
         SET status=:status,attempt_count=attempt_count+1,lease_token=NULL,leased_until=NULL,
             available_at=CASE WHEN :retry_status="pending" THEN :retry_at ELSE available_at END,
             last_error_code=:error_code,last_error_message=:error_message
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'retry_status' => $status,
        'retry_at' => gmdate('Y-m-d H:i:s', time() + $delay),
        'error_code' => mb_substr((string)($result['code'] ?? 'delivery_failed'), 0, 100),
        'error_message' => mb_substr((string)($result['message'] ?? 'Delivery failed.'), 0, 1000),
        'id' => (int)$item['id'],
    ]);
    db()->prepare(
        'UPDATE notification_delivery_attempts
         SET status=:status,response_code=:response_code,error_code=:error_code,error_message=:error_message,completed_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute([
        'status' => $suppressed ? 'suppressed' : ($permanent ? 'permanent_failure' : 'retry'),
        'response_code' => isset($result['status']) ? (int)$result['status'] : null,
        'error_code' => mb_substr((string)($result['code'] ?? 'delivery_failed'), 0, 100),
        'error_message' => mb_substr((string)($result['message'] ?? 'Delivery failed.'), 0, 1000),
        'id' => $attemptId,
    ]);
    if ($permanent && !$suppressed) notification_delivery_failure_notice((int)$item['id'], (int)$item['recipient_user_id'], (string)$item['channel'], (string)($result['message'] ?? 'Delivery failed.'));
    return $result;
}

function notification_delivery_failure_notice(int $queueId, int $recipientUserId, string $channel, string $message): void
{
    if ($queueId <= 0 || $recipientUserId <= 0) return;
    $existing = db()->prepare(
        'SELECT id FROM portal_notifications
         WHERE recipient_user_id=:user_id AND entity_type="notification_delivery_queue" AND entity_id=:queue_id LIMIT 1'
    );
    $existing->execute(['user_id' => $recipientUserId, 'queue_id' => $queueId]);
    if ($existing->fetchColumn()) return;
    db()->prepare(
        'INSERT INTO portal_notifications
            (recipient_user_id,category,title,body,link_url,entity_type,entity_id,priority)
         VALUES (:user_id,"system",:title,:body,"portal/admin.php?view=delivery","notification_delivery_queue",:queue_id,"high")'
    )->execute([
        'user_id' => $recipientUserId,
        'title' => status_label($channel) . ' notification delivery failed',
        'body' => mb_substr($message, 0, 1000),
        'queue_id' => $queueId,
    ]);
}

function notification_delivery_run(int $limit = 0): array
{
    $settings = notification_delivery_settings();
    if (!$settings['enabled'] || !notification_delivery_schema_available()) return ['processed' => 0, 'sent' => 0, 'failed' => 0];
    $items = notification_delivery_claim($limit > 0 ? $limit : $settings['worker_batch_size']);
    $result = ['processed' => 0, 'sent' => 0, 'failed' => 0];
    foreach ($items as $item) {
        $delivery = notification_delivery_process_item($item);
        $result['processed']++;
        if (!empty($delivery['ok'])) $result['sent']++; else $result['failed']++;
    }
    return $result;
}

function notification_delivery_retry(int $queueId): void
{
    notification_delivery_require_schema();
    db()->prepare(
        'UPDATE notification_delivery_queue
         SET status="pending",available_at=UTC_TIMESTAMP(),lease_token=NULL,leased_until=NULL,
             last_error_code=NULL,last_error_message=NULL
         WHERE id=:id AND status IN ("failed","suppressed")'
    )->execute(['id' => $queueId]);
}

function notification_delivery_cleanup(): array
{
    if (!notification_delivery_schema_available()) return ['attempts' => 0, 'queue' => 0, 'digests' => 0, 'subscriptions' => 0];
    $settings = notification_delivery_settings();
    $attempts = db()->exec('DELETE attempt FROM notification_delivery_attempts attempt JOIN notification_delivery_queue queue ON queue.id=attempt.queue_id WHERE queue.created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . (int)$settings['delivery_retention_days'] . ' DAY)');
    $queue = db()->exec('DELETE FROM notification_delivery_queue WHERE status IN ("sent","failed","suppressed","cancelled") AND created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . (int)$settings['delivery_retention_days'] . ' DAY)');
    $digests = db()->exec('DELETE FROM notification_digest_batches WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . (int)$settings['digest_retention_days'] . ' DAY)');
    $subscriptions = db()->exec('DELETE FROM notification_push_subscriptions WHERE status IN ("expired","revoked","failed") AND updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 DAY)');
    return ['attempts' => (int)$attempts, 'queue' => (int)$queue, 'digests' => (int)$digests, 'subscriptions' => (int)$subscriptions];
}

function notification_delivery_health(): array
{
    if (!notification_delivery_schema_available()) return ['schema' => false];
    $counts = [];
    foreach (['pending','leased','sent','failed','suppressed'] as $status) {
        $statement = db()->prepare('SELECT COUNT(*) FROM notification_delivery_queue WHERE status=:status');
        $statement->execute(['status' => $status]);
        $counts[$status] = (int)$statement->fetchColumn();
    }
    $counts['active_push_subscriptions'] = (int)db()->query('SELECT COUNT(*) FROM notification_push_subscriptions WHERE status="active"')->fetchColumn();
    $counts['vapid_initialized'] = notification_delivery_active_vapid_key() ? 1 : 0;
    return ['schema' => true, 'counts' => $counts, 'settings' => notification_delivery_settings(), 'homeserver' => homeserver_adapter_status()];
}

function notification_delivery_recent(int $limit = 50): array
{
    if (!notification_delivery_schema_available()) return [];
    $limit = max(1, min(200, $limit));
    return db()->query(
        'SELECT queue.*,user.email,user.display_name
         FROM notification_delivery_queue queue
         JOIN users user ON user.id=queue.recipient_user_id
         ORDER BY queue.created_at DESC,queue.id DESC LIMIT ' . $limit
    )->fetchAll();
}

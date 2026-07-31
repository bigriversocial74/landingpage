<?php
declare(strict_types=1);

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

function nmm_config(?string $section = null): array
{
    $config = [
        'app' => ['base_url' => 'https://pod.example.test', 'timezone' => 'America/Phoenix'],
        'security' => ['notification_delivery_secret' => str_repeat('d', 64)],
        'homeserver' => [],
    ];
    if ($section === null) return $config;
    return is_array($config[$section] ?? null) ? $config[$section] : [];
}

function nmm_site_setting(string $key): ?string
{
    $statement = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=:key LIMIT 1');
    $statement->execute(['key' => $key]);
    $value = $statement->fetchColumn();
    return $value === false ? null : (string)$value;
}

function setting(string $key, ?string $fallback = null): ?string
{
    return nmm_site_setting($key) ?? $fallback;
}

function app_url(string $path = ''): string
{
    return 'https://pod.example.test' . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function status_label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function pod_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function homeserver_connector_status(): array
{
    return [
        'paired' => true,
        'online' => true,
        'endpoint' => 'http://127.0.0.1:47831',
        'last_seen_at' => gmdate('Y-m-d H:i:s'),
        'capabilities' => ['notification_alert'],
    ];
}

function homeserver_connector_capability_available(string $capability): bool
{
    return $capability === 'notification_alert';
}

function homeserver_connector_request(string $capability, array $payload): array
{
    if ($capability !== 'notification_alert') throw new RuntimeException('Unsupported test capability.');
    if (($payload['send_allowed'] ?? true) !== false) throw new RuntimeException('Notification authority must deny sending.');
    return ['ok' => true, 'receipt_id' => 'test-homeserver-receipt'];
}

require dirname(__DIR__) . '/portal/notifications.php';

function v66j_db_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = db();
$email = 'v66j-' . substr(hash('sha256', (string)getmypid() . microtime(true)), 0, 12) . '@example.test';
$pdo->prepare(
    'INSERT INTO users (role,email,password_hash,display_name,status)
     VALUES ("admin",:email,:password_hash,"v66J Certification","active")'
)->execute(['email' => $email, 'password_hash' => password_hash('Certification-v66J-Password!', PASSWORD_DEFAULT)]);
$userId = (int)$pdo->lastInsertId();

try {
    $pairs = [
        'notification_delivery_enabled' => '1',
        'notification_email_enabled' => '1',
        'notification_push_enabled' => '0',
        'notification_homeserver_enabled' => '0',
        'notification_email_from' => 'alerts@example.test',
        'notification_email_from_name' => 'POD Alerts',
        'notification_vapid_subject' => 'mailto:alerts@example.test',
        'notification_worker_batch_size' => '25',
        'notification_max_attempts' => '3',
        'notification_delivery_retention_days' => '180',
        'notification_digest_retention_days' => '90',
    ];
    $saveSetting = $pdo->prepare(
        'INSERT INTO settings (setting_key,setting_value)
         VALUES (:key,:value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ($pairs as $key => $value) $saveSetting->execute(['key' => $key, 'value' => $value]);

    $unconfiguredId = notification_create(
        $userId,
        'system',
        'Unconfigured delivery event',
        'This event must remain in-app only.',
        'portal/admin.php?view=delivery',
        'general_notice',
        $userId - 1,
        'urgent'
    );
    $unconfiguredQueue = $pdo->prepare('SELECT COUNT(*) FROM notification_delivery_queue WHERE notification_id=:id');
    $unconfiguredQueue->execute(['id' => $unconfiguredId]);
    v66j_db_assert((int)$unconfiguredQueue->fetchColumn() === 0, 'External delivery must require a saved event preference.');

    $pdo->prepare(
        'INSERT INTO notification_delivery_preferences
            (user_id,event_key,email_mode,push_enabled,homeserver_enabled,
             include_content_email,include_content_push,include_content_homeserver,
             minimum_priority,digest_frequency)
         VALUES (:user_id,"system","immediate",0,0,0,0,0,"normal","daily")'
    )->execute(['user_id' => $userId]);

    $notificationId = notification_create(
        $userId,
        'system',
        'Certification event',
        'Private certification body text.',
        'portal/admin.php?view=delivery',
        'general_notice',
        $userId,
        'high'
    );
    v66j_db_assert($notificationId > 0, 'The canonical notification was not created.');
    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM notification_delivery_queue WHERE notification_id=:id AND channel="email"');
    $countStatement->execute(['id' => $notificationId]);
    v66j_db_assert((int)$countStatement->fetchColumn() === 1, 'The immediate email delivery was not queued exactly once.');

    notification_delivery_enqueue_notification($notificationId);
    $countStatement->execute(['id' => $notificationId]);
    v66j_db_assert((int)$countStatement->fetchColumn() === 1, 'Delivery enqueue must be idempotent.');

    $payloadStatement = $pdo->prepare('SELECT payload_json,include_content FROM notification_delivery_queue WHERE notification_id=:id AND channel="email" LIMIT 1');
    $payloadStatement->execute(['id' => $notificationId]);
    $queued = $payloadStatement->fetch();
    $payload = json_decode((string)$queued['payload_json'], true);
    v66j_db_assert(is_array($payload) && ($payload['body'] ?? 'unexpected') === '', 'Message content must be omitted by default.');
    v66j_db_assert((int)$queued['include_content'] === 0, 'The queue must record metadata-only authority by default.');

    $encrypted = notification_delivery_encrypt(['endpoint' => 'https://push.example.test/device', 'keys' => ['p256dh' => 'test', 'auth' => 'test']]);
    v66j_db_assert(
        notification_delivery_decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag'])['endpoint'] === 'https://push.example.test/device',
        'Encrypted subscription data did not round-trip.'
    );

    $vapid = notification_delivery_initialize_vapid($userId);
    v66j_db_assert(strlen((string)($vapid['public_key'] ?? '')) >= 80, 'The VAPID public key was not initialized.');
    v66j_db_assert(str_contains(notification_delivery_vapid_private_key($vapid), 'PRIVATE KEY'), 'The encrypted VAPID private key could not be restored.');
    $sameVapid = notification_delivery_initialize_vapid($userId);
    v66j_db_assert((int)$sameVapid['id'] === (int)$vapid['id'], 'VAPID initialization must be stable and idempotent.');

    $pdo->prepare(
        'INSERT INTO notification_quiet_hours
            (user_id,enabled,timezone_name,start_time,end_time,weekday_mask,
             allow_high_priority,allow_urgent_priority,digest_local_time)
         VALUES (:user_id,1,"America/Phoenix","21:00:00","07:00:00",127,0,1,"08:00:00")'
    )->execute(['user_id' => $userId]);
    $quietNow = new DateTimeImmutable('2026-07-31 05:00:00', new DateTimeZone('UTC'));
    $normalRelease = notification_delivery_quiet_release_at($userId, 'normal', $quietNow);
    $urgentRelease = notification_delivery_quiet_release_at($userId, 'urgent', $quietNow);
    v66j_db_assert($normalRelease > $quietNow, 'Normal delivery must be deferred during quiet hours.');
    v66j_db_assert($urgentRelease == $quietNow, 'Authorized urgent delivery must bypass quiet hours.');

    $pdo->prepare(
        'UPDATE notification_delivery_preferences
         SET email_mode="digest",digest_frequency="daily",include_content_email=1
         WHERE user_id=:user_id AND event_key="system"'
    )->execute(['user_id' => $userId]);
    $digestId = notification_create(
        $userId,
        'system',
        'Digest certification event',
        'Digest content authorized by the test preference.',
        'portal/admin.php?view=inbox',
        'general_notice',
        $userId + 1,
        'normal'
    );
    $digestStatement = $pdo->prepare('SELECT payload_json,include_content,available_at FROM notification_delivery_queue WHERE notification_id=:id AND channel="digest" LIMIT 1');
    $digestStatement->execute(['id' => $digestId]);
    $digestRow = $digestStatement->fetch();
    v66j_db_assert((int)($digestRow['include_content'] ?? 0) === 1, 'Explicit email content authorization was not preserved.');
    $digestPayload = json_decode((string)$digestRow['payload_json'], true);
    v66j_db_assert(str_contains((string)($digestPayload['body'] ?? ''), 'Digest content authorized'), 'Authorized digest content was not queued.');
    v66j_db_assert(strtotime((string)$digestRow['available_at']) > time(), 'Digest delivery must be scheduled in the future.');

    $pdo->prepare(
        'UPDATE settings SET setting_value="1" WHERE setting_key="notification_homeserver_enabled"'
    )->execute();
    $pdo->prepare(
        'UPDATE notification_delivery_preferences
         SET email_mode="off",homeserver_enabled=1,include_content_homeserver=0
         WHERE user_id=:user_id AND event_key="system"'
    )->execute(['user_id' => $userId]);
    $homeId = notification_create(
        $userId,
        'system',
        'HomeServer metadata event',
        'This body must remain private.',
        'portal/admin.php?view=delivery',
        'general_notice',
        $userId + 2,
        'urgent'
    );
    $homeStatement = $pdo->prepare('SELECT payload_json,include_content FROM notification_delivery_queue WHERE notification_id=:id AND channel="homeserver" LIMIT 1');
    $homeStatement->execute(['id' => $homeId]);
    $homeRow = $homeStatement->fetch();
    v66j_db_assert((int)($homeRow['include_content'] ?? 1) === 0, 'HomeServer alerts must be metadata-only by default.');
    $homePayload = json_decode((string)$homeRow['payload_json'], true);
    v66j_db_assert(($homePayload['body'] ?? 'unexpected') === '', 'Unauthorized HomeServer content must not enter the queue.');
    $homeQueueStatement = $pdo->prepare('SELECT * FROM notification_delivery_queue WHERE notification_id=:id AND channel="homeserver" LIMIT 1');
    $homeQueueStatement->execute(['id' => $homeId]);
    $homeQueue = $homeQueueStatement->fetch();
    $homeAuthorization = notification_delivery_runtime_authorization($homeQueue);
    v66j_db_assert(!empty($homeAuthorization['allowed']) && empty($homeAuthorization['include_content']), 'Runtime HomeServer authorization must preserve metadata-only delivery.');
    $pdo->prepare('UPDATE notification_delivery_preferences SET homeserver_enabled=0 WHERE user_id=:user_id AND event_key="system"')->execute(['user_id' => $userId]);
    $revokedAuthorization = notification_delivery_runtime_authorization($homeQueue);
    v66j_db_assert(empty($revokedAuthorization['allowed']), 'Runtime delivery must honor preference revocation.');
    $pdo->prepare('UPDATE notification_delivery_preferences SET homeserver_enabled=1 WHERE user_id=:user_id AND event_key="system"')->execute(['user_id' => $userId]);

    $claimed = notification_delivery_claim(20);
    v66j_db_assert(count($claimed) >= 2, 'Ready notification deliveries were not leased.');
    $leaseTokens = array_values(array_unique(array_filter(array_column($claimed, 'lease_token'))));
    v66j_db_assert(count($leaseTokens) === 1, 'A claim batch must use one opaque lease token.');

    $retryId = (int)$claimed[0]['id'];
    $pdo->prepare('UPDATE notification_delivery_queue SET status="failed",lease_token=NULL,leased_until=NULL,last_error_code="test_failure" WHERE id=:id')->execute(['id' => $retryId]);
    notification_delivery_retry($retryId);
    $retryStatement = $pdo->prepare('SELECT status,last_error_code,available_at FROM notification_delivery_queue WHERE id=:id');
    $retryStatement->execute(['id' => $retryId]);
    $retry = $retryStatement->fetch();
    v66j_db_assert($retry['status'] === 'pending' && $retry['last_error_code'] === null, 'Manual retry did not reset the failed delivery.');

    notification_delivery_failure_notice($retryId, $userId, 'email', 'Synthetic permanent failure.');
    notification_delivery_failure_notice($retryId, $userId, 'email', 'Synthetic permanent failure.');
    $failureNotice = $pdo->prepare(
        'SELECT COUNT(*) FROM portal_notifications
         WHERE recipient_user_id=:user_id AND entity_type="notification_delivery_queue" AND entity_id=:queue_id'
    );
    $failureNotice->execute(['user_id' => $userId, 'queue_id' => $retryId]);
    v66j_db_assert((int)$failureNotice->fetchColumn() === 1, 'Failure escalation notices must be deduplicated.');

    $health = notification_delivery_health();
    v66j_db_assert(!empty($health['schema']) && isset($health['counts']['pending']), 'Delivery health did not report queue state.');

    $pdo->prepare('UPDATE users SET status="inactive" WHERE id=:id')->execute(['id' => $userId]);
    $inactiveId = notification_create($userId, 'system', 'Inactive recipient', null, null, 'general_notice', $userId + 3, 'urgent');
    $inactiveQueue = $pdo->prepare('SELECT COUNT(*) FROM notification_delivery_queue WHERE notification_id=:id');
    $inactiveQueue->execute(['id' => $inactiveId]);
    v66j_db_assert((int)$inactiveQueue->fetchColumn() === 0, 'Inactive recipients must not receive external delivery.');

    fwrite(STDOUT, "Notification Delivery v66J live database integration passed.\n");
} finally {
    $pdo->prepare('DELETE FROM notification_delivery_attempts WHERE queue_id IN (SELECT id FROM notification_delivery_queue WHERE recipient_user_id=:user_id)')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM notification_digest_batches WHERE user_id=:user_id')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM notification_delivery_queue WHERE recipient_user_id=:user_id')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM notification_push_subscriptions WHERE user_id=:user_id')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM notification_quiet_hours WHERE user_id=:user_id')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM notification_delivery_preferences WHERE user_id=:user_id')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM notification_delivery_keys WHERE created_by=:user_id')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM portal_notifications WHERE recipient_user_id=:user_id')->execute(['user_id' => $userId]);
    $pdo->prepare('DELETE FROM users WHERE id=:user_id')->execute(['user_id' => $userId]);
}

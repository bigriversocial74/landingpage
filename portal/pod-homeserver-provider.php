<?php
declare(strict_types=1);

require_once __DIR__ . '/pod-agent-voice.php';

function pod_homeserver_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    if (!pod_voice_schema_available()) return $available = false;

    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "pod_homeserver_pairing_codes",
                    "pod_homeserver_connections",
                    "pod_homeserver_request_nonces",
                    "pod_homeserver_voice_jobs",
                    "pod_homeserver_voice_artifacts",
                    "pod_homeserver_voice_receipts"
               )'
        );
        $available = (int)$statement->fetchColumn() === 6;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function pod_homeserver_require_schema(): void
{
    if (!pod_homeserver_schema_available()) {
        throw new RuntimeException(
            'Import database/pod_homeserver_voice_provider_v63_5.sql before using the POD HomeServer provider.'
        );
    }
}

function pod_homeserver_config(): array
{
    $config = nmm_config('pod_homeserver');
    return is_array($config) ? $config : [];
}

function pod_homeserver_enabled(): bool
{
    return pod_homeserver_schema_available()
        && (bool)(pod_homeserver_config()['enabled'] ?? false);
}

function pod_homeserver_supported_capabilities(): array
{
    return [
        'pod.pairing.v1',
        'pod.device-heartbeat.v1',
        'pod.voice.jobs.v1',
        'pod.voice.transcription.v1',
        'pod.voice.synthesis.v1',
        'pod.voice.artifacts.v1',
        'pod.voice.receipts.v1',
        'pod.receptionist.context.v1',
    ];
}

function pod_homeserver_secret_key(): string
{
    $security = nmm_config('security');
    $app = nmm_config('app');
    $candidates = [
        (string)($security['pod_homeserver_bridge_secret'] ?? ''),
        (string)($security['pod_message_link_secret'] ?? ''),
        (string)($security['pod_call_link_secret'] ?? ''),
        (string)($app['setup_token'] ?? ''),
    ];

    foreach ($candidates as $secret) {
        $secret = trim($secret);
        if (
            strlen($secret) >= 24
            && !str_contains($secret, 'replace-with')
            && !str_contains($secret, 'change-this')
        ) {
            return hash('sha256', 'pod-homeserver-provider-v1|' . $secret, true);
        }
    }

    throw new RuntimeException(
        'Configure security.pod_homeserver_bridge_secret with a private value of at least 24 characters.'
    );
}

function pod_homeserver_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function pod_homeserver_base64url_decode(string $value): string
{
    $value = trim($value);
    if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) return '';
    $padding = strlen($value) % 4;
    if ($padding > 0) $value .= str_repeat('=', 4 - $padding);
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    return is_string($decoded) ? $decoded : '';
}

function pod_homeserver_encrypt_bytes(string $plaintext, string $context): array
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required for the POD HomeServer provider.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        pod_homeserver_secret_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        $context
    );
    if (!is_string($ciphertext) || $tag === '') {
        throw new RuntimeException('POD HomeServer data encryption failed.');
    }
    return [
        'ciphertext' => base64_encode($ciphertext),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
    ];
}

function pod_homeserver_decrypt_bytes(array $record, string $context, string $prefix = ''): string
{
    $ciphertext = base64_decode((string)($record[$prefix . 'ciphertext'] ?? ''), true);
    $iv = base64_decode((string)($record[$prefix . 'iv'] ?? ''), true);
    $tag = base64_decode((string)($record[$prefix . 'tag'] ?? ''), true);
    if (!is_string($ciphertext) || !is_string($iv) || !is_string($tag)) return '';

    try {
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            pod_homeserver_secret_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $context
        );
    } catch (Throwable) {
        return '';
    }
    return is_string($plaintext) ? $plaintext : '';
}

function pod_homeserver_encrypt_json(array $payload, string $context): array
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $encrypted = pod_homeserver_encrypt_bytes($json, $context);
    $encrypted['hash'] = hash('sha256', $json);
    return $encrypted;
}

function pod_homeserver_decrypt_json(array $record, string $context, string $prefix = ''): array
{
    $json = pod_homeserver_decrypt_bytes($record, $context, $prefix);
    if ($json === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function pod_homeserver_receipt(
    int $connectionId,
    string $type,
    ?int $jobId = null,
    ?string $statusCode = null,
    array $metadata = []
): string {
    $receiptUuid = pod_uuid_v4();
    db()->prepare(
        'INSERT INTO pod_homeserver_voice_receipts
            (receipt_uuid,connection_id,job_id,receipt_type,status_code,metadata_json)
         VALUES
            (:receipt_uuid,:connection_id,:job_id,:receipt_type,:status_code,:metadata_json)'
    )->execute([
        'receipt_uuid' => $receiptUuid,
        'connection_id' => $connectionId,
        'job_id' => $jobId,
        'receipt_type' => $type,
        'status_code' => $statusCode,
        'metadata_json' => $metadata
            ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : null,
    ]);
    return $receiptUuid;
}

function pod_homeserver_pairing_code(int $actorUserId): array
{
    pod_homeserver_require_schema();
    if (!(bool)(pod_homeserver_config()['enabled'] ?? false)) {
        throw new RuntimeException('Enable pod_homeserver.enabled before issuing Sync Codes.');
    }

    $minutes = max(5, min(60, (int)(pod_homeserver_config()['pairing_code_minutes'] ?? 15)));
    $raw = strtoupper(bin2hex(random_bytes(12)));
    $chunks = str_split($raw, 4);
    $code = 'POD-' . implode('-', $chunks);
    $codeHash = hash('sha256', $code);
    $hint = 'POD-' . $chunks[0] . '-…-' . end($chunks);
    $capabilities = pod_homeserver_supported_capabilities();
    $expiresAt = gmdate('Y-m-d H:i:s', time() + ($minutes * 60));

    db()->prepare(
        'UPDATE pod_homeserver_pairing_codes
         SET status="expired"
         WHERE status="active" AND expires_at<UTC_TIMESTAMP()'
    )->execute();
    db()->prepare(
        'INSERT INTO pod_homeserver_pairing_codes
            (code_hash,code_hint,status,requested_capabilities_json,
             expires_at,created_by_user_id)
         VALUES
            (:code_hash,:code_hint,"active",:capabilities,:expires_at,:created_by_user_id)'
    )->execute([
        'code_hash' => $codeHash,
        'code_hint' => $hint,
        'capabilities' => json_encode($capabilities, JSON_THROW_ON_ERROR),
        'expires_at' => $expiresAt,
        'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
    ]);
    $id = (int)db()->lastInsertId();
    log_activity('pod_homeserver_sync_code_issued', 'pod_homeserver_pairing_code', $id, [
        'expires_at' => $expiresAt,
        'capabilities' => $capabilities,
    ]);

    return ['id' => $id, 'code' => $code, 'hint' => $hint, 'expires_at' => $expiresAt];
}

function pod_homeserver_revoke_pairing_code(int $codeId): void
{
    pod_homeserver_require_schema();
    db()->prepare(
        'UPDATE pod_homeserver_pairing_codes
         SET status="revoked" WHERE id=:id AND status="active"'
    )->execute(['id' => $codeId]);
    log_activity('pod_homeserver_sync_code_revoked', 'pod_homeserver_pairing_code', $codeId);
}

function pod_homeserver_pairing_token(array $connection): string
{
    $material = implode('|', [
        (string)$connection['connection_uuid'],
        (string)$connection['pairing_request_id'],
        (string)$connection['installation_id'],
    ]);
    return hash_hmac('sha256', 'bearer|' . $material, pod_homeserver_secret_key());
}

function pod_homeserver_validate_public_key(string $encoded): string
{
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        throw new RuntimeException('The Sodium extension is required for signed HomeServer requests.');
    }
    $raw = pod_homeserver_base64url_decode($encoded);
    if ($raw === '' || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
        throw new RuntimeException('The HomeServer Ed25519 public key is invalid.');
    }
    return $encoded;
}

function pod_homeserver_capability_intersection(array $requested): array
{
    $supported = pod_homeserver_supported_capabilities();
    $granted = [];
    foreach ($requested as $capability) {
        $capability = trim((string)$capability);
        if (
            $capability !== ''
            && in_array($capability, $supported, true)
            && !in_array($capability, $granted, true)
        ) {
            $granted[] = $capability;
        }
    }
    foreach (['pod.pairing.v1','pod.device-heartbeat.v1','pod.voice.jobs.v1','pod.voice.receipts.v1'] as $required) {
        if (!in_array($required, $granted, true)) $granted[] = $required;
    }
    return $granted;
}

function pod_homeserver_pair(array $payload): array
{
    pod_homeserver_require_schema();
    if (!(bool)(pod_homeserver_config()['enabled'] ?? false)) {
        throw new RuntimeException('The POD HomeServer provider is disabled.');
    }

    $providerKey = trim((string)($payload['provider_key'] ?? ''));
    $syncCode = strtoupper(trim((string)($payload['sync_code'] ?? '')));
    $requestId = trim((string)($payload['request_id'] ?? ''));
    $installationId = trim((string)($payload['installation_id'] ?? ''));
    $displayName = trim((string)($payload['device_display_name'] ?? ''));
    $version = trim((string)($payload['homeserver_version'] ?? ''));
    $publicKey = trim((string)($payload['device_public_key'] ?? ''));
    $requested = is_array($payload['requested_capabilities'] ?? null)
        ? $payload['requested_capabilities']
        : [];

    if ($providerKey !== 'pod') throw new RuntimeException('The provider_key must be pod.');
    if (!preg_match('/^POD-(?:[A-F0-9]{4}-){5}[A-F0-9]{4}$/', $syncCode)) {
        throw new RuntimeException('The POD Sync Code is invalid.');
    }
    if ($requestId === '' || strlen($requestId) > 120) {
        throw new RuntimeException('A bounded idempotent request_id is required.');
    }
    if ($installationId === '' || strlen($installationId) > 120) {
        throw new RuntimeException('A bounded HomeServer installation_id is required.');
    }
    if ($displayName === '' || strlen($displayName) > 190) {
        throw new RuntimeException('Enter a HomeServer device display name.');
    }
    if ($version === '' || strlen($version) > 40) {
        throw new RuntimeException('A bounded HomeServer version is required.');
    }
    pod_homeserver_validate_public_key($publicKey);
    $identity = pod_local_identity(true);
    if (!$identity) throw new RuntimeException('The local POD identity is unavailable.');

    $existing = db()->prepare(
        'SELECT * FROM pod_homeserver_connections
         WHERE pod_identity_id=:pod_identity_id AND pairing_request_id=:request_id
         LIMIT 1'
    );
    $existing->execute([
        'pod_identity_id' => (int)$identity['id'],
        'request_id' => $requestId,
    ]);
    $connection = $existing->fetch();
    if ($connection) {
        if (
            !hash_equals((string)$connection['installation_id'], $installationId)
            || !hash_equals((string)$connection['device_public_key'], $publicKey)
        ) {
            throw new RuntimeException('The idempotent pairing request does not match the original device.');
        }
        if ((string)$connection['lifecycle_state'] === 'revoked') {
            throw new RuntimeException('The existing POD HomeServer connection was revoked.');
        }
        return pod_homeserver_pairing_response($connection);
    }

    $codeHash = hash('sha256', $syncCode);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $codeStatement = $pdo->prepare(
            'SELECT * FROM pod_homeserver_pairing_codes
             WHERE code_hash=:code_hash LIMIT 1 FOR UPDATE'
        );
        $codeStatement->execute(['code_hash' => $codeHash]);
        $code = $codeStatement->fetch();
        if (!$code || (string)$code['status'] !== 'active') {
            throw new RuntimeException('The POD Sync Code is unavailable or already used.');
        }
        if (strtotime((string)$code['expires_at']) < time()) {
            $pdo->prepare(
                'UPDATE pod_homeserver_pairing_codes SET status="expired" WHERE id=:id'
            )->execute(['id' => (int)$code['id']]);
            throw new RuntimeException('The POD Sync Code expired.');
        }

        $connectionUuid = pod_uuid_v4();
        $deviceId = pod_uuid_v4();
        $granted = pod_homeserver_capability_intersection($requested);
        $temporary = [
            'connection_uuid' => $connectionUuid,
            'pairing_request_id' => $requestId,
            'installation_id' => $installationId,
        ];
        $token = pod_homeserver_pairing_token($temporary);
        $tokenHash = hash('sha256', $token);
        $tokenHint = substr($token, 0, 6) . '…' . substr($token, -4);

        $pdo->prepare(
            'INSERT INTO pod_homeserver_connections
                (connection_uuid,pairing_request_id,pod_identity_id,installation_id,
                 device_id,device_display_name,homeserver_version,device_public_key,
                 bearer_token_hash,token_hint,lifecycle_state,
                 granted_capabilities_json,last_heartbeat_at)
             VALUES
                (:connection_uuid,:pairing_request_id,:pod_identity_id,:installation_id,
                 :device_id,:device_display_name,:homeserver_version,:device_public_key,
                 :bearer_token_hash,:token_hint,"active",
                 :granted_capabilities_json,UTC_TIMESTAMP())'
        )->execute([
            'connection_uuid' => $connectionUuid,
            'pairing_request_id' => $requestId,
            'pod_identity_id' => (int)$identity['id'],
            'installation_id' => $installationId,
            'device_id' => $deviceId,
            'device_display_name' => $displayName,
            'homeserver_version' => $version,
            'device_public_key' => $publicKey,
            'bearer_token_hash' => $tokenHash,
            'token_hint' => $tokenHint,
            'granted_capabilities_json' => json_encode($granted, JSON_THROW_ON_ERROR),
        ]);
        $connectionId = (int)$pdo->lastInsertId();
        $pdo->prepare(
            'UPDATE pod_homeserver_pairing_codes
             SET status="used",used_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute(['id' => (int)$code['id']]);
        $pdo->commit();

        $connection = pod_homeserver_connection($connectionId);
        if (!$connection) throw new RuntimeException('The POD HomeServer connection could not be loaded.');
        pod_homeserver_receipt($connectionId, 'paired', null, 'success', [
            'device_id' => $deviceId,
            'connection_uuid' => $connectionUuid,
            'granted_capabilities' => $granted,
        ]);
        log_activity('pod_homeserver_paired', 'pod_homeserver_connection', $connectionId, [
            'device_id' => $deviceId,
            'connection_uuid' => $connectionUuid,
        ]);
        return pod_homeserver_pairing_response($connection);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function pod_homeserver_pairing_response(array $connection): array
{
    $identity = pod_local_identity(true);
    $capabilities = json_decode((string)$connection['granted_capabilities_json'], true);
    if (!is_array($capabilities)) $capabilities = [];
    return [
        'schema_version' => 1,
        'provider_id' => 'pod',
        'provider_connection_id' => (string)$connection['connection_uuid'],
        'provider_identity_id' => (string)($identity['pod_uuid'] ?? ''),
        'provider_display_name' => (string)($identity['display_name'] ?? 'POD'),
        'device_id' => (string)$connection['device_id'],
        'device_token' => pod_homeserver_pairing_token($connection),
        'granted_capabilities' => $capabilities,
        'capability_registry_version' => 1,
        'endpoints' => pod_homeserver_endpoint_contract(),
    ];
}

function pod_homeserver_endpoint_contract(): array
{
    $origin = pod_configured_origin();
    $base = $origin !== '' ? $origin . '/api/homeserver/v1' : '';
    return [
        'pairing_exchange' => $base . '/pairing/exchange',
        'heartbeat' => $base . '/devices/heartbeat',
        'voice_job_poll' => $base . '/voice/jobs/poll',
        'voice_job_complete' => $base . '/voice/jobs/complete',
        'voice_job_fail' => $base . '/voice/jobs/fail',
        'voice_artifact_read' => $base . '/voice/artifacts/read',
    ];
}

function pod_homeserver_connection(int $connectionId): ?array
{
    if ($connectionId <= 0) return null;
    $statement = db()->prepare(
        'SELECT connection.*,identity.pod_uuid,identity.display_name AS pod_display_name
         FROM pod_homeserver_connections connection
         JOIN pod_identities identity ON identity.id=connection.pod_identity_id
         WHERE connection.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $connectionId]);
    return $statement->fetch() ?: null;
}

function pod_homeserver_connections(): array
{
    if (!pod_homeserver_schema_available()) return [];
    return db()->query(
        'SELECT connection.*,
                (SELECT COUNT(*) FROM pod_homeserver_voice_jobs job
                 WHERE job.connection_id=connection.id AND job.status="queued") AS queued_jobs,
                (SELECT COUNT(*) FROM pod_homeserver_voice_jobs job
                 WHERE job.connection_id=connection.id AND job.status="failed") AS failed_jobs
         FROM pod_homeserver_connections connection
         ORDER BY connection.created_at DESC,connection.id DESC'
    )->fetchAll();
}

function pod_homeserver_extract_bearer(): string
{
    $authorization = trim((string)(
        $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? ''
    ));
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
        return strtolower($matches[1]);
    }
    return '';
}

function pod_homeserver_request_path(): string
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

function pod_homeserver_authorize_signed_request(string $rawBody): array
{
    pod_homeserver_require_schema();
    if (!(bool)(pod_homeserver_config()['enabled'] ?? false)) {
        throw new RuntimeException('The POD HomeServer provider is disabled.');
    }

    $token = pod_homeserver_extract_bearer();
    if ($token === '') throw new RuntimeException('HomeServer bearer authentication is required.');
    $statement = db()->prepare(
        'SELECT * FROM pod_homeserver_connections WHERE bearer_token_hash=:token_hash LIMIT 1'
    );
    $statement->execute(['token_hash' => hash('sha256', $token)]);
    $connection = $statement->fetch();
    if (!$connection || (string)$connection['lifecycle_state'] !== 'active') {
        throw new RuntimeException('The POD HomeServer connection is inactive or revoked.');
    }

    $deviceId = trim((string)($_SERVER['HTTP_X_POD_HOMESERVER_ID'] ?? ''));
    $connectionUuid = trim((string)($_SERVER['HTTP_X_POD_CONNECTION_ID'] ?? ''));
    $timestamp = trim((string)($_SERVER['HTTP_X_POD_TIMESTAMP'] ?? ''));
    $nonce = trim((string)($_SERVER['HTTP_X_POD_NONCE'] ?? ''));
    $signatureEncoded = trim((string)($_SERVER['HTTP_X_POD_SIGNATURE'] ?? ''));
    $version = trim((string)($_SERVER['HTTP_X_POD_HOMESERVER_VERSION'] ?? ''));

    if (!hash_equals((string)$connection['device_id'], $deviceId)) {
        throw new RuntimeException('The HomeServer device identity does not match the bearer credential.');
    }
    if (!hash_equals((string)$connection['connection_uuid'], $connectionUuid)) {
        throw new RuntimeException('The POD provider connection identity does not match.');
    }
    $skew = max(60, min(900, (int)(pod_homeserver_config()['request_skew_seconds'] ?? 300)));
    if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > $skew) {
        throw new RuntimeException('The signed HomeServer request timestamp is outside the allowed window.');
    }
    if ($nonce === '' || strlen($nonce) < 16 || strlen($nonce) > 160) {
        throw new RuntimeException('A bounded unique HomeServer request nonce is required.');
    }
    $signature = pod_homeserver_base64url_decode($signatureEncoded);
    $publicKey = pod_homeserver_base64url_decode((string)$connection['device_public_key']);
    if (
        strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
        || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
    ) {
        throw new RuntimeException('The signed HomeServer request key material is invalid.');
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'POST'));
    $path = pod_homeserver_request_path();
    $canonical = implode("\n", [
        $method,
        $path,
        $timestamp,
        $nonce,
        hash('sha256', $rawBody),
    ]);
    if (!sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey)) {
        throw new RuntimeException('The signed HomeServer request could not be verified.');
    }

    $nonceHash = hash('sha256', $nonce);
    try {
        db()->prepare(
            'INSERT INTO pod_homeserver_request_nonces
                (connection_id,nonce_hash,request_timestamp)
             VALUES (:connection_id,:nonce_hash,:request_timestamp)'
        )->execute([
            'connection_id' => (int)$connection['id'],
            'nonce_hash' => $nonceHash,
            'request_timestamp' => (int)$timestamp,
        ]);
    } catch (Throwable) {
        throw new RuntimeException('The signed HomeServer request nonce was already used.');
    }

    $retentionHours = max(1, min(168, (int)(pod_homeserver_config()['nonce_retention_hours'] ?? 24)));
    db()->prepare(
        'DELETE FROM pod_homeserver_request_nonces
         WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :hours HOUR)'
    )->execute(['hours' => $retentionHours]);
    db()->prepare(
        'UPDATE pod_homeserver_connections
         SET last_request_at=UTC_TIMESTAMP(),homeserver_version=:version,
             last_ip_hash=:ip_hash,last_error_code=NULL
         WHERE id=:id'
    )->execute([
        'version' => $version !== '' ? substr($version, 0, 40) : (string)$connection['homeserver_version'],
        'ip_hash' => hash_hmac('sha256', request_ip(), pod_homeserver_secret_key()),
        'id' => (int)$connection['id'],
    ]);

    return $connection;
}

function pod_homeserver_connection_capabilities(array $connection): array
{
    $decoded = json_decode((string)($connection['granted_capabilities_json'] ?? '[]'), true);
    return is_array($decoded) ? $decoded : [];
}

function pod_homeserver_require_capability(array $connection, string $capability): void
{
    if (!in_array($capability, pod_homeserver_connection_capabilities($connection), true)) {
        throw new RuntimeException('The paired HomeServer was not granted capability ' . $capability . '.');
    }
}

function pod_homeserver_heartbeat(array $connection, array $payload): array
{
    pod_homeserver_require_capability($connection, 'pod.device-heartbeat.v1');
    $version = trim((string)($payload['homeserver_version'] ?? $connection['homeserver_version']));
    $supported = is_array($payload['supported_capabilities'] ?? null)
        ? array_values(array_filter(array_map('strval', $payload['supported_capabilities'])))
        : [];
    $activeJobs = max(0, min(1000, (int)($payload['active_voice_jobs'] ?? 0)));
    $health = trim((string)($payload['voice_runtime_health'] ?? 'unknown'));
    if (!in_array($health, ['healthy','degraded','offline','unknown'], true)) $health = 'unknown';

    db()->prepare(
        'UPDATE pod_homeserver_connections
         SET lifecycle_state="active",last_heartbeat_at=UTC_TIMESTAMP(),
             homeserver_version=:homeserver_version,last_error_code=NULL
         WHERE id=:id'
    )->execute([
        'homeserver_version' => substr($version, 0, 40),
        'id' => (int)$connection['id'],
    ]);
    $receipt = pod_homeserver_receipt(
        (int)$connection['id'],
        'heartbeat',
        null,
        $health,
        [
            'supported_capability_count' => count($supported),
            'active_voice_jobs' => $activeJobs,
        ]
    );

    return [
        'receipt_id' => $receipt,
        'connection_state' => 'active',
        'provider_time' => gmdate('c'),
        'granted_capabilities' => pod_homeserver_connection_capabilities($connection),
        'queued_voice_jobs' => pod_homeserver_queued_job_count((int)$connection['id']),
    ];
}

function pod_homeserver_job_capability(string $jobType): string
{
    return match ($jobType) {
        'speech_to_text' => 'pod.voice.transcription.v1',
        'text_to_speech' => 'pod.voice.synthesis.v1',
        default => 'pod.voice.jobs.v1',
    };
}

function pod_homeserver_queue_job(
    int $connectionId,
    string $jobType,
    array $payload,
    ?int $receptionistSessionId,
    ?int $voiceSessionId,
    int $actorUserId,
    ?int $inputArtifactId = null,
    string $priority = 'normal'
): array {
    pod_homeserver_require_schema();
    $connection = pod_homeserver_connection($connectionId);
    if (!$connection || (string)$connection['lifecycle_state'] !== 'active') {
        throw new RuntimeException('Choose an active paired HomeServer connection.');
    }
    if (!in_array($jobType, ['speech_to_text','text_to_speech','capability_test'], true)) {
        throw new RuntimeException('Unsupported HomeServer voice job type.');
    }
    pod_homeserver_require_capability($connection, pod_homeserver_job_capability($jobType));
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($json) > 64 * 1024) {
        throw new RuntimeException('The HomeServer voice job payload is too large.');
    }
    $jobUuid = pod_uuid_v4();
    $encrypted = pod_homeserver_encrypt_json($payload, 'pod-homeserver-job|' . $jobUuid);
    $ttlMinutes = max(5, min(1440, (int)(pod_homeserver_config()['job_ttl_minutes'] ?? 30)));
    $maxAttempts = max(1, min(10, (int)(pod_homeserver_config()['max_attempts'] ?? 3)));
    $expiresAt = gmdate('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

    db()->prepare(
        'INSERT INTO pod_homeserver_voice_jobs
            (job_uuid,connection_id,receptionist_session_id,voice_session_id,
             job_type,status,priority,payload_ciphertext,payload_iv,payload_tag,
             payload_hash,input_artifact_id,max_attempts,expires_at,
             created_by_user_id)
         VALUES
            (:job_uuid,:connection_id,:receptionist_session_id,:voice_session_id,
             :job_type,"queued",:priority,:payload_ciphertext,:payload_iv,:payload_tag,
             :payload_hash,:input_artifact_id,:max_attempts,:expires_at,
             :created_by_user_id)'
    )->execute([
        'job_uuid' => $jobUuid,
        'connection_id' => $connectionId,
        'receptionist_session_id' => ($receptionistSessionId ?? 0) > 0 ? $receptionistSessionId : null,
        'voice_session_id' => ($voiceSessionId ?? 0) > 0 ? $voiceSessionId : null,
        'job_type' => $jobType,
        'priority' => $priority === 'high' ? 'high' : 'normal',
        'payload_ciphertext' => $encrypted['ciphertext'],
        'payload_iv' => $encrypted['iv'],
        'payload_tag' => $encrypted['tag'],
        'payload_hash' => $encrypted['hash'],
        'input_artifact_id' => ($inputArtifactId ?? 0) > 0 ? $inputArtifactId : null,
        'max_attempts' => $maxAttempts,
        'expires_at' => $expiresAt,
        'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
    ]);
    $jobId = (int)db()->lastInsertId();
    if (($inputArtifactId ?? 0) > 0) {
        db()->prepare(
            'UPDATE pod_homeserver_voice_artifacts SET job_id=:job_id WHERE id=:artifact_id'
        )->execute(['job_id' => $jobId, 'artifact_id' => $inputArtifactId]);
    }
    pod_homeserver_receipt($connectionId, 'job_queued', $jobId, 'queued', [
        'job_uuid' => $jobUuid,
        'job_type' => $jobType,
        'expires_at' => $expiresAt,
    ]);
    log_activity('pod_homeserver_voice_job_queued', 'pod_homeserver_voice_job', $jobId, [
        'job_type' => $jobType,
        'connection_id' => $connectionId,
    ]);
    return pod_homeserver_job($jobId) ?? ['id' => $jobId, 'job_uuid' => $jobUuid];
}

function pod_homeserver_queued_job_count(int $connectionId): int
{
    $statement = db()->prepare(
        'SELECT COUNT(*) FROM pod_homeserver_voice_jobs
         WHERE connection_id=:connection_id AND status="queued" AND expires_at>=UTC_TIMESTAMP()'
    );
    $statement->execute(['connection_id' => $connectionId]);
    return (int)$statement->fetchColumn();
}

function pod_homeserver_recover_jobs(int $connectionId): void
{
    db()->prepare(
        'UPDATE pod_homeserver_voice_jobs
         SET status="expired",lease_token_hash=NULL,lease_expires_at=NULL,
             failure_code="job_expired",failure_message="The provider job expired before completion."
         WHERE connection_id=:connection_id
           AND status IN ("queued","leased","processing")
           AND expires_at<UTC_TIMESTAMP()'
    )->execute(['connection_id' => $connectionId]);
    db()->prepare(
        'UPDATE pod_homeserver_voice_jobs
         SET status=CASE WHEN attempt_count<max_attempts THEN "queued" ELSE "failed" END,
             lease_token_hash=NULL,lease_expires_at=NULL,
             failure_code=CASE WHEN attempt_count<max_attempts THEN NULL ELSE "lease_expired" END,
             failure_message=CASE WHEN attempt_count<max_attempts THEN NULL ELSE "The HomeServer job lease expired." END
         WHERE connection_id=:connection_id
           AND status IN ("leased","processing")
           AND lease_expires_at<UTC_TIMESTAMP()
           AND expires_at>=UTC_TIMESTAMP()'
    )->execute(['connection_id' => $connectionId]);
}

function pod_homeserver_poll_job(array $connection): ?array
{
    pod_homeserver_require_capability($connection, 'pod.voice.jobs.v1');
    $connectionId = (int)$connection['id'];
    pod_homeserver_cleanup_artifacts();
    pod_homeserver_recover_jobs($connectionId);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'SELECT * FROM pod_homeserver_voice_jobs
             WHERE connection_id=:connection_id AND status="queued"
               AND expires_at>=UTC_TIMESTAMP()
             ORDER BY FIELD(priority,"high","normal"),queued_at,id
             LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['connection_id' => $connectionId]);
        $job = $statement->fetch();
        if (!$job) {
            $pdo->commit();
            return null;
        }
        pod_homeserver_require_capability($connection, pod_homeserver_job_capability((string)$job['job_type']));
        $leaseToken = bin2hex(random_bytes(32));
        $leaseHash = hash('sha256', $leaseToken);
        $leaseSeconds = max(60, min(1800, (int)(pod_homeserver_config()['lease_seconds'] ?? 300)));
        $leaseExpiresAt = gmdate('Y-m-d H:i:s', time() + $leaseSeconds);
        $pdo->prepare(
            'UPDATE pod_homeserver_voice_jobs
             SET status="leased",lease_token_hash=:lease_token_hash,
                 lease_expires_at=:lease_expires_at,leased_at=UTC_TIMESTAMP(),
                 attempt_count=attempt_count+1
             WHERE id=:id'
        )->execute([
            'lease_token_hash' => $leaseHash,
            'lease_expires_at' => $leaseExpiresAt,
            'id' => (int)$job['id'],
        ]);
        $pdo->commit();
        $job['lease_token_hash'] = $leaseHash;
        $job['lease_expires_at'] = $leaseExpiresAt;
        $job['attempt_count'] = (int)$job['attempt_count'] + 1;
        $payload = pod_homeserver_decrypt_json(
            $job,
            'pod-homeserver-job|' . (string)$job['job_uuid'],
            'payload_'
        );
        if (!$payload && (string)$job['payload_hash'] !== hash('sha256', '[]')) {
            throw new RuntimeException('The encrypted HomeServer voice job payload could not be read.');
        }
        $inputArtifact = null;
        if ((int)($job['input_artifact_id'] ?? 0) > 0) {
            $inputArtifact = pod_homeserver_artifact((int)$job['input_artifact_id']);
            if ($inputArtifact) {
                $inputArtifact = [
                    'artifact_uuid' => (string)$inputArtifact['artifact_uuid'],
                    'mime_type' => (string)$inputArtifact['mime_type'],
                    'plaintext_bytes' => (int)$inputArtifact['plaintext_bytes'],
                    'content_hash' => (string)$inputArtifact['content_hash'],
                    'read_endpoint' => pod_homeserver_endpoint_contract()['voice_artifact_read'],
                ];
            }
        }
        pod_homeserver_receipt($connectionId, 'job_leased', (int)$job['id'], 'leased', [
            'job_uuid' => (string)$job['job_uuid'],
            'lease_expires_at' => $leaseExpiresAt,
            'attempt_count' => (int)$job['attempt_count'],
        ]);
        return [
            'job_uuid' => (string)$job['job_uuid'],
            'job_type' => (string)$job['job_type'],
            'priority' => (string)$job['priority'],
            'payload' => $payload,
            'payload_hash' => (string)$job['payload_hash'],
            'input_artifact' => $inputArtifact,
            'lease_token' => $leaseToken,
            'lease_expires_at' => gmdate('c', strtotime($leaseExpiresAt)),
            'attempt_count' => (int)$job['attempt_count'],
            'max_attempts' => (int)$job['max_attempts'],
            'expires_at' => gmdate('c', strtotime((string)$job['expires_at'])),
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function pod_homeserver_job(int $jobId): ?array
{
    if ($jobId <= 0) return null;
    $statement = db()->prepare(
        'SELECT job.*,connection.device_display_name,connection.connection_uuid
         FROM pod_homeserver_voice_jobs job
         JOIN pod_homeserver_connections connection ON connection.id=job.connection_id
         WHERE job.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $jobId]);
    return $statement->fetch() ?: null;
}

function pod_homeserver_job_by_uuid(string $jobUuid): ?array
{
    if (!pod_message_valid_uuid($jobUuid)) return null;
    $statement = db()->prepare(
        'SELECT job.*,connection.device_display_name,connection.connection_uuid
         FROM pod_homeserver_voice_jobs job
         JOIN pod_homeserver_connections connection ON connection.id=job.connection_id
         WHERE job.job_uuid=:job_uuid LIMIT 1'
    );
    $statement->execute(['job_uuid' => $jobUuid]);
    return $statement->fetch() ?: null;
}

function pod_homeserver_validate_job_lease(array $connection, string $jobUuid, string $leaseToken): array
{
    $job = pod_homeserver_job_by_uuid($jobUuid);
    if (!$job || (int)$job['connection_id'] !== (int)$connection['id']) {
        throw new RuntimeException('The HomeServer voice job was not found for this connection.');
    }
    if (!in_array((string)$job['status'], ['leased','processing'], true)) {
        throw new RuntimeException('The HomeServer voice job is not actively leased.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $leaseToken)) {
        throw new RuntimeException('The HomeServer voice job lease token is invalid.');
    }
    if (
        empty($job['lease_token_hash'])
        || !hash_equals((string)$job['lease_token_hash'], hash('sha256', $leaseToken))
    ) {
        throw new RuntimeException('The HomeServer voice job lease token does not match.');
    }
    if (empty($job['lease_expires_at']) || strtotime((string)$job['lease_expires_at']) < time()) {
        throw new RuntimeException('The HomeServer voice job lease expired.');
    }
    return $job;
}

function pod_homeserver_complete_job(array $connection, array $payload): array
{
    $jobUuid = trim((string)($payload['job_uuid'] ?? ''));
    $leaseToken = strtolower(trim((string)($payload['lease_token'] ?? '')));
    $job = pod_homeserver_validate_job_lease($connection, $jobUuid, $leaseToken);
    $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
    $jobType = (string)$job['job_type'];
    $outputArtifactId = null;

    if ($jobType === 'speech_to_text') {
        $transcript = trim((string)($result['transcript'] ?? ''));
        if ($transcript === '' || strlen($transcript) > 12000) {
            throw new RuntimeException('A bounded speech-to-text transcript is required.');
        }
        $result = [
            'transcript' => $transcript,
            'language' => substr(trim((string)($result['language'] ?? '')), 0, 20),
            'confidence' => max(0, min(1, (float)($result['confidence'] ?? 0))),
            'model' => substr(trim((string)($result['model'] ?? '')), 0, 190),
            'processing_ms' => max(0, min(3600000, (int)($result['processing_ms'] ?? 0))),
        ];
    } elseif ($jobType === 'text_to_speech') {
        $audioBase64 = (string)($result['audio_base64'] ?? '');
        $audio = base64_decode($audioBase64, true);
        $mime = strtolower(trim((string)($result['mime_type'] ?? '')));
        $allowed = ['audio/mpeg','audio/wav','audio/ogg','audio/webm'];
        if (!is_string($audio) || $audio === '' || !in_array($mime, $allowed, true)) {
            throw new RuntimeException('A supported bounded text-to-speech audio result is required.');
        }
        $maxBytes = max(256 * 1024, min(16 * 1024 * 1024, (int)(pod_homeserver_config()['max_audio_bytes'] ?? 8 * 1024 * 1024)));
        if (strlen($audio) > $maxBytes) throw new RuntimeException('The text-to-speech audio result is too large.');
        $artifact = pod_homeserver_store_artifact(
            (int)$connection['id'],
            (int)$job['id'],
            'output',
            'audio',
            $mime,
            $audio
        );
        $outputArtifactId = (int)$artifact['id'];
        $result = [
            'artifact_uuid' => (string)$artifact['artifact_uuid'],
            'mime_type' => $mime,
            'plaintext_bytes' => (int)$artifact['plaintext_bytes'],
            'content_hash' => (string)$artifact['content_hash'],
            'model' => substr(trim((string)($result['model'] ?? '')), 0, 190),
            'processing_ms' => max(0, min(3600000, (int)($result['processing_ms'] ?? 0))),
        ];
    } else {
        $result = [
            'runtime' => substr(trim((string)($result['runtime'] ?? '')), 0, 190),
            'models' => array_slice(array_values(array_filter(array_map('strval', (array)($result['models'] ?? [])))), 0, 50),
            'transcription_ready' => !empty($result['transcription_ready']),
            'synthesis_ready' => !empty($result['synthesis_ready']),
            'details' => substr(trim((string)($result['details'] ?? '')), 0, 700),
        ];
    }

    $encrypted = pod_homeserver_encrypt_json($result, 'pod-homeserver-result|' . $jobUuid);
    db()->prepare(
        'UPDATE pod_homeserver_voice_jobs
         SET status="completed",result_ciphertext=:result_ciphertext,
             result_iv=:result_iv,result_tag=:result_tag,result_hash=:result_hash,
             output_artifact_id=:output_artifact_id,lease_token_hash=NULL,
             lease_expires_at=NULL,completed_at=UTC_TIMESTAMP(),
             failure_code=NULL,failure_message=NULL
         WHERE id=:id'
    )->execute([
        'result_ciphertext' => $encrypted['ciphertext'],
        'result_iv' => $encrypted['iv'],
        'result_tag' => $encrypted['tag'],
        'result_hash' => $encrypted['hash'],
        'output_artifact_id' => $outputArtifactId,
        'id' => (int)$job['id'],
    ]);
    $receipt = pod_homeserver_receipt(
        (int)$connection['id'],
        'job_completed',
        (int)$job['id'],
        'completed',
        ['job_uuid' => $jobUuid, 'job_type' => $jobType, 'result_hash' => $encrypted['hash']]
    );
    log_activity('pod_homeserver_voice_job_completed', 'pod_homeserver_voice_job', (int)$job['id'], [
        'job_type' => $jobType,
        'connection_id' => (int)$connection['id'],
    ]);
    return ['receipt_id' => $receipt, 'job_uuid' => $jobUuid, 'status' => 'completed', 'result_hash' => $encrypted['hash']];
}

function pod_homeserver_fail_job(array $connection, array $payload): array
{
    $jobUuid = trim((string)($payload['job_uuid'] ?? ''));
    $leaseToken = strtolower(trim((string)($payload['lease_token'] ?? '')));
    $job = pod_homeserver_validate_job_lease($connection, $jobUuid, $leaseToken);
    $code = preg_replace('/[^a-z0-9_.-]+/i', '_', trim((string)($payload['failure_code'] ?? 'voice_runtime_failed')));
    $code = substr((string)$code, 0, 100) ?: 'voice_runtime_failed';
    $message = substr(trim((string)($payload['failure_message'] ?? 'The HomeServer voice runtime failed.')), 0, 700);
    $retryable = !empty($payload['retryable']) && (int)$job['attempt_count'] < (int)$job['max_attempts'];
    $status = $retryable && strtotime((string)$job['expires_at']) >= time() ? 'queued' : 'failed';

    db()->prepare(
        'UPDATE pod_homeserver_voice_jobs
         SET status=:status,lease_token_hash=NULL,lease_expires_at=NULL,
             failure_code=:failure_code,failure_message=:failure_message,
             completed_at=CASE WHEN :terminal=1 THEN UTC_TIMESTAMP() ELSE NULL END
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'failure_code' => $code,
        'failure_message' => $message,
        'terminal' => $status === 'failed' ? 1 : 0,
        'id' => (int)$job['id'],
    ]);
    $receipt = pod_homeserver_receipt(
        (int)$connection['id'],
        'job_failed',
        (int)$job['id'],
        $code,
        ['job_uuid' => $jobUuid, 'retryable' => $status === 'queued']
    );
    return ['receipt_id' => $receipt, 'job_uuid' => $jobUuid, 'status' => $status];
}

function pod_homeserver_artifact_directory(): string
{
    $directory = NMM_ROOT . '/storage/pod-homeserver-voice';
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the protected POD HomeServer voice storage directory.');
    }
    return $directory;
}

function pod_homeserver_store_artifact(
    int $connectionId,
    ?int $jobId,
    string $direction,
    string $mediaKind,
    string $mimeType,
    string $plaintext
): array {
    if (!in_array($direction, ['input','output'], true)) throw new RuntimeException('Invalid voice artifact direction.');
    if (!in_array($mediaKind, ['audio','json'], true)) throw new RuntimeException('Invalid voice artifact kind.');
    $maxBytes = max(256 * 1024, min(16 * 1024 * 1024, (int)(pod_homeserver_config()['max_audio_bytes'] ?? 8 * 1024 * 1024)));
    if ($plaintext === '' || strlen($plaintext) > $maxBytes) throw new RuntimeException('The voice artifact is empty or too large.');
    $artifactUuid = pod_uuid_v4();
    $encrypted = pod_homeserver_encrypt_bytes($plaintext, 'pod-homeserver-artifact|' . $artifactUuid);
    $ciphertext = base64_decode($encrypted['ciphertext'], true);
    if (!is_string($ciphertext)) throw new RuntimeException('The encrypted voice artifact could not be encoded.');
    $storedName = $artifactUuid . '-' . bin2hex(random_bytes(8)) . '.bin';
    $path = pod_homeserver_artifact_directory() . '/' . $storedName;
    if (file_put_contents($path, $ciphertext, LOCK_EX) === false) {
        throw new RuntimeException('The encrypted voice artifact could not be stored.');
    }
    @chmod($path, 0660);
    $ttlMinutes = max(5, min(1440, (int)(pod_homeserver_config()['artifact_ttl_minutes'] ?? 60)));
    $expiresAt = gmdate('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
    try {
        db()->prepare(
            'INSERT INTO pod_homeserver_voice_artifacts
                (artifact_uuid,connection_id,job_id,direction,media_kind,mime_type,
                 stored_name,content_hash,plaintext_bytes,encryption_iv,encryption_tag,
                 status,expires_at)
             VALUES
                (:artifact_uuid,:connection_id,:job_id,:direction,:media_kind,:mime_type,
                 :stored_name,:content_hash,:plaintext_bytes,:encryption_iv,:encryption_tag,
                 "active",:expires_at)'
        )->execute([
            'artifact_uuid' => $artifactUuid,
            'connection_id' => $connectionId,
            'job_id' => ($jobId ?? 0) > 0 ? $jobId : null,
            'direction' => $direction,
            'media_kind' => $mediaKind,
            'mime_type' => substr($mimeType, 0, 120),
            'stored_name' => $storedName,
            'content_hash' => hash('sha256', $plaintext),
            'plaintext_bytes' => strlen($plaintext),
            'encryption_iv' => $encrypted['iv'],
            'encryption_tag' => $encrypted['tag'],
            'expires_at' => $expiresAt,
        ]);
    } catch (Throwable $exception) {
        @unlink($path);
        throw $exception;
    }
    $artifactId = (int)db()->lastInsertId();
    pod_homeserver_receipt($connectionId, 'artifact_created', $jobId, 'active', [
        'artifact_uuid' => $artifactUuid,
        'direction' => $direction,
        'plaintext_bytes' => strlen($plaintext),
        'expires_at' => $expiresAt,
    ]);
    return pod_homeserver_artifact($artifactId) ?? ['id' => $artifactId, 'artifact_uuid' => $artifactUuid];
}

function pod_homeserver_artifact(int $artifactId): ?array
{
    if ($artifactId <= 0) return null;
    $statement = db()->prepare(
        'SELECT * FROM pod_homeserver_voice_artifacts WHERE id=:id LIMIT 1'
    );
    $statement->execute(['id' => $artifactId]);
    return $statement->fetch() ?: null;
}

function pod_homeserver_artifact_by_uuid(string $artifactUuid): ?array
{
    if (!pod_message_valid_uuid($artifactUuid)) return null;
    $statement = db()->prepare(
        'SELECT * FROM pod_homeserver_voice_artifacts WHERE artifact_uuid=:uuid LIMIT 1'
    );
    $statement->execute(['uuid' => $artifactUuid]);
    return $statement->fetch() ?: null;
}

function pod_homeserver_read_artifact(array $connection, array $payload): array
{
    pod_homeserver_require_capability($connection, 'pod.voice.artifacts.v1');
    $jobUuid = trim((string)($payload['job_uuid'] ?? ''));
    $leaseToken = strtolower(trim((string)($payload['lease_token'] ?? '')));
    $artifactUuid = trim((string)($payload['artifact_uuid'] ?? ''));
    $job = pod_homeserver_validate_job_lease($connection, $jobUuid, $leaseToken);
    $artifact = pod_homeserver_artifact_by_uuid($artifactUuid);
    if (
        !$artifact
        || (int)$artifact['connection_id'] !== (int)$connection['id']
        || (int)($artifact['job_id'] ?? 0) !== (int)$job['id']
        || (string)$artifact['direction'] !== 'input'
        || (string)$artifact['status'] !== 'active'
        || strtotime((string)$artifact['expires_at']) < time()
    ) {
        throw new RuntimeException('The HomeServer input artifact is unavailable.');
    }
    $path = pod_homeserver_artifact_directory() . '/' . basename((string)$artifact['stored_name']);
    $ciphertext = @file_get_contents($path);
    if (!is_string($ciphertext) || $ciphertext === '') {
        db()->prepare('UPDATE pod_homeserver_voice_artifacts SET status="missing" WHERE id=:id')
            ->execute(['id' => (int)$artifact['id']]);
        throw new RuntimeException('The encrypted HomeServer input artifact is missing.');
    }
    $record = [
        'ciphertext' => base64_encode($ciphertext),
        'iv' => (string)$artifact['encryption_iv'],
        'tag' => (string)$artifact['encryption_tag'],
    ];
    $plaintext = pod_homeserver_decrypt_bytes(
        $record,
        'pod-homeserver-artifact|' . (string)$artifact['artifact_uuid']
    );
    if (
        $plaintext === ''
        || !hash_equals((string)$artifact['content_hash'], hash('sha256', $plaintext))
        || strlen($plaintext) !== (int)$artifact['plaintext_bytes']
    ) {
        throw new RuntimeException('The HomeServer input artifact integrity check failed.');
    }
    db()->prepare(
        'UPDATE pod_homeserver_voice_artifacts
         SET status="consumed",consumed_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute(['id' => (int)$artifact['id']]);
    pod_homeserver_receipt((int)$connection['id'], 'artifact_consumed', (int)$job['id'], 'consumed', [
        'artifact_uuid' => $artifactUuid,
    ]);
    return [
        'artifact_uuid' => $artifactUuid,
        'mime_type' => (string)$artifact['mime_type'],
        'content_hash' => (string)$artifact['content_hash'],
        'plaintext_bytes' => (int)$artifact['plaintext_bytes'],
        'content_base64' => base64_encode($plaintext),
    ];
}

function pod_homeserver_cleanup_artifacts(): int
{
    if (!pod_homeserver_schema_available()) return 0;
    $statement = db()->query(
        'SELECT * FROM pod_homeserver_voice_artifacts
         WHERE status IN ("active","consumed") AND expires_at<UTC_TIMESTAMP()
         ORDER BY id LIMIT 100'
    );
    $count = 0;
    foreach ($statement->fetchAll() as $artifact) {
        $path = NMM_ROOT . '/storage/pod-homeserver-voice/' . basename((string)$artifact['stored_name']);
        if (is_file($path)) @unlink($path);
        db()->prepare(
            'UPDATE pod_homeserver_voice_artifacts
             SET status="expired",deleted_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute(['id' => (int)$artifact['id']]);
        pod_homeserver_receipt(
            (int)$artifact['connection_id'],
            'artifact_deleted',
            (int)($artifact['job_id'] ?? 0) ?: null,
            'expired',
            ['artifact_uuid' => (string)$artifact['artifact_uuid']]
        );
        $count++;
    }
    return $count;
}

function pod_homeserver_revoke_connection(int $connectionId, int $actorUserId): void
{
    pod_homeserver_require_schema();
    $connection = pod_homeserver_connection($connectionId);
    if (!$connection) throw new RuntimeException('The HomeServer connection was not found.');
    db()->prepare(
        'UPDATE pod_homeserver_connections
         SET lifecycle_state="revoked",revoked_at=UTC_TIMESTAMP(),
             last_error_code="provider_revoked",last_error_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute(['id' => $connectionId]);
    db()->prepare(
        'UPDATE pod_homeserver_voice_jobs
         SET status="cancelled",lease_token_hash=NULL,lease_expires_at=NULL,
             failure_code="connection_revoked",failure_message="The provider connection was revoked."
         WHERE connection_id=:connection_id
           AND status IN ("queued","leased","processing")'
    )->execute(['connection_id' => $connectionId]);
    pod_homeserver_receipt($connectionId, 'connection_revoked', null, 'provider_revoked', [
        'actor_user_id' => $actorUserId,
    ]);
    log_activity('pod_homeserver_connection_revoked', 'pod_homeserver_connection', $connectionId);
}

function pod_homeserver_jobs(int $limit = 100): array
{
    if (!pod_homeserver_schema_available()) return [];
    $limit = max(1, min(250, $limit));
    return db()->query(
        'SELECT job.*,connection.device_display_name,connection.connection_uuid
         FROM pod_homeserver_voice_jobs job
         JOIN pod_homeserver_connections connection ON connection.id=job.connection_id
         ORDER BY job.created_at DESC,job.id DESC LIMIT ' . $limit
    )->fetchAll();
}

function pod_homeserver_receipts(int $limit = 100): array
{
    if (!pod_homeserver_schema_available()) return [];
    $limit = max(1, min(250, $limit));
    return db()->query(
        'SELECT receipt.*,connection.device_display_name,job.job_uuid
         FROM pod_homeserver_voice_receipts receipt
         JOIN pod_homeserver_connections connection ON connection.id=receipt.connection_id
         LEFT JOIN pod_homeserver_voice_jobs job ON job.id=receipt.job_id
         ORDER BY receipt.id DESC LIMIT ' . $limit
    )->fetchAll();
}

function pod_homeserver_result(int $jobId): array
{
    $job = pod_homeserver_job($jobId);
    if (!$job || empty($job['result_ciphertext'])) return [];
    return pod_homeserver_decrypt_json(
        $job,
        'pod-homeserver-result|' . (string)$job['job_uuid'],
        'result_'
    );
}

function pod_homeserver_discovery(array $document): array
{
    $document['capabilities']['homeserver_voice_provider'] = [
        'version' => '1.0',
        'enabled' => pod_homeserver_enabled(),
        'provider_key' => 'pod',
        'status' => 'provider_foundation',
        'pairing' => 'one_time_sync_code',
        'authentication' => 'bearer_plus_ed25519_signed_requests',
        'capabilities' => pod_homeserver_supported_capabilities(),
        'endpoints' => pod_homeserver_endpoint_contract(),
        'raw_credentials_in_database' => false,
        'voice_payload_encryption_at_rest' => true,
        'artifact_expiration' => true,
        'coordinated_homeserver_adapter_required' => true,
    ];
    return $document;
}

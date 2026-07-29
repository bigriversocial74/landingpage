<?php
declare(strict_types=1);

/**
 * VP3.me POD licensing adapter v64.
 *
 * This module is intentionally centralized. Public pages do not depend on an
 * online licensing request, and no customer content is deleted by any license
 * transition. Premium/admin callers query Vp3EntitlementService explicitly.
 */

final class Vp3Crypto
{
    public static function localKey(): string
    {
        $security = nmm_config('security');
        $app = nmm_config('app');
        $source = (string)(
            $security['vp3_license_local_secret']
            ?? $security['data_encryption_key']
            ?? $app['setup_token']
            ?? ''
        );
        if ($source === '') {
            throw new RuntimeException('VP3 local encryption secret is not configured.');
        }
        return hash('sha256', 'vp3-pod-license-v64|' . $source, true);
    }

    public static function encrypt(string $plaintext): array
    {
        if ($plaintext === '') {
            return ['ciphertext' => '', 'iv' => '', 'tag' => ''];
        }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL is required for VP3 credential storage.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            self::localKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'vp3-pod-license-v64'
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt VP3 licensing data.');
        }
        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
        ];
    }

    public static function decrypt(string $ciphertext, string $iv, string $tag): string
    {
        if ($ciphertext === '') {
            return '';
        }
        $decodedCipher = base64_decode($ciphertext, true);
        $decodedIv = base64_decode($iv, true);
        $decodedTag = base64_decode($tag, true);
        if ($decodedCipher === false || $decodedIv === false || $decodedTag === false) {
            throw new RuntimeException('Stored VP3 licensing data is invalid.');
        }
        $plaintext = openssl_decrypt(
            $decodedCipher,
            'aes-256-gcm',
            self::localKey(),
            OPENSSL_RAW_DATA,
            $decodedIv,
            $decodedTag,
            'vp3-pod-license-v64'
        );
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt VP3 licensing data.');
        }
        return $plaintext;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url value.');
        }
        return $decoded;
    }
}

final class Vp3DeploymentIdentity
{
    private array $config;

    public function __construct()
    {
        $this->config = nmm_config('vp3_licensing');
    }

    public function providerId(): string
    {
        return $this->bounded((string)($this->config['provider_id'] ?? 'vp3'), 40);
    }

    public function providerName(): string
    {
        return $this->bounded((string)($this->config['provider_name'] ?? 'VP3.me'), 120);
    }

    public function baseUrl(): string
    {
        $value = rtrim((string)($this->config['provider_base_url'] ?? 'https://vp3.me'), '/');
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('VP3 provider base URL is invalid.');
        }
        $scheme = strtolower((string)$parts['scheme']);
        $host = strtolower((string)$parts['host']);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($local && $scheme === 'http')) {
            throw new RuntimeException('VP3 provider must use HTTPS outside local tests.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('VP3 provider URL must not contain credentials, query, or fragment.');
        }
        return $value;
    }

    public function apiVersion(): string
    {
        return $this->bounded((string)($this->config['api_version'] ?? 'v1'), 20);
    }

    public function accountId(): string
    {
        return $this->bounded((string)($this->config['account_public_id'] ?? ''), 120);
    }

    public function domainRegistrationId(): string
    {
        return $this->bounded((string)($this->config['domain_registration_id'] ?? ''), 120);
    }

    public function domain(): string
    {
        $configured = strtolower(trim((string)($this->config['domain'] ?? '')));
        if ($configured !== '') {
            return $this->bounded($configured, 255);
        }
        $base = (string)(nmm_config('app')['base_url'] ?? '');
        $host = parse_url($base, PHP_URL_HOST);
        return $this->bounded(strtolower((string)$host), 255);
    }

    public function licenseId(): string
    {
        return $this->bounded((string)($this->config['license_public_id'] ?? ''), 120);
    }

    public function deploymentId(): string
    {
        return $this->bounded((string)($this->config['deployment_id'] ?? ''), 120);
    }

    public function tokenVersion(): int
    {
        return max(1, (int)($this->config['token_version'] ?? 1));
    }

    public function installedVersion(): string
    {
        $configured = trim((string)($this->config['installed_version'] ?? ''));
        if ($configured !== '') {
            return $this->bounded($configured, 40);
        }
        if (defined('NMM_BUILD_VERSION')) {
            return $this->bounded((string)NMM_BUILD_VERSION, 40);
        }
        return '64.0.0';
    }

    public function installationFingerprint(): string
    {
        $configured = trim((string)($this->config['installation_fingerprint'] ?? ''));
        if ($configured !== '') {
            return $this->bounded($configured, 190);
        }

        $directory = NMM_ROOT . '/storage/vp3-license';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create VP3 licensing storage.');
        }
        $seedFile = $directory . '/installation.seed';
        if (!is_file($seedFile)) {
            $seed = Vp3Crypto::base64UrlEncode(random_bytes(32));
            if (file_put_contents($seedFile, $seed, LOCK_EX) === false) {
                throw new RuntimeException('Unable to create the installation fingerprint seed.');
            }
            @chmod($seedFile, 0640);
        }
        $seed = trim((string)file_get_contents($seedFile));
        if ($seed === '') {
            throw new RuntimeException('Installation fingerprint seed is unavailable.');
        }
        $database = nmm_config('database');
        $signals = [
            $this->domain(),
            (string)($database['host'] ?? ''),
            (string)($database['name'] ?? ''),
            (string)(gethostname() ?: ''),
            (string)(realpath(NMM_ROOT) ?: NMM_ROOT),
        ];
        return 'pod_' . substr(hash_hmac('sha256', implode("\0", $signals), $seed), 0, 56);
    }

    public function missingFields(): array
    {
        $required = [
            'account_public_id' => $this->accountId(),
            'domain_registration_id' => $this->domainRegistrationId(),
            'domain' => $this->domain(),
            'license_public_id' => $this->licenseId(),
            'deployment_id' => $this->deploymentId(),
        ];
        return array_keys(array_filter($required, static fn(string $value): bool => $value === ''));
    }

    public function asValidationPayload(): array
    {
        return [
            'license_public_id' => $this->licenseId(),
            'account_public_id' => $this->accountId(),
            'domain_registration_id' => $this->domainRegistrationId(),
            'domain' => $this->domain(),
            'deployment_id' => $this->deploymentId(),
            'installation_fingerprint' => $this->installationFingerprint(),
            'installed_version' => $this->installedVersion(),
            'token_version' => $this->tokenVersion(),
        ];
    }

    public function synchronizeConfiguration(): void
    {
        $statement = db()->prepare(
            'INSERT INTO vp3_license_configuration
                (id,provider_id,provider_name,provider_base_url,provider_api_version,
                 account_public_id,domain_registration_public_id,domain_hostname,
                 license_public_id,deployment_public_id,installation_fingerprint,
                 entitlement_token_version)
             VALUES
                (1,:provider_id,:provider_name,:base_url,:api_version,
                 :account_id,:domain_registration_id,:domain,
                 :license_id,:deployment_id,:fingerprint,:token_version)
             ON DUPLICATE KEY UPDATE
                provider_id=VALUES(provider_id),provider_name=VALUES(provider_name),
                provider_base_url=VALUES(provider_base_url),
                provider_api_version=VALUES(provider_api_version),
                account_public_id=VALUES(account_public_id),
                domain_registration_public_id=VALUES(domain_registration_public_id),
                domain_hostname=VALUES(domain_hostname),
                license_public_id=VALUES(license_public_id),
                deployment_public_id=VALUES(deployment_public_id),
                installation_fingerprint=VALUES(installation_fingerprint),
                entitlement_token_version=VALUES(entitlement_token_version)'
        );
        $statement->execute([
            'provider_id' => $this->providerId(),
            'provider_name' => $this->providerName(),
            'base_url' => $this->baseUrl(),
            'api_version' => $this->apiVersion(),
            'account_id' => $this->accountId() ?: null,
            'domain_registration_id' => $this->domainRegistrationId() ?: null,
            'domain' => $this->domain() ?: null,
            'license_id' => $this->licenseId() ?: null,
            'deployment_id' => $this->deploymentId() ?: null,
            'fingerprint' => $this->installationFingerprint(),
            'token_version' => $this->tokenVersion(),
        ]);
    }

    private function bounded(string $value, int $limit): string
    {
        return mb_substr(trim($value), 0, $limit);
    }
}

final class Vp3CredentialStore
{
    private Vp3DeploymentIdentity $identity;

    public function __construct(Vp3DeploymentIdentity $identity)
    {
        $this->identity = $identity;
    }

    public function bootstrapFromConfiguration(): void
    {
        $config = nmm_config('vp3_licensing');
        $credential = trim((string)($config['deployment_credential'] ?? ''));
        if ($credential === '') {
            return;
        }
        $version = max(1, (int)($config['credential_version'] ?? 1));
        $existing = db()->prepare('SELECT id FROM vp3_deployment_credentials WHERE credential_version=:version LIMIT 1');
        $existing->execute(['version' => $version]);
        if ((int)($existing->fetchColumn() ?: 0) > 0) {
            return;
        }
        $this->store($credential, $version);
    }

    public function current(): array
    {
        $this->bootstrapFromConfiguration();
        $row = db()->query(
            'SELECT credential_version,credential_ciphertext,credential_iv,credential_tag,credential_hint
             FROM vp3_deployment_credentials
             WHERE status="active"
             ORDER BY credential_version DESC,id DESC
             LIMIT 1'
        )->fetch();
        if (!is_array($row)) {
            return ['credential' => '', 'version' => 0, 'hint' => ''];
        }
        return [
            'credential' => Vp3Crypto::decrypt(
                (string)$row['credential_ciphertext'],
                (string)$row['credential_iv'],
                (string)$row['credential_tag']
            ),
            'version' => (int)$row['credential_version'],
            'hint' => (string)$row['credential_hint'],
        ];
    }

    public function store(string $credential, int $version): void
    {
        $credential = trim($credential);
        if (strlen($credential) < 32 || strlen($credential) > 512) {
            throw new RuntimeException('VP3 deployment credential length is invalid.');
        }
        $encrypted = Vp3Crypto::encrypt($credential);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE vp3_deployment_credentials SET status="rotated",rotated_at=UTC_TIMESTAMP() WHERE status="active"');
            $statement = $pdo->prepare(
                'INSERT INTO vp3_deployment_credentials
                    (credential_version,credential_ciphertext,credential_iv,credential_tag,credential_hint,status)
                 VALUES
                    (:version,:ciphertext,:iv,:tag,:hint,"active")
                 ON DUPLICATE KEY UPDATE
                    credential_ciphertext=VALUES(credential_ciphertext),
                    credential_iv=VALUES(credential_iv),credential_tag=VALUES(credential_tag),
                    credential_hint=VALUES(credential_hint),status="active",
                    activated_at=UTC_TIMESTAMP(),rotated_at=NULL,revoked_at=NULL'
            );
            $statement->execute([
                'version' => max(1, $version),
                'ciphertext' => $encrypted['ciphertext'],
                'iv' => $encrypted['iv'],
                'tag' => $encrypted['tag'],
                'hint' => substr($credential, 0, 6) . '…' . substr($credential, -4),
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}

final class Vp3LicenseAuditLogger
{
    public function receipt(
        string $type,
        string $outcome,
        ?string $code = null,
        ?string $status = null,
        array $metadata = [],
        ?string $requestId = null,
        ?string $responseHash = null,
        ?int $latencyMs = null,
        string $networkState = 'not_required'
    ): void {
        $allowedTypes = ['validate','heartbeat','token_rotate','jwks_refresh','offline_lease','storage_check','update_check','configuration'];
        $allowedOutcomes = ['success','warning','denied','error'];
        $allowedStatuses = ['active','grace','suspended','expired','terminated','unknown'];
        $allowedNetworks = ['online','offline','not_required'];
        $statement = db()->prepare(
            'INSERT INTO vp3_license_validation_receipts
                (receipt_uuid,request_id,validation_type,outcome,status_code,license_status,
                 response_hash,latency_ms,network_state,metadata_json)
             VALUES
                (:receipt_uuid,:request_id,:validation_type,:outcome,:status_code,:license_status,
                 :response_hash,:latency_ms,:network_state,:metadata_json)'
        );
        $statement->execute([
            'receipt_uuid' => $this->uuid(),
            'request_id' => $requestId,
            'validation_type' => in_array($type, $allowedTypes, true) ? $type : 'configuration',
            'outcome' => in_array($outcome, $allowedOutcomes, true) ? $outcome : 'error',
            'status_code' => $code !== null ? mb_substr($code, 0, 100) : null,
            'license_status' => $status !== null && in_array($status, $allowedStatuses, true) ? $status : null,
            'response_hash' => $responseHash,
            'latency_ms' => $latencyMs,
            'network_state' => in_array($networkState, $allowedNetworks, true) ? $networkState : 'not_required',
            'metadata_json' => $metadata === [] ? null : json_encode($this->redact($metadata), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function event(
        string $eventType,
        ?string $previousStatus,
        ?string $currentStatus,
        ?string $planCode,
        string $actorType = 'system',
        ?int $actorUserId = null,
        array $metadata = []
    ): void {
        $statement = db()->prepare(
            'INSERT INTO vp3_license_events
                (event_uuid,event_type,previous_status,current_status,plan_code,
                 actor_type,actor_user_id,metadata_json)
             VALUES
                (:uuid,:event_type,:previous_status,:current_status,:plan_code,
                 :actor_type,:actor_user_id,:metadata_json)'
        );
        $statement->execute([
            'uuid' => $this->uuid(),
            'event_type' => mb_substr($eventType, 0, 100),
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
            'plan_code' => $planCode !== null ? mb_substr($planCode, 0, 80) : null,
            'actor_type' => in_array($actorType, ['system','administrator','provider'], true) ? $actorType : 'system',
            'actor_user_id' => $actorUserId,
            'metadata_json' => $metadata === [] ? null : json_encode($this->redact($metadata), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function redact(array $metadata): array
    {
        $sensitive = ['authorization','credential','deployment_credential','token','entitlement_token','private_key','signature','customer_content','prompt','conversation'];
        $redacted = [];
        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string)$key);
            if (in_array($normalized, $sensitive, true)) {
                $redacted[$key] = '[redacted]';
                continue;
            }
            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }
        return $redacted;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

final class Vp3LicenseCache
{
    public function current(): ?array
    {
        $row = db()->query(
            'SELECT * FROM vp3_entitlement_cache
             WHERE is_current=1
             ORDER BY validated_at DESC,id DESC
             LIMIT 1'
        )->fetch();
        if (!is_array($row)) {
            return null;
        }
        $payload = json_decode((string)$row['entitlement_json'], true);
        if (!is_array($payload)) {
            return null;
        }
        $payload['_cache'] = [
            'validated_at' => (string)$row['validated_at'],
            'expires_at' => (string)$row['expires_at'],
            'offline_lease_expires_at' => (string)$row['offline_lease_expires_at'],
            'token_hash' => (string)$row['token_hash'],
            'signing_key_id' => (string)$row['signing_key_id'],
        ];
        return $payload;
    }

    public function store(string $signedToken, array $payload): void
    {
        $jti = trim((string)($payload['jti'] ?? ''));
        $kid = trim((string)($payload['signing_key_id'] ?? $payload['_header']['kid'] ?? ''));
        if ($jti === '' || $kid === '') {
            throw new RuntimeException('Verified entitlement is missing jti or signing key ID.');
        }
        $issued = $this->timestamp((int)($payload['iat'] ?? 0));
        $notBefore = $this->timestamp((int)($payload['nbf'] ?? 0));
        $expires = $this->timestamp((int)($payload['exp'] ?? 0));
        $offlineSeconds = max(0, min(2592000, (int)($payload['offline_lease_seconds'] ?? 0)));
        $offlineExpires = gmdate('Y-m-d H:i:s', ((int)$payload['exp']) + $offlineSeconds);
        $encrypted = Vp3Crypto::encrypt($signedToken);
        $status = Vp3EntitlementService::normalizeStatus((string)($payload['status'] ?? 'unknown'));
        $plan = mb_substr(trim((string)($payload['plan'] ?? '')), 0, 80);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $previous = $pdo->query('SELECT license_status,plan_code FROM vp3_license_configuration WHERE id=1')->fetch();
            $pdo->exec('UPDATE vp3_entitlement_cache SET is_current=0 WHERE is_current=1');
            $statement = $pdo->prepare(
                'INSERT INTO vp3_entitlement_cache
                    (token_jti,signing_key_id,token_version,signed_token_ciphertext,
                     signed_token_iv,signed_token_tag,token_hash,entitlement_json,
                     entitlements_json,license_status,plan_code,issued_at,not_before_at,
                     expires_at,offline_lease_expires_at,validated_at,is_current)
                 VALUES
                    (:jti,:kid,:version,:ciphertext,:iv,:tag,:token_hash,:payload,
                     :entitlements,:status,:plan,:issued_at,:not_before_at,
                     :expires_at,:offline_expires_at,UTC_TIMESTAMP(),1)
                 ON DUPLICATE KEY UPDATE
                    signing_key_id=VALUES(signing_key_id),token_version=VALUES(token_version),
                    signed_token_ciphertext=VALUES(signed_token_ciphertext),
                    signed_token_iv=VALUES(signed_token_iv),signed_token_tag=VALUES(signed_token_tag),
                    token_hash=VALUES(token_hash),entitlement_json=VALUES(entitlement_json),
                    entitlements_json=VALUES(entitlements_json),license_status=VALUES(license_status),
                    plan_code=VALUES(plan_code),issued_at=VALUES(issued_at),
                    not_before_at=VALUES(not_before_at),expires_at=VALUES(expires_at),
                    offline_lease_expires_at=VALUES(offline_lease_expires_at),
                    validated_at=UTC_TIMESTAMP(),is_current=1'
            );
            $statement->execute([
                'jti' => $jti,
                'kid' => $kid,
                'version' => max(1, (int)($payload['token_version'] ?? 1)),
                'ciphertext' => $encrypted['ciphertext'],
                'iv' => $encrypted['iv'],
                'tag' => $encrypted['tag'],
                'token_hash' => hash('sha256', $signedToken),
                'payload' => json_encode($this->withoutInternalFields($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'entitlements' => json_encode(is_array($payload['entitlements'] ?? null) ? $payload['entitlements'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'plan' => $plan !== '' ? $plan : null,
                'issued_at' => $issued,
                'not_before_at' => $notBefore,
                'expires_at' => $expires,
                'offline_expires_at' => $offlineExpires,
            ]);
            $pdo->prepare(
                'UPDATE vp3_license_configuration
                 SET plan_code=:plan,license_status=:status,
                     entitlement_expires_at=:expires_at,
                     offline_lease_expires_at=:offline_expires_at,
                     renewal_at=:renewal_at,
                     last_successful_validation_at=UTC_TIMESTAMP(),
                     last_validation_attempt_at=UTC_TIMESTAMP(),
                     last_error_code=NULL,last_error_at=NULL
                 WHERE id=1'
            )->execute([
                'plan' => $plan !== '' ? $plan : null,
                'status' => $status,
                'expires_at' => $expires,
                'offline_expires_at' => $offlineExpires,
                'renewal_at' => isset($payload['renewal_at']) ? gmdate('Y-m-d H:i:s', (int)$payload['renewal_at']) : null,
            ]);
            $pdo->commit();
            $logger = new Vp3LicenseAuditLogger();
            $previousStatus = is_array($previous) ? (string)($previous['license_status'] ?? 'unknown') : 'unknown';
            $previousPlan = is_array($previous) ? (string)($previous['plan_code'] ?? '') : '';
            if ($previousStatus !== $status || $previousPlan !== $plan) {
                $logger->event(
                    'license_entitlement_changed',
                    $previousStatus,
                    $status,
                    $plan !== '' ? $plan : null,
                    'provider',
                    null,
                    ['previous_plan' => $previousPlan]
                );
            }
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function timestamp(int $value): string
    {
        if ($value <= 0) {
            throw new RuntimeException('Entitlement timestamp is invalid.');
        }
        return gmdate('Y-m-d H:i:s', $value);
    }

    private function withoutInternalFields(array $payload): array
    {
        unset($payload['_header'], $payload['_cache']);
        return $payload;
    }
}

final class Vp3LicenseClient
{
    private Vp3DeploymentIdentity $identity;
    private Vp3CredentialStore $credentials;

    public function __construct(Vp3DeploymentIdentity $identity, Vp3CredentialStore $credentials)
    {
        $this->identity = $identity;
        $this->credentials = $credentials;
    }

    public function validate(): array
    {
        return $this->request('POST', '/api/' . $this->identity->apiVersion() . '/licenses/validate', $this->identity->asValidationPayload(), 'validate');
    }

    public function heartbeat(): array
    {
        return $this->request('POST', '/api/' . $this->identity->apiVersion() . '/licenses/heartbeat', $this->identity->asValidationPayload(), 'heartbeat');
    }

    public function rotateToken(): array
    {
        return $this->request('POST', '/api/' . $this->identity->apiVersion() . '/licenses/token/rotate', $this->identity->asValidationPayload(), 'token_rotate');
    }

    public function status(): array
    {
        $path = '/api/' . $this->identity->apiVersion() . '/licenses/' . rawurlencode($this->identity->licenseId()) . '/status';
        return $this->request('GET', $path, [], 'validate');
    }

    public function jwks(bool $force = false): array
    {
        $directory = NMM_ROOT . '/storage/vp3-license';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create VP3 JWKS cache directory.');
        }
        $cacheFile = $directory . '/jwks.json';
        $ttl = max(300, min(86400, (int)(nmm_config('vp3_licensing')['jwks_cache_seconds'] ?? 3600)));
        if (!$force && is_file($cacheFile) && (int)filemtime($cacheFile) >= time() - $ttl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && is_array($cached['keys'] ?? null)) {
                return $cached;
            }
        }
        $response = $this->rawRequest('GET', '/api/' . $this->identity->apiVersion() . '/keys/jwks.json', '', [], false);
        $decoded = json_decode((string)$response['body'], true);
        if (!is_array($decoded) || !is_array($decoded['keys'] ?? null)) {
            throw new RuntimeException('VP3 JWKS response is invalid.');
        }
        if (file_put_contents($cacheFile, json_encode($decoded, JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
            @chmod($cacheFile, 0640);
        }
        (new Vp3LicenseAuditLogger())->receipt('jwks_refresh', 'success', 'jwks_refreshed', null, ['key_count' => count($decoded['keys'])], null, hash('sha256', (string)$response['body']), (int)$response['latency_ms'], 'online');
        return $decoded;
    }

    private function request(string $method, string $path, array $payload, string $requestType): array
    {
        $credential = $this->credentials->current();
        if ((string)$credential['credential'] === '') {
            throw new RuntimeException('VP3 deployment credential is not configured.');
        }
        $requestId = $this->uuid();
        $nonce = Vp3Crypto::base64UrlEncode(random_bytes(24));
        $timestamp = time();
        $body = $method === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", [$method, $path, (string)$timestamp, $nonce, $requestId, $bodyHash]);
        $signature = Vp3Crypto::base64UrlEncode(hash_hmac('sha256', $canonical, (string)$credential['credential'], true));
        $this->reserveNonce($nonce, $requestId, $requestType, $timestamp);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: VP3-HMAC ' . $this->identity->deploymentId() . ':' . (int)$credential['version'] . ':' . $signature,
            'X-VP3-Deployment-ID: ' . $this->identity->deploymentId(),
            'X-VP3-Credential-Version: ' . (int)$credential['version'],
            'X-VP3-Timestamp: ' . $timestamp,
            'X-VP3-Nonce: ' . $nonce,
            'X-VP3-Request-ID: ' . $requestId,
            'X-VP3-Signature: ' . $signature,
        ];
        $response = $this->rawRequest($method, $path, $body, $headers, true);
        $decoded = json_decode((string)$response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('VP3 licensing response is not valid JSON.');
        }
        $decoded['_transport'] = [
            'request_id' => $requestId,
            'status' => (int)$response['status'],
            'latency_ms' => (int)$response['latency_ms'],
            'response_hash' => hash('sha256', (string)$response['body']),
        ];
        return $decoded;
    }

    private function rawRequest(string $method, string $path, string $body, array $headers, bool $authenticated): array
    {
        $url = $this->identity->baseUrl() . $path;
        $timeout = max(3, min(60, (int)(nmm_config('vp3_licensing')['request_timeout_seconds'] ?? 12)));
        $maximum = max(65536, min(4194304, (int)(nmm_config('vp3_licensing')['max_response_bytes'] ?? 1048576)));
        $started = microtime(true);
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('Unable to initialize VP3 licensing request.');
            }
            curl_setopt_array($handle, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADER => false,
            ]);
            if ($method !== 'GET') {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            }
            $responseBody = curl_exec($handle);
            $error = curl_error($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if ($responseBody === false) {
                throw new RuntimeException('VP3 licensing request failed: ' . mb_substr($error, 0, 180));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'follow_location' => 0,
                    'max_redirects' => 0,
                    'header' => implode("\r\n", $headers),
                    'content' => $method === 'GET' ? '' : $body,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);
            $responseBody = @file_get_contents($url, false, $context, 0, $maximum + 1);
            if ($responseBody === false) {
                throw new RuntimeException('VP3 licensing request failed.');
            }
            $status = 0;
            foreach (($http_response_header ?? []) as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                    $status = (int)$match[1];
                }
            }
        }
        if (strlen((string)$responseBody) > $maximum) {
            throw new RuntimeException('VP3 licensing response exceeded the configured limit.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('VP3 licensing request returned HTTP ' . $status . '.');
        }
        return [
            'status' => $status,
            'body' => (string)$responseBody,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
            'authenticated' => $authenticated,
        ];
    }

    private function reserveNonce(string $nonce, string $requestId, string $type, int $timestamp): void
    {
        db()->prepare('DELETE FROM vp3_request_nonces WHERE expires_at<UTC_TIMESTAMP()')->execute();
        $statement = db()->prepare(
            'INSERT INTO vp3_request_nonces
                (nonce_hash,request_id,request_type,request_timestamp,expires_at)
             VALUES
                (:nonce_hash,:request_id,:request_type,:request_timestamp,
                 DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE))'
        );
        $statement->execute([
            'nonce_hash' => hash('sha256', $nonce),
            'request_id' => $requestId,
            'request_type' => $type,
            'request_timestamp' => $timestamp,
        ]);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

final class Vp3LicenseVerifier
{
    private Vp3DeploymentIdentity $identity;
    private Vp3LicenseClient $client;

    public function __construct(Vp3DeploymentIdentity $identity, Vp3LicenseClient $client)
    {
        $this->identity = $identity;
        $this->client = $client;
    }

    public function verifyResponse(array $response): array
    {
        $token = trim((string)($response['entitlement_token'] ?? $response['token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('VP3 response does not contain a signed entitlement token.');
        }
        $jwks = $this->client->jwks(false);
        try {
            $payload = self::verifyCompactJws($token, $jwks);
        } catch (RuntimeException $exception) {
            if (!str_contains(strtolower($exception->getMessage()), 'signing key')) {
                throw $exception;
            }
            $payload = self::verifyCompactJws($token, $this->client->jwks(true));
        }
        $this->verifyIdentityClaims($payload);
        return ['token' => $token, 'payload' => $payload];
    }

    public static function verifyCompactJws(string $token, array $jwks, int $clockSkewSeconds = 300): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Entitlement token is not a compact JWS.');
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode(Vp3Crypto::base64UrlDecode($encodedHeader), true);
        $payload = json_decode(Vp3Crypto::base64UrlDecode($encodedPayload), true);
        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('Entitlement token header or payload is invalid.');
        }
        $alg = (string)($header['alg'] ?? '');
        $kid = (string)($header['kid'] ?? $payload['signing_key_id'] ?? '');
        if ($alg === '' || $alg === 'none' || $kid === '') {
            throw new RuntimeException('Entitlement token algorithm or signing key is invalid.');
        }
        $key = null;
        foreach (($jwks['keys'] ?? []) as $candidate) {
            if (is_array($candidate) && hash_equals((string)($candidate['kid'] ?? ''), $kid)) {
                $key = $candidate;
                break;
            }
        }
        if (!is_array($key)) {
            throw new RuntimeException('Entitlement signing key is unknown.');
        }
        if (isset($key['alg']) && (string)$key['alg'] !== '' && !hash_equals((string)$key['alg'], $alg)) {
            throw new RuntimeException('Entitlement signing algorithm does not match the key.');
        }
        $input = $encodedHeader . '.' . $encodedPayload;
        $signature = Vp3Crypto::base64UrlDecode($encodedSignature);
        $valid = match ($alg) {
            'EdDSA' => self::verifyEd25519($input, $signature, $key),
            'RS256' => self::verifyRsaSha256($input, $signature, $key),
            default => throw new RuntimeException('Entitlement signing algorithm is unsupported.'),
        };
        if (!$valid) {
            throw new RuntimeException('Entitlement signature is invalid.');
        }
        $now = time();
        $iat = (int)($payload['iat'] ?? 0);
        $nbf = (int)($payload['nbf'] ?? 0);
        $exp = (int)($payload['exp'] ?? 0);
        if ($iat <= 0 || $nbf <= 0 || $exp <= 0 || $exp <= $iat) {
            throw new RuntimeException('Entitlement timestamps are invalid.');
        }
        if ($iat > $now + $clockSkewSeconds || $nbf > $now + $clockSkewSeconds) {
            throw new RuntimeException('Entitlement is not valid yet.');
        }
        if ($exp < $now - $clockSkewSeconds) {
            throw new RuntimeException('Entitlement token is expired.');
        }
        if (!hash_equals('vp3.me', (string)($payload['iss'] ?? ''))) {
            throw new RuntimeException('Entitlement issuer is invalid.');
        }
        $audience = $payload['aud'] ?? '';
        $audienceValid = is_array($audience)
            ? in_array('pod-platform', array_map('strval', $audience), true)
            : hash_equals('pod-platform', (string)$audience);
        if (!$audienceValid) {
            throw new RuntimeException('Entitlement audience is invalid.');
        }
        $payload['_header'] = $header;
        return $payload;
    }

    private function verifyIdentityClaims(array $payload): void
    {
        $expected = [
            'sub' => $this->identity->licenseId(),
            'account_id' => $this->identity->accountId(),
            'domain_registration_id' => $this->identity->domainRegistrationId(),
            'domain' => $this->identity->domain(),
            'deployment_id' => $this->identity->deploymentId(),
            'installation_fingerprint' => $this->identity->installationFingerprint(),
        ];
        foreach ($expected as $claim => $value) {
            $actual = (string)($payload[$claim] ?? '');
            if ($value === '' || $actual === '' || !hash_equals($value, $actual)) {
                throw new RuntimeException('Entitlement ' . $claim . ' does not match this POD deployment.');
            }
        }
        $status = Vp3EntitlementService::normalizeStatus((string)($payload['status'] ?? 'unknown'));
        if ($status === 'unknown' && (string)($payload['status'] ?? '') !== 'unknown') {
            throw new RuntimeException('Entitlement status is unsupported.');
        }
        if (!is_array($payload['entitlements'] ?? null)) {
            throw new RuntimeException('Entitlement capability document is missing.');
        }
    }

    private static function verifyEd25519(string $input, string $signature, array $key): bool
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('Sodium is required to verify Ed25519 entitlements.');
        }
        if ((string)($key['kty'] ?? '') !== 'OKP' || (string)($key['crv'] ?? '') !== 'Ed25519') {
            throw new RuntimeException('Entitlement Ed25519 key is invalid.');
        }
        $publicKey = Vp3Crypto::base64UrlDecode((string)($key['x'] ?? ''));
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('Entitlement Ed25519 public key length is invalid.');
        }
        return sodium_crypto_sign_verify_detached($signature, $input, $publicKey);
    }

    private static function verifyRsaSha256(string $input, string $signature, array $key): bool
    {
        if ((string)($key['kty'] ?? '') !== 'RSA') {
            throw new RuntimeException('Entitlement RSA key is invalid.');
        }
        $pem = self::rsaJwkToPem((string)($key['n'] ?? ''), (string)($key['e'] ?? ''));
        $result = openssl_verify($input, $signature, $pem, OPENSSL_ALGO_SHA256);
        if ($result === -1) {
            throw new RuntimeException('OpenSSL could not verify the entitlement signature.');
        }
        return $result === 1;
    }

    private static function rsaJwkToPem(string $n, string $e): string
    {
        $modulus = Vp3Crypto::base64UrlDecode($n);
        $exponent = Vp3Crypto::base64UrlDecode($e);
        $rsa = self::derSequence(self::derInteger($modulus) . self::derInteger($exponent));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        if ($algorithm === false) {
            throw new RuntimeException('Unable to construct RSA verification key.');
        }
        $spki = self::derSequence($algorithm . "\x03" . self::derLength(strlen($rsa) + 1) . "\x00" . $rsa);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            $value = "\x00";
        }
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }
        return "\x02" . self::derLength(strlen($value)) . $value;
    }

    private static function derSequence(string $value): string
    {
        return "\x30" . self::derLength(strlen($value)) . $value;
    }

    private static function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff) . $encoded;
            $length >>= 8;
        }
        return chr(0x80 | strlen($encoded)) . $encoded;
    }
}

final class Vp3StorageMeter
{
    public function measure(?int $allowanceBytes): array
    {
        $config = nmm_config('vp3_licensing');
        $configuredPaths = is_array($config['storage_paths'] ?? null)
            ? $config['storage_paths']
            : ['storage'];
        $used = 0;
        $files = 0;
        $measured = [];
        $root = realpath(NMM_ROOT) ?: NMM_ROOT;
        foreach ($configuredPaths as $relative) {
            $relative = trim((string)$relative, '/\\');
            if ($relative === '') {
                continue;
            }
            $candidate = realpath(NMM_ROOT . '/' . $relative);
            if ($candidate === false || !str_starts_with($candidate, $root) || !is_dir($candidate)) {
                continue;
            }
            $measured[] = str_replace('\\', '/', substr($candidate, strlen($root) + 1));
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($candidate, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
                    continue;
                }
                $size = $file->getSize();
                if ($size >= 0) {
                    $used += $size;
                    $files++;
                }
            }
        }
        $percent = $allowanceBytes !== null && $allowanceBytes > 0
            ? ($used / $allowanceBytes) * 100
            : null;
        $state = 'unlicensed';
        if ($percent !== null) {
            $state = match (true) {
                $percent > 100 => 'over_limit',
                $percent >= 100 => 'hard_limit',
                $percent >= 90 => 'warning_90',
                $percent >= 80 => 'warning_80',
                default => 'normal',
            };
        }
        $uuid = $this->uuid();
        db()->prepare(
            'INSERT INTO vp3_storage_usage_snapshots
                (snapshot_uuid,used_bytes,allowance_bytes,usage_percent,warning_state,
                 measured_paths_json,file_count,measured_at)
             VALUES
                (:uuid,:used_bytes,:allowance_bytes,:usage_percent,:warning_state,
                 :paths,:file_count,UTC_TIMESTAMP())'
        )->execute([
            'uuid' => $uuid,
            'used_bytes' => $used,
            'allowance_bytes' => $allowanceBytes,
            'usage_percent' => $percent !== null ? round($percent, 3) : null,
            'warning_state' => $state,
            'paths' => json_encode($measured, JSON_UNESCAPED_SLASHES),
            'file_count' => $files,
        ]);
        return [
            'snapshot_uuid' => $uuid,
            'used_bytes' => $used,
            'allowance_bytes' => $allowanceBytes,
            'usage_percent' => $percent,
            'warning_state' => $state,
            'file_count' => $files,
            'measured_paths' => $measured,
            'can_consume_more' => $allowanceBytes === null || $used < $allowanceBytes,
        ];
    }

    public function latest(?int $allowanceBytes): array
    {
        $row = db()->query('SELECT * FROM vp3_storage_usage_snapshots ORDER BY measured_at DESC,id DESC LIMIT 1')->fetch();
        if (!is_array($row)) {
            return $this->measure($allowanceBytes);
        }
        $used = (int)$row['used_bytes'];
        $percent = $allowanceBytes !== null && $allowanceBytes > 0 ? ($used / $allowanceBytes) * 100 : null;
        $state = $percent === null ? 'unlicensed' : match (true) {
            $percent > 100 => 'over_limit',
            $percent >= 100 => 'hard_limit',
            $percent >= 90 => 'warning_90',
            $percent >= 80 => 'warning_80',
            default => 'normal',
        };
        return [
            'snapshot_uuid' => (string)$row['snapshot_uuid'],
            'used_bytes' => $used,
            'allowance_bytes' => $allowanceBytes,
            'usage_percent' => $percent,
            'warning_state' => $state,
            'file_count' => (int)$row['file_count'],
            'measured_paths' => json_decode((string)($row['measured_paths_json'] ?? '[]'), true) ?: [],
            'measured_at' => (string)$row['measured_at'],
            'can_consume_more' => $allowanceBytes === null || $used < $allowanceBytes,
        ];
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

final class Vp3EntitlementService
{
    private Vp3DeploymentIdentity $identity;
    private Vp3CredentialStore $credentials;
    private Vp3LicenseClient $client;
    private Vp3LicenseVerifier $verifier;
    private Vp3LicenseCache $cache;
    private Vp3LicenseAuditLogger $logger;
    private Vp3StorageMeter $storage;

    public function __construct()
    {
        $this->identity = new Vp3DeploymentIdentity();
        $this->credentials = new Vp3CredentialStore($this->identity);
        $this->client = new Vp3LicenseClient($this->identity, $this->credentials);
        $this->verifier = new Vp3LicenseVerifier($this->identity, $this->client);
        $this->cache = new Vp3LicenseCache();
        $this->logger = new Vp3LicenseAuditLogger();
        $this->storage = new Vp3StorageMeter();
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['active','grace','suspended','expired','terminated','unknown'], true)
            ? $status
            : 'unknown';
    }

    public function initialize(): void
    {
        $this->identity->synchronizeConfiguration();
        $this->credentials->bootstrapFromConfiguration();
    }

    public function validateNow(?int $actorUserId = null): array
    {
        $this->initialize();
        $missing = $this->identity->missingFields();
        if ($missing !== []) {
            $this->logger->receipt('configuration', 'denied', 'vp3_configuration_incomplete', 'unknown', ['missing_fields' => $missing]);
            throw new RuntimeException('VP3 provisioning is incomplete: ' . implode(', ', $missing) . '.');
        }
        $started = microtime(true);
        try {
            $response = $this->client->validate();
            $verified = $this->verifier->verifyResponse($response);
            $this->cache->store($verified['token'], $verified['payload']);
            $transport = is_array($response['_transport'] ?? null) ? $response['_transport'] : [];
            $status = self::normalizeStatus((string)($verified['payload']['status'] ?? 'unknown'));
            $this->logger->receipt(
                'validate',
                'success',
                'entitlement_verified',
                $status,
                ['plan' => (string)($verified['payload']['plan'] ?? '')],
                (string)($transport['request_id'] ?? ''),
                (string)($transport['response_hash'] ?? ''),
                (int)($transport['latency_ms'] ?? round((microtime(true) - $started) * 1000)),
                'online'
            );
            if ($actorUserId !== null) {
                log_activity('vp3_license_validated', 'vp3_license', 1, ['status' => $status]);
            }
            return $this->current();
        } catch (Throwable $exception) {
            db()->prepare(
                'UPDATE vp3_license_configuration
                 SET last_validation_attempt_at=UTC_TIMESTAMP(),last_error_code=:code,last_error_at=UTC_TIMESTAMP()
                 WHERE id=1'
            )->execute(['code' => $this->errorCode($exception->getMessage())]);
            $cached = $this->current();
            $offlineValid = (bool)($cached['offline_lease_valid'] ?? false);
            $this->logger->receipt(
                $offlineValid ? 'offline_lease' : 'validate',
                $offlineValid ? 'warning' : 'error',
                $this->errorCode($exception->getMessage()),
                (string)($cached['status'] ?? 'unknown'),
                ['message' => mb_substr($exception->getMessage(), 0, 300)],
                null,
                null,
                (int)round((microtime(true) - $started) * 1000),
                'offline'
            );
            if ($offlineValid) {
                return $cached;
            }
            throw $exception;
        }
    }

    public function heartbeat(?int $actorUserId = null): array
    {
        $this->initialize();
        $response = $this->client->heartbeat();
        $transport = is_array($response['_transport'] ?? null) ? $response['_transport'] : [];
        db()->prepare('UPDATE vp3_license_configuration SET last_heartbeat_at=UTC_TIMESTAMP(),last_error_code=NULL,last_error_at=NULL WHERE id=1')->execute();
        $current = $this->current();
        $this->logger->receipt(
            'heartbeat',
            'success',
            'heartbeat_accepted',
            (string)$current['status'],
            ['installed_version' => $this->identity->installedVersion()],
            (string)($transport['request_id'] ?? ''),
            (string)($transport['response_hash'] ?? ''),
            (int)($transport['latency_ms'] ?? 0),
            'online'
        );
        if ($actorUserId !== null) {
            log_activity('vp3_license_heartbeat', 'vp3_license', 1);
        }
        return $current;
    }

    public function rotateCredential(?int $actorUserId = null): array
    {
        $this->initialize();
        $response = $this->client->rotateToken();
        $credential = trim((string)($response['deployment_credential'] ?? $response['credential'] ?? ''));
        $version = max(1, (int)($response['credential_version'] ?? 0));
        if ($credential === '' || $version <= 0) {
            throw new RuntimeException('VP3 token rotation response is incomplete.');
        }
        $this->credentials->store($credential, $version);
        $transport = is_array($response['_transport'] ?? null) ? $response['_transport'] : [];
        $current = $this->current();
        $this->logger->receipt(
            'token_rotate',
            'success',
            'deployment_credential_rotated',
            (string)$current['status'],
            ['credential_version' => $version],
            (string)($transport['request_id'] ?? ''),
            (string)($transport['response_hash'] ?? ''),
            (int)($transport['latency_ms'] ?? 0),
            'online'
        );
        $this->logger->event('deployment_credential_rotated', (string)$current['status'], (string)$current['status'], (string)($current['plan'] ?? ''), $actorUserId !== null ? 'administrator' : 'system', $actorUserId, ['credential_version' => $version]);
        return ['version' => $version, 'hint' => substr($credential, 0, 6) . '…' . substr($credential, -4)];
    }

    public function current(): array
    {
        $this->identity->synchronizeConfiguration();
        $configuration = db()->query('SELECT * FROM vp3_license_configuration WHERE id=1')->fetch();
        $payload = $this->cache->current();
        $status = self::normalizeStatus((string)($payload['status'] ?? $configuration['license_status'] ?? 'unknown'));
        $entitlements = is_array($payload['entitlements'] ?? null) ? $payload['entitlements'] : [];
        $offlineExpires = (string)($payload['_cache']['offline_lease_expires_at'] ?? $configuration['offline_lease_expires_at'] ?? '');
        $offlineValid = $offlineExpires !== '' && strtotime($offlineExpires . ' UTC') >= time();
        $tokenExpires = (string)($payload['_cache']['expires_at'] ?? $configuration['entitlement_expires_at'] ?? '');
        $tokenValid = $tokenExpires !== '' && strtotime($tokenExpires . ' UTC') >= time();
        $connectionState = 'not_configured';
        if ($this->identity->missingFields() === []) {
            $connectionState = $tokenValid ? 'online_validated' : ($offlineValid ? 'offline_lease' : 'validation_required');
        }
        return [
            'provider_id' => $this->identity->providerId(),
            'provider_name' => $this->identity->providerName(),
            'provider_base_url' => $this->identity->baseUrl(),
            'account_public_id' => $this->identity->accountId(),
            'domain_registration_id' => $this->identity->domainRegistrationId(),
            'domain' => $this->identity->domain(),
            'license_public_id' => $this->identity->licenseId(),
            'deployment_id' => $this->identity->deploymentId(),
            'installation_fingerprint' => $this->identity->installationFingerprint(),
            'installed_version' => $this->identity->installedVersion(),
            'status' => $status,
            'plan' => (string)($payload['plan'] ?? $configuration['plan_code'] ?? ''),
            'entitlements' => $entitlements,
            'renewal_at' => (string)($payload['renewal_at'] ?? $configuration['renewal_at'] ?? ''),
            'entitlement_expires_at' => $tokenExpires,
            'offline_lease_expires_at' => $offlineExpires,
            'offline_lease_valid' => $offlineValid,
            'token_valid' => $tokenValid,
            'connection_state' => $connectionState,
            'last_successful_validation_at' => (string)($configuration['last_successful_validation_at'] ?? ''),
            'last_validation_attempt_at' => (string)($configuration['last_validation_attempt_at'] ?? ''),
            'last_heartbeat_at' => (string)($configuration['last_heartbeat_at'] ?? ''),
            'last_error_code' => (string)($configuration['last_error_code'] ?? ''),
            'missing_fields' => $this->identity->missingFields(),
        ];
    }

    public function allows(string $capability): bool
    {
        $capability = trim($capability);
        if (in_array($capability, ['public_site','export','recovery','security_access'], true)) {
            return true;
        }
        $current = $this->current();
        $status = (string)$current['status'];
        $entitlements = $current['entitlements'];
        if ($status === 'active') {
            return filter_var($entitlements[$capability] ?? false, FILTER_VALIDATE_BOOL);
        }
        if ($status === 'grace') {
            if (in_array($capability, ['critical_security_updates','recovery_updates','export','recovery'], true)) {
                return true;
            }
            return filter_var($entitlements[$capability] ?? false, FILTER_VALIDATE_BOOL)
                && !in_array($capability, ['new_premium_consumption','storage_expansion'], true);
        }
        return in_array($capability, ['critical_security_updates','recovery_updates','export','recovery'], true);
    }

    public function limit(string $key, ?int $default = null): ?int
    {
        $value = $this->current()['entitlements'][$key] ?? $default;
        return is_numeric($value) ? max(0, (int)$value) : $default;
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->current()['entitlements'][$key] ?? $default;
    }

    public function storage(bool $measure = false): array
    {
        $allowance = $this->limit('storage_bytes');
        $snapshot = $measure ? $this->storage->measure($allowance) : $this->storage->latest($allowance);
        $this->logger->receipt(
            'storage_check',
            in_array($snapshot['warning_state'], ['hard_limit','over_limit'], true) ? 'denied' : (str_starts_with($snapshot['warning_state'], 'warning_') ? 'warning' : 'success'),
            (string)$snapshot['warning_state'],
            (string)$this->current()['status'],
            [
                'used_bytes' => $snapshot['used_bytes'],
                'allowance_bytes' => $snapshot['allowance_bytes'],
                'usage_percent' => $snapshot['usage_percent'],
                'file_count' => $snapshot['file_count'],
            ]
        );
        return $snapshot;
    }

    public function assertStorageAvailable(int $additionalBytes): void
    {
        $additionalBytes = max(0, $additionalBytes);
        $storage = $this->storage(false);
        $allowance = $storage['allowance_bytes'];
        if ($allowance !== null && ((int)$storage['used_bytes'] + $additionalBytes) > (int)$allowance) {
            throw new RuntimeException('This upload would exceed the licensed storage allowance. Existing content remains unchanged.');
        }
    }

    public function updateEligibility(array $manifest): array
    {
        $current = $this->current();
        $critical = filter_var($manifest['critical_security'] ?? false, FILTER_VALIDATE_BOOL);
        $recovery = filter_var($manifest['recovery_update'] ?? false, FILTER_VALIDATE_BOOL);
        $reasons = [];
        if (!in_array($current['status'], ['active','grace'], true) && !$critical && !$recovery) {
            $reasons[] = 'license_state';
        }
        if (!$this->allows('automatic_updates') && !$critical && !$recovery) {
            $reasons[] = 'automatic_updates';
        }
        $allowedChannel = (string)$this->value('update_channel', 'stable');
        $requestedChannel = (string)($manifest['channel'] ?? 'stable');
        if (!$critical && !$recovery && !hash_equals($allowedChannel, $requestedChannel)) {
            $reasons[] = 'update_channel';
        }
        foreach ([
            'manifest_signed' => 'manifest_signature',
            'checksum_valid' => 'checksum',
            'package_signature_valid' => 'package_signature',
            'migration_compatible' => 'migration_compatibility',
            'backup_completed' => 'pre_update_backup',
        ] as $flag => $reason) {
            if (array_key_exists($flag, $manifest) && !filter_var($manifest[$flag], FILTER_VALIDATE_BOOL)) {
                $reasons[] = $reason;
            }
        }
        $requiredBytes = max(0, (int)($manifest['required_storage_bytes'] ?? 0));
        try {
            $this->assertStorageAvailable($requiredBytes);
        } catch (RuntimeException) {
            $reasons[] = 'storage';
        }
        $eligible = $reasons === [];
        $this->logger->receipt(
            'update_check',
            $eligible ? 'success' : 'denied',
            $eligible ? 'update_eligible' : 'update_ineligible',
            (string)$current['status'],
            [
                'channel' => $requestedChannel,
                'version' => (string)($manifest['version'] ?? ''),
                'critical_security' => $critical,
                'recovery_update' => $recovery,
                'reasons' => $reasons,
            ]
        );
        return [
            'eligible' => $eligible,
            'reasons' => array_values(array_unique($reasons)),
            'license_status' => $current['status'],
            'allowed_channel' => $allowedChannel,
            'critical_security_override' => $critical,
            'recovery_override' => $recovery,
        ];
    }

    public function notices(): array
    {
        $current = $this->current();
        $notices = [];
        if ($current['missing_fields'] !== []) {
            $notices[] = ['type' => 'warning', 'message' => 'VP3 provisioning is incomplete. The public POD remains available, but licensed premium services are disabled.'];
        }
        if ($current['status'] === 'grace') {
            $notices[] = ['type' => 'warning', 'message' => 'This POD is in its renewal grace period. Existing content remains available and customer data is preserved.'];
        } elseif ($current['status'] === 'suspended') {
            $notices[] = ['type' => 'warning', 'message' => 'This POD license is suspended. Public content, export, recovery, and security access remain available.'];
        } elseif (in_array($current['status'], ['expired','terminated'], true)) {
            $notices[] = ['type' => 'error', 'message' => 'This POD license is ' . $current['status'] . '. Customer data is retained for export and recovery according to policy.'];
        } elseif ($current['status'] === 'unknown') {
            $notices[] = ['type' => 'warning', 'message' => 'The current license state is unknown. Public content remains online; premium actions fail closed until validation succeeds.'];
        }
        if ($current['connection_state'] === 'offline_lease') {
            $notices[] = ['type' => 'warning', 'message' => 'VP3.me is temporarily unavailable. This POD is operating from its last verified offline entitlement lease.'];
        }
        return $notices;
    }

    private function errorCode(string $message): string
    {
        $value = strtolower($message);
        return match (true) {
            str_contains($value, 'signature') => 'invalid_signature',
            str_contains($value, 'signing key') => 'unknown_signing_key',
            str_contains($value, 'expired') => 'entitlement_expired',
            str_contains($value, 'fingerprint') => 'fingerprint_mismatch',
            str_contains($value, 'domain_registration') => 'domain_registration_mismatch',
            str_contains($value, 'deployment_id') => 'deployment_mismatch',
            str_contains($value, 'configuration') || str_contains($value, 'provisioning') => 'configuration_incomplete',
            str_contains($value, 'credential') => 'credential_unavailable',
            str_contains($value, 'http') || str_contains($value, 'request failed') => 'provider_unavailable',
            default => 'validation_failed',
        };
    }
}

final class Vp3LicenseMiddleware
{
    public static function requireCapability(string $capability): void
    {
        if (!vp3_license_service()->allows($capability)) {
            throw new RuntimeException('The current VP3 entitlement does not allow this operation. No existing customer data was changed.');
        }
    }

    public static function requireStorage(int $additionalBytes): void
    {
        vp3_license_service()->assertStorageAvailable($additionalBytes);
    }
}

function vp3_license_schema_available(): bool
{
    try {
        $statement = db()->query("SHOW TABLES LIKE 'vp3_license_configuration'");
        return (bool)$statement->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function vp3_license_service(): Vp3EntitlementService
{
    static $service = null;
    if (!$service instanceof Vp3EntitlementService) {
        $service = new Vp3EntitlementService();
    }
    return $service;
}

function vp3_license_allows(string $capability): bool
{
    return vp3_license_schema_available() && vp3_license_service()->allows($capability);
}

function vp3_license_limit(string $key, ?int $default = null): ?int
{
    return vp3_license_schema_available() ? vp3_license_service()->limit($key, $default) : $default;
}

function vp3_license_value(string $key, mixed $default = null): mixed
{
    return vp3_license_schema_available() ? vp3_license_service()->value($key, $default) : $default;
}

function vp3_update_eligibility(array $manifest): array
{
    if (!vp3_license_schema_available()) {
        return [
            'eligible' => false,
            'reasons' => ['licensing_schema_unavailable'],
            'license_status' => 'unknown',
            'allowed_channel' => 'stable',
            'critical_security_override' => false,
            'recovery_override' => false,
        ];
    }
    return vp3_license_service()->updateEligibility($manifest);
}

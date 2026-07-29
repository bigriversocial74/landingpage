<?php
declare(strict_types=1);

require_once __DIR__ . '/vp3-updater-core.php';

final class Vp3UpdateClient
{
    private Vp3DeploymentIdentity $identity;
    private Vp3CredentialStore $credentials;

    public function __construct()
    {
        $this->identity = new Vp3DeploymentIdentity();
        $this->credentials = new Vp3CredentialStore($this->identity);
    }

    public function check(string $channel, string $installedVersion): array
    {
        $payload = $this->identity->asValidationPayload();
        $payload['channel'] = $channel;
        $payload['installed_version'] = $installedVersion;
        $payload['updater_version'] = '65.0.0';
        $payload['php_version'] = PHP_VERSION;
        $payload['extensions'] = array_values(array_intersect(['curl', 'openssl', 'sodium', 'zip', 'zlib'], get_loaded_extensions()));

        return $this->request(
            'POST',
            '/api/' . $this->identity->apiVersion() . '/updates/check',
            $payload
        );
    }

    private function request(string $method, string $path, array $payload): array
    {
        $this->identity->synchronizeConfiguration();
        $credential = $this->credentials->current();
        if (trim((string)($credential['credential'] ?? '')) === '') {
            throw new Vp3UpdateException('A VP3 deployment credential is required to check managed releases.', 'deployment_credential_required');
        }

        $requestId = $this->uuid();
        $nonce = Vp3Crypto::base64UrlEncode(random_bytes(24));
        $timestamp = time();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", [$method, $path, (string)$timestamp, $nonce, $requestId, $bodyHash]);
        $signature = Vp3Crypto::base64UrlEncode(hash_hmac('sha256', $canonical, (string)$credential['credential'], true));
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

        $url = $this->identity->baseUrl() . $path;
        $response = $this->http($url, $method, $headers, $body, 2 * 1024 * 1024);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new Vp3UpdateException('The VP3 release service returned invalid JSON.', 'invalid_release_response');
        }
        $decoded['_transport'] = [
            'request_id' => $requestId,
            'status' => $response['status'],
            'latency_ms' => $response['latency_ms'],
            'response_hash' => hash('sha256', $response['body']),
        ];
        return $decoded;
    }

    private function http(string $url, string $method, array $headers, string $body, int $maximum): array
    {
        $timeout = max(5, min(60, (int)(nmm_config('vp3_licensing')['request_timeout_seconds'] ?? 12)));
        $started = microtime(true);
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new Vp3UpdateException('Unable to initialize the VP3 release request.', 'release_request_init_failed');
            }
            curl_setopt_array($handle, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADER => false,
                CURLOPT_POSTFIELDS => $body,
            ]);
            $responseBody = curl_exec($handle);
            $error = curl_error($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if ($responseBody === false) {
                throw new Vp3UpdateException('The VP3 release request failed: ' . mb_substr($error, 0, 180), 'release_request_failed');
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
                    'content' => $body,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);
            $responseBody = @file_get_contents($url, false, $context, 0, $maximum + 1);
            if ($responseBody === false) {
                throw new Vp3UpdateException('The VP3 release request failed.', 'release_request_failed');
            }
            $status = 0;
            foreach (($http_response_header ?? []) as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                    $status = (int)$match[1];
                }
            }
        }
        if (strlen((string)$responseBody) > $maximum) {
            throw new Vp3UpdateException('The VP3 release response exceeded the configured limit.', 'release_response_too_large');
        }
        if ($status < 200 || $status >= 300) {
            throw new Vp3UpdateException('The VP3 release service returned HTTP ' . $status . '.', 'release_service_http_' . $status);
        }
        return [
            'status' => $status,
            'body' => (string)$responseBody,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
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

final class Vp3UpdateManifestVerifier
{
    private Vp3DeploymentIdentity $identity;
    private Vp3LicenseClient $licenseClient;

    public function __construct()
    {
        $this->identity = new Vp3DeploymentIdentity();
        $credentials = new Vp3CredentialStore($this->identity);
        $this->licenseClient = new Vp3LicenseClient($this->identity, $credentials);
    }

    public function verify(string $jws): array
    {
        $parts = explode('.', trim($jws));
        if (count($parts) !== 3) {
            throw new Vp3UpdateException('The release manifest signature envelope is invalid.', 'invalid_manifest_envelope');
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        try {
            $header = json_decode(Vp3Crypto::base64UrlDecode($encodedHeader), true, 32, JSON_THROW_ON_ERROR);
            $payload = json_decode(Vp3Crypto::base64UrlDecode($encodedPayload), true, 64, JSON_THROW_ON_ERROR);
            $signature = Vp3Crypto::base64UrlDecode($encodedSignature);
        } catch (Throwable $exception) {
            throw new Vp3UpdateException('The release manifest cannot be decoded.', 'invalid_manifest_encoding');
        }
        if (!is_array($header) || !is_array($payload)) {
            throw new Vp3UpdateException('The release manifest content is invalid.', 'invalid_manifest_content');
        }
        $algorithm = (string)($header['alg'] ?? '');
        $keyId = trim((string)($header['kid'] ?? ''));
        if (!in_array($algorithm, ['EdDSA', 'RS256'], true) || $keyId === '') {
            throw new Vp3UpdateException('The release manifest signing metadata is unsupported.', 'unsupported_manifest_signature');
        }

        $jwks = $this->licenseClient->jwks(false);
        $key = $this->findKey($jwks, $keyId, $algorithm);
        if ($key === null) {
            $jwks = $this->licenseClient->jwks(true);
            $key = $this->findKey($jwks, $keyId, $algorithm);
        }
        if ($key === null) {
            throw new Vp3UpdateException('The release signing key is unavailable.', 'release_signing_key_missing');
        }
        $input = $encodedHeader . '.' . $encodedPayload;
        $valid = $algorithm === 'EdDSA'
            ? $this->verifyEd25519($input, $signature, $key)
            : $this->verifyRsaSha256($input, $signature, $key);
        if (!$valid) {
            throw new Vp3UpdateException('The release manifest signature is invalid.', 'manifest_signature_invalid');
        }
        return $this->validateClaims($payload, $header);
    }

    public function verifyPackageSignature(string $sha256, string $signature, string $keyId, string $algorithm): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', strtolower($sha256))) {
            return false;
        }
        if (!in_array($algorithm, ['EdDSA', 'RS256'], true) || $keyId === '') {
            return false;
        }
        $jwks = $this->licenseClient->jwks(false);
        $key = $this->findKey($jwks, $keyId, $algorithm);
        if ($key === null) {
            $jwks = $this->licenseClient->jwks(true);
            $key = $this->findKey($jwks, $keyId, $algorithm);
        }
        if ($key === null) {
            return false;
        }
        try {
            $decoded = Vp3Crypto::base64UrlDecode($signature);
        } catch (Throwable) {
            return false;
        }
        return $algorithm === 'EdDSA'
            ? $this->verifyEd25519(strtolower($sha256), $decoded, $key)
            : $this->verifyRsaSha256(strtolower($sha256), $decoded, $key);
    }

    private function validateClaims(array $payload, array $header): array
    {
        $now = time();
        if (!hash_equals('vp3.me', (string)($payload['iss'] ?? ''))) {
            throw new Vp3UpdateException('The release manifest issuer is invalid.', 'invalid_manifest_issuer');
        }
        $audience = $payload['aud'] ?? '';
        $audienceValid = is_array($audience)
            ? in_array('pod-updater', array_map('strval', $audience), true)
            : hash_equals('pod-updater', (string)$audience);
        if (!$audienceValid) {
            throw new Vp3UpdateException('The release manifest audience is invalid.', 'invalid_manifest_audience');
        }
        if ((int)($payload['nbf'] ?? 0) > $now + 60) {
            throw new Vp3UpdateException('The release manifest is not active yet.', 'manifest_not_active');
        }
        if ((int)($payload['exp'] ?? 0) < $now - 60) {
            throw new Vp3UpdateException('The release manifest has expired.', 'manifest_expired');
        }
        $releaseId = trim((string)($payload['release_id'] ?? ''));
        if ($releaseId === '' || strlen($releaseId) > 120 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{2,119}$/', $releaseId)) {
            throw new Vp3UpdateException('The release ID is invalid.', 'invalid_release_id');
        }
        $version = Vp3UpdateValidation::version((string)($payload['version'] ?? ''));
        $channel = (string)($payload['channel'] ?? 'stable');
        if (!in_array($channel, ['stable', 'preview', 'security'], true)) {
            throw new Vp3UpdateException('The release channel is invalid.', 'invalid_release_channel');
        }
        $packageSha = strtolower(trim((string)($payload['package_sha256'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $packageSha)) {
            throw new Vp3UpdateException('The release package checksum is invalid.', 'invalid_package_checksum');
        }
        $packageSize = (int)($payload['package_size_bytes'] ?? 0);
        if ($packageSize < 1 || $packageSize > Vp3UpdateSettings::current()['maximum_package_bytes']) {
            throw new Vp3UpdateException('The release package size is outside the allowed range.', 'invalid_package_size');
        }
        $packageUrl = Vp3UpdateValidation::packageUrl((string)($payload['package_url'] ?? ''), $this->identity->baseUrl());
        $minimumPhp = trim((string)($payload['minimum_php'] ?? '8.1.0'));
        if ($minimumPhp !== '' && version_compare(PHP_VERSION, $minimumPhp, '<')) {
            throw new Vp3UpdateException('This release requires PHP ' . $minimumPhp . ' or newer.', 'php_version_incompatible');
        }
        $migrations = [];
        foreach (is_array($payload['migrations'] ?? null) ? $payload['migrations'] : [] as $migration) {
            $migration = Vp3UpdateValidation::relativePath((string)$migration);
            if (!str_ends_with(strtolower($migration), '.sql')) {
                throw new Vp3UpdateException('A release migration path is invalid.', 'invalid_migration_path');
            }
            $migrations[] = $migration;
        }
        $deletePaths = [];
        foreach (is_array($payload['delete_paths'] ?? null) ? $payload['delete_paths'] : [] as $path) {
            $path = Vp3UpdateValidation::relativePath((string)$path, true);
            if (Vp3UpdateValidation::protectedPath($path)) {
                throw new Vp3UpdateException('The release attempts to delete a protected path.', 'protected_delete_path');
            }
            $deletePaths[] = $path;
        }
        $payload['release_id'] = $releaseId;
        $payload['version'] = $version;
        $payload['channel'] = $channel;
        $payload['package_sha256'] = $packageSha;
        $payload['package_size_bytes'] = $packageSize;
        $payload['package_url'] = $packageUrl;
        $payload['migrations'] = array_values(array_unique($migrations));
        $payload['delete_paths'] = array_values(array_unique($deletePaths));
        $payload['manifest_signed'] = true;
        $payload['manifest_signing_key_id'] = (string)($header['kid'] ?? '');
        $payload['manifest_algorithm'] = (string)($header['alg'] ?? '');
        return $payload;
    }

    private function findKey(array $jwks, string $keyId, string $algorithm): ?array
    {
        foreach (is_array($jwks['keys'] ?? null) ? $jwks['keys'] : [] as $key) {
            if (!is_array($key) || !hash_equals($keyId, (string)($key['kid'] ?? ''))) {
                continue;
            }
            if (isset($key['alg']) && (string)$key['alg'] !== $algorithm) {
                continue;
            }
            return $key;
        }
        return null;
    }

    private function verifyEd25519(string $input, string $signature, array $key): bool
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new Vp3UpdateException('Sodium is required to verify Ed25519 release signatures.', 'sodium_required');
        }
        if ((string)($key['kty'] ?? '') !== 'OKP' || (string)($key['crv'] ?? '') !== 'Ed25519') {
            return false;
        }
        $publicKey = Vp3Crypto::base64UrlDecode((string)($key['x'] ?? ''));
        return strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            && sodium_crypto_sign_verify_detached($signature, $input, $publicKey);
    }

    private function verifyRsaSha256(string $input, string $signature, array $key): bool
    {
        if ((string)($key['kty'] ?? '') !== 'RSA') {
            return false;
        }
        $pem = $this->rsaJwkToPem((string)($key['n'] ?? ''), (string)($key['e'] ?? ''));
        return openssl_verify($input, $signature, $pem, OPENSSL_ALGO_SHA256) === 1;
    }

    private function rsaJwkToPem(string $n, string $e): string
    {
        $modulus = Vp3Crypto::base64UrlDecode($n);
        $exponent = Vp3Crypto::base64UrlDecode($e);
        $rsa = $this->derSequence($this->derInteger($modulus) . $this->derInteger($exponent));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        if ($algorithm === false) {
            throw new Vp3UpdateException('Unable to construct the RSA release key.', 'rsa_key_construction_failed');
        }
        $spki = $this->derSequence($algorithm . "\x03" . $this->derLength(strlen($rsa) + 1) . "\x00" . $rsa);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            $value = "\x00";
        }
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }
        return "\x02" . $this->derLength(strlen($value)) . $value;
    }

    private function derSequence(string $value): string
    {
        return "\x30" . $this->derLength(strlen($value)) . $value;
    }

    private function derLength(int $length): string
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

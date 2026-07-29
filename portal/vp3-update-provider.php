<?php
declare(strict_types=1);

final class Vp3UpdateProviderClient
{
    private Vp3DeploymentIdentity $identity;
    private Vp3CredentialStore $credentials;

    public function __construct()
    {
        $this->identity = new Vp3DeploymentIdentity();
        $this->credentials = new Vp3CredentialStore($this->identity);
    }

    public function check(): array
    {
        $this->identity->synchronizeConfiguration();
        $this->credentials->bootstrapFromConfiguration();
        $credential = $this->credentials->current();
        if ((string)($credential['credential'] ?? '') === '') {
            throw new RuntimeException('A VP3 deployment credential is required to check managed updates.');
        }
        $settings = vp3_update_settings();
        $endpoint = trim((string)$settings['manifest_endpoint']);
        if ($endpoint === '') {
            throw new RuntimeException('The VP3 update manifest endpoint is not configured.');
        }
        $parts = Vp3UpdateHttp::assertHttpsUrl($endpoint, true);
        if (!empty($parts['query'])) {
            throw new RuntimeException('The VP3 update check endpoint must not contain a query string.');
        }
        $path = (string)($parts['path'] ?? '/');
        $payload = [
            'product' => 'vp3-pod',
            'updater_version' => '65.0.0',
            'channel' => (string)$settings['channel'],
            'installed_version' => (string)$settings['installed_version'],
            'php_version' => PHP_VERSION,
            'platform' => PHP_OS_FAMILY,
            'deployment' => $this->identity->asValidationPayload(),
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = time();
        $nonce = Vp3Crypto::base64UrlEncode(random_bytes(24));
        $requestId = vp3_update_uuid();
        $canonical = implode("\n", [
            'POST',
            $path,
            (string)$timestamp,
            $nonce,
            $requestId,
            hash('sha256', $body),
        ]);
        $signature = Vp3Crypto::base64UrlEncode(
            hash_hmac('sha256', $canonical, (string)$credential['credential'], true)
        );
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: VP3-HMAC ' . $this->identity->deploymentId() . ':' .
                (int)$credential['version'] . ':' . $signature,
            'X-VP3-Deployment-ID: ' . $this->identity->deploymentId(),
            'X-VP3-Credential-Version: ' . (int)$credential['version'],
            'X-VP3-Timestamp: ' . $timestamp,
            'X-VP3-Nonce: ' . $nonce,
            'X-VP3-Request-ID: ' . $requestId,
            'X-VP3-Signature: ' . $signature,
        ];
        $response = Vp3UpdateHttp::requestJson(
            'POST',
            $endpoint,
            $body,
            $headers,
            (int)$settings['request_timeout_seconds'],
            4 * 1024 * 1024
        );
        $json = (array)$response['json'];
        $manifest = is_array($json['manifest'] ?? null) ? $json['manifest'] : $json;
        return [
            'manifest' => $manifest,
            'request_id' => $requestId,
            'response_hash' => hash('sha256', (string)$response['body']),
            'latency_ms' => (int)$response['latency_ms'],
        ];
    }
}

final class Vp3UpdateManifest
{
    public static function verify(array $manifest): array
    {
        $settings = vp3_update_settings();
        $required = ['manifest_version', 'release_id', 'product', 'version', 'channel', 'package', 'signature'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $manifest)) {
                throw new RuntimeException('The VP3 update manifest is missing ' . $key . '.');
            }
        }
        if ((string)$manifest['product'] !== 'vp3-pod') {
            throw new RuntimeException('The update package is not for the VP3 POD product.');
        }
        $channel = (string)$manifest['channel'];
        if (!in_array($channel, ['stable', 'preview', 'security'], true)) {
            throw new RuntimeException('The update manifest channel is invalid.');
        }
        if (!hash_equals((string)$settings['channel'], $channel) && empty($manifest['critical_security'])) {
            throw new RuntimeException('The update manifest does not match the selected channel.');
        }
        $version = trim((string)$manifest['version']);
        if (!preg_match('/^[0-9A-Za-z][0-9A-Za-z._+-]{0,39}$/', $version)) {
            throw new RuntimeException('The update version is invalid.');
        }
        $minimumPhp = trim((string)($manifest['minimum_php'] ?? ''));
        if ($minimumPhp !== '' && version_compare(PHP_VERSION, $minimumPhp, '<')) {
            throw new RuntimeException('This release requires PHP ' . $minimumPhp . ' or newer.');
        }
        $minimumVersion = trim((string)($manifest['minimum_installed_version'] ?? ''));
        if ($minimumVersion !== '' && version_compare((string)$settings['installed_version'], $minimumVersion, '<')) {
            throw new RuntimeException('The installed POD version is too old for this direct update.');
        }
        $expires = trim((string)($manifest['expires_at'] ?? ''));
        if ($expires !== '' && strtotime($expires) !== false && strtotime($expires) < time()) {
            throw new RuntimeException('The VP3 update manifest has expired.');
        }
        $package = is_array($manifest['package']) ? $manifest['package'] : [];
        $packageUrl = trim((string)($package['url'] ?? ''));
        $packageHash = strtolower(trim((string)($package['sha256'] ?? '')));
        $packageSize = max(0, (int)($package['size_bytes'] ?? 0));
        if ($packageUrl === '' || !preg_match('/^[a-f0-9]{64}$/', $packageHash) || $packageSize <= 0) {
            throw new RuntimeException('The update package descriptor is incomplete.');
        }
        if ($packageSize > (int)$settings['max_package_bytes']) {
            throw new RuntimeException('The update package exceeds the configured size limit.');
        }
        Vp3UpdateHttp::assertHttpsUrl($packageUrl, true);

        $identity = new Vp3DeploymentIdentity();
        $credentials = new Vp3CredentialStore($identity);
        $client = new Vp3LicenseClient($identity, $credentials);
        $jwks = $client->jwks(false);
        $verified = Vp3UpdateCrypto::verifyManifest($manifest, $jwks);
        if (!Vp3UpdateCrypto::verifyPackageDescriptor($manifest, $jwks)) {
            throw new RuntimeException('The signed update package descriptor is invalid.');
        }

        $normalized = $manifest;
        $normalized['_manifest_hash'] = $verified['hash'];
        $normalized['_signing_key_id'] = $verified['kid'];
        $normalized['_signature_algorithm'] = $verified['alg'];
        $normalized['release_type'] = in_array(
            (string)($manifest['release_type'] ?? 'standard'),
            ['standard', 'security', 'critical'],
            true
        ) ? (string)$manifest['release_type'] : 'standard';
        $normalized['critical_security'] = filter_var(
            $manifest['critical_security'] ?? ($normalized['release_type'] === 'critical'),
            FILTER_VALIDATE_BOOL
        );
        $normalized['recovery_update'] = filter_var($manifest['recovery_update'] ?? false, FILTER_VALIDATE_BOOL);
        $normalized['required_storage_bytes'] = max(
            $packageSize * 3,
            max(0, (int)($manifest['required_storage_bytes'] ?? 0))
        );
        $normalized['migrations'] = is_array($manifest['migrations'] ?? null) ? $manifest['migrations'] : [];
        $normalized['delete_paths'] = is_array($manifest['delete_paths'] ?? null) ? $manifest['delete_paths'] : [];
        return $normalized;
    }

    public static function hasNewerVersion(array $manifest): bool
    {
        return version_compare((string)$manifest['version'], vp3_update_installed_version(), '>');
    }
}

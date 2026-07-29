<?php
declare(strict_types=1);

$root = dirname(__DIR__);

final class Vp3Crypto
{
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

require_once $root . '/portal/vp3-update-crypto.php';
require_once $root . '/portal/vp3-update-archive.php';

function v65_fail(string $message): never
{
    fwrite(STDERR, "VP3 managed update v65 failed: {$message}\n");
    exit(1);
}

function v65_source(string $path): string
{
    global $root;
    $value = @file_get_contents($root . '/' . $path);
    if (!is_string($value) || $value === '') {
        v65_fail('Unable to read ' . $path . '.');
    }
    return $value;
}

if (!function_exists('sodium_crypto_sign_keypair')) {
    v65_fail('Sodium is required for the release-signature regression.');
}

$keypair = sodium_crypto_sign_keypair();
$secretKey = sodium_crypto_sign_secretkey($keypair);
$publicKey = sodium_crypto_sign_publickey($keypair);
$kid = 'vp3-update-test-ed25519';
$jwks = [
    'keys' => [[
        'kty' => 'OKP',
        'crv' => 'Ed25519',
        'alg' => 'EdDSA',
        'use' => 'sig',
        'kid' => $kid,
        'x' => Vp3Crypto::base64UrlEncode($publicKey),
    ]],
];

$manifest = [
    'manifest_version' => 1,
    'issuer' => 'vp3.me',
    'audience' => 'pod-updater',
    'issued_at' => gmdate('c'),
    'expires_at' => gmdate('c', time() + 3600),
    'target' => [
        'license_public_id' => 'LIC-POD-TEST',
        'deployment_id' => 'POD-TEST',
        'installation_fingerprint' => 'pod_test_fingerprint',
    ],
    'release_id' => 'REL-POD-65-TEST',
    'product' => 'vp3-pod',
    'version' => '65.0.0',
    'channel' => 'stable',
    'package' => [
        'url' => 'https://vp3.me/releases/vp3-pod-65.0.0.zip',
        'sha256' => str_repeat('a', 64),
        'size_bytes' => 1024,
    ],
];
$packageDescriptor = implode("\n", [
    $manifest['product'],
    $manifest['release_id'],
    $manifest['version'],
    $manifest['channel'],
    $manifest['package']['sha256'],
    (string)$manifest['package']['size_bytes'],
]);
$manifest['package']['signature'] = [
    'alg' => 'EdDSA',
    'kid' => $kid,
    'value' => Vp3Crypto::base64UrlEncode(sodium_crypto_sign_detached($packageDescriptor, $secretKey)),
];
$manifestInput = Vp3UpdateCrypto::canonicalJson($manifest);
$manifest['signature'] = [
    'alg' => 'EdDSA',
    'kid' => $kid,
    'value' => Vp3Crypto::base64UrlEncode(sodium_crypto_sign_detached($manifestInput, $secretKey)),
];

$verified = Vp3UpdateCrypto::verifyManifest($manifest, $jwks);
if (!preg_match('/^[a-f0-9]{64}$/', (string)$verified['hash'])) {
    v65_fail('The real Ed25519 manifest signature was not verified.');
}
if (!Vp3UpdateCrypto::verifyPackageDescriptor($manifest, $jwks)) {
    v65_fail('The real Ed25519 package descriptor was not verified.');
}
$tampered = $manifest;
$tampered['version'] = '65.0.1';
try {
    Vp3UpdateCrypto::verifyManifest($tampered, $jwks);
    v65_fail('A tampered release manifest was accepted.');
} catch (RuntimeException) {
}

$archive = new Vp3UpdateArchive();
foreach (['config.php', 'storage/customer/file.txt', '../escape.php', '/absolute.php', '.env'] as $unsafe) {
    try {
        $archive->assertInstallPath($unsafe);
        v65_fail('Unsafe archive path accepted: ' . $unsafe);
    } catch (RuntimeException) {
    }
}
$archive->assertInstallPath('portal/example.php');

$paths = [
    'foundation' => 'portal/vp3-update-foundation.php',
    'http' => 'portal/vp3-update-http.php',
    'crypto' => 'portal/vp3-update-crypto.php',
    'provider' => 'portal/vp3-update-provider.php',
    'repository' => 'portal/vp3-update-repository.php',
    'archive' => 'portal/vp3-update-archive.php',
    'backup' => 'portal/vp3-update-backup.php',
    'health_core' => 'portal/vp3-update-database-health.php',
    'check' => 'portal/vp3-update-agent-check.php',
    'install' => 'portal/vp3-update-agent-install.php',
    'rollback' => 'portal/vp3-update-agent-rollback.php',
    'operations' => 'portal/vp3-update-agent-operations.php',
    'agent' => 'portal/vp3-update-agent.php',
    'settings_bridge' => 'portal/vp3-update-settings-bridge.php',
    'settings_save' => 'portal/vp3-update-settings-save.php',
    'settings_loader' => 'portal/vp3-license-settings-bridge.php',
    'action' => 'portal/vp3-update-action.php',
    'center' => 'portal/vp3-updates.php',
    'health' => 'api/vp3-update/health.php',
    'worker' => 'cron/vp3-pod-update.php',
    'version_override' => 'portal/vp3-update-version-override.php',
    'license_refresh' => 'cron/vp3-license-refresh.php',
    'maintenance' => 'vp3-update-maintenance.php',
    'htaccess' => '.htaccess',
    'sql' => 'database/vp3_pod_managed_updates_v65.sql',
];
$source = [];
foreach ($paths as $key => $path) {
    $source[$key] = v65_source($path);
}

$requirements = [
    'licensed update boundary' => ['automatic_updates_enabled', $source['check']],
    'HMAC authorization' => ['Authorization: VP3-HMAC', $source['provider']],
    'manifest issuer' => ["'issuer'", $source['provider']],
    'manifest audience' => ['pod-updater', $source['provider']],
    'deployment target' => ['installation_fingerprint', $source['provider']],
    'manifest expiry' => ['validity window is unsafe', $source['provider']],
    'JWKS refresh' => ['jwks(true)', $source['provider']],
    'streamed download' => ['CURLOPT_WRITEFUNCTION', $source['http']],
    'checksum verification' => ['hash_equals', $source['check']],
    'archive file limit' => ['max_archive_files', $source['archive']],
    'zip bomb byte limit' => ['max_extracted_bytes', $source['archive']],
    'symlink rejection' => ['Symbolic links are not allowed', $source['archive']],
    'config preservation' => ["\$relative === 'config.php'", $source['backup']],
    'storage preservation' => ["str_starts_with(\$relative, 'storage/')", $source['backup']],
    'database backup' => ['SHOW CREATE TABLE', $source['backup']],
    'destructive migration block' => ['destructive SQL operation', $source['health_core']],
    'pre-update backup' => ['backup->create', $source['install']],
    'automatic rollback' => ['automatic_rollback_completed', $source['install']],
    'local health check' => ['health->local', $source['install']],
    'remote health check' => ['health->remote', $source['install']],
    'health token hash' => ['health_token_hash', $source['health']],
    'maintenance 503' => ['http_response_code(503)', $source['maintenance']],
    'maintenance rewrite' => ['maintenance.flag', $source['htaccess']],
    'operation lock' => ['vp3_update_acquire_operation_lock', $source['action']],
    'worker lock' => ['vp3_update_acquire_operation_lock', $source['worker']],
    'worker bearer authorization' => ['authorization_required', $source['worker']],
    'manual default' => ["vp3_update_automatic_install_enabled', '0'", $source['foundation']],
    'security-only unattended policy' => ['Unattended installation is restricted', $source['foundation']],
    'settings page integration' => ['vp3-update-settings-bridge.php', $source['settings_loader']],
    'encrypted worker token' => ['vp3_admin_encrypt_secret', $source['foundation']],
    'installed version override' => ['vp3_update_installed_version', $source['version_override']],
    'license refresh version override' => ['vp3-update-version-override.php', $source['license_refresh']],
    'additive migration' => ['CREATE TABLE IF NOT EXISTS vp3_update_releases', $source['sql']],
    'rollback records' => ['vp3_update_backups', $source['sql']],
];
foreach ($requirements as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        v65_fail('Missing ' . $label . ': ' . $needle);
    }
}

$forbidden = [
    'duplicate updater core' => 'portal/vp3-updater-core.php',
    'duplicate update client' => 'portal/vp3-update-client.php',
    'live config in repository' => 'config.php',
];
foreach ($forbidden as $label => $path) {
    if (is_file($root . '/' . $path)) {
        v65_fail('Forbidden ' . $label . ': ' . $path);
    }
}
$schemaSql = preg_replace('/^--.*$/m', '', $source['sql']) ?? '';
if (preg_match('/\b(DROP\s+TABLE|DROP\s+DATABASE|TRUNCATE\s+TABLE|ALTER\s+TABLE|DELETE\s+FROM)\b/i', $schemaSql)) {
    v65_fail('The v65 installer migration is not additive.');
}

fwrite(STDOUT, "VP3 POD Signed Managed Update Agent v65 certification passed.\n");

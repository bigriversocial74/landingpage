<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/vp3_pod_licensing_v64.sql',
    'module' => 'portal/vp3-licensing.php',
    'page' => 'portal/vp3-license.php',
    'cron' => 'cron/vp3-license-refresh.php',
    'update_api' => 'api/vp3-license/update-eligibility.php',
    'config' => 'config-example.php',
    'index' => 'index.php',
];
$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    $source[$key] = $content;
}

$checks = [
    'central client' => ['final class Vp3LicenseClient', $source['module']],
    'central verifier' => ['final class Vp3LicenseVerifier', $source['module']],
    'central entitlement service' => ['final class Vp3EntitlementService', $source['module']],
    'central cache' => ['final class Vp3LicenseCache', $source['module']],
    'central middleware' => ['final class Vp3LicenseMiddleware', $source['module']],
    'audit logger' => ['final class Vp3LicenseAuditLogger', $source['module']],
    'deployment identity' => ['final class Vp3DeploymentIdentity', $source['module']],
    'explicit capability check' => ['public function allows(string $capability)', $source['module']],
    'explicit limit check' => ['public function limit(string $key', $source['module']],
    'explicit value check' => ['public function value(string $key', $source['module']],
    'offline lease' => ["'offline_lease_valid'", $source['module']],
    'public availability' => ["['public_site','export','recovery','security_access']", $source['module']],
    'non-destructive storage guard' => ['Existing content remains unchanged', $source['module']],
    'signed request canonical body' => ['implode("\\n", [$method, $path, (string)$timestamp, $nonce, $requestId, $bodyHash])', $source['module']],
    'signed request header' => ['X-VP3-Signature:', $source['module']],
    'timestamp header' => ['X-VP3-Timestamp:', $source['module']],
    'nonce header' => ['X-VP3-Nonce:', $source['module']],
    'idempotency request ID' => ['X-VP3-Request-ID:', $source['module']],
    'nonce replay table' => ['vp3_request_nonces', $source['migration'] . $source['module']],
    'local token encryption' => ['aes-256-gcm', $source['module']],
    'JWKS rotation' => ['/keys/jwks.json', $source['module']],
    'issuer verification' => ["hash_equals('vp3.me'", $source['module']],
    'audience verification' => ['pod-platform', $source['module']],
    'domain assignment verification' => ["'domain_registration_id' =>", $source['module']],
    'deployment verification' => ["'deployment_id' =>", $source['module']],
    'fingerprint verification' => ["'installation_fingerprint' =>", $source['module']],
    'storage warning 80' => ['warning_80', $source['migration'] . $source['module']],
    'storage warning 90' => ['warning_90', $source['migration'] . $source['module']],
    'storage hard limit' => ['hard_limit', $source['migration'] . $source['module']],
    'update eligibility' => ['public function updateEligibility(array $manifest)', $source['module']],
    'critical security override' => ['critical_security_override', $source['module']],
    'pre-update backup' => ["'backup_completed' => 'pre_update_backup'", $source['module']],
    'owner status page' => ['VP3 License', $source['page']],
    'owner validate action' => ['validate_license', $source['page']],
    'owner storage action' => ['measure_storage', $source['page']],
    'scheduled refresh' => ['vp3-license-refresh.php', $source['cron'] . $paths['cron']],
    'update worker authorization' => ['VP3_UPDATE_WORKER_TOKEN', $source['config']],
    'worker bearer header' => ['HTTP_AUTHORIZATION', $source['update_api']],
    'configuration public IDs' => ['VP3_LICENSE_PUBLIC_ID', $source['config']],
    'configuration domain registration' => ['VP3_DOMAIN_REGISTRATION_ID', $source['config']],
    'configuration deployment ID' => ['VP3_DEPLOYMENT_ID', $source['config']],
    'configuration credential' => ['VP3_DEPLOYMENT_CREDENTIAL', $source['config']],
    'configuration fingerprint' => ['VP3_INSTALLATION_FINGERPRINT', $source['config']],
    'license configuration table' => ['CREATE TABLE IF NOT EXISTS vp3_license_configuration', $source['migration']],
    'entitlement cache table' => ['CREATE TABLE IF NOT EXISTS vp3_entitlement_cache', $source['migration']],
    'validation receipt table' => ['CREATE TABLE IF NOT EXISTS vp3_license_validation_receipts', $source['migration']],
    'license event table' => ['CREATE TABLE IF NOT EXISTS vp3_license_events', $source['migration']],
    'storage snapshot table' => ['CREATE TABLE IF NOT EXISTS vp3_storage_usage_snapshots', $source['migration']],
    'credential table' => ['CREATE TABLE IF NOT EXISTS vp3_deployment_credentials', $source['migration']],
];
foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$privateKeyMarker = 'BEGIN ' . 'PRIVATE KEY';
$forbidden = [
    'VP3 private key' => [$privateKeyMarker, implode("\n", $source)],
    'Stripe secret' => ['sk_live_', implode("\n", $source)],
    'query-string worker token' => ["\$_GET['token']", $source['cron'] . $source['update_api']],
    'license content deletion' => ['DELETE FROM blog_', $source['module'] . $source['migration']],
    'license media deletion' => ['DELETE FROM media_', $source['module'] . $source['migration']],
    'license CRM deletion' => ['DELETE FROM crm_', $source['module'] . $source['migration']],
    'global public middleware' => ['Vp3LicenseMiddleware::requireCapability', $source['index']],
    'hard-coded professional plan' => ["=== 'professional'", $source['module'] . $source['page']],
    'hard-coded enterprise plan' => ["=== 'enterprise'", $source['module'] . $source['page']],
    'non-additive ALTER' => ['ALTER TABLE', $source['migration']],
    'destructive DROP' => ['DROP TABLE', $source['migration']],
];
foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

if (!function_exists('sodium_crypto_sign_keypair')) {
    fwrite(STDERR, "Sodium is required for the VP3 entitlement verifier regression.\n");
    exit(1);
}

require_once $root . '/portal/vp3-licensing.php';
$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public = sodium_crypto_sign_publickey($pair);
$now = time();
$header = ['alg' => 'EdDSA', 'kid' => 'test-ed25519-key', 'typ' => 'JWT'];
$payload = [
    'iss' => 'vp3.me',
    'aud' => 'pod-platform',
    'sub' => 'LIC-POD-TEST',
    'account_id' => 'VP3-TEST',
    'domain_registration_id' => 'DOM-TEST',
    'domain' => 'test.vp3.me',
    'deployment_id' => 'POD-TEST',
    'installation_fingerprint' => 'pod_test',
    'status' => 'active',
    'plan' => 'test-plan',
    'entitlements' => ['automatic_updates' => true, 'storage_bytes' => 1024],
    'iat' => $now - 5,
    'nbf' => $now - 5,
    'exp' => $now + 300,
    'offline_lease_seconds' => 259200,
    'jti' => 'test-jti-' . bin2hex(random_bytes(6)),
    'signing_key_id' => 'test-ed25519-key',
];
$encodedHeader = Vp3Crypto::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$encodedPayload = Vp3Crypto::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$input = $encodedHeader . '.' . $encodedPayload;
$signature = sodium_crypto_sign_detached($input, $secret);
$token = $input . '.' . Vp3Crypto::base64UrlEncode($signature);
$jwks = ['keys' => [[
    'kty' => 'OKP',
    'crv' => 'Ed25519',
    'alg' => 'EdDSA',
    'use' => 'sig',
    'kid' => 'test-ed25519-key',
    'x' => Vp3Crypto::base64UrlEncode($public),
]]];
$verified = Vp3LicenseVerifier::verifyCompactJws($token, $jwks);
if (($verified['sub'] ?? '') !== 'LIC-POD-TEST' || ($verified['status'] ?? '') !== 'active') {
    fwrite(STDERR, "Valid signed entitlement did not verify.\n");
    exit(1);
}

$tamperedPayload = $payload;
$tamperedPayload['status'] = 'terminated';
$tamperedToken = $encodedHeader . '.' . Vp3Crypto::base64UrlEncode(json_encode($tamperedPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) . '.' . Vp3Crypto::base64UrlEncode($signature);
try {
    Vp3LicenseVerifier::verifyCompactJws($tamperedToken, $jwks);
    fwrite(STDERR, "Tampered entitlement signature was accepted.\n");
    exit(1);
} catch (RuntimeException) {
    // Expected.
}

$expired = $payload;
$expired['iat'] = $now - 600;
$expired['nbf'] = $now - 600;
$expired['exp'] = $now - 301;
$expiredInput = $encodedHeader . '.' . Vp3Crypto::base64UrlEncode(json_encode($expired, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$expiredToken = $expiredInput . '.' . Vp3Crypto::base64UrlEncode(sodium_crypto_sign_detached($expiredInput, $secret));
try {
    Vp3LicenseVerifier::verifyCompactJws($expiredToken, $jwks);
    fwrite(STDERR, "Expired entitlement was accepted.\n");
    exit(1);
} catch (RuntimeException) {
    // Expected.
}

fwrite(STDOUT, "VP3 POD licensing v64 regression passed.\n");

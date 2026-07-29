<?php
declare(strict_types=1);

/**
 * Administrator-managed VP3 license settings.
 *
 * Values saved through the POD Settings page override matching config.php
 * values. Public identifiers are stored in the existing settings table. The
 * deployment credential is encrypted locally before it is stored.
 */

function vp3_admin_settings_table_available(): bool
{
    try {
        $statement = db()->query("SHOW TABLES LIKE 'settings'");
        return (bool)$statement->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function vp3_admin_setting(string $key, string $default = ''): string
{
    if (!vp3_admin_settings_table_available()) {
        return $default;
    }

    try {
        $statement = db()->prepare(
            'SELECT setting_value FROM settings WHERE setting_key=:setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => $key]);
        $value = $statement->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (Throwable) {
        return $default;
    }
}

function vp3_admin_save_settings(array $pairs): void
{
    if (!vp3_admin_settings_table_available()) {
        throw new RuntimeException('The POD settings table is unavailable.');
    }

    $statement = db()->prepare(
        'INSERT INTO settings(setting_key,setting_value)
         VALUES(:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );

    foreach ($pairs as $key => $value) {
        $statement->execute([
            'setting_key' => mb_substr((string)$key, 0, 190),
            'setting_value' => (string)$value,
        ]);
    }
}

function vp3_admin_local_key(): string
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
        throw new RuntimeException('A stable local encryption secret is required before storing a VP3 credential.');
    }

    return hash('sha256', 'vp3-admin-license-settings-v64.2|' . $source, true);
}

function vp3_admin_encrypt_secret(string $plaintext): string
{
    $plaintext = trim($plaintext);
    if ($plaintext === '') {
        return '';
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL is required to store the VP3 deployment credential.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        vp3_admin_local_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'vp3-admin-license-settings-v64.2'
    );

    if ($ciphertext === false) {
        throw new RuntimeException('The VP3 deployment credential could not be encrypted.');
    }

    return base64_encode($iv . $tag . $ciphertext);
}

function vp3_admin_decrypt_secret(string $encrypted): string
{
    if ($encrypted === '') {
        return '';
    }

    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        vp3_admin_local_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'vp3-admin-license-settings-v64.2'
    );

    return $plaintext === false ? '' : $plaintext;
}

function vp3_admin_effective_license_settings(): array
{
    $config = nmm_config('vp3_licensing');
    $managed = vp3_admin_setting('vp3_license_admin_managed') === '1';

    $map = [
        'license_public_id' => 'vp3_license_public_id',
        'account_public_id' => 'vp3_account_public_id',
        'domain_registration_id' => 'vp3_domain_registration_id',
        'domain' => 'vp3_domain',
        'deployment_id' => 'vp3_deployment_id',
        'installation_fingerprint' => 'vp3_installation_fingerprint',
    ];

    $values = [];
    foreach ($map as $configKey => $settingKey) {
        $values[$configKey] = $managed
            ? vp3_admin_setting($settingKey)
            : trim((string)($config[$configKey] ?? ''));
    }

    $encryptedCredential = vp3_admin_setting('vp3_deployment_credential_encrypted');
    $values['deployment_credential'] = $managed
        ? vp3_admin_decrypt_secret($encryptedCredential)
        : trim((string)($config['deployment_credential'] ?? ''));
    $values['credential_version'] = $managed
        ? max(1, (int)vp3_admin_setting('vp3_credential_version', '1'))
        : max(1, (int)($config['credential_version'] ?? 1));
    $values['managed_by_admin'] = $managed;
    $values['credential_stored'] = $encryptedCredential !== ''
        || (!$managed && $values['deployment_credential'] !== '');

    return $values;
}

function vp3_admin_apply_config_overrides(): void
{
    if (!vp3_admin_settings_table_available()) {
        return;
    }

    $effective = vp3_admin_effective_license_settings();
    if (!$effective['managed_by_admin']) {
        return;
    }

    $config = $GLOBALS['nmm_config'] ?? [];
    if (!is_array($config)) {
        return;
    }

    $license = is_array($config['vp3_licensing'] ?? null)
        ? $config['vp3_licensing']
        : [];

    foreach ([
        'license_public_id',
        'account_public_id',
        'domain_registration_id',
        'domain',
        'deployment_id',
        'installation_fingerprint',
        'deployment_credential',
        'credential_version',
    ] as $key) {
        $license[$key] = $effective[$key];
    }

    $config['vp3_licensing'] = $license;
    $GLOBALS['nmm_config'] = $config;
}

vp3_admin_apply_config_overrides();

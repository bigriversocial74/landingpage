<?php
declare(strict_types=1);

/**
 * VP3 POD Signed Managed Update Agent v65.
 *
 * The updater is additive and opt-in. It requires a verified VP3 entitlement
 * for managed updates, preserves config.php and storage/, verifies signed
 * release metadata and package integrity, creates file/database backups,
 * stages the package, applies approved migrations, runs health checks, and
 * rolls back automatically when activation fails.
 */

require_once __DIR__ . '/vp3-license-settings-store.php';
require_once __DIR__ . '/vp3-licensing.php';
require_once __DIR__ . '/vp3-license-policy.php';

function vp3_update_schema_available(): bool
{
    try {
        $tables = [
            'vp3_update_releases',
            'vp3_update_jobs',
            'vp3_update_backups',
            'vp3_update_migrations',
            'vp3_update_receipts',
        ];
        foreach ($tables as $table) {
            $statement = db()->query("SHOW TABLES LIKE " . db()->quote($table));
            if (!$statement->fetchColumn()) {
                return false;
            }
        }
        return true;
    } catch (Throwable) {
        return false;
    }
}

function vp3_update_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' .
        substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function vp3_update_work_root(): string
{
    $path = NMM_ROOT . '/storage/vp3-updates';
    if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create the VP3 update work directory.');
    }
    $deny = $path . '/.htaccess';
    if (!is_file($deny)) {
        @file_put_contents(
            $deny,
            "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
            LOCK_EX
        );
        @chmod($deny, 0640);
    }
    foreach (['downloads', 'staging', 'backups', 'rollback'] as $child) {
        $directory = $path . '/' . $child;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create VP3 update directory: ' . $child . '.');
        }
    }
    return $path;
}

function vp3_update_relative_path(string $path): string
{
    $root = str_replace('\\', '/', rtrim((string)(realpath(NMM_ROOT) ?: NMM_ROOT), '/'));
    $normalized = str_replace('\\', '/', $path);
    return str_starts_with($normalized, $root . '/')
        ? substr($normalized, strlen($root) + 1)
        : $normalized;
}

function vp3_update_installed_version(): string
{
    $stored = vp3_admin_setting('vp3_update_installed_version');
    if ($stored !== '') {
        return mb_substr($stored, 0, 40);
    }
    $config = nmm_config('vp3_licensing');
    $configured = trim((string)($config['installed_version'] ?? ''));
    return $configured !== '' ? mb_substr($configured, 0, 40) : '64.2.0';
}

function vp3_update_store_installed_version(string $version): void
{
    $version = trim($version);
    if (!preg_match('/^[0-9A-Za-z][0-9A-Za-z._+-]{0,39}$/', $version)) {
        throw new RuntimeException('The installed version value is invalid.');
    }
    vp3_admin_save_settings(['vp3_update_installed_version' => $version]);
}

function vp3_update_settings(): array
{
    $license = nmm_config('vp3_licensing');
    $baseUrl = rtrim((string)($license['provider_base_url'] ?? 'https://vp3.me'), '/');
    $apiVersion = trim((string)($license['api_version'] ?? 'v1')) ?: 'v1';
    $endpointDefault = $baseUrl . '/api/' . $apiVersion . '/updates/pod/check';
    $channel = vp3_admin_setting('vp3_update_channel', 'stable');
    if (!in_array($channel, ['stable', 'preview', 'security'], true)) {
        $channel = 'stable';
    }
    return [
        'channel' => $channel,
        'manifest_endpoint' => vp3_admin_setting('vp3_update_manifest_endpoint', $endpointDefault),
        'automatic_check_enabled' => vp3_admin_setting('vp3_update_automatic_check_enabled', '0') === '1',
        'automatic_install_enabled' => vp3_admin_setting('vp3_update_automatic_install_enabled', '0') === '1',
        'security_only' => vp3_admin_setting('vp3_update_security_only', '1') !== '0',
        'worker_token_encrypted' => vp3_admin_setting('vp3_update_worker_token_encrypted'),
        'worker_token_configured' => vp3_admin_setting('vp3_update_worker_token_encrypted') !== ''
            || trim((string)($license['update_worker_token'] ?? '')) !== '',
        'max_package_bytes' => max(
            10 * 1024 * 1024,
            min(1024 * 1024 * 1024, (int)vp3_admin_setting('vp3_update_max_package_bytes', (string)(256 * 1024 * 1024)))
        ),
        'max_extracted_bytes' => max(
            20 * 1024 * 1024,
            min(2 * 1024 * 1024 * 1024, (int)vp3_admin_setting('vp3_update_max_extracted_bytes', (string)(512 * 1024 * 1024)))
        ),
        'max_archive_files' => max(100, min(100000, (int)vp3_admin_setting('vp3_update_max_archive_files', '20000'))),
        'backup_retention_days' => max(1, min(365, (int)vp3_admin_setting('vp3_update_backup_retention_days', '30'))),
        'request_timeout_seconds' => max(10, min(300, (int)vp3_admin_setting('vp3_update_request_timeout_seconds', '60'))),
        'installed_version' => vp3_update_installed_version(),
    ];
}

function vp3_update_worker_token(): string
{
    $encrypted = vp3_admin_setting('vp3_update_worker_token_encrypted');
    if ($encrypted !== '') {
        return vp3_admin_decrypt_secret($encrypted);
    }
    return trim((string)(nmm_config('vp3_licensing')['update_worker_token'] ?? ''));
}

function vp3_update_save_settings(array $input): void
{
    $channel = (string)($input['channel'] ?? 'stable');
    if (!in_array($channel, ['stable', 'preview', 'security'], true)) {
        throw new RuntimeException('Select a valid VP3 update channel.');
    }
    $endpoint = trim((string)($input['manifest_endpoint'] ?? ''));
    if ($endpoint !== '') {
        Vp3UpdateHttp::assertHttpsUrl($endpoint, true);
    }
    $existingWorker = vp3_admin_setting('vp3_update_worker_token_encrypted');
    $newWorker = trim((string)($input['worker_token'] ?? ''));
    $removeWorker = !empty($input['remove_worker_token']);
    if ($newWorker !== '' && (strlen($newWorker) < 32 || strlen($newWorker) > 512)) {
        throw new RuntimeException('The update worker token must contain 32 to 512 characters.');
    }
    $encryptedWorker = $removeWorker
        ? ''
        : ($newWorker !== '' ? vp3_admin_encrypt_secret($newWorker) : $existingWorker);

    vp3_admin_save_settings([
        'vp3_update_channel' => $channel,
        'vp3_update_manifest_endpoint' => mb_substr($endpoint, 0, 1000),
        'vp3_update_automatic_check_enabled' => !empty($input['automatic_check_enabled']) ? '1' : '0',
        'vp3_update_automatic_install_enabled' => !empty($input['automatic_install_enabled']) ? '1' : '0',
        'vp3_update_security_only' => !empty($input['security_only']) ? '1' : '0',
        'vp3_update_worker_token_encrypted' => $encryptedWorker,
        'vp3_update_backup_retention_days' => (string)max(1, min(365, (int)($input['backup_retention_days'] ?? 30))),
        'vp3_update_request_timeout_seconds' => (string)max(10, min(300, (int)($input['request_timeout_seconds'] ?? 60))),
    ]);
}

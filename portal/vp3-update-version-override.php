<?php
declare(strict_types=1);

require_once __DIR__ . '/vp3-license-settings-store.php';

/**
 * Keep VP3 license validation and heartbeat payloads aligned with the version
 * registered by the managed updater without editing the live config.php file.
 */
function vp3_apply_managed_installed_version_override(): void
{
    $stored = vp3_admin_setting('vp3_update_installed_version');
    if ($stored === '') {
        return;
    }
    $config = $GLOBALS['nmm_config'] ?? [];
    if (!is_array($config)) {
        return;
    }
    $license = is_array($config['vp3_licensing'] ?? null)
        ? $config['vp3_licensing']
        : [];
    $license['installed_version'] = mb_substr($stored, 0, 40);
    $config['vp3_licensing'] = $license;
    $GLOBALS['nmm_config'] = $config;
}

vp3_apply_managed_installed_version_override();

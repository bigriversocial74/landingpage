<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'store' => 'portal/vp3-license-settings-store.php',
    'bridge' => 'portal/vp3-license-settings-bridge.php',
    'save' => 'portal/vp3-license-settings-save.php',
    'manager' => 'portal/vp3-license-manager.php',
    'connector' => 'portal/microgifter-connectors.php',
    'update' => 'api/vp3-license/update-eligibility.php',
    'cron' => 'cron/vp3-license-refresh.php',
];

$source = [];
foreach ($files as $key => $path) {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        fwrite(STDERR, "Missing {$path}.\n");
        exit(1);
    }
    $source[$key] = $content;
}

$checks = [
    'settings page panel' => ['VP3 License &amp; Automatic Updates', $source['bridge']],
    'license code input' => ['name="vp3_license_public_id"', $source['bridge']],
    'separate authenticated save endpoint' => ['vp3-license-settings-save.php', $source['bridge']],
    'credential encryption' => ['aes-256-gcm', $source['store']],
    'administrator requirement' => ["require_role('admin')", $source['save']],
    'csrf enforcement' => ['verify_csrf();', $source['save']],
    'managed config override' => ['vp3_admin_apply_config_overrides', $source['store']],
    'automatic update overlay' => ['vp3-license-settings-store.php', $source['update']],
    'cron overlay' => ['vp3-license-settings-store.php', $source['cron']],
    'status wrapper' => ['vp3-license.php', $source['manager']],
    'settings injection registration' => ['vp3-license-settings-bridge.php', $source['connector']],
    'unlicensed site behavior' => ['The website and administrator portal work without a license.', $source['bridge']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'plaintext credential persistence' => ["vp3_deployment_credential' => \$newCredential", $source['save']],
    'license shutdown middleware' => ["requireCapability('public_site')", implode("\n", $source)],
    'new migration dependency' => ['CREATE TABLE', implode("\n", $source)],
];
foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "VP3 license Settings v64.2 regression passed.\n");

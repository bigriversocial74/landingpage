<?php
declare(strict_types=1);

function vp3_update_status_snapshot(): array
{
    $settings = vp3_update_settings();
    $policy = function_exists('vp3_managed_updates_policy')
        ? vp3_managed_updates_policy()
        : ['automatic_updates_enabled' => false, 'state' => 'license_optional'];
    $release = null;
    $jobs = [];
    $backups = [];
    if (vp3_update_schema_available()) {
        $repository = new Vp3UpdateRepository();
        $release = $repository->latestRelease();
        $jobs = $repository->latestJobs(10);
        $backups = $repository->latestBackups(10);
    }
    return [
        'schema_available' => vp3_update_schema_available(),
        'settings' => $settings,
        'policy' => $policy,
        'latest_release' => $release,
        'jobs' => $jobs,
        'backups' => $backups,
        'requirements' => [
            'zip' => class_exists('ZipArchive'),
            'curl' => function_exists('curl_init'),
            'openssl' => function_exists('openssl_verify'),
            'sodium' => function_exists('sodium_crypto_sign_verify_detached'),
            'root_writable' => is_writable(NMM_ROOT),
            'storage_writable' => is_writable(NMM_ROOT . '/storage'),
            'base_url_configured' => trim((string)(nmm_config('app')['base_url'] ?? '')) !== '',
        ],
    ];
}

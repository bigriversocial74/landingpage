<?php
declare(strict_types=1);

function vp3_license_schema_available(): bool
{
    return true;
}

function vp3_license_allows(string $capability): bool
{
    return $capability === 'automatic_updates';
}

$root = dirname(__DIR__);
require_once $root . '/portal/vp3-license-policy.php';

$licensed = vp3_managed_updates_policy([
    'status' => 'active',
    'missing_fields' => [],
    'license_public_id' => 'lic_fixture_001',
    'deployment_id' => 'dep_fixture_001',
]);

$unlicensed = vp3_managed_updates_policy([
    'status' => 'unknown',
    'missing_fields' => ['license_public_id', 'deployment_id'],
    'license_public_id' => '',
    'deployment_id' => '',
]);

$checks = [
    'licensed site remains operational' => $licensed['site_operational'] === true,
    'license is not required for site' => $licensed['license_required_for_site'] === false,
    'license is not required for manual deployment' => $licensed['license_required_for_manual_deployment'] === false,
    'license is required for automatic updates' => $licensed['license_required_for_automatic_updates'] === true,
    'valid entitlement enables automatic updates' => $licensed['automatic_updates_enabled'] === true,
    'unlicensed site remains operational' => $unlicensed['site_operational'] === true,
    'unlicensed automatic updates remain disabled' => $unlicensed['automatic_updates_enabled'] === false,
    'unlicensed policy remains optional' => $unlicensed['state'] === 'license_optional',
    'policy scope is managed updates' => $unlicensed['commercial_scope'] === 'managed_updates',
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "VP3 managed-updates policy failed: {$label}\n");
        exit(1);
    }
}

$paths = [
    'index' => 'index.php',
    'bootstrap' => 'portal/bootstrap.php',
    'policy' => 'portal/vp3-license-policy.php',
    'owner' => 'portal/vp3-license.php',
    'eligibility' => 'api/vp3-license/update-eligibility.php',
    'legacy_test' => 'tests/vp3-pod-licensing-v64.php',
];
$source = [];
foreach ($paths as $key => $path) {
    $value = @file_get_contents($root . '/' . $path);
    if (!is_string($value) || $value === '') {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    $source[$key] = $value;
}

$required = [
    'policy version' => ['64.1', $source['policy']],
    'site operational policy' => ["'site_operational' => true", $source['policy']],
    'manual deployment policy' => ["'license_required_for_manual_deployment' => false", $source['policy']],
    'automatic update boundary' => ["'license_required_for_automatic_updates' => true", $source['policy']],
    'managed-update denial reason' => ['vp3_license_required_for_managed_updates', $source['eligibility']],
    'eligibility confirms site operation' => ["'site_operational' => true", $source['eligibility']],
    'eligibility confirms manual deployment' => ["'manual_deployment_allowed' => true", $source['eligibility']],
    'owner site available copy' => ['Core POD available', $source['owner']],
    'owner automatic update copy' => ['Automatic updates disabled', $source['owner']],
    'owner add license action' => ['Add a VP3 license', $source['owner']],
    'owner manual deployment copy' => ['Manual deployment', $source['owner']],
    'retained signed entitlement test' => ['Ed25519', $source['legacy_test']],
];

foreach ($required as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'public index license gate' => ['vp3_license_required_for_site', $source['index']],
    'bootstrap license shutdown' => ['vp3_license_block_site', $source['bootstrap']],
    'production site fail closed' => ['license_required_for_site\' => true', $source['policy']],
    'automatic updates without entitlement' => ["'license_required_for_automatic_updates' => false", $source['policy']],
];

foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "VP3 managed-updates licensing scope v64.1 regression passed.\n");

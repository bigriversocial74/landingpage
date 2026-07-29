<?php
declare(strict_types=1);

/**
 * VP3 managed-updates licensing policy v64.1.
 *
 * The POD application, public site, owner portal, CRM, publishing, media,
 * calling, messaging, backups, exports, and manual deployment remain usable
 * without a VP3 license. A verified entitlement is required only for the
 * managed automatic-update channel and any future service that explicitly
 * opts into a licensed capability.
 */
function vp3_managed_updates_policy(?array $current = null): array
{
    $schemaAvailable = function_exists('vp3_license_schema_available')
        && vp3_license_schema_available();

    if ($current === null) {
        $current = $schemaAvailable && function_exists('vp3_license_service')
            ? vp3_license_service()->current()
            : [];
    }

    $status = strtolower(trim((string)($current['status'] ?? 'unknown')));
    $missingFields = is_array($current['missing_fields'] ?? null)
        ? array_values($current['missing_fields'])
        : [];
    $licenseConfigured = $schemaAvailable
        && $missingFields === []
        && trim((string)($current['license_public_id'] ?? '')) !== ''
        && trim((string)($current['deployment_id'] ?? '')) !== '';
    $automaticUpdatesEnabled = $licenseConfigured
        && in_array($status, ['active', 'grace'], true)
        && function_exists('vp3_license_allows')
        && vp3_license_allows('automatic_updates');

    $state = $automaticUpdatesEnabled
        ? 'licensed_updates_enabled'
        : ($licenseConfigured ? 'license_validation_required' : 'license_optional');

    $message = match ($state) {
        'licensed_updates_enabled' => 'Managed automatic updates are enabled by the verified VP3 entitlement.',
        'license_validation_required' => 'The POD remains fully usable. Validate or renew the VP3 license to enable managed automatic updates.',
        default => 'The POD remains fully usable without a license. Add a VP3 license later to enable managed automatic updates.',
    };

    return [
        'policy_version' => '64.1',
        'commercial_scope' => 'managed_updates',
        'site_operational' => true,
        'license_required_for_site' => false,
        'license_required_for_manual_deployment' => false,
        'license_required_for_automatic_updates' => true,
        'automatic_updates_enabled' => $automaticUpdatesEnabled,
        'license_configured' => $licenseConfigured,
        'license_status' => $status,
        'state' => $state,
        'message' => $message,
    ];
}

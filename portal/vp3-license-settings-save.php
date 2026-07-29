<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vp3-license-settings-store.php';

$user = require_role('admin');

if (!is_post()) {
    redirect('portal/admin.php?view=settings#vp3-license-settings');
}

verify_csrf();
enforce_authenticated_action_limit($user);

try {
    $removeLicense = isset($_POST['remove_vp3_license']);
    $removeCredential = isset($_POST['remove_vp3_credential']);

    $licenseCode = $removeLicense ? '' : mb_substr(input('vp3_license_public_id'), 0, 120);
    $accountId = $removeLicense ? '' : mb_substr(input('vp3_account_public_id'), 0, 120);
    $domainRegistrationId = $removeLicense ? '' : mb_substr(input('vp3_domain_registration_id'), 0, 120);
    $domain = $removeLicense ? '' : mb_substr(strtolower(input('vp3_domain')), 0, 255);
    $deploymentId = $removeLicense ? '' : mb_substr(input('vp3_deployment_id'), 0, 120);
    $fingerprint = $removeLicense ? '' : mb_substr(input('vp3_installation_fingerprint'), 0, 190);
    $credentialVersion = max(1, min(1000000, int_input('vp3_credential_version', 1)));

    if ($licenseCode !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{2,119}$/', $licenseCode)) {
        throw new RuntimeException('Enter a valid VP3 license code.');
    }
    foreach ([$accountId, $domainRegistrationId, $deploymentId] as $publicId) {
        if ($publicId !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{2,119}$/', $publicId)) {
            throw new RuntimeException('One of the VP3 assignment IDs is invalid.');
        }
    }
    if ($domain !== '' && !preg_match('/^(?=.{1,255}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domain)) {
        throw new RuntimeException('Enter a valid POD domain without a scheme or path.');
    }

    $newCredential = trim((string)($_POST['vp3_deployment_credential'] ?? ''));
    if ($newCredential !== '' && (strlen($newCredential) < 32 || strlen($newCredential) > 512)) {
        throw new RuntimeException('The VP3 deployment credential must contain 32 to 512 characters.');
    }

    $existingEncrypted = vp3_admin_setting('vp3_deployment_credential_encrypted');
    if ($removeLicense || $removeCredential) {
        $encryptedCredential = '';
    } elseif ($newCredential !== '') {
        $encryptedCredential = vp3_admin_encrypt_secret($newCredential);
    } elseif ($existingEncrypted !== '') {
        $encryptedCredential = $existingEncrypted;
    } else {
        $configCredential = trim((string)(nmm_config('vp3_licensing')['deployment_credential'] ?? ''));
        $encryptedCredential = $configCredential !== ''
            ? vp3_admin_encrypt_secret($configCredential)
            : '';
    }

    vp3_admin_save_settings([
        'vp3_license_admin_managed' => '1',
        'vp3_license_public_id' => $licenseCode,
        'vp3_account_public_id' => $accountId,
        'vp3_domain_registration_id' => $domainRegistrationId,
        'vp3_domain' => $domain,
        'vp3_deployment_id' => $deploymentId,
        'vp3_installation_fingerprint' => $fingerprint,
        'vp3_credential_version' => (string)$credentialVersion,
        'vp3_deployment_credential_encrypted' => $encryptedCredential,
    ]);

    log_activity(
        $removeLicense ? 'vp3_license_removed' : 'vp3_license_settings_updated',
        'settings',
        null,
        [
            'license_configured' => $licenseCode !== '',
            'credential_stored' => $encryptedCredential !== '',
            'domain' => $domain,
        ]
    );

    flash(
        'success',
        $removeLicense
            ? 'The saved VP3 license was removed. The POD remains operational and managed updates are disabled.'
            : 'VP3 license settings saved. The POD remains operational; managed updates activate after successful VP3 validation.'
    );
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
}

redirect('portal/admin.php?view=settings#vp3-license-settings');

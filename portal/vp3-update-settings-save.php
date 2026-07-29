<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vp3-update-core.php';

$user = require_role('admin');
if (!is_post()) {
    redirect('portal/admin.php?view=settings#vp3-managed-updates');
}
verify_csrf();
enforce_authenticated_action_limit($user);

try {
    vp3_update_save_settings([
        'channel' => input('channel'),
        'manifest_endpoint' => input('manifest_endpoint'),
        'automatic_check_enabled' => isset($_POST['automatic_check_enabled']),
        'automatic_install_enabled' => isset($_POST['automatic_install_enabled']),
        'security_only' => isset($_POST['security_only']),
        'worker_token' => (string)($_POST['worker_token'] ?? ''),
        'remove_worker_token' => isset($_POST['remove_worker_token']),
        'backup_retention_days' => int_input('backup_retention_days', 30),
        'request_timeout_seconds' => int_input('request_timeout_seconds', 60),
    ]);
    log_activity('vp3_update_settings_updated', 'settings', null, [
        'channel' => input('channel'),
        'automatic_check_enabled' => isset($_POST['automatic_check_enabled']),
        'automatic_install_enabled' => isset($_POST['automatic_install_enabled']),
        'security_only' => isset($_POST['security_only']),
    ]);
    flash('success', 'VP3 update settings saved.');
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
}
redirect('portal/admin.php?view=settings#vp3-managed-updates');

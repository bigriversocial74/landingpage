<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vp3-update-core.php';

$user = require_role('admin');
if (!is_post()) {
    redirect('portal/vp3-updates.php');
}
verify_csrf();
enforce_authenticated_action_limit($user);
@set_time_limit(0);
@ignore_user_abort(true);

$action = input('action');
$operationLock = null;
try {
    $operationLock = vp3_update_acquire_operation_lock();
    $agent = new Vp3UpdateAgent();
    if ($action === 'check') {
        $result = $agent->check((int)$user['id'], 'administrator');
        $message = !empty($result['newer_version'])
            ? 'Signed VP3 release ' . (string)$result['release']['version'] . ' is available.'
            : 'The installed POD is current for the selected channel.';
        flash('success', $message);
    } elseif ($action === 'prepare') {
        $releaseId = int_input('release_id');
        if ($releaseId <= 0) {
            throw new RuntimeException('Select a valid VP3 release.');
        }
        $result = $agent->prepare($releaseId, (int)$user['id'], 'administrator');
        flash('success', 'Release ' . (string)$result['release']['version'] . ' was downloaded, verified, and staged.');
    } elseif ($action === 'install') {
        $releaseId = int_input('release_id');
        if ($releaseId <= 0) {
            throw new RuntimeException('Select a valid VP3 release.');
        }
        $agent->install($releaseId, (int)$user['id'], 'administrator');
        flash('success', 'The POD update completed and passed rollback-protected health validation.');
    } elseif ($action === 'rollback') {
        $backupId = int_input('backup_id');
        if ($backupId <= 0) {
            throw new RuntimeException('Select a valid update backup.');
        }
        $agent->rollback($backupId, (int)$user['id'], 'administrator');
        flash('success', 'The selected POD backup was restored and health-checked.');
    } else {
        throw new RuntimeException('Unsupported VP3 update action.');
    }
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
} finally {
    vp3_update_release_operation_lock($operationLock);
}
redirect('portal/vp3-updates.php');

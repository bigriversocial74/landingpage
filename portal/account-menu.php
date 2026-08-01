<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-account-menu-v66Q7 */

$isAdminAccount = ($user['role'] ?? '') === 'admin';
$dashboardUrl = app_url('portal/' . ($isAdminAccount ? 'admin.php?view=dashboard' : 'client.php'));
$settingsUrl = app_url('portal/' . ($isAdminAccount ? 'admin.php?view=settings' : 'client.php?view=account#settings'));
$accountUrl = app_url('portal/' . ($isAdminAccount ? 'admin.php?view=account' : 'client.php?view=account'));
?>
<details class="portal-account-menu">
    <summary aria-label="Open account menu">
        <span class="portal-user-avatar" aria-hidden="true">
            <img src="<?=e(user_profile_image_url($user))?>" alt="">
        </span>
        <strong><?=e((string)$user['display_name'])?></strong>
        <span class="portal-account-chevron" aria-hidden="true">⌄</span>
    </summary>
    <nav aria-label="Account">
        <a href="<?=e($dashboardUrl)?>">Dashboard</a>
        <a href="<?=e($settingsUrl)?>">Settings</a>
        <?php if ($isAdminAccount): ?>
            <a href="<?=e($accountUrl)?>">Account</a>
        <?php endif; ?>
        <a href="<?=e(app_url('portal/logout.php'))?>">Sign out</a>
    </nav>
</details>

<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-account-menu-v66Q7 */

function nmm_render_authenticated_account_menu(array $user): void
{
    $isAdmin = (string)($user['role'] ?? '') === 'admin';
    $name = trim((string)($user['display_name'] ?? 'Account')) ?: 'Account';
    $profileImageUrl = user_profile_image_url($user);
    $links = $isAdmin
        ? [
            ['Dashboard', app_url('portal/admin.php?view=dashboard')],
            ['Settings', app_url('portal/admin.php?view=settings')],
            ['Account', app_url('portal/admin.php?view=account')],
            ['Sign out', app_url('portal/logout.php')],
        ]
        : [
            ['Dashboard', app_url('portal/client.php')],
            ['Settings', app_url('portal/client.php?view=account')],
            ['Sign out', app_url('portal/logout.php')],
        ];
    ?>
    <div class="portal-account-menu" data-account-menu>
        <button
            class="portal-account-trigger"
            type="button"
            data-account-menu-trigger
            aria-haspopup="menu"
            aria-expanded="false"
            aria-label="Open account menu"
        >
            <span class="portal-user-avatar" aria-hidden="true">
                <img src="<?=e($profileImageUrl)?>" alt="">
            </span>
            <strong><?=e($name)?></strong>
            <svg class="portal-account-chevron" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M5 7.5 10 12l5-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </button>
        <nav class="portal-account-panel" data-account-menu-panel role="menu" hidden>
            <?php foreach ($links as [$label, $url]): ?>
                <a href="<?=e($url)?>" role="menuitem"><?=e($label)?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php
}

function nmm_render_public_account_menu(): void
{
    ?>
    <div class="public-account-menu" data-account-menu>
        <button
            class="public-account-trigger"
            type="button"
            data-account-menu-trigger
            aria-haspopup="menu"
            aria-expanded="false"
            aria-label="Open sign-in menu"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.8"></circle>
                <path d="M4.5 20c.8-4 3.3-6 7.5-6s6.7 2 7.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
            </svg>
            <strong>Account</strong>
            <svg class="public-account-chevron" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M5 7.5 10 12l5-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </button>
        <nav class="public-account-panel" data-account-menu-panel role="menu" hidden>
            <span>Sign in</span>
            <a href="<?=e(app_url('portal/login.php?role=client'))?>" role="menuitem">Client login</a>
            <a href="<?=e(app_url('portal/login.php?role=admin'))?>" role="menuitem">Administrator login</a>
        </nav>
    </div>
    <?php
}

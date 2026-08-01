<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-public-account-menu-v66Q12 */

require_once __DIR__ . '/public-follow.php';

function nmm_public_account_menu_context(): array
{
    $user = function_exists('current_user') ? current_user() : null;

    if ($user) {
        $isAdmin = (string)($user['role'] ?? '') === 'admin';
        $script = $isAdmin ? 'admin.php' : 'client.php';

        return [
            'signed_in' => true,
            'display_name' => trim((string)($user['display_name'] ?? 'Account')) ?: 'Account',
            'role_label' => $isAdmin ? 'Administrator' : 'Client',
            'avatar_url' => function_exists('user_profile_image_url')
                ? user_profile_image_url($user)
                : '',
            'dashboard_url' => app_url('portal/' . $script),
            'account_url' => app_url('portal/' . $script . '?view=account'),
            'logout_url' => app_url('portal/logout.php'),
        ];
    }

    return [
        'signed_in' => false,
        'display_name' => 'Account',
        'role_label' => 'Sign in',
        'avatar_url' => '',
        'client_url' => app_url('portal/login.php?role=client'),
        'admin_url' => app_url('portal/login.php?role=admin'),
    ];
}

function nmm_public_account_assets_html(): string
{
    return '<link rel="stylesheet" href="'
        . e(app_url('assets/css/public-account-menu-v66q7.css?v=20260801-v66Q12'))
        . '"><script defer src="'
        . e(app_url('assets/js/public-account-menu-v66q12.js?v=20260801-v66Q12'))
        . '"></script>';
}

function nmm_public_account_menu_html(): string
{
    $context = nmm_public_account_menu_context();

    ob_start();
    ?>
    <details class="public-account-menu" data-public-account-menu>
        <summary aria-label="Open account menu">
            <?php if ($context['avatar_url'] !== ''): ?>
                <img class="public-account-menu-avatar" src="<?=e($context['avatar_url'])?>" alt="">
            <?php else: ?>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4.5 20c.8-4 3.3-6 7.5-6s6.7 2 7.5 6"></path>
                </svg>
            <?php endif; ?>
            <span class="public-account-menu-label">
                <strong><?=e($context['display_name'])?></strong>
                <small><?=e($context['role_label'])?></small>
            </span>
            <b aria-hidden="true">⌄</b>
        </summary>
        <nav aria-label="Account">
            <?php if ($context['signed_in']): ?>
                <a href="<?=e($context['dashboard_url'])?>">Dashboard</a>
                <a href="<?=e($context['account_url'])?>">Account settings</a>
                <a class="public-account-signout" href="<?=e($context['logout_url'])?>">Sign out</a>
            <?php else: ?>
                <a href="<?=e($context['client_url'])?>">Client login</a>
                <a href="<?=e($context['admin_url'])?>">Administrator login</a>
            <?php endif; ?>
        </nav>
    </details>
    <?php
    return trim((string)ob_get_clean());
}

function nmm_render_public_account_menu(): void
{
    echo nmm_public_account_menu_html();
}

function nmm_remove_direct_login_links_from_header(string $html): string
{
    return preg_replace_callback(
        '#<header\b[^>]*>.*?</header>#is',
        static function (array $match): string {
            return preg_replace(
                '#<a\b[^>]*href=(?:"|\')[^"\']*portal/login\.php\?role=(?:client|admin)[^"\']*(?:"|\')[^>]*>.*?</a>#is',
                '',
                (string)$match[0]
            ) ?? (string)$match[0];
        },
        $html,
        1
    ) ?? $html;
}

function nmm_inject_public_account_menu(string $html): string
{
    $assets = nmm_public_account_assets_html() . nmm_public_follow_assets_html();

    if (!str_contains($html, 'public-account-menu-v66q7.css')) {
        $html = preg_replace('#</head>#i', $assets . '</head>', $html, 1) ?? $html;
    }

    $html = nmm_remove_direct_login_links_from_header($html);

    $headerActions = '';
    if (!str_contains($html, 'data-follow-modal-open')) {
        $headerActions .= nmm_public_follow_trigger_html('public-header-follow-link');
    }
    if (!str_contains($html, 'data-public-account-menu')) {
        $headerActions .= nmm_public_account_menu_html();
    }
    if ($headerActions !== '') {
        $html = preg_replace('#</header>#i', $headerActions . '</header>', $html, 1) ?? $html;
    }

    if (!str_contains($html, 'data-follow-modal')) {
        $html = preg_replace('#</body>#i', nmm_public_follow_modal_html() . '</body>', $html, 1) ?? $html;
    }

    return $html;
}

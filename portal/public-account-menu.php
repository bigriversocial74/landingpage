<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-public-account-follow-v66Q9 */

require_once __DIR__ . '/public-follow.php';

function nmm_public_account_menu_html(): string
{
    $clientUrl = app_url('portal/login.php?role=client');
    $adminUrl = app_url('portal/login.php?role=admin');

    ob_start();
    ?>
    <details class="public-account-menu" data-public-account-menu>
        <summary aria-label="Open account menu">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M4.5 20c.8-4 3.3-6 7.5-6s6.7 2 7.5 6"></path>
            </svg>
            <span>Account</span>
            <b aria-hidden="true">⌄</b>
        </summary>
        <nav aria-label="Sign in">
            <a href="<?=e($clientUrl)?>">Client login</a>
            <a href="<?=e($adminUrl)?>">Administrator login</a>
        </nav>
    </details>
    <?php
    return trim((string)ob_get_clean());
}

function nmm_render_public_account_menu(): void
{
    echo nmm_public_account_menu_html();
}

function nmm_inject_public_account_menu(string $html): string
{
    $assets = '<link rel="stylesheet" href="'
        . e(app_url('assets/css/public-account-menu-v66q7.css?v=20260801-v66Q9'))
        . '">'
        . nmm_public_follow_assets_html();

    if (!str_contains($html, 'public-account-menu-v66q7.css')) {
        $html = preg_replace('#</head>#i', $assets . '</head>', $html, 1) ?? $html;
    }

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

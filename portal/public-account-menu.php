<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-public-account-menu-v66Q7 */

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
    $stylesheet = '<link rel="stylesheet" href="'
        . e(app_url('assets/css/public-account-menu-v66q7.css?v=20260731-v66Q7'))
        . '">';
    if (!str_contains($html, 'public-account-menu-v66q7.css')) {
        $html = preg_replace(
            '#</head>#i',
            $stylesheet . '</head>',
            $html,
            1
        ) ?? $html;
    }

    if (!str_contains($html, 'data-public-account-menu')) {
        $html = preg_replace(
            '#</header>#i',
            nmm_public_account_menu_html() . '</header>',
            $html,
            1
        ) ?? $html;
    }

    return $html;
}

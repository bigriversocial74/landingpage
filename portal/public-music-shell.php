<?php
declare(strict_types=1);

require_once __DIR__ . '/public-sidebar.php';
require_once __DIR__ . '/public-account-menu.php';
require_once __DIR__ . '/portfolio.php';

function music_public_shell_projects(): array
{
    if (portfolio_schema_available()) {
        $projects = portfolio_public_projects();

        if ($projects) {
            return $projects;
        }
    }

    return [
        ['title' => 'Gruber AI', 'slug' => 'gruber'],
        ['title' => 'Microgifter', 'slug' => 'microgifter'],
        ['title' => 'Homestead', 'slug' => 'homestead'],
        ['title' => 'Poolzebo', 'slug' => 'poolzebo'],
        ['title' => 'Spaced Invaders', 'slug' => 'spaced-invaders'],
        ['title' => 'Stonefellow', 'slug' => 'stonefellow'],
        ['title' => 'Roger Huston', 'slug' => 'roger-huston'],
    ];
}

function music_public_shell_context(): array
{
    $user = current_user();
    $profile = primary_admin_profile();
    $profileName = public_profile_name();
    $profileImage = $profile
        ? user_profile_image_url($profile)
        : app_url('assets/images/david-evans-profile.jpg');

    return [
        'user' => $user,
        'projects' => music_public_shell_projects(),
        'profile_name' => $profileName,
        'profile_image' => $profileImage,
    ];
}

function music_render_public_sidebar(
    array $context,
    string $activePage = ''
): void {
    nmm_render_public_sidebar($context);
}

function music_render_public_header(array $context): void
{
    // The account control is rendered by the shared PHP template for every
    // public media, blog, portfolio, and related workspace page.
    ?>
<link
    rel="stylesheet"
    href="<?=e(app_url('assets/css/public-account-menu-v66q7.css?v=20260731-v66Q7'))?>"
>
<header class="workspace-header">
    <button
        aria-controls="workspaceSidebar"
        aria-expanded="false"
        aria-label="Open sidebar"
        class="sidebar-toggle"
        data-sidebar-open
        type="button"
    >
        <span></span><span></span><span></span>
    </button>

    <?php nmm_render_mobile_brand(); ?>

    <div class="workspace-header-actions">
        <?php nmm_render_public_account_menu(); ?>
    </div>
</header>
<?php
}

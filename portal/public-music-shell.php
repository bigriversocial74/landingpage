<?php
declare(strict_types=1);

require_once __DIR__ . '/public-sidebar.php';

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

    $dashboardUrl = '';
    $accountUrl = '';
    $roleLabel = '';

    if ($user) {
        $script = $user['role'] === 'admin'
            ? 'admin.php'
            : 'client.php';
        $dashboardUrl = app_url('portal/' . $script);
        $accountUrl = app_url(
            'portal/' . $script . '?view=account'
        );
        $roleLabel = $user['role'] === 'admin'
            ? 'Administrator'
            : 'Client';
    }

    return [
        'user' => $user,
        'projects' => music_public_shell_projects(),
        'profile_name' => $profileName,
        'profile_image' => $profileImage,
        'dashboard_url' => $dashboardUrl,
        'account_url' => $accountUrl,
        'role_label' => $roleLabel,
    ];
}

function music_render_public_sidebar(
    array $context,
    string $activePage = ''
): void {
    nmm_render_public_sidebar($context);
}

function music_render_public_header(
    array $context
): void {
    $user = $context['user'];
?>
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
<?php if($user):?>
<div class="workspace-account" data-public-account>
<button
    class="workspace-account-toggle"
    type="button"
    data-public-account-toggle
    aria-expanded="false"
>
<img
    src="<?=e(user_profile_image_url($user))?>"
    alt=""
>
<span>
<strong><?=e($user['display_name'])?></strong>
<small><?=e($context['role_label'])?></small>
</span>
<em aria-hidden="true">⌄</em>
</button>
<nav
    class="workspace-account-menu"
    data-public-account-menu
    hidden
>
<a href="<?=e($context['dashboard_url'])?>">Dashboard</a>
<a href="<?=e($context['account_url'])?>">Account settings</a>
<a href="<?=e(app_url('portal/logout.php'))?>">Sign out</a>
</nav>
</div>
<?php else:?>
<a
    class="workspace-header-action primary"
    href="<?=e(app_url('portal/login.php?role=client'))?>"
>Client Login</a>
<a
    class="workspace-header-action"
    href="<?=e(app_url('portal/login.php?role=admin'))?>"
>Admin Login</a>
<?php endif;?>
</div>
</header>
<?php
}

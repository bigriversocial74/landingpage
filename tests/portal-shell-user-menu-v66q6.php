<?php
declare(strict_types=1);

function v66q6_fail(string $message): never
{
    fwrite(STDERR, "v66Q.6 contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$account = (string)file_get_contents($root . '/portal/account-menu.php');
$public = (string)file_get_contents($root . '/portal/public-account-menu.php');
$landing = (string)file_get_contents($root . '/landing-page.php');
$landingJs = (string)file_get_contents($root . '/assets/js/landing-page.js');
$sitePublicJs = (string)file_get_contents($root . '/assets/js/site-public.js');
$shell = (string)file_get_contents($root . '/portal/bootstrap-shell.php');
$sidebar = (string)file_get_contents($root . '/portal/sidebar.php');
$social = (string)file_get_contents($root . '/portal/social-posts.php');

foreach ([$account, $public, $landing, $landingJs, $sitePublicJs, $shell, $sidebar, $social] as $source) {
    if ($source === '') v66q6_fail('Unable to read retained source.');
}

if (substr_count($shell, "require __DIR__ . '/sidebar.php'") !== 1) {
    v66q6_fail('The authenticated shell must include the canonical sidebar exactly once.');
}
foreach (['$isAdmin', 'nmm_module_enabled(', "['role']"] as $forbidden) {
    if (str_contains($sidebar, $forbidden)) {
        v66q6_fail('Canonical sidebar contains a role, module, or page variation: ' . $forbidden);
    }
}
foreach (glob($root . '/portal/*.php') ?: [] as $portalFile) {
    if (basename($portalFile) === 'sidebar.php') continue;
    $portalSource = (string)file_get_contents($portalFile);
    foreach (['<aside class="portal-sidebar', "class='portal-sidebar", 'data-portal-sidebar'] as $duplicateMarker) {
        if (str_contains($portalSource, $duplicateMarker)) {
            v66q6_fail('Duplicate authenticated sidebar markup found in portal/' . basename($portalFile));
        }
    }
}

foreach (['portal-user-avatar', "['display_name']", 'portal-account-chevron', 'Dashboard', 'Settings', 'Sign out'] as $needle) {
    if (!str_contains($account, $needle)) v66q6_fail('Authenticated account menu missing ' . $needle);
}
foreach (['Administrator</', 'Client</', "['email']"] as $forbidden) {
    if (str_contains($account, $forbidden)) v66q6_fail('Authenticated trigger exposes ' . $forbidden);
}
foreach (['Client login', 'Administrator login', 'portal/login.php?role=client', 'portal/login.php?role=admin'] as $needle) {
    if (!str_contains($public, $needle)) v66q6_fail('Public account menu missing ' . $needle);
}
foreach (['nmm_render_public_account_menu', 'nmm_inject_public_account_menu', 'public-account-menu-v66q7.css'] as $needle) {
    if (!str_contains($landing, $needle)) v66q6_fail('Public header integration missing ' . $needle);
}
foreach ([$landingJs, $sitePublicJs] as $loader) {
    if (str_contains($loader, 'public-user-menu-v66q6.js')) v66q6_fail('Old public menu injection remains loaded.');
}
foreach (['Publishing +', 'portal-top-action', 'portal-shell-v66q6.js'] as $forbidden) {
    if (str_contains($shell, $forbidden)) v66q6_fail('Old portal shell behavior remains: ' . $forbidden);
}
foreach (['Recent stories', 'Social Feed', 'portal/publish-story.php', 'portal/publish-social-post.php'] as $needle) {
    if (!str_contains($social, $needle)) v66q6_fail('My Feed missing ' . $needle);
}
foreach (['social-feed-toolbar', 'social-feed-guidance', '?modal=1'] as $forbidden) {
    if (str_contains($social, $forbidden)) v66q6_fail('My Feed retains removed behavior: ' . $forbidden);
}

echo "v66Q.6 canonical sidebar, account menus, My Feed, and public-header contract passed.\n";

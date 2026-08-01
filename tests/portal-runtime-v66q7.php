<?php
declare(strict_types=1);

function v66q7_fail(string $message): never
{
    fwrite(STDERR, "v66Q.7 contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        v66q7_fail('Unable to read ' . $path);
    }
    return $content;
};

$bootstrap = $read('portal/bootstrap.php');
$foundation = $read('portal/bootstrap-foundation.php');
$auth = $read('portal/bootstrap-auth.php');
$shell = $read('portal/bootstrap-shell.php');
$sidebar = $read('portal/sidebar.php');
$navigation = $read('portal/navigation.php');
$account = $read('portal/account-menu.php');
$publishing = $read('portal/publishing-center.php');
$publishingJs = $read('assets/js/portal-publishing-v66q7.js');
$myFeed = $read('portal/social-posts.php');
$federatedFeed = $read('portal/federated-feed.php');
$federatedMessages = $read('portal/federated-messages.php');
$publicMenu = $read('portal/public-account-menu.php');
$landing = $read('landing-page.php');
$landingJs = $read('assets/js/landing-page.js');
$sitePublicJs = $read('assets/js/site-public.js');
$portalJs = $read('assets/js/portal.js');

foreach ([
    "require __DIR__ . '/bootstrap-foundation.php'",
    "require_once __DIR__ . '/bootstrap-auth.php'",
    "require_once __DIR__ . '/bootstrap-shell.php'",
] as $contract) {
    if (!str_contains($bootstrap, $contract)) {
        v66q7_fail('Bootstrap include boundary missing: ' . $contract);
    }
}
if (!str_contains($foundation, "require_once __DIR__ . '/navigation.php'")) {
    v66q7_fail('Canonical navigation is not loaded by the foundation.');
}
if (!str_contains($auth, 'function current_user()')) {
    v66q7_fail('Authentication helpers were not retained.');
}

if (substr_count($shell, "require __DIR__ . '/sidebar.php'") !== 1) {
    v66q7_fail('The authenticated shell must include exactly one shared sidebar.');
}
foreach (['$isAdmin', 'nmm_module_enabled(', "['role']", 'portal_navigation_groups('] as $forbidden) {
    if (str_contains($sidebar, $forbidden)) {
        v66q7_fail('Shared sidebar contains a forbidden variation: ' . $forbidden);
    }
}
foreach (['Operations', 'Relationships', 'Work', 'System', 'Agent Chat', 'My Feed', 'Unified Inbox', 'Action Center', 'Visitor Intelligence', 'Site Analytics'] as $contract) {
    if (!str_contains($navigation, $contract)) {
        v66q7_fail('Server navigation missing: ' . $contract);
    }
}
if (str_contains($navigation, "'Notifications'")) {
    v66q7_fail('Notifications remains a sidebar label.');
}
foreach ([
    "nmm_module_enabled('clients')",
    "nmm_module_enabled('leads')",
    "nmm_module_enabled('music_library')",
    "nmm_module_enabled('social_feed')",
] as $contract) {
    if (!str_contains($navigation, $contract)) {
        v66q7_fail('Navigation module guard missing: ' . $contract);
    }
}

foreach (['portal-user-avatar', "['display_name']", 'portal-account-chevron', 'Dashboard', 'Settings', 'Sign out'] as $contract) {
    if (!str_contains($account, $contract)) {
        v66q7_fail('Authenticated account menu missing: ' . $contract);
    }
}
foreach (['Administrator</', 'Client</', "['email']"] as $forbidden) {
    if (str_contains($account, $forbidden)) {
        v66q7_fail('Authenticated account trigger exposes a forbidden label: ' . $forbidden);
    }
}
if (str_contains($shell, 'publishing-center-trigger') || str_contains($shell, '>Publishing +<')) {
    v66q7_fail('Header Publishing + remains in the shell.');
}
if (str_contains($shell, 'portal-top-action') && str_contains($shell, 'call-center')) {
    v66q7_fail('Topbar Call Center action remains in the shell.');
}

foreach (['story', 'social-post', 'blog', 'event', 'proposal', 'project', 'music-track', 'music-album', 'music-playlist'] as $key) {
    if (!str_contains($publishing, "'key' => '{$key}'")) {
        v66q7_fail('Publishing catalog missing type: ' . $key);
    }
}
foreach (['data-footer-publishing', 'data-footer-publishing-frame', 'data-footer-publishing-direct-open', 'portal-publishing-v66q7.js'] as $contract) {
    if (!str_contains($publishing, $contract)) {
        v66q7_fail('Footer Publishing workspace missing: ' . $contract);
    }
}
foreach (['nmm_module_enabled((string)$module)', 'window.location.origin', "searchParams.set('modal', '1')", 'data-footer-publishing-direct-open', 'publish-story.php', 'publish-social-post.php'] as $contract) {
    $source = str_contains($contract, 'nmm_module') ? $publishing : $publishingJs;
    if (!str_contains($source, $contract)) {
        v66q7_fail('Publishing progressive-enhancement contract missing: ' . $contract);
    }
}
foreach (['stopImmediatePropagation', "addEventListener('click',", 'portal-dashboard-publishing-v66q5.js', 'portal-shell-v66q6.js', 'portal-unified-runtime-v66q3.js'] as $forbidden) {
    if (str_contains($shell . $publishing . $publishingJs, $forbidden) && $forbidden !== "addEventListener('click',") {
        v66q7_fail('Obsolete or capture-layer Publishing behavior remains live: ' . $forbidden);
    }
}

$storiesPosition = strpos($myFeed, 'Recent stories');
$feedPosition = strpos($myFeed, 'Social Feed');
if ($storiesPosition === false || $feedPosition === false || $storiesPosition >= $feedPosition) {
    v66q7_fail('My Feed does not render Stories before Social Feed.');
}
foreach ([
    "require_once __DIR__ . '/stories-service.php'",
    "require_once __DIR__ . '/social-posts-service.php'",
    "require_once __DIR__ . '/federated-timeline.php'",
    'story-rail-create',
    'my-feed-story-create',
    'Follow users and their content will appear here.',
    'social_posts_render_card',
    'federated_timeline_query',
] as $contract) {
    if (!str_contains($myFeed, $contract)) {
        v66q7_fail('My Feed contract missing: ' . $contract);
    }
}

foreach ([
    "require_once __DIR__ . '/stories-service.php'",
    "require_once __DIR__ . '/social-posts-service.php'",
    "require_once __DIR__ . '/federated-timeline.php'",
    'Existing migrations are not assumed missing.',
    'Follow users and their content will appear here.',
] as $contract) {
    if (!str_contains($federatedFeed, $contract)) {
        v66q7_fail('Federated Timeline contract missing: ' . $contract);
    }
}
foreach ([
    "require_once __DIR__ . '/homeserver-adapter.php'",
    "require_once __DIR__ . '/federated-messaging.php'",
    'Existing migrations are not assumed missing.',
    'No federated conversations match this view.',
    'No conversation selected.',
    '>Notification Delivery</a>',
] as $contract) {
    if (!str_contains($federatedMessages, $contract)) {
        v66q7_fail('Federated Messages contract missing: ' . $contract);
    }
}

foreach (['Client login', 'Administrator login', 'data-public-account-menu'] as $contract) {
    if (!str_contains($publicMenu, $contract)) {
        v66q7_fail('Public account menu missing: ' . $contract);
    }
}
foreach ([
    "require_once __DIR__ . '/portal/public-account-menu.php'",
    'nmm_inject_public_account_menu',
    'nmm_render_public_account_menu',
    'public-account-menu-v66q7.css',
] as $contract) {
    if (!str_contains($landing, $contract)) {
        v66q7_fail('Public header integration missing: ' . $contract);
    }
}
foreach ([$landingJs, $sitePublicJs] as $loader) {
    if (str_contains($loader, 'public-user-menu-v66q6.js')) {
        v66q7_fail('Obsolete public account-menu injector remains live.');
    }
}

if (substr_count($shell, 'data-admin-assistant-messages') !== 1) {
    v66q7_fail('Agent Chat must contain one conversation canvas.');
}
if (substr_count($shell, 'data-admin-assistant-form') !== 1) {
    v66q7_fail('Agent Chat must contain one composer.');
}
foreach (['addAdminUserMessage(query);', 'openAdminChat();'] as $contract) {
    if (!str_contains($portalJs, $contract)) {
        v66q7_fail('Agent Chat retained behavior missing: ' . $contract);
    }
}

foreach ([
    '.github/workflows/build-publishing-center-v66q.yml',
    '.github/workflows/run-publishing-builder-v66q.yml',
    '.github/workflows/repair-publishing-center-v66q.yml',
    '.github/workflows/finalize-publishing-center-v66q.yml',
    '.github/v66q-build-trigger',
] as $temporary) {
    if (is_file($root . '/' . $temporary)) {
        v66q7_fail('Temporary repair artifact remains: ' . $temporary);
    }
}

echo "v66Q.7 portal runtime, feed, Publishing, navigation, and account-menu contract passed.\n";

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
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        v66q7_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        v66q7_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$bootstrap = $read('portal/bootstrap.php');
$foundation = $read('portal/bootstrap-foundation.php');
$auth = $read('portal/bootstrap-auth.php');
$shell = $read('portal/bootstrap-shell.php');
$sidebar = $read('portal/sidebar.php');
$navigation = $read('portal/navigation.php');
$account = $read('portal/account-menu.php');
$publishing = $read('portal/publishing-center.php');
$myFeed = $read('portal/social-posts.php');
$feedCss = $read('assets/css/social-feed-v66q7.css');
$federatedFeed = $read('portal/federated-feed.php');
$federatedMessages = $read('portal/federated-messages.php');
$publicMenu = $read('portal/public-account-menu.php');
$landing = $read('landing-page.php');
$landingJs = $read('assets/js/landing-page.js');
$sitePublicJs = $read('assets/js/site-public.js');
$agentChat = $read('portal/agent-chat-view.php');

foreach ([
    "require __DIR__ . '/bootstrap-foundation.php'",
    "require_once __DIR__ . '/bootstrap-auth.php'",
    "require_once __DIR__ . '/bootstrap-shell.php'",
] as $contract) {
    $require($bootstrap, $contract, 'Bootstrap boundary');
}
$require($foundation, "require_once __DIR__ . '/navigation.php'", 'Bootstrap foundation');
$require($auth, 'function current_user()', 'Authentication helpers');

if (substr_count($shell, "require __DIR__ . '/sidebar.php'") !== 1) {
    v66q7_fail('The authenticated shell must include exactly one shared sidebar.');
}
foreach (['$isAdmin', 'nmm_module_enabled(', "['role']", 'portal_navigation_groups('] as $needle) {
    $forbid($sidebar, $needle, 'Canonical sidebar');
}
foreach (['$portalSidebarGroups', 'data-portal-sidebar', 'data-portal-navigation'] as $contract) {
    $require($sidebar, $contract, 'Canonical sidebar');
}
foreach ([
    'Operations', 'Relationships', 'Work', 'System', 'Agent Chat', 'Dashboard',
    'My Feed', 'Music Library', 'Unified Inbox', 'Call Center', 'CRM',
    'Administrators', 'Action Center', 'Visitor Intelligence', 'Site Analytics',
] as $contract) {
    $require($navigation, $contract, 'Server navigation');
}
foreach ([
    "nmm_module_enabled('clients')",
    "nmm_module_enabled('leads')",
    "nmm_module_enabled('music_library')",
    "nmm_module_enabled('social_feed')",
    "nmm_module_enabled('rss')",
] as $contract) {
    $require($navigation, $contract, 'Navigation module guard');
}
$forbid($navigation, "'Notifications'", 'Server navigation');

foreach (['portal-user-avatar', "['display_name']", 'portal-account-chevron', 'Dashboard', 'Settings', 'Sign out'] as $contract) {
    $require($account, $contract, 'Authenticated account menu');
}
foreach (['Administrator</', 'Client</', "['email']"] as $needle) {
    $forbid($account, $needle, 'Authenticated account trigger');
}

foreach (['data-admin-quick-toggle', 'data-admin-launcher-tab="publishing"', 'publishing_center_render_footer_links'] as $contract) {
    $require($shell, $contract, 'Footer publishing launcher');
}
foreach (['publishing-center-trigger', '>Publishing +<', '<iframe', 'portal-publishing-v66q7.js', 'portal-dashboard-publishing-v66q5.js', 'portal-shell-v66q6.js', 'portal-unified-runtime-v66q3.js'] as $needle) {
    $forbid($shell, $needle, 'Portal shell');
}
if (str_contains($shell, 'portal-top-action') && str_contains($shell, 'call-center')) {
    v66q7_fail('Topbar Call Center action remains in the shell.');
}

foreach ([
    'story', 'social-post', 'blog', 'event', 'booking', 'syndication',
    'portfolio', 'resume', 'music-track', 'music-album', 'music-playlist',
    'client', 'lead', 'proposal', 'project', 'knowledge', 'file',
] as $key) {
    $require($publishing, "'key' => '{$key}'", 'Publishing catalog');
}
foreach (['nmm_module_enabled((string)$module)', 'data-publishing-direct', 'publishing_center_render_footer_links'] as $contract) {
    $require($publishing, $contract, 'Publishing catalog');
}
foreach (['?modal=1', '<iframe', 'data-footer-publishing-frame', 'searchParams.set', 'addEventListener'] as $needle) {
    $forbid($publishing, $needle, 'Publishing catalog');
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
    'portal/publish-story.php',
    'portal/publish-social-post.php',
    'Follow users and their content will appear here.',
    'social_posts_render_card',
    'federated_timeline_query',
] as $contract) {
    $require($myFeed, $contract, 'My Feed');
}
foreach (['Posts and Stories', 'social-feed-guidance', '?modal=1'] as $needle) {
    $forbid($myFeed, $needle, 'My Feed');
}
$require(
    $feedCss,
    '.my-feed-item-local .pod-social-card>header>div>span{display:none}',
    'My Feed account-handle suppression'
);

foreach ([
    "require_once __DIR__ . '/publishing.php'",
    "require_once __DIR__ . '/stories-service.php'",
    "require_once __DIR__ . '/social-posts-service.php'",
    "require_once __DIR__ . '/federated-timeline.php'",
    'Existing migrations are not assumed missing.',
    'Follow users and their content will appear here.',
] as $contract) {
    $require($federatedFeed, $contract, 'Federated Timeline entry point');
}
$forbid($federatedFeed, "view=delivery'))?>Notification Delivery", 'Federated Timeline markup');

foreach ([
    "require_once __DIR__ . '/homeserver-adapter.php'",
    "require_once __DIR__ . '/federated-messaging.php'",
    'Existing migrations are not assumed missing.',
    'No federated conversations match this view.',
    'No conversation selected.',
    'The failure was contained so the portal did not return HTTP 500.',
] as $contract) {
    $require($federatedMessages, $contract, 'Federated Messages entry point');
}

foreach (['Client login', 'Administrator login', 'data-public-account-menu'] as $contract) {
    $require($publicMenu, $contract, 'Public account menu');
}
foreach ([
    "require_once __DIR__ . '/portal/public-account-menu.php'",
    'nmm_inject_public_account_menu',
    'nmm_render_public_account_menu',
    'public-account-menu-v66q7.css',
] as $contract) {
    $require($landing, $contract, 'Public header integration');
}
foreach ([$landingJs, $sitePublicJs] as $loader) {
    $forbid($loader, 'public-user-menu-v66q6.js', 'Public JavaScript loader');
}

foreach (['data-agent-chat-page', 'data-agent-chat-empty', 'portal-agent-chat-v66q4.js'] as $contract) {
    $require($agentChat, $contract, 'Agent Chat retained workspace');
}

foreach ([
    'assets/js/portal-publishing-v66q7.js',
    'assets/js/portal-runtime-v66q7.js',
    'assets/css/portal-runtime-v66q7.css',
    'portal/account-menu-v66q7.php',
    'portal/bootstrap-core-v66q7.php',
    '.github/workflows/build-publishing-center-v66q.yml',
    '.github/workflows/run-publishing-builder-v66q.yml',
    '.github/workflows/repair-publishing-center-v66q.yml',
    '.github/workflows/finalize-publishing-center-v66q.yml',
    '.github/v66q-build-trigger',
] as $obsolete) {
    if (is_file($root . '/' . $obsolete)) {
        v66q7_fail('Obsolete or temporary repair artifact remains: ' . $obsolete);
    }
}

echo "v66Q.7 portal runtime, feed, publishing, navigation, and account-menu contract passed.\n";

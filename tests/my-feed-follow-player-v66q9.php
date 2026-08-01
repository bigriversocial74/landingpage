<?php
declare(strict_types=1);

function v66q9_fail(string $message): never
{
    fwrite(STDERR, "v66Q.9 contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        v66q9_fail('Unable to read ' . $path);
    }
    return $content;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        v66q9_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        v66q9_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$feed = $read('portal/social-posts.php');
$runtime = $read('portal/my-feed-runtime.php');
$follow = $read('portal/public-follow.php');
$followCss = $read('assets/css/public-follow-v66q9.css');
$followJs = $read('assets/js/public-follow-v66q9.js');
$publicSidebar = $read('portal/public-sidebar.php');
$publicSidebarJs = $read('assets/js/public-sidebar.js');
$publicAccount = $read('portal/public-account-menu.php');
$portalSidebar = $read('portal/sidebar.php');
$portalShellCss = $read('assets/css/portal-shell-v66q7.css');
$musicCss = $read('assets/css/music-mobile-upgrade-v66n.css');

foreach ([
    'function my_feed_table_columns',
    'information_schema.columns',
    'function my_feed_stories_capabilities',
    'function my_feed_load_stories',
    'local Stories query',
    'remote Stories query',
    'function my_feed_timeline_capabilities',
    'function my_feed_load_federated_posts',
    'activitypub_actor_controls',
    'activitypub_following',
    'activitypub_remote_post_actions',
] as $contract) {
    $require($runtime, $contract, 'Production My Feed runtime');
}
foreach ([
    "require_once __DIR__ . '/my-feed-runtime.php'",
    'my_feed_load_stories($userId, 40)',
    'my_feed_load_federated_posts($userId, 150)',
    'data-my-feed-runtime="v66Q.9"',
    'Recent stories',
    'Follow users and their content will appear here.',
] as $contract) {
    $require($feed, $contract, 'My Feed');
}
foreach ([
    'My Feed recovered from a service error.',
    'Stories are temporarily unavailable.',
    'Federated Timeline is temporarily unavailable.',
    'The page recovered without returning an HTTP 500 response.',
] as $forbidden) {
    $forbid($feed, $forbidden, 'My Feed diagnostics UI');
}
$storiesPosition = strpos($feed, 'Recent stories');
$feedPosition = strpos($feed, '<span>Social Feed</span>');
if ($storiesPosition === false || $feedPosition === false || $storiesPosition >= $feedPosition) {
    v66q9_fail('Stories must render before the Social Feed.');
}

foreach ([
    'POD / HomeServer',
    'RSS Feed',
    'data-follow-tab="pod"',
    'data-follow-tab="rss"',
    'pod-discovery.php',
    'follow-pod.php',
    'blog-feed.php',
    'The POD does not receive your password',
] as $contract) {
    $require($follow, $contract, 'Unified Follow modal');
}
foreach ([
    'data-follow-modal-open',
    'public-sidebar-follow-link',
    'nmm_render_public_follow_modal',
] as $contract) {
    $require($publicSidebar, $contract, 'Public sidebar Follow action');
}
foreach ([
    'nmm_public_follow_trigger_html',
    'nmm_public_follow_modal_html',
] as $contract) {
    $require($publicAccount, $contract, 'Landing-page Follow injection');
}
foreach ([
    'ArrowLeft',
    'ArrowRight',
    'navigator.clipboard',
    'public-follow-open',
] as $contract) {
    $require($followJs, $contract, 'Follow modal accessibility');
}
foreach ([
    'data-rss-modal-open',
    'data-rss-modal-close',
    'RSS Feed</span>',
] as $forbidden) {
    $forbid($publicSidebar . $publicSidebarJs, $forbidden, 'Legacy RSS-only modal');
}

foreach ([
    'portal-nav-group-label',
    'portal-nav-group-links',
    'data-portal-navigation',
] as $contract) {
    $require($portalSidebar, $contract, 'Shared portal sidebar');
}
foreach ([
    'portal-nav-group-toggle',
    'data-nav-group-toggle',
    'aria-expanded="true"',
] as $forbidden) {
    $forbid($portalSidebar, $forbidden, 'Plain text portal sidebar');
}
foreach ([
    'background:transparent',
    'border-radius:0',
    'text-decoration:underline',
    'font-size:13px',
] as $contract) {
    $require($portalShellCss, $contract, 'Plain text portal sidebar styling');
}
foreach ([
    'box-shadow:inset 3px',
    'background:#e8fafa',
    'border-radius:9px;color:#556171',
] as $forbidden) {
    $forbid($portalShellCss, $forbidden, 'Decorative portal navigation');
}
$require($followCss, '.workspace-sidebar .sidebar-actions>a', 'Plain text public sidebar');

foreach ([
    'Professional global player',
    'grid-template-columns:60px minmax(180px,270px) minmax(340px,1fr)',
    '.music-player-copy strong',
    'font-size:15px!important',
    '.music-player-copy span',
    'font-size:12px!important',
    '.music-player-timeline',
    'input[type="range"]',
    'data-music-previous',
    'data-music-next',
    'data-music-toggle',
    'data-music-queue-toggle',
    'grid-template-areas:',
    '"cover identity controls utility"',
    '"timeline timeline timeline timeline"',
    'font-size:13px!important',
    'font-size:10px!important',
    'overscroll-behavior-inline:contain',
    'scroll-snap-type:x mandatory',
] as $contract) {
    $require($musicCss, $contract, 'Professional Music player');
}
foreach (['margin-inline:-16px', 'margin-inline:-18px'] as $forbidden) {
    $forbid($musicCss, $forbidden, 'Music Library viewport width');
}

foreach (['CREATE TABLE', 'ALTER TABLE', 'DROP TABLE'] as $forbidden) {
    $forbid($runtime . $feed . $follow, $forbidden, 'Runtime schema mutation');
}

echo "v66Q.9 My Feed, Follow modal, plain sidebar, and professional player contract passed.\n";

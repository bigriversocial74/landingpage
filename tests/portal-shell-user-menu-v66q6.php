<?php
declare(strict_types=1);

function v66q6_fail(string $message): never
{
    fwrite(STDERR, "v66Q.6 contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$sources = [
    'portal runtime' => file_get_contents($root . '/assets/js/portal-shell-v66q6.js'),
    'public runtime' => file_get_contents($root . '/assets/js/public-user-menu-v66q6.js'),
    'publishing' => file_get_contents($root . '/portal/publishing-center.php'),
    'social' => file_get_contents($root . '/portal/social-posts.php'),
    'landing loader' => file_get_contents($root . '/assets/js/landing-page.js'),
    'visual-site loader' => file_get_contents($root . '/assets/js/site-public.js'),
];

foreach ($sources as $name => $source) {
    if (!is_string($source) || $source === '') {
        v66q6_fail("Unable to read {$name} source.");
    }
}

$portalRuntime = $sources['portal runtime'];
$publicRuntime = $sources['public runtime'];
$publishing = $sources['publishing'];
$social = $sources['social'];

foreach ([
    "move('Unified Inbox', 'Relationships')",
    "move('Call Center', 'Relationships')",
    "move('Visitor Intelligence', 'System')",
    "move('Site Analytics', 'System')",
    "findLink(nav, 'Notifications')?.remove()",
    "findLink(nav, 'Communications')?.remove()",
    '.portal-header-user > a.portal-top-action[href*="view=call-center"]',
    '.portal-sidebar-foot a[href*="logout.php"]',
    'portal-user-menu-panel',
    "['Settings', appUrl('portal/admin.php?view=settings')]",
    "['Settings', appUrl('portal/client.php?view=account')]",
    "['Sign out', appUrl('portal/logout.php')]",
    "small.textContent = roleLabel",
] as $contract) {
    if (!str_contains($portalRuntime, $contract)) {
        v66q6_fail("Missing portal-shell contract: {$contract}");
    }
}

foreach ([
    'Client login',
    'Administrator login',
    'public-user-menu-panel',
    "appUrl('portal/login.php?role=client')",
    "appUrl('portal/login.php?role=admin')",
] as $contract) {
    if (!str_contains($publicRuntime, $contract)) {
        v66q6_fail("Missing public user-menu contract: {$contract}");
    }
}

foreach ([
    'portal-shell-v66q6.js?v=20260731-v66Q6',
    'portal-dashboard-publishing-v66q5.js?v=20260731-v66Q5',
    'data-publishing-option',
    'href="<?=e(app_url($item[\'url\']))?>"',
] as $contract) {
    if (!str_contains($publishing, $contract)) {
        v66q6_fail("Missing Publishing Center contract: {$contract}");
    }
}
if (str_contains($publishing, 'portal-unified-runtime-v66q3.js')) {
    v66q6_fail('The conflicting v66Q.3 Publishing controller remains loaded.');
}

if (str_contains($social, 'social-feed-toolbar') || str_contains($social, 'social-feed-guidance')) {
    v66q6_fail('My Feed still renders the removed heading, action row, or guidance copy.');
}
foreach ([
    'social-feed-stories',
    'social-feed-column',
    "portal/publish-story.php?modal=1",
    "portal/publish-social-post.php?modal=1",
] as $contract) {
    if (!str_contains($social, $contract)) {
        v66q6_fail("Missing My Feed contract: {$contract}");
    }
}

foreach (['landing loader', 'visual-site loader'] as $loader) {
    if (!str_contains($sources[$loader], 'public-user-menu-v66q6.js?v=20260731-v66Q6')) {
        v66q6_fail("{$loader} does not load the logged-out user menu.");
    }
}

echo "v66Q.6 portal shell, My Feed, Publishing, and user-menu contract passed.\n";

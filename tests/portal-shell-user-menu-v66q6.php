<?php
declare(strict_types=1);

function v66q6_fail(string $message): never
{
    fwrite(STDERR, "v66Q.6 contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$portalRuntime = file_get_contents($root . '/assets/js/portal-shell-v66q6.js');
$publicRuntime = file_get_contents($root . '/assets/js/public-user-menu-v66q6.js');
$bootstrap = file_get_contents($root . '/portal/bootstrap.php');
$publishing = file_get_contents($root . '/portal/publishing-center.php');
$social = file_get_contents($root . '/portal/social-posts.php');
$landing = file_get_contents($root . '/landing-page.php');
$builder = file_get_contents($root . '/portal/site-builder-core.php');

foreach ([
    'portal runtime' => $portalRuntime,
    'public runtime' => $publicRuntime,
    'bootstrap' => $bootstrap,
    'publishing' => $publishing,
    'social' => $social,
    'landing page' => $landing,
    'site builder' => $builder,
] as $name => $source) {
    if (!is_string($source) || $source === '') {
        v66q6_fail("Unable to read {$name} source.");
    }
}

foreach ([
    "move('Unified Inbox', 'Relationships')",
    "move('Call Center', 'Relationships')",
    "move('Visitor Intelligence', 'System')",
    "move('Site Analytics', 'System')",
    "findLink(nav, 'Notifications')?.remove()",
    "findLink(nav, 'Communications')?.remove()",
    'portal-user-menu-panel',
    "['Settings', appUrl('portal/admin.php?view=settings')]",
    "['Settings', appUrl('portal/client.php?view=account')]",
    "['Sign out', appUrl('portal/logout.php')]",
    '.portal-header-user > a.portal-top-action[href*="view=call-center"]',
    '.portal-sidebar-foot a[href*="logout.php"]',
] as $contract) {
    if (!str_contains($portalRuntime, $contract)) {
        v66q6_fail("Missing portal runtime contract: {$contract}");
    }
}

foreach ([
    'Client login',
    'Administrator login',
    'public-user-menu-panel',
    "portal/login.php?role=client",
    "portal/login.php?role=admin",
] as $contract) {
    if (!str_contains($publicRuntime, $contract)) {
        v66q6_fail("Missing public user-menu contract: {$contract}");
    }
}

if (!str_contains($bootstrap, 'portal-shell-v66q6.js?v=20260731-v66Q6')) {
    v66q6_fail('The role-aware portal shell runtime is not loaded globally.');
}
if (!str_contains($landing, 'public-user-menu-v66q6.js?v=20260731-v66Q6')) {
    v66q6_fail('The fallback public header does not load the user menu.');
}
if (!str_contains($builder, 'public-user-menu-v66q6.js?v=20260731-v66Q6')) {
    v66q6_fail('The visual-builder public header does not load the user menu.');
}

if (str_contains($social, 'social-feed-toolbar') || str_contains($social, 'social-feed-guidance')) {
    v66q6_fail('My Feed still renders the removed heading, toolbar, or guidance copy.');
}
foreach (['social-feed-stories', 'social-feed-column'] as $contract) {
    if (!str_contains($social, $contract)) {
        v66q6_fail("My Feed is missing {$contract}.");
    }
}
if (!str_contains($social, 'href="<?=e(app_url(\'portal/publish-story.php?modal=1\'))?>"')) {
    v66q6_fail('The Stories action is not a progressive direct link.');
}
if (!str_contains($social, 'href="<?=e(app_url(\'portal/publish-social-post.php?modal=1\'))?>"')) {
    v66q6_fail('The Social Feed create action is not a progressive direct link.');
}

if (!str_contains($publishing, '<a') || !str_contains($publishing, 'data-publishing-option')) {
    v66q6_fail('Publishing options are not progressive links.');
}
if (str_contains($publishing, 'portal-unified-runtime-v66q3.js')) {
    v66q6_fail('The superseded v66Q.3 modal controller remains loaded.');
}

echo "v66Q.6 portal shell, My Feed, and user-menu contract passed.\n";

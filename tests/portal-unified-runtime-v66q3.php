<?php
declare(strict_types=1);

function v66q3_fail(string $message): never
{
    fwrite(STDERR, "v66Q.3 contract failure: {$message}\n");
    exit(1);
}

$runtime = file_get_contents(
    __DIR__ . '/../assets/js/portal-unified-runtime-v66q3.js'
);
$styles = file_get_contents(
    __DIR__ . '/../assets/css/portal-unified-runtime-v66q3.css'
);
$publishing = file_get_contents(
    __DIR__ . '/../portal/publishing-center.php'
);
$social = file_get_contents(
    __DIR__ . '/../portal/social-posts.php'
);

foreach ([
    'runtime' => $runtime,
    'styles' => $styles,
    'publishing' => $publishing,
    'social' => $social,
] as $name => $source) {
    if (!is_string($source) || $source === '') {
        v66q3_fail("Unable to read {$name} source.");
    }
}

$requiredRuntimeContracts = [
    "move('Unified Inbox', 'Relationships')",
    "move('Visitor Intelligence', 'System')",
    "move('Site Analytics', 'System')",
    "findNavigationLink(nav, 'Notifications')?.remove()",
    "nav.querySelector('a[href*=\"view=clients\"]')",
    "document.body.dataset.portalActive !== 'social-posts'",
    "document.body.dataset.portalActive !== 'agent'",
    "chat.classList.add('admin-assistant-chat-integrated')",
    "event.stopImmediatePropagation()",
    "`${configured.pathname}${configured.search}${configured.hash}`",
    "window.location.origin",
];

foreach ($requiredRuntimeContracts as $contract) {
    if (!str_contains($runtime, $contract)) {
        v66q3_fail("Missing runtime contract: {$contract}");
    }
}

if (!str_contains($styles, 'admin-assistant-chat-integrated')) {
    v66q3_fail('Integrated Agent Chat styles are missing.');
}
if (!str_contains($styles, 'social-feed-toolbar')) {
    v66q3_fail('My Feed top-toolbar suppression is missing.');
}
if (!str_contains(
    $publishing,
    '../assets/js/portal-unified-runtime-v66q3.js?v=20260731-v66Q3'
)) {
    v66q3_fail('Publishing Center does not load the cache-busted v66Q.3 runtime.');
}
if (str_contains($publishing, 'publishing-agent-runtime-v66q2.js')) {
    v66q3_fail('The superseded v66Q.2 runtime remains loaded.');
}
if (!str_contains($social, 'social-feed-stories')) {
    v66q3_fail('The Stories section is missing from My Feed.');
}
if (!str_contains($social, 'social-feed-column')) {
    v66q3_fail('The Social Feed column is missing from My Feed.');
}

echo "v66Q.3 unified portal runtime contract passed.\n";

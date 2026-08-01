<?php
declare(strict_types=1);

function v66q5_fail(string $message): never
{
    fwrite(STDERR, "v66Q.5 contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$runtime = file_get_contents(
    $root . '/assets/js/portal-dashboard-publishing-v66q5.js'
);
$publishing = file_get_contents(
    $root . '/portal/publishing-center.php'
);
$admin = file_get_contents(
    $root . '/portal/admin.php'
);

foreach ([
    'runtime' => $runtime,
    'publishing' => $publishing,
    'admin' => $admin,
] as $name => $source) {
    if (!is_string($source) || $source === '') {
        v66q5_fail("Unable to read {$name} source.");
    }
}

$runtimeContracts = [
    "findLink(navigation.nav, 'Call Center')",
    "navigation.groups.get('Relationships')",
    "document.body.dataset.portalActive !== 'dashboard'",
    "a[href*=\"view=clients\"]",
    "'Active clients'",
    "'Open projects'",
    "'Unread communications'",
    "heading === 'Recent projects'",
    "window.addEventListener('click'",
    "event.stopImmediatePropagation()",
    "elements.frame.hidden = false",
    "elements.frame.removeAttribute('hidden')",
    "window.location.origin",
    "data-publishing-direct-fallback",
    "Open form directly",
];

foreach ($runtimeContracts as $contract) {
    if (!str_contains($runtime, $contract)) {
        v66q5_fail("Missing runtime contract: {$contract}");
    }
}

if (!str_contains(
    $publishing,
    'portal-dashboard-publishing-v66q5.js?v=20260731-v66Q5'
)) {
    v66q5_fail('Publishing Center does not load the cache-busted v66Q.5 controller.');
}

foreach ([
    '<span>Active clients</span>',
    '<span>Open projects</span>',
    '<span>Unread communications</span>',
    '<h2>Recent projects</h2>',
] as $dashboardContract) {
    if (!str_contains($admin, $dashboardContract)) {
        v66q5_fail("Dashboard source changed unexpectedly: {$dashboardContract}");
    }
}

echo "v66Q.5 dashboard, navigation, and Publishing contract passed.\n";

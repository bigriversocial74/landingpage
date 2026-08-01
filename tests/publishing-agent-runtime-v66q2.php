<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$runtime = (string)file_get_contents(
    $root . '/assets/js/publishing-agent-runtime-v66q2.js'
);
$publishing = (string)file_get_contents(
    $root . '/portal/publishing-center.php'
);
$portal = (string)file_get_contents(
    $root . '/assets/js/portal.js'
);

foreach ([
    'window.location.origin',
    'configured.pathname',
    'data-publishing-url',
    'closeEmptyAgentOverlay',
    "dataset.portalActive !== 'agent'",
    'messages.children.length > 0',
] as $needle) {
    if (!str_contains($runtime, $needle)) {
        throw new RuntimeException(
            'Runtime repair is missing: ' . $needle
        );
    }
}

if (!str_contains(
    $publishing,
    'publishing-agent-runtime-v66q2.js?v=20260731-v66Q2'
)) {
    throw new RuntimeException(
        'Publishing Center does not load the cache-busted v66Q.2 runtime.'
    );
}

if (!str_contains($portal, 'addAdminUserMessage(query);')
    || !str_contains($portal, 'openAdminChat();')) {
    throw new RuntimeException(
        'Submitted Agent queries no longer open the chat interface.'
    );
}

echo "Publishing and Agent runtime v66Q.2 regression passed\n";

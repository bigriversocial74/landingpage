<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$legacyRuntime = (string)file_get_contents(
    $root . '/assets/js/publishing-agent-runtime-v66q2.js'
);
$unifiedRuntimePath = $root . '/assets/js/portal-unified-runtime-v66q3.js';
$unifiedRuntime = is_file($unifiedRuntimePath)
    ? (string)file_get_contents($unifiedRuntimePath)
    : '';
$publishingRuntime = (string)file_get_contents(
    $root . '/assets/js/portal-dashboard-publishing-v66q5.js'
);
$agentRuntime = (string)file_get_contents(
    $root . '/assets/js/portal-agent-chat-v66q4.js'
);
$publishing = (string)file_get_contents(
    $root . '/portal/publishing-center.php'
);
$portal = (string)file_get_contents(
    $root . '/assets/js/portal.js'
);

$successorLoaded = str_contains(
    $publishing,
    'portal-dashboard-publishing-v66q5.js?v=20260731-v66Q5'
) && str_contains(
    $publishing,
    'portal-shell-v66q6.js?v=20260731-v66Q6'
);
$unifiedLoaded = str_contains(
    $publishing,
    'portal-unified-runtime-v66q3.js?v=20260731-v66Q3'
);

if ($successorLoaded) {
    foreach ([
        'window.location.origin',
        'configured.pathname',
        'dataset.publishingUrl',
        "window.addEventListener('click'",
        'event.stopImmediatePropagation()',
        'elements.frame.hidden = false',
    ] as $needle) {
        if (!str_contains($publishingRuntime, $needle)) {
            throw new RuntimeException(
                'Successor Publishing runtime is missing retained v66Q.2 protection: '
                . $needle
            );
        }
    }

    foreach ([
        "dataset.portalActive !== 'agent'",
        'integrateAgentWorkspace',
        'agent-chat-conversation',
        'admin-assistant-active',
        'messages.childElementCount > 0',
        'MutationObserver',
    ] as $needle) {
        if (!str_contains($agentRuntime, $needle)) {
            throw new RuntimeException(
                'Successor Agent runtime is missing retained v66Q.2 protection: '
                . $needle
            );
        }
    }
} elseif ($unifiedLoaded) {
    foreach ([
        'window.location.origin',
        'configured.pathname',
        'data-publishing-url',
        'integrateAgentChat',
        "dataset.portalActive !== 'agent'",
        'admin-assistant-chat-integrated',
        'MutationObserver',
    ] as $needle) {
        if (!str_contains($unifiedRuntime, $needle)) {
            throw new RuntimeException(
                'Unified runtime is missing retained v66Q.2 protection: '
                . $needle
            );
        }
    }
} else {
    foreach ([
        'window.location.origin',
        'configured.pathname',
        'data-publishing-url',
        'closeEmptyAgentOverlay',
        "dataset.portalActive !== 'agent'",
        'messages.children.length > 0',
    ] as $needle) {
        if (!str_contains($legacyRuntime, $needle)) {
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
            'Publishing Center does not load a certified Agent runtime.'
        );
    }
}

if (!str_contains($portal, 'addAdminUserMessage(query);')
    || !str_contains($portal, 'openAdminChat();')) {
    throw new RuntimeException(
        'Submitted Agent queries no longer open the chat interface.'
    );
}

echo "Publishing and Agent runtime v66Q.2 retained regression passed\n";

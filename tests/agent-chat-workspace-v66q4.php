<?php
declare(strict_types=1);

function v66q4_fail(string $message): never
{
    fwrite(STDERR, "v66Q.4 Agent Chat contract failure: {$message}\n");
    exit(1);
}

$view = file_get_contents(__DIR__ . '/../portal/agent-chat-view.php');
$runtime = file_get_contents(__DIR__ . '/../assets/js/portal-agent-chat-v66q4.js');
$styles = file_get_contents(__DIR__ . '/../assets/css/portal-agent-chat-v66q4.css');

foreach (['view' => $view, 'runtime' => $runtime, 'styles' => $styles] as $name => $source) {
    if (!is_string($source) || $source === '') {
        v66q4_fail("Unable to read {$name} source.");
    }
}

foreach ([
    'data-agent-chat-page',
    'data-agent-chat-empty',
    'How can I help?',
    'portal-agent-chat-v66q4.css?v=20260731-v66Q4',
    'portal-agent-chat-v66q4.js?v=20260731-v66Q4',
] as $contract) {
    if (!str_contains($view, $contract)) {
        v66q4_fail("Missing Agent Chat view contract: {$contract}");
    }
}

foreach ([
    'agent-quick-prompts',
    'agent-setup-section',
    'agent-setup-grid',
    'Connect the capabilities this agent may use.',
] as $legacy) {
    if (str_contains($view, $legacy)) {
        v66q4_fail("Legacy dashboard markup remains: {$legacy}");
    }
}

foreach ([
    "document.body.dataset.portalActive !== 'agent'",
    "page.append(chat, footer)",
    "chat.appendChild(loading)",
    "input.placeholder = 'Message your North Mountain agent…'",
    "messages.childElementCount > 0",
    "empty.hidden = hasMessages",
    "new MutationObserver(syncConversationState)",
    "chat.classList.add('agent-chat-conversation')",
    "footer.classList.add('agent-chat-composer-dock')",
] as $contract) {
    if (!str_contains($runtime, $contract)) {
        v66q4_fail("Missing Agent Chat runtime contract: {$contract}");
    }
}

foreach ([
    '.agent-chat-page',
    'grid-template-rows: minmax(0, 1fr) auto',
    '.agent-chat-empty[hidden]',
    '.agent-chat-conversation',
    '.agent-chat-composer-dock',
    '.admin-assistant-message-user',
    '.admin-assistant-message-assistant',
    'position: relative !important',
] as $contract) {
    if (!str_contains($styles, $contract)) {
        v66q4_fail("Missing Agent Chat style contract: {$contract}");
    }
}

echo "v66Q.4 Agent Chat workspace contract passed.\n";

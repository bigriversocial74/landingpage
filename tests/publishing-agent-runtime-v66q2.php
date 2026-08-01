<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') throw new RuntimeException('Missing ' . $path);
    return $content;
};

$shell = $read('portal/bootstrap-shell.php');
$publishing = $read('portal/publishing-center.php');
$portal = $read('assets/js/portal.js');
$agent = $read('portal/agent-chat-view.php');
$agentRuntime = $read('assets/js/portal-agent-chat-v66q4.js');

foreach (['portal-unified-runtime-v66q3.js', 'portal-dashboard-publishing-v66q5.js', 'portal-shell-v66q6.js', 'publishing-agent-runtime-v66q2.js'] as $obsolete) {
    if (str_contains($shell . $publishing, $obsolete)) {
        throw new RuntimeException('Obsolete runtime remains live: ' . $obsolete);
    }
}
foreach (['data-publishing-direct', 'data-publishing-option'] as $needle) {
    if (!str_contains($publishing, $needle)) throw new RuntimeException('Publishing link contract missing ' . $needle);
}
foreach (['<iframe', '?modal=1', 'event.stopImmediatePropagation()'] as $forbidden) {
    if (str_contains($publishing, $forbidden)) throw new RuntimeException('Publishing interception remains: ' . $forbidden);
}
foreach (['addAdminUserMessage(query);', 'openAdminChat();'] as $needle) {
    if (!str_contains($portal, $needle)) throw new RuntimeException('Submitted Agent query no longer opens chat: ' . $needle);
}
foreach (['data-agent-chat-page', 'data-agent-chat-empty'] as $needle) {
    if (!str_contains($agent, $needle)) throw new RuntimeException('Agent workspace missing ' . $needle);
}
foreach (['integrateAgentWorkspace', 'agent-chat-conversation', 'MutationObserver'] as $needle) {
    if (!str_contains($agentRuntime, $needle)) throw new RuntimeException('Agent runtime missing ' . $needle);
}

echo "Publishing and Agent runtime v66Q.2 retained regression passed\n";

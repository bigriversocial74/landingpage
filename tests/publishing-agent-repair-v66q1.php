<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') throw new RuntimeException('Missing ' . $path);
    return $content;
};

$foundation = $read('portal/bootstrap-foundation.php');
$admin = $read('portal/admin.php');
$agent = $read('portal/agent-chat-view.php');
$agentRuntime = $read('assets/js/portal-agent-chat-v66q4.js');
$social = $read('portal/social-posts.php');
$publishing = $read('portal/publishing-center.php');
$navigation = $read('portal/navigation.php');

if (!str_contains($foundation, "frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com")) {
    throw new RuntimeException('Portal CSP changed unexpectedly.');
}
foreach (['agent_chat_render($user)', 'portal_footer();', 'exit;'] as $needle) {
    if (!str_contains($admin, $needle)) throw new RuntimeException('Agent route missing ' . $needle);
}
foreach (['agent-home-intro', 'Private POD operating assistant', 'setup-dashboard', 'results-panel'] as $forbidden) {
    if (str_contains($agent, $forbidden)) throw new RuntimeException('Agent Chat regression remains: ' . $forbidden);
}
foreach (['data-agent-chat-page', 'data-agent-chat-empty', 'portal-agent-chat-v66q4.js'] as $needle) {
    if (!str_contains($agent, $needle)) throw new RuntimeException('Agent Chat workspace missing ' . $needle);
}
foreach (['agent-chat-conversation', 'MutationObserver', 'messages.childElementCount > 0'] as $needle) {
    if (!str_contains($agentRuntime, $needle)) throw new RuntimeException('Agent runtime missing ' . $needle);
}
foreach (['data-publishing-direct', 'portal/publish-story.php', 'portal/publish-social-post.php'] as $needle) {
    if (!str_contains($publishing, $needle)) throw new RuntimeException('Direct publishing missing ' . $needle);
}
foreach (['<iframe', '?modal=1'] as $forbidden) {
    if (str_contains($publishing, $forbidden)) throw new RuntimeException('Publishing container regression: ' . $forbidden);
}
if (!str_contains($navigation, "'My Feed'")) throw new RuntimeException('My Feed navigation label missing.');
if (!str_contains($navigation, "nmm_module_enabled('social_feed')")) throw new RuntimeException('My Feed navigation is not module-gated.');
if (!str_contains($social, "portal_header('My Feed', 'social-posts', \$user);")) throw new RuntimeException('My Feed page title is missing.');

echo "Publishing links, Agent Chat, and My Feed v66Q.1 retained regression passed\n";

<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/portal/federated-interactions.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$clean = federated_interactions_clean_remote_text('<p>Hello <strong>open web</strong>.</p><script>alert(1)</script>');
if ($clean !== 'Hello open web.') $fail('Remote reply sanitization failed.');
if (federated_interactions_normalize_domain('Social.Example.') !== 'social.example') {
    $fail('Federation domain normalization failed.');
}
try {
    federated_interactions_normalize_domain('https://bad.example/path');
    $fail('Invalid federation domain was accepted.');
} catch (RuntimeException) {
}

$paths = [
    'core' => 'portal/federated-interactions.php',
    'admin' => 'portal/federated-interactions-admin.php',
    'activitypub' => 'portal/activitypub-service.php',
    'activitypub_admin' => 'portal/activitypub-admin.php',
    'content' => 'portal/content-interactions.php',
    'inbox' => 'portal/unified-inbox.php',
    'endpoint' => 'activitypub-comment.php',
    'migration' => 'database/federated_interactions_v66g.sql',
    'schema' => 'database/north_mountain_portal.sql',
    'css' => 'assets/css/federated-interactions.css',
];
$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') $fail('Missing v66G source: ' . $path);
    $source[$key] = $content;
}

$checks = [
    ['remote reply table', 'activitypub_remote_comments', $source['migration']],
    ['remote reaction table', 'activitypub_remote_reactions', $source['migration']],
    ['following table', 'activitypub_following', $source['migration']],
    ['actor controls', 'activitypub_actor_controls', $source['migration']],
    ['domain blocks', 'activitypub_domain_blocks', $source['migration']],
    ['local object map', 'activitypub_local_objects', $source['migration']],
    ['outbox Follow support', "'Follow', 'Undo', 'Like', 'Announce'", $source['activitypub']],
    ['verified inbound bridge', 'federated_interactions_process_inbound($inboxId, $payload, $remote)', $source['activitypub']],
    ['remote attribution ownership', 'does not match the verified actor', $source['core']],
    ['remote object ownership', 'cannot change ownership', $source['core']],
    ['pre-moderated replies', '"pending"', $source['core']],
    ['local comment Create hook', 'federated_interactions_local_comment_event($commentId, \'Create\'', $source['content']],
    ['local comment Update hook', 'federated_interactions_local_comment_event($commentId, \'Update\'', $source['content']],
    ['local comment Delete hook', 'federated_interactions_local_comment_event($commentId, \'Delete\'', $source['content']],
    ['local reaction hook', 'federated_interactions_local_reaction_event(', $source['content']],
    ['public remote conversation', 'federated_interactions_render_public($post)', $source['content']],
    ['real Following collection', 'federated_interactions_following_document()', $source['activitypub']],
    ['signed outbound Follow', "'type' => 'Follow'", $source['core']],
    ['signed outbound Unfollow', "'type' => 'Undo'", $source['core']],
    ['actor block containment', 'activitypub_remote_comments SET status="hidden"', $source['core']],
    ['Unified Inbox remote comments', "'federated_comment'", $source['inbox']],
    ['Unified Inbox remote reactions', "'federated_reaction'", $source['inbox']],
    ['Unified Inbox outbound follows', "'federated_follow'", $source['inbox']],
    ['administrator moderation', 'moderate_federated_comment', $source['admin']],
    ['public comment object endpoint', 'federated_interactions_comment_object($commentId)', $source['endpoint']],
];
foreach ($checks as [$label, $needle, $haystack]) {
    if (!str_contains($haystack, $needle)) $fail('Missing ' . $label . ': ' . $needle);
}

$forbidden = [
    ['fake local remote user', 'INSERT INTO users', $source['core']],
    ['anonymous remote bypass', 'registered_auto', $source['core']],
    ['unsafe remote HTML output', 'echo $comment[\'body_text\']', $source['core']],
    ['automatic remote actor trust', "moderation_status='active'", $source['admin']],
];
foreach ($forbidden as [$label, $needle, $haystack]) {
    if (str_contains($haystack, $needle)) $fail('Forbidden ' . $label . ' detected.');
}

foreach ([
    'activitypub_remote_comments', 'activitypub_remote_reactions', 'activitypub_following',
    'activitypub_actor_controls', 'activitypub_domain_blocks', 'activitypub_local_objects',
] as $table) {
    if (substr_count($source['migration'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        $fail('Additive migration must define ' . $table . ' exactly once.');
    }
    if (substr_count($source['schema'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        $fail('Fresh schema must define ' . $table . ' exactly once.');
    }
}

foreach ([
    'tools/apply-federated-interactions-v66g.py',
    '.github/workflows/apply-federated-interactions-v66g.yml',
] as $temporary) {
    if (file_exists($root . '/' . $temporary)) $fail('Temporary v66G integration file remains: ' . $temporary);
}

echo "Federated Interactions v66G source and security regression passed.\n";

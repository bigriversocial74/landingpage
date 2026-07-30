<?php
declare(strict_types=1);

function db(): PDO { throw new RuntimeException('Database access is not expected in the pure timeline test.'); }
function activitypub_setting(string $key, string $default = ''): string { return $default; }
function activitypub_actor_url(): string { return 'https://pod.example/activitypub-actor.php'; }
function activitypub_normalize_url(string $url): string { return strtolower(rtrim(trim($url), '/')); }
function activitypub_https_url(string $url): bool {
    $parts = parse_url($url);
    return is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
}

$root = dirname(__DIR__);
require_once $root . '/portal/federated-timeline.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$clean = federated_timeline_clean_text('<p>Hello <strong>timeline</strong>.</p><script>alert(1)</script><img src="https://tracker.example/pixel">');
if ($clean !== 'Hello timeline.') $fail('Remote timeline text sanitization failed.');

$public = federated_timeline_visibility([
    'to' => ['https://www.w3.org/ns/activitystreams#Public'],
]);
$unlisted = federated_timeline_visibility([
    'cc' => ['https://www.w3.org/ns/activitystreams#Public'],
]);
$direct = federated_timeline_visibility([
    'to' => [activitypub_actor_url()],
]);
if ($public !== 'public' || $unlisted !== 'unlisted' || $direct !== 'direct') {
    $fail('Federated timeline visibility classification failed.');
}
if (!federated_timeline_mentions_local([
    'tag' => [[
        'type' => 'Mention',
        'href' => activitypub_actor_url(),
        'name' => '@owner@pod.example',
    ]],
])) $fail('Local federated mention detection failed.');

$attachments = federated_timeline_attachments([
    'attachment' => [
        ['type' => 'Image', 'url' => 'https://media.example/photo.jpg', 'name' => 'Photo'],
        ['type' => 'Image', 'url' => 'http://unsafe.example/photo.jpg', 'name' => 'Unsafe'],
        ['type' => 'Unknown', 'url' => 'https://media.example/file.bin', 'name' => 'File'],
    ],
]);
if (count($attachments) !== 2 || $attachments[0]['url'] !== 'https://media.example/photo.jpg') {
    $fail('Link-only remote attachment normalization failed.');
}
if ($attachments[1]['type'] !== 'Document') $fail('Unknown attachment type was not downgraded.');

$paths = [
    'core' => 'portal/federated-timeline.php',
    'page' => 'portal/federated-feed.php',
    'reply' => 'activitypub-timeline-reply.php',
    'service' => 'portal/activitypub-service.php',
    'http' => 'portal/activitypub-http.php',
    'interactions' => 'portal/federated-interactions.php',
    'inbox' => 'portal/unified-inbox.php',
    'admin' => 'portal/activitypub-admin.php',
    'cron' => 'cron/process-activitypub.php',
    'migration' => 'database/federated_timeline_v66h.sql',
    'schema' => 'database/north_mountain_portal.sql',
    'css' => 'assets/css/federated-timeline.css',
];
$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') $fail('Missing v66H source: ' . $path);
    $source[$key] = $content;
}

$checks = [
    ['remote post table', 'activitypub_remote_posts', $source['migration']],
    ['private user state', 'activitypub_timeline_user_state', $source['migration']],
    ['remote action receipts', 'activitypub_remote_post_actions', $source['migration']],
    ['disabled default', "('activitypub_timeline_enabled','0')", $source['migration']],
    ['link-only media default', "('activitypub_timeline_remote_media_mode','link_only')", $source['migration']],
    ['accepted following boundary', 'federated_timeline_following_accepted', $source['core']],
    ['unsolicited mention quarantine', '$status = $following ? \'active\' : \'pending\'', $source['core']],
    ['actor attribution ownership', 'does not match the verified actor', $source['core']],
    ['entry actor immutability', 'cannot change actor ownership', $source['core']],
    ['entry object immutability', 'cannot change its object target', $source['core']],
    ['remote media link-only', "'remote_media_mode' => 'link_only'", $source['core']],
    ['WebFinger JRD support', 'application/jrd+json', $source['http']],
    ['WebFinger subject ownership', 'WebFinger subject does not match', $source['core']],
    ['signed Like', '\'type\' => $type === \'like\' ? \'Like\' : \'Announce\'', $source['core']],
    ['signed reply Create', "'type' => 'Create'", $source['core']],
    ['signed Undo', "'type' => 'Undo'", $source['core']],
    ['reply Delete Tombstone', "'type' => 'Tombstone'", $source['core']],
    ['dereferenceable local reply', 'federated_timeline_reply_object($uuid)', $source['reply']],
    ['same-origin POST boundary', 'same_origin_request()', $source['page']],
    ['batched action loading', 'federated_timeline_actions_for_posts', $source['page'] . $source['core']],
    ['timeline inbound bridge', 'federated_timeline_process_inbound', $source['service']],
    ['delivery failure synchronization', 'federated_timeline_sync_delivery', $source['service'] . $source['core']],
    ['actor deletion containment', 'activitypub_remote_posts SET status="deleted"', $source['service']],
    ['actor block containment', 'activitypub_remote_posts SET status="hidden"', $source['interactions']],
    ['Unified Inbox mentions', "'federated_post'", $source['inbox']],
    ['Unified Inbox action failures', "'federated_timeline_action'", $source['inbox']],
    ['timeline navigation', 'Open Federated Timeline', $source['admin']],
    ['retention worker', 'federated_timeline_cleanup()', $source['cron']],
];
foreach ($checks as [$label, $needle, $haystack]) {
    if (!str_contains($haystack, $needle)) $fail('Missing ' . $label . ': ' . $needle);
}

foreach (['<img', '<audio', '<video', '<iframe', 'background-image:'] as $remoteMediaMarkup) {
    if (str_contains(strtolower($source['page']), strtolower($remoteMediaMarkup))) {
        $fail('Remote media is auto-rendered in the private timeline: ' . $remoteMediaMarkup);
    }
}
foreach (['activitypub_fetch_json($url', 'file_get_contents($url', 'curl_exec($url'] as $attachmentFetch) {
    if (str_contains($source['core'], $attachmentFetch)) {
        $fail('Remote attachment auto-fetch detected: ' . $attachmentFetch);
    }
}
if (!str_contains($source['page'], 'noopener noreferrer nofollow')) {
    $fail('Remote media links are missing outbound privacy attributes.');
}

foreach (['activitypub_remote_posts','activitypub_timeline_user_state','activitypub_remote_post_actions'] as $table) {
    if (substr_count($source['migration'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        $fail('Additive migration must define ' . $table . ' exactly once.');
    }
    if (substr_count($source['schema'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        $fail('Fresh schema must define ' . $table . ' exactly once.');
    }
}

foreach ([
    'tools/apply-federated-timeline-v66h.py',
    '.github/workflows/apply-federated-timeline-v66h.yml',
] as $temporary) {
    if (file_exists($root . '/' . $temporary)) $fail('Temporary v66H integration file remains: ' . $temporary);
}

echo "Federated Timeline v66H source, privacy, and security regression passed.\n";

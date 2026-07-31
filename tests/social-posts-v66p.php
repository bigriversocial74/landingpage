<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$files = [
    'service' => 'portal/social-posts-service.php',
    'activitypub' => 'portal/activitypub-service.php',
    'admin' => 'portal/social-posts.php',
    'object' => 'activitypub-social-post.php',
    'single' => 'social-post.php',
    'feed' => 'social-feed.php',
    'follow' => 'follow-pod.php',
    'landing' => 'landing-page.php',
    'builder' => 'portal/site-builder-core.php',
    'timeline' => 'portal/federated-feed.php',
    'bootstrap' => 'portal/bootstrap.php',
    'css' => 'assets/css/social-posts-v66p.css',
    'js' => 'assets/js/social-posts-v66p.js',
    'sql' => 'database/social_posts_v66p.sql',
    'fresh' => 'database/north_mountain_portal_v66p.sql',
];
$content = [];
foreach ($files as $key => $path) {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        $failures[] = 'Missing ' . $path;
        continue;
    }
    $content[$key] = (string)file_get_contents($full);
}
$expect = static function (string $key, string $needle, string $message) use (&$failures, $content): void {
    if (!isset($content[$key]) || !str_contains($content[$key], $needle)) {
        $failures[] = $message;
    }
};

foreach (['pod_social_posts','pod_social_post_events'] as $table) {
    $expect('sql', 'CREATE TABLE IF NOT EXISTS ' . $table, 'Missing table ' . $table);
}
foreach (['none', 'blogs', 'social', 'tabs'] as $mode) {
    $expect('service', "'" . $mode . "'", 'Missing landing mode ' . $mode);
}
$expect('service', "['Create', 'Update', 'Delete']", 'Create, Update, and Delete federation is required.');
$expect('service', "'type' => 'Note'", 'Permanent social posts must use ActivityPub Notes.');
$expect('service', "'type' => 'Tombstone'", 'Deleted posts must produce Tombstones.');
$expect('service', 'activitypub_queue_approved_followers', 'Delivery must reuse approved ActivityPub followers.');
$expect('activitypub', 'activitypub_payload_is_public', 'Public outbox audience filtering is required.');
$expect('activitypub', 'AND payload_json LIKE :public_marker', 'Public outbox must prefilter public payload candidates.');
$expect('service', "'https://www.w3.org/ns/activitystreams#Public'", 'Public ActivityStreams audience is missing.');
$expect('service', "'to' => [activitypub_followers_url()]", 'Follower-only audience is missing.');
$expect('service', 'social_posts_same_origin_url', 'Protected same-origin media validation is required.');
$expect('service', "External post links must use HTTPS.", 'External links must require HTTPS.');
$expect('service', 'blog_public_posts', 'Landing blog mode must reuse the existing blog publisher.');
$expect('service', 'blog-feed.php', 'Existing RSS must remain linked.');
$expect('service', 'social_posts_render_landing', 'Landing content renderer is missing.');
$expect('service', 'social_posts_render_portal_stream', 'Local posts must appear in the POD timeline workspace.');
$expect('admin', 'Save draft', 'Draft publishing is required.');
$expect('admin', 'Followers only', 'Follower-only publishing control is required.');
$expect('admin', 'Tabbed blog + social', 'Tabbed landing display control is required.');
$expect('admin', 'Blog and RSS remain independent', 'Blog/RSS preservation disclosure is required.');
$expect('object', 'application/activity+json', 'ActivityPub object content type is missing.');
$expect('object', "'visibility'] !== 'public'", 'Follower-only posts must not expose public object documents.');
$expect('object', "'type' => 'Tombstone'", 'Public deleted objects must return Tombstones.');
$expect('single', "'visibility'] !== 'public'", 'Follower-only posts must not expose public HTML pages.');
$expect('feed', 'activitypub_discovery_links', 'Public social feed must expose ActivityPub discovery.');
$expect('feed', 'syndication_discovery_links', 'Public social feed must preserve RSS discovery.');
$expect('follow', '/authorize_interaction?uri=', 'Remote follow handoff is missing.');
$expect('follow', 'rate_limit_exceeded', 'Remote follow handoff must be rate-limited.');
$expect('follow', 'The POD does not receive your password', 'Remote follow password boundary must be disclosed.');
$expect('landing', "require_once __DIR__ . '/portal/social-posts-service.php';", 'Default landing page service integration is missing.');
$expect('landing', 'social_posts_render_landing', 'Default landing page content integration is missing.');
$expect('builder', "require_once __DIR__.'/social-posts-service.php';", 'Visual builder service integration is missing.');
$expect('builder', "(string)(\$page['slug']??'')==='home'", 'Visual builder must limit automatic content to the home page.');
$expect('timeline', 'social_posts_render_portal_stream', 'Federated timeline local publishing section is missing.');
$expect('bootstrap', "'social-posts' => 'My Feed'", 'Administrator My Feed navigation entry is missing.');
$expect('bootstrap', "app_url('portal/social-posts.php')", 'Administrator navigation route is missing.');
$expect('js', "ArrowLeft", 'Keyboard-accessible tabs are required.');
$expect('js', "navigator.clipboard.writeText", 'Copyable Fediverse identity is required.');
$expect('css', '@media (max-width:720px)', 'Mobile social publishing layout is required.');
$expect('css', '@media (prefers-reduced-motion:reduce)', 'Reduced-motion support is required.');
$expect('fresh', 'SOURCE database/north_mountain_portal_v66o.sql;', 'Fresh schema must retain v66O.');
$expect('fresh', 'SOURCE database/social_posts_v66p.sql;', 'Fresh schema must include Social Posts v66P.');

foreach ([
    '.github/workflows/apply-social-posts-v66p.yml',
    '.github/workflows/apply-social-posts-navigation-v66p.yml',
    '.github/workflows/apply-private-outbox-v66p.yml',
    '.github/workflows/repair-social-posts-v66p.yml',
    'tools/repair-social-posts-v66p.py',
    'tools/apply-social-posts-v66p.py',
] as $temporary) {
    if (is_file($root . '/' . $temporary)) {
        $failures[] = 'Temporary integration file remains: ' . $temporary;
    }
}

if (isset($content['follow']) && preg_match('/curl_|file_get_contents\s*\(\s*\$target|Guzzle|fsockopen/i', $content['follow'])) {
    $failures[] = 'Follow helper must not fetch the visitor\'s remote server.';
}
if (isset($content['feed']) && preg_match('/<(img|audio|video|iframe)[^>]+https?:\/\//i', $content['feed'])) {
    $failures[] = 'Public social feed must not embed arbitrary remote media.';
}
if (isset($content['service']) && str_contains($content['service'], 'CREATE TABLE')) {
    $failures[] = 'Runtime service must not mutate schema.';
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Social Posts v66P source, privacy, landing, and UX regression passed.\n";

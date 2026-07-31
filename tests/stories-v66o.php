<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$files = [
    'service' => 'portal/stories-service.php',
    'page' => 'portal/stories.php',
    'api' => 'api/story-view.php',
    'object' => 'activitypub-story.php',
    'cron' => 'cron/process-stories.php',
    'css' => 'assets/css/stories-v66o.css',
    'js' => 'assets/js/stories-v66o.js',
    'sql' => 'database/stories_v66o.sql',
    'fresh' => 'database/north_mountain_portal_v66o.sql',
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
foreach (['pod_stories','pod_story_views','pod_story_events'] as $table) {
    $expect('sql', 'CREATE TABLE IF NOT EXISTS ' . $table, 'Missing table ' . $table);
}
$expect('service', 'federated_timeline_following_accepted', 'Remote stories must require accepted Following.');
$expect('service', "['Create', 'Update', 'Delete']", 'Inbound Create, Update, and Delete are required.');
$expect('service', 'activitypub_queue_approved_followers', 'Local stories must queue only approved followers.');
$expect('service', "'to' => [activitypub_followers_url()]", 'Stories must be follower-addressed.');
$expect('service', "'endTime'", 'ActivityStreams endTime is required.');
$expect('service', '172800', 'Remote story duration must be bounded.');
$expect('service', "'remote_media_mode' => 'link_only'", 'Remote media must remain link-only.');
$expect('service', 'stories_mark_viewed', 'Durable view receipts are required.');
$expect('service', 'stories_expire_due', 'Bounded expiry processing is required.');
$expect('object', 'X-Robots-Tag: noindex, nofollow, noarchive', 'Story objects must opt out of indexing.');
$expect('object', "'type' => 'Tombstone'", 'Expired stories must return Tombstones.');
$expect('page', 'data-story-dialog', 'Accessible story viewer is required.');
$expect('page', 'Remote media: Link only', 'The UI must disclose remote media policy.');
$expect('js', "story.load_media && story.media_kind === 'image'", 'Only explicitly loadable local images may render.');
$expect('js', 'credentials: \'same-origin\'', 'View receipts must use same-origin credentials.');
$expect('css', '@media(max-width:620px)', 'Mobile Stories layout is required.');
$expect('css', '@media(prefers-reduced-motion:reduce)', 'Reduced-motion support is required.');
$expect('fresh', 'SOURCE database/north_mountain_portal_v66l.sql;', 'Fresh install must retain v66L.');
$expect('fresh', 'SOURCE database/stories_v66o.sql;', 'Fresh install must include Stories.');
if (isset($content['page']) && preg_match('/<img[^>]+https?:\/\//i', $content['page'])) {
    $failures[] = 'The page must not embed remote image URLs.';
}
if (isset($content['page']) && preg_match('/<(audio|video|iframe)\b/i', $content['page'])) {
    $failures[] = 'The page must not auto-load remote audio, video, or frames.';
}
if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "Stories v66O source, privacy, and UX regression passed.\n";

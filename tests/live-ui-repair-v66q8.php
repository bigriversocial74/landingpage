<?php
declare(strict_types=1);

function live_ui_v66q8_fail(string $message): never
{
    fwrite(STDERR, "v66Q.8 live UI repair failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $source = @file_get_contents($root . '/' . $path);
    if (!is_string($source) || $source === '') {
        live_ui_v66q8_fail('Unable to read ' . $path);
    }
    return $source;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        live_ui_v66q8_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        live_ui_v66q8_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$feed = $read('portal/social-posts.php');
$music = $read('assets/css/music-mobile-upgrade-v66n.css');
$sidebar = $read('assets/css/portal-shell-v66q7.css');

foreach ([
    'function my_feed_runtime_error',
    'function my_feed_story_json',
    'function my_feed_render_local_post',
    'JSON_INVALID_UTF8_SUBSTITUTE',
    'stories_feed($userId, 40)',
    'social_posts_owner_posts($userId, 150)',
    'federated_timeline_query(',
    'federated_timeline_actions_for_posts(',
    'My Feed recovered from a service error.',
    'The page recovered without returning an HTTP 500 response.',
] as $contract) {
    $require($feed, $contract, 'Live My Feed recovery');
}

if (substr_count($feed, 'catch (Throwable $exception)') < 8) {
    live_ui_v66q8_fail('Live My Feed does not isolate each production data boundary.');
}
$forbid(
    $feed,
    'data-story="<?=e(json_encode($storyPayload',
    'Story rendering'
);

foreach ([
    'body.music-dashboard-body{width:100%;max-width:100%;overflow-x:hidden}',
    'overscroll-behavior-inline:contain',
    'scroll-snap-type:x mandatory',
    'grid-auto-columns:minmax(150px,68vw)',
    '"cover identity play utility"',
    '"timeline timeline timeline timeline"',
    '.music-player-center{display:contents}',
    '.music-player-center .music-player-controls button{display:none}',
    'button[data-music-toggle]',
] as $contract) {
    $require($music, $contract, 'Mobile Music Library');
}
foreach (['margin-inline:-16px', 'margin-inline:-18px'] as $needle) {
    $forbid($music, $needle, 'Mobile Music Library width');
}

foreach ([
    '.portal-nav-authenticated{display:grid;gap:3px;padding:8px 0 18px',
    'padding:8px 10px 4px',
    'font-size:.62rem',
    'line-height:1.1',
    'padding:7px 10px',
    'font-size:.84rem',
    'line-height:1.2',
    'box-shadow:inset 3px 0 0 #06c9cf',
] as $contract) {
    $require($sidebar, $contract, 'Compact shared sidebar');
}
foreach (['min-height:39px', 'padding:12px 12px 7px', 'box-shadow:0 8px 18px'] as $needle) {
    $forbid($sidebar, $needle, 'Compact shared sidebar');
}

echo "v66Q.8 live feed, mobile music, and compact sidebar regression passed.\n";

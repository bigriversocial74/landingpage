<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'analytics' => 'portal/site-analytics-view.php',
    'visitor' => 'portal/visitor-intelligence.php',
    'api' => 'api/music-play.php',
    'player' => 'assets/js/music-player.js',
    'shell' => 'portal/bootstrap-shell.php',
    'sidebar' => 'portal/sidebar.php',
    'css' => 'assets/css/portal.css',
    'admin' => 'portal/admin.php',
];
$source = [];
foreach ($files as $key => $path) {
    $source[$key] = (string)file_get_contents($root . '/' . $path);
    if ($source[$key] === '') {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
}

$checks = [
    'lifecycle start accepted' => ['music_track_started', $source['visitor']],
    'lifecycle pause accepted' => ['music_track_paused', $source['visitor']],
    'lifecycle resume accepted' => ['music_track_resumed', $source['visitor']],
    'lifecycle completion accepted' => ['music_track_completed', $source['visitor']],
    'lifecycle skip accepted' => ['music_track_skipped', $source['visitor']],
    'playback identity sent' => ['playback_id: state.playbackId', $source['player']],
    'playback event index sent' => ['event_index: state.playbackEventIndex', $source['player']],
    'new playback token' => ['const playbackToken = (track)', $source['player']],
    'repeat creates new playback' => ["state.playbackId = '';", $source['player']],
    'API passes playback identity' => ["'playback_id' => \$playbackId", $source['api']],
    'API reports actual insert' => ["'recorded' => !empty(\$recordedEventId)", $source['api']],
    'track lifecycle analytics filter' => ['event_type IN (', $source['analytics']],
    'canonical website paths' => ['site_analytics_canonical_path', $source['analytics']],
    'public path exclusion' => ['site_analytics_public_path', $source['analytics']],
    'playback session aggregation' => ['site_analytics_music_playbacks', $source['analytics']],
    'verified listening panel' => ['Verified listening', $source['analytics']],
    'recent playback sessions' => ['Recent listening activity', $source['analytics']],
    'shared first navigation style' => ['.portal-nav-group:first-child', $source['css']],
    'shared navigation group class' => ['class="portal-nav-group ', $source['sidebar']],
    'portal asset cache key' => ['20260728-content-controls-v62.1', $source['shell']],
    'CRM lifecycle detail' => ["str_starts_with(\n                        (string)\$visitorEvent['event_type'],\n                        'music_track_'", $source['admin']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

if (str_contains($source['analytics'], 'event_type LIKE "music_%"')) {
    fwrite(STDERR, "Listening analytics still include library and collection page views.\n");
    exit(1);
}
if (str_contains($source['api'], "'recorded' => true,\n    'demo'")) {
    fwrite(STDERR, "Music API still claims an unconditional successful recording.\n");
    exit(1);
}
if (str_contains($source['sidebar'], "portal-nav-group <?= \$groupActive ? 'is-current'")) {
    fwrite(STDERR, "Top/active navigation still receives a special layout class.\n");
    exit(1);
}

if (!preg_match('/class="portal-nav-group(?:\s|\")/', $source['sidebar'])) {
    fwrite(STDERR, "Shared navigation group class is not present structurally.\n");
    exit(1);
}

echo "Site Analytics and listening v61.9 regression passed.\n";

<?php
/* North Mountain Media build: 20260728-site-analytics-listening-v61.9 */
declare(strict_types=1);

require_once __DIR__ . '/visitor-intelligence.php';

function site_analytics_canonical_path(mixed $value): string
{
    $raw = trim((string)$value);
    if ($raw === '') return '/';

    $path = (string)(parse_url($raw, PHP_URL_PATH) ?: strtok($raw, '?#') ?: '/');
    $path = '/' . ltrim($path, '/');
    $path = preg_replace('#/+#', '/', $path) ?: '/';
    if ($path === '/index.php' || $path === '/index.html') return '/';
    if ($path !== '/') $path = rtrim($path, '/');

    return $path !== '' ? substr($path, 0, 500) : '/';
}

function site_analytics_public_path(string $path): bool
{
    foreach (['/portal', '/api', '/assets', '/storage'] as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) return false;
    }

    return !in_array($path, [
        '/builder-media.php',
        '/music-stream.php',
        '/page-preview.php',
        '/download.php',
    ], true);
}

function site_analytics_page_label(string $path): string
{
    if ($path === '/') return 'Home';
    $name = basename($path);
    $name = preg_replace('/\.(?:php|html?)$/i', '', $name) ?: $name;
    $name = str_replace(['-', '_'], ' ', $name);
    return ucwords($name !== '' ? $name : trim($path, '/'));
}

function site_analytics_website_activity(int $days): array
{
    $output = [
        'views' => 0,
        'visitors' => [],
        'sessions' => [],
        'pages' => [],
    ];
    if (!visitor_intelligence_schema_available()) return $output;

    $threshold = (new DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d H:i:s');
    $statement = db()->prepare(
        'SELECT page_path,visitor_id,session_id
         FROM visitor_events
         WHERE occurred_at>=:threshold
           AND event_type="page_view"
         ORDER BY occurred_at DESC
         LIMIT 50000'
    );
    $statement->execute(['threshold' => $threshold]);

    foreach ($statement->fetchAll() as $row) {
        $path = site_analytics_canonical_path($row['page_path'] ?? '/');
        if (!site_analytics_public_path($path)) continue;
        $visitorId = (int)($row['visitor_id'] ?? 0);
        $sessionId = (int)($row['session_id'] ?? 0);
        $output['views']++;
        if ($visitorId > 0) $output['visitors'][$visitorId] = true;
        if ($sessionId > 0) $output['sessions'][$sessionId] = true;
        $output['pages'][$path] ??= [
            'path' => $path,
            'label' => site_analytics_page_label($path),
            'views' => 0,
            'visitors' => [],
        ];
        $output['pages'][$path]['views']++;
        if ($visitorId > 0) $output['pages'][$path]['visitors'][$visitorId] = true;
    }

    foreach ($output['pages'] as &$page) {
        $page['visitor_count'] = count($page['visitors']);
        unset($page['visitors']);
    }
    unset($page);
    $output['pages'] = array_values($output['pages']);
    usort($output['pages'], static fn(array $a, array $b): int =>
        $b['views'] <=> $a['views'] ?: strcmp($a['label'], $b['label'])
    );

    return $output;
}

function site_analytics_music_events(int $days): array
{
    if (!visitor_intelligence_schema_available()) return [];
    $threshold = (new DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d H:i:s');
    $statement = db()->prepare(
        'SELECT id,event_type,event_label,metadata_json,duration_seconds,
                occurred_at,visitor_id,session_id,page_path
         FROM visitor_events
         WHERE occurred_at>=:threshold
           AND event_type IN (
                "music_track_play",
                "music_track_started",
                "music_track_paused",
                "music_track_resumed",
                "music_track_completed",
                "music_track_skipped"
           )
         ORDER BY occurred_at ASC,id ASC
         LIMIT 20000'
    );
    $statement->execute(['threshold' => $threshold]);
    return $statement->fetchAll();
}

function site_analytics_music_playbacks(array $events): array
{
    $playbacks = [];
    $activeLegacy = [];

    foreach ($events as $event) {
        $type = (string)($event['event_type'] ?? '');
        $metadata = visitor_intelligence_metadata_decode($event['metadata_json'] ?? null);
        $trackId = (string)($metadata['track_id'] ?? $event['event_label'] ?? 'unknown');
        $legacyKey = (int)($event['session_id'] ?? 0) . '|' . $trackId;
        $playbackId = trim((string)($metadata['playback_id'] ?? ''));

        if ($playbackId === '') {
            if (in_array($type, ['music_track_started', 'music_track_play'], true)) {
                $playbackId = 'legacy-' . (int)$event['id'];
                $activeLegacy[$legacyKey] = $playbackId;
            } else {
                $playbackId = $activeLegacy[$legacyKey] ?? ('legacy-' . (int)$event['id']);
            }
        }

        $playbacks[$playbackId] ??= [
            'id' => $playbackId,
            'track_id' => $trackId,
            'title' => (string)($event['event_label'] ?: $metadata['track_title'] ?? 'Unknown track'),
            'album' => trim((string)($metadata['album_title'] ?? '')) ?: 'Singles and uncategorized',
            'artist' => trim((string)($metadata['artist'] ?? '')),
            'visitor_id' => (int)($event['visitor_id'] ?? 0),
            'session_id' => (int)($event['session_id'] ?? 0),
            'page_path' => site_analytics_canonical_path($event['page_path'] ?? '/music-library.php'),
            'started_at' => (string)$event['occurred_at'],
            'last_at' => (string)$event['occurred_at'],
            'status' => 'started',
            'position' => 0,
            'duration' => 0,
            'pauses' => 0,
            'resumes' => 0,
        ];

        $playback = &$playbacks[$playbackId];
        $playback['last_at'] = (string)$event['occurred_at'];
        $playback['title'] = (string)($event['event_label'] ?: $playback['title']);
        $playback['album'] = trim((string)($metadata['album_title'] ?? $playback['album'])) ?: $playback['album'];
        $playback['artist'] = trim((string)($metadata['artist'] ?? $playback['artist']));
        $position = max((int)($event['duration_seconds'] ?? 0), (int)($metadata['position_seconds'] ?? 0));
        $duration = (int)($metadata['duration_seconds'] ?? 0);
        $playback['position'] = max($playback['position'], $position);
        $playback['duration'] = max($playback['duration'], $duration);

        if ($type === 'music_track_paused') {
            $playback['pauses']++;
            if (!in_array($playback['status'], ['completed', 'skipped'], true)) $playback['status'] = 'paused';
        } elseif ($type === 'music_track_resumed') {
            $playback['resumes']++;
            if (!in_array($playback['status'], ['completed', 'skipped'], true)) $playback['status'] = 'listening';
        } elseif ($type === 'music_track_completed') {
            $playback['status'] = 'completed';
            unset($activeLegacy[$legacyKey]);
        } elseif ($type === 'music_track_skipped') {
            $playback['status'] = 'skipped';
            unset($activeLegacy[$legacyKey]);
        } elseif (in_array($type, ['music_track_started', 'music_track_play'], true)) {
            $playback['status'] = 'listening';
        }
        unset($playback);
    }

    foreach ($playbacks as &$playback) {
        $estimated = $playback['status'] === 'completed' && $playback['duration'] > 0
            ? $playback['duration']
            : $playback['position'];
        if ($playback['duration'] > 0) $estimated = min($estimated, $playback['duration']);
        $playback['seconds'] = max(0, $estimated);
    }
    unset($playback);

    $playbacks = array_values($playbacks);
    usort($playbacks, static fn(array $a, array $b): int =>
        strcmp($b['last_at'], $a['last_at']) ?: strcmp($b['id'], $a['id'])
    );
    return $playbacks;
}

function site_analytics_music_summary(array $playbacks): array
{
    $totals = [
        'starts' => count($playbacks),
        'completions' => 0,
        'skips' => 0,
        'pauses' => 0,
        'resumes' => 0,
        'seconds' => 0,
        'listeners' => [],
    ];
    $tracks = [];
    $albums = [];

    foreach ($playbacks as $playback) {
        $completed = $playback['status'] === 'completed';
        $skipped = $playback['status'] === 'skipped';
        $totals['completions'] += $completed ? 1 : 0;
        $totals['skips'] += $skipped ? 1 : 0;
        $totals['pauses'] += (int)$playback['pauses'];
        $totals['resumes'] += (int)$playback['resumes'];
        $totals['seconds'] += (int)$playback['seconds'];
        if ((int)$playback['visitor_id'] > 0) $totals['listeners'][(int)$playback['visitor_id']] = true;

        $trackKey = (string)$playback['track_id'];
        $tracks[$trackKey] ??= [
            'title' => $playback['title'],
            'artist' => $playback['artist'],
            'album' => $playback['album'],
            'starts' => 0,
            'completions' => 0,
            'skips' => 0,
            'seconds' => 0,
        ];
        $tracks[$trackKey]['starts']++;
        $tracks[$trackKey]['completions'] += $completed ? 1 : 0;
        $tracks[$trackKey]['skips'] += $skipped ? 1 : 0;
        $tracks[$trackKey]['seconds'] += (int)$playback['seconds'];

        $album = (string)$playback['album'];
        $albums[$album] ??= ['title' => $album, 'starts' => 0, 'completions' => 0, 'seconds' => 0];
        $albums[$album]['starts']++;
        $albums[$album]['completions'] += $completed ? 1 : 0;
        $albums[$album]['seconds'] += (int)$playback['seconds'];
    }

    $tracks = array_values($tracks);
    usort($tracks, static fn(array $a, array $b): int =>
        $b['starts'] <=> $a['starts'] ?: $b['seconds'] <=> $a['seconds']
    );
    $albums = array_values($albums);
    usort($albums, static fn(array $a, array $b): int =>
        $b['starts'] <=> $a['starts'] ?: $b['seconds'] <=> $a['seconds']
    );

    return ['totals' => $totals, 'tracks' => $tracks, 'albums' => $albums];
}

function site_analytics_render_admin(): void
{
    $days = max(7, min(365, query_int('days', 30)));
    $threshold = (new DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d H:i:s');
    $website = site_analytics_website_activity($days);
    $events = site_analytics_music_events($days);
    $playbacks = site_analytics_music_playbacks($events);
    $music = site_analytics_music_summary($playbacks);
    $musicTotals = $music['totals'];
    $completionRate = $musicTotals['starts'] > 0
        ? round(($musicTotals['completions'] / $musicTotals['starts']) * 100, 1)
        : 0;
    $conversions = 0;
    $trackedActions = 0;

    if (visitor_intelligence_schema_available()) {
        $statement = db()->prepare(
            'SELECT COUNT(*) events,
                    SUM(event_type LIKE "%submitted"
                        OR event_type LIKE "%accepted"
                        OR event_type LIKE "%conversion%") conversions
             FROM visitor_events
             WHERE occurred_at>=:threshold'
        );
        $statement->execute(['threshold' => $threshold]);
        $row = $statement->fetch() ?: [];
        $trackedActions = (int)($row['events'] ?? 0);
        $conversions = (int)($row['conversions'] ?? 0);
    }

    $topPages = array_slice($website['pages'], 0, 10);
    $maxPageViews = max(1, ...array_map(static fn(array $page): int => (int)$page['views'], $topPages ?: [['views' => 1]]));
    ?>
<div class="site-analytics-toolbar">
    <div><span>Site and listening intelligence</span><h2>Analytics dashboard</h2><p>Clean website traffic, conversion activity, and verified Music Library playback sessions.</p></div>
    <form method="get"><input type="hidden" name="view" value="site-analytics"><select name="days" onchange="this.form.submit()"><?php foreach ([7,30,60,90,180,365] as $option): ?><option value="<?=$option?>" <?=$days===$option?'selected':''?>>Last <?=$option?> days</option><?php endforeach; ?></select></form>
</div>
<div class="analytics-stat-grid">
    <article><span>Unique visitors</span><strong><?=count($website['visitors'])?></strong><small><?=count($website['sessions'])?> website sessions</small></article>
    <article><span>Page views</span><strong><?=$website['views']?></strong><small><?=$trackedActions?> total tracked actions</small></article>
    <article><span>Conversions</span><strong><?=$conversions?></strong><small>Forms, bookings, intake, proposals</small></article>
    <article><span>Listening starts</span><strong><?=$musicTotals['starts']?></strong><small><?=count($musicTotals['listeners'])?> verified listeners</small></article>
    <article><span>Completion rate</span><strong><?=$completionRate?>%</strong><small><?=$musicTotals['completions']?> completed playbacks</small></article>
</div>
<div class="site-analytics-layout-v619">
    <section class="panel analytics-panel-v619">
        <header class="panel-header"><div><span>Website activity</span><h2>Top public pages</h2><p>Query strings, duplicate Home URLs, portal pages, APIs, and static assets are consolidated or excluded.</p></div></header>
        <div class="analytics-page-list">
            <?php foreach ($topPages as $page): ?>
            <article class="analytics-page-row">
                <div class="analytics-page-copy"><strong><?=e($page['label'])?></strong><small><?=e($page['path'])?></small><div class="analytics-bar"><i style="width:<?=round(((int)$page['views']/$maxPageViews)*100,1)?>%"></i></div></div>
                <div class="analytics-page-values"><span><strong><?=(int)$page['views']?></strong><small>Views</small></span><span><strong><?=(int)$page['visitor_count']?></strong><small>Visitors</small></span></div>
            </article>
            <?php endforeach; ?>
            <?php if (!$topPages): ?><p class="analytics-empty-v619">No public website activity was recorded during this period.</p><?php endif; ?>
        </div>
    </section>
    <section class="panel analytics-panel-v619">
        <header class="panel-header"><div><span>Music Library</span><h2>Verified listening</h2><p>Each row is built from a distinct playback lifecycle rather than page-view or raw event noise.</p></div></header>
        <div class="analytics-listening-summary-v619">
            <article><span>Pauses</span><strong><?=$musicTotals['pauses']?></strong></article>
            <article><span>Resumes</span><strong><?=$musicTotals['resumes']?></strong></article>
            <article><span>Skips</span><strong><?=$musicTotals['skips']?></strong></article>
            <article><span>Completed</span><strong><?=$musicTotals['completions']?></strong></article>
            <article><span>Listening</span><strong><?=e(call_center_seconds_label($musicTotals['seconds']))?></strong></article>
        </div>
        <div class="analytics-track-header"><span>Track</span><span>Starts</span><span>Completed</span><span>Rate</span><span>Listening</span></div>
        <div class="analytics-track-list">
            <?php foreach (array_slice($music['tracks'], 0, 12) as $track): $rate=$track['starts']>0?round(($track['completions']/$track['starts'])*100):0; ?>
            <article class="analytics-track-row">
                <div class="analytics-track-copy"><strong><?=e($track['title'])?></strong><small><?=e(implode(' · ', array_filter([$track['artist'], $track['album']])))?></small><div class="analytics-bar"><i style="width:<?=$rate?>%"></i></div></div>
                <span class="analytics-track-metric"><strong><?=$track['starts']?></strong><small>Starts</small></span>
                <span class="analytics-track-metric"><strong><?=$track['completions']?></strong><small>Completed</small></span>
                <span class="analytics-track-metric"><strong><?=$rate?>%</strong><small>Rate</small></span>
                <span class="analytics-track-metric"><strong><?=e(call_center_seconds_label((int)$track['seconds']))?></strong><small>Listening</small></span>
            </article>
            <?php endforeach; ?>
            <?php if (!$music['tracks']): ?><p class="analytics-empty-v619">Listening activity will appear after a track is played.</p><?php endif; ?>
        </div>
        <?php if ($music['albums']): ?><div class="analytics-album-list-v619"><h3>Album activity</h3><?php foreach (array_slice($music['albums'],0,5) as $album): ?><article><strong><?=e($album['title'])?></strong><span><?=$album['starts']?> starts · <?=$album['completions']?> completed · <?=e(call_center_seconds_label((int)$album['seconds']))?></span></article><?php endforeach; ?></div><?php endif; ?>
    </section>
</div>
<section class="panel analytics-playback-panel-v619">
    <header class="panel-header"><div><span>Playback sessions</span><h2>Recent listening activity</h2><p>Library and collection page views are no longer repeated in this stream.</p></div></header>
    <div class="analytics-playback-list">
        <?php foreach (array_slice($playbacks, 0, 15) as $playback): ?>
        <article class="analytics-playback-row">
            <span class="status"><?=e(status_label($playback['status']))?></span>
            <div class="analytics-playback-copy"><strong><?=e($playback['title'])?></strong><small><?=e(implode(' · ', array_filter([$playback['artist'], $playback['album'], $playback['page_path']])))?></small></div>
            <time><?=e(call_center_seconds_label((int)$playback['seconds']))?> · <?=e(format_datetime($playback['last_at']))?></time>
        </article>
        <?php endforeach; ?>
        <?php if (!$playbacks): ?><p class="analytics-empty-v619">No verified listening sessions were recorded during this period.</p><?php endif; ?>
    </div>
</section>
<?php
}

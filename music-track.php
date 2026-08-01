<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('music_library');
require_once __DIR__ . '/portal/music-library.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$catalog = music_public_catalog();
$tracks = array_values(is_array($catalog['tracks'] ?? null) ? $catalog['tracks'] : []);
$requestedId = max(0, (int)($_GET['id'] ?? 0));
$requestedSlug = slugify((string)($_GET['slug'] ?? ''));
$track = null;

foreach ($tracks as $candidate) {
    if (
        ($requestedId > 0 && (int)($candidate['id'] ?? 0) === $requestedId)
        || ($requestedSlug !== '' && (string)($candidate['slug'] ?? '') === $requestedSlug)
    ) {
        $track = $candidate;
        break;
    }
}

if (!$track) {
    http_response_code(404);
}

$shell = music_public_shell_context();
$title = (string)($track['title'] ?? 'Song unavailable');
$artist = (string)($track['artist'] ?? 'North Mountain Media');
$album = trim((string)($track['album'] ?? ''));
$albumSlug = trim((string)($track['album_slug'] ?? ''));
$genre = trim((string)($track['genre'] ?? ''));
$year = (int)($track['release_year'] ?? 0);
$description = trim((string)($track['description'] ?? ''));
$coverUrl = (string)($track['cover_url'] ?? app_url('assets/images/music-cover-placeholder.svg'));
$durationLabel = (string)($track['duration_label'] ?? '—');
$playCount = (int)($track['play_count'] ?? 0);
$demoMode = !empty($track['demo']);

$related = [];
if ($track) {
    foreach ($tracks as $candidate) {
        if ((int)($candidate['id'] ?? 0) === (int)$track['id']) continue;
        $sameAlbum = $album !== '' && strcasecmp((string)($candidate['album'] ?? ''), $album) === 0;
        $sameArtist = strcasecmp((string)($candidate['artist'] ?? ''), $artist) === 0;
        if ($sameAlbum || $sameArtist) $related[] = $candidate;
    }

    if (count($related) < 5) {
        foreach ($tracks as $candidate) {
            if ((int)($candidate['id'] ?? 0) === (int)$track['id']) continue;
            $alreadyAdded = array_filter(
                $related,
                static fn(array $item): bool => (int)$item['id'] === (int)$candidate['id']
            );
            if (!$alreadyAdded) $related[] = $candidate;
            if (count($related) >= 5) break;
        }
    }
}
$related = array_slice($related, 0, 5);

function music_track_page_attributes(array $track): string
{
    return implode(' ', [
        'data-music-play',
        'data-track-id="' . (int)$track['id'] . '"',
        'data-track-title="' . e((string)$track['title']) . '"',
        'data-track-artist="' . e((string)$track['artist']) . '"',
        'data-track-album="' . e((string)($track['album'] ?? '')) . '"',
        'data-track-stream="' . e((string)$track['stream_url']) . '"',
        'data-track-cover="' . e((string)$track['cover_url']) . '"',
        'data-track-duration="' . (int)($track['duration_seconds'] ?? 0) . '"',
        'data-track-demo="' . (!empty($track['demo']) ? '1' : '0') . '"',
    ]);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; media-src 'self'; "
    . "connect-src 'self'; base-uri 'self'; "
    . "object-src 'none'; frame-ancestors 'self'"
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=e($title)?> by <?=e($artist)?> on North Mountain Media Music.">
<meta name="build-version" content="20260801-music-track-v66Q12">
<title><?=e($title)?> — North Mountain Media Music</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-library.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-dashboard.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-mobile-upgrade-v66n.css?v=20260801-professional-player-v66Q9'))?>">
</head>
<body class="music-dashboard-body music-track-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell); ?>

<section class="music-public-workspace">
<?php music_render_public_header($shell); ?>

<main class="music-track-page">
<?php if (!$track): ?>
    <section class="music-track-missing">
        <span>Music Library</span>
        <h1>Song unavailable</h1>
        <p>This song is not published or could not be found.</p>
        <a href="<?=e(app_url('music-library.php'))?>">Return to Music Library</a>
    </section>
<?php else: ?>
    <nav class="music-track-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?=e(app_url('music-library.php'))?>">Music Library</a>
        <?php if ($album !== '' && $albumSlug !== ''): ?>
            <span>›</span>
            <a href="<?=e(music_collection_public_url('album', $albumSlug))?>"><?=e($album)?></a>
        <?php endif; ?>
        <span>›</span>
        <strong><?=e($title)?></strong>
    </nav>

    <section
        class="music-track-hero"
        style="--music-track-cover:url('<?=e($coverUrl)?>')"
        data-music-collection
    >
        <figure class="music-track-art">
            <img src="<?=e($coverUrl)?>" alt="<?=e($title)?> cover">
        </figure>

        <div class="music-track-hero-copy">
            <span class="music-track-type">Song</span>
            <h1><?=e($title)?></h1>
            <p class="music-track-artist"><?=e($artist)?></p>

            <div class="music-track-meta">
                <?php if ($album !== ''): ?><span><?=e($album)?></span><?php endif; ?>
                <?php if ($genre !== ''): ?><span><?=e($genre)?></span><?php endif; ?>
                <?php if ($year > 0): ?><span><?=$year?></span><?php endif; ?>
                <span><?=e($durationLabel)?></span>
                <?php if ($playCount > 0): ?><span><?=number_format($playCount)?> plays</span><?php endif; ?>
            </div>

            <?php if ($description !== ''): ?>
                <p class="music-track-description"><?=e($description)?></p>
            <?php endif; ?>

            <div class="music-track-actions">
                <button
                    class="music-track-primary"
                    type="button"
                    <?=music_track_page_attributes($track)?>
                ><span aria-hidden="true">▶</span>Play Song</button>

                <?php if ($related): ?>
                    <button type="button" data-music-shuffle><span aria-hidden="true">🔀</span>Shuffle</button>
                <?php endif; ?>

                <?php if (!empty($track['downloadable']) && !empty($track['download_url'])): ?>
                    <a href="<?=e((string)$track['download_url'])?>">Download</a>
                <?php endif; ?>
            </div>

            <div hidden>
                <?php foreach ($related as $relatedTrack): ?>
                    <button type="button" <?=music_track_page_attributes($relatedTrack)?>>Play</button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($related): ?>
        <section class="music-track-related" data-music-collection>
            <header>
                <h2>More to listen to</h2>
                <a href="<?=e(app_url('music-library.php#all-songs'))?>">View all songs</a>
            </header>
            <div>
                <?php foreach ($related as $relatedTrack): ?>
                    <article class="music-track-related-row">
                        <img src="<?=e((string)$relatedTrack['cover_url'])?>" alt="" loading="lazy">
                        <div>
                            <strong><?=e((string)$relatedTrack['title'])?></strong>
                            <small><?=e((string)$relatedTrack['artist'])?></small>
                        </div>
                        <span><?=e((string)($relatedTrack['album'] ?: 'Single'))?></span>
                        <time><?=e((string)$relatedTrack['duration_label'])?></time>
                        <button
                            type="button"
                            <?=music_track_page_attributes($relatedTrack)?>
                            aria-label="Play <?=e((string)$relatedTrack['title'])?>"
                        >▶</button>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
</main>
</section>
</div>

<?php if ($track): ?>
<button type="button" hidden data-music-initial-track <?=music_track_page_attributes($track)?>>Initial track</button>
<?php endif; ?>

<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260801-v66Q12'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/visitor-activity.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/music-player.js?v=20260801-professional-player-v66Q12'))?>"></script>
<script src="<?=e(app_url('assets/js/music-dashboard.js?v=20260801-v66Q10'))?>"></script>
<script>
window.NMMVisitorActivity?.track('music_track_page_view', {
  event_label: <?=json_encode($title, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>,
  metadata: {
    track_id: <?=(int)($track['id'] ?? 0)?>,
    demo_mode: <?=$demoMode ? 'true' : 'false'?>
  },
  deduplicate: false
});
</script>
</body>
</html>

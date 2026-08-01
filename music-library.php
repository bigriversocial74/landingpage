<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('music_library');
require_once __DIR__ . '/portal/music-library.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$catalog = music_public_catalog();
$tracks = array_values($catalog['tracks']);
$albums = array_values($catalog['albums']);
$playlists = array_values($catalog['playlists']);
$demoMode = !empty($catalog['demo']);
$musicBanner = music_public_banner();
$shell = music_public_shell_context();

$newSongs = $tracks;
usort(
    $newSongs,
    static function (array $left, array $right): int {
        $leftDate = strtotime((string)($left['published_at'] ?: $left['created_at'])) ?: 0;
        $rightDate = strtotime((string)($right['published_at'] ?: $right['created_at'])) ?: 0;

        return $rightDate <=> $leftDate
            ?: ((int)$right['id'] <=> (int)$left['id']);
    }
);
$newSongs = array_slice($newSongs, 0, 5);

$topSongs = $tracks;
usort(
    $topSongs,
    static fn(array $left, array $right): int =>
        ((int)$right['play_count'] <=> (int)$left['play_count'])
        ?: strcasecmp((string)$left['title'], (string)$right['title'])
);
$topSongs = array_slice($topSongs, 0, 5);

$continueTracks = array_slice($topSongs ?: $newSongs ?: $tracks, 0, 3);
$recentTracks = array_slice($newSongs ?: $topSongs ?: $tracks, 0, 6);
$displayPlaylists = array_slice($playlists, 0, 6);
$displayAlbums = array_slice($albums, 0, 6);

$allSongs = $tracks;
usort(
    $allSongs,
    static fn(array $left, array $right): int =>
        strcasecmp((string)$left['title'], (string)$right['title'])
);

$initialTrack = $topSongs[0] ?? $newSongs[0] ?? $tracks[0] ?? null;
$profileName = trim((string)($shell['profile_name'] ?? 'David Evans')) ?: 'David Evans';
$heroImage = trim((string)($musicBanner['image_url'] ?? ''));
if ($heroImage === '') {
    $heroImage = (string)($shell['profile_image'] ?? app_url('assets/images/david-evans-profile.jpg'));
}
$heroSubtitle = trim((string)($musicBanner['subtitle'] ?? '')) ?: 'Listen. Create. Inspire.';

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

function music_dashboard_track_attributes(array $track): string
{
    return implode(' ', [
        'data-music-play',
        'data-track-id="' . (int)$track['id'] . '"',
        'data-track-title="' . e($track['title']) . '"',
        'data-track-artist="' . e($track['artist']) . '"',
        'data-track-album="' . e($track['album']) . '"',
        'data-track-stream="' . e($track['stream_url']) . '"',
        'data-track-cover="' . e($track['cover_url']) . '"',
        'data-track-duration="' . (int)($track['duration_seconds'] ?? 0) . '"',
        'data-track-demo="' . (!empty($track['demo']) ? '1' : '0') . '"',
    ]);
}

function music_collection_track_count(array $collection): int
{
    return count(is_array($collection['tracks'] ?? null) ? $collection['tracks'] : []);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="North Mountain Media streaming music library.">
<meta name="build-version" content="20260801-music-library-v66Q18">
<title>Music Library — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-library.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-dashboard.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-mobile-upgrade-v66n.css?v=20260801-professional-player-v66Q9'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-library-dashboard-v66q10.css?v=20260801-v66Q10'))?>">
<style>
.music-library-summary-grid{--music-summary-cover-size:52px}
.music-library-continue-row{grid-template-columns:var(--music-summary-cover-size) minmax(0,1fr)!important;min-height:64px!important}
.music-library-compact-track{grid-template-columns:18px var(--music-summary-cover-size) minmax(0,1fr) 38px 24px!important;min-height:64px!important}
.music-library-new-row{grid-template-columns:var(--music-summary-cover-size) minmax(0,1fr)!important;min-height:64px!important}
.music-library-summary-grid .music-library-continue-row>img,
.music-library-summary-grid .music-library-compact-track>img,
.music-library-summary-grid .music-library-new-row>img{
  display:block!important;width:var(--music-summary-cover-size)!important;
  min-width:var(--music-summary-cover-size)!important;max-width:var(--music-summary-cover-size)!important;
  height:var(--music-summary-cover-size)!important;min-height:var(--music-summary-cover-size)!important;
  max-height:var(--music-summary-cover-size)!important;aspect-ratio:1/1!important;
  border-radius:9px!important;object-fit:cover!important;transform:none!important
}
.music-library-summary-grid button[data-music-summary-cover-hit]{
  position:absolute!important;z-index:4!important;margin:0!important;padding:0!important;
  border:0!important;background:transparent!important;box-shadow:none!important;
  color:transparent!important;font-size:0!important;line-height:0!important;
  opacity:0!important;overflow:hidden!important;appearance:none!important;-webkit-appearance:none!important
}
.music-library-summary-grid button[data-music-summary-cover-hit]::before,
.music-library-summary-grid button[data-music-summary-cover-hit]::after{content:none!important;display:none!important}
</style>
</head>
<body class="music-dashboard-body music-library-redesign-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell); ?>

<section class="music-public-workspace">
<?php music_render_public_header($shell); ?>

<main class="music-library-dashboard" data-music-library-dashboard>
    <section class="music-library-toolbar" aria-label="Music Library search">
        <label class="music-library-search">
            <span aria-hidden="true">⌕</span>
            <input
                type="search"
                placeholder="Search songs, albums, artists..."
                autocomplete="off"
                data-music-library-search
            >
        </label>
        <p data-music-library-results aria-live="polite"></p>
    </section>

    <section
        class="music-library-hero"
        style="--music-library-hero-image:url('<?=e($heroImage)?>')"
        data-music-collection
    >
        <div class="music-library-hero-copy">
            <span>Welcome back,</span>
            <h1><?=e($profileName)?></h1>
            <p><?=e($heroSubtitle)?></p>
            <?php if ($tracks): ?>
                <button type="button" data-music-play-all>
                    <i aria-hidden="true">▶</i>
                    Play Your Library
                </button>
            <?php endif; ?>
        </div>
        <?php if ($tracks): ?>
            <div hidden>
                <?php foreach ($tracks as $track): ?>
                    <button type="button" <?=music_dashboard_track_attributes($track)?>>Play</button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($tracks): ?>
        <section class="music-library-summary-grid">
            <article class="music-library-summary-card music-library-continue" data-music-collection>
                <header>
                    <h2>Continue Listening</h2>
                    <a href="#all-songs">See All</a>
                </header>
                <div>
                    <?php foreach ($continueTracks as $index => $track): ?>
                        <article class="music-library-continue-row">
                            <img src="<?=e($track['cover_url'])?>" alt="" loading="lazy">
                            <div>
                                <strong><?=e($track['title'])?></strong>
                                <span><?=e($track['artist'])?></span>
                                <i style="--track-progress:<?=28 + ($index * 19)?>%"></i>
                            </div>
                            <button
                                type="button"
                                data-music-summary-cover-hit
                                style="position:absolute;width:0;height:0;opacity:0;border:0;padding:0;margin:0;overflow:hidden;background:transparent;color:transparent;font-size:0;line-height:0"
                                <?=music_dashboard_track_attributes($track)?>
                                aria-label="Play <?=e($track['title'])?>"
                            ></button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="music-library-summary-card music-library-top" data-music-collection>
                <header>
                    <h2>Top Songs</h2>
                    <a href="#all-songs">See All</a>
                </header>
                <div>
                    <?php foreach ($topSongs as $index => $track): ?>
                        <article class="music-library-compact-track">
                            <span><?=$index + 1?></span>
                            <img src="<?=e($track['cover_url'])?>" alt="" loading="lazy">
                            <button
                                type="button"
                                data-music-summary-cover-hit
                                style="position:absolute;width:0;height:0;opacity:0;border:0;padding:0;margin:0;overflow:hidden;background:transparent;color:transparent;font-size:0;line-height:0"
                                <?=music_dashboard_track_attributes($track)?>
                                aria-label="Play <?=e($track['title'])?>"
                            ></button>
                            <div>
                                <strong><?=e($track['title'])?></strong>
                                <small><?=e($track['artist'])?></small>
                            </div>
                            <time><?=e($track['duration_label'])?></time>
                            <button type="button" aria-label="More options">⋮</button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="music-library-summary-card music-library-new" data-music-collection>
                <header>
                    <h2>New Music</h2>
                    <a href="#all-songs">See All</a>
                </header>
                <div>
                    <?php foreach ($newSongs as $track): ?>
                        <article class="music-library-new-row">
                            <img src="<?=e($track['cover_url'])?>" alt="" loading="lazy">
                            <button
                                type="button"
                                data-music-summary-cover-hit
                                style="position:absolute;width:0;height:0;opacity:0;border:0;padding:0;margin:0;overflow:hidden;background:transparent;color:transparent;font-size:0;line-height:0"
                                <?=music_dashboard_track_attributes($track)?>
                                aria-label="Play <?=e($track['title'])?>"
                            ></button>
                            <div>
                                <strong><?=e($track['title'])?></strong>
                                <span><?=e($track['artist'])?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="music-library-rail-section" aria-labelledby="recentlyPlayedTitle">
            <header>
                <h2 id="recentlyPlayedTitle">Recently Played</h2>
                <a href="#all-songs">See All</a>
            </header>
            <div class="music-library-card-rail" data-recently-played>
                <?php foreach ($recentTracks as $track): ?>
                    <article class="music-dashboard-recent-item music-library-cover-card">
                        <div>
                            <img src="<?=e($track['cover_url'])?>" alt="<?=e($track['title'])?> cover" loading="lazy">
                            <button
                                type="button"
                                <?=music_dashboard_track_attributes($track)?>
                                aria-label="Play <?=e($track['title'])?>"
                            >▶</button>
                        </div>
                        <strong><?=e($track['title'])?></strong>
                        <span><?=e($track['artist'])?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($displayPlaylists): ?>
        <section class="music-library-rail-section" id="playlists" aria-labelledby="playlistsTitle">
            <header>
                <h2 id="playlistsTitle">Playlists</h2>
                <a href="#all-songs">See All</a>
            </header>
            <div class="music-library-card-rail">
                <?php foreach ($displayPlaylists as $playlist): ?>
                    <article class="music-library-cover-card" data-music-collection>
                        <div>
                            <a href="<?=e(music_collection_public_url('playlist', (string)$playlist['slug']))?>">
                                <img src="<?=e($playlist['cover_url'])?>" alt="<?=e($playlist['title'])?> cover" loading="lazy">
                            </a>
                            <?php if (!empty($playlist['tracks'])): ?>
                                <button type="button" data-music-play-all aria-label="Play <?=e($playlist['title'])?>">▶</button>
                            <?php endif; ?>
                        </div>
                        <strong><?=e($playlist['title'])?></strong>
                        <span><?=music_collection_track_count($playlist)?> songs</span>
                        <div hidden>
                            <?php foreach (($playlist['tracks'] ?? []) as $track): ?>
                                <button type="button" <?=music_dashboard_track_attributes($track)?>>Play</button>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($displayAlbums): ?>
        <section class="music-library-rail-section" id="albums" aria-labelledby="albumsTitle">
            <header>
                <h2 id="albumsTitle">Albums</h2>
                <a href="#all-songs">See All</a>
            </header>
            <div class="music-library-card-rail">
                <?php foreach ($displayAlbums as $album): ?>
                    <article class="music-library-cover-card" data-music-collection>
                        <div>
                            <a href="<?=e(music_collection_public_url('album', (string)$album['slug']))?>">
                                <img src="<?=e($album['cover_url'])?>" alt="<?=e($album['title'])?> cover" loading="lazy">
                            </a>
                            <?php if (!empty($album['tracks'])): ?>
                                <button type="button" data-music-play-all aria-label="Play <?=e($album['title'])?>">▶</button>
                            <?php endif; ?>
                        </div>
                        <strong><?=e($album['title'])?></strong>
                        <span><?=e((string)($album['artist'] ?? ''))?></span>
                        <div hidden>
                            <?php foreach (($album['tracks'] ?? []) as $track): ?>
                                <button type="button" <?=music_dashboard_track_attributes($track)?>>Play</button>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="music-library-all-songs" id="all-songs" data-music-collection>
        <header>
            <h2>All Songs</h2>
            <?php if ($tracks): ?>
                <button type="button" data-music-play-all>Play All</button>
            <?php endif; ?>
        </header>

        <?php if ($allSongs): ?>
            <div class="music-library-song-table" role="table" aria-label="All songs">
                <div class="music-library-song-head" role="row">
                    <span>#</span>
                    <span>Title</span>
                    <span>Artist</span>
                    <span>Album</span>
                    <span aria-label="Favorite">♡</span>
                    <span>Time</span>
                    <span></span>
                </div>
                <?php foreach ($allSongs as $index => $track): ?>
                    <article
                        class="music-library-song-row"
                        role="row"
                        data-music-song-row
                        data-music-extra-song="<?=$index >= 10 ? '1' : '0'?>"
                        data-music-search="<?=e(mb_strtolower(trim(
                            (string)$track['title'] . ' '
                            . (string)$track['artist'] . ' '
                            . (string)$track['album']
                        )))?>"
                        <?=$index >= 10 ? 'hidden' : ''?>
                    >
                        <span><?=$index + 1?></span>
                        <div class="music-library-song-title">
                            <button
                                type="button"
                                <?=music_dashboard_track_attributes($track)?>
                                aria-label="Play <?=e($track['title'])?>"
                            >▶</button>
                            <img src="<?=e($track['cover_url'])?>" alt="" loading="lazy">
                            <strong><?=e($track['title'])?></strong>
                        </div>
                        <span><?=e($track['artist'])?></span>
                        <span><?=e($track['album'] ?: 'Single')?></span>
                        <button class="music-dashboard-heart" type="button" aria-label="Save <?=e($track['title'])?>">♡</button>
                        <time><?=e($track['duration_label'])?></time>
                        <button type="button" aria-label="More options">⋮</button>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (count($allSongs) > 10): ?>
                <button class="music-library-load-more" type="button" data-music-library-load-more>
                    Load More
                </button>
            <?php endif; ?>
        <?php else: ?>
            <div class="music-library-empty">
                <strong>No public songs are available.</strong>
                <p>Published tracks will appear here automatically.</p>
            </div>
        <?php endif; ?>
    </section>
</main>
</section>
</div>

<?php if ($initialTrack): ?>
<button type="button" hidden data-music-initial-track <?=music_dashboard_track_attributes($initialTrack)?>>Initial track</button>
<?php endif; ?>

<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260801-v66Q9'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/visitor-activity.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/music-player.js?v=20260801-professional-player-v66Q9'))?>"></script>
<script src="<?=e(app_url('assets/js/music-dashboard.js?v=20260801-v66Q10'))?>"></script>
<script src="<?=e(app_url('assets/js/music-library-dashboard-v66q10.js?v=20260801-v66Q18'))?>"></script>
<script>
window.NMMVisitorActivity?.track(
  'music_library_view',
  {
    event_label: <?=json_encode(
        $demoMode ? 'Music Library demo mode' : 'Music Library',
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    )?>,
    metadata: { demo_mode: <?=$demoMode ? 'true' : 'false'?> },
    deduplicate: false
  }
);
</script>
</body>
</html>
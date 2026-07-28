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
        $leftDate = strtotime(
            (string)(
                $left['published_at']
                ?: $left['created_at']
            )
        ) ?: 0;
        $rightDate = strtotime(
            (string)(
                $right['published_at']
                ?: $right['created_at']
            )
        ) ?: 0;

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
        ?: strcasecmp(
            (string)$left['title'],
            (string)$right['title']
        )
);
$topSongs = array_slice($topSongs, 0, 5);

$featuredSongs = array_values(array_filter(
    $tracks,
    static fn(array $track): bool =>
        !empty($track['featured'])
));
$featuredSongs = array_slice($featuredSongs, 0, 8);

$allSongs = $tracks;
usort(
    $allSongs,
    static fn(array $left, array $right): int =>
        strcasecmp(
            (string)$left['title'],
            (string)$right['title']
        )
);

$recentCollections = array_slice(
    $playlists ?: $albums,
    0,
    4
);

$trending = [];
foreach ($tracks as $track) {
    $genre = strtolower(trim((string)$track['genre']));
    if ($genre !== '' && !in_array($genre, $trending, true)) {
        $trending[] = $genre;
    }
}
$trending = array_slice($trending, 0, 5);

$featuredCollection = null;

if ($musicBanner && !empty($musicBanner['tracks'])) {
    $featuredCollection = [
        'title' => (string)$musicBanner['title'],
        'tracks' => $musicBanner['tracks'],
        'type' => (string)(
            $musicBanner['collection_type']
            ?? 'playlist'
        ),
    ];
} else {
    foreach ($playlists as $playlist) {
        if (!empty($playlist['featured'])) {
            $featuredCollection = [
                'title' => (string)$playlist['title'],
                'tracks' => $playlist['tracks'],
                'type' => 'playlist',
            ];
            break;
        }
    }

    if (!$featuredCollection && $playlists) {
        $featuredCollection = [
            'title' => (string)$playlists[0]['title'],
            'tracks' => $playlists[0]['tracks'],
            'type' => 'playlist',
        ];
    }

    if (!$featuredCollection && $albums) {
        $featuredCollection = [
            'title' => (string)$albums[0]['title'],
            'tracks' => $albums[0]['tracks'],
            'type' => 'album',
        ];
    }
}

$initialTrack = $topSongs[0]
    ?? $newSongs[0]
    ?? $tracks[0]
    ?? null;

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);
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

function music_dashboard_track_attributes(
    array $track
): string {
    return implode(' ', [
        'data-music-play',
        'data-track-id="' . (int)$track['id'] . '"',
        'data-track-title="' . e($track['title']) . '"',
        'data-track-artist="' . e($track['artist']) . '"',
        'data-track-album="' . e($track['album']) . '"',
        'data-track-stream="' . e($track['stream_url']) . '"',
        'data-track-cover="' . e($track['cover_url']) . '"',
        'data-track-duration="'
            . (int)($track['duration_seconds'] ?? 0)
            . '"',
        'data-track-demo="'
            . (!empty($track['demo']) ? '1' : '0')
            . '"',
    ]);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>
<meta
    name="description"
    content="North Mountain Media streaming music library."
>
<meta
    name="build-version"
    content="20260727-site-controls-landing-v60"
>
<title>Music Library — North Mountain Media</title>
<link
    rel="stylesheet"
    href="<?=e(app_url(
        'assets/css/public-music-shell.css'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
>
<link
    rel="stylesheet"
    href="<?=e(app_url(
        'assets/css/public-sidebar.css'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
>
<link
    rel="stylesheet"
    href="<?=e(app_url(
        'assets/css/music-library.css'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
>
<link
    rel="stylesheet"
    href="<?=e(app_url(
        'assets/css/music-dashboard.css'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
>
</head>
<body class="music-dashboard-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell);?>

<section class="music-public-workspace">
<?php music_render_public_header($shell);?>

<main class="music-dashboard-canvas">
<?php if($demoMode):?>
<?php endif;?>

<?php if($musicBanner):?>
<section
    class="music-feature-banner"
    style="--music-feature-image:url('<?=e(
        $musicBanner['image_url']
    )?>')"
    data-music-collection
>
<div class="music-feature-copy">
<?php if($musicBanner['eyebrow']!==''):?>
<span><?=e($musicBanner['eyebrow'])?></span>
<?php endif;?>
<?php if($musicBanner['title']!==''):?>
<h1><?=e($musicBanner['title'])?></h1>
<?php endif;?>
<?php if($musicBanner['subtitle']!==''):?>
<p><?=e($musicBanner['subtitle'])?></p>
<?php endif;?>

<div class="music-feature-actions">
<?php if($featuredCollection&&$featuredCollection['tracks']):?>
<button
    class="music-feature-play"
    type="button"
    data-music-play-all
>
<span>▶</span>
Play
</button>
<?php endif;?>
<button
    class="music-feature-favorite"
    type="button"
    aria-label="Save featured collection"
>♡</button>
</div>
</div>

<div class="music-feature-wave" aria-hidden="true">
<?php for($wave=0;$wave<34;$wave++):?>
<i style="--wave-height:<?=10+(($wave*17)%38)?>px"></i>
<?php endfor;?>
</div>

<?php if($featuredCollection&&$featuredCollection['tracks']):?>
<button
    class="music-feature-large-play"
    type="button"
    data-music-play-all
    aria-label="Play <?=e($featuredCollection['title'])?>"
>▶</button>

<div hidden>
<?php foreach($featuredCollection['tracks'] as $track):?>
<button
    type="button"
    <?=music_dashboard_track_attributes($track)?>
>Play</button>
<?php endforeach;?>
</div>
<?php endif;?>

<div class="music-feature-dots" aria-hidden="true">
<i class="active"></i><i></i><i></i><i></i>
</div>
</section>
<?php endif;?>

<section class="music-dashboard-section" id="albums">
<header class="music-dashboard-section-header">
<h2>Albums</h2>
<a href="#all-songs">View all ›</a>
</header>

<?php if($albums):?>
<div class="music-dashboard-album-rail">
<?php foreach(array_slice($albums,0,8) as $album):?>
<article
    class="music-dashboard-album"
    data-music-collection
>
<div class="music-dashboard-album-art">
<a
    href="<?=e(music_collection_public_url(
        'album',
        (string)$album['slug']
    ))?>"
>
<img
    src="<?=e($album['cover_url'])?>"
    alt="<?=e($album['title'])?> cover"
    loading="lazy"
>
</a>
<button
    type="button"
    data-music-play-all
    aria-label="Play <?=e($album['title'])?>"
>▶</button>
</div>
<h3>
<a
    href="<?=e(music_collection_public_url(
        'album',
        (string)$album['slug']
    ))?>"
><?=e($album['title'])?></a>
</h3>
<p><?=e($album['artist'])?></p>
<div hidden>
<?php foreach($album['tracks'] as $track):?>
<button
    type="button"
    <?=music_dashboard_track_attributes($track)?>
>Play</button>
<?php endforeach;?>
</div>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="music-dashboard-empty">
No public albums are available.
</div>
<?php endif;?>
</section>

<section class="music-dashboard-grid">
<article
    class="music-dashboard-list-card"
    data-music-collection
>
<header>
<h2>New Songs</h2>
<a href="#all-songs">View all ›</a>
</header>
<div class="music-dashboard-song-list">
<?php foreach($newSongs as $track):?>
<div class="music-dashboard-song-row">
<img
    src="<?=e($track['cover_url'])?>"
    alt=""
    loading="lazy"
>
<button
    class="music-dashboard-row-play"
    type="button"
    <?=music_dashboard_track_attributes($track)?>
    aria-label="Play <?=e($track['title'])?>"
>▶</button>
<div>
<strong><?=e($track['title'])?></strong>
<span><?=e($track['artist'])?></span>
</div>
<time><?=e($track['duration_label'])?></time>
<button
    class="music-dashboard-more"
    type="button"
    aria-label="More options"
>⋮</button>
</div>
<?php endforeach;?>
</div>
</article>

<article
    class="music-dashboard-list-card"
    data-music-collection
>
<header>
<h2>Top Songs</h2>
<a href="#all-songs">View all ›</a>
</header>
<div class="music-dashboard-song-list top-songs">
<?php foreach($topSongs as $index=>$track):?>
<div class="music-dashboard-song-row">
<span class="music-dashboard-rank"><?=$index+1?></span>
<img
    src="<?=e($track['cover_url'])?>"
    alt=""
    loading="lazy"
>
<button
    class="music-dashboard-row-play"
    type="button"
    <?=music_dashboard_track_attributes($track)?>
    aria-label="Play <?=e($track['title'])?>"
>▶</button>
<div>
<strong><?=e($track['title'])?></strong>
<span><?=e($track['artist'])?></span>
</div>
<time><?=e($track['duration_label'])?></time>
<button
    class="music-dashboard-heart"
    type="button"
    aria-label="Save <?=e($track['title'])?>"
>♡</button>
</div>
<?php endforeach;?>
</div>
</article>

<div class="music-dashboard-right-stack">
<article class="music-dashboard-recent-card">
<header>
<h2>Recently Played</h2>
<a href="#all-songs">View all ›</a>
</header>
<div
    class="music-dashboard-recent-grid"
    data-recently-played
>
<?php foreach($recentCollections as $collection):?>
<article
    class="music-dashboard-recent-item"
    data-music-collection
>
<div>
<img
    src="<?=e($collection['cover_url'])?>"
    alt="<?=e($collection['title'])?> cover"
    loading="lazy"
>
<button
    type="button"
    data-music-play-all
    aria-label="Play <?=e($collection['title'])?>"
>▶</button>
</div>
<strong><?=e($collection['title'])?></strong>
<span><?=isset($collection['type'])?'Album':'Playlist'?></span>
<div hidden>
<?php foreach($collection['tracks'] as $track):?>
<button
    type="button"
    <?=music_dashboard_track_attributes($track)?>
>Play</button>
<?php endforeach;?>
</div>
</article>
<?php endforeach;?>
</div>
</article>

<article class="music-dashboard-trending">
<header>
<h2>Trending Now</h2>
<a href="#featured-songs">View all</a>
</header>
<div>
<?php foreach($trending as $tag):?>
<a href="#all-songs"># <?=e($tag)?></a>
<?php endforeach;?>
<a class="next" href="#all-songs">›</a>
</div>
</article>
</div>
</section>

<section
    class="music-dashboard-section music-featured-section"
    id="featured-songs"
    data-music-collection
>
<header class="music-dashboard-section-header">
<h2>Featured Songs</h2>
<button type="button" data-music-play-all>Play all</button>
</header>
<div class="music-dashboard-featured-rail">
<?php foreach($featuredSongs as $track):?>
<article>
<div>
<img
    src="<?=e($track['cover_url'])?>"
    alt="<?=e($track['title'])?> cover"
    loading="lazy"
>
<button
    type="button"
    <?=music_dashboard_track_attributes($track)?>
>▶</button>
</div>
<h3><?=e($track['title'])?></h3>
<p><?=e($track['artist'])?></p>
</article>
<?php endforeach;?>
</div>
</section>

<section
    class="music-dashboard-section"
    id="all-songs"
    data-music-collection
>
<header class="music-dashboard-section-header">
<h2>All Songs</h2>
<button type="button" data-music-play-all>Play all</button>
</header>
<div class="music-dashboard-all-songs">
<?php foreach($allSongs as $index=>$track):?>
<article>
<span><?=$index+1?></span>
<img
    src="<?=e($track['cover_url'])?>"
    alt=""
    loading="lazy"
>
<button
    type="button"
    <?=music_dashboard_track_attributes($track)?>
>▶</button>
<div>
<strong><?=e($track['title'])?></strong>
<small><?=e($track['artist'])?></small>
</div>
<em><?=e($track['album']?:'Single')?></em>
<time><?=e($track['duration_label'])?></time>
</article>
<?php endforeach;?>
</div>
</section>
</main>
</section>
</div>

<?php if($initialTrack):?>
<button
    type="button"
    hidden
    data-music-initial-track
    <?=music_dashboard_track_attributes($initialTrack)?>
>Initial track</button>
<?php endif;?>

<script
    src="<?=e(app_url(
        'assets/js/public-sidebar.js'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
></script>
<script
    src="<?=e(app_url(
        'assets/js/public-music-shell.js'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
></script>
<script
    src="<?=e(app_url(
        'assets/js/visitor-activity.js'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
></script>
<script
    src="<?=e(app_url(
        'assets/js/music-player.js'
        . '?v=20260728-site-analytics-v61.9'
    ))?>"
></script>
<script
    src="<?=e(app_url(
        'assets/js/music-dashboard.js'
        . '?v=20260727-site-controls-landing-v60'
    ))?>"
></script>
<script>
window.NMMVisitorActivity?.track(
  'music_library_view',
  {
    event_label: <?=json_encode(
        $demoMode
            ? 'Music Library demo mode'
            : 'Music Library',
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    )?>,
    metadata: {
      demo_mode: <?=$demoMode?'true':'false'?>
    },
    deduplicate: false
  }
);
</script>
</body>
</html>

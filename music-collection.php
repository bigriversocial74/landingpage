<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('music_library');
require_once __DIR__ . '/portal/music-library.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$type = strtolower(
    trim((string)($_GET['type'] ?? 'album'))
);
$type = $type === 'playlist'
    ? 'playlist'
    : 'album';
$slug = trim((string)($_GET['slug'] ?? ''));
$collection = music_public_collection(
    $type,
    $slug
);
$shell = music_public_shell_context();

if (!$collection) {
    http_response_code(404);
}

$tracks = $collection['tracks'] ?? [];
$collectionTitle = (string)(
    $collection['title']
    ?? (
        $type === 'playlist'
            ? 'Playlist unavailable'
            : 'Album unavailable'
    )
);
$artist = (string)(
    $collection['artist_name']
    ?? $collection['artist']
    ?? 'North Mountain Media'
);
$description = trim(
    (string)($collection['description'] ?? '')
);
$year = (int)(
    $collection['release_year'] ?? 0
);
$genre = trim(
    (string)($collection['genre'] ?? '')
);
$totalSeconds = (int)(
    $collection['total_seconds'] ?? 0
);
$trackCount = (int)(
    $collection['track_count']
    ?? count($tracks)
);
$coverUrl = (string)(
    $collection['cover_url']
    ?? app_url(
        'assets/images/music-cover-placeholder.svg'
    )
);
$collectionLabel = $type === 'playlist'
    ? 'Playlist'
    : status_label(
        (string)(
            $collection['album_type']
            ?? $collection['type']
            ?? 'album'
        )
    );
$demoMode = !empty($collection['demo']);

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

function music_collection_track_attributes_v49(
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
    content="<?=e($collectionTitle)?> — <?=e($collectionLabel)?> on North Mountain Media."
>
<meta
    name="build-version"
    content="20260727-site-controls-landing-v60"
>
<title><?=e($collectionTitle)?> — North Mountain Media Music</title>
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
<body class="music-dashboard-body music-collection-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell);?>

<section class="music-public-workspace">
<?php music_render_public_header($shell);?>

<main class="music-collection-main">
<?php if(!$collection):?>
<section class="music-collection-missing">
<span><?=e($collectionLabel)?></span>
<h1>Collection unavailable</h1>
<p>This collection is not published or could not be found.</p>
<a href="<?=e(app_url('music-library.php'))?>">
Return to Music Library
</a>
</section>
<?php else:?>
<?php if($demoMode):?>
<?php endif;?>

<nav
    class="music-collection-breadcrumbs"
    aria-label="Breadcrumb"
>
<a href="<?=e(app_url('music-library.php'))?>">
Music Library
</a>
<span>›</span>
<a
    href="<?=e(app_url(
        'music-library.php#'
        . ($type==='playlist'
            ?'recently-played'
            :'albums')
    ))?>"
><?=e($collectionLabel)?>s</a>
<span>›</span>
<strong><?=e($collectionTitle)?></strong>
</nav>

<section
    class="music-collection-hero"
    style="--collection-cover:url('<?=e($coverUrl)?>')"
>
<div
    class="music-collection-hero-backdrop"
    aria-hidden="true"
></div>

<div class="music-collection-art-wrap">
<img
    class="music-collection-art"
    src="<?=e($coverUrl)?>"
    alt="<?=e($collectionTitle)?> cover"
>
</div>

<div class="music-collection-hero-copy">
<span class="music-collection-type">
<?=e($collectionLabel)?>
</span>
<h1><?=e($collectionTitle)?></h1>

<div class="music-collection-creator">
<img
    src="<?=e($shell['profile_image'])?>"
    alt=""
>
<strong><?=e($artist)?></strong>
</div>

<div class="music-collection-meta">
<?php if($year>0):?><span><?=$year?></span><?php endif;?>
<?php if($genre!==''):?><span><?=e($genre)?></span><?php endif;?>
<span><?=$trackCount?> song<?=$trackCount===1?'':'s'?></span>
<?php if($totalSeconds>0):?>
<span><?=e(music_duration_label($totalSeconds))?></span>
<?php endif;?>
</div>

<?php if($description!==''):?>
<p><?=e($description)?></p>
<?php endif;?>

<div
    class="music-collection-actions"
    data-music-collection
>
<button
    class="music-collection-play"
    type="button"
    data-music-play-all
>
<span>▶</span>
Play <?=e($collectionLabel)?>
</button>

<button
    class="music-collection-shuffle"
    type="button"
    data-music-shuffle
>
<span>⤨</span>
Shuffle
</button>

<div hidden>
<?php foreach($tracks as $track):?>
<button
    type="button"
    <?=music_collection_track_attributes_v49($track)?>
>Play</button>
<?php endforeach;?>
</div>
</div>
</div>
</section>

<section
    class="music-collection-track-section"
    data-music-collection
>
<header class="music-collection-track-header">
<div>
<span><?=e($collectionLabel)?></span>
<h2><?=$trackCount?> track<?=$trackCount===1?'':'s'?></h2>
</div>
<button
    type="button"
    data-music-play-all
>Play all</button>
</header>

<div class="music-collection-track-list">
<div
    class="music-collection-track-columns"
    aria-hidden="true"
>
<span>#</span>
<span>Title</span>
<span>Album</span>
<span>Plays</span>
<span>Time</span>
<span></span>
</div>

<?php foreach($tracks as $index=>$track):?>
<article class="music-collection-track-row">
<span class="music-collection-track-number">
<?=$index+1?>
</span>

<button
    class="music-collection-track-title"
    type="button"
    <?=music_collection_track_attributes_v49($track)?>
>
<img
    src="<?=e($track['cover_url'])?>"
    alt=""
    loading="lazy"
>
<span>
<strong><?=e($track['title'])?></strong>
<small>
<?=e($track['artist'])?>
<?php if($track['explicit']):?>
<em>Explicit</em>
<?php endif;?>
</small>
</span>
</button>

<span class="music-collection-track-album">
<?=e($track['album']?:$collectionTitle)?>
</span>

<span class="music-collection-track-plays">
<?=number_format((int)$track['play_count'])?>
</span>

<span class="music-collection-track-duration">
<?=e($track['duration_label'])?>
</span>

<div class="music-collection-track-menu">
<?php if($track['downloadable']&&$track['download_url']):?>
<a
    href="<?=e($track['download_url'])?>"
    aria-label="Download <?=e($track['title'])?>"
>↓</a>
<?php endif;?>
<button
    type="button"
    <?=music_collection_track_attributes_v49($track)?>
    aria-label="Play <?=e($track['title'])?>"
>▶</button>
</div>
</article>
<?php endforeach;?>
</div>
</section>

<footer class="music-collection-release-info">
<?php if($type==='album'):?>
<strong><?=e($collectionTitle)?></strong>
<span>
<?php if($year>0):?>Released <?=$year?> · <?php endif;?>
<?=e($artist)?>
</span>
<?php else:?>
<strong>Curated playlist</strong>
<span><?=e($artist)?> · <?=$trackCount?> songs</span>
<?php endif;?>
</footer>
<?php endif;?>
</main>
</section>
</div>

<?php if($tracks):?>
<button
    type="button"
    hidden
    data-music-initial-track
    <?=music_collection_track_attributes_v49($tracks[0])?>
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
(() => {
  const collection = <?=json_encode(
      [
          'type' => $type,
          'id' => (int)($collection['id'] ?? 0),
          'title' => $collectionTitle,
          'demo' => $demoMode,
      ],
      JSON_UNESCAPED_SLASHES
      | JSON_UNESCAPED_UNICODE
  )?>;

  if (!collection.id) return;

  window.NMMVisitorActivity?.track(
    collection.type === 'playlist'
      ? 'music_playlist_view'
      : 'music_album_view',
    {
      event_label: collection.title,
      metadata: {
        collection_id: collection.id,
        collection_type: collection.type,
        demo_mode: collection.demo
      },
      deduplicate: false
    }
  );
})();
</script>
</body>
</html>

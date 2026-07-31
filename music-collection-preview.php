<?php
declare(strict_types=1);

require_once __DIR__ . '/portal/public-sidebar.php';

$previewSidebarContext = [
    'profile_name' => 'David Evans',
    'profile_image' => 'assets/images/david-evans-profile.jpg',
    'projects' => [
        ['slug' => 'gruber', 'title' => 'Gruber Procurement Intelligence Platform'],
        ['slug' => 'microgifter', 'title' => 'Microgifter'],
        ['slug' => 'homestead', 'title' => 'Homestead'],
        ['slug' => 'poolzebo', 'title' => 'Poolzebo'],
        ['slug' => 'spaced-invaders', 'title' => 'Spaced Invaders'],
        ['slug' => 'stonefellow', 'title' => 'Stonefellow'],
        ['slug' => 'roger-huston', 'title' => 'Roger Huston'],
    ],
];
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Album Detail Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/public-music-shell.css?v=20260728-content-controls-v62.1">
<link rel="stylesheet" href="assets/css/public-sidebar.css?v=20260728-content-controls-v62.1">
<link rel="stylesheet" href="assets/css/music-library.css?v=20260728-content-controls-v62.1">
<link rel="stylesheet" href="assets/css/music-dashboard.css?v=20260728-content-controls-v62.1">
<link rel="stylesheet" href="assets/css/music-mobile-upgrade-v66n.css?v=20260731-mobile-player-v66n">
</head>
<body class="music-dashboard-body music-collection-body">
<div class="music-public-shell">
<?php
nmm_render_public_sidebar($previewSidebarContext);
?>

<section class="music-public-workspace">
<header class="workspace-header">
<button class="sidebar-toggle" data-sidebar-open type="button"><span></span><span></span><span></span></button>
<div class="workspace-header-actions">
<a class="workspace-header-action primary" href="#">Client Login</a>
<a class="workspace-header-action" href="#">Admin Login</a>
</div>
</header>

<main class="music-collection-main">
<nav class="music-collection-breadcrumbs"><a href="#">Music Library</a><span>›</span><a href="#">Albums</a><span>›</span><strong>Golden Horizon</strong></nav>

<section class="music-collection-hero">
<div class="music-collection-art-wrap">
<img class="music-collection-art" src="assets/demo-music/covers/golden-horizon.svg" alt="Golden Horizon cover">
</div>
<div class="music-collection-hero-copy">
<span class="music-collection-type">Album</span>
<h1>Golden Horizon</h1>
<div class="music-collection-creator"><strong>Luna Shores</strong></div>
<div class="music-collection-meta"><span>2026</span><span>Lo-fi</span><span>4 songs</span><span>1:12</span></div>
<p>A calm demo release built for the North Mountain Media streaming interface.</p>
<div class="music-collection-actions" data-music-collection>
<button class="music-collection-play" type="button" data-music-play-all><span>▶</span>Play Album</button>
<button class="music-collection-shuffle" type="button" data-music-shuffle><span>⤨</span>Shuffle</button>
<div hidden><button type="button" data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900008" data-track-title="Stargazer" data-track-artist="Owen Miles" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900008" data-track-cover="assets/demo-music/covers/stargazer.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</div>
</div>
</section>

<section class="music-collection-track-section" data-music-collection>
<header class="music-collection-track-header"><div><span>Album</span><h2>4 tracks</h2></div><button type="button" data-music-play-all>Play all</button></header>
<div class="music-collection-track-list">
<div class="music-collection-track-columns"><span>#</span><span>Title</span><span>Album</span><span>Plays</span><span>Time</span><span></span></div>

<article class="music-collection-track-row">
<span class="music-collection-track-number">1</span>
<button class="music-collection-track-title" type="button" data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">
<img src="assets/demo-music/covers/golden-horizon.svg" alt="">
<span><strong>Golden Hour</strong><small>Luna Shores</small></span>
</button>
<span class="music-collection-track-album">Golden Horizon</span>
<span class="music-collection-track-plays">312</span>
<span class="music-collection-track-duration">0:18</span>
<div class="music-collection-track-menu"><button type="button" data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">▶</button></div>
</article>
<article class="music-collection-track-row">
<span class="music-collection-track-number">2</span>
<button class="music-collection-track-title" type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">
<img src="assets/demo-music/covers/golden-horizon.svg" alt="">
<span><strong>Take It Slow</strong><small>Luna Shores</small></span>
</button>
<span class="music-collection-track-album">Golden Horizon</span>
<span class="music-collection-track-plays">184</span>
<span class="music-collection-track-duration">0:18</span>
<div class="music-collection-track-menu"><button type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">▶</button></div>
</article>
<article class="music-collection-track-row">
<span class="music-collection-track-number">3</span>
<button class="music-collection-track-title" type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">
<img src="assets/demo-music/covers/still-waters.svg" alt="">
<span><strong>Falling Into Place</strong><small>Harbor Lights</small></span>
</button>
<span class="music-collection-track-album">Golden Horizon</span>
<span class="music-collection-track-plays">151</span>
<span class="music-collection-track-duration">0:18</span>
<div class="music-collection-track-menu"><button type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">▶</button></div>
</article>
<article class="music-collection-track-row">
<span class="music-collection-track-number">4</span>
<button class="music-collection-track-title" type="button" data-music-play data-track-id="900008" data-track-title="Stargazer" data-track-artist="Owen Miles" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900008" data-track-cover="assets/demo-music/covers/stargazer.svg" data-track-duration="18" data-track-demo="1">
<img src="assets/demo-music/covers/stargazer.svg" alt="">
<span><strong>Stargazer</strong><small>Owen Miles</small></span>
</button>
<span class="music-collection-track-album">Golden Horizon</span>
<span class="music-collection-track-plays">243</span>
<span class="music-collection-track-duration">0:18</span>
<div class="music-collection-track-menu"><button type="button" data-music-play data-track-id="900008" data-track-title="Stargazer" data-track-artist="Owen Miles" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900008" data-track-cover="assets/demo-music/covers/stargazer.svg" data-track-duration="18" data-track-demo="1">▶</button></div>
</article>
</div>
</section>
<footer class="music-collection-release-info"><strong>Golden Horizon</strong><span>Released 2026 · Luna Shores</span></footer>
</main>
</section>
</div>

<button type="button" hidden data-music-initial-track data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Initial</button>
<script src="assets/js/public-music-shell.js?v=20260728-content-controls-v62.1"></script>
<script src="assets/js/music-player.js?v=20260728-site-analytics-v61.9"></script>
<script src="assets/js/music-dashboard.js?v=20260728-content-controls-v62.1"></script>
<script src="assets/js/public-sidebar.js?v=20260728-content-controls-v62.1"></script>
</body>
</html>

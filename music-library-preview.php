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
<title>Music Dashboard Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60">
<link rel="stylesheet" href="assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60">
<link rel="stylesheet" href="assets/css/music-library.css?v=20260727-site-controls-landing-v60">
<link rel="stylesheet" href="assets/css/music-dashboard.css?v=20260727-site-controls-landing-v60">
</head>
<body class="music-dashboard-body">
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

<main class="music-dashboard-canvas">

<section class="music-feature-banner" style="--music-feature-image:url('assets/demo-music/featured-banner.svg')" data-music-collection>
<div class="music-feature-copy">
<span>Featured Playlist</span>
<h1>Focus Flow</h1>
<p>Deep beats and calm vibes to help you focus, create, and do your best work.</p>
<div class="music-feature-actions">
<button class="music-feature-play" type="button" data-music-play-all><span>▶</span>Play</button>
<button class="music-feature-favorite" type="button">♡</button>
</div>
</div>
<div class="music-feature-wave"><i style="--wave-height:10px"></i><i style="--wave-height:27px"></i><i style="--wave-height:44px"></i><i style="--wave-height:23px"></i><i style="--wave-height:40px"></i><i style="--wave-height:19px"></i><i style="--wave-height:36px"></i><i style="--wave-height:15px"></i><i style="--wave-height:32px"></i><i style="--wave-height:11px"></i><i style="--wave-height:28px"></i><i style="--wave-height:45px"></i><i style="--wave-height:24px"></i><i style="--wave-height:41px"></i><i style="--wave-height:20px"></i><i style="--wave-height:37px"></i><i style="--wave-height:16px"></i><i style="--wave-height:33px"></i><i style="--wave-height:12px"></i><i style="--wave-height:29px"></i><i style="--wave-height:46px"></i><i style="--wave-height:25px"></i><i style="--wave-height:42px"></i><i style="--wave-height:21px"></i><i style="--wave-height:38px"></i><i style="--wave-height:17px"></i><i style="--wave-height:34px"></i><i style="--wave-height:13px"></i><i style="--wave-height:30px"></i><i style="--wave-height:47px"></i><i style="--wave-height:26px"></i><i style="--wave-height:43px"></i><i style="--wave-height:22px"></i><i style="--wave-height:39px"></i></div>
<button class="music-feature-large-play" type="button" data-music-play-all>▶</button>
<div hidden><button type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900002" data-track-title="Better Than Yesterday" data-track-artist="Owen Miles" data-track-album="Into the Wild" data-track-stream="demo-music.php?id=900002" data-track-cover="assets/demo-music/covers/into-the-wild.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Still Waters" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900004" data-track-title="Close to You" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900004" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
<div class="music-feature-dots"><i class="active"></i><i></i><i></i><i></i></div>
</section>

<section class="music-dashboard-section">
<header class="music-dashboard-section-header"><h2>Albums</h2><a href="#all-songs">View all ›</a></header>
<div class="music-dashboard-album-rail">
<article class="music-dashboard-album" data-music-collection>
<div class="music-dashboard-album-art">
<a href="#"><img src="assets/demo-music/covers/golden-horizon.svg" alt="Golden Horizon cover"></a>
<button type="button" data-music-play-all>▶</button>
</div>
<h3><a href="#">Golden Horizon</a></h3>
<p>Luna Shores</p>
<div hidden><button type="button" data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-album" data-music-collection>
<div class="music-dashboard-album-art">
<a href="#"><img src="assets/demo-music/covers/midnight-drive.svg" alt="Midnight Drive cover"></a>
<button type="button" data-music-play-all>▶</button>
</div>
<h3><a href="#">Midnight Drive</a></h3>
<p>The Coastline</p>
<div hidden><button type="button" data-music-play data-track-id="900007" data-track-title="Midnight Drive" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900007" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900004" data-track-title="Close to You" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900004" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-album" data-music-collection>
<div class="music-dashboard-album-art">
<a href="#"><img src="assets/demo-music/covers/moments.svg" alt="Moments cover"></a>
<button type="button" data-music-play-all>▶</button>
</div>
<h3><a href="#">Moments</a></h3>
<p>Elior</p>
<div hidden><button type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Still Waters" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-album" data-music-collection>
<div class="music-dashboard-album-art">
<a href="#"><img src="assets/demo-music/covers/stargazer.svg" alt="Stargazer cover"></a>
<button type="button" data-music-play-all>▶</button>
</div>
<h3><a href="#">Stargazer</a></h3>
<p>Owen Miles</p>
<div hidden><button type="button" data-music-play data-track-id="900008" data-track-title="Stargazer" data-track-artist="Owen Miles" data-track-album="Stargazer" data-track-stream="demo-music.php?id=900008" data-track-cover="assets/demo-music/covers/stargazer.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-album" data-music-collection>
<div class="music-dashboard-album-art">
<a href="#"><img src="assets/demo-music/covers/electric-pulse.svg" alt="Electric Pulse cover"></a>
<button type="button" data-music-play-all>▶</button>
</div>
<h3><a href="#">Electric Pulse</a></h3>
<p>Neon Echo</p>
<div hidden><button type="button" data-music-play data-track-id="900009" data-track-title="Electric Pulse" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900009" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900005" data-track-title="On My Way" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900005" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-album" data-music-collection>
<div class="music-dashboard-album-art">
<a href="#"><img src="assets/demo-music/covers/into-the-wild.svg" alt="Into the Wild cover"></a>
<button type="button" data-music-play-all>▶</button>
</div>
<h3><a href="#">Into the Wild</a></h3>
<p>Pine & Stone</p>
<div hidden><button type="button" data-music-play data-track-id="900002" data-track-title="Better Than Yesterday" data-track-artist="Owen Miles" data-track-album="Into the Wild" data-track-stream="demo-music.php?id=900002" data-track-cover="assets/demo-music/covers/into-the-wild.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-album" data-music-collection>
<div class="music-dashboard-album-art">
<a href="#"><img src="assets/demo-music/covers/still-waters.svg" alt="Still Waters cover"></a>
<button type="button" data-music-play-all>▶</button>
</div>
<h3><a href="#">Still Waters</a></h3>
<p>Harbor Lights</p>
<div hidden><button type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Still Waters" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-album" data-music-collection>
<div class="music-dashboard-album-art">
<a href="#"><img src="assets/demo-music/covers/better-days.svg" alt="Better Days cover"></a>
<button type="button" data-music-play-all>▶</button>
</div>
<h3><a href="#">Better Days</a></h3>
<p>Sunset Kids</p>
<div hidden><button type="button" data-music-play data-track-id="900010" data-track-title="Better Days" data-track-artist="Sunset Kids" data-track-album="Better Days" data-track-stream="demo-music.php?id=900010" data-track-cover="assets/demo-music/covers/better-days.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article></div>
</section>

<section class="music-dashboard-grid">
<article class="music-dashboard-list-card" data-music-collection>
<header><h2>New Songs</h2><a href="#all-songs">View all ›</a></header>
<div class="music-dashboard-song-list">
<div class="music-dashboard-song-row">
<img src="assets/demo-music/covers/golden-horizon.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Take It Slow</strong><span>Luna Shores</span></div>
<time>0:18</time>
<button class="music-dashboard-more" type="button">⋮</button>
</div>
<div class="music-dashboard-song-row">
<img src="assets/demo-music/covers/into-the-wild.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900002" data-track-title="Better Than Yesterday" data-track-artist="Owen Miles" data-track-album="Into the Wild" data-track-stream="demo-music.php?id=900002" data-track-cover="assets/demo-music/covers/into-the-wild.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Better Than Yesterday</strong><span>Owen Miles</span></div>
<time>0:18</time>
<button class="music-dashboard-more" type="button">⋮</button>
</div>
<div class="music-dashboard-song-row">
<img src="assets/demo-music/covers/still-waters.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Still Waters" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Falling Into Place</strong><span>Harbor Lights</span></div>
<time>0:18</time>
<button class="music-dashboard-more" type="button">⋮</button>
</div>
<div class="music-dashboard-song-row">
<img src="assets/demo-music/covers/midnight-drive.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900004" data-track-title="Close to You" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900004" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Close to You</strong><span>The Coastline</span></div>
<time>0:18</time>
<button class="music-dashboard-more" type="button">⋮</button>
</div>
<div class="music-dashboard-song-row">
<img src="assets/demo-music/covers/electric-pulse.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900005" data-track-title="On My Way" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900005" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>On My Way</strong><span>Neon Echo</span></div>
<time>0:18</time>
<button class="music-dashboard-more" type="button">⋮</button>
</div></div>
</article>
<article class="music-dashboard-list-card" data-music-collection>
<header><h2>Top Songs</h2><a href="#all-songs">View all ›</a></header>
<div class="music-dashboard-song-list top-songs">
<div class="music-dashboard-song-row">
<span class="music-dashboard-rank">1</span>
<img src="assets/demo-music/covers/golden-horizon.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Golden Hour</strong><span>Luna Shores</span></div>
<time>0:18</time>
<button class="music-dashboard-heart" type="button">♡</button>
</div>
<div class="music-dashboard-song-row">
<span class="music-dashboard-rank">2</span>
<img src="assets/demo-music/covers/midnight-drive.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900007" data-track-title="Midnight Drive" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900007" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Midnight Drive</strong><span>The Coastline</span></div>
<time>0:18</time>
<button class="music-dashboard-heart" type="button">♡</button>
</div>
<div class="music-dashboard-song-row">
<span class="music-dashboard-rank">3</span>
<img src="assets/demo-music/covers/stargazer.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900008" data-track-title="Stargazer" data-track-artist="Owen Miles" data-track-album="Stargazer" data-track-stream="demo-music.php?id=900008" data-track-cover="assets/demo-music/covers/stargazer.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Stargazer</strong><span>Owen Miles</span></div>
<time>0:18</time>
<button class="music-dashboard-heart" type="button">♡</button>
</div>
<div class="music-dashboard-song-row">
<span class="music-dashboard-rank">4</span>
<img src="assets/demo-music/covers/electric-pulse.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900009" data-track-title="Electric Pulse" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900009" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Electric Pulse</strong><span>Neon Echo</span></div>
<time>0:18</time>
<button class="music-dashboard-heart" type="button">♡</button>
</div>
<div class="music-dashboard-song-row">
<span class="music-dashboard-rank">5</span>
<img src="assets/demo-music/covers/better-days.svg" alt="">
<button class="music-dashboard-row-play" type="button" data-music-play data-track-id="900010" data-track-title="Better Days" data-track-artist="Sunset Kids" data-track-album="Better Days" data-track-stream="demo-music.php?id=900010" data-track-cover="assets/demo-music/covers/better-days.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Better Days</strong><span>Sunset Kids</span></div>
<time>0:18</time>
<button class="music-dashboard-heart" type="button">♡</button>
</div></div>
</article>
<div class="music-dashboard-right-stack">
<article class="music-dashboard-recent-card">
<header><h2>Recently Played</h2><a href="#all-songs">View all ›</a></header>
<div class="music-dashboard-recent-grid" data-recently-played>
<article class="music-dashboard-recent-item" data-music-collection>
<div>
<img src="assets/demo-music/covers/golden-horizon.svg" alt="Focus Flow cover">
<button type="button" data-music-play-all>▶</button>
</div>
<strong>Focus Flow</strong><span>Playlist</span>
<div hidden><button type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900002" data-track-title="Better Than Yesterday" data-track-artist="Owen Miles" data-track-album="Into the Wild" data-track-stream="demo-music.php?id=900002" data-track-cover="assets/demo-music/covers/into-the-wild.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Still Waters" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900004" data-track-title="Close to You" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900004" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-recent-item" data-music-collection>
<div>
<img src="assets/demo-music/covers/moments.svg" alt="Deep Work cover">
<button type="button" data-music-play-all>▶</button>
</div>
<strong>Deep Work</strong><span>Playlist</span>
<div hidden><button type="button" data-music-play data-track-id="900005" data-track-title="On My Way" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900005" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900007" data-track-title="Midnight Drive" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900007" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-recent-item" data-music-collection>
<div>
<img src="assets/demo-music/covers/still-waters.svg" alt="Chill Vibes cover">
<button type="button" data-music-play-all>▶</button>
</div>
<strong>Chill Vibes</strong><span>Playlist</span>
<div hidden><button type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Still Waters" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900010" data-track-title="Better Days" data-track-artist="Sunset Kids" data-track-album="Better Days" data-track-stream="demo-music.php?id=900010" data-track-cover="assets/demo-music/covers/better-days.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article>
<article class="music-dashboard-recent-item" data-music-collection>
<div>
<img src="assets/demo-music/covers/electric-pulse.svg" alt="Morning Boost cover">
<button type="button" data-music-play-all>▶</button>
</div>
<strong>Morning Boost</strong><span>Playlist</span>
<div hidden><button type="button" data-music-play data-track-id="900009" data-track-title="Electric Pulse" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900009" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900005" data-track-title="On My Way" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900005" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">Play</button><button type="button" data-music-play data-track-id="900002" data-track-title="Better Than Yesterday" data-track-artist="Owen Miles" data-track-album="Into the Wild" data-track-stream="demo-music.php?id=900002" data-track-cover="assets/demo-music/covers/into-the-wild.svg" data-track-duration="18" data-track-demo="1">Play</button></div>
</article></div>
</article>
<article class="music-dashboard-trending">
<header><h2>Trending Now</h2><a href="#all-songs">View all</a></header>
<div><a href="#"># lo-fi</a><a href="#"># chill</a><a href="#"># indie</a><a href="#"># electronic</a><a href="#"># acoustic</a><a class="next" href="#">›</a></div>
</article>
</div>
</section>

<section class="music-dashboard-section" id="all-songs" data-music-collection>
<header class="music-dashboard-section-header"><h2>All Songs</h2><button type="button" data-music-play-all>Play all</button></header>
<div class="music-dashboard-all-songs">
<article>
<span>1</span>
<img src="assets/demo-music/covers/golden-horizon.svg" alt="">
<button type="button" data-music-play data-track-id="900001" data-track-title="Take It Slow" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900001" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Take It Slow</strong><small>Luna Shores</small></div>
<em>Golden Horizon</em><time>0:18</time>
</article>
<article>
<span>2</span>
<img src="assets/demo-music/covers/into-the-wild.svg" alt="">
<button type="button" data-music-play data-track-id="900002" data-track-title="Better Than Yesterday" data-track-artist="Owen Miles" data-track-album="Into the Wild" data-track-stream="demo-music.php?id=900002" data-track-cover="assets/demo-music/covers/into-the-wild.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Better Than Yesterday</strong><small>Owen Miles</small></div>
<em>Into the Wild</em><time>0:18</time>
</article>
<article>
<span>3</span>
<img src="assets/demo-music/covers/still-waters.svg" alt="">
<button type="button" data-music-play data-track-id="900003" data-track-title="Falling Into Place" data-track-artist="Harbor Lights" data-track-album="Still Waters" data-track-stream="demo-music.php?id=900003" data-track-cover="assets/demo-music/covers/still-waters.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Falling Into Place</strong><small>Harbor Lights</small></div>
<em>Still Waters</em><time>0:18</time>
</article>
<article>
<span>4</span>
<img src="assets/demo-music/covers/midnight-drive.svg" alt="">
<button type="button" data-music-play data-track-id="900004" data-track-title="Close to You" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900004" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Close to You</strong><small>The Coastline</small></div>
<em>Midnight Drive</em><time>0:18</time>
</article>
<article>
<span>5</span>
<img src="assets/demo-music/covers/electric-pulse.svg" alt="">
<button type="button" data-music-play data-track-id="900005" data-track-title="On My Way" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900005" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>On My Way</strong><small>Neon Echo</small></div>
<em>Electric Pulse</em><time>0:18</time>
</article>
<article>
<span>6</span>
<img src="assets/demo-music/covers/golden-horizon.svg" alt="">
<button type="button" data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Golden Hour</strong><small>Luna Shores</small></div>
<em>Golden Horizon</em><time>0:18</time>
</article>
<article>
<span>7</span>
<img src="assets/demo-music/covers/midnight-drive.svg" alt="">
<button type="button" data-music-play data-track-id="900007" data-track-title="Midnight Drive" data-track-artist="The Coastline" data-track-album="Midnight Drive" data-track-stream="demo-music.php?id=900007" data-track-cover="assets/demo-music/covers/midnight-drive.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Midnight Drive</strong><small>The Coastline</small></div>
<em>Midnight Drive</em><time>0:18</time>
</article>
<article>
<span>8</span>
<img src="assets/demo-music/covers/stargazer.svg" alt="">
<button type="button" data-music-play data-track-id="900008" data-track-title="Stargazer" data-track-artist="Owen Miles" data-track-album="Stargazer" data-track-stream="demo-music.php?id=900008" data-track-cover="assets/demo-music/covers/stargazer.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Stargazer</strong><small>Owen Miles</small></div>
<em>Stargazer</em><time>0:18</time>
</article>
<article>
<span>9</span>
<img src="assets/demo-music/covers/electric-pulse.svg" alt="">
<button type="button" data-music-play data-track-id="900009" data-track-title="Electric Pulse" data-track-artist="Neon Echo" data-track-album="Electric Pulse" data-track-stream="demo-music.php?id=900009" data-track-cover="assets/demo-music/covers/electric-pulse.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Electric Pulse</strong><small>Neon Echo</small></div>
<em>Electric Pulse</em><time>0:18</time>
</article>
<article>
<span>10</span>
<img src="assets/demo-music/covers/better-days.svg" alt="">
<button type="button" data-music-play data-track-id="900010" data-track-title="Better Days" data-track-artist="Sunset Kids" data-track-album="Better Days" data-track-stream="demo-music.php?id=900010" data-track-cover="assets/demo-music/covers/better-days.svg" data-track-duration="18" data-track-demo="1">▶</button>
<div><strong>Better Days</strong><small>Sunset Kids</small></div>
<em>Better Days</em><time>0:18</time>
</article></div>
</section>
</main>
</section>
</div>

<button type="button" hidden data-music-initial-track data-music-play data-track-id="900006" data-track-title="Golden Hour" data-track-artist="Luna Shores" data-track-album="Golden Horizon" data-track-stream="demo-music.php?id=900006" data-track-cover="assets/demo-music/covers/golden-horizon.svg" data-track-duration="18" data-track-demo="1">Initial</button>
<script src="assets/js/public-music-shell.js?v=20260727-site-controls-landing-v60"></script>
<script src="assets/js/music-player.js?v=20260727-site-controls-landing-v60"></script>
<script src="assets/js/music-dashboard.js?v=20260727-site-controls-landing-v60"></script>
<script src="assets/js/public-sidebar.js?v=20260727-site-controls-landing-v60"></script>
</body>
</html>

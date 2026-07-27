<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Music Demo Mode Administration Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar portal-sidebar-admin">
<div class="portal-brand">
<img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media">
</div>
<nav class="portal-nav portal-nav-admin">
<section class="portal-nav-group">
<button class="portal-nav-group-toggle" type="button">
<span>Operations</span><span>⌃</span>
</button>
<div class="portal-nav-group-links">
<a href="#">Dashboard</a>
<a class="active" href="#">Music Library</a>
<a href="#">Visitor Intelligence</a>
<a href="#">Call Center</a>
<a href="#">Communications</a>
</div>
</section>
</nav>
</aside>

<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block">
<span>North Mountain Media</span>
<h1>Music Library</h1>
</div>
<div class="portal-header-user">
<a class="portal-top-action" href="#">Call Center</a>
<a class="portal-user-info" href="#">
<span class="portal-user-avatar">
<img src="assets/images/david-evans-profile.jpg" alt="">
</span>
<span class="portal-user-copy">
<strong>David Evans</strong>
<small>Administrator</small>
</span>
</a>
</div>
</header>

<div class="portal-content">
<div class="page-actions music-admin-actions">
<nav class="music-admin-tabs">
<a class="button" href="#">Songs</a>
<a class="button" href="#">Albums</a>
<a class="button" href="#">Playlists</a>
<a class="button" href="#">Audio imports</a>
<a class="button" href="#">Banner</a>
<a class="button button-primary" href="#">Demo Mode</a>
</nav>
<span class="spacer"></span>
<a class="button" href="#">Open public player</a>
<a class="button" href="#">Upload MP3</a>
</div>

<div class="stats-grid music-admin-stats">
<article class="stat-card">
<span>Music tracks</span><strong>12</strong><small>9 public</small>
</article>
<article class="stat-card">
<span>Albums</span><strong>3</strong><small>2 public releases</small>
</article>
<article class="stat-card">
<span>Playlists</span><strong>4</strong><small>3 public collections</small>
</article>
<article class="stat-card">
<span>Recorded plays</span><strong>148</strong><small>First-party player events</small>
</article>
<article class="stat-card">
<span>Public source</span><strong>Demo</strong><small>Playable sample catalog</small>
</article>
</div>

<form class="form-panel music-demo-admin-form">
<header class="music-editor-header">
<div>
<span>Public catalog source</span>
<h2>Demo Music Mode</h2>
<p>Switch the public Music Library between the live published catalog and a complete playable sample catalog. Uploaded songs, albums, playlists, artwork, play totals, and publishing settings remain intact.</p>
</div>
<span class="music-demo-admin-badge active">Demo active</span>
</header>

<div class="music-demo-admin-layout">
<section>
<label class="music-admin-toggle">
<input type="checkbox" checked>
<span>
<strong>Enable Demo Music Mode</strong>
<small>Uses eight sample albums, ten original synthesized MP3 demos, four playlists, New Songs, Top Songs, Recently Played, Trending Now, Featured Songs, and All Songs.</small>
</span>
</label>

<label class="music-admin-toggle">
<input type="checkbox" checked>
<span>
<strong>Display the demo featured banner</strong>
<small>The mountain banner appears only while Demo Music Mode is active. A custom Banner tab image takes priority.</small>
</span>
</label>
</section>

<aside>
<h3>Included demo behavior</h3>
<ul>
<li>Byte-range MP3 playback and seeking</li>
<li>Play, pause, previous, next, shuffle, repeat, volume, and queue</li>
<li>Recently Played stored in the visitor browser</li>
<li>Music Library, album, playlist, and track-play analytics</li>
<li>Visitor/session attribution and CRM relationship timeline activity</li>
<li>No changes to the live song database</li>
</ul>
</aside>
</div>

<div class="form-footer">
<button class="button button-primary" type="button">Save Demo Mode</button>
<a class="button" href="#">Open public Music Library</a>
</div>
</form>
</div>
</main>
</div>
</body>
</html>

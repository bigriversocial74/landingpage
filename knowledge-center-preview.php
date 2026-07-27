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
<title>Knowledge Library Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar">
<div class="portal-brand">
<img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media">
</div>
<nav class="portal-nav portal-nav-admin">
<a href="#">Dashboard</a>
<a href="#">Call Center</a>
<a href="#">Clients</a>
<a href="#">Administrators</a>
<a href="#">CRM</a>
<a href="#">Projects</a>
<a href="#">Leads</a>
<a href="#">Communications</a>
<a href="#">Notifications</a>
<a href="#">Files</a>
<a class="active" href="#">Knowledge Base</a>
<a href="#">Settings</a>
<a href="#">Account</a>
</nav>
</aside>

<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block">
<span>North Mountain Media</span>
<h1>Knowledge</h1>
</div>
<div class="portal-header-user">
<a class="portal-top-action" href="#">Call Center</a>
<a class="portal-user-info" href="#">
<span class="portal-user-avatar">D</span>
<span class="portal-user-copy">
<strong>David Evans</strong>
<small>Administrator</small>
</span>
</a>
</div>
</header>

<div class="portal-content">
<div class="knowledge-center-shell">
<header class="knowledge-library-header">
<div>
<span>Knowledge Center</span>
<h2>Library</h2>
<p>
Text knowledge is the default library. Media tabs appear
only after that exact file type has been uploaded.
</p>
</div>
<div class="page-actions">
<a class="button button-primary" href="#">Add Media</a>
</div>
</header>

<nav class="knowledge-media-tabs knowledge-library-tabs">
<a class="active" href="#"><span>Text</span><small>32</small></a>
<a href="#"><span>MP3</span><small>3</small></a>
<a href="#"><span>MP4</span><small>2</small></a>
<a href="#"><span>PDF</span><small>4</small></a>
</nav>

<section class="panel knowledge-library-panel-full">
<header class="panel-header">
<div>
<span>Assistant knowledge</span>
<h2>Text Content</h2>
</div>
<span class="knowledge-library-count">32 entries</span>
</header>

<div class="knowledge-manual-grid knowledge-manual-grid-full">
<?php foreach ([
    ['David Evans Professional Profile','Profile'],
    ['Resume and Professional Experience','Resume'],
    ['Operations and Process-Improvement Fit','Capabilities'],
    ['Microgifter Platform','Project'],
    ['Microgifter Case Study','Case Study'],
    ['Gruber Procurement Intelligence Platform','Case Study'],
    ['VP3.ME Agentic Operating System','Project'],
    ['Stonefellow Membership and Streaming Platform','Project'],
    ['Poolzebo Modular Backyard Systems','Project'],
    ['Microgifter Training Lab','Project'],
    ['Contact and Availability','Contact'],
    ['North Mountain Media','Brand'],
] as $entry): ?>
<a href="#">
<strong><?=htmlspecialchars($entry[0])?></strong>
<small><?=htmlspecialchars($entry[1])?></small>
<span>Open content</span>
</a>
<?php endforeach; ?>
</div>
</section>
</div>
</div>
</main>
</div>
</body>
</html>

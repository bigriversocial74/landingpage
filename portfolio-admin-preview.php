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
<title>Portfolio Administration Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar portal-sidebar-admin">
<div class="portal-brand"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></div>
<nav class="portal-nav portal-nav-admin">
<section class="portal-nav-group">
<button class="portal-nav-group-toggle" type="button"><span>Work</span><span>⌃</span></button>
<div class="portal-nav-group-links">
<a class="active" href="#">Portfolio</a>
<a href="#">Client Projects</a>
<a href="#">Files</a>
<a href="#">Knowledge Base</a>
</div>
</section>
</nav>
</aside>
<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block"><span>North Mountain Media</span><h1>Portfolio</h1></div>
<div class="portal-header-user">
<a class="portal-top-action" href="#">Call Center</a>
<a class="portal-user-info" href="#">
<span class="portal-user-avatar"><img src="assets/images/david-evans-profile.jpg" alt=""></span>
<span class="portal-user-copy"><strong>David Evans</strong><small>Administrator</small></span>
</a>
</div>
</header>
<div class="portal-content">
<div class="page-actions">
<a class="button button-primary" href="#">Add portfolio project</a>
<span class="spacer"></span>
<a class="button" href="#">Open public portfolio</a>
</div>
<div class="portfolio-admin-grid">
<?php foreach([
['Gruber Procurement Intelligence Platform','Procurement Intelligence Platform','A connected procurement environment turning fragmented purchasing information into a shared decision-ready system.','G','Featured'],
['Microgifter','Social Gifting and Merchant CRM Platform','A mobile-first platform connecting gifting, CRM, campaigns, rewards, claims and automated commerce.','M','Featured'],
['Homestead','Household Food Operating System','A connected household system for pantry, recipes, gardens, preservation, planning, costs and alerts.','H',''],
['Poolzebo','Modular Product and Outdoor-Living System','A modular backyard pool-and-deck product system with kit and custom configurations.','P',''],
['Spaced Invaders','Browser Strategy and Defense Game','A settlement-defense simulation with intelligent UFO attacks, drone swarms and progression.','S',''],
['Stonefellow','Membership, Streaming and Entertainment Platform','Music, episodes, membership, merchandise and direct fan access in one entertainment platform.','S',''],
] as $project):?>
<article class="portfolio-admin-card">
<div class="portfolio-admin-cover">
<div class="portfolio-admin-placeholder"><span><?=htmlspecialchars($project[3])?></span></div>
<div class="portfolio-admin-badges">
<span class="status status-active">Active</span>
<?php if($project[4]):?><span class="portfolio-featured-badge">Featured</span><?php endif;?>
</div>
</div>
<div class="portfolio-admin-copy">
<span><?=htmlspecialchars($project[1])?> · 2026</span>
<h2><?=htmlspecialchars($project[0])?></h2>
<p><?=htmlspecialchars($project[2])?></p>
<div class="portfolio-admin-meta"><span>North Mountain Media</span><span>Public</span></div>
</div>
<footer>
<a class="button button-small button-primary" href="#">Manage</a>
<a class="button button-small" href="#">Preview</a>
</footer>
</article>
<?php endforeach;?>
</div>
</div>
</main>
</div>
</body>
</html>

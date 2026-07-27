<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$projects = [
    ['Microgifter',18,11,6,4,3,'Jul 26, 2026 8:12 PM'],
    ['Gruber Procurement Intelligence Platform',14,9,5,3,2,'Jul 26, 2026 7:44 PM'],
    ['Homestead',10,7,3,2,1,'Jul 26, 2026 6:10 PM'],
    ['Poolzebo',8,6,4,2,1,'Jul 26, 2026 5:30 PM'],
    ['Spaced Invaders',6,5,2,1,0,'Jul 26, 2026 4:41 PM'],
];

$trend = [
    [4,3,0],[5,4,1],[3,5,0],[7,6,1],[6,8,2],[8,7,1],[9,11,3],
    [6,8,1],[10,12,2],[11,10,2],[8,13,3],[12,15,3],[14,17,4],[13,18,4],
];
$max = 18;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Visitor Intelligence Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar portal-sidebar-admin">
<div class="portal-brand"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></div>
<nav class="portal-nav portal-nav-admin">
<section class="portal-nav-group">
<button class="portal-nav-group-toggle" type="button"><span>Operations</span><span>⌃</span></button>
<div class="portal-nav-group-links">
<a href="#">Dashboard</a>
<a class="active" href="#">Visitor Intelligence</a>
<a href="#">Call Center</a>
<a href="#">Communications</a>
<a href="#">Notifications</a>
</div>
</section>
<section class="portal-nav-group">
<button class="portal-nav-group-toggle" type="button"><span>Relationships</span><span>⌃</span></button>
<div class="portal-nav-group-links"><a href="#">Clients</a><a href="#">CRM</a><a href="#">Leads</a></div>
</section>
<section class="portal-nav-group">
<button class="portal-nav-group-toggle" type="button"><span>Work</span><span>⌃</span></button>
<div class="portal-nav-group-links"><a href="#">Portfolio</a><a href="#">Client Projects</a><a href="#">Knowledge Base</a></div>
</section>
</nav>
</aside>

<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block"><span>North Mountain Media</span><h1>Visitor Intelligence</h1></div>
<div class="portal-header-user">
<a class="portal-top-action" href="#">Call Center</a>
<a class="portal-user-info" href="#"><span class="portal-user-avatar"><img src="assets/images/david-evans-profile.jpg" alt=""></span><span class="portal-user-copy"><strong>David Evans</strong><small>Administrator</small></span></a>
</div>
</header>

<div class="portal-content">
<div class="page-actions visitor-analytics-actions">
<div class="visitor-range-switch">
<a class="button" href="#">7 days</a>
<a class="button button-primary" href="#">30 days</a>
<a class="button" href="#">90 days</a>
<a class="button" href="#">1 year</a>
</div>
<span class="spacer"></span>
<a class="button" href="#">Open CRM</a>
<a class="button" href="#">Open Portfolio</a>
</div>

<div class="stats-grid visitor-analytics-stats">
<article class="stat-card"><span>Unique visitors</span><strong>42</strong><small>7 identified contacts</small></article>
<article class="stat-card"><span>Sessions</span><strong>61</strong><small>214 recorded actions</small></article>
<article class="stat-card"><span>Portfolio views</span><strong>76</strong><small>21 project-site clicks</small></article>
<article class="stat-card"><span>Chat prompts</span><strong>19</strong><small>Resume and portfolio questions</small></article>
<article class="stat-card"><span>Resume activity</span><strong>23</strong><small>6 downloads</small></article>
<article class="stat-card"><span>Voice contacts</span><strong>8</strong><small>Calls, callbacks, and voicemail</small></article>
<article class="stat-card"><span>Contact forms</span><strong>5</strong><small>CRM-attributed submissions</small></article>
<article class="stat-card"><span>Conversion rate</span><strong>19.0%</strong><small>8 identified contact actions</small></article>
</div>

<section class="panel visitor-trend-panel">
<header class="panel-header">
<div><span>First-party activity</span><h2>14-day engagement trend</h2></div>
<div class="visitor-trend-legend"><span><i class="visitor-legend-visitors"></i>Visitors</span><span><i class="visitor-legend-portfolio"></i>Portfolio views</span><span><i class="visitor-legend-conversions"></i>Conversions</span></div>
</header>
<div class="visitor-trend-chart">
<?php foreach($trend as $index=>$day):?>
<div class="visitor-trend-day">
<div class="visitor-trend-bars">
<i class="visitor-trend-visitors" style="height:<?=($day[0]/$max)*100?>%"></i>
<i class="visitor-trend-portfolio" style="height:<?=($day[1]/$max)*100?>%"></i>
<i class="visitor-trend-conversions" style="height:<?=max(2,($day[2]/$max)*100)?>%"></i>
</div>
<span>Jul <?=$index+13?></span>
<small><?=$day[0]?> / <?=$day[1]?> / <?=$day[2]?></small>
</div>
<?php endforeach;?>
</div>
</section>

<section class="panel visitor-portfolio-performance">
<header class="panel-header"><div><span>Portfolio attribution</span><h2>Project performance</h2></div><a href="#">Manage portfolio</a></header>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Project</th><th>Views</th><th>Visitors</th><th>Engagement</th><th>Intent</th><th>Conversions</th><th>Last activity</th></tr></thead>
<tbody>
<?php foreach($projects as $project):?>
<tr>
<td><a href="#"><?=htmlspecialchars($project[0])?></a><br><small>Active</small></td>
<td><strong><?=$project[1]?></strong></td>
<td><?=$project[2]?></td>
<td><?=$project[3]?> gallery<br><small>06:18 active</small></td>
<td><?=$project[4]?> project clicks<br><small>2 chats</small></td>
<td><strong><?=$project[5]?></strong></td>
<td><?=$project[6]?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</section>

<div class="dashboard-grid visitor-intelligence-grid">
<section class="panel visitor-session-panel">
<header class="panel-header"><div><span>Known and anonymous traffic</span><h2>Recent visitors</h2></div></header>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Visitor</th><th>Latest session</th><th>Last project</th><th>Activity</th><th>Device</th></tr></thead>
<tbody>
<tr class="is-selected"><td><a href="#">Dave Evans</a><br><small>contact@example.com</small></td><td>Jul 26, 2026 8:12 PM<br><small>/index.php?portfolio=microgifter</small></td><td>Microgifter</td><td>4 pages<br><small>13 actions · 04:18</small></td><td>Desktop<br><small>Windows · LinkedIn</small></td></tr>
<tr><td><a href="#">Anonymous visitor #31</a><br><small>First-party anonymous profile</small></td><td>Jul 26, 2026 7:46 PM<br><small>/index.php</small></td><td>Gruber</td><td>3 pages<br><small>8 actions · 02:44</small></td><td>Mobile<br><small>iOS · Direct</small></td></tr>
<tr><td><a href="#">Anonymous visitor #28</a><br><small>First-party anonymous profile</small></td><td>Jul 26, 2026 6:55 PM<br><small>/call-dave.php</small></td><td>Poolzebo</td><td>2 pages<br><small>6 actions · 03:12</small></td><td>Desktop<br><small>macOS · Google</small></td></tr>
</tbody>
</table>
</div>
</section>

<section class="stack visitor-intelligence-side">
<section class="panel">
<header class="panel-header"><div><span>Acquisition</span><h2>Top referrers</h2></div></header>
<div class="visitor-referrer-list">
<article><div><strong>Direct / internal</strong><small>18 visitors</small></div><span>25 sessions</span></article>
<article><div><strong>linkedin.com</strong><small>9 visitors</small></div><span>12 sessions</span></article>
<article><div><strong>google.com</strong><small>7 visitors</small></div><span>10 sessions</span></article>
</div>
</section>
<section class="panel visitor-homeserver-card">
<header class="panel-header"><div><span>Integration readiness</span><h2>Microgifter HomeServer</h2></div><span class="status status-planning">Prepared</span></header>
<div class="panel-body"><p>Stable event UUIDs, CRM attribution, timestamps, and export-state fields are ready for the upcoming private HomeServer connection.</p><div class="visitor-homeserver-count"><strong>214</strong><span>events waiting for a future secure export worker</span></div><p class="visitor-homeserver-note">No remote connection or data export is enabled.</p></div>
</section>
</section>
</div>

<section class="panel visitor-detail-panel">
<header class="panel-header"><div><span>Visitor timeline</span><h2>Dave Evans</h2></div><div class="visitor-detail-actions"><a class="button button-small" href="#">Open CRM contact</a><a class="button button-small" href="#">Close timeline</a></div></header>
<div class="visitor-detail-summary">
<article><span>First seen</span><strong>Jul 25, 2026</strong></article><article><span>Last seen</span><strong>Jul 26, 2026</strong></article><article><span>Sessions</span><strong>3</strong></article><article><span>Events</span><strong>24</strong></article><article><span>Active time</span><strong>12:44</strong></article><article><span>First source</span><strong>linkedin.com</strong></article>
</div>
<div class="visitor-event-timeline">
<article class="visitor-event-item"><div class="visitor-event-marker"></div><div><header><strong>Contact form submitted</strong><time>Jul 26, 2026 8:12 PM</time></header><p>Microgifter implementation inquiry <span>· Microgifter</span></p><small>/index.php?portfolio=microgifter · Event 9d4d2f11</small></div></article>
<article class="visitor-event-item"><div class="visitor-event-marker"></div><div><header><strong>Chat prompt submitted</strong><time>Jul 26, 2026 8:09 PM</time></header><p>Portfolio chat prompt <span>· Microgifter</span></p><blockquote>How would Dave build a merchant CRM like this for my company?</blockquote><small>/index.php?portfolio=microgifter · Event 3b7fd021</small></div></article>
<article class="visitor-event-item"><div class="visitor-event-marker"></div><div><header><strong>Portfolio viewed</strong><time>Jul 26, 2026 8:06 PM</time></header><p>Microgifter</p><small>/index.php?portfolio=microgifter · Event 6f8caa14</small></div></article>
</div>
</section>
</div>
</main>
</div>
</body>
</html>

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
<title>Admin Data Assistant Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body" data-portal-role="admin">
<div class="portal-shell">
<aside class="portal-sidebar portal-sidebar-admin">
<div class="portal-brand">
<img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media">
</div>

<nav class="portal-nav portal-nav-admin">
<?php foreach ([
    'Operations' => ['Dashboard','Call Center','Communications','Notifications'],
    'Relationships' => ['Clients','CRM','Leads','Administrators'],
    'Work' => ['Portfolio','Client Projects','Files','Knowledge Base'],
    'System' => ['Settings','Account'],
] as $category => $items): ?>
<section class="portal-nav-group">
<button class="portal-nav-group-toggle" type="button" aria-expanded="true">
<span><?=htmlspecialchars($category)?></span><span>⌃</span>
</button>
<div class="portal-nav-group-links">
<?php foreach ($items as $item): ?>
<a class="<?=$item === 'Dashboard' ? 'active' : ''?>" href="#">
<?=htmlspecialchars($item)?>
</a>
<?php endforeach; ?>
</div>
</section>
<?php endforeach; ?>
</nav>

<div class="portal-sidebar-foot">
<a href="#">Public site</a>
<a href="#">Sign out</a>
</div>
</aside>

<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block">
<span>North Mountain Media</span>
<h1>Dashboard</h1>
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
<div class="stats-grid">
<article class="stat-card"><span>Active clients</span><strong>0</strong><small>Portal accounts</small></article>
<article class="stat-card"><span>Open projects</span><strong>0</strong><small>Current work</small></article>
<article class="stat-card"><span>CRM contacts</span><strong>2</strong><small>Active relationships</small></article>
<article class="stat-card"><span>Open opportunities</span><strong>0</strong><small>New inquiries</small></article>
<article class="stat-card"><span>Follow-ups due</span><strong>0</strong><small>CRM next actions</small></article>
<article class="stat-card"><span>Unread communications</span><strong>0</strong><small>Active calls</small></article>
<article class="stat-card"><span>Call Center queue</span><strong>0</strong><small>Public and client requests</small></article>
<article class="stat-card"><span>Notifications</span><strong>1</strong><small>Unread activity</small></article>
</div>

<section class="panel dashboard-history-panel">
<header class="panel-header dashboard-history-header">
<div><span>Recent contact activity</span><h2>Call &amp; Message History</h2></div>
<a href="#">Open Call Center</a>
</header>
<div class="dashboard-history-list">
<article class="dashboard-history-item" data-message-stage="new">
<header>
<div class="dashboard-history-identity">
<span class="dashboard-history-type">Voicemail</span>
<div><h3>Dave Evans</h3><p>Project follow-up <span>· VP3 Media</span></p></div>
</div>
<div class="dashboard-history-status">
<span class="status status-active">Voicemail</span>
<span class="status status-crm-message-new">New</span>
</div>
</header>
<div class="dashboard-history-meta">
<span><strong>Activity</strong>Jul 26, 2026 7:28 PM</span>
<span><strong>Source</strong>Public</span>
<span><strong>Duration</strong>00:24</span>
<span><strong>Answered</strong>—</span>
</div>
<p class="dashboard-history-message">Following up about the procurement platform review.</p>
<div class="dashboard-history-player">
<div><span>Voice message</span><small>Jul 26, 2026 7:28 PM · 00:24</small></div>
<audio controls preload="metadata"></audio>
</div>
<footer><div><span>contact@example.com</span></div><a class="button button-small" href="#">Open record</a></footer>
</article>

<article class="dashboard-history-item">
<header>
<div class="dashboard-history-identity">
<span class="dashboard-history-type">Live Call</span>
<div><h3>Website contact</h3><p>Browser audio call</p></div>
</div>
<div class="dashboard-history-status"><span class="status status-on_hold">Missed</span></div>
</header>
<div class="dashboard-history-meta">
<span><strong>Activity</strong>Jul 26, 2026 4:17 PM</span>
<span><strong>Source</strong>Public</span>
<span><strong>Duration</strong>00:00</span>
<span><strong>Rang</strong>Jul 26, 2026 4:17 PM</span>
</div>
<footer><div><span>No recording attached</span></div><a class="button button-small" href="#">Open record</a></footer>
</article>
</div>
</section>

<div class="dashboard-grid dashboard-recent-grid">
<section class="panel">
<header class="panel-header"><h2>Recent projects</h2><a href="#">View all</a></header>
<div class="empty-state">No current projects.</div>
</section>

<section class="panel">
<header class="panel-header"><h2>Recent CRM contacts</h2><a href="#">View CRM</a></header>
<div class="panel-body">
<div class="timeline">
<article class="timeline-item"><h3>Dave Evans</h3><p>VP3 Media</p><small>Lead · Today</small></article>
<article class="timeline-item"><h3>Website contact</h3><p>New inquiry</p><small>Lead · Today</small></article>
</div>
</div>
</section>
</div>
</div>

<section class="admin-assistant-footer">
<div class="admin-assistant-quick-menu" hidden></div>
<form class="admin-assistant-composer">
<button class="admin-assistant-plus" type="button">+</button>
<textarea rows="1" placeholder="Ask about calls, messages, CRM contacts, projects, clients, or notifications…"></textarea>
<button class="admin-assistant-submit" type="button">↑</button>
</form>
<small>Uses protected, predefined queries against the live portal database.</small>
</section>
</main>
</div>
</body>
</html>

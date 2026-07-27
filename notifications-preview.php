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
<title>Notifications Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar">
<div class="portal-brand"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></div>
<div class="portal-role"><span>Administration</span><strong>David Evans</strong><small>North Mountain Media</small></div>
<nav class="portal-nav">
<a href="#">Dashboard</a><a href="#">Call Center</a><a href="#">Clients</a><a href="#">CRM</a><a href="#">Projects</a><a href="#">Communications</a><a class="active" href="#">Notifications</a><a href="#">Knowledge Base</a><a href="#">Settings</a>
</nav>
</aside>
<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block"><span>North Mountain Media</span><h1>Notifications</h1></div>
<div class="portal-header-user">
<a class="portal-top-action" href="#">Call Center</a>
<div class="portal-notification-wrap">
<button class="portal-notification-button" type="button">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>
<span data-notification-count>7</span>
</button>
<section class="portal-notification-menu">
<header><div><span>Activity feed</span><strong>Notifications</strong></div><a href="#">View all</a></header>
<div>
<a class="unread" href="#"><span>☎</span><span><strong>Incoming public call from Jordan Michaels</strong><small>Jul 26, 2026 1:14 PM</small></span></a>
<a class="unread" href="#"><span>✉</span><span><strong>New voice message from Acme Hospitality</strong><small>Jul 26, 2026 12:45 PM</small></span></a>
<a href="#"><span>●</span><span><strong>New website contact from Renee Collins</strong><small>Jul 26, 2026 10:18 AM</small></span></a>
</div>
<footer><a href="#">Communications</a><a href="#">Open Call Center</a></footer>
</section>
</div>
<a class="portal-user-info" href="#"><span class="portal-user-avatar">D</span><span class="portal-user-copy"><strong>David Evans</strong><small>Administrator</small></span></a>
</div>
</header>
<div class="portal-content">
<section class="notification-page">
<header class="notification-page-header">
<div><span>Activity feed</span><h2>Notifications</h2><p>Calls, messages, contact requests, transcripts, projects, and system activity.</p></div>
<button class="button" type="button">Mark all read</button>
</header>
<div class="notification-feed">
<article class="notification-feed-item unread">
<span class="notification-feed-icon">☎</span>
<div class="notification-feed-copy"><header><strong>Incoming public call from Jordan Michaels</strong><time>Jul 26, 2026 1:14 PM</time></header><p>Microgifter hospitality partnership — The caller is waiting in the public browser call queue.</p><div><span class="status status-on_hold">Call</span><span class="notification-unread-label">Unread</span></div></div>
<div class="notification-feed-actions"><button class="button button-small">Open</button><button class="button button-small">Mark read</button></div>
</article>
<article class="notification-feed-item unread">
<span class="notification-feed-icon">✉</span>
<div class="notification-feed-copy"><header><strong>New voice message from Acme Hospitality</strong><time>Jul 26, 2026 12:45 PM</time></header><p>A client sent a new project voice message in Website launch review.</p><div><span class="status status-planning">Message</span><span class="notification-unread-label">Unread</span></div></div>
<div class="notification-feed-actions"><button class="button button-small">Open</button><button class="button button-small">Mark read</button></div>
</article>
<article class="notification-feed-item">
<span class="notification-feed-icon">●</span>
<div class="notification-feed-copy"><header><strong>New website contact from Renee Collins</strong><time>Jul 26, 2026 10:18 AM</time></header><p>Operations role discussion — the contact and opportunity were added to the CRM.</p><div><span class="status status-planning">Contact</span></div></div>
<div class="notification-feed-actions"><button class="button button-small">Open</button></div>
</article>
<article class="notification-feed-item">
<span class="notification-feed-icon">T</span>
<div class="notification-feed-copy"><header><strong>Reviewed transcript shared</strong><time>Jul 25, 2026 4:50 PM</time></header><p>An approved call transcript was shared with the Stonefellow client.</p><div><span class="status status-planning">Transcript</span></div></div>
<div class="notification-feed-actions"><button class="button button-small">Open</button></div>
</article>
</div>
</section>
</div>
</main>
</div>
</body>
</html>

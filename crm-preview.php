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
<title>CRM Messages Preview — North Mountain Media</title>
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
<button class="portal-nav-group-toggle" type="button"><span>Relationships</span><span>⌃</span></button>
<div class="portal-nav-group-links">
<a href="#">Clients</a>
<a class="active" href="#">CRM</a>
<a href="#">Leads</a>
</div>
</section>
</nav>
</aside>

<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block"><span>North Mountain Media</span><h1>CRM</h1></div>
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
<input placeholder="Search CRM contacts" style="min-height:40px;padding:8px 11px;border:1px solid #dfe5eb;border-radius:10px">
<select style="min-height:40px;padding:8px;border:1px solid #dfe5eb;border-radius:10px"><option>All lifecycle stages</option></select>
<button class="button">Filter CRM</button>
<span class="spacer"></span>
<button class="button button-primary">Add CRM Contact</button>
<a class="button" href="#">Raw inquiry archive</a>
</div>

<div class="dashboard-grid crm-workspace">
<section class="panel">
<header class="panel-header"><h2>2 CRM contacts</h2></header>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Contact</th><th>Opportunity</th><th>Calls</th><th>Stage</th><th>Follow-up</th></tr></thead>
<tbody>
<tr class="crm-contact-row">
<td><a href="#">Dave Evans</a><br><small>contact@example.com</small><br><small>VP3 Media</small></td>
<td>No opportunity<br><small>0 open / 0 total</small></td>
<td>
<strong>5 calls</strong><br>
<button class="crm-message-count is-open" type="button">
<span>1 voicemail · 1 messages</span><span>⌄</span>
</button>
<br><small>Jul 26, 2026 7:28 PM</small>
</td>
<td><span class="status status-lead">Lead</span></td>
<td>—</td>
</tr>
<tr class="crm-message-accordion-row">
<td colspan="5">
<div class="crm-message-accordion">
<div class="crm-message-grid">
<article class="crm-message-card" data-message-stage="listened">
<header>
<div><strong>Project follow-up</strong><small>Voicemail · Jul 26, 2026 7:28 PM · Voicemail</small></div>
<span class="status status-crm-message-listened">Listened</span>
</header>
<p class="crm-message-text">Following up about the procurement platform review.</p>
<div class="crm-message-player">
<div><span>Audio message</span><small>00:24</small></div>
<audio controls></audio>
</div>
<details class="crm-message-transcript">
<summary>Transcript</summary>
<p>I wanted to follow up and schedule time to review the next phase.</p>
</details>
<footer>
<label class="crm-message-stage-field">
<span>Message stage</span>
<select><option>New</option><option selected>Listened</option><option>Follow-up</option><option>Resolved</option><option>Archived</option></select>
</label>
<a class="button button-small" href="#">Open record</a>
</footer>
</article>

<article class="crm-message-card" data-message-stage="new">
<header>
<div><strong>Website message</strong><small>Message · Jul 26, 2026 5:10 PM · New</small></div>
<span class="status status-crm-message-new">New</span>
</header>
<p class="crm-message-text">Can you send the current implementation outline?</p>
<footer>
<label class="crm-message-stage-field">
<span>Message stage</span>
<select><option selected>New</option><option>Listened</option><option>Follow-up</option><option>Resolved</option><option>Archived</option></select>
</label>
<a class="button button-small" href="#">Open record</a>
</footer>
</article>
</div>
</div>
</td>
</tr>
</tbody>
</table>
</div>
</section>

<section class="panel"><div class="empty-state">Select a CRM contact to review the inquiry, opportunity, and activity history.</div></section>
</div>
</div>
</main>
</div>
</body>
</html>

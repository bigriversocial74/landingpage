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
<title>Call Center Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar">
<div class="portal-brand"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></div>
<nav class="portal-nav portal-nav-admin">
<a href="#">Dashboard</a>
<a class="active" href="#">Call Center</a>
<a href="#">Clients</a>
<a href="#">CRM</a>
<a href="#">Projects</a>
<a href="#">Communications</a>
<a href="#">Notifications</a>
<a href="#">Knowledge Base</a>
<a href="#">Settings</a>
</nav>
</aside>

<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block"><span>North Mountain Media</span><h1>Call Center</h1></div>
<div class="portal-header-user">
<a class="portal-top-action" href="#">Call Center</a>
<div class="portal-notification-wrap">
<button class="portal-notification-button" type="button">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>
<span data-notification-count>7</span>
</button>
</div>
<a class="portal-user-info" href="#"><span class="portal-user-avatar">D</span><span class="portal-user-copy"><strong>David Evans</strong><small>Administrator</small></span></a>
</div>
</header>

<div class="portal-content">
<section class="call-center-app">
<div class="call-center-metrics">
<article><span>Today</span><strong>8</strong><small>New call activity</small></article>
<article><span>Waiting</span><strong>4</strong><small>1 ringing now</small></article>
<article><span>Completed</span><strong>27</strong><small>Connected or resolved</small></article>
<article><span>Missed</span><strong>3</strong><small>Needs follow-up</small></article>
<article><span>Voicemails</span><strong>6</strong><small>Recorded messages</small></article>
<article><span>Messages</span><strong>9</strong><small>Written and callbacks</small></article>
<article><span>Avg. response</span><strong>01:42</strong><small>Request to response</small></article>
<article><span>Avg. duration</span><strong>12:18</strong><small>Connected calls</small></article>
</div>

<nav class="call-center-filters">
<div class="call-center-filter-links"><a class="active" href="#">All</a><a href="#">Ringing</a><a href="#">New</a><a href="#">Queued</a><a href="#">Scheduled</a><a href="#">Voicemail</a><a href="#">Missed</a><a href="#">Completed</a></div>
<button class="call-center-settings-button" type="button" aria-label="Call Center settings"><svg viewBox="0 0 24 24"><path d="M12 8.25A3.75 3.75 0 1 0 12 15.75 3.75 3.75 0 0 0 12 8.25Zm9 3.75-2.08-.72a7.3 7.3 0 0 0-.58-1.4l.96-1.98-1.2-1.2-1.98.96a7.3 7.3 0 0 0-1.4-.58L14 5h-4l-.72 2.08a7.3 7.3 0 0 0-1.4.58L5.9 6.7 4.7 7.9l.96 1.98a7.3 7.3 0 0 0-.58 1.4L3 12v1.7l2.08.72c.14.49.34.96.58 1.4L4.7 17.8 5.9 19l1.98-.96c.44.24.91.44 1.4.58L10 20.7h4l.72-2.08c.49-.14.96-.34 1.4-.58l1.98.96 1.2-1.2-.96-1.98c.24-.44.44-.91.58-1.4L21 13.7V12Z"/></svg></button>
</nav>

<section
class="call-center-settings-modal"
role="dialog"
aria-modal="true"
aria-labelledby="call-center-settings-title"
>
<button class="call-center-settings-backdrop" type="button" aria-label="Close settings"></button>
<div class="call-center-settings-dialog">
<header>
<div>
<span>Call Center</span>
<h2 id="call-center-settings-title">Settings</h2>
<p>Control public availability, ringing behavior, sound, and the voicemail greeting.</p>
</div>
<button class="call-center-settings-close" type="button">×</button>
</header>

<nav class="call-center-settings-tabs" role="tablist">
<button class="active" type="button" role="tab" aria-selected="true">Settings</button>
<button type="button" role="tab" aria-selected="false">Voicemail</button>
</nav>

<div class="call-center-settings-panels">
<section class="call-center-settings-pane active">
<form class="call-center-line-status">
<label>
<span>Public line</span>
<select><option>Available</option><option>Busy</option><option>Offline</option></select>
</label>

<label>
<span>Max rings</span>
<input type="number" value="6">
<small>Voicemail begins after approximately 6 seconds per ring.</small>
</label>

<label class="full">
<span>Status message</span>
<input value="Dave is accepting browser audio calls.">
</label>

<div class="call-center-setting-actions">
<button class="button button-primary" type="button">Save Call Center settings</button>
<button class="button" type="button">Call sounds enabled</button>
<a class="button" href="#">Open public line</a>
</div>
</form>
</section>
</div>
</div>
</section>

<div class="call-center-layout">
<section class="call-center-queue">
<header><div><span>Queue and history</span><h3>32 records</h3></div></header>
<div class="call-center-request-list">
<a class="call-center-request-card active status-ringing" href="#">
<span class="call-center-source">Public</span>
<div><strong>Jordan Michaels</strong><small>Microgifter partnership · Red Mesa Group</small><em>Jul 26, 2026 1:14 PM · Ringing</em></div>
<span class="status status-on_hold">Ringing</span>
</a>
<a class="call-center-request-card" href="#">
<span class="call-center-source">Client</span>
<div><strong>Acme Hospitality</strong><small>Homepage launch review</small><em>Jul 26, 2026 12:42 PM · Queued</em></div>
<span class="status status-planning">Queued</span>
</a>
<a class="call-center-request-card" href="#">
<span class="call-center-source">Public</span>
<div><strong>Renee Collins</strong><small>Operations role discussion</small><em>Jul 26, 2026 10:18 AM · Missed</em></div>
<span class="status status-on_hold">Missed</span>
</a>
<a class="call-center-request-card" href="#">
<span class="call-center-source">Client</span>
<div><strong>Stonefellow</strong><small>Streaming membership deployment</small><em>Jul 25, 2026 4:32 PM · Completed</em></div>
<span class="status status-active">Completed</span>
</a>
</div>
</section>

<section class="call-center-detail">
<header class="call-center-detail-header">
<div><span>Public Live Call</span><h3>Jordan Michaels</h3><p>Microgifter partnership</p></div>
<span class="status status-on_hold">Ringing</span>
</header>

<div class="call-center-contact-grid">
<article><span>Email</span><strong>jordan@example.com</strong></article>
<article><span>Phone</span><strong>(602) 555-0164</strong></article>
<article><span>Company</span><strong>Red Mesa Group</strong></article>
<article><span>CRM stage</span><strong>Qualified</strong></article>
</div>

<section class="call-center-message"><span>Caller message</span><p>I would like to discuss a hospitality partnership and how Microgifter could support multi-location customer engagement.</p></section>

<div class="call-center-time-grid">
<article><span>Requested</span><strong>Jul 26, 2026 1:14 PM</strong></article>
<article><span>Preferred</span><strong>Now</strong></article>
<article><span>First response</span><strong>—</strong></article>
<article><span>Answered</span><strong>—</strong></article>
<article><span>Ended</span><strong>—</strong></article>
<article><span>Duration</span><strong>00:00</strong></article>
<article><span>Contact attempts</span><strong>2</strong></article>
<article><span>Last contact</span><strong>Jul 23, 2026</strong></article>
<article><span>Disposition</span><strong>Unassigned</strong></article>
<article><span>Assigned to</span><strong>David Evans</strong></article>
</div>

<section class="call-center-contact-stats">
<header><span>Contact call management</span><h3>Relationship history</h3></header>
<div>
<article><span>Requests</span><strong>4</strong></article>
<article><span>Calls</span><strong>3</strong></article>
<article><span>Completed</span><strong>2</strong></article>
<article><span>Missed</span><strong>1</strong></article>
<article><span>Declined</span><strong>0</strong></article>
<article><span>Total talk time</span><strong>34:12</strong></article>
</div>
</section>

<div class="call-center-live-actions">
<button class="button button-primary" type="button">Answer public call</button>
<button class="button button-danger" type="button">Decline</button>
</div>

<form class="call-center-management-form">
<div class="form-grid">
<label class="field"><span>Status</span><select><option>Ringing</option></select></label>
<label class="field"><span>Disposition</span><select><option>Unassigned</option></select></label>
<label class="field"><span>Priority</span><select><option>High</option></select></label>
<label class="field"><span>Assigned administrator</span><select><option>David Evans</option></select></label>
<label class="field full"><span>Admin notes</span><textarea>Partnership lead. Review hospitality rollout scope and existing CRM requirements.</textarea></label>
<label class="field full"><span>Call transcript / summary</span><textarea></textarea></label>
</div>
<div class="form-footer"><button class="button button-primary" type="button">Save call record</button><button class="button" type="button">Log contact attempt</button><button class="button" type="button">Open CRM contact</button></div>
</form>

<section class="call-center-event-feed">
<header><span>Audit trail</span><h3>Call events</h3></header>
<article><span></span><div><strong>Public Call Ringing</strong><p>The website visitor started a browser audio call.</p><small>System · Jul 26, 2026 1:14 PM</small></div></article>
<article><span></span><div><strong>Contact Attempt</strong><p>Follow-up email sent after earlier conversation.</p><small>David Evans · Jul 23, 2026 3:40 PM</small></div></article>
</section>
</section>
</div>
</section>
</div>
</main>
</div>
</body>
</html>

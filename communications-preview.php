<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(self)');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Communications Center Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar">
<div class="portal-brand"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></div>
<div class="portal-role"><span>Administration</span><strong>David Evans</strong><small>North Mountain Media</small></div>
<nav class="portal-nav">
<a href="#">Dashboard</a><a href="#">Clients</a><a href="#">Administrators</a><a href="#">CRM</a><a href="#">Projects</a><a href="#">Leads</a><a class="active" href="#">Communications</a><a href="#">Files</a><a href="#">Knowledge Base</a><a href="#">Settings</a><a href="#">Account</a>
</nav>
</aside>
<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block"><span>North Mountain Media</span><h1>Communications</h1></div>
<div class="portal-header-user"><a class="portal-top-action" href="#">Communications</a><a class="portal-user-info" href="#"><span class="portal-user-avatar">D</span><span class="portal-user-copy"><strong>David Evans</strong><small>Administrator</small></span></a></div>
</header>
<div class="portal-content">
<section class="communications-app">
<aside class="communications-sidebar">
<div class="communications-sidebar-head"><div><span>Secure workspace</span><h2>Conversations</h2></div><button class="button button-small">New</button></div>
<div class="communications-thread-list">
<a class="communications-thread active" href="#"><span class="communications-thread-avatar">M</span><span class="communications-thread-copy"><strong>Microgifter strategy call</strong><small>Merchant Partner · Microgifter</small><em>I uploaded the latest campaign notes and sent a voice message.</em></span><span class="communications-unread">2</span></a>
<a class="communications-thread" href="#"><span class="communications-thread-avatar">A</span><span class="communications-thread-copy"><strong>Website project review</strong><small>Acme Hospitality · Website Redesign</small><em>Thanks, the revised homepage looks much better.</em></span></a>
<a class="communications-thread" href="#"><span class="communications-thread-avatar">S</span><span class="communications-thread-copy"><strong>Stonefellow membership</strong><small>Stonefellow · Streaming Platform</small><em>Missed audio call.</em></span><span class="communications-unread">1</span></a>
</div>
</aside>

<main class="communications-main">
<header class="communications-header">
<div><span>Merchant Partner</span><h2>Microgifter strategy call</h2><small>Microgifter · Waiting Admin · High priority</small></div>
<div class="communications-header-actions"><button class="button button-primary">Audio call</button><button class="button">Settings</button></div>
</header>

<section class="communications-timeline">
<article class="communication-message">
<header><strong>Merchant Partner</strong><time>Jul 26, 2026 9:18 AM</time></header>
<div class="communication-message-body">I uploaded the latest campaign notes. Can we review the merchant CRM lifecycle and the next deployment steps?</div>
<footer><span>Text</span></footer>
</article>

<article class="communication-message own">
<header><strong>David Evans</strong><time>Jul 26, 2026 9:21 AM</time></header>
<div class="communication-message-body">Yes. I reviewed the notes and added the CRM follow-up items. Send the voice note when ready and we can continue here.</div>
<footer><span>Text</span></footer>
</article>

<article class="communication-message">
<header><strong>Merchant Partner</strong><time>Jul 26, 2026 9:24 AM</time></header>
<div class="communication-message-body">Voice message</div>
<audio controls></audio>
<details class="communication-transcript"><summary>Reviewed transcript</summary><div>The next priority is connecting campaign activity, customer claims, and follow-up tasks to the merchant CRM record.</div></details>
<footer><span>Voice</span><a href="#">Download</a></footer>
</article>

<article class="communication-message own type-call_event">
<header><strong>David Evans</strong><time>Jul 26, 2026 9:30 AM</time></header>
<div class="communication-message-body">Audio call ended after 00:18:42.</div>
<footer><span>Call Event</span></footer>
</article>

<article class="communication-message own">
<header><strong>David Evans</strong><time>Jul 26, 2026 9:31 AM</time></header>
<div class="communication-message-body">Consented call recording</div>
<audio controls></audio>
<footer><span>Call Recording</span><a href="#">Download</a></footer>
</article>
</section>

<section class="communications-composer">
<form>
<textarea placeholder="Write a message…"></textarea>
<label class="communications-internal-note"><input type="checkbox"><span>Internal CRM note</span></label>
<div class="communications-composer-actions"><div><button class="communication-icon-button" type="button">＋</button><button class="communication-icon-button" type="button">●</button></div><button class="button button-primary" type="button">Send</button></div>
</form>
</section>

<section class="communications-transcripts">
<header><div><span>Private review</span><h2>Transcripts</h2></div><small>2 records</small></header>
<div class="communications-transcript-list">
<form class="communications-transcript-review">
<header><div><strong>Call Recording · microgifter-call.webm</strong><small>Review · Jul 26, 2026 9:33 AM</small></div><span class="status status-planning">Review</span></header>
<label class="field"><span>Raw transcript or notes</span><textarea>Automatic or manually entered source transcript appears here.</textarea></label>
<label class="field"><span>Reviewed transcript</span><textarea>Dave reviews names, project details, commitments, and follow-up items before sharing.</textarea></label>
<label class="checkbox-row"><input type="checkbox" checked><span>Share the approved transcript with the client</span></label>
<div class="form-footer"><button class="button" type="button">Save review</button><button class="button button-primary" type="button">Approve transcript</button><button class="button" type="button">Create Knowledge draft</button></div>
</form>
</div>
</section>
</main>

<section class="communication-active-call">
<div class="communication-active-call-person"><span class="communication-call-pulse"></span><div><small>Recording · Connected</small><strong>Merchant Partner</strong></div></div>
<time>12:46</time>
<div class="communication-active-call-actions"><button>Mute</button><button>Recording</button><button class="end">End call</button></div>
</section>
</section>
</div>
</main>
</div>
</body>
</html>

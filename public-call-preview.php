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
<title>Call Us Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/call-dave.css?v=20260727-site-controls-landing-v60">
</head>
<body class="public-call-body">
<header class="public-main-header">
<div class="public-main-header-inner">
<a href="#" class="public-main-brand">
<img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media">
</a>

<nav class="public-main-nav" aria-label="Main navigation">
<a href="#">Portfolio</a>
<div class="public-account">
<button class="public-account-toggle" type="button">
<img src="assets/images/david-evans-profile.jpg" alt="">
<span><strong>David Evans</strong><small>Administrator</small></span>
<em>⌄</em>
</button>
</div>
</nav>
</div>
</header>

<main class="public-call-main">
<section class="public-call-intro">
<div class="public-call-status status-available"><span></span>Available</div>
<span class="public-call-kicker">Browser audio and voicemail</span>
<h1>Call Us</h1>

<div class="public-call-profile-message">
<img src="assets/images/david-evans-profile.jpg" alt="David Evans profile photo">
<p>Dave is accepting browser audio calls.</p>
</div>

<div class="public-call-direct">
<a href="#">Email Dave</a>
</div>
</section>

<section class="public-call-card">
<nav class="public-call-tabs">
<button class="active" type="button">Call Us</button>
<button type="button">Leave voicemail</button>
</nav>

<form>
<div class="public-call-form-grid">
<label><span>Name</span><input value="Jordan Michaels"></label>
<label><span>Email <em>optional</em></span><input value="jordan@example.com"></label>
<label><span>Phone <em>optional</em></span><input value="(602) 555-0164"></label>
<label><span>Company <em>optional</em></span><input value="Red Mesa Group"></label>
<label class="full"><span>Call topic <em>optional</em></span><input value="Microgifter hospitality partnership"></label>
</div>

<label class="public-call-consent">
<input type="checkbox" checked>
<span>I understand that the browser will request microphone access for a live audio call. This call is not recorded automatically.</span>
</label>

<div class="public-call-actions">
<button class="public-call-primary" type="button">Start browser call</button>
</div>
</form>
</section>
</main>

<footer class="public-call-footer">
<span>North Mountain Media · Phoenix, Arizona</span>
</footer>
</body>
</html>

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
<title>Account Settings Preview — North Mountain Media</title>
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
<button class="portal-nav-group-toggle" type="button"><span>System</span><span>⌃</span></button>
<div class="portal-nav-group-links">
<a href="#">Settings</a>
<a class="active" href="#">Account</a>
</div>
</section>
</nav>
</aside>

<main class="portal-main">
<header class="portal-topbar">
<div class="portal-title-block">
<span>North Mountain Media</span>
<h1>Account</h1>
</div>
<div class="portal-header-user">
<a class="portal-top-action" href="#">Call Center</a>
<a class="portal-user-info" href="#">
<span class="portal-user-avatar"><img src="assets/images/david-evans-profile.jpg" alt=""></span>
<span class="portal-user-copy">
<strong>David Evans</strong>
<small>Administrator</small>
</span>
</a>
</div>
</header>

<div class="portal-content">
<div class="account-settings-grid">
<form class="form-panel account-profile-form">
<header class="account-form-header">
<img src="assets/images/david-evans-profile.jpg" alt="David Evans profile photo">
<div>
<span>Account profile</span>
<h2>Profile and contact settings</h2>
<p>This information powers the logged-in account menu, public contact details, sidebar profile, and Call Us page.</p>
</div>
</header>

<div class="form-grid">
<label class="field"><span>Display name</span><input value="David Evans"></label>
<label class="field"><span>Email</span><input value="account@example.com"></label>
<label class="field"><span>Phone</span><input value="Account phone"></label>
<label class="field"><span>Company</span><input value="North Mountain Media"></label>
<label class="field full"><span>Profile photo</span><input type="file"><small>JPG, PNG, WebP, or GIF. Maximum 5 MB.</small></label>
</div>
<div class="form-footer"><button class="button button-primary" type="button">Save account settings</button></div>
</form>

<form class="form-panel account-password-form">
<header class="account-password-header">
<span>Security</span>
<h2>Reset password</h2>
<p>Confirm the current password, then choose a new password with at least 12 characters.</p>
</header>
<div class="form-grid">
<label class="field full"><span>Current password</span><input type="password" value="password"></label>
<label class="field"><span>New password</span><input type="password" value="password1234"></label>
<label class="field"><span>Confirm password</span><input type="password" value="password1234"></label>
</div>
<div class="form-footer"><button class="button button-primary" type="button">Reset password</button></div>
</form>
</div>
</div>
</main>
</div>
</body>
</html>

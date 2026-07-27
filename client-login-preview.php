<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Client Login Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="auth-body">
<main class="auth-shell"><section class="auth-card">
<a class="auth-logo" href="index-preview.php"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></a>
<div class="auth-heading"><span>Client access</span><h1>Client login</h1></div>
<form>
<label class="field"><span>Email address</span><input type="email" placeholder="client@example.com"></label>
<label class="field"><span>Password</span><input type="password" placeholder="••••••••••••"></label>
<button class="button button-primary" type="button">Sign in</button>
</form>
<p class="auth-help">Client accounts are created by North Mountain Media. Contact Dave if you need access.</p>
<p class="auth-switch"><a href="admin-preview.php">Preview the administrator dashboard</a></p>
<p class="auth-return"><a href="index-preview.php">Return to the public portfolio</a></p>
</section></main>
</body></html>

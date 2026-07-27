<?php
declare(strict_types=1);

require_once __DIR__ . '/portal/public-sidebar.php';

$previewSidebarContext = [
    'profile_name' => 'David Evans',
    'profile_image' => 'assets/images/david-evans-profile.jpg',
    'projects' => [
        ['slug' => 'gruber', 'title' => 'Gruber Procurement Intelligence Platform'],
        ['slug' => 'microgifter', 'title' => 'Microgifter'],
        ['slug' => 'homestead', 'title' => 'Homestead'],
        ['slug' => 'poolzebo', 'title' => 'Poolzebo'],
        ['slug' => 'spaced-invaders', 'title' => 'Spaced Invaders'],
        ['slug' => 'stonefellow', 'title' => 'Stonefellow'],
        ['slug' => 'roger-huston', 'title' => 'Roger Huston'],
    ],
];
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Blog Article Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60">
<link rel="stylesheet" href="assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60">
<link rel="stylesheet" href="assets/css/blog.css?v=20260727-site-controls-landing-v60">
</head>
<body class="blog-body">
<div class="music-public-shell">
<?php
nmm_render_public_sidebar($previewSidebarContext);
?>

<section class="music-public-workspace">
<header class="workspace-header">
<button aria-controls="workspaceSidebar" aria-expanded="false" aria-label="Open sidebar" class="sidebar-toggle" data-sidebar-open type="button"><span></span><span></span><span></span></button><div class="workspace-header-actions"><a class="workspace-header-action primary" href="#">Client Login</a><a class="workspace-header-action" href="#">Admin Login</a></div></header>
<main class="blog-post-main">
<nav class="blog-breadcrumbs"><a href="#">Blog</a><span>›</span><a href="#">Product Systems</a><span>›</span><strong>Turning a resume into a publishing system</strong></nav>
<header class="blog-post-header">
<span>Product Systems</span>
<h1>Turning a resume into a publishing system</h1>
<p>A structured resume can work like a focused content system instead of a document that must be manually rewritten every time something changes.</p>
<div class="blog-meta"><span>July 26, 2026</span><span>David Evans</span></div>
</header>
<figure class="blog-post-cover"><div class="blog-featured-placeholder"><span>R</span></div></figure>
<article class="blog-post-article">
<p>A traditional resume is usually maintained as one large block of content. That works until the same experience needs to appear in a public site, a recruiter view, a project proposal, or an AI-assisted conversation.</p>
<h2>Resume entries become reusable records</h2>
<p>Each role, skill group, education item, project, award, and profile summary can become a structured post with its own publishing status and display order.</p>
<h3>What this improves</h3>
<ul><li>Update one role without editing the entire page.</li><li>Control which entries are public.</li><li>Reuse the same records in chat, search, and future HomeServer connections.</li></ul>
</article>
<div class="blog-tags"><span># resume</span><span># publishing</span><span># structured-data</span></div>
<footer class="blog-post-footer"><a href="#">← Back to Blog</a></footer>
</main>
</section>
</div>
<script src="assets/js/public-sidebar.js?v=20260727-site-controls-landing-v60"></script>
</body>
</html>

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
<title>Blog Preview — North Mountain Media</title>
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
<button aria-controls="workspaceSidebar" aria-expanded="false" aria-label="Open sidebar" class="sidebar-toggle" data-sidebar-open type="button"><span></span><span></span><span></span></button>
<div class="workspace-header-actions">
<a class="workspace-header-action primary" href="#">Client Login</a>
<a class="workspace-header-action" href="#">Admin Login</a>
</div>
</header>

<main class="blog-canvas">
<header class="blog-archive-header">
<div>
<span>North Mountain Media Journal</span>
<h1>Ideas, systems, and things being built.</h1>
<p>Articles about product strategy, connected business systems, ecommerce, CRM, operational design, music platforms, and independent software development.</p>
</div>
<form class="blog-filter-form">
<input type="search" placeholder="Search articles">
<button type="button">Search</button>
</form>
</header>

<nav class="blog-category-nav">
<a class="active" href="#">All posts</a>
<a href="#">Product Systems · 4</a>
<a href="#">Commerce · 3</a>
<a href="#">Music · 2</a>
</nav>

<article class="blog-featured">
<div class="blog-featured-media">
<a class="blog-featured-placeholder" href="#"><span>M</span></a>
</div>
<div class="blog-featured-copy">
<span>Product Systems</span>
<h2><a href="#">Why connected workflows matter more than isolated features</a></h2>
<p>How operational software becomes more useful when records, actions, communication, and reporting belong to one visible lifecycle.</p>
<div class="blog-meta"><span>July 26, 2026</span><span>David Evans</span></div>
<a class="blog-read-link" href="#">Read article →</a>
</div>
</article>

<section class="blog-grid">
<?php foreach([
['Building a CRM around the customer lifecycle','Commerce','A practical look at connecting inquiries, calls, messages, opportunities, and follow-up.'],
['Turning a resume into a publishing system','Product Systems','Why structured resume posts are easier to maintain, search, reuse, and present.'],
['Designing independent music platforms','Music','Albums, playlists, protected streaming, analytics, and direct audience relationships.'],
] as $item):?>
<article class="blog-card">
<div class="blog-card-media"><a class="blog-card-placeholder" href="#"><span><?=htmlspecialchars(substr($item[0],0,1))?></span></a></div>
<div class="blog-card-copy">
<span><?=htmlspecialchars($item[1])?></span>
<h2><a href="#"><?=htmlspecialchars($item[0])?></a></h2>
<p><?=htmlspecialchars($item[2])?></p>
<div class="blog-meta"><span>July 2026</span><span>David Evans</span></div>
</div>
</article>
<?php endforeach;?>
</section>
</main>
</section>
</div>
<script src="assets/js/public-sidebar.js?v=20260727-site-controls-landing-v60"></script>
</body>
</html>

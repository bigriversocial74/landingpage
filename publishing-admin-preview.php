<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Publishing Administration Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar portal-sidebar-admin">
<div class="portal-brand"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></div>
<nav class="portal-nav portal-nav-admin">
<section class="portal-nav-group is-current">
<button class="portal-nav-group-toggle" type="button"><span>Work</span><span>⌃</span></button>
<div class="portal-nav-group-links">
<a href="#">Portfolio</a>
<a class="active" href="#">Blog</a>
<a href="#">Resume Posts</a>
<a href="#">Client Projects</a>
<a href="#">Files</a>
<a href="#">Knowledge Base</a>
</div>
</section>
</nav>
</aside>
<main class="portal-main">
<header class="portal-topbar"><div class="portal-title-block"><span>North Mountain Media</span><h1>Blog</h1></div></header>
<div class="portal-content">
<div class="stats-grid publishing-stats">
<article class="stat-card"><span>Blog posts</span><strong>7</strong><small>All publishing records</small></article>
<article class="stat-card"><span>Published</span><strong>4</strong><small>Visible to public visitors</small></article>
<article class="stat-card"><span>Drafts</span><strong>3</strong><small>Administrator-only work</small></article>
</div>
<div class="page-actions"><a class="button button-primary" href="#">Create blog post</a><a class="button" href="#">Open public blog</a></div>
<div class="publishing-admin-grid">
<?php foreach([
['Why connected workflows matter','Product Systems','Published'],
['Building a CRM around the lifecycle','Commerce','Draft'],
['Designing independent music platforms','Music','Published'],
] as $post):?>
<article class="publishing-admin-card">
<div class="publishing-admin-cover"><div class="publishing-cover-placeholder"><span><?=htmlspecialchars(substr($post[0],0,1))?></span></div><div class="publishing-admin-badges"><span class="status"><?=htmlspecialchars($post[2])?></span></div></div>
<div class="publishing-admin-copy"><span><?=htmlspecialchars($post[1])?></span><h2><?=htmlspecialchars($post[0])?></h2><p>Manage the title, excerpt, article body, cover, gallery, category, tags, SEO, status, and publication date.</p><div class="publishing-admin-meta"><span>David Evans</span></div></div>
<footer><a class="button button-small button-primary" href="#">Manage</a><a class="button button-small" href="#">Preview</a></footer>
</article>
<?php endforeach;?>
</div>
</div>
</main>
</div>
</body>
</html>

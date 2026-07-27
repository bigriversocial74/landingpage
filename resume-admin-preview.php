<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Resume Posts Administration Preview — North Mountain Media</title>
<link rel="stylesheet" href="assets/css/portal.css?v=20260727-site-controls-landing-v60">
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar portal-sidebar-admin">
<div class="portal-brand"><img src="assets/images/north-mountain-media-logo.png" alt="North Mountain Media"></div>
<nav class="portal-nav portal-nav-admin"><section class="portal-nav-group is-current"><button class="portal-nav-group-toggle" type="button"><span>Work</span><span>⌃</span></button><div class="portal-nav-group-links"><a href="#">Portfolio</a><a href="#">Blog</a><a class="active" href="#">Resume Posts</a><a href="#">Client Projects</a><a href="#">Files</a><a href="#">Knowledge Base</a></div></section></nav>
</aside>
<main class="portal-main">
<header class="portal-topbar"><div class="portal-title-block"><span>North Mountain Media</span><h1>Resume Posts</h1></div></header>
<div class="portal-content">
<div class="stats-grid publishing-stats">
<article class="stat-card"><span>Resume posts</span><strong>11</strong><small>All structured entries</small></article>
<article class="stat-card"><span>Published</span><strong>11</strong><small>Visible on the public resume</small></article>
<article class="stat-card"><span>Main column</span><strong>6</strong><small>Profile and career entries</small></article>
<article class="stat-card"><span>Sidebar</span><strong>5</strong><small>Skills, strengths, and education</small></article>
</div>
<div class="page-actions"><a class="button button-primary" href="#">Create resume post</a><a class="button" href="#">Open public resume</a></div>
<div class="resume-admin-list">
<?php foreach([
[1,'Profile / Hero','David Evans','','Published'],
[10,'Experience','Founder & Systems / Product Operations Lead','VP3 Media Corp. / Microgifter','Published'],
[20,'Experience','eCommerce Listing Specialist','Kodi Distributing','Published'],
[30,'Experience','Client Services Manager','Timeshare Attorneys of America','Published'],
[20,'Skill Group','Core competencies','','Published'],
] as $post):?>
<article class="resume-admin-row">
<div class="resume-admin-order"><strong><?=$post[0]?></strong><span><?=$post[1]==='Skill Group'?'sidebar':'main'?></span></div>
<div class="resume-admin-entry"><span><?=htmlspecialchars($post[1])?></span><h2><?=htmlspecialchars($post[2])?><?php if($post[3]):?><small> — <?=htmlspecialchars($post[3])?></small><?php endif;?></h2><p>Structured, editable resume content with status, order, dates, summary, achievements, skills, and optional links.</p><div><span class="status"><?=htmlspecialchars($post[4])?></span></div></div>
<footer><a class="button button-small button-primary" href="#">Manage</a><a class="button button-small" href="#">Preview</a></footer>
</article>
<?php endforeach;?>
</div>
</div>
</main>
</div>
</body>
</html>

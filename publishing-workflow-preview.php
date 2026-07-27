<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta
    name="build-version"
    content="20260727-site-controls-landing-v60"
>
<title>Publishing Workflow Preview — North Mountain Media</title>
<link
    rel="stylesheet"
    href="assets/css/portal.css?v=20260727-site-controls-landing-v60"
>
</head>
<body class="portal-body">
<div class="portal-shell">
<aside class="portal-sidebar portal-sidebar-admin">
<div class="portal-brand">
<img
    src="assets/images/north-mountain-media-logo.png"
    alt="North Mountain Media"
>
</div>
<nav class="portal-nav portal-nav-admin">
<section class="portal-nav-group is-current">
<button class="portal-nav-group-toggle" type="button">
<span>Work</span><span>⌃</span>
</button>
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
<header class="portal-topbar">
<div class="portal-title-block">
<span>North Mountain Media</span>
<h1>Publishing Workflow</h1>
</div>
<div class="portal-header-user">
<a class="portal-top-action" href="#">Call Center</a>
</div>
</header>

<div class="portal-content">
<div class="stats-grid publishing-stats">
<article class="stat-card">
<span>Blog posts</span><strong>12</strong>
<small>Published, draft, and archived</small>
</article>
<article class="stat-card">
<span>Scheduled</span><strong>2</strong>
<small>Waiting for publication dates</small>
</article>
<article class="stat-card">
<span>Article views</span><strong>486</strong>
<small>Last 30 days</small>
</article>
<article class="stat-card">
<span>Conversions</span><strong>19</strong>
<small>Content-assisted contacts</small>
</article>
</div>

<div class="page-actions">
<a class="button button-primary" href="#">Create blog post</a>
<a class="button" href="#">Open public blog</a>
<a class="button" href="#">RSS feed</a>
<a class="button" href="#">XML sitemap</a>
</div>

<section class="panel publishing-settings-panel">
<header class="panel-header">
<div>
<span>Public Blog configuration</span>
<h2>Blog settings</h2>
</div>
</header>
<div class="panel-body">
<div class="form-grid">
<label class="field">
<span>Blog label</span>
<input value="North Mountain Media Journal">
</label>
<label class="field">
<span>Archive headline</span>
<input value="Ideas, systems, and things being built.">
</label>
<label class="field full">
<span>Archive description</span>
<textarea rows="3">Articles about product systems, commerce, CRM, operations, music platforms, and independent software development.</textarea>
</label>
<label class="field">
<span>Posts per page</span>
<input type="number" value="9">
</label>
<label class="field">
<span>Default author</span>
<select><option>David Evans</option></select>
</label>
<label class="checkbox-row">
<input type="checkbox" checked>
<span>Enable the public RSS feed.</span>
</label>
<label class="checkbox-row">
<input type="checkbox" checked>
<span>Include publishing records in the XML sitemap.</span>
</label>
</div>
</div>
</section>

<section class="panel publishing-analytics-panel">
<header class="panel-header">
<div>
<span>Visitor Intelligence · Last 30 days</span>
<h2>Blog performance</h2>
</div>
</header>
<div class="publishing-analytics-stats">
<article><span>Archive views</span><strong>172</strong></article>
<article><span>Article views</span><strong>486</strong></article>
<article><span>Articles reached</span><strong>8</strong></article>
<article><span>Attributed conversions</span><strong>19</strong></article>
</div>
<div class="publishing-metric-table">
<div class="publishing-metric-row publishing-metric-head">
<span>Content</span><span>Status</span><span>Views</span>
<span>Visitors</span><span>Last view</span>
</div>
<?php foreach([
['Turning a resume into a publishing system','Published','138','91','Today 4:22 PM'],
['Building a CRM around the lifecycle','Published','116','78','Today 2:06 PM'],
['Designing independent music platforms','Scheduled','84','59','Yesterday'],
] as $row):?>
<div class="publishing-metric-row">
<strong><?=htmlspecialchars($row[0])?></strong>
<span><?=htmlspecialchars($row[1])?></span>
<span><?=$row[2]?></span>
<span><?=$row[3]?></span>
<span><?=htmlspecialchars($row[4])?></span>
</div>
<?php endforeach;?>
</div>
<div class="publishing-attribution">
<header>
<span>Conversion attribution</span>
<h3>What visitors viewed before contacting North Mountain Media</h3>
</header>
<div class="publishing-attribution-list">
<?php foreach([
['Blog post viewed','Turning a resume into a publishing system','7','4'],
['Portfolio viewed','Microgifter','6','5'],
['Resume post viewed','Founder & Systems / Product Operations Lead','4','3'],
] as $row):?>
<article>
<div>
<span><?=htmlspecialchars($row[0])?></span>
<strong><?=htmlspecialchars($row[1])?></strong>
</div>
<div><strong><?=$row[2]?></strong><span>conversions</span></div>
<div><strong><?=$row[3]?></strong><span>opportunities</span></div>
</article>
<?php endforeach;?>
</div>
</div>
</section>

<div class="publishing-autosave-banner">
<div>
<span>Unsaved autosave available</span>
<strong>Saved today at 4:18 PM</strong>
<p>The browser preserved a newer working copy. Revision history also retains periodic snapshots.</p>
</div>
<button class="button button-small" type="button">
Apply autosave
</button>
</div>

<div class="publishing-editor-layout">
<form class="form-panel publishing-editor-form">
<header class="publishing-editor-header">
<div>
<span>Blog publishing</span>
<h2>Turning a resume into a publishing system</h2>
<p>Draft preview, autosave, revisions, SEO, cover cropping, and publication scheduling.</p>
</div>
<span
    class="publishing-autosave-status"
    data-state="saved"
>Autosaved 4:18 PM</span>
</header>

<section class="publishing-form-section">
<header><span>Identity</span><h3>Post basics</h3></header>
<div class="form-grid">
<label class="field">
<span>Post title</span>
<input value="Turning a resume into a publishing system">
</label>
<label class="field">
<span>Status</span>
<select><option>Published</option><option>Draft</option></select>
</label>
<label class="field">
<span>Publish date</span>
<input type="datetime-local" value="2026-07-28T09:00">
</label>
<label class="field">
<span>Author</span>
<select><option>David Evans</option></select>
</label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Discovery</span><h3>SEO and sharing</h3></header>
<div class="form-grid">
<label class="field">
<span>SEO title</span>
<input value="Database-Backed Resume Publishing">
</label>
<label class="field">
<span>SEO description</span>
<textarea rows="3">Structured resume records that power the public site and assistant answers.</textarea>
</label>
<label class="field full">
<span>Canonical URL</span>
<input value="https://northmountainmedia.com/blog-post.php?slug=resume-publishing">
</label>
</div>
</section>
</form>

<aside class="publishing-editor-sidebar">
<section class="panel">
<header class="panel-header">
<div><span>Version history</span><h2>7 revisions</h2></div>
</header>
<div class="publishing-revision-list">
<?php foreach([
['Manual','Published SEO update','Today 4:20 PM'],
['Autosave','Working article draft','Today 4:18 PM'],
['Manual','Added cover and gallery','Yesterday 6:40 PM'],
['Restore','Restored structured-data copy','July 25, 2026'],
] as $revision):?>
<article>
<div>
<span><?=htmlspecialchars($revision[0])?></span>
<strong><?=htmlspecialchars($revision[1])?></strong>
<small><?=htmlspecialchars($revision[2])?> · David Evans</small>
</div>
<button class="button button-small" type="button">Restore</button>
</article>
<?php endforeach;?>
</div>
</section>
</aside>
</div>

<div class="resume-sort-toolbar">
<div>
<span>Drag-and-drop order</span>
<strong>Move Resume Posts inside or between columns.</strong>
</div>
<span data-state="saved">Order saved</span>
</div>

<div class="resume-sort-columns">
<?php foreach([
'Main column'=>[
['Founder & Systems / Product Operations Lead','Experience'],
['eCommerce Listing Specialist','Experience'],
['Client Services Manager','Experience'],
],
'Sidebar'=>[
['Core competencies','Skill Group'],
['Tools & platforms','Skill Group'],
['Education','Education'],
],
] as $column=>$items):?>
<section class="resume-sort-column">
<header>
<span><?=htmlspecialchars($column)?></span>
<strong><?=count($items)?> posts</strong>
</header>
<div class="resume-admin-list resume-sort-list">
<?php foreach($items as $item):?>
<article class="resume-admin-row" draggable="true">
<div class="resume-admin-order"><strong>↕</strong><span>drag</span></div>
<div class="resume-admin-entry">
<span><?=htmlspecialchars($item[1])?></span>
<h2><?=htmlspecialchars($item[0])?></h2>
<p>Structured Resume Post with preview, autosave, revisions, duplication, and analytics.</p>
</div>
<footer>
<a class="button button-small button-primary" href="#">Manage</a>
<a class="button button-small" href="#">Preview</a>
</footer>
</article>
<?php endforeach;?>
</div>
</section>
<?php endforeach;?>
</div>
</div>
</main>
</div>
</body>
</html>

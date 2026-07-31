<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
nmm_require_public_module('rss');
require_once __DIR__ . '/portal/public-syndication.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$settings = syndication_settings();
$tags = syndication_public_tags();
$authors = syndication_public_authors();
$shell = music_public_shell_context();
$feeds = [
    ['enabled'=>$settings['rss_enabled'],'title'=>'RSS 2.0','description'=>'Full article content, categories, cover media, and audio enclosures.','path'=>'blog-feed.php','type'=>'application/rss+xml'],
    ['enabled'=>$settings['atom_enabled'],'title'=>'Atom 1.0','description'=>'Standards-based entries with stable IDs, updated dates, categories, and enclosures.','path'=>'blog-atom.php','type'=>'application/atom+xml'],
    ['enabled'=>$settings['json_enabled'],'title'=>'JSON Feed 1.1','description'=>'A developer-friendly JSON representation with authors, tags, images, and attachments.','path'=>'blog-json-feed.php','type'=>'application/feed+json'],
    ['enabled'=>$settings['podcast_enabled'],'title'=>'Podcast RSS','description'=>'Audio posts only, with complete channel metadata and podcast-compatible enclosures.','path'=>'podcast-feed.php','type'=>'application/rss+xml'],
];
$canonical = publishing_absolute_url('blog-feeds.php');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Subscribe to North Mountain Media articles and audio using RSS, Atom, JSON Feed, or podcast applications.">
<link rel="canonical" href="<?=e($canonical)?>">
<?=syndication_discovery_links(['category'=>'','tag'=>'','author'=>''])?>
<title>Feeds &amp; Syndication — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260728-content-controls-v62.1'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260728-content-controls-v62.1'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-syndication.css?v=20260730-v66E'))?>">
</head>
<body class="blog-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'blog');?>
<section class="music-public-workspace">
<?php music_render_public_header($shell);?>
<main class="syndication-main">
<section class="syndication-hero">
<div>
<span>Open-web distribution</span>
<h1>Choose how you want to follow the work.</h1>
<p>Subscribe through a traditional reader, a JSON-aware application, or a podcast client. Category, tag, and author filters can create focused feeds without requiring a platform account.</p>
</div>
<div class="syndication-status">
<div><span>Article feeds</span><strong><?=($settings['rss_enabled']||$settings['atom_enabled']||$settings['json_enabled'])?'Active':'Disabled'?></strong></div>
<div><span>Podcast feed</span><strong><?=$settings['podcast_enabled']?'Active':'Disabled'?></strong></div>
<div><span>Webmention</span><strong><?=$settings['webmention_enabled']?'Moderated':'Disabled'?></strong></div>
<div><span>WebSub</span><strong><?=$settings['websub_enabled']?'Hub connected':'Not configured'?></strong></div>
</div>
</section>

<section class="syndication-grid" aria-label="Available feeds">
<?php foreach($feeds as $feed):?>
<?php if(!$feed['enabled']) continue;?>
<?php $url=publishing_absolute_url($feed['path']);?>
<article class="syndication-card">
<header><div><h2><?=e($feed['title'])?></h2><p><?=e($feed['description'])?></p></div><span><?=e($feed['type'])?></span></header>
<code><?=e($url)?></code>
<a href="<?=e($url)?>">Open feed</a>
<small>Copy this address into a compatible reader or subscription application.</small>
</article>
<?php endforeach;?>
</section>

<?php if($tags):?>
<section class="syndication-section">
<header><div><span>Focused subscriptions</span><h2>Feeds by tag</h2><p>Each tag can be followed independently in RSS, Atom, or JSON Feed.</p></div></header>
<div class="syndication-chips">
<?php foreach($tags as $tag=>$count):?>
<a class="syndication-chip" href="<?=e(publishing_absolute_url('blog-json-feed.php?tag='.rawurlencode((string)$tag)))?>"><?=e($tag)?> · <?=(int)$count?></a>
<?php endforeach;?>
</div>
</section>
<?php endif;?>

<?php if($authors):?>
<section class="syndication-section">
<header><div><span>Contributors</span><h2>Feeds by author</h2><p>Author-specific feeds use stable account IDs while displaying the public author name.</p></div></header>
<div class="syndication-chips">
<?php foreach($authors as $author):?>
<a class="syndication-chip" href="<?=e(publishing_absolute_url('blog-feed.php?author='.(int)$author['id']))?>"><?=e($author['display_name'])?> · <?=(int)$author['post_count']?></a>
<?php endforeach;?>
</div>
</section>
<?php endif;?>

<section class="syndication-section">
<header><div><span>Independent web</span><h2>Protocol support</h2></div></header>
<div class="syndication-protocols">
<article><h3>Feed discovery</h3><p>Blog pages advertise the enabled RSS, Atom, JSON, and podcast feeds directly in the document head.</p></article>
<article><h3>Webmention</h3><p>Other websites can send verified replies, likes, reposts, and mentions. Every incoming item is moderated before public display.</p></article>
<article><h3>WebSub</h3><p>When a hub is configured, publication events are queued and delivered with retry receipts instead of delaying the editor.</p></article>
</div>
</section>
</main>
</section>
</div>
</body>
</html>

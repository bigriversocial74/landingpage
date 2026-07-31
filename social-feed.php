<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';
require_once __DIR__ . '/portal/social-posts-service.php';
require_once __DIR__ . '/portal/public-syndication.php';

nmm_require_public_module('social_feed');

$settings = activitypub_settings();
$posts = social_posts_settings()['enabled'] ? social_posts_public_posts(60) : [];

header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Public social posts from <?=e($settings['display_name'])?>.">
<link rel="canonical" href="<?=e(app_url('social-feed.php'))?>">
<?=activitypub_discovery_links()?>
<?php if(nmm_module_enabled('blog')):?><?=syndication_discovery_links(['category'=>'','tag'=>'','author'=>''])?><?php endif;?>
<meta property="og:type" content="profile">
<meta property="og:title" content="<?=e($settings['display_name'])?> — Social Feed">
<meta property="og:description" content="Permanent public ActivityPub posts from this POD.">
<title><?=e($settings['display_name'])?> — Social Feed</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">
</head>
<body class="pod-social-public-body">
<header class="pod-social-public-header">
<a href="<?=e(app_url('index.php'))?>"><?=e(setting('site_name','Personal POD'))?></a>
<nav><a href="<?=e(app_url('blog.php'))?>">Blog</a><?php if(nmm_module_enabled('blog')):?><a href="<?=e(app_url('blog-feed.php'))?>">RSS</a><?php endif;?><a class="pod-follow-button" href="<?=e(app_url('follow-pod.php'))?>">Follow this POD</a></nav>
</header>
<main class="pod-social-feed-page">
<section class="pod-social-profile">
<div class="pod-social-profile-avatar" aria-hidden="true"><?=e(mb_strtoupper(mb_substr((string)$settings['display_name'],0,1)))?></div>
<div><span>ActivityPub profile</span><h1><?=e($settings['display_name'])?></h1><p><?=e($settings['summary']?:'Posts published directly from this personal online deployment.')?></p><strong><?=e('@'.activitypub_account())?></strong></div>
<a class="pod-follow-button" href="<?=e(app_url('follow-pod.php'))?>">Follow this POD</a>
</section>
<section class="pod-social-feed-list" aria-label="Public social posts">
<?php foreach($posts as $post) social_posts_render_card($post);?>
<?php if(!$posts):?><div class="pod-content-empty">Public social posts will appear here.</div><?php endif;?>
</section>
</main>
<script src="<?=e(app_url('assets/js/social-posts-v66p.js?v=20260731-v66P'))?>"></script>
</body>
</html>

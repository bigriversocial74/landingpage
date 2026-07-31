<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub-service.php';
require_once __DIR__ . '/portal/social-posts-service.php';

$uuid = strtolower(trim((string)($_GET['id'] ?? '')));
$post = social_posts_find_uuid($uuid);
if (
    !$post
    || (string)$post['status'] !== 'published'
    || (string)$post['visibility'] !== 'public'
) {
    http_response_code(404);
    exit('Social post not found.');
}

$description = mb_substr(
    social_posts_clean_text((string)($post['body_text'] ?? ''), 220),
    0,
    220
);
$description = $description !== '' ? $description : 'A public social post from this POD.';
$canonical = social_posts_public_url($post);
$activityObject = social_posts_object_url($post);
$settings = activitypub_settings();

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
<meta name="description" content="<?=e($description)?>">
<link rel="canonical" href="<?=e($canonical)?>">
<link rel="alternate" type="application/activity+json" href="<?=e($activityObject)?>">
<meta property="og:type" content="article">
<meta property="og:title" content="<?=e($settings['display_name'].' on the Fediverse')?>">
<meta property="og:description" content="<?=e($description)?>">
<meta property="og:url" content="<?=e($canonical)?>">
<?php if(!empty($post['media_url'])&&$post['media_kind']==='image'):?><meta property="og:image" content="<?=e((string)$post['media_url'])?>"><?php endif;?>
<title><?=e($settings['display_name'])?> — Social Post</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">
</head>
<body class="pod-social-public-body">
<header class="pod-social-public-header">
<a href="<?=e(app_url('index.php'))?>"><?=e(setting('site_name','Personal POD'))?></a>
<nav><a href="<?=e(app_url('social-feed.php'))?>">Social feed</a><a href="<?=e(app_url('blog.php'))?>">Blog</a><a class="pod-follow-button" href="<?=e(app_url('follow-pod.php'))?>">Follow this POD</a></nav>
</header>
<main class="pod-social-single">
<?php social_posts_render_card($post);?>
<p class="pod-social-protocol-note">This is a public ActivityPub Note published by <strong><?=e('@'.activitypub_account())?></strong>.</p>
</main>
<script src="<?=e(app_url('assets/js/social-posts-v66p.js?v=20260731-v66P'))?>"></script>
</body>
</html>

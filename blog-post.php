<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
require_once __DIR__ . '/portal/publishing.php';
require_once __DIR__ . '/portal/publishing-workflow.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$previewRequested = !empty($_GET['preview']);
$previewId = max(0, (int)($_GET['id'] ?? 0));
$viewer = $previewRequested ? current_user() : null;
$isAdminPreview = (
    $previewRequested
    && $previewId > 0
    && $viewer
    && ($viewer['role'] ?? '') === 'admin'
);
$post = $isAdminPreview
    ? publishing_blog_preview_post($previewId)
    : blog_public_post_by_slug($slug);
$shell = music_public_shell_context();

if (!$post) {
    http_response_code(404);
}

$title = $post
    ? ($post['seo_title']?:$post['title'])
    : 'Article unavailable';
$description = $post
    ? ($post['seo_description']?:$post['excerpt'])
    : 'The requested article is unavailable.';
$canonicalUrl = $post
    ? (
        $post['canonical_url']
        ?: publishing_absolute_url(
            'blog-post.php?slug='
            .rawurlencode($post['slug'])
        )
    )
    : publishing_absolute_url('blog.php');
$ogImage = (
    $post
    && $post['cover']
)
    ? publishing_absolute_url(
        'blog-media.php?id='
        .(int)$post['cover']['id']
    )
    : '';
$structuredData = $post
    ? [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post['title'],
        'description' => $description,
        'datePublished' => $post['published_at'] ?: null,
        'dateModified' => $post['updated_at'] ?: $post['published_at'],
        'mainEntityOfPage' => $canonicalUrl,
        'author' => [
            '@type' => 'Person',
            'name' => $post['author_name'] ?: 'David Evans',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'North Mountain Media',
        ],
        'image' => $ogImage !== '' ? [$ogImage] : null,
    ]
    : null;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; connect-src 'self'; "
    . "base-uri 'self'; object-src 'none'; frame-ancestors 'self'"
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=e($description)?>">
<meta name="build-version" content="20260727-site-controls-landing-v60">
<link rel="canonical" href="<?=e($canonicalUrl)?>">
<?php if($isAdminPreview):?>
<meta name="robots" content="noindex,nofollow">
<?php endif;?>
<meta property="og:type" content="article">
<meta property="og:title" content="<?=e($title)?>">
<meta property="og:description" content="<?=e($description)?>">
<meta property="og:url" content="<?=e($canonicalUrl)?>">
<meta property="og:site_name" content="North Mountain Media">
<?php if($ogImage!==''):?>
<meta property="og:image" content="<?=e($ogImage)?>">
<?php endif;?>
<meta name="twitter:card" content="<?=$ogImage!==''?'summary_large_image':'summary'?>">
<meta name="twitter:title" content="<?=e($title)?>">
<meta name="twitter:description" content="<?=e($description)?>">
<?php if($structuredData):?>
<script type="application/ld+json"><?=json_encode(
    array_filter(
        $structuredData,
        static fn(mixed $value): bool => $value!==null
    ),
    JSON_UNESCAPED_SLASHES
    |JSON_UNESCAPED_UNICODE
    |JSON_HEX_TAG
    |JSON_HEX_AMP
    |JSON_HEX_APOS
    |JSON_HEX_QUOT
)?></script>
<?php endif;?>
<title><?=e($title)?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/blog.css?v=20260727-site-controls-landing-v60'))?>">
</head>
<body class="blog-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'blog');?>

<section class="music-public-workspace">
<?php music_render_public_header($shell);?>

<main class="blog-post-main">
<?php if($isAdminPreview&&$post):?>
<div class="publishing-preview-banner">
Administrator preview · <?=e(
    publishing_publication_state($post)['label']
)?>
</div>
<?php endif;?>
<?php if(!$post):?>
<div class="blog-empty">
The requested article is not published or could not be found.
</div>
<?php else:?>
<nav class="blog-breadcrumbs" aria-label="Breadcrumb">
<a href="<?=e(app_url('blog.php'))?>">Blog</a>
<span>›</span>
<?php if($post['category']!==''):?>
<a href="<?=e(app_url(
    'blog.php?category='
    .rawurlencode($post['category'])
))?>"><?=e($post['category'])?></a>
<span>›</span>
<?php endif;?>
<strong><?=e($post['title'])?></strong>
</nav>

<header class="blog-post-header">
<span><?=e($post['category']?:'North Mountain Media Journal')?></span>
<h1><?=e($post['title'])?></h1>
<?php if($post['excerpt']!==''):?>
<p><?=e($post['excerpt'])?></p>
<?php endif;?>
<div class="blog-meta">
<span><?=e($post['published_label'])?></span>
<span><?=e($post['author_name']?:'David Evans')?></span>
</div>
</header>

<?php if($post['cover']):?>
<figure class="blog-post-cover <?=e(publishing_media_ratio_class($post['cover']))?>">
<img
    src="<?=e($post['cover']['url'])?>"
    alt="<?=e($post['cover']['alt']?:$post['title'].' cover')?>"
    style="<?=e(publishing_media_position_style($post['cover']))?>"
>
<?php if($post['cover']['caption']!==''):?>
<figcaption><?=e($post['cover']['caption'])?></figcaption>
<?php endif;?>
</figure>
<?php endif;?>

<article class="blog-post-article">
<?=$post['body_html']?>
</article>

<?php if($post['tags']):?>
<div class="blog-tags" aria-label="Article tags">
<?php foreach($post['tags'] as $tag):?>
<span># <?=e($tag)?></span>
<?php endforeach;?>
</div>
<?php endif;?>

<?php if($post['gallery']):?>
<section class="blog-gallery" aria-label="Article gallery">
<?php foreach($post['gallery'] as $image):?>
<figure class="<?=e(publishing_media_ratio_class($image))?>">
<img
    src="<?=e($image['url'])?>"
    alt="<?=e($image['alt'])?>"
    loading="lazy"
    style="<?=e(publishing_media_position_style($image))?>"
>
<?php if($image['caption']!==''):?>
<figcaption><?=e($image['caption'])?></figcaption>
<?php endif;?>
</figure>
<?php endforeach;?>
</section>
<?php endif;?>

<footer class="blog-post-footer">
<a href="<?=e(app_url('blog.php'))?>">← Back to Blog</a>
</footer>
<?php endif;?>
</main>
</section>
</div>

<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/visitor-activity.js?v=20260727-site-controls-landing-v60'))?>"></script>
<?php if($post&&!$isAdminPreview):?>
<script>
window.NMMVisitorActivity?.track(
  'blog_post_view',
  {
    event_label: <?=json_encode($post['title'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,
    metadata: {
      post_id: <?=(int)$post['id']?>,
      post_slug: <?=json_encode($post['slug'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,
      category: <?=json_encode($post['category'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>
    },
    deduplicate: false
  }
);
</script>
<?php endif;?>
</body>
</html>

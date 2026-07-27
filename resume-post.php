<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('resume');
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
    ? publishing_resume_preview_post($previewId)
    : resume_public_post_by_slug($slug);
$shell = music_public_shell_context();

if (!$post) {
    http_response_code(404);
}

$canonicalUrl = $post
    ? publishing_absolute_url(
        'resume-post.php?slug='
        .rawurlencode($post['slug'])
    )
    : publishing_absolute_url(
        'index.php?mode=resume'
    );
$resumeDescription = $post
    ? publishing_excerpt(
        $post['summary']
        ?: $post['body']
        ?: implode(' ', $post['achievements'])
    )
    : 'The requested resume entry is unavailable.';
$resumeStructuredData = $post
    ? [
        '@context' => 'https://schema.org',
        '@type' => 'ProfilePage',
        'name' => $post['title'],
        'description' => $resumeDescription,
        'url' => $canonicalUrl,
        'dateModified' => $post['updated_at'] ?: null,
        'mainEntity' => [
            '@type' => 'Person',
            'name' => 'David Evans',
            'jobTitle' => $post['title'],
            'worksFor' => $post['organization'] !== ''
                ? [
                    '@type' => 'Organization',
                    'name' => $post['organization'],
                ]
                : null,
        ],
    ]
    : null;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=e($resumeDescription)?>">
<meta name="build-version" content="20260727-site-controls-landing-v60">
<link rel="canonical" href="<?=e($canonicalUrl)?>">
<?php if($isAdminPreview):?>
<meta name="robots" content="noindex,nofollow">
<?php endif;?>
<meta property="og:type" content="profile">
<meta property="og:title" content="<?=e($post?$post['title']:'Resume entry unavailable')?>">
<meta property="og:description" content="<?=e($resumeDescription)?>">
<meta property="og:url" content="<?=e($canonicalUrl)?>">
<?php if($resumeStructuredData):?>
<script type="application/ld+json"><?=json_encode(
    $resumeStructuredData,
    JSON_UNESCAPED_SLASHES
    |JSON_UNESCAPED_UNICODE
    |JSON_HEX_TAG
    |JSON_HEX_AMP
    |JSON_HEX_APOS
    |JSON_HEX_QUOT
)?></script>
<?php endif;?>
<title><?=e($post?$post['title']:'Resume entry unavailable')?> — David Evans</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/blog.css?v=20260727-site-controls-landing-v60'))?>">
</head>
<body class="blog-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'resume');?>

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
<div class="blog-empty">The requested resume entry is unavailable.</div>
<?php else:?>
<nav class="blog-breadcrumbs">
<a href="<?=e(app_url('index.php?mode=resume'))?>">Resume</a>
<span>›</span>
<strong><?=e($post['title'])?></strong>
</nav>

<header class="blog-post-header">
<span><?=e(status_label($post['post_type']))?></span>
<h1><?=e($post['title'])?></h1>
<?php if($post['organization']!==''):?>
<p><strong><?=e($post['organization'])?></strong></p>
<?php endif;?>
<div class="blog-meta">
<?php if($post['date_label']!==''):?><span><?=e($post['date_label'])?></span><?php endif;?>
<?php if($post['location']!==''):?><span><?=e($post['location'])?></span><?php endif;?>
</div>
</header>

<article class="blog-post-article">
<?php if($post['summary']!==''):?><p><?=e($post['summary'])?></p><?php endif;?>
<?=$post['body_html']?>
<?php if($post['achievements']):?>
<ul>
<?php foreach($post['achievements'] as $item):?>
<li><?=e($item)?></li>
<?php endforeach;?>
</ul>
<?php endif;?>
</article>

<?php if($post['skills']):?>
<div class="blog-tags">
<?php foreach($post['skills'] as $skill):?><span><?=e($skill)?></span><?php endforeach;?>
</div>
<?php endif;?>

<footer class="blog-post-footer">
<a href="<?=e(app_url('index.php?mode=resume'))?>">← Back to Resume</a>
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
  'resume_post_view',
  {
    event_label: <?=json_encode($post['title'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,
    metadata: {
      resume_post_id: <?=(int)$post['id']?>,
      resume_post_slug: <?=json_encode($post['slug'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,
      resume_post_type: <?=json_encode($post['post_type'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>
    },
    deduplicate: false
  }
);
</script>
<?php endif;?>
</body>
</html>

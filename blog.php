<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
require_once __DIR__ . '/portal/publishing.php';
require_once __DIR__ . '/portal/publishing-workflow.php';
require_once __DIR__ . '/portal/public-music-shell.php';

$category = trim((string)($_GET['category'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$blogSettings = publishing_blog_settings();
$page = max(1, (int)($_GET['page'] ?? 1));
$postsPerPage = (int)$blogSettings['posts_per_page'];
$totalPosts = blog_public_post_count($category, $search);
$totalPages = max(
    1,
    (int)ceil($totalPosts / max(1, $postsPerPage))
);
$page = min($page, $totalPages);
$offset = ($page - 1) * $postsPerPage;
$posts = blog_public_posts(
    $category,
    $search,
    $postsPerPage,
    $offset
);
$categories = blog_public_categories();
$shell = music_public_shell_context();
$featured = null;

foreach ($posts as $post) {
    if ($page === 1 && $post['featured']) {
        $featured = $post;
        break;
    }
}

if (!$featured && $posts && $page === 1) {
    $featured = $posts[0];
}

$gridPosts = $posts;

if ($featured) {
    $gridPosts = array_values(array_filter(
        $posts,
        static fn(array $post): bool =>
            $post['id'] !== $featured['id']
    ));
}

$canonicalQuery = array_filter([
    'category' => $category !== '' ? $category : null,
    'q' => $search !== '' ? $search : null,
    'page' => $page > 1 ? $page : null,
]);
$canonicalPath = 'blog.php'
    . ($canonicalQuery
        ? '?' . http_build_query($canonicalQuery)
        : '');
$canonicalUrl = publishing_absolute_url($canonicalPath);
$archiveTitle = (string)$blogSettings['title'];
$archiveIntro = (string)$blogSettings['intro'];
$archiveDescription = (string)$blogSettings['description'];

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
<meta name="description" content="<?=e($archiveDescription)?>">
<meta name="build-version" content="20260727-site-controls-landing-v60">
<link rel="canonical" href="<?=e($canonicalUrl)?>">
<?php if($blogSettings['rss_enabled']):?>
<link
    rel="alternate"
    type="application/rss+xml"
    title="<?=e($archiveTitle)?> RSS"
    href="<?=e(publishing_absolute_url('blog-feed.php' . ($category!=='' ? '?category=' . rawurlencode($category) : '')))?>"
>
<?php endif;?>
<?php if($blogSettings['atom_enabled']):?>
<link rel="alternate" type="application/atom+xml" title="<?=e($archiveTitle)?> Atom" href="<?=e(publishing_absolute_url('blog-atom.php' . ($category!=='' ? '?category=' . rawurlencode($category) : '')))?>">
<?php endif;?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?=e($archiveIntro)?>">
<meta property="og:description" content="<?=e($archiveDescription)?>">
<meta property="og:url" content="<?=e($canonicalUrl)?>">
<meta property="og:site_name" content="North Mountain Media">
<title><?=e($archiveTitle)?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-music-shell.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/public-sidebar.css?v=20260727-site-controls-landing-v60'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/blog.css?v=20260727-site-controls-landing-v60'))?>">
</head>
<body class="blog-body">
<div class="music-public-shell">
<?php music_render_public_sidebar($shell,'blog');?>

<section class="music-public-workspace">
<?php music_render_public_header($shell);?>

<main class="blog-canvas">
<header class="blog-archive-header">
<div>
<span><?=e($archiveTitle)?></span>
<h1><?=e($archiveIntro)?></h1>
<p><?=e($archiveDescription)?></p>
</div>

<form class="blog-filter-form" method="get">
<input
    type="search"
    name="q"
    value="<?=e($search)?>"
    placeholder="Search articles"
    aria-label="Search articles"
>
<?php if($category!==''):?>
<input type="hidden" name="category" value="<?=e($category)?>">
<?php endif;?>
<button type="submit">Search</button>
</form>
</header>

<nav class="blog-category-nav" aria-label="Blog categories">
<a class="<?=$category===''?'active':''?>" href="<?=e(app_url('blog.php'))?>">All posts</a>
<?php foreach($categories as $item):?>
<a
    class="<?=$category===$item['category']?'active':''?>"
    href="<?=e(app_url(
        'blog.php?category='
        .rawurlencode((string)$item['category'])
    ))?>"
>
<?=e($item['category'])?> · <?=(int)$item['post_count']?>
</a>
<?php endforeach;?>
</nav>

<?php if($featured):?>
<article class="blog-featured">
<div class="blog-featured-media <?=e(publishing_media_ratio_class($featured['cover']??[]))?>">
<?php if($featured['cover']):?>
<a href="<?=e($featured['url'])?>">
<img
    src="<?=e($featured['cover']['url'])?>"
    alt="<?=e($featured['cover']['alt']?:$featured['title'].' cover')?>"
    style="<?=e(publishing_media_position_style($featured['cover']))?>"
>
</a>
<?php else:?>
<a class="blog-featured-placeholder" href="<?=e($featured['url'])?>">
<span><?=e(mb_strtoupper(mb_substr($featured['title'],0,1)))?></span>
</a>
<?php endif;?>
</div>
<div class="blog-featured-copy">
<span><?=e($featured['category']?:'Featured article')?></span>
<h2><a href="<?=e($featured['url'])?>"><?=e($featured['title'])?></a></h2>
<p><?=e($featured['excerpt'])?></p>
<div class="blog-meta">
<span><?=e($featured['published_label'])?></span>
<span><?=e($featured['author_name']?:'David Evans')?></span>
</div>
<a class="blog-read-link" href="<?=e($featured['url'])?>">Read article →</a>
</div>
</article>
<?php endif;?>

<?php if($gridPosts):?>
<section class="blog-grid" aria-label="Blog posts">
<?php foreach($gridPosts as $post):?>
<article class="blog-card">
<div class="blog-card-media <?=e(publishing_media_ratio_class($post['cover']??[]))?>">
<?php if($post['cover']):?>
<a href="<?=e($post['url'])?>">
<img
    src="<?=e($post['cover']['url'])?>"
    alt="<?=e($post['cover']['alt']?:$post['title'].' cover')?>"
    loading="lazy"
    style="<?=e(publishing_media_position_style($post['cover']))?>"
>
</a>
<?php else:?>
<a class="blog-card-placeholder" href="<?=e($post['url'])?>">
<span><?=e(mb_strtoupper(mb_substr($post['title'],0,1)))?></span>
</a>
<?php endif;?>
</div>
<div class="blog-card-copy">
<span><?=e($post['category']?:'Article')?></span>
<h2><a href="<?=e($post['url'])?>"><?=e($post['title'])?></a></h2>
<p><?=e($post['excerpt'])?></p>
<div class="blog-meta">
<span><?=e($post['published_label'])?></span>
<span><?=e($post['author_name']?:'David Evans')?></span>
</div>
</div>
</article>
<?php endforeach;?>
</section>
<?php elseif(!$featured):?>
<div class="blog-empty">
<?php if($search!==''||$category!==''):?>
No published articles matched the selected filters.
<?php else:?>
Published articles will appear here.
<?php endif;?>
</div>
<?php endif;?>

<?php if($totalPages>1):?>
<nav class="blog-pagination" aria-label="Blog pagination">
<?php if($page>1):?>
<a href="<?=e(app_url(
    'blog.php?'
    .http_build_query(array_filter([
        'category'=>$category?:null,
        'q'=>$search?:null,
        'page'=>$page-1,
    ]))
))?>">← Previous</a>
<?php endif;?>
<span>Page <?=$page?> of <?=$totalPages?></span>
<?php if($page<$totalPages):?>
<a href="<?=e(app_url(
    'blog.php?'
    .http_build_query(array_filter([
        'category'=>$category?:null,
        'q'=>$search?:null,
        'page'=>$page+1,
    ]))
))?>">Next →</a>
<?php endif;?>
</nav>
<?php endif;?>

</main>
</section>
</div>

<script src="<?=e(app_url('assets/js/public-sidebar.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/public-music-shell.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script src="<?=e(app_url('assets/js/visitor-activity.js?v=20260727-site-controls-landing-v60'))?>"></script>
<script>
window.NMMVisitorActivity?.track(
  'blog_archive_view',
  {
    event_label: 'North Mountain Media Blog',
    metadata: {
      category: <?=json_encode($category,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,
      search: <?=json_encode($search,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,
      page: <?=$page?>
    },
    deduplicate: false
  }
);
</script>
</body>
</html>

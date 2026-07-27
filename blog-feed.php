<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
require_once __DIR__ . '/portal/publishing.php';
require_once __DIR__ . '/portal/publishing-workflow.php';

$settings = publishing_blog_settings();

if (!$settings['rss_enabled']) {
    http_response_code(404);
    exit('RSS feed is disabled.');
}

$posts = blog_public_posts(null, null, 30, 0);
$feedUrl = publishing_absolute_url('blog-feed.php');
$blogUrl = publishing_absolute_url('blog.php');

header('Content-Type: application/rss+xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=900, must-revalidate');

$xml = static fn(mixed $value): string =>
    htmlspecialchars(
        (string)$value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0">
<channel>
<title><?=$xml($settings['title'])?></title>
<link><?=$xml($blogUrl)?></link>
<description><?=$xml($settings['description'])?></description>
<language>en-us</language>
<lastBuildDate><?=gmdate(DATE_RSS)?></lastBuildDate>
<atom:link
    xmlns:atom="http://www.w3.org/2005/Atom"
    href="<?=$xml($feedUrl)?>"
    rel="self"
    type="application/rss+xml"
/>
<?php foreach($posts as $post):?>
<item>
<title><?=$xml($post['title'])?></title>
<link><?=$xml(publishing_absolute_url(
    'blog-post.php?slug='
    .rawurlencode($post['slug'])
))?></link>
<guid isPermaLink="true"><?=$xml(publishing_absolute_url(
    'blog-post.php?slug='
    .rawurlencode($post['slug'])
))?></guid>
<description><?=$xml($post['excerpt'])?></description>
<pubDate><?=gmdate(
    DATE_RSS,
    strtotime(
        $post['published_at']
        ?: $post['updated_at']
        ?: 'now'
    )
)?></pubDate>
<?php if($post['category']!==''):?>
<category><?=$xml($post['category'])?></category>
<?php endif;?>
<?php foreach($post['tags'] as $tag):?>
<category><?=$xml($tag)?></category>
<?php endforeach;?>
</item>
<?php endforeach;?>
</channel>
</rss>

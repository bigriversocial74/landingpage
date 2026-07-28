<?php
declare(strict_types=1);

/* North Mountain Media build: 20260728-rss-feed-reader-v62 */

function publishing_feed_xml(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function publishing_feed_cdata(string $value): string
{
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
}

function publishing_feed_category(): string
{
    return mb_substr(trim((string)($_GET['category'] ?? '')), 0, 120);
}

function publishing_feed_post_url(array $post): string
{
    $canonical = trim((string)($post['canonical_url'] ?? ''));
    if ($canonical !== '' && filter_var($canonical, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string)parse_url($canonical, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $canonical;
        }
    }
    return publishing_absolute_url(
        'blog-post.php?slug=' . rawurlencode((string)$post['slug'])
    );
}

function publishing_feed_cover_url(array $post): string
{
    return !empty($post['cover']['id'])
        ? publishing_absolute_url('blog-media.php?id=' . (int)$post['cover']['id'])
        : '';
}

function publishing_feed_timestamp(array $post, string $field, string $fallback = ''): int
{
    $value = trim((string)($post[$field] ?? $fallback));
    $time = $value !== '' ? strtotime($value) : false;
    return $time !== false ? $time : time();
}

function publishing_feed_context(string $format): array
{
    $settings = publishing_blog_settings();
    $category = publishing_feed_category();
    $limit = (int)$settings['feed_item_limit'];
    $posts = blog_public_posts($category, null, $limit, 0);
    $suffix = $category !== '' ? ' — ' . $category : '';
    $selfPath = $format === 'atom' ? 'blog-atom.php' : 'blog-feed.php';
    if ($category !== '') {
        $selfPath .= '?category=' . rawurlencode($category);
    }
    $blogPath = 'blog.php';
    if ($category !== '') {
        $blogPath .= '?category=' . rawurlencode($category);
    }
    $lastModified = 0;
    foreach ($posts as $post) {
        $lastModified = max(
            $lastModified,
            publishing_feed_timestamp($post, 'updated_at', (string)($post['published_at'] ?? ''))
        );
    }
    if ($lastModified <= 0) {
        $lastModified = 946684800; // Stable empty-feed timestamp: 2000-01-01 UTC.
    }
    return [
        'settings' => $settings,
        'category' => $category,
        'posts' => $posts,
        'title' => (string)$settings['title'] . $suffix,
        'description' => $category !== ''
            ? 'Published ' . $category . ' articles from ' . (string)$settings['title'] . '.'
            : (string)$settings['description'],
        'self_url' => publishing_absolute_url($selfPath),
        'blog_url' => publishing_absolute_url($blogPath),
        'last_modified' => $lastModified,
    ];
}

function publishing_feed_send(string $xml, string $contentType, int $lastModified): never
{
    $etag = '"' . hash('sha256', $xml) . '"';
    header('Content-Type: ' . $contentType . '; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=900, must-revalidate');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $notModified = false;
    if ($ifNoneMatch !== '') {
        $candidates = array_map('trim', explode(',', $ifNoneMatch));
        $notModified = in_array('*', $candidates, true)
            || in_array($etag, $candidates, true)
            || in_array('W/' . $etag, $candidates, true);
    } else {
        $ifModifiedSince = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) ?: 0;
        $notModified = $ifModifiedSince > 0 && $ifModifiedSince >= $lastModified;
    }
    if ($notModified) {
        http_response_code(304);
        exit;
    }

    echo $xml;
    exit;
}

function publishing_render_rss_feed(): string
{
    $context = publishing_feed_context('rss');
    $settings = $context['settings'];
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/">' . "\n<channel>\n";
    $xml .= '<title>' . publishing_feed_xml($context['title']) . "</title>\n";
    $xml .= '<link>' . publishing_feed_xml($context['blog_url']) . "</link>\n";
    $xml .= '<description>' . publishing_feed_xml($context['description']) . "</description>\n";
    $xml .= '<language>' . publishing_feed_xml($settings['feed_language']) . "</language>\n";
    $xml .= '<lastBuildDate>' . gmdate(DATE_RSS, $context['last_modified']) . "</lastBuildDate>\n";
    $xml .= '<generator>North Mountain Media Portal v62</generator>' . "\n";
    $xml .= '<atom:link href="' . publishing_feed_xml($context['self_url']) . '" rel="self" type="application/rss+xml" />' . "\n";
    if ($settings['atom_enabled']) {
        $xml .= '<atom:link href="' . publishing_feed_xml(publishing_absolute_url('blog-atom.php' . ($context['category'] !== '' ? '?category=' . rawurlencode($context['category']) : ''))) . '" rel="alternate" type="application/atom+xml" />' . "\n";
    }

    foreach ($context['posts'] as $post) {
        $url = publishing_feed_post_url($post);
        $published = publishing_feed_timestamp($post, 'published_at', (string)($post['created_at'] ?? ''));
        $cover = publishing_feed_cover_url($post);
        $xml .= "<item>\n";
        $xml .= '<title>' . publishing_feed_xml($post['title']) . "</title>\n";
        $xml .= '<link>' . publishing_feed_xml($url) . "</link>\n";
        $xml .= '<guid isPermaLink="false">nmm:blog:' . (int)$post['id'] . "</guid>\n";
        $xml .= '<description>' . publishing_feed_xml($post['excerpt']) . "</description>\n";
        $xml .= '<content:encoded>' . publishing_feed_cdata((string)$post['body_html']) . "</content:encoded>\n";
        $xml .= '<pubDate>' . gmdate(DATE_RSS, $published) . "</pubDate>\n";
        if (trim((string)$post['author_name']) !== '') {
            $xml .= '<dc:creator>' . publishing_feed_xml($post['author_name']) . "</dc:creator>\n";
        }
        if ($post['category'] !== '') {
            $xml .= '<category>' . publishing_feed_xml($post['category']) . "</category>\n";
        }
        foreach ($post['tags'] as $tag) {
            $xml .= '<category>' . publishing_feed_xml($tag) . "</category>\n";
        }
        if ($cover !== '') {
            $xml .= '<media:content url="' . publishing_feed_xml($cover) . '" medium="image">';
            $xml .= '<media:title>' . publishing_feed_xml((string)($post['cover']['alt'] ?: $post['title'])) . '</media:title>';
            $xml .= "</media:content>\n";
        }
        $xml .= "</item>\n";
    }
    $xml .= "</channel>\n</rss>\n";
    return $xml;
}

function publishing_atom_id(array $post): string
{
    $host = (string)(parse_url(publishing_absolute_url('index.php'), PHP_URL_HOST) ?: 'northmountainmedia.local');
    $year = gmdate('Y', publishing_feed_timestamp($post, 'published_at', (string)($post['created_at'] ?? '')));
    return 'tag:' . $host . ',' . $year . ':blog/' . (int)$post['id'];
}

function publishing_render_atom_feed(): string
{
    $context = publishing_feed_context('atom');
    $settings = $context['settings'];
    $rssPath = 'blog-feed.php' . ($context['category'] !== '' ? '?category=' . rawurlencode($context['category']) : '');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
    $xml .= '<id>' . publishing_feed_xml($context['self_url']) . "</id>\n";
    $xml .= '<title>' . publishing_feed_xml($context['title']) . "</title>\n";
    $xml .= '<subtitle>' . publishing_feed_xml($context['description']) . "</subtitle>\n";
    $xml .= '<updated>' . gmdate(DATE_ATOM, $context['last_modified']) . "</updated>\n";
    $xml .= '<link rel="self" type="application/atom+xml" href="' . publishing_feed_xml($context['self_url']) . '" />' . "\n";
    $xml .= '<link rel="alternate" type="text/html" href="' . publishing_feed_xml($context['blog_url']) . '" />' . "\n";
    if ($settings['rss_enabled']) {
        $xml .= '<link rel="alternate" type="application/rss+xml" href="' . publishing_feed_xml(publishing_absolute_url($rssPath)) . '" />' . "\n";
    }
    $xml .= '<generator uri="' . publishing_feed_xml(publishing_absolute_url('index.php')) . '">North Mountain Media Portal v62</generator>' . "\n";
    $xml .= '<rights>' . publishing_feed_xml($settings['feed_copyright']) . "</rights>\n";

    foreach ($context['posts'] as $post) {
        $url = publishing_feed_post_url($post);
        $published = publishing_feed_timestamp($post, 'published_at', (string)($post['created_at'] ?? ''));
        $updated = publishing_feed_timestamp($post, 'updated_at', (string)($post['published_at'] ?? ''));
        $xml .= "<entry>\n";
        $xml .= '<id>' . publishing_feed_xml(publishing_atom_id($post)) . "</id>\n";
        $xml .= '<title>' . publishing_feed_xml($post['title']) . "</title>\n";
        $xml .= '<link rel="alternate" type="text/html" href="' . publishing_feed_xml($url) . '" />' . "\n";
        $xml .= '<published>' . gmdate(DATE_ATOM, $published) . "</published>\n";
        $xml .= '<updated>' . gmdate(DATE_ATOM, $updated) . "</updated>\n";
        $xml .= '<author><name>' . publishing_feed_xml((string)($post['author_name'] ?: 'David Evans')) . "</name></author>\n";
        $xml .= '<summary type="text">' . publishing_feed_xml($post['excerpt']) . "</summary>\n";
        $xml .= '<content type="html">' . publishing_feed_cdata((string)$post['body_html']) . "</content>\n";
        if ($post['category'] !== '') {
            $xml .= '<category term="' . publishing_feed_xml($post['category']) . '" />' . "\n";
        }
        foreach ($post['tags'] as $tag) {
            $xml .= '<category term="' . publishing_feed_xml($tag) . '" />' . "\n";
        }
        $xml .= "</entry>\n";
    }
    $xml .= "</feed>\n";
    return $xml;
}

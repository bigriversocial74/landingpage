<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-public-syndication-v66E */

if (!function_exists('blog_public_posts')) {
    require_once __DIR__ . '/publishing.php';
}
if (
    !function_exists('publishing_blog_settings')
    || !function_exists('publishing_absolute_url')
) {
    require_once __DIR__ . '/publishing-workflow.php';
}
if (!function_exists('blog_rich_media_first_enclosure')) {
    require_once __DIR__ . '/blog-rich-media.php';
}

function syndication_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN ("syndication_webmentions","syndication_websub_deliveries")'
        );
        $available = (int)$statement->fetchColumn() === 2;
    } catch (Throwable) {
        $available = false;
    }
    return $available;
}

function syndication_setting(string $key, string $fallback = ''): string
{
    return trim((string)(setting($key, $fallback) ?? $fallback));
}

function syndication_settings(): array
{
    $blog = publishing_blog_settings();
    $hub = syndication_setting('blog_websub_hub_url');
    if ($hub !== '' && !syndication_http_url($hub)) $hub = '';
    $ownerEmail = strtolower(syndication_setting('blog_podcast_owner_email'));
    if ($ownerEmail !== '' && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) $ownerEmail = '';
    $podcastType = syndication_setting('blog_podcast_type', 'episodic');
    if (!in_array($podcastType, ['episodic','serial'], true)) $podcastType = 'episodic';
    return $blog + [
        'json_enabled' => syndication_setting('blog_json_feed_enabled', '1') !== '0',
        'podcast_enabled' => syndication_setting('blog_podcast_feed_enabled', '1') !== '0',
        'webmention_enabled' => syndication_setting('blog_webmention_enabled', '1') !== '0',
        'websub_enabled' => syndication_setting('blog_websub_enabled', '0') !== '0' && $hub !== '',
        'websub_hub_url' => $hub,
        'podcast_title' => syndication_setting('blog_podcast_title', 'North Mountain Media Podcast'),
        'podcast_author' => syndication_setting('blog_podcast_author', 'North Mountain Media'),
        'podcast_owner_name' => syndication_setting('blog_podcast_owner_name', 'David Evans'),
        'podcast_owner_email' => $ownerEmail,
        'podcast_category' => syndication_setting('blog_podcast_category', 'Technology'),
        'podcast_explicit' => syndication_setting('blog_podcast_explicit', '0') === '1',
        'podcast_type' => $podcastType,
        'podcast_image_url' => syndication_setting('blog_podcast_image_url'),
    ];
}

function syndication_http_url(string $value): bool
{
    if (!filter_var($value, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http','https'], true)
        && parse_url($value, PHP_URL_USER) === null
        && parse_url($value, PHP_URL_PASS) === null;
}

function syndication_filter_from_request(): array
{
    $category = mb_substr(trim((string)($_GET['category'] ?? '')), 0, 120);
    $tag = mb_substr(trim((string)($_GET['tag'] ?? '')), 0, 120);
    $author = mb_substr(trim((string)($_GET['author'] ?? '')), 0, 190);
    return ['category'=>$category,'tag'=>$tag,'author'=>$author];
}

function syndication_filter_query(array $filter): string
{
    $query = array_filter([
        'category' => trim((string)($filter['category'] ?? '')) ?: null,
        'tag' => trim((string)($filter['tag'] ?? '')) ?: null,
        'author' => trim((string)($filter['author'] ?? '')) ?: null,
    ]);
    return $query ? '?' . http_build_query($query) : '';
}

function syndication_filter_suffix(array $filter): string
{
    foreach (['category'=>'Category','tag'=>'Tag','author'=>'Author'] as $key=>$label) {
        $value = trim((string)($filter[$key] ?? ''));
        if ($value !== '') return ' — ' . $label . ': ' . $value;
    }
    return '';
}

function syndication_public_posts(array $filter, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    $category = trim((string)($filter['category'] ?? ''));
    $tag = trim((string)($filter['tag'] ?? ''));
    $author = trim((string)($filter['author'] ?? ''));
    if ($tag === '' && $author === '' && function_exists('blog_public_posts')) {
        return blog_public_posts($category, null, $limit, 0);
    }
    if (!publishing_schema_available()) return [];
    $where = [
        'post.status="published"',
        '(post.published_at IS NULL OR post.published_at<=UTC_TIMESTAMP())',
    ];
    $params = [];
    if ($category !== '') {
        $where[] = 'post.category=:category';
        $params['category'] = $category;
    }
    if ($author !== '') {
        if (ctype_digit($author)) {
            $where[] = 'post.author_user_id=:author_id';
            $params['author_id'] = (int)$author;
        } else {
            $where[] = 'user.display_name=:author_name';
            $params['author_name'] = $author;
        }
    }
    $candidateLimit = $tag !== '' ? 250 : $limit;
    $statement = db()->prepare(
        'SELECT post.*,user.display_name AS author_name
         FROM blog_posts post
         LEFT JOIN users user ON user.id=post.author_user_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY post.featured DESC,COALESCE(post.published_at,post.created_at) DESC,post.id DESC
         LIMIT ' . $candidateLimit
    );
    $statement->execute($params);
    $posts = [];
    foreach ($statement->fetchAll() as $row) {
        $payload = blog_post_payload($row, blog_post_media((int)$row['id']));
        if ($tag !== '' && !in_array($tag, $payload['tags'], true)) continue;
        $posts[] = $payload;
        if (count($posts) >= $limit) break;
    }
    return $posts;
}

function syndication_post_url(array $post): string
{
    $canonical = trim((string)($post['canonical_url'] ?? ''));
    if ($canonical !== '' && syndication_http_url($canonical)) return $canonical;
    return publishing_absolute_url('blog-post.php?slug=' . rawurlencode((string)$post['slug']));
}

function syndication_audio_attachment(array $post): ?array
{
    $item = blog_rich_media_first_enclosure((string)($post['body'] ?? ''));
    if (!$item) return null;
    return [
        'url' => (string)$item['url'],
        'mime_type' => (string)$item['type'],
        'size_in_bytes' => (int)$item['length'],
        'duration_in_seconds' => !empty($item['duration_seconds']) ? (int)$item['duration_seconds'] : null,
        'title' => trim((string)($item['title'] ?? '')) ?: (string)$post['title'],
    ];
}

function syndication_context(string $format, ?array $filter = null): array
{
    $settings = syndication_settings();
    $filter ??= syndication_filter_from_request();
    $posts = syndication_public_posts($filter, (int)$settings['feed_item_limit']);
    $lastModified = 946684800;
    foreach ($posts as $post) {
        $time = strtotime((string)($post['updated_at'] ?: $post['published_at'])) ?: 0;
        $lastModified = max($lastModified, $time);
    }
    $paths = [
        'rss'=>'blog-feed.php','atom'=>'blog-atom.php','json'=>'blog-json-feed.php',
        'podcast'=>'podcast-feed.php','directory'=>'blog-feeds.php',
    ];
    $query = syndication_filter_query($filter);
    return [
        'settings'=>$settings,
        'filter'=>$filter,
        'posts'=>$posts,
        'title'=>(string)$settings['title'] . syndication_filter_suffix($filter),
        'description'=>(string)$settings['description'],
        'self_url'=>publishing_absolute_url(($paths[$format] ?? $paths['rss']) . $query),
        'blog_url'=>publishing_absolute_url('blog.php' . ($filter['category'] !== '' ? '?category=' . rawurlencode($filter['category']) : '')),
        'directory_url'=>publishing_absolute_url('blog-feeds.php'),
        'last_modified'=>$lastModified,
    ];
}

function syndication_iso_date(?string $value): ?string
{
    $time = $value ? strtotime($value) : false;
    return $time ? gmdate(DATE_ATOM, $time) : null;
}

function syndication_render_json_feed(?array $filter = null): string
{
    $context = syndication_context('json', $filter);
    $items = [];
    foreach ($context['posts'] as $post) {
        $item = [
            'id' => 'nmm:blog:' . (int)$post['id'],
            'url' => syndication_post_url($post),
            'title' => (string)$post['title'],
            'content_html' => (string)$post['body_html'],
            'summary' => (string)$post['excerpt'],
            'date_published' => syndication_iso_date((string)$post['published_at']),
            'date_modified' => syndication_iso_date((string)$post['updated_at']),
            'authors' => [['name'=>(string)($post['author_name'] ?: 'David Evans')]],
            'tags' => array_values(array_unique(array_filter(array_merge(
                $post['category'] !== '' ? [(string)$post['category']] : [],
                is_array($post['tags']) ? $post['tags'] : []
            )))),
        ];
        if (!empty($post['cover']['id'])) {
            $item['image'] = publishing_absolute_url('blog-media.php?id=' . (int)$post['cover']['id']);
        }
        $audio = syndication_audio_attachment($post);
        if ($audio) $item['attachments'] = [array_filter($audio, static fn($value): bool => $value !== null && $value !== '')];
        $items[] = array_filter($item, static fn($value): bool => $value !== null && $value !== [] && $value !== '');
    }
    $feed = [
        'version'=>'https://jsonfeed.org/version/1.1',
        'user_comment'=>'This feed can be added to a compatible reader using its feed_url.',
        'title'=>$context['title'],
        'home_page_url'=>$context['blog_url'],
        'feed_url'=>$context['self_url'],
        'description'=>$context['description'],
        'language'=>$context['settings']['feed_language'],
        'authors'=>[['name'=>$context['settings']['podcast_author']]],
        'items'=>$items,
    ];
    if ($context['settings']['websub_enabled']) {
        $feed['hubs'] = [[
            'type'=>'WebSub',
            'url'=>$context['settings']['websub_hub_url'],
        ]];
    }
    return json_encode($feed, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . "\n";
}

function syndication_xml(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_XML1|ENT_QUOTES, 'UTF-8');
}

function syndication_cdata(string $value): string
{
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
}

function syndication_render_podcast_feed(): string
{
    $context = syndication_context('podcast', ['category'=>'','tag'=>'','author'=>'']);
    $settings = $context['settings'];
    $episodes = [];
    foreach ($context['posts'] as $post) {
        $audio = syndication_audio_attachment($post);
        if ($audio) $episodes[] = [$post,$audio];
    }
    $image = trim((string)$settings['podcast_image_url']);
    if ($image !== '' && !syndication_http_url($image)) $image = '';
    if ($image === '' && !empty($episodes[0][0]['cover']['id'])) {
        $image = publishing_absolute_url('blog-media.php?id=' . (int)$episodes[0][0]['cover']['id']);
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://podcastindex.org/namespace/1.0">' . "\n<channel>\n";
    $xml .= '<title>' . syndication_xml($settings['podcast_title']) . "</title>\n";
    $xml .= '<link>' . syndication_xml($context['blog_url']) . "</link>\n";
    $xml .= '<description>' . syndication_xml($context['description']) . "</description>\n";
    $xml .= '<language>' . syndication_xml($settings['feed_language']) . "</language>\n";
    $xml .= '<atom:link rel="self" type="application/rss+xml" href="' . syndication_xml($context['self_url']) . '" />' . "\n";
    if ($settings['websub_enabled']) $xml .= '<atom:link rel="hub" href="' . syndication_xml($settings['websub_hub_url']) . '" />' . "\n";
    $xml .= '<itunes:author>' . syndication_xml($settings['podcast_author']) . "</itunes:author>\n";
    $xml .= '<itunes:summary>' . syndication_xml($context['description']) . "</itunes:summary>\n";
    $xml .= '<itunes:explicit>' . ($settings['podcast_explicit'] ? 'true' : 'false') . "</itunes:explicit>\n";
    $xml .= '<itunes:type>' . syndication_xml($settings['podcast_type']) . "</itunes:type>\n";
    $xml .= '<itunes:category text="' . syndication_xml($settings['podcast_category']) . '" />' . "\n";
    if ($settings['podcast_owner_email'] !== '') {
        $xml .= '<podcast:locked owner="' . syndication_xml($settings['podcast_owner_email']) . '">no</podcast:locked>' . "\n";
        $xml .= '<itunes:owner><itunes:name>' . syndication_xml($settings['podcast_owner_name']) . '</itunes:name><itunes:email>' . syndication_xml($settings['podcast_owner_email']) . "</itunes:email></itunes:owner>\n";
    }
    if ($image !== '') $xml .= '<itunes:image href="' . syndication_xml($image) . '" />' . "\n";
    foreach ($episodes as [$post,$audio]) {
        $url = syndication_post_url($post);
        $published = strtotime((string)$post['published_at']) ?: time();
        $xml .= "<item>\n";
        $xml .= '<title>' . syndication_xml($post['title']) . "</title>\n";
        $xml .= '<link>' . syndication_xml($url) . "</link>\n";
        $xml .= '<guid isPermaLink="false">nmm:podcast:' . (int)$post['id'] . "</guid>\n";
        $xml .= '<description>' . syndication_xml($post['excerpt']) . "</description>\n";
        $xml .= '<content:encoded>' . syndication_cdata((string)$post['body_html']) . "</content:encoded>\n";
        $xml .= '<pubDate>' . gmdate(DATE_RSS, $published) . "</pubDate>\n";
        $xml .= '<enclosure url="' . syndication_xml($audio['url']) . '" length="' . (int)$audio['size_in_bytes'] . '" type="' . syndication_xml($audio['mime_type']) . '" />' . "\n";
        $xml .= '<itunes:author>' . syndication_xml((string)($post['author_name'] ?: $settings['podcast_author'])) . "</itunes:author>\n";
        $xml .= '<itunes:explicit>' . ($settings['podcast_explicit'] ? 'true' : 'false') . "</itunes:explicit>\n";
        if ($audio['duration_in_seconds']) $xml .= '<itunes:duration>' . (int)$audio['duration_in_seconds'] . "</itunes:duration>\n";
        if (!empty($post['cover']['id'])) $xml .= '<itunes:image href="' . syndication_xml(publishing_absolute_url('blog-media.php?id=' . (int)$post['cover']['id'])) . '" />' . "\n";
        $xml .= "</item>\n";
    }
    $xml .= "</channel>\n</rss>\n";
    return $xml;
}

function syndication_send(string $body, string $contentType, int $lastModified): never
{
    $etag = '"' . hash('sha256', $body) . '"';
    header('Content-Type: ' . $contentType . '; charset=UTF-8');
    header('Cache-Control: public, max-age=900, must-revalidate');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
    header('X-Content-Type-Options: nosniff');
    $match = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $modified = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) ?: 0;
    if (($match !== '' && (in_array($etag, array_map('trim', explode(',', $match)), true) || $match === '*'))
        || ($match === '' && $modified >= $lastModified)) {
        http_response_code(304);
        exit;
    }
    echo $body;
    exit;
}

function syndication_discovery_links(?array $filter = null, bool $includeWebmention = true): string
{
    $settings = syndication_settings();
    $filter ??= ['category'=>'','tag'=>'','author'=>''];
    $query = syndication_filter_query($filter);
    $links = [];
    if ($settings['rss_enabled']) $links[] = '<link rel="alternate" type="application/rss+xml" title="' . e($settings['title'] . ' RSS') . '" href="' . e(publishing_absolute_url('blog-feed.php' . $query)) . '">';
    if ($settings['atom_enabled']) $links[] = '<link rel="alternate" type="application/atom+xml" title="' . e($settings['title'] . ' Atom') . '" href="' . e(publishing_absolute_url('blog-atom.php' . $query)) . '">';
    if ($settings['json_enabled']) $links[] = '<link rel="alternate" type="application/feed+json" title="' . e($settings['title'] . ' JSON Feed') . '" href="' . e(publishing_absolute_url('blog-json-feed.php' . $query)) . '">';
    if ($settings['podcast_enabled']) $links[] = '<link rel="alternate" type="application/rss+xml" title="' . e($settings['podcast_title']) . '" href="' . e(publishing_absolute_url('podcast-feed.php')) . '">';
    if ($settings['websub_enabled']) $links[] = '<link rel="hub" href="' . e($settings['websub_hub_url']) . '">';
    if ($includeWebmention && $settings['webmention_enabled']) $links[] = '<link rel="webmention" href="' . e(publishing_absolute_url('webmention.php')) . '">';
    return implode("\n", $links);
}

function syndication_public_tags(): array
{
    $counts = [];
    foreach (syndication_public_posts(['category'=>'','tag'=>'','author'=>''], 100) as $post) {
        foreach ($post['tags'] as $tag) $counts[$tag] = ($counts[$tag] ?? 0) + 1;
    }
    ksort($counts, SORT_NATURAL|SORT_FLAG_CASE);
    return $counts;
}

function syndication_public_authors(): array
{
    if (!publishing_schema_available()) return [];
    try {
        return db()->query(
            'SELECT user.id,user.display_name,COUNT(*) AS post_count
             FROM blog_posts post JOIN users user ON user.id=post.author_user_id
             WHERE post.status="published" AND (post.published_at IS NULL OR post.published_at<=UTC_TIMESTAMP())
             GROUP BY user.id,user.display_name ORDER BY user.display_name,user.id'
        )->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

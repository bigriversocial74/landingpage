<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-webmention-v66E */

require_once __DIR__ . '/public-syndication.php';

function syndication_public_ip(string $ip): bool
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function syndication_public_url_resolution(string $url): ?array
{
    if (!syndication_http_url($url)) return null;
    $parts = parse_url($url);
    if (!is_array($parts)) return null;
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) return null;
    $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    if ($port <= 0 || $port > 65535) return null;
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (!empty($record['ip'])) $ips[] = (string)$record['ip'];
            if (!empty($record['ipv6'])) $ips[] = (string)$record['ipv6'];
        }
        if (!$ips) $ips = @gethostbynamel($host) ?: [];
    }
    $ips = array_values(array_unique(array_filter(array_map('strval', $ips))));
    if ($ips === [] || count(array_filter($ips, 'syndication_public_ip')) !== count($ips)) return null;
    return ['scheme'=>$scheme,'host'=>$host,'port'=>$port,'ips'=>$ips];
}

function syndication_public_url_host(string $url): bool
{
    return syndication_public_url_resolution($url) !== null;
}

function syndication_curl_resolve(array $resolution): array
{
    $host = (string)($resolution['host'] ?? '');
    $port = (int)($resolution['port'] ?? 0);
    $ip = (string)(($resolution['ips'][0] ?? ''));
    if ($host === '' || $port <= 0 || $ip === '') return [];
    $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
    return [$host . ':' . $port . ':' . $address];
}

function syndication_absolute_location(string $base, string $location): string
{
    $location = trim($location);
    if ($location === '') return '';
    if (syndication_http_url($location)) return $location;
    $parts = parse_url($base);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';
    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    if (str_starts_with($location, '//')) return $parts['scheme'] . ':' . $location;
    if (str_starts_with($location, '/')) return $origin . $location;
    $path = (string)($parts['path'] ?? '/');
    $directory = preg_replace('~/[^/]*$~', '/', $path) ?: '/';
    return $origin . $directory . $location;
}

function syndication_fetch_public_html(string $url, int $maxRedirects = 3): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The cURL extension is required to verify Webmentions.');
    }
    $current = $url;
    for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
        $resolution = syndication_public_url_resolution($current);
        if (!$resolution) {
            throw new RuntimeException('The Webmention source must resolve only to public HTTP or HTTPS addresses.');
        }
        $headers = [];
        $body = '';
        $handle = curl_init($current);
        if ($handle === false) throw new RuntimeException('The Webmention source could not be opened.');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => syndication_curl_resolve($resolution),
            CURLOPT_USERAGENT => 'NorthMountainMedia-Webmention/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 1024 * 1024) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower((string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
        $error = curl_error($handle);
        curl_close($handle);
        if ($ok === false) throw new RuntimeException('The Webmention source could not be verified: ' . ($error ?: 'download failed'));
        if (in_array($status, [301,302,303,307,308], true)) {
            $next = syndication_absolute_location($current, (string)($headers['location'] ?? ''));
            if ($next === '') throw new RuntimeException('The Webmention source returned an invalid redirect.');
            $current = $next;
            continue;
        }
        if ($status < 200 || $status >= 300) throw new RuntimeException('The Webmention source returned HTTP ' . $status . '.');
        if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml+xml')) {
            throw new RuntimeException('The Webmention source is not an HTML document.');
        }
        if ($body === '') throw new RuntimeException('The Webmention source returned an empty document.');
        return ['url'=>$current,'status'=>$status,'content_type'=>$contentType,'html'=>$body];
    }
    throw new RuntimeException('The Webmention source redirected too many times.');
}

function syndication_normalize_url(string $url): string
{
    $parts = parse_url(trim($url));
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return trim($url);
    $scheme = strtolower((string)$parts['scheme']);
    $host = strtolower((string)$parts['host']);
    $port = isset($parts['port']) && !in_array((int)$parts['port'], [$scheme === 'https' ? 443 : 80], true)
        ? ':' . (int)$parts['port'] : '';
    $path = (string)($parts['path'] ?? '/');
    if ($path === '') $path = '/';
    return $scheme . '://' . $host . $port . $path
        . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

function syndication_webmention_target_post(string $target): ?array
{
    if (!syndication_http_url($target)) return null;
    $base = parse_url(publishing_absolute_url('index.php'));
    $parts = parse_url($target);
    if (!is_array($base) || !is_array($parts)) return null;
    if (strtolower((string)($parts['host'] ?? '')) !== strtolower((string)($base['host'] ?? ''))) return null;
    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    $slug = trim((string)($query['slug'] ?? ''));
    if ($slug !== '') {
        $post = blog_public_post_by_slug($slug);
        if (!$post) return null;
        $allowed = [
            syndication_normalize_url(
                publishing_absolute_url('blog-post.php?slug=' . rawurlencode((string)$post['slug']))
            ),
        ];
        $canonical = trim((string)($post['canonical_url'] ?? ''));
        if ($canonical !== '' && syndication_http_url($canonical)) {
            $allowed[] = syndication_normalize_url($canonical);
        }
        return in_array(syndication_normalize_url($target), array_values(array_unique($allowed)), true)
            ? $post
            : null;
    }
    try {
        $statement = db()->prepare(
            'SELECT post.*,user.display_name AS author_name
             FROM blog_posts post LEFT JOIN users user ON user.id=post.author_user_id
             WHERE post.canonical_url=:target AND post.status="published"
               AND (post.published_at IS NULL OR post.published_at<=UTC_TIMESTAMP())
             LIMIT 1'
        );
        $statement->execute(['target'=>$target]);
        $post = $statement->fetch();
        return $post ? blog_post_payload($post, blog_post_media((int)$post['id'])) : null;
    } catch (Throwable) {
        return null;
    }
}

function syndication_xpath_first(DOMXPath $xpath, array $queries): ?DOMNode
{
    foreach ($queries as $query) {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) return $nodes->item(0);
    }
    return null;
}

function syndication_node_value(?DOMNode $node, string $attribute = ''): string
{
    if (!$node) return '';
    if ($attribute !== '' && $node instanceof DOMElement) return trim((string)$node->getAttribute($attribute));
    return trim(preg_replace('/\s+/u', ' ', (string)$node->textContent) ?? '');
}

function syndication_webmention_metadata(string $html, string $source, string $target): array
{
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) throw new RuntimeException('The Webmention source HTML could not be parsed.');
    $xpath = new DOMXPath($document);
    $targetNormalized = syndication_normalize_url($target);
    $linked = false;
    $mentionType = 'mention';
    foreach ($xpath->query('//a[@href] | //link[@href]') ?: [] as $node) {
        if (!$node instanceof DOMElement) continue;
        $href = syndication_absolute_location($source, $node->getAttribute('href'));
        if (syndication_normalize_url($href) !== $targetNormalized) continue;
        $linked = true;
        $classes = ' ' . preg_replace('/\s+/', ' ', $node->getAttribute('class')) . ' ';
        if (str_contains($classes, ' u-like-of ')) $mentionType = 'like';
        elseif (str_contains($classes, ' u-repost-of ')) $mentionType = 'repost';
        elseif (str_contains($classes, ' u-in-reply-to ')) $mentionType = 'reply';
    }
    if (!$linked) throw new RuntimeException('The Webmention source does not link to the target article.');
    $titleNode = syndication_xpath_first($xpath, [
        '//*[contains(concat(" ",normalize-space(@class)," ")," h-entry ")]//*[contains(concat(" ",normalize-space(@class)," ")," p-name ")][1]',
        '//meta[@property="og:title"][1]',
        '//title[1]',
    ]);
    $title = $titleNode instanceof DOMElement && strtolower($titleNode->tagName) === 'meta'
        ? syndication_node_value($titleNode, 'content') : syndication_node_value($titleNode);
    $authorNode = syndication_xpath_first($xpath, [
        '//*[contains(concat(" ",normalize-space(@class)," ")," p-author ")][1]',
        '//*[contains(concat(" ",normalize-space(@class)," ")," h-card ")]//*[contains(concat(" ",normalize-space(@class)," ")," p-name ")][1]',
        '//meta[@name="author"][1]',
    ]);
    $author = $authorNode instanceof DOMElement && strtolower($authorNode->tagName) === 'meta'
        ? syndication_node_value($authorNode, 'content') : syndication_node_value($authorNode);
    $authorUrlNode = syndication_xpath_first($xpath, [
        '//*[contains(concat(" ",normalize-space(@class)," ")," h-card ")]//*[contains(concat(" ",normalize-space(@class)," ")," u-url ")][1]',
        '//link[@rel="author"][1]',
    ]);
    $authorUrl = $authorUrlNode instanceof DOMElement
        ? syndication_absolute_location($source, $authorUrlNode->getAttribute('href')) : '';
    if ($authorUrl !== '' && !syndication_http_url($authorUrl)) $authorUrl = '';
    $photoNode = syndication_xpath_first($xpath, [
        '//*[contains(concat(" ",normalize-space(@class)," ")," h-card ")]//*[contains(concat(" ",normalize-space(@class)," ")," u-photo ")][1]',
    ]);
    $photo = '';
    if ($photoNode instanceof DOMElement) {
        $photo = syndication_absolute_location($source, $photoNode->getAttribute('src') ?: $photoNode->getAttribute('href'));
        if (!syndication_http_url($photo)) $photo = '';
    }
    $contentNode = syndication_xpath_first($xpath, [
        '//*[contains(concat(" ",normalize-space(@class)," ")," e-content ")][1]',
        '//*[contains(concat(" ",normalize-space(@class)," ")," p-content ")][1]',
        '//meta[@name="description"][1]',
    ]);
    $excerpt = $contentNode instanceof DOMElement && strtolower($contentNode->tagName) === 'meta'
        ? syndication_node_value($contentNode, 'content') : syndication_node_value($contentNode);
    return [
        'mention_type'=>$mentionType,
        'author_name'=>mb_substr($author, 0, 190),
        'author_url'=>mb_substr($authorUrl, 0, 1000),
        'author_photo_url'=>mb_substr($photo, 0, 1000),
        'source_title'=>mb_substr($title, 0, 500),
        'source_excerpt'=>mb_substr($excerpt, 0, 2000),
        'source_content_hash'=>hash('sha256', $html),
    ];
}

function syndication_receive_webmention(string $source, string $target): int
{
    if (!syndication_schema_available()) throw new RuntimeException('Public syndication schema is not installed.');
    $source = mb_substr(trim($source), 0, 1000);
    $target = mb_substr(trim($target), 0, 1000);
    if ($source === '' || $target === '' || !syndication_http_url($source) || !syndication_http_url($target)) {
        throw new RuntimeException('Valid source and target HTTP URLs are required.');
    }
    if (hash_equals(syndication_normalize_url($source), syndication_normalize_url($target))) {
        throw new RuntimeException('The Webmention source and target must be different.');
    }
    $post = syndication_webmention_target_post($target);
    if (!$post) throw new RuntimeException('The Webmention target is not a published local article.');
    $fetched = syndication_fetch_public_html($source);
    $metadata = syndication_webmention_metadata((string)$fetched['html'], (string)$fetched['url'], $target);
    $statement = db()->prepare(
        'INSERT INTO syndication_webmentions
            (source_url,target_url,target_post_id,mention_type,status,author_name,author_url,
             author_photo_url,source_title,source_excerpt,source_content_hash,verified_at)
         VALUES
            (:source_url,:target_url,:target_post_id,:mention_type,"pending",:author_name,:author_url,
             :author_photo_url,:source_title,:source_excerpt,:source_content_hash,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            target_post_id=VALUES(target_post_id),mention_type=VALUES(mention_type),
            author_name=VALUES(author_name),author_url=VALUES(author_url),
            author_photo_url=VALUES(author_photo_url),source_title=VALUES(source_title),
            source_excerpt=VALUES(source_excerpt),source_content_hash=VALUES(source_content_hash),
            verification_attempts=verification_attempts+1,verification_error=NULL,verified_at=UTC_TIMESTAMP(),
            status=CASE WHEN status IN ("spam","rejected") THEN status ELSE "pending" END'
    );
    $statement->execute($metadata + [
        'source_url'=>(string)$fetched['url'],
        'target_url'=>$target,
        'target_post_id'=>(int)$post['id'],
    ]);
    $id = (int)db()->lastInsertId();
    if ($id <= 0) {
        $lookup = db()->prepare('SELECT id FROM syndication_webmentions WHERE source_url=:source AND target_url=:target LIMIT 1');
        $lookup->execute(['source'=>(string)$fetched['url'],'target'=>$target]);
        $id = (int)$lookup->fetchColumn();
    }
    return $id;
}

function syndication_approved_webmentions(int $postId): array
{
    if (!syndication_schema_available() || $postId <= 0) return [];
    $statement = db()->prepare(
        'SELECT * FROM syndication_webmentions
         WHERE target_post_id=:post_id AND status="approved"
         ORDER BY received_at ASC,id ASC'
    );
    $statement->execute(['post_id'=>$postId]);
    return $statement->fetchAll();
}

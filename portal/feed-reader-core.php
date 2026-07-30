<?php
declare(strict_types=1);

/* North Mountain Media build: 20260728-rss-feed-reader-v62 */

function feed_reader_schema_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "feed_folders",
                    "feed_sources",
                    "feed_subscriptions",
                    "feed_items",
                    "feed_item_states",
                    "feed_refresh_runs"
               )'
        );
        $available = (int)$statement->fetchColumn() === 6;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function feed_reader_config(): array
{
    $config = nmm_config('feed_reader');

    return [
        'enabled' => ($config['enabled'] ?? true) !== false
            && setting('feed_reader_enabled', '1') !== '0'
            && (!function_exists('nmm_module_enabled') || nmm_module_enabled('feed_reader', true)),
        'cron_token' => trim((string)($config['cron_token'] ?? '')),
        'refresh_minutes' => max(
            5,
            min(
                1440,
                (int)($config['refresh_minutes'] ?? setting('feed_refresh_minutes', '30'))
            )
        ),
        'max_sources_per_user' => max(
            5,
            min(500, (int)($config['max_sources_per_user'] ?? 100))
        ),
        'max_response_bytes' => max(
            262144,
            min(10485760, (int)($config['max_response_bytes'] ?? 2097152))
        ),
        'connect_timeout_seconds' => max(
            2,
            min(20, (int)($config['connect_timeout_seconds'] ?? 5))
        ),
        'request_timeout_seconds' => max(
            5,
            min(60, (int)($config['request_timeout_seconds'] ?? 20))
        ),
        'max_redirects' => max(
            0,
            min(8, (int)($config['max_redirects'] ?? 5))
        ),
        'max_items_per_feed' => max(
            20,
            min(500, (int)($config['max_items_per_feed'] ?? 200))
        ),
        'refresh_batch_size' => max(
            1,
            min(100, (int)($config['refresh_batch_size'] ?? 20))
        ),
        'allowed_ports' => array_values(array_unique(array_map(
            'intval',
            is_array($config['allowed_ports'] ?? null)
                ? $config['allowed_ports']
                : [80, 443]
        ))),
        'user_agent' => trim((string)(
            $config['user_agent']
            ?? 'NorthMountainMediaFeedReader/62 (+feed subscription service)'
        )),
    ];
}

function feed_reader_require_enabled(): void
{
    if (!feed_reader_config()['enabled']) {
        throw new RuntimeException('The Feed Reader module is currently disabled.');
    }
}

function feed_reader_limit_text(mixed $value, int $limit): string
{
    $value = trim((string)$value);
    return mb_substr($value, 0, max(0, $limit));
}

function feed_reader_normalize_url(string $url): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($url === '' || strlen($url) > 2000) {
        throw new RuntimeException('Enter a valid feed URL no longer than 2,000 characters.');
    }

    $parts = parse_url($url);

    if (!is_array($parts)) {
        throw new RuntimeException('The feed URL could not be parsed.');
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower(rtrim(trim((string)($parts['host'] ?? ''), '[]'), '.'));
    if (preg_match('/[^\x20-\x7E]/', $host)) {
        if (!function_exists('idn_to_ascii')) {
            throw new RuntimeException('Internationalized feed hostnames require the PHP intl extension.');
        }
        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (!is_string($ascii) || $ascii === '') {
            throw new RuntimeException('The internationalized feed hostname is invalid.');
        }
        $host = strtolower($ascii);
    }
    $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);

    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new RuntimeException('Feed URLs must use HTTP or HTTPS.');
    }

    if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('The feed URL must contain a public hostname without embedded credentials.');
    }

    $allowedPorts = feed_reader_config()['allowed_ports'];
    if (!in_array($port, $allowedPorts, true)) {
        throw new RuntimeException('The feed URL uses a network port that is not permitted.');
    }

    $path = (string)($parts['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }

    $path = preg_replace('#/+#', '/', $path) ?? $path;
    $query = isset($parts['query']) && $parts['query'] !== ''
        ? '?' . $parts['query']
        : '';
    $portPart = (
        ($scheme === 'https' && $port === 443)
        || ($scheme === 'http' && $port === 80)
    ) ? '' : ':' . $port;

    $hostPart = str_contains($host, ':') ? '[' . $host . ']' : $host;
    return $scheme . '://' . $hostPart . $portPart . $path . $query;
}

function feed_reader_url_hash(string $url): string
{
    return hash('sha256', feed_reader_normalize_url($url));
}

function feed_reader_ip_is_public(string $ip): bool
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function feed_reader_resolve_public_ips(string $host): array
{
    $host = strtolower(rtrim(trim($host, " []\t\n\r\0\x0B"), '.'));

    if (
        $host === ''
        || $host === 'localhost'
        || str_ends_with($host, '.localhost')
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.internal')
        || str_ends_with($host, '.home')
    ) {
        throw new RuntimeException('Local and private feed hosts are not permitted.');
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!feed_reader_ip_is_public($host)) {
            throw new RuntimeException('Private and reserved network addresses are not permitted.');
        }
        return [$host];
    }

    $ips = [];
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);

    if (is_array($records)) {
        foreach ($records as $record) {
            $candidate = (string)($record['ip'] ?? $record['ipv6'] ?? '');
            if ($candidate !== '') {
                $ips[] = $candidate;
            }
        }
    }

    if (!$ips) {
        $fallback = @gethostbynamel($host);
        if (is_array($fallback)) {
            $ips = array_merge($ips, $fallback);
        }
    }

    $ips = array_values(array_unique(array_filter($ips)));

    if (!$ips) {
        throw new RuntimeException('The feed hostname could not be resolved.');
    }

    foreach ($ips as $ip) {
        if (!feed_reader_ip_is_public($ip)) {
            throw new RuntimeException('The feed hostname resolves to a private or reserved network address.');
        }
    }

    return $ips;
}

function feed_reader_validate_remote_url(string $url): array
{
    $url = feed_reader_normalize_url($url);
    $parts = parse_url($url);

    if (!is_array($parts)) {
        throw new RuntimeException('The feed URL could not be parsed.');
    }

    $host = trim((string)$parts['host'], '[]');
    $scheme = (string)$parts['scheme'];
    $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
    $ips = feed_reader_resolve_public_ips($host);

    return [
        'url' => $url,
        'scheme' => $scheme,
        'host' => $host,
        'port' => $port,
        'ips' => $ips,
    ];
}

function feed_reader_resolve_url(string $baseUrl, string $candidate): string
{
    $candidate = trim(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($candidate === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $candidate)) {
        try {
            return feed_reader_normalize_url($candidate);
        } catch (Throwable) {
            return '';
        }
    }

    if (str_starts_with($candidate, '//')) {
        $scheme = (string)(parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https');
        try {
            return feed_reader_normalize_url($scheme . ':' . $candidate);
        } catch (Throwable) {
            return '';
        }
    }

    if (preg_match('#^(mailto|javascript|data|file|ftp):#i', $candidate)) {
        return '';
    }

    $base = parse_url($baseUrl);
    if (!is_array($base) || empty($base['host'])) {
        return '';
    }

    $scheme = (string)($base['scheme'] ?? 'https');
    $baseHost = trim((string)$base['host'], '[]');
    $authority = $scheme . '://' . (str_contains($baseHost, ':') ? '[' . $baseHost . ']' : $baseHost);
    if (isset($base['port'])) {
        $authority .= ':' . (int)$base['port'];
    }

    if (str_starts_with($candidate, '/')) {
        $path = $candidate;
    } else {
        $basePath = (string)($base['path'] ?? '/');
        $directory = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';
        $path = $directory . $candidate;
    }

    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    try {
        return feed_reader_normalize_url($authority . '/' . implode('/', $segments));
    } catch (Throwable) {
        return '';
    }
}

function feed_reader_fetch(
    string $url,
    ?string $etag = null,
    ?string $lastModified = null
): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required to retrieve external feeds.');
    }

    $config = feed_reader_config();
    $currentUrl = feed_reader_normalize_url($url);
    $redirects = 0;

    while (true) {
        $validated = feed_reader_validate_remote_url($currentUrl);
        $headers = [];
        $body = '';
        $overflow = false;
        $maxBytes = (int)$config['max_response_bytes'];
        $responseHeaders = [];
        $status = 0;

        $requestHeaders = [
            'Accept: application/atom+xml, application/rss+xml, application/rdf+xml, application/xml, text/xml;q=0.9, */*;q=0.2',
            'Accept-Encoding: gzip, deflate',
        ];

        if ($etag !== null && trim($etag) !== '') {
            $requestHeaders[] = 'If-None-Match: ' . trim($etag);
        }
        if ($lastModified !== null && trim($lastModified) !== '') {
            $requestHeaders[] = 'If-Modified-Since: ' . trim($lastModified);
        }

        $ip = (string)$validated['ips'][0];
        $resolveIp = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
        $resolve = $validated['host'] . ':' . $validated['port'] . ':' . $resolveIp;
        $handle = curl_init($currentUrl);

        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => (int)$config['connect_timeout_seconds'],
            CURLOPT_TIMEOUT => (int)$config['request_timeout_seconds'],
            CURLOPT_USERAGENT => (string)$config['user_agent'],
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$resolve],
            CURLOPT_PROXY => '',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers, &$responseHeaders, &$status): int {
                $length = strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '') {
                    return $length;
                }
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $trimmed, $matches)) {
                    $status = (int)$matches[1];
                    $headers = [];
                    $responseHeaders = [];
                    return $length;
                }
                $position = strpos($trimmed, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($trimmed, 0, $position)));
                    $value = trim(substr($trimmed, $position + 1));
                    $headers[$name] = isset($headers[$name])
                        ? $headers[$name] . ', ' . $value
                        : $value;
                    $responseHeaders[] = [$name, $value];
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$overflow, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $overflow = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_NOPROXY')) {
            $options[CURLOPT_NOPROXY] = '*';
        }

        curl_setopt_array($handle, $options);
        $success = curl_exec($handle);
        $error = curl_error($handle);
        $curlStatus = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);

        if ($status <= 0) {
            $status = $curlStatus;
        }

        if ($overflow) {
            throw new RuntimeException('The feed response exceeded the configured size limit.');
        }

        if ($success === false && $status === 0) {
            throw new RuntimeException('The feed request failed: ' . feed_reader_limit_text($error, 300));
        }

        if (isset($headers['content-length']) && (int)$headers['content-length'] > $maxBytes) {
            throw new RuntimeException('The feed response is larger than the configured size limit.');
        }

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $location = trim((string)($headers['location'] ?? ''));
            if ($location === '') {
                throw new RuntimeException('The feed returned a redirect without a destination.');
            }
            if ($redirects >= (int)$config['max_redirects']) {
                throw new RuntimeException('The feed exceeded the redirect limit.');
            }
            $next = feed_reader_resolve_url($currentUrl, $location);
            if ($next === '') {
                throw new RuntimeException('The feed redirected to an invalid destination.');
            }
            $currentUrl = $next;
            $redirects++;
            continue;
        }

        if ($status === 304) {
            return [
                'status' => 304,
                'url' => $currentUrl,
                'body' => '',
                'content_type' => $contentType,
                'etag' => trim((string)($headers['etag'] ?? $etag ?? '')),
                'last_modified' => trim((string)($headers['last-modified'] ?? $lastModified ?? '')),
                'headers' => $responseHeaders,
            ];
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('The feed returned HTTP ' . $status . '.');
        }

        if (trim($body) === '') {
            throw new RuntimeException('The feed returned an empty response.');
        }

        return [
            'status' => $status,
            'url' => $currentUrl,
            'body' => $body,
            'content_type' => $contentType,
            'etag' => trim((string)($headers['etag'] ?? '')),
            'last_modified' => trim((string)($headers['last-modified'] ?? '')),
            'headers' => $responseHeaders,
        ];
    }
}

function feed_reader_xml_text(?DOMNode $node): string
{
    return $node ? trim((string)$node->textContent) : '';
}

function feed_reader_xpath_first(DOMXPath $xpath, string $expression, ?DOMNode $context = null): ?DOMNode
{
    $nodes = $xpath->query($expression, $context);
    return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
}

function feed_reader_node_inner_xml(?DOMNode $node, bool $textMarkup = false): string
{
    if (!$node || !$node->ownerDocument) {
        return '';
    }

    if ($textMarkup && $node->childNodes->length === 1) {
        $child = $node->firstChild;
        if ($child && in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
            return trim((string)$child->nodeValue);
        }
    }

    $html = '';
    foreach ($node->childNodes as $child) {
        if (in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
            $html .= $textMarkup
                ? (string)$child->nodeValue
                : (string)$node->ownerDocument->saveHTML($child);
        } else {
            $html .= (string)$node->ownerDocument->saveHTML($child);
        }
    }
    return trim($html);
}

function feed_reader_safe_content_url(string $url, string $baseUrl): string
{
    $resolved = feed_reader_resolve_url($baseUrl, $url);
    if ($resolved === '') {
        return '';
    }

    $scheme = strtolower((string)parse_url($resolved, PHP_URL_SCHEME));
    $host = strtolower(rtrim(trim((string)parse_url($resolved, PHP_URL_HOST), '[]'), '.'));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return '';
    }
    if (
        $host === 'localhost'
        || str_ends_with($host, '.localhost')
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.internal')
        || str_ends_with($host, '.home')
    ) {
        return '';
    }
    if (filter_var($host, FILTER_VALIDATE_IP) && !feed_reader_ip_is_public($host)) {
        return '';
    }
    return $resolved;
}

function feed_reader_discover_feed_url(string $html, string $pageUrl): string
{
    if (trim($html) === '') {
        return '';
    }

    $candidates = [];
    if (class_exists('DOMDocument')) {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($loaded) {
            foreach ($document->getElementsByTagName('link') as $link) {
                $rel = strtolower(trim($link->getAttribute('rel')));
                $type = strtolower(trim($link->getAttribute('type')));
                if (str_contains($rel, 'alternate') && in_array($type, [
                    'application/rss+xml',
                    'application/atom+xml',
                    'application/rdf+xml',
                    'text/xml',
                    'application/xml',
                ], true)) {
                    $candidates[] = $link->getAttribute('href');
                }
            }
        }
    }

    if (!$candidates && preg_match_all(
        "#<link\\b[^>]*\\b(?:type=['\"](?:application/(?:rss|atom|rdf)\\+xml|application/xml|text/xml)['\"])[^>]*>#i",
        $html,
        $matches
    )) {
        foreach ($matches[0] as $tag) {
            if (preg_match("#\\bhref=['\"]([^'\"]+)['\"]#i", $tag, $href)) {
                $candidates[] = $href[1];
            }
        }
    }

    foreach (array_slice(array_values(array_unique($candidates)), 0, 10) as $candidate) {
        $resolved = feed_reader_resolve_url($pageUrl, (string)$candidate);
        if ($resolved === '') {
            continue;
        }
        try {
            feed_reader_validate_remote_url($resolved);
            return $resolved;
        } catch (Throwable) {
            continue;
        }
    }
    return '';
}

function feed_reader_sanitize_html(string $html, string $baseUrl): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return nl2br(e(strip_tags($html)));
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?><div data-feed-root>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return nl2br(e(strip_tags($html)));
    }

    $allowed = [
        'div','p','br','h1','h2','h3','h4','h5','h6','strong','b','em','i','u','s',
        'blockquote','pre','code','ul','ol','li','a','img','figure','figcaption','hr',
        'table','thead','tbody','tfoot','tr','th','td','span','sup','sub'
    ];
    $dangerous = [
        'script','style','iframe','object','embed','form','input','button','textarea','select',
        'option','svg','math','canvas','video','audio','source','link','meta','base'
    ];

    $nodes = [];
    foreach ($document->getElementsByTagName('*') as $node) {
        $nodes[] = $node;
    }

    foreach (array_reverse($nodes) as $node) {
        $tag = strtolower($node->tagName);
        if ($node->hasAttribute('data-feed-root')) {
            continue;
        }

        if (in_array($tag, $dangerous, true)) {
            $node->parentNode?->removeChild($node);
            continue;
        }

        if (!in_array($tag, $allowed, true)) {
            $parent = $node->parentNode;
            if ($parent) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            }
            continue;
        }

        $attributes = [];
        foreach ($node->attributes ?? [] as $attribute) {
            $attributes[] = $attribute->name;
        }

        foreach ($attributes as $attribute) {
            $keep = false;
            if ($tag === 'a' && in_array($attribute, ['href','title'], true)) {
                $keep = true;
            }
            if ($tag === 'img' && in_array($attribute, ['src','alt','title','width','height'], true)) {
                $keep = true;
            }
            if (in_array($tag, ['th','td'], true) && in_array($attribute, ['colspan','rowspan'], true)) {
                $keep = true;
            }
            if (!$keep) {
                $node->removeAttribute($attribute);
            }
        }

        if ($tag === 'a') {
            $href = feed_reader_safe_content_url($node->getAttribute('href'), $baseUrl);
            if ($href === '') {
                $node->removeAttribute('href');
            } else {
                $node->setAttribute('href', $href);
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }

        if ($tag === 'img') {
            $src = feed_reader_safe_content_url($node->getAttribute('src'), $baseUrl);
            if ($src === '') {
                $node->parentNode?->removeChild($node);
                continue;
            }
            $node->setAttribute('src', $src);
            $node->setAttribute('loading', 'lazy');
            $node->setAttribute('decoding', 'async');
            $node->setAttribute('referrerpolicy', 'no-referrer');
        }
    }

    $root = null;
    foreach ($document->getElementsByTagName('div') as $candidate) {
        if ($candidate->hasAttribute('data-feed-root')) {
            $root = $candidate;
            break;
        }
    }

    return feed_reader_node_inner_xml($root);
}

function feed_reader_extract_first_image(string $html, string $baseUrl): string
{
    if (preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $matches)) {
        return feed_reader_safe_content_url($matches[1], $baseUrl);
    }
    return '';
}

function feed_reader_parse_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
}

function feed_reader_parse_feed(string $xml, string $feedUrl): array
{
    if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        throw new RuntimeException('The PHP DOM/XML extension is required to parse RSS and Atom feeds.');
    }
    if (strlen($xml) > (int)feed_reader_config()['max_response_bytes']) {
        throw new RuntimeException('The feed response exceeded the configured size limit.');
    }

    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
        throw new RuntimeException('Feeds containing document type or entity declarations are not accepted.');
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML(
        $xml,
        LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_NOBLANKS
    );
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || !$document->documentElement) {
        $message = $errors ? trim((string)$errors[0]->message) : 'Invalid XML.';
        throw new RuntimeException('The feed XML could not be parsed: ' . feed_reader_limit_text($message, 240));
    }

    $xpath = new DOMXPath($document);
    $rootName = strtolower((string)$document->documentElement->localName);
    $format = 'unknown';
    $container = null;
    $itemNodes = null;

    if ($rootName === 'rss') {
        $format = 'rss';
        $container = feed_reader_xpath_first($xpath, '/*[local-name()="rss"]/*[local-name()="channel"]');
        $itemNodes = $xpath->query('./*[local-name()="item"]', $container);
    } elseif ($rootName === 'feed') {
        $format = 'atom';
        $container = $document->documentElement;
        $itemNodes = $xpath->query('./*[local-name()="entry"]', $container);
    } elseif ($rootName === 'rdf' || $rootName === 'rdf:rdf') {
        $format = 'rdf';
        $container = feed_reader_xpath_first($xpath, '/*[local-name()="RDF"]/*[local-name()="channel"]');
        $itemNodes = $xpath->query('/*[local-name()="RDF"]/*[local-name()="item"]');
    } else {
        throw new RuntimeException('The document is not a supported RSS, Atom, or RDF feed.');
    }

    if (!$container || !$itemNodes) {
        throw new RuntimeException('The feed does not contain a readable channel or entry collection.');
    }

    $feedTitle = feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="title"]', $container));
    $feedDescription = feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="description" or local-name()="subtitle"]', $container));
    $language = feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="language"]', $container));
    $siteUrl = '';
    $imageUrl = '';

    if ($format === 'atom') {
        $links = $xpath->query('./*[local-name()="link"]', $container);
        foreach ($links ?: [] as $linkNode) {
            if (!$linkNode instanceof DOMElement) {
                continue;
            }
            $rel = strtolower($linkNode->getAttribute('rel') ?: 'alternate');
            if ($rel === 'alternate' && $siteUrl === '') {
                $siteUrl = feed_reader_safe_content_url($feedUrl, $linkNode->getAttribute('href'));
            }
        }
        $icon = feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="icon" or local-name()="logo"]', $container));
        $imageUrl = feed_reader_safe_content_url($feedUrl, $icon);
    } else {
        $siteUrl = feed_reader_safe_content_url(
            $feedUrl,
            feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="link"]', $container))
        );
        $imageUrl = feed_reader_safe_content_url(
            $feedUrl,
            feed_reader_xml_text(feed_reader_xpath_first(
                $xpath,
                './*[local-name()="image"]/*[local-name()="url"]',
                $container
            ))
        );
    }

    $items = [];
    $maximum = (int)feed_reader_config()['max_items_per_feed'];

    foreach ($itemNodes as $node) {
        if (count($items) >= $maximum || !$node instanceof DOMElement) {
            break;
        }

        $title = feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="title"]', $node));
        $link = '';
        $guid = '';
        $summaryRaw = '';
        $contentRaw = '';
        $author = '';
        $published = null;
        $updated = null;
        $categories = [];
        $enclosureUrl = '';
        $enclosureType = '';
        $enclosureLength = null;
        $itemImage = '';

        if ($format === 'atom') {
            $links = $xpath->query('./*[local-name()="link"]', $node);
            foreach ($links ?: [] as $linkNode) {
                if (!$linkNode instanceof DOMElement) {
                    continue;
                }
                $rel = strtolower($linkNode->getAttribute('rel') ?: 'alternate');
                $href = feed_reader_safe_content_url($feedUrl, $linkNode->getAttribute('href'));
                if ($rel === 'alternate' && $link === '') {
                    $link = $href;
                }
                if ($rel === 'enclosure' && $enclosureUrl === '') {
                    $enclosureUrl = $href;
                    $enclosureType = feed_reader_limit_text($linkNode->getAttribute('type'), 190);
                    $length = (int)$linkNode->getAttribute('length');
                    $enclosureLength = $length > 0 ? $length : null;
                }
            }
            $guid = feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="id"]', $node));
            $summaryNode = feed_reader_xpath_first($xpath, './*[local-name()="summary"]', $node);
            $contentNode = feed_reader_xpath_first($xpath, './*[local-name()="content"]', $node);
            $summaryRaw = $summaryNode ? feed_reader_node_inner_xml($summaryNode, true) : '';
            $contentRaw = $contentNode ? feed_reader_node_inner_xml($contentNode, true) : '';
            $author = feed_reader_xml_text(feed_reader_xpath_first(
                $xpath,
                './*[local-name()="author"]/*[local-name()="name"]',
                $node
            ));
            $published = feed_reader_parse_date(feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="published"]', $node)));
            $updated = feed_reader_parse_date(feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="updated"]', $node)));
            $categoryNodes = $xpath->query('./*[local-name()="category"]', $node);
            foreach ($categoryNodes ?: [] as $categoryNode) {
                if ($categoryNode instanceof DOMElement) {
                    $category = trim($categoryNode->getAttribute('term') ?: $categoryNode->textContent);
                    if ($category !== '') {
                        $categories[] = feed_reader_limit_text($category, 120);
                    }
                }
            }
        } else {
            $link = feed_reader_safe_content_url(
                $feedUrl,
                feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="link"]', $node))
            );
            $guid = feed_reader_xml_text(feed_reader_xpath_first($xpath, './*[local-name()="guid"]', $node));
            $summaryRaw = feed_reader_node_inner_xml(feed_reader_xpath_first($xpath, './*[local-name()="description"]', $node), true);
            $contentRaw = feed_reader_node_inner_xml(feed_reader_xpath_first($xpath, './*[local-name()="encoded"]', $node), true);
            $author = feed_reader_xml_text(feed_reader_xpath_first(
                $xpath,
                './*[local-name()="creator" or local-name()="author"]',
                $node
            ));
            $published = feed_reader_parse_date(feed_reader_xml_text(feed_reader_xpath_first(
                $xpath,
                './*[local-name()="pubDate" or local-name()="date" or local-name()="published"]',
                $node
            )));
            $updated = feed_reader_parse_date(feed_reader_xml_text(feed_reader_xpath_first(
                $xpath,
                './*[local-name()="updated" or local-name()="modified"]',
                $node
            )));
            $categoryNodes = $xpath->query('./*[local-name()="category"]', $node);
            foreach ($categoryNodes ?: [] as $categoryNode) {
                $category = trim((string)$categoryNode->textContent);
                if ($category !== '') {
                    $categories[] = feed_reader_limit_text($category, 120);
                }
            }
            $enclosure = feed_reader_xpath_first($xpath, './*[local-name()="enclosure"]', $node);
            if ($enclosure instanceof DOMElement) {
                $enclosureUrl = feed_reader_safe_content_url($feedUrl, $enclosure->getAttribute('url'));
                $enclosureType = feed_reader_limit_text($enclosure->getAttribute('type'), 190);
                $length = (int)$enclosure->getAttribute('length');
                $enclosureLength = $length > 0 ? $length : null;
            }
        }

        $mediaNode = feed_reader_xpath_first(
            $xpath,
            './*[local-name()="content" or local-name()="thumbnail"][@url]',
            $node
        );
        if ($mediaNode instanceof DOMElement) {
            $itemImage = feed_reader_safe_content_url($feedUrl, $mediaNode->getAttribute('url'));
        }

        $contentHtml = feed_reader_sanitize_html($contentRaw !== '' ? $contentRaw : $summaryRaw, $link ?: $feedUrl);
        $summaryHtml = feed_reader_sanitize_html($summaryRaw, $link ?: $feedUrl);
        $summary = publishing_excerpt(strip_tags($summaryHtml !== '' ? $summaryHtml : $contentHtml), 700);

        if ($itemImage === '') {
            $itemImage = feed_reader_extract_first_image($contentHtml, $link ?: $feedUrl);
        }

        $title = feed_reader_limit_text($title !== '' ? $title : 'Untitled item', 500);
        $author = feed_reader_limit_text($author, 255);
        $guid = feed_reader_limit_text($guid, 1000);
        $link = feed_reader_limit_text($link, 1000);
        $keySource = $guid !== '' ? 'guid|' . $guid : ($link !== '' ? 'link|' . $link : 'content|' . $title . '|' . ($published ?? '') . '|' . $summary);
        $contentHash = hash('sha256', implode('|', [$title, $author, $summary, $contentHtml, $link, $published ?? '', $updated ?? '']));

        $items[] = [
            'item_key_hash' => hash('sha256', $keySource),
            'guid_value' => $guid !== '' ? $guid : null,
            'canonical_url' => $link !== '' ? $link : null,
            'title' => $title,
            'author_name' => $author !== '' ? $author : null,
            'summary' => $summary !== '' ? $summary : null,
            'content_html' => $contentHtml !== '' ? $contentHtml : null,
            'categories' => array_values(array_unique($categories)),
            'image_url' => $itemImage !== '' ? feed_reader_limit_text($itemImage, 1000) : null,
            'enclosure_url' => $enclosureUrl !== '' ? feed_reader_limit_text($enclosureUrl, 1000) : null,
            'enclosure_type' => $enclosureType !== '' ? $enclosureType : null,
            'enclosure_length' => $enclosureLength,
            'content_hash' => $contentHash,
            'published_at' => $published,
            'source_updated_at' => $updated,
        ];
    }

    if (!$items && $feedTitle === '') {
        throw new RuntimeException('The feed did not contain recognizable metadata or entries.');
    }

    return [
        'format' => $format,
        'title' => feed_reader_limit_text($feedTitle !== '' ? $feedTitle : (parse_url($feedUrl, PHP_URL_HOST) ?: 'Untitled feed'), 255),
        'description' => feed_reader_limit_text($feedDescription, 4000),
        'language' => feed_reader_limit_text($language, 40),
        'site_url' => $siteUrl !== '' ? feed_reader_limit_text($siteUrl, 1000) : null,
        'image_url' => $imageUrl !== '' ? feed_reader_limit_text($imageUrl, 1000) : null,
        'items' => $items,
    ];
}

function feed_reader_source_identity(int $sourceId, string $finalUrl): array
{
    $finalUrl = feed_reader_normalize_url($finalUrl);
    $hash = hash('sha256', $finalUrl);
    $statement = db()->prepare(
        'SELECT id FROM feed_sources WHERE canonical_hash=:hash AND id<>:id LIMIT 1'
    );
    $statement->execute(['hash' => $hash, 'id' => $sourceId]);
    if ($statement->fetchColumn()) {
        $current = feed_reader_source_by_id($sourceId);
        return [
            'feed_url' => (string)($current['feed_url'] ?? $finalUrl),
            'canonical_url' => (string)($current['canonical_url'] ?? $finalUrl),
            'canonical_hash' => (string)($current['canonical_hash'] ?? hash('sha256', $finalUrl)),
        ];
    }
    return [
        'feed_url' => $finalUrl,
        'canonical_url' => $finalUrl,
        'canonical_hash' => $hash,
    ];
}

function feed_reader_source_by_id(int $sourceId): ?array
{
    if (!feed_reader_schema_available() || $sourceId <= 0) {
        return null;
    }
    $statement = db()->prepare('SELECT * FROM feed_sources WHERE id=:id LIMIT 1');
    $statement->execute(['id' => $sourceId]);
    return $statement->fetch() ?: null;
}

function feed_reader_source_for_user(int $userId, int $sourceId): ?array
{
    if (!feed_reader_schema_available() || $userId <= 0 || $sourceId <= 0) {
        return null;
    }
    $statement = db()->prepare(
        'SELECT source.*,subscription.id AS subscription_id,
                subscription.folder_id,subscription.display_title,
                subscription.status AS subscription_status
         FROM feed_subscriptions subscription
         JOIN feed_sources source ON source.id=subscription.source_id
         WHERE subscription.user_id=:user_id
           AND source.id=:source_id
         LIMIT 1'
    );
    $statement->execute(['user_id' => $userId, 'source_id' => $sourceId]);
    return $statement->fetch() ?: null;
}

function feed_reader_store_items(int $sourceId, array $items): array
{
    if ($sourceId <= 0) {
        return ['item_count' => 0, 'new_item_count' => 0];
    }

    $existingStatement = db()->prepare('SELECT item_key_hash FROM feed_items WHERE source_id=:source_id');
    $existingStatement->execute(['source_id' => $sourceId]);
    $existing = array_fill_keys(array_map('strval', $existingStatement->fetchAll(PDO::FETCH_COLUMN)), true);

    $statement = db()->prepare(
        'INSERT INTO feed_items (
            source_id,item_key_hash,guid_value,canonical_url,title,author_name,
            summary,content_html,categories_json,image_url,enclosure_url,
            enclosure_type,enclosure_length,content_hash,published_at,source_updated_at
         ) VALUES (
            :source_id,:item_key_hash,:guid_value,:canonical_url,:title,:author_name,
            :summary,:content_html,:categories_json,:image_url,:enclosure_url,
            :enclosure_type,:enclosure_length,:content_hash,:published_at,:source_updated_at
         )
         ON DUPLICATE KEY UPDATE
            guid_value=VALUES(guid_value),
            canonical_url=VALUES(canonical_url),
            title=VALUES(title),
            author_name=VALUES(author_name),
            summary=VALUES(summary),
            content_html=VALUES(content_html),
            categories_json=VALUES(categories_json),
            image_url=VALUES(image_url),
            enclosure_url=VALUES(enclosure_url),
            enclosure_type=VALUES(enclosure_type),
            enclosure_length=VALUES(enclosure_length),
            content_hash=VALUES(content_hash),
            published_at=VALUES(published_at),
            source_updated_at=VALUES(source_updated_at)'
    );

    $newCount = 0;
    foreach ($items as $item) {
        $key = (string)$item['item_key_hash'];
        if (!isset($existing[$key])) {
            $newCount++;
            $existing[$key] = true;
        }
        $statement->execute([
            'source_id' => $sourceId,
            'item_key_hash' => $key,
            'guid_value' => $item['guid_value'],
            'canonical_url' => $item['canonical_url'],
            'title' => $item['title'],
            'author_name' => $item['author_name'],
            'summary' => $item['summary'],
            'content_html' => $item['content_html'],
            'categories_json' => $item['categories']
                ? json_encode($item['categories'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'image_url' => $item['image_url'],
            'enclosure_url' => $item['enclosure_url'],
            'enclosure_type' => $item['enclosure_type'],
            'enclosure_length' => $item['enclosure_length'],
            'content_hash' => $item['content_hash'],
            'published_at' => $item['published_at'],
            'source_updated_at' => $item['source_updated_at'],
        ]);
    }

    return ['item_count' => count($items), 'new_item_count' => $newCount];
}

function feed_reader_next_refresh_at(int $failureCount = 0): string
{
    $minutes = (int)feed_reader_config()['refresh_minutes'];
    if ($failureCount > 0) {
        $minutes = min(1440, $minutes * (2 ** min(6, $failureCount)));
    }
    return gmdate('Y-m-d H:i:s', time() + $minutes * 60);
}

function feed_reader_create_refresh_run(int $sourceId, string $trigger, ?int $userId): int
{
    $allowed = ['subscription','manual','scheduled','opml'];
    if (!in_array($trigger, $allowed, true)) {
        $trigger = 'scheduled';
    }
    $statement = db()->prepare(
        'INSERT INTO feed_refresh_runs (source_id,requested_by,trigger_type,status)
         VALUES (:source_id,:requested_by,:trigger_type,"started")'
    );
    $statement->execute([
        'source_id' => $sourceId,
        'requested_by' => $userId && $userId > 0 ? $userId : null,
        'trigger_type' => $trigger,
    ]);
    return (int)db()->lastInsertId();
}

function feed_reader_finish_refresh_run(
    int $runId,
    string $status,
    int $httpStatus,
    int $itemCount,
    int $newItemCount,
    int $durationMs,
    ?string $error = null
): void {
    $statement = db()->prepare(
        'UPDATE feed_refresh_runs
         SET status=:status,http_status=:http_status,item_count=:item_count,
             new_item_count=:new_item_count,duration_ms=:duration_ms,
             error_message=:error_message,completed_at=UTC_TIMESTAMP()
         WHERE id=:id'
    );
    $statement->execute([
        'status' => $status,
        'http_status' => $httpStatus > 0 ? $httpStatus : null,
        'item_count' => max(0, $itemCount),
        'new_item_count' => max(0, $newItemCount),
        'duration_ms' => max(0, $durationMs),
        'error_message' => $error !== null ? feed_reader_limit_text($error, 1000) : null,
        'id' => $runId,
    ]);
}

function feed_reader_refresh_source(
    int $sourceId,
    string $trigger = 'manual',
    ?int $userId = null,
    bool $force = false
): array {
    feed_reader_require_enabled();

    $source = feed_reader_source_by_id($sourceId);
    if (!$source) {
        throw new RuntimeException('The feed source could not be found.');
    }

    if ($source['status'] === 'paused' && !$force) {
        return ['status' => 'skipped', 'item_count' => 0, 'new_item_count' => 0];
    }

    $claim = db()->prepare(
        'UPDATE feed_sources
         SET refresh_lock_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3 MINUTE)
         WHERE id=:id
           AND (refresh_lock_until IS NULL OR refresh_lock_until<UTC_TIMESTAMP())'
    );
    $claim->execute(['id' => $sourceId]);

    if ($claim->rowCount() !== 1) {
        return ['status' => 'skipped', 'item_count' => 0, 'new_item_count' => 0];
    }

    $runId = feed_reader_create_refresh_run($sourceId, $trigger, $userId);
    $started = microtime(true);

    try {
        $response = feed_reader_fetch(
            (string)$source['feed_url'],
            (string)($source['etag'] ?? ''),
            (string)($source['last_modified'] ?? '')
        );
        $duration = (int)round((microtime(true) - $started) * 1000);
        $identity = feed_reader_source_identity($sourceId, (string)$response['url']);

        if ((int)$response['status'] === 304) {
            db()->prepare(
                'UPDATE feed_sources
                 SET canonical_url=:canonical_url,canonical_hash=:canonical_hash,
                     etag=:etag,last_modified=:last_modified,last_http_status=304,
                     last_checked_at=UTC_TIMESTAMP(),last_success_at=UTC_TIMESTAMP(),
                     next_refresh_at=:next_refresh_at,refresh_lock_until=NULL,
                     failure_count=0,last_error=NULL,status="active"
                 WHERE id=:id'
            )->execute([
                'canonical_url' => $identity['canonical_url'],
                'canonical_hash' => $identity['canonical_hash'],
                'etag' => $response['etag'] !== '' ? $response['etag'] : null,
                'last_modified' => $response['last_modified'] !== '' ? $response['last_modified'] : null,
                'next_refresh_at' => feed_reader_next_refresh_at(),
                'id' => $sourceId,
            ]);
            feed_reader_finish_refresh_run($runId, 'not_modified', 304, 0, 0, $duration);
            return ['status' => 'not_modified', 'item_count' => 0, 'new_item_count' => 0];
        }

        $parsed = feed_reader_parse_feed((string)$response['body'], (string)$response['url']);
        db()->beginTransaction();
        $counts = feed_reader_store_items($sourceId, $parsed['items']);
        db()->prepare(
            'UPDATE feed_sources
             SET feed_url=:feed_url,canonical_url=:canonical_url,canonical_hash=:canonical_hash,
                 site_url=:site_url,title=:title,description=:description,language=:language,
                 image_url=:image_url,feed_format=:feed_format,status="active",
                 etag=:etag,last_modified=:last_modified,last_http_status=:http_status,
                 last_checked_at=UTC_TIMESTAMP(),last_success_at=UTC_TIMESTAMP(),
                 next_refresh_at=:next_refresh_at,refresh_lock_until=NULL,
                 failure_count=0,last_error=NULL
             WHERE id=:id'
        )->execute([
            'feed_url' => $identity['feed_url'],
            'canonical_url' => $identity['canonical_url'],
            'canonical_hash' => $identity['canonical_hash'],
            'site_url' => $parsed['site_url'],
            'title' => $parsed['title'],
            'description' => $parsed['description'] !== '' ? $parsed['description'] : null,
            'language' => $parsed['language'] !== '' ? $parsed['language'] : null,
            'image_url' => $parsed['image_url'],
            'feed_format' => $parsed['format'],
            'etag' => $response['etag'] !== '' ? $response['etag'] : null,
            'last_modified' => $response['last_modified'] !== '' ? $response['last_modified'] : null,
            'http_status' => (int)$response['status'],
            'next_refresh_at' => feed_reader_next_refresh_at(),
            'id' => $sourceId,
        ]);
        db()->commit();

        feed_reader_finish_refresh_run(
            $runId,
            'success',
            (int)$response['status'],
            (int)$counts['item_count'],
            (int)$counts['new_item_count'],
            $duration
        );

        return ['status' => 'success'] + $counts;
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $duration = (int)round((microtime(true) - $started) * 1000);
        $failureCount = min(1000, (int)$source['failure_count'] + 1);
        $error = feed_reader_limit_text($exception->getMessage(), 1000);
        db()->prepare(
            'UPDATE feed_sources
             SET status="error",last_checked_at=UTC_TIMESTAMP(),
                 next_refresh_at=:next_refresh_at,refresh_lock_until=NULL,
                 failure_count=:failure_count,last_error=:last_error
             WHERE id=:id'
        )->execute([
            'next_refresh_at' => feed_reader_next_refresh_at($failureCount),
            'failure_count' => $failureCount,
            'last_error' => $error,
            'id' => $sourceId,
        ]);
        feed_reader_finish_refresh_run($runId, 'failed', 0, 0, 0, $duration, $error);
        throw $exception;
    }
}

function feed_reader_create_source(string $url, ?int $userId, string $trigger = 'subscription'): array
{
    $started = microtime(true);
    $response = feed_reader_fetch($url);
    try {
        $parsed = feed_reader_parse_feed((string)$response['body'], (string)$response['url']);
    } catch (Throwable $firstException) {
        $discovered = feed_reader_discover_feed_url((string)$response['body'], (string)$response['url']);
        if ($discovered === '' || hash_equals(feed_reader_normalize_url((string)$response['url']), feed_reader_normalize_url($discovered))) {
            throw $firstException;
        }
        $response = feed_reader_fetch($discovered);
        $parsed = feed_reader_parse_feed((string)$response['body'], (string)$response['url']);
    }
    $canonical = (string)$response['url'];
    $hash = hash('sha256', $canonical);

    $existing = db()->prepare('SELECT * FROM feed_sources WHERE canonical_hash=:hash LIMIT 1');
    $existing->execute(['hash' => $hash]);
    $source = $existing->fetch();

    if ($source) {
        return $source;
    }

    db()->beginTransaction();
    try {
        $insert = db()->prepare(
            'INSERT INTO feed_sources (
                feed_url,canonical_url,canonical_hash,site_url,title,description,
                language,image_url,feed_format,status,etag,last_modified,
                last_http_status,last_checked_at,last_success_at,next_refresh_at
             ) VALUES (
                :feed_url,:canonical_url,:canonical_hash,:site_url,:title,:description,
                :language,:image_url,:feed_format,"active",:etag,:last_modified,
                :last_http_status,UTC_TIMESTAMP(),UTC_TIMESTAMP(),:next_refresh_at
             )'
        );
        $insert->execute([
            'feed_url' => $canonical,
            'canonical_url' => $canonical,
            'canonical_hash' => $hash,
            'site_url' => $parsed['site_url'],
            'title' => $parsed['title'],
            'description' => $parsed['description'] !== '' ? $parsed['description'] : null,
            'language' => $parsed['language'] !== '' ? $parsed['language'] : null,
            'image_url' => $parsed['image_url'],
            'feed_format' => $parsed['format'],
            'etag' => $response['etag'] !== '' ? $response['etag'] : null,
            'last_modified' => $response['last_modified'] !== '' ? $response['last_modified'] : null,
            'last_http_status' => (int)$response['status'],
            'next_refresh_at' => feed_reader_next_refresh_at(),
        ]);
        $sourceId = (int)db()->lastInsertId();
        $counts = feed_reader_store_items($sourceId, $parsed['items']);
        db()->commit();

        $runId = feed_reader_create_refresh_run($sourceId, $trigger, $userId);
        feed_reader_finish_refresh_run(
            $runId,
            'success',
            (int)$response['status'],
            (int)$counts['item_count'],
            (int)$counts['new_item_count'],
            (int)round((microtime(true) - $started) * 1000)
        );

        return feed_reader_source_by_id($sourceId) ?? [];
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if ($exception instanceof PDOException && (string)$exception->getCode() === '23000') {
            $duplicate = db()->prepare('SELECT * FROM feed_sources WHERE canonical_hash=:hash LIMIT 1');
            $duplicate->execute(['hash' => $hash]);
            $row = $duplicate->fetch();
            if ($row) {
                return $row;
            }
        }
        throw $exception;
    }
}

function feed_reader_subscription_count(int $userId): int
{
    $statement = db()->prepare('SELECT COUNT(*) FROM feed_subscriptions WHERE user_id=:user_id');
    $statement->execute(['user_id' => $userId]);
    return (int)$statement->fetchColumn();
}

function feed_reader_folder_for_user(int $userId, int $folderId): ?array
{
    if ($folderId <= 0) {
        return null;
    }
    $statement = db()->prepare('SELECT * FROM feed_folders WHERE id=:id AND user_id=:user_id LIMIT 1');
    $statement->execute(['id' => $folderId, 'user_id' => $userId]);
    return $statement->fetch() ?: null;
}

function feed_reader_subscribe(int $userId, string $url, int $folderId = 0, string $titleOverride = '', string $trigger = 'subscription'): array
{
    feed_reader_require_enabled();
    if (!feed_reader_schema_available()) {
        throw new RuntimeException('Import database/rss_feed_reader_v62.sql before adding feeds.');
    }

    if (feed_reader_subscription_count($userId) >= (int)feed_reader_config()['max_sources_per_user']) {
        throw new RuntimeException('This account has reached the feed subscription limit.');
    }

    if ($folderId > 0 && !feed_reader_folder_for_user($userId, $folderId)) {
        throw new RuntimeException('The selected feed folder is not available.');
    }

    if (function_exists('feed_reader_resolve_subscription_url')) {
        $url = feed_reader_resolve_subscription_url($url);
    }
    $normalized = feed_reader_normalize_url($url);
    $hash = hash('sha256', $normalized);
    $statement = db()->prepare('SELECT * FROM feed_sources WHERE canonical_hash=:hash LIMIT 1');
    $statement->execute(['hash' => $hash]);
    $source = $statement->fetch();

    if (!$source) {
        $source = feed_reader_create_source($normalized, $userId, $trigger);
    }

    if (!$source || empty($source['id'])) {
        throw new RuntimeException('The feed source could not be created.');
    }

    $insert = db()->prepare(
        'INSERT INTO feed_subscriptions (user_id,source_id,folder_id,display_title,status)
         VALUES (:user_id,:source_id,:folder_id,:display_title,"active")
         ON DUPLICATE KEY UPDATE
            folder_id=VALUES(folder_id),
            display_title=VALUES(display_title),
            status="active"'
    );
    $insert->execute([
        'user_id' => $userId,
        'source_id' => (int)$source['id'],
        'folder_id' => $folderId > 0 ? $folderId : null,
        'display_title' => trim($titleOverride) !== '' ? feed_reader_limit_text($titleOverride, 255) : null,
    ]);

    log_activity('feed_subscribed', 'feed_source', (int)$source['id'], ['url' => $normalized]);
    return $source;
}

function feed_reader_unsubscribe(int $userId, int $sourceId): void
{
    $statement = db()->prepare('DELETE FROM feed_subscriptions WHERE user_id=:user_id AND source_id=:source_id');
    $statement->execute(['user_id' => $userId, 'source_id' => $sourceId]);
    log_activity('feed_unsubscribed', 'feed_source', $sourceId);
}

function feed_reader_save_folder(int $userId, int $folderId, string $name): int
{
    $name = feed_reader_limit_text($name, 190);
    if ($name === '') {
        throw new RuntimeException('Enter a folder name.');
    }

    if ($folderId > 0) {
        $statement = db()->prepare('UPDATE feed_folders SET name=:name WHERE id=:id AND user_id=:user_id');
        $statement->execute(['name' => $name, 'id' => $folderId, 'user_id' => $userId]);
        if ($statement->rowCount() === 0 && !feed_reader_folder_for_user($userId, $folderId)) {
            throw new RuntimeException('The feed folder could not be found.');
        }
        log_activity('feed_folder_updated', 'feed_folder', $folderId);
        return $folderId;
    }

    $statement = db()->prepare('INSERT INTO feed_folders (user_id,name) VALUES (:user_id,:name)');
    $statement->execute(['user_id' => $userId, 'name' => $name]);
    $folderId = (int)db()->lastInsertId();
    log_activity('feed_folder_created', 'feed_folder', $folderId);
    return $folderId;
}

function feed_reader_delete_folder(int $userId, int $folderId): void
{
    $statement = db()->prepare('DELETE FROM feed_folders WHERE id=:id AND user_id=:user_id');
    $statement->execute(['id' => $folderId, 'user_id' => $userId]);
    log_activity('feed_folder_deleted', 'feed_folder', $folderId);
}

function feed_reader_update_subscription(int $userId, int $sourceId, int $folderId, string $displayTitle, string $status): void
{
    if ($folderId > 0 && !feed_reader_folder_for_user($userId, $folderId)) {
        throw new RuntimeException('The selected feed folder is not available.');
    }
    $status = $status === 'paused' ? 'paused' : 'active';
    $statement = db()->prepare(
        'UPDATE feed_subscriptions
         SET folder_id=:folder_id,display_title=:display_title,status=:status
         WHERE user_id=:user_id AND source_id=:source_id'
    );
    $statement->execute([
        'folder_id' => $folderId > 0 ? $folderId : null,
        'display_title' => trim($displayTitle) !== '' ? feed_reader_limit_text($displayTitle, 255) : null,
        'status' => $status,
        'user_id' => $userId,
        'source_id' => $sourceId,
    ]);
    log_activity('feed_subscription_updated', 'feed_source', $sourceId);
}

function feed_reader_set_item_state(int $userId, int $itemId, string $state, bool $value): array
{
    $columns = [
        'read' => ['is_read', 'read_at'],
        'starred' => ['is_starred', 'starred_at'],
        'saved' => ['is_saved', 'saved_at'],
        'archived' => ['is_archived', 'archived_at'],
    ];

    if (!isset($columns[$state])) {
        throw new RuntimeException('The requested feed-item state is not supported.');
    }

    $access = db()->prepare(
        'SELECT item.id
         FROM feed_items item
         JOIN feed_subscriptions subscription ON subscription.source_id=item.source_id
         WHERE item.id=:item_id AND subscription.user_id=:user_id
         LIMIT 1'
    );
    $access->execute(['item_id' => $itemId, 'user_id' => $userId]);
    if (!$access->fetchColumn()) {
        throw new RuntimeException('The feed item is not available to this account.');
    }

    [$column, $timestamp] = $columns[$state];
    $sql = 'INSERT INTO feed_item_states (user_id,item_id,' . $column . ',' . $timestamp . ')
            VALUES (:user_id,:item_id,:value,' . ($value ? 'UTC_TIMESTAMP()' : 'NULL') . ')
            ON DUPLICATE KEY UPDATE
                ' . $column . '=VALUES(' . $column . '),
                ' . $timestamp . '=' . ($value ? 'UTC_TIMESTAMP()' : 'NULL');
    db()->prepare($sql)->execute([
        'user_id' => $userId,
        'item_id' => $itemId,
        'value' => $value ? 1 : 0,
    ]);

    return ['item_id' => $itemId, 'state' => $state, 'value' => $value];
}

function feed_reader_mark_all_read(int $userId, int $sourceId = 0, int $folderId = 0): int
{
    $where = ['subscription.user_id=:user_id'];
    $params = ['user_id' => $userId];
    if ($sourceId > 0) {
        $where[] = 'subscription.source_id=:source_id';
        $params['source_id'] = $sourceId;
    }
    if ($folderId > 0) {
        $where[] = 'subscription.folder_id=:folder_id';
        $params['folder_id'] = $folderId;
    }

    $statement = db()->prepare(
        'INSERT INTO feed_item_states (user_id,item_id,is_read,read_at)
         SELECT :state_user_id,item.id,1,UTC_TIMESTAMP()
         FROM feed_items item
         JOIN feed_subscriptions subscription ON subscription.source_id=item.source_id
         WHERE ' . implode(' AND ', $where) . '
         ON DUPLICATE KEY UPDATE is_read=1,read_at=UTC_TIMESTAMP()'
    );
    $statement->execute(['state_user_id' => $userId] + $params);
    return $statement->rowCount();
}

function feed_reader_folders(int $userId): array
{
    $statement = db()->prepare(
        'SELECT folder.*,
                COUNT(DISTINCT subscription.id) AS source_count,
                COUNT(DISTINCT CASE
                    WHEN COALESCE(state.is_read,0)=0 AND COALESCE(state.is_archived,0)=0
                    THEN item.id END
                ) AS unread_count
         FROM feed_folders folder
         LEFT JOIN feed_subscriptions subscription
           ON subscription.folder_id=folder.id AND subscription.user_id=folder.user_id
         LEFT JOIN feed_items item ON item.source_id=subscription.source_id
         LEFT JOIN feed_item_states state
           ON state.item_id=item.id AND state.user_id=folder.user_id
         WHERE folder.user_id=:user_id
         GROUP BY folder.id
         ORDER BY folder.sort_order,folder.name,folder.id'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function feed_reader_subscriptions(int $userId): array
{
    $statement = db()->prepare(
        'SELECT subscription.*,source.feed_url,source.site_url,source.title,
                source.description,source.image_url,source.feed_format,
                source.status AS source_status,source.last_checked_at,
                source.last_success_at,source.next_refresh_at,
                source.failure_count,source.last_error,
                folder.name AS folder_name,
                COUNT(DISTINCT item.id) AS item_count,
                COUNT(DISTINCT CASE
                    WHEN COALESCE(state.is_read,0)=0 AND COALESCE(state.is_archived,0)=0
                    THEN item.id END
                ) AS unread_count
         FROM feed_subscriptions subscription
         JOIN feed_sources source ON source.id=subscription.source_id
         LEFT JOIN feed_folders folder ON folder.id=subscription.folder_id
         LEFT JOIN feed_items item ON item.source_id=source.id
         LEFT JOIN feed_item_states state
           ON state.item_id=item.id AND state.user_id=subscription.user_id
         WHERE subscription.user_id=:user_id
         GROUP BY subscription.id,source.id,folder.id
         ORDER BY COALESCE(folder.sort_order,9999),folder.name,
                  subscription.sort_order,COALESCE(subscription.display_title,source.title),source.id'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function feed_reader_counts(int $userId): array
{
    $statement = db()->prepare(
        'SELECT COUNT(DISTINCT item.id) AS total,
                COUNT(DISTINCT CASE WHEN COALESCE(state.is_read,0)=0 AND COALESCE(state.is_archived,0)=0 THEN item.id END) AS unread,
                COUNT(DISTINCT CASE WHEN COALESCE(state.is_starred,0)=1 THEN item.id END) AS starred,
                COUNT(DISTINCT CASE WHEN COALESCE(state.is_saved,0)=1 THEN item.id END) AS saved,
                COUNT(DISTINCT CASE WHEN COALESCE(state.is_archived,0)=1 THEN item.id END) AS archived
         FROM feed_subscriptions subscription
         JOIN feed_items item ON item.source_id=subscription.source_id
         LEFT JOIN feed_item_states state ON state.item_id=item.id AND state.user_id=subscription.user_id
         WHERE subscription.user_id=:user_id AND subscription.status="active"'
    );
    $statement->execute(['user_id' => $userId]);
    $row = $statement->fetch() ?: [];
    return array_map(static fn($value): int => (int)($value ?? 0), $row);
}

function feed_reader_items(int $userId, array $filters, int $limit = 200): array
{
    $where = ['subscription.user_id=:user_id', 'subscription.status="active"'];
    $params = ['user_id' => $userId];
    $sourceId = max(0, (int)($filters['source_id'] ?? 0));
    $folderId = max(0, (int)($filters['folder_id'] ?? 0));
    $stateFilter = (string)($filters['state'] ?? 'all');
    $search = trim((string)($filters['q'] ?? ''));

    if ($sourceId > 0) {
        $where[] = 'source.id=:source_id';
        $params['source_id'] = $sourceId;
    }
    if ($folderId > 0) {
        $where[] = 'subscription.folder_id=:folder_id';
        $params['folder_id'] = $folderId;
    }
    if ($stateFilter === 'unread') {
        $where[] = 'COALESCE(state.is_read,0)=0 AND COALESCE(state.is_archived,0)=0';
    } elseif ($stateFilter === 'starred') {
        $where[] = 'COALESCE(state.is_starred,0)=1';
    } elseif ($stateFilter === 'saved') {
        $where[] = 'COALESCE(state.is_saved,0)=1';
    } elseif ($stateFilter === 'archived') {
        $where[] = 'COALESCE(state.is_archived,0)=1';
    } elseif ($stateFilter === 'listened' && function_exists('feed_reader_media_schema_available') && feed_reader_media_schema_available()) {
        $where[] = 'EXISTS (SELECT 1 FROM feed_item_media_states media_state WHERE media_state.user_id=subscription.user_id AND media_state.item_id=item.id AND media_state.is_listened=1)';
    } elseif ($stateFilter === 'notes' && function_exists('feed_reader_media_schema_available') && feed_reader_media_schema_available()) {
        $where[] = 'EXISTS (SELECT 1 FROM feed_item_media_states media_state WHERE media_state.user_id=subscription.user_id AND media_state.item_id=item.id AND media_state.note_text IS NOT NULL AND media_state.note_text<>"")';
    } else {
        $where[] = 'COALESCE(state.is_archived,0)=0';
    }
    if ($search !== '') {
        $where[] = '(item.title LIKE :search OR item.summary LIKE :search OR item.author_name LIKE :search OR source.title LIKE :search)';
        $params['search'] = '%' . mb_substr($search, 0, 190) . '%';
    }

    $statement = db()->prepare(
        'SELECT item.*,source.title AS source_title,source.site_url,source.image_url AS source_image_url,
                COALESCE(subscription.display_title,source.title) AS subscription_title,
                COALESCE(state.is_read,0) AS is_read,
                COALESCE(state.is_starred,0) AS is_starred,
                COALESCE(state.is_saved,0) AS is_saved,
                COALESCE(state.is_archived,0) AS is_archived
         FROM feed_subscriptions subscription
         JOIN feed_sources source ON source.id=subscription.source_id
         JOIN feed_items item ON item.source_id=source.id
         LEFT JOIN feed_item_states state
           ON state.item_id=item.id AND state.user_id=subscription.user_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY COALESCE(item.published_at,item.discovered_at) DESC,item.id DESC
         LIMIT ' . max(1, min(500, $limit))
    );
    $statement->execute($params);
    return $statement->fetchAll();
}

function feed_reader_item_for_user(int $userId, int $itemId): ?array
{
    if ($itemId <= 0) {
        return null;
    }
    $statement = db()->prepare(
        'SELECT item.*,source.title AS source_title,source.site_url,source.feed_url,
                source.image_url AS source_image_url,
                COALESCE(subscription.display_title,source.title) AS subscription_title,
                COALESCE(state.is_read,0) AS is_read,
                COALESCE(state.is_starred,0) AS is_starred,
                COALESCE(state.is_saved,0) AS is_saved,
                COALESCE(state.is_archived,0) AS is_archived
         FROM feed_items item
         JOIN feed_sources source ON source.id=item.source_id
         JOIN feed_subscriptions subscription
           ON subscription.source_id=source.id AND subscription.user_id=:user_id
         LEFT JOIN feed_item_states state
           ON state.item_id=item.id AND state.user_id=:state_user_id
         WHERE item.id=:item_id
         LIMIT 1'
    );
    $statement->execute([
        'user_id' => $userId,
        'state_user_id' => $userId,
        'item_id' => $itemId,
    ]);
    return $statement->fetch() ?: null;
}

function feed_reader_recent_refreshes(int $userId, int $limit = 30): array
{
    $statement = db()->prepare(
        'SELECT run.*,source.title AS source_title
         FROM feed_refresh_runs run
         JOIN feed_sources source ON source.id=run.source_id
         WHERE EXISTS (
            SELECT 1 FROM feed_subscriptions subscription
            WHERE subscription.source_id=source.id AND subscription.user_id=:user_id
         )
         ORDER BY run.started_at DESC,run.id DESC
         LIMIT ' . max(1, min(100, $limit))
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function feed_reader_refresh_due_sources(int $limit = 0): array
{
    $limit = $limit > 0 ? $limit : (int)feed_reader_config()['refresh_batch_size'];
    $statement = db()->prepare(
        'SELECT source.id
         FROM feed_sources source
         WHERE source.status<>"paused"
           AND EXISTS (
                SELECT 1 FROM feed_subscriptions subscription
                WHERE subscription.source_id=source.id AND subscription.status="active"
           )
           AND (source.next_refresh_at IS NULL OR source.next_refresh_at<=UTC_TIMESTAMP())
           AND (source.refresh_lock_until IS NULL OR source.refresh_lock_until<UTC_TIMESTAMP())
         ORDER BY COALESCE(source.next_refresh_at,"1970-01-01") ASC,source.id ASC
         LIMIT ' . max(1, min(100, $limit))
    );
    $statement->execute();
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function feed_reader_run_scheduled_refresh(int $limit = 0): array
{
    $results = ['processed' => 0, 'success' => 0, 'not_modified' => 0, 'failed' => 0, 'skipped' => 0, 'new_items' => 0];
    foreach (feed_reader_refresh_due_sources($limit) as $sourceId) {
        $results['processed']++;
        try {
            $result = feed_reader_refresh_source($sourceId, 'scheduled', null, false);
            $status = (string)($result['status'] ?? 'success');
            if (isset($results[$status])) {
                $results[$status]++;
            } else {
                $results['success']++;
            }
            $results['new_items'] += (int)($result['new_item_count'] ?? 0);
        } catch (Throwable) {
            $results['failed']++;
        }
    }
    return $results;
}

function feed_reader_opml_export(int $userId): string
{
    $subscriptions = feed_reader_subscriptions($userId);
    $folders = [];
    foreach ($subscriptions as $subscription) {
        $folder = trim((string)($subscription['folder_name'] ?? ''));
        $folders[$folder][] = $subscription;
    }

    $attribute = static fn(mixed $value): string => htmlspecialchars(
        (string)$value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
    $lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<opml version="2.0">',
        '  <head>',
        '    <title>North Mountain Media Feed Subscriptions</title>',
        '    <dateCreated>' . $attribute(gmdate(DATE_RFC2822)) . '</dateCreated>',
        '  </head>',
        '  <body>',
    ];

    foreach ($folders as $folderName => $items) {
        $indent = '    ';
        if ($folderName !== '') {
            $lines[] = '    <outline text="' . $attribute($folderName) . '" title="' . $attribute($folderName) . '">';
            $indent = '      ';
        }
        foreach ($items as $subscription) {
            $title = trim((string)($subscription['display_title'] ?: $subscription['title']));
            $line = $indent . '<outline type="rss" text="' . $attribute($title)
                . '" title="' . $attribute($title)
                . '" xmlUrl="' . $attribute($subscription['feed_url']) . '"';
            if (!empty($subscription['site_url'])) {
                $line .= ' htmlUrl="' . $attribute($subscription['site_url']) . '"';
            }
            $lines[] = $line . ' />';
        }
        if ($folderName !== '') {
            $lines[] = '    </outline>';
        }
    }

    $lines[] = '  </body>';
    $lines[] = '</opml>';
    return implode("\n", $lines) . "\n";
}

function feed_reader_opml_entries(string $xml): array
{
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('The PHP DOM/XML extension is required to import OPML files.');
    }
    if (strlen($xml) > 2 * 1024 * 1024) {
        throw new RuntimeException('The OPML file is too large.');
    }
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
        throw new RuntimeException('OPML files containing document type or entity declarations are not accepted.');
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || strtolower((string)$document->documentElement?->localName) !== 'opml') {
        throw new RuntimeException('The uploaded file is not valid OPML.');
    }

    $entries = [];
    $walk = static function (DOMElement $node, string $folder = '') use (&$walk, &$entries): void {
        $xmlUrl = trim($node->getAttribute('xmlUrl'));
        $label = trim($node->getAttribute('title') ?: $node->getAttribute('text'));
        $nextFolder = $folder;

        if ($xmlUrl === '' && $label !== '') {
            $nextFolder = feed_reader_limit_text($label, 190);
        }

        if ($xmlUrl !== '') {
            $entries[] = [
                'url' => $xmlUrl,
                'title' => feed_reader_limit_text($label, 255),
                'folder' => $folder,
            ];
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->localName) === 'outline') {
                $walk($child, $nextFolder);
            }
        }
    };

    foreach ($document->getElementsByTagName('body') as $body) {
        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->localName) === 'outline') {
                $walk($child, '');
            }
        }
        break;
    }

    return array_slice($entries, 0, (int)feed_reader_config()['max_sources_per_user']);
}

function feed_reader_import_opml(int $userId, string $xml): array
{
    $entries = feed_reader_opml_entries($xml);
    $folders = [];
    foreach (feed_reader_folders($userId) as $folder) {
        $folders[mb_strtolower((string)$folder['name'])] = (int)$folder['id'];
    }

    $result = ['total' => count($entries), 'imported' => 0, 'failed' => 0, 'errors' => []];
    foreach ($entries as $entry) {
        try {
            $folderId = 0;
            $folderName = trim((string)$entry['folder']);
            if ($folderName !== '') {
                $key = mb_strtolower($folderName);
                if (!isset($folders[$key])) {
                    $folders[$key] = feed_reader_save_folder($userId, 0, $folderName);
                }
                $folderId = $folders[$key];
            }
            feed_reader_subscribe($userId, (string)$entry['url'], $folderId, (string)$entry['title'], 'opml');
            $result['imported']++;
        } catch (Throwable $exception) {
            $result['failed']++;
            if (count($result['errors']) < 10) {
                $result['errors'][] = feed_reader_limit_text((string)$entry['url'] . ': ' . $exception->getMessage(), 500);
            }
        }
    }

    log_activity('feed_opml_imported', 'feed_subscription', null, [
        'total' => $result['total'],
        'imported' => $result['imported'],
        'failed' => $result['failed'],
    ]);
    return $result;
}

function feed_reader_cleanup(): array
{
    $days = max(30, min(3650, (int)setting('feed_item_retention_days', '365')));
    $deletedItems = db()->exec(
        'DELETE item FROM feed_items item
         LEFT JOIN feed_subscriptions subscription ON subscription.source_id=item.source_id
         WHERE subscription.id IS NULL
           AND item.discovered_at<UTC_TIMESTAMP()-INTERVAL ' . $days . ' DAY'
    );
    $deletedRuns = db()->exec(
        'DELETE FROM feed_refresh_runs
         WHERE started_at<UTC_TIMESTAMP()-INTERVAL 180 DAY'
    );
    $deletedSources = db()->exec(
        'DELETE source FROM feed_sources source
         LEFT JOIN feed_subscriptions subscription ON subscription.source_id=source.id
         WHERE subscription.id IS NULL
           AND source.updated_at<UTC_TIMESTAMP()-INTERVAL 30 DAY'
    );
    return [
        'deleted_items' => (int)$deletedItems,
        'deleted_runs' => (int)$deletedRuns,
        'deleted_sources' => (int)$deletedSources,
    ];
}

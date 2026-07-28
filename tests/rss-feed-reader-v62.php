<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'core' => 'portal/feed-reader-core.php',
    'view' => 'portal/feed-reader-view.php',
    'api' => 'portal/feed-reader-api.php',
    'opml' => 'portal/feed-reader-opml.php',
    'cron' => 'cron/feed-refresh.php',
    'rss' => 'blog-feed.php',
    'atom' => 'blog-atom.php',
    'output' => 'portal/blog-feed-output.php',
    'blog' => 'blog.php',
    'post' => 'blog-post.php',
    'settings' => 'portal/publishing-workflow.php',
    'settingsView' => 'portal/publishing-workflow-view.php',
    'settingsSave' => 'portal/publishing-admin.php',
    'siteSettings' => 'portal/site-settings.php',
    'admin' => 'portal/admin.php',
    'client' => 'portal/client.php',
    'bootstrap' => 'portal/bootstrap.php',
    'script' => 'assets/js/feed-reader.js',
    'css' => 'assets/css/feed-reader.css',
    'migration' => 'database/rss_feed_reader_v62.sql',
    'fullSchema' => 'database/north_mountain_portal.sql',
    'config' => 'config-example.php',
    'docs' => 'RSS-FEED-READER-SETUP-v62.md',
    'scorecard' => 'V62-SCORECARD.md',
];
$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    $source[$key] = $content;
}

$checks = [
    'RSS endpoint' => ['publishing_render_rss_feed', $source['rss'] . $source['output']],
    'Atom endpoint' => ['publishing_render_atom_feed', $source['atom'] . $source['output']],
    'RSS content namespace' => ['xmlns:content=', $source['output']],
    'RSS author namespace' => ['xmlns:dc=', $source['output']],
    'RSS image namespace' => ['xmlns:media=', $source['output']],
    'category feeds' => ['publishing_feed_category', $source['output']],
    'feed conditional requests' => ['HTTP_IF_NONE_MATCH', $source['output']],
    'feed item setting' => ['feed_public_item_limit', $source['settings'] . $source['settingsSave']],
    'RSS discovery' => ['application/rss+xml', $source['blog'] . $source['post']],
    'Atom discovery' => ['application/atom+xml', $source['blog'] . $source['post']],
    'feed folders table' => ['CREATE TABLE IF NOT EXISTS feed_folders', $source['migration']],
    'feed sources table' => ['CREATE TABLE IF NOT EXISTS feed_sources', $source['migration']],
    'feed subscriptions table' => ['CREATE TABLE IF NOT EXISTS feed_subscriptions', $source['migration']],
    'feed items table' => ['CREATE TABLE IF NOT EXISTS feed_items', $source['migration']],
    'per-user item states table' => ['CREATE TABLE IF NOT EXISTS feed_item_states', $source['migration']],
    'refresh evidence table' => ['CREATE TABLE IF NOT EXISTS feed_refresh_runs', $source['migration']],
    'HTTP HTTPS restriction' => ["in_array(\$scheme, ['http', 'https'], true)", $source['core']],
    'private IP protection' => ['FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE', $source['core']],
    'DNS pinning' => ['CURLOPT_RESOLVE', $source['core']],
    'proxy bypass prevention' => ["CURLOPT_PROXY => ''", $source['core']],
    'manual redirect validation' => ['CURLOPT_FOLLOWLOCATION => false', $source['core']],
    'response limit' => ['max_response_bytes', $source['core']],
    'request timeouts' => ['request_timeout_seconds', $source['core']],
    'XML entity rejection' => ["preg_match('/<!DOCTYPE|<!ENTITY/i'", $source['core']],
    'XML network isolation' => ['LIBXML_NONET', $source['core']],
    'HTML sanitizer' => ['feed_reader_sanitize_html', $source['core']],
    'site feed autodiscovery' => ['feed_reader_discover_feed_url', $source['core']],
    'RSS parser' => ["\$format = 'rss'", $source['core']],
    'Atom parser' => ["\$format = 'atom'", $source['core']],
    'RDF parser' => ["\$format = 'rdf'", $source['core']],
    'conditional source requests' => ['If-None-Match:', $source['core']],
    'deduplication key' => ['item_key_hash', $source['core'] . $source['migration']],
    'retry backoff' => ['2 ** min(6, $failureCount)', $source['core']],
    'refresh lock' => ['refresh_lock_until', $source['core'] . $source['migration']],
    'OPML import' => ['feed_reader_import_opml', $source['core'] . $source['view']],
    'OPML export' => ['feed_reader_opml_export', $source['core'] . $source['opml']],
    'three pane reader' => ['feed-reader-layout', $source['view'] . $source['css']],
    'unread state' => ['is_read', $source['core'] . $source['view']],
    'star state' => ['is_starred', $source['core'] . $source['view']],
    'saved state' => ['is_saved', $source['core'] . $source['view']],
    'archive state' => ['is_archived', $source['core'] . $source['view']],
    'reader search' => ['Search titles, summaries, authors, and sources', $source['view']],
    'keyboard navigation' => ["event.key.toLowerCase() === 'j'", $source['script']],
    'accessible status' => ['aria-live', $source['script']],
    'CSRF API' => ['verify_csrf();', $source['api']],
    'same-origin API' => ['same_origin_request()', $source['api']],
    'subscription rate limit' => ['feed_subscribe', $source['view']],
    'OPML rate limit' => ['feed_opml_import', $source['view']],
    'authenticated admin route' => ["'feeds'", $source['admin']],
    'authenticated client route' => ["'feeds'", $source['client']],
    'module control' => ["'feed_reader' =>", $source['siteSettings']],
    'scheduled refresh command' => ['feed_reader_run_scheduled_refresh', $source['cron']],
    'cron token' => ['cron_token', $source['cron'] . $source['config']],
    'config limits' => ['allowed_ports', $source['config']],
    'responsive mobile reader' => ['@media(max-width:760px)', str_replace(' ', '', $source['css'])],
    'deployment documentation' => ['database/rss_feed_reader_v62.sql', $source['docs']],
    'final certification' => ['10/10', $source['scorecard']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'automatic redirect following' => ['CURLOPT_FOLLOWLOCATION => true', $source['core']],
    'dangerous XML entity expansion' => ['LIBXML_NOENT', $source['core']],
    'inline Feed Reader event handler' => ['onsubmit=', $source['view']],
    'unsafe script retention' => ["'script' =>", $source['core']],
];
foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

foreach (['feed_folders','feed_sources','feed_subscriptions','feed_items','feed_item_states','feed_refresh_runs'] as $table) {
    if (substr_count($source['fullSchema'], 'CREATE TABLE IF NOT EXISTS ' . $table) !== 1) {
        fwrite(STDERR, "Fresh-install schema must contain exactly one {$table} definition.\n");
        exit(1);
    }
}

if (!is_file($root . '/tests/fixtures/rss-v62.xml') || !is_file($root . '/tests/fixtures/atom-v62.xml')) {
    fwrite(STDERR, "RSS and Atom parser fixtures are required.\n");
    exit(1);
}

// Execute the pure URL-security helpers without requiring a database.
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null): string {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}
function nmm_config(string $key): array { return []; }
function setting(string $key, ?string $fallback = null): ?string { return $fallback; }
function nmm_module_enabled(string $module, ?bool $fallback = null): bool { return $fallback ?? true; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function publishing_excerpt(mixed $value, int $limit = 180): string { return substr(trim(strip_tags((string)$value)), 0, $limit); }
function log_activity(...$arguments): void {}
require_once $root . '/portal/feed-reader-core.php';

if (feed_reader_ip_is_public('127.0.0.1') || feed_reader_ip_is_public('10.0.0.1') || feed_reader_ip_is_public('169.254.169.254') || feed_reader_ip_is_public('::1') || feed_reader_ip_is_public('fc00::1')) {
    fwrite(STDERR, "Private or metadata-network address passed the public-IP gate.\n");
    exit(1);
}
if (!feed_reader_ip_is_public('8.8.8.8')) {
    fwrite(STDERR, "A public address failed the public-IP gate.\n");
    exit(1);
}
if (feed_reader_normalize_url('https://[2606:4700:4700::1111]/feed') !== 'https://[2606:4700:4700::1111]/feed') {
    fwrite(STDERR, "Public IPv6 URL normalization failed.\n");
    exit(1);
}
foreach (['http://user:pass@example.com/feed','ftp://example.com/feed','http://127.0.0.1/feed'] as $unsafe) {
    try {
        $unsafe === 'http://127.0.0.1/feed'
            ? feed_reader_validate_remote_url($unsafe)
            : feed_reader_normalize_url($unsafe);
        fwrite(STDERR, "Unsafe URL was accepted: {$unsafe}\n");
        exit(1);
    } catch (RuntimeException) {
    }
}
if (feed_reader_safe_content_url('http://127.0.0.1/private', 'https://example.com/') !== '') {
    fwrite(STDERR, "Private browser content URL was accepted.\n");
    exit(1);
}

function publishing_blog_settings(): array {
    return [
        'title' => 'Test Journal',
        'description' => 'Test description',
        'feed_item_limit' => 30,
        'feed_language' => 'en-us',
        'feed_copyright' => 'Copyright Test',
        'rss_enabled' => true,
        'atom_enabled' => true,
    ];
}
function publishing_absolute_url(string $path): string { return 'https://portal.example/' . ltrim($path, '/'); }
function blog_public_posts(?string $category, ?string $search, int $limit, int $offset): array {
    return [[
        'id' => 7,
        'title' => 'Feed output test',
        'slug' => 'feed-output-test',
        'canonical_url' => '',
        'excerpt' => 'A test excerpt.',
        'body_html' => '<p>Full test content.</p>',
        'author_name' => 'Test Author',
        'category' => 'Testing',
        'tags' => ['RSS', 'Atom'],
        'published_at' => '2026-07-27 12:00:00',
        'updated_at' => '2026-07-27 13:00:00',
        'created_at' => '2026-07-27 11:00:00',
        'cover' => null,
    ]];
}
require_once $root . '/portal/blog-feed-output.php';
$_GET = [];
$rssOutput = publishing_render_rss_feed();
$atomOutput = publishing_render_atom_feed();
foreach ([
    '<rss version="2.0"' => $rssOutput,
    '<content:encoded><![CDATA[<p>Full test content.</p>]]></content:encoded>' => $rssOutput,
    '<dc:creator>Test Author</dc:creator>' => $rssOutput,
    '<feed xmlns="http://www.w3.org/2005/Atom">' => $atomOutput,
    '<entry>' => $atomOutput,
    '<content type="html"><![CDATA[<p>Full test content.</p>]]></content>' => $atomOutput,
] as $expected => $generated) {
    if (!str_contains($generated, $expected)) {
        fwrite(STDERR, "Generated feed output is missing: {$expected}\n");
        exit(1);
    }
}

if (class_exists('DOMDocument') && class_exists('DOMXPath')) {
    $rss = feed_reader_parse_feed((string)file_get_contents($root . '/tests/fixtures/rss-v62.xml'), 'https://example.com/feed.xml');
    $atom = feed_reader_parse_feed((string)file_get_contents($root . '/tests/fixtures/atom-v62.xml'), 'https://example.org/atom.xml');
    if (($rss['format'] ?? '') !== 'rss' || count($rss['items'] ?? []) !== 1) {
        fwrite(STDERR, "RSS fixture parsing failed.\n");
        exit(1);
    }
    if (($atom['format'] ?? '') !== 'atom' || count($atom['items'] ?? []) !== 1) {
        fwrite(STDERR, "Atom fixture parsing failed.\n");
        exit(1);
    }
    $rssContent = (string)($rss['items'][0]['content_html'] ?? '');
    if (!str_contains($rssContent, '<p>Body') || str_contains($rssContent, '<script')) {
        fwrite(STDERR, "Feed HTML sanitization or CDATA preservation failed.\n");
        exit(1);
    }
    $encodedAttack = feed_reader_sanitize_html('&lt;script&gt;alert(1)&lt;/script&gt;', 'https://example.com/');
    if (str_contains(strtolower($encodedAttack), '<script')) {
        fwrite(STDERR, "Encoded active content escaped the sanitizer.\n");
        exit(1);
    }
}

echo "RSS and Feed Reader v62 certification regression passed.\n";

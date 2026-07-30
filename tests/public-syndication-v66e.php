<?php
declare(strict_types=1);

define('NMM_ROOT', dirname(__DIR__));
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function app_url(string $path): string { return 'https://pod.example/' . ltrim($path, '/'); }
function nmm_config(?string $section=null): array { return $section==='app' ? ['base_url'=>'https://pod.example'] : []; }
function setting(string $key, mixed $default=null): mixed { return $default; }

require_once NMM_ROOT . '/portal/webmention-service.php';

$fail = static function(string $message): never { fwrite(STDERR, $message . "\n"); exit(1); };

if (!syndication_http_url('https://example.com/post')) $fail('HTTPS URL rejected.');
foreach (['javascript:alert(1)','file:///etc/passwd','https://user:pass@example.com/'] as $unsafe) {
    if (syndication_http_url($unsafe)) $fail('Unsafe URL accepted: ' . $unsafe);
}
foreach (['127.0.0.1','10.0.0.1','169.254.169.254','::1','fc00::1'] as $private) {
    if (syndication_public_ip($private)) $fail('Private IP accepted: ' . $private);
}
if (!syndication_public_ip('8.8.8.8')) $fail('Public IPv4 rejected.');
if (syndication_public_url_resolution('http://127.0.0.1/') !== null) $fail('Private literal URL resolved.');
$resolution = syndication_public_url_resolution('https://8.8.8.8/test');
if (!$resolution || ($resolution['port'] ?? 0) !== 443) $fail('Public literal resolution failed.');
if (syndication_curl_resolve($resolution) !== ['8.8.8.8:443:8.8.8.8']) $fail('cURL DNS pin failed.');

$query = syndication_filter_query(['category'=>'','tag'=>'PHP & APIs','author'=>'']);
if ($query !== '?tag=PHP+%26+APIs') $fail('Feed filter query encoding failed.');
if (syndication_normalize_url('HTTPS://Example.COM:443/post?x=1#fragment') !== 'https://example.com/post?x=1') {
    $fail('URL normalization failed.');
}

$target = 'https://pod.example/blog-post.php?slug=hello-world';
$html = '<!doctype html><html><head><title>Reply title</title></head><body>'
    . '<article class="h-entry"><span class="p-author">Alex Rivera</span>'
    . '<div class="e-content">A thoughtful independent-web reply.</div>'
    . '<a class="u-in-reply-to" href="' . $target . '">Original</a></article></body></html>';
$metadata = syndication_webmention_metadata($html, 'https://writer.example/reply', $target);
if (($metadata['mention_type'] ?? '') !== 'reply') $fail('Webmention reply classification failed.');
if (($metadata['author_name'] ?? '') !== 'Alex Rivera') $fail('Webmention author parsing failed.');
if (!str_contains((string)($metadata['source_excerpt'] ?? ''), 'thoughtful')) $fail('Webmention excerpt parsing failed.');
try {
    syndication_webmention_metadata('<html><body>No link</body></html>', 'https://writer.example/no-link', $target);
    $fail('Unlinked Webmention source accepted.');
} catch (RuntimeException) {
}

$root = NMM_ROOT;
$files = [
    'core'=>'portal/public-syndication.php','mention'=>'portal/webmention-service.php',
    'websub'=>'portal/websub-service.php','admin'=>'portal/syndication-admin.php',
    'adminRoute'=>'portal/admin.php','bootstrap'=>'portal/bootstrap.php',
    'feed'=>'portal/blog-feed-output.php','blog'=>'blog.php','post'=>'blog-post.php',
    'json'=>'blog-json-feed.php','podcast'=>'podcast-feed.php','receiver'=>'webmention.php',
    'directory'=>'blog-feeds.php','worker'=>'cron/process-syndication.php',
    'migration'=>'database/public_syndication_v66e.sql','schema'=>'database/north_mountain_portal.sql',
    'workflow'=>'.github/workflows/public-syndication-quality.yml',
];
$source=[];
foreach ($files as $key=>$path) {
    $source[$key]=(string)@file_get_contents($root.'/'.$path);
    if ($source[$key]==='') $fail('Missing public syndication source: '.$path);
}
$checks = [
    ['JSON Feed 1.1','https://jsonfeed.org/version/1.1',$source['core']],
    ['JSON MIME','application/feed+json',$source['core'].$source['json']],
    ['JSON WebSub hubs','$feed[\'hubs\']',$source['core']],
    ['tag filter',"'tag'",$source['core']],
    ['author filter',"'author'",$source['core']],
    ['podcast RSS','xmlns:podcast=',$source['core']],
    ['feed directory','Feeds &amp; Syndication',$source['directory']],
    ['Webmention endpoint','syndication_receive_webmention',$source['receiver']],
    ['Webmention moderation','moderate_webmention',$source['admin']],
    ['public IP protection','FILTER_FLAG_NO_PRIV_RANGE',$source['mention']],
    ['DNS pin','CURLOPT_RESOLVE',$source['mention'].$source['websub']],
    ['redirect revalidation','syndication_public_url_resolution($current)',$source['mention']],
    ['bounded source','1024 * 1024',$source['mention']],
    ['exact target','array_values(array_unique($allowed))',$source['mention']],
    ['WebSub queue','syndication_websub_deliveries',$source['websub']],
    ['nonblocking publish hook','syndication_queue_websub',$source['adminRoute'].file_get_contents($root.'/portal/publishing-admin.php')],
    ['CLI worker',"PHP_SAPI !== 'cli'",$source['worker']],
    ['discovery links','syndication_discovery_links',$source['blog'].$source['post']],
    ['approved display','syndication_approved_webmentions',$source['post']],
    ['administrator route',"'syndication'",$source['adminRoute'].$source['bootstrap']],
    ['MySQL gate','mysql:8.4',$source['workflow']],
    ['MariaDB gate','mariadb:11.4',$source['workflow']],
];
foreach ($checks as [$label,$needle,$haystack]) {
    if (!str_contains((string)$haystack,$needle)) $fail('Missing '.$label.': '.$needle);
}
foreach (['syndication_webmentions','syndication_websub_deliveries'] as $table) {
    $needle='CREATE TABLE IF NOT EXISTS '.$table;
    if (substr_count($source['migration'],$needle)!==1) $fail('Migration must define '.$table.' exactly once.');
    if (substr_count($source['schema'],$needle)!==1) $fail('Fresh schema must define '.$table.' exactly once.');
}
foreach ([
    'tools/apply-public-syndication-v66e.py','.github/workflows/apply-public-syndication-v66e.yml',
    'tools/fix-public-syndication-security-v66e.py','.github/workflows/fix-public-syndication-security-v66e.yml',
    'tools/refine-public-syndication-v66e.py','.github/workflows/refine-public-syndication-v66e.yml',
] as $temporary) {
    if (is_file($root.'/'.$temporary)) $fail('Temporary builder remains: '.$temporary);
}

echo "Public Syndication v66E regression passed.\n";

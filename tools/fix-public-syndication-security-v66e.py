from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8')


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected one anchor, found {count}')
    return content.replace(old, new, 1)


webmention = read('portal/webmention-service.php')
old_host = '''function syndication_public_url_host(string $url): bool
{
    if (!syndication_http_url($url)) return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) return false;
    if (filter_var($host, FILTER_VALIDATE_IP)) return syndication_public_ip($host);
    $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
    $ips = [];
    foreach ($records as $record) {
        if (!empty($record['ip'])) $ips[] = (string)$record['ip'];
        if (!empty($record['ipv6'])) $ips[] = (string)$record['ipv6'];
    }
    if (!$ips) $ips = @gethostbynamel($host) ?: [];
    return $ips !== [] && count(array_filter($ips, 'syndication_public_ip')) === count($ips);
}
'''
new_host = '''function syndication_public_url_resolution(string $url): ?array
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
'''
webmention = replace_once(webmention, old_host, new_host, 'public URL resolution')
webmention = replace_once(
    webmention,
    '''    for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
        if (!syndication_public_url_host($current)) {
            throw new RuntimeException('The Webmention source must resolve to a public HTTP or HTTPS address.');
        }
        $headers = [];
''',
    '''    for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
        $resolution = syndication_public_url_resolution($current);
        if (!$resolution) {
            throw new RuntimeException('The Webmention source must resolve only to public HTTP or HTTPS addresses.');
        }
        $headers = [];
''',
    'Webmention resolution claim',
)
webmention = replace_once(
    webmention,
    '''            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'NorthMountainMedia-Webmention/1.0',
''',
    '''            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => syndication_curl_resolve($resolution),
            CURLOPT_USERAGENT => 'NorthMountainMedia-Webmention/1.0',
''',
    'Webmention DNS pin',
)
old_target = '''    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    $slug = trim((string)($query['slug'] ?? ''));
    if ($slug !== '') return blog_public_post_by_slug($slug);
    try {
'''
new_target = '''    $query = [];
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
'''
webmention = replace_once(webmention, old_target, new_target, 'exact Webmention target')
write('portal/webmention-service.php', webmention)

websub = read('portal/websub-service.php')
websub = replace_once(
    websub,
    '''    if (!syndication_public_url_host($hub) || !syndication_http_url($topic)) {
        return ['ok'=>false,'status'=>0,'body'=>'','error'=>'The hub or topic URL is not valid for public delivery.'];
    }
''',
    '''    $resolution = syndication_public_url_resolution($hub);
    if (!$resolution || !syndication_http_url($topic)) {
        return ['ok'=>false,'status'=>0,'body'=>'','error'=>'The hub or topic URL is not valid for public delivery.'];
    }
''',
    'WebSub resolution claim',
)
websub = replace_once(
    websub,
    '''        CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json,text/plain,*/*'],
''',
    '''        CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
        CURLOPT_RESOLVE=>syndication_curl_resolve($resolution),
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json,text/plain,*/*'],
''',
    'WebSub DNS pin',
)
write('portal/websub-service.php', websub)

print('Public Syndication v66E security hardening applied.')

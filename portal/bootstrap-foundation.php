<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-portal-shell-v66Q7 */

if (defined('NMM_BOOTSTRAPPED')) {
    return;
}

define('NMM_BOOTSTRAPPED', true);
define('NMM_ROOT', dirname(__DIR__));

$configFile = NMM_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Configuration required</title><style>body{font:16px/1.6 system-ui;margin:0;background:#f4f6f8;color:#17202c}.box{max-width:720px;margin:10vh auto;padding:32px;background:#fff;border:1px solid #dde3ea;border-radius:20px}code{background:#f2f4f7;padding:2px 6px;border-radius:5px}</style></head><body>';
    echo '<main class="box"><h1>Portal configuration required</h1><p>Copy <code>config-example.php</code> to <code>config.php</code>, enter the database credentials and a secure setup token, then open <code>/install.php</code>.</p></main></body></html>';
    exit;
}

$config = require $configFile;
if (!is_array($config)) {
    throw new RuntimeException('config.php must return an array.');
}

$GLOBALS['nmm_config'] = $config;
$appConfig = is_array($config['app'] ?? null) ? $config['app'] : [];
$securityConfig = is_array($config['security'] ?? null) ? $config['security'] : [];
date_default_timezone_set((string)($appConfig['timezone'] ?? 'America/Phoenix'));

$remoteAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
$trustedProxies = array_values(array_filter(array_map('strval', $securityConfig['trusted_proxies'] ?? [])));
$proxyTrusted = $remoteAddress !== '' && in_array($remoteAddress, $trustedProxies, true);
$forwardedProto = $proxyTrusted
    ? strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''))
    : '';
$isSecure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || $forwardedProto === 'https';
$forceHttps = (bool)($securityConfig['force_https'] ?? false);

if ($forceHttps && !$isSecure && PHP_SAPI !== 'cli' && !headers_sent()) {
    $baseUrl = rtrim((string)($appConfig['base_url'] ?? ''), '/');
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if (str_starts_with(strtolower($baseUrl), 'https://')) {
        header('Location: ' . $baseUrl . $requestUri, true, 301);
        exit;
    }
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        header('Location: https://' . $host . $requestUri, true, 301);
        exit;
    }
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name((string)($appConfig['session_name'] ?? 'nmm_portal'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isSecure || $forceHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$now = time();
$idleSeconds = max(300, (int)($securityConfig['session_idle_seconds'] ?? 1800));
$absoluteSeconds = max($idleSeconds, (int)($securityConfig['session_absolute_seconds'] ?? 43200));
$regenerateSeconds = max(300, (int)($securityConfig['session_regenerate_seconds'] ?? 900));
$userAgentHash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$sessionExpired = isset($_SESSION['user_id']) && (
    ($now - (int)($_SESSION['session_created_at'] ?? $now)) > $absoluteSeconds
    || ($now - (int)($_SESSION['session_last_activity'] ?? $now)) > $idleSeconds
    || (
        isset($_SESSION['session_user_agent'])
        && !hash_equals((string)$_SESSION['session_user_agent'], $userAgentHash)
    )
);
if ($sessionExpired) {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (!isset($_SESSION['session_created_at'])) {
    $_SESSION['session_created_at'] = $now;
}
if (!isset($_SESSION['session_regenerated_at']) || ($now - (int)$_SESSION['session_regenerated_at']) > $regenerateSeconds) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated_at'] = $now;
}
$_SESSION['session_last_activity'] = $now;
$_SESSION['session_user_agent'] = $userAgentHash;

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
$isPublicPage = defined('NMM_PUBLIC_PAGE') && NMM_PUBLIC_PAGE;
$publicMicrophonePage = defined('NMM_PUBLIC_MICROPHONE_PAGE') && NMM_PUBLIC_MICROPHONE_PAGE;
header('Permissions-Policy: camera=(), microphone=' . (($isPublicPage && !$publicMicrophonePage) ? '()' : '(self)') . ', geolocation=(), payment=(), usb=()');
header(
    "Content-Security-Policy: default-src 'self'; "
    . ($isPublicPage
        ? "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        : "script-src 'self'; style-src 'self' 'unsafe-inline'; ")
    . "img-src 'self' data: https: blob:; font-src 'self' data:; media-src 'self' https: blob:; "
    . "connect-src 'self'; worker-src 'self' blob:; frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com; "
    . "form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'"
);

function nmm_config(?string $section = null): array
{
    $config = $GLOBALS['nmm_config'] ?? [];
    if ($section === null) return is_array($config) ? $config : [];
    $value = $config[$section] ?? [];
    return is_array($value) ? $value : [];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = nmm_config('database');
    $name = (string)($config['name'] ?? '');
    if ($name === '') throw new RuntimeException('Database name is not configured.');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string)($config['host'] ?? 'localhost'),
        (int)($config['port'] ?? 3306),
        $name,
        (string)($config['charset'] ?? 'utf8mb4')
    );
    $pdo = new PDO(
        $dsn,
        (string)($config['username'] ?? ''),
        (string)($config['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );
    return $pdo;
}

function e(null|string|int|float $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function app_url(string $path = ''): string
{
    $base = rtrim((string)(nmm_config('app')['base_url'] ?? ''), '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}
function redirect(string $path): never
{
    if (!preg_match('#^https?://#i', $path)) $path = app_url($path);
    header('Location: ' . $path, true, 303);
    exit;
}
function is_post(): bool { return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'; }
function input(string $key, string $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }
function nullable_input(string $key): ?string { $value = input($key); return $value === '' ? null : $value; }
function int_input(string $key, int $default = 0): int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    return ($value === false || $value === null) ? $default : $value;
}
function query_int(string $key, int $default = 0): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    return ($value === false || $value === null) ? $default : $value;
}
function request_ip(): string
{
    $remote = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    $trusted = array_values(array_filter(array_map('strval', nmm_config('security')['trusted_proxies'] ?? [])));
    if ($remote !== '' && in_array($remote, $trusted, true)) {
        $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $candidate = trim(explode(',', $forwarded)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) return substr($candidate, 0, 64);
        }
    }
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'unknown';
}
function same_origin_request(): bool
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') return true;
    $base = trim((string)(nmm_config('app')['base_url'] ?? ''));
    if ($base === '') return true;
    $originParts = parse_url($origin);
    $baseParts = parse_url($base);
    if (!is_array($originParts) || !is_array($baseParts)) return false;
    $originScheme = strtolower((string)($originParts['scheme'] ?? ''));
    $baseScheme = strtolower((string)($baseParts['scheme'] ?? ''));
    $originHost = strtolower((string)($originParts['host'] ?? ''));
    $baseHost = strtolower((string)($baseParts['host'] ?? ''));
    $originPort = (int)($originParts['port'] ?? ($originScheme === 'https' ? 443 : 80));
    $basePort = (int)($baseParts['port'] ?? ($baseScheme === 'https' ? 443 : 80));
    return $originScheme !== '' && $originHost !== ''
        && hash_equals($baseScheme, $originScheme)
        && hash_equals($baseHost, $originHost)
        && $basePort === $originPort;
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = ['type' => $type, 'message' => $message]; }
function pull_flashes(): array { $messages = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return is_array($messages) ? $messages : []; }
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}
function rotate_csrf_token(): void { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void
{
    $provided = (string)($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($provided === '' || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('The form session expired. Refresh the page and try again.');
    }
}
function format_date(?string $value, string $format = 'M j, Y'): string
{
    if (!$value) return '—';
    try { return (new DateTimeImmutable($value))->format($format); } catch (Throwable) { return '—'; }
}
function format_datetime(?string $value): string { return format_date($value, 'M j, Y g:i A'); }
function format_money(null|string|float|int $value): string { return ($value === null || $value === '') ? '—' : '$' . number_format((float)$value, 2); }
function format_bytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 1) . ' MB';
    return number_format($bytes / 1073741824, 1) . ' GB';
}
function status_label(string $status): string { return ucwords(str_replace('_', ' ', $status)); }
function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'project-' . bin2hex(random_bytes(3));
}
function random_password(int $length = 18): string
{
    $length = max(14, $length);
    $groups = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%'];
    $characters = array_map(static fn(string $group): string => $group[random_int(0, strlen($group) - 1)], $groups);
    $all = implode('', $groups);
    while (count($characters) < $length) $characters[] = $all[random_int(0, strlen($all) - 1)];
    for ($index = count($characters) - 1; $index > 0; $index--) {
        $swap = random_int(0, $index);
        [$characters[$index], $characters[$swap]] = [$characters[$swap], $characters[$index]];
    }
    return implode('', $characters);
}
function password_policy_errors(string $password, string $email = ''): array
{
    $minimum = max(12, (int)(nmm_config('security')['password_min_length'] ?? 12));
    $errors = [];
    if (strlen($password) < $minimum) $errors[] = 'Password must contain at least ' . $minimum . ' characters.';
    if (strlen($password) > 256) $errors[] = 'Password is too long.';
    $classes = 0;
    $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
    $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
    $classes += preg_match('/[0-9]/', $password) ? 1 : 0;
    $classes += preg_match('/[^a-zA-Z0-9]/', $password) ? 1 : 0;
    if ($classes < 3) $errors[] = 'Use at least three of: lowercase, uppercase, numbers, and symbols.';
    $emailLocal = strtolower(strtok($email, '@') ?: '');
    if ($emailLocal !== '' && strlen($emailLocal) >= 4 && str_contains(strtolower($password), $emailLocal)) {
        $errors[] = 'Password must not contain the email username.';
    }
    return $errors;
}
function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function setting(string $key, ?string $fallback = null): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $statement = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key');
        $statement->execute(['setting_key' => $key]);
        $value = $statement->fetchColumn();
        $cache[$key] = $value === false ? $fallback : (string)$value;
    } catch (Throwable) {
        $cache[$key] = $fallback;
    }
    return $cache[$key];
}

require_once __DIR__ . '/site-settings.php';
require_once __DIR__ . '/site-builder-core.php';
require_once __DIR__ . '/publishing-center.php';
require_once __DIR__ . '/navigation.php';

function rate_limit_exceeded(string $action, string $identity, int $limit, int $windowSeconds): bool
{
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $identityHash = hash('sha256', $action . '|' . $identity);
    try {
        $insert = db()->prepare('INSERT INTO rate_limit_events (action_key, identity_hash) VALUES (:action_key, :identity_hash)');
        $insert->execute(['action_key' => $action, 'identity_hash' => $identityHash]);
        $threshold = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
        $count = db()->prepare('SELECT COUNT(*) FROM rate_limit_events WHERE action_key=:action_key AND identity_hash=:identity_hash AND created_at>=:threshold');
        $count->execute(['action_key' => $action, 'identity_hash' => $identityHash, 'threshold' => $threshold]);
        if (random_int(1, 100) === 1) db()->exec('DELETE FROM rate_limit_events WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 2 DAY)');
        return (int)$count->fetchColumn() > $limit;
    } catch (Throwable) {
        $key = 'rate_limit_' . $identityHash;
        $events = array_values(array_filter(
            $_SESSION[$key] ?? [],
            static fn($timestamp): bool => (int)$timestamp >= time() - $windowSeconds
        ));
        $events[] = time();
        $_SESSION[$key] = $events;
        return count($events) > $limit;
    }
}

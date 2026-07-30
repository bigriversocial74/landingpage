<?php
declare(strict_types=1);

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
    echo '<main class="box"><h1>Portal configuration required</h1>';
    echo '<p>Copy <code>config-example.php</code> to <code>config.php</code>, enter the database credentials and a secure setup token, then open <code>/install.php</code>.</p>';
    echo '</main></body></html>';
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
$trustedProxies = array_values(array_filter(
    array_map('strval', $securityConfig['trusted_proxies'] ?? [])
));
$proxyTrusted = $remoteAddress !== '' && in_array($remoteAddress, $trustedProxies, true);

$forwardedProto = $proxyTrusted
    ? strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''))
    : '';

$isSecure = (
    (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || $forwardedProto === 'https'
);

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

if (
    !isset($_SESSION['session_regenerated_at'])
    || ($now - (int)$_SESSION['session_regenerated_at']) > $regenerateSeconds
) {
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
$publicMicrophonePage = defined('NMM_PUBLIC_MICROPHONE_PAGE')
    && NMM_PUBLIC_MICROPHONE_PAGE;
header(
    'Permissions-Policy: camera=(), microphone=' .
    (($isPublicPage && !$publicMicrophonePage) ? '()' : '(self)') .
    ', geolocation=(), payment=(), usb=()'
);

header(
    "Content-Security-Policy: default-src 'self'; " .
    ($isPublicPage
        ? "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        : "script-src 'self'; style-src 'self' 'unsafe-inline'; ") .
    "img-src 'self' data: https: blob:; font-src 'self' data:; " .
    "media-src 'self' https: blob:; connect-src 'self'; worker-src 'self' blob:; " .
    "frame-src https://www.youtube-nocookie.com https://player.vimeo.com; " .
    "form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'"
);

function nmm_config(?string $section = null): array
{
    $config = $GLOBALS['nmm_config'] ?? [];

    if ($section === null) {
        return is_array($config) ? $config : [];
    }

    $value = $config[$section] ?? [];
    return is_array($value) ? $value : [];
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = nmm_config('database');
    $name = (string)($config['name'] ?? '');

    if ($name === '') {
        throw new RuntimeException('Database name is not configured.');
    }

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

function e(null|string|int|float $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = rtrim((string)(nmm_config('app')['base_url'] ?? ''), '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    if (!preg_match('#^https?://#i', $path)) {
        $path = app_url($path);
    }

    header('Location: ' . $path, true, 303);
    exit;
}

function is_post(): bool
{
    return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

function input(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function nullable_input(string $key): ?string
{
    $value = input($key);
    return $value === '' ? null : $value;
}

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
    $trusted = array_values(array_filter(
        array_map('strval', nmm_config('security')['trusted_proxies'] ?? [])
    ));

    if ($remote !== '' && in_array($remote, $trusted, true)) {
        $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

        if ($forwarded !== '') {
            $candidate = trim(explode(',', $forwarded)[0]);

            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return substr($candidate, 0, 64);
            }
        }
    }

    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'unknown';
}

function same_origin_request(): bool
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));

    if ($origin === '') {
        return true;
    }

    $base = trim((string)(nmm_config('app')['base_url'] ?? ''));

    if ($base === '') {
        return true;
    }

    $originParts = parse_url($origin);
    $baseParts = parse_url($base);

    if (!is_array($originParts) || !is_array($baseParts)) {
        return false;
    }

    $originScheme = strtolower((string)($originParts['scheme'] ?? ''));
    $baseScheme = strtolower((string)($baseParts['scheme'] ?? ''));
    $originHost = strtolower((string)($originParts['host'] ?? ''));
    $baseHost = strtolower((string)($baseParts['host'] ?? ''));
    $originPort = (int)($originParts['port'] ?? (
        $originScheme === 'https' ? 443 : 80
    ));
    $basePort = (int)($baseParts['port'] ?? (
        $baseScheme === 'https' ? 443 : 80
    ));

    return $originScheme !== ''
        && $originHost !== ''
        && hash_equals($baseScheme, $originScheme)
        && hash_equals($baseHost, $originHost)
        && $basePort === $originPort;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function rotate_csrf_token(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

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
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable) {
        return '—';
    }
}

function format_datetime(?string $value): string
{
    return format_date($value, 'M j, Y g:i A');
}

function format_money(null|string|float|int $value): string
{
    return ($value === null || $value === '')
        ? '—'
        : '$' . number_format((float)$value, 2);
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 1) . ' MB';
    return number_format($bytes / 1073741824, 1) . ' GB';
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'project-' . bin2hex(random_bytes(3));
}

function random_password(int $length = 18): string
{
    $length = max(14, $length);

    $groups = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%',
    ];

    $characters = array_map(
        static fn (string $group): string => $group[random_int(0, strlen($group) - 1)],
        $groups
    );

    $all = implode('', $groups);

    while (count($characters) < $length) {
        $characters[] = $all[random_int(0, strlen($all) - 1)];
    }

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

    if (strlen($password) < $minimum) {
        $errors[] = 'Password must contain at least ' . $minimum . ' characters.';
    }

    if (strlen($password) > 256) {
        $errors[] = 'Password is too long.';
    }

    $classes = 0;
    $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
    $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
    $classes += preg_match('/[0-9]/', $password) ? 1 : 0;
    $classes += preg_match('/[^a-zA-Z0-9]/', $password) ? 1 : 0;

    if ($classes < 3) {
        $errors[] = 'Use at least three of: lowercase, uppercase, numbers, and symbols.';
    }

    $emailLocal = strtolower(strtok($email, '@') ?: '');

    if (
        $emailLocal !== ''
        && strlen($emailLocal) >= 4
        && str_contains(strtolower($password), $emailLocal)
    ) {
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

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $statement = db()->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = :setting_key'
        );
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

function rate_limit_exceeded(
    string $action,
    string $identity,
    int $limit,
    int $windowSeconds
): bool {
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $identityHash = hash('sha256', $action . '|' . $identity);

    try {
        $insert = db()->prepare(
            'INSERT INTO rate_limit_events (action_key, identity_hash)
             VALUES (:action_key, :identity_hash)'
        );
        $insert->execute([
            'action_key' => $action,
            'identity_hash' => $identityHash,
        ]);

        $threshold = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
        $count = db()->prepare(
            'SELECT COUNT(*)
             FROM rate_limit_events
             WHERE action_key = :action_key
               AND identity_hash = :identity_hash
               AND created_at >= :threshold'
        );
        $count->execute([
            'action_key' => $action,
            'identity_hash' => $identityHash,
            'threshold' => $threshold,
        ]);

        if (random_int(1, 100) === 1) {
            db()->exec(
                'DELETE FROM rate_limit_events
                 WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 2 DAY)'
            );
        }

        return (int)$count->fetchColumn() > $limit;
    } catch (Throwable) {
        $key = 'rate_limit_' . $identityHash;
        $events = array_values(array_filter(
            $_SESSION[$key] ?? [],
            static fn ($timestamp): bool => (int)$timestamp >= time() - $windowSeconds
        ));
        $events[] = time();
        $_SESSION[$key] = $events;
        return count($events) > $limit;
    }
}


function profile_columns_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema=DATABASE()
               AND table_name="users"
               AND column_name IN (
                    "profile_image_stored_name",
                    "profile_image_mime",
                    "profile_image_updated_at"
               )'
        );
        $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function profile_image_storage_directory(): string
{
    $directory = NMM_ROOT . '/storage/profile-images';

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The profile-image storage directory could not be created.');
    }

    return $directory;
}

function user_profile_image_url(?array $user): string
{
    if (
        !$user
        || empty($user['id'])
        || empty($user['profile_image_stored_name'])
    ) {
        return app_url(
            ($user['role'] ?? 'admin') === 'admin'
                ? 'assets/images/david-evans-profile.jpg'
                : 'assets/images/profile-placeholder.svg'
        );
    }

    $version = rawurlencode((string)(
        $user['profile_image_updated_at']
        ?? $user['updated_at']
        ?? '1'
    ));

    return app_url(
        'portal/profile-image.php?id='
        . (int)$user['id']
        . '&v='
        . $version
    );
}

function primary_admin_profile(): ?array
{
    static $profile = false;

    if ($profile !== false) {
        return $profile ?: null;
    }

    try {
        $profileFields = profile_columns_available()
            ? 'profile_image_stored_name, profile_image_mime,
               profile_image_updated_at'
            : 'NULL AS profile_image_stored_name,
               NULL AS profile_image_mime,
               NULL AS profile_image_updated_at';

        $statement = db()->query(
            'SELECT id, role, email, display_name, company, phone, status, '
            . $profileFields .
            ', updated_at
             FROM users
             WHERE role="admin" AND status="active"
             ORDER BY id ASC
             LIMIT 1'
        );
        $record = $statement->fetch();
        $profile = $record ?: null;
    } catch (Throwable) {
        $profile = null;
    }

    return $profile ?: null;
}

function public_contact_email(): string
{
    return trim((string)(primary_admin_profile()['email'] ?? ''));
}

function public_contact_phone(): string
{
    return trim((string)(primary_admin_profile()['phone'] ?? ''));
}

function public_profile_name(): string
{
    return trim((string)(primary_admin_profile()['display_name'] ?? 'David Evans'));
}

function save_account_profile(
    int $userId,
    array $values,
    ?array $upload = null,
    bool $removeImage = false
): void {
    if (!profile_columns_available()) {
        throw new RuntimeException(
            'Import database/account_profile_v38.sql before saving account profile settings.'
        );
    }

    $displayName = trim((string)($values['display_name'] ?? ''));
    $email = strtolower(trim((string)($values['email'] ?? '')));
    $company = trim((string)($values['company'] ?? ''));
    $phone = trim((string)($values['phone'] ?? ''));

    if ($displayName === '' || strlen($displayName) > 160) {
        throw new RuntimeException('Enter a valid display name.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new RuntimeException('Enter a valid account email.');
    }

    if (strlen($company) > 190 || strlen($phone) > 60) {
        throw new RuntimeException('The company or phone value is too long.');
    }

    $duplicate = db()->prepare(
        'SELECT id
         FROM users
         WHERE email=:email AND id<>:user_id
         LIMIT 1'
    );
    $duplicate->execute([
        'email' => $email,
        'user_id' => $userId,
    ]);

    if ($duplicate->fetchColumn()) {
        throw new RuntimeException('That email address is already used by another account.');
    }

    $currentStatement = db()->prepare(
        'SELECT profile_image_stored_name, profile_image_mime
         FROM users
         WHERE id=:user_id
         LIMIT 1'
    );
    $currentStatement->execute(['user_id' => $userId]);
    $currentProfile = $currentStatement->fetch() ?: [];
    $currentStoredName = (string)($currentProfile['profile_image_stored_name'] ?? '');
    $currentMime = (string)($currentProfile['profile_image_mime'] ?? '');

    $newStoredName = $currentStoredName;
    $newMime = $currentMime !== '' ? $currentMime : null;
    $newImageUploaded = false;

    if (
        is_array($upload)
        && isset($upload['error'])
        && (int)$upload['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The profile photo upload failed.');
        }

        $temporaryPath = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);

        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('The uploaded profile photo is invalid.');
        }

        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException('Profile photos must be 5 MB or smaller.');
        }

        $imageInfo = @getimagesize($temporaryPath);

        if (!is_array($imageInfo)) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($temporaryPath);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Upload a JPG, PNG, WebP, or GIF profile photo.');
        }

        $newStoredName = sprintf(
            'profile-%d-%s.%s',
            $userId,
            bin2hex(random_bytes(16)),
            $extensions[$mime]
        );
        $destination = profile_image_storage_directory() . '/' . $newStoredName;

        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('The profile photo could not be stored.');
        }

        chmod($destination, 0640);
        $newMime = $mime;
        $newImageUploaded = true;
    }

    if ($removeImage && !$newImageUploaded) {
        $newStoredName = '';
        $newMime = null;
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $statement = $pdo->prepare(
            'UPDATE users
             SET display_name=:display_name,
                 email=:email,
                 company=:company,
                 phone=:phone,
                 profile_image_stored_name=:profile_image_stored_name,
                 profile_image_mime=:profile_image_mime,
                 profile_image_updated_at=CASE
                    WHEN :profile_changed=1 THEN UTC_TIMESTAMP()
                    ELSE profile_image_updated_at
                 END
             WHERE id=:user_id'
        );
        $statement->execute([
            'display_name' => $displayName,
            'email' => $email,
            'company' => $company !== '' ? $company : null,
            'phone' => $phone !== '' ? $phone : null,
            'profile_image_stored_name' => $newStoredName !== '' ? $newStoredName : null,
            'profile_image_mime' => $newStoredName !== '' ? $newMime : null,
            'profile_changed' => ($newImageUploaded || $removeImage) ? 1 : 0,
            'user_id' => $userId,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($newImageUploaded && $newStoredName !== '') {
            @unlink(profile_image_storage_directory() . '/' . $newStoredName);
        }

        throw $exception;
    }

    if (
        ($newImageUploaded || $removeImage)
        && $currentStoredName !== ''
        && $currentStoredName !== $newStoredName
    ) {
        @unlink(profile_image_storage_directory() . '/' . basename($currentStoredName));
    }

    log_activity('account_profile_updated', 'user', $userId, [
        'profile_image_changed' => $newImageUploaded || $removeImage,
    ]);
}

function current_user(): ?array
{
    static $cachedId = null;
    static $cached = null;

    $id = (int)($_SESSION['user_id'] ?? 0);

    if ($id <= 0) {
        return null;
    }

    if ($cachedId === $id) {
        return $cached;
    }

    $profileFields = profile_columns_available()
        ? 'profile_image_stored_name, profile_image_mime,
           profile_image_updated_at'
        : 'NULL AS profile_image_stored_name,
           NULL AS profile_image_mime,
           NULL AS profile_image_updated_at';

    $statement = db()->prepare(
        'SELECT id, role, email, display_name, company, phone, status,
                must_change_password, '
        . $profileFields .
        ', last_login_at, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }

    $cachedId = $id;
    $cached = $user;
    return $user;
}

function log_activity(
    string $event,
    ?string $entityType = null,
    ?int $entityId = null,
    array $metadata = []
): void {
    try {
        $statement = db()->prepare(
            'INSERT INTO activity_log
                (user_id, event_type, entity_type, entity_id, metadata_json)
             VALUES
                (:user_id, :event_type, :entity_type, :entity_id, :metadata_json)'
        );
        $statement->execute([
            'user_id' => current_user()['id'] ?? null,
            'event_type' => $event,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata
                ? json_encode($metadata, JSON_THROW_ON_ERROR)
                : null,
        ]);
    } catch (Throwable) {
    }
}

function login_blocked(string $email, string $ip): bool
{
    $security = nmm_config('security');
    $window = max(60, (int)($security['login_window_seconds'] ?? 900));
    $emailLimit = max(1, (int)($security['login_email_limit'] ?? 5));
    $ipLimit = max(1, (int)($security['login_ip_limit'] ?? 20));
    $threshold = gmdate('Y-m-d H:i:s', time() - $window);

    $emailStatement = db()->prepare(
        'SELECT COUNT(*)
         FROM login_attempts
         WHERE success = 0
           AND email = :email
           AND created_at >= :threshold'
    );
    $emailStatement->execute([
        'email' => strtolower($email),
        'threshold' => $threshold,
    ]);

    if ((int)$emailStatement->fetchColumn() >= $emailLimit) {
        return true;
    }

    $ipStatement = db()->prepare(
        'SELECT COUNT(*)
         FROM login_attempts
         WHERE success = 0
           AND ip_address = :ip_address
           AND created_at >= :threshold'
    );
    $ipStatement->execute([
        'ip_address' => $ip,
        'threshold' => $threshold,
    ]);

    return (int)$ipStatement->fetchColumn() >= $ipLimit;
}

function attempt_login(string $email, string $password, string $role): bool
{
    $email = strtolower(trim($email));
    $ip = request_ip();

    if (login_blocked($email, $ip)) {
        return false;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM users
         WHERE email = :email
           AND role = :role
           AND status = "active"
         LIMIT 1'
    );
    $statement->execute([
        'email' => $email,
        'role' => $role,
    ]);
    $user = $statement->fetch();

    $valid = $user && password_verify($password, (string)$user['password_hash']);

    db()->prepare(
        'INSERT INTO login_attempts (email, ip_address, success)
         VALUES (:email, :ip_address, :success)'
    )->execute([
        'email' => $email,
        'ip_address' => $ip,
        'success' => $valid ? 1 : 0,
    ]);

    if (!$valid) {
        return false;
    }

    session_regenerate_id(true);
    rotate_csrf_token();

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['session_created_at'] = time();
    $_SESSION['session_last_activity'] = time();
    $_SESSION['session_regenerated_at'] = time();
    $_SESSION['session_user_agent'] = hash(
        'sha256',
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
    );

    db()->prepare(
        'UPDATE users
         SET last_login_at = UTC_TIMESTAMP()
         WHERE id = :id'
    )->execute(['id' => $user['id']]);

    log_activity('login', 'user', (int)$user['id'], ['role' => $role]);
    return true;
}

function logout_user(): void
{
    if (current_user()) {
        log_activity('logout', 'user', (int)current_user()['id']);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'],
            'domain' => $parameters['domain'],
            'secure' => $parameters['secure'],
            'httponly' => $parameters['httponly'],
            'samesite' => $parameters['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function require_role(string $role): array
{
    $user = current_user();

    if (!$user || $user['role'] !== $role) {
        redirect('portal/login.php?role=' . urlencode($role));
    }

    if (
        (int)$user['must_change_password'] === 1
        && (string)($_GET['view'] ?? '') !== 'account'
    ) {
        redirect(
            'portal/' .
            ($role === 'admin' ? 'admin.php' : 'client.php') .
            '?view=account&required=1'
        );
    }

    return $user;
}

function enforce_authenticated_action_limit(array $user): void
{
    $security = nmm_config('security');
    $isAdmin = $user['role'] === 'admin';
    $limit = $isAdmin
        ? max(10, (int)($security['admin_action_limit'] ?? 120))
        : max(5, (int)($security['client_action_limit'] ?? 45));
    $window = max(30, (int)($security['action_window_seconds'] ?? 60));

    if (
        rate_limit_exceeded(
            $isAdmin ? 'admin_action' : 'client_action',
            (string)$user['id'],
            $limit,
            $window
        )
    ) {
        http_response_code(429);
        exit('Too many actions were submitted. Wait briefly and try again.');
    }
}

function portal_header(string $title, string $active, array $user): void
{
    $GLOBALS['nmm_portal_active_view'] = $active;
    $isAdmin = $user['role'] === 'admin';

    $navigation = $isAdmin
        ? []
        : [
            'dashboard' => 'Dashboard',
            'call-center' => 'Call Us',
            'projects' => 'Projects',
            'communications' => 'Communications',
            'notifications' => 'Notifications',
            'files' => 'Files',
            'feeds' => 'Feed Reader',
            'account' => 'Account',
        ];

    $adminNavigationGroups = [
        'Operations' => [
            'dashboard' => 'Dashboard',
            'inbox' => 'Unified Inbox',
            'music' => 'Music Library',
            'analytics' => 'Visitor Intelligence',
            'site-analytics' => 'Site Analytics',
            'call-center' => 'Call Center',
            'communications' => 'Communications',
            'notifications' => 'Notifications',
        ],
        'Relationships' => [
            'clients' => 'Clients',
            'crm' => 'CRM',
            'leads' => 'Leads',
            'administrators' => 'Administrators',
        ],
        'Work' => [
            'portfolio' => 'Portfolio',
            'blog' => 'Blog',
            'feeds' => 'Feed Reader',
            'events' => 'Events',
            'bookings' => 'Bookings',
            'proposals' => 'Proposals',
            'resume' => 'Resume Posts',
            'projects' => 'Client Projects',
            'files' => 'Files',
            'knowledge' => 'Knowledge Base',
            'builder' => 'Page Editor',
            'menus' => 'Navigation',
        ],
        'System' => [
            'settings' => 'Settings',
            'account' => 'Account',
        ],
    ];

    $landingPageEditorEnabled = $isAdmin && nmm_module_enabled('landing_page');
    if ($isAdmin && !$landingPageEditorEnabled) {
        unset($adminNavigationGroups['Work']['builder']);
    }

    if (!nmm_module_enabled('feed_reader', true)) {
        if ($isAdmin) {
            unset($adminNavigationGroups['Work']['feeds']);
        } else {
            unset($navigation['feeds']);
        }
    }

    $script = $isAdmin ? 'admin.php' : 'client.php';
    $flashes = pull_flashes();
    require_once __DIR__ . '/notifications.php';

    $accountUrl = app_url('portal/' . $script . '?view=account');
    $messagesUrl = app_url('portal/' . $script . '?view=communications');
    $callCenterUrl = app_url('portal/' . $script . '?view=call-center');
    $notificationsUrl = app_url('portal/' . $script . '?view=notifications');
    $notificationApiUrl = app_url('portal/notifications-api.php');
    $callCenterApiUrl = $isAdmin
        ? app_url('portal/call-center-api.php')
        : '';
    $adminAssistantApiUrl = $isAdmin
        ? app_url('portal/admin-assistant-api.php')
        : '';
    $notificationCount = notification_unread_count((int)$user['id']);
    $recentNotifications = notification_recent((int)$user['id'], 6, false);
    $roleLabel = $isAdmin ? 'Administrator' : 'Client';
    $profileImageUrl = user_profile_image_url($user);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> — <?= e(setting('site_name', 'North Mountain Media')) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/portal.css?v=20260728-content-controls-v62.1')) ?>">
    <?php if($active==='feeds'):?><link rel="stylesheet" href="<?= e(app_url('assets/css/feed-reader.css?v=20260728-content-controls-v62.1')) ?>"><?php endif;?>
    <?php if($active==='inbox'):?><link rel="stylesheet" href="<?= e(app_url('assets/css/unified-inbox.css?v=20260730-v66D')) ?>"><?php endif;?>
</head>
<body
    class="portal-body"
    data-portal-role="<?= e($user['role']) ?>"
    data-notification-api="<?= e($notificationApiUrl) ?>"
    data-call-center-api="<?= e($callCenterApiUrl) ?>"
    data-admin-assistant-api="<?= e($adminAssistantApiUrl) ?>"
>
<div class="portal-shell">
    <aside class="portal-sidebar <?= $isAdmin ? 'portal-sidebar-admin' : '' ?>" id="portalSidebar">
        <div class="portal-brand">
            <a href="<?= e(app_url('portal/' . $script)) ?>">
                <img src="<?= e(nmm_site_logo_url()) ?>" alt="<?= e(nmm_site_logo_alt()) ?>">
            </a>
            <button type="button" class="portal-sidebar-close" data-sidebar-close aria-label="Close navigation">×</button>
        </div>

        <?php if (!$isAdmin): ?>
            <div class="portal-role">
                <span>Client Portal</span>
                <strong><?= e($user['display_name']) ?></strong>
                <?php if (!empty($user['company'])): ?>
                    <small><?= e($user['company']) ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <nav
                class="portal-nav portal-nav-admin"
                aria-label="Administrator navigation"
                data-admin-navigation
            >
                <?php foreach ($adminNavigationGroups as $groupLabel => $groupItems): ?>
                    <?php
                    $groupId = 'admin-nav-' . strtolower(
                        preg_replace('/[^a-z0-9]+/i', '-', $groupLabel)
                    );
                    $groupActive = array_key_exists($active, $groupItems);
                    ?>
                    <section
                        class="portal-nav-group"
                        data-nav-group
                    >
                        <button
                            class="portal-nav-group-toggle"
                            type="button"
                            aria-expanded="true"
                            aria-controls="<?= e($groupId) ?>"
                            data-nav-group-toggle
                        >
                            <span><?= e($groupLabel) ?></span>
                            <span aria-hidden="true">⌃</span>
                        </button>

                        <div
                            class="portal-nav-group-links"
                            id="<?= e($groupId) ?>"
                            data-nav-group-links
                        >
                            <?php foreach ($groupItems as $key => $label): ?>
                                <a
                                    class="<?= $active === $key ? 'active' : '' ?>"
                                    href="<?= e(app_url(
                                        'portal/' . $script .
                                        ($key === 'dashboard' ? '' : '?view=' . $key)
                                    )) ?>"
                                ><?= e($label) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </nav>
        <?php else: ?>
            <nav class="portal-nav" aria-label="<?= e($roleLabel) ?> navigation">
                <?php foreach ($navigation as $key => $label): ?>
                    <a
                        class="<?= $active === $key ? 'active' : '' ?>"
                        href="<?= e(app_url(
                            'portal/' . $script .
                            ($key === 'dashboard' ? '' : '?view=' . $key)
                        )) ?>"
                    ><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="portal-sidebar-foot">
            <a href="<?= e(app_url('index.php')) ?>">Public site</a>
            <a href="<?= e(app_url('portal/logout.php')) ?>">Sign out</a>
        </div>
    </aside>

    <button
        class="portal-sidebar-backdrop"
        data-sidebar-close
        type="button"
        aria-label="Close navigation"
    ></button>

    <main class="portal-main">
        <header class="portal-topbar">
            <button
                class="portal-menu-button"
                data-sidebar-open
                type="button"
                aria-label="Open navigation"
            >
                <span></span><span></span><span></span>
            </button>
            <?php nmm_render_mobile_brand(); ?>

            <div class="portal-title-block">
                <span><?= $isAdmin ? 'North Mountain Media' : 'Client workspace' ?></span>
                <h1><?= e($title) ?></h1>
            </div>

            <div class="portal-header-user">
                <a class="portal-top-action" href="<?= e($callCenterUrl) ?>">
                    <?= $isAdmin ? 'Call Center' : 'Call Us' ?>
                </a>

                <div class="portal-notification-wrap">
                    <button
                        class="portal-notification-button"
                        type="button"
                        data-notification-toggle
                        aria-expanded="false"
                        aria-label="Open notifications"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M10 21h4"></path>
                        </svg>
                        <span
                            data-notification-count
                            <?= $notificationCount > 0 ? '' : 'hidden' ?>
                        ><?= $notificationCount ?></span>
                    </button>

                    <section class="portal-notification-menu" data-notification-menu hidden>
                        <header>
                            <div>
                                <span>Activity feed</span>
                                <strong>Notifications</strong>
                            </div>
                            <a href="<?= e($notificationsUrl) ?>">View all</a>
                        </header>

                        <div data-notification-preview-list>
                            <?php foreach ($recentNotifications as $notification): ?>
                                <a
                                    class="<?= (int)$notification['is_read'] === 0 ? 'unread' : '' ?>"
                                    href="<?= e(notification_portal_link($user, $notification['link_url'])) ?>"
                                    data-notification-preview
                                    data-notification-id="<?= (int)$notification['id'] ?>"
                                >
                                    <span><?= e(notification_category_icon((string)$notification['category'])) ?></span>
                                    <span>
                                        <strong><?= e($notification['title']) ?></strong>
                                        <small><?= e(format_datetime($notification['created_at'])) ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>

                            <?php if (!$recentNotifications): ?>
                                <div class="portal-notification-empty">No notifications yet.</div>
                            <?php endif; ?>
                        </div>

                        <footer>
                            <a href="<?= e($messagesUrl) ?>">Communications</a>
                            <a href="<?= e($callCenterUrl) ?>"><?= $isAdmin ? 'Open Call Center' : 'Call Us' ?></a>
                        </footer>
                    </section>
                </div>

                <a
                    class="portal-user-info"
                    href="<?= e($accountUrl) ?>"
                    aria-label="Open account settings"
                >
                    <span class="portal-user-avatar" aria-hidden="true">
                        <img
                            src="<?= e($profileImageUrl) ?>"
                            alt=""
                        >
                    </span>

                    <span class="portal-user-copy">
                        <strong><?= e($user['display_name']) ?></strong>
                        <small><?= e($user['company'] ?: $roleLabel) ?></small>
                    </span>
                </a>
            </div>
        </header>

        <?php if ($isAdmin): ?>
            <section class="portal-global-call-alert" data-global-call-alert hidden>
                <span class="communication-call-pulse" aria-hidden="true"></span>
                <div>
                    <small>Incoming public browser call</small>
                    <strong data-global-call-name>Website visitor</strong>
                    <em data-global-call-subject>New public call</em>
                </div>
                <a class="button button-primary button-small" data-global-call-open href="<?= e($callCenterUrl) ?>">Open Call Center</a>
                <button type="button" data-global-call-dismiss aria-label="Dismiss call alert">×</button>
            </section>
        <?php endif; ?>

        <div class="portal-content">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
    <?php
}

function portal_footer(): void
{
    $footerUser = current_user();
    $isAdmin = $footerUser && $footerUser['role'] === 'admin';
    $active = (string)($GLOBALS['nmm_portal_active_view'] ?? '');
    ?>
        </div>

        <?php if ($isAdmin): ?>
            <section
                class="admin-assistant-loading"
                data-admin-assistant-loading
                aria-live="polite"
                hidden
            >
                <div class="admin-assistant-loading-orb" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
                <strong>Querying North Mountain data</strong>
                <small>Reviewing calls, communications, CRM, and current work.</small>
            </section>

            <section
                class="admin-assistant-chat"
                data-admin-assistant-chat
                aria-label="North Mountain administrator assistant"
                hidden
            >
                <header>
                    <div>
                        <span>North Mountain Admin Assistant</span>
                        <strong>Operations chat</strong>
                    </div>
                    <div>
                        <button type="button" data-admin-chat-new>New chat</button>
                        <button type="button" data-admin-chat-close aria-label="Close assistant">×</button>
                    </div>
                </header>

                <div
                    class="admin-assistant-messages"
                    data-admin-assistant-messages
                    aria-live="polite"
                ></div>
            </section>

            <section class="admin-assistant-footer" data-admin-assistant-footer>
                <button
                    class="admin-assistant-launcher-backdrop"
                    type="button"
                    data-admin-launcher-backdrop
                    aria-label="Close administrator tools"
                    hidden
                ></button>

                <div
                    class="admin-assistant-quick-menu admin-assistant-launcher-modal"
                    data-admin-assistant-quick-menu
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="admin-launcher-title"
                    hidden
                >
                    <header class="admin-assistant-launcher-header">
                        <div>
                            <span>Administrator tools</span>
                            <strong id="admin-launcher-title">Quick access</strong>
                        </div>
                        <button type="button" data-admin-quick-close aria-label="Close administrator tools">×</button>
                    </header>

                    <nav class="admin-assistant-launcher-tabs" role="tablist" aria-label="Administrator tool categories">
                        <button
                            type="button"
                            id="admin-launcher-tab-queries"
                            role="tab"
                            aria-selected="true"
                            aria-controls="admin-launcher-panel-queries"
                            data-admin-launcher-tab="queries"
                            class="is-active"
                        >Data queries</button>
                        <button
                            type="button"
                            id="admin-launcher-tab-actions"
                            role="tab"
                            aria-selected="false"
                            aria-controls="admin-launcher-panel-actions"
                            data-admin-launcher-tab="actions"
                        >Actions</button>
                    </nav>

                    <div class="admin-assistant-launcher-body">
                        <section
                            class="admin-assistant-launcher-panel is-active"
                            id="admin-launcher-panel-queries"
                            role="tabpanel"
                            aria-labelledby="admin-launcher-tab-queries"
                            data-admin-launcher-panel="queries"
                        >
                            <div class="admin-assistant-launcher-intro">
                                <strong>Quick data queries</strong>
                                <span>Ask the assistant to review live portal records.</span>
                            </div>
                            <div class="admin-assistant-query-grid">
                                <button type="button" data-admin-quick-prompt="Most recent call history"><strong>Recent calls</strong><small>Review the latest call history</small></button>
                                <button type="button" data-admin-quick-prompt="Missed messages"><strong>Missed messages</strong><small>Find calls and messages needing attention</small></button>
                                <button type="button" data-admin-quick-prompt="CRM contacts needing attention"><strong>CRM attention</strong><small>Surface contacts needing follow-up</small></button>
                                <button type="button" data-admin-quick-prompt="Music Library"><strong>Music Library</strong><small>Review catalog and listening activity</small></button>
                                <button type="button" data-admin-quick-prompt="Visitor activity"><strong>Visitor activity</strong><small>Inspect recent website engagement</small></button>
                                <button type="button" data-admin-quick-prompt="Portfolio performance"><strong>Portfolio performance</strong><small>Review project views and conversions</small></button>
                                <button type="button" data-admin-quick-prompt="Unread communications"><strong>Communications</strong><small>Show unread conversations</small></button>
                                <button type="button" data-admin-quick-prompt="Open projects"><strong>Open projects</strong><small>Summarize current project work</small></button>
                                <button type="button" data-admin-quick-prompt="Unread notifications"><strong>Notifications</strong><small>Review unread portal activity</small></button>
                            </div>
                        </section>

                        <section
                            class="admin-assistant-launcher-panel"
                            id="admin-launcher-panel-actions"
                            role="tabpanel"
                            aria-labelledby="admin-launcher-tab-actions"
                            data-admin-launcher-panel="actions"
                            hidden
                        >
                            <div class="admin-assistant-launcher-intro">
                                <strong>Dashboard actions</strong>
                                <span>Open operational tools or create a new record.</span>
                            </div>
                            <div class="admin-assistant-action-grid">
                                <a href="<?= e(app_url('portal/admin.php?view=call-center')) ?>"><span>Open</span><strong>Call Center</strong><small>Calls, voicemail, and callbacks</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=communications')) ?>"><span>Open</span><strong>Communications</strong><small>Messages and active conversations</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=notifications')) ?>"><span>Open</span><strong>Notifications</strong><small>Unread portal activity</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=crm')) ?>"><span>Open</span><strong>CRM</strong><small>Contacts, leads, and opportunities</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=analytics')) ?>"><span>Open</span><strong>Visitor Intelligence</strong><small>Traffic and conversion analytics</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=site-analytics')) ?>"><span>Open</span><strong>Site Analytics</strong><small>Website and Music Library activity</small></a>
                                <?php if ($landingPageEditorEnabled): ?>
                                    <a href="<?= e(app_url('portal/admin.php?view=builder')) ?>"><span>Build</span><strong>Page Editor</strong><small>Visual landing pages, sections, and blocks</small></a>
                                <?php endif; ?>
                                <a href="<?= e(app_url('portal/admin.php?view=menus')) ?>"><span>Manage</span><strong>Navigation</strong><small>Menus, dropdowns, and locations</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=blog&edit=new')) ?>"><span>Create</span><strong>Blog post</strong><small>Start a new article</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=events&edit=new')) ?>"><span>Create</span><strong>Event</strong><small>Add an event or registration page</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=bookings&type=new')) ?>"><span>Create</span><strong>Appointment type</strong><small>Configure a booking option</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=proposals&edit=new')) ?>"><span>Create</span><strong>Proposal</strong><small>Build a proposal or estimate</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=resume&edit=new')) ?>"><span>Create</span><strong>Resume post</strong><small>Add a resume or career entry</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=projects&edit=new')) ?>"><span>Create</span><strong>Project</strong><small>Start a client project</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=clients&edit=new')) ?>"><span>Create</span><strong>Client</strong><small>Add a client portal account</small></a>
                                <a href="<?= e(app_url('portal/admin.php?view=files')) ?>"><span>Manage</span><strong>Upload file</strong><small>Open the protected file manager</small></a>
                            </div>
                        </section>
                    </div>
                </div>

                <form class="admin-assistant-composer" data-admin-assistant-form>
                    <button
                        class="admin-assistant-plus"
                        type="button"
                        data-admin-quick-toggle
                        aria-expanded="false"
                        aria-label="Open administrator tools"
                    >+</button>

                    <textarea
                        rows="1"
                        maxlength="500"
                        data-admin-assistant-input
                        placeholder="Ask about calls, messages, CRM contacts, projects, clients, or notifications…"
                        aria-label="Ask the administrator assistant"
                    ></textarea>

                    <button
                        class="admin-assistant-submit"
                        type="submit"
                        aria-label="Send query"
                    >↑</button>
                </form>

                <small>
                    Uses protected, predefined queries against the live portal database.
                </small>
            </section>
        <?php endif; ?>
    </main>
</div>

<section
    class="portal-confirm-modal"
    data-confirm-modal
    aria-hidden="true"
    hidden
>
    <button
        class="portal-confirm-backdrop"
        type="button"
        data-confirm-cancel
        aria-label="Cancel confirmation"
    ></button>
    <div
        class="portal-confirm-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="portal-confirm-title"
        aria-describedby="portal-confirm-message"
    >
        <div class="portal-confirm-icon" aria-hidden="true">!</div>
        <div class="portal-confirm-copy">
            <span data-confirm-eyebrow>Confirm action</span>
            <h2 id="portal-confirm-title" data-confirm-title>Are you sure?</h2>
            <p id="portal-confirm-message" data-confirm-message>This action cannot be undone.</p>
        </div>
        <div class="portal-confirm-actions">
            <button class="button" type="button" data-confirm-cancel>Cancel</button>
            <button class="button button-danger" type="button" data-confirm-accept>Continue</button>
        </div>
    </div>
</section>

<script src="<?= e(app_url('assets/js/portal.js?v=20260728-content-controls-v62.1')) ?>"></script>
<?php if($active==='feeds'):?><script src="<?= e(app_url('assets/js/feed-reader.js?v=20260728-content-controls-v62.1')) ?>"></script><?php endif;?>
</body>
</html>
    <?php
}

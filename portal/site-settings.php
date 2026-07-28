<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

function nmm_setting_bool(string $key, bool $fallback = false): bool
{
    $value = setting($key, $fallback ? '1' : '0');
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','on','enabled'], true);
}

function nmm_module_definitions(): array
{
    return [
        'landing_page' => ['label' => 'Landing Page', 'description' => 'Use the selected landing-page template as the public home page.', 'default' => false],
        'portfolio' => ['label' => 'Portfolio', 'description' => 'Show portfolio projects, case studies, and portfolio navigation.', 'default' => true],
        'resume' => ['label' => 'Resume', 'description' => 'Show the public resume and resume posts.', 'default' => true],
        'music_library' => ['label' => 'Music Library', 'description' => 'Show the public music catalog, albums, playlists, and tracks.', 'default' => true],
        'blog' => ['label' => 'Blog', 'description' => 'Show the public blog archive and published posts.', 'default' => true],
        'feed_reader' => ['label' => 'Feed Reader', 'description' => 'Allow authenticated users to subscribe to and read external RSS and Atom feeds.', 'default' => true],
        'events' => ['label' => 'Events', 'description' => 'Show the public events calendar and event pages.', 'default' => true],
        'bookings' => ['label' => 'Bookings', 'description' => 'Show public appointment types and available booking times.', 'default' => true],
        'project_intake' => ['label' => 'Project Intake', 'description' => 'Show the public project-intake workflow.', 'default' => true],
        'call_us' => ['label' => 'Call Us', 'description' => 'Show the public browser-call and contact experience.', 'default' => true],
    ];
}

function nmm_module_enabled(string $module, ?bool $fallback = null): bool
{
    $definitions = nmm_module_definitions();
    $default = $fallback ?? (bool)($definitions[$module]['default'] ?? false);
    return nmm_setting_bool('module_' . $module . '_enabled', $default);
}

function nmm_require_public_module(string $module): void
{
    if (nmm_module_enabled($module)) {
        return;
    }
    $viewer = function_exists('current_user') ? current_user() : null;
    if ($viewer && ($viewer['role'] ?? '') === 'admin') {
        return;
    }

    http_response_code(404);
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow', true);
    $siteName = setting('site_name', 'North Mountain Media') ?: 'North Mountain Media';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow"><title>Page unavailable — ' . e($siteName) . '</title>';
    echo '<style>body{margin:0;background:#f5f7fa;color:#142033;font:16px/1.6 system-ui}.box{max-width:680px;margin:12vh auto;padding:36px;background:#fff;border:1px solid #dfe5ec;border-radius:24px;box-shadow:0 24px 60px rgba(15,23,42,.08)}a{display:inline-flex;margin-top:12px;padding:11px 16px;border-radius:999px;background:#142033;color:#fff;text-decoration:none;font-weight:700}</style></head><body>';
    echo '<main class="box"><p>North Mountain Media</p><h1>This page is currently unavailable.</h1><p>The module has been disabled in site settings.</p><a href="' . e(app_url('index.php')) . '">Return home</a></main></body></html>';
    exit;
}

function nmm_site_setting(string $key, string $fallback = ''): string
{
    return trim((string)setting($key, $fallback));
}

function nmm_site_logo_mode(): string
{
    $mode = nmm_site_setting('mobile_header_logo_mode', 'logo');
    return in_array($mode, ['logo','name','hidden'], true) ? $mode : 'logo';
}

function nmm_landing_template(): string
{
    $template = nmm_site_setting('landing_template', 'split');
    return in_array($template, ['split','centered','editorial','showcase'], true)
        ? $template
        : 'split';
}

function nmm_site_storage_directory(string $type): string
{
    $directory = NMM_ROOT . '/storage/' . ($type === 'logo' ? 'site-branding' : 'landing-pages');
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The site image storage directory could not be created.');
    }
    return $directory;
}

function nmm_store_site_image(?array $upload, string $type): ?array
{
    if (!is_array($upload) || !isset($upload['error']) || (int)$upload['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image upload did not complete.');
    }

    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($temporary === '' || !is_uploaded_file($temporary) || $size <= 0 || $size > 8 * 1024 * 1024) {
        throw new RuntimeException('Upload a valid image no larger than 8 MB.');
    }
    if (!is_array(@getimagesize($temporary))) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Images must be JPG, PNG, WebP, or GIF files.');
    }

    $prefix = preg_replace('/[^a-z0-9-]+/', '-', strtolower($type)) ?: 'site-image';
    $storedName = $prefix . '-' . bin2hex(random_bytes(18)) . '.' . $extensions[$mime];
    $destination = nmm_site_storage_directory($type === 'logo' ? 'logo' : 'landing') . '/' . $storedName;
    if (!move_uploaded_file($temporary, $destination)) {
        throw new RuntimeException('The uploaded image could not be stored.');
    }
    chmod($destination, 0640);
    return ['stored_name' => $storedName, 'mime' => $mime];
}

function nmm_site_media_url(string $slot): string
{
    $map = [
        'logo' => ['key' => 'site_logo_stored_name', 'fallback' => 'assets/images/north-mountain-media-logo.png'],
        'hero' => ['key' => 'landing_hero_image_stored_name', 'fallback' => ''],
        'secondary' => ['key' => 'landing_secondary_image_stored_name', 'fallback' => ''],
        'social' => ['key' => 'seo_social_image_stored_name', 'fallback' => ''],
    ];
    if (!isset($map[$slot])) {
        return '';
    }
    $storedName = nmm_site_setting($map[$slot]['key']);
    if ($storedName === '') {
        return $map[$slot]['fallback'] !== '' ? app_url($map[$slot]['fallback']) : '';
    }
    return app_url('site-media.php?slot=' . rawurlencode($slot));
}

function nmm_site_logo_url(): string
{
    return nmm_site_media_url('logo');
}

function nmm_site_logo_alt(): string
{
    return nmm_site_setting('site_logo_alt', setting('site_name', 'North Mountain Media') ?: 'North Mountain Media');
}

function nmm_landing_features(): array
{
    $raw = nmm_site_setting('landing_features', "Strategy and planning|Translate goals into a clear system and launch path.\nConnected execution|Bring content, CRM, commerce, and client operations together.\nMeasurable progress|Use practical workflows, reporting, and follow-through.");
    $features = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        [$title, $description] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
        $features[] = ['title' => $title, 'description' => $description];
        if (count($features) >= 6) {
            break;
        }
    }
    return $features;
}

function nmm_render_mobile_brand(): void
{
    $mode = nmm_site_logo_mode();
    if ($mode === 'hidden') {
        return;
    }
    $siteName = setting('site_name', 'North Mountain Media') ?: 'North Mountain Media';
    echo '<a class="nmm-mobile-brand nmm-mobile-brand-' . e($mode) . '" href="' . e(app_url('index.php')) . '">';
    if ($mode === 'logo') {
        echo '<img src="' . e(nmm_site_logo_url()) . '" alt="' . e(nmm_site_logo_alt()) . '">';
    } else {
        echo '<strong>' . e($siteName) . '</strong>';
    }
    echo '</a>';
}

function nmm_render_seo_meta(string $pageTitle = '', string $description = '', string $canonicalPath = 'index.php'): void
{
    $siteName = setting('site_name', 'North Mountain Media') ?: 'North Mountain Media';
    $seoTitle = nmm_site_setting('seo_title', $pageTitle !== '' ? $pageTitle : $siteName);
    $seoDescription = nmm_site_setting('seo_description', $description);
    $keywords = nmm_site_setting('seo_keywords');
    $robots = nmm_setting_bool('seo_index_enabled', true) ? 'index,follow' : 'noindex,nofollow';
    $canonicalBase = rtrim(nmm_site_setting('seo_site_url'), '/');
    $canonical = $canonicalBase !== '' ? $canonicalBase . '/' . ltrim($canonicalPath, '/') : '';
    $socialImage = nmm_site_media_url('social');

    echo '<title>' . e($seoTitle) . '</title>' . PHP_EOL;
    if ($seoDescription !== '') {
        echo '<meta name="description" content="' . e($seoDescription) . '">' . PHP_EOL;
        echo '<meta property="og:description" content="' . e($seoDescription) . '">' . PHP_EOL;
    }
    if ($keywords !== '') {
        echo '<meta name="keywords" content="' . e($keywords) . '">' . PHP_EOL;
    }
    echo '<meta name="robots" content="' . e($robots) . '">' . PHP_EOL;
    echo '<meta property="og:title" content="' . e($seoTitle) . '">' . PHP_EOL;
    echo '<meta property="og:type" content="website">' . PHP_EOL;
    if ($canonical !== '') {
        echo '<link rel="canonical" href="' . e($canonical) . '">' . PHP_EOL;
        echo '<meta property="og:url" content="' . e($canonical) . '">' . PHP_EOL;
    }
    if ($socialImage !== '') {
        echo '<meta property="og:image" content="' . e($socialImage) . '">' . PHP_EOL;
    }
}

function nmm_normalize_public_link(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strlen($value) > 500) {
        throw new RuntimeException('Button links must be 500 characters or shorter.');
    }
    if (preg_match('~^(https?://|mailto:|tel:|/|\./|\.\./|[a-z0-9][a-z0-9/_-]*\.php(?:[?#].*)?|#)~i', $value)) {
        return $value;
    }
    throw new RuntimeException('Use a valid HTTPS, email, phone, anchor, or site-relative button link.');
}

function nmm_remove_site_media_file(string $storedName, string $type): void
{
    $storedName = basename(trim($storedName));
    if ($storedName === '') {
        return;
    }
    $directory = NMM_ROOT . '/storage/' . ($type === 'logo' ? 'site-branding' : 'landing-pages');
    $file = $directory . '/' . $storedName;
    if (is_file($file)) {
        @unlink($file);
    }
}

function nmm_public_link_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('~^(https?://|mailto:|tel:|#)~i', $value)) {
        return $value;
    }
    return app_url($value);
}

<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('music_library');
require_once __DIR__ . '/portal/music-library.php';

$banner = music_banner_settings();
$user = current_user();
$isAdmin = $user && $user['role'] === 'admin';
$isPreview = isset($_GET['preview']) && $_GET['preview'] === '1';

if (
    !music_banner_image_exists($banner)
    || (
        !$banner['enabled']
        && !($isAdmin && $isPreview)
    )
) {
    http_response_code(404);
    exit;
}

$storedName = basename((string)$banner['stored_name']);
$path = music_banner_storage_directory()
    . '/'
    . $storedName;

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    exit;
}

$modified = filemtime($path) ?: time();
$etag = '"'
    . hash(
        'sha256',
        $storedName
        . '|'
        . $modified
        . '|'
        . $size
    )
    . '"';

header(
    'Content-Type: '
    . (
        (string)$banner['mime_type'] !== ''
            ? (string)$banner['mime_type']
            : 'image/jpeg'
    )
);
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header(
    'Cache-Control: '
    . ($isPreview ? 'private, no-store' : 'public, max-age=86400, must-revalidate')
);
header(
    'Last-Modified: '
    . gmdate('D, d M Y H:i:s', $modified)
    . ' GMT'
);
header('ETag: ' . $etag);

if (
    !$isPreview
    && trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag
) {
    http_response_code(304);
    exit;
}

readfile($path);
exit;

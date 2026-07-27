<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/bootstrap.php';

$userId = query_int('id');

if (!profile_columns_available()) {
    http_response_code(404);
    exit;
}

if ($userId <= 0) {
    http_response_code(404);
    exit;
}

$statement = db()->prepare(
    'SELECT id, role, status, profile_image_stored_name,
            profile_image_mime, profile_image_updated_at
     FROM users
     WHERE id=:user_id
     LIMIT 1'
);
$statement->execute(['user_id' => $userId]);
$profile = $statement->fetch();

if (
    !$profile
    || $profile['status'] !== 'active'
    || empty($profile['profile_image_stored_name'])
) {
    http_response_code(404);
    exit;
}

$current = current_user();
$primaryAdministrator = primary_admin_profile();
$publicAdministrator = (
    $profile['role'] === 'admin'
    && $primaryAdministrator
    && (int)$primaryAdministrator['id'] === (int)$profile['id']
);
$ownProfile = $current && (int)$current['id'] === (int)$profile['id'];

if (!$publicAdministrator && !$ownProfile) {
    http_response_code(403);
    exit;
}

$storedName = basename((string)$profile['profile_image_stored_name']);
$file = profile_image_storage_directory() . '/' . $storedName;

if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit;
}

$mime = trim((string)($profile['profile_image_mime'] ?? ''));

if (!in_array($mime, [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
], true)) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file);
}

$modified = filemtime($file) ?: time();
$etag = '"' . hash('sha256', $storedName . '|' . $modified . '|' . filesize($file)) . '"';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($file));
header(
    'Cache-Control: '
    . ($publicAdministrator
        ? 'public, max-age=86400, immutable'
        : 'private, max-age=3600')
);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
header('ETag: ' . $etag);

if (
    trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag
) {
    http_response_code(304);
    exit;
}

readfile($file);

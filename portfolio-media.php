<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('portfolio');
require_once __DIR__ . '/portal/portfolio.php';

$mediaId = query_int('id');

if (!portfolio_schema_available() || $mediaId <= 0) {
    http_response_code(404);
    exit;
}

$statement = db()->prepare(
    'SELECT media.*,
            project.status AS project_status,
            project.published_at
     FROM portfolio_media media
     JOIN portfolio_projects project
       ON project.id=media.project_id
     WHERE media.id=:media_id
     LIMIT 1'
);
$statement->execute(['media_id' => $mediaId]);
$media = $statement->fetch();

if (!$media) {
    http_response_code(404);
    exit;
}

$currentUser = current_user();
$adminPreview = $currentUser && $currentUser['role'] === 'admin';
$publicProject = (
    $media['project_status'] === 'active'
    && (
        empty($media['published_at'])
        || strtotime((string)$media['published_at']) <= time()
    )
);

if (!$adminPreview && !$publicProject) {
    http_response_code(404);
    exit;
}

$storedName = basename((string)$media['stored_name']);
$file = portfolio_storage_directory() . '/' . $storedName;

if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit;
}

$mime = (string)$media['mime_type'];

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
    http_response_code(415);
    exit;
}

$modified = filemtime($file) ?: time();
$etag = '"' . hash(
    'sha256',
    $storedName . '|' . $modified . '|' . filesize($file)
) . '"';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($file));
header(
    'Cache-Control: '
    . ($publicProject
        ? 'public, max-age=86400, immutable'
        : 'private, no-store')
);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

readfile($file);

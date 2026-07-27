<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
require_once __DIR__ . '/portal/publishing.php';

$mediaId = query_int('id');

if (!publishing_schema_available() || $mediaId <= 0) {
    http_response_code(404);
    exit('Image not found.');
}

$statement = db()->prepare(
    'SELECT media.*,post.status,post.published_at
     FROM blog_media media
     JOIN blog_posts post ON post.id=media.post_id
     WHERE media.id=:media_id
     LIMIT 1'
);
$statement->execute(['media_id' => $mediaId]);
$media = $statement->fetch();

if (!$media) {
    http_response_code(404);
    exit('Image not found.');
}

$user = current_user();
$isAdmin = $user && $user['role'] === 'admin';
$isPublic = (
    $media['status'] === 'published'
    && (
        $media['published_at'] === null
        || strtotime((string)$media['published_at']) <= time()
    )
);

if (!$isAdmin && !$isPublic) {
    http_response_code(404);
    exit('Image not found.');
}

$storedName = basename((string)$media['stored_name']);
$path = NMM_ROOT . '/storage/blog-media/' . $storedName;

if (
    $storedName === ''
    || !is_file($path)
    || !is_readable($path)
) {
    http_response_code(404);
    exit('Image not found.');
}

$mime = (string)$media['mime_type'];
$allowed = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
];

if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('Unsupported image.');
}

$size = filesize($path);
$etag = '"' . hash_file('sha256', $path) . '"';

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400, must-revalidate');
header('ETag: ' . $etag);

if (
    trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))
    === $etag
) {
    http_response_code(304);
    exit;
}

readfile($path);
exit;

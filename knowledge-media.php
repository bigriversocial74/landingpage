<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';

$id = query_int('id');

if ($id <= 0) {
    http_response_code(404);
    exit('Knowledge media not found.');
}

$viewer = current_user();
$isAdmin = $viewer && $viewer['role'] === 'admin';
$cover = isset($_GET['cover']);

$sql = 'SELECT *
        FROM knowledge_assets
        WHERE id = :id';

if (!$isAdmin) {
    $sql .= ' AND status = "published"
              AND is_public = 1';
}

$sql .= ' LIMIT 1';

$statement = db()->prepare($sql);
$statement->execute(['id' => $id]);
$asset = $statement->fetch();

if (!$asset) {
    http_response_code(404);
    exit('Knowledge media not found.');
}

$storedName = $cover
    ? (string)($asset['cover_stored_name'] ?? '')
    : (string)$asset['stored_name'];

if ($storedName === '') {
    http_response_code(404);
    exit('Knowledge cover not found.');
}

$path = NMM_ROOT
    . '/storage/knowledge-assets/'
    . basename($storedName);

if (!is_file($path)) {
    http_response_code(404);
    exit($cover
        ? 'Stored cover is unavailable.'
        : 'Stored media is unavailable.');
}

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    exit('Could not read the media file.');
}

$mime = $cover
    ? (string)($asset['cover_mime_type'] ?? 'image/jpeg')
    : (string)$asset['mime_type'];
$download = isset($_GET['download']) && !$cover;
$filename = str_replace(
    ['"', "\r", "\n"],
    '',
    $cover
        ? (
            pathinfo(
                (string)$asset['original_name'],
                PATHINFO_FILENAME
            )
            . '-cover.'
            . (string)($asset['cover_extension'] ?? 'jpg')
        )
        : (string)$asset['original_name']
);

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header(
    'Cache-Control: '
    . ($isAdmin ? 'private' : 'public')
    . ', max-age=3600, must-revalidate'
);
header(
    'Content-Disposition: ' .
    ($download ? 'attachment' : 'inline') .
    '; filename="' . $filename . '"'
);

$range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));

if (
    !$download
    && $range !== ''
    && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)
) {
    $start = $matches[1] === '' ? 0 : (int)$matches[1];
    $end = $matches[2] === '' ? $size - 1 : (int)$matches[2];

    if ($start < 0 || $end < $start || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }

    $end = min($end, $size - 1);
    $length = $end - $start + 1;

    http_response_code(206);
    header('Content-Length: ' . $length);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);

    $handle = fopen($path, 'rb');

    if ($handle === false) {
        http_response_code(500);
        exit;
    }

    fseek($handle, $start);
    $remaining = $length;

    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $remaining));

        if ($chunk === false) {
            break;
        }

        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }

    fclose($handle);
    exit;
}

header('Content-Length: ' . $size);
readfile($path);
exit;

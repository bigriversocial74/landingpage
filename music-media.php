<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('music_library');
require_once __DIR__ . '/portal/music-library.php';

$trackId = query_int('id');

if (
    $trackId <= 0
    || !music_library_schema_available()
) {
    http_response_code(404);
    exit('Music track not found.');
}

$viewer = current_user();
$isAdmin = $viewer && $viewer['role'] === 'admin';

$sql = 'SELECT track.*,
               asset.original_name,
               asset.stored_name,
               asset.mime_type,
               asset.size_bytes,
               asset.status AS asset_status,
               asset.is_public AS asset_is_public
        FROM music_tracks track
        JOIN knowledge_assets asset
          ON asset.id=track.knowledge_asset_id
        WHERE track.id=:track_id
          AND asset.media_kind="audio"';

if (!$isAdmin) {
    $sql .= ' AND track.status="active"
              AND asset.status="published"
              AND asset.is_public=1
              AND (
                    track.published_at IS NULL
                    OR track.published_at<=UTC_TIMESTAMP()
              )';
}

$sql .= ' LIMIT 1';

$statement = db()->prepare($sql);
$statement->execute(['track_id' => $trackId]);
$track = $statement->fetch();

if (!$track) {
    http_response_code(404);
    exit('Music track not found.');
}

$path = knowledge_storage_path(
    basename((string)$track['stored_name'])
);

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Stored music file is unavailable.');
}

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    exit('Could not read the music file.');
}

$mime = (string)$track['mime_type'];
$download = isset($_GET['download'])
    && (int)$track['is_downloadable'] === 1;
$filename = str_replace(
    ['"', "\r", "\n"],
    '',
    (string)$track['original_name']
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
    'Content-Disposition: '
    . ($download ? 'attachment' : 'inline')
    . '; filename="'
    . $filename
    . '"'
);

$range = trim(
    (string)($_SERVER['HTTP_RANGE'] ?? '')
);

if (
    !$download
    && $range !== ''
    && preg_match(
        '/bytes=(\d*)-(\d*)/',
        $range,
        $matches
    )
) {
    $start = $matches[1] === ''
        ? 0
        : (int)$matches[1];
    $end = $matches[2] === ''
        ? $size - 1
        : (int)$matches[2];

    if (
        $start < 0
        || $end < $start
        || $start >= $size
    ) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }

    $end = min($end, $size - 1);
    $length = $end - $start + 1;

    http_response_code(206);
    header('Content-Length: ' . $length);
    header(
        'Content-Range: bytes '
        . $start
        . '-'
        . $end
        . '/'
        . $size
    );

    $handle = fopen($path, 'rb');

    if ($handle === false) {
        http_response_code(500);
        exit;
    }

    fseek($handle, $start);
    $remaining = $length;

    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread(
            $handle,
            min(8192, $remaining)
        );

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

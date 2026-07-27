<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/music-library.php';

$trackId = query_int('id');

if (
    $trackId <= 0
    || !music_demo_mode_enabled()
) {
    http_response_code(404);
    exit('Demo track not found.');
}

$track = music_demo_track_by_id($trackId);
$path = music_demo_audio_path($trackId);

if (!$track || !$path) {
    http_response_code(404);
    exit('Demo track not found.');
}

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    exit('Could not read the demo track.');
}

header('Content-Type: audio/mpeg');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600, must-revalidate');
header(
    'Content-Disposition: inline; filename="'
    . str_replace(
        ['"', "\r", "\n"],
        '',
        (string)$track['slug']
    )
    . '.mp3"'
);

$range = trim(
    (string)($_SERVER['HTTP_RANGE'] ?? '')
);

if (
    $range !== ''
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

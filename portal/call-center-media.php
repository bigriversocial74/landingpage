<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/call-center.php';

$user = current_user();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(401);
    exit('Administrator authentication required.');
}

$mediaId = query_int('id');
$media = call_center_media($mediaId);

if (!$media) {
    http_response_code(404);
    exit('Call Center media not found.');
}

$path = call_center_media_storage_path(
    (string)$media['stored_name']
);

if (!is_file($path)) {
    http_response_code(404);
    exit('Stored Call Center media is unavailable.');
}

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    exit('Could not read the Call Center media.');
}

$mime = (string)$media['mime_type'];
$filename = str_replace(
    ['"', "\r", "\n"],
    '',
    (string)$media['original_name']
);
$download = isset($_GET['download']);

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600, must-revalidate');
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
    header(
        'Content-Range: bytes ' .
        $start . '-' . $end . '/' . $size
    );

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

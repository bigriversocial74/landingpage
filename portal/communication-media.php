<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/communications.php';

$user = current_user();

if (!$user || !in_array($user['role'], ['admin', 'client'], true)) {
    http_response_code(401);
    exit('Authentication required.');
}

$id = query_int('id');

if ($id <= 0) {
    http_response_code(404);
    exit('Communication media not found.');
}

$sql = 'SELECT attachment.*, conversation.client_user_id
        FROM communication_attachments attachment
        JOIN communication_threads conversation
          ON conversation.id = attachment.thread_id
        WHERE attachment.id = :id';

if ($user['role'] !== 'admin') {
    $sql .= ' AND conversation.client_user_id = :client_user_id';
}

$sql .= ' LIMIT 1';

$statement = db()->prepare($sql);
$parameters = ['id' => $id];

if ($user['role'] !== 'admin') {
    $parameters['client_user_id'] = $user['id'];
}

$statement->execute($parameters);
$attachment = $statement->fetch();

if (!$attachment) {
    http_response_code(404);
    exit('Communication media not found.');
}

$path = communication_storage_path(
    (string)$attachment['stored_name']
);

if (!is_file($path)) {
    http_response_code(404);
    exit('Stored communication media is unavailable.');
}

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    exit('Could not read the communication media.');
}

$mime = (string)$attachment['mime_type'];
$download = isset($_GET['download']);
$inlineKinds = ['image', 'audio', 'video'];
$inline = !$download && (
    in_array($attachment['media_kind'], $inlineKinds, true)
    || $mime === 'application/pdf'
);
$filename = str_replace(
    ['"', "\r", "\n"],
    '',
    (string)$attachment['original_name']
);

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600, must-revalidate');
header(
    'Content-Disposition: ' .
    ($inline ? 'inline' : 'attachment') .
    '; filename="' . $filename . '"'
);

$range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));

if (
    $inline
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

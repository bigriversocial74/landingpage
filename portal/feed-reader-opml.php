<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/feed-reader-core.php';

$user = current_user();
if (!$user) {
    redirect('portal/login.php');
}

feed_reader_require_enabled();
if (!feed_reader_schema_available()) {
    http_response_code(503);
    exit('Feed Reader database migration is required.');
}

$xml = feed_reader_opml_export((int)$user['id']);
$filename = 'north-mountain-media-feeds-' . gmdate('Y-m-d') . '.opml';

header('Content-Type: text/x-opml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xml));
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
echo $xml;

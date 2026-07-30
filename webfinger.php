<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub.php';

header('Cache-Control: public, max-age=300, must-revalidate');
header('X-Content-Type-Options: nosniff');
try {
    $resource = mb_substr(trim((string)($_GET['resource'] ?? '')), 0, 1000);
    if ($resource === '') throw new RuntimeException('The resource parameter is required.');
    activitypub_json_response(
        activitypub_webfinger_document($resource),
        'application/jrd+json; charset=UTF-8'
    );
} catch (Throwable) {
    http_response_code(404);
    exit;
}

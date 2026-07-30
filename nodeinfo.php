<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub.php';

try {
    activitypub_json_response(
        activitypub_nodeinfo_document(),
        'application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.1#"; charset=UTF-8'
    );
} catch (Throwable) {
    http_response_code(404);
    exit;
}

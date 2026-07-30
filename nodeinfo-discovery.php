<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/activitypub.php';

try {
    if (!activitypub_settings()['enabled']) throw new RuntimeException('Federation disabled.');
    activitypub_json_response([
        'links' => [[
            'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.1',
            'href' => publishing_absolute_url('nodeinfo.php'),
        ]],
    ], 'application/json; charset=UTF-8');
} catch (Throwable) {
    http_response_code(404);
    exit;
}

<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
nmm_require_public_module('rss');
require_once __DIR__ . '/portal/public-syndication.php';

$settings = syndication_settings();
if (!$settings['json_enabled']) {
    http_response_code(404);
    exit('JSON Feed is disabled.');
}
$context = syndication_context('json');
syndication_send(
    syndication_render_json_feed($context['filter']),
    'application/feed+json',
    (int)$context['last_modified']
);

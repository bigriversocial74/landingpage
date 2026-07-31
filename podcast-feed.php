<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
nmm_require_public_module('rss');
require_once __DIR__ . '/portal/public-syndication.php';

$settings = syndication_settings();
if (!$settings['podcast_enabled']) {
    http_response_code(404);
    exit('Podcast feed is disabled.');
}
$context = syndication_context('podcast', ['category'=>'','tag'=>'','author'=>'']);
syndication_send(
    syndication_render_podcast_feed(),
    'application/rss+xml',
    (int)$context['last_modified']
);

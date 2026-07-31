<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('blog');
nmm_require_public_module('rss');
require_once __DIR__ . '/portal/publishing.php';
require_once __DIR__ . '/portal/publishing-workflow.php';
require_once __DIR__ . '/portal/blog-feed-output.php';

$settings = publishing_blog_settings();
if (!$settings['atom_enabled']) {
    http_response_code(404);
    exit('Atom feed is disabled.');
}

$xml = publishing_render_atom_feed();
$context = publishing_feed_context('atom');
publishing_feed_send($xml, 'application/atom+xml', (int)$context['last_modified']);

<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/publishing.php';
require_once __DIR__ . '/portal/publishing-workflow.php';
require_once __DIR__ . '/portal/portfolio.php';
require_once __DIR__ . '/portal/events-calendar.php';
require_once __DIR__ . '/portal/appointments-booking.php';
require_once __DIR__ . '/portal/proposals-intake.php';
require_once __DIR__ . '/portal/site-builder-core.php';

$settings = publishing_blog_settings();

if (!$settings['sitemap_enabled']) {
    http_response_code(404);
    exit('Sitemap is disabled.');
}

$urls = [[
    'loc' => publishing_absolute_url('index.php'),
    'lastmod' => gmdate('Y-m-d'),
    'priority' => '1.0',
]];
if (nmm_module_enabled('blog')) $urls[] = ['loc'=>publishing_absolute_url('blog.php'),'lastmod'=>gmdate('Y-m-d'),'priority'=>'0.8'];
if (nmm_module_enabled('music_library')) $urls[] = ['loc'=>publishing_absolute_url('music-library.php'),'lastmod'=>gmdate('Y-m-d'),'priority'=>'0.7'];
if (nmm_module_enabled('events')) $urls[] = ['loc'=>publishing_absolute_url('events.php'),'lastmod'=>gmdate('Y-m-d'),'priority'=>'0.8'];
if (nmm_module_enabled('call_us')) $urls[] = ['loc'=>publishing_absolute_url('call-dave.php'),'lastmod'=>gmdate('Y-m-d'),'priority'=>'0.6'];
if (nmm_module_enabled('resume')) $urls[] = ['loc'=>publishing_absolute_url('workspace.php#resume'),'lastmod'=>gmdate('Y-m-d'),'priority'=>'0.8'];

if (nmm_module_enabled('bookings') && booking_public_link_available()) {
    $urls[] = [
        'loc' => publishing_absolute_url('booking.php'),
        'lastmod' => gmdate('Y-m-d'),
        'priority' => '0.8',
    ];
}

if (nmm_module_enabled('project_intake') && proposals_schema_available() && proposals_settings()['intake_public_enabled']) {
    $urls[] = [
        'loc' => publishing_absolute_url('intake.php'),
        'lastmod' => gmdate('Y-m-d'),
        'priority' => '0.7',
    ];
}

if (nmm_module_enabled('blog')) foreach (blog_public_posts(null, null, 250, 0) as $post) {
    $urls[] = [
        'loc' => publishing_absolute_url(
            'blog-post.php?slug='
            .rawurlencode($post['slug'])
        ),
        'lastmod' => gmdate(
            'Y-m-d',
            strtotime(
                $post['updated_at']
                ?: $post['published_at']
                ?: 'now'
            )
        ),
        'priority' => $post['featured'] ? '0.9' : '0.7',
    ];
}

if (nmm_module_enabled('resume')) foreach (resume_public_posts() as $post) {
    $urls[] = [
        'loc' => publishing_absolute_url(
            'resume-post.php?slug='
            .rawurlencode($post['slug'])
        ),
        'lastmod' => gmdate(
            'Y-m-d',
            strtotime(
                $post['updated_at']
                ?: $post['published_at']
                ?: 'now'
            )
        ),
        'priority' => $post['featured'] ? '0.8' : '0.6',
    ];
}

if (nmm_module_enabled('events') && events_schema_available()) {
    foreach (events_public_events([
        'include_past' => true,
        'limit' => 250,
    ]) as $event) {
        $urls[] = [
            'loc' => publishing_absolute_url(
                'event.php?slug=' . rawurlencode((string)$event['slug'])
            ),
            'lastmod' => gmdate(
                'Y-m-d',
                strtotime(
                    $event['updated_at']
                    ?? $event['published_at']
                    ?? 'now'
                )
            ),
            'priority' => !empty($event['featured']) ? '0.9' : '0.7',
        ];
    }
}

if (site_builder_schema_available()) {
    foreach (site_builder_pages() as $builderPage) {
        if (($builderPage['status'] ?? '') !== 'published' || ($builderPage['slug'] ?? '') === 'home' || !(bool)($builderPage['seo_index_enabled'] ?? 0)) {
            continue;
        }
        $urls[] = [
            'loc' => publishing_absolute_url('page.php?slug=' . rawurlencode((string)$builderPage['slug'])),
            'lastmod' => gmdate('Y-m-d', strtotime((string)($builderPage['updated_at'] ?? 'now'))),
            'priority' => '0.7',
        ];
    }
}

if (nmm_module_enabled('portfolio')) try {
    foreach (portfolio_public_projects() as $project) {
        $urls[] = [
            'loc' => publishing_absolute_url(
                'workspace.php?portfolio='
                .rawurlencode((string)$project['slug'])
            ),
            'lastmod' => gmdate(
                'Y-m-d',
                strtotime(
                    $project['updated_at']
                    ?? $project['published_at']
                    ?? 'now'
                )
            ),
            'priority' => !empty($project['featured'])
                ? '0.8'
                : '0.6',
        ];
    }
} catch (Throwable) {
    // Sitemap remains valid when Portfolio is not installed.
}

header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=1800, must-revalidate');

$xml = static fn(mixed $value): string =>
    htmlspecialchars(
        (string)$value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach($urls as $url):?>
<url>
<loc><?=$xml($url['loc'])?></loc>
<lastmod><?=$xml($url['lastmod'])?></lastmod>
<priority><?=$xml($url['priority'])?></priority>
</url>
<?php endforeach;?>
</urlset>

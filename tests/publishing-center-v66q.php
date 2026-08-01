<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        throw new RuntimeException('Missing ' . $path);
    }
    return $content;
};

$publishing = $read('portal/publishing-center.php');
$shell = $read('portal/bootstrap-shell.php');
$navigation = $read('portal/navigation.php');
$social = $read('portal/social-posts.php');
$settings = $read('portal/site-settings.php');
$service = $read('portal/social-posts-service.php');

foreach ([
    'story','social-post','blog','event','booking','syndication','portfolio','resume',
    'music-track','music-album','music-playlist','client','lead','proposal','project',
    'file','knowledge',
] as $key) {
    if (!preg_match("/'key'\\s*=>\\s*'" . preg_quote($key, '/') . "'/", $publishing)) {
        throw new RuntimeException('Publishing catalog missing ' . $key);
    }
}
foreach (['nmm_module_enabled((string)$module)', 'data-publishing-direct', 'data-publishing-option'] as $needle) {
    if (!str_contains($publishing, $needle)) {
        throw new RuntimeException('Publishing Center missing ' . $needle);
    }
}
foreach (['<iframe', '?modal=1', 'publishing-center-v66q.js', 'data-footer-publishing-frame'] as $forbidden) {
    if (str_contains($publishing, $forbidden)) {
        throw new RuntimeException('Publishing Center retains forbidden container behavior: ' . $forbidden);
    }
}
foreach (['data-admin-quick-toggle', 'data-admin-launcher-tab="publishing"', 'publishing_center_render_footer_links'] as $needle) {
    if (!str_contains($shell, $needle)) {
        throw new RuntimeException('Footer Publishing launcher missing ' . $needle);
    }
}
foreach (['Publishing +', 'portal-dashboard-publishing-v66q5.js', 'portal-shell-v66q6.js', 'portal-unified-runtime-v66q3.js'] as $forbidden) {
    if (str_contains($shell, $forbidden)) {
        throw new RuntimeException('Live shell retains obsolete Publishing behavior: ' . $forbidden);
    }
}
foreach (['Recent stories', 'Social Feed', 'portal/publish-story.php', 'portal/publish-social-post.php'] as $needle) {
    if (!str_contains($social, $needle)) {
        throw new RuntimeException('My Feed missing ' . $needle);
    }
}
foreach ([
    "nmm_module_enabled('social_feed')",
    "nmm_module_enabled('stories')",
    "nmm_module_enabled('rss')",
    "nmm_module_enabled('music_library')",
    "nmm_module_enabled('clients')",
] as $needle) {
    if (!str_contains($navigation . $publishing . $service . $social, $needle)) {
        throw new RuntimeException('Module-gated surface missing ' . $needle);
    }
}
foreach ([
    "'clients' =>", "'leads' =>", "'rss' =>", "'social_feed' =>", "'stories' =>",
] as $needle) {
    if (!str_contains($settings, $needle)) {
        throw new RuntimeException('Site Settings missing module definition ' . $needle);
    }
}
foreach ([
    '.github/workflows/build-publishing-center-v66q.yml',
    '.github/workflows/run-publishing-builder-v66q.yml',
    '.github/workflows/repair-publishing-center-v66q.yml',
    '.github/workflows/finalize-publishing-center-v66q.yml',
    '.github/v66q-build-trigger',
] as $temporary) {
    if (is_file($root . '/' . $temporary)) {
        throw new RuntimeException('Temporary v66Q artifact remains: ' . $temporary);
    }
}

echo "Publishing Center v66Q direct-link, module, and source contract passed\n";

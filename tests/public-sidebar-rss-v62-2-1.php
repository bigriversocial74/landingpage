<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'sidebar' => $root . '/portal/public-sidebar.php',
    'follow' => $root . '/portal/public-follow.php',
    'style' => $root . '/assets/css/public-follow-v66q9.css',
    'script' => $root . '/assets/js/public-follow-v66q9.js',
];

$content = [];
foreach ($files as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required source: {$path}\n");
        exit(1);
    }
    $content[$key] = (string)file_get_contents($path);
}

$checks = [
    'shared Follow helper' => ["require_once __DIR__ . '/public-follow.php'", $content['sidebar']],
    'plain Follow link' => ['public-sidebar-follow-link', $content['sidebar']],
    'single Follow modal render' => ['nmm_render_public_follow_modal', $content['sidebar']],
    'POD follow tab' => ['POD / HomeServer', $content['follow']],
    'RSS follow tab' => ['RSS Feed', $content['follow']],
    'POD discovery URL' => ['pod-discovery.php', $content['follow']],
    'standalone follow fallback' => ['follow-pod.php', $content['follow']],
    'RSS feed URL' => ['blog-feed.php', $content['follow']],
    'RSS explanation' => ['RSS delivers newly published articles', $content['follow']],
    'RSS copy action' => ['Copy RSS URL', $content['follow']],
    'RSS open-feed action' => ['Open feed', $content['follow']],
    'tabbed interface' => ['role="tablist"', $content['follow']],
    'POD panel' => ['data-follow-panel="pod"', $content['follow']],
    'RSS panel' => ['data-follow-panel="rss"', $content['follow']],
    'plain text sidebar styling' => ['.workspace-sidebar .sidebar-actions>a', $content['style']],
    'normal sidebar link padding' => ['padding:5px 0!important', $content['style']],
    'modal open behavior' => ['const openModal', $content['script']],
    'modal close behavior' => ['const closeModal', $content['script']],
    'copy behavior' => ['navigator.clipboard?.writeText', $content['script']],
    'keyboard tabs' => ['ArrowRight', $content['script']],
    'Escape close behavior' => ["event.key === 'Escape'", $content['script']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

foreach ([
    'data-rss-modal-open',
    'data-rss-modal-close',
    'const openRssModal',
    'const closeRssModal',
    'rss-sidebar-button',
] as $obsolete) {
    if (str_contains($content['sidebar'] . $content['script'], $obsolete)) {
        fwrite(STDERR, "Obsolete RSS-only modal behavior remains: {$obsolete}\n");
        exit(1);
    }
}

if (substr_count($content['sidebar'], 'nmm_public_follow_trigger_html') !== 1) {
    fwrite(STDERR, "Expected exactly one shared Follow trigger in the public sidebar.\n");
    exit(1);
}

if (substr_count($content['follow'], 'data-follow-modal-close') < 2) {
    fwrite(STDERR, "The Follow modal requires both backdrop and close-button controls.\n");
    exit(1);
}

fwrite(STDOUT, "Public RSS goals retained by unified Follow modal v66Q.9.\n");

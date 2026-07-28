<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'view' => 'portal/feed-reader-view.php',
    'script' => 'assets/js/feed-reader-social.js',
    'css' => 'assets/css/feed-reader-social.css',
];

$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    $source[$key] = $content;
}

$checks = [
    'social reader root' => ['data-social-feed-reader', $source['view']],
    'social feed cards' => ['feed-reader-social-card', $source['view'] . $source['css']],
    'dedicated feed navigation' => ['data-feed-sidebar', $source['view']],
    'portal sidebar relocation' => ["portalSidebar.insertBefore(feedSidebar", $source['script']],
    'settings icon action' => ['data-feed-settings-open', $source['view'] . $source['script']],
    'settings modal' => ['data-feed-settings-dialog', $source['view'] . $source['script']],
    'OPML in settings modal' => ['Import or export subscriptions', $source['view']],
    'subscription management in settings modal' => ['Manage subscriptions', $source['view']],
    'refresh history in settings modal' => ['Recent refresh history', $source['view']],
    'full article reader' => ['feed-reader-article-page', $source['view'] . $source['css']],
    'article entry link' => ['Read article', $source['view']],
    'back to feed control' => ['Back to feed', $source['view']],
    'private state controls' => ['data-feed-state', $source['view'] . $source['script']],
    'responsive social feed' => ['@media(max-width:760px)', str_replace(' ', '', $source['css'])],
    'cache-busted social assets' => ['20260728-social-feed-reader-v62-2', $source['view']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'legacy reader JavaScript root' => ['data-feed-reader>', $source['view']],
    'legacy in-page management section' => ['data-feed-management', $source['view']],
    'browser confirmation call' => ['window.confirm(', $source['script']],
    'inline event handler' => ['onclick=', $source['view']],
];

foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Social Feed Reader v62.2 regression passed.\n");

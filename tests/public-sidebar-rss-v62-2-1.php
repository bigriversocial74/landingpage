<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sidebarPath = $root . '/portal/public-sidebar.php';
$stylePath = $root . '/assets/css/public-sidebar-v62-2-1.css';
$scriptPath = $root . '/assets/js/public-sidebar.js';

foreach ([$sidebarPath, $stylePath, $scriptPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required source: {$path}\n");
        exit(1);
    }
}

$sidebar = (string)file_get_contents($sidebarPath);
$style = (string)file_get_contents($stylePath);
$script = (string)file_get_contents($scriptPath);

$checks = [
    'v62.2.1 stylesheet load' => [
        'assets/css/public-sidebar-v62-2-1.css?v=20260728-v62.2.1',
        $sidebar,
    ],
    'conversation section scope' => [
        'sidebar-section sidebar-conversation-section',
        $sidebar,
    ],
    'RSS modal trigger' => ['data-rss-modal-open', $sidebar],
    'compact RSS icon width attribute' => ['width="17"', $sidebar],
    'compact RSS icon height attribute' => ['height="17"', $sidebar],
    'RSS label' => ['<span>RSS Feed</span>', $sidebar],
    'RSS explanation' => ['RSS lets you receive newly published articles', $sidebar],
    'RSS feed URL field' => ['data-rss-feed-url', $sidebar],
    'RSS copy action' => ['Copy RSS Feed URL', $sidebar],
    'RSS open-feed action' => ['>Open feed</a>', $sidebar],
    'RSS close buttons' => ['data-rss-modal-close', $sidebar],
    'conversation menu reset' => [
        '.sidebar-conversation-section .sidebar-custom-menu',
        $style,
    ],
    'conversation item alignment' => ['justify-content:flex-start', $style],
    'normal sidebar item padding' => ['padding:5px 0', $style],
    '17 pixel RSS icon' => ['width:17px!important', $style],
    '17 pixel RSS icon cap' => ['max-width:17px!important', $style],
    'mobile sidebar coverage' => ['@media(max-width:760px)', $style],
    'modal open behavior' => ['const openRssModal', $script],
    'modal close behavior' => ['const closeRssModal', $script],
    'copy behavior' => ['navigator.clipboard?.writeText', $script],
    'Escape close behavior' => ["event.key === 'Escape'", $script],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$callPosition = strpos($sidebar, 'data-call-widget-open');
$rssPosition = strpos($sidebar, 'data-rss-modal-open');
if ($callPosition === false || $rssPosition === false || $rssPosition <= $callPosition) {
    fwrite(STDERR, "RSS Feed must remain directly after the Conversation navigation and Call Us fallback.\n");
    exit(1);
}

if (substr_count($sidebar, 'data-rss-modal-open') !== 1) {
    fwrite(STDERR, "Expected exactly one RSS modal trigger.\n");
    exit(1);
}

if (substr_count($sidebar, 'data-rss-modal-close') < 2) {
    fwrite(STDERR, "RSS modal backdrop and close-button controls are required.\n");
    exit(1);
}

if (preg_match('/\.rss-sidebar-button\s+svg\s*\{[^}]*width\s*:\s*(?:[2-9][0-9]|1[89])[0-9]*px/si', $style)) {
    fwrite(STDERR, "RSS sidebar icon exceeds the compact 16–18 pixel target.\n");
    exit(1);
}

fwrite(STDOUT, "Public sidebar RSS v62.2.1 regression passed.\n");

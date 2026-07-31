<?php
declare(strict_types=1);

function music_mobile_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$cssPath = $root . '/assets/css/music-mobile-upgrade-v66n.css';
$css = file_get_contents($cssPath);
music_mobile_assert($css !== false, 'Mobile upgrade stylesheet is missing.');

foreach ([
    'music-library.php',
    'music-library-preview.php',
    'music-collection.php',
    'music-collection-preview.php',
] as $page) {
    $source = file_get_contents($root . '/' . $page);
    music_mobile_assert($source !== false, $page . ' could not be read.');
    music_mobile_assert(
        str_contains($source, 'music-mobile-upgrade-v66n.css'),
        $page . ' does not load the mobile upgrade stylesheet.'
    );
}

foreach ([
    '--music-touch-target:44px',
    '--music-mobile-player-height:176px',
    'grid-template-areas:',
    '"cover identity utility"',
    '"center center center"',
    '.music-player-queue-panel',
    '.music-dashboard-song-row',
    '.music-dashboard-all-songs article',
    '.music-collection-track-title>img',
    'env(safe-area-inset-bottom)',
    '@media(max-width:820px)',
    '@media(max-width:560px)',
    '@media(prefers-reduced-motion:reduce)',
    ':focus-visible',
] as $required) {
    music_mobile_assert(
        str_contains($css, $required),
        'Missing mobile contract: ' . $required
    );
}

music_mobile_assert(
    !str_contains($css, '.music-player-utility{display:none'),
    'Mobile queue access was removed with the utility controls.'
);

fwrite(STDOUT, "Music Library mobile v66N regression passed.\n");

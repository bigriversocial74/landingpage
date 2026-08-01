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
    '"cover identity play utility"',
    '"timeline timeline timeline timeline"',
    '.music-player-center{display:contents}',
    '.music-player-queue-panel',
    '.music-dashboard-song-row',
    '.music-dashboard-all-songs article',
    '.music-collection-track-title>img',
    'overscroll-behavior-inline:contain',
    'scroll-snap-type:x mandatory',
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

foreach (['margin-inline:-16px', 'margin-inline:-18px'] as $forbidden) {
    music_mobile_assert(
        !str_contains($css, $forbidden),
        'The mobile rail can still expand the page width: ' . $forbidden
    );
}

music_mobile_assert(
    preg_match(
        '/\.music-player-utility\s*\{[^}]*grid-area:utility;[^}]*display:flex!important/s',
        $css
    ) === 1,
    'Mobile queue access is not restored in the compact player.'
);
music_mobile_assert(
    str_contains($css, '.music-player-center .music-player-controls button[data-music-toggle]'),
    'The Play control is not preserved in the top-row player area.'
);

fwrite(STDOUT, "Music Library mobile v66Q.8 regression passed.\n");

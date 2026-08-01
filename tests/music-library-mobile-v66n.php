<?php
declare(strict_types=1);

function music_mobile_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$css = file_get_contents($root . '/assets/css/music-mobile-upgrade-v66n.css');
music_mobile_assert(is_string($css) && $css !== '', 'Music player stylesheet is missing.');

foreach ([
    'music-library.php',
    'music-library-preview.php',
    'music-collection.php',
    'music-collection-preview.php',
] as $page) {
    $source = file_get_contents($root . '/' . $page);
    music_mobile_assert(is_string($source), $page . ' could not be read.');
    music_mobile_assert(
        str_contains($source, 'music-mobile-upgrade-v66n.css'),
        $page . ' does not load the responsive player stylesheet.'
    );
}

foreach ([
    '--music-mobile-player-height:128px',
    'Professional global player',
    'font-size:15px!important',
    'font-size:12px!important',
    'font-size:13px!important',
    'font-size:10px!important',
    '.music-player-timeline',
    'input[type="range"]',
    'data-music-previous',
    'data-music-next',
    'data-music-toggle',
    'data-music-queue-toggle',
    '"cover identity controls utility"',
    '"timeline timeline timeline timeline"',
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
        'Missing professional player contract: ' . $required
    );
}

foreach (['margin-inline:-16px', 'margin-inline:-18px'] as $forbidden) {
    music_mobile_assert(
        !str_contains($css, $forbidden),
        'A mobile rail can still expand the page width: ' . $forbidden
    );
}

music_mobile_assert(
    preg_match('/\.music-player-copy strong\s*\{[^}]*font-size:15px!important/s', $css) === 1,
    'Desktop track title is not a normal readable size.'
);
music_mobile_assert(
    preg_match('/\.music-player-copy span\s*\{[^}]*font-size:12px!important/s', $css) === 1,
    'Desktop artist name is not a normal readable size.'
);
music_mobile_assert(
    str_contains($css, 'grid-area:timeline!important'),
    'The mobile scrubber is not kept below the compact top row.'
);
music_mobile_assert(
    str_contains($css, 'button[data-music-toggle]'),
    'The Play control is not retained in the top row.'
);

fwrite(STDOUT, "Music Library professional player v66Q.9 regression passed.\n");

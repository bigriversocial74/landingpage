<?php
declare(strict_types=1);

function music_mobile_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$playerCss = file_get_contents($root . '/assets/css/music-mobile-upgrade-v66n.css');
$dashboardCss = file_get_contents($root . '/assets/css/music-library-dashboard-v66q10.css');
$dashboardJs = file_get_contents($root . '/assets/js/music-library-dashboard-v66q10.js');
$library = file_get_contents($root . '/music-library.php');

music_mobile_assert(is_string($playerCss) && $playerCss !== '', 'Music player stylesheet is missing.');
music_mobile_assert(is_string($dashboardCss) && $dashboardCss !== '', 'Music Library dashboard stylesheet is missing.');
music_mobile_assert(is_string($dashboardJs) && $dashboardJs !== '', 'Music Library dashboard controller is missing.');
music_mobile_assert(is_string($library) && $library !== '', 'Music Library page could not be read.');

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
    'env(safe-area-inset-bottom)',
] as $required) {
    music_mobile_assert(
        str_contains($playerCss, $required),
        'Missing professional player contract: ' . $required
    );
}

foreach ([
    'music-library-dashboard-v66q10.css',
    'music-library-dashboard-v66q10.js',
    'data-music-library-dashboard',
    'data-music-library-search',
    'Welcome back,',
    'Play Your Library',
    'Continue Listening',
    'Top Songs',
    'New Music',
    'Recently Played',
    'data-music-library-load-more',
    'data-music-extra-song="<?=$index >= 10 ? \'1\' : \'0\'?>"',
    'if ($displayPlaylists)',
    'if ($displayAlbums)',
] as $required) {
    music_mobile_assert(
        str_contains($library, $required),
        'Missing reference dashboard contract: ' . $required
    );
}

foreach ([
    'No public albums are available.',
    'No public playlists are available.',
    '<section class="music-dashboard-section" id="albums">',
] as $forbidden) {
    music_mobile_assert(
        !str_contains($library, $forbidden),
        'Empty collection sections can still render: ' . $forbidden
    );
}

foreach ([
    '.music-library-summary-grid',
    'grid-template-columns:1.08fr 1fr 1fr',
    '.music-library-card-rail',
    'grid-template-columns:repeat(6,minmax(0,1fr))',
    '.music-library-song-table',
    '.music-library-load-more',
    'overflow-x:auto',
    'overscroll-behavior-inline:contain',
    'scroll-snap-type:x mandatory',
    '@media(max-width:820px)',
    '@media(max-width:560px)',
] as $required) {
    music_mobile_assert(
        str_contains($dashboardCss, $required),
        'Missing responsive dashboard contract: ' . $required
    );
}

foreach ([
    'rows.length <= 10',
    'index < 10',
    'data-music-library-load-more',
    'data-music-library-search',
    "event.key !== 'Escape'",
] as $required) {
    music_mobile_assert(
        str_contains($dashboardJs, $required),
        'Missing dashboard behavior contract: ' . $required
    );
}

foreach (['margin-inline:-16px', 'margin-inline:-18px'] as $forbidden) {
    music_mobile_assert(
        !str_contains($playerCss . $dashboardCss, $forbidden),
        'A mobile rail can still expand the page width: ' . $forbidden
    );
}

music_mobile_assert(
    preg_match('/\.music-player-copy strong\s*\{[^}]*font-size:15px!important/s', $playerCss) === 1,
    'Desktop track title is not a normal readable size.'
);
music_mobile_assert(
    preg_match('/\.music-player-copy span\s*\{[^}]*font-size:12px!important/s', $playerCss) === 1,
    'Desktop artist name is not a normal readable size.'
);
music_mobile_assert(
    str_contains($playerCss, 'grid-area:timeline!important'),
    'The mobile scrubber is not kept below the compact top row.'
);
music_mobile_assert(
    str_contains($playerCss, 'button[data-music-toggle]'),
    'The Play control is not retained in the top row.'
);

fwrite(STDOUT, "Music Library reference dashboard and professional player v66Q.10 regression passed.\n");

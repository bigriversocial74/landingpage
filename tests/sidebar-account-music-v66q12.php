<?php
declare(strict_types=1);

function v66q12_fail(string $message): never
{
    fwrite(STDERR, "v66Q.12 experience contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        v66q12_fail('Unable to read ' . $path);
    }
    return $content;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        v66q12_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        v66q12_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$account = $read('portal/public-account-menu.php');
$accountJs = $read('assets/js/public-account-menu-v66q12.js');
$accountCss = $read('assets/css/public-account-menu-v66q7.css');
$publicSidebar = $read('portal/public-sidebar.php');
$musicShell = $read('portal/public-music-shell.php');
$musicUi = $read('assets/js/music-ui-v66q12.js');
$musicCss = $read('assets/css/music-ui-v66q12.css');
$musicFixCss = $read('assets/css/music-ui-v66q12-fixes.css');
$trackPage = $read('music-track.php');

foreach ([
    'function nmm_public_account_menu_context',
    "'signed_in' => true",
    "'signed_in' => false",
    'Client login',
    'Administrator login',
    'Dashboard',
    'Account settings',
    'Sign out',
    'data-public-account-menu',
    'nmm_remove_direct_login_links_from_header',
    'public-account-menu-v66q12.js?v=20260801-v66Q12',
] as $contract) {
    $require($account, $contract, 'Public Account dropdown');
}
foreach ([
    'buildLoggedOutMenu',
    'portal/login.php?role=',
    'client.replaceWith(menu)',
    'admin.remove()',
    "event.key !== 'Escape'",
] as $contract) {
    $require($accountJs, $contract, 'Public Account runtime');
}
foreach ([
    '.public-account-menu-label',
    '.public-account-menu>nav',
    '.public-account-menu-avatar',
] as $contract) {
    $require($accountCss, $contract, 'Public Account styling');
}
$require($publicSidebar, 'nmm_public_account_assets_html()', 'Public fallback Account assets');
$require($musicShell, 'nmm_render_public_account_menu()', 'Music header Account dropdown');
foreach (['Client Login</a>', 'Admin Login</a>'] as $forbidden) {
    $forbid($musicShell, $forbidden, 'Music header direct login buttons');
}

foreach ([
    "const unfinishedKey = 'nmm_music_unfinished_v2'",
    'unfinished.slice(0, 5)',
    'unfinished.length <= 5',
    'loadMore.dataset.continueLoadMore',
    "audio.addEventListener('timeupdate'",
    "audio.addEventListener('pause'",
    "audio.addEventListener('seeked'",
    "audio.addEventListener('ended'",
    "window.addEventListener('pagehide'",
    'progress >= 0.98',
    'trackPageUrl',
    'music-track.php',
    'normalizePlayControls',
    'row.insertBefore(play, menu)',
    "shuffle.setAttribute('aria-pressed'",
] as $contract) {
    $require($musicUi, $contract, 'Music runtime');
}
foreach ([
    '.music-library-play-control',
    'margin:0 0 0 auto!important',
    '.music-library-compact-track>.music-library-play-control',
    '.music-library-new-row>.music-library-play-control',
    '.music-library-song-row>.music-library-play-control',
    'button[data-music-shuffle-toggle]',
    '@media(max-width:820px)',
    '.music-track-page',
    '.music-track-hero',
    '.music-track-related-row',
] as $contract) {
    $require($musicCss, $contract, 'Music UI styling');
}
$require($musicFixCss, '.music-track-primary.music-library-play-control', 'Song primary play control');

foreach ([
    'music_public_catalog()',
    '$requestedId',
    '$requestedSlug',
    'music_track_page_attributes',
    'data-music-shuffle',
    'More to listen to',
    'music_track_page_view',
    'music-ui-v66q12.css',
    'music-ui-v66q12.js',
] as $contract) {
    $require($trackPage . $musicShell, $contract, 'Dedicated song page');
}

foreach (['CREATE TABLE', 'ALTER TABLE', 'DROP TABLE'] as $forbidden) {
    $forbid($account . $musicUi . $trackPage, $forbidden, 'Runtime schema mutation');
}

echo "v66Q.12 Account dropdown, song pages, shuffle, unified play controls, and Continue Listening contract passed.\n";

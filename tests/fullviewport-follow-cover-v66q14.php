<?php
declare(strict_types=1);

function v66q14_fail(string $message): never
{
    fwrite(STDERR, "v66Q.14 runtime contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        v66q14_fail('Unable to read ' . $path);
    }
    return $content;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        v66q14_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        v66q14_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$adminRuntime = $read('assets/js/admin-actions-fullwidth-v66q13.js');
$adminCss = $read('assets/css/admin-actions-fullwidth-v66q13.css');
$musicRuntime = $read('assets/js/music-library-dashboard-v66q10.js');
$musicPage = $read('music-library.php');
$follow = $read('portal/public-follow.php');
$publicAccount = $read('portal/public-account-menu.php');

foreach ([
    "stylesheet.dataset.adminFullviewportStyles = 'v66Q.14'",
    "'../css/admin-actions-fullwidth-v66q13.css?v=20260801-v66Q14'",
    'document.head.appendChild(stylesheet)',
    'document.body.append(backdrop, modal)',
    "element.style.setProperty(property, value, 'important')",
    "'left': '0'",
    "'width': '100vw'",
    "'height': '100dvh'",
    "'max-width': 'none'",
    "'z-index': '2147483001'",
] as $contract) {
    $require($adminRuntime, $contract, 'Viewport-owned Administrator Tools');
}
foreach ([
    'position:fixed!important',
    'inset:0!important',
    'width:100vw!important',
    'height:100dvh!important',
] as $contract) {
    $require($adminCss, $contract, 'Viewport modal fallback CSS');
}

foreach ([
    "dashboard.querySelectorAll('.music-library-continue-row')",
    "dashboard.querySelectorAll('.music-library-compact-track')",
    "coverButton.className = 'music-library-cover-play'",
    "target.dataset.musicPlay = ''",
    'explicitPlay.remove()',
    'new MutationObserver(normalizeTopSectionPlayControls)',
    '.music-library-continue-row{grid-template-columns:52px minmax(0,1fr)!important}',
    '.music-library-compact-track{grid-template-columns:18px 40px minmax(0,1fr) 38px 24px!important}',
] as $contract) {
    $require($musicRuntime, $contract, 'Cover artwork play controls');
}
$forbid($musicRuntime, "dashboard.querySelectorAll('.music-library-song-row').forEach(convertRowCoverToPlay)", 'All Songs play control');
foreach ([
    'class="music-library-all-songs"',
    'class="music-library-song-title"',
    'aria-label="Play <?=e($track[\'title\'])?>"',
] as $contract) {
    $require($musicPage, $contract, 'Bottom All Songs play buttons');
}

foreach ([
    "$podEnabled = nmm_module_enabled('social_feed')",
    "$rssEnabled = nmm_module_enabled('rss')",
    "if ($podEnabled)",
    "if ($rssEnabled)",
    "$showTabs = $context['method_count'] === 2",
    'POD / HomeServer',
    'RSS Feed',
    'data-follow-modal-open',
    'blog-feed.php',
] as $contract) {
    $require($follow, $contract, 'Capability-driven public Follow');
}
$forbid($follow, "nmm_module_enabled('blog')", 'RSS Follow capability');
$forbid($follow, 'publishing_blog_settings()', 'RSS Follow capability');
foreach ([
    'nmm_public_follow_assets_html()',
    'nmm_public_follow_trigger_html',
    'nmm_public_follow_modal_html',
] as $contract) {
    $require($publicAccount, $contract, 'Index Follow injection');
}

foreach (['CREATE TABLE', 'ALTER TABLE', 'DROP TABLE'] as $forbidden) {
    $forbid($adminRuntime . $musicRuntime . $follow, $forbidden, 'Runtime schema mutation');
}

echo "v66Q.14 full-viewport modal, cover-play controls, and dual-method Follow contract passed.\n";

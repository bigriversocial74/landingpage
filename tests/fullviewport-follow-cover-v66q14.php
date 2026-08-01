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
    "left: '0'",
    "width: '100vw'",
    "height: '100dvh'",
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
    "...dashboard.querySelectorAll('.music-library-continue-row')",
    "...dashboard.querySelectorAll('.music-library-compact-track')",
    "...dashboard.querySelectorAll('.music-library-new-row')",
    "row.classList.add('music-library-cover-play-row')",
    "button.classList.add('music-library-cover-play')",
    "button.dataset.coverPlayOverlay = '1'",
    "button.textContent = ''",
    'const rowRect = row.getBoundingClientRect()',
    'const imageRect = image.getBoundingClientRect()',
    'button.style.left = `${imageRect.left - rowRect.left}px`',
    'button.style.top = `${imageRect.top - rowRect.top}px`',
    'button.style.width = `${imageRect.width}px`',
    'button.style.height = `${imageRect.height}px`',
    'new MutationObserver(normalizeTopSectionPlayControls)',
    'new ResizeObserver(scheduleCoverPlayGeometry)',
    '.music-library-cover-play-row{position:relative!important}',
    'position:absolute!important;z-index:4!important;display:block!important',
    '.music-library-summary-grid{--music-summary-cover-size:52px}',
    'grid-template-columns:var(--music-summary-cover-size) minmax(0,1fr)!important',
    'grid-template-columns:18px var(--music-summary-cover-size) minmax(0,1fr) 38px 24px!important',
] as $contract) {
    $require($musicRuntime, $contract, 'Position-preserving cover play controls');
}
foreach ([
    'image.replaceWith(',
    'imageContainer.replaceWith(',
    'appendChild(image)',
    'explicitPlay.remove()',
    "document.createElement('button')",
] as $forbidden) {
    $forbid($musicRuntime, $forbidden, 'Album cover DOM position');
}
$forbid(
    $musicRuntime,
    "dashboard.querySelectorAll('.music-library-song-row').forEach",
    'All Songs play control'
);
$forbid(
    $musicRuntime,
    'grid-template-columns:18px 40px minmax(0,1fr)',
    'Uniform Top Songs cover sizing'
);
foreach ([
    'class="music-library-all-songs"',
    'class="music-library-song-title"',
    'data-music-song-row',
    'aria-label="Play ',
] as $contract) {
    $require($musicPage, $contract, 'Bottom All Songs play buttons');
}

foreach ([
    '$podEnabled = nmm_module_enabled(\'social_feed\')',
    '$blogSettings = publishing_blog_settings()',
    '$rssEnabled = nmm_module_enabled(\'rss\')',
    'if ($podEnabled)',
    'if ($rssEnabled)',
    '$showTabs = $context[\'method_count\'] === 2',
    'POD / HomeServer',
    'RSS Feed',
    'data-follow-modal-open',
    'blog-feed.php',
] as $contract) {
    $require($follow, $contract, 'Capability-driven public Follow');
}
$forbid($follow, "nmm_module_enabled('blog')", 'RSS Follow capability');
$forbid($follow, '$rssEnabled = nmm_module_enabled(\'rss\') &&', 'RSS Follow capability');
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

echo "v66Q.18 full-viewport modal, uniform fixed-position summary covers, invisible overlay play controls, and dual-method Follow contract passed.\n";

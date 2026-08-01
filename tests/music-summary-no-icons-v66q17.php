<?php
declare(strict_types=1);

function v66q17_fail(string $message): never
{
    fwrite(STDERR, "v66Q.17 music summary contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$pagePath = $root . '/music-library.php';
$runtimePath = $root . '/assets/js/music-library-dashboard-v66q10.js';

$page = @file_get_contents($pagePath);
$runtime = @file_get_contents($runtimePath);
if (!is_string($page) || $page === '') {
    v66q17_fail('Unable to read music-library.php');
}
if (!is_string($runtime) || $runtime === '') {
    v66q17_fail('Unable to read music library runtime');
}

foreach ([
    '20260801-music-library-v66Q17',
    'music-library-dashboard-v66q10.js?v=20260801-v66Q17',
    'data-music-summary-cover-hit',
    'position:absolute;width:0;height:0;opacity:0',
] as $required) {
    if (!str_contains($page, $required)) {
        v66q17_fail('Missing server-rendered no-icon contract: ' . $required);
    }
}

preg_match_all(
    '/<button\b(?=[^>]*data-music-summary-cover-hit)[^>]*>(.*?)<\/button>/s',
    $page,
    $summaryButtons
);
if (count($summaryButtons[0] ?? []) !== 3) {
    v66q17_fail('Expected exactly three summary cover hit targets.');
}
foreach (($summaryButtons[1] ?? []) as $buttonBody) {
    if (trim(strip_tags((string)$buttonBody)) !== '') {
        v66q17_fail('A summary cover hit target contains visible button content.');
    }
}

foreach ([
    'image.replaceWith(',
    'coverButton.appendChild(image)',
    'imageContainer.replaceWith(',
    'row.insertBefore(image',
    'row.appendChild(image)',
] as $forbidden) {
    if (str_contains($runtime, $forbidden)) {
        v66q17_fail('Album-cover movement remains in runtime: ' . $forbidden);
    }
}

foreach ([
    'button.replaceChildren()',
    "button.classList.add('music-library-cover-play')",
    "button.style.opacity = '0'",
    "'button[data-music-summary-cover-hit][data-music-play]'",
    'image.getBoundingClientRect()',
    'button.style.left =',
    'button.style.top =',
    'button.style.width =',
    'button.style.height =',
] as $required) {
    if (!str_contains($runtime, $required)) {
        v66q17_fail('Missing transparent overlay behavior: ' . $required);
    }
}

$allSongsPosition = strpos($page, 'class="music-library-all-songs"');
if ($allSongsPosition === false) {
    v66q17_fail('All Songs section is missing.');
}
$allSongs = substr($page, $allSongsPosition);
if (!str_contains($allSongs, '>▶</button>')) {
    v66q17_fail('All Songs explicit Play controls were removed.');
}

echo "v66Q.17 summary covers remain fixed with no visible Play buttons.\n";

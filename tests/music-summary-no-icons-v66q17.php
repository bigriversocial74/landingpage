<?php
declare(strict_types=1);

function v66q17_fail(string $message): never
{
    fwrite(STDERR, "v66Q.17 retained music summary contract failure: {$message}\n");
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
    '20260801-music-library-v66Q18',
    'music-library-dashboard-v66q10.js?v=20260801-v66Q18',
    'data-music-summary-cover-hit',
    'position:absolute;width:0;height:0;opacity:0',
] as $required) {
    if (!str_contains($page, $required)) {
        v66q17_fail('Missing retained server-rendered no-icon contract: ' . $required);
    }
}

preg_match_all(
    '/<button\b[\s\S]*?data-music-summary-cover-hit[\s\S]*?<\/button>/',
    $page,
    $summaryButtons
);
if (count($summaryButtons[0] ?? []) !== 3) {
    v66q17_fail('Expected exactly three summary cover hit targets.');
}

foreach (($summaryButtons[0] ?? []) as $buttonMarkup) {
    $closingTagStart = strrpos((string)$buttonMarkup, '</button>');
    $openingTagEnd = $closingTagStart === false
        ? false
        : strrpos(substr((string)$buttonMarkup, 0, $closingTagStart), '>');
    if ($openingTagEnd === false || $closingTagStart === false) {
        v66q17_fail('Unable to locate the summary button body boundaries.');
    }

    $buttonBody = substr(
        (string)$buttonMarkup,
        $openingTagEnd + 1,
        $closingTagStart - ($openingTagEnd + 1)
    );
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
    "button.textContent = ''",
    "button.classList.add('music-library-cover-play')",
    "button.style.setProperty('opacity', '0', 'important')",
    "button.style.setProperty('background', 'transparent', 'important')",
    "button.style.setProperty('box-shadow', 'none', 'important')",
    "'button[data-music-summary-cover-hit][data-music-play]'",
    'image.getBoundingClientRect()',
    'button.style.left =',
    'button.style.top =',
    'button.style.width =',
    'button.style.height =',
] as $required) {
    if (!str_contains($runtime, $required)) {
        v66q17_fail('Missing retained transparent overlay behavior: ' . $required);
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

echo "v66Q.17 no-icon behavior retained under uniform v66Q.18 summary covers.\n";

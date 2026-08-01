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

$marker = 'data-music-summary-cover-hit';
$markerOffset = 0;
$summaryButtonCount = 0;
while (($markerPosition = strpos($page, $marker, $markerOffset)) !== false) {
    $buttonStart = strrpos(substr($page, 0, $markerPosition), '<button');
    $buttonEnd = strpos($page, '</button>', $markerPosition);
    if ($buttonStart === false || $buttonEnd === false) {
        v66q17_fail('Unable to isolate a summary cover hit target.');
    }

    $buttonMarkup = substr(
        $page,
        $buttonStart,
        ($buttonEnd + strlen('</button>')) - $buttonStart
    );
    $ariaPosition = strpos($buttonMarkup, 'aria-label=');
    $openingTagEnd = $ariaPosition === false
        ? false
        : strpos($buttonMarkup, '>', $ariaPosition);
    if ($openingTagEnd === false) {
        v66q17_fail('Unable to locate the end of a summary button opening tag.');
    }

    $buttonBody = substr(
        $buttonMarkup,
        $openingTagEnd + 1,
        strrpos($buttonMarkup, '</button>') - ($openingTagEnd + 1)
    );
    if (trim(strip_tags((string)$buttonBody)) !== '') {
        v66q17_fail('A summary cover hit target contains visible button content.');
    }

    $summaryButtonCount++;
    $markerOffset = $markerPosition + strlen($marker);
}
if ($summaryButtonCount !== 3) {
    v66q17_fail('Expected exactly three summary cover hit targets.');
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

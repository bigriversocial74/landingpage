<?php
declare(strict_types=1);

function v66q18_fail(string $message): never
{
    fwrite(STDERR, "v66Q.18 uniform cover contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$page = @file_get_contents($root . '/music-library.php');
$runtime = @file_get_contents($root . '/assets/js/music-library-dashboard-v66q10.js');
if (!is_string($page) || $page === '') {
    v66q18_fail('Unable to read music-library.php');
}
if (!is_string($runtime) || $runtime === '') {
    v66q18_fail('Unable to read music library runtime.');
}

foreach ([
    '20260801-music-library-v66Q18',
    'music-library-dashboard-v66q10.js?v=20260801-v66Q18',
    '--music-summary-cover-size:52px',
    '.music-library-continue-row{grid-template-columns:var(--music-summary-cover-size) minmax(0,1fr)!important',
    '.music-library-compact-track{grid-template-columns:18px var(--music-summary-cover-size) minmax(0,1fr) 38px 24px!important',
    '.music-library-new-row{grid-template-columns:var(--music-summary-cover-size) minmax(0,1fr)!important',
    'opacity:0!important',
    'background:transparent!important',
    'box-shadow:none!important',
] as $required) {
    if (!str_contains($page, $required)) {
        v66q18_fail('Missing server-rendered layout guard: ' . $required);
    }
}

foreach ([
    '.music-library-summary-grid{--music-summary-cover-size:52px}',
    'width:var(--music-summary-cover-size)!important',
    'height:var(--music-summary-cover-size)!important',
    "button.style.setProperty('opacity', '0', 'important')",
    "button.style.setProperty('background', 'transparent', 'important')",
    "button.style.setProperty('box-shadow', 'none', 'important')",
    "button.style.setProperty('width', `\${imageRect.width}px`, 'important')",
    "button.style.setProperty('height', `\${imageRect.height}px`, 'important')",
    'transform:none!important',
] as $required) {
    if (!str_contains($runtime, $required)) {
        v66q18_fail('Missing runtime layout protection: ' . $required);
    }
}

foreach ([
    'grid-template-columns:18px 40px minmax(0,1fr)',
    '.music-library-compact-track img{width:40px;height:40px',
    'transform:scale(1.04)',
] as $forbidden) {
    if (str_contains($runtime, $forbidden)) {
        v66q18_fail('Stale unequal-cover behavior remains: ' . $forbidden);
    }
}

preg_match_all(
    '/<button\b[\s\S]*?data-music-summary-cover-hit[\s\S]*?<\/button>/',
    $page,
    $summaryButtons
);
if (count($summaryButtons[0] ?? []) !== 3) {
    v66q18_fail('Expected exactly three summary cover hit targets.');
}

foreach (['music-library-continue-row', 'music-library-compact-track', 'music-library-new-row'] as $rowClass) {
    $rowPosition = strpos($page, 'class="' . $rowClass . '"');
    if ($rowPosition === false) {
        v66q18_fail('Missing summary row: ' . $rowClass);
    }
    $rowSlice = substr($page, $rowPosition, 1400);
    if (!str_contains($rowSlice, '<img ') || !str_contains($rowSlice, 'data-music-summary-cover-hit')) {
        v66q18_fail('Summary row lost its fixed cover or invisible hit target: ' . $rowClass);
    }
}

$allSongsPosition = strpos($page, 'class="music-library-all-songs"');
if ($allSongsPosition === false || !str_contains(substr($page, $allSongsPosition), '>▶</button>')) {
    v66q18_fail('All Songs explicit Play controls were changed.');
}

echo "v66Q.18 summary covers are uniformly 52x52 with no visible Play circles.\n";

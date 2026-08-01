<?php
declare(strict_types=1);

function sidebar_v66q12_fail(string $message): never
{
    fwrite(STDERR, "v66Q.12 sidebar contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        sidebar_v66q12_fail('Unable to read ' . $path);
    }
    return $content;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        sidebar_v66q12_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        sidebar_v66q12_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$sidebar = $read('portal/sidebar.php');
$accordion = $read('assets/js/portal-sidebar-accordion-v66q11.js');
$sidebarCss = $read('assets/css/portal-sidebar-accordion-v66q11.css');
$shellCss = $read('assets/css/portal-shell-v66q7.css');
$baseCss = $read('assets/css/portal.css');
$publicSidebar = $read('portal/public-sidebar.php');

foreach ([
    'data-sidebar-storage-key="nmm.portal.sidebar.open-groups.v66q12"',
    'data-nav-group=',
    'data-nav-group-toggle',
    'data-nav-group-panel',
    'aria-expanded=',
    'aria-controls=',
    'portal-sidebar-accordion-v66q11.css?v=20260801-v66Q12',
    'portal-sidebar-accordion-v66q11.js?v=20260801-v66Q12',
] as $contract) {
    $require($sidebar, $contract, 'Accordion sidebar markup');
}

foreach ([
    'localStorage.getItem',
    'localStorage.setItem',
    'JSON.stringify(openGroupKeys())',
    'new Set(parsedState.map(String))',
    'setGroupState(group, savedOpenKeys.has(groupKey(group)))',
    'setGroupState(group, !isOpen)',
    'panel.hidden = !isOpen',
    "toggle?.setAttribute('aria-expanded'",
    "group.classList.toggle('is-open'",
] as $contract) {
    $require($accordion, $contract, 'Independent accordion state controller');
}
foreach ([
    'groups.forEach((group) => {\n      const isOpen = openKey',
    'applyState(isOpen ? \'\' : groupKey(group))',
] as $forbidden) {
    $forbid($accordion, $forbidden, 'Single-open accordion behavior');
}

$require($baseCss, '.portal-sidebar{position:fixed', 'Fixed sidebar base');
foreach ([
    '.portal-sidebar.portal-sidebar-shared',
    'overflow:hidden!important',
    '.portal-sidebar-shared .portal-nav-authenticated',
    'line-height:1.2!important',
    'background:transparent!important',
    'border-radius:0!important',
    '.portal-nav-group-links[hidden]',
] as $contract) {
    $require($sidebarCss, $contract, 'Cache-safe fixed sidebar styling');
}
$require($shellCss, '.portal-sidebar-shared{', 'Retained sidebar override');

if (preg_match('/\.portal-sidebar-shared \.portal-nav-authenticated\s*\{[^}]*overflow\s*:\s*auto/s', $sidebarCss) === 1) {
    sidebar_v66q12_fail('Authenticated sidebar navigation still scrolls.');
}

foreach ([
    'profile-chip',
    'profile-avatar',
    'Phoenix, Arizona',
    'sidebar-foot',
] as $forbidden) {
    $forbid($publicSidebar, $forbidden, 'Public index sidebar profile footer');
}

foreach (['CREATE TABLE', 'ALTER TABLE', 'DROP TABLE'] as $forbidden) {
    $forbid($sidebar . $accordion . $publicSidebar, $forbidden, 'Sidebar runtime schema mutation');
}

echo "v66Q.12 independent persisted accordion sidebar contract passed.\n";

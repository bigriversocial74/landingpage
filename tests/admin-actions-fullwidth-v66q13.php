<?php
declare(strict_types=1);

function v66q13_fail(string $message): never
{
    fwrite(STDERR, "v66Q.13 Administrator Actions failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        v66q13_fail('Unable to read ' . $path);
    }
    return $content;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        v66q13_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        v66q13_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$publishing = $read('portal/publishing-center.php');
$runtime = $read('assets/js/admin-actions-fullwidth-v66q13.js');
$css = $read('assets/css/admin-actions-fullwidth-v66q13.css');
$shell = $read('portal/bootstrap-shell.php');

foreach ([
    'function publishing_center_enabled_actions',
    'nmm_module_enabled($module)',
    'data-admin-create-action-catalog',
    'data-admin-create-direct',
    'Only modules currently enabled in Settings are shown.',
    "'label'=>'Add Blog / Article'",
    "'url'=>'portal/admin.php?view=blog&edit=new'",
    "'label'=>'Add Portfolio Project'",
    "'url'=>'portal/admin.php?view=portfolio&edit=new'",
    "'label'=>'Add Client'",
    "'url'=>'portal/admin.php?view=clients&edit=new'",
    'admin-actions-fullwidth-v66q13.css',
    'admin-actions-fullwidth-v66q13.js',
] as $contract) {
    $require($publishing, $contract, 'Enabled direct create catalog');
}

foreach ([
    'footer-publishing-stage',
    'data-footer-publishing-frame',
    'publishing-center-v66q.js',
    '<iframe',
] as $forbidden) {
    $forbid($publishing, $forbidden, 'Removed embedded Publishing workspace');
}

foreach ([
    'publishingTab?.remove()',
    "publishingPanel.removeAttribute('data-admin-launcher-panel')",
    'actionsPanel.insertBefore(catalog',
    'document.body.append(backdrop, modal)',
    "modal.dataset.adminFullwidth = 'v66Q.13'",
    'actionsTab?.click()',
] as $contract) {
    $require($runtime, $contract, 'Full-viewport modal runtime');
}

foreach ([
    'position:fixed!important',
    'inset:0!important',
    'width:100vw!important',
    'height:100dvh!important',
    'max-width:none!important',
    'border-radius:0!important',
    'grid-template-columns:repeat(4,minmax(0,1fr))',
    '[data-admin-launcher-tab="publishing"]',
] as $contract) {
    $require($css, $contract, 'Full-viewport modal styling');
}

$require($shell, 'data-admin-launcher-panel="actions"', 'Administrator Actions panel');
$require($shell, 'publishing_center_render_footer_links();', 'Enabled action catalog mount point');

foreach (['CREATE TABLE', 'ALTER TABLE', 'DROP TABLE'] as $forbidden) {
    $forbid($publishing . $runtime, $forbidden, 'Runtime schema mutation');
}

echo "v66Q.13 full-width Administrator Tools and enabled direct Actions contract passed.\n";

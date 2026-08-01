<?php
declare(strict_types=1);

$bootstrap = file_get_contents(__DIR__ . '/../portal/bootstrap.php');
$navigation = file_get_contents(__DIR__ . '/../portal/navigation.php');
$shell = file_get_contents(__DIR__ . '/../portal/bootstrap-shell.php');
$admin = file_get_contents(__DIR__ . '/../portal/admin.php');
$editor = file_get_contents(__DIR__ . '/../portal/site-builder.php');
if (
    $bootstrap === false
    || $navigation === false
    || $shell === false
    || $admin === false
    || $editor === false
) {
    fwrite(STDERR, "Unable to read portal source.\n");
    exit(1);
}

$portalSource = $bootstrap . "\n" . $navigation . "\n" . $shell;
$checks = [
    "nmm_module_enabled('landing_page')" => $portalSource,
    "'builder'," => $navigation,
    "'Page Editor'" => $navigation,
    "portal/admin.php?view=builder" => $navigation,
    'name="module_<?=e($moduleKey)?>_enabled"' => $admin,
    'data-editor-modal-open="landing"' => $editor,
    'data-editor-modal-panel="landing"' => $editor,
    'data-editor-back' => $editor,
];
foreach ($checks as $needle => $haystack) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing Landing Page editor integration: {$needle}\n");
        exit(1);
    }
}
foreach (['name="landing_template"', 'name="landing_headline"', 'name="landing_hero_image"'] as $needle) {
    if (str_contains($admin, $needle)) {
        fwrite(STDERR, "Landing page settings remain in System Settings: {$needle}\n");
        exit(1);
    }
}
if (str_contains($editor, 'data-editor-panel="landing"')) {
    fwrite(STDERR, "Landing settings must open in the editor modal, not a sidebar panel.\n");
    exit(1);
}
echo "Landing Page editor toggle and modal integration passed.\n";

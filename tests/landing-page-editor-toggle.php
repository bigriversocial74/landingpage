<?php
declare(strict_types=1);

$bootstrap = file_get_contents(__DIR__ . '/../portal/bootstrap.php');
$admin = file_get_contents(__DIR__ . '/../portal/admin.php');
$editor = file_get_contents(__DIR__ . '/../portal/site-builder.php');
if ($bootstrap === false || $admin === false || $editor === false) {
    fwrite(STDERR, "Unable to read portal source.\n");
    exit(1);
}
$checks = [
    "nmm_module_enabled('landing_page')" => $bootstrap,
    'unset($adminNavigationGroups[\'Work\'][\'builder\'])' => $bootstrap,
    "'builder' => 'Page Editor'" => $bootstrap,
    'if ($landingPageEditorEnabled)' => $bootstrap,
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
foreach (['name="landing_template"','name="landing_headline"','name="landing_hero_image"'] as $needle) {
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

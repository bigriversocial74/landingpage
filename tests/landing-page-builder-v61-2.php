<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$core = file_get_contents($root . '/portal/site-builder-core.php');
$editor = file_get_contents($root . '/portal/site-builder.php');
$script = file_get_contents($root . '/assets/js/site-builder.js');
$admin = file_get_contents($root . '/portal/admin.php');
$adminCss = file_get_contents($root . '/assets/css/site-builder-admin.css');
$publicCss = file_get_contents($root . '/assets/css/site-builder-public.css');

foreach (compact('core', 'editor', 'script', 'admin', 'adminCss', 'publicCss') as $name => $contents) {
    if ($contents === false || $contents === '') {
        fwrite(STDERR, "Unable to read {$name} source.\n");
        exit(1);
    }
}

$checks = [
    'legacy landing import' => ['site_builder_landing_payload_from_settings', $core],
    'safe import boundary' => ['site_builder_should_import_landing_settings', $core],
    'template image inventory' => ['site_builder_template_image_inventory', $core],
    'expanded gallery block' => ["'gallery'=>", $core],
    'expanded image text block' => ["'image_text'=>", $core],
    'newsletter block' => ["'newsletter'=>", $core],
    'landing editor panel' => ['data-editor-panel="landing"', $editor],
    'upper-left back button' => ['data-editor-back', $editor],
    'visible category filter' => ['data-library-categories', $editor],
    'landing settings renderer' => ['renderLandingSettings', $script],
    'block category filtering' => ['renderLibraryCategories', $script],
    'block image uploader' => ['chooseAndUploadImage', $script],
    'template image keys' => ['templateImageKey', $script],
    'preview back button' => ['site-preview-back', $publicCss],
    'visual block cards' => ['site-library-card-preview', $adminCss],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbiddenAdminFields = [
    'name="landing_template"',
    'name="landing_headline"',
    'name="landing_hero_image"',
    '<h2>Basic SEO</h2>',
];
foreach ($forbiddenAdminFields as $needle) {
    if (str_contains($admin, $needle)) {
        fwrite(STDERR, "Landing page field remains in System Settings: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($admin, 'name="module_<?=e($moduleKey)?>_enabled"')) {
    fwrite(STDERR, "Module toggles were removed from System Settings.\n");
    exit(1);
}

if (str_contains($admin, "'landing' => 'Landing settings'")) {
    fwrite(STDERR, "Landing settings must not be added to the main administrator sidebar.\n");
    exit(1);
}

echo "Landing Page Builder v61.2 regression checks passed.\n";

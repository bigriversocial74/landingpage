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
    'landing payload builder' => ['site_builder_landing_payload_from_settings', $core],
    'template image inventory' => ['site_builder_template_image_inventory', $core],
    'server empty Home fallback' => ['if ($isHomeLandingPage && empty($payload[\'sections\']))', $editor],
    'published payload preference' => ['$defaultTemplateSource = \'published Home page\'', $editor],
    'active template fallback' => ['$defaultTemplateSource = \'active landing template\'', $editor],
    'actual section status boundary' => ['$defaultTemplateLoaded && !empty($payload[\'sections\'])', $editor],
    'landing settings modal' => ['data-editor-modal-panel="landing"', $editor],
    'landing settings modal launcher' => ['data-editor-modal-open="landing"', $editor],
    'modal settings workspace' => ['data-editor-modal', $editor],
    'modal inspector workspace' => ['site-editor-inspector-modal', $editor],
    'visible category filter' => ['data-library-categories', $editor],
    'browser empty Home fallback' => ['ensureDefaultLandingCanvas', $script],
    'fallback requires actual sections' => ['payloadHasSections', $script],
    'editor modal controller' => ['openEditorModal', $script],
    'block category filtering' => ['renderLibraryCategories', $script],
    'block image uploader' => ['chooseAndUploadImage', $script],
    'template image keys' => ['templateImageKey', $script],
    'empty canvas library action' => ['data-empty-library-open', $script],
    'preview back button' => ['site-preview-back', $publicCss],
    'visual block cards' => ['site-library-card-preview', $adminCss],
    'modal block library' => ['site-library-drawer.site-library-modal', $adminCss],
    'current cache busting' => ['v=20260727-v61.7', $editor],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

foreach (['data-editor-panel="landing"', 'data-editor-panel="styles"', 'data-editor-panel="seo"', 'data-editor-panel="page"'] as $needle) {
    if (str_contains($editor, $needle)) {
        fwrite(STDERR, "Settings still render as sidebar panels: {$needle}\n");
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

echo "Landing Page Builder current regression checks passed.\n";

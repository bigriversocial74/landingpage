<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'editor' => $root . '/portal/site-builder.php',
    'core' => $root . '/portal/site-builder-core.php',
    'script' => $root . '/assets/js/site-builder.js',
    'adminCss' => $root . '/assets/css/site-builder-admin.css',
    'publicCss' => $root . '/assets/css/site-builder-public.css',
];
$sources = [];
foreach ($files as $name => $path) {
    $source = file_get_contents($path);
    if ($source === false || $source === '') {
        fwrite(STDERR, "Unable to read {$name}: {$path}\n");
        exit(1);
    }
    $sources[$name] = $source;
}

$checks = [
    'v61.5 editor build' => ['20260727-landing-page-builder-v61.5', $sources['editor']],
    'home slug landing boundary' => ["\$isLandingPage = ((\$page['page_type'] ?? '') === 'landing') || \$isHomePage;", $sources['editor']],
    'server split fallback' => ["\$payload = site_builder_templates()['split'];", $sources['editor']],
    'boot interaction lock' => ['editor-booting', $sources['editor'] . $sources['adminCss']],
    'deterministic initializer' => ['const initializeEditor = () =>', $sources['script']],
    'animation frame verification' => ['requestAnimationFrame(() =>', $sources['script']],
    'browser hard fallback' => ['hardDefaultLandingPayload', $sources['script']],
    'header settings model' => ['const ensureHeaderSettings = () =>', $sources['script']],
    'header rendered in canvas' => ['data-preview-header', $sources['script']],
    'header editor button' => ['data-editor-modal-open="header"', $sources['editor']],
    'header modal' => ['data-editor-modal-panel="header"', $sources['editor']],
    'header layer row' => ['Header & navigation</span><small>template</small>', $sources['script']],
    'configured header links' => ["'headerLinks' => \$headerLinks", $sources['editor']],
    'nested theme sanitizer' => ['site_builder_sanitize_settings($value,$depth+1)', $sources['core']],
    'public header settings' => ["\$headerStyle=in_array", $sources['core']],
    'public header CTA' => ['visual-site-header-cta', $sources['core'] . $sources['publicCss']],
    'visible library inventory' => ['12 section definitions', $root . '/V61.5-VALIDATION.txt'],
    'visible block server cards' => ['data-library-server-card="blocks"', $sources['editor']],
    'library counts' => ['<?=count($sectionLibrary)?> sections · <?=count($blockLibrary)?> blocks', $sources['editor']],
    'v61.5 cache key' => ['site-builder.js?v=20260727-v61.5', $sources['editor']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (is_string($haystack) && str_ends_with($haystack, '.txt')) {
        $haystack = (string)file_get_contents($haystack);
    }
    if (!str_contains((string)$haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$core = $sources['core'];
$sectionStart = strpos($core, 'function site_builder_section_library');
$blockStart = strpos($core, 'function site_builder_block_library');
$templateStart = strpos($core, 'function site_builder_templates');
if ($sectionStart === false || $blockStart === false || $templateStart === false) {
    fwrite(STDERR, "Unable to locate library definitions.\n");
    exit(1);
}
$sectionSource = substr($core, $sectionStart, $blockStart - $sectionStart);
$blockSource = substr($core, $blockStart, $templateStart - $blockStart);
if (substr_count($sectionSource, "'label'=>") !== 12) {
    fwrite(STDERR, "Expected exactly 12 section definitions.\n");
    exit(1);
}
if (substr_count($blockSource, "'label'=>") !== 22) {
    fwrite(STDERR, "Expected exactly 22 block definitions.\n");
    exit(1);
}

echo "Landing Page Editor v61.5 regression passed.\n";

<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$editor = file_get_contents($root . '/portal/site-builder.php');
$script = file_get_contents($root . '/assets/js/site-builder.js');
$core = file_get_contents($root . '/portal/site-builder-core.php');
$css = file_get_contents($root . '/assets/css/site-builder-admin.css');
foreach (compact('editor','script','core','css') as $name=>$source) { if ($source === false || $source === '') { fwrite(STDERR, "Unable to read {$name}.\n"); exit(1); } }
$checks = [
 'home slug landing boundary' => ["\$isLandingPage = ((\$page['page_type'] ?? '') === 'landing') || \$isHomePage;", $editor],
 'server split fallback' => ["\$payload = site_builder_templates()['split'];", $editor],
 'browser hard fallback' => ['hardDefaultLandingPayload', $script],
 'browser home boundary' => ["String(state.page.slug || '').toLowerCase() === 'home'", $script],
 'visible library summary' => ['editor-library-summary', $editor],
 'topbar library launcher' => ['data-topbar-library', $editor],
 'server rendered library cards' => ['data-library-server-card="sections"', $editor],
 'library inventory css' => ['v61.4 visible library inventory', $css],
 'section library' => ['function site_builder_section_library', $core],
 'block library' => ['function site_builder_block_library', $core],
];
foreach ($checks as $label=>[$needle,$haystack]) { if (!str_contains($haystack,$needle)) { fwrite(STDERR,"Missing {$label}: {$needle}\n"); exit(1); } }
if (substr_count($core, "'label'=>") < 34) { fwrite(STDERR,"Expected complete section/block inventory.\n"); exit(1); }
echo "Landing Page Editor v61.4 regression passed.\n";

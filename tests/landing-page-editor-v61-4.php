<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$editor = (string)file_get_contents($root.'/portal/site-builder.php');
$script = (string)file_get_contents($root.'/assets/js/site-builder.js');
$core = (string)file_get_contents($root.'/portal/site-builder-core.php');
$css = (string)file_get_contents($root.'/assets/css/site-builder-admin.css');
$checks = [
 'home slug landing boundary' => ["\$isLandingPage = ((\$page['page_type'] ?? '') === 'landing') || \$isHomePage;", $editor],
 'server split fallback' => ["\$payload = site_builder_templates()['split'];", $editor],
 'browser hard fallback' => ['hardDefaultLandingPayload', $script],
 'topbar library launcher' => ['data-topbar-library', $editor],
 'server rendered section cards' => ['data-library-server-card="sections"', $editor],
 'server rendered block cards' => ['data-library-server-card="blocks"', $editor],
 'section library' => ['function site_builder_section_library', $core],
 'block library' => ['function site_builder_block_library', $core],
 'clean section sidebar' => ['data-editor-tab="sections"', $editor],
 'restored blocks sidebar' => ['data-editor-tab="blocks"', $editor],
 'plain sidebar action' => ['editor-text-action', $editor.$css],
];
foreach($checks as $label=>[$needle,$haystack]){if(!str_contains($haystack,$needle)){fwrite(STDERR,"Missing {$label}: {$needle}\n");exit(1);}}
if(str_contains($editor,'data-editor-tab="layers"')){fwrite(STDERR,"Layers tab must be removed.\n");exit(1);}
if(str_contains($editor,'editor-library-summary')){fwrite(STDERR,"Sidebar library promo must be removed.\n");exit(1);}
echo "Landing Page Editor sidebar regression passed.\n";

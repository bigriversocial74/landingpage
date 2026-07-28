<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$files=[
 'editor'=>'portal/site-builder.php',
 'core'=>'portal/site-builder-core.php',
 'api'=>'portal/site-builder-api.php',
 'media'=>'portal/site-builder-media.php',
 'script'=>'assets/js/site-builder.js',
 'advanced'=>'assets/js/site-builder-advanced.js',
 'adminCss'=>'assets/css/site-builder-admin.css',
 'publicCss'=>'assets/css/site-builder-public.css',
];
$s=[];
foreach($files as $name=>$file){
    $s[$name]=(string)file_get_contents($root.'/'.$file);
    if($s[$name]===''){fwrite(STDERR,"Unable to read {$file}\n");exit(1);}
}
$checks=[
 'v61.8 build'=>['20260727-visual-layout-system-v61.8',$s['editor'].$s['core'].$s['api'].$s['advanced']],
 'responsive setting model'=>['responsiveKeys',$s['script']],
 'device override writer'=>['writeSetting',$s['script']],
 'grid layout controls'=>['gridColumns',$s['script'].$s['core']],
 'block spans'=>['columnSpan',$s['script'].$s['core']],
 'floating toolbar'=>['data-inline-toolbar',$s['editor']],
 'inline formatting model'=>['inlineStyles',$s['script'].$s['core']],
 'autosave action'=>['autosave_page',$s['api'].$s['advanced']],
 'named snapshots'=>['save_named_revision',$s['api'].$s['advanced']],
 'media library'=>['site_builder_media_library',$s['core'].$s['media']],
 'media browser'=>['data-media-modal',$s['editor']],
 'focal point'=>['imageFocalX',$s['script'].$s['core']],
 'global section resolver'=>['site_builder_resolve_global_sections',$s['core'].$s['editor']],
 'global section api'=>['save_global_section',$s['api'].$s['advanced']],
 'quality audit'=>['runQualityAudit',$s['advanced']],
 'command palette'=>['data-command-palette',$s['editor'].$s['advanced']],
 'global design tokens'=>['buttonPaddingX',$s['editor'].$s['core']],
 'public responsive grid'=>['--grid-cols-tablet',$s['core'].$s['publicCss']],
];
foreach($checks as $label=>[$needle,$haystack]){
    if(!str_contains($haystack,$needle)){fwrite(STDERR,"Missing {$label}: {$needle}\n");exit(1);}
}
if(str_contains($s['editor'],'data-editor-tab="layers"')){fwrite(STDERR,"Layers tab returned.\n");exit(1);}
echo "Visual layout system v61.8 regression passed.\n";

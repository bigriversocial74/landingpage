<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$files=['editor'=>'portal/site-builder.php','core'=>'portal/site-builder-core.php','script'=>'assets/js/site-builder.js','bootstrap'=>'assets/js/site-builder-bootstrap.js','adminCss'=>'assets/css/site-builder-admin.css','publicCss'=>'assets/css/site-builder-public.css','publicJs'=>'assets/js/site-public.js'];
$s=[];foreach($files as $name=>$file){$s[$name]=(string)file_get_contents($root.'/'.$file);if($s[$name]===''){fwrite(STDERR,"Unable to read {$file}\n");exit(1);}}
$checks=[
 'v61.7 editor build'=>['20260727-visual-page-editor-v61.7',$s['editor'].$s['script'].$s['core']],
 'CSP safe bootstrap'=>['<textarea id="nmm-site-builder-bootstrap" hidden>',$s['editor']],
 'external bootstrap'=>['site-builder-bootstrap.js?v=20260727-v61.7',$s['editor']],
 'registry assignment'=>['window.NMM_SITE_BUILDER = payload;',$s['bootstrap']],
 'pages workspace'=>['data-editor-tab="pages"',$s['editor']],
 'sections workspace'=>['data-editor-tab="sections"',$s['editor']],
 'blocks workspace'=>['data-editor-tab="blocks"',$s['editor']],
 'design workspace'=>['data-editor-tab="design"',$s['editor']],
 'canvas inline editing'=>['contentEditable = \'true\'',$s['script']],
 'section image replacement'=>['data-section-image-replace',$s['script']],
 'section contextual toolbar'=>['editor-section-toolbar',$s['script'].$s['adminCss']],
 'block contextual toolbar'=>['editor-block-toolbar',$s['script'].$s['adminCss']],
 'headline size control'=>['headlineSize',$s['script'].$s['core']],
 'background overlay control'=>['overlayOpacity',$s['script'].$s['core']],
 'section image position'=>['imagePosition',$s['script'].$s['core']],
 'template catalog'=>['function site_builder_template_catalog',$s['core']],
 'studio split template'=>['Studio Split',$s['core']],
 'centered launch template'=>['Centered Launch',$s['core']],
 'editorial story template'=>['Editorial Story',$s['core']],
 'platform showcase template'=>['Platform Showcase',$s['core']],
 'editor mobile drawer'=>['editor-page-mobile-drawer',$s['script'].$s['adminCss']],
 'public mobile drawer'=>['visual-site-navigation-mobile',$s['core'].$s['publicCss']],
 'public menu state'=>['document.body.classList.toggle(\'menu-open\'',$s['publicJs']],
 'mobile hamburger'=>['visual-site-menu-button',$s['core'].$s['publicCss']],
 'responsive section stack'=>['grid-template-columns:1fr!important',$s['publicCss']],
 'JSON failure boundary'=>['JSON_THROW_ON_ERROR',$s['editor']],
];
foreach($checks as $label=>[$needle,$haystack]){if(!str_contains($haystack,$needle)){fwrite(STDERR,"Missing {$label}: {$needle}\n");exit(1);}}
foreach(['data-editor-tab="layers"','site-editor-tool-menu','editor-library-summary','site-editor-page-picker'] as $forbidden){if(str_contains($s['editor'],$forbidden)){fwrite(STDERR,"Removed sidebar UI returned: {$forbidden}\n");exit(1);}}
if(str_contains($s['editor'],'<script>window.NMM_SITE_BUILDER=')){fwrite(STDERR,"Inline executable bootstrap violates CSP.\n");exit(1);}
$core=$s['core'];$sectionStart=strpos($core,'function site_builder_section_library');$blockStart=strpos($core,'function site_builder_block_library');$cleanStart=strpos($core,'function site_builder_clean_text');if($sectionStart===false||$blockStart===false||$cleanStart===false){fwrite(STDERR,"Unable to locate libraries.\n");exit(1);}$sectionSource=substr($core,$sectionStart,$blockStart-$sectionStart);$blockSource=substr($core,$blockStart,$cleanStart-$blockStart);if(substr_count($sectionSource,"'label'=>")!==12){fwrite(STDERR,"Expected 12 section definitions.\n");exit(1);}if(substr_count($blockSource,"'label'=>")!==22){fwrite(STDERR,"Expected 22 block definitions.\n");exit(1);}
echo "Visual Page Editor v61.7 regression passed.\n";

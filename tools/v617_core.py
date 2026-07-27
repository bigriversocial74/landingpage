from pathlib import Path
import re

path = Path('portal/site-builder-core.php')
text = path.read_text()
text = text.replace('20260727-landing-page-builder-v61.5', '20260727-visual-page-editor-v61.7')

catalog_and_templates = r'''function site_builder_template_catalog(): array
{
    return [
        'split'=>['label'=>'Studio Split','description'=>'Balanced service page with proof, capabilities, and a focused close.','tone'=>'Professional','sections'=>5],
        'centered'=>['label'=>'Centered Launch','description'=>'Confident product or campaign launch with a visual centerpiece and social proof.','tone'=>'Bold','sections'=>5],
        'editorial'=>['label'=>'Editorial Story','description'=>'Narrative page for perspective, process, evidence, and a strong point of view.','tone'=>'Editorial','sections'=>5],
        'showcase'=>['label'=>'Platform Showcase','description'=>'High-contrast product presentation with media, metrics, capabilities, and conversion.','tone'=>'Technology','sections'=>5],
    ];
}

function site_builder_templates(): array
{
    $theme=static function(string $template,string $primary,string $accent,int $radius,int $width,string $headingFont='system'): array {
        $theme=['template'=>$template,'contentWidth'=>(string)$width,'primary'=>$primary,'accent'=>$accent,'radius'=>(string)$radius,'footerText'=>'North Mountain Media · Phoenix, Arizona','headingFont'=>$headingFont,'bodyFont'=>'system','baseFontSize'=>'16','sectionGap'=>'0'];
        foreach(site_builder_template_image_inventory()[$template]??[] as $slot)$theme[site_builder_template_image_key($template,(string)$slot['slot'])]='';
        return $theme;
    };
    $button=static fn(string $label,string $url,string $style='primary'): array=>['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>$label,'url'=>$url,'style'=>$style]];
    $feature=static fn(string $title,string $copy): array=>['id'=>site_builder_id('feature'),'type'=>'feature','settings'=>['title'=>$title,'text'=>$copy,'image'=>'','imageAlt'=>'','padding'=>'22','borderRadius'=>'18','shadow'=>'soft']];
    $hero=static function(string $headline,string $text,string $layout='split',string $alignment='left')use($button):array{return ['id'=>site_builder_id('hero'),'type'=>'hero','settings'=>['eyebrow'=>'North Mountain Media','headline'=>$headline,'text'=>$text,'body'=>'','alignment'=>$alignment,'layout'=>$layout,'image'=>'','imageAlt'=>'','imagePosition'=>$layout==='centered'?'top':'right','headlineSize'=>$layout==='showcase'?'78':'68','textSize'=>'20','paddingTop'=>'96','paddingBottom'=>'96','minHeight'=>'620','overlayColor'=>'#08121e','overlayOpacity'=>'28'],'blocks'=>[$button('Start a project','intake.php'),$button('View portfolio','workspace.php','secondary')]];};
    $features=static function(string $headline,string $text)use($feature):array{return ['id'=>site_builder_id('features'),'type'=>'features','settings'=>['eyebrow'=>'Capabilities','headline'=>$headline,'text'=>$text,'layout'=>'grid','headlineSize'=>'52','paddingTop'=>'88','paddingBottom'=>'88','backgroundColor'=>'#f5f7f9'],'blocks'=>[$feature('Strategy and planning','Translate goals into a clear launch path.'),$feature('Connected execution','Bring content, CRM, commerce, and operations together.'),$feature('Measurable progress','Track activity, engagement, and conversion.')]];};
    $cta=static function(string $headline)use($button):array{return ['id'=>site_builder_id('cta'),'type'=>'cta','settings'=>['eyebrow'=>'Ready to build','headline'=>$headline,'text'=>'Start with a practical conversation about the goal, audience, and next step.','alignment'=>'center','headlineSize'=>'58','paddingTop'=>'96','paddingBottom'=>'96','backgroundColor'=>'#152638','textColor'=>'#ffffff'],'blocks'=>[$button('Start a project','intake.php')]];};

    $split=[
        $hero('Connected digital systems for ambitious ideas.','Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.','split'),
        ['id'=>site_builder_id('columns'),'type'=>'columns','settings'=>['eyebrow'=>'Built for momentum','headline'=>'One connected team. One measurable experience.','text'=>'A practical operating layer for growing ideas.','layout'=>'cards','headlineSize'=>'44','paddingTop'=>'64','paddingBottom'=>'64'],'blocks'=>[['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'01','label'=>'Clear strategy']],['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'02','label'=>'Connected systems']],['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'03','label'=>'Measurable growth']]]],
        $features('A clearer path from concept to working system.','Choose a focused starting point and connect the workflows required to deliver it.'),
        ['id'=>site_builder_id('content'),'type'=>'content','settings'=>['eyebrow'=>'Why it works','headline'=>'Designed around the actual business, not a generic template.','text'=>'The page, workflows, content, and customer journey are planned as one connected experience.','layout'=>'editorial','headlineSize'=>'48','paddingTop'=>'84','paddingBottom'=>'84'],'blocks'=>[['id'=>site_builder_id('testimonial'),'type'=>'testimonial','settings'=>['quote'=>'A focused system is easier to operate, improve, and scale.','name'=>'North Mountain Media','role'=>'Digital systems studio']]]],
        $cta('Turn the next idea into a connected working system.'),
    ];
    $centered=[
        $hero('Build a clearer digital future.','A centered, confident presentation for services, products, media, and conversion.','centered','center'),
        ['id'=>site_builder_id('media'),'type'=>'media','settings'=>['eyebrow'=>'Featured experience','headline'=>'Lead with the work.','text'=>'Use a wide visual, product image, or campaign story to anchor the page.','image'=>'','imageAlt'=>'','layout'=>'wide','imagePosition'=>'top','headlineSize'=>'50','paddingTop'=>'72','paddingBottom'=>'72'],'blocks'=>[]],
        $features('Everything needed to move from idea to launch.','A complete page structure for product launches, service offers, and campaigns.'),
        ['id'=>site_builder_id('columns'),'type'=>'columns','settings'=>['eyebrow'=>'Proof','headline'=>'Add trust before the final ask.','text'=>'Use testimonials, results, and customer evidence.','layout'=>'cards','headlineSize'=>'46','paddingTop'=>'80','paddingBottom'=>'80'],'blocks'=>[['id'=>site_builder_id('testimonial'),'type'=>'testimonial','settings'=>['quote'=>'Add a customer result or endorsement here.','name'=>'Customer name','role'=>'Company']],['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'100%','label'=>'Focused on the goal']]]],
        $cta('Make the next launch easier to understand and act on.'),
    ];
    $editorial=[
        $hero('Ideas deserve a strong point of view.','An editorial layout for storytelling, experience, proof, and perspective.','editorial'),
        ['id'=>site_builder_id('content'),'type'=>'content','settings'=>['eyebrow'=>'The perspective','headline'=>'Useful systems begin with clear thinking.','text'=>'Explain the challenge, the insight, and the practical path forward.','body'=>'Use longer narrative copy, supporting imagery, and pull quotes to create an intentional reading experience.','layout'=>'editorial','fontFamily'=>'editorial','headlineSize'=>'62','paddingTop'=>'96','paddingBottom'=>'96'],'blocks'=>[['id'=>site_builder_id('quote'),'type'=>'quote','settings'=>['quote'=>'Clarity is not decoration. It is part of how the system works.','citation'=>'North Mountain Media']]]],
        ['id'=>site_builder_id('media'),'type'=>'media','settings'=>['eyebrow'=>'The work','headline'=>'Give the story visual context.','text'=>'Pair the narrative with a strong image, process artifact, or featured project.','image'=>'','imageAlt'=>'','layout'=>'wide','headlineSize'=>'48','paddingTop'=>'72','paddingBottom'=>'72'],'blocks'=>[]],
        $features('From perspective to practical execution.','Connect strategy, design, operations, and measurable customer action.'),
        $cta('Tell the story, then make the next step obvious.'),
    ];
    $showcase=[
        $hero('One platform. Many connected experiences.','A high-contrast product showcase for digital systems, media, and automated commerce.','showcase'),
        ['id'=>site_builder_id('media'),'type'=>'media','settings'=>['eyebrow'=>'Product experience','headline'=>'Show the interface at full scale.','text'=>'Use the visual centerpiece for a dashboard, platform, product, or campaign system.','image'=>'','imageAlt'=>'','layout'=>'showcase','headlineSize'=>'54','paddingTop'=>'80','paddingBottom'=>'80','backgroundColor'=>'#111923','textColor'=>'#ffffff'],'blocks'=>[]],
        ['id'=>site_builder_id('columns'),'type'=>'columns','settings'=>['eyebrow'=>'Platform metrics','headline'=>'Make the value measurable.','text'=>'Highlight the connected outcomes the platform creates.','layout'=>'cards','headlineSize'=>'48','paddingTop'=>'72','paddingBottom'=>'72','backgroundColor'=>'#0c1118','textColor'=>'#ffffff'],'blocks'=>[['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'01','label'=>'Unified experience','backgroundColor'=>'#172231','textColor'=>'#ffffff']],['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'02','label'=>'Measurable activity','backgroundColor'=>'#172231','textColor'=>'#ffffff']],['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'03','label'=>'Practical automation','backgroundColor'=>'#172231','textColor'=>'#ffffff']]]],
        $features('Built to connect the full experience.','Present the capabilities, workflows, and customer outcomes in one place.'),
        $cta('Turn the platform into a clear customer journey.'),
    ];
    return [
        'split'=>['version'=>2,'theme'=>$theme('split','#152638','#0b8588',18,1180),'sections'=>$split],
        'centered'=>['version'=>2,'theme'=>$theme('centered','#101b2c','#0b8588',24,1080),'sections'=>$centered],
        'editorial'=>['version'=>2,'theme'=>$theme('editorial','#30251f','#a45c32',10,1120,'editorial'),'sections'=>$editorial],
        'showcase'=>['version'=>2,'theme'=>$theme('showcase','#0c1118','#55d6be',20,1240,'geometric'),'sections'=>$showcase],
        'blank'=>['version'=>2,'theme'=>['template'=>'blank','contentWidth'=>'1180','primary'=>'#152638','accent'=>'#0b8588','radius'=>'18','footerText'=>'North Mountain Media · Phoenix, Arizona','headingFont'=>'system','bodyFont'=>'system','baseFontSize'=>'16','sectionGap'=>'0'],'sections'=>[]],
    ];
}'''
text, count = re.subn(r'function site_builder_templates\(\): array\n\{.*?\n\}\n\nfunction site_builder_landing_payload_from_settings', catalog_and_templates + '\n\nfunction site_builder_landing_payload_from_settings', text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('Template function not replaced')

advanced_renderer = r'''function site_builder_hex_rgba(string $hex,float $opacity): string
{
    $hex=ltrim(trim($hex),'#');if(strlen($hex)===3)$hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];if(!preg_match('/^[0-9a-f]{6}$/i',$hex))$hex='000000';
    return 'rgba('.hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2)).','.max(0,min(1,$opacity)).')';
}

function site_builder_block_wrapper_style(array $block): string
{
    $s=is_array($block['settings']??null)?$block['settings']:[];$styles=[];
    if(($s['backgroundColor']??'')!==''&&preg_match('/^#[0-9a-f]{6}$/i',(string)$s['backgroundColor']))$styles[]='background-color:'.$s['backgroundColor'];
    if(($s['textColor']??'')!==''&&preg_match('/^#[0-9a-f]{6}$/i',(string)$s['textColor']))$styles[]='color:'.$s['textColor'];
    if(($s['fontSize']??'')!=='')$styles[]='font-size:'.max(9,min(80,(int)$s['fontSize'])).'px';
    if(($s['fontWeight']??'')!=='')$styles[]='font-weight:'.max(100,min(900,(int)$s['fontWeight']));
    if(($s['padding']??'')!=='')$styles[]='padding:'.max(0,min(120,(int)$s['padding'])).'px';
    if(($s['borderRadius']??'')!=='')$styles[]='border-radius:'.max(0,min(80,(int)$s['borderRadius'])).'px';
    if(in_array((string)($s['textAlign']??''),['left','center','right'],true))$styles[]='text-align:'.$s['textAlign'];
    if(($s['width']??'')==='full')$styles[]='flex-basis:100%';elseif(($s['width']??'')==='half')$styles[]='flex-basis:calc(50% - 9px)';elseif(($s['width']??'')==='third')$styles[]='flex-basis:calc(33.333% - 12px)';
    if(($s['shadow']??'')==='soft')$styles[]='box-shadow:0 12px 30px rgba(18,32,48,.10)';elseif(($s['shadow']??'')==='strong')$styles[]='box-shadow:0 20px 48px rgba(18,32,48,.20)';
    return $styles?' style="'.e(implode(';',$styles)).'"':'';
}

function site_builder_render_section(array $section): string
{
    $type=(string)($section['type']??'content');$settings=is_array($section['settings']??null)?$section['settings']:[];$blocks=is_array($section['blocks']??null)?$section['blocks']:[];
    if(!empty($settings['hidden']))return'';
    $alignment=in_array((string)($settings['alignment']??'left'),['left','center','right'],true)?(string)$settings['alignment']:'left';
    $layout=preg_replace('/[^a-z0-9_-]/i','',(string)($settings['layout']??'default'))?:'default';$imagePosition=preg_replace('/[^a-z0-9_-]/i','',(string)($settings['imagePosition']??'right'))?:'right';
    $classes=['site-builder-section','site-section-'.$type,'align-'.$alignment,'layout-'.$layout,'image-'.$imagePosition];if(($settings['image']??'')!=='')$classes[]='has-section-image';if(($settings['backgroundImage']??'')!=='')$classes[]='has-background-image';
    foreach(['desktop','tablet','mobile']as$device)if(!empty($settings['hideOn'.ucfirst($device)]))$classes[]='hide-'.$device;
    $styles=[];
    if(($settings['backgroundColor']??'')!==''&&preg_match('/^#[0-9a-f]{6}$/i',(string)$settings['backgroundColor']))$styles[]='background-color:'.$settings['backgroundColor'];
    if(($settings['textColor']??'')!==''&&preg_match('/^#[0-9a-f]{6}$/i',(string)$settings['textColor']))$styles[]='color:'.$settings['textColor'];
    if(($settings['backgroundImage']??'')!==''){$opacity=max(0,min(90,(int)($settings['overlayOpacity']??34)))/100;$overlay=site_builder_hex_rgba((string)($settings['overlayColor']??'#08121e'),$opacity);$styles[]='background-image:linear-gradient('.$overlay.','.$overlay.'),url("'.e(nmm_public_link_url((string)$settings['backgroundImage'])).'")';$styles[]='background-position:'.e(in_array((string)($settings['backgroundPosition']??'center'),['center','top','bottom','left','right'],true)?(string)$settings['backgroundPosition']:'center');}
    if(($settings['paddingTop']??'')!=='')$styles[]='padding-top:'.max(0,min(280,(int)$settings['paddingTop'])).'px';if(($settings['paddingBottom']??'')!=='')$styles[]='padding-bottom:'.max(0,min(280,(int)$settings['paddingBottom'])).'px';if(($settings['minHeight']??'')!=='')$styles[]='min-height:'.max(0,min(1200,(int)$settings['minHeight'])).'px';
    $styles[]='--section-headline-size:'.max(20,min(140,(int)($settings['headlineSize']??($type==='hero'?68:52)))).'px';$styles[]='--section-copy-size:'.max(11,min(44,(int)($settings['textSize']??20))).'px';$styles[]='--section-body-size:'.max(11,min(36,(int)($settings['bodySize']??16))).'px';$styles[]='--section-eyebrow-size:'.max(9,min(32,(int)($settings['eyebrowSize']??12))).'px';
    $styles[]='--section-image-radius:'.max(0,min(60,(int)($settings['imageRadius']??18))).'px';$styles[]='--section-content-width:'.max(280,min(1400,(int)($settings['contentWidth']??760))).'px';
    $font=(string)($settings['fontFamily']??'system');if(in_array($font,['editorial','geometric','mono'],true))$classes[]='font-'.$font;if(($settings['fontWeight']??'')!=='')$styles[]='--section-heading-weight:'.max(100,min(900,(int)$settings['fontWeight']));
    $style=$styles?' style="'.implode(';',$styles).'"':'';
    ob_start();?><section class="<?=e(implode(' ',$classes))?>" data-section-type="<?=e($type)?>"<?=$style?>><div class="site-section-inner"><div class="site-section-head"><div class="site-section-copy-column"><?php if(($settings['eyebrow']??'')!==''):?><p class="site-section-eyebrow"><?=e($settings['eyebrow'])?></p><?php endif;?><?php if(($settings['headline']??'')!==''):?><h2><?=e($settings['headline'])?></h2><?php endif;?><?php if(($settings['text']??'')!==''):?><p class="site-section-copy"><?=nl2br(e($settings['text']))?></p><?php endif;?><?php if(($settings['body']??'')!==''):?><p class="site-section-body"><?=nl2br(e($settings['body']))?></p><?php endif;?></div><?php if(($settings['image']??'')!==''):?><figure class="site-section-media"><img src="<?=e(nmm_public_link_url((string)$settings['image']))?>" alt="<?=e($settings['imageAlt']??'')?>" style="object-fit:<?=e(($settings['imageFit']??'cover')==='contain'?'contain':'cover')?>"></figure><?php endif;?></div><?php if($type==='contact'&&(!$blocks)):?><?=site_builder_contact_form($settings)?><?php elseif($type==='portfolio'&&(!$blocks)):?><?=site_builder_portfolio_project($settings)?><?php elseif($type==='music'&&(!$blocks)):?><?=site_builder_music_track($settings)?><?php elseif($type==='events'&&(!$blocks)):?><?=site_builder_event_list($settings)?><?php elseif($type==='microgifter'&&(!$blocks)):?><?php require_once __DIR__.'/microgifter-connectors.php';echo microgifter_render_offer_block($settings);?><?php endif;?><div class="site-section-blocks"><?php foreach($blocks as $block):?><div class="site-builder-block site-block-<?=e($block['type']??'text')?>"<?=site_builder_block_wrapper_style($block)?>><?=site_builder_render_block($block)?></div><?php endforeach;?></div></div></section><?php return(string)ob_get_clean();
}'''
text, count = re.subn(r'function site_builder_render_section\(array \$section\): string\n\{.*?\n\}\n\nfunction site_builder_render_page', advanced_renderer + '\n\nfunction site_builder_render_page', text, count=1, flags=re.S)
if count != 1:
    raise SystemExit('Section renderer not replaced')

text = text.replace('20260727-landing-page-builder-v61.5', '20260727-visual-page-editor-v61.7')
text = text.replace("assets/css/site-builder-public.css?v=20260727-v61.5", "assets/css/site-builder-public.css?v=20260727-v61.7")
text = text.replace("assets/js/site-public.js?v=20260727-v61.5", "assets/js/site-public.js?v=20260727-v61.7")
text = text.replace("--site-radius:<?=max(0,min(48,(int)($theme['radius']??18)))?>px", "--site-radius:<?=max(0,min(48,(int)($theme['radius']??18)))?>px;--site-base-font-size:<?=max(14,min(22,(int)($theme['baseFontSize']??16)))?>px;--site-section-gap:<?=max(0,min(80,(int)($theme['sectionGap']??0)))?>px")
text = text.replace("<body class=\"visual-site-page template-<?=e($template)?>\">", "<body class=\"visual-site-page template-<?=e($template)?> heading-font-<?=e((string)($theme['headingFont']??'system'))?> body-font-<?=e((string)($theme['bodyFont']??'system'))?>\">")

old_header = '''<?php if($headerShowNavigation):?><button type="button" class="visual-site-menu-button" data-site-menu-toggle aria-expanded="false">Menu</button><nav class="visual-site-navigation visual-site-navigation-desktop"><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?></nav><nav class="visual-site-navigation visual-site-navigation-mobile" data-site-menu><?php if(!site_builder_render_menu_location('mobile','visual-menu')):?><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?><?php endif;?></nav><?php endif;?><?php if($headerCtaLabel!==''&&$headerCtaUrl!==''):?><a class="visual-site-header-cta" href="<?=e(nmm_public_link_url($headerCtaUrl))?>"><?=e($headerCtaLabel)?></a><?php endif;?></header>'''
new_header = '''<?php if($headerShowNavigation):?><button type="button" class="visual-site-menu-button" data-site-menu-toggle aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button><nav class="visual-site-navigation visual-site-navigation-desktop"><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?></nav><?php endif;?><?php if($headerCtaLabel!==''&&$headerCtaUrl!==''):?><a class="visual-site-header-cta" href="<?=e(nmm_public_link_url($headerCtaUrl))?>"><?=e($headerCtaLabel)?></a><?php endif;?></header><?php if($headerShowNavigation):?><aside class="visual-site-navigation-mobile mobile-menu-<?=e(in_array((string)($header['mobileMenu']??'drawer'),['drawer','dropdown'],true)?(string)$header['mobileMenu']:'drawer')?>" data-site-menu aria-hidden="true"><header><a class="visual-site-brand" href="<?=e(app_url('index.php'))?>"><?php if($headerLogo!==''):?><img src="<?=e(nmm_public_link_url($headerLogo))?>" alt="<?=e($headerLogoAlt)?>"><?php endif;?><span><?=e($headerName)?></span></a><button type="button" data-site-menu-close aria-label="Close navigation">×</button></header><nav><?php if(!site_builder_render_menu_location('mobile','visual-menu')):?><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?><?php endif;?></nav><?php if($headerCtaLabel!==''&&$headerCtaUrl!==''):?><a class="visual-site-mobile-cta" href="<?=e(nmm_public_link_url($headerCtaUrl))?>"><?=e($headerCtaLabel)?></a><?php endif;?></aside><button type="button" class="visual-site-menu-backdrop" data-site-menu-close aria-label="Close navigation"></button><?php endif;?>'''
if old_header not in text:
    raise SystemExit('Public header markup not found')
text = text.replace(old_header, new_header, 1)

path.write_text(text)

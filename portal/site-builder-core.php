<?php
/* North Mountain Media build: 20260727-visual-page-editor-v61.7 */
declare(strict_types=1);

function site_builder_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ("site_pages","site_page_revisions","site_saved_blocks","site_menus","site_menu_items")'
        );
        $available = (int)$statement->fetchColumn() === 5;
    } catch (Throwable) { $available = false; }
    return $available;
}

function site_builder_id(string $prefix='item'): string
{
    return preg_replace('/[^a-z0-9-]/i','', $prefix) . '-' . bin2hex(random_bytes(5));
}

function site_builder_template_image_key(string $template,string $slot): string
{
    return 'image_'.preg_replace('/[^a-z0-9_]/i','',$template).'_'.preg_replace('/[^a-z0-9_]/i','',$slot);
}

function site_builder_template_image_inventory(): array
{
    $common=[
        ['slot'=>'hero','label'=>'Hero image','description'=>'Primary visual used in the opening section.','target'=>'hero'],
        ['slot'=>'supporting','label'=>'Supporting image','description'=>'Secondary image used in the main content or feature section.','target'=>'supporting'],
        ['slot'=>'feature_background','label'=>'Feature background','description'=>'Optional background image behind the feature inventory.','target'=>'feature_background'],
        ['slot'=>'cta_background','label'=>'CTA background','description'=>'Optional closing call-to-action background.','target'=>'cta_background'],
        ['slot'=>'social','label'=>'Social preview','description'=>'Recommended 1200 × 630 image for sharing.','target'=>'social'],
    ];
    return [
        'split'=>array_map(static fn($item)=>$item+['template'=>'split'],$common),
        'centered'=>array_map(static fn($item)=>$item+['template'=>'centered'],$common),
        'editorial'=>array_map(static fn($item)=>$item+['template'=>'editorial'],$common),
        'showcase'=>array_map(static fn($item)=>$item+['template'=>'showcase'],$common),
    ];
}

function site_builder_template_catalog(): array
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
}

function site_builder_landing_payload_from_settings(): array
{
    $template=nmm_landing_template();
    $templates=site_builder_templates();
    $payload=$templates[$template]??$templates['split'];
    $theme=$payload['theme'];
    $heroImage=nmm_site_media_url('hero');
    $supportingImage=nmm_site_media_url('secondary');
    $socialImage=nmm_site_media_url('social');
    $theme['footerText']=nmm_site_setting('landing_footer_text','North Mountain Media · Phoenix, Arizona');
    $theme[site_builder_template_image_key($template,'hero')]=$heroImage;
    $theme[site_builder_template_image_key($template,'supporting')]=$supportingImage;
    $theme[site_builder_template_image_key($template,'social')]=$socialImage;
    $payload['theme']=$theme;

    $features=[];
    foreach(nmm_landing_features() as $feature){
        $features[]=['id'=>site_builder_id('feature'),'type'=>'feature','settings'=>[
            'title'=>(string)($feature['title']??'Feature'),
            'text'=>(string)($feature['description']??''),
            'image'=>'','imageAlt'=>'',
        ]];
    }
    if(!$features){
        $features=site_builder_templates()['split']['sections'][1]['blocks'];
    }
    $hero=['id'=>site_builder_id('hero'),'type'=>'hero','settings'=>[
        'eyebrow'=>nmm_site_setting('landing_eyebrow','North Mountain Media'),
        'headline'=>nmm_site_setting('landing_headline','Connected digital systems for ambitious ideas.'),
        'text'=>nmm_site_setting('landing_subheadline','Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.'),
        'body'=>nmm_site_setting('landing_body','North Mountain Media builds focused digital products and operational platforms that help businesses, creators, and new ventures move from fragmented tools to connected execution.'),
        'alignment'=>$template==='centered'?'center':'left',
        'layout'=>$template,
        'image'=>$heroImage,
        'imageAlt'=>nmm_site_setting('landing_hero_image_alt','North Mountain Media featured work'),
    ],'blocks'=>[]];
    $primaryLabel=nmm_site_setting('landing_primary_button_label','Start a project');
    $primaryUrl=nmm_site_setting('landing_primary_button_url','intake.php');
    $secondaryLabel=nmm_site_setting('landing_secondary_button_label','View portfolio');
    $secondaryUrl=nmm_site_setting('landing_secondary_button_url','workspace.php');
    if($primaryLabel!==''&&$primaryUrl!=='')$hero['blocks'][]=['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>$primaryLabel,'url'=>$primaryUrl,'style'=>'primary']];
    if($secondaryLabel!==''&&$secondaryUrl!=='')$hero['blocks'][]=['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>$secondaryLabel,'url'=>$secondaryUrl,'style'=>'secondary']];

    $featureSection=['id'=>site_builder_id('features'),'type'=>'features','settings'=>[
        'eyebrow'=>nmm_site_setting('landing_section_eyebrow','What we build'),
        'headline'=>nmm_site_setting('landing_section_title','A clearer path from concept to working system.'),
        'text'=>nmm_site_setting('landing_section_body','Choose a focused starting point, connect the required workflows, and create a platform that can grow without losing clarity.'),
        'image'=>$template==='split'?$supportingImage:'',
        'imageAlt'=>nmm_site_setting('landing_secondary_image_alt','North Mountain Media project detail'),
        'layout'=>$template==='split'?'split':'grid',
    ],'blocks'=>$features];
    $cta=['id'=>site_builder_id('cta'),'type'=>'cta','settings'=>[
        'eyebrow'=>nmm_site_setting('landing_cta_eyebrow','Ready to build'),
        'headline'=>nmm_site_setting('landing_cta_title','Turn the next idea into a connected working system.'),
        'text'=>'','alignment'=>'center',
    ],'blocks'=>[]];
    if($primaryLabel!==''&&$primaryUrl!=='')$cta['blocks'][]=['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>$primaryLabel,'url'=>$primaryUrl,'style'=>'primary']];

    $sections=[$hero];
    if(in_array($template,['centered','editorial'],true)&&$supportingImage!==''){
        $sections[]=['id'=>site_builder_id('media'),'type'=>$template==='editorial'?'content':'media','settings'=>[
            'eyebrow'=>$template==='editorial'?'The work':'Featured work',
            'headline'=>$template==='editorial'?'Experience, context, and practical execution.':'Show the work. Explain the value.',
            'text'=>'','body'=>'','image'=>$supportingImage,
            'imageAlt'=>nmm_site_setting('landing_secondary_image_alt','North Mountain Media project detail'),
            'layout'=>$template,
        ],'blocks'=>[]];
    }
    if($template==='showcase'&&$supportingImage!==''){
        $sections[]=['id'=>site_builder_id('media'),'type'=>'media','settings'=>['eyebrow'=>'Featured system','headline'=>'A closer look at the experience.','text'=>'','image'=>$supportingImage,'imageAlt'=>nmm_site_setting('landing_secondary_image_alt','North Mountain Media project detail'),'layout'=>'showcase'],'blocks'=>[]];
    }
    $sections[]=$featureSection;
    $sections[]=$cta;
    $payload['sections']=$sections;
    return site_builder_sanitize_payload($payload);
}

function site_builder_should_import_landing_settings(array $page,array $revisions): bool
{
    return ($page['slug']??'')==='home'
        && ($page['page_type']??'')==='landing'
        && empty($page['published_json'])
        && count($revisions)===0;
}

function site_builder_section_library(): array
{
    return [
        'hero'=>['label'=>'Hero','category'=>'Layout','description'=>'Opening headline, supporting copy, image, and actions.','icon'=>'hero','keywords'=>'banner intro image buttons'],
        'content'=>['label'=>'Content story','category'=>'Layout','description'=>'Editorial copy with an optional supporting image.','icon'=>'content','keywords'=>'text story image'],
        'features'=>['label'=>'Feature grid','category'=>'Content','description'=>'Visual cards for services, benefits, or capabilities.','icon'=>'features','keywords'=>'cards services benefits image'],
        'columns'=>['label'=>'Flexible columns','category'=>'Layout','description'=>'Cards, statistics, or mixed content in columns.','icon'=>'columns','keywords'=>'grid stats cards'],
        'media'=>['label'=>'Media feature','category'=>'Media','description'=>'Wide image, video, or visual story section.','icon'=>'media','keywords'=>'image video showcase'],
        'portfolio'=>['label'=>'Portfolio projects','category'=>'Dynamic','description'=>'Published portfolio content from the portal.','icon'=>'portfolio','keywords'=>'work case study projects'],
        'music'=>['label'=>'Music release','category'=>'Dynamic','description'=>'A selected song or release from the Music Library.','icon'=>'music','keywords'=>'audio track album song'],
        'events'=>['label'=>'Upcoming events','category'=>'Dynamic','description'=>'Published upcoming events from the calendar.','icon'=>'events','keywords'=>'calendar schedule event'],
        'contact'=>['label'=>'Contact form','category'=>'Conversion','description'=>'CRM-connected inquiry form.','icon'=>'contact','keywords'=>'lead form inquiry crm'],
        'cta'=>['label'=>'Call to action','category'=>'Conversion','description'=>'Focused conversion statement with one or more buttons.','icon'=>'cta','keywords'=>'button conversion closing'],
        'microgifter'=>['label'=>'Microgifter offer','category'=>'Microgifter','description'=>'Adapter-powered offer, campaign, or reward.','icon'=>'gift','keywords'=>'gift reward campaign commerce'],
        'spacer'=>['label'=>'Spacer / divider','category'=>'Utility','description'=>'Controlled visual space between sections.','icon'=>'spacer','keywords'=>'space divider'],
    ];
}

function site_builder_block_library(): array
{
    return [
        'heading'=>['label'=>'Heading','category'=>'Content','description'=>'Section or card heading.','icon'=>'heading','keywords'=>'title headline'],
        'text'=>['label'=>'Paragraph','category'=>'Content','description'=>'Supporting paragraph or long-form copy.','icon'=>'text','keywords'=>'copy paragraph'],
        'image'=>['label'=>'Image','category'=>'Media','description'=>'Responsive image with alt text and optional caption.','icon'=>'image','keywords'=>'photo media upload'],
        'image_text'=>['label'=>'Image + text','category'=>'Media','description'=>'Image, headline, copy, and optional link in one card.','icon'=>'image-text','keywords'=>'photo card content'],
        'button'=>['label'=>'Button','category'=>'Conversion','description'=>'Primary, secondary, or text link button.','icon'=>'button','keywords'=>'link action'],
        'button_group'=>['label'=>'Button group','category'=>'Conversion','description'=>'Two coordinated calls to action.','icon'=>'buttons','keywords'=>'actions links'],
        'feature'=>['label'=>'Feature card','category'=>'Content','description'=>'Image-ready feature or service card.','icon'=>'feature','keywords'=>'service benefit icon image'],
        'stat'=>['label'=>'Statistic','category'=>'Content','description'=>'Large value with a supporting label.','icon'=>'stat','keywords'=>'metric number'],
        'testimonial'=>['label'=>'Testimonial','category'=>'Content','description'=>'Customer quote with optional portrait.','icon'=>'quote','keywords'=>'review portrait'],
        'quote'=>['label'=>'Pull quote','category'=>'Content','description'=>'Editorial quote or highlighted statement.','icon'=>'quote','keywords'=>'statement citation'],
        'gallery'=>['label'=>'Image gallery','category'=>'Media','description'=>'Upload and arrange several images.','icon'=>'gallery','keywords'=>'photos grid upload'],
        'video'=>['label'=>'Video','category'=>'Media','description'=>'Video URL with an optional uploaded poster image.','icon'=>'video','keywords'=>'movie poster media'],
        'audio'=>['label'=>'Audio player','category'=>'Music','description'=>'Standalone audio URL and title.','icon'=>'audio','keywords'=>'sound player'],
        'music_track'=>['label'=>'Music track','category'=>'Music','description'=>'Selected published Music Library track.','icon'=>'music','keywords'=>'song album stream'],
        'portfolio_project'=>['label'=>'Portfolio project','category'=>'Dynamic','description'=>'Selected published portfolio project.','icon'=>'portfolio','keywords'=>'work case study'],
        'event_list'=>['label'=>'Event list','category'=>'Dynamic','description'=>'Upcoming public events.','icon'=>'events','keywords'=>'calendar schedule'],
        'contact_form'=>['label'=>'Contact form','category'=>'Conversion','description'=>'Lead form connected to the portal CRM.','icon'=>'contact','keywords'=>'form lead crm'],
        'newsletter'=>['label'=>'Email signup','category'=>'Conversion','description'=>'Compact email-capture form.','icon'=>'newsletter','keywords'=>'subscribe email'],
        'social_links'=>['label'=>'Social links','category'=>'Conversion','description'=>'A row of labeled social or external links.','icon'=>'social','keywords'=>'linkedin facebook instagram'],
        'microgifter_offer'=>['label'=>'Microgifter offer','category'=>'Microgifter','description'=>'Campaign, gift, reward, or offer card.','icon'=>'gift','keywords'=>'commerce reward campaign'],
        'divider'=>['label'=>'Divider','category'=>'Utility','description'=>'Horizontal visual divider.','icon'=>'divider','keywords'=>'line'],
        'spacer'=>['label'=>'Spacer','category'=>'Utility','description'=>'Adjustable vertical spacing.','icon'=>'spacer','keywords'=>'space'],
    ];
}

function site_builder_clean_text(mixed $value,int $max=4000): string
{
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$value) ?? '';
    return substr($value,0,$max);
}

function site_builder_clean_url(mixed $value): string
{
    $value=site_builder_clean_text($value,500);
    if($value==='') return '';
    if(preg_match('~^(https?://|mailto:|tel:|#|/|\./|\.\./)~i',$value)) return $value;
    if(preg_match('~^[a-z][a-z0-9+.-]*:~i',$value)) return '';
    if(preg_match('/\s|[<>"\\\\]/',$value)) return '';
    return $value;
}

function site_builder_sanitize_settings(array $settings,int $depth=0): array
{
    $clean=[];
    foreach(array_slice($settings,0,120,true) as $key=>$value){
        $key=preg_replace('/[^a-z0-9_-]/i','',substr((string)$key,0,96))??'';
        if($key==='') continue;
        if(is_bool($value)||is_int($value)||is_float($value)){$clean[$key]=$value;continue;}
        if(is_array($value)){
            if($depth>=3) continue;
            if(array_is_list($value)){
                $clean[$key]=array_slice(array_map(static fn($item)=>is_scalar($item)?site_builder_clean_text($item,1000):'', $value),0,60);
            }else{
                $clean[$key]=site_builder_sanitize_settings($value,$depth+1);
            }
            continue;
        }
        $lowerKey=strtolower($key);$isUrl=str_ends_with($lowerKey,'url')||in_array($lowerKey,['image','logo','video','audio','backgroundimage','poster'],true)||str_starts_with($lowerKey,'image_');
        $clean[$key]=$isUrl?site_builder_clean_url($value):site_builder_clean_text($value,12000);
    }
    return $clean;
}

function site_builder_sanitize_payload(array $payload): array
{
    $sectionTypes=array_keys(site_builder_section_library());
    $blockTypes=array_keys(site_builder_block_library());
    $clean=['version'=>2,'theme'=>site_builder_sanitize_settings(is_array($payload['theme']??null)?$payload['theme']:[]),'sections'=>[]];
    foreach(array_slice(is_array($payload['sections']??null)?$payload['sections']:[],0,80) as $section){
        if(!is_array($section)) continue;
        $type=(string)($section['type']??'content');
        if(!in_array($type,$sectionTypes,true)) $type='content';
        $item=['id'=>site_builder_clean_text($section['id']??site_builder_id($type),80),'type'=>$type,'settings'=>site_builder_sanitize_settings(is_array($section['settings']??null)?$section['settings']:[]),'blocks'=>[]];
        foreach(array_slice(is_array($section['blocks']??null)?$section['blocks']:[],0,120) as $block){
            if(!is_array($block)) continue;
            $blockType=(string)($block['type']??'text');
            if(!in_array($blockType,$blockTypes,true)) $blockType='text';
            $item['blocks'][]=['id'=>site_builder_clean_text($block['id']??site_builder_id($blockType),80),'type'=>$blockType,'settings'=>site_builder_sanitize_settings(is_array($block['settings']??null)?$block['settings']:[])];
        }
        $clean['sections'][]=$item;
    }
    return $clean;
}

function site_builder_decode(?string $json): array
{
    if(!$json) return site_builder_templates()['blank'];
    try{$payload=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){return site_builder_templates()['blank'];}
    return site_builder_sanitize_payload(is_array($payload)?$payload:[]);
}

function site_builder_encode(array $payload): string
{
    return json_encode(site_builder_sanitize_payload($payload),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}

function site_builder_pages(bool $includeArchived=false): array
{
    if(!site_builder_schema_available()) return [];
    $sql='SELECT * FROM site_pages'.($includeArchived?'':' WHERE status<>"archived"').' ORDER BY FIELD(page_type,"landing","custom"),title,id';
    return db()->query($sql)->fetchAll();
}

function site_builder_page(int|string $identifier): ?array
{
    if(!site_builder_schema_available()) return null;
    if(is_int($identifier)||ctype_digit((string)$identifier)){$s=db()->prepare('SELECT * FROM site_pages WHERE id=:id LIMIT 1');$s->execute(['id'=>(int)$identifier]);}
    else{$s=db()->prepare('SELECT * FROM site_pages WHERE slug=:slug LIMIT 1');$s->execute(['slug'=>(string)$identifier]);}
    $page=$s->fetch();return $page?:null;
}

function site_builder_revisions(int $pageId,int $limit=30): array
{
    $s=db()->prepare('SELECT revision.*,user.display_name FROM site_page_revisions revision LEFT JOIN users user ON user.id=revision.created_by WHERE revision.page_id=:page_id ORDER BY revision.revision_number DESC LIMIT '.max(1,min(100,$limit)));
    $s->execute(['page_id'=>$pageId]);return $s->fetchAll();
}

function site_builder_save_revision(int $pageId,array $payload,string $type,int $userId,?string $note=null): int
{
    $s=db()->prepare('SELECT COALESCE(MAX(revision_number),0)+1 FROM site_page_revisions WHERE page_id=:page_id');$s->execute(['page_id'=>$pageId]);$number=(int)$s->fetchColumn();
    db()->prepare('INSERT INTO site_page_revisions(page_id,revision_number,revision_type,payload_json,note,created_by) VALUES(:page_id,:number,:type,:payload,:note,:user_id)')->execute(['page_id'=>$pageId,'number'=>$number,'type'=>$type,'payload'=>site_builder_encode($payload),'note'=>$note,'user_id'=>$userId]);
    return $number;
}

function site_builder_public_page(string $slug='home'): ?array
{
    if(!site_builder_schema_available()) return null;
    $s=db()->prepare('SELECT * FROM site_pages WHERE slug=:slug AND status="published" AND published_json IS NOT NULL LIMIT 1');$s->execute(['slug'=>$slug]);$page=$s->fetch();return $page?:null;
}

function site_builder_button(string $label,string $url,string $style='primary'): string
{
    if($label===''||$url==='') return '';
    return '<a class="site-block-button site-block-button-'.e(in_array($style,['primary','secondary','text'],true)?$style:'primary').'" href="'.e(nmm_public_link_url($url)).'" data-site-event="builder_button_clicked" data-site-label="'.e($label).'">'.e($label).'</a>';
}

function site_builder_lines(string $value,int $limit=12): array
{
    return array_slice(array_values(array_filter(array_map('trim',preg_split('/[\r\n]+/',$value)?:[]))),0,$limit);
}

function site_builder_render_block(array $block): string
{
    $type=(string)($block['type']??'text');$s=is_array($block['settings']??null)?$block['settings']:[];
    ob_start();
    if($type==='heading'){echo '<h3>'.e($s['text']??$s['title']??'Heading').'</h3>';}
    elseif($type==='text'){echo '<p>'.nl2br(e($s['text']??'Add supporting copy.')).'</p>';}
    elseif($type==='button'){echo site_builder_button((string)($s['label']??'Learn more'),(string)($s['url']??'#'),(string)($s['style']??'primary'));}
    elseif($type==='button_group'){echo '<div class="site-button-group">'.site_builder_button((string)($s['primaryLabel']??'Get started'),(string)($s['primaryUrl']??'#'),'primary').site_builder_button((string)($s['secondaryLabel']??'Learn more'),(string)($s['secondaryUrl']??'#'),'secondary').'</div>';}
    elseif($type==='image'&&($s['url']??'')!==''){echo '<figure class="site-image-block"><img loading="lazy" src="'.e(nmm_public_link_url((string)$s['url'])).'" alt="'.e($s['alt']??'').'">'.(($s['caption']??'')!==''?'<figcaption>'.e($s['caption']).'</figcaption>':'').'</figure>';}
    elseif($type==='image_text'){echo '<article class="site-image-text-card">';if(($s['image']??'')!=='')echo '<img loading="lazy" src="'.e(nmm_public_link_url((string)$s['image'])).'" alt="'.e($s['imageAlt']??'').'">';echo '<div><h3>'.e($s['title']??'Image and text').'</h3><p>'.nl2br(e($s['text']??'')).'</p>'.site_builder_button((string)($s['buttonLabel']??''),(string)($s['buttonUrl']??''),'text').'</div></article>';}
    elseif($type==='feature'){echo '<article class="site-feature-card">';if(($s['image']??'')!=='')echo '<img loading="lazy" src="'.e(nmm_public_link_url((string)$s['image'])).'" alt="'.e($s['imageAlt']??'').'">';echo '<h3>'.e($s['title']??'Feature').'</h3><p>'.e($s['text']??'').'</p></article>';}
    elseif($type==='stat'){echo '<article class="site-stat-card"><strong>'.e($s['value']??'0').'</strong><span>'.e($s['label']??'Metric').'</span></article>';}
    elseif($type==='testimonial'){echo '<blockquote class="site-testimonial">';if(($s['image']??'')!=='')echo '<img loading="lazy" src="'.e(nmm_public_link_url((string)$s['image'])).'" alt="'.e($s['imageAlt']??'').'">';echo '<p>'.e($s['quote']??'Add a customer quote.').'</p><cite>'.e($s['name']??'Customer').(($s['role']??'')!==''?' · '.e($s['role']):'').'</cite></blockquote>';}
    elseif($type==='quote'){echo '<blockquote class="site-pull-quote"><p>'.e($s['quote']??'Add a highlighted statement.').'</p>'.(($s['citation']??'')!==''?'<cite>'.e($s['citation']).'</cite>':'').'</blockquote>';}
    elseif($type==='gallery'){$images=site_builder_lines((string)($s['images']??''),8);if($images){echo '<div class="site-image-gallery">';foreach($images as $image)echo '<img loading="lazy" src="'.e(nmm_public_link_url($image)).'" alt="'.e($s['alt']??'Gallery image').'">';echo '</div>';}}
    elseif($type==='video'&&($s['url']??'')!==''){echo '<video class="site-video-block" controls preload="metadata"'.(($s['poster']??'')!==''?' poster="'.e(nmm_public_link_url((string)$s['poster'])).'"':'').'><source src="'.e(nmm_public_link_url((string)$s['url'])).'"></video>';}
    elseif($type==='divider'){echo '<hr>';}
    elseif($type==='spacer'){echo '<div class="site-block-spacer" style="height:'.max(12,min(240,(int)($s['height']??48))).'px"></div>';}
    elseif($type==='contact_form'){echo site_builder_contact_form($s);}
    elseif($type==='newsletter'){echo site_builder_newsletter_form($s);}
    elseif($type==='social_links'){$links=site_builder_lines((string)($s['links']??''),12);echo '<div class="site-social-links">';foreach($links as $line){[$label,$url]=array_pad(array_map('trim',explode('|',$line,2)),2,'');if($label!==''&&$url!=='')echo site_builder_button($label,$url,'secondary');}echo '</div>';}
    elseif($type==='music_track'){echo site_builder_music_track($s);}
    elseif($type==='portfolio_project'){echo site_builder_portfolio_project($s);}
    elseif($type==='event_list'){echo site_builder_event_list($s);}
    elseif($type==='microgifter_offer'){require_once __DIR__.'/microgifter-connectors.php';echo microgifter_render_offer_block($s);}
    elseif($type==='audio'&&($s['url']??'')!==''){echo '<div class="site-audio-block">'.(($s['title']??'')!==''?'<strong>'.e($s['title']).'</strong>':'').'<audio controls preload="metadata" src="'.e(nmm_public_link_url((string)$s['url'])).'"></audio></div>';}
    return (string)ob_get_clean();
}

function site_builder_contact_form(array $s=[]): string
{
    ob_start();?><form class="site-contact-form" data-site-contact-form><div class="site-form-grid"><label><span>Name</span><input name="name" required maxlength="160"></label><label><span>Email</span><input type="email" name="email" required maxlength="190"></label><label><span>Phone</span><input name="phone" maxlength="60"></label><label><span>Company</span><input name="company" maxlength="190"></label><label class="full"><span>How can we help?</span><textarea name="message" required maxlength="8000" rows="5"></textarea></label><input class="site-honeypot" name="website" tabindex="-1" autocomplete="off"><input type="hidden" name="opportunity" value="<?=e($s['opportunity']??'Website inquiry')?>"></div><button class="site-block-button site-block-button-primary" type="submit"><?=e($s['buttonLabel']??'Send inquiry')?></button><p class="site-form-status" data-site-form-status></p></form><?php return (string)ob_get_clean();
}

function site_builder_newsletter_form(array $s=[]): string
{
    ob_start();?><form class="site-newsletter-form" data-site-contact-form><label><span><?=e($s['label']??'Email address')?></span><input type="email" name="email" required maxlength="190" placeholder="<?=e($s['placeholder']??'you@example.com')?>"></label><input type="hidden" name="name" value="Newsletter subscriber"><input type="hidden" name="message" value="Website email signup"><input type="hidden" name="opportunity" value="<?=e($s['opportunity']??'Newsletter signup')?>"><button class="site-block-button site-block-button-primary" type="submit"><?=e($s['buttonLabel']??'Subscribe')?></button><p class="site-form-status" data-site-form-status></p></form><?php return (string)ob_get_clean();
}

function site_builder_music_track(array $s): string
{
    $id=max(0,(int)($s['trackId']??0));if($id<=0||!function_exists('music_library_schema_available')||!music_library_schema_available()) return '<div class="site-dynamic-placeholder">Choose a published music track.</div>';
    $q=db()->prepare('SELECT track.id,track.title,track.artist_name,track.duration_seconds,track.album_id,asset.stored_name,album.title album_title FROM music_tracks track JOIN knowledge_assets asset ON asset.id=track.knowledge_asset_id LEFT JOIN music_albums album ON album.id=track.album_id WHERE track.id=:id AND track.status="active" AND asset.status="published" AND asset.is_public=1 LIMIT 1');$q->execute(['id'=>$id]);$t=$q->fetch();if(!$t)return '<div class="site-dynamic-placeholder">Music track unavailable.</div>';
    $stream=music_track_stream_url($id);$cover=music_cover_url($t['album_id']!==null?'album':'track',$t['album_id']!==null?(int)$t['album_id']:$id);return '<article class="site-music-card"><div><span>'.e($t['album_title']??'Music Library').'</span><h3>'.e($t['title']).'</h3><p>'.e($t['artist_name']).'</p></div><button type="button" data-music-play data-track-id="'.$id.'" data-track-title="'.e($t['title']).'" data-track-artist="'.e($t['artist_name']).'" data-track-album="'.e($t['album_title']??'').'" data-track-stream="'.e($stream).'" data-track-cover="'.e($cover).'" data-track-duration="'.(int)$t['duration_seconds'].'">Play</button></article>';
}

function site_builder_portfolio_project(array $s): string
{
    $id=max(0,(int)($s['projectId']??0));
    try{$q=$id>0?db()->prepare('SELECT id,title,slug,summary FROM portfolio_projects WHERE id=:id AND status="active" LIMIT 1'):null;if($q){$q->execute(['id'=>$id]);$project=$q->fetch();}else{$project=db()->query('SELECT id,title,slug,summary FROM portfolio_projects WHERE status="active" ORDER BY featured DESC,sort_order,id LIMIT 1')->fetch();}}catch(Throwable){$project=false;}
    if(!$project)return '<div class="site-dynamic-placeholder">Choose a published portfolio project.</div>';
    return '<article class="site-project-card"><span>Featured project</span><h3>'.e($project['title']).'</h3><p>'.e($project['summary']??'').'</p><a href="'.e(app_url('workspace.php#'.($project['slug']??'featured-project'))).'">View project</a></article>';
}

function site_builder_event_list(array $s): string
{
    try{$rows=db()->query('SELECT id,title,slug,start_at,location_name FROM calendar_events WHERE status="published" AND visibility="public" AND start_at>=UTC_TIMESTAMP() ORDER BY start_at LIMIT 3')->fetchAll();}catch(Throwable){$rows=[];}
    if(!$rows)return '<div class="site-dynamic-placeholder">No upcoming published events.</div>';
    $html='<div class="site-event-list">';foreach($rows as $event){$html.='<a href="'.e(app_url('event.php?slug='.$event['slug'])).'"><time>'.e(format_datetime($event['start_at'])).'</time><strong>'.e($event['title']).'</strong><span>'.e($event['location_name']??'').'</span></a>';}$html.='</div>';return $html;
}

function site_builder_hex_rgba(string $hex,float $opacity): string
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
}

function site_builder_render_page(array $page,array $payload,bool $preview=false): void
{
    if(!headers_sent()){
        header('Cache-Control: '.($preview?'no-store, private':'no-cache, max-age=0, must-revalidate'));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; media-src 'self' https: blob:; font-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
    }
    $theme=is_array($payload['theme']??null)?$payload['theme']:[];
    $siteName=setting('site_name','North Mountain Media')?:'North Mountain Media';
    $title=(string)((($page['seo_title']??'')!=='')?$page['seo_title']:($page['title']??'Page'));
    $description=(string)($page['seo_description']??'');
    $keywords=(string)($page['seo_keywords']??'');
    $canonical=(string)($page['seo_canonical_url']??'');
    $social=(string)($page['seo_social_image']??'');
    $template=preg_replace('/[^a-z0-9_-]/i','',(string)($page['template_key']??$theme['template']??'split'))?:'split';
    if($canonical===''){ $base=rtrim(nmm_site_setting('seo_site_url'),'/'); if($base!=='')$canonical=$base.'/'.($page['slug']==='home'?'':('page.php?slug='.rawurlencode((string)$page['slug']))); }
    if($social==='')$social=(string)($theme[site_builder_template_image_key($template,'social')]??'');
    if($social==='')$social=nmm_site_media_url('social');
    $footerText=(string)($theme['footerText']??nmm_site_setting('landing_footer_text','North Mountain Media · Phoenix, Arizona'));
    $header=is_array($theme['header']??null)?$theme['header']:[];
    $headerStyle=in_array((string)($header['style']??'light'),['light','dark','transparent'],true)?(string)$header['style']:'light';
    $headerLogo=trim((string)($header['logo']??''))!==''?(string)$header['logo']:nmm_site_logo_url();
    $headerLogoAlt=(string)($header['logoAlt']??nmm_site_logo_alt());
    $headerName=(string)($header['siteName']??$siteName);
    $headerShowNavigation=!array_key_exists('showNavigation',$header)||(bool)$header['showNavigation'];
    $headerSticky=!array_key_exists('sticky',$header)||(bool)$header['sticky'];
    $headerCtaLabel=trim((string)($header['ctaLabel']??''));
    $headerCtaUrl=trim((string)($header['ctaUrl']??''));
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="build-version" content="20260727-visual-page-editor-v61.7"><title><?=e($title)?> — <?=e($siteName)?></title><?php if($description!==''):?><meta name="description" content="<?=e($description)?>"><meta property="og:description" content="<?=e($description)?>"><?php endif;?><?php if($keywords!==''):?><meta name="keywords" content="<?=e($keywords)?>"><?php endif;?><meta name="robots" content="<?=$preview||!(bool)($page['seo_index_enabled']??1)?'noindex,nofollow':'index,follow'?>"><meta property="og:title" content="<?=e($title)?>"><meta property="og:type" content="website"><?php if($canonical!==''):?><link rel="canonical" href="<?=e($canonical)?>"><meta property="og:url" content="<?=e($canonical)?>"><?php endif;?><?php if($social!==''):?><meta property="og:image" content="<?=e(nmm_public_link_url($social))?>"><?php endif;?><link rel="stylesheet" href="<?=e(app_url('assets/css/site-builder-public.css?v=20260727-v61.7'))?>"><link rel="stylesheet" href="<?=e(app_url('assets/css/music-library.css?v=20260727-v61'))?>"><style>:root{--site-content-width:<?=max(720,min(1600,(int)($theme['contentWidth']??1180)))?>px;--site-primary:<?=e($theme['primary']??'#152638')?>;--site-accent:<?=e($theme['accent']??'#0b8588')?>;--site-radius:<?=max(0,min(48,(int)($theme['radius']??18)))?>px;--site-base-font-size:<?=max(14,min(22,(int)($theme['baseFontSize']??16)))?>px;--site-section-gap:<?=max(0,min(80,(int)($theme['sectionGap']??0)))?>px}</style></head><body class="visual-site-page template-<?=e($template)?> heading-font-<?=e((string)($theme['headingFont']??'system'))?> body-font-<?=e((string)($theme['bodyFont']??'system'))?>"><?php if($preview):?><a class="site-preview-back" href="<?=e(app_url('portal/site-builder.php?page='.(int)$page['id']))?>">← Back to editor</a><?php endif;?><header class="visual-site-header header-<?=e($headerStyle)?> <?=$headerSticky?'is-sticky':'not-sticky'?>"><a class="visual-site-brand visual-site-brand-desktop" href="<?=e(app_url('index.php'))?>"><?php if($headerLogo!==''):?><img src="<?=e(nmm_public_link_url($headerLogo))?>" alt="<?=e($headerLogoAlt)?>"><?php endif;?><span><?=e($headerName)?></span></a><div class="visual-site-brand-mobile"><a class="visual-site-brand" href="<?=e(app_url('index.php'))?>"><?php if($headerLogo!==''):?><img src="<?=e(nmm_public_link_url($headerLogo))?>" alt="<?=e($headerLogoAlt)?>"><?php endif;?><span><?=e($headerName)?></span></a></div><?php if($headerShowNavigation):?><button type="button" class="visual-site-menu-button" data-site-menu-toggle aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button><nav class="visual-site-navigation visual-site-navigation-desktop"><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?></nav><?php endif;?><?php if($headerCtaLabel!==''&&$headerCtaUrl!==''):?><a class="visual-site-header-cta" href="<?=e(nmm_public_link_url($headerCtaUrl))?>"><?=e($headerCtaLabel)?></a><?php endif;?></header><?php if($headerShowNavigation):?><aside class="visual-site-navigation-mobile mobile-menu-<?=e(in_array((string)($header['mobileMenu']??'drawer'),['drawer','dropdown'],true)?(string)$header['mobileMenu']:'drawer')?>" data-site-menu aria-hidden="true"><header><a class="visual-site-brand" href="<?=e(app_url('index.php'))?>"><?php if($headerLogo!==''):?><img src="<?=e(nmm_public_link_url($headerLogo))?>" alt="<?=e($headerLogoAlt)?>"><?php endif;?><span><?=e($headerName)?></span></a><button type="button" data-site-menu-close aria-label="Close navigation">×</button></header><nav><?php if(!site_builder_render_menu_location('mobile','visual-menu')):?><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?><?php endif;?></nav><?php if($headerCtaLabel!==''&&$headerCtaUrl!==''):?><a class="visual-site-mobile-cta" href="<?=e(nmm_public_link_url($headerCtaUrl))?>"><?=e($headerCtaLabel)?></a><?php endif;?></aside><button type="button" class="visual-site-menu-backdrop" data-site-menu-close aria-label="Close navigation"></button><?php endif;?><main><?php foreach($payload['sections']??[] as $section) echo site_builder_render_section($section);?></main><footer class="visual-site-footer"><div><?php if(!site_builder_render_menu_location('footer','visual-footer-menu')):?><span><?=e($footerText)?></span><?php endif;?></div></footer><script src="<?=e(app_url('assets/js/site-public.js?v=20260727-v61.7'))?>"></script><script src="<?=e(app_url('assets/js/music-player.js?v=20260727-v61'))?>"></script></body></html><?php
}

function site_builder_module_links(): array
{
    $links=[
      'landing_page'=>['Landing Page','index.php'],'portfolio'=>['Portfolio','workspace.php#featured-project'],'resume'=>['Resume','workspace.php#resume'],'music_library'=>['Music Library','music-library.php'],'blog'=>['Blog','blog.php'],'events'=>['Events','events.php'],'bookings'=>['Bookings','booking.php'],'project_intake'=>['Project Intake','intake.php'],'call_us'=>['Call Us','call-dave.php'],
    ];
    $links=array_filter($links,static fn($v,$k)=>nmm_module_enabled((string)$k),ARRAY_FILTER_USE_BOTH);
    if(isset($links['bookings'])&&function_exists('booking_public_link_available')&&!booking_public_link_available())unset($links['bookings']);
    return $links;
}

function site_builder_menus(): array
{
    if(!site_builder_schema_available())return[];return db()->query('SELECT * FROM site_menus ORDER BY name,id')->fetchAll();
}
function site_builder_menu(string|int $identifier): ?array
{
    if(is_int($identifier)||ctype_digit((string)$identifier)){$s=db()->prepare('SELECT * FROM site_menus WHERE id=:id LIMIT 1');$s->execute(['id'=>(int)$identifier]);}else{$s=db()->prepare('SELECT * FROM site_menus WHERE slug=:slug LIMIT 1');$s->execute(['slug'=>(string)$identifier]);}$row=$s->fetch();return$row?:null;
}
function site_builder_menu_items(int $menuId): array
{
    $s=db()->prepare('SELECT item.*,page.slug page_slug,page.status page_status FROM site_menu_items item LEFT JOIN site_pages page ON page.id=item.page_id WHERE item.menu_id=:menu_id ORDER BY item.sort_order,item.id');$s->execute(['menu_id'=>$menuId]);return$s->fetchAll();
}
function site_builder_menu_item_url(array $item): string
{
    if(($item['item_type']??'')==='page'&&!empty($item['page_slug'])&&($item['page_status']??'')==='published')return ($item['page_slug']==='home'?'index.php':'page.php?slug='.rawurlencode((string)$item['page_slug']));
    if(($item['item_type']??'')==='module'&&!empty($item['module_key'])){return site_builder_module_links()[$item['module_key']][1]??'';}
    return (string)($item['url']??'');
}
function site_builder_render_menu_location(string $location,string $class='site-menu'): bool
{
    if(!site_builder_schema_available())return false;$slug=nmm_site_setting('menu_location_'.$location,$location==='header'?'primary':$location);$menu=site_builder_menu($slug);if(!$menu||$menu['status']!=='active')return false;$items=site_builder_menu_items((int)$menu['id']);if(!$items)return false;
    $children=[];$roots=[];foreach($items as $item){$parent=(int)($item['parent_id']??0);if($parent>0)$children[$parent][]=$item;else$roots[]=$item;}
    $render=function(array $nodes)use(&$render,$children,$class):array{$html='<ul class="'.e($class).'">';$count=0;foreach($nodes as $item){$url=site_builder_menu_item_url($item);if($url==='')continue;$target=$item['target']==='_blank'?' target="_blank" rel="noopener"':'';$childHtml='';$childCount=0;if(!empty($children[(int)$item['id']]))[$childHtml,$childCount]=$render($children[(int)$item['id']]);$html.='<li class="'.e($item['css_class']??'').'"><a href="'.e(nmm_public_link_url($url)).'"'.$target.'>'.e($item['label']).'</a>'.($childCount>0?$childHtml:'').'</li>';$count++;}$html.='</ul>';return[$html,$count];};
    [$html,$count]=$render($roots);if($count<1)return false;echo$html;return true;
}
function site_builder_fallback_menu(): string
{
    $html='';foreach(site_builder_module_links() as [$label,$url])$html.='<a href="'.e(app_url($url)).'">'.e($label).'</a>';return$html;
}

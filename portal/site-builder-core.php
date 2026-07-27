<?php
/* North Mountain Media build: 20260727-landing-page-builder-v61.3 */
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

function site_builder_templates(): array
{
    $theme=static function(string $template,string $primary,string $accent,int $radius,int $width): array {
        $theme=['template'=>$template,'contentWidth'=>(string)$width,'primary'=>$primary,'accent'=>$accent,'radius'=>(string)$radius,'footerText'=>'North Mountain Media · Phoenix, Arizona'];
        foreach(site_builder_template_image_inventory()[$template]??[] as $slot){
            $theme[site_builder_template_image_key($template,(string)$slot['slot'])]='';
        }
        return $theme;
    };
    $hero=static fn(string $headline,string $text,string $alignment='left'): array => [
        'id'=>site_builder_id('hero'),'type'=>'hero','settings'=>[
            'eyebrow'=>'North Mountain Media','headline'=>$headline,'text'=>$text,'body'=>'','alignment'=>$alignment,'layout'=>$alignment==='center'?'centered':'split','image'=>'','imageAlt'=>'',
        ],'blocks'=>[
            ['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>'Start a project','url'=>'intake.php','style'=>'primary']],
            ['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>'View portfolio','url'=>'workspace.php','style'=>'secondary']],
        ],
    ];
    $features=static fn(): array => [
        'id'=>site_builder_id('features'),'type'=>'features','settings'=>[
            'eyebrow'=>'What we build','headline'=>'A clearer path from concept to working system.','text'=>'Choose a focused starting point and connect the workflows required to deliver it.','image'=>'','imageAlt'=>'','layout'=>'grid',
        ],'blocks'=>[
            ['id'=>site_builder_id('feature'),'type'=>'feature','settings'=>['title'=>'Strategy and planning','text'=>'Translate goals into a clear launch path.','image'=>'','imageAlt'=>'']],
            ['id'=>site_builder_id('feature'),'type'=>'feature','settings'=>['title'=>'Connected execution','text'=>'Bring content, CRM, commerce, and operations together.','image'=>'','imageAlt'=>'']],
            ['id'=>site_builder_id('feature'),'type'=>'feature','settings'=>['title'=>'Measurable progress','text'=>'Track activity, engagement, and conversion.','image'=>'','imageAlt'=>'']],
        ],
    ];
    $cta=static fn(): array => ['id'=>site_builder_id('cta'),'type'=>'cta','settings'=>['eyebrow'=>'Ready to build','headline'=>'Turn the next idea into a connected working system.','text'=>'Start with a practical conversation about the goal and next step.','alignment'=>'center'],'blocks'=>[
        ['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>'Start a project','url'=>'intake.php','style'=>'primary']],
    ]];
    $splitHero=$hero('Connected digital systems for ambitious ideas.','Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.');
    $centeredHero=$hero('Build a clearer digital future.','A centered, confident presentation for services, products, media, and conversion.','center');
    $editorialHero=$hero('Ideas deserve a strong point of view.','An editorial layout for storytelling, experience, proof, and perspective.');
    $showcaseHero=$hero('One platform. Many connected experiences.','A high-contrast product showcase for digital systems, media, and automated commerce.');
    return [
        'split'=>['version'=>2,'theme'=>$theme('split','#152638','#0b8588',18,1180),'sections'=>[$splitHero,$features(),$cta()]],
        'centered'=>['version'=>2,'theme'=>$theme('centered','#101b2c','#0b8588',24,1080),'sections'=>[$centeredHero,['id'=>site_builder_id('media'),'type'=>'media','settings'=>['eyebrow'=>'Featured work','headline'=>'Show the work. Explain the value.','text'=>'Use this section for a wide image, video, or featured project.','image'=>'','imageAlt'=>'','layout'=>'wide'],'blocks'=>[]],$features(),$cta()]],
        'editorial'=>['version'=>2,'theme'=>$theme('editorial','#30251f','#a45c32',10,1120),'sections'=>[$editorialHero,['id'=>site_builder_id('content'),'type'=>'content','settings'=>['eyebrow'=>'The approach','headline'=>'Useful systems begin with clear thinking.','text'=>'Explain the challenge, the insight, and the practical path forward.','body'=>'','image'=>'','imageAlt'=>'','layout'=>'editorial'],'blocks'=>[]],$features(),$cta()]],
        'showcase'=>['version'=>2,'theme'=>$theme('showcase','#0c1118','#55d6be',20,1240),'sections'=>[$showcaseHero,['id'=>site_builder_id('columns'),'type'=>'columns','settings'=>['eyebrow'=>'Platform capabilities','headline'=>'Built to connect the full experience.','text'=>'Show measurable outcomes and platform capabilities.','layout'=>'cards'],'blocks'=>[
            ['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'01','label'=>'Unified experience']],
            ['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'02','label'=>'Measurable activity']],
            ['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'03','label'=>'Practical automation']],
        ]],$features(),$cta()]],
        'blank'=>['version'=>2,'theme'=>['template'=>'blank','contentWidth'=>'1180','primary'=>'#152638','accent'=>'#0b8588','radius'=>'18','footerText'=>'North Mountain Media · Phoenix, Arizona'],'sections'=>[]],
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

function site_builder_sanitize_settings(array $settings): array
{
    $clean=[];
    foreach(array_slice($settings,0,120,true) as $key=>$value){
        $key=preg_replace('/[^a-z0-9_-]/i','',substr((string)$key,0,96))??'';
        if($key==='') continue;
        if(is_bool($value)||is_int($value)||is_float($value)){$clean[$key]=$value;continue;}
        if(is_array($value)){$clean[$key]=array_slice(array_map(static fn($item)=>site_builder_clean_text($item,1000),$value),0,60);continue;}
        $lowerKey=strtolower($key);$isUrl=str_ends_with($lowerKey,'url')||in_array($lowerKey,['image','video','audio','backgroundimage','poster'],true)||str_starts_with($lowerKey,'image_');
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

function site_builder_render_section(array $section): string
{
    $type=(string)($section['type']??'content');$settings=is_array($section['settings']??null)?$section['settings']:[];$blocks=is_array($section['blocks']??null)?$section['blocks']:[];
    if(!empty($settings['hidden'])) return '';
    $alignment=in_array((string)($settings['alignment']??'left'),['left','center','right'],true)?(string)$settings['alignment']:'left';
    $layout=preg_replace('/[^a-z0-9_-]/i','',(string)($settings['layout']??'default'))?:'default';
    $classes=['site-builder-section','site-section-'.$type,'align-'.$alignment,'layout-'.$layout];
    if(($settings['image']??'')!=='')$classes[]='has-section-image';
    foreach(['desktop','tablet','mobile'] as $device){if(!empty($settings['hideOn'.ucfirst($device)]))$classes[]='hide-'.$device;}
    $styles=[];
    if(($settings['backgroundColor']??'')!==''&&preg_match('/^#[0-9a-f]{3,8}$/i',(string)$settings['backgroundColor']))$styles[]='background-color:'.$settings['backgroundColor'];
    if(($settings['backgroundImage']??'')!=='')$styles[]='background-image:linear-gradient(rgba(7,18,30,.34),rgba(7,18,30,.34)),url("'.e(nmm_public_link_url((string)$settings['backgroundImage'])).'")';
    $paddingTop=max(0,min(240,(int)($settings['paddingTop']??0)));$paddingBottom=max(0,min(240,(int)($settings['paddingBottom']??0)));
    if($paddingTop>0)$styles[]='padding-top:'.$paddingTop.'px';if($paddingBottom>0)$styles[]='padding-bottom:'.$paddingBottom.'px';
    $style=$styles?' style="'.implode(';',$styles).'"':'';
    ob_start();?><section class="<?=e(implode(' ',$classes))?>" data-section-type="<?=e($type)?>"<?=$style?>><div class="site-section-inner"><div class="site-section-head"><div class="site-section-copy-column"><?php if(($settings['eyebrow']??'')!==''):?><p class="site-section-eyebrow"><?=e($settings['eyebrow'])?></p><?php endif;?><?php if(($settings['headline']??'')!==''):?><h2><?=e($settings['headline'])?></h2><?php endif;?><?php if(($settings['text']??'')!==''):?><p class="site-section-copy"><?=nl2br(e($settings['text']))?></p><?php endif;?><?php if(($settings['body']??'')!==''):?><p class="site-section-body"><?=nl2br(e($settings['body']))?></p><?php endif;?></div><?php if(($settings['image']??'')!==''):?><figure class="site-section-media"><img src="<?=e(nmm_public_link_url((string)$settings['image']))?>" alt="<?=e($settings['imageAlt']??'')?>"></figure><?php endif;?></div><?php if($type==='contact'&&(!$blocks)):?><?=site_builder_contact_form($settings)?><?php elseif($type==='portfolio'&&(!$blocks)):?><?=site_builder_portfolio_project($settings)?><?php elseif($type==='music'&&(!$blocks)):?><?=site_builder_music_track($settings)?><?php elseif($type==='events'&&(!$blocks)):?><?=site_builder_event_list($settings)?><?php elseif($type==='microgifter'&&(!$blocks)):?><?php require_once __DIR__.'/microgifter-connectors.php';echo microgifter_render_offer_block($settings);?><?php endif;?><div class="site-section-blocks"><?php foreach($blocks as $block):?><div class="site-builder-block site-block-<?=e($block['type']??'text')?>"><?=site_builder_render_block($block)?></div><?php endforeach;?></div></div></section><?php return (string)ob_get_clean();
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
    $theme=$payload['theme']??[];$siteName=setting('site_name','North Mountain Media')?:'North Mountain Media';$title=(string)((($page['seo_title']??'')!=='')?$page['seo_title']:($page['title']??'Page'));$description=(string)($page['seo_description']??'');$keywords=(string)($page['seo_keywords']??'');$canonical=(string)($page['seo_canonical_url']??'');$social=(string)($page['seo_social_image']??'');$template=preg_replace('/[^a-z0-9_-]/i','',(string)($page['template_key']??$theme['template']??'split'))?:'split';
    if($canonical===''){ $base=rtrim(nmm_site_setting('seo_site_url'),'/'); if($base!=='')$canonical=$base.'/'.($page['slug']==='home'?'':('page.php?slug='.rawurlencode((string)$page['slug']))); }
    if($social==='')$social=(string)($theme[site_builder_template_image_key($template,'social')]??'');
    if($social==='')$social=nmm_site_media_url('social');
    $footerText=(string)($theme['footerText']??nmm_site_setting('landing_footer_text','North Mountain Media · Phoenix, Arizona'));
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="build-version" content="20260727-landing-page-builder-v61.3"><title><?=e($title)?> — <?=e($siteName)?></title><?php if($description!==''):?><meta name="description" content="<?=e($description)?>"><meta property="og:description" content="<?=e($description)?>"><?php endif;?><?php if($keywords!==''):?><meta name="keywords" content="<?=e($keywords)?>"><?php endif;?><meta name="robots" content="<?=$preview||!(bool)($page['seo_index_enabled']??1)?'noindex,nofollow':'index,follow'?>"><meta property="og:title" content="<?=e($title)?>"><meta property="og:type" content="website"><?php if($canonical!==''):?><link rel="canonical" href="<?=e($canonical)?>"><meta property="og:url" content="<?=e($canonical)?>"><?php endif;?><?php if($social!==''):?><meta property="og:image" content="<?=e(nmm_public_link_url($social))?>"><?php endif;?><link rel="stylesheet" href="<?=e(app_url('assets/css/site-builder-public.css?v=20260727-v61.3'))?>"><link rel="stylesheet" href="<?=e(app_url('assets/css/music-library.css?v=20260727-v61'))?>"><style>:root{--site-content-width:<?=max(720,min(1600,(int)($theme['contentWidth']??1180)))?>px;--site-primary:<?=e($theme['primary']??'#152638')?>;--site-accent:<?=e($theme['accent']??'#0b8588')?>;--site-radius:<?=max(0,min(48,(int)($theme['radius']??18)))?>px}</style></head><body class="visual-site-page template-<?=e($template)?>"><?php if($preview):?><a class="site-preview-back" href="<?=e(app_url('portal/site-builder.php?page='.(int)$page['id']))?>">← Back to editor</a><?php endif;?><header class="visual-site-header"><a class="visual-site-brand visual-site-brand-desktop" href="<?=e(app_url('index.php'))?>"><img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>"></a><div class="visual-site-brand-mobile"><?php nmm_render_mobile_brand();?></div><button type="button" class="visual-site-menu-button" data-site-menu-toggle aria-expanded="false">Menu</button><nav class="visual-site-navigation visual-site-navigation-desktop"><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?></nav><nav class="visual-site-navigation visual-site-navigation-mobile" data-site-menu><?php if(!site_builder_render_menu_location('mobile','visual-menu')):?><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?><?php endif;?></nav></header><main><?php foreach($payload['sections']??[] as $section) echo site_builder_render_section($section);?></main><footer class="visual-site-footer"><div><?php if(!site_builder_render_menu_location('footer','visual-footer-menu')):?><span><?=e($footerText)?></span><?php endif;?></div></footer><script src="<?=e(app_url('assets/js/site-public.js?v=20260727-v61.3'))?>"></script><script src="<?=e(app_url('assets/js/music-player.js?v=20260727-v61'))?>"></script></body></html><?php
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

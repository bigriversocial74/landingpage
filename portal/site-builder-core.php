<?php
/* North Mountain Media build: 20260727-visual-site-builder-v61 */
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

function site_builder_templates(): array
{
    $hero = static fn(string $headline,string $text,string $alignment='left'): array => [
        'id'=>site_builder_id('hero'),'type'=>'hero','settings'=>[
            'eyebrow'=>'North Mountain Media','headline'=>$headline,'text'=>$text,'alignment'=>$alignment,
        ],'blocks'=>[
            ['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>'Start a project','url'=>'intake.php','style'=>'primary']],
            ['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>'View portfolio','url'=>'workspace.php','style'=>'secondary']],
        ],
    ];
    $features = [
        'id'=>site_builder_id('features'),'type'=>'features','settings'=>[
            'eyebrow'=>'What we build','headline'=>'A clearer path from concept to working system.','text'=>'Choose a focused starting point and connect the workflows required to deliver it.',
        ],'blocks'=>[
            ['id'=>site_builder_id('feature'),'type'=>'feature','settings'=>['title'=>'Strategy and planning','text'=>'Translate goals into a clear launch path.']],
            ['id'=>site_builder_id('feature'),'type'=>'feature','settings'=>['title'=>'Connected execution','text'=>'Bring content, CRM, commerce, and operations together.']],
            ['id'=>site_builder_id('feature'),'type'=>'feature','settings'=>['title'=>'Measurable progress','text'=>'Track activity, engagement, and conversion.']],
        ],
    ];
    $cta = ['id'=>site_builder_id('cta'),'type'=>'cta','settings'=>['eyebrow'=>'Ready to build','headline'=>'Turn the next idea into a connected working system.','text'=>'Start with a practical conversation about the goal and next step.'],'blocks'=>[
        ['id'=>site_builder_id('button'),'type'=>'button','settings'=>['label'=>'Start a project','url'=>'intake.php','style'=>'primary']],
    ]];
    return [
        'split'=>['version'=>1,'theme'=>['contentWidth'=>'1180','primary'=>'#152638','accent'=>'#0b8588','radius'=>'18'],'sections'=>[$hero('Connected digital systems for ambitious ideas.','Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.'),$features,$cta]],
        'centered'=>['version'=>1,'theme'=>['contentWidth'=>'1080','primary'=>'#101b2c','accent'=>'#0b8588','radius'=>'24'],'sections'=>[$hero('Build a clearer digital future.','A centered, confident presentation for services, products, media, and conversion.','center'),['id'=>site_builder_id('media'),'type'=>'media','settings'=>['headline'=>'Show the work. Explain the value.','text'=>'Use this section for a wide image, video, or featured project.','image'=>''],'blocks'=>[]],$features,$cta]],
        'editorial'=>['version'=>1,'theme'=>['contentWidth'=>'1120','primary'=>'#30251f','accent'=>'#a45c32','radius'=>'10'],'sections'=>[$hero('Ideas deserve a strong point of view.','An editorial layout for storytelling, experience, proof, and perspective.'),['id'=>site_builder_id('content'),'type'=>'content','settings'=>['eyebrow'=>'The approach','headline'=>'Useful systems begin with clear thinking.','text'=>'Explain the challenge, the insight, and the practical path forward.'],'blocks'=>[]],$features,$cta]],
        'showcase'=>['version'=>1,'theme'=>['contentWidth'=>'1240','primary'=>'#0c1118','accent'=>'#55d6be','radius'=>'20'],'sections'=>[$hero('One platform. Many connected experiences.','A high-contrast product showcase for digital systems, media, and automated commerce.'),['id'=>site_builder_id('stats'),'type'=>'columns','settings'=>['headline'=>'Built to connect the full experience.','text'=>'Show measurable outcomes and platform capabilities.'],'blocks'=>[
            ['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'01','label'=>'Unified experience']],
            ['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'02','label'=>'Measurable activity']],
            ['id'=>site_builder_id('stat'),'type'=>'stat','settings'=>['value'=>'03','label'=>'Practical automation']],
        ]],$cta]],
        'blank'=>['version'=>1,'theme'=>['contentWidth'=>'1180','primary'=>'#152638','accent'=>'#0b8588','radius'=>'18'],'sections'=>[]],
    ];
}

function site_builder_section_library(): array
{
    return [
        'hero'=>['label'=>'Hero','category'=>'Layout','description'=>'Headline, copy, image, and actions.'],
        'content'=>['label'=>'Content','category'=>'Layout','description'=>'Editorial text section.'],
        'features'=>['label'=>'Feature grid','category'=>'Layout','description'=>'Cards for services or capabilities.'],
        'columns'=>['label'=>'Columns','category'=>'Layout','description'=>'Flexible cards, statistics, or content.'],
        'media'=>['label'=>'Media feature','category'=>'Media','description'=>'Wide image, video, or visual story.'],
        'portfolio'=>['label'=>'Portfolio projects','category'=>'Dynamic','description'=>'Published portfolio content.'],
        'music'=>['label'=>'Music releases','category'=>'Dynamic','description'=>'Albums, songs, and listening activity.'],
        'events'=>['label'=>'Upcoming events','category'=>'Dynamic','description'=>'Published upcoming events.'],
        'contact'=>['label'=>'Contact form','category'=>'Conversion','description'=>'CRM-connected inquiry form.'],
        'cta'=>['label'=>'Call to action','category'=>'Conversion','description'=>'Focused conversion section.'],
        'microgifter'=>['label'=>'Microgifter offer','category'=>'Microgifter','description'=>'Adapter-powered offer or campaign.'],
        'spacer'=>['label'=>'Spacer / divider','category'=>'Utility','description'=>'Visual space between sections.'],
    ];
}

function site_builder_block_library(): array
{
    return [
        'heading'=>['label'=>'Heading','category'=>'Content'],
        'text'=>['label'=>'Paragraph','category'=>'Content'],
        'image'=>['label'=>'Image','category'=>'Media'],
        'button'=>['label'=>'Button','category'=>'Conversion'],
        'feature'=>['label'=>'Feature card','category'=>'Content'],
        'stat'=>['label'=>'Statistic','category'=>'Content'],
        'testimonial'=>['label'=>'Testimonial','category'=>'Content'],
        'audio'=>['label'=>'Audio / track','category'=>'Music'],
        'music_track'=>['label'=>'Music track','category'=>'Music'],
        'portfolio_project'=>['label'=>'Portfolio project','category'=>'Dynamic'],
        'event_list'=>['label'=>'Event list','category'=>'Dynamic'],
        'contact_form'=>['label'=>'Contact form','category'=>'Conversion'],
        'microgifter_offer'=>['label'=>'Microgifter offer','category'=>'Microgifter'],
        'divider'=>['label'=>'Divider','category'=>'Utility'],
        'spacer'=>['label'=>'Spacer','category'=>'Utility'],
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
    foreach(array_slice($settings,0,60,true) as $key=>$value){
        $key=preg_replace('/[^a-z0-9_-]/i','',substr((string)$key,0,64))??'';
        if($key==='') continue;
        if(is_bool($value)||is_int($value)||is_float($value)){$clean[$key]=$value;continue;}
        if(is_array($value)){$clean[$key]=array_slice(array_map(static fn($item)=>site_builder_clean_text($item,500),$value),0,30);continue;}
        $lowerKey=strtolower($key);$isUrl=str_ends_with($lowerKey,'url')||in_array($lowerKey,['image','video','audio','backgroundimage'],true);
        $clean[$key]=$isUrl?site_builder_clean_url($value):site_builder_clean_text($value,5000);
    }
    return $clean;
}

function site_builder_sanitize_payload(array $payload): array
{
    $sectionTypes=array_keys(site_builder_section_library());
    $blockTypes=array_keys(site_builder_block_library());
    $clean=['version'=>1,'theme'=>site_builder_sanitize_settings(is_array($payload['theme']??null)?$payload['theme']:[]),'sections'=>[]];
    foreach(array_slice(is_array($payload['sections']??null)?$payload['sections']:[],0,60) as $section){
        if(!is_array($section)) continue;
        $type=(string)($section['type']??'content');
        if(!in_array($type,$sectionTypes,true)) $type='content';
        $item=['id'=>site_builder_clean_text($section['id']??site_builder_id($type),80),'type'=>$type,'settings'=>site_builder_sanitize_settings(is_array($section['settings']??null)?$section['settings']:[]),'blocks'=>[]];
        foreach(array_slice(is_array($section['blocks']??null)?$section['blocks']:[],0,80) as $block){
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

function site_builder_render_block(array $block): string
{
    $type=(string)($block['type']??'text');$s=is_array($block['settings']??null)?$block['settings']:[];
    ob_start();
    if($type==='heading'){echo '<h3>'.e($s['text']??$s['title']??'Heading').'</h3>';}
    elseif($type==='text'){echo '<p>'.nl2br(e($s['text']??'Add supporting copy.')).'</p>';}
    elseif($type==='button'){echo site_builder_button((string)($s['label']??'Learn more'),(string)($s['url']??'#'),(string)($s['style']??'primary'));}
    elseif($type==='image'&&($s['url']??'')!==''){echo '<figure><img loading="lazy" src="'.e(nmm_public_link_url((string)$s['url'])).'" alt="'.e($s['alt']??'').'"></figure>';}
    elseif($type==='feature'){echo '<article class="site-feature-card"><h3>'.e($s['title']??'Feature').'</h3><p>'.e($s['text']??'').'</p></article>';}
    elseif($type==='stat'){echo '<article class="site-stat-card"><strong>'.e($s['value']??'0').'</strong><span>'.e($s['label']??'Metric').'</span></article>';}
    elseif($type==='testimonial'){echo '<blockquote><p>'.e($s['quote']??'Add a customer quote.').'</p><cite>'.e($s['name']??'Customer').'</cite></blockquote>';}
    elseif($type==='divider'){echo '<hr>';}
    elseif($type==='spacer'){echo '<div class="site-block-spacer" style="height:'.max(12,min(240,(int)($s['height']??48))).'px"></div>';}
    elseif($type==='contact_form'){echo site_builder_contact_form($s);}
    elseif($type==='music_track'){echo site_builder_music_track($s);}
    elseif($type==='portfolio_project'){echo site_builder_portfolio_project($s);}
    elseif($type==='event_list'){echo site_builder_event_list($s);}
    elseif($type==='microgifter_offer'){require_once __DIR__.'/microgifter-connectors.php';echo microgifter_render_offer_block($s);}
    elseif($type==='audio'&&($s['url']??'')!==''){echo '<audio controls preload="metadata" src="'.e(nmm_public_link_url((string)$s['url'])).'"></audio>';}
    return (string)ob_get_clean();
}

function site_builder_contact_form(array $s=[]): string
{
    ob_start();?><form class="site-contact-form" data-site-contact-form><div class="site-form-grid"><label><span>Name</span><input name="name" required maxlength="160"></label><label><span>Email</span><input type="email" name="email" required maxlength="190"></label><label><span>Phone</span><input name="phone" maxlength="60"></label><label><span>Company</span><input name="company" maxlength="190"></label><label class="full"><span>How can we help?</span><textarea name="message" required maxlength="8000" rows="5"></textarea></label><input class="site-honeypot" name="website" tabindex="-1" autocomplete="off"><input type="hidden" name="opportunity" value="<?=e($s['opportunity']??'Website inquiry')?>"></div><button class="site-block-button site-block-button-primary" type="submit"><?=e($s['buttonLabel']??'Send inquiry')?></button><p class="site-form-status" data-site-form-status></p></form><?php return (string)ob_get_clean();
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
    try{$q=$id>0?db()->prepare('SELECT id,title,slug,summary FROM portfolio_projects WHERE id=:id AND status="active" LIMIT 1'):null;if($q){$q->execute(['id'=>$id]);$p=$q->fetch();}else{$p=db()->query('SELECT id,title,slug,summary FROM portfolio_projects WHERE status="active" ORDER BY featured DESC,sort_order,id LIMIT 1')->fetch();}}catch(Throwable){$p=false;}
    if(!$p)return '<div class="site-dynamic-placeholder">Choose a published portfolio project.</div>';
    return '<article class="site-project-card"><span>Featured project</span><h3>'.e($p['title']).'</h3><p>'.e($p['summary']??'').'</p><a href="'.e(app_url('workspace.php#'.($p['slug']??'featured-project'))).'">View project</a></article>';
}

function site_builder_event_list(array $s): string
{
    try{$rows=db()->query('SELECT id,title,slug,start_at,location_name FROM calendar_events WHERE status="published" AND visibility="public" AND start_at>=UTC_TIMESTAMP() ORDER BY start_at LIMIT 3')->fetchAll();}catch(Throwable){$rows=[];}
    if(!$rows)return '<div class="site-dynamic-placeholder">No upcoming published events.</div>';
    $html='<div class="site-event-list">';foreach($rows as $event){$html.='<a href="'.e(app_url('event.php?slug='.$event['slug'])).'"><time>'.e(format_datetime($event['start_at'])).'</time><strong>'.e($event['title']).'</strong><span>'.e($event['location_name']??'').'</span></a>';}$html.='</div>';return $html;
}

function site_builder_render_section(array $section): string
{
    $type=(string)($section['type']??'content');$s=is_array($section['settings']??null)?$section['settings']:[];$blocks=is_array($section['blocks']??null)?$section['blocks']:[];
    if(!empty($s['hidden'])) return '';
    $alignCandidate=(string)($s['alignment']??'left');$align=in_array($alignCandidate,['left','center','right'],true)?$alignCandidate:'left';
    $classes=['site-builder-section','site-section-'.$type,'align-'.$align];
    foreach(['desktop','tablet','mobile'] as $device){if(!empty($s['hideOn'.ucfirst($device)]))$classes[]='hide-'.$device;}
    $styles=[];
    if(($s['backgroundColor']??'')!==''&&preg_match('/^#[0-9a-f]{3,8}$/i',(string)$s['backgroundColor']))$styles[]='background-color:'.$s['backgroundColor'];
    if(($s['backgroundImage']??'')!=='')$styles[]='background-image:linear-gradient(rgba(7,18,30,.30),rgba(7,18,30,.30)),url("'.e(nmm_public_link_url((string)$s['backgroundImage'])).'")';
    $paddingTop=max(0,min(240,(int)($s['paddingTop']??0)));$paddingBottom=max(0,min(240,(int)($s['paddingBottom']??0)));
    if($paddingTop>0)$styles[]='padding-top:'.$paddingTop.'px';if($paddingBottom>0)$styles[]='padding-bottom:'.$paddingBottom.'px';
    $style=$styles?' style="'.implode(';',$styles).'"':'';
    ob_start();?><section class="<?=e(implode(' ',$classes))?>" data-section-type="<?=e($type)?>"<?=$style?>><div class="site-section-inner"><?php if(($s['eyebrow']??'')!==''):?><p class="site-section-eyebrow"><?=e($s['eyebrow'])?></p><?php endif;?><?php if(($s['headline']??'')!==''):?><h2><?=e($s['headline'])?></h2><?php endif;?><?php if(($s['text']??'')!==''):?><p class="site-section-copy"><?=nl2br(e($s['text']))?></p><?php endif;?><?php if($type==='media'&&($s['image']??'')!==''):?><figure class="site-section-media"><img src="<?=e(nmm_public_link_url((string)$s['image']))?>" alt="<?=e($s['imageAlt']??'')?>"></figure><?php endif;?><?php if($type==='contact'&&(!$blocks)):?><?=site_builder_contact_form($s)?><?php elseif($type==='portfolio'&&(!$blocks)):?><?=site_builder_portfolio_project($s)?><?php elseif($type==='music'&&(!$blocks)):?><?=site_builder_music_track($s)?><?php elseif($type==='events'&&(!$blocks)):?><?=site_builder_event_list($s)?><?php elseif($type==='microgifter'&&(!$blocks)):?><?php require_once __DIR__.'/microgifter-connectors.php';echo microgifter_render_offer_block($s);?><?php endif;?><div class="site-section-blocks"><?php foreach($blocks as $block):?><div class="site-builder-block site-block-<?=e($block['type']??'text')?>"><?=site_builder_render_block($block)?></div><?php endforeach;?></div></div></section><?php return (string)ob_get_clean();
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
    $theme=$payload['theme']??[];$siteName=setting('site_name','North Mountain Media')?:'North Mountain Media';$title=(string)((($page['seo_title']??'')!=='')?$page['seo_title']:($page['title']??'Page'));$description=(string)($page['seo_description']??'');$keywords=(string)($page['seo_keywords']??'');$canonical=(string)($page['seo_canonical_url']??'');$social=(string)($page['seo_social_image']??'');
    if($canonical===''){ $base=rtrim(nmm_site_setting('seo_site_url'),'/'); if($base!=='')$canonical=$base.'/'.($page['slug']==='home'?'':('page.php?slug='.rawurlencode((string)$page['slug']))); }
    if($social==='')$social=nmm_site_media_url('social');
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="build-version" content="20260727-visual-site-builder-v61"><title><?=e($title)?> — <?=e($siteName)?></title><?php if($description!==''):?><meta name="description" content="<?=e($description)?>"><meta property="og:description" content="<?=e($description)?>"><?php endif;?><?php if($keywords!==''):?><meta name="keywords" content="<?=e($keywords)?>"><?php endif;?><meta name="robots" content="<?=$preview||!(bool)($page['seo_index_enabled']??1)?'noindex,nofollow':'index,follow'?>"><meta property="og:title" content="<?=e($title)?>"><meta property="og:type" content="website"><?php if($canonical!==''):?><link rel="canonical" href="<?=e($canonical)?>"><meta property="og:url" content="<?=e($canonical)?>"><?php endif;?><?php if($social!==''):?><meta property="og:image" content="<?=e(nmm_public_link_url($social))?>"><?php endif;?><link rel="stylesheet" href="<?=e(app_url('assets/css/site-builder-public.css?v=20260727-v61'))?>"><link rel="stylesheet" href="<?=e(app_url('assets/css/music-library.css?v=20260727-v61'))?>"><style>:root{--site-content-width:<?=max(720,min(1600,(int)($theme['contentWidth']??1180)))?>px;--site-primary:<?=e($theme['primary']??'#152638')?>;--site-accent:<?=e($theme['accent']??'#0b8588')?>;--site-radius:<?=max(0,min(48,(int)($theme['radius']??18)))?>px}</style></head><body class="visual-site-page"><header class="visual-site-header"><a class="visual-site-brand visual-site-brand-desktop" href="<?=e(app_url('index.php'))?>"><img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>"></a><div class="visual-site-brand-mobile"><?php nmm_render_mobile_brand();?></div><button type="button" class="visual-site-menu-button" data-site-menu-toggle aria-expanded="false">Menu</button><nav class="visual-site-navigation visual-site-navigation-desktop"><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?></nav><nav class="visual-site-navigation visual-site-navigation-mobile" data-site-menu><?php if(!site_builder_render_menu_location('mobile','visual-menu')):?><?php if(!site_builder_render_menu_location('header','visual-menu')):?><?=site_builder_fallback_menu()?><?php endif;?><?php endif;?></nav></header><?php if($preview):?><div class="site-preview-banner">Draft preview · <a href="<?=e(app_url('portal/site-builder.php?page='.(int)$page['id']))?>">Return to editor</a></div><?php endif;?><main><?php foreach($payload['sections']??[] as $section) echo site_builder_render_section($section);?></main><footer class="visual-site-footer"><div><?php if(!site_builder_render_menu_location('footer','visual-footer-menu')):?><span><?=e(nmm_site_setting('landing_footer_text','North Mountain Media · Phoenix, Arizona'))?></span><?php endif;?></div></footer><script src="<?=e(app_url('assets/js/site-public.js?v=20260727-v61'))?>"></script><script src="<?=e(app_url('assets/js/music-player.js?v=20260727-v61'))?>"></script></body></html><?php
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

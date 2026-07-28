<?php
/* North Mountain Media build: 20260727-visual-layout-system-v61.8 */
declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require_once __DIR__.'/site-builder-core.php';

$user=require_role('admin');
if(!is_post())json_response(['ok'=>false,'message'=>'Method not allowed.'],405);
if(!same_origin_request())json_response(['ok'=>false,'message'=>'Invalid request origin.'],403);
verify_csrf();
if(!site_builder_schema_available())json_response(['ok'=>false,'message'=>'Import database/visual_site_builder_v61.sql first.'],409);

$data=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($data))$data=$_POST;
$action=trim((string)($data['action']??''));

$cleanPageInput=static function(array $data,array $page):array{
    $payload=site_builder_sanitize_payload(is_array($data['payload']??null)?$data['payload']:[]);
    $title=site_builder_clean_text($data['title']??$page['title'],190);
    $slug=slugify(site_builder_clean_text($data['slug']??$page['slug'],190));
    if($title===''||$slug==='')throw new RuntimeException('Page title and slug are required.');
    $dupe=db()->prepare('SELECT id FROM site_pages WHERE slug=:slug AND id<>:id LIMIT 1');
    $dupe->execute(['slug'=>$slug,'id'=>(int)$page['id']]);
    if($dupe->fetchColumn())throw new RuntimeException('That page slug is already in use.');
    return [
        'payload'=>$payload,'title'=>$title,'slug'=>$slug,
        'seo_title'=>site_builder_clean_text($data['seo_title']??'',190),
        'seo_description'=>site_builder_clean_text($data['seo_description']??'',500),
        'seo_keywords'=>site_builder_clean_text($data['seo_keywords']??'',500),
        'seo_canonical'=>site_builder_clean_url($data['seo_canonical_url']??''),
        'seo_social'=>site_builder_clean_url($data['seo_social_image']??''),
        'seo_index'=>!empty($data['seo_index_enabled'])?1:0,
        'template'=>site_builder_clean_text($data['template_key']??$page['template_key'],80),
    ];
};

try{
    if($action==='create_page'){
        $title=site_builder_clean_text($data['title']??'Untitled page',190);
        $slug=slugify(site_builder_clean_text($data['slug']??$title,190));
        $type=($data['page_type']??'custom')==='landing'?'landing':'custom';
        $template=(string)($data['template_key']??'blank');
        $templates=site_builder_templates();
        if(!isset($templates[$template]))$template='blank';
        if($title===''||$slug==='')throw new RuntimeException('Page title and slug are required.');
        $exists=db()->prepare('SELECT id FROM site_pages WHERE slug=:slug LIMIT 1');
        $exists->execute(['slug'=>$slug]);
        if($exists->fetchColumn())throw new RuntimeException('That page slug is already in use.');
        db()->prepare('INSERT INTO site_pages(title,slug,page_type,status,template_key,draft_json,created_by,updated_by) VALUES(:title,:slug,:type,"draft",:template,:payload,:user_id,:user_id)')
            ->execute(['title'=>$title,'slug'=>$slug,'type'=>$type,'template'=>$template,'payload'=>site_builder_encode($templates[$template]),'user_id'=>(int)$user['id']]);
        $id=(int)db()->lastInsertId();
        site_builder_save_revision($id,$templates[$template],'draft',(int)$user['id'],'Page created');
        log_activity('site_page_created','site_page',$id);
        json_response(['ok'=>true,'page_id'=>$id,'redirect'=>app_url('portal/site-builder.php?page='.$id)]);
    }

    if(in_array($action,['save_page','publish_page','autosave_page'],true)){
        $id=max(0,(int)($data['page_id']??0));
        $page=site_builder_page($id);
        if(!$page)throw new RuntimeException('Page not found.');
        $input=$cleanPageInput($data,$page);
        $pdo=db();
        $pdo->beginTransaction();
        try{
            $params=[
                'title'=>$input['title'],'slug'=>$input['slug'],'template'=>$input['template'],
                'payload'=>site_builder_encode($input['payload']),
                'seo_title'=>$input['seo_title']?:null,'seo_description'=>$input['seo_description']?:null,
                'seo_keywords'=>$input['seo_keywords']?:null,'seo_canonical'=>$input['seo_canonical']?:null,
                'seo_social'=>$input['seo_social']?:null,'seo_index'=>$input['seo_index'],
                'user_id'=>(int)$user['id'],'id'=>$id,
            ];
            if($action==='publish_page'){
                $pdo->prepare('UPDATE site_pages SET title=:title,slug=:slug,template_key=:template,draft_json=:payload,published_json=:payload,status="published",seo_title=:seo_title,seo_description=:seo_description,seo_keywords=:seo_keywords,seo_canonical_url=:seo_canonical,seo_social_image=:seo_social,seo_index_enabled=:seo_index,updated_by=:user_id,published_by=:user_id,published_at=UTC_TIMESTAMP() WHERE id=:id')->execute($params);
                $revision=site_builder_save_revision($id,$input['payload'],'publish',(int)$user['id'],'Published from visual editor');
            }else{
                $pdo->prepare('UPDATE site_pages SET title=:title,slug=:slug,template_key=:template,draft_json=:payload,seo_title=:seo_title,seo_description=:seo_description,seo_keywords=:seo_keywords,seo_canonical_url=:seo_canonical,seo_social_image=:seo_social,seo_index_enabled=:seo_index,updated_by=:user_id WHERE id=:id')->execute($params);
                $revision=$action==='save_page'?site_builder_save_revision($id,$input['payload'],'draft',(int)$user['id'],'Saved from visual editor'):null;
            }
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        if($action!=='autosave_page')log_activity($action==='publish_page'?'site_page_published':'site_page_saved','site_page',$id);
        json_response([
            'ok'=>true,
            'message'=>$action==='publish_page'?'Page published.':($action==='autosave_page'?'Autosaved.':'Draft saved.'),
            'published'=>$action==='publish_page',
            'revision_number'=>$revision??null,
            'saved_at'=>gmdate('c'),
            'preview_url'=>app_url('page-preview.php?id='.$id),
        ]);
    }

    if($action==='save_named_revision'){
        $id=max(0,(int)($data['page_id']??0));
        $page=site_builder_page($id);
        if(!$page)throw new RuntimeException('Page not found.');
        $payload=site_builder_sanitize_payload(is_array($data['payload']??null)?$data['payload']:[]);
        $note=site_builder_clean_text($data['note']??'Named snapshot',190);
        if($note==='')$note='Named snapshot';
        $pdo=db();$pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE site_pages SET draft_json=:payload,updated_by=:user_id WHERE id=:id')->execute(['payload'=>site_builder_encode($payload),'user_id'=>(int)$user['id'],'id'=>$id]);
            $number=site_builder_save_revision($id,$payload,'draft',(int)$user['id'],$note);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        json_response(['ok'=>true,'message'=>'Named snapshot saved.','revision_number'=>$number,'note'=>$note]);
    }

    if($action==='restore_revision'){
        $pageId=max(0,(int)($data['page_id']??0));
        $revisionId=max(0,(int)($data['revision_id']??0));
        $s=db()->prepare('SELECT * FROM site_page_revisions WHERE id=:id AND page_id=:page_id LIMIT 1');
        $s->execute(['id'=>$revisionId,'page_id'=>$pageId]);
        $revision=$s->fetch();
        if(!$revision)throw new RuntimeException('Revision not found.');
        $payload=site_builder_decode((string)$revision['payload_json']);
        $pdo=db();$pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE site_pages SET draft_json=:payload,updated_by=:user_id WHERE id=:page_id')->execute(['payload'=>site_builder_encode($payload),'user_id'=>(int)$user['id'],'page_id'=>$pageId]);
            site_builder_save_revision($pageId,$payload,'restore',(int)$user['id'],'Restored revision '.$revision['revision_number']);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        json_response(['ok'=>true,'redirect'=>app_url('portal/site-builder.php?page='.$pageId)]);
    }

    if($action==='save_reusable_item'){
        $name=site_builder_clean_text($data['name']??'Saved item',190);
        $item=is_array($data['item']??null)?$data['item']:[];
        $kind=($data['kind']??'block')==='section'?'section':'block';
        $type=site_builder_clean_text($item['type']??($kind==='section'?'content':'text'),80);
        $payload=$kind==='section'
            ?(site_builder_sanitize_payload(['sections'=>[$item]])['sections'][0]??[])
            :(site_builder_sanitize_payload(['sections'=>[['type'=>'content','blocks'=>[$item]]]])['sections'][0]['blocks'][0]??[]);
        if(!$payload)throw new RuntimeException('The reusable item is invalid.');
        db()->prepare('INSERT INTO site_saved_blocks(name,category,block_type,payload_json,created_by,updated_by) VALUES(:name,:category,:type,:payload,:user_id,:user_id)')
            ->execute(['name'=>$name,'category'=>$kind,'type'=>$type,'payload'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'user_id'=>(int)$user['id']]);
        json_response(['ok'=>true,'message'=>'Reusable '.$kind.' saved.']);
    }

    if($action==='save_global_section'){
        $name=site_builder_clean_text($data['name']??'Global section',190);
        $globalId=max(0,(int)($data['global_id']??0));
        $item=is_array($data['item']??null)?$data['item']:[];
        $section=site_builder_sanitize_payload(['sections'=>[$item]])['sections'][0]??null;
        if(!$section)throw new RuntimeException('The global section is invalid.');
        $pdo=db();$pdo->beginTransaction();
        try{
            if($globalId>0){
                $check=$pdo->prepare('SELECT id FROM site_saved_blocks WHERE id=:id AND category="global_section" LIMIT 1');
                $check->execute(['id'=>$globalId]);
                if(!$check->fetchColumn())throw new RuntimeException('Global section not found.');
            }else{
                $pdo->prepare('INSERT INTO site_saved_blocks(name,category,block_type,payload_json,created_by,updated_by) VALUES(:name,"global_section",:type,"{}",:user_id,:user_id)')
                    ->execute(['name'=>$name,'type'=>(string)($section['type']??'content'),'user_id'=>(int)$user['id']]);
                $globalId=(int)$pdo->lastInsertId();
            }
            $section['settings']=is_array($section['settings']??null)?$section['settings']:[];
            $section['settings']['globalSectionId']=$globalId;
            $section['settings']['globalSectionName']=$name;
            $payload=json_encode($section,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $pdo->prepare('UPDATE site_saved_blocks SET name=:name,block_type=:type,payload_json=:payload,updated_by=:user_id WHERE id=:id AND category="global_section"')
                ->execute(['name'=>$name,'type'=>(string)($section['type']??'content'),'payload'=>$payload,'user_id'=>(int)$user['id'],'id'=>$globalId]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        json_response(['ok'=>true,'message'=>'Global section saved.','global_id'=>$globalId,'name'=>$name,'item'=>$section]);
    }

    if($action==='archive_page'){
        $id=max(0,(int)($data['page_id']??0));
        $page=site_builder_page($id);
        if(!$page)throw new RuntimeException('Page not found.');
        if(($page['slug']??'')==='home')throw new RuntimeException('The Home page cannot be archived.');
        db()->prepare('UPDATE site_pages SET status="archived",updated_by=:user_id WHERE id=:id')->execute(['user_id'=>(int)$user['id'],'id'=>$id]);
        log_activity('site_page_archived','site_page',$id);
        json_response(['ok'=>true,'redirect'=>app_url('portal/site-builder.php')]);
    }

    throw new RuntimeException('Unknown editor action.');
}catch(Throwable $e){
    json_response(['ok'=>false,'message'=>$e->getMessage()],422);
}

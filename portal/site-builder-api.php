<?php
/* North Mountain Media build: 20260727-visual-site-builder-v61 */
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/site-builder-core.php';
$user=require_role('admin');
if(!is_post()) json_response(['ok'=>false,'message'=>'Method not allowed.'],405);
if(!same_origin_request()) json_response(['ok'=>false,'message'=>'Invalid request origin.'],403);
verify_csrf();
if(!site_builder_schema_available()) json_response(['ok'=>false,'message'=>'Import database/visual_site_builder_v61.sql first.'],409);
$data=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($data))$data=$_POST;
$action=trim((string)($data['action']??''));
try{
    if($action==='create_page'){
        $title=site_builder_clean_text($data['title']??'Untitled page',190);$slug=slugify(site_builder_clean_text($data['slug']??$title,190));$type=($data['page_type']??'custom')==='landing'?'landing':'custom';$template=(string)($data['template_key']??'blank');$templates=site_builder_templates();if(!isset($templates[$template]))$template='blank';if($title===''||$slug==='')throw new RuntimeException('Page title and slug are required.');
        $exists=db()->prepare('SELECT id FROM site_pages WHERE slug=:slug LIMIT 1');$exists->execute(['slug'=>$slug]);if($exists->fetchColumn())throw new RuntimeException('That page slug is already in use.');
        db()->prepare('INSERT INTO site_pages(title,slug,page_type,status,template_key,draft_json,created_by,updated_by) VALUES(:title,:slug,:type,"draft",:template,:payload,:user_id,:user_id)')->execute(['title'=>$title,'slug'=>$slug,'type'=>$type,'template'=>$template,'payload'=>site_builder_encode($templates[$template]),'user_id'=>(int)$user['id']]);$id=(int)db()->lastInsertId();
        site_builder_save_revision($id,$templates[$template],'draft',(int)$user['id'],'Page created');log_activity('site_page_created','site_page',$id);json_response(['ok'=>true,'page_id'=>$id,'redirect'=>app_url('portal/site-builder.php?page='.$id)]);
    }
    if($action==='save_page'||$action==='publish_page'){
        $id=max(0,(int)($data['page_id']??0));$page=site_builder_page($id);if(!$page)throw new RuntimeException('Page not found.');$payload=site_builder_sanitize_payload(is_array($data['payload']??null)?$data['payload']:[]);$title=site_builder_clean_text($data['title']??$page['title'],190);$slug=slugify(site_builder_clean_text($data['slug']??$page['slug'],190));if($title===''||$slug==='')throw new RuntimeException('Page title and slug are required.');
        $dupe=db()->prepare('SELECT id FROM site_pages WHERE slug=:slug AND id<>:id LIMIT 1');$dupe->execute(['slug'=>$slug,'id'=>$id]);if($dupe->fetchColumn())throw new RuntimeException('That page slug is already in use.');
        $seoTitle=site_builder_clean_text($data['seo_title']??'',190);$seoDescription=site_builder_clean_text($data['seo_description']??'',500);$seoKeywords=site_builder_clean_text($data['seo_keywords']??'',500);$seoCanonical=site_builder_clean_url($data['seo_canonical_url']??'');$seoSocial=site_builder_clean_url($data['seo_social_image']??'');$seoIndex=!empty($data['seo_index_enabled'])?1:0;$template=site_builder_clean_text($data['template_key']??$page['template_key'],80);
        $pdo=db();$pdo->beginTransaction();
        try{
            if($action==='publish_page'){
                $pdo->prepare('UPDATE site_pages SET title=:title,slug=:slug,template_key=:template,draft_json=:payload,published_json=:payload,status="published",seo_title=:seo_title,seo_description=:seo_description,seo_keywords=:seo_keywords,seo_canonical_url=:seo_canonical,seo_social_image=:seo_social,seo_index_enabled=:seo_index,updated_by=:user_id,published_by=:user_id,published_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['title'=>$title,'slug'=>$slug,'template'=>$template,'payload'=>site_builder_encode($payload),'seo_title'=>$seoTitle?:null,'seo_description'=>$seoDescription?:null,'seo_keywords'=>$seoKeywords?:null,'seo_canonical'=>$seoCanonical?:null,'seo_social'=>$seoSocial?:null,'seo_index'=>$seoIndex,'user_id'=>(int)$user['id'],'id'=>$id]);site_builder_save_revision($id,$payload,'publish',(int)$user['id'],'Published from visual editor');
            }else{
                $pdo->prepare('UPDATE site_pages SET title=:title,slug=:slug,template_key=:template,draft_json=:payload,seo_title=:seo_title,seo_description=:seo_description,seo_keywords=:seo_keywords,seo_canonical_url=:seo_canonical,seo_social_image=:seo_social,seo_index_enabled=:seo_index,updated_by=:user_id WHERE id=:id')->execute(['title'=>$title,'slug'=>$slug,'template'=>$template,'payload'=>site_builder_encode($payload),'seo_title'=>$seoTitle?:null,'seo_description'=>$seoDescription?:null,'seo_keywords'=>$seoKeywords?:null,'seo_canonical'=>$seoCanonical?:null,'seo_social'=>$seoSocial?:null,'seo_index'=>$seoIndex,'user_id'=>(int)$user['id'],'id'=>$id]);site_builder_save_revision($id,$payload,'draft',(int)$user['id'],'Saved from visual editor');
            }$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        log_activity($action==='publish_page'?'site_page_published':'site_page_saved','site_page',$id);json_response(['ok'=>true,'message'=>$action==='publish_page'?'Page published.':'Draft saved.','published'=>$action==='publish_page','preview_url'=>app_url('page-preview.php?id='.$id)]);
    }
    if($action==='restore_revision'){
        $pageId=max(0,(int)($data['page_id']??0));$revisionId=max(0,(int)($data['revision_id']??0));$s=db()->prepare('SELECT * FROM site_page_revisions WHERE id=:id AND page_id=:page_id LIMIT 1');$s->execute(['id'=>$revisionId,'page_id'=>$pageId]);$revision=$s->fetch();if(!$revision)throw new RuntimeException('Revision not found.');$payload=site_builder_decode((string)$revision['payload_json']);$pdo=db();$pdo->beginTransaction();try{$pdo->prepare('UPDATE site_pages SET draft_json=:payload,updated_by=:user_id WHERE id=:page_id')->execute(['payload'=>site_builder_encode($payload),'user_id'=>(int)$user['id'],'page_id'=>$pageId]);site_builder_save_revision($pageId,$payload,'restore',(int)$user['id'],'Restored revision '.$revision['revision_number']);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}json_response(['ok'=>true,'redirect'=>app_url('portal/site-builder.php?page='.$pageId)]);
    }
    if($action==='save_reusable_item'){
        $name=site_builder_clean_text($data['name']??'Saved item',190);$item=is_array($data['item']??null)?$data['item']:[];$kind=($data['kind']??'block')==='section'?'section':'block';$type=site_builder_clean_text($item['type']??($kind==='section'?'content':'text'),80);$payload=$kind==='section'?site_builder_sanitize_payload(['sections'=>[$item]])['sections'][0]??[]:(site_builder_sanitize_payload(['sections'=>[['type'=>'content','blocks'=>[$item]]]])['sections'][0]['blocks'][0]??[]);if(!$payload)throw new RuntimeException('The reusable item is invalid.');db()->prepare('INSERT INTO site_saved_blocks(name,category,block_type,payload_json,created_by,updated_by) VALUES(:name,:category,:type,:payload,:user_id,:user_id)')->execute(['name'=>$name,'category'=>$kind,'type'=>$type,'payload'=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'user_id'=>(int)$user['id']]);json_response(['ok'=>true,'message'=>'Reusable '.($kind==='section'?'section':'block').' saved.']);
    }
    if($action==='archive_page'){
        $id=max(0,(int)($data['page_id']??0));$page=site_builder_page($id);if(!$page)throw new RuntimeException('Page not found.');if(($page['slug']??'')==='home')throw new RuntimeException('The Home page cannot be archived.');db()->prepare('UPDATE site_pages SET status="archived",updated_by=:user_id WHERE id=:id')->execute(['user_id'=>(int)$user['id'],'id'=>$id]);log_activity('site_page_archived','site_page',$id);json_response(['ok'=>true,'redirect'=>app_url('portal/site-builder.php')]);
    }
    throw new RuntimeException('Unknown editor action.');
}catch(Throwable $e){json_response(['ok'=>false,'message'=>$e->getMessage()],422);}

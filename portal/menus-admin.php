<?php
/* North Mountain Media build: 20260727-visual-site-builder-v61 */
declare(strict_types=1);
require_once __DIR__.'/site-builder-core.php';

function site_menu_handle_admin_action(string $action,array $user): bool
{
    if(!in_array($action,['create_site_menu','save_site_menu','delete_site_menu'],true))return false;
    if(!site_builder_schema_available())throw new RuntimeException('Import database/visual_site_builder_v61.sql first.');
    if($action==='create_site_menu'){
        $name=site_builder_clean_text(input('menu_name'),190);$slug=slugify(input('menu_slug')?:$name);if($name===''||$slug==='')throw new RuntimeException('Enter a menu name.');
        $exists=db()->prepare('SELECT id FROM site_menus WHERE slug=:slug LIMIT 1');$exists->execute(['slug'=>$slug]);if($exists->fetchColumn())throw new RuntimeException('That menu slug is already in use.');
        db()->prepare('INSERT INTO site_menus(name,slug,status,created_by,updated_by) VALUES(:name,:slug,"active",:user_id,:user_id)')->execute(['name'=>$name,'slug'=>$slug,'user_id'=>(int)$user['id']]);$id=(int)db()->lastInsertId();flash('success','Navigation menu created.');redirect('portal/admin.php?view=menus&menu='.$id);
    }
    $menuId=int_input('menu_id');$menu=site_builder_menu($menuId);if(!$menu)throw new RuntimeException('Menu not found.');
    if($action==='delete_site_menu'){
        db()->prepare('DELETE FROM site_menus WHERE id=:id')->execute(['id'=>$menuId]);flash('success','Navigation menu deleted.');redirect('portal/admin.php?view=menus');
    }
    $name=site_builder_clean_text(input('menu_name'),190);$slug=slugify(input('menu_slug')?:$name);$status=input('menu_status')==='inactive'?'inactive':'active';if($name===''||$slug==='')throw new RuntimeException('Enter a menu name and slug.');
    $items=json_decode((string)($_POST['menu_items_json']??'[]'),true);if(!is_array($items))throw new RuntimeException('The menu item structure is invalid.');
    $locations=['header','mobile','sidebar','footer'];$pdo=db();$pdo->beginTransaction();
    try{
        $pdo->prepare('UPDATE site_menus SET name=:name,slug=:slug,status=:status,updated_by=:user_id WHERE id=:id')->execute(['name'=>$name,'slug'=>$slug,'status'=>$status,'user_id'=>(int)$user['id'],'id'=>$menuId]);
        $pdo->prepare('DELETE FROM site_menu_items WHERE menu_id=:menu_id')->execute(['menu_id'=>$menuId]);
        $lastAtDepth=[];$statement=$pdo->prepare('INSERT INTO site_menu_items(menu_id,parent_id,item_type,label,url,module_key,page_id,target,css_class,description,sort_order) VALUES(:menu_id,:parent_id,:item_type,:label,:url,:module_key,:page_id,:target,:css_class,:description,:sort_order)');
        foreach(array_slice($items,0,150) as $index=>$item){if(!is_array($item))continue;$depth=max(0,min(4,(int)($item['depth']??0)));$type=in_array(($item['item_type']??''),['module','page','custom'],true)?$item['item_type']:'custom';$label=site_builder_clean_text($item['label']??'',190);if($label==='')continue;$parentId=$depth>0?($lastAtDepth[$depth-1]??null):null;$url=$type==='custom'?site_builder_clean_url($item['url']??''):null;$moduleKey=$type==='module'?site_builder_clean_text($item['module_key']??'',80):null;$pageId=$type==='page'?max(0,(int)($item['page_id']??0)):null;$target=($item['target']??'')==='_blank'?'_blank':'_self';$statement->execute(['menu_id'=>$menuId,'parent_id'=>$parentId,'item_type'=>$type,'label'=>$label,'url'=>$url?:null,'module_key'=>$moduleKey?:null,'page_id'=>$pageId?:null,'target'=>$target,'css_class'=>site_builder_clean_text($item['css_class']??'',190)?:null,'description'=>site_builder_clean_text($item['description']??'',500)?:null,'sort_order'=>($index+1)*10]);$lastAtDepth[$depth]=(int)$pdo->lastInsertId();foreach(array_keys($lastAtDepth) as $key)if($key>$depth)unset($lastAtDepth[$key]);}
        $setting=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(:key,:value) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($locations as $location){$key='menu_location_'.$location;if(isset($_POST['location_'.$location])){$setting->execute(['key'=>$key,'value'=>$slug]);}elseif(nmm_site_setting($key)===$menu['slug']){$setting->execute(['key'=>$key,'value'=>'']);}}
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    log_activity('site_menu_updated','site_menu',$menuId);flash('success','Navigation menu and locations saved.');redirect('portal/admin.php?view=menus&menu='.$menuId);
}

function site_menu_render_admin(array $user): void
{
    if(!site_builder_schema_available()){echo '<section class="panel"><div class="panel-body"><h2>Navigation migration required</h2><p>Import <code>database/visual_site_builder_v61.sql</code>.</p></div></section>';return;}
    $menus=site_builder_menus();$menuId=max(0,query_int('menu',(int)($menus[0]['id']??0)));$menu=site_builder_menu($menuId)??($menus[0]??null);$items=$menu?site_builder_menu_items((int)$menu['id']):[];$pages=array_values(array_filter(site_builder_pages(),static fn($page)=>($page['status']??'')==='published'));$modules=site_builder_module_links();$locations=['header'=>'Desktop header','mobile'=>'Mobile menu','sidebar'=>'Public sidebar','footer'=>'Footer'];
    $payload=[];$depthById=[];foreach($items as $item){$parent=(int)($item['parent_id']??0);$depth=$parent>0?(($depthById[$parent]??0)+1):0;$depthById[(int)$item['id']]=$depth;$payload[]=['id'=>(int)$item['id'],'depth'=>$depth,'item_type'=>$item['item_type'],'label'=>$item['label'],'url'=>$item['url']??'','module_key'=>$item['module_key']??'','page_id'=>(int)($item['page_id']??0),'target'=>$item['target'],'css_class'=>$item['css_class']??'','description'=>$item['description']??''];}
    ?>
<div class="menu-manager-toolbar"><div><span>Visual navigation manager</span><h2>Menus</h2><p>Create WordPress-style navigation structures with nested dropdown items and menu locations.</p></div><form method="post" class="menu-create-form"><?=csrf_field()?><input type="hidden" name="action" value="create_site_menu"><input name="menu_name" placeholder="New menu name" required><button class="button button-primary">Create menu</button></form></div>
<?php if(!$menu):?><section class="panel"><div class="panel-body"><p>Create the first navigation menu.</p></div></section><?php return;endif;?>
<div class="menu-manager-layout" data-menu-manager>
<aside class="menu-source-column">
<section class="panel menu-source-panel"><header><h3>Pages</h3></header><div><?php foreach($pages as $page):?><label><input type="checkbox" data-menu-source data-item-type="page" data-page-id="<?=$page['id']?>" data-label="<?=e($page['title'])?>"><span><?=e($page['title'])?></span><small><?=e($page['slug'])?></small></label><?php endforeach;?></div><button type="button" data-add-selected>Add selected to menu</button></section>
<section class="panel menu-source-panel"><header><h3>Modules</h3></header><div><?php foreach($modules as $key=>[$label,$url]):?><label><input type="checkbox" data-menu-source data-item-type="module" data-module-key="<?=e($key)?>" data-label="<?=e($label)?>"><span><?=e($label)?></span><small><?=e($url)?></small></label><?php endforeach;?></div><button type="button" data-add-selected>Add selected to menu</button></section>
<section class="panel menu-source-panel"><header><h3>Custom link</h3></header><label>URL<input data-custom-url placeholder="https:// or site path"></label><label>Link text<input data-custom-label placeholder="Link label"></label><button type="button" data-add-custom>Add to menu</button></section>
</aside>
<form method="post" class="panel menu-editor-panel" data-menu-form><?=csrf_field()?><input type="hidden" name="action" value="save_site_menu"><input type="hidden" name="menu_id" value="<?=$menu['id']?>"><input type="hidden" name="menu_items_json" data-menu-json value="<?=e(json_encode($payload,JSON_UNESCAPED_SLASHES))?>">
<header class="menu-editor-header"><div><label>Select menu<select data-menu-selector><?php foreach($menus as $m):?><option value="<?=$m['id']?>" <?=$m['id']==$menu['id']?'selected':''?>><?=e($m['name'])?></option><?php endforeach;?></select></label></div><div class="menu-identity-fields"><label>Name<input name="menu_name" value="<?=e($menu['name'])?>" required></label><label>Slug<input name="menu_slug" value="<?=e($menu['slug'])?>" required></label><label>Status<select name="menu_status"><option value="active" <?=$menu['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$menu['status']==='inactive'?'selected':''?>>Inactive</option></select></label></div></header>
<div class="menu-editor-instructions">Drag items to reorder. Use indent and outdent to create dropdown levels. Expand an item to edit its label, destination behavior, CSS class, or description.</div><ol class="menu-item-list" data-menu-list></ol>
<section class="menu-location-settings"><h3>Display locations</h3><div><?php foreach($locations as $key=>$label):?><label><input type="checkbox" name="location_<?=e($key)?>" value="1" <?=nmm_site_setting('menu_location_'.$key)===$menu['slug']?'checked':''?>><span><?=e($label)?></span></label><?php endforeach;?></div></section>
<footer class="menu-editor-footer"><button class="button button-primary">Save menu</button></footer></form>
</div><form method="post" class="menu-delete-form" data-confirm="Permanently delete this menu and its navigation items?" data-confirm-title="Delete menu?" data-confirm-eyebrow="Navigation" data-confirm-action="Delete menu"><?=csrf_field()?><input type="hidden" name="action" value="delete_site_menu"><input type="hidden" name="menu_id" value="<?=$menu['id']?>"><button class="button button-danger">Delete menu</button></form>
<script>window.NMM_MENU_MANAGER=<?=json_encode(['items'=>$payload],JSON_UNESCAPED_SLASHES|JSON_HEX_TAG)?>;</script><script src="<?=e(app_url('assets/js/menu-manager.js?v=20260727-v61'))?>"></script>
<?php }

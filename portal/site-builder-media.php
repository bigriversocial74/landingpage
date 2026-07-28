<?php
/* North Mountain Media build: 20260727-visual-layout-system-v61.8 */
declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require_once __DIR__.'/site-builder-core.php';
require_role('admin');

if(!is_post()){
    json_response(['ok'=>true,'items'=>site_builder_media_library()]);
}
if(!same_origin_request())json_response(['ok'=>false,'message'=>'Invalid request origin.'],403);
verify_csrf();

$action=trim((string)($_POST['action']??'upload'));
if($action==='delete'){
    $name=basename((string)($_POST['stored_name']??''));
    if(!preg_match('/^builder-[a-f0-9]{36}\.(?:jpe?g|png|webp|gif)$/i',$name))json_response(['ok'=>false,'message'=>'Invalid media file.'],422);
    $path=NMM_ROOT.'/storage/site-builder-media/'.$name;
    if(is_file($path)&&!unlink($path))json_response(['ok'=>false,'message'=>'The media file could not be deleted.'],500);
    log_activity('site_builder_media_deleted','site_media',null,['stored_name'=>$name]);
    json_response(['ok'=>true,'message'=>'Media deleted.','items'=>site_builder_media_library()]);
}

$upload=$_FILES['image']??null;
if(!is_array($upload)||(int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_response(['ok'=>false,'message'=>'Choose an image to upload.'],422);
$tmp=(string)($upload['tmp_name']??'');
$size=(int)($upload['size']??0);
if($tmp===''||!is_uploaded_file($tmp)||$size<1||$size>12*1024*1024)json_response(['ok'=>false,'message'=>'Upload a valid image no larger than 12 MB.'],422);
$image=@getimagesize($tmp);
if(!is_array($image))json_response(['ok'=>false,'message'=>'The uploaded file is not a valid image.'],422);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'';
$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
if(!isset($extensions[$mime]))json_response(['ok'=>false,'message'=>'Images must be JPG, PNG, WebP, or GIF.'],422);
$directory=NMM_ROOT.'/storage/site-builder-media';
if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))json_response(['ok'=>false,'message'=>'The media directory could not be created.'],500);
$name='builder-'.bin2hex(random_bytes(18)).'.'.$extensions[$mime];
$destination=$directory.'/'.$name;
if(!move_uploaded_file($tmp,$destination))json_response(['ok'=>false,'message'=>'The image could not be stored.'],500);
@chmod($destination,0640);
log_activity('site_builder_media_uploaded','site_media',null,['stored_name'=>$name,'size_bytes'=>$size,'mime_type'=>$mime]);
$item=[
    'storedName'=>$name,
    'url'=>app_url('builder-media.php?file='.rawurlencode($name)),
    'width'=>(int)($image[0]??0),
    'height'=>(int)($image[1]??0),
    'mime'=>$mime,
    'size'=>$size,
    'modified'=>time(),
];
json_response(['ok'=>true,'url'=>$item['url'],'stored_name'=>$name,'item'=>$item,'items'=>site_builder_media_library()]);

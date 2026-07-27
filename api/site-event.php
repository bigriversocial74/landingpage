<?php
/* North Mountain Media build: 20260727-visual-site-builder-v61 */
declare(strict_types=1);
define('NMM_PUBLIC_PAGE',true);
require dirname(__DIR__).'/portal/bootstrap.php';
require_once dirname(__DIR__).'/portal/visitor-intelligence.php';
if(!is_post())json_response(['ok'=>false],405);
if(!same_origin_request())json_response(['ok'=>false],403);
$data=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($data))json_response(['ok'=>false],400);
$type=preg_replace('/[^a-z0-9_-]/i','',substr((string)($data['event_type']??''),0,64))??'';
if($type===''||!str_starts_with($type,'builder_'))json_response(['ok'=>false],422);
$label=substr(trim((string)($data['event_label']??'')),0,190);
$metadata=is_array($data['metadata']??null)?array_slice($data['metadata'],0,30,true):[];
$pagePath=visitor_intelligence_path($data['page_path']??'');
try{
    visitor_intelligence_track($type,['event_label'=>$label,'page_path'=>$pagePath,'target_url'=>$metadata['target_url']??null,'metadata'=>$metadata]);
}catch(Throwable $e){error_log('Site builder event failed: '.$e->getMessage());}
if($type==='builder_microgifter_offer_clicked'&&nmm_setting_bool('microgifter_analytics_sync_enabled',false)){
    try{
        require_once dirname(__DIR__).'/portal/microgifter-connectors.php';
        $result=microgifter_connector()->recordConversion([
            'event_type'=>'offer_clicked',
            'offer_id'=>substr((string)($metadata['offer_id']??''),0,190),
            'offer_title'=>$label,
            'amount'=>(string)($metadata['offer_price']??''),
            'target_url'=>substr((string)($metadata['target_url']??''),0,500),
            'page_path'=>$pagePath,
            'source'=>'north_mountain_media_site_builder',
        ]);
        if(empty($result['ok']))error_log('Microgifter analytics sync did not succeed: '.json_encode($result));
    }catch(Throwable $e){error_log('Microgifter analytics sync failed: '.$e->getMessage());}
}
json_response(['ok'=>true]);

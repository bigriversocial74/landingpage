<?php
/* North Mountain Media build: 20260727-visual-site-builder-v61 */
declare(strict_types=1);

interface MicrogifterConnector
{
    public function testConnection(): array;
    public function listOffers(array $filters=[]): array;
    public function createContact(array $contact): array;
    public function recordConversion(array $conversion): array;
}

function microgifter_secret_key(): string
{
    $security=nmm_config('security');$source=(string)($security['data_encryption_key']??$security['setup_token']??nmm_config('app')['setup_token']??'north-mountain-media');return hash('sha256',$source,true);
}
function microgifter_encrypt(string $value): string
{
    if($value==='')return'';$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',microgifter_secret_key(),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)return'';return base64_encode($iv.$tag.$cipher);
}
function microgifter_decrypt(string $value): string
{
    if($value==='')return'';$raw=base64_decode($value,true);if($raw===false||strlen($raw)<29)return'';$iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);$plain=openssl_decrypt($cipher,'aes-256-gcm',microgifter_secret_key(),OPENSSL_RAW_DATA,$iv,$tag);return$plain===false?'':$plain;
}

abstract class HttpMicrogifterConnector implements MicrogifterConnector
{
    public function __construct(protected string $endpoint,protected string $token='',protected int $timeout=8){}
    protected function request(string $path,array $payload=[],string $method='POST'): array
    {
        if($this->endpoint==='')return['ok'=>false,'message'=>'Endpoint is not configured.'];$url=rtrim($this->endpoint,'/').'/'.ltrim($path,'/');$headers=['Content-Type: application/json','Accept: application/json'];if($this->token!=='')$headers[]='Authorization: Bearer '.$this->token;
        $context=stream_context_create(['http'=>['method'=>$method,'timeout'=>$this->timeout,'ignore_errors'=>true,'header'=>implode("\r\n",$headers),'content'=>$method==='GET'?'':json_encode($payload,JSON_UNESCAPED_SLASHES)]]);$body=@file_get_contents($url,false,$context);if($body===false)return['ok'=>false,'message'=>'Connection failed.'];$decoded=json_decode($body,true);return is_array($decoded)?$decoded:['ok'=>false,'message'=>'Invalid connector response.'];
    }
    public function testConnection(): array{return$this->request('health',[],'GET');}
    public function listOffers(array $filters=[]): array{return$this->request('offers/search',$filters);}
    public function createContact(array $contact): array{return$this->request('contacts',$contact);}
    public function recordConversion(array $conversion): array{return$this->request('conversions',$conversion);}
}
class MicrogifterApiConnector extends HttpMicrogifterConnector {}
class MicrogifterMcpConnector extends HttpMicrogifterConnector
{
    protected function rpc(string $method,array $params=[]): array{return$this->request('', ['jsonrpc'=>'2.0','id'=>bin2hex(random_bytes(6)),'method'=>$method,'params'=>$params]);}
    public function testConnection(): array{return$this->rpc('microgifter.health');}
    public function listOffers(array $filters=[]): array{return$this->rpc('microgifter.offers.list',$filters);}
    public function createContact(array $contact): array{return$this->rpc('microgifter.contacts.create',$contact);}
    public function recordConversion(array $conversion): array{return$this->rpc('microgifter.conversions.record',$conversion);}
}
class MicrogifterHomeServerConnector extends MicrogifterMcpConnector {}
class DemoMicrogifterConnector implements MicrogifterConnector
{
    public function testConnection(): array{return['ok'=>true,'message'=>'Demo connector is ready.'];}
    public function listOffers(array $filters=[]): array{return['ok'=>true,'offers'=>[['id'=>'demo-local-gift','title'=>'Local experience gift','description'=>'A safe demonstration offer for the visual site builder.','price'=>'$25.00','url'=>'#']]];}
    public function createContact(array $contact): array{return['ok'=>true,'demo'=>true,'contact'=>$contact];}
    public function recordConversion(array $conversion): array{return['ok'=>true,'demo'=>true,'conversion'=>$conversion];}
}
class DisabledMicrogifterConnector implements MicrogifterConnector
{
    public function testConnection(): array{return['ok'=>false,'message'=>'Microgifter integration is disabled.'];}
    public function listOffers(array $filters=[]): array{return['ok'=>false,'offers'=>[]];}
    public function createContact(array $contact): array{return['ok'=>false];}
    public function recordConversion(array $conversion): array{return['ok'=>false];}
}
function microgifter_connector(): MicrogifterConnector
{
    $mode=nmm_site_setting('microgifter_connection_mode','disabled');$endpoint=nmm_site_setting('microgifter_endpoint');$token=microgifter_decrypt(nmm_site_setting('microgifter_token_encrypted'));$timeout=max(2,min(30,(int)nmm_site_setting('microgifter_timeout_seconds','8')));
    return match($mode){'demo'=>new DemoMicrogifterConnector(),'api'=>new MicrogifterApiConnector($endpoint,$token,$timeout),'mcp'=>new MicrogifterMcpConnector($endpoint,$token,$timeout),'homeserver'=>new MicrogifterHomeServerConnector($endpoint,$token,$timeout),default=>new DisabledMicrogifterConnector()};
}
function microgifter_cached_offers(array $filters=[]): array
{
    $mode=nmm_site_setting('microgifter_connection_mode','disabled');
    if($mode==='disabled')return['ok'=>false,'offers'=>[]];
    $filters['merchant_id']=$filters['merchant_id']??nmm_site_setting('microgifter_merchant_id');
    $minutes=max(1,min(1440,(int)nmm_site_setting('microgifter_cache_minutes','15')));
    $directory=NMM_ROOT.'/storage/microgifter-cache';
    if(!is_dir($directory))@mkdir($directory,0750,true);
    $key=hash('sha256',json_encode([$mode,nmm_site_setting('microgifter_endpoint'),$filters],JSON_UNESCAPED_SLASHES));
    $file=$directory.'/'.$key.'.json';
    if(is_file($file)&&filemtime($file)!==false&&filemtime($file)>time()-($minutes*60)){
        $cached=json_decode((string)@file_get_contents($file),true);if(is_array($cached))return$cached;
    }
    $result=microgifter_connector()->listOffers($filters);
    if(is_array($result)&&!empty($result['ok'])&&is_dir($directory)){@file_put_contents($file,json_encode($result,JSON_UNESCAPED_SLASHES),LOCK_EX);@chmod($file,0640);}
    return is_array($result)?$result:['ok'=>false,'offers'=>[]];
}
function microgifter_render_offer_block(array $settings=[]): string
{
    $mode=nmm_site_setting('microgifter_connection_mode','disabled');$title=(string)($settings['title']??'Send a meaningful local gift');$text=(string)($settings['text']??'Microgifter campaign and offer data will appear here when the connector is enabled.');$button=(string)($settings['buttonLabel']??'Explore the offer');$url=(string)($settings['url']??'#');
    $offerId=(string)($settings['offerId']??'');$price='';
    if(in_array($mode,['demo','api','mcp','homeserver'],true)){try{$result=microgifter_cached_offers(['limit'=>1,'offer_id'=>$offerId?:null]);$offer=$result['offers'][0]??$result['data'][0]??null;if(is_array($offer)){$offerId=(string)($offer['id']??$offerId);$title=(string)($offer['title']??$title);$text=(string)($offer['description']??$text);$url=(string)($offer['url']??$url);$price=(string)($offer['price']??'');}}catch(Throwable $e){error_log('Microgifter offer block failed: '.$e->getMessage());}}
    $link=$button!==''&&$url!==''?'<a class="site-block-button site-block-button-primary" href="'.e(nmm_public_link_url($url)).'" data-site-event="builder_microgifter_offer_clicked" data-site-label="'.e($title).'" data-site-offer-id="'.e($offerId).'" data-site-offer-price="'.e($price).'">'.e($button).'</a>':'';
    return '<article class="site-microgifter-card" data-microgifter-mode="'.e($mode).'"><span>Microgifter · '.e(status_label($mode)).'</span><h3>'.e($title).'</h3><p>'.e($text).'</p>'.($price!==''?'<strong class="site-microgifter-price">'.e($price).'</strong>':'').$link.'</article>';
}

require_once __DIR__ . '/vp3-license-settings-bridge.php';

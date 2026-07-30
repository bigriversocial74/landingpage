<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function feed_reader_fetch(string $url): array { return ['body'=>'<meta itemprop="channelId" content="UCabcdefghijklmnopqrstuv">','url'=>$url]; }
require_once dirname(__DIR__).'/portal/feed-reader-media.php';

$channel=feed_reader_youtube_direct_feed_url('https://www.youtube.com/channel/UCabcdefghijklmnopqrstuv');
if($channel!=='https://www.youtube.com/feeds/videos.xml?channel_id=UCabcdefghijklmnopqrstuv'){fwrite(STDERR,"YouTube channel resolution failed.\n");exit(1);}
$playlist=feed_reader_youtube_direct_feed_url('https://www.youtube.com/playlist?list=PLabcdefghijklmnopqrstuv');
if($playlist!=='https://www.youtube.com/feeds/videos.xml?playlist_id=PLabcdefghijklmnopqrstuv'){fwrite(STDERR,"YouTube playlist resolution failed.\n");exit(1);}
if(feed_reader_youtube_channel_id_from_html('<script>{"channelId":"UCabcdefghijklmnopqrstuv"}</script>')!=='UCabcdefghijklmnopqrstuv'){fwrite(STDERR,"YouTube HTML discovery failed.\n");exit(1);}
if(feed_reader_resolve_subscription_url('https://www.youtube.com/@example')!=='https://www.youtube.com/feeds/videos.xml?channel_id=UCabcdefghijklmnopqrstuv'){fwrite(STDERR,"YouTube handle resolution failed.\n");exit(1);}
$video=feed_reader_video_embed('https://youtu.be/dQw4w9WgXcQ');
if(!$video||!str_contains($video['embed_url'],'youtube-nocookie.com')){fwrite(STDERR,"YouTube privacy embed failed.\n");exit(1);}
$audio=feed_reader_item_media(['title'=>'Episode','enclosure_url'=>'https://example.com/episode.mp3','enclosure_type'=>'audio/mpeg','image_url'=>'']);
if($audio['kind']!=='audio'){fwrite(STDERR,"Audio classification failed.\n");exit(1);}

$root=dirname(__DIR__);
$paths=[
 'media'=>'portal/feed-reader-media.php','core'=>'portal/feed-reader-core.php','view'=>'portal/feed-reader-view.php','api'=>'portal/feed-reader-api.php',
 'bootstrap'=>'portal/bootstrap.php','script'=>'assets/js/feed-reader-social.js','css'=>'assets/css/feed-reader-media-v66b.css',
 'migration'=>'database/feed_reader_media_v66b.sql','schema'=>'database/north_mountain_portal.sql',
];
$source=[];foreach($paths as $key=>$path){$source[$key]=(string)file_get_contents($root.'/'.$path);if($source[$key]===''){fwrite(STDERR,"Missing {$path}.\n");exit(1);}}
$checks=[
 ['subscription resolver','feed_reader_resolve_subscription_url',$source['core']],
 ['media module','feed-reader-media.php',$source['view'].$source['api']],
 ['listened filter','stateFilter === \'listened\'',$source['core']],
 ['inline audio cards','data-feed-audio-source',$source['view'].$source['media']],
 ['privacy video loader','data-feed-video-load',$source['view'].$source['media'].$source['script']],
 ['durable playback API','playback_state',$source['api'].$source['script']],
 ['private notes','save_note',$source['api'].$source['view']],
 ['collections','collection_toggle',$source['api'].$source['view']],
 ['settings dependency injection','bool $mediaReady',$source['view']],
 ['settings collections dependency','array $collections',$source['view']],
 ['track-switch playback ownership','const previousTrigger = currentTrigger',$source['script']],
 ['same-track resume','if (player.paused) player.play().catch',$source['script']],
 ['listening queue','data-feed-player-next',$source['view'].$source['script']],
 ['playback threshold','listenedFromProgress',$source['script']],
 ['YouTube frame CSP','frame-src https://www.youtube-nocookie.com https://player.vimeo.com',$source['bootstrap']],
 ['HTTPS media CSP',"media-src 'self' https: blob:",$source['bootstrap']],
 ['media state migration','CREATE TABLE IF NOT EXISTS feed_item_media_states',$source['migration']],
 ['collection migration','CREATE TABLE IF NOT EXISTS feed_collections',$source['migration']],
 ['fresh schema media state','CREATE TABLE IF NOT EXISTS feed_item_media_states',$source['schema']],
 ['responsive player','.feed-reader-player',$source['css']],
];
foreach($checks as [$label,$needle,$haystack]){if(!str_contains($haystack,$needle)){fwrite(STDERR,"Missing {$label}: {$needle}\n");exit(1);}}
foreach(['feed_item_media_states','feed_collections','feed_collection_items'] as $table){if(substr_count($source['schema'],'CREATE TABLE IF NOT EXISTS '.$table)!==1){fwrite(STDERR,"Fresh schema must define {$table} once.\n");exit(1);}}
echo "Feed Reader Media v66B regression passed.\n";

<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function app_url(string $path): string { return '/' . ltrim($path, '/'); }

$root=dirname(__DIR__);
require_once $root.'/portal/blog-rich-media.php';

$youtube=blog_rich_media_video_from_url('https://youtu.be/dQw4w9WgXcQ?t=1m2s');
if(!$youtube||$youtube['provider']!=='YouTube'||!str_contains($youtube['embed_url'],'youtube-nocookie.com')||!str_contains($youtube['embed_url'],'start=62')){fwrite(STDERR,"YouTube normalization failed.\n");exit(1);}
$vimeo=blog_rich_media_video_from_url('https://vimeo.com/123456789');
if(!$vimeo||$vimeo['provider']!=='Vimeo'||!str_contains($vimeo['embed_url'],'player.vimeo.com')){fwrite(STDERR,"Vimeo normalization failed.\n");exit(1);}
foreach(['http://youtube.com/watch?v=dQw4w9WgXcQ','https://example.com/video/123','javascript:alert(1)'] as $unsafe){if(blog_rich_media_video_from_url($unsafe)!==null){fwrite(STDERR,"Unsafe video URL accepted.\n");exit(1);}}
$directive=blog_rich_media_parse_directive('[[track:42|Founder update]]');
if(!$directive||$directive['kind']!=='audio'||$directive['source']!=='42'||$directive['caption']!=='Founder update'){fwrite(STDERR,"Audio directive parsing failed.\n");exit(1);}
if(blog_rich_media_duration_iso(3723)!=='PT1H2M3S'){fwrite(STDERR,"Audio duration formatting failed.\n");exit(1);}

$files=[
 'publishing'=>$root.'/portal/publishing.php',
 'admin'=>$root.'/portal/publishing-admin.php',
 'workflowView'=>$root.'/portal/publishing-workflow-view.php',
 'post'=>$root.'/blog-post.php',
 'feed'=>$root.'/portal/blog-feed-output.php',
 'adminJs'=>$root.'/assets/js/blog-rich-media-admin.js',
 'publicJs'=>$root.'/assets/js/blog-rich-media.js',
 'css'=>$root.'/assets/css/blog-rich-media.css',
];
$source=[];foreach($files as $key=>$path){$source[$key]=(string)file_get_contents($path);if($source[$key]===''){fwrite(STDERR,"Missing rich media source: {$key}\n");exit(1);}}
$checks=[
 ['body directive renderer','blog_rich_media_render_directive',$source['publishing']],
 ['admin composer','data-blog-rich-media-composer',$source['admin']],
 ['active music selector','blog_rich_media_tracks_for_admin',$source['admin']],
 ['admin insertion script','data-insert-video',$source['adminJs']],
 ['privacy video CSP','frame-src https://www.youtube-nocookie.com https://player.vimeo.com',$source['post']],
 ['public rich media CSS','blog-rich-media.css?v=20260730-v66A',$source['post']],
 ['public audio runtime','blog-rich-media.js?v=20260730-v66A',$source['post']],
 ['RSS enclosure','<enclosure url=',$source['feed']],
 ['Atom enclosure','rel="enclosure"',$source['feed']],
 ['podcast namespace','xmlns:itunes=',$source['feed']],
 ['playback resume','localStorage',$source['publicJs']],
 ['playback speed','playbackRate',$source['publicJs']],
 ['responsive player','.blog-audio-card',$source['css']],
];
foreach($checks as [$label,$needle,$haystack]){if(!str_contains($haystack,$needle)){fwrite(STDERR,"Missing {$label}: {$needle}\n");exit(1);}}
if(str_contains($source['publishing'],'<iframe src="'.'$line')){fwrite(STDERR,"Arbitrary iframe rendering detected.\n");exit(1);}
echo "Rich Blog Media v66A regression passed.\n";

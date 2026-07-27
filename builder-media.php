<?php
/* North Mountain Media build: 20260727-visual-site-builder-v61 */
declare(strict_types=1);
define('NMM_PUBLIC_PAGE',true);
require __DIR__.'/portal/bootstrap.php';
$file=basename((string)($_GET['file']??''));
if(!preg_match('/^builder-[a-f0-9]{36}\.(?:jpg|png|webp|gif)$/',$file)){http_response_code(404);exit;}
$path=NMM_ROOT.'/storage/site-builder-media/'.$file;if(!is_file($path)){http_response_code(404);exit;}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream';if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true)){http_response_code(404);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Cache-Control: public,max-age=31536000,immutable');header('X-Content-Type-Options: nosniff');readfile($path);

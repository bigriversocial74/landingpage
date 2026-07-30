<?php
declare(strict_types=1);

define('NMM_ROOT', dirname(__DIR__));

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('DB_PORT') ?: 3306);
    $name = getenv('DB_NAME') ?: 'nmm';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'root';
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    return $pdo;
}
function setting(string $key, mixed $default=null): mixed
{
    $statement=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=:key LIMIT 1');
    $statement->execute(['key'=>$key]);
    $value=$statement->fetchColumn();
    return $value===false ? $default : $value;
}
function app_url(string $path): string { return 'https://pod.example/' . ltrim($path, '/'); }
function nmm_config(?string $section=null): array { return $section==='app' ? ['base_url'=>'https://pod.example'] : []; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function format_datetime(?string $value): string { return (string)$value; }
function status_label(string $value): string { return ucwords(str_replace('_',' ',$value)); }

require_once NMM_ROOT . '/portal/public-syndication.php';
require_once NMM_ROOT . '/portal/blog-feed-output.php';
require_once NMM_ROOT . '/portal/webmention-service.php';
require_once NMM_ROOT . '/portal/websub-service.php';

$fail=static function(string $message): never { fwrite(STDERR,$message."\n"); exit(1); };
$pdo=db();
$email='syndication-v66e@example.test';
$pdo->prepare('DELETE FROM users WHERE email=:email')->execute(['email'=>$email]);
$pdo->prepare(
    'INSERT INTO users(role,email,password_hash,display_name,status,must_change_password)
     VALUES("admin",:email,:password_hash,"Syndication Author","active",0)'
)->execute(['email'=>$email,'password_hash'=>password_hash('Test-only-Password-66E!',PASSWORD_DEFAULT)]);
$userId=(int)$pdo->lastInsertId();

$slugs=['syndication-v66e-alpha','syndication-v66e-beta'];
$delete=$pdo->prepare('DELETE FROM blog_posts WHERE slug=:slug');
foreach($slugs as $slug) $delete->execute(['slug'=>$slug]);
$insert=$pdo->prepare(
    'INSERT INTO blog_posts
        (author_user_id,title,slug,status,featured,category,excerpt,body,tags,seo_title,
         seo_description,canonical_url,published_at)
     VALUES
        (:author_user_id,:title,:slug,"published",:featured,:category,:excerpt,:body,:tags,
         NULL,NULL,NULL,:published_at)'
);
$insert->execute([
    'author_user_id'=>$userId,'title'=>'Open Web Alpha','slug'=>$slugs[0],'featured'=>1,
    'category'=>'Syndication','excerpt'=>'Alpha feed entry.','body'=>'## Alpha\nAn open web article.',
    'tags'=>'open-web, php','published_at'=>'2026-07-29 12:00:00',
]);
$alphaId=(int)$pdo->lastInsertId();
$insert->execute([
    'author_user_id'=>$userId,'title'=>'Operations Beta','slug'=>$slugs[1],'featured'=>0,
    'category'=>'Operations','excerpt'=>'Beta feed entry.','body'=>'## Beta\nAn operations article.',
    'tags'=>'operations, systems','published_at'=>'2026-07-28 12:00:00',
]);
$betaId=(int)$pdo->lastInsertId();

try {
    $category=syndication_public_posts(['category'=>'Syndication','tag'=>'','author'=>''],30);
    if(count($category)!==1 || ($category[0]['id']??0)!==$alphaId) $fail('Category feed filtering failed.');
    $tag=syndication_public_posts(['category'=>'','tag'=>'open-web','author'=>''],30);
    if(count($tag)!==1 || ($tag[0]['id']??0)!==$alphaId) $fail('Tag feed filtering failed.');
    $author=syndication_public_posts(['category'=>'','tag'=>'','author'=>(string)$userId],30);
    $authorIds=array_map(static fn(array $post): int=>(int)$post['id'],$author);
    sort($authorIds);
    $expected=[$alphaId,$betaId]; sort($expected);
    if($authorIds!==$expected) $fail('Author feed filtering failed.');

    $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES("blog_websub_hub_url","https://hub.example.test/"),("blog_websub_enabled","1") ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute();
    $json=syndication_render_json_feed(['category'=>'','tag'=>'open-web','author'=>'']);
    $decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    if(($decoded['version']??'')!=='https://jsonfeed.org/version/1.1') $fail('JSON Feed version failed.');
    if(count($decoded['items']??[])!==1 || ($decoded['items'][0]['title']??'')!=='Open Web Alpha') $fail('JSON Feed item rendering failed.');
    if(!str_contains((string)($decoded['feed_url']??''),'tag=open-web')) $fail('JSON Feed self URL filter failed.');
    if(($decoded['hubs'][0]['type']??'')!=='WebSub' || ($decoded['hubs'][0]['url']??'')!=='https://hub.example.test/') $fail('JSON Feed WebSub hub rendering failed.');

    $_GET=['tag'=>'open-web'];
    $rss=publishing_render_rss_feed();
    if(!str_contains($rss,'<rss version="2.0"') || !str_contains($rss,'Open Web Alpha') || str_contains($rss,'Operations Beta')) $fail('RSS filtered rendering failed.');
    if(!str_contains($rss,'application/feed+json')) $fail('RSS JSON discovery failed.');
    $atom=publishing_render_atom_feed();
    if(!str_contains($atom,'<feed xmlns="http://www.w3.org/2005/Atom">') || !str_contains($atom,'Open Web Alpha') || str_contains($atom,'Operations Beta')) $fail('Atom filtered rendering failed.');

    $podcast=syndication_render_podcast_feed();
    if(!str_contains($podcast,'xmlns:itunes=') || !str_contains($podcast,'xmlns:podcast=')) $fail('Podcast namespace rendering failed.');
    if(!str_contains($podcast,'<itunes:type>episodic</itunes:type>')) $fail('Podcast channel metadata failed.');
    if(str_contains($podcast,'<podcast:locked owner="">')) $fail('Empty podcast owner metadata rendered.');

    $pdo->prepare(
        'INSERT INTO syndication_webmentions
            (source_url,target_url,target_post_id,mention_type,status,author_name,source_title,
             source_excerpt,source_content_hash,verified_at)
         VALUES
            ("https://writer.example/reply","https://pod.example/blog-post.php?slug=syndication-v66e-alpha",
             :post_id,"reply","approved","Alex Rivera","External reply","Approved mention.",
             :hash,UTC_TIMESTAMP())'
    )->execute(['post_id'=>$alphaId,'hash'=>hash('sha256','approved mention')]);
    $mentions=syndication_approved_webmentions($alphaId);
    if(count($mentions)!==1 || ($mentions[0]['author_name']??'')!=='Alex Rivera') $fail('Approved Webmention retrieval failed.');

    $topics=syndication_websub_topics();
    foreach(['blog-feed.php','blog-atom.php','blog-json-feed.php','podcast-feed.php'] as $path) {
        if(!count(array_filter($topics,static fn(string $topic): bool=>str_ends_with($topic,$path)))) $fail('Missing WebSub topic: '.$path);
    }
} finally {
    $pdo->prepare('DELETE FROM blog_posts WHERE id IN (:alpha,:beta)')->execute(['alpha'=>$alphaId,'beta'=>$betaId]);
    $pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$userId]);
}

echo "Public Syndication v66E database integration passed.\n";

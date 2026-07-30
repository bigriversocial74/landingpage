<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
require_once dirname(__DIR__) . '/portal/content-interactions.php';

$clean = content_interactions_clean_body("  Hello\x00 world   \n\n\n\nNext  ");
if ($clean !== "Hello world\n\n\nNext") { fwrite(STDERR, "Comment cleaning failed.\n"); exit(1); }
if (!str_contains(content_interactions_render_text('<script>alert(1)</script>'), '&lt;script&gt;')) { fwrite(STDERR, "Comment escaping failed.\n"); exit(1); }
if (array_keys(content_interactions_reaction_types()) !== ['like','support','insightful']) { fwrite(STDERR, "Reaction catalog failed.\n"); exit(1); }
$now = gmdate('Y-m-d H:i:s');
if (!content_interactions_can_edit(['author_user_id'=>7,'status'=>'approved','created_at'=>$now], ['id'=>7,'role'=>'client'])) { fwrite(STDERR, "Owner edit window failed.\n"); exit(1); }
if (content_interactions_can_edit(['author_user_id'=>7,'status'=>'approved','created_at'=>'2020-01-01 00:00:00'], ['id'=>7,'role'=>'client'])) { fwrite(STDERR, "Expired edit window failed.\n"); exit(1); }
if (!content_interactions_can_edit(['author_user_id'=>7,'status'=>'approved','created_at'=>'2020-01-01 00:00:00'], ['id'=>1,'role'=>'admin'])) { fwrite(STDERR, "Administrator edit override failed.\n"); exit(1); }

$root = dirname(__DIR__);
$paths = [
 'core'=>'portal/content-interactions.php', 'admin'=>'portal/content-interactions-admin.php',
 'api'=>'content-interactions-api.php', 'post'=>'blog-post.php', 'publishingAdmin'=>'portal/publishing-admin.php',
 'script'=>'assets/js/content-interactions.js', 'css'=>'assets/css/content-interactions.css',
 'migration'=>'database/content_interactions_v66c.sql', 'schema'=>'database/north_mountain_portal.sql',
];
$source=[];foreach($paths as $key=>$path){$source[$key]=(string)file_get_contents($root.'/'.$path);if($source[$key]===''){fwrite(STDERR,"Missing {$path}.\n");exit(1);}}
$checks = [
 ['public interaction render','content_interactions_render_public',$source['post']],
 ['authenticated API','Authentication required',$source['api']],
 ['same-origin API','same_origin_request',$source['api']],
 ['CSRF API','verify_csrf',$source['api']],
 ['existing rate limiter','rate_limit_exceeded',$source['api']],
 ['anonymous posting disabled','Anonymous posting is disabled',$source['core']],
 ['pre-moderation','pre_moderated',$source['core'].$source['admin']],
 ['one-level replies',"(int)$parent['depth'] !== 0",$source['core']],
 ['edit history','content_comment_edits',$source['core'].$source['migration']],
 ['reports','content_comment_reports',$source['core'].$source['migration']],
 ['five-report auto hide','$count >= 5',$source['core']],
 ['post/comment reactions','target_type',$source['core'].$source['migration']],
 ['moderation events','content_moderation_events',$source['core'].$source['migration']],
 ['participant notifications','content_interactions_notify_participants',$source['core']],
 ['admin moderation queue','content_interactions_render_admin_summary',$source['publishingAdmin']],
 ['per-post settings','content_interactions_render_post_settings',$source['publishingAdmin']],
 ['delete cleanup','content_interactions_cleanup',$source['publishingAdmin']],
 ['settings override','array_replace($defaults',$source['core']],
 ['edit duplicate exclusion','exclude_comment_id',$source['core']],
 ['closed schema fallback','content-interaction-unavailable',$source['core']],
 ['public script','content-interactions.js?v=20260730-v66C',$source['post']],
 ['public CSS','content-interactions.css?v=20260730-v66C',$source['post']],
 ['generic fresh schema','CREATE TABLE IF NOT EXISTS content_comments',$source['schema']],
];
foreach($checks as [$label,$needle,$haystack]){if(!str_contains($haystack,$needle)){fwrite(STDERR,"Missing {$label}: {$needle}\n");exit(1);}}
foreach(['content_interaction_settings','content_comments','content_comment_edits','content_reactions','content_comment_reports','content_moderation_events'] as $table){
    $needle='CREATE TABLE IF NOT EXISTS '.$table;
    if(substr_count($source['migration'],$needle)!==1){fwrite(STDERR,"Migration must define {$table} exactly once.\n");exit(1);}
    if(substr_count($source['schema'],$needle)!==1){fwrite(STDERR,"Fresh schema must define {$table} exactly once.\n");exit(1);}
}
echo "Content Interactions v66C regression passed.\n";

<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/social-posts-service.php';
require_once __DIR__ . '/stories-service.php';

$user = require_role('admin');
$userId = (int)$user['id'];

if (is_post()) {
    if (!same_origin_request()) { http_response_code(403); exit('Cross-origin request denied.'); }
    verify_csrf();
    enforce_authenticated_action_limit($user);
    try {
        $action = input('action');
        if ($action === 'delete_post') {
  social_posts_delete(int_input('post_id'), $userId);
  flash('success','The post was deleted and a signed Tombstone was queued when required.');
        } elseif ($action === 'save_settings') {
  $mode = input('landing_mode','tabs');
  if (!in_array($mode,['none','blogs','social','tabs'],true)) $mode='tabs';
  $visibility = input('default_visibility','public');
  if (!in_array($visibility,['public','followers'],true)) $visibility='public';
  social_posts_save_settings([
      'social_posts_enabled'=>isset($_POST['social_posts_enabled'])?'1':'0',
      'social_posts_default_visibility'=>$visibility,
      'social_posts_allow_public'=>isset($_POST['allow_public'])?'1':'0',
      'social_posts_landing_mode'=>$mode,
      'social_posts_landing_limit'=>(string)max(1,min(12,int_input('landing_limit',6))),
      'social_posts_show_follow_button'=>isset($_POST['show_follow_button'])?'1':'0',
  ]);
  flash('success','Social Feed settings were updated.');
        } else throw new RuntimeException('Unsupported Social Feed action.');
    } catch (Throwable $exception) { flash('error',$exception->getMessage()); }
    redirect('portal/social-posts.php');
}

$schemaAvailable = social_posts_schema_available();
$settings = social_posts_settings();
$allPosts = $schemaAvailable ? social_posts_owner_posts($userId,150) : [];
$posts = array_values(array_filter($allPosts, static fn(array $post): bool => (string)$post['status']==='published'));
$drafts = array_values(array_filter($allPosts, static fn(array $post): bool => (string)$post['status']==='draft'));
$storiesAvailable = nmm_module_enabled('stories') && stories_schema_available();
$activitySettings = activitypub_settings();

portal_header('Social Feed','social-posts',$user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/stories-v66o.css?v=20260731-v66O'))?>">
<div class="social-feed-admin" data-stories-app data-story-view-endpoint="<?=e(app_url('api/story-view.php'))?>" data-csrf="<?=e(csrf_token())?>">
<div class="social-feed-toolbar">
<div><span>@<?=e(activitypub_account())?></span><strong>Posts and Stories</strong></div>
<div><button type="button" data-publishing-open="story">Add story</button><button type="button" data-publishing-open="social-post">Create post</button><button type="button" data-feed-settings-open aria-label="Open Social Feed settings">Settings</button><a href="<?=e(app_url('social-feed.php'))?>" target="_blank" rel="noopener">Public feed</a></div>
</div>
<p class="social-feed-guidance">Create a post with Publishing +, publish immediately, or choose <strong>Save draft</strong> for later. Blog and RSS remain independent from the Social Feed.</p>

<?php if(!$schemaAvailable):?><section class="pod-social-warning"><strong>Social Posts migration required.</strong> Import <code>database/social_posts_v66p.sql</code>.</section><?php endif;?>
<?php if(nmm_module_enabled('stories')&&!$storiesAvailable):?><section class="pod-social-warning"><strong>Stories migration required.</strong> Import <code>database/stories_v66o.sql</code>.</section><?php endif;?>

<?php if($storiesAvailable):?>
<section class="social-feed-stories"><header><div><span>Stories</span><h2>Recent updates</h2></div><button type="button" data-publishing-open="story">+</button></header><?php stories_render_rail($userId,40);?></section>
<?php endif;?>

<main class="social-feed-column" aria-label="Published social posts">
<?php foreach($posts as $post):?>
<section class="social-feed-record"><?php social_posts_render_card($post);?><div class="social-feed-record-actions"><button type="button" data-publishing-open="social-post" data-publishing-url="<?=e(app_url('portal/publish-social-post.php?modal=1&id='.(int)$post['id']))?>">Edit</button><form method="post" data-confirm data-confirm-title="Delete this social post?" data-confirm="The post will be removed and a signed Tombstone will be queued when required."><?=csrf_field()?><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?=(int)$post['id']?>"><button type="submit">Delete</button></form></div></section>
<?php endforeach;?>
<?php if(!$posts):?><section class="social-feed-empty"><span>Social Feed</span><h2>No posts yet.</h2><p>Use Publishing + to create the first permanent post.</p><button type="button" data-publishing-open="social-post">Create post</button></section><?php endif;?>
<?php if($drafts):?><section class="social-feed-drafts"><strong><?=count($drafts)?> draft<?=count($drafts)===1?'':'s'?></strong><div><?php foreach($drafts as $draft):?><button type="button" data-publishing-open="social-post" data-publishing-url="<?=e(app_url('portal/publish-social-post.php?modal=1&id='.(int)$draft['id']))?>"><?=e(mb_substr((string)($draft['body_text']?:'Untitled draft'),0,70))?></button><?php endforeach;?></div></section><?php endif;?>
</main>

<dialog class="social-feed-settings" data-feed-settings-dialog>
<header><div><span>Social Feed</span><h2>Publishing settings</h2></div><button type="button" data-feed-settings-close aria-label="Close settings">×</button></header>
<form method="post" class="pod-social-settings"><?=csrf_field()?><input type="hidden" name="action" value="save_settings">
<label><input type="checkbox" name="social_posts_enabled" <?=$settings['enabled']?'checked':''?>><span><strong>Enable social publishing</strong><small>Allow permanent local ActivityPub Notes.</small></span></label>
<label><input type="checkbox" name="allow_public" <?=$settings['allow_public']?'checked':''?>><span><strong>Allow public posts</strong><small>Public posts can appear on the landing page and public feed.</small></span></label>
<label><input type="checkbox" name="show_follow_button" <?=$settings['show_follow_button']?'checked':''?>><span><strong>Show Follow this POD</strong><small>Display the remote-follow helper when ActivityPub is active.</small></span></label>
<label><span><strong>Landing content</strong><small>Blog, social, or both</small></span><select name="landing_mode"><option value="none" <?=$settings['landing_mode']==='none'?'selected':''?>>Do not display</option><option value="blogs" <?=$settings['landing_mode']==='blogs'?'selected':''?>>Blog posts</option><option value="social" <?=$settings['landing_mode']==='social'?'selected':''?>>Social posts</option><option value="tabs" <?=$settings['landing_mode']==='tabs'?'selected':''?>>Tabbed blog + social</option></select></label>
<label><span><strong>Default visibility</strong><small>New social posts</small></span><select name="default_visibility"><option value="public" <?=$settings['default_visibility']==='public'?'selected':''?>>Public</option><option value="followers" <?=$settings['default_visibility']==='followers'?'selected':''?>>Followers only</option></select></label>
<label><span><strong>Landing limit</strong><small>1–12 items per source</small></span><input type="number" name="landing_limit" min="1" max="12" value="<?=$settings['landing_limit']?>"></label>
<button class="pod-social-button" type="submit">Save settings</button></form>
</dialog>

<?php if($storiesAvailable):?>
<dialog class="story-viewer" data-story-dialog aria-label="Story viewer"><div class="story-viewer-progress"><i data-story-progress></i></div><header><div><strong data-story-author></strong><span data-story-time></span></div><button type="button" data-story-close aria-label="Close story">×</button></header><main><div class="story-viewer-media" data-story-media hidden></div><span class="story-viewer-type" data-story-type></span><h2 data-story-title></h2><p data-story-body></p><a data-story-link target="_blank" rel="noopener noreferrer nofollow" hidden>Open story link</a></main><footer><button type="button" data-story-previous aria-label="Previous story">‹</button><button type="button" data-story-next aria-label="Next story">›</button></footer></dialog>
<?php endif;?>
</div>
<script src="<?=e(app_url('assets/js/social-posts-v66p.js?v=20260731-v66P'))?>"></script>
<?php if($storiesAvailable):?><script src="<?=e(app_url('assets/js/stories-v66o.js?v=20260731-v66O'))?>"></script><?php endif;?>
<?php portal_footer(); ?>

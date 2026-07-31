<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/stories-service.php';

$user = require_role('admin');
$userId = (int)$user['id'];
$settings = stories_settings();

if (is_post()) {
    if (!same_origin_request()) { http_response_code(403); exit('Cross-origin request denied.'); }
    verify_csrf();
    enforce_authenticated_action_limit($user);
    try {
        stories_create_local([
  'title'=>input('title'),
  'body_text'=>input('body_text'),
  'media_kind'=>input('media_kind','none'),
  'media_url'=>input('media_url'),
  'media_alt'=>input('media_alt'),
  'link_url'=>input('link_url'),
        ], $userId);
        flash('success','Your follower story is live.');
        redirect('portal/publish-story.php?modal=1&done=1');
    } catch (Throwable $exception) {
        flash('error',$exception->getMessage());
        redirect('portal/publish-story.php?modal=1');
    }
}

portal_header('Create story', 'social-posts', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/stories-v66o.css?v=20260731-v66O'))?>">
<div class="publishing-form-shell">
<?php if(isset($_GET['done'])):?><section class="publishing-form-success"><strong>Story published</strong><p>The Social Feed is being refreshed.</p></section><script src="<?=e(app_url('assets/js/publishing-complete-v66q.js'))?>"></script><?php else:?>
<header><span>Follower Stories</span><h2>New story</h2><p>Share a temporary update with approved followers. It expires in <?=$settings['duration_hours']?> hours.</p></header>
<form method="post" class="story-composer">
<?=csrf_field()?>
<label><span>Title</span><input name="title" maxlength="200" placeholder="A quick update"></label>
<label><span>Story</span><textarea name="body_text" maxlength="4000" rows="7" placeholder="Share an update with approved followers."></textarea></label>
<div class="story-form-grid"><label><span>Media type</span><select name="media_kind"><option value="none">No media</option><option value="image">Image</option><option value="audio">Audio</option><option value="video">Video</option><option value="link">Link card</option></select></label><label><span>Same-origin media URL</span><input name="media_url" maxlength="2048" placeholder="<?=e(app_url('storage/...'))?>"></label></div>
<label><span>Media description</span><input name="media_alt" maxlength="500" placeholder="Describe the media for accessibility"></label>
<label><span>Optional same-origin destination</span><input name="link_url" maxlength="2048" placeholder="<?=e(app_url('blog-post.php?...'))?>"></label>
<p class="story-policy-note">Stories are not end-to-end encrypted; recipients can retain content they receive.</p>
<button class="stories-button" type="submit">Publish story</button>
</form>
<?php endif;?>
</div>
<?php portal_footer(); ?>

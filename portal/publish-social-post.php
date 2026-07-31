<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/social-posts-service.php';

$user = require_role('admin');
$userId = (int)$user['id'];
$postId = query_int('id');
$editing = $postId > 0 ? social_posts_find($postId) : null;
if ($editing && (int)$editing['owner_user_id'] !== $userId) $editing = null;
$settings = social_posts_settings();

if (is_post()) {
    if (!same_origin_request()) { http_response_code(403); exit('Cross-origin request denied.'); }
    verify_csrf();
    enforce_authenticated_action_limit($user);
    try {
        $publish = input('intent', 'publish') === 'publish';
        $values = [
  'body_text'=>input('body_text'),
  'media_kind'=>input('media_kind','none'),
  'media_url'=>input('media_url'),
  'media_alt'=>input('media_alt'),
  'link_url'=>input('link_url'),
  'visibility'=>input('visibility','public'),
        ];
        $submittedId = int_input('post_id');
        if ($submittedId > 0) social_posts_update($submittedId, $values, $userId, $publish);
        else social_posts_create($values, $userId, $publish);
        flash('success', $publish ? 'Social post published.' : 'Social post saved as a draft.');
        redirect('portal/publish-social-post.php?modal=1&done=1');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect('portal/publish-social-post.php?modal=1' . ($postId > 0 ? '&id='.$postId : ''));
    }
}

portal_header($editing ? 'Edit social post' : 'Create social post', 'social-posts', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">
<div class="publishing-form-shell">
<?php if(isset($_GET['done'])):?><section class="publishing-form-success"><strong>Published</strong><p>The Social Feed is being refreshed.</p></section><script src="<?=e(app_url('assets/js/publishing-complete-v66q.js'))?>"></script><?php else:?>
<header><span>Social Feed</span><h2><?=$editing?'Edit social post':'New social post'?></h2><p>Publish a permanent public or follower-only ActivityPub Note.</p></header>
<form method="post" class="pod-social-composer">
<?=csrf_field()?>
<?php if($editing):?><input type="hidden" name="post_id" value="<?=(int)$editing['id']?>"><?php endif;?>
<label><span>Post</span><textarea name="body_text" maxlength="8000" rows="8" placeholder="Share an update from this POD."><?=e((string)($editing['body_text']??''))?></textarea></label>
<div class="pod-social-form-grid">
<label><span>Visibility</span><select name="visibility"><option value="public" <?=(string)($editing['visibility']??$settings['default_visibility'])==='public'?'selected':''?> <?=$settings['allow_public']?'':'disabled'?>>Public</option><option value="followers" <?=(string)($editing['visibility']??$settings['default_visibility'])==='followers'?'selected':''?>>Followers only</option></select></label>
<label><span>Media type</span><select name="media_kind"><?php foreach(['none'=>'No media','image'=>'Image','audio'=>'Audio','video'=>'Video','link'=>'Media link'] as $value=>$label):?><option value="<?=e($value)?>" <?=(string)($editing['media_kind']??'none')===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
</div>
<label><span>Protected same-origin media URL</span><input name="media_url" maxlength="2048" value="<?=e((string)($editing['media_url']??''))?>" placeholder="<?=e(app_url('storage/...'))?>"></label>
<label><span>Media description</span><input name="media_alt" maxlength="500" value="<?=e((string)($editing['media_alt']??''))?>" placeholder="Describe the media for accessibility"></label>
<label><span>Optional HTTPS link</span><input name="link_url" maxlength="2048" value="<?=e((string)($editing['link_url']??''))?>" placeholder="https://example.com/resource"></label>
<p class="pod-social-policy-note">Followers-only delivery is audience-restricted, not end-to-end encrypted.</p>
<div class="pod-social-composer-actions"><button class="pod-social-button secondary" type="submit" name="intent" value="draft">Save draft</button><button class="pod-social-button" type="submit" name="intent" value="publish"><?=$editing?'Publish update':'Publish post'?></button></div>
</form>
<?php endif;?>
</div>
<?php portal_footer(); ?>

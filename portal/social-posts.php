<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/social-posts-service.php';

$user = require_role('admin');
$userId = (int)$user['id'];

if (is_post()) {
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Cross-origin request denied.');
    }
    verify_csrf();
    enforce_authenticated_action_limit($user);
    try {
        $action = input('action');
        if ($action === 'create_post') {
            $publish = input('intent', 'publish') === 'publish';
            social_posts_create([
                'body_text' => input('body_text'),
                'media_kind' => input('media_kind', 'none'),
                'media_url' => input('media_url'),
                'media_alt' => input('media_alt'),
                'link_url' => input('link_url'),
                'visibility' => input('visibility', 'public'),
            ], $userId, $publish);
            flash('success', $publish
                ? 'The social post was published and ActivityPub delivery was queued.'
                : 'The social post was saved as a draft.');
        } elseif ($action === 'update_post') {
            $publish = input('intent', 'publish') === 'publish';
            social_posts_update(
                int_input('post_id'),
                [
                    'body_text' => input('body_text'),
                    'media_kind' => input('media_kind', 'none'),
                    'media_url' => input('media_url'),
                    'media_alt' => input('media_alt'),
                    'link_url' => input('link_url'),
                    'visibility' => input('visibility', 'public'),
                ],
                $userId,
                $publish
            );
            flash('success', $publish
                ? 'The social post was updated and the signed ActivityPub update was queued.'
                : 'The draft was updated.');
        } elseif ($action === 'delete_post') {
            social_posts_delete(int_input('post_id'), $userId);
            flash('success', 'The post was deleted and a signed Tombstone was queued when required.');
        } elseif ($action === 'save_settings') {
            $mode = input('landing_mode', 'tabs');
            if (!in_array($mode, ['none', 'blogs', 'social', 'tabs'], true)) $mode = 'tabs';
            $visibility = input('default_visibility', 'public');
            if (!in_array($visibility, ['public', 'followers'], true)) $visibility = 'public';
            social_posts_save_settings([
                'social_posts_enabled' => isset($_POST['social_posts_enabled']) ? '1' : '0',
                'social_posts_default_visibility' => $visibility,
                'social_posts_allow_public' => isset($_POST['allow_public']) ? '1' : '0',
                'social_posts_landing_mode' => $mode,
                'social_posts_landing_limit' => (string)max(1, min(12, int_input('landing_limit', 6))),
                'social_posts_show_follow_button' => isset($_POST['show_follow_button']) ? '1' : '0',
            ]);
            flash('success', 'Social publishing and landing-page settings were updated.');
        } else {
            throw new RuntimeException('Unsupported social publishing action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('portal/social-posts.php');
}

$schemaAvailable = social_posts_schema_available();
$settings = social_posts_settings();
$posts = $schemaAvailable ? social_posts_owner_posts($userId, 150) : [];
$editId = query_int('edit');
$editing = $editId > 0 ? social_posts_find($editId) : null;
if ($editing && (int)($editing['owner_user_id'] ?? 0) !== $userId) $editing = null;
$publishedCount = count(array_filter($posts, static fn(array $post): bool => $post['status'] === 'published'));
$draftCount = count(array_filter($posts, static fn(array $post): bool => $post['status'] === 'draft'));
$activitySettings = activitypub_settings();

portal_header('Social Posts', 'social-posts', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">
<div class="pod-social-admin">
<section class="pod-social-admin-hero">
<div>
<span>Permanent social publishing · Section 66P</span>
<h2>Publish from your POD like a social profile.</h2>
<p>Create permanent public or follower-only ActivityPub Notes. Stories remain temporary, while blog posts and RSS continue unchanged.</p>
</div>
<nav>
<a href="<?=e(app_url('portal/federated-feed.php'))?>">Federated Timeline</a>
<a href="<?=e(app_url('portal/stories.php'))?>">Stories</a>
<a href="<?=e(app_url('social-feed.php'))?>" target="_blank" rel="noopener">Public social feed</a>
</nav>
</section>

<?php if(!$schemaAvailable):?>
<section class="pod-social-warning"><strong>Social Posts migration required.</strong> Import <code>database/social_posts_v66p.sql</code>.</section>
<?php else:?>
<section class="pod-social-stats">
<article><span>Published</span><strong><?=$publishedCount?></strong></article>
<article><span>Drafts</span><strong><?=$draftCount?></strong></article>
<article><span>Followers</span><strong><?=activitypub_schema_available()?count(activitypub_followers(false,500)):0?></strong></article>
<article><span>Landing display</span><strong><?=e(status_label($settings['landing_mode']))?></strong></article>
</section>

<div class="pod-social-admin-layout">
<section class="pod-social-admin-panel" id="composer">
<header><div><span><?=$editing?'Edit':'Create'?></span><h2><?=$editing?'Update social post':'New social post'?></h2></div><b><?=e('@'.activitypub_account())?></b></header>
<form method="post" class="pod-social-composer">
<?=csrf_field()?>
<input type="hidden" name="action" value="<?=$editing?'update_post':'create_post'?>">
<?php if($editing):?><input type="hidden" name="post_id" value="<?=(int)$editing['id']?>"><?php endif;?>
<label><span>Post</span><textarea name="body_text" maxlength="8000" rows="8" placeholder="Share an update from this POD."><?=e((string)($editing['body_text']??''))?></textarea></label>
<div class="pod-social-form-grid">
<label><span>Visibility</span><select name="visibility">
<option value="public" <?=(string)($editing['visibility']??$settings['default_visibility'])==='public'?'selected':''?> <?=$settings['allow_public']?'':'disabled'?>>Public</option>
<option value="followers" <?=(string)($editing['visibility']??$settings['default_visibility'])==='followers'?'selected':''?>>Followers only</option>
</select></label>
<label><span>Media type</span><select name="media_kind">
<?php foreach(['none'=>'No media','image'=>'Image','audio'=>'Audio','video'=>'Video','link'=>'Media link'] as $value=>$label):?><option value="<?=e($value)?>" <?=(string)($editing['media_kind']??'none')===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?>
</select></label>
</div>
<label><span>Protected same-origin media URL</span><input name="media_url" maxlength="2048" value="<?=e((string)($editing['media_url']??''))?>" placeholder="<?=e(app_url('storage/...'))?>"></label>
<label><span>Media description</span><input name="media_alt" maxlength="500" value="<?=e((string)($editing['media_alt']??''))?>" placeholder="Describe the media for accessibility"></label>
<label><span>Optional HTTPS link</span><input name="link_url" maxlength="2048" value="<?=e((string)($editing['link_url']??''))?>" placeholder="https://example.com/resource"></label>
<p class="pod-social-policy-note">Public posts can appear on the landing page and open web. Followers-only posts are delivered only to approved ActivityPub followers, but recipients may retain received content.</p>
<div class="pod-social-composer-actions">
<?php if(!$editing||$editing['status']==='draft'):?><button class="pod-social-button secondary" type="submit" name="intent" value="draft">Save draft</button><?php endif;?>
<button class="pod-social-button" type="submit" name="intent" value="publish"><?=$editing&&$editing['status']==='published'?'Publish update':'Publish post'?></button>
<?php if($editing):?><a href="<?=e(app_url('portal/social-posts.php'))?>">Cancel edit</a><?php endif;?>
</div>
</form>
</section>

<section class="pod-social-admin-panel">
<header><div><span>Display</span><h2>Landing-page content</h2></div><b><?=e(status_label($settings['landing_mode']))?></b></header>
<form method="post" class="pod-social-settings">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_settings">
<label><input type="checkbox" name="social_posts_enabled" <?=$settings['enabled']?'checked':''?>><span><strong>Enable social publishing</strong><small>Allow permanent local ActivityPub Notes.</small></span></label>
<label><input type="checkbox" name="allow_public" <?=$settings['allow_public']?'checked':''?>><span><strong>Allow public posts</strong><small>Public posts can appear on the landing page and public feed.</small></span></label>
<label><input type="checkbox" name="show_follow_button" <?=$settings['show_follow_button']?'checked':''?>><span><strong>Show Follow this POD</strong><small>Display the remote-follow helper when ActivityPub is active.</small></span></label>
<label><span><strong>Landing content</strong><small>Blog, social, or both</small></span><select name="landing_mode">
<option value="none" <?=$settings['landing_mode']==='none'?'selected':''?>>Do not display</option>
<option value="blogs" <?=$settings['landing_mode']==='blogs'?'selected':''?>>Blog posts</option>
<option value="social" <?=$settings['landing_mode']==='social'?'selected':''?>>Social posts</option>
<option value="tabs" <?=$settings['landing_mode']==='tabs'?'selected':''?>>Tabbed blog + social</option>
</select></label>
<label><span><strong>Default visibility</strong><small>New social posts</small></span><select name="default_visibility"><option value="public" <?=$settings['default_visibility']==='public'?'selected':''?>>Public</option><option value="followers" <?=$settings['default_visibility']==='followers'?'selected':''?>>Followers only</option></select></label>
<label><span><strong>Landing limit</strong><small>1–12 items per source</small></span><input type="number" name="landing_limit" min="1" max="12" value="<?=$settings['landing_limit']?>"></label>
<div class="pod-social-fixed-policy"><strong>Blog and RSS remain independent</strong><span>Changing this display setting does not disable, replace, or rewrite existing blog posts or RSS output.</span></div>
<button class="pod-social-button secondary" type="submit">Save publishing settings</button>
</form>
</section>
</div>

<section class="pod-social-admin-panel pod-social-management">
<header><div><span>Library</span><h2>Social posts</h2></div><span><?=count($posts)?> records</span></header>
<div class="pod-social-management-list">
<?php foreach($posts as $post):?>
<article>
<div class="pod-social-management-copy"><span><?=e(status_label((string)$post['status']))?> · <?=e(status_label((string)$post['visibility']))?></span><strong><?=e(mb_substr((string)($post['body_text']?:'Media or link post'),0,140))?></strong><small><?=e(format_datetime((string)($post['published_at']?:$post['created_at'])))?></small></div>
<div class="pod-social-management-actions">
<a href="<?=e(app_url('portal/social-posts.php?edit='.(int)$post['id'].'#composer'))?>">Edit</a>
<?php if($post['status']==='published'&&$post['visibility']==='public'):?><a href="<?=e(social_posts_public_url($post))?>" target="_blank" rel="noopener">View</a><?php endif;?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?=(int)$post['id']?>"><button type="submit">Delete</button></form>
</div>
</article>
<?php endforeach;?>
<?php if(!$posts):?><div class="pod-content-empty">Create the first permanent social post for this POD.</div><?php endif;?>
</div>
</section>

<section class="pod-social-admin-panel pod-social-federation-status">
<header><div><span>Federation</span><h2>Delivery boundary</h2></div><b><?=$activitySettings['enabled']?'Active':'Not active'?></b></header>
<p>Actor: <code><?=e(activitypub_actor_url())?></code></p>
<p>Account: <code><?=e('@'.activitypub_account())?></code></p>
<p>Public Notes use the ActivityStreams Public audience and approved followers. Followers-only Notes are addressed only to the approved followers collection.</p>
</section>
<?php endif;?>
</div>
<script src="<?=e(app_url('assets/js/social-posts-v66p.js?v=20260731-v66P'))?>"></script>
<?php portal_footer(); ?>

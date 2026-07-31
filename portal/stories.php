<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/stories-service.php';

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
        if ($action === 'create_story') {
            stories_create_local([
                'title' => input('title'),
                'body_text' => input('body_text'),
                'media_kind' => input('media_kind', 'none'),
                'media_url' => input('media_url'),
                'media_alt' => input('media_alt'),
                'link_url' => input('link_url'),
            ], $userId);
            flash('success', 'Your follower story is live.');
        } elseif ($action === 'delete_story') {
            stories_delete_local(int_input('story_id'), $userId);
            flash('success', 'The story was deleted and a signed Tombstone was queued.');
        } elseif ($action === 'moderate_story') {
            stories_moderate_remote(
                int_input('story_id'),
                input('decision'),
                $userId
            );
            flash('success', 'The remote story moderation state was updated.');
        } elseif ($action === 'save_story_settings') {
            $values = [
                'stories_enabled' => isset($_POST['stories_enabled']) ? '1' : '0',
                'stories_receive_remote' => isset($_POST['receive_remote']) ? '1' : '0',
                'stories_duration_hours' => (string)max(1, min(48, int_input('duration_hours', 24))),
                'stories_max_active' => (string)max(1, min(50, int_input('max_active', 10))),
                'stories_remote_media_mode' => 'link_only',
            ];
            $statement = db()->prepare(
                'INSERT INTO settings(setting_key,setting_value)
                 VALUES(:setting_key,:setting_value)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
            );
            foreach ($values as $key => $value) {
                $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
            }
            flash('success', 'Story settings were updated.');
        } elseif ($action === 'expire_stories') {
            $count = count(stories_expire_due(250));
            flash('success', $count . ' due stories were expired.');
        } else {
            throw new RuntimeException('Unsupported Stories action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('portal/stories.php');
}

$schemaAvailable = stories_schema_available();
$settings = stories_settings();
$stories = $schemaAvailable ? stories_feed($userId, 150) : [];
$localStories = array_values(array_filter(
    $stories,
    static fn(array $story): bool => (string)$story['direction'] === 'local'
));
$remoteStories = array_values(array_filter(
    $stories,
    static fn(array $story): bool => (string)$story['direction'] === 'remote'
));

portal_header('Stories', 'communications', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/stories-v66o.css?v=20260731-v66O'))?>">
<div class="stories-shell" data-stories-app data-story-view-endpoint="<?=e(app_url('api/story-view.php'))?>" data-csrf="<?=e(csrf_token())?>">
<section class="stories-hero">
<div>
<span class="stories-kicker">Followed feeds · Section 66O</span>
<h2>Stories that belong to your social graph.</h2>
<p>Publish 24-hour follower stories from this POD and view verified stories from actors you already follow. Remote media remains link-only and never auto-loads.</p>
</div>
<nav>
<a href="<?=e(app_url('portal/federated-feed.php'))?>">Federated Timeline</a>
<a href="<?=e(app_url('portal/admin.php?view=federation'))?>">Federation controls</a>
</nav>
</section>

<?php if(!$schemaAvailable):?>
<section class="stories-warning"><strong>Stories migration required.</strong> Import <code>database/stories_v66o.sql</code>.</section>
<?php else:?>

<section class="stories-stats">
<article><span>Your active stories</span><strong><?=count($localStories)?></strong></article>
<article><span>Following stories</span><strong><?=count($remoteStories)?></strong></article>
<article><span>Unviewed</span><strong><?=count(array_filter($stories,static fn(array $story):bool=>empty($story['first_viewed_at'])))?></strong></article>
<article><span>Remote media policy</span><strong>Link only</strong></article>
</section>

<?php stories_render_rail($userId, 40);?>

<div class="stories-layout">
<section class="stories-panel" id="storyComposer">
<header><div><span class="stories-kicker">Create</span><h2>New follower story</h2></div><b>Expires in <?=$settings['duration_hours']?> hours</b></header>
<form method="post" class="story-composer">
<?=csrf_field()?>
<input type="hidden" name="action" value="create_story">
<label><span>Title</span><input name="title" maxlength="200" placeholder="A quick update"></label>
<label><span>Story</span><textarea name="body_text" maxlength="4000" rows="7" placeholder="Share an update with approved followers."></textarea></label>
<div class="story-form-grid">
<label><span>Media type</span><select name="media_kind"><option value="none">No media</option><option value="image">Image</option><option value="audio">Audio</option><option value="video">Video</option><option value="link">Link card</option></select></label>
<label><span>Same-origin media URL</span><input name="media_url" maxlength="2048" placeholder="<?=e(app_url('storage/...'))?>"></label>
</div>
<label><span>Media description</span><input name="media_alt" maxlength="500" placeholder="Describe the media for accessibility"></label>
<label><span>Optional same-origin destination</span><input name="link_url" maxlength="2048" placeholder="<?=e(app_url('blog-post.php?...'))?>"></label>
<p class="story-policy-note">Delivery is restricted to approved ActivityPub followers. Stories are not end-to-end encrypted; recipients can retain content they receive.</p>
<button class="stories-button" type="submit">Publish story</button>
</form>
</section>

<section class="stories-panel">
<header><div><span class="stories-kicker">Policy</span><h2>Story controls</h2></div><b><?=$settings['enabled']?'Enabled':'Disabled'?></b></header>
<form method="post" class="story-settings">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_story_settings">
<label><input type="checkbox" name="stories_enabled" <?=$settings['enabled']?'checked':''?>><span><strong>Enable Stories</strong><small>Allow local publishing and feed display.</small></span></label>
<label><input type="checkbox" name="receive_remote" <?=$settings['receive_remote']?'checked':''?>><span><strong>Receive followed stories</strong><small>Only verified actors with an accepted Following relationship qualify.</small></span></label>
<label><span><strong>Duration</strong><small>1–48 hours</small></span><input type="number" name="duration_hours" min="1" max="48" value="<?=$settings['duration_hours']?>"></label>
<label><span><strong>Active limit</strong><small>Per local owner</small></span><input type="number" name="max_active" min="1" max="50" value="<?=$settings['max_active']?>"></label>
<div class="story-fixed-policy"><strong>Remote media: Link only</strong><span>No remote image, audio, video, iframe, or tracking pixel is loaded by the POD.</span></div>
<button class="stories-button secondary" type="submit">Save policy</button>
</form>
<form method="post" class="story-expire-form"><?=csrf_field()?><input type="hidden" name="action" value="expire_stories"><button class="stories-button secondary" type="submit">Process due expirations</button></form>
</section>
</div>

<section class="stories-panel stories-management">
<header><div><span class="stories-kicker">Active feed</span><h2>Manage stories</h2></div><span><?=count($stories)?> active</span></header>
<div class="story-management-list">
<?php foreach($stories as $story):
    $author = (string)($story['direction']==='local'
        ? ($story['owner_name']?:'Your POD')
        : ($story['remote_display_name']?:$story['remote_username']?:'Remote actor'));
?>
<article>
<div class="story-management-copy"><span><?=e(status_label((string)$story['direction']))?></span><strong><?=e($story['title']?:'Untitled story')?></strong><small><?=e($author)?> · expires <?=e(format_datetime((string)$story['expires_at']))?></small></div>
<div class="story-management-actions">
<?php if($story['direction']==='local'):?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_story"><input type="hidden" name="story_id" value="<?=(int)$story['id']?>"><button type="submit">Delete</button></form>
<?php else:?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="moderate_story"><input type="hidden" name="story_id" value="<?=(int)$story['id']?>"><input type="hidden" name="decision" value="hide"><button type="submit">Hide</button></form>
<?php endif;?>
</div>
</article>
<?php endforeach;?>
<?php if(!$stories):?><div class="stories-empty">No active stories are available.</div><?php endif;?>
</div>
</section>

<dialog class="story-viewer" data-story-dialog aria-label="Story viewer">
<div class="story-viewer-progress"><i data-story-progress></i></div>
<header><div><strong data-story-author></strong><span data-story-time></span></div><button type="button" data-story-close aria-label="Close story">×</button></header>
<main>
<div class="story-viewer-media" data-story-media hidden></div>
<span class="story-viewer-type" data-story-type></span>
<h2 data-story-title></h2>
<p data-story-body></p>
<a data-story-link target="_blank" rel="noopener noreferrer nofollow" hidden>Open story link</a>
</main>
<footer><button type="button" data-story-previous aria-label="Previous story">‹</button><button type="button" data-story-next aria-label="Next story">›</button></footer>
</dialog>

<?php endif;?>
</div>
<script src="<?=e(app_url('assets/js/stories-v66o.js?v=20260731-v66O'))?>"></script>
<?php portal_footer(); ?>

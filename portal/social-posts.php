<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-my-feed-v66Q9 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/social-posts-service.php';
require_once __DIR__ . '/stories-service.php';
require_once __DIR__ . '/federated-timeline.php';
require_once __DIR__ . '/my-feed-runtime.php';

$user = require_role('admin');
$userId = (int)$user['id'];

function my_feed_story_json(array $payload): string
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    return is_string($json) ? $json : '{}';
}

function my_feed_render_local_post(array $post): void
{
    $level = ob_get_level();

    try {
        ob_start();
        social_posts_render_card($post);
        $html = ob_get_clean();
        echo is_string($html) ? $html : '';
    } catch (Throwable $exception) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        my_feed_log_failure('local post rendering', $exception);
        ?>
        <div class="my-feed-empty" role="status">
            <strong>This post could not be displayed.</strong>
            <p>The remaining feed continues below.</p>
        </div>
        <?php
    }
}

if (is_post()) {
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Cross-origin request denied.');
    }

    verify_csrf();
    enforce_authenticated_action_limit($user);

    try {
        $action = input('action');

        if ($action === 'delete_post') {
            social_posts_delete(int_input('post_id'), $userId);
            flash('success', 'The post was deleted and a signed Tombstone was queued when required.');
        } elseif ($action === 'timeline_state') {
            federated_timeline_set_state(
                int_input('post_id'),
                $userId,
                input('state_action')
            );
        } elseif ($action === 'timeline_action') {
            $type = input('timeline_action');
            federated_timeline_action(
                int_input('post_id'),
                $type,
                $userId,
                input('reply_text')
            );
            flash('success', 'The signed federated ' . status_label($type) . ' activity was queued.');
        } elseif ($action === 'undo_timeline_action') {
            federated_timeline_undo_action(int_input('action_id'), $userId);
            flash('success', 'The signed Undo activity was queued.');
        } else {
            throw new RuntimeException('Unsupported Social Feed action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('portal/social-posts.php');
}

try {
    $socialEnabled = nmm_module_enabled('social_feed');
} catch (Throwable $exception) {
    my_feed_log_failure('Social Feed module setting', $exception);
    $socialEnabled = true;
}

try {
    $storiesEnabled = nmm_module_enabled('stories');
} catch (Throwable $exception) {
    my_feed_log_failure('Stories module setting', $exception);
    $storiesEnabled = true;
}

$storyResult = $storiesEnabled
    ? my_feed_load_stories($userId, 40)
    : ['available' => false, 'items' => []];
$storyItems = $storyResult['items'];

$socialSchemaAvailable = false;
$ownerPosts = [];
if ($socialEnabled) {
    try {
        $socialSchemaAvailable = social_posts_schema_available();
        if ($socialSchemaAvailable) {
            $ownerPosts = social_posts_owner_posts($userId, 150);
        }
    } catch (Throwable $exception) {
        my_feed_log_failure('local Social Feed query', $exception);
        $socialSchemaAvailable = false;
    }
}

$publishedPosts = array_values(array_filter(
    $ownerPosts,
    static fn(array $post): bool => (string)($post['status'] ?? '') === 'published'
));
$drafts = array_values(array_filter(
    $ownerPosts,
    static fn(array $post): bool => (string)($post['status'] ?? '') === 'draft'
));

$timelineResult = $socialEnabled
    ? my_feed_load_federated_posts($userId, 150)
    : ['available' => false, 'items' => [], 'actions_available' => false];
$remotePosts = $timelineResult['items'];
$remoteActions = $timelineResult['actions_available']
    ? my_feed_remote_actions($remotePosts)
    : [];
$followingCount = my_feed_following_count();

$feedItems = [];
foreach ($publishedPosts as $post) {
    $feedItems[] = [
        'kind' => 'local',
        'timestamp' => (string)($post['published_at'] ?? $post['created_at'] ?? ''),
        'record' => $post,
    ];
}
foreach ($remotePosts as $post) {
    $feedItems[] = [
        'kind' => 'remote',
        'timestamp' => (string)($post['source_published_at'] ?? $post['created_at'] ?? ''),
        'record' => $post,
    ];
}
usort(
    $feedItems,
    static fn(array $left, array $right): int => strcmp(
        (string)$right['timestamp'],
        (string)$left['timestamp']
    )
);

portal_header('My Feed', 'social-posts', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/stories-v66o.css?v=20260731-v66O'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-feed-v66q7.css?v=20260801-v66Q9'))?>">

<div
    class="my-feed"
    data-stories-app
    data-story-view-endpoint="<?=e(app_url('api/story-view.php'))?>"
    data-csrf="<?=e(csrf_token())?>"
    data-my-feed-runtime="v66Q.9"
>
    <?php if ($storiesEnabled): ?>
        <section class="stories-rail-panel my-feed-stories" aria-labelledby="storiesRailTitle">
            <header>
                <div>
                    <span>Stories</span>
                    <h2 id="storiesRailTitle">Recent stories</h2>
                </div>
                <a class="my-feed-story-create" href="<?=e(app_url('portal/publish-story.php'))?>" aria-label="Create story">+</a>
            </header>
            <div class="stories-rail" data-stories-rail>
                <a class="story-rail-create" href="<?=e(app_url('portal/publish-story.php'))?>">
                    <b>＋</b>
                    <span>Add story</span>
                </a>
                <?php foreach ($storyItems as $story): ?>
                    <?php
                    $direction = (string)($story['direction'] ?? 'local');
                    $storyAuthor = $direction === 'local'
                        ? (string)($story['owner_name'] ?? 'Your POD')
                        : (string)(
                            $story['remote_display_name']
                            ?? $story['remote_username']
                            ?? 'Remote user'
                        );
                    $storyPayload = [
                        'id' => (int)($story['id'] ?? 0),
                        'title' => (string)($story['title'] ?? ''),
                        'body' => (string)($story['body_text'] ?? ''),
                        'author' => $storyAuthor,
                        'published' => (string)($story['published_at'] ?? ''),
                        'expires' => (string)($story['expires_at'] ?? ''),
                        'media_kind' => (string)($story['media_kind'] ?? 'none'),
                        'media_url' => (string)($story['media_url'] ?? ''),
                        'media_alt' => (string)($story['media_alt'] ?? ''),
                        'link_url' => (string)($story['link_url'] ?? ''),
                        'direction' => $direction,
                        'load_media' => $direction === 'local',
                    ];
                    ?>
                    <button
                        class="story-rail-card <?=empty($story['first_viewed_at']) ? 'unviewed' : 'viewed'?>"
                        type="button"
                        data-story-open
                        data-story="<?=e(my_feed_story_json($storyPayload))?>"
                    >
                        <span class="story-rail-ring"><i><?=e(mb_strtoupper(mb_substr($storyAuthor, 0, 1)))?></i></span>
                        <strong><?=e($storyAuthor)?></strong>
                        <small><?=e((string)($story['title'] ?? 'New story'))?></small>
                    </button>
                <?php endforeach; ?>
                <?php if (!$storyItems): ?>
                    <div class="stories-rail-empty">No active stories yet. Create the first story or follow users to see theirs here.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="my-feed-stream" aria-labelledby="myFeedStreamTitle">
        <header class="my-feed-stream-header">
            <div>
                <span>Social Feed</span>
                <h2 id="myFeedStreamTitle">Latest posts</h2>
            </div>
            <?php if ($socialEnabled): ?>
                <a class="my-feed-create" href="<?=e(app_url('portal/publish-social-post.php'))?>">Create post</a>
            <?php endif; ?>
        </header>

        <?php if (!$socialEnabled): ?>
            <div class="my-feed-empty">
                <strong>Social Feed is disabled.</strong>
                <p>Enable Social Feed in Settings to publish posts and display followed content here.</p>
            </div>
        <?php elseif (!$socialSchemaAvailable): ?>
            <div class="my-feed-empty">
                <strong>Social Feed is unavailable.</strong>
                <p>Local posts could not be loaded. No feed data was changed.</p>
            </div>
        <?php else: ?>
            <?php if ($followingCount === 0): ?>
                <aside class="my-feed-get-started">
                    <div>
                        <strong>Follow users to build your feed.</strong>
                        <p>Follow users and their content will appear here.</p>
                    </div>
                    <a href="<?=e(app_url('portal/federated-feed.php'))?>">Find users</a>
                </aside>
            <?php endif; ?>

            <div class="my-feed-items">
                <?php foreach ($feedItems as $item): ?>
                    <?php if ($item['kind'] === 'local'): ?>
                        <?php $post = $item['record']; ?>
                        <article class="my-feed-item my-feed-item-local">
                            <?php my_feed_render_local_post($post); ?>
                            <div class="my-feed-item-actions">
                                <a href="<?=e(app_url('portal/publish-social-post.php?id=' . (int)($post['id'] ?? 0)))?>">Edit</a>
                                <form method="post" data-confirm data-confirm-title="Delete this social post?" data-confirm="The post will be removed and a signed Tombstone will be queued when required.">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?=(int)($post['id'] ?? 0)?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php else: ?>
                        <?php
                        $post = $item['record'];
                        $postId = (int)($post['id'] ?? 0);
                        $actions = $remoteActions[$postId] ?? [
                            'like' => null,
                            'announce' => null,
                            'replies' => [],
                        ];
                        $displayName = (string)(
                            $post['display_name']
                            ?? $post['preferred_username']
                            ?? 'Remote user'
                        );
                        $body = (string)($post['body_text'] ?? $post['summary'] ?? '');
                        $actorUrl = (string)($post['profile_url'] ?? $post['actor_uri'] ?? '');
                        $sourceUrl = (string)($post['source_url'] ?? '');
                        ?>
                        <article class="my-feed-item my-feed-item-remote">
                            <header class="my-feed-remote-header">
                                <span class="my-feed-avatar" aria-hidden="true"><?=e(mb_strtoupper(mb_substr($displayName, 0, 1)))?></span>
                                <div>
                                    <strong><?=e($displayName)?></strong>
                                    <?php if ($actorUrl !== ''): ?>
                                        <a href="<?=e($actorUrl)?>" target="_blank" rel="noopener noreferrer"><?=e((string)($post['actor_uri'] ?? $actorUrl))?></a>
                                    <?php endif; ?>
                                </div>
                                <time datetime="<?=e((string)$item['timestamp'])?>"><?=e(format_datetime((string)$item['timestamp']))?></time>
                            </header>

                            <?php if (!empty($post['title'])): ?><h3><?=e((string)$post['title'])?></h3><?php endif; ?>
                            <?php if ($body !== ''): ?><p class="my-feed-remote-body"><?=nl2br(e($body))?></p><?php endif; ?>

                            <div class="my-feed-remote-actions">
                                <?php if ($timelineResult['actions_available']): ?>
                                    <form method="post">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="action" value="<?=!empty($actions['like']) ? 'undo_timeline_action' : 'timeline_action'?>">
                                        <?php if (!empty($actions['like'])): ?>
                                            <input type="hidden" name="action_id" value="<?=(int)$actions['like']['id']?>">
                                        <?php else: ?>
                                            <input type="hidden" name="timeline_action" value="like">
                                            <input type="hidden" name="post_id" value="<?=$postId?>">
                                        <?php endif; ?>
                                        <button type="submit"><?=!empty($actions['like']) ? 'Unlike' : 'Like'?></button>
                                    </form>

                                    <form method="post">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="action" value="<?=!empty($actions['announce']) ? 'undo_timeline_action' : 'timeline_action'?>">
                                        <?php if (!empty($actions['announce'])): ?>
                                            <input type="hidden" name="action_id" value="<?=(int)$actions['announce']['id']?>">
                                        <?php else: ?>
                                            <input type="hidden" name="timeline_action" value="announce">
                                            <input type="hidden" name="post_id" value="<?=$postId?>">
                                        <?php endif; ?>
                                        <button type="submit"><?=!empty($actions['announce']) ? 'Undo boost' : 'Boost'?></button>
                                    </form>
                                <?php endif; ?>

                                <form method="post">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="timeline_state">
                                    <input type="hidden" name="state_action" value="<?=empty($post['saved_at']) ? 'save' : 'unsave'?>">
                                    <input type="hidden" name="post_id" value="<?=$postId?>">
                                    <button type="submit"><?=empty($post['saved_at']) ? 'Save' : 'Unsave'?></button>
                                </form>

                                <?php if ($sourceUrl !== ''): ?><a href="<?=e($sourceUrl)?>" target="_blank" rel="noopener noreferrer">Open original</a><?php endif; ?>
                            </div>

                            <?php if ($timelineResult['actions_available']): ?>
                                <form method="post" class="my-feed-reply">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="timeline_action">
                                    <input type="hidden" name="timeline_action" value="reply">
                                    <input type="hidden" name="post_id" value="<?=$postId?>">
                                    <label>
                                        <span class="sr-only">Reply to <?=e($displayName)?></span>
                                        <input name="reply_text" maxlength="4000" required placeholder="Write a reply">
                                    </label>
                                    <button type="submit">Reply</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (!$feedItems): ?>
                    <div class="my-feed-empty">
                        <strong>Your Social Feed is ready.</strong>
                        <p>Create a post or follow users and their content will appear here.</p>
                        <div>
                            <a href="<?=e(app_url('portal/publish-social-post.php'))?>">Create post</a>
                            <a href="<?=e(app_url('portal/federated-feed.php'))?>">Find users</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($drafts): ?>
                <section class="my-feed-drafts" aria-labelledby="myFeedDraftsTitle">
                    <header>
                        <span>Drafts</span>
                        <strong id="myFeedDraftsTitle"><?=count($drafts)?> saved draft<?=count($drafts) === 1 ? '' : 's'?></strong>
                    </header>
                    <div>
                        <?php foreach ($drafts as $draft): ?>
                            <a href="<?=e(app_url('portal/publish-social-post.php?id=' . (int)($draft['id'] ?? 0)))?>"><?=e(mb_substr((string)($draft['body_text'] ?? 'Untitled draft'), 0, 90))?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($storiesEnabled && $storyResult['available']): ?><?php stories_render_viewer(); ?><?php endif; ?>
</div>

<?php if ($storiesEnabled && $storyResult['available']): ?>
<script src="<?=e(app_url('assets/js/stories-v66o.js?v=20260801-v66Q9'))?>"></script>
<?php endif; ?>
<?php portal_footer(); ?>

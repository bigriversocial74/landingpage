<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-my-feed-v66Q7 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/social-posts-service.php';
require_once __DIR__ . '/stories-service.php';
require_once __DIR__ . '/federated-timeline.php';

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

$socialEnabled = nmm_module_enabled('social_feed');
$storiesEnabled = nmm_module_enabled('stories');
$socialSchemaAvailable = social_posts_schema_available();
$storiesSchemaAvailable = stories_schema_available();
$timelineSchemaAvailable = federated_timeline_schema_available();
$storyItems = ($storiesEnabled && $storiesSchemaAvailable)
    ? stories_feed($userId, 40)
    : [];

$ownerPosts = ($socialEnabled && $socialSchemaAvailable)
    ? social_posts_owner_posts($userId, 150)
    : [];
$publishedPosts = array_values(array_filter(
    $ownerPosts,
    static fn(array $post): bool => (string)$post['status'] === 'published'
));
$drafts = array_values(array_filter(
    $ownerPosts,
    static fn(array $post): bool => (string)$post['status'] === 'draft'
));
$remotePosts = ($socialEnabled && $timelineSchemaAvailable)
    ? federated_timeline_query($userId, ['queue' => 'following'], 150)
    : [];
$remoteActions = ($timelineSchemaAvailable && $remotePosts)
    ? federated_timeline_actions_for_posts(array_column($remotePosts, 'id'))
    : [];

$followingCount = 0;
try {
    $followingCount = (int)db()->query(
        'SELECT COUNT(*) FROM activitypub_following WHERE status="accepted"'
    )->fetchColumn();
} catch (Throwable) {
    $followingCount = 0;
}

$feedItems = [];
foreach ($publishedPosts as $post) {
    $feedItems[] = [
        'kind' => 'local',
        'timestamp' => (string)($post['published_at'] ?: $post['created_at']),
        'record' => $post,
    ];
}
foreach ($remotePosts as $post) {
    $feedItems[] = [
        'kind' => 'remote',
        'timestamp' => (string)($post['source_published_at'] ?: $post['created_at']),
        'record' => $post,
    ];
}
usort(
    $feedItems,
    static fn(array $left, array $right): int => strcmp($right['timestamp'], $left['timestamp'])
);

portal_header('My Feed', 'social-posts', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/stories-v66o.css?v=20260731-v66O'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-feed-v66q7.css?v=20260731-v66Q7'))?>">

<div
    class="my-feed"
    data-stories-app
    data-story-view-endpoint="<?=e(app_url('api/story-view.php'))?>"
    data-csrf="<?=e(csrf_token())?>"
>
    <?php if ($storiesEnabled): ?>
        <?php if ($storiesSchemaAvailable): ?>
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
                        $storyAuthor = (string)($story['direction'] === 'local'
                            ? ($story['owner_name'] ?: 'Your POD')
                            : ($story['remote_display_name'] ?: $story['remote_username'] ?: 'Remote user'));
                        $storyPayload = [
                            'id' => (int)$story['id'],
                            'title' => (string)($story['title'] ?? ''),
                            'body' => (string)($story['body_text'] ?? ''),
                            'author' => $storyAuthor,
                            'published' => (string)$story['published_at'],
                            'expires' => (string)$story['expires_at'],
                            'media_kind' => (string)$story['media_kind'],
                            'media_url' => (string)($story['media_url'] ?? ''),
                            'media_alt' => (string)($story['media_alt'] ?? ''),
                            'link_url' => (string)($story['link_url'] ?? ''),
                            'direction' => (string)$story['direction'],
                            'load_media' => (string)$story['direction'] === 'local',
                        ];
                        ?>
                        <button
                            class="story-rail-card <?=empty($story['first_viewed_at']) ? 'unviewed' : 'viewed'?>"
                            type="button"
                            data-story-open
                            data-story="<?=e(json_encode($storyPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))?>"
                        >
                            <span class="story-rail-ring"><i><?=e(mb_strtoupper(mb_substr($storyAuthor, 0, 1)))?></i></span>
                            <strong><?=e($storyAuthor)?></strong>
                            <small><?=e((string)($story['title'] ?: 'New story'))?></small>
                        </button>
                    <?php endforeach; ?>
                    <?php if (!$storyItems): ?>
                        <div class="stories-rail-empty">No active stories yet. Create the first story or follow users to see theirs here.</div>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <section class="my-feed-notice" role="status">
                <strong>Stories are temporarily unavailable.</strong>
                <span>The Stories service could not load. The Social Feed remains available below.</span>
            </section>
        <?php endif; ?>
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
                <strong>Social Feed is temporarily unavailable.</strong>
                <p>The page could not load the Social Posts service. No database migration is assumed.</p>
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
                            <?php social_posts_render_card($post); ?>
                            <div class="my-feed-item-actions">
                                <a href="<?=e(app_url('portal/publish-social-post.php?id=' . (int)$post['id']))?>">Edit</a>
                                <form
                                    method="post"
                                    data-confirm
                                    data-confirm-title="Delete this social post?"
                                    data-confirm="The post will be removed and a signed Tombstone will be queued when required."
                                >
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php else: ?>
                        <?php
                        $post = $item['record'];
                        $actions = $remoteActions[(int)$post['id']] ?? [
                            'like' => null,
                            'announce' => null,
                            'replies' => [],
                        ];
                        $displayName = (string)(
                            $post['display_name']
                            ?: $post['preferred_username']
                            ?: 'Remote user'
                        );
                        $body = (string)($post['body_text'] ?: $post['summary'] ?: '');
                        ?>
                        <article class="my-feed-item my-feed-item-remote">
                            <header class="my-feed-remote-header">
                                <span class="my-feed-avatar" aria-hidden="true"><?=e(mb_strtoupper(mb_substr($displayName, 0, 1)))?></span>
                                <div>
                                    <strong><?=e($displayName)?></strong>
                                    <a href="<?=e((string)($post['profile_url'] ?: $post['actor_uri']))?>" target="_blank" rel="noopener noreferrer">
                                        <?=e((string)$post['actor_uri'])?>
                                    </a>
                                </div>
                                <time datetime="<?=e($item['timestamp'])?>"><?=e(format_datetime($item['timestamp']))?></time>
                            </header>

                            <?php if (!empty($post['title'])): ?>
                                <h3><?=e((string)$post['title'])?></h3>
                            <?php endif; ?>
                            <?php if ($body !== ''): ?>
                                <p class="my-feed-remote-body"><?=nl2br(e($body))?></p>
                            <?php endif; ?>

                            <div class="my-feed-remote-actions">
                                <?php if ($actions['like']): ?>
                                    <form method="post">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="action" value="undo_timeline_action">
                                        <input type="hidden" name="action_id" value="<?=(int)$actions['like']['id']?>">
                                        <button type="submit">Unlike</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="action" value="timeline_action">
                                        <input type="hidden" name="timeline_action" value="like">
                                        <input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
                                        <button type="submit">Like</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($actions['announce']): ?>
                                    <form method="post">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="action" value="undo_timeline_action">
                                        <input type="hidden" name="action_id" value="<?=(int)$actions['announce']['id']?>">
                                        <button type="submit">Undo boost</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="action" value="timeline_action">
                                        <input type="hidden" name="timeline_action" value="announce">
                                        <input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
                                        <button type="submit">Boost</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="timeline_state">
                                    <input type="hidden" name="state_action" value="<?=empty($post['saved_at']) ? 'save' : 'unsave'?>">
                                    <input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
                                    <button type="submit"><?=empty($post['saved_at']) ? 'Save' : 'Unsave'?></button>
                                </form>

                                <a href="<?=e((string)$post['source_url'])?>" target="_blank" rel="noopener noreferrer">Open original</a>
                            </div>

                            <form method="post" class="my-feed-reply">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="timeline_action">
                                <input type="hidden" name="timeline_action" value="reply">
                                <input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
                                <label>
                                    <span class="sr-only">Reply to <?=e($displayName)?></span>
                                    <input name="reply_text" maxlength="4000" required placeholder="Write a reply">
                                </label>
                                <button type="submit">Reply</button>
                            </form>
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
                            <a href="<?=e(app_url('portal/publish-social-post.php?id=' . (int)$draft['id']))?>">
                                <?=e(mb_substr((string)($draft['body_text'] ?: 'Untitled draft'), 0, 90))?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($storiesEnabled && $storiesSchemaAvailable): ?>
        <?php stories_render_viewer(); ?>
    <?php endif; ?>
</div>

<?php if ($storiesEnabled && $storiesSchemaAvailable): ?>
    <script src="<?=e(app_url('assets/js/stories-v66o.js?v=20260731-v66O'))?>"></script>
<?php endif; ?>
<?php portal_footer(); ?>

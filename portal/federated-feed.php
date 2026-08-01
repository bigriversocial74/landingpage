<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-federated-feed-v66Q7 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/publishing.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/stories-service.php';
require_once __DIR__ . '/social-posts-service.php';
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
    $action = input('action');

    try {
        if ($action === 'save_timeline_settings') {
            $retention = max(7, min(730, int_input('retention_days', 90)));
            foreach ([
                'activitypub_timeline_enabled' => isset($_POST['timeline_enabled']) ? '1' : '0',
                'activitypub_timeline_store_following' => isset($_POST['store_following']) ? '1' : '0',
                'activitypub_timeline_receive_mentions' => isset($_POST['receive_mentions']) ? '1' : '0',
                'activitypub_timeline_retention_days' => (string)$retention,
                'activitypub_timeline_remote_media_mode' => 'link_only',
            ] as $key => $value) {
                publishing_save_setting($key, $value);
            }
            flash('success', 'Federated timeline settings were updated.');
        } elseif ($action === 'timeline_state') {
            federated_timeline_set_state(int_input('post_id'), $userId, input('state_action'));
        } elseif ($action === 'moderate_timeline_post') {
            federated_timeline_moderate(
                int_input('post_id'),
                input('decision'),
                $userId,
                input('note')
            );
            flash('success', 'The federated post moderation state was updated.');
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
        } elseif ($action === 'delete_timeline_reply') {
            federated_timeline_delete_reply(int_input('action_id'), $userId);
            flash('success', 'The federated reply deletion was queued.');
        } elseif ($action === 'discover_actor') {
            $actor = federated_timeline_resolve_actor_input(input('actor_input'));
            $_SESSION['federated_actor_discovery'] = [
                'actor_uri' => (string)$actor['actor_uri'],
                'display_name' => (string)(
                    $actor['display_name']
                    ?: $actor['preferred_username']
                    ?: 'Remote actor'
                ),
                'summary' => (string)($actor['summary'] ?? ''),
                'profile_url' => (string)($actor['profile_url'] ?: $actor['actor_uri']),
                'created_at' => time(),
            ];
            flash('success', 'The remote ActivityPub actor was verified.');
        } elseif ($action === 'follow_discovered_actor') {
            federated_interactions_follow_actor(input('actor_uri'), $userId);
            unset($_SESSION['federated_actor_discovery']);
            flash('success', 'The signed Follow activity was queued.');
        } elseif ($action === 'cleanup_timeline') {
            $deleted = federated_timeline_cleanup();
            flash('success', $deleted . ' expired unsaved timeline entries were removed.');
        } else {
            throw new RuntimeException('Unsupported federated timeline action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    $query = [];
    foreach (['queue', 'q', 'actor_id'] as $key) {
        $value = trim((string)($_POST['return_' . $key] ?? ''));
        if ($value !== '') {
            $query[$key] = $value;
        }
    }

    redirect('portal/federated-feed.php' . ($query ? '?' . http_build_query($query) : ''));
}

$schemaAvailable = federated_timeline_schema_available();
$settings = federated_timeline_settings();
$queue = trim((string)($_GET['queue'] ?? 'following'));
if (!in_array($queue, ['following', 'mentions', 'boosts', 'unread', 'saved', 'hidden', 'all'], true)) {
    $queue = 'following';
}
$search = trim((string)($_GET['q'] ?? ''));
$actorId = query_int('actor_id');
$posts = [];
$actionsByPost = [];
$actors = [];
$counts = [];

if ($schemaAvailable) {
    try {
        $posts = federated_timeline_query($userId, [
            'queue' => $queue,
            'q' => $search,
            'actor_id' => $actorId,
        ], 150);
        $actionsByPost = federated_timeline_actions_for_posts(array_column($posts, 'id'));
        $actors = db()->query(
            'SELECT DISTINCT actor.id,actor.actor_uri,actor.preferred_username,actor.display_name
             FROM activitypub_remote_posts post
             JOIN activitypub_remote_actors actor ON actor.id=post.remote_actor_id
             ORDER BY COALESCE(actor.display_name,actor.preferred_username,actor.actor_uri),actor.id'
        )->fetchAll();
        $counts = db()->query(
            'SELECT
                SUM(status="active") AS active_count,
                SUM(status="pending" AND mentions_local=1) AS pending_mentions,
                SUM(status="active" AND entry_type="announce") AS boost_count,
                SUM(status="hidden") AS hidden_count
             FROM activitypub_remote_posts'
        )->fetch() ?: [];
    } catch (Throwable $exception) {
        log_activity('federated_feed_load_failed', null, null, [
            'error' => mb_substr($exception->getMessage(), 0, 500),
        ]);
        $schemaAvailable = false;
    }
}

$discovery = $_SESSION['federated_actor_discovery'] ?? null;
if (!is_array($discovery) || time() - (int)($discovery['created_at'] ?? 0) > 900) {
    $discovery = null;
    unset($_SESSION['federated_actor_discovery']);
}

portal_header('Federated Timeline', 'federation', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/federated-timeline.css?v=20260731-v66Q7'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/stories-v66o.css?v=20260731-v66O'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/social-posts-v66p.css?v=20260731-v66P'))?>">

<div
    class="ft-shell"
    data-stories-app
    data-story-view-endpoint="<?=e(app_url('api/story-view.php'))?>"
    data-csrf="<?=e(csrf_token())?>"
>
    <section class="ft-panel ft-hero">
        <div>
            <span class="ft-kicker">Private open-social workspace</span>
            <h2>Your followed network, on your POD.</h2>
            <p>Read verified posts, review mentions, save entries, and send signed replies, likes, boosts, and Undo activities.</p>
        </div>
        <div class="ft-hero-actions">
            <a class="ft-button secondary" href="<?=e(app_url('portal/admin.php?view=federation'))?>">Federation controls</a>
            <a class="ft-button secondary" href="<?=e(app_url('portal/admin.php?view=delivery'))?>">Notification Delivery</a>
            <a class="ft-button secondary" href="<?=e(app_url('portal/admin.php?view=inbox'))?>">Unified Inbox</a>
        </div>
    </section>

    <?php if (nmm_module_enabled('stories') && stories_schema_available()): ?>
        <?php stories_render_rail($userId, 24); ?>
    <?php endif; ?>

    <?php if (nmm_module_enabled('social_feed') && social_posts_schema_available()): ?>
        <?php social_posts_render_portal_stream($userId, 8); ?>
    <?php endif; ?>

    <?php if (!$schemaAvailable): ?>
        <section class="ft-warning" role="status">
            <strong>Federated Timeline is temporarily unavailable.</strong>
            <span>The page could not verify its required schema or runtime services. Existing migrations are not assumed missing.</span>
        </section>
    <?php else: ?>
        <div class="ft-stats">
            <article><span>Active entries</span><strong><?=(int)($counts['active_count'] ?? 0)?></strong></article>
            <article><span>Pending mentions</span><strong><?=(int)($counts['pending_mentions'] ?? 0)?></strong></article>
            <article><span>Boost entries</span><strong><?=(int)($counts['boost_count'] ?? 0)?></strong></article>
            <article><span>Hidden entries</span><strong><?=(int)($counts['hidden_count'] ?? 0)?></strong></article>
        </div>

        <section class="ft-panel ft-settings">
            <header>
                <div><span class="ft-kicker">Privacy policy</span><h2>Timeline controls</h2></div>
                <strong><?=$settings['enabled'] ? 'Enabled' : 'Disabled'?></strong>
            </header>
            <form method="post" class="ft-settings-form">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_timeline_settings">
                <label><input type="checkbox" name="timeline_enabled" <?=$settings['enabled'] ? 'checked' : ''?>><span><strong>Enable private timeline ingestion</strong><small>Store verified activities only after signature validation.</small></span></label>
                <label><input type="checkbox" name="store_following" <?=$settings['store_following'] ? 'checked' : ''?>><span><strong>Store accepted Following posts</strong><small>Unfollowed actors cannot populate the home timeline.</small></span></label>
                <label><input type="checkbox" name="receive_mentions" <?=$settings['receive_mentions'] ? 'checked' : ''?>><span><strong>Quarantine direct mentions</strong><small>Unsolicited mentions remain pending until reviewed.</small></span></label>
                <label class="ft-retention"><span>Unsaved retention</span><input type="number" name="retention_days" min="7" max="730" value="<?=$settings['retention_days']?>"></label>
                <div class="ft-media-policy"><strong>Remote media: Link only</strong><span>Remote media is never auto-loaded.</span></div>
                <button class="ft-button" type="submit">Save timeline policy</button>
            </form>
        </section>

        <section class="ft-panel ft-discovery">
            <header><div><span class="ft-kicker">Actor discovery</span><h2>Find a remote user</h2></div></header>
            <form method="post" class="ft-discovery-form">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="discover_actor">
                <input name="actor_input" required placeholder="@name@example.social or actor URL">
                <button class="ft-button" type="submit">Verify actor</button>
            </form>
            <?php if ($discovery): ?>
                <article class="ft-discovery-result">
                    <div>
                        <strong><?=e($discovery['display_name'])?></strong>
                        <span><?=e($discovery['actor_uri'])?></span>
                        <p><?=e($discovery['summary'])?></p>
                        <a href="<?=e($discovery['profile_url'])?>" target="_blank" rel="noopener noreferrer nofollow">Open remote profile</a>
                    </div>
                    <form method="post">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="follow_discovered_actor">
                        <input type="hidden" name="actor_uri" value="<?=e($discovery['actor_uri'])?>">
                        <button class="ft-button" type="submit">Follow user</button>
                    </form>
                </article>
            <?php endif; ?>
        </section>

        <section class="ft-panel ft-toolbar">
            <nav aria-label="Timeline views">
                <?php foreach (['following'=>'Following','mentions'=>'Mentions','boosts'=>'Boosts','unread'=>'Unread','saved'=>'Saved','hidden'=>'Hidden','all'=>'All'] as $key => $label): ?>
                    <a
                        class="<?=$queue === $key ? 'active' : ''?>"
                        href="<?=e(app_url('portal/federated-feed.php?' . http_build_query(['queue'=>$key,'q'=>$search,'actor_id'=>$actorId])))?>"
                    ><?=e($label)?></a>
                <?php endforeach; ?>
            </nav>
            <form method="get">
                <input type="hidden" name="queue" value="<?=e($queue)?>">
                <input name="q" value="<?=e($search)?>" placeholder="Search posts or users">
                <select name="actor_id">
                    <option value="0">All users</option>
                    <?php foreach ($actors as $actor): ?>
                        <option value="<?=(int)$actor['id']?>" <?=(int)$actor['id'] === $actorId ? 'selected' : ''?>>
                            <?=e($actor['display_name'] ?: $actor['preferred_username'] ?: $actor['actor_uri'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="ft-button secondary" type="submit">Filter</button>
            </form>
        </section>

        <section class="ft-stream" aria-label="Federated timeline">
            <?php if (!$posts): ?>
                <div class="ft-empty">Follow users and their content will appear here.</div>
            <?php endif; ?>

            <?php foreach ($posts as $post): ?>
                <?php
                $postActions = $actionsByPost[(int)$post['id']] ?? [
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
                <article class="ft-card" id="remote-post-<?=(int)$post['id']?>">
                    <header>
                        <div>
                            <span class="ft-type"><?=e(status_label((string)$post['entry_type']))?></span>
                            <h2><?=e($displayName)?></h2>
                            <a href="<?=e($post['profile_url'] ?: $post['actor_uri'])?>" target="_blank" rel="noopener noreferrer nofollow"><?=e($post['actor_uri'])?></a>
                        </div>
                        <time><?=e(format_datetime((string)($post['source_published_at'] ?: $post['created_at'])))?></time>
                    </header>

                    <?php if (!empty($post['title'])): ?><h3><?=e($post['title'])?></h3><?php endif; ?>
                    <?php if ($body !== ''): ?><p class="ft-body"><?=nl2br(e($body))?></p><?php endif; ?>

                    <div class="ft-actions">
                        <form method="post">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="timeline_state">
                            <input type="hidden" name="state_action" value="<?=empty($post['saved_at']) ? 'save' : 'unsave'?>">
                            <input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
                            <button class="ft-button secondary" type="submit"><?=empty($post['saved_at']) ? 'Save' : 'Unsave'?></button>
                        </form>

                        <?php if ($postActions['like']): ?>
                            <form method="post">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="undo_timeline_action">
                                <input type="hidden" name="action_id" value="<?=(int)$postActions['like']['id']?>">
                                <button class="ft-button secondary" type="submit">Unlike</button>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="timeline_action">
                                <input type="hidden" name="timeline_action" value="like">
                                <input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
                                <button class="ft-button secondary" type="submit">Like</button>
                            </form>
                        <?php endif; ?>

                        <a class="ft-button secondary" href="<?=e($post['source_url'])?>" target="_blank" rel="noopener noreferrer nofollow">Open original</a>
                    </div>

                    <form method="post" class="ft-reply">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="timeline_action">
                        <input type="hidden" name="timeline_action" value="reply">
                        <input type="hidden" name="post_id" value="<?=(int)$post['id']?>">
                        <input name="reply_text" maxlength="4000" required placeholder="Write a reply">
                        <button class="ft-button" type="submit">Reply</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (nmm_module_enabled('stories') && stories_schema_available()): ?>
        <?php stories_render_viewer(); ?>
    <?php endif; ?>
</div>

<?php if (nmm_module_enabled('stories') && stories_schema_available()): ?>
    <script src="<?=e(app_url('assets/js/stories-v66o.js?v=20260731-v66O'))?>"></script>
<?php endif; ?>
<?php portal_footer(); ?>

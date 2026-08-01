<?php
declare(strict_types=1);

/* North Mountain Media build: 20260801-my-feed-runtime-v66Q9 */

function my_feed_table_columns(string $table): array
{
    static $cache = [];

    if (!preg_match('/^[a-z0-9_]+$/i', $table)) {
        return [];
    }
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $statement = db()->prepare(
            'SELECT column_name
             FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name=:table_name'
        );
        $statement->execute(['table_name' => $table]);
        $columns = array_map(
            'strval',
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
        return $cache[$table] = array_fill_keys($columns, true);
    } catch (Throwable $exception) {
        error_log('[My Feed] Column inspection failed for ' . $table . ': ' . $exception->getMessage());
        return $cache[$table] = [];
    }
}

function my_feed_table_has_columns(string $table, array $required): bool
{
    $columns = my_feed_table_columns($table);
    if (!$columns) {
        return false;
    }

    foreach ($required as $column) {
        if (!isset($columns[$column])) {
            return false;
        }
    }

    return true;
}

function my_feed_log_failure(string $service, Throwable $exception): void
{
    error_log(sprintf(
        '[My Feed v66Q.9] %s failed: %s in %s:%d',
        $service,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
}

function my_feed_stories_capabilities(): array
{
    $core = my_feed_table_has_columns('pod_stories', [
        'id',
        'direction',
        'owner_user_id',
        'remote_actor_id',
        'title',
        'body_text',
        'media_kind',
        'media_url',
        'media_alt',
        'link_url',
        'status',
        'published_at',
        'expires_at',
    ])
        && my_feed_table_has_columns('pod_story_views', [
            'story_id',
            'viewer_user_id',
            'first_viewed_at',
            'last_viewed_at',
            'view_count',
        ])
        && my_feed_table_has_columns('users', ['id', 'display_name']);

    $remote = $core
        && my_feed_table_has_columns('activitypub_remote_actors', [
            'id',
            'actor_uri',
            'preferred_username',
            'display_name',
            'profile_url',
            'avatar_url',
            'status',
        ])
        && my_feed_table_has_columns('activitypub_following', [
            'remote_actor_id',
            'status',
        ]);

    return [
        'core' => $core,
        'remote' => $remote,
        'actor_controls' => my_feed_table_has_columns('activitypub_actor_controls', [
            'remote_actor_id',
            'moderation_status',
        ]),
    ];
}

function my_feed_load_stories(int $userId, int $limit = 40): array
{
    $limit = max(1, min(100, $limit));
    $capabilities = my_feed_stories_capabilities();
    $items = [];
    $localLoaded = false;
    $remoteLoaded = false;

    if (!$capabilities['core'] || $userId <= 0) {
        return [
            'available' => false,
            'items' => [],
            'local_loaded' => false,
            'remote_loaded' => false,
        ];
    }

    try {
        $statement = db()->prepare(
            'SELECT story.*,
                    owner.display_name AS owner_name,
                    NULL AS remote_display_name,
                    NULL AS remote_username,
                    NULL AS actor_uri,
                    NULL AS profile_url,
                    NULL AS avatar_url,
                    story_view.first_viewed_at,
                    story_view.last_viewed_at,
                    story_view.view_count
             FROM pod_stories AS story
             LEFT JOIN users AS owner ON owner.id=story.owner_user_id
             LEFT JOIN pod_story_views AS story_view
               ON story_view.story_id=story.id
              AND story_view.viewer_user_id=:viewer_user_id
             WHERE story.direction="local"
               AND story.status="active"
               AND story.expires_at>UTC_TIMESTAMP()
             ORDER BY story.published_at DESC,story.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(['viewer_user_id' => $userId]);
        $items = $statement->fetchAll();
        $localLoaded = true;
    } catch (Throwable $exception) {
        my_feed_log_failure('local Stories query', $exception);
    }

    if ($capabilities['remote']) {
        try {
            $controlJoin = $capabilities['actor_controls']
                ? 'LEFT JOIN activitypub_actor_controls AS actor_control
                     ON actor_control.remote_actor_id=actor.id'
                : '';
            $controlFilter = $capabilities['actor_controls']
                ? 'AND COALESCE(actor_control.moderation_status,"active")<>"blocked"'
                : '';

            $statement = db()->prepare(
                'SELECT story.*,
                        NULL AS owner_name,
                        actor.display_name AS remote_display_name,
                        actor.preferred_username AS remote_username,
                        actor.actor_uri,
                        actor.profile_url,
                        actor.avatar_url,
                        story_view.first_viewed_at,
                        story_view.last_viewed_at,
                        story_view.view_count
                 FROM pod_stories AS story
                 JOIN activitypub_remote_actors AS actor
                   ON actor.id=story.remote_actor_id
                 JOIN activitypub_following AS accepted_follow
                   ON accepted_follow.remote_actor_id=story.remote_actor_id
                  AND accepted_follow.status="accepted"
                 ' . $controlJoin . '
                 LEFT JOIN pod_story_views AS story_view
                   ON story_view.story_id=story.id
                  AND story_view.viewer_user_id=:viewer_user_id
                 WHERE story.direction="remote"
                   AND story.status="active"
                   AND story.expires_at>UTC_TIMESTAMP()
                   AND actor.status<>"blocked"
                   ' . $controlFilter . '
                 ORDER BY story.published_at DESC,story.id DESC
                 LIMIT ' . $limit
            );
            $statement->execute(['viewer_user_id' => $userId]);
            $items = array_merge($items, $statement->fetchAll());
            $remoteLoaded = true;
        } catch (Throwable $exception) {
            my_feed_log_failure('remote Stories query', $exception);
        }
    }

    usort(
        $items,
        static fn(array $left, array $right): int => strcmp(
            (string)($right['published_at'] ?? ''),
            (string)($left['published_at'] ?? '')
        ) ?: ((int)($right['id'] ?? 0) <=> (int)($left['id'] ?? 0))
    );

    return [
        'available' => $localLoaded || $remoteLoaded,
        'items' => array_slice($items, 0, $limit),
        'local_loaded' => $localLoaded,
        'remote_loaded' => $remoteLoaded,
    ];
}

function my_feed_timeline_capabilities(): array
{
    $core = my_feed_table_has_columns('activitypub_remote_posts', [
        'id',
        'remote_actor_id',
        'entry_type',
        'source_url',
        'title',
        'summary',
        'body_text',
        'status',
        'source_published_at',
        'created_at',
    ])
        && my_feed_table_has_columns('activitypub_remote_actors', [
            'id',
            'actor_uri',
            'preferred_username',
            'display_name',
            'profile_url',
            'status',
        ])
        && my_feed_table_has_columns('activitypub_timeline_user_state', [
            'remote_post_id',
            'user_id',
            'read_at',
            'saved_at',
            'hidden_at',
        ])
        && my_feed_table_has_columns('activitypub_following', [
            'remote_actor_id',
            'status',
        ]);

    return [
        'core' => $core,
        'actions' => my_feed_table_has_columns('activitypub_remote_post_actions', [
            'id',
            'remote_post_id',
            'action_type',
            'status',
        ]),
        'actor_controls' => my_feed_table_has_columns('activitypub_actor_controls', [
            'remote_actor_id',
            'moderation_status',
        ]),
    ];
}

function my_feed_load_federated_posts(int $userId, int $limit = 150): array
{
    $limit = max(1, min(250, $limit));
    $capabilities = my_feed_timeline_capabilities();

    if (!$capabilities['core'] || $userId <= 0) {
        return ['available' => false, 'items' => [], 'actions_available' => false];
    }

    $controlJoin = $capabilities['actor_controls']
        ? 'LEFT JOIN activitypub_actor_controls AS actor_control
             ON actor_control.remote_actor_id=actor.id'
        : '';
    $controlSelect = $capabilities['actor_controls']
        ? 'COALESCE(actor_control.moderation_status,"active") AS moderation_status'
        : '"active" AS moderation_status';
    $controlFilter = $capabilities['actor_controls']
        ? 'AND COALESCE(actor_control.moderation_status,"active")<>"blocked"'
        : '';
    $actionSelect = $capabilities['actions']
        ? '(SELECT COUNT(*) FROM activitypub_remote_post_actions AS post_action
             WHERE post_action.remote_post_id=remote_post.id
               AND post_action.action_type="like"
               AND post_action.status="active") AS liked,
           (SELECT COUNT(*) FROM activitypub_remote_post_actions AS post_action
             WHERE post_action.remote_post_id=remote_post.id
               AND post_action.action_type="announce"
               AND post_action.status="active") AS boosted'
        : '0 AS liked,0 AS boosted';

    try {
        $statement = db()->prepare(
            'SELECT remote_post.*,
                    actor.actor_uri,
                    actor.preferred_username,
                    actor.display_name,
                    actor.profile_url,
                    timeline_state.read_at,
                    timeline_state.saved_at,
                    timeline_state.hidden_at,
                    ' . $controlSelect . ',
                    ' . $actionSelect . '
             FROM activitypub_remote_posts AS remote_post
             JOIN activitypub_remote_actors AS actor
               ON actor.id=remote_post.remote_actor_id
             JOIN activitypub_following AS accepted_follow
               ON accepted_follow.remote_actor_id=remote_post.remote_actor_id
              AND accepted_follow.status="accepted"
             LEFT JOIN activitypub_timeline_user_state AS timeline_state
               ON timeline_state.remote_post_id=remote_post.id
              AND timeline_state.user_id=:user_id
             ' . $controlJoin . '
             WHERE remote_post.status="active"
               AND timeline_state.hidden_at IS NULL
               AND actor.status<>"blocked"
               ' . $controlFilter . '
             ORDER BY COALESCE(remote_post.source_published_at,remote_post.created_at) DESC,
                      remote_post.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(['user_id' => $userId]);

        return [
            'available' => true,
            'items' => $statement->fetchAll(),
            'actions_available' => $capabilities['actions'],
        ];
    } catch (Throwable $exception) {
        my_feed_log_failure('federated timeline query', $exception);
        return ['available' => false, 'items' => [], 'actions_available' => false];
    }
}

function my_feed_remote_actions(array $posts): array
{
    if (!$posts || !my_feed_timeline_capabilities()['actions']) {
        return [];
    }

    try {
        return federated_timeline_actions_for_posts(
            array_map('intval', array_column($posts, 'id'))
        );
    } catch (Throwable $exception) {
        my_feed_log_failure('federated action lookup', $exception);
        return [];
    }
}

function my_feed_following_count(): int
{
    if (!my_feed_table_has_columns('activitypub_following', ['status'])) {
        return 0;
    }

    try {
        return (int)db()->query(
            'SELECT COUNT(*) FROM activitypub_following WHERE status="accepted"'
        )->fetchColumn();
    } catch (Throwable $exception) {
        my_feed_log_failure('following count', $exception);
        return 0;
    }
}

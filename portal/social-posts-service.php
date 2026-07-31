<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-pod-social-posts-v66P */

function social_posts_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN ("pod_social_posts","pod_social_post_events")'
        );
        return $available = (int)$statement->fetchColumn() === 2;
    } catch (Throwable) {
        return $available = false;
    }
}

function social_posts_require_schema(): void
{
    if (!social_posts_schema_available()) {
        throw new RuntimeException(
            'Import database/social_posts_v66p.sql before using Social Posts.'
        );
    }
}

function social_posts_settings(): array
{
    $mode = strtolower(trim((string)setting('social_posts_landing_mode', 'tabs')));
    if (!in_array($mode, ['none', 'blogs', 'social', 'tabs'], true)) $mode = 'tabs';
    $visibility = strtolower(trim((string)setting('social_posts_default_visibility', 'public')));
    if (!in_array($visibility, ['public', 'followers'], true)) $visibility = 'public';
    return [
        'enabled' => (string)setting('social_posts_enabled', '1') !== '0',
        'default_visibility' => $visibility,
        'allow_public' => (string)setting('social_posts_allow_public', '1') !== '0',
        'landing_mode' => $mode,
        'landing_limit' => max(1, min(12, (int)setting('social_posts_landing_limit', '6'))),
        'show_follow_button' => (string)setting('social_posts_show_follow_button', '1') !== '0',
    ];
}

function social_posts_save_settings(array $values): void
{
    $statement = db()->prepare(
        'INSERT INTO settings(setting_key,setting_value)
         VALUES(:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ($values as $key => $value) {
        $statement->execute([
            'setting_key' => (string)$key,
            'setting_value' => (string)$value,
        ]);
    }
}

function social_posts_clean_text(string $value, int $limit): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim(preg_replace('/[\t ]+/u', ' ', str_replace(["\r\n", "\r"], "\n", $value)) ?? '');
    $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;
    return mb_substr($value, 0, $limit);
}

function social_posts_same_origin_url(string $value, string $label): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (str_starts_with($value, '/')) $value = app_url(ltrim($value, '/'));
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        throw new RuntimeException($label . ' URL is invalid.');
    }
    $base = parse_url(app_url());
    $candidate = parse_url($value);
    if (!is_array($base) || !is_array($candidate)) {
        throw new RuntimeException($label . ' URL is invalid.');
    }
    $baseScheme = strtolower((string)($base['scheme'] ?? ''));
    $candidateScheme = strtolower((string)($candidate['scheme'] ?? ''));
    $baseHost = strtolower((string)($base['host'] ?? ''));
    $candidateHost = strtolower((string)($candidate['host'] ?? ''));
    $basePort = (int)($base['port'] ?? ($baseScheme === 'https' ? 443 : 80));
    $candidatePort = (int)($candidate['port'] ?? ($candidateScheme === 'https' ? 443 : 80));
    if (
        $baseScheme === '' || $baseHost === ''
        || $candidateScheme !== $baseScheme
        || $candidateHost !== $baseHost
        || $candidatePort !== $basePort
        || !in_array($candidateScheme, ['http', 'https'], true)
        || isset($candidate['user']) || isset($candidate['pass'])
    ) {
        throw new RuntimeException($label . ' must use protected same-origin storage.');
    }
    return mb_substr($value, 0, 2048);
}

function social_posts_link_url(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (str_starts_with($value, '/')) $value = app_url(ltrim($value, '/'));
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('The post link URL is invalid.');
    }
    $candidate = parse_url($value);
    $base = parse_url(app_url());
    if (!is_array($candidate) || isset($candidate['user']) || isset($candidate['pass'])) {
        throw new RuntimeException('The post link URL is invalid.');
    }
    $scheme = strtolower((string)($candidate['scheme'] ?? ''));
    $sameOrigin = is_array($base)
        && strtolower((string)($base['scheme'] ?? '')) === $scheme
        && strtolower((string)($base['host'] ?? '')) === strtolower((string)($candidate['host'] ?? ''))
        && (int)($base['port'] ?? ($scheme === 'https' ? 443 : 80))
            === (int)($candidate['port'] ?? ($scheme === 'https' ? 443 : 80));
    if ($scheme !== 'https' && !$sameOrigin) {
        throw new RuntimeException('External post links must use HTTPS.');
    }
    return mb_substr($value, 0, 2048);
}

function social_posts_find(int $postId): ?array
{
    if ($postId <= 0 || !social_posts_schema_available()) return null;
    $statement = db()->prepare(
        'SELECT post.*,owner.display_name AS owner_name
         FROM pod_social_posts post
         LEFT JOIN users owner ON owner.id=post.owner_user_id
         WHERE post.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $postId]);
    return $statement->fetch() ?: null;
}

function social_posts_find_uuid(string $uuid): ?array
{
    if (!social_posts_schema_available() || !activitypub_valid_uuid($uuid)) return null;
    $statement = db()->prepare(
        'SELECT post.*,owner.display_name AS owner_name
         FROM pod_social_posts post
         LEFT JOIN users owner ON owner.id=post.owner_user_id
         WHERE post.post_uuid=:uuid LIMIT 1'
    );
    $statement->execute(['uuid' => strtolower($uuid)]);
    return $statement->fetch() ?: null;
}

function social_posts_object_url(array $post): string
{
    return app_url(
        'activitypub-social-post.php?id=' . rawurlencode((string)$post['post_uuid'])
    );
}

function social_posts_public_url(array $post): string
{
    return app_url('social-post.php?id=' . rawurlencode((string)$post['post_uuid']));
}

function social_posts_audience(array $post): array
{
    if ((string)($post['visibility'] ?? 'public') === 'followers') {
        return ['to' => [activitypub_followers_url()], 'cc' => []];
    }
    return [
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => [activitypub_followers_url()],
    ];
}

function social_posts_hashtags(string $body): array
{
    preg_match_all('/(?:^|\s)#([\p{L}\p{N}_]{1,64})/u', $body, $matches);
    $tags = [];
    foreach (array_unique($matches[1] ?? []) as $name) {
        $tags[] = ['type' => 'Hashtag', 'name' => '#' . $name];
        if (count($tags) >= 20) break;
    }
    return $tags;
}

function social_posts_activity_object(array $post): array
{
    $audience = social_posts_audience($post);
    $published = strtotime((string)($post['published_at'] ?? '')) ?: time();
    $object = [
        'id' => social_posts_object_url($post),
        'type' => 'Note',
        'attributedTo' => activitypub_actor_url(),
        'published' => gmdate(DATE_ATOM, $published),
        'to' => $audience['to'],
        'cc' => $audience['cc'],
        'content' => nl2br(e((string)($post['body_text'] ?? ''))),
        'url' => social_posts_public_url($post),
        'sensitive' => false,
    ];
    if (!empty($post['edited_at'])) {
        $object['updated'] = gmdate(
            DATE_ATOM,
            strtotime((string)$post['edited_at']) ?: $published
        );
    }
    $tags = social_posts_hashtags((string)($post['body_text'] ?? ''));
    if ($tags) $object['tag'] = $tags;
    $attachments = [];
    if (!empty($post['media_url'])) {
        $attachments[] = [
            'type' => match ((string)$post['media_kind']) {
                'image' => 'Image',
                'audio' => 'Audio',
                'video' => 'Video',
                default => 'Link',
            },
            'url' => (string)$post['media_url'],
            'name' => (string)($post['media_alt'] ?? ''),
        ];
    }
    if (!empty($post['link_url'])) {
        $attachments[] = [
            'type' => 'Link',
            'href' => (string)$post['link_url'],
            'name' => (string)$post['link_url'],
        ];
    }
    if ($attachments) $object['attachment'] = $attachments;
    return $object;
}

function social_posts_record_event(
    int $postId,
    string $eventType,
    string $eventKey,
    ?int $actorUserId = null,
    array $metadata = []
): void {
    if ($postId <= 0 || !social_posts_schema_available()) return;
    $eventType = mb_substr(trim($eventType), 0, 80);
    if ($eventType === '') return;
    $hash = hash('sha256', implode('|', [
        'social-posts-v66p',
        (string)$postId,
        $eventType,
        $eventKey,
    ]));
    db()->prepare(
        'INSERT IGNORE INTO pod_social_post_events
            (social_post_id,event_type,actor_user_id,event_sha256,metadata_json)
         VALUES
            (:post_id,:event_type,:actor_user_id,:event_sha256,:metadata_json)'
    )->execute([
        'post_id' => $postId,
        'event_type' => $eventType,
        'actor_user_id' => ($actorUserId ?? 0) > 0 ? $actorUserId : null,
        'event_sha256' => $hash,
        'metadata_json' => $metadata
            ? json_encode(
                $metadata,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
              )
            : null,
    ]);
}

function social_posts_publish_activity(
    array $post,
    string $activityType,
    ?int $actorUserId
): int {
    if (
        !in_array($activityType, ['Create', 'Update', 'Delete'], true)
        || !activitypub_schema_available()
        || !activitypub_settings()['enabled']
    ) {
        return 0;
    }
    $objectUri = social_posts_object_url($post);
    $version = (string)($post['updated_at'] ?? $post['published_at'] ?? '');
    if ($activityType === 'Delete') {
        $version .= '|' . (string)($post['deleted_at'] ?? gmdate('Y-m-d H:i:s'));
    }
    $uuid = activitypub_uuid_from_seed(
        'social-posts-v66p|' . $activityType . '|'
        . (string)$post['post_uuid'] . '|' . $version
    );
    $audience = social_posts_audience($post);
    $object = $activityType === 'Delete'
        ? [
            'id' => $objectUri,
            'type' => 'Tombstone',
            'formerType' => 'Note',
            'deleted' => gmdate(DATE_ATOM),
          ]
        : social_posts_activity_object($post);
    $activity = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_activity_url($uuid),
        'type' => $activityType,
        'actor' => activitypub_actor_url(),
        'published' => gmdate(DATE_ATOM),
        'to' => $audience['to'],
        'cc' => $audience['cc'],
        'object' => $object,
    ];
    $outboxId = activitypub_store_outbox_activity(
        $activity,
        $activityType,
        $activityType === 'Delete' ? 'Tombstone' : 'Note',
        $objectUri,
        null,
        $actorUserId
    );
    $queued = activitypub_queue_approved_followers($outboxId);
    social_posts_record_event(
        (int)$post['id'],
        'federation_' . strtolower($activityType),
        (string)$outboxId,
        $actorUserId,
        ['outbox_activity_id' => $outboxId, 'queued_deliveries' => $queued]
    );
    return $outboxId;
}

function social_posts_normalize_input(array $input): array
{
    $settings = social_posts_settings();
    $body = social_posts_clean_text((string)($input['body_text'] ?? ''), 8000);
    $mediaKind = strtolower(trim((string)($input['media_kind'] ?? 'none')));
    if (!in_array($mediaKind, ['none', 'image', 'audio', 'video', 'link'], true)) {
        $mediaKind = 'none';
    }
    $mediaUrl = social_posts_same_origin_url(
        (string)($input['media_url'] ?? ''),
        'Post media'
    );
    if ($mediaUrl === '') $mediaKind = 'none';
    $mediaAlt = social_posts_clean_text((string)($input['media_alt'] ?? ''), 500);
    $linkUrl = social_posts_link_url((string)($input['link_url'] ?? ''));
    $visibility = strtolower(trim((string)($input['visibility'] ?? $settings['default_visibility'])));
    if (!in_array($visibility, ['public', 'followers'], true)) {
        $visibility = $settings['default_visibility'];
    }
    if (!$settings['allow_public'] && $visibility === 'public') $visibility = 'followers';
    if ($body === '' && $mediaUrl === '' && $linkUrl === '') {
        throw new RuntimeException('Add text, protected media, or a link to the post.');
    }
    return [
        'body_text' => $body !== '' ? $body : null,
        'media_kind' => $mediaKind,
        'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
        'media_alt' => $mediaAlt !== '' ? $mediaAlt : null,
        'link_url' => $linkUrl !== '' ? $linkUrl : null,
        'visibility' => $visibility,
        'body_sha256' => hash('sha256', implode('|', [
            $body,
            $mediaKind,
            $mediaUrl,
            $mediaAlt,
            $linkUrl,
            $visibility,
        ])),
    ];
}

function social_posts_create(array $input, int $userId, bool $publish): int
{
    social_posts_require_schema();
    if (!social_posts_settings()['enabled']) {
        throw new RuntimeException('Social post publishing is disabled.');
    }
    if ($userId <= 0) throw new RuntimeException('A valid post owner is required.');
    $values = social_posts_normalize_input($input);
    $uuid = strtolower(pod_uuid_v4());
    $status = $publish ? 'published' : 'draft';
    db()->beginTransaction();
    try {
        db()->prepare(
            'INSERT INTO pod_social_posts
                (post_uuid,owner_user_id,body_text,body_sha256,media_kind,
                 media_url,media_alt,link_url,visibility,status,published_at)
             VALUES
                (:post_uuid,:owner_user_id,:body_text,:body_sha256,:media_kind,
                 :media_url,:media_alt,:link_url,:visibility,:status,
                 CASE WHEN :published="published" THEN UTC_TIMESTAMP() ELSE NULL END)'
        )->execute($values + [
            'post_uuid' => $uuid,
            'owner_user_id' => $userId,
            'status' => $status,
            'published' => $status,
        ]);
        $postId = (int)db()->lastInsertId();
        social_posts_record_event(
            $postId,
            $publish ? 'created_published' : 'created_draft',
            $uuid,
            $userId,
            ['visibility' => $values['visibility']]
        );
        db()->commit();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        throw $exception;
    }
    if ($publish && ($post = social_posts_find($postId))) {
        try {
            social_posts_publish_activity($post, 'Create', $userId);
        } catch (Throwable $exception) {
            log_activity('social_post_federation_failed', 'social_post', $postId, [
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
        }
    }
    log_activity('social_post_' . ($publish ? 'published' : 'drafted'), 'social_post', $postId);
    return $postId;
}

function social_posts_update(int $postId, array $input, int $userId, bool $publish): void
{
    social_posts_require_schema();
    $current = social_posts_find($postId);
    if (!$current || (string)$current['status'] === 'deleted') {
        throw new RuntimeException('The social post was not found.');
    }
    if ((int)($current['owner_user_id'] ?? 0) !== $userId) {
        throw new RuntimeException('You do not own this social post.');
    }
    if ((string)$current['status'] === 'published' && !$publish) {
        throw new RuntimeException('Published posts cannot return to draft. Edit or delete the post instead.');
    }
    $values = social_posts_normalize_input($input);
    $wasPublished = (string)$current['status'] === 'published';
    $status = $publish ? 'published' : 'draft';
    db()->prepare(
        'UPDATE pod_social_posts
         SET body_text=:body_text,body_sha256=:body_sha256,
             media_kind=:media_kind,media_url=:media_url,media_alt=:media_alt,
             link_url=:link_url,visibility=:visibility,status=:status,
             published_at=CASE
                WHEN :publish_state="published" THEN COALESCE(published_at,UTC_TIMESTAMP())
                ELSE published_at END,
             edited_at=CASE WHEN :edit_state="published" THEN UTC_TIMESTAMP() ELSE edited_at END,
             updated_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute($values + [
        'status' => $status,
        'publish_state' => $status,
        'edit_state' => $status,
        'id' => $postId,
    ]);
    $post = social_posts_find($postId);
    social_posts_record_event(
        $postId,
        $publish ? ($wasPublished ? 'updated_published' : 'draft_published') : 'updated_draft',
        (string)($post['updated_at'] ?? gmdate('c')),
        $userId,
        ['visibility' => $values['visibility']]
    );
    if ($publish && $post) {
        try {
            social_posts_publish_activity($post, $wasPublished ? 'Update' : 'Create', $userId);
        } catch (Throwable $exception) {
            log_activity('social_post_federation_failed', 'social_post', $postId, [
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
        }
    }
    log_activity('social_post_updated', 'social_post', $postId);
}

function social_posts_delete(int $postId, int $userId): void
{
    social_posts_require_schema();
    $post = social_posts_find($postId);
    if (!$post || (string)$post['status'] === 'deleted') return;
    if ((int)($post['owner_user_id'] ?? 0) !== $userId) {
        throw new RuntimeException('You do not own this social post.');
    }
    $wasPublished = (string)$post['status'] === 'published';
    db()->prepare(
        'UPDATE pod_social_posts
         SET status="deleted",deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute(['id' => $postId]);
    $post = social_posts_find($postId) ?? $post;
    social_posts_record_event(
        $postId,
        'deleted',
        (string)($post['deleted_at'] ?? gmdate('c')),
        $userId
    );
    if ($wasPublished) {
        try {
            social_posts_publish_activity($post, 'Delete', $userId);
        } catch (Throwable $exception) {
            log_activity('social_post_delete_federation_failed', 'social_post', $postId, [
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
        }
    }
    log_activity('social_post_deleted', 'social_post', $postId);
}

function social_posts_owner_posts(int $userId, int $limit = 100): array
{
    if (!social_posts_schema_available() || $userId <= 0) return [];
    $limit = max(1, min(250, $limit));
    $statement = db()->prepare(
        'SELECT post.*,owner.display_name AS owner_name
         FROM pod_social_posts post
         LEFT JOIN users owner ON owner.id=post.owner_user_id
         WHERE post.owner_user_id=:user_id AND post.status<>"deleted"
         ORDER BY COALESCE(post.published_at,post.created_at) DESC,post.id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function social_posts_public_posts(int $limit = 20): array
{
    if (!social_posts_schema_available()) return [];
    $limit = max(1, min(100, $limit));
    return db()->query(
        'SELECT post.*,owner.display_name AS owner_name
         FROM pod_social_posts post
         LEFT JOIN users owner ON owner.id=post.owner_user_id
         WHERE post.status="published" AND post.visibility="public"
           AND post.published_at<=UTC_TIMESTAMP()
         ORDER BY post.published_at DESC,post.id DESC
         LIMIT ' . $limit
    )->fetchAll();
}

function social_posts_published_local(int $userId, int $limit = 10): array
{
    if (!social_posts_schema_available() || $userId <= 0) return [];
    $limit = max(1, min(50, $limit));
    $statement = db()->prepare(
        'SELECT post.*,owner.display_name AS owner_name
         FROM pod_social_posts post
         LEFT JOIN users owner ON owner.id=post.owner_user_id
         WHERE post.owner_user_id=:user_id AND post.status="published"
         ORDER BY post.published_at DESC,post.id DESC LIMIT ' . $limit
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function social_posts_render_media(array $post): void
{
    if (empty($post['media_url'])) return;
    $url = (string)$post['media_url'];
    $alt = (string)($post['media_alt'] ?? '');
    echo '<figure class="pod-social-media">';
    if ((string)$post['media_kind'] === 'image') {
        echo '<img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy">';
    } elseif ((string)$post['media_kind'] === 'audio') {
        echo '<audio controls preload="none" src="' . e($url) . '"></audio>';
    } elseif ((string)$post['media_kind'] === 'video') {
        echo '<video controls preload="metadata" src="' . e($url) . '"></video>';
    } else {
        echo '<a href="' . e($url) . '">' . e($alt !== '' ? $alt : 'Open media') . '</a>';
    }
    echo '</figure>';
}

function social_posts_render_card(array $post, bool $compact = false): void
{
    $author = (string)($post['owner_name'] ?: activitypub_settings()['display_name'] ?: 'POD owner');
    $published = (string)($post['published_at'] ?: $post['created_at']);
    ?>
<article class="pod-social-card<?=$compact?' is-compact':''?>">
<header>
<div class="pod-social-avatar" aria-hidden="true"><?=e(mb_strtoupper(mb_substr($author,0,1)))?></div>
<div><strong><?=e($author)?></strong><span><?=e('@'.activitypub_account())?> · <?=e(format_datetime($published))?></span></div>
<span class="pod-social-visibility"><?=e(status_label((string)$post['visibility']))?></span>
</header>
<?php if(!empty($post['body_text'])):?><p class="pod-social-body"><?=nl2br(e((string)$post['body_text']))?></p><?php endif;?>
<?php social_posts_render_media($post);?>
<?php if(!empty($post['link_url'])):?><a class="pod-social-link" href="<?=e((string)$post['link_url'])?>" target="_blank" rel="noopener noreferrer nofollow"><?=e((string)$post['link_url'])?></a><?php endif;?>
<footer><a href="<?=e(social_posts_public_url($post))?>">Open post</a><span>ActivityPub Note</span></footer>
</article>
<?php
}

function social_posts_landing_blog_items(int $limit): array
{
    if (!nmm_module_enabled('blog')) return [];
    require_once __DIR__ . '/publishing.php';
    require_once __DIR__ . '/publishing-workflow.php';
    return function_exists('blog_public_posts')
        ? blog_public_posts('', '', $limit, 0)
        : [];
}

function social_posts_render_blog_cards(array $posts): void
{
    echo '<div class="pod-content-grid">';
    foreach ($posts as $post) {
        $url = (string)($post['url'] ?? app_url('blog.php'));
        echo '<article class="pod-blog-card">';
        if (!empty($post['cover']['url'])) {
            echo '<a class="pod-blog-cover" href="' . e($url) . '"><img src="'
                . e((string)$post['cover']['url']) . '" alt="'
                . e((string)($post['cover']['alt'] ?? $post['title'])) . '" loading="lazy"></a>';
        }
        echo '<div><span>' . e((string)($post['category'] ?: 'Article')) . '</span>';
        echo '<h3><a href="' . e($url) . '">' . e((string)$post['title']) . '</a></h3>';
        echo '<p>' . e((string)($post['excerpt'] ?? '')) . '</p>';
        echo '<small>' . e((string)($post['published_label'] ?? '')) . '</small></div></article>';
    }
    if (!$posts) echo '<div class="pod-content-empty">Published blog posts will appear here.</div>';
    echo '</div>';
}

function social_posts_render_social_cards(array $posts): void
{
    echo '<div class="pod-content-grid pod-social-grid">';
    foreach ($posts as $post) social_posts_render_card($post, true);
    if (!$posts) echo '<div class="pod-content-empty">Published social posts will appear here.</div>';
    echo '</div>';
}

function social_posts_render_landing(): void
{
    if (function_exists('nmm_module_enabled') && !nmm_module_enabled('social_feed')) return;
    $settings = social_posts_settings();
    $mode = $settings['landing_mode'];
    if ($mode === 'none') return;
    $limit = $settings['landing_limit'];
    $blogs = in_array($mode, ['blogs', 'tabs'], true)
        ? social_posts_landing_blog_items($limit)
        : [];
    $social = in_array($mode, ['social', 'tabs'], true) && $settings['enabled']
        ? social_posts_public_posts($limit)
        : [];
    $activitySettings = function_exists('activitypub_settings') ? activitypub_settings() : ['enabled'=>false];
    ?>
<section class="pod-content-section" data-pod-content-section>
<header class="pod-content-header">
<div><span>From this POD</span><h2>Posts, stories, and published ideas.</h2><p>Follow the POD for permanent social posts and follower Stories. Blog articles and RSS remain available as open-web publishing channels.</p></div>
<div class="pod-content-actions">
<?php if($settings['show_follow_button']&&!empty($activitySettings['enabled'])):?><a class="pod-follow-button" href="<?=e(app_url('follow-pod.php'))?>">Follow this POD</a><?php endif;?>
<?php if(nmm_module_enabled('blog')):?><a href="<?=e(app_url('blog.php'))?>">View blog</a><?php endif;?>
<a href="<?=e(app_url('social-feed.php'))?>">View social feed</a>
</div>
</header>
<?php if($mode==='tabs'):?>
<div class="pod-content-tabs" role="tablist" aria-label="POD content">
<button type="button" role="tab" aria-selected="true" aria-controls="podSocialPanel" data-pod-tab="social">Social posts</button>
<button type="button" role="tab" aria-selected="false" aria-controls="podBlogPanel" data-pod-tab="blogs">Blog posts</button>
</div>
<div id="podSocialPanel" role="tabpanel" data-pod-panel="social"><?php social_posts_render_social_cards($social);?></div>
<div id="podBlogPanel" role="tabpanel" data-pod-panel="blogs" hidden><?php social_posts_render_blog_cards($blogs);?><?php if(nmm_module_enabled('rss')):?><div class="pod-rss-link"><a href="<?=e(app_url('blog-feed.php'))?>">RSS feed</a></div><?php endif;?></div>
<?php elseif($mode==='social'):?>
<?php social_posts_render_social_cards($social);?>
<?php else:?>
<?php social_posts_render_blog_cards($blogs);?><?php if(nmm_module_enabled('rss')):?><div class="pod-rss-link"><a href="<?=e(app_url('blog-feed.php'))?>">RSS feed</a></div><?php endif;?>
<?php endif;?>
</section>
<?php
}

function social_posts_render_portal_stream(int $userId, int $limit = 8): void
{
    if (!social_posts_schema_available()) return;
    $posts = social_posts_published_local($userId, $limit);
    ?>
<section class="pod-local-stream">
<header><div><span>Your POD</span><h2>Published social posts</h2></div><a href="<?=e(app_url('portal/social-posts.php'))?>">Create or manage posts</a></header>
<div class="pod-local-stream-grid">
<?php foreach($posts as $post) social_posts_render_card($post, true);?>
<?php if(!$posts):?><div class="pod-content-empty">No permanent social posts have been published yet.</div><?php endif;?>
</div>
</section>
<?php
}

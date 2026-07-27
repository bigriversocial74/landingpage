<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

function publishing_workflow_schema_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    if (!publishing_schema_available()) {
        $available = false;
        return false;
    }

    try {
        $statement = db()->query(
            'SELECT
                (
                    SELECT COUNT(*)
                    FROM information_schema.tables
                    WHERE table_schema=DATABASE()
                      AND table_name IN (
                        "blog_post_revisions",
                        "resume_post_revisions"
                      )
                ) AS revision_tables,
                (
                    SELECT COUNT(*)
                    FROM information_schema.columns
                    WHERE table_schema=DATABASE()
                      AND (
                        (
                            table_name="blog_posts"
                            AND column_name IN (
                                "canonical_url",
                                "autosave_json",
                                "autosaved_at",
                                "autosaved_by"
                            )
                        )
                        OR (
                            table_name="resume_posts"
                            AND column_name IN (
                                "autosave_json",
                                "autosaved_at",
                                "autosaved_by"
                            )
                        )
                        OR (
                            table_name="blog_media"
                            AND column_name IN (
                                "focal_x",
                                "focal_y",
                                "crop_ratio"
                            )
                        )
                      )
                ) AS workflow_columns'
        );

        $row = $statement->fetch() ?: [];
        $available = (
            (int)($row['revision_tables'] ?? 0) === 2
            && (int)($row['workflow_columns'] ?? 0) === 10
        );
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function publishing_setting(
    string $key,
    string $fallback = ''
): string {
    return trim((string)(setting($key, $fallback) ?? $fallback));
}

function publishing_save_setting(
    string $key,
    ?string $value
): void {
    db()->prepare(
        'INSERT INTO settings
            (setting_key,setting_value)
         VALUES
            (:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE
            setting_value=VALUES(setting_value)'
    )->execute([
        'setting_key' => $key,
        'setting_value' => $value,
    ]);
}

function publishing_blog_settings(): array
{
    $postsPerPage = max(
        3,
        min(
            48,
            (int)publishing_setting(
                'blog_posts_per_page',
                '9'
            )
        )
    );

    return [
        'title' => publishing_setting(
            'blog_title',
            'North Mountain Media Journal'
        ),
        'intro' => publishing_setting(
            'blog_intro',
            'Ideas, systems, and things being built.'
        ),
        'description' => publishing_setting(
            'blog_description',
            'Articles about product strategy, connected business systems, ecommerce, CRM, operational design, music platforms, and independent software development.'
        ),
        'posts_per_page' => $postsPerPage,
        'default_author_user_id' => max(
            0,
            (int)publishing_setting(
                'blog_default_author_user_id',
                ''
            )
        ),
        'rss_enabled' => publishing_setting(
            'blog_rss_enabled',
            '1'
        ) !== '0',
        'sitemap_enabled' => publishing_setting(
            'blog_sitemap_enabled',
            '1'
        ) !== '0',
    ];
}

function publishing_admin_users(): array
{
    try {
        return db()->query(
            'SELECT id,display_name,email
             FROM users
             WHERE role="admin"
               AND status="active"
             ORDER BY display_name,email,id'
        )->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function publishing_unique_slug(
    string $table,
    string $baseSlug,
    int $ignoreId = 0
): string {
    if (!in_array(
        $table,
        ['blog_posts', 'resume_posts'],
        true
    )) {
        throw new RuntimeException(
            'Unsupported publishing table.'
        );
    }

    $baseSlug = slugify($baseSlug);

    if ($baseSlug === '') {
        $baseSlug = 'untitled';
    }

    $candidate = $baseSlug;
    $suffix = 2;

    while (true) {
        $statement = db()->prepare(
            'SELECT id
             FROM ' . $table . '
             WHERE slug=:slug
               AND id<>:ignore_id
             LIMIT 1'
        );
        $statement->execute([
            'slug' => $candidate,
            'ignore_id' => $ignoreId,
        ]);

        if (!$statement->fetchColumn()) {
            return $candidate;
        }

        $candidate = substr(
            $baseSlug,
            0,
            max(1, 180 - strlen((string)$suffix))
        ) . '-' . $suffix;
        $suffix++;
    }
}

function publishing_blog_snapshot(
    array $post
): array {
    $fields = [
        'author_user_id',
        'title',
        'slug',
        'status',
        'featured',
        'category',
        'excerpt',
        'body',
        'tags',
        'seo_title',
        'seo_description',
        'canonical_url',
        'published_at',
    ];
    $snapshot = [];

    foreach ($fields as $field) {
        $snapshot[$field] = $post[$field] ?? null;
    }

    return $snapshot;
}

function publishing_resume_snapshot(
    array $post
): array {
    $fields = [
        'title',
        'slug',
        'post_type',
        'column_name',
        'status',
        'featured',
        'sort_order',
        'section_label',
        'subtitle',
        'organization',
        'location',
        'date_label',
        'start_date',
        'end_date',
        'is_current',
        'summary',
        'body',
        'achievements',
        'skills',
        'link_url',
        'link_label',
        'published_at',
    ];
    $snapshot = [];

    foreach ($fields as $field) {
        $snapshot[$field] = $post[$field] ?? null;
    }

    return $snapshot;
}

function publishing_create_blog_revision(
    int $postId,
    string $revisionType,
    int $userId,
    ?array $snapshot = null
): int {
    if (
        !publishing_workflow_schema_available()
        || $postId <= 0
    ) {
        return 0;
    }

    if (!in_array(
        $revisionType,
        ['manual', 'autosave', 'restore', 'duplicate'],
        true
    )) {
        $revisionType = 'manual';
    }

    if ($snapshot === null) {
        $post = blog_admin_post($postId);

        if (!$post) {
            return 0;
        }

        $snapshot = publishing_blog_snapshot($post);
    }

    $statement = db()->prepare(
        'INSERT INTO blog_post_revisions
            (post_id,revision_type,snapshot_json,created_by)
         VALUES
            (:post_id,:revision_type,:snapshot_json,:created_by)'
    );
    $statement->execute([
        'post_id' => $postId,
        'revision_type' => $revisionType,
        'snapshot_json' => json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        'created_by' => $userId > 0 ? $userId : null,
    ]);

    return (int)db()->lastInsertId();
}

function publishing_create_resume_revision(
    int $postId,
    string $revisionType,
    int $userId,
    ?array $snapshot = null
): int {
    if (
        !publishing_workflow_schema_available()
        || $postId <= 0
    ) {
        return 0;
    }

    if (!in_array(
        $revisionType,
        ['manual', 'autosave', 'restore', 'duplicate', 'reorder'],
        true
    )) {
        $revisionType = 'manual';
    }

    if ($snapshot === null) {
        $post = resume_admin_post($postId);

        if (!$post) {
            return 0;
        }

        $snapshot = publishing_resume_snapshot($post);
    }

    $statement = db()->prepare(
        'INSERT INTO resume_post_revisions
            (post_id,revision_type,snapshot_json,created_by)
         VALUES
            (:post_id,:revision_type,:snapshot_json,:created_by)'
    );
    $statement->execute([
        'post_id' => $postId,
        'revision_type' => $revisionType,
        'snapshot_json' => json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        'created_by' => $userId > 0 ? $userId : null,
    ]);

    return (int)db()->lastInsertId();
}

function publishing_blog_revisions(
    int $postId,
    int $limit = 25
): array {
    if (
        !publishing_workflow_schema_available()
        || $postId <= 0
    ) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $statement = db()->prepare(
        'SELECT revision.*,
                user.display_name AS author_name
         FROM blog_post_revisions revision
         LEFT JOIN users user
           ON user.id=revision.created_by
         WHERE revision.post_id=:post_id
         ORDER BY revision.created_at DESC,revision.id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['post_id' => $postId]);

    return $statement->fetchAll();
}

function publishing_resume_revisions(
    int $postId,
    int $limit = 25
): array {
    if (
        !publishing_workflow_schema_available()
        || $postId <= 0
    ) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $statement = db()->prepare(
        'SELECT revision.*,
                user.display_name AS author_name
         FROM resume_post_revisions revision
         LEFT JOIN users user
           ON user.id=revision.created_by
         WHERE revision.post_id=:post_id
         ORDER BY revision.created_at DESC,revision.id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['post_id' => $postId]);

    return $statement->fetchAll();
}

function publishing_revision_snapshot(
    string $system,
    int $revisionId
): ?array {
    if (!publishing_workflow_schema_available()) {
        return null;
    }

    $table = match ($system) {
        'blog' => 'blog_post_revisions',
        'resume' => 'resume_post_revisions',
        default => '',
    };

    if ($table === '' || $revisionId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM ' . $table . '
         WHERE id=:revision_id
         LIMIT 1'
    );
    $statement->execute([
        'revision_id' => $revisionId,
    ]);
    $revision = $statement->fetch();

    if (!$revision) {
        return null;
    }

    $snapshot = json_decode(
        (string)$revision['snapshot_json'],
        true
    );

    if (!is_array($snapshot)) {
        return null;
    }

    $revision['snapshot'] = $snapshot;

    return $revision;
}

function publishing_save_blog_autosave(
    int $postId,
    array $snapshot,
    int $userId
): void {
    if (
        !publishing_workflow_schema_available()
        || $postId <= 0
    ) {
        throw new RuntimeException(
            'Import database/publishing_workflow_v56.sql before using autosave.'
        );
    }

    $json = json_encode(
        $snapshot,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
    );

    db()->prepare(
        'UPDATE blog_posts
         SET autosave_json=:autosave_json,
             autosaved_at=UTC_TIMESTAMP(),
             autosaved_by=:autosaved_by
         WHERE id=:post_id'
    )->execute([
        'autosave_json' => $json,
        'autosaved_by' => $userId,
        'post_id' => $postId,
    ]);

    $last = db()->prepare(
        'SELECT created_at
         FROM blog_post_revisions
         WHERE post_id=:post_id
           AND revision_type="autosave"
         ORDER BY id DESC
         LIMIT 1'
    );
    $last->execute(['post_id' => $postId]);
    $lastAt = (string)($last->fetchColumn() ?: '');

    if (
        $lastAt === ''
        || strtotime($lastAt) <= time() - 300
    ) {
        publishing_create_blog_revision(
            $postId,
            'autosave',
            $userId,
            $snapshot
        );
    }
}

function publishing_save_resume_autosave(
    int $postId,
    array $snapshot,
    int $userId
): void {
    if (
        !publishing_workflow_schema_available()
        || $postId <= 0
    ) {
        throw new RuntimeException(
            'Import database/publishing_workflow_v56.sql before using autosave.'
        );
    }

    $json = json_encode(
        $snapshot,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
    );

    db()->prepare(
        'UPDATE resume_posts
         SET autosave_json=:autosave_json,
             autosaved_at=UTC_TIMESTAMP(),
             autosaved_by=:autosaved_by
         WHERE id=:post_id'
    )->execute([
        'autosave_json' => $json,
        'autosaved_by' => $userId,
        'post_id' => $postId,
    ]);

    $last = db()->prepare(
        'SELECT created_at
         FROM resume_post_revisions
         WHERE post_id=:post_id
           AND revision_type="autosave"
         ORDER BY id DESC
         LIMIT 1'
    );
    $last->execute(['post_id' => $postId]);
    $lastAt = (string)($last->fetchColumn() ?: '');

    if (
        $lastAt === ''
        || strtotime($lastAt) <= time() - 300
    ) {
        publishing_create_resume_revision(
            $postId,
            'autosave',
            $userId,
            $snapshot
        );
    }
}

function publishing_autosave_payload(
    array $post
): ?array {
    $value = (string)($post['autosave_json'] ?? '');

    if ($value === '') {
        return null;
    }

    $payload = json_decode($value, true);

    return is_array($payload) ? $payload : null;
}

function publishing_publication_state(
    array $post
): array {
    $status = (string)($post['status'] ?? 'draft');
    $publishedAt = (string)($post['published_at'] ?? '');
    $scheduled = (
        $status === 'published'
        && $publishedAt !== ''
        && strtotime($publishedAt) > time()
    );

    if ($scheduled) {
        return [
            'key' => 'scheduled',
            'label' => 'Scheduled',
            'detail' => format_datetime($publishedAt),
        ];
    }

    return [
        'key' => $status,
        'label' => status_label($status),
        'detail' => $publishedAt !== ''
            ? format_datetime($publishedAt)
            : '',
    ];
}

function publishing_duplicate_blog_post(
    int $postId,
    int $userId
): int {
    $post = blog_admin_post($postId);

    if (!$post) {
        throw new RuntimeException(
            'Blog post not found.'
        );
    }

    $newSlug = publishing_unique_slug(
        'blog_posts',
        (string)$post['slug'] . '-copy'
    );
    $newTitle = (string)$post['title'] . ' — Copy';
    $statement = db()->prepare(
        'INSERT INTO blog_posts
            (author_user_id,title,slug,status,featured,category,
             excerpt,body,tags,seo_title,seo_description,canonical_url,
             published_at)
         VALUES
            (:author_user_id,:title,:slug,"draft",0,:category,
             :excerpt,:body,:tags,:seo_title,:seo_description,NULL,NULL)'
    );
    $statement->execute([
        'author_user_id' => (int)(
            $post['author_user_id']
            ?: $userId
        ),
        'title' => $newTitle,
        'slug' => $newSlug,
        'category' => $post['category'] ?: null,
        'excerpt' => $post['excerpt'] ?: null,
        'body' => (string)$post['body'],
        'tags' => $post['tags'] ?: null,
        'seo_title' => $post['seo_title'] ?: null,
        'seo_description' =>
            $post['seo_description'] ?: null,
    ]);
    $newId = (int)db()->lastInsertId();

    foreach (blog_post_media($postId) as $media) {
        $source = blog_storage_directory()
            . '/'
            . basename((string)$media['stored_name']);

        if (!is_file($source)) {
            continue;
        }

        $extension = strtolower(
            pathinfo($source, PATHINFO_EXTENSION)
        );
        $storedName = sprintf(
            'blog-%d-%s.%s',
            $newId,
            bin2hex(random_bytes(18)),
            $extension
        );
        $destination = blog_storage_directory()
            . '/'
            . $storedName;

        if (!copy($source, $destination)) {
            continue;
        }

        chmod($destination, 0640);

        db()->prepare(
            'INSERT INTO blog_media
                (post_id,media_role,original_name,stored_name,mime_type,
                 size_bytes,width_px,height_px,alt_text,caption,
                 focal_x,focal_y,crop_ratio,sort_order,created_by)
             VALUES
                (:post_id,:media_role,:original_name,:stored_name,:mime_type,
                 :size_bytes,:width_px,:height_px,:alt_text,:caption,
                 :focal_x,:focal_y,:crop_ratio,:sort_order,:created_by)'
        )->execute([
            'post_id' => $newId,
            'media_role' => $media['media_role'],
            'original_name' => $media['original_name'],
            'stored_name' => $storedName,
            'mime_type' => $media['mime_type'],
            'size_bytes' => filesize($destination),
            'width_px' => $media['width_px'] ?: null,
            'height_px' => $media['height_px'] ?: null,
            'alt_text' => $media['alt_text'] ?: null,
            'caption' => $media['caption'] ?: null,
            'focal_x' => $media['focal_x'] ?? 50,
            'focal_y' => $media['focal_y'] ?? 50,
            'crop_ratio' =>
                $media['crop_ratio'] ?? 'original',
            'sort_order' => (int)$media['sort_order'],
            'created_by' => $userId,
        ]);
    }

    publishing_create_blog_revision(
        $newId,
        'duplicate',
        $userId
    );

    return $newId;
}

function publishing_duplicate_resume_post(
    int $postId,
    int $userId
): int {
    $post = resume_admin_post($postId);

    if (!$post) {
        throw new RuntimeException(
            'Resume post not found.'
        );
    }

    $snapshot = publishing_resume_snapshot($post);
    $snapshot['title'] = (string)$post['title'] . ' — Copy';
    $snapshot['slug'] = publishing_unique_slug(
        'resume_posts',
        (string)$post['slug'] . '-copy'
    );
    $snapshot['status'] = 'draft';
    $snapshot['featured'] = 0;
    $snapshot['sort_order'] = (int)$post['sort_order'] + 5;
    $snapshot['published_at'] = null;

    $statement = db()->prepare(
        'INSERT INTO resume_posts
            (title,slug,post_type,column_name,status,featured,sort_order,
             section_label,subtitle,organization,location,date_label,
             start_date,end_date,is_current,summary,body,achievements,
             skills,link_url,link_label,created_by,updated_by,published_at)
         VALUES
            (:title,:slug,:post_type,:column_name,:status,:featured,:sort_order,
             :section_label,:subtitle,:organization,:location,:date_label,
             :start_date,:end_date,:is_current,:summary,:body,:achievements,
             :skills,:link_url,:link_label,:created_by,:updated_by,
             :published_at)'
    );
    $statement->execute(
        $snapshot + [
            'created_by' => $userId,
            'updated_by' => $userId,
        ]
    );
    $newId = (int)db()->lastInsertId();

    publishing_create_resume_revision(
        $newId,
        'duplicate',
        $userId
    );

    return $newId;
}

function publishing_restore_blog_revision(
    int $revisionId,
    int $userId
): int {
    $revision = publishing_revision_snapshot(
        'blog',
        $revisionId
    );

    if (!$revision) {
        throw new RuntimeException(
            'Blog revision not found.'
        );
    }

    $postId = (int)$revision['post_id'];
    $current = blog_admin_post($postId);

    if (!$current) {
        throw new RuntimeException(
            'Blog post not found.'
        );
    }

    publishing_create_blog_revision(
        $postId,
        'restore',
        $userId,
        publishing_blog_snapshot($current)
    );

    $snapshot = $revision['snapshot'];
    $snapshot['slug'] = publishing_unique_slug(
        'blog_posts',
        (string)($snapshot['slug'] ?? $current['slug']),
        $postId
    );

    db()->prepare(
        'UPDATE blog_posts
         SET author_user_id=:author_user_id,
             title=:title,
             slug=:slug,
             status=:status,
             featured=:featured,
             category=:category,
             excerpt=:excerpt,
             body=:body,
             tags=:tags,
             seo_title=:seo_title,
             seo_description=:seo_description,
             canonical_url=:canonical_url,
             published_at=:published_at,
             autosave_json=NULL,
             autosaved_at=NULL,
             autosaved_by=NULL
         WHERE id=:post_id'
    )->execute([
        'author_user_id' => (int)(
            $snapshot['author_user_id']
            ?? $current['author_user_id']
            ?? $userId
        ),
        'title' => (string)($snapshot['title'] ?? ''),
        'slug' => (string)$snapshot['slug'],
        'status' => (string)(
            $snapshot['status'] ?? 'draft'
        ),
        'featured' => !empty($snapshot['featured']) ? 1 : 0,
        'category' => $snapshot['category'] ?: null,
        'excerpt' => $snapshot['excerpt'] ?: null,
        'body' => (string)($snapshot['body'] ?? ''),
        'tags' => $snapshot['tags'] ?: null,
        'seo_title' => $snapshot['seo_title'] ?: null,
        'seo_description' =>
            $snapshot['seo_description'] ?: null,
        'canonical_url' =>
            $snapshot['canonical_url'] ?: null,
        'published_at' =>
            publishing_normalize_datetime(
                isset($snapshot['published_at'])
                    ? (string)$snapshot['published_at']
                    : null
            ),
        'post_id' => $postId,
    ]);

    return $postId;
}

function publishing_restore_resume_revision(
    int $revisionId,
    int $userId
): int {
    $revision = publishing_revision_snapshot(
        'resume',
        $revisionId
    );

    if (!$revision) {
        throw new RuntimeException(
            'Resume revision not found.'
        );
    }

    $postId = (int)$revision['post_id'];
    $current = resume_admin_post($postId);

    if (!$current) {
        throw new RuntimeException(
            'Resume post not found.'
        );
    }

    publishing_create_resume_revision(
        $postId,
        'restore',
        $userId,
        publishing_resume_snapshot($current)
    );

    $snapshot = $revision['snapshot'];
    $snapshot['slug'] = publishing_unique_slug(
        'resume_posts',
        (string)($snapshot['slug'] ?? $current['slug']),
        $postId
    );

    $values = [
        'title' => (string)($snapshot['title'] ?? ''),
        'slug' => (string)$snapshot['slug'],
        'post_type' => (string)(
            $snapshot['post_type'] ?? 'experience'
        ),
        'column_name' => (string)(
            $snapshot['column_name'] ?? 'main'
        ),
        'status' => (string)(
            $snapshot['status'] ?? 'draft'
        ),
        'featured' => !empty($snapshot['featured']) ? 1 : 0,
        'sort_order' => max(
            0,
            (int)($snapshot['sort_order'] ?? 100)
        ),
        'section_label' =>
            $snapshot['section_label'] ?: null,
        'subtitle' => $snapshot['subtitle'] ?: null,
        'organization' =>
            $snapshot['organization'] ?: null,
        'location' => $snapshot['location'] ?: null,
        'date_label' => $snapshot['date_label'] ?: null,
        'start_date' => $snapshot['start_date'] ?: null,
        'end_date' => $snapshot['end_date'] ?: null,
        'is_current' => !empty($snapshot['is_current']) ? 1 : 0,
        'summary' => $snapshot['summary'] ?: null,
        'body' => $snapshot['body'] ?: null,
        'achievements' =>
            $snapshot['achievements'] ?: null,
        'skills' => $snapshot['skills'] ?: null,
        'link_url' => $snapshot['link_url'] ?: null,
        'link_label' => $snapshot['link_label'] ?: null,
        'updated_by' => $userId,
        'published_at' =>
            publishing_normalize_datetime(
                isset($snapshot['published_at'])
                    ? (string)$snapshot['published_at']
                    : null
            ),
        'post_id' => $postId,
    ];

    db()->prepare(
        'UPDATE resume_posts
         SET title=:title,
             slug=:slug,
             post_type=:post_type,
             column_name=:column_name,
             status=:status,
             featured=:featured,
             sort_order=:sort_order,
             section_label=:section_label,
             subtitle=:subtitle,
             organization=:organization,
             location=:location,
             date_label=:date_label,
             start_date=:start_date,
             end_date=:end_date,
             is_current=:is_current,
             summary=:summary,
             body=:body,
             achievements=:achievements,
             skills=:skills,
             link_url=:link_url,
             link_label=:link_label,
             updated_by=:updated_by,
             published_at=:published_at,
             autosave_json=NULL,
             autosaved_at=NULL,
             autosaved_by=NULL
         WHERE id=:post_id'
    )->execute($values);

    return $postId;
}

function publishing_reorder_resume_posts(
    array $groups,
    int $userId
): void {
    if (!publishing_workflow_schema_available()) {
        throw new RuntimeException(
            'Import database/publishing_workflow_v56.sql before reordering Resume Posts.'
        );
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        foreach (['main', 'sidebar'] as $column) {
            $ids = array_values(array_filter(
                array_map(
                    'intval',
                    is_array($groups[$column] ?? null)
                        ? $groups[$column]
                        : []
                ),
                static fn(int $id): bool => $id > 0
            ));
            $order = 10;

            foreach ($ids as $id) {
                $current = resume_admin_post($id);

                if (!$current) {
                    continue;
                }

                publishing_create_resume_revision(
                    $id,
                    'reorder',
                    $userId,
                    publishing_resume_snapshot($current)
                );

                $pdo->prepare(
                    'UPDATE resume_posts
                     SET sort_order=:sort_order,
                         column_name=:column_name,
                         updated_by=:updated_by
                     WHERE id=:post_id'
                )->execute([
                    'sort_order' => $order,
                    'updated_by' => $userId,
                    'post_id' => $id,
                    'column_name' => $column,
                ]);
                $order += 10;
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function publishing_replace_blog_media(
    int $mediaId,
    int $postId,
    array $upload
): void {
    $statement = db()->prepare(
        'SELECT *
         FROM blog_media
         WHERE id=:media_id
           AND post_id=:post_id
         LIMIT 1'
    );
    $statement->execute([
        'media_id' => $mediaId,
        'post_id' => $postId,
    ]);
    $media = $statement->fetch();

    if (!$media) {
        throw new RuntimeException(
            'Blog image not found.'
        );
    }

    if (
        (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
        === UPLOAD_ERR_NO_FILE
    ) {
        return;
    }

    if (
        (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'The replacement image upload failed.'
        );
    }

    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    $limit = publishing_image_limit_bytes();

    if (
        $temporary === ''
        || !is_uploaded_file($temporary)
        || $size <= 0
        || $size > $limit
    ) {
        throw new RuntimeException(
            'Replacement images must be valid uploads no larger than '
            . format_bytes($limit)
            . '.'
        );
    }

    $imageInfo = @getimagesize($temporary);

    if (!is_array($imageInfo)) {
        throw new RuntimeException(
            'The replacement file is not a valid image.'
        );
    }

    $mime = (
        new finfo(FILEINFO_MIME_TYPE)
    )->file($temporary) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException(
            'Replacement images must be JPG, PNG, WebP, or GIF.'
        );
    }

    $storedName = sprintf(
        'blog-%d-%s.%s',
        $postId,
        bin2hex(random_bytes(18)),
        $extensions[$mime]
    );
    $destination = blog_storage_directory()
        . '/'
        . $storedName;

    if (!move_uploaded_file($temporary, $destination)) {
        throw new RuntimeException(
            'The replacement image could not be stored.'
        );
    }

    chmod($destination, 0640);
    $oldPath = blog_storage_directory()
        . '/'
        . basename((string)$media['stored_name']);

    try {
        db()->prepare(
            'UPDATE blog_media
             SET original_name=:original_name,
                 stored_name=:stored_name,
                 mime_type=:mime_type,
                 size_bytes=:size_bytes,
                 width_px=:width_px,
                 height_px=:height_px
             WHERE id=:media_id
               AND post_id=:post_id'
        )->execute([
            'original_name' => substr(
                basename((string)($upload['name'] ?? 'image')),
                0,
                255
            ),
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'size_bytes' => filesize($destination),
            'width_px' => (int)($imageInfo[0] ?? 0) ?: null,
            'height_px' => (int)($imageInfo[1] ?? 0) ?: null,
            'media_id' => $mediaId,
            'post_id' => $postId,
        ]);
    } catch (Throwable $exception) {
        @unlink($destination);
        throw $exception;
    }

    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

function publishing_media_position_style(
    array $media
): string {
    $x = max(
        0,
        min(100, (float)($media['focal_x'] ?? 50))
    );
    $y = max(
        0,
        min(100, (float)($media['focal_y'] ?? 50))
    );

    return sprintf(
        'object-position:%.2f%% %.2f%%;',
        $x,
        $y
    );
}

function publishing_media_ratio_class(
    array $media
): string {
    return 'publishing-ratio-'
        . str_replace(
            ':',
            '-',
            (string)($media['crop_ratio'] ?? 'original')
        );
}

function publishing_blog_preview_post(
    int $postId
): ?array {
    $post = blog_admin_post($postId);

    if (!$post) {
        return null;
    }

    return blog_post_payload(
        $post,
        $post['media'] ?? blog_post_media($postId)
    );
}

function publishing_resume_preview_post(
    int $postId
): ?array {
    $post = resume_admin_post($postId);

    return $post
        ? resume_post_payload($post)
        : null;
}

function publishing_content_analytics_summary(
    int $days = 30
): array {
    $days = max(1, min(365, $days));

    if (!visitor_intelligence_schema_available()) {
        return [];
    }

    $statement = db()->query(
        'SELECT
            SUM(event_type="blog_archive_view") AS blog_archive_views,
            SUM(event_type="blog_post_view") AS blog_post_views,
            COUNT(DISTINCT CASE
                WHEN event_type="blog_post_view"
                THEN JSON_UNQUOTE(
                    JSON_EXTRACT(metadata_json,"$.post_id")
                ) END
            ) AS blog_posts_viewed,
            SUM(event_type="resume_view") AS resume_views,
            SUM(event_type="resume_post_view") AS resume_post_views,
            COUNT(DISTINCT CASE
                WHEN event_type="resume_post_view"
                THEN JSON_UNQUOTE(
                    JSON_EXTRACT(metadata_json,"$.resume_post_id")
                ) END
            ) AS resume_posts_viewed,
            SUM(event_type="portfolio_view") AS portfolio_views,
            SUM(event_type IN (
                "contact_form_submitted",
                "call_started",
                "callback_requested",
                "public_message_submitted",
                "voicemail_submitted",
                "appointment_booking_submit",
                "intake_submitted",
                "proposal_accepted"
            )) AS conversions
         FROM visitor_events
         WHERE occurred_at>=UTC_TIMESTAMP()-INTERVAL '
         . $days
         . ' DAY'
    );

    $row = $statement->fetch() ?: [];

    return array_map(
        static fn(mixed $value): int =>
            (int)($value ?? 0),
        $row
    );
}

function publishing_blog_post_metrics(
    int $days = 30
): array {
    $days = max(1, min(365, $days));

    if (
        !publishing_schema_available()
        || !visitor_intelligence_schema_available()
    ) {
        return [];
    }

    $statement = db()->query(
        'SELECT post.id,
                post.title,
                post.slug,
                post.status,
                post.published_at,
                COUNT(event.id) AS views,
                COUNT(DISTINCT event.visitor_id) AS visitors,
                MAX(event.occurred_at) AS last_view_at
         FROM blog_posts post
         LEFT JOIN visitor_events event
           ON event.event_type="blog_post_view"
          AND CAST(
                JSON_UNQUOTE(
                    JSON_EXTRACT(
                        event.metadata_json,
                        "$.post_id"
                    )
                ) AS UNSIGNED
              )=post.id
          AND event.occurred_at>=UTC_TIMESTAMP()-INTERVAL '
         . $days
         . ' DAY
         GROUP BY post.id
         ORDER BY views DESC,
                  COALESCE(post.published_at,post.updated_at) DESC,
                  post.id DESC'
    );

    return $statement->fetchAll();
}

function publishing_resume_post_metrics(
    int $days = 30
): array {
    $days = max(1, min(365, $days));

    if (
        !publishing_schema_available()
        || !visitor_intelligence_schema_available()
    ) {
        return [];
    }

    $statement = db()->query(
        'SELECT post.id,
                post.title,
                post.slug,
                post.post_type,
                post.column_name,
                post.status,
                post.published_at,
                COUNT(event.id) AS views,
                COUNT(DISTINCT event.visitor_id) AS visitors,
                MAX(event.occurred_at) AS last_view_at
         FROM resume_posts post
         LEFT JOIN visitor_events event
           ON event.event_type="resume_post_view"
          AND CAST(
                JSON_UNQUOTE(
                    JSON_EXTRACT(
                        event.metadata_json,
                        "$.resume_post_id"
                    )
                ) AS UNSIGNED
              )=post.id
          AND event.occurred_at>=UTC_TIMESTAMP()-INTERVAL '
         . $days
         . ' DAY
         GROUP BY post.id
         ORDER BY views DESC,
                  post.column_name,
                  post.sort_order,
                  post.id'
    );

    return $statement->fetchAll();
}

function publishing_conversion_attribution(
    int $days = 30,
    int $limit = 50
): array {
    $days = max(1, min(365, $days));
    $limit = max(1, min(250, $limit));

    if (!visitor_intelligence_schema_available()) {
        return [];
    }

    $conversionTypes = [
        'contact_form_submitted',
        'call_started',
        'callback_requested',
        'public_message_submitted',
        'voicemail_submitted',
        'appointment_booking_submit',
        'intake_submitted',
        'proposal_accepted',
    ];
    $sourceTypes = [
        'blog_post_view',
        'resume_post_view',
        'portfolio_view',
    ];
    $conversions = db()->query(
        'SELECT event.*
         FROM visitor_events event
         WHERE event.event_type IN (
            "' . implode('","', $conversionTypes) . '"
         )
           AND event.occurred_at>=UTC_TIMESTAMP()-INTERVAL '
         . $days
         . ' DAY
         ORDER BY event.occurred_at DESC,event.id DESC
         LIMIT ' . $limit
    )->fetchAll();

    $output = [];
    $sourceStatement = db()->prepare(
        'SELECT source.*
         FROM visitor_events source
         WHERE source.visitor_id=:visitor_id
           AND source.session_id=:session_id
           AND source.event_type IN (
                "' . implode('","', $sourceTypes) . '"
           )
           AND (
                source.occurred_at<:occurred_at
                OR (
                    source.occurred_at=:occurred_at_equal
                    AND source.id<:event_id
                )
           )
         ORDER BY source.occurred_at DESC,source.id DESC
         LIMIT 1'
    );

    foreach ($conversions as $conversion) {
        $sourceStatement->execute([
            'visitor_id' => (int)$conversion['visitor_id'],
            'session_id' => (int)$conversion['session_id'],
            'occurred_at' => $conversion['occurred_at'],
            'occurred_at_equal' => $conversion['occurred_at'],
            'event_id' => (int)$conversion['id'],
        ]);
        $source = $sourceStatement->fetch();
        $sourceMetadata = $source
            ? visitor_intelligence_metadata_decode(
                $source['metadata_json'] ?? ''
            )
            : [];
        $conversionMetadata =
            visitor_intelligence_metadata_decode(
                $conversion['metadata_json'] ?? ''
            );

        $sourceLabel = 'Direct / unattributed';
        $sourceType = 'direct';
        $sourceId = null;

        if ($source) {
            $sourceType = (string)$source['event_type'];
            $sourceLabel = (string)(
                $source['event_label']
                ?: visitor_intelligence_event_label(
                    $sourceType
                )
            );

            if ($sourceType === 'blog_post_view') {
                $sourceId = (int)(
                    $sourceMetadata['post_id'] ?? 0
                ) ?: null;
            } elseif ($sourceType === 'resume_post_view') {
                $sourceId = (int)(
                    $sourceMetadata['resume_post_id'] ?? 0
                ) ?: null;
            } elseif ($sourceType === 'portfolio_view') {
                $sourceId = (int)(
                    $source['portfolio_project_id'] ?? 0
                ) ?: null;
            }
        }

        $output[] = [
            'conversion_id' => (int)$conversion['id'],
            'conversion_type' =>
                (string)$conversion['event_type'],
            'conversion_label' =>
                visitor_intelligence_event_label(
                    (string)$conversion['event_type']
                ),
            'conversion_at' =>
                (string)$conversion['occurred_at'],
            'crm_contact_id' => (int)(
                $conversion['crm_contact_id'] ?? 0
            ) ?: null,
            'crm_opportunity_id' => (int)(
                $conversion['crm_opportunity_id'] ?? 0
            ) ?: null,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_label' => $sourceLabel,
            'source_path' => $source
                ? (string)($source['page_path'] ?? '')
                : '',
            'conversion_metadata' => $conversionMetadata,
        ];
    }

    return $output;
}

function publishing_attribution_summary(
    int $days = 30
): array {
    $rows = publishing_conversion_attribution(
        $days,
        250
    );
    $summary = [];

    foreach ($rows as $row) {
        $key = $row['source_type']
            . '|'
            . ($row['source_id'] ?? 0)
            . '|'
            . $row['source_label'];

        if (!isset($summary[$key])) {
            $summary[$key] = [
                'source_type' => $row['source_type'],
                'source_id' => $row['source_id'],
                'source_label' => $row['source_label'],
                'conversions' => 0,
                'opportunities' => 0,
                'last_conversion_at' => '',
            ];
        }

        $summary[$key]['conversions']++;

        if ($row['crm_opportunity_id']) {
            $summary[$key]['opportunities']++;
        }

        if (
            $summary[$key]['last_conversion_at'] === ''
            || $row['conversion_at']
                > $summary[$key]['last_conversion_at']
        ) {
            $summary[$key]['last_conversion_at'] =
                $row['conversion_at'];
        }
    }

    usort(
        $summary,
        static fn(array $a, array $b): int =>
            [$b['conversions'], $b['opportunities']]
            <=>
            [$a['conversions'], $a['opportunities']]
    );

    return array_values($summary);
}

function publishing_absolute_url(
    string $path
): string {
    $configured = trim((string)(
        nmm_config('app')['base_url'] ?? ''
    ));

    if ($configured !== '') {
        return rtrim($configured, '/')
            . '/'
            . ltrim($path, '/');
    }

    $scheme = (
        !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off'
    ) ? 'https' : 'http';
    $host = trim((string)(
        $_SERVER['HTTP_HOST']
        ?? 'localhost'
    ));
    $basePath = rtrim(
        str_replace(
            '\\',
            '/',
            dirname(
                (string)($_SERVER['SCRIPT_NAME'] ?? '/')
            )
        ),
        '/'
    );

    return $scheme
        . '://'
        . $host
        . ($basePath !== '' ? $basePath : '')
        . '/'
        . ltrim($path, '/');
}

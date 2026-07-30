<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-content-interactions-v66C */

require_once __DIR__ . '/content-interactions-admin.php';
require_once __DIR__ . '/websub-service.php';
require_once __DIR__ . '/activitypub-service.php';

function publishing_handle_admin_action(
    string $action,
    array $user
): bool {
    $blogActions = [
        'save_blog_post',
        'archive_blog_post',
        'delete_blog_post',
        'update_blog_media',
        'delete_blog_media',
        'save_blog_settings',
        'duplicate_blog_post',
        'restore_blog_revision',
        'save_content_interaction_settings',
        'moderate_content_comment',
    ];
    $resumeActions = [
        'save_resume_post',
        'archive_resume_post',
        'delete_resume_post',
        'duplicate_resume_post',
        'restore_resume_revision',
    ];

    if (
        !in_array($action, $blogActions, true)
        && !in_array($action, $resumeActions, true)
    ) {
        return false;
    }

    if (!publishing_schema_available()) {
        throw new RuntimeException(
            'Import database/publishing_systems_v51.sql and database/publishing_workflow_v56.sql before managing the complete publishing workflow.'
        );
    }

    if (content_interactions_handle_admin_action($action, $user)) {
        return true;
    }

    if ($action === 'save_blog_post') {
        $id = int_input('id');
        $existingPost = $id > 0 ? blog_admin_post($id) : null;
        $title = input('title');
        $slug = slugify(input('slug') ?: $title);
        $status = input('status');
        $category = trim(input('category'));
        $body = trim((string)($_POST['body'] ?? ''));
        $publishedAt = publishing_normalize_datetime(
            nullable_input('published_at')
        );
        $blogSettings = publishing_blog_settings();
        $authorUserId = max(
            0,
            int_input('author_user_id')
        );

        if ($authorUserId <= 0) {
            $authorUserId = (int)(
                $blogSettings['default_author_user_id']
                ?: $user['id']
            );
        }

        $canonicalUrl = trim(input('canonical_url'));

        if (
            $canonicalUrl !== ''
            && (
                !filter_var(
                    $canonicalUrl,
                    FILTER_VALIDATE_URL
                )
                || !preg_match(
                    '/^https?:\/\//i',
                    $canonicalUrl
                )
            )
        ) {
            throw new RuntimeException(
                'Enter a valid HTTP or HTTPS canonical URL.'
            );
        }

        if ($title === '' || $slug === '' || $body === '') {
            throw new RuntimeException(
                'Enter a blog title, slug, and article body.'
            );
        }

        if (
            mb_strlen($title) > 190
            || mb_strlen($slug) > 190
            || mb_strlen($category) > 120
        ) {
            throw new RuntimeException(
                'One of the blog identity fields is too long.'
            );
        }

        if (!in_array(
            $status,
            ['draft', 'published', 'archived'],
            true
        )) {
            $status = 'draft';
        }

        $duplicate = db()->prepare(
            'SELECT id
             FROM blog_posts
             WHERE slug=:slug
               AND id<>:post_id
             LIMIT 1'
        );
        $duplicate->execute([
            'slug' => $slug,
            'post_id' => $id,
        ]);

        if ($duplicate->fetchColumn()) {
            throw new RuntimeException(
                'That blog slug is already in use.'
            );
        }

        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = gmdate('Y-m-d H:i:s');
        }

        $values = [
            'author_user_id' => $authorUserId,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'category' => $category !== '' ? $category : null,
            'excerpt' => nullable_input('excerpt'),
            'body' => $body,
            'tags' => nullable_input('tags'),
            'seo_title' => nullable_input('seo_title'),
            'seo_description' => nullable_input('seo_description'),
            'canonical_url' => $canonicalUrl !== ''
                ? $canonicalUrl
                : null,
            'published_at' => $publishedAt,
        ];

        if ($id > 0) {
            $statement = db()->prepare(
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
                 WHERE id=:id'
            );
            $statement->execute(
                $values + ['id' => $id]
            );
            $event = 'blog_post_updated';
            $message = 'Blog post updated.';
        } else {
            $statement = db()->prepare(
                'INSERT INTO blog_posts
                    (author_user_id,title,slug,status,featured,category,
                     excerpt,body,tags,seo_title,seo_description,
                     canonical_url,published_at)
                 VALUES
                    (:author_user_id,:title,:slug,:status,:featured,:category,
                     :excerpt,:body,:tags,:seo_title,:seo_description,
                     :canonical_url,:published_at)'
            );
            $statement->execute($values);
            $id = (int)db()->lastInsertId();
            $event = 'blog_post_created';
            $message = 'Blog post created.';
        }

        $coverUpload = $_FILES['cover_image'] ?? null;

        if (
            is_array($coverUpload)
            && (int)($coverUpload['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_NO_FILE
        ) {
            blog_store_image(
                $coverUpload,
                $id,
                'cover',
                (int)$user['id'],
                input('cover_alt'),
                input('cover_caption')
            );
        }

        $galleryUploads = blog_multiple_uploads(
            $_FILES['gallery_images'] ?? []
        );
        $galleryOrder = count(blog_post_media($id)) + 10;

        foreach ($galleryUploads as $upload) {
            blog_store_image(
                $upload,
                $id,
                'gallery',
                (int)$user['id'],
                input('gallery_alt'),
                input('gallery_caption'),
                $galleryOrder
            );
            $galleryOrder += 10;
        }

        publishing_create_blog_revision(
            $id,
            'manual',
            (int)$user['id']
        );

        log_activity(
            $event,
            'blog_post',
            $id,
            ['status' => $status]
        );
        if ($status === 'published') {
            syndication_queue_websub(
                $event === 'blog_post_created' ? 'publish' : 'update',
                (int)$user['id'],
                $id
            );
            activitypub_blog_event(
                $id,
                $existingPost && (string)$existingPost['status'] === 'published'
                    ? 'Update'
                    : 'Create',
                (int)$user['id']
            );
        } elseif ($existingPost && (string)$existingPost['status'] === 'published') {
            activitypub_blog_event($id, 'Delete', (int)$user['id'], $existingPost);
        }
        flash('success', $message);
        redirect(
            'portal/admin.php?view=blog&edit=' . $id
        );
    }

    if ($action === 'save_blog_settings') {
        $blogTitle = substr(
            trim(input('blog_title')),
            0,
            190
        );
        $blogIntro = substr(
            trim(input('blog_intro')),
            0,
            500
        );
        $blogDescription = substr(
            trim((string)($_POST['blog_description'] ?? '')),
            0,
            1200
        );
        $postsPerPage = max(
            3,
            min(48, int_input('blog_posts_per_page'))
        );
        $defaultAuthor = max(
            0,
            int_input('blog_default_author_user_id')
        );

        publishing_save_setting(
            'blog_title',
            $blogTitle !== ''
                ? $blogTitle
                : 'North Mountain Media Journal'
        );
        publishing_save_setting(
            'blog_intro',
            $blogIntro !== ''
                ? $blogIntro
                : 'Ideas, systems, and things being built.'
        );
        publishing_save_setting(
            'blog_description',
            $blogDescription
        );
        publishing_save_setting(
            'blog_posts_per_page',
            (string)$postsPerPage
        );
        publishing_save_setting(
            'blog_default_author_user_id',
            $defaultAuthor > 0
                ? (string)$defaultAuthor
                : ''
        );
        publishing_save_setting(
            'blog_rss_enabled',
            isset($_POST['blog_rss_enabled'])
                ? '1'
                : '0'
        );
        publishing_save_setting(
            'blog_atom_enabled',
            isset($_POST['blog_atom_enabled']) ? '1' : '0'
        );
        publishing_save_setting(
            'feed_public_item_limit',
            (string)max(5, min(100, int_input('feed_public_item_limit')))
        );
        publishing_save_setting(
            'blog_feed_language',
            mb_substr(trim(input('blog_feed_language')) ?: 'en-us', 0, 40)
        );
        publishing_save_setting(
            'blog_feed_copyright',
            mb_substr(trim(input('blog_feed_copyright')), 0, 255)
        );
        publishing_save_setting(
            'blog_sitemap_enabled',
            isset($_POST['blog_sitemap_enabled'])
                ? '1'
                : '0'
        );

        log_activity(
            'blog_settings_updated',
            'settings',
            null
        );
        flash('success', 'Blog settings updated.');
        redirect('portal/admin.php?view=blog');
    }

    if ($action === 'duplicate_blog_post') {
        $newId = publishing_duplicate_blog_post(
            int_input('id'),
            (int)$user['id']
        );
        log_activity(
            'blog_post_duplicated',
            'blog_post',
            $newId
        );
        flash(
            'success',
            'Blog post duplicated as a draft.'
        );
        redirect(
            'portal/admin.php?view=blog&edit=' . $newId
        );
    }

    if ($action === 'restore_blog_revision') {
        $postId = publishing_restore_blog_revision(
            int_input('revision_id'),
            (int)$user['id']
        );
        log_activity(
            'blog_revision_restored',
            'blog_post',
            $postId
        );
        $restoredPost = activitypub_blog_post($postId);
        if ($restoredPost && (string)$restoredPost['status'] === 'published') {
            syndication_queue_websub('update', (int)$user['id'], $postId);
            activitypub_blog_event($postId, 'Update', (int)$user['id']);
        }
        flash('success', 'Blog revision restored.');
        redirect(
            'portal/admin.php?view=blog&edit=' . $postId
        );
    }

    if ($action === 'archive_blog_post') {
        $id = int_input('id');
        $existingPost = blog_admin_post($id);

        db()->prepare(
            'UPDATE blog_posts
             SET status="archived"
             WHERE id=:id'
        )->execute(['id' => $id]);

        log_activity(
            'blog_post_archived',
            'blog_post',
            $id
        );
        if ($existingPost && (string)$existingPost['status'] === 'published') {
            activitypub_blog_event($id, 'Delete', (int)$user['id'], $existingPost);
        }
        flash('success', 'Blog post archived.');
        redirect('portal/admin.php?view=blog');
    }


    if ($action === 'delete_blog_post') {
        $id = int_input('id');
        $postStatement = db()->prepare(
            'SELECT post.*,user.display_name AS author_name
             FROM blog_posts post
             LEFT JOIN users user ON user.id=post.author_user_id
             WHERE post.id=:id
             LIMIT 1'
        );
        $postStatement->execute(['id' => $id]);
        $post = $postStatement->fetch();

        if (!$post) {
            throw new RuntimeException('Blog post not found.');
        }

        $mediaStatement = db()->prepare(
            'SELECT *
             FROM blog_media
             WHERE post_id=:post_id'
        );
        $mediaStatement->execute(['post_id' => $id]);
        $media = $mediaStatement->fetchAll();

        if ((string)($post['status'] ?? '') === 'published') {
            activitypub_blog_event($id, 'Delete', (int)$user['id'], $post);
        }
        content_interactions_cleanup('blog_post', $id);
        db()->prepare(
            'DELETE FROM blog_posts
             WHERE id=:id'
        )->execute(['id' => $id]);

        foreach ($media as $item) {
            blog_delete_media_file($item);
        }

        log_activity(
            'blog_post_deleted',
            'blog_post',
            $id,
            ['title' => (string)$post['title']]
        );
        flash('success', 'Blog post and its media were permanently deleted.');
        redirect('portal/admin.php?view=blog');
    }

    if ($action === 'update_blog_media') {
        $mediaId = int_input('media_id');
        $postId = int_input('post_id');
        $role = input('media_role');
        $cropRatio = input('crop_ratio');
        $focalX = max(
            0,
            min(100, (float)input('focal_x'))
        );
        $focalY = max(
            0,
            min(100, (float)input('focal_y'))
        );

        if (!in_array($role, ['cover', 'gallery'], true)) {
            $role = 'gallery';
        }

        if (!in_array(
            $cropRatio,
            ['original', '16:9', '4:3', '1:1', '3:4'],
            true
        )) {
            $cropRatio = 'original';
        }

        $replacement = $_FILES[
            'replacement_image'
        ] ?? null;

        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($role === 'cover') {
                $pdo->prepare(
                    'UPDATE blog_media
                     SET media_role="gallery",
                         sort_order=sort_order+1
                     WHERE post_id=:post_id
                       AND media_role="cover"
                       AND id<>:media_id'
                )->execute([
                    'post_id' => $postId,
                    'media_id' => $mediaId,
                ]);
            }

            $pdo->prepare(
                'UPDATE blog_media
                 SET media_role=:media_role,
                     alt_text=:alt_text,
                     caption=:caption,
                     focal_x=:focal_x,
                     focal_y=:focal_y,
                     crop_ratio=:crop_ratio,
                     sort_order=:sort_order
                 WHERE id=:media_id
                   AND post_id=:post_id'
            )->execute([
                'media_role' => $role,
                'alt_text' => nullable_input('alt_text'),
                'caption' => nullable_input('caption'),
                'focal_x' => $focalX,
                'focal_y' => $focalY,
                'crop_ratio' => $cropRatio,
                'sort_order' => max(0, int_input('sort_order')),
                'media_id' => $mediaId,
                'post_id' => $postId,
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if (
            is_array($replacement)
            && (int)(
                $replacement['error']
                ?? UPLOAD_ERR_NO_FILE
            ) !== UPLOAD_ERR_NO_FILE
        ) {
            publishing_replace_blog_media(
                $mediaId,
                $postId,
                $replacement
            );
        }

        flash('success', 'Blog image updated.');
        redirect(
            'portal/admin.php?view=blog&edit=' . $postId
        );
    }

    if ($action === 'delete_blog_media') {
        $mediaId = int_input('media_id');
        $postId = int_input('post_id');
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

        db()->prepare(
            'DELETE FROM blog_media
             WHERE id=:media_id
               AND post_id=:post_id'
        )->execute([
            'media_id' => $mediaId,
            'post_id' => $postId,
        ]);

        blog_delete_media_file($media);
        flash('success', 'Blog image deleted.');
        redirect(
            'portal/admin.php?view=blog&edit=' . $postId
        );
    }

    if ($action === 'save_resume_post') {
        $id = int_input('id');
        $title = input('title');
        $slug = slugify(input('slug') ?: $title);
        $postType = input('post_type');
        $columnName = input('column_name');
        $status = input('status');
        $linkUrl = trim(input('link_url'));
        $publishedAt = publishing_normalize_datetime(
            nullable_input('published_at')
        );

        $types = [
            'profile',
            'experience',
            'education',
            'skill_group',
            'strengths',
            'certification',
            'award',
            'project',
            'volunteer',
            'custom',
        ];

        if ($title === '' || $slug === '') {
            throw new RuntimeException(
                'Enter a resume-post title and slug.'
            );
        }

        if (!in_array($postType, $types, true)) {
            $postType = 'experience';
        }

        if (!in_array(
            $columnName,
            ['main', 'sidebar'],
            true
        )) {
            $columnName = 'main';
        }

        if (!in_array(
            $status,
            ['draft', 'published', 'archived'],
            true
        )) {
            $status = 'draft';
        }

        if (
            $linkUrl !== ''
            && (
                !filter_var($linkUrl, FILTER_VALIDATE_URL)
                || !preg_match('/^https?:\/\//i', $linkUrl)
            )
        ) {
            throw new RuntimeException(
                'Enter a valid HTTP or HTTPS resume link.'
            );
        }

        $duplicate = db()->prepare(
            'SELECT id
             FROM resume_posts
             WHERE slug=:slug
               AND id<>:post_id
             LIMIT 1'
        );
        $duplicate->execute([
            'slug' => $slug,
            'post_id' => $id,
        ]);

        if ($duplicate->fetchColumn()) {
            throw new RuntimeException(
                'That resume-post slug is already in use.'
            );
        }

        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = gmdate('Y-m-d H:i:s');
        }

        $values = [
            'title' => $title,
            'slug' => $slug,
            'post_type' => $postType,
            'column_name' => $columnName,
            'status' => $status,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'sort_order' => max(0, int_input('sort_order')),
            'section_label' => nullable_input('section_label'),
            'subtitle' => nullable_input('subtitle'),
            'organization' => nullable_input('organization'),
            'location' => nullable_input('location'),
            'date_label' => nullable_input('date_label'),
            'start_date' => nullable_input('start_date'),
            'end_date' => nullable_input('end_date'),
            'is_current' => isset($_POST['is_current']) ? 1 : 0,
            'summary' => nullable_input('summary'),
            'body' => nullable_input('body'),
            'achievements' => nullable_input('achievements'),
            'skills' => nullable_input('skills'),
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
            'link_label' => nullable_input('link_label'),
            'updated_by' => (int)$user['id'],
            'published_at' => $publishedAt,
        ];

        if ($id > 0) {
            $statement = db()->prepare(
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
                 WHERE id=:id'
            );
            $statement->execute(
                $values + ['id' => $id]
            );
            $event = 'resume_post_updated';
            $message = 'Resume post updated.';
        } else {
            $statement = db()->prepare(
                'INSERT INTO resume_posts
                    (title,slug,post_type,column_name,status,featured,
                     sort_order,section_label,subtitle,organization,
                     location,date_label,start_date,end_date,is_current,
                     summary,body,achievements,skills,link_url,link_label,
                     created_by,updated_by,published_at)
                 VALUES
                    (:title,:slug,:post_type,:column_name,:status,:featured,
                     :sort_order,:section_label,:subtitle,:organization,
                     :location,:date_label,:start_date,:end_date,:is_current,
                     :summary,:body,:achievements,:skills,:link_url,:link_label,
                     :created_by,:updated_by,:published_at)'
            );
            $statement->execute(
                $values + [
                    'created_by' => (int)$user['id'],
                ]
            );
            $id = (int)db()->lastInsertId();
            $event = 'resume_post_created';
            $message = 'Resume post created.';
        }

        publishing_create_resume_revision(
            $id,
            'manual',
            (int)$user['id']
        );

        log_activity(
            $event,
            'resume_post',
            $id,
            [
                'type' => $postType,
                'status' => $status,
            ]
        );
        flash('success', $message);
        redirect(
            'portal/admin.php?view=resume&edit=' . $id
        );
    }

    if ($action === 'duplicate_resume_post') {
        $newId = publishing_duplicate_resume_post(
            int_input('id'),
            (int)$user['id']
        );
        log_activity(
            'resume_post_duplicated',
            'resume_post',
            $newId
        );
        flash(
            'success',
            'Resume post duplicated as a draft.'
        );
        redirect(
            'portal/admin.php?view=resume&edit=' . $newId
        );
    }

    if ($action === 'restore_resume_revision') {
        $postId = publishing_restore_resume_revision(
            int_input('revision_id'),
            (int)$user['id']
        );
        log_activity(
            'resume_revision_restored',
            'resume_post',
            $postId
        );
        flash('success', 'Resume revision restored.');
        redirect(
            'portal/admin.php?view=resume&edit=' . $postId
        );
    }

    if ($action === 'archive_resume_post') {
        $id = int_input('id');

        db()->prepare(
            'UPDATE resume_posts
             SET status="archived"
             WHERE id=:id'
        )->execute(['id' => $id]);

        log_activity(
            'resume_post_archived',
            'resume_post',
            $id
        );
        flash('success', 'Resume post archived.');
        redirect('portal/admin.php?view=resume');
    }


    if ($action === 'delete_resume_post') {
        $id = int_input('id');
        $statement = db()->prepare(
            'SELECT id,title
             FROM resume_posts
             WHERE id=:id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $post = $statement->fetch();

        if (!$post) {
            throw new RuntimeException('Resume post not found.');
        }

        db()->prepare(
            'DELETE FROM resume_posts
             WHERE id=:id'
        )->execute(['id' => $id]);

        log_activity(
            'resume_post_deleted',
            'resume_post',
            $id,
            ['title' => (string)$post['title']]
        );
        flash('success', 'Resume post was permanently deleted.');
        redirect('portal/admin.php?view=resume');
    }

    return true;
}

function publishing_render_migration_required(
    string $system
): void {
?>
<section class="panel publishing-migration-panel">
<header class="panel-header">
<div>
<span>Database migration required</span>
<h2><?=e($system)?> is ready to install</h2>
</div>
</header>
<div class="panel-body">
<p>
Import <code>database/publishing_systems_v51.sql</code>.
The migration creates Blog Posts, Blog Media, and Resume Posts,
then converts the current public resume into editable database records.
</p>
</div>
</section>
<?php
}

function publishing_render_blog_admin(
    array $user
): void {
    if (!publishing_schema_available()) {
        publishing_render_migration_required('Blog');
        return;
    }

    if (!publishing_workflow_schema_available()) {
        publishing_render_workflow_migration();
        return;
    }

    $blogSettings = publishing_blog_settings();
    $adminUsers = publishing_admin_users();

    $editValue = (string)($_GET['edit'] ?? '');
    $postId = ctype_digit($editValue)
        ? (int)$editValue
        : 0;
    $selected = $postId > 0
        ? blog_admin_post($postId)
        : null;
    $editing = $editValue === 'new' || $selected;

    if (!$editing) {
        $posts = blog_admin_posts();
        $publishedCount = count(array_filter(
            $posts,
            static fn(array $post): bool =>
                $post['status'] === 'published'
        ));
        $draftCount = count(array_filter(
            $posts,
            static fn(array $post): bool =>
                $post['status'] === 'draft'
        ));
        $scheduledCount = count(array_filter(
            $posts,
            static fn(array $post): bool =>
                publishing_publication_state(
                    $post
                )['key'] === 'scheduled'
        ));
?>
<div class="stats-grid publishing-stats">
<article class="stat-card">
<span>Blog posts</span>
<strong><?=count($posts)?></strong>
<small>All publishing records</small>
</article>
<article class="stat-card">
<span>Published</span>
<strong><?=$publishedCount?></strong>
<small>Visible to public visitors</small>
</article>
<article class="stat-card">
<span>Drafts</span>
<strong><?=$draftCount?></strong>
<small>Administrator-only work</small>
</article>
<article class="stat-card">
<span>Scheduled</span>
<strong><?=$scheduledCount?></strong>
<small>Published when their date arrives</small>
</article>
</div>

<div class="page-actions">
<a
    class="button button-primary"
    href="?view=blog&edit=new"
>Create blog post</a>
<a
    class="button"
    href="<?=e(app_url('blog.php'))?>"
    target="_blank"
    rel="noopener"
>Open public blog</a>
<?php if($blogSettings['rss_enabled']):?>
<a
    class="button"
    href="<?=e(app_url('blog-feed.php'))?>"
    target="_blank"
    rel="noopener"
>RSS feed</a>
<?php endif;?>
<?php if($blogSettings['sitemap_enabled']):?>
<a
    class="button"
    href="<?=e(app_url('sitemap.php'))?>"
    target="_blank"
    rel="noopener"
>XML sitemap</a>
<?php endif;?>
</div>

<?php publishing_render_blog_settings_panel(
    $blogSettings,
    $adminUsers
);?>

<?php publishing_render_analytics_panel('blog',30);?>
<?php content_interactions_render_admin_summary();?>

<?php if($posts):?>
<div class="publishing-admin-grid">
<?php foreach($posts as $post):?>
<?php $publicationState=publishing_publication_state($post);?>
<article class="publishing-admin-card">
<div class="publishing-admin-cover">
<?php if($post['cover_media_id']):?>
<img
    src="<?=e(blog_media_url((int)$post['cover_media_id']))?>"
    alt=""
>
<?php else:?>
<div class="publishing-cover-placeholder">
<span><?=e(mb_strtoupper(mb_substr($post['title'],0,1)))?></span>
</div>
<?php endif;?>
<div class="publishing-admin-badges">
<span class="status status-<?=e($publicationState['key'])?>">
<?=e($publicationState['label'])?>
</span>
<?php if((int)$post['featured']===1):?>
<span class="publishing-featured-badge">Featured</span>
<?php endif;?>
</div>
</div>
<div class="publishing-admin-copy">
<span><?=e($post['category']?:'Uncategorized')?></span>
<h2><?=e($post['title'])?></h2>
<p><?=e(
    $post['excerpt']
    ?: publishing_excerpt($post['body'])
)?></p>
<div class="publishing-admin-meta">
<span><?=e(
    $post['published_at']
        ? date('M j, Y',strtotime($post['published_at']))
        : 'Not scheduled'
)?></span>
<span><?=e($post['author_name']?:'Administrator')?></span>
</div>
</div>
<footer>
<a
    class="button button-small button-primary"
    href="?view=blog&edit=<?=(int)$post['id']?>"
>Manage</a>
<a
    class="button button-small"
    href="<?=e(app_url(
        'blog-post.php?preview=1&id='
        .(int)$post['id']
    ))?>"
    target="_blank"
    rel="noopener"
>Preview</a>
<form
    method="post"
    data-confirm="Permanently delete this blog post, all revisions, and every uploaded image?"
    data-confirm-title="Delete blog post?"
    data-confirm-eyebrow="Permanent deletion"
    data-confirm-action="Delete post"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_blog_post">
<input type="hidden" name="id" value="<?=(int)$post['id']?>">
<button class="button button-small button-danger" type="submit">Delete</button>
</form>
</footer>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="panel">
<div class="empty-state">
No blog posts have been created.
</div>
</div>
<?php endif;?>
<?php
        publishing_render_workflow_script();
        return;
    }

    $media = $selected['media'] ?? [];
    $richMediaTracks = blog_rich_media_tracks_for_admin();
?>
<div class="page-actions">
<a class="button" href="?view=blog">← All blog posts</a>
<?php if($selected):?>
<a
    class="button"
    href="<?=e(app_url(
        'blog-post.php?preview=1&id='
        .(int)$selected['id']
    ))?>"
    target="_blank"
    rel="noopener"
>Preview saved version</a>
<form method="post" class="inline-form">
<?=csrf_field()?>
<input
    type="hidden"
    name="action"
    value="duplicate_blog_post"
>
<input
    type="hidden"
    name="id"
    value="<?=(int)$selected['id']?>"
>
<button class="button" type="submit">
Duplicate post
</button>
</form>
<?php endif;?>
</div>

<?php if($selected):?>
<?php publishing_render_autosave_banner(
    $selected,
    'blog'
);?>
<?php content_interactions_render_post_settings($selected);?>
<?php endif;?>

<div class="publishing-editor-layout">
<form
    method="post"
    enctype="multipart/form-data"
    class="form-panel publishing-editor-form"
    data-publishing-autosave="blog"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="save_blog_post">
<input
    type="hidden"
    name="id"
    value="<?=(int)($selected['id']??0)?>"
>

<header class="publishing-editor-header">
<div>
<span>Blog publishing</span>
<h2><?=e($selected['title']??'Create blog post')?></h2>
<p>
Write, organize, schedule, publish, and optimize a public article.
Body content supports plain paragraphs, ## headings, ### subheadings,
and - list items.
</p>
</div>
<span
    class="publishing-autosave-status"
    data-autosave-status
>Autosave ready</span>
</header>

<section class="publishing-form-section">
<header><span>Identity</span><h3>Post basics</h3></header>
<div class="form-grid">
<label class="field">
<span>Post title</span>
<input
    name="title"
    value="<?=e($selected['title']??'')?>"
    required
>
</label>
<label class="field">
<span>Slug</span>
<input
    name="slug"
    value="<?=e($selected['slug']??'')?>"
    placeholder="generated-from-title"
>
</label>
<label class="field">
<span>Status</span>
<select name="status">
<?php foreach(['draft','published','archived'] as $status):?>
<option
    value="<?=e($status)?>"
    <?=($selected['status']??'draft')===$status?'selected':''?>
><?=e(status_label($status))?></option>
<?php endforeach;?>
</select>
</label>
<label class="field">
<span>Category</span>
<input
    name="category"
    value="<?=e($selected['category']??'')?>"
    placeholder="Product Systems"
>
</label>
<label class="field">
<span>Author</span>
<select name="author_user_id">
<?php
$selectedAuthor=(int)(
    $selected['author_user_id']
    ?? $blogSettings['default_author_user_id']
    ?? $user['id']
);
?>
<?php foreach($adminUsers as $adminUser):?>
<option
    value="<?=(int)$adminUser['id']?>"
    <?=$selectedAuthor===(int)$adminUser['id']?'selected':''?>
>
<?=e(
    $adminUser['display_name']
    ?: $adminUser['email']
)?>
</option>
<?php endforeach;?>
</select>
</label>
<label class="field">
<span>Publish date</span>
<input
    type="datetime-local"
    name="published_at"
    value="<?=e(publishing_datetime_local(
        $selected['published_at']??''
    ))?>"
>
</label>
<label class="checkbox-row publishing-featured-control">
<input
    type="checkbox"
    name="featured"
    value="1"
    <?=(int)($selected['featured']??0)===1?'checked':''?>
>
<span>Feature this post on the Blog archive.</span>
</label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Story</span><h3>Excerpt and article</h3></header>
<div class="form-grid">
<label class="field full">
<span>Excerpt</span>
<textarea
    name="excerpt"
    rows="3"
    placeholder="Short archive and sharing summary"
><?=e($selected['excerpt']??'')?></textarea>
</label>
<label class="field full">
<span>Article body</span>
<textarea
    name="body"
    rows="20"
    required
    placeholder="Write the article in safe text formatting…"
><?=e($selected['body']??'')?></textarea>
<small>
Use ## Heading, ### Subheading, and - List item.
HTML is escaped for public safety.
</small>
</label>
</div>
</section>

<section class="publishing-form-section blog-rich-media-composer" data-blog-rich-media-composer>
<header><span>Rich media</span><h3>Video and audio blocks</h3><p>Insert approved media at the current cursor position in the article body.</p></header>
<div class="form-grid">
<label class="field full"><span>YouTube or Vimeo URL</span><input type="url" data-video-url placeholder="https://www.youtube.com/watch?v=..."></label>
<label class="field full"><span>Video caption</span><input data-video-caption maxlength="500" placeholder="Optional accessible title or caption"></label>
<div class="field full blog-rich-media-actions"><button class="button" type="button" data-insert-video>Insert video block</button></div>
<label class="field full"><span>Music Library track</span><select data-track-select><option value="">Choose an active public track</option><?php foreach($richMediaTracks as $track):?><option value="<?=(int)$track['id']?>"><?=e($track['title'].' · '.$track['artist'].' · '.$track['duration_label'])?></option><?php endforeach;?></select></label>
<label class="field full"><span>Audio caption</span><input data-track-caption maxlength="500" placeholder="Optional context for this recording"></label>
<div class="field full blog-rich-media-actions"><button class="button" type="button" data-insert-track <?=$richMediaTracks?'':'disabled'?>>Insert audio player</button><a class="button" href="<?=e(app_url('portal/admin.php?view=knowledge&section=add'))?>" target="_blank" rel="noopener">Upload audio</a><a class="button" href="<?=e(app_url('portal/admin.php?view=music&section=tracks'))?>" target="_blank" rel="noopener">Manage Music Library</a></div>
<small class="field full blog-rich-media-status" data-rich-media-status><?=$richMediaTracks?'Ready to insert media.':'Upload audio, adopt it into the Music Library, and set it Active/Public to make it selectable.'?></small>
</div>
</section>

<section class="publishing-form-section">
<header><span>Discovery</span><h3>Tags and SEO</h3></header>
<div class="form-grid">
<label class="field full">
<span>Tags</span>
<textarea
    name="tags"
    rows="3"
    placeholder="One tag per line"
><?=e($selected['tags']??'')?></textarea>
</label>
<label class="field">
<span>SEO title</span>
<input
    name="seo_title"
    maxlength="190"
    value="<?=e($selected['seo_title']??'')?>"
>
</label>
<label class="field">
<span>SEO description</span>
<textarea
    name="seo_description"
    maxlength="320"
    rows="3"
><?=e($selected['seo_description']??'')?></textarea>
</label>
<label class="field full">
<span>Canonical URL</span>
<input
    type="url"
    name="canonical_url"
    maxlength="500"
    value="<?=e($selected['canonical_url']??'')?>"
    placeholder="Leave blank to use the article URL"
>
</label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Media</span><h3>Cover and gallery</h3></header>
<div class="form-grid">
<label class="field full">
<span>New cover image</span>
<input
    type="file"
    name="cover_image"
    accept="image/jpeg,image/png,image/webp,image/gif"
>
<small>
Landscape images work best. Uploading a new cover moves the old cover
into the gallery.
</small>
</label>
<label class="field">
<span>Cover alt text</span>
<input name="cover_alt">
</label>
<label class="field">
<span>Cover caption</span>
<input name="cover_caption">
</label>
<label class="field full">
<span>Add gallery images</span>
<input
    type="file"
    name="gallery_images[]"
    accept="image/jpeg,image/png,image/webp,image/gif"
    multiple
>
</label>
<label class="field">
<span>Default gallery alt text</span>
<input name="gallery_alt">
</label>
<label class="field">
<span>Default gallery caption</span>
<input name="gallery_caption">
</label>
</div>
</section>

<div class="form-footer">
<button class="button button-primary" type="submit">
Save blog post
</button>
</div>
</form>

<aside class="publishing-editor-sidebar">
<section class="panel">
<header class="panel-header">
<div><span>Publishing</span><h2>Post controls</h2></div>
</header>
<div class="panel-body">
<p>
Published posts appear on the public Blog archive when their
publication date is reached.
</p>
<?php if($selected):?>
<form
    method="post"
    class="inline-form"
    data-confirm="Archive this blog post? It will be removed from the public Blog but can still be edited later."
    data-confirm-title="Archive blog post?"
    data-confirm-eyebrow="Publishing"
    data-confirm-action="Archive post"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="archive_blog_post">
<input type="hidden" name="id" value="<?=(int)$selected['id']?>">
<button class="button button-danger" type="submit">
Archive post
</button>
</form>
<div class="content-danger-zone">
<strong>Delete permanently</strong>
<p>Delete this post, its revision history, and every uploaded image. This cannot be undone.</p>
<form
    method="post"
    data-confirm="This permanently deletes the blog post, all revisions, and all uploaded images. This action cannot be undone."
    data-confirm-title="Delete blog post?"
    data-confirm-eyebrow="Permanent deletion"
    data-confirm-action="Delete post"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_blog_post">
<input type="hidden" name="id" value="<?=(int)$selected['id']?>">
<button class="button button-danger" type="submit">Delete post</button>
</form>
</div>
<?php endif;?>
</div>
</section>

<?php if($selected):?>
<section class="panel">
<header class="panel-header">
<div><span>Media library</span><h2><?=count($media)?> images</h2></div>
</header>
<?php if($media):?>
<div class="publishing-media-grid">
<?php foreach($media as $item):?>
<article
    class="publishing-media-card"
    data-media-card
>
<img
    data-media-preview
    src="<?=e(blog_media_url((int)$item['id']))?>"
    alt="<?=e($item['alt_text']??'')?>"
    style="<?=e(publishing_media_position_style($item))?>"
>
<form method="post" enctype="multipart/form-data">
<?=csrf_field()?>
<input type="hidden" name="action" value="update_blog_media">
<input type="hidden" name="post_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="media_id" value="<?=(int)$item['id']?>">
<label class="field">
<span>Role</span>
<select name="media_role">
<option value="cover" <?=$item['media_role']==='cover'?'selected':''?>>Cover</option>
<option value="gallery" <?=$item['media_role']==='gallery'?'selected':''?>>Gallery</option>
</select>
</label>
<label class="field">
<span>Alt text</span>
<input name="alt_text" value="<?=e($item['alt_text']??'')?>">
</label>
<label class="field">
<span>Caption</span>
<textarea name="caption" rows="2"><?=e($item['caption']??'')?></textarea>
</label>
<label class="field">
<span>Crop ratio</span>
<select name="crop_ratio">
<?php foreach([
    'original'=>'Original',
    '16:9'=>'16:9 landscape',
    '4:3'=>'4:3 landscape',
    '1:1'=>'Square',
    '3:4'=>'3:4 portrait',
] as $ratio=>$label):?>
<option
    value="<?=e($ratio)?>"
    <?=($item['crop_ratio']??'original')===$ratio?'selected':''?>
><?=e($label)?></option>
<?php endforeach;?>
</select>
</label>
<label class="field">
<span>
Horizontal focal point
<small data-focal-output="focal_x"></small>
</span>
<input
    type="range"
    name="focal_x"
    min="0"
    max="100"
    step="1"
    value="<?=e((string)($item['focal_x']??50))?>"
    data-focal-control
>
</label>
<label class="field">
<span>
Vertical focal point
<small data-focal-output="focal_y"></small>
</span>
<input
    type="range"
    name="focal_y"
    min="0"
    max="100"
    step="1"
    value="<?=e((string)($item['focal_y']??50))?>"
    data-focal-control
>
</label>
<label class="field">
<span>Replace image</span>
<input
    type="file"
    name="replacement_image"
    accept="image/jpeg,image/png,image/webp,image/gif"
>
</label>
<label class="field">
<span>Order</span>
<input
    type="number"
    name="sort_order"
    min="0"
    value="<?=(int)$item['sort_order']?>"
>
</label>
<button class="button button-small" type="submit">
Update image
</button>
</form>
<form
    method="post"
    data-confirm="Permanently delete this blog image from the post and storage?"
    data-confirm-title="Delete blog image?"
    data-confirm-eyebrow="Blog media"
    data-confirm-action="Delete image"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_blog_media">
<input type="hidden" name="post_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="media_id" value="<?=(int)$item['id']?>">
<button class="button button-small button-danger" type="submit">
Delete
</button>
</form>
</article>
<?php endforeach;?>
</div>
<?php else:?>
<div class="empty-state">
Upload a cover or gallery image when saving the post.
</div>
<?php endif;?>
</section>
<?php if($selected):?>
<?php publishing_render_revision_panel(
    'blog',
    (int)$selected['id']
);?>
<?php endif;?>
<?php endif;?>
</aside>
</div>
<?php publishing_render_workflow_script();?>
<?php
}

function publishing_render_resume_admin(
    array $user
): void {
    if (!publishing_schema_available()) {
        publishing_render_migration_required('Resume Posts');
        return;
    }

    if (!publishing_workflow_schema_available()) {
        publishing_render_workflow_migration();
        return;
    }

    $editValue = (string)($_GET['edit'] ?? '');
    $postId = ctype_digit($editValue)
        ? (int)$editValue
        : 0;
    $selected = $postId > 0
        ? resume_admin_post($postId)
        : null;
    $editing = $editValue === 'new' || $selected;

    $types = [
        'profile' => 'Profile / Hero',
        'experience' => 'Experience',
        'education' => 'Education',
        'skill_group' => 'Skill Group',
        'strengths' => 'Strengths',
        'certification' => 'Certification',
        'award' => 'Award',
        'project' => 'Project',
        'volunteer' => 'Volunteer',
        'custom' => 'Custom',
    ];

    if (!$editing) {
        $posts = resume_admin_posts();
        $publishedCount = count(array_filter(
            $posts,
            static fn(array $post): bool =>
                $post['status'] === 'published'
        ));
        $mainCount = count(array_filter(
            $posts,
            static fn(array $post): bool =>
                $post['column_name'] === 'main'
        ));
        $sidebarCount = count($posts) - $mainCount;
?>
<div class="stats-grid publishing-stats">
<article class="stat-card">
<span>Resume posts</span>
<strong><?=count($posts)?></strong>
<small>All structured entries</small>
</article>
<article class="stat-card">
<span>Published</span>
<strong><?=$publishedCount?></strong>
<small>Visible on the public resume</small>
</article>
<article class="stat-card">
<span>Main column</span>
<strong><?=$mainCount?></strong>
<small>Profile and career entries</small>
</article>
<article class="stat-card">
<span>Sidebar</span>
<strong><?=$sidebarCount?></strong>
<small>Skills, strengths, and education</small>
</article>
</div>

<div class="page-actions">
<a
    class="button button-primary"
    href="?view=resume&edit=new"
>Create resume post</a>
<a
    class="button"
    href="<?=e(app_url('index.php?mode=resume'))?>"
    target="_blank"
    rel="noopener"
>Open public resume</a>
</div>

<?php publishing_render_analytics_panel('resume',30);?>

<?php if($posts):?>
<?php publishing_render_resume_sortable(
    $posts,
    $types
);?>
<?php else:?>
<div class="panel">
<div class="empty-state">
No resume posts have been created.
</div>
</div>
<?php endif;?>
<?php publishing_render_workflow_script();?>
<?php
        return;
    }
?>
<div class="page-actions">
<a class="button" href="?view=resume">← All resume posts</a>
<?php if($selected):?>
<a
    class="button"
    href="<?=e(app_url(
        'resume-post.php?preview=1&id='
        .(int)$selected['id']
    ))?>"
    target="_blank"
    rel="noopener"
>Preview saved version</a>
<form method="post" class="inline-form">
<?=csrf_field()?>
<input
    type="hidden"
    name="action"
    value="duplicate_resume_post"
>
<input
    type="hidden"
    name="id"
    value="<?=(int)$selected['id']?>"
>
<button class="button" type="submit">
Duplicate resume post
</button>
</form>
<?php endif;?>
</div>

<?php if($selected):?>
<?php publishing_render_autosave_banner(
    $selected,
    'resume'
);?>
<?php endif;?>

<div class="publishing-editor-layout resume-editor-layout">
<form
    method="post"
    class="form-panel publishing-editor-form"
    data-publishing-autosave="resume"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="save_resume_post">
<input
    type="hidden"
    name="id"
    value="<?=(int)($selected['id']??0)?>"
>

<header class="publishing-editor-header">
<div>
<span>Resume publishing</span>
<h2><?=e($selected['title']??'Create resume post')?></h2>
<p>
Every visible resume section is a structured post. Profile posts power
the hero; main-column posts power experience and projects; sidebar
posts power focus, skills, strengths, and education.
</p>
</div>
<span
    class="publishing-autosave-status"
    data-autosave-status
>Autosave ready</span>
</header>

<section class="publishing-form-section">
<header><span>Identity</span><h3>Post basics</h3></header>
<div class="form-grid">
<label class="field">
<span>Title or role</span>
<input
    name="title"
    value="<?=e($selected['title']??'')?>"
    required
>
</label>
<label class="field">
<span>Slug</span>
<input
    name="slug"
    value="<?=e($selected['slug']??'')?>"
    placeholder="generated-from-title"
>
</label>
<label class="field">
<span>Post type</span>
<select name="post_type">
<?php foreach($types as $value=>$label):?>
<option
    value="<?=e($value)?>"
    <?=($selected['post_type']??'experience')===$value?'selected':''?>
><?=e($label)?></option>
<?php endforeach;?>
</select>
</label>
<label class="field">
<span>Resume column</span>
<select name="column_name">
<option value="main" <?=($selected['column_name']??'main')==='main'?'selected':''?>>Main column</option>
<option value="sidebar" <?=($selected['column_name']??'main')==='sidebar'?'selected':''?>>Sidebar</option>
</select>
</label>
<label class="field">
<span>Status</span>
<select name="status">
<?php foreach(['draft','published','archived'] as $status):?>
<option
    value="<?=e($status)?>"
    <?=($selected['status']??'draft')===$status?'selected':''?>
><?=e(status_label($status))?></option>
<?php endforeach;?>
</select>
</label>
<label class="field">
<span>Display order</span>
<input
    type="number"
    name="sort_order"
    min="0"
    value="<?=(int)($selected['sort_order']??100)?>"
>
</label>
<label class="checkbox-row">
<input
    type="checkbox"
    name="featured"
    value="1"
    <?=(int)($selected['featured']??0)===1?'checked':''?>
>
<span>Feature or prioritize this entry.</span>
</label>
<label class="checkbox-row">
<input
    type="checkbox"
    name="is_current"
    value="1"
    <?=(int)($selected['is_current']??0)===1?'checked':''?>
>
<span>This role or activity is current.</span>
</label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Heading</span><h3>Organization and dates</h3></header>
<div class="form-grid">
<label class="field">
<span>Section label</span>
<input
    name="section_label"
    value="<?=e($selected['section_label']??'')?>"
    placeholder="Professional experience"
>
</label>
<label class="field">
<span>Subtitle</span>
<input
    name="subtitle"
    value="<?=e($selected['subtitle']??'')?>"
    placeholder="Hero headline or secondary title"
>
</label>
<label class="field">
<span>Organization</span>
<input
    name="organization"
    value="<?=e($selected['organization']??'')?>"
>
</label>
<label class="field">
<span>Location</span>
<input
    name="location"
    value="<?=e($selected['location']??'')?>"
>
</label>
<label class="field">
<span>Display date</span>
<input
    name="date_label"
    value="<?=e($selected['date_label']??'')?>"
    placeholder="May 2024–Present"
>
</label>
<label class="field">
<span>Publish date</span>
<input
    type="datetime-local"
    name="published_at"
    value="<?=e(publishing_datetime_local(
        $selected['published_at']??''
    ))?>"
>
</label>
<label class="field">
<span>Start date</span>
<input
    type="date"
    name="start_date"
    value="<?=e($selected['start_date']??'')?>"
>
</label>
<label class="field">
<span>End date</span>
<input
    type="date"
    name="end_date"
    value="<?=e($selected['end_date']??'')?>"
>
</label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Content</span><h3>Summary and details</h3></header>
<div class="form-grid">
<label class="field full">
<span>Summary</span>
<textarea name="summary" rows="5"><?=e($selected['summary']??'')?></textarea>
</label>
<label class="field full">
<span>Extended body</span>
<textarea
    name="body"
    rows="8"
    placeholder="Optional detail-page content"
><?=e($selected['body']??'')?></textarea>
</label>
<label class="field">
<span>Achievements or bullets</span>
<textarea
    name="achievements"
    rows="12"
    placeholder="One item per line"
><?=e($selected['achievements']??'')?></textarea>
</label>
<label class="field">
<span>Skills or tags</span>
<textarea
    name="skills"
    rows="12"
    placeholder="One item per line"
><?=e($selected['skills']??'')?></textarea>
</label>
</div>
</section>

<section class="publishing-form-section">
<header><span>Link</span><h3>Optional continuation</h3></header>
<div class="form-grid">
<label class="field">
<span>Link URL</span>
<input
    type="url"
    name="link_url"
    value="<?=e($selected['link_url']??'')?>"
    placeholder="https://"
>
</label>
<label class="field">
<span>Link label</span>
<input
    name="link_label"
    value="<?=e($selected['link_label']??'')?>"
    placeholder="View project"
>
</label>
</div>
</section>

<div class="form-footer">
<button class="button button-primary" type="submit">
Save resume post
</button>
</div>
</form>

<aside class="publishing-editor-sidebar">
<section class="panel">
<header class="panel-header">
<div><span>Resume structure</span><h2>Rendering rules</h2></div>
</header>
<div class="panel-body publishing-help-list">
<p><strong>Profile</strong> powers the public resume hero.</p>
<p><strong>Main column</strong> displays career, project, certification, award, volunteer, and custom posts.</p>
<p><strong>Sidebar</strong> displays focus, skill groups, strengths, education, and supporting posts.</p>
<p><strong>Achievements</strong> render as bullets. <strong>Skills</strong> render as chips.</p>
</div>
</section>

<?php if($selected):?>
<section class="panel">
<header class="panel-header">
<div><span>Publishing</span><h2>Entry controls</h2></div>
</header>
<div class="panel-body">
<form
    method="post"
    data-confirm="Archive this resume post? It will be removed from the public resume but can still be edited later."
    data-confirm-title="Archive resume post?"
    data-confirm-eyebrow="Publishing"
    data-confirm-action="Archive post"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="archive_resume_post">
<input type="hidden" name="id" value="<?=(int)$selected['id']?>">
<button class="button button-danger" type="submit">
Archive resume post
</button>
</form>
<div class="content-danger-zone">
<strong>Delete permanently</strong>
<p>Delete this resume entry and its complete revision history. This cannot be undone.</p>
<form
    method="post"
    data-confirm="This permanently deletes the resume post and all of its revisions. This action cannot be undone."
    data-confirm-title="Delete resume post?"
    data-confirm-eyebrow="Permanent deletion"
    data-confirm-action="Delete post"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_resume_post">
<input type="hidden" name="id" value="<?=(int)$selected['id']?>">
<button class="button button-danger" type="submit">Delete resume post</button>
</form>
</div>
</div>
</section>
<?php if($selected):?>
<?php publishing_render_revision_panel(
    'resume',
    (int)$selected['id']
);?>
<?php endif;?>
<?php endif;?>
</aside>
</div>
<?php publishing_render_workflow_script();?>
<?php
}

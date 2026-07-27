<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

function publishing_schema_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "blog_posts",
                    "blog_media",
                    "resume_posts"
               )'
        );
        $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function publishing_split_values(?string $value): array
{
    $parts = preg_split('/[\r\n,|]+/', (string)$value) ?: [];
    $output = [];

    foreach ($parts as $part) {
        $item = trim($part);

        if ($item !== '' && !in_array($item, $output, true)) {
            $output[] = $item;
        }
    }

    return $output;
}

function publishing_excerpt(
    ?string $value,
    int $limit = 220
): string {
    $text = trim(
        preg_replace(
            '/\s+/',
            ' ',
            strip_tags((string)$value)
        ) ?? ''
    );

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(
        mb_substr($text, 0, max(1, $limit - 1))
    ) . '…';
}

function publishing_render_body(?string $value): string
{
    $lines = preg_split(
        '/\R/',
        trim((string)$value)
    ) ?: [];
    $html = [];
    $paragraph = [];
    $list = [];

    $flushParagraph = static function () use (
        &$paragraph,
        &$html
    ): void {
        if (!$paragraph) {
            return;
        }

        $html[] = '<p>'
            . e(implode(' ', $paragraph))
            . '</p>';
        $paragraph = [];
    };

    $flushList = static function () use (
        &$list,
        &$html
    ): void {
        if (!$list) {
            return;
        }

        $items = array_map(
            static fn(string $item): string =>
                '<li>' . e($item) . '</li>',
            $list
        );

        $html[] = '<ul>' . implode('', $items) . '</ul>';
        $list = [];
    };

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);

        if ($line === '') {
            $flushParagraph();
            $flushList();
            continue;
        }

        if (str_starts_with($line, '### ')) {
            $flushParagraph();
            $flushList();
            $html[] = '<h3>' . e(substr($line, 4)) . '</h3>';
            continue;
        }

        if (str_starts_with($line, '## ')) {
            $flushParagraph();
            $flushList();
            $html[] = '<h2>' . e(substr($line, 3)) . '</h2>';
            continue;
        }

        if (
            str_starts_with($line, '- ')
            || str_starts_with($line, '* ')
        ) {
            $flushParagraph();
            $list[] = trim(substr($line, 2));
            continue;
        }

        if ($list) {
            $flushList();
        }

        $paragraph[] = $line;
    }

    $flushParagraph();
    $flushList();

    return implode("\n", $html);
}

function publishing_datetime_local(?string $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp
        ? gmdate('Y-m-d\TH:i', $timestamp)
        : '';
}

function publishing_normalize_datetime(
    ?string $value
): ?string {
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    $timestamp = strtotime($value);

    if (!$timestamp) {
        throw new RuntimeException(
            'Enter a valid publication date and time.'
        );
    }

    return gmdate('Y-m-d H:i:s', $timestamp);
}

function publishing_image_limit_bytes(): int
{
    $app = nmm_config('app');

    return max(
        5 * 1024 * 1024,
        (int)(
            $app['max_blog_image_bytes']
            ?? 10 * 1024 * 1024
        )
    );
}

function blog_storage_directory(): string
{
    $directory = NMM_ROOT . '/storage/blog-media';

    if (
        !is_dir($directory)
        && !mkdir($directory, 0750, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'The blog-media storage directory could not be created.'
        );
    }

    return $directory;
}

function blog_media_url(int $mediaId): string
{
    return app_url('blog-media.php?id=' . $mediaId);
}

function blog_post_media(int $postId): array
{
    if (!publishing_schema_available() || $postId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT *
         FROM blog_media
         WHERE post_id=:post_id
         ORDER BY
            CASE WHEN media_role="cover" THEN 0 ELSE 1 END,
            sort_order ASC,
            id ASC'
    );
    $statement->execute(['post_id' => $postId]);

    return $statement->fetchAll();
}

function blog_post_payload(
    array $post,
    array $media = []
): array {
    $cover = null;
    $gallery = [];

    foreach ($media as $item) {
        $payload = [
            'id' => (int)$item['id'],
            'url' => blog_media_url((int)$item['id']),
            'alt' => trim((string)($item['alt_text'] ?? '')),
            'caption' => trim((string)($item['caption'] ?? '')),
            'width' => (int)($item['width_px'] ?? 0),
            'height' => (int)($item['height_px'] ?? 0),
            'focal_x' => (float)($item['focal_x'] ?? 50),
            'focal_y' => (float)($item['focal_y'] ?? 50),
            'crop_ratio' => (string)(
                $item['crop_ratio'] ?? 'original'
            ),
        ];

        if (
            ($item['media_role'] ?? '') === 'cover'
            && $cover === null
        ) {
            $cover = $payload;
        } else {
            $gallery[] = $payload;
        }
    }

    $publishedAt = (string)(
        $post['published_at']
        ?? $post['created_at']
        ?? ''
    );

    return [
        'id' => (int)$post['id'],
        'title' => (string)$post['title'],
        'slug' => (string)$post['slug'],
        'status' => (string)$post['status'],
        'featured' => (int)$post['featured'] === 1,
        'category' => trim((string)($post['category'] ?? '')),
        'excerpt' => trim((string)(
            $post['excerpt']
            ?? publishing_excerpt($post['body'] ?? '')
        )),
        'body' => (string)($post['body'] ?? ''),
        'body_html' => publishing_render_body(
            (string)($post['body'] ?? '')
        ),
        'tags' => publishing_split_values(
            (string)($post['tags'] ?? '')
        ),
        'seo_title' => trim((string)($post['seo_title'] ?? '')),
        'seo_description' => trim(
            (string)($post['seo_description'] ?? '')
        ),
        'canonical_url' => trim(
            (string)($post['canonical_url'] ?? '')
        ),
        'author_user_id' => (int)(
            $post['author_user_id'] ?? 0
        ),
        'author_name' => trim(
            (string)($post['author_name'] ?? 'David Evans')
        ),
        'published_at' => $publishedAt,
        'published_label' => $publishedAt !== ''
            ? date('F j, Y', strtotime($publishedAt))
            : '',
        'updated_at' => (string)($post['updated_at'] ?? ''),
        'cover' => $cover,
        'gallery' => $gallery,
        'url' => app_url(
            'blog-post.php?slug='
            . rawurlencode((string)$post['slug'])
        ),
    ];
}

function blog_admin_posts(): array
{
    if (!publishing_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT post.*,
                user.display_name AS author_name,
                cover.id AS cover_media_id
         FROM blog_posts post
         LEFT JOIN users user
           ON user.id=post.author_user_id
         LEFT JOIN blog_media cover
           ON cover.id=(
                SELECT media.id
                FROM blog_media media
                WHERE media.post_id=post.id
                  AND media.media_role="cover"
                ORDER BY media.sort_order ASC,media.id ASC
                LIMIT 1
           )
         ORDER BY
            CASE post.status
                WHEN "published" THEN 0
                WHEN "draft" THEN 1
                ELSE 2
            END,
            post.featured DESC,
            COALESCE(post.published_at,post.updated_at) DESC,
            post.id DESC'
    )->fetchAll();
}

function blog_admin_post(int $postId): ?array
{
    if (!publishing_schema_available() || $postId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT post.*,
                user.display_name AS author_name
         FROM blog_posts post
         LEFT JOIN users user
           ON user.id=post.author_user_id
         WHERE post.id=:post_id
         LIMIT 1'
    );
    $statement->execute(['post_id' => $postId]);
    $post = $statement->fetch();

    if (!$post) {
        return null;
    }

    $post['media'] = blog_post_media($postId);

    return $post;
}

function blog_public_posts(
    ?string $category = null,
    ?string $search = null,
    int $limit = 100,
    int $offset = 0
): array {
    if (!publishing_schema_available()) {
        return [];
    }

    $where = [
        'post.status="published"',
        '(post.published_at IS NULL OR post.published_at<=UTC_TIMESTAMP())',
    ];
    $parameters = [];

    $category = trim((string)$category);
    $search = trim((string)$search);

    if ($category !== '') {
        $where[] = 'post.category=:category';
        $parameters['category'] = $category;
    }

    if ($search !== '') {
        $where[] = '(
            post.title LIKE :search
            OR post.excerpt LIKE :search
            OR post.body LIKE :search
            OR post.tags LIKE :search
        )';
        $parameters['search'] = '%' . $search . '%';
    }

    $statement = db()->prepare(
        'SELECT post.*,
                user.display_name AS author_name
         FROM blog_posts post
         LEFT JOIN users user
           ON user.id=post.author_user_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY
            post.featured DESC,
            COALESCE(post.published_at,post.created_at) DESC,
            post.id DESC
         LIMIT ' . max(1, min(250, $limit))
         . ' OFFSET ' . max(0, $offset)
    );
    $statement->execute($parameters);

    $output = [];

    foreach ($statement->fetchAll() as $post) {
        $output[] = blog_post_payload(
            $post,
            blog_post_media((int)$post['id'])
        );
    }

    return $output;
}

function blog_public_post_count(
    ?string $category = null,
    ?string $search = null
): int {
    if (!publishing_schema_available()) {
        return 0;
    }

    $where = [
        'status="published"',
        '(published_at IS NULL OR published_at<=UTC_TIMESTAMP())',
    ];
    $parameters = [];
    $category = trim((string)$category);
    $search = trim((string)$search);

    if ($category !== '') {
        $where[] = 'category=:category';
        $parameters['category'] = $category;
    }

    if ($search !== '') {
        $where[] = '(
            title LIKE :search
            OR excerpt LIKE :search
            OR body LIKE :search
            OR tags LIKE :search
        )';
        $parameters['search'] = '%' . $search . '%';
    }

    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM blog_posts
         WHERE ' . implode(' AND ', $where)
    );
    $statement->execute($parameters);

    return (int)$statement->fetchColumn();
}

function blog_public_post_by_slug(
    string $slug
): ?array {
    if (!publishing_schema_available()) {
        return null;
    }

    $slug = slugify($slug);

    if ($slug === '') {
        return null;
    }

    $statement = db()->prepare(
        'SELECT post.*,
                user.display_name AS author_name
         FROM blog_posts post
         LEFT JOIN users user
           ON user.id=post.author_user_id
         WHERE post.slug=:slug
           AND post.status="published"
           AND (
                post.published_at IS NULL
                OR post.published_at<=UTC_TIMESTAMP()
           )
         LIMIT 1'
    );
    $statement->execute(['slug' => $slug]);
    $post = $statement->fetch();

    return $post
        ? blog_post_payload(
            $post,
            blog_post_media((int)$post['id'])
        )
        : null;
}

function blog_public_categories(): array
{
    if (!publishing_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT category,COUNT(*) AS post_count
         FROM blog_posts
         WHERE status="published"
           AND category IS NOT NULL
           AND category<>""
           AND (
                published_at IS NULL
                OR published_at<=UTC_TIMESTAMP()
           )
         GROUP BY category
         ORDER BY category'
    )->fetchAll();
}

function blog_multiple_uploads(array $files): array
{
    if (
        !isset($files['name'])
        || !is_array($files['name'])
    ) {
        return [];
    }

    $uploads = [];
    $count = count($files['name']);

    for ($index = 0; $index < $count; $index++) {
        $error = (int)(
            $files['error'][$index]
            ?? UPLOAD_ERR_NO_FILE
        );

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $uploads[] = [
            'name' => (string)($files['name'][$index] ?? ''),
            'type' => (string)($files['type'][$index] ?? ''),
            'tmp_name' => (string)($files['tmp_name'][$index] ?? ''),
            'error' => $error,
            'size' => (int)($files['size'][$index] ?? 0),
        ];
    }

    return $uploads;
}

function blog_store_image(
    array $upload,
    int $postId,
    string $role,
    int $userId,
    string $altText = '',
    string $caption = '',
    int $sortOrder = 0
): int {
    if (!publishing_schema_available()) {
        throw new RuntimeException(
            'Import database/publishing_systems_v51.sql before uploading blog media.'
        );
    }

    if ($postId <= 0 || $userId <= 0) {
        throw new RuntimeException(
            'Save the blog post before uploading images.'
        );
    }

    if (!in_array($role, ['cover', 'gallery'], true)) {
        throw new RuntimeException(
            'The blog media role is invalid.'
        );
    }

    if (
        (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'The blog image upload did not complete.'
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
            'Blog images must be valid uploads no larger than '
            . format_bytes($limit)
            . '.'
        );
    }

    $imageInfo = @getimagesize($temporary);

    if (!is_array($imageInfo)) {
        throw new RuntimeException(
            'The uploaded blog file is not a valid image.'
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
            'Blog images must be JPG, PNG, WebP, or GIF files.'
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
            'The blog image could not be stored.'
        );
    }

    chmod($destination, 0640);

    $pdo = db();

    try {
        $pdo->beginTransaction();

        if ($role === 'cover') {
            $pdo->prepare(
                'UPDATE blog_media
                 SET media_role="gallery",
                     sort_order=sort_order+1
                 WHERE post_id=:post_id
                   AND media_role="cover"'
            )->execute(['post_id' => $postId]);
        }

        $statement = $pdo->prepare(
            'INSERT INTO blog_media
                (post_id,media_role,original_name,stored_name,mime_type,
                 size_bytes,width_px,height_px,alt_text,caption,sort_order,
                 created_by)
             VALUES
                (:post_id,:media_role,:original_name,:stored_name,:mime_type,
                 :size_bytes,:width_px,:height_px,:alt_text,:caption,
                 :sort_order,:created_by)'
        );
        $statement->execute([
            'post_id' => $postId,
            'media_role' => $role,
            'original_name' => substr(
                basename((string)($upload['name'] ?? 'image')),
                0,
                255
            ),
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'width_px' => (int)($imageInfo[0] ?? 0) ?: null,
            'height_px' => (int)($imageInfo[1] ?? 0) ?: null,
            'alt_text' => substr(trim($altText), 0, 500) ?: null,
            'caption' => substr(trim($caption), 0, 500) ?: null,
            'sort_order' => max(0, $sortOrder),
            'created_by' => $userId,
        ]);

        $mediaId = (int)$pdo->lastInsertId();
        $pdo->commit();

        return $mediaId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        @unlink($destination);
        throw $exception;
    }
}

function blog_delete_media_file(array $media): void
{
    $storedName = basename(
        (string)($media['stored_name'] ?? '')
    );

    if ($storedName === '') {
        return;
    }

    $path = blog_storage_directory() . '/' . $storedName;

    if (is_file($path)) {
        @unlink($path);
    }
}

function resume_post_payload(array $post): array
{
    return [
        'id' => (int)$post['id'],
        'title' => (string)$post['title'],
        'slug' => (string)$post['slug'],
        'post_type' => (string)$post['post_type'],
        'column_name' => (string)$post['column_name'],
        'status' => (string)$post['status'],
        'featured' => (int)$post['featured'] === 1,
        'sort_order' => (int)$post['sort_order'],
        'section_label' => trim(
            (string)($post['section_label'] ?? '')
        ),
        'subtitle' => trim(
            (string)($post['subtitle'] ?? '')
        ),
        'organization' => trim(
            (string)($post['organization'] ?? '')
        ),
        'location' => trim(
            (string)($post['location'] ?? '')
        ),
        'date_label' => trim(
            (string)($post['date_label'] ?? '')
        ),
        'start_date' => (string)($post['start_date'] ?? ''),
        'end_date' => (string)($post['end_date'] ?? ''),
        'is_current' => (int)($post['is_current'] ?? 0) === 1,
        'summary' => trim(
            (string)($post['summary'] ?? '')
        ),
        'body' => trim((string)($post['body'] ?? '')),
        'body_html' => publishing_render_body(
            (string)($post['body'] ?? '')
        ),
        'achievements' => publishing_split_values(
            (string)($post['achievements'] ?? '')
        ),
        'skills' => publishing_split_values(
            (string)($post['skills'] ?? '')
        ),
        'link_url' => trim(
            (string)($post['link_url'] ?? '')
        ),
        'link_label' => trim(
            (string)($post['link_label'] ?? '')
        ),
        'published_at' => (string)(
            $post['published_at'] ?? ''
        ),
        'updated_at' => (string)(
            $post['updated_at'] ?? ''
        ),
        'url' => app_url(
            'resume-post.php?slug='
            . rawurlencode((string)$post['slug'])
        ),
    ];
}

function resume_admin_posts(): array
{
    if (!publishing_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT *
         FROM resume_posts
         ORDER BY
            CASE status
                WHEN "published" THEN 0
                WHEN "draft" THEN 1
                ELSE 2
            END,
            CASE column_name
                WHEN "main" THEN 0
                ELSE 1
            END,
            sort_order ASC,
            id ASC'
    )->fetchAll();
}

function resume_admin_post(int $postId): ?array
{
    if (!publishing_schema_available() || $postId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM resume_posts
         WHERE id=:post_id
         LIMIT 1'
    );
    $statement->execute(['post_id' => $postId]);
    $post = $statement->fetch();

    return $post ?: null;
}

function resume_public_posts(): array
{
    if (!publishing_schema_available()) {
        return [];
    }

    $rows = db()->query(
        'SELECT *
         FROM resume_posts
         WHERE status="published"
           AND (
                published_at IS NULL
                OR published_at<=UTC_TIMESTAMP()
           )
         ORDER BY
            CASE column_name
                WHEN "main" THEN 0
                ELSE 1
            END,
            featured DESC,
            sort_order ASC,
            id ASC'
    )->fetchAll();

    return array_map(
        'resume_post_payload',
        $rows
    );
}

function resume_public_post_by_slug(
    string $slug
): ?array {
    if (!publishing_schema_available()) {
        return null;
    }

    $slug = slugify($slug);

    if ($slug === '') {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM resume_posts
         WHERE slug=:slug
           AND status="published"
           AND (
                published_at IS NULL
                OR published_at<=UTC_TIMESTAMP()
           )
         LIMIT 1'
    );
    $statement->execute(['slug' => $slug]);
    $post = $statement->fetch();

    return $post
        ? resume_post_payload($post)
        : null;
}

function resume_fallback_payload(): array
{
    $profile = [
        'id' => 0,
        'title' => 'David Evans',
        'slug' => 'david-evans-profile',
        'post_type' => 'profile',
        'column_name' => 'main',
        'status' => 'published',
        'featured' => true,
        'sort_order' => 1,
        'section_label' => 'Operations · Inventory · Process Improvement',
        'subtitle' => 'Distribution · Ecommerce · Procurement Systems · AI-Assisted Business Intelligence',
        'organization' => '',
        'location' => 'Phoenix, Arizona',
        'date_label' => '',
        'summary' => 'Operations and systems professional with more than 20 years of experience across ecommerce, inventory coordination, distribution, CRM workflows, customer operations and digital product development. Supported a high-volume Amazon retail catalog exceeding 100,000 SKUs and has hands-on experience connecting product data, inventory, fulfillment, billing and customer service. Known for identifying fragmented processes, organizing information and translating operational goals into practical workflows, dashboards and business systems.',
        'body' => '',
        'body_html' => '',
        'achievements' => [],
        'skills' => [],
        'link_url' => 'https://www.linkedin.com/in/david-evans-15005530/',
        'link_label' => 'LinkedIn',
        'url' => '',
    ];

    $experience = [
        [
            'title' => 'Founder & Systems / Product Operations Lead',
            'slug' => 'vp3-media-microgifter',
            'organization' => 'VP3 Media Corp. / Microgifter',
            'location' => 'Phoenix, Arizona',
            'date_label' => 'May 2024–Present',
            'summary' => 'Developing Microgifter, a side project addressing gaps in the gift-certificate market through digital gifting, merchant CRM, lifecycle tracking and automated commerce.',
            'achievements' => [
                'Define product architecture, data relationships, operational workflows, user roles, reporting needs, testing standards and release priorities across a production PHP/MySQL platform.',
                'Coordinate technical, product, customer, marketing and business workstreams while maintaining requirements, dependencies, QA, documentation and implementation follow-through.',
                'Turn fragmented customer, merchant, campaign, ownership, claim, redemption and reporting processes into structured and repeatable systems.',
                'Maintain implementation checklists, data dependencies, release validation and documented QA across ongoing product development.',
            ],
        ],
        [
            'title' => 'eCommerce Listing Specialist',
            'slug' => 'kodi-distributing',
            'organization' => 'Kodi Distributing',
            'location' => 'Phoenix, Arizona',
            'date_label' => 'September 2023–April 2024',
            'summary' => 'Supported high-volume ecommerce operations across Amazon and additional marketplace channels for a catalog exceeding 100,000 SKUs.',
            'achievements' => [
                'Created, maintained and optimized product listings while protecting product-data accuracy, categorization, consistency and catalog integrity at scale.',
                'Coordinated inventory updates and product availability across systems, supporting reliable marketplace, fulfillment and customer-order operations.',
                'Improved listing quality and merchandising structure while performing detailed QA in a complex multi-channel environment.',
                'Worked across marketing, inventory, product data and fulfillment teams to resolve issues and keep digital commerce workflows moving.',
                'Supported the accuracy and availability of product information used by customers, marketplace teams, inventory operations and fulfillment.',
            ],
        ],
        [
            'title' => 'Client Services Manager',
            'slug' => 'timeshare-attorneys-of-america',
            'organization' => 'Timeshare Attorneys of America',
            'location' => 'Phoenix, Arizona',
            'date_label' => 'June 2010–September 2016',
            'summary' => 'Managed client intake, Zoho CRM, customer communications, documentation, scheduling and operational workflows supporting the full client lifecycle.',
            'achievements' => [
                'Administered Zoho CRM records, customer statuses, communication histories, follow-up activity, workflow progression and lifecycle visibility.',
                'Managed onboarding, document discovery, case preparation, scheduling, customer questions and parallel workstreams with strong attention to detail.',
                'Standardized fragmented intake and documentation processes into more consistent, repeatable operational workflows.',
                'Coordinated internal handoffs and follow-up priorities so client records, documents, scheduling and next actions remained visible.',
            ],
        ],
        [
            'title' => 'Marketing Coordinator',
            'slug' => 'platypusco',
            'organization' => 'Platypusco',
            'location' => 'Missoula County, Montana',
            'date_label' => 'March 2010–October 2010',
            'summary' => 'Supported ecommerce, inventory, fulfillment, customer experience and marketing operations within the 3dcart platform.',
            'achievements' => [
                'Maintained product listings and storefront data while coordinating inventory, order fulfillment, shipping, tracking and customer-service workflows.',
                'Assisted with digital campaigns and promotional initiatives while working across marketing, ecommerce, inventory and fulfillment functions.',
                'Helped keep storefront, product, shipping and customer information aligned during day-to-day ecommerce activity.',
            ],
        ],
        [
            'title' => 'Sales & Distribution Operations',
            'slug' => 'treecycle',
            'organization' => 'Treecycle',
            'location' => 'Missoula County, Montana',
            'date_label' => 'March 2003–February 2004',
            'summary' => 'Managed customer accounts and supported daily distribution workflows spanning sales, billing, inventory control, order fulfillment and delivery.',
            'achievements' => [
                'Tracked product availability, coordinated orders and billing, supported fulfillment and delivery, and resolved customer and service issues.',
                'Maintained ongoing customer relationships supporting retention, repeat business and reliable day-to-day operations.',
                'Worked directly across sales, inventory, billing, fulfillment and delivery rather than treating each function as a separate workflow.',
            ],
        ],
    ];

    foreach ($experience as $index => &$post) {
        $post += [
            'id' => 0,
            'post_type' => 'experience',
            'column_name' => 'main',
            'status' => 'published',
            'featured' => $index === 0,
            'sort_order' => 10 + ($index * 10),
            'section_label' => $index === 0
                ? 'Professional experience'
                : '',
            'subtitle' => '',
            'body' => '',
            'body_html' => '',
            'skills' => [],
            'link_url' => '',
            'link_label' => '',
            'url' => '',
        ];
    }
    unset($post);

    $sidebar = [
        [
            'title' => 'Primary focus',
            'slug' => 'primary-focus',
            'post_type' => 'custom',
            'summary' => 'Operations, inventory, procurement systems and process improvement.',
            'skills' => [],
            'achievements' => [],
        ],
        [
            'title' => 'Core competencies',
            'slug' => 'core-competencies',
            'post_type' => 'skill_group',
            'summary' => '',
            'skills' => [
                'Process improvement',
                'Inventory operations',
                'Purchasing workflows',
                'Data quality',
                'Cross-functional coordination',
                'Reporting',
                'AI-assisted analysis',
                'Project ownership',
            ],
            'achievements' => [],
        ],
        [
            'title' => 'Tools & platforms',
            'slug' => 'tools-platforms',
            'post_type' => 'skill_group',
            'summary' => '',
            'skills' => [
                'Zoho CRM',
                'Amazon',
                '3dcart',
                'CSV / XLSX',
                'ChatGPT',
                'Claude',
                'PHP',
                'MySQL',
                'APIs',
                'Adobe',
            ],
            'achievements' => [],
        ],
        [
            'title' => 'Operational strengths',
            'slug' => 'operational-strengths',
            'post_type' => 'strengths',
            'summary' => '',
            'skills' => [],
            'achievements' => [
                'Questions inefficient processes',
                'Organizes fragmented information',
                'Builds repeatable workflows',
                'Maintains accuracy at scale',
                'Owns work through completion',
            ],
        ],
        [
            'title' => 'Education',
            'slug' => 'university-of-montana',
            'post_type' => 'education',
            'organization' => 'University of Montana',
            'date_label' => '1992–1996',
            'summary' => 'Business and Marketing coursework',
            'skills' => [],
            'achievements' => [],
        ],
    ];

    foreach ($sidebar as $index => &$post) {
        $post += [
            'id' => 0,
            'column_name' => 'sidebar',
            'status' => 'published',
            'featured' => false,
            'sort_order' => 10 + ($index * 10),
            'section_label' => '',
            'subtitle' => '',
            'organization' => '',
            'location' => '',
            'date_label' => '',
            'body' => '',
            'body_html' => '',
            'link_url' => '',
            'link_label' => '',
            'url' => '',
        ];
    }
    unset($post);

    return [
        'profile' => $profile,
        'main' => $experience,
        'sidebar' => $sidebar,
        'database_backed' => false,
    ];
}

function resume_public_payload(): array
{
    $posts = resume_public_posts();

    if (!$posts) {
        return resume_fallback_payload();
    }

    $profile = null;
    $main = [];
    $sidebar = [];

    foreach ($posts as $post) {
        if (
            $post['post_type'] === 'profile'
            && $profile === null
        ) {
            $profile = $post;
            continue;
        }

        if ($post['column_name'] === 'sidebar') {
            $sidebar[] = $post;
        } else {
            $main[] = $post;
        }
    }

    $fallback = resume_fallback_payload();

    return [
        'profile' => $profile ?: $fallback['profile'],
        'main' => $main,
        'sidebar' => $sidebar,
        'database_backed' => true,
    ];
}

function resume_knowledge_text(array $payload): string
{
    $parts = [];
    $profile = $payload['profile'] ?? null;

    if ($profile) {
        $parts[] = trim(
            (string)$profile['title']
            . '. '
            . (string)$profile['subtitle']
            . '. '
            . (string)$profile['summary']
        );
    }

    foreach ($payload['main'] ?? [] as $post) {
        $heading = trim(
            (string)$post['title']
            . (
                !empty($post['organization'])
                    ? ' at ' . $post['organization']
                    : ''
            )
        );
        $details = array_filter([
            $post['date_label'] ?? '',
            $post['location'] ?? '',
        ]);

        $parts[] = $heading
            . (
                $details
                    ? ' (' . implode(' · ', $details) . ')'
                    : ''
            )
            . '. '
            . (string)($post['summary'] ?? '')
            . (
                !empty($post['achievements'])
                    ? ' Key work: '
                        . implode(
                            ' ',
                            $post['achievements']
                        )
                    : ''
            );
    }

    foreach ($payload['sidebar'] ?? [] as $post) {
        $values = array_filter([
            $post['summary'] ?? '',
            !empty($post['skills'])
                ? implode(', ', $post['skills'])
                : '',
            !empty($post['achievements'])
                ? implode(', ', $post['achievements'])
                : '',
            $post['organization'] ?? '',
            $post['date_label'] ?? '',
        ]);

        if ($values) {
            $parts[] = (string)$post['title']
                . ': '
                . implode(' · ', $values);
        }
    }

    return trim(implode("\n\n", $parts));
}

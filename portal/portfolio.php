<?php
declare(strict_types=1);

function portfolio_schema_available(): bool
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
                    "portfolio_projects",
                    "portfolio_media"
               )'
        );
        $available = (int)$statement->fetchColumn() === 2;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function portfolio_storage_directory(): string
{
    $directory = NMM_ROOT . '/storage/portfolio-media';

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The portfolio-media storage directory could not be created.');
    }

    return $directory;
}

function portfolio_image_limit_bytes(): int
{
    $app = nmm_config('app');

    return max(
        5 * 1024 * 1024,
        (int)($app['max_portfolio_image_bytes'] ?? 12 * 1024 * 1024)
    );
}

function portfolio_split_values(?string $value): array
{
    $parts = preg_split('/[\r\n,|]+/', (string)$value) ?: [];
    $clean = [];

    foreach ($parts as $part) {
        $item = trim($part);

        if ($item !== '' && !in_array($item, $clean, true)) {
            $clean[] = $item;
        }
    }

    return $clean;
}

function portfolio_project_media(int $projectId): array
{
    if (!portfolio_schema_available() || $projectId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT *
         FROM portfolio_media
         WHERE project_id=:project_id
         ORDER BY
            CASE WHEN media_role="cover" THEN 0 ELSE 1 END,
            sort_order ASC,
            id ASC'
    );
    $statement->execute(['project_id' => $projectId]);

    return $statement->fetchAll();
}

function portfolio_media_url(int $mediaId): string
{
    return app_url('portfolio-media.php?id=' . $mediaId);
}

function portfolio_project_payload(array $project, array $media = []): array
{
    $cover = null;
    $gallery = [];

    foreach ($media as $item) {
        $payload = [
            'id' => (int)$item['id'],
            'url' => portfolio_media_url((int)$item['id']),
            'alt' => trim((string)($item['alt_text'] ?? '')),
            'caption' => trim((string)($item['caption'] ?? '')),
            'width' => (int)($item['width_px'] ?? 0),
            'height' => (int)($item['height_px'] ?? 0),
        ];

        if (($item['media_role'] ?? '') === 'cover' && $cover === null) {
            $cover = $payload;
        } else {
            $gallery[] = $payload;
        }
    }

    return [
        'id' => (int)$project['id'],
        'title' => (string)$project['title'],
        'slug' => (string)$project['slug'],
        'status' => (string)$project['status'],
        'featured' => (int)$project['featured'] === 1,
        'sort_order' => (int)$project['sort_order'],
        'project_url' => trim((string)($project['project_url'] ?? '')),
        'project_url_label' => trim((string)($project['project_url_label'] ?? 'View project')),
        'client_name' => trim((string)($project['client_name'] ?? '')),
        'project_type' => trim((string)($project['project_type'] ?? '')),
        'industry' => trim((string)($project['industry'] ?? '')),
        'year_label' => trim((string)($project['year_label'] ?? '')),
        'role_title' => trim((string)($project['role_title'] ?? '')),
        'summary' => trim((string)($project['summary'] ?? '')),
        'overview' => trim((string)($project['overview'] ?? '')),
        'challenge' => trim((string)($project['challenge'] ?? '')),
        'solution' => trim((string)($project['solution'] ?? '')),
        'results' => trim((string)($project['results'] ?? '')),
        'services' => portfolio_split_values($project['services'] ?? ''),
        'technologies' => portfolio_split_values($project['technologies'] ?? ''),
        'keywords' => portfolio_split_values($project['keywords'] ?? ''),
        'cover' => $cover,
        'gallery' => $gallery,
        'updated_at' => (string)($project['updated_at'] ?? ''),
    ];
}

function portfolio_admin_projects(): array
{
    if (!portfolio_schema_available()) {
        return [];
    }

    $rows = db()->query(
        'SELECT project.*,
                cover.id AS cover_media_id,
                cover.alt_text AS cover_alt_text
         FROM portfolio_projects project
         LEFT JOIN portfolio_media cover
           ON cover.id=(
                SELECT media.id
                FROM portfolio_media media
                WHERE media.project_id=project.id
                  AND media.media_role="cover"
                ORDER BY media.sort_order ASC,media.id ASC
                LIMIT 1
           )
         ORDER BY
            CASE project.status
                WHEN "active" THEN 0
                WHEN "draft" THEN 1
                ELSE 2
            END,
            project.featured DESC,
            project.sort_order ASC,
            project.updated_at DESC'
    )->fetchAll();

    return $rows;
}

function portfolio_admin_project(int $projectId): ?array
{
    if (!portfolio_schema_available() || $projectId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM portfolio_projects
         WHERE id=:project_id
         LIMIT 1'
    );
    $statement->execute(['project_id' => $projectId]);
    $project = $statement->fetch();

    if (!$project) {
        return null;
    }

    $project['media'] = portfolio_project_media($projectId);

    return $project;
}

function portfolio_public_projects(): array
{
    if (!portfolio_schema_available()) {
        return [];
    }

    $projects = db()->query(
        'SELECT *
         FROM portfolio_projects
         WHERE status="active"
           AND (published_at IS NULL OR published_at<=UTC_TIMESTAMP())
         ORDER BY featured DESC,sort_order ASC,updated_at DESC,id ASC'
    )->fetchAll();

    $output = [];

    foreach ($projects as $project) {
        $output[] = portfolio_project_payload(
            $project,
            portfolio_project_media((int)$project['id'])
        );
    }

    return $output;
}

function portfolio_multiple_uploads(array $files): array
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
        $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);

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

function portfolio_store_image(
    array $upload,
    int $projectId,
    string $role,
    int $userId,
    string $altText = '',
    string $caption = '',
    int $sortOrder = 0
): int {
    if (!portfolio_schema_available()) {
        throw new RuntimeException(
            'Import database/portfolio_backend_v41.sql before uploading portfolio media.'
        );
    }

    if ($projectId <= 0 || $userId <= 0) {
        throw new RuntimeException('Save the portfolio project before uploading images.');
    }

    if (!in_array($role, ['cover', 'gallery'], true)) {
        throw new RuntimeException('The portfolio image role is invalid.');
    }

    if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The portfolio image upload did not complete.');
    }

    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    $limit = portfolio_image_limit_bytes();

    if (
        $temporary === ''
        || !is_uploaded_file($temporary)
        || $size <= 0
        || $size > $limit
    ) {
        throw new RuntimeException(
            'Portfolio images must be valid uploads no larger than ' . format_bytes($limit) . '.'
        );
    }

    $imageInfo = @getimagesize($temporary);

    if (!is_array($imageInfo)) {
        throw new RuntimeException('The uploaded portfolio file is not a valid image.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Portfolio images must be JPG, PNG, WebP, or GIF files.');
    }

    $altText = substr(trim($altText), 0, 500);
    $caption = substr(trim($caption), 0, 500);

    $storedName = sprintf(
        'portfolio-%d-%s.%s',
        $projectId,
        bin2hex(random_bytes(18)),
        $extensions[$mime]
    );
    $destination = portfolio_storage_directory() . '/' . $storedName;

    if (!move_uploaded_file($temporary, $destination)) {
        throw new RuntimeException('The portfolio image could not be stored.');
    }

    chmod($destination, 0640);

    $pdo = db();

    try {
        $pdo->beginTransaction();

        if ($role === 'cover') {
            $pdo->prepare(
                'UPDATE portfolio_media
                 SET media_role="gallery",
                     sort_order=sort_order+1
                 WHERE project_id=:project_id
                   AND media_role="cover"'
            )->execute(['project_id' => $projectId]);
        }

        $statement = $pdo->prepare(
            'INSERT INTO portfolio_media
                (project_id,media_role,original_name,stored_name,mime_type,
                 size_bytes,width_px,height_px,alt_text,caption,sort_order,created_by)
             VALUES
                (:project_id,:media_role,:original_name,:stored_name,:mime_type,
                 :size_bytes,:width_px,:height_px,:alt_text,:caption,:sort_order,:created_by)'
        );
        $statement->execute([
            'project_id' => $projectId,
            'media_role' => $role,
            'original_name' => basename((string)($upload['name'] ?? 'portfolio-image')),
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'width_px' => (int)($imageInfo[0] ?? 0),
            'height_px' => (int)($imageInfo[1] ?? 0),
            'alt_text' => $altText !== '' ? $altText : null,
            'caption' => $caption !== '' ? $caption : null,
            'sort_order' => max(0, $sortOrder),
            'created_by' => $userId,
        ]);

        $mediaId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        @unlink($destination);
        throw $exception;
    }

    log_activity(
        'portfolio_media_uploaded',
        'portfolio_project',
        $projectId,
        [
            'media_id' => $mediaId,
            'role' => $role,
        ]
    );

    return $mediaId;
}

function portfolio_delete_media(int $mediaId, int $userId): void
{
    if (!portfolio_schema_available() || $mediaId <= 0 || $userId <= 0) {
        throw new RuntimeException('The portfolio image could not be removed.');
    }

    $statement = db()->prepare(
        'SELECT *
         FROM portfolio_media
         WHERE id=:media_id
         LIMIT 1'
    );
    $statement->execute(['media_id' => $mediaId]);
    $media = $statement->fetch();

    if (!$media) {
        throw new RuntimeException('The portfolio image was not found.');
    }

    db()->prepare(
        'DELETE FROM portfolio_media
         WHERE id=:media_id'
    )->execute(['media_id' => $mediaId]);

    @unlink(
        portfolio_storage_directory()
        . '/'
        . basename((string)$media['stored_name'])
    );

    log_activity(
        'portfolio_media_deleted',
        'portfolio_project',
        (int)$media['project_id'],
        [
            'media_id' => $mediaId,
            'role' => $media['media_role'],
        ]
    );
}

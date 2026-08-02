<?php
declare(strict_types=1);

/* North Mountain Media build: 20260802-music-customer-hardening-v66Q21 */

function music_customer_register_final(
    string $name,
    string $email,
    string $password,
    string $confirm
): array {
    $result = music_customer_register_v21($name, $email, $password, $confirm);
    if (
        !empty($result['created'])
        && !empty($result['verification_required'])
        && empty($result['email_sent'])
    ) {
        db()->prepare(
            'DELETE FROM users WHERE id=:user_id AND role="customer"'
        )->execute(['user_id' => (int)$result['user_id']]);
        throw new RuntimeException(
            'The verification email could not be delivered, so the account was not activated. Try again after email delivery is repaired.'
        );
    }
    return $result;
}

function music_customer_request_email_change_final(
    array $user,
    string $newEmail,
    string $currentPassword
): array {
    try {
        return music_customer_request_email_change(
            $user,
            $newEmail,
            $currentPassword
        );
    } catch (Throwable $exception) {
        if (music_customer_lifecycle_schema_available()) {
            db()->prepare(
                'UPDATE music_customer_account_state
                 SET pending_email=NULL
                 WHERE user_id=:user_id'
            )->execute(['user_id' => (int)$user['id']]);
        }
        throw $exception;
    }
}

function music_customer_public_track_page_final(
    string $query,
    int $page = 1,
    int $perPage = 25
): array {
    if (!music_library_schema_available()) {
        return [
            'tracks' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'query' => '',
        ];
    }

    $query = mb_substr(trim($query), 0, 100);
    $page = max(1, $page);
    $perPage = max(10, min(50, $perPage));
    $where = '';
    $params = [];
    if ($query !== '') {
        $where = ' AND (
            track.title LIKE :search_title
            OR track.artist_name LIKE :search_artist
            OR album.title LIKE :search_album
            OR track.genre LIKE :search_genre
        )';
        $like = '%' . $query . '%';
        $params = [
            'search_title' => $like,
            'search_artist' => $like,
            'search_album' => $like,
            'search_genre' => $like,
        ];
    }

    $baseSql = '
         FROM music_tracks track
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
          AND asset.media_kind="audio"
          AND asset.status="published"
          AND asset.is_public=1
         LEFT JOIN music_albums album ON album.id=track.album_id
         WHERE track.status="active"
           AND (track.published_at IS NULL OR track.published_at<=UTC_TIMESTAMP())'
        . $where;

    $count = db()->prepare('SELECT COUNT(*)' . $baseSql);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $statement = db()->prepare(
        'SELECT track.*,
                asset.original_name,asset.mime_type,asset.size_bytes,
                asset.cover_stored_name AS asset_cover_stored_name,
                album.title AS album_title,
                album.slug AS album_slug,
                album.artist_name AS album_artist_name,
                album.cover_stored_name AS album_cover_stored_name'
        . $baseSql
        . ' ORDER BY track.featured DESC,track.title ASC
            LIMIT :result_limit OFFSET :result_offset'
    );
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':result_limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':result_offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    return [
        'tracks' => array_map('music_track_payload', $statement->fetchAll()),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'query' => $query,
    ];
}

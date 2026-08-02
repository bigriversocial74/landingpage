<?php
declare(strict_types=1);

/* North Mountain Media build: 20260802-music-customer-security-v66Q20 */

function music_customer_public_track_exists(int $trackId): bool
{
    if (!music_library_schema_available() || $trackId <= 0) return false;

    $statement = db()->prepare(
        'SELECT track.id
         FROM music_tracks track
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
          AND asset.media_kind="audio"
          AND asset.status="published"
          AND asset.is_public=1
         WHERE track.id=:track_id
           AND track.status="active"
           AND (
                track.published_at IS NULL
                OR track.published_at<=UTC_TIMESTAMP()
           )
         LIMIT 1'
    );
    $statement->execute(['track_id' => $trackId]);
    return (bool)$statement->fetchColumn();
}

function music_customer_visible_playlist(int $playlistId, int $userId): ?array
{
    if (!music_customer_accounts_ready() || $playlistId <= 0 || $userId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM music_customer_playlists
         WHERE id=:playlist_id
           AND customer_user_id=:user_id
           AND status="active"
         LIMIT 1'
    );
    $statement->execute([
        'playlist_id' => $playlistId,
        'user_id' => $userId,
    ]);
    $playlist = $statement->fetch();
    if (!$playlist) return null;

    $tracks = db()->prepare(
        'SELECT item.position,item.added_at,track.*,
                album.title AS album_title
         FROM music_customer_playlist_tracks item
         JOIN music_tracks track
           ON track.id=item.track_id
          AND track.status="active"
          AND (
               track.published_at IS NULL
               OR track.published_at<=UTC_TIMESTAMP()
          )
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
          AND asset.media_kind="audio"
          AND asset.status="published"
          AND asset.is_public=1
         LEFT JOIN music_albums album ON album.id=track.album_id
         WHERE item.playlist_id=:playlist_id
         ORDER BY item.position ASC,item.added_at ASC,item.track_id ASC'
    );
    $tracks->execute(['playlist_id' => $playlistId]);
    $playlist['tracks'] = $tracks->fetchAll();
    return $playlist;
}

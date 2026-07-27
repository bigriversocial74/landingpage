<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('music_library');
require_once __DIR__ . '/portal/knowledge-assets.php';
require_once __DIR__ . '/portal/music-library.php';

$type = strtolower(
    trim((string)($_GET['type'] ?? 'track'))
);
$id = query_int('id');

if (
    !music_library_schema_available()
    || $id <= 0
    || !in_array(
        $type,
        ['track', 'album', 'playlist'],
        true
    )
) {
    http_response_code(404);
    exit;
}

$viewer = current_user();
$isAdmin = $viewer && $viewer['role'] === 'admin';
$record = null;
$storedName = '';
$mime = '';
$storage = '';

if ($type === 'track') {
    $sql = 'SELECT track.title,
                   track.status,
                   track.published_at,
                   asset.cover_stored_name,
                   asset.cover_mime_type,
                   album.cover_stored_name AS album_cover_stored_name,
                   album.cover_mime_type AS album_cover_mime_type,
                   album.status AS album_status
            FROM music_tracks track
            JOIN knowledge_assets asset
              ON asset.id=track.knowledge_asset_id
            LEFT JOIN music_albums album
              ON album.id=track.album_id
            WHERE track.id=:id';

    if (!$isAdmin) {
        $sql .= ' AND track.status="active"
                  AND asset.status="published"
                  AND asset.is_public=1
                  AND (
                        track.published_at IS NULL
                        OR track.published_at<=UTC_TIMESTAMP()
                  )';
    }

    $sql .= ' LIMIT 1';
    $statement = db()->prepare($sql);
    $statement->execute(['id' => $id]);
    $record = $statement->fetch();

    if ($record) {
        if (!empty($record['cover_stored_name'])) {
            $storedName = (string)$record['cover_stored_name'];
            $mime = (string)$record['cover_mime_type'];
            $storage = 'knowledge';
        } elseif (
            !empty($record['album_cover_stored_name'])
            && (
                $isAdmin
                || $record['album_status'] === 'active'
            )
        ) {
            $storedName = (string)$record['album_cover_stored_name'];
            $mime = (string)$record['album_cover_mime_type'];
            $storage = 'music';
        }
    }
} elseif ($type === 'album') {
    $sql = 'SELECT album.*
            FROM music_albums album
            WHERE album.id=:id';

    if (!$isAdmin) {
        $sql .= ' AND album.status="active"
                  AND (
                        album.published_at IS NULL
                        OR album.published_at<=UTC_TIMESTAMP()
                  )';
    }

    $sql .= ' LIMIT 1';
    $statement = db()->prepare($sql);
    $statement->execute(['id' => $id]);
    $record = $statement->fetch();

    if ($record && !empty($record['cover_stored_name'])) {
        $storedName = (string)$record['cover_stored_name'];
        $mime = (string)$record['cover_mime_type'];
        $storage = 'music';
    } elseif ($record) {
        $cover = db()->prepare(
            'SELECT asset.cover_stored_name,
                    asset.cover_mime_type
             FROM music_tracks track
             JOIN knowledge_assets asset
               ON asset.id=track.knowledge_asset_id
             WHERE track.album_id=:album_id
               AND track.status="active"
               AND asset.status="published"
               AND asset.is_public=1
               AND asset.cover_stored_name IS NOT NULL
             ORDER BY
                track.disc_number,
                COALESCE(track.track_number,9999),
                track.sort_order,
                track.id
             LIMIT 1'
        );
        $cover->execute(['album_id' => $id]);
        $fallback = $cover->fetch();

        if ($fallback) {
            $storedName = (string)$fallback['cover_stored_name'];
            $mime = (string)$fallback['cover_mime_type'];
            $storage = 'knowledge';
        }
    }
} else {
    $sql = 'SELECT playlist.*
            FROM music_playlists playlist
            WHERE playlist.id=:id';

    if (!$isAdmin) {
        $sql .= ' AND playlist.status="active"
                  AND (
                        playlist.published_at IS NULL
                        OR playlist.published_at<=UTC_TIMESTAMP()
                  )';
    }

    $sql .= ' LIMIT 1';
    $statement = db()->prepare($sql);
    $statement->execute(['id' => $id]);
    $record = $statement->fetch();

    if ($record && !empty($record['cover_stored_name'])) {
        $storedName = (string)$record['cover_stored_name'];
        $mime = (string)$record['cover_mime_type'];
        $storage = 'music';
    } elseif ($record) {
        $cover = db()->prepare(
            'SELECT asset.cover_stored_name,
                    asset.cover_mime_type,
                    album.cover_stored_name AS album_cover_stored_name,
                    album.cover_mime_type AS album_cover_mime_type
             FROM music_playlist_tracks item
             JOIN music_tracks track
               ON track.id=item.track_id
             JOIN knowledge_assets asset
               ON asset.id=track.knowledge_asset_id
             LEFT JOIN music_albums album
               ON album.id=track.album_id
             WHERE item.playlist_id=:playlist_id
               AND track.status="active"
               AND asset.status="published"
               AND asset.is_public=1
             ORDER BY item.position,item.track_id
             LIMIT 1'
        );
        $cover->execute(['playlist_id' => $id]);
        $fallback = $cover->fetch();

        if ($fallback) {
            if (!empty($fallback['cover_stored_name'])) {
                $storedName = (string)$fallback['cover_stored_name'];
                $mime = (string)$fallback['cover_mime_type'];
                $storage = 'knowledge';
            } elseif (!empty($fallback['album_cover_stored_name'])) {
                $storedName = (string)$fallback['album_cover_stored_name'];
                $mime = (string)$fallback['album_cover_mime_type'];
                $storage = 'music';
            }
        }
    }
}

if (!$record) {
    http_response_code(404);
    exit;
}

if ($storedName === '') {
    $label = strtoupper(
        substr(
            trim(
                (string)(
                    $record['title']
                    ?? 'Music'
                )
            ),
            0,
            2
        )
    );
    $label = preg_replace(
        '/[^A-Z0-9]/',
        '',
        $label
    ) ?: '♪';

    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop stop-color="#101b27"/><stop offset=".55" stop-color="#27505a"/>'
        . '<stop offset="1" stop-color="#0a8c91"/></linearGradient></defs>'
        . '<rect width="800" height="800" fill="url(#g)"/>'
        . '<circle cx="620" cy="170" r="170" fill="#7ee0d8" opacity=".15"/>'
        . '<circle cx="170" cy="650" r="210" fill="#7a82ff" opacity=".12"/>'
        . '<text x="400" y="455" text-anchor="middle" fill="#fff" '
        . 'font-family="Arial,sans-serif" font-size="180" font-weight="700">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</text></svg>';
    exit;
}

$path = $storage === 'knowledge'
    ? knowledge_storage_path($storedName)
    : music_cover_storage_directory()
        . '/'
        . basename($storedName);

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    exit;
}

$modified=filemtime($path)?:time();
$etag='"'.hash(
    'sha256',
    basename($storedName)
    .'|'.$modified
    .'|'.$size
).'"';

header('Content-Type: ' . ($mime ?: 'image/jpeg'));
header('Content-Length: ' . $size);
header(
    'Cache-Control: '
    .($isAdmin
        ?'private, no-store'
        :'public, max-age=86400, must-revalidate')
);
header(
    'Last-Modified: '
    .gmdate('D, d M Y H:i:s',$modified)
    .' GMT'
);
header('ETag: '.$etag);
header('X-Content-Type-Options: nosniff');

if(
    trim(
        (string)(
            $_SERVER['HTTP_IF_NONE_MATCH']??''
        )
    )===$etag
){
    http_response_code(304);
    exit;
}

readfile($path);
exit;

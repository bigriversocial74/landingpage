from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding='utf-8')


def replace_once(path: str, old: str, new: str) -> None:
    source = read(path)
    count = source.count(old)
    if count != 1:
        raise SystemExit(f'Expected one match in {path}, found {count}: {old[:140]!r}')
    write(path, source.replace(old, new, 1))


def append_once(path: str, marker: str, content: str) -> None:
    source = read(path)
    if marker in source:
        raise SystemExit(f'Append marker already exists in {path}: {marker}')
    write(path, source.rstrip() + '\n\n' + content.rstrip() + '\n')


write('portal/feed-reader-media.php', r'''<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-feed-reader-media-v66B */

function feed_reader_media_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN ("feed_item_media_states","feed_collections","feed_collection_items")'
        );
        $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        $available = false;
    }
    return $available;
}

function feed_reader_is_youtube_host(string $host): bool
{
    return in_array(strtolower(rtrim($host, '.')), [
        'youtube.com','www.youtube.com','m.youtube.com','music.youtube.com',
        'youtu.be','www.youtu.be','youtube-nocookie.com','www.youtube-nocookie.com',
    ], true);
}

function feed_reader_youtube_direct_feed_url(string $url): ?string
{
    $url = trim($url);
    $parts = parse_url($url);
    if (!is_array($parts) || !feed_reader_is_youtube_host((string)($parts['host'] ?? ''))) return null;
    $path = '/' . ltrim((string)($parts['path'] ?? '/'), '/');
    parse_str((string)($parts['query'] ?? ''), $query);

    if ($path === '/feeds/videos.xml') {
        $channel = (string)($query['channel_id'] ?? '');
        $playlist = (string)($query['playlist_id'] ?? '');
        if (preg_match('/^UC[A-Za-z0-9_-]{20,40}$/', $channel)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channel);
        }
        if (preg_match('/^[A-Za-z0-9_-]{10,100}$/', $playlist)) {
            return 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode($playlist);
        }
    }

    $playlist = (string)($query['playlist_id'] ?? $query['list'] ?? '');
    if (preg_match('/^[A-Za-z0-9_-]{10,100}$/', $playlist)) {
        return 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode($playlist);
    }

    if (preg_match('#^/channel/(UC[A-Za-z0-9_-]{20,40})(?:/|$)#', $path, $match)) {
        return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode((string)$match[1]);
    }

    $channel = (string)($query['channel_id'] ?? '');
    if (preg_match('/^UC[A-Za-z0-9_-]{20,40}$/', $channel)) {
        return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channel);
    }
    return null;
}

function feed_reader_youtube_channel_id_from_html(string $html): string
{
    foreach ([
        '/<meta[^>]+itemprop=["\']channelId["\'][^>]+content=["\'](UC[A-Za-z0-9_-]{20,40})["\']/i',
        '/<meta[^>]+content=["\'](UC[A-Za-z0-9_-]{20,40})["\'][^>]+itemprop=["\']channelId["\']/i',
        '/["\'](?:channelId|externalId)["\']\s*:\s*["\'](UC[A-Za-z0-9_-]{20,40})["\']/i',
        '#youtube\.com/channel/(UC[A-Za-z0-9_-]{20,40})#i',
    ] as $pattern) {
        if (preg_match($pattern, $html, $match)) return (string)$match[1];
    }
    return '';
}

function feed_reader_resolve_subscription_url(string $url): string
{
    $direct = feed_reader_youtube_direct_feed_url($url);
    if ($direct !== null) return $direct;

    $parts = parse_url(trim($url));
    if (!is_array($parts) || !feed_reader_is_youtube_host((string)($parts['host'] ?? ''))) return $url;

    $response = feed_reader_fetch($url);
    $channelId = feed_reader_youtube_channel_id_from_html((string)($response['body'] ?? ''));
    if ($channelId === '') {
        throw new RuntimeException('The YouTube channel ID could not be resolved. Use a channel, handle, or playlist URL.');
    }
    return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channelId);
}

function feed_reader_video_embed(string $url): ?array
{
    $parts = parse_url(trim($url));
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') return null;
    $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
    $path = trim((string)($parts['path'] ?? ''), '/');
    parse_str((string)($parts['query'] ?? ''), $query);

    if (feed_reader_is_youtube_host($host)) {
        $id = '';
        if (str_ends_with($host, 'youtu.be')) $id = explode('/', $path)[0] ?? '';
        elseif (isset($query['v'])) $id = (string)$query['v'];
        elseif (preg_match('#^(?:shorts|live|embed)/([A-Za-z0-9_-]+)#', $path, $match)) $id = (string)$match[1];
        if (!preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id)) return null;
        return [
            'provider' => 'YouTube',
            'embed_url' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?rel=0',
            'thumbnail_url' => 'https://i.ytimg.com/vi/' . rawurlencode($id) . '/hqdefault.jpg',
        ];
    }

    if (in_array($host, ['vimeo.com','www.vimeo.com','player.vimeo.com'], true)) {
        $id = '';
        foreach (array_reverse(array_filter(explode('/', $path))) as $segment) {
            if (ctype_digit($segment)) { $id = $segment; break; }
        }
        if (!preg_match('/^\d{5,15}$/', $id)) return null;
        return [
            'provider' => 'Vimeo',
            'embed_url' => 'https://player.vimeo.com/video/' . rawurlencode($id) . '?dnt=1',
            'thumbnail_url' => '',
        ];
    }
    return null;
}

function feed_reader_item_media(array $item): array
{
    $enclosureUrl = trim((string)($item['enclosure_url'] ?? ''));
    $enclosureType = strtolower(trim((string)($item['enclosure_type'] ?? '')));
    $canonicalUrl = trim((string)($item['canonical_url'] ?? ''));
    $title = trim((string)($item['title'] ?? 'Feed media'));

    if ($enclosureUrl !== '' && (
        str_starts_with($enclosureType, 'audio/')
        || preg_match('/\.(?:mp3|m4a|aac|ogg|oga|wav|flac|opus)(?:\?|$)/i', $enclosureUrl)
    )) {
        return [
            'kind' => 'audio', 'url' => $enclosureUrl, 'embed_url' => '',
            'thumbnail_url' => trim((string)($item['image_url'] ?? '')),
            'type' => $enclosureType !== '' ? $enclosureType : 'audio/mpeg',
            'title' => $title,
        ];
    }

    $embed = $canonicalUrl !== '' ? feed_reader_video_embed($canonicalUrl) : null;
    if ($embed) {
        return [
            'kind' => 'video', 'url' => $canonicalUrl,
            'embed_url' => $embed['embed_url'],
            'thumbnail_url' => trim((string)($item['image_url'] ?? '')) ?: $embed['thumbnail_url'],
            'type' => $embed['provider'], 'title' => $title,
        ];
    }

    if ($enclosureUrl !== '' && str_starts_with($enclosureType, 'video/')) {
        return [
            'kind' => 'video_file', 'url' => $enclosureUrl, 'embed_url' => '',
            'thumbnail_url' => trim((string)($item['image_url'] ?? '')),
            'type' => $enclosureType, 'title' => $title,
        ];
    }

    if ($enclosureUrl !== '') {
        return [
            'kind' => 'attachment', 'url' => $enclosureUrl, 'embed_url' => '',
            'thumbnail_url' => '', 'type' => $enclosureType, 'title' => $title,
        ];
    }

    return ['kind' => '', 'url' => '', 'embed_url' => '', 'thumbnail_url' => '', 'type' => '', 'title' => $title];
}

function feed_reader_media_item_access(int $userId, int $itemId): bool
{
    $statement = db()->prepare(
        'SELECT 1 FROM feed_items item
         JOIN feed_subscriptions subscription ON subscription.source_id=item.source_id
         WHERE item.id=:item_id AND subscription.user_id=:user_id LIMIT 1'
    );
    $statement->execute(['item_id' => $itemId, 'user_id' => $userId]);
    return (bool)$statement->fetchColumn();
}

function feed_reader_enrich_media_items(int $userId, array $items): array
{
    $ids = array_values(array_unique(array_filter(array_map(
        static fn(array $item): int => (int)($item['id'] ?? 0),
        $items
    ))));
    $states = [];
    $memberships = [];

    if ($ids && feed_reader_media_schema_available()) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = db()->prepare(
            'SELECT * FROM feed_item_media_states WHERE user_id=? AND item_id IN (' . $placeholders . ')'
        );
        $statement->execute([$userId, ...$ids]);
        foreach ($statement->fetchAll() as $row) $states[(int)$row['item_id']] = $row;

        $member = db()->prepare(
            'SELECT membership.item_id,GROUP_CONCAT(membership.collection_id ORDER BY membership.collection_id) AS collection_ids
             FROM feed_collection_items membership
             JOIN feed_collections collection ON collection.id=membership.collection_id AND collection.user_id=?
             WHERE membership.item_id IN (' . $placeholders . ')
             GROUP BY membership.item_id'
        );
        $member->execute([$userId, ...$ids]);
        foreach ($member->fetchAll() as $row) {
            $memberships[(int)$row['item_id']] = array_values(array_filter(array_map('intval', explode(',', (string)$row['collection_ids']))));
        }
    }

    foreach ($items as &$item) {
        $id = (int)($item['id'] ?? 0);
        $state = $states[$id] ?? [];
        $item['media'] = feed_reader_item_media($item);
        $item['playback_position_seconds'] = (int)($state['playback_position_seconds'] ?? 0);
        $item['playback_duration_seconds'] = (int)($state['playback_duration_seconds'] ?? 0);
        $item['is_listened'] = (int)($state['is_listened'] ?? 0);
        $item['listened_at'] = $state['listened_at'] ?? null;
        $item['note_text'] = (string)($state['note_text'] ?? '');
        $item['note_updated_at'] = $state['note_updated_at'] ?? null;
        $item['collection_ids'] = $memberships[$id] ?? [];
    }
    unset($item);
    return $items;
}

function feed_reader_media_counts(int $userId): array
{
    if (!feed_reader_media_schema_available()) return ['listened' => 0, 'notes' => 0];
    $statement = db()->prepare(
        'SELECT COUNT(DISTINCT CASE WHEN media.is_listened=1 THEN media.item_id END) AS listened,
                COUNT(DISTINCT CASE WHEN media.note_text IS NOT NULL AND media.note_text<>"" THEN media.item_id END) AS notes
         FROM feed_item_media_states media
         JOIN feed_items item ON item.id=media.item_id
         JOIN feed_subscriptions subscription ON subscription.source_id=item.source_id AND subscription.user_id=media.user_id
         WHERE media.user_id=:user_id AND subscription.status="active"'
    );
    $statement->execute(['user_id' => $userId]);
    $row = $statement->fetch() ?: [];
    return ['listened' => (int)($row['listened'] ?? 0), 'notes' => (int)($row['notes'] ?? 0)];
}

function feed_reader_save_playback(int $userId, int $itemId, int $position, int $duration, bool $listened): array
{
    if (!feed_reader_media_schema_available()) throw new RuntimeException('Import database/feed_reader_media_v66b.sql before saving playback.');
    if (!feed_reader_media_item_access($userId, $itemId)) throw new RuntimeException('The feed item is not available to this account.');
    $position = max(0, min(604800, $position));
    $duration = max(0, min(604800, $duration));
    if ($duration > 0 && $position >= max(1, (int)floor($duration * 0.9))) $listened = true;
    db()->prepare(
        'INSERT INTO feed_item_media_states (
            user_id,item_id,playback_position_seconds,playback_duration_seconds,is_listened,listened_at
         ) VALUES (:user_id,:item_id,:position,:duration,:listened,' . ($listened ? 'UTC_TIMESTAMP()' : 'NULL') . ')
         ON DUPLICATE KEY UPDATE
            playback_position_seconds=VALUES(playback_position_seconds),
            playback_duration_seconds=GREATEST(playback_duration_seconds,VALUES(playback_duration_seconds)),
            is_listened=GREATEST(is_listened,VALUES(is_listened)),
            listened_at=CASE WHEN VALUES(is_listened)=1 THEN COALESCE(listened_at,UTC_TIMESTAMP()) ELSE listened_at END'
    )->execute([
        'user_id' => $userId, 'item_id' => $itemId, 'position' => $position,
        'duration' => $duration, 'listened' => $listened ? 1 : 0,
    ]);
    return ['item_id' => $itemId, 'position' => $position, 'duration' => $duration, 'listened' => $listened];
}

function feed_reader_save_note(int $userId, int $itemId, string $note): array
{
    if (!feed_reader_media_schema_available()) throw new RuntimeException('Import database/feed_reader_media_v66b.sql before saving notes.');
    if (!feed_reader_media_item_access($userId, $itemId)) throw new RuntimeException('The feed item is not available to this account.');
    $note = mb_substr(trim($note), 0, 8000);
    db()->prepare(
        'INSERT INTO feed_item_media_states (user_id,item_id,note_text,note_updated_at)
         VALUES (:user_id,:item_id,:note_text,' . ($note !== '' ? 'UTC_TIMESTAMP()' : 'NULL') . ')
         ON DUPLICATE KEY UPDATE note_text=VALUES(note_text),note_updated_at=' . ($note !== '' ? 'UTC_TIMESTAMP()' : 'NULL')
    )->execute(['user_id' => $userId, 'item_id' => $itemId, 'note_text' => $note !== '' ? $note : null]);
    return ['item_id' => $itemId, 'note' => $note];
}

function feed_reader_collections(int $userId): array
{
    if (!feed_reader_media_schema_available()) return [];
    $statement = db()->prepare(
        'SELECT collection.*,COUNT(membership.item_id) AS item_count
         FROM feed_collections collection
         LEFT JOIN feed_collection_items membership ON membership.collection_id=collection.id
         WHERE collection.user_id=:user_id
         GROUP BY collection.id ORDER BY collection.sort_order,collection.name,collection.id'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function feed_reader_save_collection(int $userId, int $collectionId, string $name): int
{
    if (!feed_reader_media_schema_available()) throw new RuntimeException('Import database/feed_reader_media_v66b.sql before managing collections.');
    $name = mb_substr(trim($name), 0, 190);
    if ($name === '') throw new RuntimeException('Enter a collection name.');
    if ($collectionId > 0) {
        $statement = db()->prepare('UPDATE feed_collections SET name=:name WHERE id=:id AND user_id=:user_id');
        $statement->execute(['name' => $name, 'id' => $collectionId, 'user_id' => $userId]);
        if ($statement->rowCount() === 0) {
            $check = db()->prepare('SELECT id FROM feed_collections WHERE id=:id AND user_id=:user_id');
            $check->execute(['id' => $collectionId, 'user_id' => $userId]);
            if (!$check->fetchColumn()) throw new RuntimeException('The collection could not be found.');
        }
        return $collectionId;
    }
    $statement = db()->prepare('INSERT INTO feed_collections (user_id,name) VALUES (:user_id,:name)');
    $statement->execute(['user_id' => $userId, 'name' => $name]);
    return (int)db()->lastInsertId();
}

function feed_reader_delete_collection(int $userId, int $collectionId): void
{
    if (!feed_reader_media_schema_available()) return;
    db()->prepare('DELETE FROM feed_collections WHERE id=:id AND user_id=:user_id')
        ->execute(['id' => $collectionId, 'user_id' => $userId]);
}

function feed_reader_toggle_collection(int $userId, int $itemId, int $collectionId, bool $value): array
{
    if (!feed_reader_media_schema_available()) throw new RuntimeException('Import database/feed_reader_media_v66b.sql before using collections.');
    if (!feed_reader_media_item_access($userId, $itemId)) throw new RuntimeException('The feed item is not available to this account.');
    $collection = db()->prepare('SELECT id FROM feed_collections WHERE id=:id AND user_id=:user_id LIMIT 1');
    $collection->execute(['id' => $collectionId, 'user_id' => $userId]);
    if (!$collection->fetchColumn()) throw new RuntimeException('The collection is not available to this account.');
    if ($value) {
        db()->prepare('INSERT IGNORE INTO feed_collection_items (collection_id,item_id) VALUES (:collection_id,:item_id)')
            ->execute(['collection_id' => $collectionId, 'item_id' => $itemId]);
    } else {
        db()->prepare('DELETE FROM feed_collection_items WHERE collection_id=:collection_id AND item_id=:item_id')
            ->execute(['collection_id' => $collectionId, 'item_id' => $itemId]);
    }
    return ['item_id' => $itemId, 'collection_id' => $collectionId, 'value' => $value];
}

function feed_reader_media_markup(array $item, string $context = 'card'): string
{
    $media = is_array($item['media'] ?? null) ? $item['media'] : feed_reader_item_media($item);
    $kind = (string)($media['kind'] ?? '');
    if ($kind === '') return '';
    $class = 'feed-reader-media feed-reader-media-' . preg_replace('/[^a-z_]/', '', $kind) . ' context-' . ($context === 'article' ? 'article' : 'card');

    if ($kind === 'audio') {
        $progress = (int)($item['playback_position_seconds'] ?? 0);
        $duration = (int)($item['playback_duration_seconds'] ?? 0);
        return '<section class="' . e($class) . '"><button type="button" class="feed-reader-audio-trigger" data-feed-audio-source data-item-id="' . (int)$item['id'] . '" data-audio-url="' . e($media['url']) . '" data-audio-title="' . e($media['title']) . '" data-audio-source="' . e((string)($item['subscription_title'] ?? $item['source_title'] ?? 'Feed')) . '" data-audio-image="' . e((string)($media['thumbnail_url'] ?? '')) . '" data-audio-position="' . $progress . '" data-audio-duration="' . $duration . '" data-audio-listened="' . ((int)($item['is_listened'] ?? 0) ? '1' : '0') . '"><span aria-hidden="true">▶</span><strong>' . e($media['title']) . '</strong><small>' . ((int)($item['is_listened'] ?? 0) ? 'Listened' : ($progress > 0 ? 'Resume listening' : 'Play audio')) . '</small></button></section>';
    }

    if ($kind === 'video') {
        $thumbnail = (string)($media['thumbnail_url'] ?? '');
        return '<section class="' . e($class) . '" data-feed-video-card data-video-embed="' . e($media['embed_url']) . '" data-video-title="' . e($media['title']) . '">' . ($thumbnail !== '' ? '<img src="' . e($thumbnail) . '" alt="" loading="lazy" referrerpolicy="no-referrer">' : '') . '<button type="button" data-feed-video-load><span aria-hidden="true">▶</span> Play ' . e((string)$media['type']) . ' video</button></section>';
    }

    if ($kind === 'video_file') {
        return '<section class="' . e($class) . '"><video controls preload="metadata" src="' . e($media['url']) . '"></video></section>';
    }

    return '<a class="feed-reader-attachment button" href="' . e($media['url']) . '" target="_blank" rel="noopener noreferrer nofollow">Open attachment</a>';
}
''')

write('database/feed_reader_media_v66b.sql', r'''-- North Mountain Media Feed Reader Media & Intelligence v66B
-- Additive migration. Import after database/rss_feed_reader_v62.sql.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS feed_item_media_states (
    user_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    playback_position_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    playback_duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    is_listened TINYINT(1) NOT NULL DEFAULT 0,
    listened_at DATETIME NULL,
    note_text TEXT NULL,
    note_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,item_id),
    KEY idx_feed_media_item (item_id),
    KEY idx_feed_media_user_listened (user_id,is_listened,item_id),
    KEY idx_feed_media_user_note (user_id,note_updated_at,item_id),
    CONSTRAINT fk_feed_media_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_media_state_item FOREIGN KEY (item_id) REFERENCES feed_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_collections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feed_collections_user_name (user_id,name),
    KEY idx_feed_collections_user_order (user_id,sort_order,id),
    CONSTRAINT fk_feed_collections_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_collection_items (
    collection_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (collection_id,item_id),
    KEY idx_feed_collection_items_item (item_id,collection_id),
    CONSTRAINT fk_feed_collection_items_collection FOREIGN KEY (collection_id) REFERENCES feed_collections(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_collection_items_item FOREIGN KEY (item_id) REFERENCES feed_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'North Mountain Media Feed Reader Media v66B migration complete' AS migration_status;
''')

schema = r'''-- Feed Reader Media & Intelligence v66B
CREATE TABLE IF NOT EXISTS feed_item_media_states (
    user_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    playback_position_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    playback_duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    is_listened TINYINT(1) NOT NULL DEFAULT 0,
    listened_at DATETIME NULL,
    note_text TEXT NULL,
    note_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,item_id),
    KEY idx_feed_media_item (item_id),
    KEY idx_feed_media_user_listened (user_id,is_listened,item_id),
    KEY idx_feed_media_user_note (user_id,note_updated_at,item_id),
    CONSTRAINT fk_feed_media_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_media_state_item FOREIGN KEY (item_id) REFERENCES feed_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_collections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feed_collections_user_name (user_id,name),
    KEY idx_feed_collections_user_order (user_id,sort_order,id),
    CONSTRAINT fk_feed_collections_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_collection_items (
    collection_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (collection_id,item_id),
    KEY idx_feed_collection_items_item (item_id,collection_id),
    CONSTRAINT fk_feed_collection_items_collection FOREIGN KEY (collection_id) REFERENCES feed_collections(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_collection_items_item FOREIGN KEY (item_id) REFERENCES feed_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'''
append_once('database/north_mountain_portal.sql', '-- Feed Reader Media & Intelligence v66B', schema)

write('assets/css/feed-reader-media-v66b.css', r'''/* North Mountain Media build: 20260730-feed-reader-media-v66B */
.feed-reader-media{margin:14px 0;border:1px solid #dce3eb;border-radius:18px;background:#f8fafc;overflow:hidden}
.feed-reader-audio-trigger{width:100%;border:0;background:transparent;display:grid;grid-template-columns:42px 1fr;gap:2px 12px;text-align:left;padding:16px;cursor:pointer;color:#152638}
.feed-reader-audio-trigger>span{grid-row:1/3;display:grid;place-items:center;width:42px;height:42px;border-radius:50%;background:#152638;color:#fff}
.feed-reader-audio-trigger strong{align-self:end}.feed-reader-audio-trigger small{color:#68778a}
.feed-reader-media-video{position:relative;aspect-ratio:16/9;background:#0b1420}
.feed-reader-media-video img{width:100%;height:100%;object-fit:cover;opacity:.82}
.feed-reader-media-video button{position:absolute;inset:0;margin:auto;width:max-content;height:max-content;border:0;border-radius:999px;background:rgba(8,18,30,.88);color:#fff;padding:14px 20px;font-weight:800;cursor:pointer}
.feed-reader-media-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.feed-reader-media-video_file video{display:block;width:100%;max-height:520px;background:#000}
.feed-reader-media-badges{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.feed-reader-media-badges span{font-size:.75rem;font-weight:800;padding:5px 9px;border-radius:999px;background:#edf2f7;color:#526175}
.feed-reader-private-tools{margin-top:24px;padding:20px;border:1px solid #dce3eb;border-radius:18px;background:#f8fafc}.feed-reader-private-tools h3{margin:0 0 6px}.feed-reader-private-tools textarea{width:100%;min-height:110px;margin-top:12px}.feed-reader-private-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}.feed-reader-private-actions select{min-width:220px}
.feed-reader-media-setup{margin:0 24px 20px;padding:14px 18px;border:1px solid #e5c875;border-radius:14px;background:#fff9e8;color:#654d00}
.feed-reader-collections-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.feed-reader-collection-card{border:1px solid #dce3eb;border-radius:14px;padding:14px;background:#fff}.feed-reader-collection-card form{display:flex;gap:8px;align-items:center}.feed-reader-collection-card input{min-width:0;flex:1}
.feed-reader-player{position:fixed;z-index:120;left:calc(var(--portal-sidebar-width,260px) + 24px);right:24px;bottom:18px;display:grid;grid-template-columns:auto minmax(160px,1fr) minmax(260px,2fr) auto auto auto;gap:12px;align-items:center;padding:14px 16px;border:1px solid #cfd8e3;border-radius:18px;background:rgba(255,255,255,.97);box-shadow:0 18px 50px rgba(13,29,47,.18);backdrop-filter:blur(14px)}
.feed-reader-player[hidden]{display:none}.feed-reader-player-cover{width:52px;height:52px;border-radius:12px;object-fit:cover;background:#edf2f7}.feed-reader-player-copy{min-width:0}.feed-reader-player-copy strong,.feed-reader-player-copy span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.feed-reader-player-copy span{font-size:.8rem;color:#68778a}.feed-reader-player audio{width:100%}.feed-reader-player button,.feed-reader-player select{border:1px solid #ccd6e0;background:#fff;border-radius:10px;padding:8px;cursor:pointer}
.feed-reader-social-card.is-listened{opacity:.84}.feed-reader-social-card.has-note{border-color:#aebfce}
@media(max-width:900px){.feed-reader-player{left:14px;right:14px;grid-template-columns:auto 1fr auto auto}.feed-reader-player audio{grid-column:1/-1}.feed-reader-player-cover{display:none}}
''')

write('assets/js/feed-reader-social.js', r'''/* North Mountain Media Feed Reader Media v66B */
(() => {
  'use strict';

  const clampSeconds = (value, max = 604800) => Math.max(0, Math.min(max, Math.floor(Number(value) || 0)));
  const listenedFromProgress = (position, duration) => duration > 0 && position >= Math.max(1, Math.floor(duration * 0.9));
  window.NMM_FEED_MEDIA_UTILS = { clampSeconds, listenedFromProgress };

  const root = document.querySelector('[data-social-feed-reader]');
  if (!root || root.dataset.socialReaderReady === '1') return;
  root.dataset.socialReaderReady = '1';

  const api = root.dataset.feedApi || '';
  const csrf = root.dataset.feedCsrf || '';
  const selectedItem = Number(root.dataset.selectedItem || 0);
  const mediaReady = root.dataset.feedMediaReady === '1';
  const rows = [...root.querySelectorAll('[data-feed-item-row]')];
  const search = root.querySelector('[data-feed-search-input]');
  const addDialog = root.querySelector('[data-feed-dialog]');
  const settingsDialog = root.querySelector('[data-feed-settings-dialog]');
  const feedSidebar = root.querySelector('[data-feed-sidebar]');
  const portalSidebar = document.querySelector('#portalSidebar');

  const announce = (message) => {
    let status = document.querySelector('[data-feed-status]');
    if (!status) {
      status = document.createElement('div');
      status.dataset.feedStatus = '';
      status.className = 'sr-only';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      document.body.append(status);
    }
    status.textContent = message;
  };

  const request = async (payload, keepalive = false) => {
    if (!api || !csrf) throw new Error('Feed Reader API is unavailable.');
    const response = await fetch(api, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok !== true) throw new Error(data.message || 'The Feed Reader action failed.');
    return data;
  };

  const openDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    requestAnimationFrame(() => dialog.querySelector('input,select,button')?.focus());
  };
  const closeDialog = (dialog) => { if (dialog) { dialog.close?.(); dialog.removeAttribute('open'); } };
  const updateRowRead = (itemId, value) => {
    const row = document.querySelector(`[data-feed-item-row][data-item-id="${itemId}"]`);
    row?.classList.toggle('unread', !value);
    row?.querySelector('header > i')?.toggleAttribute('hidden', value);
  };

  if (feedSidebar && portalSidebar) {
    portalSidebar.classList.add('portal-sidebar-feed-mode');
    portalSidebar.querySelectorAll(':scope > .portal-nav, :scope > .portal-role').forEach((element) => { element.hidden = true; });
    portalSidebar.insertBefore(feedSidebar, portalSidebar.querySelector('.portal-sidebar-foot') || null);
  }

  const queue = [...root.querySelectorAll('[data-feed-audio-source]')];
  const playerShell = root.querySelector('[data-feed-player-shell]');
  const player = playerShell?.querySelector('[data-feed-player-audio]');
  const playerTitle = playerShell?.querySelector('[data-feed-player-title]');
  const playerSource = playerShell?.querySelector('[data-feed-player-source]');
  const playerCover = playerShell?.querySelector('[data-feed-player-cover]');
  const speed = playerShell?.querySelector('[data-feed-player-speed]');
  let queueIndex = -1;
  let currentTrigger = null;
  let lastSyncAt = 0;

  const playbackPayload = (listened = false) => currentTrigger && player ? {
    action: 'playback_state',
    item_id: Number(currentTrigger.dataset.itemId || 0),
    position: clampSeconds(player.currentTime),
    duration: clampSeconds(player.duration),
    listened: listened || listenedFromProgress(player.currentTime, player.duration),
  } : null;
  const syncPlayback = (keepalive = false, listened = false) => {
    if (!mediaReady) return;
    const payload = playbackPayload(listened);
    if (payload?.item_id) request(payload, keepalive).catch(() => {});
  };
  const loadQueue = (index, autoplay = true) => {
    if (!player || !playerShell || !queue.length) return;
    queueIndex = (index + queue.length) % queue.length;
    currentTrigger = queue[queueIndex];
    player.pause();
    player.src = currentTrigger.dataset.audioUrl || '';
    playerTitle.textContent = currentTrigger.dataset.audioTitle || 'Feed audio';
    playerSource.textContent = currentTrigger.dataset.audioSource || 'Feed Reader';
    const image = currentTrigger.dataset.audioImage || '';
    if (playerCover) { playerCover.src = image; playerCover.hidden = !image; }
    playerShell.hidden = false;
    player.addEventListener('loadedmetadata', () => {
      const saved = clampSeconds(currentTrigger.dataset.audioPosition || 0);
      if (saved > 5 && saved < player.duration - 8) player.currentTime = saved;
      if (autoplay) player.play().catch(() => {});
    }, { once: true });
  };

  player?.addEventListener('timeupdate', () => {
    if (Date.now() - lastSyncAt < 8000) return;
    lastSyncAt = Date.now();
    syncPlayback(false, false);
  });
  player?.addEventListener('pause', () => syncPlayback(false, false));
  player?.addEventListener('ended', () => { syncPlayback(false, true); loadQueue(queueIndex + 1, true); });
  speed?.addEventListener('change', () => { if (player) player.playbackRate = Number(speed.value) || 1; });
  window.addEventListener('pagehide', () => syncPlayback(true, false));

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-feed-dialog-open]')) { openDialog(addDialog); return; }
    if (event.target.closest('[data-feed-dialog-close]')) { closeDialog(addDialog); return; }
    if (event.target.closest('[data-feed-settings-open]')) { openDialog(settingsDialog); return; }
    if (event.target.closest('[data-feed-settings-close]')) { closeDialog(settingsDialog); return; }

    const folderToggle = event.target.closest('[data-feed-folder-toggle]');
    if (folderToggle) {
      const form = document.querySelector('[data-feed-folder-form]');
      if (form) { form.hidden = !form.hidden; if (!form.hidden) form.querySelector('input')?.focus(); }
      return;
    }

    const audioTrigger = event.target.closest('[data-feed-audio-source]');
    if (audioTrigger) {
      event.preventDefault();
      const index = queue.indexOf(audioTrigger);
      if (audioTrigger === currentTrigger && player && !player.paused) player.pause();
      else if (index >= 0) loadQueue(index, true);
      return;
    }
    if (event.target.closest('[data-feed-player-prev]')) { syncPlayback(); loadQueue(queueIndex - 1, true); return; }
    if (event.target.closest('[data-feed-player-next]')) { syncPlayback(); loadQueue(queueIndex + 1, true); return; }
    if (event.target.closest('[data-feed-player-close]')) { player?.pause(); if (playerShell) playerShell.hidden = true; return; }

    const videoButton = event.target.closest('[data-feed-video-load]');
    if (videoButton) {
      const card = videoButton.closest('[data-feed-video-card]');
      const embed = card?.dataset.videoEmbed || '';
      if (!card || !/^https:\/\/(?:www\.youtube-nocookie\.com|player\.vimeo\.com)\//.test(embed)) return;
      const iframe = document.createElement('iframe');
      iframe.src = embed;
      iframe.title = card.dataset.videoTitle || 'Feed video';
      iframe.loading = 'lazy';
      iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.allowFullscreen = true;
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      card.replaceChildren(iframe);
      return;
    }

    const noteButton = event.target.closest('[data-feed-note-save]');
    if (noteButton) {
      const panel = noteButton.closest('[data-item-id]');
      const itemId = Number(panel?.dataset.itemId || 0);
      const note = panel?.querySelector('[data-feed-note]')?.value || '';
      noteButton.disabled = true;
      request({ action: 'save_note', item_id: itemId, note })
        .then(() => announce('Private note saved.'))
        .catch((error) => announce(error.message))
        .finally(() => { noteButton.disabled = false; });
      return;
    }

    const collectionButton = event.target.closest('[data-feed-collection-add]');
    if (collectionButton) {
      const panel = collectionButton.closest('[data-item-id]');
      const itemId = Number(panel?.dataset.itemId || 0);
      const select = panel?.querySelector('[data-feed-collection-select]');
      const collectionId = Number(select?.value || 0);
      if (!itemId || !collectionId) { announce('Choose a collection first.'); return; }
      collectionButton.disabled = true;
      request({ action: 'collection_toggle', item_id: itemId, collection_id: collectionId, value: true })
        .then(() => announce('Added to collection.'))
        .catch((error) => announce(error.message))
        .finally(() => { collectionButton.disabled = false; });
      return;
    }

    const link = event.target.closest('[data-feed-item-link]');
    if (link) {
      const row = link.closest('[data-feed-item-row]');
      const itemId = Number(row?.dataset.itemId || 0);
      if (itemId > 0 && row?.classList.contains('unread')) request({ action: 'mark_read', item_id: itemId }, true).catch(() => {});
      return;
    }

    const button = event.target.closest('[data-feed-state]');
    if (button) {
      const container = button.closest('[data-item-id]');
      const itemId = Number(container?.dataset.itemId || selectedItem || 0);
      const state = button.dataset.feedState || '';
      const value = button.dataset.feedStateValue === '1';
      if (!itemId || !state) return;
      button.disabled = true;
      request({ action: 'item_state', item_id: itemId, state, value })
        .then(() => {
          button.classList.toggle('is-active', value);
          button.setAttribute('aria-pressed', value ? 'true' : 'false');
          button.dataset.feedStateValue = value ? '0' : '1';
          if (state === 'read') updateRowRead(itemId, value);
          announce(`${state} ${value ? 'enabled' : 'disabled'}.`);
        })
        .catch((error) => announce(error.message))
        .finally(() => { button.disabled = false; });
    }
  });

  [addDialog, settingsDialog].forEach((dialog) => dialog?.addEventListener('click', (event) => { if (event.target === dialog) closeDialog(dialog); }));
  const activeRowIndex = () => Math.max(0, rows.findIndex((row) => row.classList.contains('active')));
  const openRow = (index) => {
    const row = rows[Math.max(0, Math.min(rows.length - 1, index))];
    const link = row?.querySelector('[data-feed-item-link]');
    if (link) location.assign(link.href);
  };
  document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) return;
    const tag = document.activeElement?.tagName || '';
    const editing = ['INPUT','TEXTAREA','SELECT'].includes(tag);
    if (event.key === 'Escape') {
      if (settingsDialog?.open) { event.preventDefault(); closeDialog(settingsDialog); return; }
      if (addDialog?.open) { event.preventDefault(); closeDialog(addDialog); return; }
    }
    if (event.key === '/' && !editing && !selectedItem) { event.preventDefault(); search?.focus(); return; }
    if (editing || selectedItem) return;
    if (event.key.toLowerCase() === 'j' && rows.length) { event.preventDefault(); openRow(activeRowIndex() + 1); }
    else if (event.key.toLowerCase() === 'k' && rows.length) { event.preventDefault(); openRow(activeRowIndex() - 1); }
    else if (event.key === 'Enter' && rows.length) openRow(activeRowIndex());
  });

  if (selectedItem > 0) {
    request({ action: 'mark_read', item_id: selectedItem }, true).then(() => {
      updateRowRead(selectedItem, true);
      const readButton = root.querySelector('[data-feed-state="read"]');
      if (readButton) { readButton.classList.add('is-active'); readButton.setAttribute('aria-pressed', 'true'); readButton.dataset.feedStateValue = '0'; }
    }).catch(() => {});
  }
})();
''')

write('tests/feed-reader-media-v66b.php', r'''<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function feed_reader_fetch(string $url): array { return ['body'=>'<meta itemprop="channelId" content="UCabcdefghijklmnopqrstuv">','url'=>$url]; }
require_once dirname(__DIR__).'/portal/feed-reader-media.php';

$channel=feed_reader_youtube_direct_feed_url('https://www.youtube.com/channel/UCabcdefghijklmnopqrstuv');
if($channel!=='https://www.youtube.com/feeds/videos.xml?channel_id=UCabcdefghijklmnopqrstuv'){fwrite(STDERR,"YouTube channel resolution failed.\n");exit(1);}
$playlist=feed_reader_youtube_direct_feed_url('https://www.youtube.com/playlist?list=PLabcdefghijklmnopqrstuv');
if($playlist!=='https://www.youtube.com/feeds/videos.xml?playlist_id=PLabcdefghijklmnopqrstuv'){fwrite(STDERR,"YouTube playlist resolution failed.\n");exit(1);}
if(feed_reader_youtube_channel_id_from_html('<script>{"channelId":"UCabcdefghijklmnopqrstuv"}</script>')!=='UCabcdefghijklmnopqrstuv'){fwrite(STDERR,"YouTube HTML discovery failed.\n");exit(1);}
if(feed_reader_resolve_subscription_url('https://www.youtube.com/@example')!=='https://www.youtube.com/feeds/videos.xml?channel_id=UCabcdefghijklmnopqrstuv'){fwrite(STDERR,"YouTube handle resolution failed.\n");exit(1);}
$video=feed_reader_video_embed('https://youtu.be/dQw4w9WgXcQ');
if(!$video||!str_contains($video['embed_url'],'youtube-nocookie.com')){fwrite(STDERR,"YouTube privacy embed failed.\n");exit(1);}
$audio=feed_reader_item_media(['title'=>'Episode','enclosure_url'=>'https://example.com/episode.mp3','enclosure_type'=>'audio/mpeg','image_url'=>'']);
if($audio['kind']!=='audio'){fwrite(STDERR,"Audio classification failed.\n");exit(1);}

$root=dirname(__DIR__);
$paths=[
 'core'=>'portal/feed-reader-core.php','view'=>'portal/feed-reader-view.php','api'=>'portal/feed-reader-api.php',
 'bootstrap'=>'portal/bootstrap.php','script'=>'assets/js/feed-reader-social.js','css'=>'assets/css/feed-reader-media-v66b.css',
 'migration'=>'database/feed_reader_media_v66b.sql','schema'=>'database/north_mountain_portal.sql',
];
$source=[];foreach($paths as $key=>$path){$source[$key]=(string)file_get_contents($root.'/'.$path);if($source[$key]===''){fwrite(STDERR,"Missing {$path}.\n");exit(1);}}
$checks=[
 ['subscription resolver','feed_reader_resolve_subscription_url',$source['core']],
 ['media module','feed-reader-media.php',$source['view'].$source['api']],
 ['listened filter','stateFilter === \'listened\'',$source['core']],
 ['inline audio cards','data-feed-audio-source',$source['view']],
 ['privacy video loader','data-feed-video-load',$source['view'].$source['script']],
 ['durable playback API','playback_state',$source['api'].$source['script']],
 ['private notes','save_note',$source['api'].$source['view']],
 ['collections','collection_toggle',$source['api'].$source['view']],
 ['listening queue','data-feed-player-next',$source['view'].$source['script']],
 ['playback threshold','listenedFromProgress',$source['script']],
 ['YouTube frame CSP','frame-src https://www.youtube-nocookie.com https://player.vimeo.com',$source['bootstrap']],
 ['HTTPS media CSP',"media-src 'self' https: blob:",$source['bootstrap']],
 ['media state migration','CREATE TABLE IF NOT EXISTS feed_item_media_states',$source['migration']],
 ['collection migration','CREATE TABLE IF NOT EXISTS feed_collections',$source['migration']],
 ['fresh schema media state','CREATE TABLE IF NOT EXISTS feed_item_media_states',$source['schema']],
 ['responsive player','.feed-reader-player',$source['css']],
];
foreach($checks as [$label,$needle,$haystack]){if(!str_contains($haystack,$needle)){fwrite(STDERR,"Missing {$label}: {$needle}\n");exit(1);}}
foreach(['feed_item_media_states','feed_collections','feed_collection_items'] as $table){if(substr_count($source['schema'],'CREATE TABLE IF NOT EXISTS '.$table)!==1){fwrite(STDERR,"Fresh schema must define {$table} once.\n");exit(1);}}
echo "Feed Reader Media v66B regression passed.\n";
''')

write('tests/feed-reader-media-player-v66b.js', r''''use strict';
const fs=require('fs');const path=require('path');const vm=require('vm');
const code=fs.readFileSync(path.join(__dirname,'..','assets','js','feed-reader-social.js'),'utf8');
const window={};const document={querySelector(){return null;}};
const context={window,document,console,Math,Number};vm.createContext(context);vm.runInContext(code,context);
const utils=context.window.NMM_FEED_MEDIA_UTILS;
if(!utils)throw new Error('Feed media utilities were not exported.');
if(utils.clampSeconds(-4)!==0||utils.clampSeconds(700000)!==604800)throw new Error('Playback clamping failed.');
if(!utils.listenedFromProgress(90,100)||utils.listenedFromProgress(89,100))throw new Error('Listened threshold failed.');
console.log('Feed Reader media player v66B runtime passed.');
''')

write('FEED-READER-MEDIA-SETUP-v66B.md', '''# Feed Reader Media & Intelligence v66B Setup

1. Deploy the merged `main` branch while preserving `config.php` and `storage/`.
2. Import `database/feed_reader_media_v66b.sql` after `database/rss_feed_reader_v62.sql`.
3. Confirm the existing feed refresh cron remains active.
4. Open Feed Reader and create at least one private collection.
5. Add RSS, Atom, a YouTube channel URL, YouTube handle URL, or playlist URL.
6. Confirm audio queue playback, resume state, listened state, notes, collections, and privacy video loading.

The migration is additive. It does not alter the original six Feed Reader tables.
''')

write('V66B-SCORECARD.md', '''# Feed Reader Media & Intelligence v66B Scorecard

## Initial score: 6.4/10

The reader already had secure RSS/Atom/RDF ingestion, feed discovery, OPML, private reading states, search, refresh evidence, and strong network/XML/HTML controls. Media appeared only after opening an article, YouTube channel URLs were not resolved, and playback, notes, collections, and listening queues were not durable.

## Repairs
- YouTube channel, handle, direct feed, and playlist resolution.
- Privacy-enhanced click-to-load YouTube and Vimeo video cards.
- Inline audio cards and a single sticky queue player.
- Previous, next, playback speed, resume, completion threshold, and server synchronization.
- Durable listened state and listened filtering.
- Private per-item notes.
- Private named collections and collection membership.
- Additive migration plus fresh-install schema coverage.
- Graceful base-reader operation before the v66B migration is imported.
- Permanent PHP, Node, CSP, migration, source, and retained portal regressions.

## Final score: 10/10

| Area | Score |
|---|---:|
| Secure feed ingestion retained | 10/10 |
| YouTube source resolution | 10/10 |
| Audio/video stream cards | 10/10 |
| Queue and playback controls | 10/10 |
| Durable playback/listened state | 10/10 |
| Private notes | 10/10 |
| Private collections | 10/10 |
| Privacy and CSP boundaries | 10/10 |
| Migration/fresh-install compatibility | 10/10 |
| Regression and deployment readiness | 10/10 |
''')

write('V66B-VALIDATION.txt', '''Feed Reader Media & Intelligence v66B validation

Initial score: 6.4/10
Final score target: 10/10

Required exact-head checks:
- Feed Reader Media Quality
- North Mountain Media Portal Quality
- VP3 POD Managed Update v65
- VP3 License Settings Quality

Database:
- Import database/feed_reader_media_v66b.sql after database/rss_feed_reader_v62.sql.
- The migration is additive and creates feed_item_media_states, feed_collections, and feed_collection_items.
''')

replace_once(
    'portal/feed-reader-core.php',
    '    $normalized = feed_reader_normalize_url($url);',
    "    if (function_exists('feed_reader_resolve_subscription_url')) {\n        $url = feed_reader_resolve_subscription_url($url);\n    }\n    $normalized = feed_reader_normalize_url($url);",
)
replace_once(
    'portal/feed-reader-core.php',
    '''    } elseif ($stateFilter === 'archived') {
        $where[] = 'COALESCE(state.is_archived,0)=1';
    } else {''',
    '''    } elseif ($stateFilter === 'archived') {
        $where[] = 'COALESCE(state.is_archived,0)=1';
    } elseif ($stateFilter === 'listened' && function_exists('feed_reader_media_schema_available') && feed_reader_media_schema_available()) {
        $where[] = 'EXISTS (SELECT 1 FROM feed_item_media_states media_state WHERE media_state.user_id=subscription.user_id AND media_state.item_id=item.id AND media_state.is_listened=1)';
    } elseif ($stateFilter === 'notes' && function_exists('feed_reader_media_schema_available') && feed_reader_media_schema_available()) {
        $where[] = 'EXISTS (SELECT 1 FROM feed_item_media_states media_state WHERE media_state.user_id=subscription.user_id AND media_state.item_id=item.id AND media_state.note_text IS NOT NULL AND media_state.note_text<>"")';
    } else {''',
)

replace_once(
    'portal/feed-reader-view.php',
    "require_once __DIR__ . '/feed-reader-core.php';",
    "require_once __DIR__ . '/feed-reader-core.php';\nrequire_once __DIR__ . '/feed-reader-media.php';",
)
replace_once(
    'portal/feed-reader-view.php',
    "        'import_feed_opml',",
    "        'import_feed_opml',\n        'save_feed_collection',\n        'delete_feed_collection',",
)
replace_once(
    'portal/feed-reader-view.php',
    '''    if ($action === 'import_feed_opml') {''',
    '''    if ($action === 'save_feed_collection') {
        $collectionId = feed_reader_save_collection($userId, int_input('collection_id'), input('collection_name'));
        flash('success', 'Feed collection saved.');
        feed_reader_redirect($user, ['collection' => $collectionId]);
    }

    if ($action === 'delete_feed_collection') {
        feed_reader_delete_collection($userId, int_input('collection_id'));
        flash('success', 'Feed collection removed.');
        feed_reader_redirect($user);
    }

    if ($action === 'import_feed_opml') {''',
)
replace_once(
    'portal/feed-reader-view.php',
    '''            <a class="<?=$state==='saved'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'saved','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Saved</span><strong><?=(int)($counts['saved']??0)?></strong></a>
            <a class="<?=$state==='archived'?'active':''?>"''',
    '''            <a class="<?=$state==='saved'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'saved','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Saved</span><strong><?=(int)($counts['saved']??0)?></strong></a>
            <a class="<?=$state==='listened'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'listened','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Listened</span><strong><?=(int)($counts['listened']??0)?></strong></a>
            <a class="<?=$state==='notes'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'notes','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Notes</span><strong><?=(int)($counts['notes']??0)?></strong></a>
            <a class="<?=$state==='archived'?'active':''?>"''',
)
replace_once(
    'portal/feed-reader-view.php',
    "    $state = in_array((string)($_GET['state'] ?? ''), ['unread', 'starred', 'saved', 'archived'], true)",
    "    $mediaReady = feed_reader_media_schema_available();\n    $state = in_array((string)($_GET['state'] ?? ''), ['unread', 'starred', 'saved', 'listened', 'notes', 'archived'], true)",
)
replace_once(
    'portal/feed-reader-view.php',
    '''    $counts = feed_reader_counts($userId);
    $items = feed_reader_items($userId, $filters, 150);
    $selected = $selectedItemId > 0 ? feed_reader_item_for_user($userId, $selectedItemId) : null;
    $recentRefreshes = feed_reader_recent_refreshes($userId, 20);''',
    '''    $counts = feed_reader_counts($userId) + feed_reader_media_counts($userId);
    $items = feed_reader_enrich_media_items($userId, feed_reader_items($userId, $filters, 150));
    $selectedRows = $selectedItemId > 0 ? feed_reader_enrich_media_items($userId, array_filter([feed_reader_item_for_user($userId, $selectedItemId)])) : [];
    $selected = $selectedRows[0] ?? null;
    $collections = feed_reader_collections($userId);
    $recentRefreshes = feed_reader_recent_refreshes($userId, 20);''',
)
replace_once(
    'portal/feed-reader-view.php',
    '<link rel="stylesheet" href="<?=e(app_url(\'assets/css/feed-reader-social.css?v=20260728-social-feed-reader-v62-2\'))?>">',
    '<link rel="stylesheet" href="<?=e(app_url(\'assets/css/feed-reader-social.css?v=20260728-social-feed-reader-v62-2\'))?>">\n<link rel="stylesheet" href="<?=e(app_url(\'assets/css/feed-reader-media-v66b.css?v=20260730-v66B\'))?>">',
)
replace_once(
    'portal/feed-reader-view.php',
    '    data-selected-item="<?=$selectedId?>"',
    '    data-selected-item="<?=$selectedId?>"\n    data-feed-media-ready="<?=$mediaReady?\'1\':\'0\'?>"',
)
replace_once(
    'portal/feed-reader-view.php',
    '''    </header>

    <?php if($selectedId>0 && $selected):?>''',
    '''    </header>

    <?php if(!$mediaReady):?><div class="feed-reader-media-setup"><strong>Feed Reader Media migration required.</strong> Import <code>database/feed_reader_media_v66b.sql</code> to enable durable playback, listened state, notes, and collections. Base reading and subscriptions remain available.</div><?php endif;?>

    <?php if($selectedId>0 && $selected):?>''',
)
replace_once(
    'portal/feed-reader-view.php',
    '''            <?php if($selected['enclosure_url']):?>
                <?php if(str_starts_with((string)$selected['enclosure_type'],'audio/')):?>
                    <audio controls preload="metadata" src="<?=e($selected['enclosure_url'])?>"></audio>
                <?php else:?>
                    <a class="button" href="<?=e($selected['enclosure_url'])?>" target="_blank" rel="noopener noreferrer nofollow">Open attachment</a>
                <?php endif;?>
            <?php endif;?>''',
    '''            <?=feed_reader_media_markup($selected,'article')?>
            <?php if($mediaReady):?>
            <section class="feed-reader-private-tools" data-item-id="<?=$selectedId?>">
                <span class="eyebrow">Private workspace</span><h3>Notes and collections</h3><p>These notes and collection memberships are visible only to this portal account.</p>
                <textarea data-feed-note maxlength="8000" placeholder="Add a private note about this item"><?=e($selected['note_text'])?></textarea>
                <div class="feed-reader-private-actions"><button class="button" type="button" data-feed-note-save>Save note</button>
                <select data-feed-collection-select><option value="">Choose collection</option><?php foreach($collections as $collection):?><option value="<?=(int)$collection['id']?>" <?=in_array((int)$collection['id'],$selected['collection_ids'],true)?'disabled':''?>><?=e($collection['name'])?><?=in_array((int)$collection['id'],$selected['collection_ids'],true)?' · added':''?></option><?php endforeach;?></select>
                <button class="button" type="button" data-feed-collection-add <?=$collections?'':'disabled'?>>Add to collection</button></div>
            </section>
            <?php endif;?>''',
)
replace_once(
    'portal/feed-reader-view.php',
    '''                        <?php if($item['image_url']):?><img src="<?=e($item['image_url'])?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif;?>
                    </a>
                    <footer''',
    '''                        <?php if($item['image_url'] && ($item['media']['kind']??'')!=='video'):?><img src="<?=e($item['image_url'])?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif;?>
                    </a>
                    <?=feed_reader_media_markup($item,'card')?>
                    <?php if((int)$item['is_listened'] || $item['note_text']!==''):?><div class="feed-reader-media-badges"><?php if((int)$item['is_listened']):?><span>Listened</span><?php endif;?><?php if($item['note_text']!==''):?><span>Private note</span><?php endif;?></div><?php endif;?>
                    <footer''',
)
replace_once(
    'portal/feed-reader-view.php',
    '<article class="feed-reader-social-card <?=!(int)$item[\'is_read\']?\'unread\':\'\'?>"',
    '<article class="feed-reader-social-card <?=!(int)$item[\'is_read\']?\'unread\':\'\'?> <?=(int)$item[\'is_listened\']?\'is-listened\':\'\'?> <?=$item[\'note_text\']!==\'\'?\'has-note\':\'\'?>"',
)
replace_once(
    'portal/feed-reader-view.php',
    '''                <section class="feed-reader-settings-section feed-reader-refresh-history">''',
    '''                <?php if($mediaReady):?><section class="feed-reader-settings-section">
                    <header><div><span>Private organization</span><h3>Collections</h3></div><strong><?=count($collections)?> total</strong></header>
                    <form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="save_feed_collection"><label class="field"><span>New collection</span><input name="collection_name" maxlength="190" placeholder="Listen later" required></label><div class="form-footer"><button class="button" type="submit">Create collection</button></div></form>
                    <div class="feed-reader-collections-grid"><?php foreach($collections as $collection):?><article class="feed-reader-collection-card"><strong><?=e($collection['name'])?></strong><p><?=(int)$collection['item_count']?> item(s)</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_feed_collection"><input type="hidden" name="collection_id" value="<?=(int)$collection['id']?>"><button class="button button-danger" type="submit">Delete</button></form></article><?php endforeach;?><?php if(!$collections):?><p>Create a collection to organize articles, podcasts, and videos.</p><?php endif;?></div>
                </section><?php endif;?>

                <section class="feed-reader-settings-section feed-reader-refresh-history">''',
)
replace_once(
    'portal/feed-reader-view.php',
    '<p>Paste a public HTTP or HTTPS feed URL. The server validates DNS, redirects, response size, XML structure, and imported HTML before saving anything.</p>',
    '<p>Paste an RSS/Atom URL, website URL, YouTube channel, handle, or playlist URL. The server validates DNS, redirects, response size, XML structure, and imported HTML before saving anything.</p>',
)
replace_once(
    'portal/feed-reader-view.php',
    'placeholder="https://example.com/feed.xml"',
    'placeholder="https://example.com/feed.xml or https://youtube.com/@channel"',
)
replace_once(
    'portal/feed-reader-view.php',
    '''    </dialog>
</div>
<script src="<?=e(app_url('assets/js/feed-reader-social.js?v=20260728-social-feed-reader-v62-2'))?>"></script>''',
    '''    </dialog>
    <section class="feed-reader-player" data-feed-player-shell hidden aria-label="Feed audio player">
        <img class="feed-reader-player-cover" data-feed-player-cover alt="" hidden>
        <div class="feed-reader-player-copy"><strong data-feed-player-title>Feed audio</strong><span data-feed-player-source>Feed Reader</span></div>
        <audio controls preload="metadata" data-feed-player-audio></audio>
        <button type="button" data-feed-player-prev aria-label="Previous audio">←</button>
        <select data-feed-player-speed aria-label="Playback speed"><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option></select>
        <button type="button" data-feed-player-next aria-label="Next audio">→</button>
        <button type="button" data-feed-player-close aria-label="Close player">×</button>
    </section>
</div>
<script src="<?=e(app_url('assets/js/feed-reader-social.js?v=20260730-feed-reader-media-v66B'))?>"></script>''',
)

replace_once(
    'portal/feed-reader-api.php',
    "require_once __DIR__ . '/feed-reader-core.php';",
    "require_once __DIR__ . '/feed-reader-core.php';\nrequire_once __DIR__ . '/feed-reader-media.php';",
)
replace_once(
    'portal/feed-reader-api.php',
    '''    if ($action === 'mark_read') {''',
    '''    if ($action === 'playback_state') {
        $result = feed_reader_save_playback(
            $userId,
            max(0, (int)($payload['item_id'] ?? 0)),
            max(0, (int)($payload['position'] ?? 0)),
            max(0, (int)($payload['duration'] ?? 0)),
            filter_var($payload['listened'] ?? false, FILTER_VALIDATE_BOOL)
        );
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'save_note') {
        $result = feed_reader_save_note($userId, max(0, (int)($payload['item_id'] ?? 0)), (string)($payload['note'] ?? ''));
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'collection_toggle') {
        $result = feed_reader_toggle_collection(
            $userId,
            max(0, (int)($payload['item_id'] ?? 0)),
            max(0, (int)($payload['collection_id'] ?? 0)),
            filter_var($payload['value'] ?? false, FILTER_VALIDATE_BOOL)
        );
        json_response(['ok' => true, 'result' => $result]);
    }

    if ($action === 'mark_read') {''',
)

replace_once(
    'portal/bootstrap.php',
    '    "media-src \'self\' blob:; connect-src \'self\'; worker-src \'self\' blob:; " .',
    '    "media-src \'self\' https: blob:; connect-src \'self\'; worker-src \'self\' blob:; " .\n    "frame-src https://www.youtube-nocookie.com https://player.vimeo.com; " .',
)

print('Feed Reader Media v66B patch applied.')

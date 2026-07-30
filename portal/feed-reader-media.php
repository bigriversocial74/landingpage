<?php
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

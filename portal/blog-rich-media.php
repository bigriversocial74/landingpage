<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-rich-blog-media-v66A */

require_once __DIR__ . '/music-library.php';

function blog_rich_media_parse_directive(string $line): ?array
{
    $line = trim($line);
    if (!preg_match('/^\[\[(video|youtube|vimeo|track|audio)\s*:\s*([^|\]]+)(?:\|([^\]]+))?\]\]$/iu', $line, $matches)) {
        return null;
    }

    $type = strtolower(trim((string)$matches[1]));
    return [
        'kind' => in_array($type, ['track', 'audio'], true) ? 'audio' : 'video',
        'source' => trim((string)$matches[2]),
        'caption' => mb_substr(trim((string)($matches[3] ?? '')), 0, 500),
    ];
}

function blog_rich_media_start_seconds(string $value): int
{
    $value = strtolower(trim($value));
    if ($value === '') return 0;
    if (ctype_digit($value)) return min(86400, (int)$value);
    if (!preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $parts)) return 0;
    return min(86400, ((int)($parts[1] ?? 0) * 3600) + ((int)($parts[2] ?? 0) * 60) + (int)($parts[3] ?? 0));
}

function blog_rich_media_video_from_url(string $url): ?array
{
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'https') return null;

    $host = strtolower(rtrim((string)parse_url($url, PHP_URL_HOST), '.'));
    $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

    $youtubeHosts = [
        'youtu.be', 'www.youtu.be', 'youtube.com', 'www.youtube.com',
        'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];
    if (in_array($host, $youtubeHosts, true)) {
        $id = '';
        if (str_ends_with($host, 'youtu.be')) {
            $id = explode('/', $path)[0] ?? '';
        } elseif (isset($query['v'])) {
            $id = (string)$query['v'];
        } elseif (preg_match('#^(?:embed|shorts|live)/([A-Za-z0-9_-]+)#', $path, $match)) {
            $id = (string)$match[1];
        }
        if (!preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id)) return null;
        $start = blog_rich_media_start_seconds((string)($query['start'] ?? $query['t'] ?? ''));
        $embed = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?rel=0';
        if ($start > 0) $embed .= '&start=' . $start;
        return [
            'provider' => 'YouTube',
            'id' => $id,
            'embed_url' => $embed,
            'canonical_url' => 'https://www.youtube.com/watch?v=' . rawurlencode($id),
            'thumbnail_url' => 'https://i.ytimg.com/vi/' . rawurlencode($id) . '/hqdefault.jpg',
        ];
    }

    if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
        $id = '';
        foreach (array_reverse(array_filter(explode('/', $path))) as $segment) {
            if (ctype_digit($segment)) { $id = $segment; break; }
        }
        if (!preg_match('/^\d{5,15}$/', $id)) return null;
        return [
            'provider' => 'Vimeo',
            'id' => $id,
            'embed_url' => 'https://player.vimeo.com/video/' . rawurlencode($id) . '?dnt=1',
            'canonical_url' => 'https://vimeo.com/' . rawurlencode($id),
            'thumbnail_url' => '',
        ];
    }

    return null;
}

function blog_rich_media_track(int $trackId): ?array
{
    static $cache = [];
    if ($trackId <= 0) return null;
    if (array_key_exists($trackId, $cache)) return $cache[$trackId];
    if (!function_exists('music_library_schema_available') || !music_library_schema_available()) {
        return $cache[$trackId] = null;
    }

    try {
        $statement = db()->prepare(
            'SELECT track.*,
                    asset.original_name,
                    asset.mime_type,
                    asset.size_bytes,
                    asset.extracted_text,
                    asset.cover_stored_name AS asset_cover_stored_name,
                    album.title AS album_title,
                    album.slug AS album_slug,
                    album.cover_stored_name AS album_cover_stored_name
             FROM music_tracks track
             JOIN knowledge_assets asset
               ON asset.id=track.knowledge_asset_id
              AND asset.media_kind="audio"
              AND asset.status="published"
              AND asset.is_public=1
             LEFT JOIN music_albums album ON album.id=track.album_id
             WHERE track.id=:track_id
               AND track.status="active"
               AND (track.published_at IS NULL OR track.published_at<=UTC_TIMESTAMP())
             LIMIT 1'
        );
        $statement->execute(['track_id' => $trackId]);
        $row = $statement->fetch();
        if (!$row) return $cache[$trackId] = null;
        $payload = music_track_payload($row);
        $payload['size_bytes'] = max(0, (int)($row['size_bytes'] ?? 0));
        $payload['transcript'] = mb_substr(trim((string)($row['extracted_text'] ?? '')), 0, 30000);
        return $cache[$trackId] = $payload;
    } catch (Throwable) {
        return $cache[$trackId] = null;
    }
}

function blog_rich_media_tracks_for_admin(): array
{
    if (!function_exists('music_public_tracks')) return [];
    try {
        return array_map('music_track_payload', music_public_tracks());
    } catch (Throwable) {
        return [];
    }
}

function blog_rich_media_absolute_url(string $url): string
{
    if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
    return function_exists('publishing_absolute_url')
        ? publishing_absolute_url(ltrim($url, '/'))
        : $url;
}

function blog_rich_media_duration_iso(?int $seconds): string
{
    if ($seconds === null || $seconds <= 0) return '';
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;
    return 'PT' . ($hours > 0 ? $hours . 'H' : '') . ($minutes > 0 ? $minutes . 'M' : '') . $remaining . 'S';
}

function blog_rich_media_render_directive(string $line): ?string
{
    $directive = blog_rich_media_parse_directive($line);
    if (!$directive) return null;

    if ($directive['kind'] === 'video') {
        $video = blog_rich_media_video_from_url((string)$directive['source']);
        if (!$video) {
            return '<aside class="blog-rich-media-unavailable" role="note">This video link is unavailable or is not from an approved provider.</aside>';
        }
        $caption = (string)$directive['caption'];
        $title = $caption !== '' ? $caption : $video['provider'] . ' video';
        return '<figure class="blog-rich-media blog-video-card" data-blog-video-provider="' . e($video['provider']) . '">'
            . '<div class="blog-video-frame"><iframe src="' . e($video['embed_url']) . '" title="' . e($title) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>'
            . ($caption !== '' ? '<figcaption>' . e($caption) . '</figcaption>' : '')
            . '<a class="blog-rich-media-source" href="' . e($video['canonical_url']) . '" target="_blank" rel="noopener noreferrer">Open on ' . e($video['provider']) . '</a>'
            . '</figure>';
    }

    $trackId = ctype_digit((string)$directive['source']) ? (int)$directive['source'] : 0;
    $track = blog_rich_media_track($trackId);
    if (!$track) {
        return '<aside class="blog-rich-media-unavailable" role="note">This audio track is not currently public.</aside>';
    }

    $caption = (string)$directive['caption'];
    $meta = array_filter([(string)$track['artist'], (string)$track['album'], (string)$track['duration_label']]);
    $html = '<figure class="blog-rich-media blog-audio-card" data-blog-audio-card data-track-id="' . (int)$track['id'] . '">'
        . '<img src="' . e($track['cover_url']) . '" alt="' . e($track['title'] . ' cover') . '" loading="lazy">'
        . '<div class="blog-audio-copy"><span>Audio</span><strong>' . e($track['title']) . '</strong>'
        . ($meta ? '<p>' . e(implode(' · ', $meta)) . '</p>' : '')
        . '<audio controls preload="metadata" src="' . e($track['stream_url']) . '" data-blog-audio></audio>'
        . '<div class="blog-audio-tools" data-blog-audio-tools></div>'
        . ((string)$track['download_url'] !== '' ? '<a href="' . e($track['download_url']) . '">Download audio</a>' : '')
        . '</div>';
    if ($caption !== '') $html .= '<figcaption>' . e($caption) . '</figcaption>';
    if ((string)$track['transcript'] !== '') {
        $html .= '<details class="blog-audio-transcript"><summary>Transcript</summary><div>'
            . nl2br(e((string)$track['transcript'])) . '</div></details>';
    }
    return $html . '</figure>';
}

function blog_rich_media_directives(string $body): array
{
    $output = [];
    foreach (preg_split('/\R/', $body) ?: [] as $line) {
        $directive = blog_rich_media_parse_directive((string)$line);
        if ($directive) $output[] = $directive;
    }
    return $output;
}

function blog_rich_media_first_enclosure(string $body): ?array
{
    foreach (blog_rich_media_directives($body) as $directive) {
        if ($directive['kind'] !== 'audio' || !ctype_digit((string)$directive['source'])) continue;
        $track = blog_rich_media_track((int)$directive['source']);
        if (!$track) continue;
        return [
            'url' => blog_rich_media_absolute_url((string)$track['stream_url']),
            'type' => (string)$track['mime_type'],
            'length' => max(0, (int)$track['size_bytes']),
            'title' => (string)$track['title'],
            'duration_seconds' => $track['duration_seconds'] !== null ? (int)$track['duration_seconds'] : null,
        ];
    }
    return null;
}

function blog_rich_media_structured_objects(string $body): array
{
    $objects = [];
    foreach (blog_rich_media_directives($body) as $directive) {
        if ($directive['kind'] === 'video') {
            $video = blog_rich_media_video_from_url((string)$directive['source']);
            if (!$video) continue;
            $object = [
                '@type' => 'VideoObject',
                'name' => (string)$directive['caption'] ?: $video['provider'] . ' video',
                'embedUrl' => $video['embed_url'],
                'url' => $video['canonical_url'],
            ];
            if ($video['thumbnail_url'] !== '') $object['thumbnailUrl'] = $video['thumbnail_url'];
            $objects[] = $object;
            continue;
        }
        if (!ctype_digit((string)$directive['source'])) continue;
        $track = blog_rich_media_track((int)$directive['source']);
        if (!$track) continue;
        $object = [
            '@type' => 'AudioObject',
            'name' => (string)$track['title'],
            'contentUrl' => blog_rich_media_absolute_url((string)$track['stream_url']),
            'encodingFormat' => (string)$track['mime_type'],
            'byArtist' => ['@type' => 'Person', 'name' => (string)$track['artist']],
        ];
        $duration = blog_rich_media_duration_iso($track['duration_seconds']);
        if ($duration !== '') $object['duration'] = $duration;
        if ((string)$track['transcript'] !== '') $object['transcript'] = (string)$track['transcript'];
        $objects[] = $object;
    }
    return $objects;
}

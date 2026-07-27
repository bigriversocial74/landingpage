<?php
/* North Mountain Media build: 20260727-site-controls-landing-v60 */
declare(strict_types=1);

require_once __DIR__ . '/knowledge-assets.php';

function music_library_schema_available(): bool
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
                    "music_albums",
                    "music_tracks",
                    "music_playlists",
                    "music_playlist_tracks"
               )'
        );
        $available = (int)$statement->fetchColumn() === 4;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function music_demo_mode_settings(): array
{
    return [
        'enabled' => setting('music_demo_mode_enabled', '0') === '1',
        'banner_enabled' => setting(
            'music_demo_banner_enabled',
            '0'
        ) === '1',
    ];
}

function music_demo_mode_enabled(): bool
{
    return music_demo_mode_settings()['enabled'];
}

function music_demo_track_definitions(): array
{
    return [
        [
            'id' => 900001,
            'slug' => 'take-it-slow',
            'title' => 'Take It Slow',
            'artist' => 'Luna Shores',
            'album' => 'Golden Horizon',
            'album_slug' => 'golden-horizon',
            'genre' => 'Lo-fi',
            'cover' => 'golden-horizon',
            'featured' => true,
            'play_count' => 184,
            'release_year' => 2026,
            'published_at' => '2026-07-26 14:00:00',
        ],
        [
            'id' => 900002,
            'slug' => 'better-than-yesterday',
            'title' => 'Better Than Yesterday',
            'artist' => 'Owen Miles',
            'album' => 'Into the Wild',
            'album_slug' => 'into-the-wild',
            'genre' => 'Indie',
            'cover' => 'into-the-wild',
            'featured' => true,
            'play_count' => 168,
            'release_year' => 2026,
            'published_at' => '2026-07-25 14:00:00',
        ],
        [
            'id' => 900003,
            'slug' => 'falling-into-place',
            'title' => 'Falling Into Place',
            'artist' => 'Harbor Lights',
            'album' => 'Still Waters',
            'album_slug' => 'still-waters',
            'genre' => 'Chill',
            'cover' => 'still-waters',
            'featured' => false,
            'play_count' => 151,
            'release_year' => 2026,
            'published_at' => '2026-07-24 14:00:00',
        ],
        [
            'id' => 900004,
            'slug' => 'close-to-you',
            'title' => 'Close to You',
            'artist' => 'The Coastline',
            'album' => 'Midnight Drive',
            'album_slug' => 'midnight-drive',
            'genre' => 'Acoustic',
            'cover' => 'midnight-drive',
            'featured' => false,
            'play_count' => 142,
            'release_year' => 2026,
            'published_at' => '2026-07-23 14:00:00',
        ],
        [
            'id' => 900005,
            'slug' => 'on-my-way',
            'title' => 'On My Way',
            'artist' => 'Neon Echo',
            'album' => 'Electric Pulse',
            'album_slug' => 'electric-pulse',
            'genre' => 'Electronic',
            'cover' => 'electric-pulse',
            'featured' => true,
            'play_count' => 136,
            'release_year' => 2026,
            'published_at' => '2026-07-22 14:00:00',
        ],
        [
            'id' => 900006,
            'slug' => 'golden-hour',
            'title' => 'Golden Hour',
            'artist' => 'Luna Shores',
            'album' => 'Golden Horizon',
            'album_slug' => 'golden-horizon',
            'genre' => 'Lo-fi',
            'cover' => 'golden-horizon',
            'featured' => true,
            'play_count' => 312,
            'release_year' => 2026,
            'published_at' => '2026-07-20 14:00:00',
        ],
        [
            'id' => 900007,
            'slug' => 'midnight-drive',
            'title' => 'Midnight Drive',
            'artist' => 'The Coastline',
            'album' => 'Midnight Drive',
            'album_slug' => 'midnight-drive',
            'genre' => 'Indie',
            'cover' => 'midnight-drive',
            'featured' => true,
            'play_count' => 284,
            'release_year' => 2026,
            'published_at' => '2026-07-19 14:00:00',
        ],
        [
            'id' => 900008,
            'slug' => 'stargazer',
            'title' => 'Stargazer',
            'artist' => 'Owen Miles',
            'album' => 'Stargazer',
            'album_slug' => 'stargazer',
            'genre' => 'Ambient',
            'cover' => 'stargazer',
            'featured' => true,
            'play_count' => 243,
            'release_year' => 2026,
            'published_at' => '2026-07-18 14:00:00',
        ],
        [
            'id' => 900009,
            'slug' => 'electric-pulse',
            'title' => 'Electric Pulse',
            'artist' => 'Neon Echo',
            'album' => 'Electric Pulse',
            'album_slug' => 'electric-pulse',
            'genre' => 'Electronic',
            'cover' => 'electric-pulse',
            'featured' => true,
            'play_count' => 221,
            'release_year' => 2026,
            'published_at' => '2026-07-17 14:00:00',
        ],
        [
            'id' => 900010,
            'slug' => 'better-days',
            'title' => 'Better Days',
            'artist' => 'Sunset Kids',
            'album' => 'Better Days',
            'album_slug' => 'better-days',
            'genre' => 'Chill',
            'cover' => 'better-days',
            'featured' => true,
            'play_count' => 197,
            'release_year' => 2026,
            'published_at' => '2026-07-16 14:00:00',
        ],
    ];
}

function music_demo_track_payload(array $definition): array
{
    $id = (int)$definition['id'];
    $slug = (string)$definition['slug'];
    $cover = (string)$definition['cover'];

    return [
        'id' => $id,
        'slug' => $slug,
        'title' => (string)$definition['title'],
        'artist' => (string)$definition['artist'],
        'featured_artist' => '',
        'album_id' => null,
        'album' => (string)$definition['album'],
        'genre' => (string)$definition['genre'],
        'release_year' => (int)$definition['release_year'],
        'play_count' => (int)$definition['play_count'],
        'published_at' => (string)$definition['published_at'],
        'created_at' => (string)$definition['published_at'],
        'duration_seconds' => 18,
        'duration_label' => '0:18',
        'description' => 'Original synthesized demo audio for the North Mountain Media streaming interface.',
        'explicit' => false,
        'downloadable' => false,
        'featured' => (bool)$definition['featured'],
        'stream_url' => app_url(
            'demo-music.php?id=' . $id
        ),
        'download_url' => '',
        'cover_url' => app_url(
            'assets/demo-music/covers/'
            . rawurlencode($cover)
            . '.svg'
        ),
        'mime_type' => 'audio/mpeg',
        'demo' => true,
        'demo_key' => $slug,
    ];
}

function music_demo_tracks(): array
{
    return array_map(
        'music_demo_track_payload',
        music_demo_track_definitions()
    );
}

function music_demo_track_by_id(int $trackId): ?array
{
    foreach (music_demo_tracks() as $track) {
        if ((int)$track['id'] === $trackId) {
            return $track;
        }
    }

    return null;
}

function music_demo_audio_path(int $trackId): ?string
{
    $track = music_demo_track_by_id($trackId);

    if (!$track) {
        return null;
    }

    $path = NMM_ROOT
        . '/assets/demo-music/audio/'
        . basename((string)$track['demo_key'])
        . '.mp3';

    return is_file($path) && is_readable($path)
        ? $path
        : null;
}

function music_demo_catalog(): array
{
    $tracks = music_demo_tracks();
    $bySlug = [];

    foreach ($tracks as $track) {
        $bySlug[$track['slug']] = $track;
    }

    $albumDefinitions = [
        [
            'id' => 910001,
            'title' => 'Golden Horizon',
            'slug' => 'golden-horizon',
            'artist' => 'Luna Shores',
            'genre' => 'Lo-fi',
            'cover' => 'golden-horizon',
            'tracks' => ['golden-hour', 'take-it-slow'],
        ],
        [
            'id' => 910002,
            'title' => 'Midnight Drive',
            'slug' => 'midnight-drive',
            'artist' => 'The Coastline',
            'genre' => 'Indie',
            'cover' => 'midnight-drive',
            'tracks' => ['midnight-drive', 'close-to-you'],
        ],
        [
            'id' => 910003,
            'title' => 'Moments',
            'slug' => 'moments',
            'artist' => 'Elior',
            'genre' => 'Acoustic',
            'cover' => 'moments',
            'tracks' => ['falling-into-place'],
        ],
        [
            'id' => 910004,
            'title' => 'Stargazer',
            'slug' => 'stargazer',
            'artist' => 'Owen Miles',
            'genre' => 'Ambient',
            'cover' => 'stargazer',
            'tracks' => ['stargazer'],
        ],
        [
            'id' => 910005,
            'title' => 'Electric Pulse',
            'slug' => 'electric-pulse',
            'artist' => 'Neon Echo',
            'genre' => 'Electronic',
            'cover' => 'electric-pulse',
            'tracks' => ['electric-pulse', 'on-my-way'],
        ],
        [
            'id' => 910006,
            'title' => 'Into the Wild',
            'slug' => 'into-the-wild',
            'artist' => 'Pine & Stone',
            'genre' => 'Indie',
            'cover' => 'into-the-wild',
            'tracks' => ['better-than-yesterday'],
        ],
        [
            'id' => 910007,
            'title' => 'Still Waters',
            'slug' => 'still-waters',
            'artist' => 'Harbor Lights',
            'genre' => 'Chill',
            'cover' => 'still-waters',
            'tracks' => ['falling-into-place'],
        ],
        [
            'id' => 910008,
            'title' => 'Better Days',
            'slug' => 'better-days',
            'artist' => 'Sunset Kids',
            'genre' => 'Chill',
            'cover' => 'better-days',
            'tracks' => ['better-days'],
        ],
    ];

    $albums = [];

    foreach ($albumDefinitions as $album) {
        $albumTracks = [];

        foreach ($album['tracks'] as $slug) {
            if (isset($bySlug[$slug])) {
                $albumTracks[] = $bySlug[$slug];
            }
        }

        $albums[] = [
            'id' => (int)$album['id'],
            'title' => (string)$album['title'],
            'slug' => (string)$album['slug'],
            'artist' => (string)$album['artist'],
            'type' => 'album',
            'genre' => (string)$album['genre'],
            'release_year' => 2026,
            'description' => 'Demo release for the North Mountain Media streaming interface.',
            'featured' => true,
            'cover_url' => app_url(
                'assets/demo-music/covers/'
                . rawurlencode((string)$album['cover'])
                . '.svg'
            ),
            'tracks' => $albumTracks,
            'demo' => true,
        ];
    }

    $playlistDefinitions = [
        [
            'id' => 920001,
            'title' => 'Focus Flow',
            'slug' => 'focus-flow',
            'description' => 'Deep beats and calm textures for focused creative work.',
            'cover' => 'golden-horizon',
            'tracks' => [
                'golden-hour',
                'take-it-slow',
                'falling-into-place',
                'stargazer',
            ],
        ],
        [
            'id' => 920002,
            'title' => 'Deep Work',
            'slug' => 'deep-work',
            'description' => 'A steady sequence for long-form concentration.',
            'cover' => 'moments',
            'tracks' => [
                'midnight-drive',
                'close-to-you',
                'stargazer',
            ],
        ],
        [
            'id' => 920003,
            'title' => 'Chill Vibes',
            'slug' => 'chill-vibes',
            'description' => 'Easy-going demos with soft ambient movement.',
            'cover' => 'still-waters',
            'tracks' => [
                'falling-into-place',
                'better-days',
                'take-it-slow',
            ],
        ],
        [
            'id' => 920004,
            'title' => 'Morning Boost',
            'slug' => 'morning-boost',
            'description' => 'Bright electronic and indie demos for a new start.',
            'cover' => 'electric-pulse',
            'tracks' => [
                'electric-pulse',
                'on-my-way',
                'better-than-yesterday',
            ],
        ],
    ];

    $playlists = [];

    foreach ($playlistDefinitions as $playlist) {
        $playlistTracks = [];

        foreach ($playlist['tracks'] as $slug) {
            if (isset($bySlug[$slug])) {
                $playlistTracks[] = $bySlug[$slug];
            }
        }

        $playlists[] = [
            'id' => (int)$playlist['id'],
            'title' => (string)$playlist['title'],
            'slug' => (string)$playlist['slug'],
            'description' => (string)$playlist['description'],
            'featured' => true,
            'cover_url' => app_url(
                'assets/demo-music/covers/'
                . rawurlencode((string)$playlist['cover'])
                . '.svg'
            ),
            'tracks' => $playlistTracks,
            'demo' => true,
        ];
    }

    return [
        'tracks' => $tracks,
        'albums' => $albums,
        'playlists' => $playlists,
        'demo' => true,
    ];
}

function music_public_catalog(): array
{
    if (music_demo_mode_enabled()) {
        return music_demo_catalog();
    }

    if (!music_library_schema_available()) {
        return [
            'tracks' => [],
            'albums' => [],
            'playlists' => [],
            'demo' => false,
        ];
    }

    $catalog = music_library_public_payload();
    $catalog['demo'] = false;

    return $catalog;
}

function music_demo_featured_banner(): ?array
{
    $settings = music_demo_mode_settings();

    if (
        !$settings['enabled']
        || !$settings['banner_enabled']
    ) {
        return null;
    }

    $catalog = music_demo_catalog();
    $playlist = $catalog['playlists'][0] ?? null;

    if (!$playlist) {
        return null;
    }

    return [
        'image_url' => app_url(
            'assets/demo-music/featured-banner.svg'
        ),
        'eyebrow' => 'Featured playlist',
        'title' => 'Focus Flow',
        'subtitle' => 'Deep beats and calm vibes to help you focus, create, and do your best work.',
        'alt_text' => 'Mountain landscape for the Focus Flow featured playlist',
        'link_url' => '',
        'collection_type' => 'playlist',
        'collection_id' => (int)$playlist['id'],
        'collection_slug' => (string)$playlist['slug'],
        'tracks' => $playlist['tracks'],
        'demo' => true,
    ];
}

function music_banner_storage_directory(): string
{
    $directory = NMM_ROOT . '/storage/music-banners';

    if (
        !is_dir($directory)
        && !mkdir($directory, 0750, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'The music banner storage directory could not be created.'
        );
    }

    return $directory;
}

function music_banner_limit_bytes(): int
{
    $app = nmm_config('app');

    return max(
        4 * 1024 * 1024,
        (int)($app['max_music_banner_bytes'] ?? 12 * 1024 * 1024)
    );
}

function music_store_banner(
    array $upload,
    ?string $existingStoredName = null
): array {
    if (
        (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
        === UPLOAD_ERR_NO_FILE
    ) {
        return [
            'stored_name' => $existingStoredName,
            'mime_type' => null,
            'size_bytes' => null,
            'sha256' => null,
            'changed' => false,
        ];
    }

    if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'The music banner upload did not complete.'
        );
    }

    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    $maximum = music_banner_limit_bytes();

    if (
        $temporary === ''
        || !is_uploaded_file($temporary)
        || $size <= 0
        || $size > $maximum
    ) {
        throw new RuntimeException(
            'Music banners must be valid uploads no larger than '
            . format_bytes($maximum)
            . '.'
        );
    }

    $image = @getimagesize($temporary);

    if (!is_array($image)) {
        throw new RuntimeException(
            'The uploaded music banner is not a valid image.'
        );
    }

    $width = (int)($image[0] ?? 0);
    $height = (int)($image[1] ?? 0);

    if ($width < 900 || $height < 240) {
        throw new RuntimeException(
            'Music banners must be at least 900 × 240 pixels.'
        );
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))
        ->file($temporary)
        ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException(
            'Music banners must be JPG, PNG, or WebP.'
        );
    }

    $storedName = 'music-banner-'
        . bin2hex(random_bytes(20))
        . '.'
        . $extensions[$mime];
    $destination = music_banner_storage_directory()
        . '/'
        . $storedName;

    if (!move_uploaded_file($temporary, $destination)) {
        throw new RuntimeException(
            'The music banner could not be stored.'
        );
    }

    chmod($destination, 0640);
    $sha256 = hash_file('sha256', $destination);

    if ($sha256 === false) {
        @unlink($destination);
        throw new RuntimeException(
            'The music banner could not be verified.'
        );
    }

    if (
        $existingStoredName
        && basename($existingStoredName) !== $storedName
    ) {
        @unlink(
            music_banner_storage_directory()
            . '/'
            . basename($existingStoredName)
        );
    }

    return [
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'size_bytes' => $size,
        'sha256' => $sha256,
        'changed' => true,
    ];
}

function music_banner_settings(): array
{
    return [
        'enabled' => setting('music_banner_enabled', '0') === '1',
        'stored_name' => trim(
            (string)setting('music_banner_stored_name', '')
        ),
        'mime_type' => trim(
            (string)setting('music_banner_mime_type', '')
        ),
        'size_bytes' => max(
            0,
            (int)setting('music_banner_size_bytes', '0')
        ),
        'sha256' => trim(
            (string)setting('music_banner_sha256', '')
        ),
        'eyebrow' => trim(
            (string)setting('music_banner_eyebrow', '')
        ),
        'title' => trim(
            (string)setting('music_banner_title', '')
        ),
        'subtitle' => trim(
            (string)setting('music_banner_subtitle', '')
        ),
        'alt_text' => trim(
            (string)setting('music_banner_alt_text', '')
        ),
        'link_url' => trim(
            (string)setting('music_banner_link_url', '')
        ),
    ];
}

function music_banner_image_exists(array $banner): bool
{
    $storedName = basename(
        (string)($banner['stored_name'] ?? '')
    );

    return (
        $storedName !== ''
        && is_file(
            music_banner_storage_directory()
            . '/'
            . $storedName
        )
    );
}

function music_public_banner(): ?array
{
    $banner = music_banner_settings();

    if (
        !$banner['enabled']
        || !music_banner_image_exists($banner)
    ) {
        return music_demo_featured_banner();
    }

    $linkUrl = (string)$banner['link_url'];

    if (
        $linkUrl !== ''
        && !str_starts_with($linkUrl, '/')
        && !preg_match('#^https?://#i', $linkUrl)
    ) {
        $linkUrl = '';
    }

    return [
        'image_url' => app_url('music-banner.php'),
        'eyebrow' => (string)$banner['eyebrow'],
        'title' => (string)$banner['title'],
        'subtitle' => (string)$banner['subtitle'],
        'alt_text' => (
            (string)$banner['alt_text'] !== ''
                ? (string)$banner['alt_text']
                : (
                    (string)$banner['title'] !== ''
                        ? (string)$banner['title']
                        : 'Music banner'
                )
        ),
        'link_url' => $linkUrl,
    ];
}

function music_save_settings(array $pairs): void
{
    $statement = db()->prepare(
        'INSERT INTO settings
            (setting_key,setting_value)
         VALUES
            (:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE
            setting_value=VALUES(setting_value)'
    );

    foreach ($pairs as $key => $value) {
        $statement->execute([
            'setting_key' => (string)$key,
            'setting_value' => $value !== null
                ? (string)$value
                : '',
        ]);
    }
}

function music_cover_storage_directory(): string
{
    $directory = NMM_ROOT . '/storage/music-covers';

    if (
        !is_dir($directory)
        && !mkdir($directory, 0750, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'The music cover storage directory could not be created.'
        );
    }

    return $directory;
}

function music_cover_limit_bytes(): int
{
    $app = nmm_config('app');

    return max(
        2 * 1024 * 1024,
        (int)($app['max_music_cover_bytes'] ?? 8 * 1024 * 1024)
    );
}

function music_duration_label(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '—';
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;

    return $hours > 0
        ? sprintf('%d:%02d:%02d', $hours, $minutes, $remaining)
        : sprintf('%d:%02d', $minutes, $remaining);
}

function music_store_cover(
    array $upload,
    ?string $existingStoredName = null
): array {
    if (
        (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
        === UPLOAD_ERR_NO_FILE
    ) {
        return [
            'stored_name' => $existingStoredName,
            'extension' => null,
            'mime_type' => null,
            'size_bytes' => null,
            'sha256' => null,
            'changed' => false,
        ];
    }

    if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'The music cover upload did not complete.'
        );
    }

    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    $maximum = music_cover_limit_bytes();

    if (
        $temporary === ''
        || !is_uploaded_file($temporary)
        || $size <= 0
        || $size > $maximum
    ) {
        throw new RuntimeException(
            'Music cover images must be valid uploads no larger than '
            . format_bytes($maximum)
            . '.'
        );
    }

    $image = @getimagesize($temporary);

    if (!is_array($image)) {
        throw new RuntimeException(
            'The uploaded music cover is not a valid image.'
        );
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))
        ->file($temporary)
        ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException(
            'Music cover images must be JPG, PNG, or WebP.'
        );
    }

    $storedName = 'music-cover-'
        . bin2hex(random_bytes(20))
        . '.'
        . $extensions[$mime];
    $destination = music_cover_storage_directory()
        . '/'
        . $storedName;

    if (!move_uploaded_file($temporary, $destination)) {
        throw new RuntimeException(
            'The music cover could not be stored.'
        );
    }

    chmod($destination, 0640);
    $sha256 = hash_file('sha256', $destination);

    if ($sha256 === false) {
        @unlink($destination);
        throw new RuntimeException(
            'The music cover could not be verified.'
        );
    }

    if (
        $existingStoredName
        && basename($existingStoredName) !== $storedName
    ) {
        @unlink(
            music_cover_storage_directory()
            . '/'
            . basename($existingStoredName)
        );
    }

    return [
        'stored_name' => $storedName,
        'extension' => $extensions[$mime],
        'mime_type' => $mime,
        'size_bytes' => $size,
        'sha256' => $sha256,
        'changed' => true,
    ];
}

function music_audio_assets(bool $unlinkedOnly = false): array
{
    $sql = 'SELECT asset.*
            FROM knowledge_assets asset';

    if (music_library_schema_available()) {
        $sql .= ' LEFT JOIN music_tracks track
                    ON track.knowledge_asset_id=asset.id';
    }

    $sql .= ' WHERE asset.media_kind="audio"';

    if ($unlinkedOnly && music_library_schema_available()) {
        $sql .= ' AND track.id IS NULL';
    }

    $sql .= ' ORDER BY asset.updated_at DESC,asset.id DESC';

    try {
        return db()->query($sql)->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function music_admin_albums(): array
{
    if (!music_library_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT album.*,
                COUNT(track.id) AS track_count,
                COALESCE(SUM(track.duration_seconds),0) AS total_seconds
         FROM music_albums album
         LEFT JOIN music_tracks track
           ON track.album_id=album.id
          AND track.status<>"archived"
         GROUP BY album.id
         ORDER BY
            CASE album.status
                WHEN "active" THEN 0
                WHEN "draft" THEN 1
                ELSE 2
            END,
            album.featured DESC,
            album.sort_order ASC,
            album.updated_at DESC'
    )->fetchAll();
}

function music_admin_album(int $albumId): ?array
{
    if (!music_library_schema_available() || $albumId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM music_albums
         WHERE id=:album_id
         LIMIT 1'
    );
    $statement->execute(['album_id' => $albumId]);
    $album = $statement->fetch();

    return $album ?: null;
}

function music_admin_tracks(): array
{
    if (!music_library_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT track.*,
                asset.original_name,
                asset.mime_type,
                asset.size_bytes,
                asset.status AS asset_status,
                asset.is_public AS asset_is_public,
                asset.cover_stored_name AS asset_cover_stored_name,
                album.title AS album_title
         FROM music_tracks track
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
         LEFT JOIN music_albums album
           ON album.id=track.album_id
         ORDER BY
            CASE track.status
                WHEN "active" THEN 0
                WHEN "draft" THEN 1
                ELSE 2
            END,
            track.featured DESC,
            COALESCE(album.sort_order,9999),
            track.disc_number,
            COALESCE(track.track_number,9999),
            track.sort_order,
            track.title'
    )->fetchAll();
}

function music_admin_track(int $trackId): ?array
{
    if (!music_library_schema_available() || $trackId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT track.*,
                asset.original_name,
                asset.mime_type,
                asset.size_bytes,
                asset.status AS asset_status,
                asset.is_public AS asset_is_public,
                asset.cover_stored_name AS asset_cover_stored_name,
                asset.cover_mime_type AS asset_cover_mime_type,
                album.title AS album_title
         FROM music_tracks track
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
         LEFT JOIN music_albums album
           ON album.id=track.album_id
         WHERE track.id=:track_id
         LIMIT 1'
    );
    $statement->execute(['track_id' => $trackId]);
    $track = $statement->fetch();

    return $track ?: null;
}

function music_admin_playlists(): array
{
    if (!music_library_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT playlist.*,
                COUNT(item.track_id) AS track_count,
                COALESCE(SUM(track.duration_seconds),0) AS total_seconds
         FROM music_playlists playlist
         LEFT JOIN music_playlist_tracks item
           ON item.playlist_id=playlist.id
         LEFT JOIN music_tracks track
           ON track.id=item.track_id
          AND track.status<>"archived"
         GROUP BY playlist.id
         ORDER BY
            CASE playlist.status
                WHEN "active" THEN 0
                WHEN "draft" THEN 1
                ELSE 2
            END,
            playlist.featured DESC,
            playlist.sort_order ASC,
            playlist.updated_at DESC'
    )->fetchAll();
}

function music_admin_playlist(int $playlistId): ?array
{
    if (!music_library_schema_available() || $playlistId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM music_playlists
         WHERE id=:playlist_id
         LIMIT 1'
    );
    $statement->execute(['playlist_id' => $playlistId]);
    $playlist = $statement->fetch();

    if (!$playlist) {
        return null;
    }

    $tracks = db()->prepare(
        'SELECT item.position,
                track.*,
                asset.original_name,
                album.title AS album_title
         FROM music_playlist_tracks item
         JOIN music_tracks track
           ON track.id=item.track_id
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
         LEFT JOIN music_albums album
           ON album.id=track.album_id
         WHERE item.playlist_id=:playlist_id
         ORDER BY item.position ASC,item.track_id ASC'
    );
    $tracks->execute(['playlist_id' => $playlistId]);
    $playlist['tracks'] = $tracks->fetchAll();

    return $playlist;
}

function music_detect_duration_seconds(
    array $asset
): ?int {
    $storedName = basename(
        (string)($asset['stored_name'] ?? '')
    );

    if ($storedName === '') {
        return null;
    }

    $path = knowledge_storage_path($storedName);

    if (
        !is_file($path)
        || !is_readable($path)
        || !function_exists('exec')
    ) {
        return null;
    }

    $ffprobe = null;

    if (function_exists('transcription_resolve_binary')) {
        $ffprobe = transcription_resolve_binary(
            'ffprobe'
        );
    }

    if ($ffprobe === null) {
        $output = [];
        $code = 1;
        exec(
            'command -v ffprobe 2>/dev/null',
            $output,
            $code
        );

        if ($code === 0 && !empty($output[0])) {
            $ffprobe = trim((string)$output[0]);
        }
    }

    if (!$ffprobe) {
        return null;
    }

    $output = [];
    $code = 1;
    $command = escapeshellarg($ffprobe)
        . ' -v error'
        . ' -show_entries format=duration'
        . ' -of default=noprint_wrappers=1:nokey=1 '
        . escapeshellarg($path)
        . ' 2>/dev/null';

    exec($command, $output, $code);

    if ($code !== 0 || empty($output[0])) {
        return null;
    }

    $duration = (float)trim((string)$output[0]);

    if (
        !is_finite($duration)
        || $duration <= 0
        || $duration > 24 * 60 * 60
    ) {
        return null;
    }

    return max(1, (int)round($duration));
}

function music_detach_asset_from_chat(
    int $assetId
): void {
    if ($assetId <= 0) {
        return;
    }

    try {
        $statement = db()->prepare(
            'SELECT entry_id
             FROM knowledge_assets
             WHERE id=:asset_id
             LIMIT 1'
        );
        $statement->execute([
            'asset_id' => $assetId,
        ]);
        $entryId = $statement->fetchColumn();

        if (
            $entryId !== false
            && trim((string)$entryId) !== ''
        ) {
            knowledge_remove_published_entry(
                (string)$entryId
            );
        }

        db()->prepare(
            'UPDATE knowledge_assets
             SET entry_id=NULL
             WHERE id=:asset_id'
        )->execute([
            'asset_id' => $assetId,
        ]);
    } catch (Throwable $exception) {
        error_log(
            'North Mountain Media could not detach music asset '
            . $assetId
            . ' from chat knowledge: '
            . $exception->getMessage()
        );
    }
}

function music_adopt_asset(
    int $assetId,
    int $userId
): int {
    if (!music_library_schema_available()) {
        throw new RuntimeException(
            'Import database/music_library_v44.sql before adopting audio assets.'
        );
    }

    $statement = db()->prepare(
        'SELECT *
         FROM knowledge_assets
         WHERE id=:asset_id
           AND media_kind="audio"
         LIMIT 1'
    );
    $statement->execute(['asset_id' => $assetId]);
    $asset = $statement->fetch();

    if (!$asset) {
        throw new RuntimeException(
            'The selected audio asset was not found.'
        );
    }

    $existing = db()->prepare(
        'SELECT id
         FROM music_tracks
         WHERE knowledge_asset_id=:asset_id
         LIMIT 1'
    );
    $existing->execute(['asset_id' => $assetId]);
    $existingId = (int)($existing->fetchColumn() ?: 0);

    if ($existingId > 0) {
        return $existingId;
    }

    $title = trim((string)$asset['title']);

    if ($title === '') {
        $title = trim(
            pathinfo(
                (string)$asset['original_name'],
                PATHINFO_FILENAME
            )
        );
    }

    $baseSlug = slugify($title) ?: 'track-' . $assetId;
    $slug = $baseSlug;
    $suffix = 2;

    while (true) {
        $duplicate = db()->prepare(
            'SELECT id
             FROM music_tracks
             WHERE slug=:slug
             LIMIT 1'
        );
        $duplicate->execute(['slug' => $slug]);

        if (!$duplicate->fetchColumn()) {
            break;
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }

    $status = (
        $asset['status'] === 'published'
        && (int)$asset['is_public'] === 1
    ) ? 'active' : 'draft';
    $duration = music_detect_duration_seconds(
        $asset
    );

    $insert = db()->prepare(
        'INSERT INTO music_tracks
            (knowledge_asset_id,title,slug,artist_name,status,
             duration_seconds,description,created_by,
             updated_by,published_at)
         VALUES
            (:asset_id,:title,:slug,:artist_name,:status,
             :duration_seconds,:description,:created_by,
             :updated_by,
             CASE WHEN :publish_status="active"
                  THEN UTC_TIMESTAMP() ELSE NULL END)'
    );
    $insert->execute([
        'asset_id' => $assetId,
        'title' => $title,
        'slug' => $slug,
        'artist_name' => 'David Evans',
        'status' => $status,
        'duration_seconds' => $duration,
        'description' => (
            trim((string)$asset['summary']) !== ''
                ? trim((string)$asset['summary'])
                : null
        ),
        'created_by' => $userId,
        'updated_by' => $userId,
        'publish_status' => $status,
    ]);

    $trackId = (int)db()->lastInsertId();

    music_detach_asset_from_chat($assetId);

    log_activity(
        'music_track_adopted',
        'music_track',
        $trackId,
        [
            'knowledge_asset_id' => $assetId,
            'status' => $status,
        ]
    );

    return $trackId;
}

function music_cover_url(
    string $type,
    int $id
): string {
    return app_url(
        'music-cover.php?type='
        . rawurlencode($type)
        . '&id='
        . $id
    );
}

function music_track_stream_url(int $trackId): string
{
    return app_url('music-media.php?id=' . $trackId);
}

function music_track_download_url(int $trackId): string
{
    return app_url(
        'music-media.php?id='
        . $trackId
        . '&download=1'
    );
}

function music_public_tracks(): array
{
    if (!music_library_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT track.*,
                asset.original_name,
                asset.mime_type,
                asset.size_bytes,
                asset.cover_stored_name AS asset_cover_stored_name,
                album.title AS album_title,
                album.slug AS album_slug,
                album.artist_name AS album_artist_name,
                album.cover_stored_name AS album_cover_stored_name
         FROM music_tracks track
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
          AND asset.media_kind="audio"
          AND asset.status="published"
          AND asset.is_public=1
         LEFT JOIN music_albums album
           ON album.id=track.album_id
         WHERE track.status="active"
           AND (
                track.published_at IS NULL
                OR track.published_at<=UTC_TIMESTAMP()
           )
         ORDER BY
            track.featured DESC,
            COALESCE(album.sort_order,9999),
            track.disc_number,
            COALESCE(track.track_number,9999),
            track.sort_order,
            track.title'
    )->fetchAll();
}

function music_public_albums(): array
{
    if (!music_library_schema_available()) {
        return [];
    }

    $albums = db()->query(
        'SELECT album.*
         FROM music_albums album
         WHERE album.status="active"
           AND (
                album.published_at IS NULL
                OR album.published_at<=UTC_TIMESTAMP()
           )
         ORDER BY
            album.featured DESC,
            album.sort_order ASC,
            COALESCE(album.release_date,"1000-01-01") DESC,
            album.title'
    )->fetchAll();
    $output = [];

    foreach ($albums as $album) {
        $statement = db()->prepare(
            'SELECT track.*,
                    asset.original_name,
                    asset.mime_type,
                    asset.size_bytes,
                    asset.cover_stored_name AS asset_cover_stored_name,
                    :album_title AS album_title,
                    :album_slug AS album_slug
             FROM music_tracks track
             JOIN knowledge_assets asset
               ON asset.id=track.knowledge_asset_id
              AND asset.media_kind="audio"
              AND asset.status="published"
              AND asset.is_public=1
             WHERE track.album_id=:album_id
               AND track.status="active"
             ORDER BY
                track.disc_number,
                COALESCE(track.track_number,9999),
                track.sort_order,
                track.title'
        );
        $statement->execute([
            'album_id' => (int)$album['id'],
            'album_title' => (string)$album['title'],
            'album_slug' => (string)$album['slug'],
        ]);
        $tracks = $statement->fetchAll();

        if (!$tracks) {
            continue;
        }

        $album['tracks'] = $tracks;
        $output[] = $album;
    }

    return $output;
}

function music_public_playlists(): array
{
    if (!music_library_schema_available()) {
        return [];
    }

    $playlists = db()->query(
        'SELECT playlist.*
         FROM music_playlists playlist
         WHERE playlist.status="active"
           AND (
                playlist.published_at IS NULL
                OR playlist.published_at<=UTC_TIMESTAMP()
           )
         ORDER BY
            playlist.featured DESC,
            playlist.sort_order ASC,
            playlist.title'
    )->fetchAll();
    $output = [];

    foreach ($playlists as $playlist) {
        $statement = db()->prepare(
            'SELECT track.*,
                    item.position,
                    asset.original_name,
                    asset.mime_type,
                    asset.size_bytes,
                    asset.cover_stored_name AS asset_cover_stored_name,
                    album.title AS album_title,
                    album.slug AS album_slug,
                    album.cover_stored_name AS album_cover_stored_name
             FROM music_playlist_tracks item
             JOIN music_tracks track
               ON track.id=item.track_id
              AND track.status="active"
             JOIN knowledge_assets asset
               ON asset.id=track.knowledge_asset_id
              AND asset.media_kind="audio"
              AND asset.status="published"
              AND asset.is_public=1
             LEFT JOIN music_albums album
               ON album.id=track.album_id
             WHERE item.playlist_id=:playlist_id
             ORDER BY item.position ASC,item.track_id ASC'
        );
        $statement->execute([
            'playlist_id' => (int)$playlist['id'],
        ]);
        $tracks = $statement->fetchAll();

        if (!$tracks) {
            continue;
        }

        $playlist['tracks'] = $tracks;
        $output[] = $playlist;
    }

    return $output;
}

function music_public_album_by_slug(
    string $slug
): ?array {
    $slug = slugify($slug);

    if ($slug === '') {
        return null;
    }

    foreach (music_public_albums() as $album) {
        if ((string)$album['slug'] !== $slug) {
            continue;
        }

        $album['tracks'] = array_map(
            'music_track_payload',
            $album['tracks']
        );
        $album['cover_url'] = music_cover_url(
            'album',
            (int)$album['id']
        );
        $album['collection_type'] = 'album';
        $album['track_count'] = count($album['tracks']);
        $album['total_seconds'] = array_sum(
            array_map(
                static fn(array $track): int =>
                    (int)($track['duration_seconds'] ?? 0),
                $album['tracks']
            )
        );

        return $album;
    }

    return null;
}

function music_public_playlist_by_slug(
    string $slug
): ?array {
    $slug = slugify($slug);

    if ($slug === '') {
        return null;
    }

    foreach (music_public_playlists() as $playlist) {
        if ((string)$playlist['slug'] !== $slug) {
            continue;
        }

        $playlist['tracks'] = array_map(
            'music_track_payload',
            $playlist['tracks']
        );
        $playlist['cover_url'] = music_cover_url(
            'playlist',
            (int)$playlist['id']
        );
        $playlist['collection_type'] = 'playlist';
        $playlist['artist_name'] = 'North Mountain Media';
        $playlist['track_count'] = count(
            $playlist['tracks']
        );
        $playlist['total_seconds'] = array_sum(
            array_map(
                static fn(array $track): int =>
                    (int)($track['duration_seconds'] ?? 0),
                $playlist['tracks']
            )
        );

        return $playlist;
    }

    return null;
}

function music_public_collection(
    string $type,
    string $slug
): ?array {
    $type = $type === 'playlist'
        ? 'playlist'
        : 'album';
    $slug = slugify($slug);

    if (music_demo_mode_enabled()) {
        $catalog = music_demo_catalog();
        $collections = $type === 'playlist'
            ? $catalog['playlists']
            : $catalog['albums'];

        foreach ($collections as $collection) {
            if ((string)$collection['slug'] !== $slug) {
                continue;
            }

            $collection['collection_type'] = $type;
            $collection['artist_name'] = (
                $type === 'playlist'
                    ? 'North Mountain Media'
                    : (string)$collection['artist']
            );
            $collection['album_type'] = (
                $type === 'playlist'
                    ? 'playlist'
                    : (string)$collection['type']
            );
            $collection['track_count'] = count(
                $collection['tracks']
            );
            $collection['total_seconds'] = array_sum(
                array_map(
                    static fn(array $track): int =>
                        (int)($track['duration_seconds'] ?? 0),
                    $collection['tracks']
                )
            );

            return $collection;
        }

        return null;
    }

    return $type === 'playlist'
        ? music_public_playlist_by_slug($slug)
        : music_public_album_by_slug($slug);
}

function music_collection_public_url(
    string $type,
    string $slug
): string {
    return app_url(
        'music-collection.php?type='
        . rawurlencode(
            $type === 'playlist'
                ? 'playlist'
                : 'album'
        )
        . '&slug='
        . rawurlencode($slug)
    );
}

function music_track_payload(array $track): array
{
    $trackId = (int)$track['id'];
    $coverType = 'track';

    return [
        'id' => $trackId,
        'title' => (string)$track['title'],
        'slug' => (string)$track['slug'],
        'artist' => trim(
            (string)$track['artist_name']
            . (
                trim((string)($track['featured_artist'] ?? '')) !== ''
                    ? ' feat. ' . trim((string)$track['featured_artist'])
                    : ''
            )
        ),
        'album' => trim((string)($track['album_title'] ?? '')),
        'album_slug' => trim((string)($track['album_slug'] ?? '')),
        'genre' => trim((string)($track['genre'] ?? '')),
        'release_year' => (int)($track['release_year'] ?? 0),
        'play_count' => (int)($track['play_count'] ?? 0),
        'published_at' => trim((string)($track['published_at'] ?? '')),
        'created_at' => trim((string)($track['created_at'] ?? '')),
        'duration_seconds' => (
            $track['duration_seconds'] !== null
                ? (int)$track['duration_seconds']
                : null
        ),
        'duration_label' => music_duration_label(
            $track['duration_seconds'] !== null
                ? (int)$track['duration_seconds']
                : null
        ),
        'description' => trim((string)($track['description'] ?? '')),
        'explicit' => (int)$track['is_explicit'] === 1,
        'downloadable' => (int)$track['is_downloadable'] === 1,
        'featured' => (int)$track['featured'] === 1,
        'stream_url' => music_track_stream_url($trackId),
        'download_url' => (
            (int)$track['is_downloadable'] === 1
                ? music_track_download_url($trackId)
                : ''
        ),
        'cover_url' => music_cover_url($coverType, $trackId),
        'mime_type' => (string)$track['mime_type'],
    ];
}

function music_library_public_payload(): array
{
    $tracks = array_map(
        'music_track_payload',
        music_public_tracks()
    );
    $trackMap = [];

    foreach ($tracks as $track) {
        $trackMap[$track['id']] = $track;
    }

    $albums = [];

    foreach (music_public_albums() as $album) {
        $albumTracks = [];

        foreach ($album['tracks'] as $track) {
            $payload = music_track_payload($track);
            $trackMap[$payload['id']] = $payload;
            $albumTracks[] = $payload;
        }

        $albums[] = [
            'id' => (int)$album['id'],
            'title' => (string)$album['title'],
            'slug' => (string)$album['slug'],
            'artist' => (string)$album['artist_name'],
            'type' => (string)$album['album_type'],
            'genre' => trim((string)($album['genre'] ?? '')),
            'release_year' => (int)($album['release_year'] ?? 0),
            'description' => trim((string)($album['description'] ?? '')),
            'featured' => (int)$album['featured'] === 1,
            'cover_url' => music_cover_url(
                'album',
                (int)$album['id']
            ),
            'tracks' => $albumTracks,
        ];
    }

    $playlists = [];

    foreach (music_public_playlists() as $playlist) {
        $playlistTracks = [];

        foreach ($playlist['tracks'] as $track) {
            $payload = music_track_payload($track);
            $trackMap[$payload['id']] = $payload;
            $playlistTracks[] = $payload;
        }

        $playlists[] = [
            'id' => (int)$playlist['id'],
            'title' => (string)$playlist['title'],
            'slug' => (string)$playlist['slug'],
            'description' => trim(
                (string)($playlist['description'] ?? '')
            ),
            'featured' => (int)$playlist['featured'] === 1,
            'cover_url' => music_cover_url(
                'playlist',
                (int)$playlist['id']
            ),
            'tracks' => $playlistTracks,
        ];
    }

    return [
        'tracks' => array_values($trackMap),
        'albums' => $albums,
        'playlists' => $playlists,
    ];
}

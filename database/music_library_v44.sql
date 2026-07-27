SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS music_albums (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    artist_name VARCHAR(190) NOT NULL DEFAULT 'David Evans',
    album_type ENUM('album','ep','single','compilation') NOT NULL DEFAULT 'album',
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    release_date DATE NULL,
    release_year SMALLINT UNSIGNED NULL,
    genre VARCHAR(120) NULL,
    description TEXT NULL,
    cover_stored_name VARCHAR(255) NULL,
    cover_extension VARCHAR(20) NULL,
    cover_mime_type VARCHAR(120) NULL,
    cover_size_bytes BIGINT UNSIGNED NULL,
    cover_sha256 CHAR(64) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_music_albums_slug (slug),
    KEY idx_music_albums_public (status,featured,sort_order,published_at),
    CONSTRAINT fk_music_albums_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_music_albums_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music_tracks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    knowledge_asset_id BIGINT UNSIGNED NOT NULL,
    album_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    artist_name VARCHAR(190) NOT NULL DEFAULT 'David Evans',
    featured_artist VARCHAR(190) NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    disc_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    track_number SMALLINT UNSIGNED NULL,
    genre VARCHAR(120) NULL,
    release_year SMALLINT UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    description TEXT NULL,
    lyrics MEDIUMTEXT NULL,
    is_explicit TINYINT(1) NOT NULL DEFAULT 0,
    is_downloadable TINYINT(1) NOT NULL DEFAULT 0,
    play_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_music_tracks_asset (knowledge_asset_id),
    UNIQUE KEY uq_music_tracks_slug (slug),
    KEY idx_music_tracks_public (status,featured,sort_order,published_at),
    KEY idx_music_tracks_album (album_id,disc_number,track_number,sort_order),
    KEY idx_music_tracks_artist (artist_name,title),
    CONSTRAINT fk_music_tracks_asset
        FOREIGN KEY (knowledge_asset_id)
        REFERENCES knowledge_assets(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_music_tracks_album
        FOREIGN KEY (album_id)
        REFERENCES music_albums(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_music_tracks_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_music_tracks_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music_playlists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    cover_stored_name VARCHAR(255) NULL,
    cover_extension VARCHAR(20) NULL,
    cover_mime_type VARCHAR(120) NULL,
    cover_size_bytes BIGINT UNSIGNED NULL,
    cover_sha256 CHAR(64) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_music_playlists_slug (slug),
    KEY idx_music_playlists_public (status,featured,sort_order,published_at),
    CONSTRAINT fk_music_playlists_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_music_playlists_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music_playlist_tracks (
    playlist_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    added_by BIGINT UNSIGNED NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (playlist_id,track_id),
    KEY idx_music_playlist_tracks_order (playlist_id,position,track_id),
    KEY idx_music_playlist_tracks_track (track_id),
    CONSTRAINT fk_music_playlist_tracks_playlist
        FOREIGN KEY (playlist_id)
        REFERENCES music_playlists(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_music_playlist_tracks_track
        FOREIGN KEY (track_id)
        REFERENCES music_tracks(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_music_playlist_tracks_added_by
        FOREIGN KEY (added_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

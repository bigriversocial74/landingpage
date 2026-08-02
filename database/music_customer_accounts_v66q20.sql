SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','client','customer') NOT NULL;

CREATE TABLE IF NOT EXISTS music_customer_playlists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    description VARCHAR(1000) NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_music_customer_playlist_slug (customer_user_id,slug),
    KEY idx_music_customer_playlist_owner (customer_user_id,status,updated_at),
    CONSTRAINT fk_music_customer_playlist_user
        FOREIGN KEY (customer_user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music_customer_playlist_tracks (
    playlist_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    added_by BIGINT UNSIGNED NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (playlist_id,track_id),
    KEY idx_music_customer_playlist_order (playlist_id,position,track_id),
    KEY idx_music_customer_playlist_track (track_id),
    CONSTRAINT fk_music_customer_playlist_track_playlist
        FOREIGN KEY (playlist_id)
        REFERENCES music_customer_playlists(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_music_customer_playlist_track_track
        FOREIGN KEY (track_id)
        REFERENCES music_tracks(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_music_customer_playlist_track_user
        FOREIGN KEY (added_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key,setting_value)
SELECT 'music_customer_accounts_enabled','0'
WHERE NOT EXISTS (
    SELECT 1 FROM settings
    WHERE setting_key='music_customer_accounts_enabled'
);

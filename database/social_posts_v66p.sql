-- Section 66P — POD Social Post Publisher
-- Additive, repeat-safe schema for permanent local ActivityPub Notes,
-- landing-page presentation settings, edit/delete evidence, and delivery state.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pod_social_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    body_text TEXT NULL,
    body_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    media_kind ENUM('none','image','audio','video','link') NOT NULL DEFAULT 'none',
    media_url VARCHAR(2048) NULL,
    media_alt VARCHAR(500) NULL,
    link_url VARCHAR(2048) NULL,
    visibility ENUM('public','followers') NOT NULL DEFAULT 'public',
    status ENUM('draft','published','deleted') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    edited_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_social_posts_uuid (post_uuid),
    KEY idx_pod_social_posts_public (status,visibility,published_at,id),
    KEY idx_pod_social_posts_owner (owner_user_id,status,updated_at,id),
    CONSTRAINT fk_pod_social_posts_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_social_post_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    social_post_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_social_post_events_hash (event_sha256),
    KEY idx_pod_social_post_events_post (social_post_id,created_at,id),
    KEY idx_pod_social_post_events_type (event_type,created_at,id),
    CONSTRAINT fk_pod_social_post_events_post
        FOREIGN KEY (social_post_id) REFERENCES pod_social_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pod_social_post_events_user
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
    ('social_posts_enabled','1'),
    ('social_posts_default_visibility','public'),
    ('social_posts_allow_public','1'),
    ('social_posts_landing_mode','tabs'),
    ('social_posts_landing_limit','6'),
    ('social_posts_show_follow_button','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

SELECT 'POD Social Post Publisher v66P migration complete' AS migration_status;

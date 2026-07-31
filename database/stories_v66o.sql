-- Section 66O — Stories for Followed Feeds
-- Additive, repeat-safe schema for local follower stories, remote followed stories,
-- view receipts, moderation evidence, and bounded expiry processing.

CREATE TABLE IF NOT EXISTS pod_stories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    story_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    direction ENUM('local','remote') NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    remote_actor_id BIGINT UNSIGNED NULL,
    source_activity_uri VARCHAR(2048) NULL,
    source_object_uri VARCHAR(2048) NULL,
    source_object_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
    title VARCHAR(200) NULL,
    body_text TEXT NULL,
    body_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    media_kind ENUM('none','image','audio','video','link') NOT NULL DEFAULT 'none',
    media_url VARCHAR(2048) NULL,
    media_alt VARCHAR(500) NULL,
    link_url VARCHAR(2048) NULL,
    visibility ENUM('followers','public') NOT NULL DEFAULT 'followers',
    status ENUM('active','expired','deleted','hidden') NOT NULL DEFAULT 'active',
    published_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_stories_uuid (story_uuid),
    UNIQUE KEY uq_pod_stories_remote_object (source_object_sha256),
    KEY idx_pod_stories_feed (status,expires_at,published_at),
    KEY idx_pod_stories_owner (owner_user_id,status,expires_at),
    KEY idx_pod_stories_remote_actor (remote_actor_id,status,expires_at),
    CONSTRAINT fk_pod_stories_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pod_stories_remote_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_story_views (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    story_id BIGINT UNSIGNED NOT NULL,
    viewer_user_id BIGINT UNSIGNED NOT NULL,
    first_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    view_count INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_story_views_story_user (story_id,viewer_user_id),
    KEY idx_pod_story_views_user (viewer_user_id,last_viewed_at),
    CONSTRAINT fk_pod_story_views_story
        FOREIGN KEY (story_id) REFERENCES pod_stories(id) ON DELETE CASCADE,
    CONSTRAINT fk_pod_story_views_user
        FOREIGN KEY (viewer_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_story_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    story_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    remote_actor_id BIGINT UNSIGNED NULL,
    event_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_story_events_hash (event_sha256),
    KEY idx_pod_story_events_story (story_id,created_at),
    KEY idx_pod_story_events_type (event_type,created_at),
    CONSTRAINT fk_pod_story_events_story
        FOREIGN KEY (story_id) REFERENCES pod_stories(id) ON DELETE CASCADE,
    CONSTRAINT fk_pod_story_events_user
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pod_story_events_remote_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
    ('stories_enabled','1'),
    ('stories_receive_remote','1'),
    ('stories_duration_hours','24'),
    ('stories_max_active','10'),
    ('stories_remote_media_mode','link_only')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

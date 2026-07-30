-- North Mountain Media Public Syndication v66E
-- Additive migration. Import after the base portal schema and publishing migrations.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS syndication_webmentions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_url VARCHAR(1000) NOT NULL,
    target_url VARCHAR(1000) NOT NULL,
    target_post_id BIGINT UNSIGNED NULL,
    mention_type ENUM('mention','reply','like','repost') NOT NULL DEFAULT 'mention',
    status ENUM('pending','approved','hidden','spam','rejected') NOT NULL DEFAULT 'pending',
    author_name VARCHAR(190) NULL,
    author_url VARCHAR(1000) NULL,
    author_photo_url VARCHAR(1000) NULL,
    source_title VARCHAR(500) NULL,
    source_excerpt TEXT NULL,
    source_content_hash CHAR(64) NOT NULL,
    verification_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    verification_error VARCHAR(1000) NULL,
    verified_at DATETIME NULL,
    moderated_by_user_id BIGINT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_syndication_webmention_source_target (source_url(384),target_url(384)),
    KEY idx_syndication_webmentions_status_received (status,received_at,id),
    KEY idx_syndication_webmentions_post_status (target_post_id,status,received_at,id),
    KEY idx_syndication_webmentions_moderator (moderated_by_user_id,moderated_at),
    CONSTRAINT fk_syndication_webmentions_post
        FOREIGN KEY (target_post_id) REFERENCES blog_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_syndication_webmentions_moderator
        FOREIGN KEY (moderated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS syndication_websub_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    topic_url VARCHAR(1000) NOT NULL,
    hub_url VARCHAR(1000) NOT NULL,
    event_type ENUM('publish','update','archive','manual') NOT NULL DEFAULT 'update',
    payload_sha256 CHAR(64) NOT NULL,
    status ENUM('pending','delivering','delivered','failed') NOT NULL DEFAULT 'pending',
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    response_code SMALLINT UNSIGNED NULL,
    response_excerpt VARCHAR(1000) NULL,
    last_error VARCHAR(1000) NULL,
    delivered_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_syndication_websub_delivery_payload (hub_url(300),topic_url(300),payload_sha256),
    KEY idx_syndication_websub_queue (status,next_attempt_at,created_at,id),
    KEY idx_syndication_websub_creator (created_by_user_id,created_at),
    CONSTRAINT fk_syndication_websub_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
    ('blog_json_feed_enabled','1'),
    ('blog_podcast_feed_enabled','1'),
    ('blog_webmention_enabled','1'),
    ('blog_websub_enabled','0'),
    ('blog_websub_hub_url',''),
    ('blog_podcast_title','North Mountain Media Podcast'),
    ('blog_podcast_author','North Mountain Media'),
    ('blog_podcast_owner_name','David Evans'),
    ('blog_podcast_owner_email',''),
    ('blog_podcast_category','Technology'),
    ('blog_podcast_explicit','0'),
    ('blog_podcast_type','episodic')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

SELECT 'North Mountain Media Public Syndication v66E migration complete' AS migration_status;

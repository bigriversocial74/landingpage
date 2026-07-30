-- North Mountain Media Federated Timeline, Discovery & Remote Content v66H
-- Additive migration. Import after federated_interactions_v66g.sql.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS activitypub_remote_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inbox_activity_id BIGINT UNSIGNED NULL,
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    entry_uri VARCHAR(1000) NOT NULL,
    source_activity_uri VARCHAR(1000) NOT NULL,
    object_uri VARCHAR(1000) NOT NULL,
    entry_type ENUM('note','article','announce') NOT NULL DEFAULT 'note',
    boosted_object_uri VARCHAR(1000) NULL,
    in_reply_to_uri VARCHAR(1000) NULL,
    source_url VARCHAR(1000) NULL,
    title VARCHAR(500) NULL,
    summary TEXT NULL,
    body_text MEDIUMTEXT NULL,
    body_hash CHAR(64) NOT NULL,
    content_warning VARCHAR(1000) NULL,
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    language_code VARCHAR(35) NULL,
    visibility ENUM('public','unlisted','followers','direct') NOT NULL DEFAULT 'public',
    attachments_json MEDIUMTEXT NULL,
    tags_json MEDIUMTEXT NULL,
    mentions_local TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','active','hidden','deleted') NOT NULL DEFAULT 'active',
    moderation_note VARCHAR(1000) NULL,
    source_published_at DATETIME NULL,
    source_updated_at DATETIME NULL,
    moderated_by_user_id BIGINT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_remote_post_entry (entry_uri(384)),
    KEY idx_activitypub_remote_posts_timeline (status,source_published_at,created_at,id),
    KEY idx_activitypub_remote_posts_actor (remote_actor_id,status,source_published_at,id),
    KEY idx_activitypub_remote_posts_mentions (mentions_local,status,created_at,id),
    KEY idx_activitypub_remote_posts_object (object_uri(384)),
    KEY idx_activitypub_remote_posts_inbox (inbox_activity_id),
    FULLTEXT KEY ft_activitypub_remote_posts_text (title,summary,body_text),
    CONSTRAINT fk_activitypub_remote_posts_inbox
        FOREIGN KEY (inbox_activity_id) REFERENCES activitypub_inbox_activities(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_remote_posts_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_remote_posts_moderator
        FOREIGN KEY (moderated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_timeline_user_state (
    remote_post_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NULL,
    saved_at DATETIME NULL,
    hidden_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (remote_post_id,user_id),
    KEY idx_activitypub_timeline_state_user (user_id,updated_at,remote_post_id),
    KEY idx_activitypub_timeline_state_saved (user_id,saved_at,remote_post_id),
    CONSTRAINT fk_activitypub_timeline_state_post
        FOREIGN KEY (remote_post_id) REFERENCES activitypub_remote_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_timeline_state_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_remote_post_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_uuid CHAR(36) NOT NULL,
    remote_post_id BIGINT UNSIGNED NOT NULL,
    action_type ENUM('like','announce','reply') NOT NULL,
    activity_uri VARCHAR(1000) NOT NULL,
    outbox_activity_id BIGINT UNSIGNED NULL,
    object_uri VARCHAR(1000) NOT NULL,
    reply_object_uri VARCHAR(1000) NULL,
    reply_text TEXT NULL,
    object_json MEDIUMTEXT NULL,
    status ENUM('active','undone','deleted','failed') NOT NULL DEFAULT 'active',
    last_error VARCHAR(1000) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_remote_post_action_uuid (action_uuid),
    UNIQUE KEY uq_activitypub_remote_post_activity_uri (activity_uri(384)),
    KEY idx_activitypub_remote_post_actions_post (remote_post_id,action_type,status,created_at,id),
    KEY idx_activitypub_remote_post_actions_creator (created_by_user_id,created_at,id),
    CONSTRAINT fk_activitypub_remote_post_actions_post
        FOREIGN KEY (remote_post_id) REFERENCES activitypub_remote_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_remote_post_actions_outbox
        FOREIGN KEY (outbox_activity_id) REFERENCES activitypub_outbox_activities(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_remote_post_actions_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
    ('activitypub_timeline_enabled','0'),
    ('activitypub_timeline_store_following','1'),
    ('activitypub_timeline_receive_mentions','1'),
    ('activitypub_timeline_retention_days','90'),
    ('activitypub_timeline_remote_media_mode','link_only')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

SELECT 'North Mountain Media Federated Timeline v66H migration complete' AS migration_status;

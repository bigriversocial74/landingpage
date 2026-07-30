-- North Mountain Media Federated Interactions, Replies & Social Graph v66G
-- Additive migration. Import after content_interactions_v66c.sql and activitypub_federation_v66f.sql.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE activitypub_outbox_activities
    MODIFY activity_type ENUM('Create','Update','Delete','Accept','Reject','Follow','Undo','Like','Announce') NOT NULL;

CREATE TABLE IF NOT EXISTS activitypub_remote_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inbox_activity_id BIGINT UNSIGNED NULL,
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    blog_post_id BIGINT UNSIGNED NOT NULL,
    parent_remote_comment_id BIGINT UNSIGNED NULL,
    parent_local_comment_id BIGINT UNSIGNED NULL,
    object_uri VARCHAR(1000) NOT NULL,
    source_activity_uri VARCHAR(1000) NOT NULL,
    in_reply_to_uri VARCHAR(1000) NOT NULL,
    source_url VARCHAR(1000) NULL,
    body_text TEXT NOT NULL,
    body_hash CHAR(64) NOT NULL,
    status ENUM('pending','approved','hidden','spam','deleted') NOT NULL DEFAULT 'pending',
    moderation_note VARCHAR(1000) NULL,
    source_published_at DATETIME NULL,
    source_updated_at DATETIME NULL,
    moderated_by_user_id BIGINT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_remote_comment_object (object_uri(384)),
    UNIQUE KEY uq_activitypub_remote_comment_activity (source_activity_uri(384)),
    KEY idx_activitypub_remote_comments_post (blog_post_id,status,created_at,id),
    KEY idx_activitypub_remote_comments_actor (remote_actor_id,status,created_at,id),
    KEY idx_activitypub_remote_comments_parent_remote (parent_remote_comment_id,id),
    KEY idx_activitypub_remote_comments_parent_local (parent_local_comment_id,id),
    KEY idx_activitypub_remote_comments_moderation (status,moderated_at,id),
    CONSTRAINT fk_activitypub_remote_comments_inbox
        FOREIGN KEY (inbox_activity_id) REFERENCES activitypub_inbox_activities(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_remote_comments_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_remote_comments_post
        FOREIGN KEY (blog_post_id) REFERENCES blog_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_remote_comments_parent_remote
        FOREIGN KEY (parent_remote_comment_id) REFERENCES activitypub_remote_comments(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_remote_comments_parent_local
        FOREIGN KEY (parent_local_comment_id) REFERENCES content_comments(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_remote_comments_moderator
        FOREIGN KEY (moderated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_remote_reactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inbox_activity_id BIGINT UNSIGNED NULL,
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    blog_post_id BIGINT UNSIGNED NULL,
    local_comment_id BIGINT UNSIGNED NULL,
    remote_comment_id BIGINT UNSIGNED NULL,
    activity_uri VARCHAR(1000) NOT NULL,
    object_uri VARCHAR(1000) NOT NULL,
    reaction_type ENUM('like','announce') NOT NULL,
    status ENUM('active','undone','deleted') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_remote_reaction_activity (activity_uri(384)),
    KEY idx_activitypub_remote_reactions_post (blog_post_id,status,reaction_type,created_at,id),
    KEY idx_activitypub_remote_reactions_local_comment (local_comment_id,status,id),
    KEY idx_activitypub_remote_reactions_remote_comment (remote_comment_id,status,id),
    KEY idx_activitypub_remote_reactions_actor (remote_actor_id,status,created_at,id),
    CONSTRAINT fk_activitypub_remote_reactions_inbox
        FOREIGN KEY (inbox_activity_id) REFERENCES activitypub_inbox_activities(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_remote_reactions_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_remote_reactions_post
        FOREIGN KEY (blog_post_id) REFERENCES blog_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_remote_reactions_local_comment
        FOREIGN KEY (local_comment_id) REFERENCES content_comments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_remote_reactions_remote_comment
        FOREIGN KEY (remote_comment_id) REFERENCES activitypub_remote_comments(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_following (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    follow_activity_uri VARCHAR(1000) NOT NULL,
    follow_outbox_activity_id BIGINT UNSIGNED NULL,
    status ENUM('pending','accepted','rejected','removed','blocked') NOT NULL DEFAULT 'pending',
    created_by_user_id BIGINT UNSIGNED NULL,
    accepted_at DATETIME NULL,
    removed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_following_actor (remote_actor_id),
    UNIQUE KEY uq_activitypub_following_activity (follow_activity_uri(384)),
    KEY idx_activitypub_following_status (status,created_at,id),
    CONSTRAINT fk_activitypub_following_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_following_outbox
        FOREIGN KEY (follow_outbox_activity_id) REFERENCES activitypub_outbox_activities(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_following_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_actor_controls (
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    moderation_status ENUM('active','muted','blocked') NOT NULL DEFAULT 'active',
    moderation_note VARCHAR(1000) NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (remote_actor_id),
    KEY idx_activitypub_actor_controls_status (moderation_status,updated_at),
    CONSTRAINT fk_activitypub_actor_controls_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_actor_controls_user
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_domain_blocks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    domain_name VARCHAR(253) NOT NULL,
    reason VARCHAR(1000) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_domain_block (domain_name),
    KEY idx_activitypub_domain_blocks_created (created_at,id),
    CONSTRAINT fk_activitypub_domain_blocks_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_local_objects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type ENUM('comment','reaction') NOT NULL,
    entity_key VARCHAR(190) NOT NULL,
    blog_post_id BIGINT UNSIGNED NULL,
    local_comment_id BIGINT UNSIGNED NULL,
    object_uri VARCHAR(1000) NOT NULL,
    create_activity_uri VARCHAR(1000) NULL,
    last_activity_uri VARCHAR(1000) NULL,
    last_payload_hash CHAR(64) NULL,
    status ENUM('active','deleted') NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_local_object_entity (entity_type,entity_key),
    UNIQUE KEY uq_activitypub_local_object_uri (object_uri(384)),
    KEY idx_activitypub_local_objects_post (blog_post_id,entity_type,status,id),
    KEY idx_activitypub_local_objects_comment (local_comment_id,status,id),
    CONSTRAINT fk_activitypub_local_objects_post
        FOREIGN KEY (blog_post_id) REFERENCES blog_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_local_objects_comment
        FOREIGN KEY (local_comment_id) REFERENCES content_comments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_local_objects_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
    ('activitypub_federate_comments','1'),
    ('activitypub_federate_reactions','1'),
    ('activitypub_allow_remote_replies','1'),
    ('activitypub_allow_remote_reactions','1'),
    ('activitypub_remote_reply_moderation','pre_moderated'),
    ('activitypub_show_following','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

SELECT 'North Mountain Media Federated Interactions v66G migration complete' AS migration_status;

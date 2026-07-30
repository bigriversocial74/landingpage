-- North Mountain Media Content Interactions v66C
-- Additive migration. Import after database/publishing_systems_v51.sql and the base portal schema.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS content_interaction_settings (
    content_type VARCHAR(40) NOT NULL,
    content_id BIGINT UNSIGNED NOT NULL,
    comments_enabled TINYINT(1) NOT NULL DEFAULT 1,
    replies_enabled TINYINT(1) NOT NULL DEFAULT 1,
    reactions_enabled TINYINT(1) NOT NULL DEFAULT 1,
    moderation_mode ENUM('pre_moderated','registered_auto') NOT NULL DEFAULT 'pre_moderated',
    comments_closed_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (content_type,content_id),
    KEY idx_content_interaction_settings_updated_by (updated_by),
    CONSTRAINT fk_content_interaction_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_type VARCHAR(40) NOT NULL,
    content_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    author_user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    body_hash CHAR(64) NOT NULL,
    status ENUM('pending','approved','hidden','spam','deleted') NOT NULL DEFAULT 'pending',
    depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
    report_count INT UNSIGNED NOT NULL DEFAULT 0,
    edited_at DATETIME NULL,
    moderated_at DATETIME NULL,
    moderated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_comments_content (content_type,content_id,status,parent_id,created_at,id),
    KEY idx_content_comments_author (author_user_id,created_at,id),
    KEY idx_content_comments_moderation (status,report_count,created_at,id),
    KEY idx_content_comments_parent (parent_id,id),
    KEY idx_content_comments_moderated_by (moderated_by),
    KEY idx_content_comments_deleted_by (deleted_by),
    CONSTRAINT fk_content_comments_parent FOREIGN KEY (parent_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comments_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comments_moderator FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_content_comments_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_comment_edits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    editor_user_id BIGINT UNSIGNED NOT NULL,
    previous_body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_comment_edits_comment (comment_id,created_at,id),
    KEY idx_content_comment_edits_editor (editor_user_id,created_at,id),
    CONSTRAINT fk_content_comment_edits_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comment_edits_editor FOREIGN KEY (editor_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_reactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type ENUM('content','comment') NOT NULL,
    content_type VARCHAR(40) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reaction_type ENUM('like','support','insightful') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_reaction_target_user (target_type,content_type,target_id,user_id),
    KEY idx_content_reactions_target (target_type,content_type,target_id,reaction_type),
    KEY idx_content_reactions_user (user_id,created_at,id),
    CONSTRAINT fk_content_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_comment_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    reporter_user_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_comment_reporter (comment_id,reporter_user_id),
    KEY idx_content_comment_reports_created (created_at,id),
    KEY idx_content_comment_reports_reporter (reporter_user_id,created_at,id),
    CONSTRAINT fk_content_comment_reports_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comment_reports_user FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_moderation_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    moderator_user_id BIGINT UNSIGNED NOT NULL,
    action ENUM('approved','hidden','spam','deleted') NOT NULL,
    note VARCHAR(1000) NULL,
    previous_status ENUM('pending','approved','hidden','spam','deleted') NOT NULL,
    new_status ENUM('pending','approved','hidden','spam','deleted') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_moderation_comment (comment_id,created_at,id),
    KEY idx_content_moderation_user (moderator_user_id,created_at,id),
    CONSTRAINT fk_content_moderation_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_moderation_user FOREIGN KEY (moderator_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'North Mountain Media Content Interactions v66C migration complete' AS migration_status;

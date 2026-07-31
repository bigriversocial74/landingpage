-- North Mountain Media Federated Messaging, Conversation Safety & HomeServer Handoff v66I
-- Additive migration. Import after activitypub_federation_v66f.sql and federated_interactions_v66g.sql.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS activitypub_message_threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_uuid CHAR(36) NOT NULL,
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    thread_key CHAR(64) NOT NULL,
    conversation_uri VARCHAR(1000) NULL,
    subject VARCHAR(500) NULL,
    status ENUM('request','open','archived','muted','blocked','closed') NOT NULL DEFAULT 'request',
    trust_level ENUM('unknown','follower','following','mutual','approved') NOT NULL DEFAULT 'unknown',
    needs_response TINYINT(1) NOT NULL DEFAULT 0,
    risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_message_at DATETIME NULL,
    accepted_by_user_id BIGINT UNSIGNED NULL,
    accepted_at DATETIME NULL,
    rejected_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_message_thread_uuid (thread_uuid),
    UNIQUE KEY uq_activitypub_message_thread_key (remote_actor_id,thread_key),
    KEY idx_activitypub_message_threads_queue (status,needs_response,last_message_at,id),
    KEY idx_activitypub_message_threads_actor (remote_actor_id,status,last_message_at,id),
    CONSTRAINT fk_activitypub_message_threads_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_message_threads_acceptor
        FOREIGN KEY (accepted_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_uuid CHAR(36) NOT NULL,
    thread_id BIGINT UNSIGNED NOT NULL,
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('inbound','outbound','system') NOT NULL,
    activity_uri VARCHAR(1000) NOT NULL,
    object_uri VARCHAR(1000) NOT NULL,
    source_activity_uri VARCHAR(1000) NULL,
    in_reply_to_uri VARCHAR(1000) NULL,
    inbox_activity_id BIGINT UNSIGNED NULL,
    outbox_activity_id BIGINT UNSIGNED NULL,
    body_text MEDIUMTEXT NULL,
    body_hash CHAR(64) NOT NULL,
    attachments_json MEDIUMTEXT NULL,
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('request','visible','edited','deleted','failed','spam') NOT NULL DEFAULT 'visible',
    last_error VARCHAR(1000) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    source_published_at DATETIME NULL,
    source_updated_at DATETIME NULL,
    edited_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_message_uuid (message_uuid),
    UNIQUE KEY uq_activitypub_message_activity (activity_uri(384)),
    UNIQUE KEY uq_activitypub_message_object (object_uri(384)),
    KEY idx_activitypub_messages_thread (thread_id,created_at,id),
    KEY idx_activitypub_messages_actor (remote_actor_id,direction,created_at,id),
    KEY idx_activitypub_messages_status (status,created_at,id),
    KEY idx_activitypub_messages_inbox (inbox_activity_id),
    KEY idx_activitypub_messages_outbox (outbox_activity_id),
    CONSTRAINT fk_activitypub_messages_thread
        FOREIGN KEY (thread_id) REFERENCES activitypub_message_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_messages_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_messages_inbox
        FOREIGN KEY (inbox_activity_id) REFERENCES activitypub_inbox_activities(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_messages_outbox
        FOREIGN KEY (outbox_activity_id) REFERENCES activitypub_outbox_activities(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_messages_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_message_user_state (
    thread_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    last_read_message_id BIGINT UNSIGNED NULL,
    read_at DATETIME NULL,
    archived_at DATETIME NULL,
    muted_at DATETIME NULL,
    pinned_at DATETIME NULL,
    hidden_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id,user_id),
    KEY idx_activitypub_message_state_user (user_id,updated_at,thread_id),
    KEY idx_activitypub_message_state_pinned (user_id,pinned_at,thread_id),
    CONSTRAINT fk_activitypub_message_state_thread
        FOREIGN KEY (thread_id) REFERENCES activitypub_message_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_message_state_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_message_state_last_read
        FOREIGN KEY (last_read_message_id) REFERENCES activitypub_messages(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_message_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    event_note VARCHAR(1000) NULL,
    evidence_json MEDIUMTEXT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activitypub_message_events_thread (thread_id,created_at,id),
    KEY idx_activitypub_message_events_type (event_type,created_at,id),
    CONSTRAINT fk_activitypub_message_events_thread
        FOREIGN KEY (thread_id) REFERENCES activitypub_message_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_message_events_message
        FOREIGN KEY (message_id) REFERENCES activitypub_messages(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_message_events_user
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_message_assistance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_uuid CHAR(36) NOT NULL,
    thread_id BIGINT UNSIGNED NOT NULL,
    source_message_id BIGINT UNSIGNED NULL,
    capability ENUM('summary','draft','translate') NOT NULL,
    input_sha256 CHAR(64) NOT NULL,
    status ENUM('unavailable','pending','completed','failed') NOT NULL DEFAULT 'pending',
    result_text MEDIUMTEXT NULL,
    receipt_json MEDIUMTEXT NULL,
    last_error VARCHAR(1000) NULL,
    requested_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_message_assistance_uuid (request_uuid),
    KEY idx_activitypub_message_assistance_thread (thread_id,created_at,id),
    KEY idx_activitypub_message_assistance_status (status,created_at,id),
    CONSTRAINT fk_activitypub_message_assistance_thread
        FOREIGN KEY (thread_id) REFERENCES activitypub_message_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_message_assistance_message
        FOREIGN KEY (source_message_id) REFERENCES activitypub_messages(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_message_assistance_user
        FOREIGN KEY (requested_by_user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
    ('activitypub_messages_enabled','0'),
    ('activitypub_messages_accept_mode','requests'),
    ('activitypub_messages_retention_days','180'),
    ('activitypub_messages_max_body','10000'),
    ('activitypub_messages_actor_hourly_limit','30'),
    ('activitypub_messages_remote_media_mode','link_only'),
    ('activitypub_messages_homeserver_assistance','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

SELECT 'North Mountain Media Federated Messaging v66I migration complete' AS migration_status;
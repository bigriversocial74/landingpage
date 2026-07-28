SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pod_agent_receptionist_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pod_identity_id BIGINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    agent_name VARCHAR(120) NOT NULL DEFAULT 'POD Receptionist',
    greeting VARCHAR(700) NOT NULL DEFAULT 'Hello. I am the owner''s POD receptionist. I can answer approved public questions, take a message, request a callback, or help connect your call.',
    available_route ENUM('owner_first','agent_first','agent_only') NOT NULL DEFAULT 'owner_first',
    busy_route ENUM('agent_first','agent_only','voicemail','callback') NOT NULL DEFAULT 'agent_first',
    offline_route ENUM('agent_first','agent_only','voicemail','callback') NOT NULL DEFAULT 'agent_first',
    allow_transfer TINYINT(1) NOT NULL DEFAULT 1,
    allow_callback TINYINT(1) NOT NULL DEFAULT 1,
    allow_message TINYINT(1) NOT NULL DEFAULT 1,
    allow_public_profile TINYINT(1) NOT NULL DEFAULT 1,
    allow_public_portfolio TINYINT(1) NOT NULL DEFAULT 1,
    allow_public_blog TINYINT(1) NOT NULL DEFAULT 1,
    maximum_questions SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    session_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_agent_receptionist_identity (pod_identity_id),
    CONSTRAINT fk_pod_agent_receptionist_identity
        FOREIGN KEY (pod_identity_id) REFERENCES pod_identities(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_agent_receptionist_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_agent_receptionist_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_uuid CHAR(36) NOT NULL,
    relationship_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    caller_pod_uuid VARCHAR(80) NOT NULL,
    caller_display_name VARCHAR(190) NOT NULL,
    agent_name VARCHAR(120) NOT NULL,
    line_status ENUM('available','busy','offline') NOT NULL,
    route_decision ENUM(
        'owner_first','agent_first','agent_only','voicemail','callback','declined'
    ) NOT NULL,
    status ENUM(
        'active','transfer_offered','transferred','message_taken',
        'callback_requested','completed','expired','blocked'
    ) NOT NULL DEFAULT 'active',
    question_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    callback_request_id BIGINT UNSIGNED NULL,
    transfer_requested_at DATETIME NULL,
    transferred_at DATETIME NULL,
    summary TEXT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_agent_receptionist_session_uuid (session_uuid),
    KEY idx_pod_agent_receptionist_relationship (relationship_id,last_activity_at),
    KEY idx_pod_agent_receptionist_status (status,last_activity_at),
    KEY idx_pod_agent_receptionist_contact (crm_contact_id,last_activity_at),
    CONSTRAINT fk_pod_agent_receptionist_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_agent_receptionist_contact
        FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_agent_receptionist_callback
        FOREIGN KEY (callback_request_id) REFERENCES call_center_requests(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_agent_receptionist_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    sender_role ENUM('caller','agent','system') NOT NULL,
    body TEXT NOT NULL,
    intent VARCHAR(80) NULL,
    sources_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pod_agent_receptionist_messages_session (session_id,id),
    CONSTRAINT fk_pod_agent_receptionist_messages_session
        FOREIGN KEY (session_id) REFERENCES pod_agent_receptionist_sessions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_agent_receptionist_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    relationship_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type ENUM(
        'session_started','question_answered','transfer_offered','transfer_requested',
        'transferred','message_taken','callback_requested','session_completed',
        'session_expired','settings_updated','rejected','system'
    ) NOT NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pod_agent_receptionist_events_session (session_id,id),
    KEY idx_pod_agent_receptionist_events_relationship (relationship_id,id),
    CONSTRAINT fk_pod_agent_receptionist_events_session
        FOREIGN KEY (session_id) REFERENCES pod_agent_receptionist_sessions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_agent_receptionist_events_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_agent_receptionist_events_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

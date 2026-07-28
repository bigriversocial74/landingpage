SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pod_agent_voice_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pod_identity_id BIGINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    recognition_language VARCHAR(20) NOT NULL DEFAULT 'en-US',
    preferred_voice_name VARCHAR(190) NULL,
    speech_rate DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    speech_pitch DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    auto_speak TINYINT(1) NOT NULL DEFAULT 1,
    allow_hands_free TINYINT(1) NOT NULL DEFAULT 1,
    hands_free_default TINYINT(1) NOT NULL DEFAULT 0,
    push_to_talk_default TINYINT(1) NOT NULL DEFAULT 1,
    maximum_voice_turns SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    privacy_notice VARCHAR(700) NOT NULL DEFAULT 'Voice input and speech output use browser capabilities when available. This POD does not upload or store raw live audio.',
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_agent_voice_identity (pod_identity_id),
    CONSTRAINT fk_pod_agent_voice_identity
        FOREIGN KEY (pod_identity_id) REFERENCES pod_identities(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_agent_voice_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_agent_voice_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voice_session_uuid CHAR(36) NOT NULL,
    receptionist_session_id BIGINT UNSIGNED NOT NULL,
    relationship_id BIGINT UNSIGNED NOT NULL,
    capability_mode ENUM('full_voice','recognition_only','synthesis_only','text_only') NOT NULL,
    recognition_supported TINYINT(1) NOT NULL DEFAULT 0,
    synthesis_supported TINYINT(1) NOT NULL DEFAULT 0,
    selected_voice_name VARCHAR(190) NULL,
    recognition_language VARCHAR(20) NOT NULL,
    hands_free_enabled TINYINT(1) NOT NULL DEFAULT 0,
    spoken_replies_enabled TINYINT(1) NOT NULL DEFAULT 1,
    recognized_turns SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    spoken_turns SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    error_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active','completed','cancelled','expired','failed') NOT NULL DEFAULT 'active',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_agent_voice_session_uuid (voice_session_uuid),
    KEY idx_pod_agent_voice_receptionist (receptionist_session_id,last_activity_at),
    KEY idx_pod_agent_voice_relationship (relationship_id,last_activity_at),
    KEY idx_pod_agent_voice_status (status,last_activity_at),
    CONSTRAINT fk_pod_agent_voice_receptionist
        FOREIGN KEY (receptionist_session_id) REFERENCES pod_agent_receptionist_sessions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_agent_voice_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_agent_voice_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voice_session_id BIGINT UNSIGNED NOT NULL,
    receptionist_session_id BIGINT UNSIGNED NOT NULL,
    relationship_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM(
        'voice_started','recognition_started','recognition_result','recognition_stopped',
        'speech_started','speech_completed','speech_cancelled','capability_fallback',
        'voice_error','voice_completed','settings_updated','system'
    ) NOT NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pod_agent_voice_events_session (voice_session_id,id),
    KEY idx_pod_agent_voice_events_receptionist (receptionist_session_id,id),
    KEY idx_pod_agent_voice_events_relationship (relationship_id,id),
    CONSTRAINT fk_pod_agent_voice_events_session
        FOREIGN KEY (voice_session_id) REFERENCES pod_agent_voice_sessions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_agent_voice_events_receptionist
        FOREIGN KEY (receptionist_session_id) REFERENCES pod_agent_receptionist_sessions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_agent_voice_events_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

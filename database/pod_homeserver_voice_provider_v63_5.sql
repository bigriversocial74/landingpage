SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pod_homeserver_pairing_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code_hash CHAR(64) NOT NULL,
    code_hint VARCHAR(24) NOT NULL,
    status ENUM('active','used','expired','revoked') NOT NULL DEFAULT 'active',
    requested_capabilities_json JSON NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_homeserver_pairing_code_hash (code_hash),
    KEY idx_pod_homeserver_pairing_status (status,expires_at),
    CONSTRAINT fk_pod_homeserver_pairing_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_homeserver_connections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    connection_uuid CHAR(36) NOT NULL,
    pairing_request_id VARCHAR(120) NOT NULL,
    pod_identity_id BIGINT UNSIGNED NOT NULL,
    installation_id VARCHAR(120) NOT NULL,
    device_id CHAR(36) NOT NULL,
    device_display_name VARCHAR(190) NOT NULL,
    homeserver_version VARCHAR(40) NOT NULL,
    device_public_key VARCHAR(100) NOT NULL,
    bearer_token_hash CHAR(64) NOT NULL,
    token_hint VARCHAR(16) NOT NULL,
    lifecycle_state ENUM(
        'active','offline','suspended','revoked','replacing','error'
    ) NOT NULL DEFAULT 'active',
    granted_capabilities_json JSON NOT NULL,
    capability_registry_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    last_heartbeat_at DATETIME NULL,
    last_request_at DATETIME NULL,
    last_ip_hash CHAR(64) NULL,
    last_error_code VARCHAR(100) NULL,
    last_error_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_homeserver_connection_uuid (connection_uuid),
    UNIQUE KEY uq_pod_homeserver_pairing_request (pod_identity_id,pairing_request_id),
    UNIQUE KEY uq_pod_homeserver_device_id (device_id),
    UNIQUE KEY uq_pod_homeserver_installation (pod_identity_id,installation_id),
    UNIQUE KEY uq_pod_homeserver_bearer_hash (bearer_token_hash),
    KEY idx_pod_homeserver_connection_state (lifecycle_state,last_heartbeat_at),
    CONSTRAINT fk_pod_homeserver_connection_identity
        FOREIGN KEY (pod_identity_id) REFERENCES pod_identities(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_homeserver_request_nonces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    connection_id BIGINT UNSIGNED NOT NULL,
    nonce_hash CHAR(64) NOT NULL,
    request_timestamp BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_homeserver_connection_nonce (connection_id,nonce_hash),
    KEY idx_pod_homeserver_nonce_retention (created_at),
    CONSTRAINT fk_pod_homeserver_nonce_connection
        FOREIGN KEY (connection_id) REFERENCES pod_homeserver_connections(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_homeserver_voice_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_uuid CHAR(36) NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    receptionist_session_id BIGINT UNSIGNED NULL,
    voice_session_id BIGINT UNSIGNED NULL,
    job_type ENUM('speech_to_text','text_to_speech','capability_test') NOT NULL,
    status ENUM(
        'queued','leased','processing','completed','failed','cancelled','expired'
    ) NOT NULL DEFAULT 'queued',
    priority ENUM('normal','high') NOT NULL DEFAULT 'normal',
    payload_ciphertext LONGTEXT NOT NULL,
    payload_iv VARCHAR(64) NOT NULL,
    payload_tag VARCHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    input_artifact_id BIGINT UNSIGNED NULL,
    result_ciphertext LONGTEXT NULL,
    result_iv VARCHAR(64) NULL,
    result_tag VARCHAR(64) NULL,
    result_hash CHAR(64) NULL,
    output_artifact_id BIGINT UNSIGNED NULL,
    lease_token_hash CHAR(64) NULL,
    lease_expires_at DATETIME NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    failure_code VARCHAR(100) NULL,
    failure_message VARCHAR(700) NULL,
    queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    leased_at DATETIME NULL,
    completed_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_homeserver_voice_job_uuid (job_uuid),
    UNIQUE KEY uq_pod_homeserver_voice_lease_hash (lease_token_hash),
    KEY idx_pod_homeserver_voice_queue (connection_id,status,priority,queued_at),
    KEY idx_pod_homeserver_voice_expiry (status,expires_at),
    KEY idx_pod_homeserver_voice_input_artifact (input_artifact_id),
    KEY idx_pod_homeserver_voice_output_artifact (output_artifact_id),
    CONSTRAINT fk_pod_homeserver_voice_connection
        FOREIGN KEY (connection_id) REFERENCES pod_homeserver_connections(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_homeserver_voice_receptionist
        FOREIGN KEY (receptionist_session_id) REFERENCES pod_agent_receptionist_sessions(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_homeserver_voice_session
        FOREIGN KEY (voice_session_id) REFERENCES pod_agent_voice_sessions(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_homeserver_voice_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_homeserver_voice_artifacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    artifact_uuid CHAR(36) NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    direction ENUM('input','output') NOT NULL,
    media_kind ENUM('audio','json') NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    content_hash CHAR(64) NOT NULL,
    plaintext_bytes BIGINT UNSIGNED NOT NULL,
    encryption_iv VARCHAR(64) NOT NULL,
    encryption_tag VARCHAR(64) NOT NULL,
    status ENUM('active','consumed','deleted','expired','missing') NOT NULL DEFAULT 'active',
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_homeserver_voice_artifact_uuid (artifact_uuid),
    UNIQUE KEY uq_pod_homeserver_voice_stored_name (stored_name),
    KEY idx_pod_homeserver_artifact_expiry (status,expires_at),
    KEY idx_pod_homeserver_artifact_job (job_id,direction),
    CONSTRAINT fk_pod_homeserver_artifact_connection
        FOREIGN KEY (connection_id) REFERENCES pod_homeserver_connections(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_homeserver_artifact_job
        FOREIGN KEY (job_id) REFERENCES pod_homeserver_voice_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_homeserver_voice_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_uuid CHAR(36) NOT NULL,
    connection_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    receipt_type ENUM(
        'paired','heartbeat','job_queued','job_leased','job_completed',
        'job_failed','artifact_created','artifact_consumed','artifact_deleted',
        'connection_revoked','request_rejected','system'
    ) NOT NULL,
    status_code VARCHAR(100) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_homeserver_voice_receipt_uuid (receipt_uuid),
    KEY idx_pod_homeserver_receipt_connection (connection_id,id),
    KEY idx_pod_homeserver_receipt_job (job_id,id),
    CONSTRAINT fk_pod_homeserver_receipt_connection
        FOREIGN KEY (connection_id) REFERENCES pod_homeserver_connections(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_homeserver_receipt_job
        FOREIGN KEY (job_id) REFERENCES pod_homeserver_voice_jobs(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

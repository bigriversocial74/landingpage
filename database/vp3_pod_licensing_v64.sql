SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS vp3_license_configuration (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    provider_id VARCHAR(40) NOT NULL DEFAULT 'vp3',
    provider_name VARCHAR(120) NOT NULL DEFAULT 'VP3.me',
    provider_base_url VARCHAR(255) NOT NULL,
    provider_api_version VARCHAR(20) NOT NULL DEFAULT 'v1',
    account_public_id VARCHAR(120) NULL,
    domain_registration_public_id VARCHAR(120) NULL,
    domain_hostname VARCHAR(255) NULL,
    license_public_id VARCHAR(120) NULL,
    deployment_public_id VARCHAR(120) NULL,
    installation_fingerprint VARCHAR(190) NULL,
    plan_code VARCHAR(80) NULL,
    entitlement_token_version INT UNSIGNED NOT NULL DEFAULT 1,
    license_status ENUM('active','grace','suspended','expired','terminated','unknown') NOT NULL DEFAULT 'unknown',
    entitlement_expires_at DATETIME NULL,
    offline_lease_expires_at DATETIME NULL,
    renewal_at DATETIME NULL,
    last_successful_validation_at DATETIME NULL,
    last_validation_attempt_at DATETIME NULL,
    last_heartbeat_at DATETIME NULL,
    last_error_code VARCHAR(100) NULL,
    last_error_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_license_public_id (license_public_id),
    UNIQUE KEY uq_vp3_deployment_public_id (deployment_public_id),
    UNIQUE KEY uq_vp3_installation_fingerprint (installation_fingerprint),
    CONSTRAINT chk_vp3_license_configuration_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_deployment_credentials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    credential_version INT UNSIGNED NOT NULL DEFAULT 1,
    credential_ciphertext LONGTEXT NOT NULL,
    credential_iv VARCHAR(64) NOT NULL,
    credential_tag VARCHAR(64) NOT NULL,
    credential_hint VARCHAR(20) NOT NULL,
    status ENUM('active','rotated','revoked') NOT NULL DEFAULT 'active',
    activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rotated_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_deployment_credential_version (credential_version),
    KEY idx_vp3_deployment_credential_status (status,activated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_entitlement_cache (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_jti VARCHAR(190) NOT NULL,
    signing_key_id VARCHAR(190) NOT NULL,
    token_version INT UNSIGNED NOT NULL DEFAULT 1,
    signed_token_ciphertext LONGTEXT NOT NULL,
    signed_token_iv VARCHAR(64) NOT NULL,
    signed_token_tag VARCHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    entitlement_json JSON NOT NULL,
    entitlements_json JSON NOT NULL,
    license_status ENUM('active','grace','suspended','expired','terminated','unknown') NOT NULL,
    plan_code VARCHAR(80) NULL,
    issued_at DATETIME NOT NULL,
    not_before_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    offline_lease_expires_at DATETIME NOT NULL,
    validated_at DATETIME NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_entitlement_jti (token_jti),
    UNIQUE KEY uq_vp3_entitlement_token_hash (token_hash),
    KEY idx_vp3_entitlement_current (is_current,validated_at),
    KEY idx_vp3_entitlement_expiry (expires_at,offline_lease_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_license_validation_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_uuid CHAR(36) NOT NULL,
    request_id CHAR(36) NULL,
    validation_type ENUM('validate','heartbeat','token_rotate','jwks_refresh','offline_lease','storage_check','update_check','configuration') NOT NULL,
    outcome ENUM('success','warning','denied','error') NOT NULL,
    status_code VARCHAR(100) NULL,
    license_status ENUM('active','grace','suspended','expired','terminated','unknown') NULL,
    response_hash CHAR(64) NULL,
    latency_ms INT UNSIGNED NULL,
    network_state ENUM('online','offline','not_required') NOT NULL DEFAULT 'not_required',
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_license_receipt_uuid (receipt_uuid),
    KEY idx_vp3_license_receipt_type (validation_type,created_at),
    KEY idx_vp3_license_receipt_outcome (outcome,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_license_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uuid CHAR(36) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    previous_status ENUM('active','grace','suspended','expired','terminated','unknown') NULL,
    current_status ENUM('active','grace','suspended','expired','terminated','unknown') NULL,
    plan_code VARCHAR(80) NULL,
    actor_type ENUM('system','administrator','provider') NOT NULL DEFAULT 'system',
    actor_user_id BIGINT UNSIGNED NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_license_event_uuid (event_uuid),
    KEY idx_vp3_license_event_type (event_type,created_at),
    KEY idx_vp3_license_event_status (current_status,created_at),
    CONSTRAINT fk_vp3_license_event_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_storage_usage_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot_uuid CHAR(36) NOT NULL,
    used_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    allowance_bytes BIGINT UNSIGNED NULL,
    usage_percent DECIMAL(7,3) NULL,
    warning_state ENUM('normal','warning_80','warning_90','hard_limit','over_limit','unlicensed') NOT NULL DEFAULT 'unlicensed',
    measured_paths_json JSON NULL,
    file_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    measured_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_storage_snapshot_uuid (snapshot_uuid),
    KEY idx_vp3_storage_measured_at (measured_at),
    KEY idx_vp3_storage_warning_state (warning_state,measured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_request_nonces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nonce_hash CHAR(64) NOT NULL,
    request_id CHAR(36) NOT NULL,
    request_type ENUM('validate','heartbeat','token_rotate','jwks_refresh') NOT NULL,
    request_timestamp BIGINT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_request_nonce_hash (nonce_hash),
    UNIQUE KEY uq_vp3_request_id (request_id),
    KEY idx_vp3_request_nonce_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO vp3_license_configuration (
    id,provider_id,provider_name,provider_base_url,provider_api_version,license_status
) VALUES (
    1,'vp3','VP3.me','https://vp3.me','v1','unknown'
) ON DUPLICATE KEY UPDATE id=VALUES(id);

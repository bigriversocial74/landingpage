-- North Mountain Media Portal
-- Section 66M: Incident Response, Runbooks & Recovery Center
-- Additive and repeat-safe. Canonical source records remain authoritative.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS recovery_settings (
    id TINYINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    dry_run TINYINT(1) NOT NULL DEFAULT 1,
    emergency_disabled TINYINT(1) NOT NULL DEFAULT 0,
    worker_batch_size SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    approval_expiry_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    simulation_ttl_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    execution_lease_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 300,
    execution_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 365,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_recovery_settings_user
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO recovery_settings (id,enabled,dry_run,emergency_disabled)
VALUES (1,0,1,0);

CREATE TABLE IF NOT EXISTS recovery_runbooks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    runbook_uuid CHAR(36) NOT NULL,
    runbook_key VARCHAR(120) NOT NULL,
    name VARCHAR(190) NOT NULL,
    description VARCHAR(1000) NULL,
    metric_family VARCHAR(60) NOT NULL,
    impact ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    status ENUM('draft','active','paused','disabled') NOT NULL DEFAULT 'active',
    approval_required TINYINT(1) NOT NULL DEFAULT 1,
    cooldown_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    max_concurrency SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    current_version INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_runbook_uuid (runbook_uuid),
    UNIQUE KEY uq_recovery_runbook_key (runbook_key),
    KEY idx_recovery_runbook_family (metric_family,status,impact),
    CONSTRAINT fk_recovery_runbook_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_recovery_runbook_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recovery_runbook_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    runbook_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    definition_hash CHAR(64) NOT NULL,
    definition_json LONGTEXT NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_runbook_version (runbook_id,version_number),
    UNIQUE KEY uq_recovery_runbook_definition (runbook_id,definition_hash),
    CONSTRAINT fk_recovery_runbook_version_runbook
        FOREIGN KEY (runbook_id) REFERENCES recovery_runbooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_runbook_version_user
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recovery_recommendations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    incident_id BIGINT UNSIGNED NOT NULL,
    runbook_id BIGINT UNSIGNED NOT NULL,
    status ENUM('recommended','accepted','dismissed','superseded') NOT NULL DEFAULT 'recommended',
    reason_code VARCHAR(120) NOT NULL,
    priority_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_recommendation (incident_id,runbook_id),
    KEY idx_recovery_recommendation_queue (status,priority_order,created_at),
    CONSTRAINT fk_recovery_recommendation_incident
        FOREIGN KEY (incident_id) REFERENCES operations_health_incidents(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_recommendation_runbook
        FOREIGN KEY (runbook_id) REFERENCES recovery_runbooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recovery_simulations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    simulation_uuid CHAR(36) NOT NULL,
    incident_id BIGINT UNSIGNED NOT NULL,
    runbook_id BIGINT UNSIGNED NOT NULL,
    runbook_version_id BIGINT UNSIGNED NOT NULL,
    simulation_hash CHAR(64) NOT NULL,
    input_json LONGTEXT NOT NULL,
    plan_json LONGTEXT NOT NULL,
    status ENUM('valid','stale','invalid','consumed') NOT NULL DEFAULT 'valid',
    expires_at DATETIME NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_simulation_uuid (simulation_uuid),
    UNIQUE KEY uq_recovery_simulation_hash (incident_id,runbook_version_id,simulation_hash),
    KEY idx_recovery_simulation_valid (status,expires_at,created_at),
    CONSTRAINT fk_recovery_simulation_incident
        FOREIGN KEY (incident_id) REFERENCES operations_health_incidents(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_simulation_runbook
        FOREIGN KEY (runbook_id) REFERENCES recovery_runbooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_simulation_version
        FOREIGN KEY (runbook_version_id) REFERENCES recovery_runbook_versions(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_simulation_user
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recovery_approvals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    approval_uuid CHAR(36) NOT NULL,
    simulation_id BIGINT UNSIGNED NOT NULL,
    incident_id BIGINT UNSIGNED NOT NULL,
    runbook_id BIGINT UNSIGNED NOT NULL,
    runbook_version_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','approved','rejected','expired','cancelled','consumed') NOT NULL DEFAULT 'pending',
    request_hash CHAR(64) NOT NULL,
    requested_by_user_id BIGINT UNSIGNED NULL,
    resolved_by_user_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_approval_uuid (approval_uuid),
    UNIQUE KEY uq_recovery_approval_simulation (simulation_id),
    KEY idx_recovery_approval_queue (status,expires_at,created_at),
    CONSTRAINT fk_recovery_approval_simulation
        FOREIGN KEY (simulation_id) REFERENCES recovery_simulations(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_approval_incident
        FOREIGN KEY (incident_id) REFERENCES operations_health_incidents(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_approval_runbook
        FOREIGN KEY (runbook_id) REFERENCES recovery_runbooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_approval_version
        FOREIGN KEY (runbook_version_id) REFERENCES recovery_runbook_versions(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_approval_requested_by
        FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_recovery_approval_resolved_by
        FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recovery_executions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    execution_uuid CHAR(36) NOT NULL,
    incident_id BIGINT UNSIGNED NOT NULL,
    runbook_id BIGINT UNSIGNED NOT NULL,
    runbook_version_id BIGINT UNSIGNED NOT NULL,
    simulation_id BIGINT UNSIGNED NOT NULL,
    approval_id BIGINT UNSIGNED NULL,
    idempotency_key CHAR(64) NOT NULL,
    status ENUM('queued','running','verifying','completed','partially_completed','failed','cancelled','simulated') NOT NULL DEFAULT 'queued',
    impact ENUM('low','medium','high') NOT NULL,
    requested_by_user_id BIGINT UNSIGNED NULL,
    lease_token CHAR(64) NULL,
    leased_until DATETIME NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    verification_status ENUM('pending','healthy','unresolved','unknown') NOT NULL DEFAULT 'pending',
    verification_json LONGTEXT NULL,
    error_code VARCHAR(120) NULL,
    error_message VARCHAR(1000) NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_execution_uuid (execution_uuid),
    UNIQUE KEY uq_recovery_execution_idempotency (idempotency_key),
    KEY idx_recovery_execution_worker (status,leased_until,created_at),
    KEY idx_recovery_execution_incident (incident_id,status,created_at),
    KEY idx_recovery_execution_runbook (runbook_id,status,created_at),
    CONSTRAINT fk_recovery_execution_incident
        FOREIGN KEY (incident_id) REFERENCES operations_health_incidents(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_execution_runbook
        FOREIGN KEY (runbook_id) REFERENCES recovery_runbooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_execution_version
        FOREIGN KEY (runbook_version_id) REFERENCES recovery_runbook_versions(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_execution_simulation
        FOREIGN KEY (simulation_id) REFERENCES recovery_simulations(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_execution_approval
        FOREIGN KEY (approval_id) REFERENCES recovery_approvals(id) ON DELETE SET NULL,
    CONSTRAINT fk_recovery_execution_user
        FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recovery_execution_steps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    execution_id BIGINT UNSIGNED NOT NULL,
    step_index SMALLINT UNSIGNED NOT NULL,
    step_key VARCHAR(120) NOT NULL,
    handler_key VARCHAR(120) NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    status ENUM('pending','running','completed','failed','skipped','simulated') NOT NULL DEFAULT 'pending',
    input_json LONGTEXT NOT NULL,
    output_json LONGTEXT NULL,
    lease_token CHAR(64) NULL,
    leased_until DATETIME NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    error_code VARCHAR(120) NULL,
    error_message VARCHAR(1000) NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_execution_step (execution_id,step_index),
    UNIQUE KEY uq_recovery_step_idempotency (idempotency_key),
    KEY idx_recovery_step_worker (status,leased_until,created_at),
    CONSTRAINT fk_recovery_step_execution
        FOREIGN KEY (execution_id) REFERENCES recovery_executions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recovery_action_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_uuid CHAR(36) NOT NULL,
    execution_id BIGINT UNSIGNED NOT NULL,
    step_id BIGINT UNSIGNED NULL,
    action_type VARCHAR(120) NOT NULL,
    status ENUM('proposed','applied','no_change','failed','verified','simulated') NOT NULL,
    before_json LONGTEXT NULL,
    after_json LONGTEXT NULL,
    result_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_receipt_uuid (receipt_uuid),
    UNIQUE KEY uq_recovery_receipt_step (step_id),
    KEY idx_recovery_receipt_execution (execution_id,created_at),
    CONSTRAINT fk_recovery_receipt_execution
        FOREIGN KEY (execution_id) REFERENCES recovery_executions(id) ON DELETE CASCADE,
    CONSTRAINT fk_recovery_receipt_step
        FOREIGN KEY (step_id) REFERENCES recovery_execution_steps(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'North Mountain Media Incident Response Runbooks v66M migration complete' AS migration_status;

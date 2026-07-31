-- North Mountain Media Portal
-- Section 66K: Automation Rules, Routing & Action Center
-- Additive and repeat-safe. Existing source records remain canonical.

CREATE TABLE IF NOT EXISTS automation_settings (
    id TINYINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    dry_run TINYINT(1) NOT NULL DEFAULT 1,
    worker_batch_size SMALLINT UNSIGNED NOT NULL DEFAULT 25,
    approval_expiry_hours SMALLINT UNSIGNED NOT NULL DEFAULT 72,
    event_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    execution_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 365,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_automation_settings_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO automation_settings (id, enabled, dry_run)
VALUES (1, 0, 1);

CREATE TABLE IF NOT EXISTS automation_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_uuid CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status ENUM('draft','active','paused','expired','disabled') NOT NULL DEFAULT 'draft',
    event_key VARCHAR(100) NOT NULL DEFAULT '*',
    source_type VARCHAR(80) NULL,
    priority_order INT UNSIGNED NOT NULL DEFAULT 100,
    stop_processing TINYINT(1) NOT NULL DEFAULT 0,
    condition_mode ENUM('all','any') NOT NULL DEFAULT 'all',
    conditions_json LONGTEXT NOT NULL,
    actions_json LONGTEXT NOT NULL,
    max_executions_per_hour INT UNSIGNED NOT NULL DEFAULT 60,
    max_executions_per_day INT UNSIGNED NOT NULL DEFAULT 500,
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    last_evaluated_at DATETIME NULL,
    last_triggered_at DATETIME NULL,
    execution_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_automation_rules_uuid (rule_uuid),
    KEY idx_automation_rules_match (status,event_key,source_type,priority_order),
    KEY idx_automation_rules_schedule (status,starts_at,expires_at),
    CONSTRAINT fk_automation_rules_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_automation_rules_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rule_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_automation_rule_versions (rule_id,version_number),
    CONSTRAINT fk_automation_rule_versions_rule
        FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE,
    CONSTRAINT fk_automation_rule_versions_user
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uuid CHAR(36) NOT NULL,
    dedupe_key CHAR(64) NOT NULL,
    event_key VARCHAR(100) NOT NULL,
    source_type VARCHAR(80) NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    recipient_user_id BIGINT UNSIGNED NULL,
    category VARCHAR(80) NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    payload_json LONGTEXT NOT NULL,
    occurred_at DATETIME NOT NULL,
    status ENUM('pending','processing','completed','failed','suppressed') NOT NULL DEFAULT 'pending',
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lease_token CHAR(64) NULL,
    leased_until DATETIME NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    matched_rule_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_error_code VARCHAR(100) NULL,
    last_error_message VARCHAR(1000) NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_automation_events_uuid (event_uuid),
    UNIQUE KEY uq_automation_events_dedupe (dedupe_key),
    KEY idx_automation_events_worker (status,available_at,priority,id),
    KEY idx_automation_events_source (source_type,source_id),
    KEY idx_automation_events_recipient (recipient_user_id,created_at),
    CONSTRAINT fk_automation_events_recipient
        FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_executions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    execution_uuid CHAR(36) NOT NULL,
    event_id BIGINT UNSIGNED NULL,
    rule_id BIGINT UNSIGNED NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    status ENUM('no_match','matched','executed','awaiting_approval','partially_executed','failed','suppressed','simulated') NOT NULL,
    matched_json LONGTEXT NULL,
    proposed_actions_json LONGTEXT NULL,
    applied_actions_json LONGTEXT NULL,
    error_code VARCHAR(100) NULL,
    error_message VARCHAR(1000) NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_automation_executions_uuid (execution_uuid),
    UNIQUE KEY uq_automation_executions_idempotency (idempotency_key),
    UNIQUE KEY uq_automation_executions_event_rule (event_id,rule_id),
    KEY idx_automation_executions_status (status,created_at),
    CONSTRAINT fk_automation_executions_event
        FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE SET NULL,
    CONSTRAINT fk_automation_executions_rule
        FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_action_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    execution_id BIGINT UNSIGNED NOT NULL,
    action_index SMALLINT UNSIGNED NOT NULL,
    action_type VARCHAR(80) NOT NULL,
    status ENUM('proposed','applied','skipped','failed','awaiting_approval','approved','rejected','reverted') NOT NULL,
    before_json LONGTEXT NULL,
    after_json LONGTEXT NULL,
    error_code VARCHAR(100) NULL,
    error_message VARCHAR(1000) NULL,
    approved_by_user_id BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_automation_action_receipts (execution_id,action_index),
    KEY idx_automation_action_receipts_status (status,created_at),
    CONSTRAINT fk_automation_action_receipts_execution
        FOREIGN KEY (execution_id) REFERENCES automation_executions(id) ON DELETE CASCADE,
    CONSTRAINT fk_automation_action_receipts_approved_by
        FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_approvals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    approval_uuid CHAR(36) NOT NULL,
    execution_id BIGINT UNSIGNED NOT NULL,
    action_receipt_id BIGINT UNSIGNED NOT NULL,
    approval_type ENUM('homeserver_proposal','consequential_action') NOT NULL DEFAULT 'homeserver_proposal',
    status ENUM('pending','approved','rejected','expired','cancelled','completed','failed') NOT NULL DEFAULT 'pending',
    capability VARCHAR(100) NULL,
    request_hash CHAR(64) NOT NULL,
    request_json LONGTEXT NOT NULL,
    result_json LONGTEXT NULL,
    requested_by_user_id BIGINT UNSIGNED NULL,
    resolved_by_user_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_automation_approvals_uuid (approval_uuid),
    UNIQUE KEY uq_automation_approvals_receipt (action_receipt_id),
    KEY idx_automation_approvals_queue (status,expires_at,created_at),
    CONSTRAINT fk_automation_approvals_execution
        FOREIGN KEY (execution_id) REFERENCES automation_executions(id) ON DELETE CASCADE,
    CONSTRAINT fk_automation_approvals_receipt
        FOREIGN KEY (action_receipt_id) REFERENCES automation_action_receipts(id) ON DELETE CASCADE,
    CONSTRAINT fk_automation_approvals_requested_by
        FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_automation_approvals_resolved_by
        FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rule_counters (
    rule_id BIGINT UNSIGNED NOT NULL,
    window_type ENUM('hour','day') NOT NULL,
    window_start DATETIME NOT NULL,
    execution_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rule_id,window_type,window_start),
    CONSTRAINT fk_automation_rule_counters_rule
        FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
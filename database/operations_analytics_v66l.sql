-- North Mountain Media Portal
-- Section 66L: POD Operations Analytics, Health & Reporting
-- Additive and repeat-safe. Canonical source records remain authoritative.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS operations_analytics_settings (
    id TINYINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    hourly_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    daily_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 730,
    incident_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 730,
    report_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 730,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_operations_analytics_settings_user
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO operations_analytics_settings (id,enabled)
VALUES (1,0);

CREATE TABLE IF NOT EXISTS operations_metric_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_key VARCHAR(120) NOT NULL,
    metric_family VARCHAR(60) NOT NULL,
    window_type ENUM('hour','day') NOT NULL,
    window_started_at DATETIME NOT NULL,
    window_ended_at DATETIME NOT NULL,
    metric_value DECIMAL(24,6) NOT NULL DEFAULT 0,
    sample_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unit VARCHAR(32) NOT NULL DEFAULT 'count',
    source_version VARCHAR(40) NOT NULL DEFAULT 'v66L',
    aggregate_json LONGTEXT NULL,
    collected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operations_metric_window (metric_key,window_type,window_started_at),
    KEY idx_operations_metric_family_window (metric_family,window_type,window_started_at),
    KEY idx_operations_metric_collected (collected_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operations_health_policies (
    check_key VARCHAR(120) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    comparison ENUM('greater_or_equal','less_or_equal') NOT NULL DEFAULT 'greater_or_equal',
    attention_threshold DECIMAL(24,6) NULL,
    degraded_threshold DECIMAL(24,6) NULL,
    critical_threshold DECIMAL(24,6) NULL,
    minimum_sample_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (check_key),
    CONSTRAINT fk_operations_health_policy_user
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operations_health_state (
    check_key VARCHAR(120) NOT NULL,
    metric_key VARCHAR(120) NOT NULL,
    metric_family VARCHAR(60) NOT NULL,
    health_status ENUM('healthy','attention','degraded','critical','unknown') NOT NULL DEFAULT 'unknown',
    reason_code VARCHAR(120) NOT NULL,
    observed_value DECIMAL(24,6) NULL,
    threshold_value DECIMAL(24,6) NULL,
    evidence_json LONGTEXT NULL,
    evaluated_at DATETIME NOT NULL,
    last_changed_at DATETIME NOT NULL,
    first_unhealthy_at DATETIME NULL,
    recovered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (check_key),
    KEY idx_operations_health_status (health_status,evaluated_at),
    KEY idx_operations_health_family (metric_family,health_status,evaluated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operations_health_incidents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    incident_uuid CHAR(36) NOT NULL,
    open_key CHAR(64) NULL,
    check_key VARCHAR(120) NOT NULL,
    metric_key VARCHAR(120) NOT NULL,
    metric_family VARCHAR(60) NOT NULL,
    highest_status ENUM('attention','degraded','critical') NOT NULL,
    reason_code VARCHAR(120) NOT NULL,
    opened_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    recovered_at DATETIME NULL,
    occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
    opening_evidence_json LONGTEXT NULL,
    latest_evidence_json LONGTEXT NULL,
    recovery_evidence_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operations_incident_uuid (incident_uuid),
    UNIQUE KEY uq_operations_incident_open (open_key),
    KEY idx_operations_incident_status (recovered_at,highest_status,last_seen_at),
    KEY idx_operations_incident_family (metric_family,recovered_at,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operations_report_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_uuid CHAR(36) NOT NULL,
    frequency ENUM('daily','weekly','monthly','manual') NOT NULL,
    window_started_at DATETIME NOT NULL,
    window_ended_at DATETIME NOT NULL,
    status ENUM('building','completed','failed','cancelled') NOT NULL DEFAULT 'building',
    summary_json LONGTEXT NULL,
    generated_by_user_id BIGINT UNSIGNED NULL,
    error_code VARCHAR(120) NULL,
    error_message VARCHAR(1000) NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operations_report_uuid (report_uuid),
    UNIQUE KEY uq_operations_report_window (frequency,window_started_at,window_ended_at),
    KEY idx_operations_report_status (status,created_at),
    CONSTRAINT fk_operations_report_user
        FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operations_worker_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_uuid CHAR(36) NOT NULL,
    window_type ENUM('hour','day') NOT NULL,
    window_started_at DATETIME NOT NULL,
    window_ended_at DATETIME NOT NULL,
    status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
    metrics_written INT UNSIGNED NOT NULL DEFAULT 0,
    checks_evaluated INT UNSIGNED NOT NULL DEFAULT 0,
    incidents_opened INT UNSIGNED NOT NULL DEFAULT 0,
    incidents_recovered INT UNSIGNED NOT NULL DEFAULT 0,
    error_code VARCHAR(120) NULL,
    error_message VARCHAR(1000) NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operations_worker_uuid (run_uuid),
    UNIQUE KEY uq_operations_worker_window (window_type,window_started_at),
    KEY idx_operations_worker_status (status,started_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO operations_health_policies
(check_key,comparison,attention_threshold,degraded_threshold,critical_threshold)
VALUES
('notification.queue.depth','greater_or_equal',25,100,500),
('notification.queue.oldest_minutes','greater_or_equal',15,60,240),
('activitypub.delivery.depth','greater_or_equal',25,100,500),
('activitypub.delivery.oldest_minutes','greater_or_equal',15,60,240),
('automation.event.depth','greater_or_equal',25,100,500),
('automation.event.oldest_minutes','greater_or_equal',15,60,240),
('automation.approval.depth','greater_or_equal',5,20,100),
('unified_inbox.needs_response','greater_or_equal',10,50,200);

SELECT 'North Mountain Media Operations Analytics v66L migration complete' AS migration_status;

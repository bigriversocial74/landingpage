SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS notification_delivery_preferences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(100) NOT NULL,
    in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
    email_mode ENUM('off','immediate','digest') NOT NULL DEFAULT 'off',
    push_enabled TINYINT(1) NOT NULL DEFAULT 0,
    homeserver_enabled TINYINT(1) NOT NULL DEFAULT 0,
    include_content_email TINYINT(1) NOT NULL DEFAULT 0,
    include_content_push TINYINT(1) NOT NULL DEFAULT 0,
    include_content_homeserver TINYINT(1) NOT NULL DEFAULT 0,
    minimum_priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    digest_frequency ENUM('hourly','daily','weekly') NOT NULL DEFAULT 'daily',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_delivery_preference (user_id,event_key),
    KEY idx_notification_delivery_preference_user (user_id,updated_at),
    CONSTRAINT fk_notification_delivery_preference_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_quiet_hours (
    user_id BIGINT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    timezone_name VARCHAR(80) NOT NULL DEFAULT 'America/Phoenix',
    start_time TIME NOT NULL DEFAULT '21:00:00',
    end_time TIME NOT NULL DEFAULT '07:00:00',
    weekday_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
    allow_high_priority TINYINT(1) NOT NULL DEFAULT 0,
    allow_urgent_priority TINYINT(1) NOT NULL DEFAULT 1,
    digest_local_time TIME NOT NULL DEFAULT '08:00:00',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_notification_quiet_hours_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_notification_quiet_weekdays CHECK (weekday_mask <= 127)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_delivery_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_type ENUM('vapid') NOT NULL,
    key_version INT UNSIGNED NOT NULL DEFAULT 1,
    public_key VARCHAR(255) NOT NULL,
    private_key_ciphertext MEDIUMTEXT NOT NULL,
    private_key_iv VARCHAR(64) NOT NULL,
    private_key_tag VARCHAR(64) NOT NULL,
    status ENUM('active','retired') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retired_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_delivery_key_version (key_type,key_version),
    KEY idx_notification_delivery_key_active (key_type,status,id),
    CONSTRAINT fk_notification_delivery_key_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subscription_uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    subscription_ciphertext MEDIUMTEXT NOT NULL,
    subscription_iv VARCHAR(64) NOT NULL,
    subscription_tag VARCHAR(64) NOT NULL,
    vapid_key_version INT UNSIGNED NOT NULL DEFAULT 1,
    user_agent_hash CHAR(64) NULL,
    status ENUM('active','expired','revoked','failed') NOT NULL DEFAULT 'active',
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_success_at DATETIME NULL,
    last_failure_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_push_uuid (subscription_uuid),
    UNIQUE KEY uq_notification_push_endpoint (user_id,endpoint_hash),
    KEY idx_notification_push_active (user_id,status,updated_at),
    CONSTRAINT fk_notification_push_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_delivery_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(100) NOT NULL,
    channel ENUM('email','push','homeserver','digest') NOT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    dedupe_key CHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    include_content TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','leased','sent','failed','suppressed','cancelled') NOT NULL DEFAULT 'pending',
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lease_token CHAR(64) NULL,
    leased_until DATETIME NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    last_error_code VARCHAR(100) NULL,
    last_error_message VARCHAR(1000) NULL,
    provider_reference VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_delivery_dedupe (recipient_user_id,channel,dedupe_key),
    KEY idx_notification_delivery_ready (status,available_at,priority,id),
    KEY idx_notification_delivery_notification (notification_id,channel),
    KEY idx_notification_delivery_recipient (recipient_user_id,status,created_at),
    CONSTRAINT fk_notification_delivery_notification
        FOREIGN KEY (notification_id) REFERENCES portal_notifications(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_delivery_recipient
        FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_delivery_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT UNSIGNED NOT NULL,
    status ENUM('started','sent','retry','permanent_failure','suppressed') NOT NULL,
    response_code INT NULL,
    provider_reference VARCHAR(255) NULL,
    error_code VARCHAR(100) NULL,
    error_message VARCHAR(1000) NULL,
    receipt_json JSON NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_delivery_attempt (queue_id,attempt_number),
    KEY idx_notification_delivery_attempt_status (status,started_at),
    CONSTRAINT fk_notification_delivery_attempt_queue
        FOREIGN KEY (queue_id) REFERENCES notification_delivery_queue(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_digest_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    frequency ENUM('hourly','daily','weekly') NOT NULL,
    window_started_at DATETIME NOT NULL,
    window_ended_at DATETIME NOT NULL,
    item_count INT UNSIGNED NOT NULL DEFAULT 0,
    payload_sha256 CHAR(64) NOT NULL,
    status ENUM('building','queued','sent','failed','cancelled') NOT NULL DEFAULT 'building',
    queue_id BIGINT UNSIGNED NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_digest_uuid (batch_uuid),
    UNIQUE KEY uq_notification_digest_window (user_id,frequency,window_started_at,window_ended_at),
    KEY idx_notification_digest_status (status,created_at),
    CONSTRAINT fk_notification_digest_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_digest_queue
        FOREIGN KEY (queue_id) REFERENCES notification_delivery_queue(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key,setting_value) VALUES
    ('notification_delivery_enabled','0'),
    ('notification_email_enabled','0'),
    ('notification_push_enabled','0'),
    ('notification_homeserver_enabled','0'),
    ('notification_email_from',''),
    ('notification_email_from_name','North Mountain Media'),
    ('notification_vapid_subject',''),
    ('notification_worker_batch_size','25'),
    ('notification_max_attempts','5'),
    ('notification_digest_retention_days','90'),
    ('notification_delivery_retention_days','180');

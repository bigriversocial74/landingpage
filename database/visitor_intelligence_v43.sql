SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS visitor_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_token_hash CHAR(64) NOT NULL,
    identified_contact_id BIGINT UNSIGNED NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    identified_at DATETIME NULL,
    first_landing_path VARCHAR(500) NULL,
    first_referrer_url VARCHAR(1000) NULL,
    first_referrer_host VARCHAR(255) NULL,
    first_utm_source VARCHAR(190) NULL,
    first_utm_medium VARCHAR(190) NULL,
    first_utm_campaign VARCHAR(190) NULL,
    last_path VARCHAR(500) NULL,
    last_device_type VARCHAR(40) NULL,
    last_browser_family VARCHAR(80) NULL,
    last_platform VARCHAR(80) NULL,
    last_language VARCHAR(40) NULL,
    last_timezone VARCHAR(100) NULL,
    visit_count INT UNSIGNED NOT NULL DEFAULT 0,
    session_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_events INT UNSIGNED NOT NULL DEFAULT 0,
    total_active_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    opted_out_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_visitor_profiles_token_hash (visitor_token_hash),
    KEY idx_visitor_profiles_contact (identified_contact_id,last_seen_at),
    KEY idx_visitor_profiles_last_seen (last_seen_at),
    CONSTRAINT fk_visitor_profiles_contact
        FOREIGN KEY (identified_contact_id)
        REFERENCES crm_contacts(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_id BIGINT UNSIGNED NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    last_portfolio_project_id BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    landing_path VARCHAR(500) NULL,
    current_path VARCHAR(500) NULL,
    referrer_url VARCHAR(1000) NULL,
    referrer_host VARCHAR(255) NULL,
    utm_source VARCHAR(190) NULL,
    utm_medium VARCHAR(190) NULL,
    utm_campaign VARCHAR(190) NULL,
    utm_term VARCHAR(190) NULL,
    utm_content VARCHAR(190) NULL,
    device_type VARCHAR(40) NULL,
    browser_family VARCHAR(80) NULL,
    platform VARCHAR(80) NULL,
    language VARCHAR(40) NULL,
    timezone_name VARCHAR(100) NULL,
    viewport_width INT UNSIGNED NULL,
    viewport_height INT UNSIGNED NULL,
    page_view_count INT UNSIGNED NOT NULL DEFAULT 0,
    event_count INT UNSIGNED NOT NULL DEFAULT 0,
    active_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_visitor_sessions_token_hash (session_token_hash),
    KEY idx_visitor_sessions_visitor (visitor_id,last_activity_at),
    KEY idx_visitor_sessions_contact (crm_contact_id,last_activity_at),
    KEY idx_visitor_sessions_project (last_portfolio_project_id,last_activity_at),
    KEY idx_visitor_sessions_started (started_at),
    CONSTRAINT fk_visitor_sessions_visitor
        FOREIGN KEY (visitor_id)
        REFERENCES visitor_profiles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_visitor_sessions_contact
        FOREIGN KEY (crm_contact_id)
        REFERENCES crm_contacts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_visitor_sessions_project
        FOREIGN KEY (last_portfolio_project_id)
        REFERENCES portfolio_projects(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uuid CHAR(36) NOT NULL,
    visitor_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    crm_opportunity_id BIGINT UNSIGNED NULL,
    portfolio_project_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(64) NOT NULL,
    event_label VARCHAR(190) NULL,
    page_path VARCHAR(500) NULL,
    target_url VARCHAR(1000) NULL,
    metadata_json LONGTEXT NULL,
    duration_seconds INT UNSIGNED NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    homeserver_exported_at DATETIME NULL,
    export_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_visitor_events_uuid (event_uuid),
    KEY idx_visitor_events_type_time (event_type,occurred_at),
    KEY idx_visitor_events_visitor_time (visitor_id,occurred_at),
    KEY idx_visitor_events_session_time (session_id,occurred_at),
    KEY idx_visitor_events_contact_time (crm_contact_id,occurred_at),
    KEY idx_visitor_events_opportunity_time (crm_opportunity_id,occurred_at),
    KEY idx_visitor_events_project_time (portfolio_project_id,occurred_at),
    KEY idx_visitor_events_homeserver (homeserver_exported_at,id),
    CONSTRAINT fk_visitor_events_visitor
        FOREIGN KEY (visitor_id)
        REFERENCES visitor_profiles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_visitor_events_session
        FOREIGN KEY (session_id)
        REFERENCES visitor_sessions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_visitor_events_contact
        FOREIGN KEY (crm_contact_id)
        REFERENCES crm_contacts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_visitor_events_opportunity
        FOREIGN KEY (crm_opportunity_id)
        REFERENCES crm_opportunities(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_visitor_events_project
        FOREIGN KEY (portfolio_project_id)
        REFERENCES portfolio_projects(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

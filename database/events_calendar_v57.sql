-- North Mountain Media Events & Calendar v57
-- MySQL 5.7+/8.0 and MariaDB 10.3+ compatible

START TRANSACTION;

CREATE TABLE IF NOT EXISTS calendar_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    status ENUM('draft','published','cancelled','completed','archived') NOT NULL DEFAULT 'draft',
    visibility ENUM('public','unlisted','private') NOT NULL DEFAULT 'public',
    event_type ENUM('meeting','webinar','workshop','performance','community','launch','deadline','other') NOT NULL DEFAULT 'other',
    format_type ENUM('in_person','virtual','hybrid') NOT NULL DEFAULT 'in_person',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    start_at DATETIME NOT NULL,
    end_at DATETIME NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Phoenix',
    location_name VARCHAR(190) NULL,
    address_line VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    postal_code VARCHAR(40) NULL,
    virtual_url VARCHAR(500) NULL,
    registration_enabled TINYINT(1) NOT NULL DEFAULT 1,
    capacity SMALLINT UNSIGNED NULL,
    waitlist_enabled TINYINT(1) NOT NULL DEFAULT 1,
    registration_deadline DATETIME NULL,
    price_cents INT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    external_registration_url VARCHAR(500) NULL,
    reminder_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    summary TEXT NULL,
    description MEDIUMTEXT NULL,
    tags TEXT NULL,
    seo_title VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    color_hex CHAR(7) NOT NULL DEFAULT '#26394F',
    cover_original_name VARCHAR(255) NULL,
    cover_stored_name VARCHAR(255) NULL,
    cover_mime_type VARCHAR(100) NULL,
    cover_size_bytes BIGINT UNSIGNED NULL,
    cover_width_px INT UNSIGNED NULL,
    cover_height_px INT UNSIGNED NULL,
    cover_alt_text VARCHAR(500) NULL,
    cover_caption VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calendar_events_slug (slug),
    UNIQUE KEY uq_calendar_events_cover (cover_stored_name),
    KEY idx_calendar_events_public (status,visibility,start_at,featured),
    KEY idx_calendar_events_type_start (event_type,start_at),
    KEY idx_calendar_events_registration (registration_enabled,registration_deadline,start_at),
    CONSTRAINT fk_calendar_events_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_calendar_events_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_event_registrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    display_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(60) NULL,
    company VARCHAR(190) NULL,
    party_size SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('registered','waitlist','confirmed','cancelled','attended','no_show') NOT NULL DEFAULT 'registered',
    source VARCHAR(80) NOT NULL DEFAULT 'public_event',
    notes TEXT NULL,
    confirmation_token CHAR(64) NOT NULL,
    reminder_opt_in TINYINT(1) NOT NULL DEFAULT 1,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    checked_in_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_registration_email (event_id,email),
    UNIQUE KEY uq_event_registration_token (confirmation_token),
    KEY idx_event_registration_status (event_id,status,registered_at),
    KEY idx_event_registration_contact (crm_contact_id,registered_at),
    KEY idx_event_registration_user (user_id,event_id),
    CONSTRAINT fk_event_registration_event
        FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_registration_contact
        FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_event_registration_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_event_reminders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    registration_id BIGINT UNSIGNED NOT NULL,
    reminder_type ENUM('email','in_app') NOT NULL DEFAULT 'email',
    scheduled_for DATETIME NOT NULL,
    status ENUM('pending','ready','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_reminder_registration (registration_id,reminder_type,scheduled_for),
    KEY idx_event_reminder_queue (status,scheduled_for,event_id),
    CONSTRAINT fk_event_reminder_event
        FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_reminder_registration
        FOREIGN KEY (registration_id) REFERENCES calendar_event_registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key,setting_value)
VALUES
    ('events_title','Events'),
    ('events_intro','Upcoming events, sessions, and appearances.'),
    ('events_description','Browse upcoming North Mountain Media events, workshops, performances, meetings, and live sessions.'),
    ('events_default_timezone','America/Phoenix'),
    ('events_default_location','Phoenix, Arizona'),
    ('events_posts_per_page','12'),
    ('events_calendar_start_monday','0'),
    ('events_ics_enabled','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

COMMIT;

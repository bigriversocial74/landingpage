-- North Mountain Media Appointments & Booking v58
-- Build: 20260726-appointments-booking-v58
-- MySQL 5.7+/8.0 and MariaDB 10.3+ compatible
-- Additive migration. Import after database/events_calendar_v57.sql.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS booking_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    description TEXT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    buffer_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    buffer_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    minimum_notice_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    maximum_days_ahead SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    slot_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    confirmation_mode ENUM('request','automatic') NOT NULL DEFAULT 'request',
    location_mode ENUM('phone','video','in_person','client_choice') NOT NULL DEFAULT 'video',
    default_location VARCHAR(255) NULL,
    default_meeting_url VARCHAR(500) NULL,
    create_opportunity TINYINT(1) NOT NULL DEFAULT 1,
    opportunity_type VARCHAR(120) NULL,
    color_hex CHAR(7) NOT NULL DEFAULT '#26394F',
    sort_order INT NOT NULL DEFAULT 100,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_types_slug (slug),
    KEY idx_booking_types_public (status,sort_order,id),
    KEY idx_booking_types_owner (owner_user_id,status),
    CONSTRAINT fk_booking_types_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_types_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_types_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_availability_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(190) NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    booking_type_id BIGINT UNSIGNED NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Phoenix',
    valid_from DATE NULL,
    valid_until DATE NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_availability_rule_key (rule_key),
    KEY idx_booking_availability_day (active,day_of_week,start_time,end_time),
    KEY idx_booking_availability_type (booking_type_id,active,day_of_week),
    KEY idx_booking_availability_owner (owner_user_id,active,day_of_week),
    CONSTRAINT fk_booking_availability_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_availability_type
        FOREIGN KEY (booking_type_id) REFERENCES booking_types(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_availability_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_availability_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_blackouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Phoenix',
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking_blackouts_range (start_at,end_at),
    KEY idx_booking_blackouts_owner (owner_user_id,start_at,end_at),
    CONSTRAINT fk_booking_blackouts_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_blackouts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_blackouts_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_day_locks (
    owner_user_id BIGINT UNSIGNED NOT NULL,
    local_date DATE NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (owner_user_id,local_date),
    CONSTRAINT fk_booking_day_locks_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_type_id BIGINT UNSIGNED NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    crm_opportunity_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    status ENUM('requested','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'requested',
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Phoenix',
    location_mode ENUM('phone','video','in_person') NOT NULL DEFAULT 'video',
    location_details VARCHAR(500) NULL,
    meeting_url VARCHAR(500) NULL,
    display_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(60) NULL,
    company VARCHAR(190) NULL,
    subject VARCHAR(190) NULL,
    notes TEXT NULL,
    admin_notes TEXT NULL,
    confirmation_token CHAR(64) NOT NULL,
    reminder_opt_in TINYINT(1) NOT NULL DEFAULT 1,
    source VARCHAR(80) NOT NULL DEFAULT 'public_booking',
    reschedule_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    previous_start_at DATETIME NULL,
    previous_end_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    no_show_at DATETIME NULL,
    rescheduled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_appointments_token (confirmation_token),
    KEY idx_appointments_schedule (owner_user_id,start_at,end_at,status),
    KEY idx_appointments_type_schedule (booking_type_id,start_at,status),
    KEY idx_appointments_contact (crm_contact_id,start_at),
    KEY idx_appointments_opportunity (crm_opportunity_id),
    KEY idx_appointments_email (email,start_at),
    CONSTRAINT fk_appointments_type
        FOREIGN KEY (booking_type_id) REFERENCES booking_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_contact
        FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_appointments_opportunity
        FOREIGN KEY (crm_opportunity_id) REFERENCES crm_opportunities(id) ON DELETE SET NULL,
    CONSTRAINT fk_appointments_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_reminders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id BIGINT UNSIGNED NOT NULL,
    reminder_type ENUM('email','in_app') NOT NULL DEFAULT 'email',
    scheduled_for DATETIME NOT NULL,
    status ENUM('pending','ready','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_appointment_reminder (appointment_id,reminder_type,scheduled_for),
    KEY idx_appointment_reminder_queue (status,scheduled_for,appointment_id),
    CONSTRAINT fk_appointment_reminder_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key,setting_value)
VALUES
    ('bookings_enabled','0'),
    ('bookings_title','Book a Meeting'),
    ('bookings_intro','Choose an available time to talk about your project.'),
    ('bookings_description','Schedule a consultation, project review, product demonstration, or support session with North Mountain Media.'),
    ('bookings_default_timezone','America/Phoenix'),
    ('bookings_default_location','Phoenix, Arizona'),
    ('bookings_reminder_hours','24'),
    ('bookings_public_window_days','60'),
    ('bookings_sidebar_label','Bookings'),
    ('bookings_calendar_conflicts','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO booking_types
    (name,slug,status,description,duration_minutes,buffer_before_minutes,
     buffer_after_minutes,minimum_notice_hours,maximum_days_ahead,
     slot_interval_minutes,confirmation_mode,location_mode,default_location,
     create_opportunity,opportunity_type,color_hex,sort_order)
VALUES
    ('Consultation','consultation','active',
     'A focused conversation about goals, requirements, scope, and next steps.',
     30,0,15,24,60,30,'request','video',NULL,1,'Consultation','#26394F',10),
    ('Project Review','project-review','active',
     'Review an active project, workflow, prototype, or implementation plan.',
     45,0,15,24,60,15,'request','video',NULL,1,'Project Review','#0B8588',20),
    ('Product Demo','product-demo','active',
     'Walk through a product, platform, or working system demonstration.',
     45,0,15,24,60,15,'request','video',NULL,1,'Product Demo','#6D4FC2',30),
    ('Support Session','support-session','active',
     'Book time for troubleshooting, implementation support, or follow-up.',
     30,0,15,12,45,15,'request','client_choice',NULL,0,'Support','#9A5A22',40)
ON DUPLICATE KEY UPDATE slug=VALUES(slug);

INSERT INTO booking_availability_rules
    (rule_key,owner_user_id,booking_type_id,day_of_week,start_time,end_time,
     timezone,active,sort_order)
VALUES
    ('default-monday-0900-1700',NULL,NULL,1,'09:00:00','17:00:00','America/Phoenix',1,10),
    ('default-tuesday-0900-1700',NULL,NULL,2,'09:00:00','17:00:00','America/Phoenix',1,20),
    ('default-wednesday-0900-1700',NULL,NULL,3,'09:00:00','17:00:00','America/Phoenix',1,30),
    ('default-thursday-0900-1700',NULL,NULL,4,'09:00:00','17:00:00','America/Phoenix',1,40),
    ('default-friday-0900-1500',NULL,NULL,5,'09:00:00','15:00:00','America/Phoenix',1,50)
ON DUPLICATE KEY UPDATE rule_key=VALUES(rule_key);

COMMIT;

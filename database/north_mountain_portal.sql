SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role ENUM('admin', 'client') NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    company VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    profile_image_stored_name VARCHAR(255) NULL,
    profile_image_mime VARCHAR(100) NULL,
    profile_image_updated_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    billing_name VARCHAR(190) NULL,
    address_line_1 VARCHAR(190) NULL,
    address_line_2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    state_region VARCHAR(120) NULL,
    postal_code VARCHAR(40) NULL,
    country VARCHAR(100) NULL,
    private_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_client_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    company VARCHAR(190) NULL,
    opportunity VARCHAR(120) NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'contacted', 'qualified', 'converted', 'closed') NOT NULL DEFAULT 'new',
    source VARCHAR(80) NOT NULL DEFAULT 'website',
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_leads_status_created (status, created_at),
    KEY idx_leads_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NULL,
    display_name VARCHAR(160) NOT NULL,
    company VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    lifecycle_stage ENUM('lead', 'prospect', 'qualified', 'client', 'partner', 'closed') NOT NULL DEFAULT 'lead',
    source VARCHAR(80) NOT NULL DEFAULT 'website_contact',
    owner_user_id BIGINT UNSIGNED NULL,
    client_user_id BIGINT UNSIGNED NULL,
    last_inquiry_at DATETIME NULL,
    last_contacted_at DATETIME NULL,
    next_follow_up_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_crm_contacts_email (email),
    KEY idx_crm_contacts_stage_updated (lifecycle_stage, updated_at),
    KEY idx_crm_contacts_owner_follow_up (owner_user_id, next_follow_up_at),
    CONSTRAINT fk_crm_contacts_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_crm_contacts_client
        FOREIGN KEY (client_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_opportunities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id BIGINT UNSIGNED NOT NULL,
    lead_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    opportunity_type VARCHAR(120) NULL,
    stage ENUM('new', 'reviewing', 'contacted', 'qualified', 'proposal', 'won', 'lost') NOT NULL DEFAULT 'new',
    estimated_value DECIMAL(12,2) NULL,
    probability TINYINT UNSIGNED NOT NULL DEFAULT 10,
    next_action VARCHAR(255) NULL,
    next_action_at DATETIME NULL,
    source VARCHAR(80) NOT NULL DEFAULT 'website_contact',
    message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_crm_opportunities_lead (lead_id),
    KEY idx_crm_opportunities_contact_stage (contact_id, stage),
    KEY idx_crm_opportunities_next_action (next_action_at),
    CONSTRAINT fk_crm_opportunities_contact
        FOREIGN KEY (contact_id) REFERENCES crm_contacts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crm_opportunities_lead
        FOREIGN KEY (lead_id) REFERENCES leads(id)
        ON DELETE SET NULL,
    CONSTRAINT chk_crm_opportunity_probability CHECK (probability <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_activities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id BIGINT UNSIGNED NOT NULL,
    opportunity_id BIGINT UNSIGNED NULL,
    admin_user_id BIGINT UNSIGNED NULL,
    activity_type ENUM('inquiry', 'note', 'email', 'call', 'meeting', 'status_change', 'conversion', 'system') NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crm_activities_contact_created (contact_id, created_at),
    KEY idx_crm_activities_opportunity (opportunity_id),
    CONSTRAINT fk_crm_activities_contact
        FOREIGN KEY (contact_id) REFERENCES crm_contacts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crm_activities_opportunity
        FOREIGN KEY (opportunity_id) REFERENCES crm_opportunities(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_crm_activities_admin
        FOREIGN KEY (admin_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    summary TEXT NULL,
    status ENUM('discovery', 'planning', 'active', 'review', 'on_hold', 'completed', 'archived') NOT NULL DEFAULT 'planning',
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    start_date DATE NULL,
    due_date DATE NULL,
    budget DECIMAL(12,2) NULL,
    next_milestone VARCHAR(255) NULL,
    next_milestone_date DATE NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_slug (slug),
    KEY idx_projects_client_status (client_user_id, status),
    CONSTRAINT fk_projects_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_projects_progress CHECK (progress <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    visibility ENUM('client', 'admin') NOT NULL DEFAULT 'client',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_updates_project_created (project_id, created_at),
    CONSTRAINT fk_project_updates_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_updates_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    project_url VARCHAR(500) NULL,
    project_url_label VARCHAR(120) NOT NULL DEFAULT 'View project',
    client_name VARCHAR(190) NULL,
    project_type VARCHAR(190) NULL,
    industry VARCHAR(190) NULL,
    year_label VARCHAR(80) NULL,
    role_title VARCHAR(255) NULL,
    services TEXT NULL,
    technologies TEXT NULL,
    summary TEXT NULL,
    overview MEDIUMTEXT NULL,
    challenge MEDIUMTEXT NULL,
    solution MEDIUMTEXT NULL,
    results MEDIUMTEXT NULL,
    keywords TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_portfolio_projects_slug (slug),
    KEY idx_portfolio_projects_public (status,featured,sort_order,published_at),
    CONSTRAINT fk_portfolio_projects_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_portfolio_projects_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    media_role ENUM('cover','gallery') NOT NULL DEFAULT 'gallery',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    width_px INT UNSIGNED NULL,
    height_px INT UNSIGNED NULL,
    alt_text VARCHAR(500) NULL,
    caption VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_portfolio_media_stored_name (stored_name),
    KEY idx_portfolio_media_project_role (project_id,media_role,sort_order,id),
    CONSTRAINT fk_portfolio_media_project
        FOREIGN KEY (project_id) REFERENCES portfolio_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_portfolio_media_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    sender_type ENUM('admin', 'client', 'system') NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    is_read_by_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_read_by_client TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_client_created (client_user_id, created_at),
    KEY idx_messages_admin_unread (is_read_by_admin, created_at),
    KEY idx_messages_client_unread (client_user_id, is_read_by_client, created_at),
    CONSTRAINT fk_messages_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    description VARCHAR(500) NULL,
    visibility ENUM('client', 'admin') NOT NULL DEFAULT 'client',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_files_stored_name (stored_name),
    KEY idx_files_client_created (client_user_id, created_at),
    KEY idx_files_project (project_id),
    CONSTRAINT fk_files_client FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_files_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(64) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_email_created (email, created_at),
    KEY idx_login_attempts_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS rate_limit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_key VARCHAR(100) NOT NULL,
    identity_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rate_limit_lookup (action_key, identity_hash, created_at),
    KEY idx_rate_limit_cleanup (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_created (created_at),
    KEY idx_activity_user (user_id),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id VARCHAR(190) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    cover_stored_name VARCHAR(255) NULL,
    cover_extension VARCHAR(20) NULL,
    cover_mime_type VARCHAR(190) NULL,
    cover_size_bytes BIGINT UNSIGNED NULL,
    cover_sha256 CHAR(64) NULL,
    extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    media_kind ENUM('document', 'image', 'audio', 'video', 'data') NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'uploaded-knowledge',
    summary TEXT NULL,
    keywords TEXT NULL,
    audiences_json JSON NULL,
    extracted_text LONGTEXT NULL,
    extraction_method VARCHAR(80) NULL,
    extraction_status ENUM('ready', 'needs_text', 'error') NOT NULL DEFAULT 'needs_text',
    extraction_error TEXT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    uploaded_by BIGINT UNSIGNED NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_knowledge_assets_stored_name (stored_name),
    UNIQUE KEY uq_knowledge_assets_entry_id (entry_id),
    KEY idx_knowledge_assets_status_updated (status, updated_at),
    KEY idx_knowledge_assets_kind (media_kind),
    CONSTRAINT fk_knowledge_assets_uploaded_by
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_transcription_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id BIGINT UNSIGNED NOT NULL,
    status ENUM('queued', 'processing', 'review', 'approved', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    provider VARCHAR(60) NOT NULL DEFAULT 'openai',
    model VARCHAR(120) NOT NULL,
    language VARCHAR(20) NULL,
    prompt TEXT NULL,
    speaker_diarization TINYINT(1) NOT NULL DEFAULT 0,
    raw_transcript_text LONGTEXT NULL,
    reviewed_transcript_text LONGTEXT NULL,
    segments_json JSON NULL,
    usage_json JSON NULL,
    response_json JSON NULL,
    error_message TEXT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    requested_by BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_transcription_jobs_queue (status, next_attempt_at, queued_at),
    KEY idx_transcription_jobs_asset_created (asset_id, created_at),
    CONSTRAINT fk_transcription_jobs_asset
        FOREIGN KEY (asset_id) REFERENCES knowledge_assets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_transcription_jobs_requested_by
        FOREIGN KEY (requested_by) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_transcription_jobs_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    legacy_key VARCHAR(190) NULL,
    client_user_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    assigned_admin_user_id BIGINT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    status ENUM('open', 'waiting_admin', 'waiting_client', 'resolved', 'archived') NOT NULL DEFAULT 'open',
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    last_message_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_communication_threads_legacy_key (legacy_key),
    KEY idx_communication_threads_client_status (client_user_id, status, last_message_at),
    KEY idx_communication_threads_admin_status (assigned_admin_user_id, status, last_message_at),
    KEY idx_communication_threads_project (project_id),
    KEY idx_communication_threads_crm (crm_contact_id),
    CONSTRAINT fk_communication_threads_client
        FOREIGN KEY (client_user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_communication_threads_crm
        FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_threads_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_threads_admin
        FOREIGN KEY (assigned_admin_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_threads_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    media_kind ENUM('document', 'image', 'audio', 'video', 'data') NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    duration_seconds DECIMAL(12,3) NULL,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_communication_attachments_stored_name (stored_name),
    KEY idx_communication_attachments_thread_created (thread_id, created_at),
    CONSTRAINT fk_communication_attachments_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_communication_attachments_uploaded_by
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_calls (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT UNSIGNED NOT NULL,
    initiator_user_id BIGINT UNSIGNED NOT NULL,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('ringing', 'accepted', 'declined', 'missed', 'ended', 'failed', 'cancelled') NOT NULL DEFAULT 'ringing',
    recording_status ENUM('not_requested', 'requested', 'consented', 'recording', 'available', 'declined', 'failed') NOT NULL DEFAULT 'not_requested',
    initiator_recording_consent ENUM('not_requested', 'pending', 'granted', 'declined') NOT NULL DEFAULT 'not_requested',
    recipient_recording_consent ENUM('not_requested', 'pending', 'granted', 'declined') NOT NULL DEFAULT 'not_requested',
    recording_requested_by BIGINT UNSIGNED NULL,
    recording_attachment_id BIGINT UNSIGNED NULL,
    ringing_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    answered_at DATETIME NULL,
    ended_at DATETIME NULL,
    duration_seconds INT UNSIGNED NULL,
    end_reason VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communication_calls_thread_created (thread_id, created_at),
    KEY idx_communication_calls_recipient_status (recipient_user_id, status, expires_at),
    CONSTRAINT fk_communication_calls_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_communication_calls_initiator
        FOREIGN KEY (initiator_user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_communication_calls_recipient
        FOREIGN KEY (recipient_user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_communication_calls_recording_requested_by
        FOREIGN KEY (recording_requested_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_calls_recording_attachment
        FOREIGN KEY (recording_attachment_id) REFERENCES communication_attachments(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_transcripts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT UNSIGNED NOT NULL,
    source_attachment_id BIGINT UNSIGNED NULL,
    call_id BIGINT UNSIGNED NULL,
    source_type ENUM('voice_message', 'call_recording', 'manual') NOT NULL,
    status ENUM('draft', 'review', 'approved', 'archived') NOT NULL DEFAULT 'draft',
    raw_text LONGTEXT NULL,
    reviewed_text LONGTEXT NULL,
    shared_with_client TINYINT(1) NOT NULL DEFAULT 0,
    reviewed_by BIGINT UNSIGNED NULL,
    knowledge_asset_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communication_transcripts_thread_status (thread_id, status, updated_at),
    KEY idx_communication_transcripts_attachment (source_attachment_id),
    KEY idx_communication_transcripts_call (call_id),
    CONSTRAINT fk_communication_transcripts_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_communication_transcripts_attachment
        FOREIGN KEY (source_attachment_id) REFERENCES communication_attachments(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_transcripts_call
        FOREIGN KEY (call_id) REFERENCES communication_calls(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_transcripts_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_transcripts_knowledge_asset
        FOREIGN KEY (knowledge_asset_id) REFERENCES knowledge_assets(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    legacy_message_id BIGINT UNSIGNED NULL,
    thread_id BIGINT UNSIGNED NOT NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    sender_role ENUM('admin', 'client', 'system') NOT NULL,
    message_type ENUM('text', 'voice', 'file', 'call_event', 'call_recording', 'transcript', 'internal_note', 'system') NOT NULL DEFAULT 'text',
    body LONGTEXT NULL,
    attachment_id BIGINT UNSIGNED NULL,
    call_id BIGINT UNSIGNED NULL,
    transcript_id BIGINT UNSIGNED NULL,
    visibility ENUM('client', 'admin') NOT NULL DEFAULT 'client',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_communication_messages_legacy (legacy_message_id),
    KEY idx_communication_messages_thread_created (thread_id, id),
    KEY idx_communication_messages_attachment (attachment_id),
    KEY idx_communication_messages_call (call_id),
    KEY idx_communication_messages_transcript (transcript_id),
    CONSTRAINT fk_communication_messages_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_communication_messages_sender
        FOREIGN KEY (sender_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_messages_attachment
        FOREIGN KEY (attachment_id) REFERENCES communication_attachments(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_messages_call
        FOREIGN KEY (call_id) REFERENCES communication_calls(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_communication_messages_transcript
        FOREIGN KEY (transcript_id) REFERENCES communication_transcripts(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_thread_members (
    thread_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    member_role ENUM('admin', 'client') NOT NULL,
    last_read_message_id BIGINT UNSIGNED NULL,
    last_read_at DATETIME NULL,
    notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id, user_id),
    KEY idx_communication_members_user (user_id, thread_id),
    CONSTRAINT fk_communication_members_thread
        FOREIGN KEY (thread_id) REFERENCES communication_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_communication_members_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_communication_members_last_read
        FOREIGN KEY (last_read_message_id) REFERENCES communication_messages(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_call_signals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_id BIGINT UNSIGNED NOT NULL,
    sender_user_id BIGINT UNSIGNED NOT NULL,
    signal_type ENUM('offer', 'answer', 'ice', 'hangup') NOT NULL,
    payload_json JSON NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communication_signals_call_id (call_id, id),
    KEY idx_communication_signals_cleanup (created_at),
    CONSTRAINT fk_communication_signals_call
        FOREIGN KEY (call_id) REFERENCES communication_calls(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_communication_signals_sender
        FOREIGN KEY (sender_user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    category ENUM('call','message','contact','transcript','project','system') NOT NULL DEFAULT 'system',
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    link_url VARCHAR(500) NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_portal_notifications_recipient_read (recipient_user_id,is_read,created_at),
    KEY idx_portal_notifications_entity (entity_type,entity_id),
    CONSTRAINT fk_portal_notifications_recipient
        FOREIGN KEY (recipient_user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS call_center_greetings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    duration_seconds DECIMAL(12,3) NULL,
    sha256 CHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_call_center_greetings_stored_name (stored_name),
    KEY idx_call_center_greetings_active_updated (is_active,updated_at),
    CONSTRAINT fk_call_center_greetings_admin
        FOREIGN KEY (admin_user_id) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_center_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source ENUM('client','public','admin') NOT NULL,
    request_type ENUM('call_request','live_call','callback','voicemail') NOT NULL DEFAULT 'call_request',
    client_user_id BIGINT UNSIGNED NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    communication_thread_id BIGINT UNSIGNED NULL,
    communication_call_id BIGINT UNSIGNED NULL,
    assigned_admin_user_id BIGINT UNSIGNED NULL,
    requested_by_user_id BIGINT UNSIGNED NULL,
    guest_name VARCHAR(160) NULL,
    guest_email VARCHAR(190) NULL,
    guest_phone VARCHAR(60) NULL,
    guest_company VARCHAR(190) NULL,
    subject VARCHAR(190) NOT NULL,
    message TEXT NULL,
    preferred_at DATETIME NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('new','queued','scheduled','ringing','accepted','completed','missed','declined','cancelled','failed','voicemail','resolved','spam') NOT NULL DEFAULT 'new',
    message_stage ENUM('new','listened','follow_up','resolved','archived') NOT NULL DEFAULT 'new',
    listened_at DATETIME NULL,
    message_stage_updated_at DATETIME NULL,
    message_stage_updated_by_user_id BIGINT UNSIGNED NULL,
    disposition ENUM('unassigned','connected','callback_scheduled','left_message','no_answer','not_available','declined','resolved','spam') NOT NULL DEFAULT 'unassigned',
    public_token_hash CHAR(64) NULL,
    token_expires_at DATETIME NULL,
    expires_at DATETIME NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    queued_at DATETIME NULL,
    first_response_at DATETIME NULL,
    ringing_at DATETIME NULL,
    answered_at DATETIME NULL,
    ended_at DATETIME NULL,
    duration_seconds INT UNSIGNED NULL,
    last_contact_at DATETIME NULL,
    guest_heartbeat_at DATETIME NULL,
    admin_heartbeat_at DATETIME NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    admin_notes LONGTEXT NULL,
    transcript_text LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_call_center_public_token_hash (public_token_hash),
    UNIQUE KEY uq_call_center_communication_call (communication_call_id),
    KEY idx_call_center_queue (status,priority,requested_at),
    KEY idx_call_center_assigned (assigned_admin_user_id,status,requested_at),
    KEY idx_call_center_contact (crm_contact_id,requested_at),
    KEY idx_call_center_message_stage (crm_contact_id,message_stage,requested_at),
    KEY idx_call_center_client (client_user_id,requested_at),
    KEY idx_call_center_public_expiry (status,expires_at),
    CONSTRAINT fk_call_center_client
        FOREIGN KEY (client_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_call_center_contact
        FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_call_center_thread
        FOREIGN KEY (communication_thread_id) REFERENCES communication_threads(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_call_center_communication_call
        FOREIGN KEY (communication_call_id) REFERENCES communication_calls(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_call_center_admin
        FOREIGN KEY (assigned_admin_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_call_center_requested_by
        FOREIGN KEY (requested_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_center_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    media_type ENUM('voicemail','call_recording') NOT NULL DEFAULT 'voicemail',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    duration_seconds DECIMAL(12,3) NULL,
    sha256 CHAR(64) NOT NULL,
    transcript_status ENUM(
        'not_requested','queued','processing','review','approved','failed'
    ) NOT NULL DEFAULT 'not_requested',
    transcription_source ENUM('manual','local','imported') NULL,
    raw_transcript_text LONGTEXT NULL,
    reviewed_transcript_text LONGTEXT NULL,
    transcription_error TEXT NULL,
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_call_center_media_stored_name (stored_name),
    KEY idx_call_center_media_request_created (request_id,created_at),
    KEY idx_call_center_media_transcript_status (transcript_status,updated_at),
    CONSTRAINT fk_call_center_media_request
        FOREIGN KEY (request_id) REFERENCES call_center_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_call_center_media_uploaded_by
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_call_center_media_reviewed_by
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_center_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    notes TEXT NULL,
    metadata_json JSON NULL,
    event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_call_center_events_request_time (request_id,event_at),
    CONSTRAINT fk_call_center_events_request
        FOREIGN KEY (request_id) REFERENCES call_center_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_call_center_events_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_center_signals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    sender_side ENUM('guest','admin') NOT NULL,
    signal_type ENUM('offer','answer','ice','hangup') NOT NULL,
    payload_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_call_center_signals_request (request_id,id),
    KEY idx_call_center_signals_cleanup (created_at),
    CONSTRAINT fk_call_center_signals_request
        FOREIGN KEY (request_id) REFERENCES call_center_requests(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_contact_call_stats (
    contact_id BIGINT UNSIGNED NOT NULL,
    total_requests INT UNSIGNED NOT NULL DEFAULT 0,
    total_calls INT UNSIGNED NOT NULL DEFAULT 0,
    completed_calls INT UNSIGNED NOT NULL DEFAULT 0,
    missed_calls INT UNSIGNED NOT NULL DEFAULT 0,
    declined_calls INT UNSIGNED NOT NULL DEFAULT 0,
    total_duration_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    average_response_seconds INT UNSIGNED NULL,
    first_call_at DATETIME NULL,
    last_call_at DATETIME NULL,
    last_call_status VARCHAR(40) NULL,
    last_call_source VARCHAR(40) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (contact_id),
    CONSTRAINT fk_crm_contact_call_stats_contact
        FOREIGN KEY (contact_id) REFERENCES crm_contacts(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value)
VALUES
    ('site_name', 'North Mountain Media'),
    ('portal_welcome', 'Project updates, secure communications, voice notes, calls, and shared files in one place.'),
    ('public_call_status', 'available'),
    ('public_call_message', 'Dave is accepting browser audio calls.')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO portfolio_projects (
    title,slug,status,featured,sort_order,project_url,project_url_label,
    client_name,project_type,industry,year_label,role_title,
    services,technologies,summary,overview,challenge,solution,results,
    keywords,published_at
) VALUES
(
    'Gruber Procurement Intelligence Platform',
    'gruber',
    'active',
    1,
    10,
    'https://northmountainmedia.com/gruber',
    'View the Gruber platform',
    'Self-directed portfolio demonstration',
    'Procurement Intelligence Platform',
    'Procurement and multi-company operations',
    '2026',
    'Product strategy, workflow architecture, interface design and implementation',
    'Opportunity analysis\nWorkflow mapping\nInformation architecture\nInterface design\nPHP/MySQL implementation\nQuality assurance',
    'PHP\nMySQL\nProcurement workflows\nSupplier data\nInventory\nExecutive reporting\nHuman-supervised AI',
    'A connected procurement operating environment that turns fragmented supplier, SKU, inventory, purchasing, approval and savings information into a shared decision-ready system.',
    'The platform brings supplier records, item and SKU masters, purchase orders, inventory snapshots, savings opportunities, scorecards, approvals, audit history and executive reporting into one shared environment.',
    'Purchasing information can become fragmented across companies, departments, spreadsheets, supplier records, item masters, inventory systems, approvals and reporting. This makes savings opportunities, supplier risk, data quality and next actions harder to see.',
    'A connected procurement environment with shared masters, purchase orders, inventory snapshots, savings tracking, supplier scorecards, approvals, audit history, executive reporting and human-supervised AI assistance.',
    'A working portfolio demonstration showing how procurement teams could operate from a more visible, accountable and decision-ready system. It is a self-directed demonstration rather than a claimed deployed Gruber production system.',
    'gruber\nprocurement\nsupplier management\npurchase orders\ninventory\nsavings\nscorecards\nbusiness intelligence',
    UTC_TIMESTAMP()
),
(
    'Microgifter',
    'microgifter',
    'active',
    1,
    20,
    'https://microgifter.com/',
    'View Microgifter',
    'Microgifter',
    'Social Gifting and Merchant CRM Platform',
    'Hospitality, local commerce and customer engagement',
    '2024–Present',
    'Founder, product architect and systems builder',
    'Product strategy\nMerchant CRM\nSocial gifting\nCampaign architecture\nLifecycle design\nUI/UX\nPHP/MySQL\nQA and release planning',
    'PHP\nMySQL\nJavaScript\nMerchant CRM\nCampaign automation\nGift lifecycle tracking\nAgent-assisted commerce',
    'A mobile-first social-gifting, merchant CRM, loyalty, campaign, claim and automated-commerce platform designed to make local gifting easier.',
    'Microgifter connects gift certificates and product gifting with merchant CRM records, campaigns, offers, rewards, referrals, customer messaging, claim and redemption tracking, recurring programs and agent-assisted commerce.',
    'Local customers often fall back on national brands because independent business gifts are difficult to discover, purchase, send and manage. Merchants also do not want more hardware or disconnected marketing, loyalty, CRM and redemption systems.',
    'One connected mobile-first platform for social gifting, merchant CRM, campaigns, rewards, referrals, messaging, claims, redemption and automated commerce.',
    'A functional and continually expanding platform demonstrating how local gifting can become a measurable customer-lifecycle and commerce system rather than a standalone gift certificate.',
    'microgifter\nsocial gifting\nmerchant crm\nloyalty\ncampaigns\nclaims\nredemption\nautomated commerce',
    UTC_TIMESTAMP()
),
(
    'Homestead',
    'homestead',
    'active',
    0,
    30,
    'https://github.com/bigriversocial74/foodfarm',
    'View Homestead repository',
    'North Mountain Media',
    'Household Food Operating System',
    'Household operations and sustainable domestic agriculture',
    '2026',
    'Product concept, requirements, data architecture, privacy and QA',
    'Product requirements\nData modeling\nHousehold workflows\nPrivacy architecture\nInterface direction\nPhased delivery\nRepository QA',
    'PHP\nMySQL\nHousehold permissions\nInventory ledgers\nForecasting\nNotifications\nShared calendar',
    'A household food operating system connecting family access, pantry inventory, recipes, meals, gardens, harvests, preservation, shopping, tasks, forecasting, costs, nutrition and alerts.',
    'Homestead organizes the complete domestic food lifecycle through connected household records instead of isolated pantry lists, recipes, garden schedules and shopping notes.',
    'Household food management is fragmented across recipes, pantry lists, shopping notes, garden schedules, preservation records, family responsibilities, budgets and calendars.',
    'One lifecycle-based system connecting members, inventory, recipes, prepared food, meals, gardens, harvests, preservation, shopping, tasks, forecasts, costs, nutrition planning, alerts and calendar activity.',
    'A multi-phase PHP/MySQL application with household isolation, transactional workflows, provenance, idempotency, protected health checks and an expanding operational feature set.',
    'homestead\nfoodfarm\npantry\ngarden\npreservation\nfamily operations\nforecasting\nhousehold system',
    UTC_TIMESTAMP()
),
(
    'Poolzebo',
    'poolzebo',
    'active',
    0,
    40,
    'https://northmountainmedia.com/pool',
    'View Poolzebo',
    'North Mountain Media',
    'Modular Product and Outdoor-Living System',
    'Outdoor living, modular construction and direct sales',
    '2026',
    'Concept development, product-system design, positioning and web experience',
    'Product concept\nModular configuration\nBrand positioning\nExperience design\nWeb design\nSales presentation',
    'PHP\nResponsive web design\nProduct visualization\nModular configuration\nLead generation',
    'A modular backyard pool-and-deck system combining repeatable kit models with larger custom outdoor-living configurations.',
    'Poolzebo is designed around the complete backyard experience rather than a standalone deck or gazebo. Smaller versions can work as repeatable kit models while larger builds can add extended roofs, lounges, bars and different pool placements.',
    'Traditional backyard pool, deck and gazebo purchases are fragmented across contractors, components, planning and installation decisions.',
    'A coordinated modular product system that packages pool, deck, shade, lounge and optional bar experiences into clearer kit and custom configurations.',
    'A differentiated product and brand concept with a direct positioning idea: vacation starts in the backyard.',
    'poolzebo\npool deck\nmodular backyard\noutdoor living\nkit system\ncustom pool',
    UTC_TIMESTAMP()
),
(
    'Spaced Invaders',
    'spaced-invaders',
    'active',
    0,
    50,
    'https://northmountainmedia.com/space',
    'Play Spaced Invaders',
    'North Mountain Media',
    'Browser Strategy and Defense Game',
    'Interactive entertainment and game systems',
    '2026',
    'Game concept, systems design, interface direction and simulation logic',
    'Game design\nSimulation systems\nEnemy intelligence\nDefense balancing\nInterface design\nProgression design',
    'PHP\nJavaScript\nBrowser simulation\nGame-state systems\nResponsive UI',
    'A browser-based settlement defense game featuring intelligent UFO attacks, missile defenses, drone swarms, captures, settlement progression and operational command views.',
    'Spaced Invaders combines settlement management with a live alien-defense simulation. UFOs maneuver around defenses, require multiple hits, launch drone swarms and create persistent settlement outcomes.',
    'Simple invader games often rely on predictable movement and disconnected scorekeeping, which limits strategy and long-term progression.',
    'A richer simulation with adaptive UFO behavior, layered defenses, settlement-level statistics, capture systems, command feeds and tabbed operational views.',
    'A playable game concept demonstrating interactive systems design, state tracking, balancing, responsive interface work and iterative simulation refinement.',
    'spaced invaders\nbrowser game\nufo\nsettlement defense\nmissile defense\ndrone swarm\ngame systems',
    UTC_TIMESTAMP()
),
(
    'Stonefellow',
    'stonefellow',
    'active',
    0,
    60,
    'https://stonefellow.com/',
    'View Stonefellow',
    'Ganjafesto Records',
    'Membership, Streaming and Entertainment Platform',
    'Music, episodic media and direct-to-fan commerce',
    '2026',
    'Entertainment product design, brand direction, streaming UX and commerce architecture',
    'Entertainment branding\nMembership architecture\nMusic streaming UX\nEpisode design\nMerchandise commerce\nAuthentication and checkout',
    'PHP\nMySQL\nJavaScript\nStreaming interfaces\nMembership\nEcommerce',
    'A membership and streaming platform for original music, episodic media, merchandise, playlists and direct fan relationships.',
    'Stonefellow combines a public subscription site, music previews, member streaming, playlists, albums, episodic content, cast and series pages, merchandise, authentication, cart and checkout.',
    'Independent entertainment properties often split music, episodes, membership, merchandise and audience relationships across unrelated platforms.',
    'One branded entertainment environment connecting subscription access, streaming, episodic storytelling, cast information, playlists, merchandise and direct fan commerce.',
    'A working product direction demonstrating entertainment branding, audience ownership, content architecture, subscriptions, ecommerce and media-product interface design.',
    'stonefellow\nmusic streaming\nmembership\nepisodes\nmerchandise\ndirect to fan\nentertainment platform',
    UTC_TIMESTAMP()
),
(
    'Roger Huston',
    'roger-huston',
    'active',
    0,
    70,
    'https://rogerhuston.com/',
    'View Roger Huston',
    'Ganjafesto Records',
    'Artist Portfolio and Direct-to-Fan Commerce',
    'Music, visual art and creator commerce',
    '2025–Present',
    'Artist, producer, designer, product architect and ecommerce builder',
    'Songwriting\nMusic production\nGraphic design\nArtist branding\nDirect-to-fan commerce\nCustom product design\nKnowledge-agent strategy',
    'Reaper\nMusic production\nPrint-on-demand\nEcommerce\nCustom vinyl\nKnowledge agent',
    'An artist-commerce environment connecting Space Reggae music, visual art, personalized products, fulfillment, affiliates and conversational engagement.',
    'Roger Huston extends beyond a traditional artist site by connecting music discovery, graphic artwork, streaming links, print-on-demand products, personalized vinyl records, affiliate-assisted configuration, publishing and an artist knowledge agent.',
    'Independent music sites often stop at streaming links or basic merchandise, leaving artist identity, visual work, personalized fan products, customer service and conversational engagement disconnected.',
    'A broader creative-commerce environment combining music, visual art, merchandise, custom vinyl, affiliates, Ganjafesto Records and an artist knowledge agent.',
    'A working demonstration of how an independent creator can turn a music catalog into a connected brand, commerce, personalization, publishing and conversational-media system.',
    'roger huston\nspace reggae\nartist commerce\ncustom vinyl\nmusic production\nvisual art\ndirect to fan',
    UTC_TIMESTAMP()
)
ON DUPLICATE KEY UPDATE
    title=VALUES(title),
    status=VALUES(status),
    featured=VALUES(featured),
    sort_order=VALUES(sort_order),
    project_url=VALUES(project_url),
    project_url_label=VALUES(project_url_label),
    client_name=VALUES(client_name),
    project_type=VALUES(project_type),
    industry=VALUES(industry),
    year_label=VALUES(year_label),
    role_title=VALUES(role_title),
    services=VALUES(services),
    technologies=VALUES(technologies),
    summary=VALUES(summary),
    overview=VALUES(overview),
    challenge=VALUES(challenge),
    solution=VALUES(solution),
    results=VALUES(results),
    keywords=VALUES(keywords),
    published_at=COALESCE(published_at,VALUES(published_at));

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

CREATE TABLE IF NOT EXISTS music_albums (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    artist_name VARCHAR(190) NOT NULL DEFAULT 'David Evans',
    album_type ENUM('album','ep','single','compilation') NOT NULL DEFAULT 'album',
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    release_date DATE NULL,
    release_year SMALLINT UNSIGNED NULL,
    genre VARCHAR(120) NULL,
    description TEXT NULL,
    cover_stored_name VARCHAR(255) NULL,
    cover_extension VARCHAR(20) NULL,
    cover_mime_type VARCHAR(120) NULL,
    cover_size_bytes BIGINT UNSIGNED NULL,
    cover_sha256 CHAR(64) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_music_albums_slug (slug),
    KEY idx_music_albums_public (status,featured,sort_order,published_at),
    CONSTRAINT fk_music_albums_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_music_albums_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music_tracks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    knowledge_asset_id BIGINT UNSIGNED NOT NULL,
    album_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    artist_name VARCHAR(190) NOT NULL DEFAULT 'David Evans',
    featured_artist VARCHAR(190) NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    disc_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    track_number SMALLINT UNSIGNED NULL,
    genre VARCHAR(120) NULL,
    release_year SMALLINT UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    description TEXT NULL,
    lyrics MEDIUMTEXT NULL,
    is_explicit TINYINT(1) NOT NULL DEFAULT 0,
    is_downloadable TINYINT(1) NOT NULL DEFAULT 0,
    play_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_music_tracks_asset (knowledge_asset_id),
    UNIQUE KEY uq_music_tracks_slug (slug),
    KEY idx_music_tracks_public (status,featured,sort_order,published_at),
    KEY idx_music_tracks_album (album_id,disc_number,track_number,sort_order),
    KEY idx_music_tracks_artist (artist_name,title),
    CONSTRAINT fk_music_tracks_asset
        FOREIGN KEY (knowledge_asset_id)
        REFERENCES knowledge_assets(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_music_tracks_album
        FOREIGN KEY (album_id)
        REFERENCES music_albums(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_music_tracks_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_music_tracks_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music_playlists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    cover_stored_name VARCHAR(255) NULL,
    cover_extension VARCHAR(20) NULL,
    cover_mime_type VARCHAR(120) NULL,
    cover_size_bytes BIGINT UNSIGNED NULL,
    cover_sha256 CHAR(64) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_music_playlists_slug (slug),
    KEY idx_music_playlists_public (status,featured,sort_order,published_at),
    CONSTRAINT fk_music_playlists_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_music_playlists_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music_playlist_tracks (
    playlist_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    added_by BIGINT UNSIGNED NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (playlist_id,track_id),
    KEY idx_music_playlist_tracks_order (playlist_id,position,track_id),
    KEY idx_music_playlist_tracks_track (track_id),
    CONSTRAINT fk_music_playlist_tracks_playlist
        FOREIGN KEY (playlist_id)
        REFERENCES music_playlists(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_music_playlist_tracks_track
        FOREIGN KEY (track_id)
        REFERENCES music_tracks(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_music_playlist_tracks_added_by
        FOREIGN KEY (added_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Blog and database-backed Resume publishing v51
-- North Mountain Media Publishing Systems v51
-- Blog publishing + database-backed resume posts
-- MySQL 8 / MariaDB 10.11 compatible


CREATE TABLE IF NOT EXISTS blog_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    category VARCHAR(120) NULL,
    excerpt TEXT NULL,
    body MEDIUMTEXT NOT NULL,
    tags TEXT NULL,
    seo_title VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_posts_slug (slug),
    KEY idx_blog_posts_public (status,featured,published_at,updated_at),
    KEY idx_blog_posts_category (category,status,published_at),
    CONSTRAINT fk_blog_posts_author
        FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    media_role ENUM('cover','gallery') NOT NULL DEFAULT 'gallery',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    width_px INT UNSIGNED NULL,
    height_px INT UNSIGNED NULL,
    alt_text VARCHAR(500) NULL,
    caption VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_media_stored_name (stored_name),
    KEY idx_blog_media_post_role (post_id,media_role,sort_order,id),
    CONSTRAINT fk_blog_media_post
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_media_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resume_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    post_type ENUM(
        'profile',
        'experience',
        'education',
        'skill_group',
        'strengths',
        'certification',
        'award',
        'project',
        'volunteer',
        'custom'
    ) NOT NULL DEFAULT 'experience',
    column_name ENUM('main','sidebar') NOT NULL DEFAULT 'main',
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    section_label VARCHAR(190) NULL,
    subtitle VARCHAR(500) NULL,
    organization VARCHAR(190) NULL,
    location VARCHAR(190) NULL,
    date_label VARCHAR(190) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    summary MEDIUMTEXT NULL,
    body MEDIUMTEXT NULL,
    achievements MEDIUMTEXT NULL,
    skills MEDIUMTEXT NULL,
    link_url VARCHAR(500) NULL,
    link_label VARCHAR(120) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resume_posts_slug (slug),
    KEY idx_resume_posts_public (
        status,column_name,post_type,featured,sort_order,published_at
    ),
    CONSTRAINT fk_resume_posts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_resume_posts_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convert the current public resume into editable resume posts.
INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,featured,sort_order,
    section_label,subtitle,organization,location,date_label,
    summary,body,achievements,skills,link_url,link_label,published_at
)
SELECT
    'David Evans',
    'david-evans-profile',
    'profile',
    'main',
    'published',
    1,
    1,
    'Operations · Inventory · Process Improvement',
    'Distribution · Ecommerce · Procurement Systems · AI-Assisted Business Intelligence',
    NULL,
    'Phoenix, Arizona',
    NULL,
    'Operations and systems professional with more than 20 years of experience across ecommerce, inventory coordination, distribution, CRM workflows, customer operations and digital product development. Supported a high-volume Amazon retail catalog exceeding 100,000 SKUs and has hands-on experience connecting product data, inventory, fulfillment, billing and customer service. Known for identifying fragmented processes, organizing information and translating operational goals into practical workflows, dashboards and business systems.',
    NULL,
    NULL,
    NULL,
    'https://www.linkedin.com/in/david-evans-15005530/',
    'LinkedIn',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='david-evans-profile'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,featured,sort_order,
    section_label,organization,location,date_label,summary,achievements,published_at
)
SELECT
    'Founder & Systems / Product Operations Lead',
    'vp3-media-microgifter',
    'experience',
    'main',
    'published',
    1,
    10,
    'Professional experience',
    'VP3 Media Corp. / Microgifter',
    'Phoenix, Arizona',
    'May 2024–Present',
    'Developing Microgifter, a side project addressing gaps in the gift-certificate market through digital gifting, merchant CRM, lifecycle tracking and automated commerce.',
    'Define product architecture, data relationships, operational workflows, user roles, reporting needs, testing standards and release priorities across a production PHP/MySQL platform.
Coordinate technical, product, customer, marketing and business workstreams while maintaining requirements, dependencies, QA, documentation and implementation follow-through.
Turn fragmented customer, merchant, campaign, ownership, claim, redemption and reporting processes into structured and repeatable systems.
Maintain implementation checklists, data dependencies, release validation and documented QA across ongoing product development.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='vp3-media-microgifter'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,location,date_label,summary,achievements,published_at
)
SELECT
    'eCommerce Listing Specialist',
    'kodi-distributing',
    'experience',
    'main',
    'published',
    20,
    'Kodi Distributing',
    'Phoenix, Arizona',
    'September 2023–April 2024',
    'Supported high-volume ecommerce operations across Amazon and additional marketplace channels for a catalog exceeding 100,000 SKUs.',
    'Created, maintained and optimized product listings while protecting product-data accuracy, categorization, consistency and catalog integrity at scale.
Coordinated inventory updates and product availability across systems, supporting reliable marketplace, fulfillment and customer-order operations.
Improved listing quality and merchandising structure while performing detailed QA in a complex multi-channel environment.
Worked across marketing, inventory, product data and fulfillment teams to resolve issues and keep digital commerce workflows moving.
Supported the accuracy and availability of product information used by customers, marketplace teams, inventory operations and fulfillment.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='kodi-distributing'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,location,date_label,summary,achievements,published_at
)
SELECT
    'Client Services Manager',
    'timeshare-attorneys-of-america',
    'experience',
    'main',
    'published',
    30,
    'Timeshare Attorneys of America',
    'Phoenix, Arizona',
    'June 2010–September 2016',
    'Managed client intake, Zoho CRM, customer communications, documentation, scheduling and operational workflows supporting the full client lifecycle.',
    'Administered Zoho CRM records, customer statuses, communication histories, follow-up activity, workflow progression and lifecycle visibility.
Managed onboarding, document discovery, case preparation, scheduling, customer questions and parallel workstreams with strong attention to detail.
Standardized fragmented intake and documentation processes into more consistent, repeatable operational workflows.
Coordinated internal handoffs and follow-up priorities so client records, documents, scheduling and next actions remained visible.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='timeshare-attorneys-of-america'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,location,date_label,summary,achievements,published_at
)
SELECT
    'Marketing Coordinator',
    'platypusco',
    'experience',
    'main',
    'published',
    40,
    'Platypusco',
    'Missoula County, Montana',
    'March 2010–October 2010',
    'Supported ecommerce, inventory, fulfillment, customer experience and marketing operations within the 3dcart platform.',
    'Maintained product listings and storefront data while coordinating inventory, order fulfillment, shipping, tracking and customer-service workflows.
Assisted with digital campaigns and promotional initiatives while working across marketing, ecommerce, inventory and fulfillment functions.
Helped keep storefront, product, shipping and customer information aligned during day-to-day ecommerce activity.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='platypusco'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,location,date_label,summary,achievements,published_at
)
SELECT
    'Sales & Distribution Operations',
    'treecycle',
    'experience',
    'main',
    'published',
    50,
    'Treecycle',
    'Missoula County, Montana',
    'March 2003–February 2004',
    'Managed customer accounts and supported daily distribution workflows spanning sales, billing, inventory control, order fulfillment and delivery.',
    'Tracked product availability, coordinated orders and billing, supported fulfillment and delivery, and resolved customer and service issues.
Maintained ongoing customer relationships supporting retention, repeat business and reliable day-to-day operations.
Worked directly across sales, inventory, billing, fulfillment and delivery rather than treating each function as a separate workflow.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='treecycle'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,summary,published_at
)
SELECT
    'Primary focus',
    'primary-focus',
    'custom',
    'sidebar',
    'published',
    10,
    'Operations, inventory, procurement systems and process improvement.',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='primary-focus'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,skills,published_at
)
SELECT
    'Core competencies',
    'core-competencies',
    'skill_group',
    'sidebar',
    'published',
    20,
    'Process improvement
Inventory operations
Purchasing workflows
Data quality
Cross-functional coordination
Reporting
AI-assisted analysis
Project ownership',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='core-competencies'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,skills,published_at
)
SELECT
    'Tools & platforms',
    'tools-platforms',
    'skill_group',
    'sidebar',
    'published',
    30,
    'Zoho CRM
Amazon
3dcart
CSV / XLSX
ChatGPT
Claude
PHP
MySQL
APIs
Adobe',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='tools-platforms'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,achievements,published_at
)
SELECT
    'Operational strengths',
    'operational-strengths',
    'strengths',
    'sidebar',
    'published',
    40,
    'Questions inefficient processes
Organizes fragmented information
Builds repeatable workflows
Maintains accuracy at scale
Owns work through completion',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='operational-strengths'
);

INSERT INTO resume_posts (
    title,slug,post_type,column_name,status,sort_order,
    organization,date_label,summary,published_at
)
SELECT
    'Education',
    'university-of-montana',
    'education',
    'sidebar',
    'published',
    50,
    'University of Montana',
    '1992–1996',
    'Business and Marketing coursework',
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM resume_posts WHERE slug='university-of-montana'
);



-- Publishing Workflow, SEO, revisions, and analytics v56
-- North Mountain Media Publishing Workflow v56
-- Import-safe compatibility revision
-- Uses information_schema checks before each ADD COLUMN statement
-- Compatible with MySQL and MariaDB versions that support PREPARE/EXECUTE

-- DDL statements auto-commit in MySQL/MariaDB, so this migration does not
-- wrap schema changes in START TRANSACTION / COMMIT.

SET @nmm_schema := DATABASE();

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_posts` ADD COLUMN `canonical_url` VARCHAR(500) NULL AFTER `seo_description`',
        'SELECT ''blog_posts.canonical_url already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_posts'
      AND COLUMN_NAME = 'canonical_url'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_posts` ADD COLUMN `autosave_json` MEDIUMTEXT NULL AFTER `canonical_url`',
        'SELECT ''blog_posts.autosave_json already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_posts'
      AND COLUMN_NAME = 'autosave_json'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_posts` ADD COLUMN `autosaved_at` DATETIME NULL AFTER `autosave_json`',
        'SELECT ''blog_posts.autosaved_at already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_posts'
      AND COLUMN_NAME = 'autosaved_at'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_posts` ADD COLUMN `autosaved_by` BIGINT UNSIGNED NULL AFTER `autosaved_at`',
        'SELECT ''blog_posts.autosaved_by already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_posts'
      AND COLUMN_NAME = 'autosaved_by'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `resume_posts` ADD COLUMN `autosave_json` MEDIUMTEXT NULL AFTER `link_label`',
        'SELECT ''resume_posts.autosave_json already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'resume_posts'
      AND COLUMN_NAME = 'autosave_json'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `resume_posts` ADD COLUMN `autosaved_at` DATETIME NULL AFTER `autosave_json`',
        'SELECT ''resume_posts.autosaved_at already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'resume_posts'
      AND COLUMN_NAME = 'autosaved_at'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `resume_posts` ADD COLUMN `autosaved_by` BIGINT UNSIGNED NULL AFTER `autosaved_at`',
        'SELECT ''resume_posts.autosaved_by already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'resume_posts'
      AND COLUMN_NAME = 'autosaved_by'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_media` ADD COLUMN `focal_x` DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER `caption`',
        'SELECT ''blog_media.focal_x already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_media'
      AND COLUMN_NAME = 'focal_x'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_media` ADD COLUMN `focal_y` DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER `focal_x`',
        'SELECT ''blog_media.focal_y already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_media'
      AND COLUMN_NAME = 'focal_y'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_media` ADD COLUMN `crop_ratio` ENUM(''original'',''16:9'',''4:3'',''1:1'',''3:4'') NOT NULL DEFAULT ''original'' AFTER `focal_y`',
        'SELECT ''blog_media.crop_ratio already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_media'
      AND COLUMN_NAME = 'crop_ratio'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

CREATE TABLE IF NOT EXISTS blog_post_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    revision_type ENUM(
        'manual','autosave','restore','duplicate'
    ) NOT NULL DEFAULT 'manual',
    snapshot_json MEDIUMTEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_blog_revision_post (post_id,created_at,id),
    KEY idx_blog_revision_type (revision_type,created_at),
    CONSTRAINT fk_blog_revision_post
        FOREIGN KEY (post_id)
        REFERENCES blog_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_blog_revision_user
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resume_post_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    revision_type ENUM(
        'manual','autosave','restore','duplicate','reorder'
    ) NOT NULL DEFAULT 'manual',
    snapshot_json MEDIUMTEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resume_revision_post (post_id,created_at,id),
    KEY idx_resume_revision_type (revision_type,created_at),
    CONSTRAINT fk_resume_revision_post
        FOREIGN KEY (post_id)
        REFERENCES resume_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_resume_revision_user
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key,setting_value)
VALUES
    ('blog_title','North Mountain Media Journal'),
    ('blog_intro','Ideas, systems, and things being built.'),
    ('blog_description','Articles about product strategy, connected business systems, ecommerce, CRM, operational design, music platforms, and independent software development.'),
    ('blog_posts_per_page','9'),
    ('blog_default_author_user_id',''),
    ('blog_rss_enabled','1'),
    ('blog_sitemap_enabled','1')
ON DUPLICATE KEY UPDATE
    setting_value=setting_value;

SELECT 'North Mountain Media Publishing Workflow v56 migration complete' AS migration_status;


-- Events & Calendar v57
-- North Mountain Media Events & Calendar v57
-- MySQL 5.7+/8.0 and MariaDB 10.3+ compatible


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

-- Appointments & Booking v58
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

-- Proposals, Estimates & Client Intake v59
CREATE TABLE IF NOT EXISTS intake_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 booking_type_id BIGINT UNSIGNED NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 title VARCHAR(190) NOT NULL,
 introduction TEXT NULL,
 completion_message TEXT NULL,
 create_opportunity TINYINT(1) NOT NULL DEFAULT 1,
 opportunity_type VARCHAR(120) NULL,
 sort_order INT NOT NULL DEFAULT 100,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_intake_templates_slug(slug),
 KEY idx_intake_templates_status(status,sort_order,id),
 KEY idx_intake_templates_booking(booking_type_id,status),
 CONSTRAINT fk_intake_templates_booking FOREIGN KEY(booking_type_id) REFERENCES booking_types(id) ON DELETE SET NULL,
 CONSTRAINT fk_intake_templates_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_intake_templates_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intake_questions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 template_id BIGINT UNSIGNED NOT NULL,
 question_key VARCHAR(120) NOT NULL,
 label VARCHAR(255) NOT NULL,
 help_text VARCHAR(500) NULL,
 field_type ENUM('short_text','long_text','email','phone','number','date','select','checkbox') NOT NULL DEFAULT 'short_text',
 options_json TEXT NULL,
 required TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_intake_question_key(template_id,question_key),
 KEY idx_intake_questions_order(template_id,sort_order,id),
 CONSTRAINT fk_intake_questions_template FOREIGN KEY(template_id) REFERENCES intake_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_intakes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 template_id BIGINT UNSIGNED NOT NULL,
 appointment_id BIGINT UNSIGNED NULL,
 crm_contact_id BIGINT UNSIGNED NULL,
 crm_opportunity_id BIGINT UNSIGNED NULL,
 converted_proposal_id BIGINT UNSIGNED NULL,
 status ENUM('started','submitted','reviewed','converted','archived') NOT NULL DEFAULT 'started',
 display_name VARCHAR(160) NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(60) NULL,
 company VARCHAR(190) NULL,
 project_title VARCHAR(190) NULL,
 summary TEXT NULL,
 secure_token CHAR(64) NOT NULL,
 submitted_at DATETIME NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_project_intakes_token(secure_token),
 KEY idx_project_intakes_status(status,updated_at,id),
 KEY idx_project_intakes_contact(crm_contact_id,created_at),
 KEY idx_project_intakes_opportunity(crm_opportunity_id),
 KEY idx_project_intakes_appointment(appointment_id),
 KEY idx_project_intakes_proposal(converted_proposal_id),
 CONSTRAINT fk_project_intakes_template FOREIGN KEY(template_id) REFERENCES intake_templates(id) ON DELETE RESTRICT,
 CONSTRAINT fk_project_intakes_appointment FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
 CONSTRAINT fk_project_intakes_contact FOREIGN KEY(crm_contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL,
 CONSTRAINT fk_project_intakes_opportunity FOREIGN KEY(crm_opportunity_id) REFERENCES crm_opportunities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_intake_answers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 intake_id BIGINT UNSIGNED NOT NULL,
 question_id BIGINT UNSIGNED NOT NULL,
 answer_text MEDIUMTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_project_intake_answer(intake_id,question_id),
 KEY idx_project_intake_answers_question(question_id,intake_id),
 CONSTRAINT fk_project_intake_answers_intake FOREIGN KEY(intake_id) REFERENCES project_intakes(id) ON DELETE CASCADE,
 CONSTRAINT fk_project_intake_answers_question FOREIGN KEY(question_id) REFERENCES intake_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(190) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 description TEXT NULL,
 payload_json MEDIUMTEXT NOT NULL,
 sort_order INT NOT NULL DEFAULT 100,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_proposal_templates_name(name),
 KEY idx_proposal_templates_status(status,sort_order,id),
 CONSTRAINT fk_proposal_templates_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposal_templates_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_number VARCHAR(60) NOT NULL,
 crm_contact_id BIGINT UNSIGNED NOT NULL,
 crm_opportunity_id BIGINT UNSIGNED NULL,
 intake_id BIGINT UNSIGNED NULL,
 appointment_id BIGINT UNSIGNED NULL,
 converted_project_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 status ENUM('draft','sent','viewed','accepted','declined','expired','converted','archived') NOT NULL DEFAULT 'draft',
 currency_code CHAR(3) NOT NULL DEFAULT 'USD',
 valid_until DATE NULL,
 public_intro TEXT NULL,
 scope_text MEDIUMTEXT NULL,
 deliverables_text MEDIUMTEXT NULL,
 timeline_text MEDIUMTEXT NULL,
 assumptions_text MEDIUMTEXT NULL,
 exclusions_text MEDIUMTEXT NULL,
 terms_text MEDIUMTEXT NULL,
 internal_notes MEDIUMTEXT NULL,
 discount_cents BIGINT NOT NULL DEFAULT 0,
 tax_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
 subtotal_cents BIGINT NOT NULL DEFAULT 0,
 tax_cents BIGINT NOT NULL DEFAULT 0,
 total_cents BIGINT NOT NULL DEFAULT 0,
 deposit_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
 deposit_amount_cents BIGINT NOT NULL DEFAULT 0,
 payment_url VARCHAR(500) NULL,
 secure_token CHAR(64) NOT NULL,
 current_revision INT UNSIGNED NOT NULL DEFAULT 1,
 sent_at DATETIME NULL,
 first_viewed_at DATETIME NULL,
 last_viewed_at DATETIME NULL,
 view_count INT UNSIGNED NOT NULL DEFAULT 0,
 accepted_at DATETIME NULL,
 accepted_name VARCHAR(160) NULL,
 accepted_ip VARCHAR(64) NULL,
 accepted_user_agent VARCHAR(500) NULL,
 declined_at DATETIME NULL,
 declined_reason TEXT NULL,
 converted_at DATETIME NULL,
 next_follow_up_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_proposals_number(proposal_number),
 UNIQUE KEY uq_proposals_token(secure_token),
 KEY idx_proposals_status_followup(status,next_follow_up_at,updated_at),
 KEY idx_proposals_contact(crm_contact_id,created_at),
 KEY idx_proposals_opportunity(crm_opportunity_id),
 KEY idx_proposals_intake(intake_id),
 KEY idx_proposals_project(converted_project_id),
 CONSTRAINT fk_proposals_contact FOREIGN KEY(crm_contact_id) REFERENCES crm_contacts(id) ON DELETE RESTRICT,
 CONSTRAINT fk_proposals_opportunity FOREIGN KEY(crm_opportunity_id) REFERENCES crm_opportunities(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_intake FOREIGN KEY(intake_id) REFERENCES project_intakes(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_appointment FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_project FOREIGN KEY(converted_project_id) REFERENCES projects(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_proposals_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_line_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 item_type ENUM('service','product','expense','discount') NOT NULL DEFAULT 'service',
 name VARCHAR(190) NOT NULL,
 description TEXT NULL,
 quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
 unit_amount_cents BIGINT NOT NULL DEFAULT 0,
 discount_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
 taxable TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_proposal_line_items_order(proposal_id,sort_order,id),
 CONSTRAINT fk_proposal_line_items_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 revision_number INT UNSIGNED NOT NULL,
 snapshot_json MEDIUMTEXT NOT NULL,
 revision_note VARCHAR(500) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_proposal_revision_number(proposal_id,revision_number),
 KEY idx_proposal_revisions_created(proposal_id,created_at),
 CONSTRAINT fk_proposal_revisions_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
 CONSTRAINT fk_proposal_revisions_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_audit_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 event_type ENUM('created','updated','sent','viewed','accepted','declined','expired','duplicated','revision_restored','converted','pdf_downloaded','reminder') NOT NULL,
 actor_type ENUM('admin','client','public','system') NOT NULL DEFAULT 'system',
 actor_user_id BIGINT UNSIGNED NULL,
 actor_name VARCHAR(160) NULL,
 ip_address VARCHAR(64) NULL,
 user_agent VARCHAR(500) NULL,
 metadata_json TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_proposal_audit_created(proposal_id,created_at,id),
 CONSTRAINT fk_proposal_audit_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
 CONSTRAINT fk_proposal_audit_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_reminders (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proposal_id BIGINT UNSIGNED NOT NULL,
 reminder_type ENUM('follow_up','expiration','deposit') NOT NULL DEFAULT 'follow_up',
 scheduled_for DATETIME NOT NULL,
 status ENUM('pending','ready','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
 attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 last_error TEXT NULL,
 sent_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_proposal_reminder(proposal_id,reminder_type,scheduled_for),
 KEY idx_proposal_reminder_queue(status,scheduled_for,proposal_id),
 CONSTRAINT fk_proposal_reminder_proposal FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
 ('intake_public_enabled','1'),
 ('intake_default_template_slug','project-intake'),
 ('proposals_company_name','North Mountain Media'),
 ('proposals_company_location','Phoenix, Arizona'),
 ('proposals_default_valid_days','14'),
 ('proposals_default_tax_percent','0'),
 ('proposals_default_deposit_percent','50'),
 ('proposals_follow_up_days','3'),
 ('proposals_pdf_footer','Prepared by North Mountain Media'),
 ('proposals_acceptance_statement','I approve this proposal, estimate, scope, and terms.')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

INSERT INTO intake_templates(name,slug,status,title,introduction,completion_message,create_opportunity,opportunity_type,sort_order)
VALUES('Project Intake','project-intake','active','Tell us about your project','Share the goals, requirements, timing, audience, and budget context needed to prepare a useful recommendation or proposal.','Your project intake was received. North Mountain Media will review it and follow up with next steps.',1,'Project Intake',10)
ON DUPLICATE KEY UPDATE name=VALUES(name);

SET @nmm_intake_template_id=(SELECT id FROM intake_templates WHERE slug='project-intake' LIMIT 1);
INSERT INTO intake_questions(template_id,question_key,label,help_text,field_type,options_json,required,sort_order) VALUES
 (@nmm_intake_template_id,'project_name','Project or initiative name',NULL,'short_text',NULL,1,10),
 (@nmm_intake_template_id,'project_summary','What are you trying to build, improve, or launch?','Describe the current situation and desired outcome.','long_text',NULL,1,20),
 (@nmm_intake_template_id,'audience','Who is the primary audience or user?',NULL,'short_text',NULL,1,30),
 (@nmm_intake_template_id,'goals','What would make this project successful?','List measurable outcomes, operational improvements, or customer results.','long_text',NULL,1,40),
 (@nmm_intake_template_id,'features','Which features, deliverables, or services are required?',NULL,'long_text',NULL,0,50),
 (@nmm_intake_template_id,'existing_systems','What systems, websites, files, or tools already exist?',NULL,'long_text',NULL,0,60),
 (@nmm_intake_template_id,'target_date','Is there a target launch or completion date?',NULL,'date',NULL,0,70),
 (@nmm_intake_template_id,'budget_range','What budget range should guide the recommendation?',NULL,'select','["Under $2,500","$2,500–$5,000","$5,000–$10,000","$10,000–$25,000","$25,000+","Not determined"]',0,80),
 (@nmm_intake_template_id,'decision_process','Who is involved in reviewing or approving the project?',NULL,'long_text',NULL,0,90),
 (@nmm_intake_template_id,'additional_context','Anything else North Mountain Media should know?',NULL,'long_text',NULL,0,100)
ON DUPLICATE KEY UPDATE label=VALUES(label),help_text=VALUES(help_text),field_type=VALUES(field_type),options_json=VALUES(options_json),required=VALUES(required),sort_order=VALUES(sort_order);

INSERT INTO proposal_templates(name,status,description,payload_json,sort_order)
VALUES('Digital Product Proposal','active','Reusable structure for web applications, portals, ecommerce systems, and digital product work.','{"public_intro":"A practical proposal designed around the project goals, required deliverables, timeline, and implementation risks.","scope_text":"Discovery, product planning, information architecture, interface design, implementation, validation, and deployment support.","deliverables_text":"Working application or website, responsive interface, configured administrative workflows, documentation, and deployment package.","timeline_text":"Work is organized into clearly reviewed phases. Timing depends on scope, content readiness, feedback, and integration requirements.","assumptions_text":"The client provides timely access, content, approvals, and required third-party credentials.","exclusions_text":"Third-party subscriptions, advertising spend, payment processing fees, licensing, and services not listed in the estimate are excluded.","terms_text":"Changes outside the approved scope may require a revised estimate. Deposits and approved milestone payments are non-refundable once the related work begins.","line_items":[{"name":"Discovery and product planning","description":"Requirements, workflow, architecture, and implementation plan.","quantity":1,"unit_amount_cents":150000},{"name":"Design and implementation","description":"Responsive interface and production build.","quantity":1,"unit_amount_cents":350000},{"name":"Validation and deployment","description":"Testing, documentation, and deployment support.","quantity":1,"unit_amount_cents":100000}]}',10)
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),payload_json=VALUES(payload_json),sort_order=VALUES(sort_order);

-- v60 site modules, landing page builder, branding, and SEO defaults
INSERT INTO settings(setting_key,setting_value) VALUES
 ('module_landing_page_enabled','0'),
 ('module_portfolio_enabled','1'),
 ('module_resume_enabled','1'),
 ('module_music_library_enabled','1'),
 ('module_blog_enabled','1'),
 ('module_events_enabled','1'),
 ('module_bookings_enabled','1'),
 ('module_project_intake_enabled','1'),
 ('module_call_us_enabled','1'),
 ('site_logo_stored_name',''),
 ('site_logo_mime',''),
 ('site_logo_alt','North Mountain Media'),
 ('mobile_header_logo_mode','logo'),
 ('seo_title','North Mountain Media'),
 ('seo_description',''),
 ('seo_keywords',''),
 ('seo_site_url',''),
 ('seo_index_enabled','1'),
 ('seo_social_image_stored_name',''),
 ('seo_social_image_mime',''),
 ('landing_template','split'),
 ('landing_eyebrow','North Mountain Media'),
 ('landing_headline','Connected digital systems for ambitious ideas.'),
 ('landing_subheadline','Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.'),
 ('landing_body','North Mountain Media builds focused digital products and operational platforms that help businesses, creators, and new ventures move from fragmented tools to connected execution.'),
 ('landing_primary_button_label','Start a project'),
 ('landing_primary_button_url','intake.php'),
 ('landing_secondary_button_label','View portfolio'),
 ('landing_secondary_button_url','workspace.php'),
 ('landing_hero_image_stored_name',''),
 ('landing_hero_image_mime',''),
 ('landing_hero_image_alt','North Mountain Media featured work'),
 ('landing_secondary_image_stored_name',''),
 ('landing_secondary_image_mime',''),
 ('landing_secondary_image_alt','North Mountain Media project detail'),
 ('landing_section_eyebrow','What we build'),
 ('landing_section_title','A clearer path from concept to working system.'),
 ('landing_section_body','Choose a focused starting point, connect the required workflows, and create a platform that can grow without losing clarity.'),
 ('landing_features','Strategy and planning|Translate goals into a clear system and launch path.\nConnected execution|Bring content, CRM, commerce, and client operations together.\nMeasurable progress|Use practical workflows, reporting, and follow-through.'),
 ('landing_cta_eyebrow','Ready to build'),
 ('landing_cta_title','Turn the next idea into a connected working system.'),
 ('landing_footer_text','North Mountain Media · Phoenix, Arizona')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


-- v61 visual site builder, navigation, analytics, and Microgifter adapter schema
-- North Mountain Media Visual Site Builder v61
-- Build: 20260727-visual-site-builder-v61
-- Additive MySQL/MariaDB migration. Import after site_modules_landing_v60.sql.

CREATE TABLE IF NOT EXISTS site_pages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 page_type ENUM('landing','custom') NOT NULL DEFAULT 'custom',
 status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 template_key VARCHAR(80) NOT NULL DEFAULT 'blank',
 draft_json LONGTEXT NOT NULL,
 published_json LONGTEXT NULL,
 seo_title VARCHAR(190) NULL,
 seo_description VARCHAR(500) NULL,
 seo_keywords VARCHAR(500) NULL,
 seo_canonical_url VARCHAR(500) NULL,
 seo_social_image VARCHAR(500) NULL,
 seo_index_enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 published_by BIGINT UNSIGNED NULL,
 published_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_site_pages_slug(slug),
 KEY idx_site_pages_status(status,page_type,updated_at),
 CONSTRAINT fk_site_pages_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_site_pages_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_site_pages_published FOREIGN KEY(published_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_page_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 page_id BIGINT UNSIGNED NOT NULL,
 revision_number INT UNSIGNED NOT NULL,
 revision_type ENUM('draft','publish','restore') NOT NULL DEFAULT 'draft',
 payload_json LONGTEXT NOT NULL,
 note VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_site_page_revision(page_id,revision_number),
 KEY idx_site_page_revisions(page_id,created_at,id),
 CONSTRAINT fk_site_page_revisions_page FOREIGN KEY(page_id) REFERENCES site_pages(id) ON DELETE CASCADE,
 CONSTRAINT fk_site_page_revisions_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_saved_blocks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(190) NOT NULL,
 category VARCHAR(80) NOT NULL DEFAULT 'saved',
 block_type VARCHAR(80) NOT NULL,
 payload_json LONGTEXT NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_site_saved_blocks(category,name,id),
 CONSTRAINT fk_site_saved_blocks_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_site_saved_blocks_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_menus (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_site_menus_slug(slug),
 KEY idx_site_menus_status(status,name,id),
 CONSTRAINT fk_site_menus_created FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_site_menus_updated FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_menu_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 menu_id BIGINT UNSIGNED NOT NULL,
 parent_id BIGINT UNSIGNED NULL,
 item_type ENUM('module','page','custom') NOT NULL DEFAULT 'custom',
 label VARCHAR(190) NOT NULL,
 url VARCHAR(500) NULL,
 module_key VARCHAR(80) NULL,
 page_id BIGINT UNSIGNED NULL,
 target ENUM('_self','_blank') NOT NULL DEFAULT '_self',
 css_class VARCHAR(190) NULL,
 description VARCHAR(500) NULL,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_site_menu_items_order(menu_id,parent_id,sort_order,id),
 KEY idx_site_menu_items_page(page_id),
 CONSTRAINT fk_site_menu_items_menu FOREIGN KEY(menu_id) REFERENCES site_menus(id) ON DELETE CASCADE,
 CONSTRAINT fk_site_menu_items_parent FOREIGN KEY(parent_id) REFERENCES site_menu_items(id) ON DELETE CASCADE,
 CONSTRAINT fk_site_menu_items_page FOREIGN KEY(page_id) REFERENCES site_pages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_pages(title,slug,page_type,status,template_key,draft_json,published_json,seo_title,seo_description,seo_index_enabled)
SELECT 'Home','home','landing','draft','split',
'{"version":1,"theme":{"contentWidth":"1180","primary":"#152638","accent":"#0b8588","radius":"18"},"sections":[{"id":"hero-1","type":"hero","settings":{"eyebrow":"North Mountain Media","headline":"Connected digital systems for ambitious ideas.","text":"Strategy, design, content, CRM, commerce, and client operations brought together in one practical system.","alignment":"left"},"blocks":[{"id":"button-1","type":"button","settings":{"label":"Start a project","url":"intake.php","style":"primary"}},{"id":"button-2","type":"button","settings":{"label":"View portfolio","url":"workspace.php","style":"secondary"}}]},{"id":"features-1","type":"features","settings":{"eyebrow":"What we build","headline":"A clearer path from concept to working system.","text":"Choose a focused starting point, connect the required workflows, and create a platform that can grow without losing clarity."},"blocks":[{"id":"feature-1","type":"feature","settings":{"title":"Strategy and planning","text":"Translate goals into a clear system and launch path."}},{"id":"feature-2","type":"feature","settings":{"title":"Connected execution","text":"Bring content, CRM, commerce, and client operations together."}},{"id":"feature-3","type":"feature","settings":{"title":"Measurable progress","text":"Use practical workflows, reporting, and follow-through."}}]},{"id":"cta-1","type":"cta","settings":{"eyebrow":"Ready to build","headline":"Turn the next idea into a connected working system.","text":"Start with a conversation about the goal, audience, and practical next step."},"blocks":[{"id":"button-3","type":"button","settings":{"label":"Start a project","url":"intake.php","style":"primary"}}]}]}',
NULL,'North Mountain Media','Connected digital systems, media, CRM, publishing, and client operations.',1
WHERE NOT EXISTS(SELECT 1 FROM site_pages WHERE slug='home');

INSERT INTO site_menus(name,slug,status)
SELECT 'Primary Navigation','primary','active' WHERE NOT EXISTS(SELECT 1 FROM site_menus WHERE slug='primary');
INSERT INTO site_menus(name,slug,status)
SELECT 'Mobile Navigation','mobile','active' WHERE NOT EXISTS(SELECT 1 FROM site_menus WHERE slug='mobile');
INSERT INTO site_menus(name,slug,status)
SELECT 'Public Sidebar','sidebar','active' WHERE NOT EXISTS(SELECT 1 FROM site_menus WHERE slug='sidebar');
INSERT INTO site_menus(name,slug,status)
SELECT 'Footer Navigation','footer','active' WHERE NOT EXISTS(SELECT 1 FROM site_menus WHERE slug='footer');

INSERT INTO settings(setting_key,setting_value) VALUES
('menu_location_header','primary'),
('menu_location_mobile','mobile'),
('menu_location_sidebar','sidebar'),
('menu_location_footer','footer'),
('microgifter_connection_mode','disabled'),
('microgifter_endpoint',''),
('microgifter_merchant_id',''),
('microgifter_cache_minutes','15'),
('microgifter_timeout_seconds','8'),
('microgifter_live_transactions_enabled','0'),
('microgifter_contact_sync_enabled','0'),
('microgifter_analytics_sync_enabled','0')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


-- Seed familiar public navigation. The visual menu manager can reorder, rename, nest, or remove every item.
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Home','landing_page',10 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='landing_page');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Portfolio','portfolio',20 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='portfolio');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Resume','resume',30 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='resume');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Music Library','music_library',40 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='music_library');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Blog','blog',50 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='blog');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Events','events',60 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='events');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Bookings','bookings',70 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='bookings');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Project Intake','project_intake',80 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='project_intake');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Call Us','call_us',90 FROM site_menus menu WHERE menu.slug IN ('primary','mobile','sidebar') AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='call_us');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Home','landing_page',10 FROM site_menus menu WHERE menu.slug='footer' AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='landing_page');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Project Intake','project_intake',20 FROM site_menus menu WHERE menu.slug='footer' AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='project_intake');
INSERT INTO site_menu_items(menu_id,item_type,label,module_key,sort_order)
SELECT menu.id,'module','Call Us','call_us',30 FROM site_menus menu WHERE menu.slug='footer' AND NOT EXISTS(SELECT 1 FROM site_menu_items item WHERE item.menu_id=menu.id AND item.module_key='call_us');


-- North Mountain Media RSS & Feed Reader Platform v62
-- Additive migration for MySQL 5.7+/8.0 and MariaDB 10.3+
-- Import after database/publishing_workflow_v56.sql.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS feed_folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feed_folders_user_name (user_id,name),
    KEY idx_feed_folders_user_order (user_id,sort_order,id),
    CONSTRAINT fk_feed_folders_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    feed_url VARCHAR(1000) NOT NULL,
    canonical_url VARCHAR(1000) NOT NULL,
    canonical_hash CHAR(64) NOT NULL,
    site_url VARCHAR(1000) NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    language VARCHAR(40) NULL,
    image_url VARCHAR(1000) NULL,
    feed_format ENUM('rss','atom','rdf','unknown') NOT NULL DEFAULT 'unknown',
    status ENUM('active','error','paused') NOT NULL DEFAULT 'active',
    etag VARCHAR(500) NULL,
    last_modified VARCHAR(190) NULL,
    last_http_status SMALLINT UNSIGNED NULL,
    last_checked_at DATETIME NULL,
    last_success_at DATETIME NULL,
    next_refresh_at DATETIME NULL,
    refresh_lock_until DATETIME NULL,
    failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feed_sources_canonical_hash (canonical_hash),
    KEY idx_feed_sources_refresh (status,next_refresh_at,refresh_lock_until),
    KEY idx_feed_sources_success (last_success_at),
    KEY idx_feed_sources_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    folder_id BIGINT UNSIGNED NULL,
    display_title VARCHAR(255) NULL,
    status ENUM('active','paused') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 100,
    last_viewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feed_subscriptions_user_source (user_id,source_id),
    KEY idx_feed_subscriptions_user_folder (user_id,folder_id,status,sort_order),
    KEY idx_feed_subscriptions_source (source_id,status),
    CONSTRAINT fk_feed_subscriptions_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_subscriptions_source
        FOREIGN KEY (source_id) REFERENCES feed_sources(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_subscriptions_folder
        FOREIGN KEY (folder_id) REFERENCES feed_folders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_id BIGINT UNSIGNED NOT NULL,
    item_key_hash CHAR(64) NOT NULL,
    guid_value VARCHAR(1000) NULL,
    canonical_url VARCHAR(1000) NULL,
    title VARCHAR(500) NOT NULL,
    author_name VARCHAR(255) NULL,
    summary MEDIUMTEXT NULL,
    content_html MEDIUMTEXT NULL,
    categories_json TEXT NULL,
    image_url VARCHAR(1000) NULL,
    enclosure_url VARCHAR(1000) NULL,
    enclosure_type VARCHAR(190) NULL,
    enclosure_length BIGINT UNSIGNED NULL,
    content_hash CHAR(64) NOT NULL,
    published_at DATETIME NULL,
    source_updated_at DATETIME NULL,
    discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feed_items_source_key (source_id,item_key_hash),
    KEY idx_feed_items_source_published (source_id,published_at,id),
    KEY idx_feed_items_published (published_at,id),
    KEY idx_feed_items_content_hash (content_hash),
    CONSTRAINT fk_feed_items_source
        FOREIGN KEY (source_id) REFERENCES feed_sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_item_states (
    user_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    is_saved TINYINT(1) NOT NULL DEFAULT 0,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    starred_at DATETIME NULL,
    saved_at DATETIME NULL,
    archived_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,item_id),
    KEY idx_feed_states_item (item_id),
    KEY idx_feed_states_user_read (user_id,is_read,is_archived,item_id),
    KEY idx_feed_states_user_starred (user_id,is_starred,item_id),
    KEY idx_feed_states_user_saved (user_id,is_saved,item_id),
    CONSTRAINT fk_feed_states_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_states_item
        FOREIGN KEY (item_id) REFERENCES feed_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_refresh_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NULL,
    trigger_type ENUM('subscription','manual','scheduled','opml') NOT NULL DEFAULT 'scheduled',
    status ENUM('started','success','not_modified','failed','skipped') NOT NULL DEFAULT 'started',
    http_status SMALLINT UNSIGNED NULL,
    item_count INT UNSIGNED NOT NULL DEFAULT 0,
    new_item_count INT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NULL,
    error_message VARCHAR(1000) NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_feed_refresh_source_started (source_id,started_at,id),
    KEY idx_feed_refresh_status_started (status,started_at),
    CONSTRAINT fk_feed_refresh_source
        FOREIGN KEY (source_id) REFERENCES feed_sources(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_refresh_user
        FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key,setting_value)
VALUES
    ('feed_reader_enabled','1'),
    ('feed_refresh_minutes','30'),
    ('feed_item_retention_days','365'),
    ('feed_public_item_limit','30'),
    ('blog_atom_enabled','1'),
    ('blog_feed_language','en-us'),
    ('blog_feed_copyright','Copyright North Mountain Media'),
    ('module_feed_reader_enabled','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

SELECT 'North Mountain Media RSS & Feed Reader v62 migration complete' AS migration_status;

-- Feed Reader Media & Intelligence v66B
CREATE TABLE IF NOT EXISTS feed_item_media_states (
    user_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    playback_position_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    playback_duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    is_listened TINYINT(1) NOT NULL DEFAULT 0,
    listened_at DATETIME NULL,
    note_text TEXT NULL,
    note_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,item_id),
    KEY idx_feed_media_item (item_id),
    KEY idx_feed_media_user_listened (user_id,is_listened,item_id),
    KEY idx_feed_media_user_note (user_id,note_updated_at,item_id),
    CONSTRAINT fk_feed_media_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_media_state_item FOREIGN KEY (item_id) REFERENCES feed_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_collections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feed_collections_user_name (user_id,name),
    KEY idx_feed_collections_user_order (user_id,sort_order,id),
    CONSTRAINT fk_feed_collections_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_collection_items (
    collection_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (collection_id,item_id),
    KEY idx_feed_collection_items_item (item_id,collection_id),
    CONSTRAINT fk_feed_collection_items_collection FOREIGN KEY (collection_id) REFERENCES feed_collections(id) ON DELETE CASCADE,
    CONSTRAINT fk_feed_collection_items_item FOREIGN KEY (item_id) REFERENCES feed_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content Interactions v66C
CREATE TABLE IF NOT EXISTS content_interaction_settings (
    content_type VARCHAR(40) NOT NULL,
    content_id BIGINT UNSIGNED NOT NULL,
    comments_enabled TINYINT(1) NOT NULL DEFAULT 1,
    replies_enabled TINYINT(1) NOT NULL DEFAULT 1,
    reactions_enabled TINYINT(1) NOT NULL DEFAULT 1,
    moderation_mode ENUM('pre_moderated','registered_auto') NOT NULL DEFAULT 'pre_moderated',
    comments_closed_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (content_type,content_id),
    KEY idx_content_interaction_settings_updated_by (updated_by),
    CONSTRAINT fk_content_interaction_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_type VARCHAR(40) NOT NULL,
    content_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    author_user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    body_hash CHAR(64) NOT NULL,
    status ENUM('pending','approved','hidden','spam','deleted') NOT NULL DEFAULT 'pending',
    depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
    report_count INT UNSIGNED NOT NULL DEFAULT 0,
    edited_at DATETIME NULL,
    moderated_at DATETIME NULL,
    moderated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_comments_content (content_type,content_id,status,parent_id,created_at,id),
    KEY idx_content_comments_author (author_user_id,created_at,id),
    KEY idx_content_comments_moderation (status,report_count,created_at,id),
    KEY idx_content_comments_parent (parent_id,id),
    KEY idx_content_comments_moderated_by (moderated_by),
    KEY idx_content_comments_deleted_by (deleted_by),
    CONSTRAINT fk_content_comments_parent FOREIGN KEY (parent_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comments_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comments_moderator FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_content_comments_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_comment_edits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    editor_user_id BIGINT UNSIGNED NOT NULL,
    previous_body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_comment_edits_comment (comment_id,created_at,id),
    KEY idx_content_comment_edits_editor (editor_user_id,created_at,id),
    CONSTRAINT fk_content_comment_edits_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comment_edits_editor FOREIGN KEY (editor_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_reactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type ENUM('content','comment') NOT NULL,
    content_type VARCHAR(40) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reaction_type ENUM('like','support','insightful') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_reaction_target_user (target_type,content_type,target_id,user_id),
    KEY idx_content_reactions_target (target_type,content_type,target_id,reaction_type),
    KEY idx_content_reactions_user (user_id,created_at,id),
    CONSTRAINT fk_content_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_comment_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    reporter_user_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('open','resolved') NOT NULL DEFAULT 'open',
    resolved_at DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_content_comment_reporter (comment_id,reporter_user_id),
    KEY idx_content_comment_reports_status (status,created_at,id),
    KEY idx_content_comment_reports_reporter (reporter_user_id,created_at,id),
    KEY idx_content_comment_reports_resolved_by (resolved_by),
    CONSTRAINT fk_content_comment_reports_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comment_reports_user FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_comment_reports_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_moderation_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    moderator_user_id BIGINT UNSIGNED NULL,
    action ENUM('approved','hidden','spam','deleted','auto_hidden') NOT NULL,
    note VARCHAR(1000) NULL,
    previous_status ENUM('pending','approved','hidden','spam','deleted') NOT NULL,
    new_status ENUM('pending','approved','hidden','spam','deleted') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_content_moderation_comment (comment_id,created_at,id),
    KEY idx_content_moderation_user (moderator_user_id,created_at,id),
    CONSTRAINT fk_content_moderation_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_moderation_user FOREIGN KEY (moderator_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unified Social Inbox v66D
CREATE TABLE IF NOT EXISTS unified_inbox_workflow (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    workflow_status ENUM('open','waiting','resolved') NOT NULL DEFAULT 'open',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    assigned_user_id BIGINT UNSIGNED NULL,
    needs_response TINYINT(1) NOT NULL DEFAULT 0,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    snoozed_until DATETIME NULL,
    note TEXT NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unified_inbox_workflow_source (source_type,source_id),
    KEY idx_unified_inbox_workflow_queue (workflow_status,needs_response,pinned,priority,snoozed_until),
    KEY idx_unified_inbox_workflow_assignee (assigned_user_id,workflow_status,updated_at),
    CONSTRAINT fk_unified_inbox_workflow_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_unified_inbox_workflow_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unified_inbox_user_state (
    user_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    read_override ENUM('inherit','read','unread') NOT NULL DEFAULT 'inherit',
    archived_at DATETIME NULL,
    last_viewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,source_type,source_id),
    KEY idx_unified_inbox_user_archive (user_id,archived_at,updated_at),
    CONSTRAINT fk_unified_inbox_user_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


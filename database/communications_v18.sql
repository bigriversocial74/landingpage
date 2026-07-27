SET NAMES utf8mb4;
SET time_zone = '+00:00';

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

INSERT INTO communication_threads
    (legacy_key, client_user_id, crm_contact_id, project_id,
     assigned_admin_user_id, subject, status, priority,
     last_message_at, created_by, created_at, updated_at)
SELECT
    CONCAT('legacy:', m.client_user_id, ':', COALESCE(m.project_id, 0)),
    m.client_user_id,
    (
        SELECT c.id
        FROM crm_contacts c
        WHERE c.client_user_id = m.client_user_id
        ORDER BY c.id
        LIMIT 1
    ),
    m.project_id,
    (
        SELECT u.id
        FROM users u
        WHERE u.role = 'admin' AND u.status = 'active'
        ORDER BY u.id
        LIMIT 1
    ),
    COALESCE(NULLIF(MAX(m.subject), ''), 'Portal messages'),
    'open',
    'normal',
    MAX(m.created_at),
    m.client_user_id,
    MIN(m.created_at),
    MAX(m.created_at)
FROM messages m
GROUP BY m.client_user_id, m.project_id
ON DUPLICATE KEY UPDATE
    last_message_at = GREATEST(
        COALESCE(communication_threads.last_message_at, '1970-01-01 00:00:00'),
        COALESCE(VALUES(last_message_at), '1970-01-01 00:00:00')
    ),
    updated_at = GREATEST(communication_threads.updated_at, VALUES(updated_at));

INSERT IGNORE INTO communication_thread_members
    (thread_id, user_id, member_role, last_read_at)
SELECT
    t.id,
    t.client_user_id,
    'client',
    UTC_TIMESTAMP()
FROM communication_threads t
WHERE t.legacy_key LIKE 'legacy:%';

INSERT IGNORE INTO communication_thread_members
    (thread_id, user_id, member_role, last_read_at)
SELECT
    t.id,
    t.assigned_admin_user_id,
    'admin',
    UTC_TIMESTAMP()
FROM communication_threads t
WHERE t.legacy_key LIKE 'legacy:%'
  AND t.assigned_admin_user_id IS NOT NULL;

INSERT IGNORE INTO communication_messages
    (legacy_message_id, thread_id, sender_user_id, sender_role,
     message_type, body, visibility, created_at)
SELECT
    m.id,
    t.id,
    m.sender_user_id,
    m.sender_type,
    'text',
    CONCAT(
        CASE WHEN m.subject <> '' THEN CONCAT(m.subject, '\n\n') ELSE '' END,
        m.body
    ),
    'client',
    m.created_at
FROM messages m
JOIN communication_threads t
  ON t.legacy_key = CONCAT(
      'legacy:',
      m.client_user_id,
      ':',
      COALESCE(m.project_id, 0)
  );

UPDATE communication_threads t
SET t.last_message_at = (
    SELECT MAX(cm.created_at)
    FROM communication_messages cm
    WHERE cm.thread_id = t.id
)
WHERE t.legacy_key LIKE 'legacy:%';

UPDATE communication_thread_members tm
SET tm.last_read_message_id = (
    SELECT MAX(cm.id)
    FROM communication_messages cm
    WHERE cm.thread_id = tm.thread_id
),
tm.last_read_at = UTC_TIMESTAMP()
WHERE EXISTS (
    SELECT 1
    FROM communication_threads t
    WHERE t.id = tm.thread_id
      AND t.legacy_key LIKE 'legacy:%'
);

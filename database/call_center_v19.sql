SET NAMES utf8mb4;
SET time_zone = '+00:00';

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


INSERT INTO call_center_requests (
    source,
    request_type,
    client_user_id,
    crm_contact_id,
    communication_thread_id,
    communication_call_id,
    assigned_admin_user_id,
    requested_by_user_id,
    subject,
    message,
    priority,
    status,
    disposition,
    requested_at,
    queued_at,
    first_response_at,
    ringing_at,
    answered_at,
    ended_at,
    duration_seconds,
    last_contact_at,
    guest_heartbeat_at,
    admin_heartbeat_at,
    attempt_count,
    created_at,
    updated_at
)
SELECT
    'client',
    'live_call',
    conversation.client_user_id,
    conversation.crm_contact_id,
    conversation.id,
    call_record.id,
    CASE
        WHEN initiator.role = 'admin' THEN call_record.initiator_user_id
        WHEN recipient.role = 'admin' THEN call_record.recipient_user_id
        ELSE conversation.assigned_admin_user_id
    END,
    call_record.initiator_user_id,
    conversation.subject,
    'Authenticated portal audio call',
    conversation.priority,
    CASE call_record.status
        WHEN 'ended' THEN 'completed'
        ELSE call_record.status
    END,
    CASE call_record.status
        WHEN 'ended' THEN 'connected'
        WHEN 'accepted' THEN 'connected'
        WHEN 'missed' THEN 'no_answer'
        WHEN 'declined' THEN 'declined'
        WHEN 'cancelled' THEN 'no_answer'
        WHEN 'failed' THEN 'not_available'
        ELSE 'unassigned'
    END,
    call_record.created_at,
    call_record.created_at,
    call_record.answered_at,
    call_record.ringing_at,
    call_record.answered_at,
    call_record.ended_at,
    call_record.duration_seconds,
    COALESCE(call_record.ended_at,call_record.answered_at),
    NULL,
    NULL,
    CASE
        WHEN call_record.status IN ('accepted','ended','declined','missed','failed')
        THEN 1
        ELSE 0
    END,
    call_record.created_at,
    call_record.updated_at
FROM communication_calls call_record
JOIN communication_threads conversation
  ON conversation.id=call_record.thread_id
LEFT JOIN users initiator
  ON initiator.id=call_record.initiator_user_id
LEFT JOIN users recipient
  ON recipient.id=call_record.recipient_user_id
ON DUPLICATE KEY UPDATE
    client_user_id=VALUES(client_user_id),
    crm_contact_id=VALUES(crm_contact_id),
    communication_thread_id=VALUES(communication_thread_id),
    assigned_admin_user_id=VALUES(assigned_admin_user_id),
    requested_by_user_id=VALUES(requested_by_user_id),
    subject=VALUES(subject),
    priority=VALUES(priority),
    status=VALUES(status),
    disposition=VALUES(disposition),
    first_response_at=VALUES(first_response_at),
    ringing_at=VALUES(ringing_at),
    answered_at=VALUES(answered_at),
    ended_at=VALUES(ended_at),
    duration_seconds=VALUES(duration_seconds),
    last_contact_at=VALUES(last_contact_at),
    attempt_count=GREATEST(attempt_count,VALUES(attempt_count)),
    updated_at=VALUES(updated_at);

INSERT INTO crm_contact_call_stats (
    contact_id,total_requests,total_calls,completed_calls,missed_calls,
    declined_calls,total_duration_seconds,average_response_seconds,
    first_call_at,last_call_at,last_call_status,last_call_source
)
SELECT
    request_record.crm_contact_id,
    COUNT(*),
    COALESCE(SUM(request_record.request_type='live_call'),0),
    COALESCE(SUM(
        request_record.request_type='live_call'
        AND request_record.status='completed'
    ),0),
    COALESCE(SUM(
        request_record.request_type='live_call'
        AND request_record.status='missed'
    ),0),
    COALESCE(SUM(
        request_record.request_type='live_call'
        AND request_record.status='declined'
    ),0),
    COALESCE(SUM(
        CASE
            WHEN request_record.request_type='live_call'
            THEN COALESCE(request_record.duration_seconds,0)
            ELSE 0
        END
    ),0),
    AVG(
        CASE
            WHEN request_record.first_response_at IS NOT NULL
            THEN TIMESTAMPDIFF(
                SECOND,
                request_record.requested_at,
                request_record.first_response_at
            )
            ELSE NULL
        END
    ),
    MIN(
        CASE
            WHEN request_record.request_type='live_call'
            THEN request_record.requested_at
            ELSE NULL
        END
    ),
    MAX(
        CASE
            WHEN request_record.request_type='live_call'
            THEN COALESCE(
                request_record.ended_at,
                request_record.answered_at,
                request_record.ringing_at,
                request_record.requested_at
            )
            ELSE NULL
        END
    ),
    SUBSTRING_INDEX(
        GROUP_CONCAT(
            CASE
                WHEN request_record.request_type='live_call'
                THEN request_record.status
                ELSE NULL
            END
            ORDER BY COALESCE(
                request_record.ended_at,
                request_record.answered_at,
                request_record.ringing_at,
                request_record.requested_at
            ) DESC
        ),
        ',',
        1
    ),
    SUBSTRING_INDEX(
        GROUP_CONCAT(
            CASE
                WHEN request_record.request_type='live_call'
                THEN request_record.source
                ELSE NULL
            END
            ORDER BY COALESCE(
                request_record.ended_at,
                request_record.answered_at,
                request_record.ringing_at,
                request_record.requested_at
            ) DESC
        ),
        ',',
        1
    )
FROM call_center_requests request_record
WHERE request_record.crm_contact_id IS NOT NULL
GROUP BY request_record.crm_contact_id
ON DUPLICATE KEY UPDATE
    total_requests=VALUES(total_requests),
    total_calls=VALUES(total_calls),
    completed_calls=VALUES(completed_calls),
    missed_calls=VALUES(missed_calls),
    declined_calls=VALUES(declined_calls),
    total_duration_seconds=VALUES(total_duration_seconds),
    average_response_seconds=VALUES(average_response_seconds),
    first_call_at=VALUES(first_call_at),
    last_call_at=VALUES(last_call_at),
    last_call_status=VALUES(last_call_status),
    last_call_source=VALUES(last_call_source);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('public_call_status', 'available'),
    ('public_call_message', 'Dave is accepting browser audio calls.')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

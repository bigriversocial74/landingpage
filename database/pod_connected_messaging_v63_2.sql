SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pod_relationship_message_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    relationship_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    token_hash CHAR(64) NULL,
    token_hint VARCHAR(16) NULL,
    endpoint_origin VARCHAR(500) NULL,
    endpoint_path VARCHAR(500) NULL,
    secret_ciphertext LONGTEXT NULL,
    secret_iv VARCHAR(64) NULL,
    secret_tag VARCHAR(64) NULL,
    status ENUM('missing','active','revoked','expired','invalid') NOT NULL DEFAULT 'missing',
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    use_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_message_link_direction (relationship_id,direction),
    UNIQUE KEY uq_pod_message_link_token_hash (token_hash),
    KEY idx_pod_message_links_status_expiry (status,expires_at),
    CONSTRAINT fk_pod_message_links_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_message_links_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_message_links_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_message_threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_uuid CHAR(36) NOT NULL,
    relationship_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    status ENUM('open','resolved','archived','blocked') NOT NULL DEFAULT 'open',
    last_message_at DATETIME NULL,
    last_inbound_at DATETIME NULL,
    last_outbound_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_message_threads_uuid (conversation_uuid),
    KEY idx_pod_message_threads_relationship (relationship_id,status,last_message_at),
    KEY idx_pod_message_threads_contact (crm_contact_id,last_message_at),
    CONSTRAINT fk_pod_message_threads_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_message_threads_contact
        FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_message_threads_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_uuid CHAR(36) NOT NULL,
    thread_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('inbound','outbound','system') NOT NULL,
    sender_pod_uuid VARCHAR(80) NOT NULL,
    recipient_pod_uuid VARCHAR(80) NOT NULL,
    sender_display_name VARCHAR(190) NOT NULL,
    message_type ENUM('text','system') NOT NULL DEFAULT 'text',
    body LONGTEXT NULL,
    in_reply_to_uuid CHAR(36) NULL,
    delivery_status ENUM(
        'received','queued','sending','delivered','failed','rejected'
    ) NOT NULL,
    remote_receipt_uuid CHAR(36) NULL,
    remote_received_at DATETIME NULL,
    read_at DATETIME NULL,
    failure_code VARCHAR(80) NULL,
    failure_message VARCHAR(700) NULL,
    sent_by_user_id BIGINT UNSIGNED NULL,
    sent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_messages_uuid (message_uuid),
    KEY idx_pod_messages_thread_created (thread_id,id),
    KEY idx_pod_messages_delivery (delivery_status,updated_at),
    KEY idx_pod_messages_unread (direction,read_at,id),
    KEY idx_pod_messages_reply (in_reply_to_uuid),
    CONSTRAINT fk_pod_messages_thread
        FOREIGN KEY (thread_id) REFERENCES pod_message_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_messages_sent_by
        FOREIGN KEY (sent_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_message_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_uuid CHAR(36) NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    relationship_id BIGINT UNSIGNED NOT NULL,
    receipt_type ENUM(
        'accepted','delivered','duplicate','failed','rejected','retried'
    ) NOT NULL,
    remote_status_code SMALLINT UNSIGNED NULL,
    details_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_message_receipts_uuid (receipt_uuid),
    KEY idx_pod_message_receipts_message (message_id,id),
    KEY idx_pod_message_receipts_relationship (relationship_id,id),
    CONSTRAINT fk_pod_message_receipts_message
        FOREIGN KEY (message_id) REFERENCES pod_messages(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_message_receipts_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_message_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    relationship_id BIGINT UNSIGNED NOT NULL,
    thread_id BIGINT UNSIGNED NULL,
    message_id BIGINT UNSIGNED NULL,
    message_link_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type ENUM(
        'link_issued','link_rotated','link_revoked','remote_link_saved',
        'remote_link_removed','thread_created','message_queued',
        'message_received','message_delivered','message_failed',
        'message_retried','message_rejected','thread_archived','system'
    ) NOT NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pod_message_events_relationship (relationship_id,id),
    KEY idx_pod_message_events_thread (thread_id,id),
    KEY idx_pod_message_events_message (message_id,id),
    CONSTRAINT fk_pod_message_events_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_message_events_thread
        FOREIGN KEY (thread_id) REFERENCES pod_message_threads(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_message_events_message
        FOREIGN KEY (message_id) REFERENCES pod_messages(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_message_events_link
        FOREIGN KEY (message_link_id) REFERENCES pod_relationship_message_links(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_message_events_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

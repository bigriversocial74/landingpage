SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pod_relationship_call_links (
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
    UNIQUE KEY uq_pod_call_link_direction (relationship_id,direction),
    UNIQUE KEY uq_pod_call_link_token_hash (token_hash),
    KEY idx_pod_call_links_status_expiry (status,expires_at),
    CONSTRAINT fk_pod_call_links_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_call_links_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_call_links_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_connected_call_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    relationship_id BIGINT UNSIGNED NOT NULL,
    call_link_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type ENUM(
        'link_issued','link_rotated','link_revoked','remote_link_saved',
        'remote_link_removed','call_launched','call_context_opened',
        'call_context_rejected','system'
    ) NOT NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pod_connected_call_events_relationship (relationship_id,id),
    KEY idx_pod_connected_call_events_link (call_link_id,id),
    CONSTRAINT fk_pod_connected_call_events_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_connected_call_events_link
        FOREIGN KEY (call_link_id) REFERENCES pod_relationship_call_links(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pod_connected_call_events_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

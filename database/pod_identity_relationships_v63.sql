SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pod_identities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pod_uuid VARCHAR(80) NOT NULL,
    local_key VARCHAR(32) NULL,
    is_local TINYINT(1) NOT NULL DEFAULT 0,
    identity_type ENUM(
        'personal_pod','business_pod','artist_pod','project_pod',
        'organization_pod','group_pod'
    ) NOT NULL DEFAULT 'personal_pod',
    owner_user_id BIGINT UNSIGNED NULL,
    display_name VARCHAR(190) NOT NULL,
    public_username VARCHAR(120) NOT NULL,
    summary VARCHAR(700) NULL,
    canonical_origin VARCHAR(500) NULL,
    profile_url VARCHAR(1000) NULL,
    agent_url VARCHAR(1000) NULL,
    main_feed_url VARCHAR(1000) NULL,
    avatar_url VARCHAR(1000) NULL,
    public_key TEXT NULL,
    key_algorithm VARCHAR(40) NULL,
    key_created_at DATETIME NULL,
    key_revoked_at DATETIME NULL,
    verification_status ENUM(
        'local','unverified','discovered','verified','mismatch','revoked'
    ) NOT NULL DEFAULT 'unverified',
    status ENUM('draft','active','suspended','migrating','retired') NOT NULL DEFAULT 'active',
    discovered_at DATETIME NULL,
    last_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_identities_uuid (pod_uuid),
    UNIQUE KEY uq_pod_identities_local_key (local_key),
    KEY idx_pod_identities_local_status (is_local,status),
    KEY idx_pod_identities_origin (canonical_origin(190)),
    KEY idx_pod_identities_owner (owner_user_id),
    CONSTRAINT fk_pod_identities_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_identity_origins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pod_identity_id BIGINT UNSIGNED NOT NULL,
    origin VARCHAR(500) NOT NULL,
    status ENUM('pending','verified','previous','revoked','failed') NOT NULL DEFAULT 'pending',
    verification_method VARCHAR(80) NULL,
    verification_evidence VARCHAR(1000) NULL,
    verified_at DATETIME NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_identity_origins_identity_origin (pod_identity_id,origin(190)),
    KEY idx_pod_identity_origins_status (status,last_seen_at),
    CONSTRAINT fk_pod_identity_origins_identity
        FOREIGN KEY (pod_identity_id) REFERENCES pod_identities(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_relationships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_identity_id BIGINT UNSIGNED NOT NULL,
    remote_identity_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    relationship_type ENUM(
        'personal','family','friend','professional','client','prospect',
        'collaborator','vendor','investor','community','other'
    ) NOT NULL DEFAULT 'professional',
    direction ENUM('inbound','outbound','mutual') NOT NULL DEFAULT 'outbound',
    status ENUM(
        'pending_inbound','pending_outbound','connected','blocked','disconnected'
    ) NOT NULL DEFAULT 'pending_outbound',
    trust_status ENUM('unverified','discovered','verified','mismatch','revoked') NOT NULL DEFAULT 'unverified',
    messaging_permission ENUM('none','request','message') NOT NULL DEFAULT 'request',
    calling_permission ENUM('none','request','call') NOT NULL DEFAULT 'request',
    agent_permission ENUM('none','public','relationship') NOT NULL DEFAULT 'public',
    notes TEXT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    connected_at DATETIME NULL,
    blocked_at DATETIME NULL,
    disconnected_at DATETIME NULL,
    last_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pod_relationship_pair (local_identity_id,remote_identity_id),
    KEY idx_pod_relationships_status (status,updated_at),
    KEY idx_pod_relationships_contact (crm_contact_id),
    KEY idx_pod_relationships_remote (remote_identity_id,status),
    CONSTRAINT fk_pod_relationships_local_identity
        FOREIGN KEY (local_identity_id) REFERENCES pod_identities(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_relationships_remote_identity
        FOREIGN KEY (remote_identity_id) REFERENCES pod_identities(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_relationships_contact
        FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id)
        ON DELETE SET NULL,
    CONSTRAINT chk_pod_relationship_distinct CHECK (local_identity_id<>remote_identity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pod_relationship_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    relationship_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type ENUM(
        'created','requested','approved','connected','permissions_updated',
        'contact_linked','verified','blocked','disconnected','restored','system'
    ) NOT NULL,
    previous_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pod_relationship_events_relationship (relationship_id,id),
    KEY idx_pod_relationship_events_actor (actor_user_id,created_at),
    CONSTRAINT fk_pod_relationship_events_relationship
        FOREIGN KEY (relationship_id) REFERENCES pod_relationships(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pod_relationship_events_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

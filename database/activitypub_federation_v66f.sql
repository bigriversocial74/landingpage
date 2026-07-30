-- North Mountain Media ActivityPub Federation v66F
-- Additive migration. Import after the v63 POD identity and v66E syndication migrations.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS activitypub_actor_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_identity_id BIGINT UNSIGNED NOT NULL,
    key_id VARCHAR(1000) NOT NULL,
    algorithm VARCHAR(40) NOT NULL DEFAULT 'rsa-sha256',
    public_key_pem TEXT NOT NULL,
    private_key_ciphertext MEDIUMTEXT NOT NULL,
    private_key_iv VARCHAR(64) NOT NULL,
    private_key_tag VARCHAR(64) NOT NULL,
    status ENUM('active','retired','revoked') NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retired_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_actor_key_id (key_id(384)),
    KEY idx_activitypub_actor_keys_local_status (local_identity_id,status,created_at,id),
    CONSTRAINT fk_activitypub_actor_keys_identity
        FOREIGN KEY (local_identity_id) REFERENCES pod_identities(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_actor_keys_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_remote_actors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_uri VARCHAR(1000) NOT NULL,
    preferred_username VARCHAR(190) NULL,
    display_name VARCHAR(190) NULL,
    summary TEXT NULL,
    profile_url VARCHAR(1000) NULL,
    avatar_url VARCHAR(1000) NULL,
    inbox_url VARCHAR(1000) NOT NULL,
    shared_inbox_url VARCHAR(1000) NULL,
    public_key_id VARCHAR(1000) NOT NULL,
    public_key_pem TEXT NOT NULL,
    status ENUM('active','blocked','deleted','unavailable') NOT NULL DEFAULT 'active',
    etag VARCHAR(500) NULL,
    last_modified VARCHAR(500) NULL,
    last_error VARCHAR(1000) NULL,
    fetched_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_remote_actor_uri (actor_uri(384)),
    KEY idx_activitypub_remote_actor_status (status,updated_at,id),
    KEY idx_activitypub_remote_actor_key (public_key_id(384))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_followers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    follow_activity_id VARCHAR(1000) NOT NULL,
    status ENUM('pending','approved','rejected','removed') NOT NULL DEFAULT 'pending',
    moderated_by_user_id BIGINT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    followed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_follower_actor (remote_actor_id),
    UNIQUE KEY uq_activitypub_follow_activity (follow_activity_id(384)),
    KEY idx_activitypub_followers_status (status,created_at,id),
    CONSTRAINT fk_activitypub_followers_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_followers_moderator
        FOREIGN KEY (moderated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_inbox_activities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_id VARCHAR(1000) NOT NULL,
    actor_uri VARCHAR(1000) NOT NULL,
    activity_type VARCHAR(80) NOT NULL,
    object_uri VARCHAR(1000) NULL,
    request_digest CHAR(64) NOT NULL,
    signature_key_id VARCHAR(1000) NOT NULL,
    status ENUM('pending','accepted','ignored','rejected','error') NOT NULL DEFAULT 'pending',
    payload_json MEDIUMTEXT NOT NULL,
    error_message VARCHAR(1000) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_inbox_activity (activity_id(384)),
    UNIQUE KEY uq_activitypub_inbox_digest (request_digest),
    KEY idx_activitypub_inbox_status_received (status,received_at,id),
    KEY idx_activitypub_inbox_actor (actor_uri(384),received_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_outbox_activities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_uuid CHAR(36) NOT NULL,
    activity_uri VARCHAR(1000) NOT NULL,
    activity_type ENUM('Create','Update','Delete','Accept','Reject') NOT NULL,
    object_type VARCHAR(80) NOT NULL,
    object_uri VARCHAR(1000) NOT NULL,
    blog_post_id BIGINT UNSIGNED NULL,
    payload_json MEDIUMTEXT NOT NULL,
    payload_sha256 CHAR(64) NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_outbox_uuid (activity_uuid),
    UNIQUE KEY uq_activitypub_outbox_uri (activity_uri(384)),
    UNIQUE KEY uq_activitypub_outbox_payload (payload_sha256),
    KEY idx_activitypub_outbox_published (published_at,id),
    KEY idx_activitypub_outbox_post (blog_post_id,published_at,id),
    CONSTRAINT fk_activitypub_outbox_post
        FOREIGN KEY (blog_post_id) REFERENCES blog_posts(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_activitypub_outbox_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activitypub_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    outbox_activity_id BIGINT UNSIGNED NOT NULL,
    remote_actor_id BIGINT UNSIGNED NOT NULL,
    inbox_url VARCHAR(1000) NOT NULL,
    status ENUM('pending','delivering','delivered','failed') NOT NULL DEFAULT 'pending',
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    response_code SMALLINT UNSIGNED NULL,
    response_excerpt VARCHAR(1000) NULL,
    last_error VARCHAR(1000) NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activitypub_delivery_actor (outbox_activity_id,remote_actor_id),
    KEY idx_activitypub_delivery_queue (status,next_attempt_at,created_at,id),
    CONSTRAINT fk_activitypub_delivery_activity
        FOREIGN KEY (outbox_activity_id) REFERENCES activitypub_outbox_activities(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_activitypub_delivery_actor
        FOREIGN KEY (remote_actor_id) REFERENCES activitypub_remote_actors(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
    ('activitypub_enabled','0'),
    ('activitypub_federate_blog_posts','1'),
    ('activitypub_manual_follow_approval','1'),
    ('activitypub_username',''),
    ('activitypub_display_name',''),
    ('activitypub_summary',''),
    ('activitypub_show_followers','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

SELECT 'North Mountain Media ActivityPub Federation v66F migration complete' AS migration_status;

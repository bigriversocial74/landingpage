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

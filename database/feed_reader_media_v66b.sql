-- North Mountain Media Feed Reader Media & Intelligence v66B
-- Additive migration. Import after database/rss_feed_reader_v62.sql.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

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

SELECT 'North Mountain Media Feed Reader Media v66B migration complete' AS migration_status;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @schema_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=@schema_name
          AND TABLE_NAME='knowledge_assets'
          AND COLUMN_NAME='cover_stored_name'
    ),
    'SELECT 1',
    'ALTER TABLE knowledge_assets ADD COLUMN cover_stored_name VARCHAR(255) NULL AFTER stored_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=@schema_name
          AND TABLE_NAME='knowledge_assets'
          AND COLUMN_NAME='cover_extension'
    ),
    'SELECT 1',
    'ALTER TABLE knowledge_assets ADD COLUMN cover_extension VARCHAR(20) NULL AFTER cover_stored_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=@schema_name
          AND TABLE_NAME='knowledge_assets'
          AND COLUMN_NAME='cover_mime_type'
    ),
    'SELECT 1',
    'ALTER TABLE knowledge_assets ADD COLUMN cover_mime_type VARCHAR(190) NULL AFTER cover_extension'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=@schema_name
          AND TABLE_NAME='knowledge_assets'
          AND COLUMN_NAME='cover_size_bytes'
    ),
    'SELECT 1',
    'ALTER TABLE knowledge_assets ADD COLUMN cover_size_bytes BIGINT UNSIGNED NULL AFTER cover_mime_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=@schema_name
          AND TABLE_NAME='knowledge_assets'
          AND COLUMN_NAME='cover_sha256'
    ),
    'SELECT 1',
    'ALTER TABLE knowledge_assets ADD COLUMN cover_sha256 CHAR(64) NULL AFTER cover_size_bytes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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

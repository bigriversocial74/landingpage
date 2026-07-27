SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS knowledge_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id VARCHAR(190) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
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

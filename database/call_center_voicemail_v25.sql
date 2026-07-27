CREATE TABLE IF NOT EXISTS call_center_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    media_type ENUM('voicemail','call_recording') NOT NULL DEFAULT 'voicemail',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    duration_seconds DECIMAL(12,3) NULL,
    sha256 CHAR(64) NOT NULL,
    transcript_status ENUM(
        'not_requested','queued','processing','review','approved','failed'
    ) NOT NULL DEFAULT 'not_requested',
    transcription_source ENUM('manual','local','imported') NULL,
    raw_transcript_text LONGTEXT NULL,
    reviewed_transcript_text LONGTEXT NULL,
    transcription_error TEXT NULL,
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_call_center_media_stored_name (stored_name),
    KEY idx_call_center_media_request_created (request_id,created_at),
    KEY idx_call_center_media_transcript_status (transcript_status,updated_at),
    CONSTRAINT fk_call_center_media_request
        FOREIGN KEY (request_id) REFERENCES call_center_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_call_center_media_uploaded_by
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_call_center_media_reviewed_by
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

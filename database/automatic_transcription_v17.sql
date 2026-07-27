SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS knowledge_transcription_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id BIGINT UNSIGNED NOT NULL,
    status ENUM('queued', 'processing', 'review', 'approved', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    provider VARCHAR(60) NOT NULL DEFAULT 'openai',
    model VARCHAR(120) NOT NULL,
    language VARCHAR(20) NULL,
    prompt TEXT NULL,
    speaker_diarization TINYINT(1) NOT NULL DEFAULT 0,
    raw_transcript_text LONGTEXT NULL,
    reviewed_transcript_text LONGTEXT NULL,
    segments_json JSON NULL,
    usage_json JSON NULL,
    response_json JSON NULL,
    error_message TEXT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    requested_by BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_transcription_jobs_queue (status, next_attempt_at, queued_at),
    KEY idx_transcription_jobs_asset_created (asset_id, created_at),
    CONSTRAINT fk_transcription_jobs_asset
        FOREIGN KEY (asset_id) REFERENCES knowledge_assets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_transcription_jobs_requested_by
        FOREIGN KEY (requested_by) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_transcription_jobs_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

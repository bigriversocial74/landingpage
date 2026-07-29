-- VP3 POD Signed Managed Update Agent v65
-- Import after database/vp3_pod_licensing_v64.sql.
-- Additive and repeat-safe. No customer-content table is altered or removed.

CREATE TABLE IF NOT EXISTS vp3_update_releases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_uuid CHAR(36) NOT NULL,
    provider_release_id VARCHAR(120) NOT NULL,
    product_code VARCHAR(80) NOT NULL DEFAULT 'vp3-pod',
    version VARCHAR(40) NOT NULL,
    channel ENUM('stable','preview','security') NOT NULL DEFAULT 'stable',
    release_type ENUM('standard','security','critical') NOT NULL DEFAULT 'standard',
    manifest_version INT UNSIGNED NOT NULL DEFAULT 1,
    manifest_json LONGTEXT NOT NULL,
    manifest_hash CHAR(64) NOT NULL,
    signing_key_id VARCHAR(190) NOT NULL,
    package_url VARCHAR(1000) NOT NULL,
    package_sha256 CHAR(64) NOT NULL,
    package_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    package_signature TEXT NOT NULL,
    published_at DATETIME NULL,
    expires_at DATETIME NULL,
    release_notes TEXT NULL,
    eligibility_state ENUM('unknown','eligible','denied') NOT NULL DEFAULT 'unknown',
    eligibility_reasons_json LONGTEXT NULL,
    discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_update_release_uuid (release_uuid),
    UNIQUE KEY uq_vp3_update_provider_release_id (provider_release_id),
    KEY idx_vp3_update_release_version_channel (version, channel),
    KEY idx_vp3_update_release_discovered (discovered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_update_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_uuid CHAR(36) NOT NULL,
    release_id BIGINT UNSIGNED NULL,
    requested_by_user_id BIGINT UNSIGNED NULL,
    requested_by_type ENUM('administrator','worker','system') NOT NULL DEFAULT 'administrator',
    operation ENUM('check','prepare','install','rollback') NOT NULL,
    status ENUM(
        'queued','checking','downloading','verifying','staging','backing_up',
        'installing','migrating','health_check','completed','failed',
        'rolling_back','rolled_back','cancelled'
    ) NOT NULL DEFAULT 'queued',
    current_step VARCHAR(120) NULL,
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    previous_version VARCHAR(40) NULL,
    target_version VARCHAR(40) NULL,
    package_path VARCHAR(1000) NULL,
    staging_path VARCHAR(1000) NULL,
    backup_id BIGINT UNSIGNED NULL,
    installed_files_json LONGTEXT NULL,
    created_files_json LONGTEXT NULL,
    migration_results_json LONGTEXT NULL,
    health_results_json LONGTEXT NULL,
    error_code VARCHAR(120) NULL,
    error_message TEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_update_job_uuid (job_uuid),
    KEY idx_vp3_update_job_status (status),
    KEY idx_vp3_update_job_release (release_id),
    KEY idx_vp3_update_job_created (created_at),
    CONSTRAINT fk_vp3_update_job_release
        FOREIGN KEY (release_id) REFERENCES vp3_update_releases(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_update_backups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_uuid CHAR(36) NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    source_version VARCHAR(40) NOT NULL,
    target_version VARCHAR(40) NULL,
    file_archive_path VARCHAR(1000) NOT NULL,
    file_archive_sha256 CHAR(64) NOT NULL,
    file_archive_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    database_dump_path VARCHAR(1000) NOT NULL,
    database_dump_sha256 CHAR(64) NOT NULL,
    database_dump_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    inventory_path VARCHAR(1000) NOT NULL,
    inventory_sha256 CHAR(64) NOT NULL,
    status ENUM('creating','ready','restoring','restored','failed','expired') NOT NULL DEFAULT 'creating',
    retention_until DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    restored_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_update_backup_uuid (backup_uuid),
    KEY idx_vp3_update_backup_job (job_id),
    KEY idx_vp3_update_backup_status (status),
    CONSTRAINT fk_vp3_update_backup_job
        FOREIGN KEY (job_id) REFERENCES vp3_update_jobs(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS vp3_update_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NULL,
    migration_path VARCHAR(500) NOT NULL,
    migration_sha256 CHAR(64) NOT NULL,
    execution_order INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('pending','running','completed','failed','rolled_back') NOT NULL DEFAULT 'pending',
    statement_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_update_job_migration (job_id, migration_path),
    KEY idx_vp3_update_migration_release (release_id),
    CONSTRAINT fk_vp3_update_migration_job
        FOREIGN KEY (job_id) REFERENCES vp3_update_jobs(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_vp3_update_migration_release
        FOREIGN KEY (release_id) REFERENCES vp3_update_releases(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vp3_update_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_uuid CHAR(36) NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    release_id BIGINT UNSIGNED NULL,
    receipt_type ENUM(
        'update_check','manifest_verify','package_download','package_verify',
        'backup','stage','install','migration','health_check','rollback','cleanup'
    ) NOT NULL,
    outcome ENUM('success','warning','denied','error') NOT NULL,
    status_code VARCHAR(120) NULL,
    message VARCHAR(500) NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vp3_update_receipt_uuid (receipt_uuid),
    KEY idx_vp3_update_receipt_job (job_id),
    KEY idx_vp3_update_receipt_release (release_id),
    KEY idx_vp3_update_receipt_created (created_at),
    CONSTRAINT fk_vp3_update_receipt_job
        FOREIGN KEY (job_id) REFERENCES vp3_update_jobs(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_vp3_update_receipt_release
        FOREIGN KEY (release_id) REFERENCES vp3_update_releases(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

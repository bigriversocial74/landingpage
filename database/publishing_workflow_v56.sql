-- North Mountain Media Publishing Workflow v56
-- Import-safe compatibility revision
-- Uses information_schema checks before each ADD COLUMN statement
-- Compatible with MySQL and MariaDB versions that support PREPARE/EXECUTE

-- DDL statements auto-commit in MySQL/MariaDB, so this migration does not
-- wrap schema changes in START TRANSACTION / COMMIT.

SET @nmm_schema := DATABASE();

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_posts` ADD COLUMN `canonical_url` VARCHAR(500) NULL AFTER `seo_description`',
        'SELECT ''blog_posts.canonical_url already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_posts'
      AND COLUMN_NAME = 'canonical_url'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_posts` ADD COLUMN `autosave_json` MEDIUMTEXT NULL AFTER `canonical_url`',
        'SELECT ''blog_posts.autosave_json already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_posts'
      AND COLUMN_NAME = 'autosave_json'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_posts` ADD COLUMN `autosaved_at` DATETIME NULL AFTER `autosave_json`',
        'SELECT ''blog_posts.autosaved_at already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_posts'
      AND COLUMN_NAME = 'autosaved_at'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_posts` ADD COLUMN `autosaved_by` BIGINT UNSIGNED NULL AFTER `autosaved_at`',
        'SELECT ''blog_posts.autosaved_by already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_posts'
      AND COLUMN_NAME = 'autosaved_by'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `resume_posts` ADD COLUMN `autosave_json` MEDIUMTEXT NULL AFTER `link_label`',
        'SELECT ''resume_posts.autosave_json already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'resume_posts'
      AND COLUMN_NAME = 'autosave_json'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `resume_posts` ADD COLUMN `autosaved_at` DATETIME NULL AFTER `autosave_json`',
        'SELECT ''resume_posts.autosaved_at already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'resume_posts'
      AND COLUMN_NAME = 'autosaved_at'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `resume_posts` ADD COLUMN `autosaved_by` BIGINT UNSIGNED NULL AFTER `autosaved_at`',
        'SELECT ''resume_posts.autosaved_by already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'resume_posts'
      AND COLUMN_NAME = 'autosaved_by'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_media` ADD COLUMN `focal_x` DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER `caption`',
        'SELECT ''blog_media.focal_x already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_media'
      AND COLUMN_NAME = 'focal_x'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_media` ADD COLUMN `focal_y` DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER `focal_x`',
        'SELECT ''blog_media.focal_y already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_media'
      AND COLUMN_NAME = 'focal_y'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

SET @nmm_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `blog_media` ADD COLUMN `crop_ratio` ENUM(''original'',''16:9'',''4:3'',''1:1'',''3:4'') NOT NULL DEFAULT ''original'' AFTER `focal_y`',
        'SELECT ''blog_media.crop_ratio already exists'' AS migration_status'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmm_schema
      AND TABLE_NAME = 'blog_media'
      AND COLUMN_NAME = 'crop_ratio'
);
PREPARE nmm_stmt FROM @nmm_sql;
EXECUTE nmm_stmt;
DEALLOCATE PREPARE nmm_stmt;

CREATE TABLE IF NOT EXISTS blog_post_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    revision_type ENUM(
        'manual','autosave','restore','duplicate'
    ) NOT NULL DEFAULT 'manual',
    snapshot_json MEDIUMTEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_blog_revision_post (post_id,created_at,id),
    KEY idx_blog_revision_type (revision_type,created_at),
    CONSTRAINT fk_blog_revision_post
        FOREIGN KEY (post_id)
        REFERENCES blog_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_blog_revision_user
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resume_post_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    revision_type ENUM(
        'manual','autosave','restore','duplicate','reorder'
    ) NOT NULL DEFAULT 'manual',
    snapshot_json MEDIUMTEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resume_revision_post (post_id,created_at,id),
    KEY idx_resume_revision_type (revision_type,created_at),
    CONSTRAINT fk_resume_revision_post
        FOREIGN KEY (post_id)
        REFERENCES resume_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_resume_revision_user
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key,setting_value)
VALUES
    ('blog_title','North Mountain Media Journal'),
    ('blog_intro','Ideas, systems, and things being built.'),
    ('blog_description','Articles about product strategy, connected business systems, ecommerce, CRM, operational design, music platforms, and independent software development.'),
    ('blog_posts_per_page','9'),
    ('blog_default_author_user_id',''),
    ('blog_rss_enabled','1'),
    ('blog_sitemap_enabled','1')
ON DUPLICATE KEY UPDATE
    setting_value=setting_value;

SELECT 'North Mountain Media Publishing Workflow v56 migration complete' AS migration_status;

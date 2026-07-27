SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @has_profile_image_stored_name := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'profile_image_stored_name'
);
SET @sql := IF(
    @has_profile_image_stored_name = 0,
    'ALTER TABLE users ADD COLUMN profile_image_stored_name VARCHAR(255) NULL AFTER must_change_password',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @has_profile_image_mime := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'profile_image_mime'
);
SET @sql := IF(
    @has_profile_image_mime = 0,
    'ALTER TABLE users ADD COLUMN profile_image_mime VARCHAR(100) NULL AFTER profile_image_stored_name',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @has_profile_image_updated_at := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'profile_image_updated_at'
);
SET @sql := IF(
    @has_profile_image_updated_at = 0,
    'ALTER TABLE users ADD COLUMN profile_image_updated_at DATETIME NULL AFTER profile_image_mime',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

DELETE FROM settings
WHERE setting_key = 'contact_email';

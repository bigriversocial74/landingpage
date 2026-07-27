SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @has_message_stage := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema=DATABASE()
      AND table_name='call_center_requests'
      AND column_name='message_stage'
);
SET @sql := IF(
    @has_message_stage=0,
    'ALTER TABLE call_center_requests ADD COLUMN message_stage ENUM(''new'',''listened'',''follow_up'',''resolved'',''archived'') NOT NULL DEFAULT ''new'' AFTER status',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @has_listened_at := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema=DATABASE()
      AND table_name='call_center_requests'
      AND column_name='listened_at'
);
SET @sql := IF(
    @has_listened_at=0,
    'ALTER TABLE call_center_requests ADD COLUMN listened_at DATETIME NULL AFTER message_stage',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @has_message_stage_updated_at := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema=DATABASE()
      AND table_name='call_center_requests'
      AND column_name='message_stage_updated_at'
);
SET @sql := IF(
    @has_message_stage_updated_at=0,
    'ALTER TABLE call_center_requests ADD COLUMN message_stage_updated_at DATETIME NULL AFTER listened_at',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @has_message_stage_updated_by := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema=DATABASE()
      AND table_name='call_center_requests'
      AND column_name='message_stage_updated_by_user_id'
);
SET @sql := IF(
    @has_message_stage_updated_by=0,
    'ALTER TABLE call_center_requests ADD COLUMN message_stage_updated_by_user_id BIGINT UNSIGNED NULL AFTER message_stage_updated_at',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @has_message_stage_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='call_center_requests'
      AND index_name='idx_call_center_message_stage'
);
SET @sql := IF(
    @has_message_stage_index=0,
    'ALTER TABLE call_center_requests ADD KEY idx_call_center_message_stage (crm_contact_id,message_stage,requested_at)',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

UPDATE call_center_requests
SET message_stage=CASE
    WHEN status IN ('resolved','spam') THEN 'resolved'
    ELSE 'new'
END
WHERE request_type='voicemail'
   OR (
        source='public'
        AND request_type IN ('call_request','callback')
   );


ALTER TABLE crm_contacts
    MODIFY email VARCHAR(190) NULL;

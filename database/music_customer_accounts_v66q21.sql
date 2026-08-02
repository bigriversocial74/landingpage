SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS music_customer_account_state (
    user_id BIGINT UNSIGNED NOT NULL,
    email_verified_at DATETIME NULL,
    pending_email VARCHAR(190) NULL,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    last_verification_sent_at DATETIME NULL,
    last_password_reset_at DATETIME NULL,
    last_email_change_requested_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_music_customer_state_verification (email_verified_at),
    KEY idx_music_customer_state_pending_email (pending_email),
    CONSTRAINT fk_music_customer_state_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS music_customer_account_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    purpose ENUM('verify_email','password_reset','admin_reset','change_email') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    target_email VARCHAR(190) NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    request_ip VARCHAR(64) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_music_customer_account_token_hash (token_hash),
    KEY idx_music_customer_account_token_lookup (purpose,token_hash,consumed_at,expires_at),
    KEY idx_music_customer_account_token_user (user_id,purpose,created_at),
    KEY idx_music_customer_account_token_cleanup (expires_at,consumed_at),
    CONSTRAINT fk_music_customer_account_token_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_music_customer_account_token_creator
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO music_customer_account_state
    (user_id,email_verified_at,auth_version)
SELECT id,UTC_TIMESTAMP(),1
FROM users
WHERE role='customer'
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id);

INSERT INTO settings (setting_key,setting_value)
SELECT 'music_customer_email_verification_required','0'
WHERE NOT EXISTS (
    SELECT 1 FROM settings
    WHERE setting_key='music_customer_email_verification_required'
);

INSERT INTO settings (setting_key,setting_value)
SELECT 'music_customer_password_recovery_enabled','1'
WHERE NOT EXISTS (
    SELECT 1 FROM settings
    WHERE setting_key='music_customer_password_recovery_enabled'
);

<?php
declare(strict_types=1);

/* North Mountain Media build: 20260802-music-customer-lifecycle-v66Q21 */

function music_customer_lifecycle_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;

    try {
        $statement = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                   "music_customer_account_state",
                   "music_customer_account_tokens"
               )'
        );
        return $available = (int)$statement->fetchColumn() === 2;
    } catch (Throwable) {
        return $available = false;
    }
}

function music_customer_lifecycle_ready(): bool
{
    return music_customer_accounts_ready()
        && music_customer_lifecycle_schema_available();
}

function music_customer_setting_enabled(string $key, bool $default = false): bool
{
    try {
        return setting($key, $default ? '1' : '0') === '1';
    } catch (Throwable) {
        return $default;
    }
}

function music_customer_email_verification_required(): bool
{
    return music_customer_setting_enabled(
        'music_customer_email_verification_required',
        false
    );
}

function music_customer_password_recovery_enabled(): bool
{
    return music_customer_setting_enabled(
        'music_customer_password_recovery_enabled',
        true
    );
}

function music_customer_mail_configuration(): array
{
    $enabled = music_customer_setting_enabled('notification_email_enabled', false);
    $from = strtolower(trim((string)setting('notification_email_from', '')));
    $name = trim((string)setting('notification_email_from_name', 'North Mountain Media'));

    return [
        'enabled' => $enabled,
        'from' => filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : '',
        'name' => mb_substr($name !== '' ? $name : 'North Mountain Media', 0, 120),
    ];
}

function music_customer_mail_ready(): bool
{
    $mail = music_customer_mail_configuration();
    return $mail['enabled'] && $mail['from'] !== '';
}

function music_customer_send_account_email(
    string $recipient,
    string $subject,
    string $body
): bool {
    $recipient = strtolower(trim($recipient));
    $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? '');
    $mail = music_customer_mail_configuration();

    if (
        !music_customer_mail_ready()
        || !filter_var($recipient, FILTER_VALIDATE_EMAIL)
        || $subject === ''
    ) {
        return false;
    }

    $fromName = preg_replace('/[\r\n]+/', ' ', (string)$mail['name']) ?: 'North Mountain Media';
    $headers = implode("\r\n", [
        'From: ' . $fromName . ' <' . $mail['from'] . '>',
        'Reply-To: ' . $mail['from'],
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Auto-Response-Suppress: All',
    ]);

    return @mail(
        $recipient,
        mb_substr($subject, 0, 190),
        str_replace(["\r\n", "\r"], "\n", $body),
        $headers
    );
}

function music_customer_save_security_settings(
    bool $verificationRequired,
    bool $recoveryEnabled
): void {
    if (!music_customer_lifecycle_ready()) {
        throw new RuntimeException(
            'Import database/music_customer_accounts_v66q21.sql before saving customer security settings.'
        );
    }
    if ($verificationRequired && !music_customer_mail_ready()) {
        throw new RuntimeException(
            'Enable Notification Delivery email and configure a valid From address before requiring customer email verification.'
        );
    }

    $statement = db()->prepare(
        'INSERT INTO settings (setting_key,setting_value)
         VALUES (:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ([
        'music_customer_email_verification_required' => $verificationRequired ? '1' : '0',
        'music_customer_password_recovery_enabled' => $recoveryEnabled ? '1' : '0',
    ] as $key => $value) {
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
    }
}

function music_customer_state(int $userId, bool $legacyVerified = true): array
{
    if (!music_customer_lifecycle_schema_available() || $userId <= 0) {
        return [
            'user_id' => $userId,
            'email_verified_at' => null,
            'pending_email' => null,
            'auth_version' => 1,
        ];
    }

    $statement = db()->prepare(
        'SELECT * FROM music_customer_account_state
         WHERE user_id=:user_id LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $state = $statement->fetch();
    if ($state) return $state;

    db()->prepare(
        'INSERT INTO music_customer_account_state
            (user_id,email_verified_at,auth_version)
         VALUES
            (:user_id,CASE WHEN :legacy_verified=1 THEN UTC_TIMESTAMP() ELSE NULL END,1)'
    )->execute([
        'user_id' => $userId,
        'legacy_verified' => $legacyVerified ? 1 : 0,
    ]);

    $statement->execute(['user_id' => $userId]);
    return $statement->fetch() ?: [
        'user_id' => $userId,
        'email_verified_at' => $legacyVerified ? gmdate('Y-m-d H:i:s') : null,
        'pending_email' => null,
        'auth_version' => 1,
    ];
}

function music_customer_create_state(int $userId, bool $verified): void
{
    db()->prepare(
        'INSERT INTO music_customer_account_state
            (user_id,email_verified_at,auth_version)
         VALUES
            (:user_id,CASE WHEN :verified=1 THEN UTC_TIMESTAMP() ELSE NULL END,1)
         ON DUPLICATE KEY UPDATE
            email_verified_at=VALUES(email_verified_at),
            pending_email=NULL,
            auth_version=GREATEST(auth_version,1)'
    )->execute([
        'user_id' => $userId,
        'verified' => $verified ? 1 : 0,
    ]);
}

function music_customer_start_secure_session(int $userId): void
{
    $state = music_customer_state($userId, true);
    session_regenerate_id(true);
    rotate_csrf_token();
    $_SESSION['user_id'] = $userId;
    $_SESSION['music_customer_auth_version'] = (int)$state['auth_version'];
    $_SESSION['session_created_at'] = time();
    $_SESSION['session_last_activity'] = time();
    $_SESSION['session_regenerated_at'] = time();
    $_SESSION['session_user_agent'] = hash(
        'sha256',
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
    db()->prepare(
        'UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute(['id' => $userId]);
}

function require_music_customer_v21(): array
{
    $user = current_user();
    if (!$user || (string)($user['role'] ?? '') !== 'customer') {
        redirect('portal/login.php?role=customer');
    }
    if (!music_customer_accounts_active()) {
        logout_user();
        redirect('music-library.php?customer_accounts=disabled');
    }
    if (!music_customer_lifecycle_ready()) {
        logout_user();
        redirect('portal/login.php?role=customer&setup=required');
    }

    $state = music_customer_state((int)$user['id'], true);
    $sessionVersion = (int)($_SESSION['music_customer_auth_version'] ?? 0);
    if ($sessionVersion < 1 || $sessionVersion !== (int)$state['auth_version']) {
        logout_user();
        redirect('portal/login.php?role=customer&session=expired');
    }
    if (
        music_customer_email_verification_required()
        && empty($state['email_verified_at'])
    ) {
        logout_user();
        redirect('portal/customer-verify.php?pending=1');
    }

    $user['music_customer_state'] = $state;
    return $user;
}

function music_customer_record_login_attempt(
    string $email,
    string $ip,
    bool $success
): void {
    db()->prepare(
        'INSERT INTO login_attempts (email,ip_address,success)
         VALUES (:email,:ip_address,:success)'
    )->execute([
        'email' => strtolower($email),
        'ip_address' => $ip,
        'success' => $success ? 1 : 0,
    ]);
}

function music_customer_attempt_login_v21(
    string $email,
    string $password
): array {
    $email = strtolower(trim($email));
    $ip = request_ip();
    if (login_blocked($email, $ip)) {
        return ['ok' => false, 'reason' => 'blocked'];
    }

    $statement = db()->prepare(
        'SELECT * FROM users
         WHERE email=:email AND role="customer" AND status="active"
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    $valid = $user && password_verify($password, (string)$user['password_hash']);
    music_customer_record_login_attempt($email, $ip, (bool)$valid);
    if (!$valid) return ['ok' => false, 'reason' => 'invalid'];

    $state = music_customer_state((int)$user['id'], true);
    if (
        music_customer_email_verification_required()
        && empty($state['email_verified_at'])
    ) {
        music_customer_send_verification_link((int)$user['id']);
        return ['ok' => false, 'reason' => 'verification_required'];
    }

    music_customer_start_secure_session((int)$user['id']);
    log_activity('login', 'user', (int)$user['id'], ['role' => 'customer']);
    return ['ok' => true, 'reason' => 'accepted'];
}

function music_customer_token_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function music_customer_issue_token(
    int $userId,
    string $purpose,
    ?string $targetEmail = null,
    int $ttlSeconds = 3600,
    ?int $createdByUserId = null
): array {
    $allowed = ['verify_email', 'password_reset', 'admin_reset', 'change_email'];
    if (!in_array($purpose, $allowed, true) || $userId <= 0) {
        throw new RuntimeException('Invalid customer account token request.');
    }

    $raw = music_customer_token_encode(random_bytes(32));
    $hash = hash('sha256', $raw);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + max(300, min(86400, $ttlSeconds)));
    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE music_customer_account_tokens
             SET consumed_at=COALESCE(consumed_at,UTC_TIMESTAMP())
             WHERE user_id=:user_id AND purpose=:purpose AND consumed_at IS NULL'
        )->execute([
            'user_id' => $userId,
            'purpose' => $purpose,
        ]);
        $pdo->prepare(
            'DELETE FROM music_customer_account_tokens
             WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)'
        )->execute();
        $pdo->prepare(
            'INSERT INTO music_customer_account_tokens
                (user_id,purpose,token_hash,target_email,expires_at,
                 request_ip,created_by_user_id)
             VALUES
                (:user_id,:purpose,:token_hash,:target_email,:expires_at,
                 :request_ip,:created_by_user_id)'
        )->execute([
            'user_id' => $userId,
            'purpose' => $purpose,
            'token_hash' => $hash,
            'target_email' => $targetEmail,
            'expires_at' => $expiresAt,
            'request_ip' => request_ip(),
            'created_by_user_id' => $createdByUserId,
        ]);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    $path = in_array($purpose, ['verify_email', 'change_email'], true)
        ? 'portal/customer-verify.php?token=' . rawurlencode($raw)
        : 'portal/customer-password.php?token=' . rawurlencode($raw);

    return [
        'token' => $raw,
        'url' => app_url($path),
        'expires_at' => $expiresAt,
        'purpose' => $purpose,
    ];
}

function music_customer_token_record(
    string $rawToken,
    array $purposes
): ?array {
    $rawToken = trim($rawToken);
    if ($rawToken === '' || strlen($rawToken) > 180 || !$purposes) return null;

    $placeholders = implode(',', array_fill(0, count($purposes), '?'));
    $statement = db()->prepare(
        'SELECT token.*,user.email,user.display_name,user.status,user.role
         FROM music_customer_account_tokens token
         JOIN users user ON user.id=token.user_id
         WHERE token.token_hash=?
           AND token.purpose IN (' . $placeholders . ')
           AND token.consumed_at IS NULL
           AND token.expires_at>UTC_TIMESTAMP()
           AND user.role="customer"
         LIMIT 1'
    );
    $statement->execute(array_merge([hash('sha256', $rawToken)], $purposes));
    $record = $statement->fetch();
    return $record ?: null;
}

function music_customer_send_verification_link(
    int $userId,
    string $purpose = 'verify_email',
    ?string $targetEmail = null
): array {
    if (!music_customer_mail_ready()) {
        return ['sent' => false, 'url' => '', 'rate_limited' => false];
    }

    $statement = db()->prepare(
        'SELECT id,email,display_name,status FROM users
         WHERE id=:user_id AND role="customer" LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();
    if (!$user || $user['status'] !== 'active') {
        return ['sent' => false, 'url' => '', 'rate_limited' => false];
    }

    $recipient = strtolower(trim($targetEmail ?: (string)$user['email']));
    $identity = hash('sha256', $userId . '|' . $purpose . '|' . $recipient);
    if (rate_limit_exceeded('music_customer_verification', $identity, 3, 3600)) {
        return ['sent' => false, 'url' => '', 'rate_limited' => true];
    }

    $issued = music_customer_issue_token(
        $userId,
        $purpose,
        $targetEmail,
        3600
    );
    $subject = $purpose === 'change_email'
        ? 'Confirm your new North Mountain Media email'
        : 'Verify your North Mountain Media listener account';
    $body = "Hello " . (string)$user['display_name'] . ",\n\n"
        . ($purpose === 'change_email'
            ? "Confirm this email address for your listener account:\n"
            : "Verify your listener account to activate private playlists:\n")
        . $issued['url'] . "\n\n"
        . "This one-time link expires in one hour. If you did not request it, ignore this message.\n";
    $sent = music_customer_send_account_email($recipient, $subject, $body);

    if ($sent) {
        db()->prepare(
            'UPDATE music_customer_account_state
             SET last_verification_sent_at=UTC_TIMESTAMP()
             WHERE user_id=:user_id'
        )->execute(['user_id' => $userId]);
        log_activity('music_customer_verification_sent', 'user', $userId, [
            'purpose' => $purpose,
        ]);
    }

    return ['sent' => $sent, 'url' => $issued['url'], 'rate_limited' => false];
}

function music_customer_register_v21(
    string $name,
    string $email,
    string $password,
    string $confirm
): array {
    $name = trim($name);
    $email = strtolower(trim($email));
    if ($name === '' || mb_strlen($name) > 160) {
        throw new RuntimeException('Enter your name.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new RuntimeException('Enter a valid email address.');
    }
    $errors = password_policy_errors($password, $email);
    if ($errors) throw new RuntimeException(implode(' ', $errors));
    if (!hash_equals($password, $confirm)) {
        throw new RuntimeException('The passwords do not match.');
    }

    $verificationRequired = music_customer_email_verification_required();
    if ($verificationRequired && !music_customer_mail_ready()) {
        throw new RuntimeException('Customer registration email is not configured. Contact the site owner.');
    }

    $existing = db()->prepare(
        'SELECT id,role,status FROM users WHERE email=:email LIMIT 1'
    );
    $existing->execute(['email' => $email]);
    $existingUser = $existing->fetch();
    if ($existingUser) {
        if (
            $existingUser['role'] === 'customer'
            && $existingUser['status'] === 'active'
            && $verificationRequired
        ) {
            $state = music_customer_state((int)$existingUser['id'], true);
            if (empty($state['email_verified_at'])) {
                music_customer_send_verification_link((int)$existingUser['id']);
            }
        }
        return [
            'created' => false,
            'user_id' => 0,
            'verification_required' => $verificationRequired,
            'email_sent' => false,
        ];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'INSERT INTO users
                (role,email,password_hash,display_name,status,must_change_password)
             VALUES
                ("customer",:email,:password_hash,:display_name,"active",0)'
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $name,
        ]);
        $userId = (int)$pdo->lastInsertId();
        music_customer_create_state($userId, !$verificationRequired);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$exception->getCode() === '23000') {
            return [
                'created' => false,
                'user_id' => 0,
                'verification_required' => $verificationRequired,
                'email_sent' => false,
            ];
        }
        throw $exception;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    $emailSent = false;
    if ($verificationRequired) {
        $emailSent = music_customer_send_verification_link($userId)['sent'];
    }
    log_activity('music_customer_registered', 'user', $userId, [
        'verification_required' => $verificationRequired,
    ]);

    return [
        'created' => true,
        'user_id' => $userId,
        'verification_required' => $verificationRequired,
        'email_sent' => $emailSent,
    ];
}

function music_customer_verify_token(string $rawToken): array
{
    $record = music_customer_token_record(
        $rawToken,
        ['verify_email', 'change_email']
    );
    if (!$record) {
        throw new RuntimeException('This verification link is invalid or has expired.');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $locked = $pdo->prepare(
            'SELECT * FROM music_customer_account_tokens
             WHERE id=:id AND consumed_at IS NULL
               AND expires_at>UTC_TIMESTAMP()
             FOR UPDATE'
        );
        $locked->execute(['id' => $record['id']]);
        $token = $locked->fetch();
        if (!$token) throw new RuntimeException('This verification link is no longer available.');

        if ($token['purpose'] === 'change_email') {
            $targetEmail = strtolower(trim((string)$token['target_email']));
            if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The pending email address is invalid.');
            }
            $duplicate = $pdo->prepare(
                'SELECT id FROM users
                 WHERE email=:email AND id<>:user_id LIMIT 1'
            );
            $duplicate->execute([
                'email' => $targetEmail,
                'user_id' => $token['user_id'],
            ]);
            if ($duplicate->fetchColumn()) {
                throw new RuntimeException('That email address is already used by another account.');
            }
            $pdo->prepare(
                'UPDATE users SET email=:email
                 WHERE id=:user_id AND role="customer"'
            )->execute([
                'email' => $targetEmail,
                'user_id' => $token['user_id'],
            ]);
            $pdo->prepare(
                'UPDATE music_customer_account_state
                 SET email_verified_at=UTC_TIMESTAMP(),pending_email=NULL,
                     auth_version=auth_version+1
                 WHERE user_id=:user_id'
            )->execute(['user_id' => $token['user_id']]);
        } else {
            $pdo->prepare(
                'UPDATE music_customer_account_state
                 SET email_verified_at=UTC_TIMESTAMP(),pending_email=NULL
                 WHERE user_id=:user_id'
            )->execute(['user_id' => $token['user_id']]);
        }

        $pdo->prepare(
            'UPDATE music_customer_account_tokens
             SET consumed_at=UTC_TIMESTAMP()
             WHERE user_id=:user_id AND purpose=:purpose AND consumed_at IS NULL'
        )->execute([
            'user_id' => $token['user_id'],
            'purpose' => $token['purpose'],
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    log_activity('music_customer_email_verified', 'user', (int)$record['user_id'], [
        'purpose' => (string)$record['purpose'],
    ]);
    return [
        'user_id' => (int)$record['user_id'],
        'purpose' => (string)$record['purpose'],
    ];
}

function music_customer_request_password_reset(string $email): void
{
    if (!music_customer_password_recovery_enabled() || !music_customer_mail_ready()) return;

    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    if (rate_limit_exceeded('music_customer_password_ip', request_ip(), 8, 3600)) return;
    if (rate_limit_exceeded('music_customer_password_email', hash('sha256', $email), 3, 3600)) return;

    $statement = db()->prepare(
        'SELECT id,email,display_name FROM users
         WHERE email=:email AND role="customer" AND status="active"
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    if (!$user) return;

    $issued = music_customer_issue_token((int)$user['id'], 'password_reset', null, 3600);
    $body = "Hello " . (string)$user['display_name'] . ",\n\n"
        . "Use this one-time link to reset your listener-account password:\n"
        . $issued['url'] . "\n\n"
        . "The link expires in one hour. If you did not request it, ignore this message.\n";
    if (music_customer_send_account_email($email, 'Reset your North Mountain Media listener password', $body)) {
        log_activity('music_customer_password_reset_requested', 'user', (int)$user['id']);
    }
}

function music_customer_complete_password_reset(
    string $rawToken,
    string $password,
    string $confirm
): void {
    $record = music_customer_token_record(
        $rawToken,
        ['password_reset', 'admin_reset']
    );
    if (!$record) throw new RuntimeException('This password-reset link is invalid or has expired.');

    $errors = password_policy_errors($password, (string)$record['email']);
    if ($errors) throw new RuntimeException(implode(' ', $errors));
    if (!hash_equals($password, $confirm)) {
        throw new RuntimeException('The new passwords do not match.');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $locked = $pdo->prepare(
            'SELECT id,user_id FROM music_customer_account_tokens
             WHERE id=:id AND consumed_at IS NULL
               AND expires_at>UTC_TIMESTAMP()
             FOR UPDATE'
        );
        $locked->execute(['id' => $record['id']]);
        $token = $locked->fetch();
        if (!$token) throw new RuntimeException('This password-reset link is no longer available.');

        $pdo->prepare(
            'UPDATE users
             SET password_hash=:password_hash,must_change_password=0
             WHERE id=:user_id AND role="customer" AND status="active"'
        )->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'user_id' => $token['user_id'],
        ]);
        $pdo->prepare(
            'UPDATE music_customer_account_state
             SET auth_version=auth_version+1,last_password_reset_at=UTC_TIMESTAMP()
             WHERE user_id=:user_id'
        )->execute(['user_id' => $token['user_id']]);
        $pdo->prepare(
            'UPDATE music_customer_account_tokens
             SET consumed_at=COALESCE(consumed_at,UTC_TIMESTAMP())
             WHERE user_id=:user_id AND consumed_at IS NULL'
        )->execute(['user_id' => $token['user_id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    log_activity('music_customer_password_reset_completed', 'user', (int)$record['user_id']);
}

function music_customer_issue_admin_reset(int $customerId, int $adminId): array
{
    $statement = db()->prepare(
        'SELECT id,email,display_name,status FROM users
         WHERE id=:id AND role="customer" LIMIT 1'
    );
    $statement->execute(['id' => $customerId]);
    $customer = $statement->fetch();
    if (!$customer) throw new RuntimeException('Customer account not found.');
    if ($customer['status'] !== 'active') {
        throw new RuntimeException('Activate the customer account before issuing a reset link.');
    }

    $issued = music_customer_issue_token(
        $customerId,
        'admin_reset',
        null,
        1800,
        $adminId
    );
    $body = "Hello " . (string)$customer['display_name'] . ",\n\n"
        . "An administrator created a one-time password reset for your listener account:\n"
        . $issued['url'] . "\n\n"
        . "The link expires in 30 minutes. If you did not expect this, contact the site owner.\n";
    $sent = music_customer_send_account_email(
        (string)$customer['email'],
        'North Mountain Media listener password reset',
        $body
    );
    log_activity('music_customer_admin_reset_issued', 'user', $customerId, [
        'delivered' => $sent,
    ]);
    return ['url' => $issued['url'], 'sent' => $sent];
}

function music_customer_request_email_change(
    array $user,
    string $newEmail,
    string $currentPassword
): array {
    $newEmail = strtolower(trim($newEmail));
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 190) {
        throw new RuntimeException('Enter a valid new email address.');
    }
    if (hash_equals(strtolower((string)$user['email']), $newEmail)) {
        return ['changed' => false, 'pending' => false];
    }
    if (!music_customer_mail_ready()) {
        throw new RuntimeException('Email changes require configured account email delivery.');
    }

    $password = db()->prepare(
        'SELECT password_hash FROM users
         WHERE id=:user_id AND role="customer" LIMIT 1'
    );
    $password->execute(['user_id' => $user['id']]);
    if (!password_verify($currentPassword, (string)$password->fetchColumn())) {
        throw new RuntimeException('Enter your current password to change the account email.');
    }
    $duplicate = db()->prepare(
        'SELECT id FROM users WHERE email=:email AND id<>:user_id LIMIT 1'
    );
    $duplicate->execute([
        'email' => $newEmail,
        'user_id' => $user['id'],
    ]);
    if ($duplicate->fetchColumn()) {
        throw new RuntimeException('That email address is already used by another account.');
    }

    music_customer_state((int)$user['id'], true);
    db()->prepare(
        'UPDATE music_customer_account_state
         SET pending_email=:pending_email,
             last_email_change_requested_at=UTC_TIMESTAMP()
         WHERE user_id=:user_id'
    )->execute([
        'pending_email' => $newEmail,
        'user_id' => $user['id'],
    ]);
    $delivery = music_customer_send_verification_link(
        (int)$user['id'],
        'change_email',
        $newEmail
    );
    if (!$delivery['sent']) {
        throw new RuntimeException('The confirmation email could not be sent. The current account email was not changed.');
    }
    log_activity('music_customer_email_change_requested', 'user', (int)$user['id']);
    return ['changed' => false, 'pending' => true];
}

function music_customer_change_password_v21(
    array $user,
    string $currentPassword,
    string $newPassword,
    string $confirmPassword
): void {
    $errors = password_policy_errors($newPassword, (string)$user['email']);
    if ($errors) throw new RuntimeException(implode(' ', $errors));
    if (!hash_equals($newPassword, $confirmPassword)) {
        throw new RuntimeException('The new passwords do not match.');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'SELECT password_hash FROM users
             WHERE id=:user_id AND role="customer" FOR UPDATE'
        );
        $statement->execute(['user_id' => $user['id']]);
        if (!password_verify($currentPassword, (string)$statement->fetchColumn())) {
            throw new RuntimeException('Current password is not correct.');
        }
        $pdo->prepare(
            'UPDATE users SET password_hash=:password_hash,must_change_password=0
             WHERE id=:user_id AND role="customer"'
        )->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'user_id' => $user['id'],
        ]);
        $pdo->prepare(
            'UPDATE music_customer_account_state
             SET auth_version=auth_version+1,last_password_reset_at=UTC_TIMESTAMP()
             WHERE user_id=:user_id'
        )->execute(['user_id' => $user['id']]);
        $pdo->prepare(
            'UPDATE music_customer_account_tokens
             SET consumed_at=COALESCE(consumed_at,UTC_TIMESTAMP())
             WHERE user_id=:user_id AND consumed_at IS NULL'
        )->execute(['user_id' => $user['id']]);
        $version = (int)$pdo->query(
            'SELECT auth_version FROM music_customer_account_state
             WHERE user_id=' . (int)$user['id']
        )->fetchColumn();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    $_SESSION['music_customer_auth_version'] = $version;
    log_activity('music_customer_password_changed', 'user', (int)$user['id']);
}

function music_customer_delete_account_v21(
    array $user,
    string $currentPassword,
    string $confirmation
): void {
    if (!hash_equals('DELETE', trim($confirmation))) {
        throw new RuntimeException('Type DELETE to confirm account deletion.');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'SELECT password_hash FROM users
             WHERE id=:user_id AND role="customer" FOR UPDATE'
        );
        $statement->execute(['user_id' => $user['id']]);
        if (!password_verify($currentPassword, (string)$statement->fetchColumn())) {
            throw new RuntimeException('Current password is not correct.');
        }
        $pdo->prepare(
            'INSERT INTO activity_log
                (user_id,event_type,entity_type,entity_id,metadata_json)
             VALUES
                (:user_id,"music_customer_account_deleted","user",:entity_id,
                 JSON_OBJECT("self_service",true))'
        )->execute([
            'user_id' => $user['id'],
            'entity_id' => $user['id'],
        ]);
        $pdo->prepare(
            'DELETE FROM users WHERE id=:user_id AND role="customer"'
        )->execute(['user_id' => $user['id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function music_customer_validate_playlist_values(
    string $title,
    string $description
): array {
    $title = trim($title);
    $description = trim($description);
    if ($title === '' || mb_strlen($title) > 120) {
        throw new RuntimeException('Enter a playlist name of 120 characters or fewer.');
    }
    if (mb_strlen($description) > 1000) {
        throw new RuntimeException('Playlist descriptions must be 1,000 characters or fewer.');
    }
    return [$title, $description];
}

function music_customer_create_playlist_v21(
    int $userId,
    string $title,
    string $description = ''
): int {
    [$title, $description] = music_customer_validate_playlist_values($title, $description);
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $owner = $pdo->prepare(
            'SELECT id FROM users
             WHERE id=:user_id AND role="customer" AND status="active"
             FOR UPDATE'
        );
        $owner->execute(['user_id' => $userId]);
        if (!$owner->fetchColumn()) throw new RuntimeException('Customer account not found.');
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM music_customer_playlists
             WHERE customer_user_id=:user_id AND status="active"'
        );
        $count->execute(['user_id' => $userId]);
        if ((int)$count->fetchColumn() >= 100) {
            throw new RuntimeException('This account has reached the 100-playlist limit.');
        }
        $statement = $pdo->prepare(
            'INSERT INTO music_customer_playlists
                (customer_user_id,title,slug,description,status)
             VALUES
                (:user_id,:title,:slug,:description,"active")'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $title,
            'slug' => music_customer_unique_playlist_slug($userId, $title),
            'description' => $description !== '' ? $description : null,
        ]);
        $playlistId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    log_activity('music_customer_playlist_created', 'music_customer_playlist', $playlistId);
    return $playlistId;
}

function music_customer_lock_playlist(
    PDO $pdo,
    int $playlistId,
    int $userId
): array {
    $statement = $pdo->prepare(
        'SELECT * FROM music_customer_playlists
         WHERE id=:playlist_id AND customer_user_id=:user_id
           AND status="active"
         FOR UPDATE'
    );
    $statement->execute([
        'playlist_id' => $playlistId,
        'user_id' => $userId,
    ]);
    $playlist = $statement->fetch();
    if (!$playlist) throw new RuntimeException('Playlist not found.');
    return $playlist;
}

function music_customer_update_playlist_v21(
    int $playlistId,
    int $userId,
    string $title,
    string $description = ''
): void {
    [$title, $description] = music_customer_validate_playlist_values($title, $description);
    $pdo = db();
    try {
        $pdo->beginTransaction();
        music_customer_lock_playlist($pdo, $playlistId, $userId);
        $pdo->prepare(
            'UPDATE music_customer_playlists
             SET title=:title,slug=:slug,description=:description
             WHERE id=:playlist_id AND customer_user_id=:user_id'
        )->execute([
            'title' => $title,
            'slug' => music_customer_unique_playlist_slug($userId, $title, $playlistId),
            'description' => $description !== '' ? $description : null,
            'playlist_id' => $playlistId,
            'user_id' => $userId,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    log_activity('music_customer_playlist_updated', 'music_customer_playlist', $playlistId);
}

function music_customer_delete_playlist_v21(int $playlistId, int $userId): void
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        music_customer_lock_playlist($pdo, $playlistId, $userId);
        $pdo->prepare(
            'DELETE FROM music_customer_playlists
             WHERE id=:playlist_id AND customer_user_id=:user_id'
        )->execute([
            'playlist_id' => $playlistId,
            'user_id' => $userId,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    log_activity('music_customer_playlist_deleted', 'music_customer_playlist', $playlistId);
}

function music_customer_compact_positions(PDO $pdo, int $playlistId): void
{
    $statement = $pdo->prepare(
        'SELECT track_id FROM music_customer_playlist_tracks
         WHERE playlist_id=:playlist_id
         ORDER BY position ASC,added_at ASC,track_id ASC'
    );
    $statement->execute(['playlist_id' => $playlistId]);
    $trackIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    $update = $pdo->prepare(
        'UPDATE music_customer_playlist_tracks
         SET position=:position
         WHERE playlist_id=:playlist_id AND track_id=:track_id'
    );
    foreach ($trackIds as $index => $trackId) {
        $update->execute([
            'position' => $index + 1,
            'playlist_id' => $playlistId,
            'track_id' => $trackId,
        ]);
    }
}

function music_customer_add_track_v21(
    int $playlistId,
    int $trackId,
    int $userId
): bool {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        music_customer_lock_playlist($pdo, $playlistId, $userId);
        if (!music_customer_public_track_exists($trackId)) {
            throw new RuntimeException('That track is not available in the public Music Library.');
        }
        $existing = $pdo->prepare(
            'SELECT 1 FROM music_customer_playlist_tracks
             WHERE playlist_id=:playlist_id AND track_id=:track_id LIMIT 1'
        );
        $existing->execute([
            'playlist_id' => $playlistId,
            'track_id' => $trackId,
        ]);
        if ($existing->fetchColumn()) {
            $pdo->commit();
            return false;
        }
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM music_customer_playlist_tracks
             WHERE playlist_id=:playlist_id'
        );
        $count->execute(['playlist_id' => $playlistId]);
        if ((int)$count->fetchColumn() >= 500) {
            throw new RuntimeException('This playlist has reached the 500-track limit.');
        }
        $position = $pdo->prepare(
            'SELECT COALESCE(MAX(position),0)+1
             FROM music_customer_playlist_tracks
             WHERE playlist_id=:playlist_id'
        );
        $position->execute(['playlist_id' => $playlistId]);
        $pdo->prepare(
            'INSERT INTO music_customer_playlist_tracks
                (playlist_id,track_id,position,added_by)
             VALUES
                (:playlist_id,:track_id,:position,:user_id)'
        )->execute([
            'playlist_id' => $playlistId,
            'track_id' => $trackId,
            'position' => (int)$position->fetchColumn(),
            'user_id' => $userId,
        ]);
        $pdo->prepare(
            'UPDATE music_customer_playlists SET updated_at=UTC_TIMESTAMP()
             WHERE id=:playlist_id'
        )->execute(['playlist_id' => $playlistId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    log_activity('music_customer_track_added', 'music_customer_playlist', $playlistId, [
        'track_id' => $trackId,
    ]);
    return true;
}

function music_customer_remove_track_v21(
    int $playlistId,
    int $trackId,
    int $userId
): bool {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        music_customer_lock_playlist($pdo, $playlistId, $userId);
        $delete = $pdo->prepare(
            'DELETE FROM music_customer_playlist_tracks
             WHERE playlist_id=:playlist_id AND track_id=:track_id'
        );
        $delete->execute([
            'playlist_id' => $playlistId,
            'track_id' => $trackId,
        ]);
        $removed = $delete->rowCount() > 0;
        if ($removed) {
            music_customer_compact_positions($pdo, $playlistId);
            $pdo->prepare(
                'UPDATE music_customer_playlists SET updated_at=UTC_TIMESTAMP()
                 WHERE id=:playlist_id'
            )->execute(['playlist_id' => $playlistId]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    if ($removed) {
        log_activity('music_customer_track_removed', 'music_customer_playlist', $playlistId, [
            'track_id' => $trackId,
        ]);
    }
    return $removed;
}

function music_customer_move_track_v21(
    int $playlistId,
    int $trackId,
    int $userId,
    string $direction
): void {
    if (!in_array($direction, ['up', 'down'], true)) {
        throw new RuntimeException('Invalid playlist movement.');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        music_customer_lock_playlist($pdo, $playlistId, $userId);
        $statement = $pdo->prepare(
            'SELECT track_id,position
             FROM music_customer_playlist_tracks
             WHERE playlist_id=:playlist_id
             ORDER BY position ASC,added_at ASC,track_id ASC
             FOR UPDATE'
        );
        $statement->execute(['playlist_id' => $playlistId]);
        $rows = $statement->fetchAll();
        $index = null;
        foreach ($rows as $rowIndex => $row) {
            if ((int)$row['track_id'] === $trackId) {
                $index = $rowIndex;
                break;
            }
        }
        if ($index === null) throw new RuntimeException('Track not found in this playlist.');
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($rows[$target])) {
            $pdo->commit();
            return;
        }
        $update = $pdo->prepare(
            'UPDATE music_customer_playlist_tracks
             SET position=:position
             WHERE playlist_id=:playlist_id AND track_id=:track_id'
        );
        $currentPosition = (int)$rows[$index]['position'];
        $targetPosition = (int)$rows[$target]['position'];
        $update->execute([
            'position' => $targetPosition,
            'playlist_id' => $playlistId,
            'track_id' => $trackId,
        ]);
        $update->execute([
            'position' => $currentPosition,
            'playlist_id' => $playlistId,
            'track_id' => (int)$rows[$target]['track_id'],
        ]);
        music_customer_compact_positions($pdo, $playlistId);
        $pdo->prepare(
            'UPDATE music_customer_playlists SET updated_at=UTC_TIMESTAMP()
             WHERE id=:playlist_id'
        )->execute(['playlist_id' => $playlistId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    log_activity('music_customer_track_reordered', 'music_customer_playlist', $playlistId, [
        'track_id' => $trackId,
        'direction' => $direction,
    ]);
}

function music_customer_public_track_page(
    string $query,
    int $page = 1,
    int $perPage = 25
): array {
    if (!music_library_schema_available()) {
        return [
            'tracks' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'query' => '',
        ];
    }

    $query = mb_substr(trim($query), 0, 100);
    $page = max(1, $page);
    $perPage = max(10, min(50, $perPage));
    $where = '';
    $params = [];
    if ($query !== '') {
        $where = ' AND (
            track.title LIKE :search
            OR track.artist_name LIKE :search
            OR album.title LIKE :search
            OR track.genre LIKE :search
        )';
        $params['search'] = '%' . $query . '%';
    }

    $count = db()->prepare(
        'SELECT COUNT(*)
         FROM music_tracks track
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
          AND asset.media_kind="audio"
          AND asset.status="published"
          AND asset.is_public=1
         LEFT JOIN music_albums album ON album.id=track.album_id
         WHERE track.status="active"
           AND (track.published_at IS NULL OR track.published_at<=UTC_TIMESTAMP())'
        . $where
    );
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $statement = db()->prepare(
        'SELECT track.*,
                asset.original_name,asset.mime_type,asset.size_bytes,
                asset.cover_stored_name AS asset_cover_stored_name,
                album.title AS album_title,
                album.slug AS album_slug,
                album.artist_name AS album_artist_name,
                album.cover_stored_name AS album_cover_stored_name
         FROM music_tracks track
         JOIN knowledge_assets asset
           ON asset.id=track.knowledge_asset_id
          AND asset.media_kind="audio"
          AND asset.status="published"
          AND asset.is_public=1
         LEFT JOIN music_albums album ON album.id=track.album_id
         WHERE track.status="active"
           AND (track.published_at IS NULL OR track.published_at<=UTC_TIMESTAMP())'
        . $where
        . ' ORDER BY track.featured DESC,track.title ASC
            LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    return [
        'tracks' => array_map('music_track_payload', $statement->fetchAll()),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'query' => $query,
    ];
}

function music_customer_security_panel_html(): string
{
    $ready = music_customer_lifecycle_ready();
    $verification = $ready && music_customer_email_verification_required();
    $recovery = $ready && music_customer_password_recovery_enabled();
    $mailReady = music_customer_mail_ready();

    ob_start();
    ?>
    <section class="panel music-customer-admin-panel" data-music-customer-security>
        <header class="panel-header">
            <div>
                <span class="music-customer-eyebrow">Account security</span>
                <h2>Verification and recovery</h2>
            </div>
            <span class="status status-<?=$ready ? 'active' : 'inactive'?>">
                <?=$ready ? 'Ready' : 'Migration required'?>
            </span>
        </header>
        <div class="panel-body">
            <p class="music-customer-admin-copy">
                Protect listener accounts with one-time hashed links, verified email changes, session-version revocation, and non-enumerating password recovery.
            </p>
            <div class="music-customer-admin-stats">
                <span><strong><?=$mailReady ? 'Ready' : 'Off'?></strong> email delivery</span>
                <span><strong><?=$verification ? 'Required' : 'Optional'?></strong> verification</span>
                <span><strong><?=$recovery ? 'Enabled' : 'Disabled'?></strong> recovery</span>
            </div>
            <?php if (!$ready): ?>
                <div class="alert alert-warning">
                    Import <code>database/music_customer_accounts_v66q21.sql</code> to enable lifecycle security.
                </div>
            <?php elseif (!$mailReady): ?>
                <div class="alert alert-warning">
                    Notification email delivery is not configured. Verification cannot be required and password-reset links cannot be emailed.
                </div>
            <?php endif; ?>
            <form method="post" action="<?=e(app_url('portal/admin.php?view=music&section=customers'))?>">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_music_customer_security">
                <div class="music-customer-security-options">
                    <label class="music-customer-toggle">
                        <input type="checkbox" name="verification_required" value="1" <?=$verification ? 'checked' : ''?> <?=$ready && $mailReady ? '' : 'disabled'?>>
                        <span>
                            <strong>Require verified customer email</strong>
                            <small>New customers must use a one-time email link before signing in. Existing customers remain verified.</small>
                        </span>
                    </label>
                    <label class="music-customer-toggle">
                        <input type="checkbox" name="recovery_enabled" value="1" <?=$recovery ? 'checked' : ''?> <?=$ready ? '' : 'disabled'?>>
                        <span>
                            <strong>Enable customer password recovery</strong>
                            <small>Uses generic responses and one-time hashed links. No temporary passwords are exposed.</small>
                        </span>
                    </label>
                </div>
                <div class="form-footer music-customer-admin-actions">
                    <button class="button button-primary" type="submit" <?=$ready ? '' : 'disabled'?>>Save account security</button>
                </div>
            </form>
        </div>
    </section>
    <?php
    return trim((string)ob_get_clean());
}

function music_customer_inject_security_panel(string $html): string
{
    $asset = '<link rel="stylesheet" href="'
        . e(app_url('assets/css/music-customer-accounts-v66q21.css?v=20260802-v66Q21'))
        . '">';
    if (!str_contains($html, 'music-customer-accounts-v66q21.css')) {
        $html = preg_replace('#</head>#i', $asset . '</head>', $html, 1) ?? $html;
    }
    if (!str_contains($html, 'data-music-customer-security')) {
        $marker = '<div class="portal-content">';
        $html = str_replace(
            $marker,
            $marker . music_customer_security_panel_html(),
            $html,
            $count
        );
    }
    return $html;
}

function music_customer_lifecycle_runtime(): void
{
    if (PHP_SAPI === 'cli') return;
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $view = (string)($_GET['view'] ?? '');

    if (
        $script === 'admin.php'
        && is_post()
        && input('action') === 'save_music_customer_security'
    ) {
        $user = require_role('admin');
        verify_csrf();
        if (!same_origin_request()) {
            http_response_code(403);
            exit('Invalid request origin.');
        }
        enforce_authenticated_action_limit($user);
        try {
            $verification = isset($_POST['verification_required']);
            $recovery = isset($_POST['recovery_enabled']);
            music_customer_save_security_settings($verification, $recovery);
            log_activity('music_customer_security_updated', 'settings', null, [
                'verification_required' => $verification,
                'recovery_enabled' => $recovery,
            ]);
            flash('success', 'Customer verification and recovery settings were updated.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('portal/admin.php?view=music&section=customers');
    }

    if ($script === 'admin.php' && $view === 'music') {
        ob_start('music_customer_inject_security_panel');
    }
}

music_customer_lifecycle_runtime();

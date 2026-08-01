<?php
declare(strict_types=1);

function profile_columns_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $statement = db()->query(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name="users" AND column_name IN ("profile_image_stored_name","profile_image_mime","profile_image_updated_at")'
        );
        $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        $available = false;
    }
    return $available;
}

function profile_image_storage_directory(): string
{
    $directory = NMM_ROOT . '/storage/profile-images';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The profile-image storage directory could not be created.');
    }
    return $directory;
}

function user_profile_image_url(?array $user): string
{
    if (!$user || empty($user['id']) || empty($user['profile_image_stored_name'])) {
        return app_url(
            ($user['role'] ?? 'admin') === 'admin'
                ? 'assets/images/david-evans-profile.jpg'
                : 'assets/images/profile-placeholder.svg'
        );
    }
    $version = rawurlencode((string)($user['profile_image_updated_at'] ?? $user['updated_at'] ?? '1'));
    return app_url('portal/profile-image.php?id=' . (int)$user['id'] . '&v=' . $version);
}

function primary_admin_profile(): ?array
{
    static $profile = false;
    if ($profile !== false) return $profile ?: null;
    try {
        $profileFields = profile_columns_available()
            ? 'profile_image_stored_name,profile_image_mime,profile_image_updated_at'
            : 'NULL AS profile_image_stored_name,NULL AS profile_image_mime,NULL AS profile_image_updated_at';
        $statement = db()->query(
            'SELECT id,role,email,display_name,company,phone,status,' . $profileFields . ',updated_at FROM users WHERE role="admin" AND status="active" ORDER BY id ASC LIMIT 1'
        );
        $profile = $statement->fetch() ?: null;
    } catch (Throwable) {
        $profile = null;
    }
    return $profile ?: null;
}

function public_contact_email(): string { return trim((string)(primary_admin_profile()['email'] ?? '')); }
function public_contact_phone(): string { return trim((string)(primary_admin_profile()['phone'] ?? '')); }
function public_profile_name(): string { return trim((string)(primary_admin_profile()['display_name'] ?? 'David Evans')); }

function save_account_profile(int $userId, array $values, ?array $upload = null, bool $removeImage = false): void
{
    if (!profile_columns_available()) {
        throw new RuntimeException('Import database/account_profile_v38.sql before saving account profile settings.');
    }
    $displayName = trim((string)($values['display_name'] ?? ''));
    $email = strtolower(trim((string)($values['email'] ?? '')));
    $company = trim((string)($values['company'] ?? ''));
    $phone = trim((string)($values['phone'] ?? ''));
    if ($displayName === '' || strlen($displayName) > 160) throw new RuntimeException('Enter a valid display name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) throw new RuntimeException('Enter a valid account email.');
    if (strlen($company) > 190 || strlen($phone) > 60) throw new RuntimeException('The company or phone value is too long.');

    $duplicate = db()->prepare('SELECT id FROM users WHERE email=:email AND id<>:user_id LIMIT 1');
    $duplicate->execute(['email' => $email, 'user_id' => $userId]);
    if ($duplicate->fetchColumn()) throw new RuntimeException('That email address is already used by another account.');

    $currentStatement = db()->prepare('SELECT profile_image_stored_name,profile_image_mime FROM users WHERE id=:user_id LIMIT 1');
    $currentStatement->execute(['user_id' => $userId]);
    $currentProfile = $currentStatement->fetch() ?: [];
    $currentStoredName = (string)($currentProfile['profile_image_stored_name'] ?? '');
    $newStoredName = $currentStoredName;
    $newMime = (string)($currentProfile['profile_image_mime'] ?? '') ?: null;
    $newImageUploaded = false;

    if (is_array($upload) && isset($upload['error']) && (int)$upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ((int)$upload['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('The profile photo upload failed.');
        $temporaryPath = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) throw new RuntimeException('The uploaded profile photo is invalid.');
        if ($size <= 0 || $size > 5 * 1024 * 1024) throw new RuntimeException('Profile photos must be 5 MB or smaller.');
        if (!is_array(@getimagesize($temporaryPath))) throw new RuntimeException('The uploaded file is not a valid image.');
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($extensions[$mime])) throw new RuntimeException('Upload a JPG, PNG, WebP, or GIF profile photo.');
        $newStoredName = sprintf('profile-%d-%s.%s', $userId, bin2hex(random_bytes(16)), $extensions[$mime]);
        $destination = profile_image_storage_directory() . '/' . $newStoredName;
        if (!move_uploaded_file($temporaryPath, $destination)) throw new RuntimeException('The profile photo could not be stored.');
        chmod($destination, 0640);
        $newMime = $mime;
        $newImageUploaded = true;
    }
    if ($removeImage && !$newImageUploaded) {
        $newStoredName = '';
        $newMime = null;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'UPDATE users SET display_name=:display_name,email=:email,company=:company,phone=:phone,profile_image_stored_name=:profile_image_stored_name,profile_image_mime=:profile_image_mime,profile_image_updated_at=CASE WHEN :profile_changed=1 THEN UTC_TIMESTAMP() ELSE profile_image_updated_at END WHERE id=:user_id'
        );
        $statement->execute([
            'display_name' => $displayName,
            'email' => $email,
            'company' => $company !== '' ? $company : null,
            'phone' => $phone !== '' ? $phone : null,
            'profile_image_stored_name' => $newStoredName !== '' ? $newStoredName : null,
            'profile_image_mime' => $newStoredName !== '' ? $newMime : null,
            'profile_changed' => ($newImageUploaded || $removeImage) ? 1 : 0,
            'user_id' => $userId,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newImageUploaded && $newStoredName !== '') @unlink(profile_image_storage_directory() . '/' . $newStoredName);
        throw $exception;
    }
    if (($newImageUploaded || $removeImage) && $currentStoredName !== '' && $currentStoredName !== $newStoredName) {
        @unlink(profile_image_storage_directory() . '/' . basename($currentStoredName));
    }
    log_activity('account_profile_updated', 'user', $userId, ['profile_image_changed' => $newImageUploaded || $removeImage]);
}

function current_user(): ?array
{
    static $cachedId = null;
    static $cached = null;
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0) return null;
    if ($cachedId === $id) return $cached;
    $profileFields = profile_columns_available()
        ? 'profile_image_stored_name,profile_image_mime,profile_image_updated_at'
        : 'NULL AS profile_image_stored_name,NULL AS profile_image_mime,NULL AS profile_image_updated_at';
    $statement = db()->prepare(
        'SELECT id,role,email,display_name,company,phone,status,must_change_password,' . $profileFields . ',last_login_at,created_at FROM users WHERE id=:id LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }
    $cachedId = $id;
    $cached = $user;
    return $user;
}

function log_activity(string $event, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
{
    try {
        $statement = db()->prepare(
            'INSERT INTO activity_log (user_id,event_type,entity_type,entity_id,metadata_json) VALUES (:user_id,:event_type,:entity_type,:entity_id,:metadata_json)'
        );
        $statement->execute([
            'user_id' => current_user()['id'] ?? null,
            'event_type' => $event,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    } catch (Throwable) {
    }
}

function login_blocked(string $email, string $ip): bool
{
    $security = nmm_config('security');
    $window = max(60, (int)($security['login_window_seconds'] ?? 900));
    $emailLimit = max(1, (int)($security['login_email_limit'] ?? 5));
    $ipLimit = max(1, (int)($security['login_ip_limit'] ?? 20));
    $threshold = gmdate('Y-m-d H:i:s', time() - $window);
    $emailStatement = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE success=0 AND email=:email AND created_at>=:threshold');
    $emailStatement->execute(['email' => strtolower($email), 'threshold' => $threshold]);
    if ((int)$emailStatement->fetchColumn() >= $emailLimit) return true;
    $ipStatement = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE success=0 AND ip_address=:ip_address AND created_at>=:threshold');
    $ipStatement->execute(['ip_address' => $ip, 'threshold' => $threshold]);
    return (int)$ipStatement->fetchColumn() >= $ipLimit;
}

function attempt_login(string $email, string $password, string $role): bool
{
    $email = strtolower(trim($email));
    $ip = request_ip();
    if (login_blocked($email, $ip)) return false;
    $statement = db()->prepare('SELECT * FROM users WHERE email=:email AND role=:role AND status="active" LIMIT 1');
    $statement->execute(['email' => $email, 'role' => $role]);
    $user = $statement->fetch();
    $valid = $user && password_verify($password, (string)$user['password_hash']);
    db()->prepare('INSERT INTO login_attempts (email,ip_address,success) VALUES (:email,:ip_address,:success)')
        ->execute(['email' => $email, 'ip_address' => $ip, 'success' => $valid ? 1 : 0]);
    if (!$valid) return false;
    session_regenerate_id(true);
    rotate_csrf_token();
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['session_created_at'] = time();
    $_SESSION['session_last_activity'] = time();
    $_SESSION['session_regenerated_at'] = time();
    $_SESSION['session_user_agent'] = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    db()->prepare('UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $user['id']]);
    log_activity('login', 'user', (int)$user['id'], ['role' => $role]);
    return true;
}

function logout_user(): void
{
    if (current_user()) log_activity('logout', 'user', (int)current_user()['id']);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'],
            'domain' => $parameters['domain'],
            'secure' => $parameters['secure'],
            'httponly' => $parameters['httponly'],
            'samesite' => $parameters['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function require_role(string $role): array
{
    $user = current_user();
    if (!$user || $user['role'] !== $role) {
        redirect('portal/login.php?role=' . urlencode($role));
    }
    if ((int)$user['must_change_password'] === 1 && (string)($_GET['view'] ?? '') !== 'account') {
        redirect('portal/' . ($role === 'admin' ? 'admin.php' : 'client.php') . '?view=account&required=1');
    }
    return $user;
}

function enforce_authenticated_action_limit(array $user): void
{
    $security = nmm_config('security');
    $isAdmin = $user['role'] === 'admin';
    $limit = $isAdmin
        ? max(10, (int)($security['admin_action_limit'] ?? 120))
        : max(5, (int)($security['client_action_limit'] ?? 45));
    $window = max(30, (int)($security['action_window_seconds'] ?? 60));
    if (rate_limit_exceeded($isAdmin ? 'admin_action' : 'client_action', (string)$user['id'], $limit, $window)) {
        http_response_code(429);
        exit('Too many actions were submitted. Wait briefly and try again.');
    }
}

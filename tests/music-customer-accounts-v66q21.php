<?php
declare(strict_types=1);

function v66q21_fail(string $message): never
{
    fwrite(STDERR, "v66Q.21 customer account hardening failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        v66q21_fail('Unable to read ' . $path);
    }
    return $content;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        v66q21_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        v66q21_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$bootstrap = $read('portal/bootstrap.php');
$lifecycle = $read('portal/music-customer-lifecycle-v66q21.php');
$hardening = $read('portal/music-customer-hardening-v66q21.php');
$register = $read('portal/customer-register.php');
$login = $read('portal/login.php');
$verify = $read('portal/customer-verify.php');
$password = $read('portal/customer-password.php');
$customer = $read('portal/customer.php');
$admin = $read('portal/music-customers.php');
$migration = $read('database/music_customer_accounts_v66q21.sql');
$css = $read('assets/css/music-customer-accounts-v66q21.css');
$installer = $read('install.php');

foreach ([
    "require_once __DIR__ . '/music-customer-lifecycle-v66q21.php'",
    "require_once __DIR__ . '/music-customer-hardening-v66q21.php'",
] as $contract) {
    $require($bootstrap, $contract, 'Bootstrap');
}

foreach ([
    'CREATE TABLE IF NOT EXISTS music_customer_account_state',
    'CREATE TABLE IF NOT EXISTS music_customer_account_tokens',
    "ENUM('verify_email','password_reset','admin_reset','change_email')",
    'token_hash CHAR(64)',
    'auth_version INT UNSIGNED NOT NULL DEFAULT 1',
    'ON DELETE CASCADE',
    "'music_customer_email_verification_required','0'",
    "'music_customer_password_recovery_enabled','1'",
] as $contract) {
    $require($migration, $contract, 'Lifecycle migration');
}
$forbid($migration, 'raw_token', 'Lifecycle migration');
$forbid($migration, 'temporary_password', 'Lifecycle migration');

foreach ([
    'function music_customer_lifecycle_schema_available()',
    'function require_music_customer_v21()',
    'music_customer_auth_version',
    'function music_customer_issue_token(',
    "hash('sha256', \$raw)",
    'consumed_at IS NULL',
    'expires_at>UTC_TIMESTAMP()',
    'function music_customer_attempt_login_v21(',
    'verification_required',
    'function music_customer_verify_token(',
    'function music_customer_request_password_reset(',
    'function music_customer_complete_password_reset(',
    'function music_customer_issue_admin_reset(',
    'function music_customer_request_email_change(',
    'function music_customer_change_password_v21(',
    'function music_customer_delete_account_v21(',
    'function music_customer_create_playlist_v21(',
    'function music_customer_add_track_v21(',
    'function music_customer_remove_track_v21(',
    'function music_customer_move_track_v21(',
    'FOR UPDATE',
    'music_customer_compact_positions',
    'function music_customer_public_track_page(',
    'data-music-customer-security',
    'save_music_customer_security',
] as $contract) {
    $require($lifecycle, $contract, 'Lifecycle implementation');
}
$forbid($lifecycle, 'INSERT IGNORE INTO music_customer_playlist_tracks', 'Playlist idempotency');
$forbid($lifecycle, 'Temporary password:', 'Secure reset delivery');

foreach ([
    'function music_customer_register_final(',
    'DELETE FROM users WHERE id=:user_id AND role="customer"',
    'function music_customer_request_email_change_final(',
    'SET pending_email=NULL',
    'function music_customer_public_track_page_final(',
    ':search_title',
    ':search_artist',
    ':search_album',
    ':search_genre',
    ':result_limit',
    ':result_offset',
] as $contract) {
    $require($hardening, $contract, 'Final edge-case hardening');
}
$forbid($hardening, ' OR album.title LIKE :search\n', 'Native PDO search binding');

foreach ([
    'music_customer_register_final(',
    'Existing accounts are not disclosed',
    'role="alert"',
    'customer-password.php',
    'customer-verify.php',
] as $contract) {
    $require($register, $contract, 'Registration');
}
$forbid($register, 'An account already uses that email address', 'Registration enumeration');

foreach ([
    'music_customer_attempt_login_v21(',
    'verification_required',
    'customer-password.php',
    'customer-verify.php',
    "['admin', 'client', 'customer', 'pod']",
    'attempt_pod_account_login',
] as $contract) {
    $require($login, $contract, 'Login and retained POD resume');
}

foreach ([
    'music_customer_verify_token(',
    'resend_verification',
    'does not disclose whether an account exists',
] as $contract) {
    $require($verify, $contract, 'Verification flow');
}
foreach ([
    'music_customer_request_password_reset(',
    'music_customer_complete_password_reset(',
    'never confirms whether an email address is registered',
] as $contract) {
    $require($password, $contract, 'Password recovery');
}

foreach ([
    'require_music_customer_v21()',
    'music_customer_create_playlist_v21(',
    'music_customer_update_playlist_v21(',
    'music_customer_delete_playlist_v21(',
    'music_customer_add_track_v21(',
    'music_customer_remove_track_v21(',
    'music_customer_move_track_v21(',
    'music_customer_request_email_change_final(',
    'music_customer_public_track_page_final(',
    'music_customer_change_password_v21(',
    'music_customer_delete_account_v21(',
    'aria-current="page"',
    '<caption>',
    'music-customer-skip-link',
    'Delete my customer account',
] as $contract) {
    $require($customer, $contract, 'Customer workspace');
}
foreach (['client_user_id', "require_role('client')", 'portal/admin.php'] as $forbidden) {
    $forbid($customer, $forbidden, 'Customer role isolation');
}

foreach ([
    'issue_customer_reset',
    'music_customer_issue_admin_reset(',
    'No temporary password was created or displayed',
    'revoke_customer_sessions',
    'auth_version=auth_version+1',
    'send_customer_verification',
    'Search customer name or email',
] as $contract) {
    $require($admin, $contract, 'Customer administration');
}
$forbid($admin, 'random_password()', 'Administrator reset');
$forbid($admin, 'Temporary password:', 'Administrator reset');

foreach ([
    '.music-customer-skip-link',
    ':focus-visible',
    '.music-customer-pagination',
    '.music-customer-track-actions',
    '.music-customer-danger-zone',
    '@media(prefers-reduced-motion:reduce)',
] as $contract) {
    $require($css, $contract, 'Accessible interface');
}

foreach ([
    'database/music_library_v44.sql',
    'database/music_customer_accounts_v66q20.sql',
    'database/music_customer_accounts_v66q21.sql',
] as $contract) {
    $require($installer, $contract, 'Fresh installer');
}

$runtime = $lifecycle . $hardening . $register . $login . $verify . $password . $customer . $admin;
foreach (['CREATE TABLE', 'ALTER TABLE', 'DROP TABLE'] as $forbidden) {
    $forbid($runtime, $forbidden, 'Runtime schema mutation');
}

echo "v66Q.21 customer lifecycle, recovery, transactional playlists, accessibility, and fresh-install contract passed.\n";

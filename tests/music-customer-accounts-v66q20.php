<?php
declare(strict_types=1);

function v66q20_fail(string $message): never
{
    fwrite(STDERR, "v66Q.20 music customer account contract failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        v66q20_fail('Unable to read ' . $path);
    }
    return $content;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) {
        v66q20_fail($label . ' missing: ' . $needle);
    }
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) {
        v66q20_fail($label . ' retains forbidden behavior: ' . $needle);
    }
};

$bootstrap = $read('portal/bootstrap.php');
$foundation = $read('portal/music-customer-accounts.php');
$login = $read('portal/login.php');
$register = $read('portal/customer-register.php');
$customer = $read('portal/customer.php');
$adminCustomers = $read('portal/music-customers.php');
$publicAccount = $read('portal/public-account-menu.php');
$migration = $read('database/music_customer_accounts_v66q20.sql');
$css = $read('assets/css/music-customer-accounts-v66q20.css');

$require($bootstrap, "require_once __DIR__ . '/music-customer-accounts.php'", 'Shared bootstrap');

foreach ([
    "ENUM('admin','client','customer')",
    'CREATE TABLE IF NOT EXISTS music_customer_playlists',
    'CREATE TABLE IF NOT EXISTS music_customer_playlist_tracks',
    'FOREIGN KEY (customer_user_id)',
    'ON DELETE CASCADE',
    "'music_customer_accounts_enabled','0'",
] as $contract) {
    $require($migration, $contract, 'Customer schema');
}

foreach ([
    'function music_customer_schema_available()',
    'function music_customer_accounts_enabled()',
    'function music_customer_accounts_active()',
    'function require_music_customer()',
    'function music_customer_create_playlist(',
    'function music_customer_update_playlist(',
    'function music_customer_delete_playlist(',
    'function music_customer_add_track(',
    'function music_customer_remove_track(',
    'customer_user_id=:user_id',
    'WHERE id=:playlist_id AND customer_user_id=:user_id',
    'save_music_customer_accounts',
    'data-music-customer-admin',
    'data-music-customer-strip',
    "ob_start('music_customer_inject_admin_panel')",
    "ob_start('music_customer_inject_public_library')",
] as $contract) {
    $require($foundation, $contract, 'Customer account foundation');
}

foreach ([
    "['admin', 'client', 'customer', 'pod']",
    "'customer' => 'Customer'",
    'music_customer_accounts_active()',
    'attempt_login(input(\'email\')',
    'portal/customer-register.php',
] as $contract) {
    $require($login, $contract, 'Customer login');
}

foreach ([
    'role,email,password_hash,display_name,status,must_change_password',
    '("customer",:email,:password_hash,:display_name,"active",0)',
    'password_policy_errors($password, $email)',
    "rate_limit_exceeded('music_customer_register'",
    'same_origin_request()',
    'music_customer_start_session($userId)',
] as $contract) {
    $require($register, $contract, 'Customer registration');
}

foreach ([
    '$user = require_music_customer()',
    "['playlists', 'library', 'account']",
    'if ($action === \'create_playlist\')',
    'if ($action === \'add_track\')',
    'if ($action === \'remove_track\')',
    'if ($action === \'change_password\')',
    'music_public_tracks()',
    'music_customer_playlist(',
] as $contract) {
    $require($customer, $contract, 'Customer workspace');
}
foreach (['projects', 'client_user_id', 'require_role(\'client\')'] as $forbidden) {
    $forbid($customer, $forbidden, 'Customer/client separation');
}

foreach ([
    "WHERE id=:id AND role=\"customer\"",
    "UPDATE users SET status=:status WHERE id=:id AND role=\"customer\"",
    'reset_customer_password',
    'playlist_count',
    'saved_track_count',
] as $contract) {
    $require($adminCustomers, $contract, 'Customer administration');
}

foreach ([
    "'customer' => 'customer.php'",
    "'customer' => 'Customer'",
    'music_customer_accounts_active()',
    'Create listener account',
    'portal/login.php?role=customer',
] as $contract) {
    $require($publicAccount, $contract, 'Public customer account menu');
}

foreach ([
    '.music-customer-admin-panel',
    '.music-customer-public-strip',
    '.music-customer-body',
    '.music-customer-grid',
    '.music-customer-register-card',
] as $contract) {
    $require($css, $contract, 'Customer interface styles');
}

echo "v66Q.20 opt-in customer accounts, private playlists, and role isolation contract passed.\n";

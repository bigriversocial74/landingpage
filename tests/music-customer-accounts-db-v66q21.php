<?php
declare(strict_types=1);

function v66q21_db_fail(string $message): never
{
    fwrite(STDERR, "v66Q.21 customer database failure: {$message}\n");
    exit(1);
}

function v66q21_db_assert(bool $condition, string $message): void
{
    if (!$condition) v66q21_db_fail($message);
}

$host = (string)(getenv('DB_HOST') ?: '127.0.0.1');
$port = (int)(getenv('DB_PORT') ?: 3306);
$name = (string)(getenv('DB_NAME') ?: 'nmm_test');
$user = (string)(getenv('DB_USER') ?: 'root');
$password = (string)(getenv('DB_PASSWORD') ?: '');
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ([
    'music_customer_account_tokens',
    'music_customer_account_state',
    'music_customer_playlist_tracks',
    'music_customer_playlists',
    'music_playlist_tracks',
    'music_playlists',
    'music_tracks',
    'music_albums',
    'knowledge_assets',
    'settings',
    'users',
] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec(
    "CREATE TABLE users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        role ENUM('admin','client') NOT NULL,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        display_name VARCHAR(160) NOT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        must_change_password TINYINT(1) NOT NULL DEFAULT 0,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$pdo->exec(
    "CREATE TABLE settings (
        setting_key VARCHAR(190) NOT NULL,
        setting_value TEXT NULL,
        PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$pdo->exec(
    "CREATE TABLE knowledge_assets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$root = dirname(__DIR__);
$applyFile = static function (string $relativePath) use ($pdo, $root): void {
    $sql = @file_get_contents($root . '/' . $relativePath);
    if (!is_string($sql) || trim($sql) === '') {
        v66q21_db_fail('Unable to read ' . $relativePath);
    }
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $pdo->exec($statement);
    }
};

$applyFile('database/music_library_v44.sql');
$applyFile('database/music_customer_accounts_v66q20.sql');
$applyFile('database/music_customer_accounts_v66q21.sql');
$applyFile('database/music_customer_accounts_v66q20.sql');
$applyFile('database/music_customer_accounts_v66q21.sql');

$columnType = strtolower((string)$pdo->query(
    "SELECT column_type FROM information_schema.columns
     WHERE table_schema=DATABASE() AND table_name='users' AND column_name='role'"
)->fetchColumn());
v66q21_db_assert(str_contains($columnType, "'customer'"), 'users.role does not include customer');

foreach ([
    'music_albums',
    'music_tracks',
    'music_playlists',
    'music_playlist_tracks',
    'music_customer_playlists',
    'music_customer_playlist_tracks',
    'music_customer_account_state',
    'music_customer_account_tokens',
] as $table) {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_name=:table_name'
    );
    $statement->execute(['table_name' => $table]);
    v66q21_db_assert((int)$statement->fetchColumn() === 1, $table . ' was not created');
}

$settings = $pdo->query(
    "SELECT setting_key,setting_value FROM settings
     WHERE setting_key IN (
       'music_customer_accounts_enabled',
       'music_customer_email_verification_required',
       'music_customer_password_recovery_enabled'
     )"
)->fetchAll(PDO::FETCH_KEY_PAIR);
v66q21_db_assert(($settings['music_customer_accounts_enabled'] ?? null) === '0', 'customer accounts default must be disabled');
v66q21_db_assert(($settings['music_customer_email_verification_required'] ?? null) === '0', 'verification default must be optional');
v66q21_db_assert(($settings['music_customer_password_recovery_enabled'] ?? null) === '1', 'password recovery default must be enabled');

$pdo->prepare(
    'INSERT INTO users (role,email,password_hash,display_name,status)
     VALUES ("customer",:email,:password_hash,:display_name,"active")'
)->execute([
    'email' => 'listener@example.test',
    'password_hash' => password_hash('TestPassword!234', PASSWORD_DEFAULT),
    'display_name' => 'Listener Test',
]);
$customerId = (int)$pdo->lastInsertId();

$pdo->prepare(
    'INSERT INTO music_customer_account_state
        (user_id,email_verified_at,auth_version)
     VALUES (:user_id,UTC_TIMESTAMP(),1)'
)->execute(['user_id' => $customerId]);
$pdo->prepare(
    'INSERT INTO music_customer_account_tokens
        (user_id,purpose,token_hash,expires_at,request_ip)
     VALUES (:user_id,"password_reset",:token_hash,
             DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),"127.0.0.1")'
)->execute([
    'user_id' => $customerId,
    'token_hash' => hash('sha256', 'one-time-secret'),
]);

$pdo->exec('INSERT INTO knowledge_assets (id) VALUES (1)');
$pdo->prepare(
    'INSERT INTO music_tracks
        (knowledge_asset_id,title,slug,artist_name,status,published_at)
     VALUES
        (1,"Certified Track","certified-track","Test Artist","active",UTC_TIMESTAMP())'
)->execute();
$trackId = (int)$pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO music_customer_playlists
        (customer_user_id,title,slug,description,status)
     VALUES
        (:user_id,"Certification Playlist","certification-playlist",
         "Live database test","active")'
)->execute(['user_id' => $customerId]);
$playlistId = (int)$pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO music_customer_playlist_tracks
        (playlist_id,track_id,position,added_by)
     VALUES (:playlist_id,:track_id,1,:user_id)'
)->execute([
    'playlist_id' => $playlistId,
    'track_id' => $trackId,
    'user_id' => $customerId,
]);

v66q21_db_assert(
    (int)$pdo->query('SELECT COUNT(*) FROM music_customer_playlist_tracks')->fetchColumn() === 1,
    'playlist track could not be inserted'
);
v66q21_db_assert(
    (int)$pdo->query('SELECT auth_version FROM music_customer_account_state')->fetchColumn() === 1,
    'customer auth version was not stored'
);
v66q21_db_assert(
    (int)$pdo->query('SELECT COUNT(*) FROM music_customer_account_tokens WHERE token_hash=SHA2("one-time-secret",256)')->fetchColumn() === 1,
    'hashed account token was not stored'
);

$duplicateRejected = false;
try {
    $pdo->prepare(
        'INSERT INTO music_customer_account_tokens
            (user_id,purpose,token_hash,expires_at)
         VALUES (:user_id,"password_reset",:token_hash,
                 DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))'
    )->execute([
        'user_id' => $customerId,
        'token_hash' => hash('sha256', 'one-time-secret'),
    ]);
} catch (PDOException $exception) {
    $duplicateRejected = (string)$exception->getCode() === '23000';
}
v66q21_db_assert($duplicateRejected, 'duplicate token hash was not rejected');

$pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $customerId]);
foreach ([
    'music_customer_account_state',
    'music_customer_account_tokens',
    'music_customer_playlists',
    'music_customer_playlist_tracks',
] as $table) {
    v66q21_db_assert(
        (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() === 0,
        $table . ' did not cascade when the customer was deleted'
    );
}
v66q21_db_assert(
    (int)$pdo->query('SELECT COUNT(*) FROM music_tracks')->fetchColumn() === 1,
    'customer deletion must not delete published music'
);

echo "v66Q.21 live customer role, repeat-safe migrations, token constraints, private playlist FKs, and account cascade passed.\n";

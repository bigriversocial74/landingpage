<?php
declare(strict_types=1);

/**
 * North Mountain Media one-time installer.
 *
 * Requirements:
 * - config.php must exist and return the same structure as config-example.php.
 * - app.setup_token must be replaced with a long random token.
 * - security.force_https should remain false until HTTPS is confirmed.
 */

$configFile = __DIR__ . '/config.php';

function nmm_install_document(string $title, string $body): never
{
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<style>body{margin:0;background:#f1f4f6;color:#17202d;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}'
        . 'main{width:min(100% - 32px,760px);margin:8vh auto;padding:28px;border:1px solid #dfe5eb;border-radius:20px;background:#fff}'
        . 'code{padding:2px 6px;border-radius:5px;background:#f2f4f7}</style></head><body>'
        . '<main><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . $body . '</main></body></html>';
    exit;
}

if (!is_file($configFile)) {
    nmm_install_document(
        'Configuration required',
        '<p>Copy <code>config-example.php</code> to <code>config.php</code>, enter the database credentials, and replace the setup token.</p>'
    );
}

$config = require $configFile;
if (!is_array($config)) {
    http_response_code(500);
    exit('config.php must return a PHP array.');
}

$app = is_array($config['app'] ?? null) ? $config['app'] : [];
$expectedToken = trim((string)($app['setup_token'] ?? ''));
if (
    $expectedToken === ''
    || $expectedToken === 'replace-with-a-long-random-one-time-token'
) {
    nmm_install_document(
        'Replace the setup token',
        '<p>Open <code>config.php</code> and replace the example <code>setup_token</code> with a long private value before continuing.</p>'
    );
}

require __DIR__ . '/portal/bootstrap.php';

function nmm_install_execute_sql_file(string $relativePath): void
{
    $absolutePath = __DIR__ . '/' . ltrim($relativePath, '/');
    $sql = @file_get_contents($absolutePath);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Could not read installer SQL: ' . $relativePath);
    }

    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            db()->exec($statement);
        }
    }
}

function nmm_install_schema_files(): array
{
    return [
        'database/north_mountain_portal.sql',
        'database/music_library_v44.sql',
        'database/music_customer_accounts_v66q20.sql',
        'database/music_customer_accounts_v66q21.sql',
    ];
}

$providedToken = trim((string)(
    $_POST['setup_token']
    ?? $_GET['token']
    ?? ''
));
$tokenAccepted = $providedToken !== ''
    && hash_equals($expectedToken, $providedToken);
$error = '';
$message = '';

if (is_post() && input('stage') === 'token') {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }
    if (!$tokenAccepted) {
        $error = 'The setup token did not match config.php. Copy it exactly without quotes or spaces.';
    }
}

if (is_post() && input('stage') === 'install') {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }

    if (!$tokenAccepted) {
        http_response_code(403);
        $error = 'The setup token expired or did not match config.php.';
    } elseif (rate_limit_exceeded('portal_install', request_ip(), 10, 3600)) {
        http_response_code(429);
        $error = 'Too many installation attempts were submitted. Wait and try again.';
    } else {
        try {
            $database = nmm_config('database');
            foreach (['host', 'name', 'username'] as $requiredKey) {
                if (trim((string)($database[$requiredKey] ?? '')) === '') {
                    throw new RuntimeException(
                        'Database setting "' . $requiredKey . '" is missing in config.php.'
                    );
                }
            }

            $email = strtolower(input('email'));
            $name = input('display_name');
            $password = (string)($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid administrator email address.');
            }
            if ($name === '') {
                throw new RuntimeException('Enter the administrator name.');
            }
            $passwordErrors = password_policy_errors($password, $email);
            if ($passwordErrors) {
                throw new RuntimeException(implode(' ', $passwordErrors));
            }

            foreach (nmm_install_schema_files() as $schemaFile) {
                nmm_install_execute_sql_file($schemaFile);
            }

            db()->prepare(
                'INSERT INTO users
                    (role,email,password_hash,display_name,status)
                 VALUES
                    ("admin",:email,:password_hash,:display_name,"active")
                 ON DUPLICATE KEY UPDATE
                    role="admin",
                    password_hash=VALUES(password_hash),
                    display_name=VALUES(display_name),
                    status="active"'
            )->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => $name,
            ]);

            $message = 'Installation completed successfully. The portal, Music Library, customer account role, private playlists, and customer lifecycle tables are installed. Delete or rename install.php, rotate the setup token, configure HTTPS, and sign in.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$showInstallForm = $tokenAccepted && $message === '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Install North Mountain Media Portal</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/portal.css?v=20260802-v66Q21'))?>">
</head>
<body>
<main class="setup-shell">
    <img src="<?=e(app_url('assets/images/north-mountain-media-logo.png'))?>" alt="North Mountain Media" style="width:280px;max-width:100%">
    <h1>Portal installation</h1>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success" role="status"><?=e($message)?></div>
        <p><a class="button button-primary" href="<?=e(app_url('portal/login.php?role=admin'))?>">Open administrator login</a></p>
    <?php elseif (!$showInstallForm): ?>
        <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?=e($error)?></div><?php endif; ?>
        <p>Enter the exact <code>setup_token</code> value from <code>config.php</code>.</p>
        <form method="post" class="form-panel">
            <?=csrf_field()?>
            <input type="hidden" name="stage" value="token">
            <label class="field"><span>Setup token</span><input type="password" name="setup_token" autocomplete="off" required></label>
            <div class="form-footer"><button class="button button-primary" type="submit">Continue</button></div>
        </form>
    <?php else: ?>
        <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?=e($error)?></div><?php endif; ?>
        <p>The setup token was accepted. The installer will apply the base portal, Music Library, customer account, and customer lifecycle schemas in dependency order.</p>
        <form method="post" class="form-panel">
            <?=csrf_field()?>
            <input type="hidden" name="stage" value="install">
            <input type="hidden" name="setup_token" value="<?=e($providedToken)?>">
            <div class="form-grid">
                <label class="field"><span>Administrator name</span><input name="display_name" value="David Evans" required></label>
                <label class="field"><span>Administrator email</span><input type="email" name="email" placeholder="administrator@example.com" autocomplete="email" required></label>
                <label class="field full"><span>Administrator password</span><input type="password" name="password" minlength="12" maxlength="256" autocomplete="new-password" required><small>Use at least 12 characters and at least three character types.</small></label>
            </div>
            <div class="form-footer"><button class="button button-primary" type="submit">Install portal and administrator</button></div>
        </form>
    <?php endif; ?>
</main>
</body>
</html>

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

if (!is_file($configFile)) {
    http_response_code(503);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Configuration required</title>
    <style>
        body{margin:0;background:#f1f4f6;color:#17202d;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
        main{width:min(100% - 32px,760px);margin:8vh auto;padding:28px;border:1px solid #dfe5eb;border-radius:20px;background:#fff}
        code{padding:2px 6px;border-radius:5px;background:#f2f4f7}
    </style>
</head>
<body>
<main>
    <h1>Configuration required</h1>
    <p>Copy <code>config-example.php</code> to <code>config.php</code>, then enter the database credentials and replace the setup token.</p>
    <p>During installation, leave <code>base_url</code> blank and keep <code>force_https</code> set to <code>false</code>.</p>
</main>
</body>
</html>
    <?php
    exit;
}

$config = require $configFile;

if (!is_array($config)) {
    http_response_code(500);
    exit('config.php must return a PHP array.');
}

$app = is_array($config['app'] ?? null) ? $config['app'] : [];
$security = is_array($config['security'] ?? null) ? $config['security'] : [];
$expectedToken = trim((string)($app['setup_token'] ?? ''));

if (
    $expectedToken === ''
    || $expectedToken === 'replace-with-a-long-random-one-time-token'
) {
    http_response_code(503);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Setup token required</title>
    <style>
        body{margin:0;background:#f1f4f6;color:#17202d;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
        main{width:min(100% - 32px,760px);margin:8vh auto;padding:28px;border:1px solid #dfe5eb;border-radius:20px;background:#fff}
        code{padding:2px 6px;border-radius:5px;background:#f2f4f7}
    </style>
</head>
<body>
<main>
    <h1>Replace the setup token</h1>
    <p>Open <code>config.php</code> and replace:</p>
    <pre><code>'setup_token' =&gt; 'replace-with-a-long-random-one-time-token',</code></pre>
    <p>with a long value containing letters and numbers. Example:</p>
    <pre><code>'setup_token' =&gt; 'nmm-setup-2026-change-this-to-a-long-private-value',</code></pre>
</main>
</body>
</html>
    <?php
    exit;
}

/**
 * Load the normal portal framework only after config has been validated.
 * HTTPS is intentionally not forced by the example config during setup.
 */
require __DIR__ . '/portal/bootstrap.php';

$providedToken = trim((string)(
    $_POST['setup_token']
    ?? $_GET['token']
    ?? ''
));

$tokenAccepted = (
    $providedToken !== ''
    && hash_equals($expectedToken, $providedToken)
);

$error = '';
$message = '';

if (is_post() && input('stage') === 'token') {
    verify_csrf();

    if (!$tokenAccepted) {
        $error = 'The setup token did not match config.php. Copy the value exactly without quotes or spaces.';
    }
}

if (is_post() && input('stage') === 'install') {
    verify_csrf();

    if (!$tokenAccepted) {
        http_response_code(403);
        $error = 'The setup token expired or did not match config.php.';
    } elseif (
        rate_limit_exceeded(
            'portal_install',
            request_ip(),
            10,
            3600
        )
    ) {
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

            $sql = file_get_contents(
                __DIR__ . '/database/north_mountain_portal.sql'
            );

            if ($sql === false) {
                throw new RuntimeException('Could not read the SQL installer.');
            }

            foreach (
                preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []
                as $statement
            ) {
                $statement = trim($statement);

                if ($statement !== '') {
                    db()->exec($statement);
                }
            }

            $email = strtolower(input('email'));
            $name = input('display_name');
            $password = (string)($_POST['password'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'Enter a valid administrator email address.'
                );
            }

            if ($name === '') {
                throw new RuntimeException(
                    'Enter the administrator name.'
                );
            }

            $passwordErrors = password_policy_errors(
                $password,
                $email
            );

            if ($passwordErrors) {
                throw new RuntimeException(
                    implode(' ', $passwordErrors)
                );
            }

            $statement = db()->prepare(
                'INSERT INTO users
                    (role, email, password_hash, display_name, status)
                 VALUES
                    ("admin", :email, :password_hash, :display_name, "active")
                 ON DUPLICATE KEY UPDATE
                    role = "admin",
                    password_hash = VALUES(password_hash),
                    display_name = VALUES(display_name),
                    status = "active"'
            );
            $statement->execute([
                'email' => $email,
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                'display_name' => $name,
            ]);

            $message = 'Installation completed successfully. Delete or rename install.php, rotate the setup token, and sign in through /portal/login.php?role=admin.';
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
    <title>Install North Mountain Media Portal</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/portal.css?v=20260726-installer-repair-v14')) ?>">
</head>
<body>
<main class="setup-shell">
    <img
        src="<?= e(app_url('assets/images/north-mountain-media-logo.png')) ?>"
        alt="North Mountain Media"
        style="width:280px;max-width:100%"
    >

    <h1>Portal installation</h1>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
        <p><a class="button button-primary" href="<?= e(app_url('portal/login.php?role=admin')) ?>">Open administrator login</a></p>

    <?php elseif (!$showInstallForm): ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <p>Enter the exact <code>setup_token</code> value from <code>config.php</code>.</p>

        <form method="post" class="form-panel">
            <?= csrf_field() ?>
            <input type="hidden" name="stage" value="token">

            <label class="field">
                <span>Setup token</span>
                <input
                    type="password"
                    name="setup_token"
                    autocomplete="off"
                    required
                >
            </label>

            <div class="form-footer">
                <button class="button button-primary" type="submit">
                    Continue
                </button>
            </div>
        </form>

    <?php else: ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <p>The setup token was accepted. Create or reset the first administrator account.</p>

        <form method="post" class="form-panel">
            <?= csrf_field() ?>
            <input type="hidden" name="stage" value="install">
            <input
                type="hidden"
                name="setup_token"
                value="<?= e($providedToken) ?>"
            >

            <div class="form-grid">
                <label class="field">
                    <span>Administrator name</span>
                    <input
                        name="display_name"
                        value="David Evans"
                        required
                    >
                </label>

                <label class="field">
                    <span>Administrator email</span>
                    <input
                        type="email"
                        name="email"
                        placeholder="administrator@example.com"
                        required
                    >
                </label>

                <label class="field full">
                    <span>Administrator password</span>
                    <input
                        type="password"
                        name="password"
                        minlength="12"
                        required
                    >
                    <small>
                        Use at least 12 characters and at least three of:
                        lowercase, uppercase, numbers, and symbols.
                    </small>
                </label>
            </div>

            <div class="form-footer">
                <button class="button button-primary" type="submit">
                    Install portal and administrator
                </button>
            </div>
        </form>
    <?php endif; ?>
</main>
</body>
</html>

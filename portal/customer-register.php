<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($signedIn = current_user()) {
    redirect(music_customer_home_for_role((string)$signedIn['role']));
}

$featureReady = music_customer_accounts_active();
$error = '';

if (is_post()) {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }

    try {
        if (!$featureReady) {
            throw new RuntimeException('Customer accounts are not currently available.');
        }
        if (input('website') !== '') {
            throw new RuntimeException('The account could not be created.');
        }
        if (
            function_exists('rate_limit_exceeded')
            && rate_limit_exceeded('music_customer_register', request_ip(), 5, 3600)
        ) {
            throw new RuntimeException('Too many account attempts were submitted. Try again later.');
        }

        $name = trim(input('display_name'));
        $email = strtolower(trim(input('email')));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($name === '' || mb_strlen($name) > 160) {
            throw new RuntimeException('Enter your name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new RuntimeException('Enter a valid email address.');
        }
        $passwordErrors = password_policy_errors($password, $email);
        if ($passwordErrors) {
            throw new RuntimeException(implode(' ', $passwordErrors));
        }
        if (!hash_equals($password, $confirm)) {
            throw new RuntimeException('The passwords do not match.');
        }

        $duplicate = db()->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
        $duplicate->execute(['email' => $email]);
        if ($duplicate->fetchColumn()) {
            throw new RuntimeException('An account already uses that email address.');
        }

        $statement = db()->prepare(
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
        $userId = (int)db()->lastInsertId();
        music_customer_start_session($userId);
        log_activity('music_customer_registered', 'user', $userId);
        flash('success', 'Your listener account is ready. Create your first playlist.');
        redirect('portal/customer.php?view=playlists&new=1');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,follow">
<title>Create Customer Account — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))?>">
</head>
<body class="music-customer-body">
<main class="music-customer-register-shell">
    <section class="music-customer-register-card">
        <a href="<?=e(app_url('music-library.php'))?>">
            <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
        </a>
        <?php if (!$featureReady): ?>
            <h1>Customer accounts are unavailable</h1>
            <p>The site owner has not enabled listener accounts and private playlists.</p>
            <div class="music-customer-register-links">
                <a href="<?=e(app_url('music-library.php'))?>">Return to Music Library</a>
                <a href="<?=e(app_url('portal/login.php?role=client'))?>">Client login</a>
            </div>
        <?php else: ?>
            <h1>Create your listener account</h1>
            <p>Save private playlists and organize the tracks you want to hear again. Customer accounts do not include client-project or administrator access.</p>
            <?php if ($error !== ''): ?>
                <div class="music-customer-alert music-customer-alert-error"><?=e($error)?></div>
            <?php endif; ?>
            <form method="post" class="music-customer-form" autocomplete="on">
                <?=csrf_field()?>
                <label class="music-customer-field music-customer-honeypot" aria-hidden="true">
                    <span>Website</span>
                    <input name="website" tabindex="-1" autocomplete="off">
                </label>
                <label class="music-customer-field">
                    <span>Name</span>
                    <input name="display_name" maxlength="160" value="<?=e(input('display_name'))?>" autocomplete="name" required>
                </label>
                <label class="music-customer-field">
                    <span>Email address</span>
                    <input type="email" name="email" maxlength="190" value="<?=e(input('email'))?>" autocomplete="email" required>
                </label>
                <label class="music-customer-field">
                    <span>Password</span>
                    <input type="password" name="password" maxlength="256" autocomplete="new-password" required>
                </label>
                <label class="music-customer-field">
                    <span>Confirm password</span>
                    <input type="password" name="confirm_password" maxlength="256" autocomplete="new-password" required>
                </label>
                <button class="music-customer-button" type="submit">Create customer account</button>
            </form>
            <div class="music-customer-register-links">
                <a href="<?=e(app_url('portal/login.php?role=customer'))?>">Already have an account?</a>
                <a href="<?=e(app_url('music-library.php'))?>">Return to Music Library</a>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

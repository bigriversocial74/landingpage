<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$message = '';
$error = '';
$verified = false;

if ($token !== '') {
    try {
        if (!music_customer_lifecycle_ready()) {
            throw new RuntimeException('Customer account verification is not installed.');
        }
        $result = music_customer_verify_token($token);
        $verified = true;
        $message = $result['purpose'] === 'change_email'
            ? 'Your new email address is confirmed. Sign in again with the new address.'
            : 'Your listener account is verified. You can sign in and create playlists.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if (is_post() && input('action') === 'resend_verification') {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }

    $email = strtolower(trim(input('email')));
    if (
        !rate_limit_exceeded('music_customer_verify_request_ip', request_ip(), 8, 3600)
        && filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $statement = db()->prepare(
            'SELECT id FROM users
             WHERE email=:email AND role="customer" AND status="active"
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $userId = (int)($statement->fetchColumn() ?: 0);
        if ($userId > 0) {
            $state = music_customer_state($userId, true);
            if (empty($state['email_verified_at'])) {
                music_customer_send_verification_link($userId);
            }
        }
    }
    $message = 'If the account still needs verification and email delivery is available, a new one-time link has been sent.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Verify Customer Account — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q21.css?v=20260802-v66Q21'))?>">
</head>
<body class="music-customer-body">
<a class="music-customer-skip-link" href="#customer-verification">Skip to verification</a>
<main class="music-customer-register-shell" id="customer-verification">
    <section class="music-customer-register-card">
        <a href="<?=e(app_url('music-library.php'))?>">
            <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
        </a>
        <div class="music-customer-token-status">
            <div>
                <span class="music-customer-eyebrow">Listener account</span>
                <h1>Email verification</h1>
            </div>
            <?php if ($message !== ''): ?>
                <div class="music-customer-alert music-customer-alert-success" role="status"><?=e($message)?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="music-customer-alert music-customer-alert-error" role="alert"><?=e($error)?></div>
            <?php endif; ?>

            <?php if ($verified): ?>
                <a class="music-customer-button" href="<?=e(app_url('portal/login.php?role=customer'))?>">Customer sign in</a>
            <?php else: ?>
                <p class="music-customer-card-subheading">Enter the account email to request another one-time verification link. The response does not disclose whether an account exists.</p>
                <form method="post" class="music-customer-form">
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="resend_verification">
                    <label class="music-customer-field">
                        <span>Email address</span>
                        <input type="email" name="email" maxlength="190" autocomplete="email" required>
                    </label>
                    <button class="music-customer-button" type="submit">Send verification link</button>
                </form>
                <div class="music-customer-auth-actions">
                    <a href="<?=e(app_url('portal/login.php?role=customer'))?>">Customer sign in</a>
                    <a href="<?=e(app_url('music-library.php'))?>">Return to Music Library</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>

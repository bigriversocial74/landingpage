<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$record = $token !== ''
    ? music_customer_token_record($token, ['password_reset', 'admin_reset'])
    : null;
$message = '';
$error = '';
$completed = false;

if (is_post()) {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }

    try {
        $action = input('action');
        if ($action === 'request_reset') {
            if (
                music_customer_lifecycle_ready()
                && music_customer_password_recovery_enabled()
            ) {
                music_customer_request_password_reset(input('email'));
            }
            $message = 'If that customer account can receive recovery email, a one-time reset link has been sent.';
        } elseif ($action === 'complete_reset') {
            music_customer_complete_password_reset(
                $token,
                (string)($_POST['new_password'] ?? ''),
                (string)($_POST['confirm_password'] ?? '')
            );
            $completed = true;
            $record = null;
            $message = 'Your password was updated. Existing customer sessions and unused account links were revoked.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $record = $token !== ''
            ? music_customer_token_record($token, ['password_reset', 'admin_reset'])
            : null;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Customer Password Recovery — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q21.css?v=20260802-v66Q21'))?>">
</head>
<body class="music-customer-body">
<a class="music-customer-skip-link" href="#customer-password">Skip to password recovery</a>
<main class="music-customer-register-shell" id="customer-password">
    <section class="music-customer-register-card">
        <a href="<?=e(app_url('music-library.php'))?>">
            <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
        </a>
        <span class="music-customer-eyebrow">Listener account</span>
        <h1><?= $record ? 'Choose a new password' : 'Password recovery' ?></h1>
        <p><?= $record
            ? 'This one-time link will be consumed when the password is changed.'
            : 'Request a secure one-time link. The response never confirms whether an email address is registered.' ?></p>

        <?php if ($message !== ''): ?>
            <div class="music-customer-alert music-customer-alert-success" role="status"><?=e($message)?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="music-customer-alert music-customer-alert-error" role="alert"><?=e($error)?></div>
        <?php endif; ?>

        <?php if ($completed): ?>
            <a class="music-customer-button" href="<?=e(app_url('portal/login.php?role=customer'))?>">Customer sign in</a>
        <?php elseif ($record): ?>
            <form method="post" class="music-customer-form">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="complete_reset">
                <input type="hidden" name="token" value="<?=e($token)?>">
                <label class="music-customer-field">
                    <span>New password</span>
                    <input type="password" name="new_password" maxlength="256" autocomplete="new-password" required>
                    <small>Use at least 12 characters and at least three character types.</small>
                </label>
                <label class="music-customer-field">
                    <span>Confirm new password</span>
                    <input type="password" name="confirm_password" maxlength="256" autocomplete="new-password" required>
                </label>
                <button class="music-customer-button" type="submit">Update password</button>
            </form>
        <?php else: ?>
            <?php if ($token !== '' && $message === ''): ?>
                <div class="music-customer-alert music-customer-alert-error" role="alert">This password-reset link is invalid or expired.</div>
            <?php endif; ?>
            <form method="post" class="music-customer-form">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="request_reset">
                <label class="music-customer-field">
                    <span>Email address</span>
                    <input type="email" name="email" maxlength="190" autocomplete="email" required>
                </label>
                <button class="music-customer-button" type="submit">Send reset link</button>
            </form>
        <?php endif; ?>

        <div class="music-customer-auth-actions">
            <a href="<?=e(app_url('portal/login.php?role=customer'))?>">Customer sign in</a>
            <a href="<?=e(app_url('music-library.php'))?>">Return to Music Library</a>
        </div>
    </section>
</main>
</body>
</html>

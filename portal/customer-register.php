<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($signedIn = current_user()) {
    redirect(music_customer_home_for_role((string)$signedIn['role']));
}

$featureEnabled = music_customer_accounts_active();
$lifecycleReady = music_customer_lifecycle_ready();
$error = '';
$submitted = false;
$verificationRequired = $lifecycleReady && music_customer_email_verification_required();

if (is_post()) {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }

    try {
        if (!$featureEnabled) {
            throw new RuntimeException('Customer accounts are not currently available.');
        }
        if (!$lifecycleReady) {
            throw new RuntimeException('Customer account security is not installed. Contact the site owner.');
        }
        if (input('website') !== '') {
            throw new RuntimeException('The account request could not be accepted.');
        }
        if (rate_limit_exceeded('music_customer_register_ip', request_ip(), 5, 3600)) {
            throw new RuntimeException('Too many account requests were submitted. Try again later.');
        }

        $result = music_customer_register_final(
            input('display_name'),
            input('email'),
            (string)($_POST['password'] ?? ''),
            (string)($_POST['confirm_password'] ?? '')
        );
        $verificationRequired = (bool)$result['verification_required'];

        if ($result['created'] && !$verificationRequired) {
            music_customer_start_secure_session((int)$result['user_id']);
            flash('success', 'Your listener account is ready. Create your first playlist.');
            redirect('portal/customer.php?view=playlists&new=1');
        }

        $submitted = true;
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
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q21.css?v=20260802-v66Q21'))?>">
</head>
<body class="music-customer-body">
<a class="music-customer-skip-link" href="#customer-registration">Skip to account registration</a>
<main class="music-customer-register-shell" id="customer-registration">
    <section class="music-customer-register-card">
        <a href="<?=e(app_url('music-library.php'))?>">
            <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
        </a>

        <?php if (!$featureEnabled): ?>
            <h1>Customer accounts are unavailable</h1>
            <p>The site owner has not enabled listener accounts and private playlists.</p>
            <div class="music-customer-register-links">
                <a href="<?=e(app_url('music-library.php'))?>">Return to Music Library</a>
                <a href="<?=e(app_url('portal/login.php?role=client'))?>">Client login</a>
            </div>
        <?php elseif (!$lifecycleReady): ?>
            <h1>Account setup is incomplete</h1>
            <p>The customer lifecycle migration must be installed before new listener accounts can be created.</p>
            <div class="music-customer-register-links">
                <a href="<?=e(app_url('music-library.php'))?>">Return to Music Library</a>
                <a href="<?=e(app_url('portal/login.php?role=customer'))?>">Customer login</a>
            </div>
        <?php elseif ($submitted): ?>
            <span class="music-customer-eyebrow">Account request received</span>
            <h1>Check your email</h1>
            <div class="music-customer-alert music-customer-alert-success" role="status">
                If this address can be used for a listener account, the next step has been sent. Existing accounts are not disclosed.
            </div>
            <p>Verification links are one-time, expire after one hour, and do not contain your password.</p>
            <div class="music-customer-register-links">
                <a href="<?=e(app_url('portal/customer-verify.php?pending=1'))?>">Resend verification</a>
                <a href="<?=e(app_url('portal/login.php?role=customer'))?>">Customer sign in</a>
            </div>
        <?php else: ?>
            <span class="music-customer-eyebrow">Private playlists</span>
            <h1>Create your listener account</h1>
            <p>Save private playlists and organize the tracks you want to hear again. Customer accounts never include client-project or administrator access.</p>
            <?php if ($verificationRequired): ?>
                <div class="music-customer-notice">Email verification is required. A one-time activation link will be sent after registration.</div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="music-customer-alert music-customer-alert-error" role="alert"><?=e($error)?></div>
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
                    <input type="password" name="password" maxlength="256" autocomplete="new-password" aria-describedby="customer-password-help" required>
                    <small id="customer-password-help">Use at least 12 characters and at least three of: lowercase, uppercase, numbers, and symbols.</small>
                </label>
                <label class="music-customer-field">
                    <span>Confirm password</span>
                    <input type="password" name="confirm_password" maxlength="256" autocomplete="new-password" required>
                </label>
                <button class="music-customer-button" type="submit">Create customer account</button>
            </form>
            <div class="music-customer-auth-actions">
                <a href="<?=e(app_url('portal/login.php?role=customer'))?>">Already have an account?</a>
                <a href="<?=e(app_url('portal/customer-password.php'))?>">Forgot password?</a>
                <a href="<?=e(app_url('music-library.php'))?>">Return to Music Library</a>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

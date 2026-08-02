<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/pod-follow-handoff.php';

function attempt_pod_account_login(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    $ip = request_ip();
    if (login_blocked($email, $ip)) return false;
    $statement = db()->prepare('SELECT * FROM users WHERE email=:email AND status="active" ORDER BY FIELD(role,"admin","client","customer"),id LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    $valid = $user && password_verify($password, (string)$user['password_hash']);
    db()->prepare('INSERT INTO login_attempts (email,ip_address,success) VALUES (:email,:ip_address,:success)')
        ->execute(['email' => $email, 'ip_address' => $ip, 'success' => $valid ? 1 : 0]);
    if (!$valid) return false;
    music_customer_start_session((int)$user['id']);
    log_activity('login', 'user', (int)$user['id'], ['role' => (string)$user['role'], 'context' => 'pod_follow']);
    return true;
}

$role = (string)($_GET['role'] ?? $_POST['role'] ?? 'client');
if (!in_array($role, ['admin', 'client', 'customer', 'pod'], true)) $role = 'client';
$returnTo = pod_follow_safe_login_return((string)($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
$customerEnabled = music_customer_accounts_active();

if ($user = current_user()) {
    if ($returnTo !== '') redirect($returnTo);
    redirect(music_customer_home_for_role((string)$user['role']));
}

$error = '';
if ($role === 'customer' && !$customerEnabled) {
    $error = 'Customer accounts are not currently enabled for this Music Library.';
}

if (is_post()) {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }

    $accepted = false;
    if ($role === 'customer' && !$customerEnabled) {
        $error = 'Customer accounts are not currently enabled for this Music Library.';
    } else {
        $accepted = $role === 'pod'
            ? attempt_pod_account_login(input('email'), (string)($_POST['password'] ?? ''))
            : attempt_login(input('email'), (string)($_POST['password'] ?? ''), $role);
    }

    if ($accepted) {
        if ($returnTo !== '') redirect($returnTo);
        $signedIn = current_user();
        redirect(music_customer_home_for_role((string)($signedIn['role'] ?? $role)));
    }
    if ($error === '') {
        $error = 'The email or password was not accepted. Please try again.';
    }
}

$label = match ($role) {
    'admin' => 'Administrator',
    'pod' => 'POD',
    'customer' => 'Customer',
    default => 'Client',
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($label)?> Login — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/portal.css'))?>">
</head>
<body class="auth-body">
<main class="auth-shell">
<section class="auth-card">
<a class="auth-logo" href="<?=e(app_url('index.php'))?>"><img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>"></a>
<div class="auth-heading"><span><?=e($label)?> access</span><h1><?=e($label)?> login</h1></div>
<?php if ($error): ?><div class="alert alert-error"><?=e($error)?></div><?php endif; ?>
<?php if ($role !== 'customer' || $customerEnabled): ?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="role" value="<?=e($role)?>">
<?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?=e($returnTo)?>"><?php endif; ?>
<label class="field"><span>Email address</span><input type="email" name="email" autocomplete="email" required></label>
<label class="field"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
<button class="button button-primary" type="submit"><?= $role === 'pod' ? 'Sign in and follow' : 'Sign in' ?></button>
</form>
<?php endif; ?>
<p class="auth-help">
<?php if ($role === 'pod'): ?>
Sign in to your POD account. After authentication, your POD will send the signed ActivityPub Follow request and return you to the site you chose to follow.
<?php elseif ($role === 'customer'): ?>
Customer accounts are listener accounts for private playlists and music preferences. They do not include client projects or administrator tools.
<?php elseif ($role === 'client'): ?>
Client accounts are created by North Mountain Media. Contact Dave if you need project access.
<?php else: ?>
Administrator access is restricted to authorized North Mountain Media staff.
<?php endif; ?>
</p>
<?php if ($role === 'customer' && $customerEnabled): ?>
<p class="auth-switch"><a href="<?=e(app_url('portal/customer-register.php'))?>">Create a customer account</a></p>
<?php endif; ?>
<?php if ($role !== 'pod'): ?>
<p class="auth-switch">
<?php if ($customerEnabled && $role !== 'customer'): ?><a href="<?=e(app_url('portal/login.php?role=customer'))?>">Customer login</a> · <?php endif; ?>
<?php if ($role !== 'client'): ?><a href="<?=e(app_url('portal/login.php?role=client'))?>">Client login</a><?php endif; ?>
<?php if ($role !== 'admin'): ?><?= $role !== 'client' ? ' · ' : '' ?><a href="<?=e(app_url('portal/login.php?role=admin'))?>">Administrator login</a><?php endif; ?>
</p>
<?php endif; ?>
<p class="auth-return"><a href="<?=e(app_url($role === 'customer' ? 'music-library.php' : 'index.php'))?>">Return to <?= $role === 'customer' ? 'Music Library' : 'the public portfolio' ?></a></p>
</section>
</main>
</body>
</html>

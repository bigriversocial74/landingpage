<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if ($u = current_user()) redirect('portal/' . ($u['role'] === 'admin' ? 'admin.php' : 'client.php'));
$role = (string)($_GET['role'] ?? $_POST['role'] ?? 'client');
if (!in_array($role, ['admin','client'], true)) $role = 'client';
$error = '';
if (is_post()) {
    verify_csrf();
    if (!same_origin_request()) { http_response_code(403); exit('Invalid request origin.'); }
    if (attempt_login(input('email'), (string)($_POST['password'] ?? ''), $role)) redirect('portal/' . ($role === 'admin' ? 'admin.php' : 'client.php'));
    $error = 'The email or password was not accepted. Please try again.';
}
$label = $role === 'admin' ? 'Administrator' : 'Client';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($label) ?> Login — North Mountain Media</title><link rel="stylesheet" href="<?= e(app_url('assets/css/portal.css')) ?>"></head><body class="auth-body"><main class="auth-shell"><section class="auth-card"><a class="auth-logo" href="<?= e(app_url('index.php')) ?>"><img src="<?= e(nmm_site_logo_url()) ?>" alt="<?= e(nmm_site_logo_alt()) ?>"></a><div class="auth-heading"><span><?= e($label) ?> access</span><h1><?= e($label) ?> login</h1></div>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="role" value="<?= e($role) ?>"><label class="field"><span>Email address</span><input type="email" name="email" autocomplete="email" required></label><label class="field"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label><button class="button button-primary" type="submit">Sign in</button></form>
<p class="auth-help"><?= $role === 'client' ? 'Client accounts are created by North Mountain Media. Contact Dave if you need access.' : 'Administrator access is restricted to authorized North Mountain Media staff.' ?></p><p class="auth-switch"><a href="<?= e(app_url('portal/login.php?role=' . ($role === 'admin' ? 'client' : 'admin'))) ?>">Use <?= $role === 'admin' ? 'client' : 'administrator' ?> login</a></p><p class="auth-return"><a href="<?= e(app_url('index.php')) ?>">Return to the public portfolio</a></p></section></main></body></html>

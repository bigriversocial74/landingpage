<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
define('NMM_PUBLIC_MICROPHONE_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
nmm_require_public_module('call_us');
require_once __DIR__ . '/portal/pod-connected-calling.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$error = '';
$token = trim((string)($_GET['token'] ?? ''));

try {
    if ($token === '') {
        throw new RuntimeException('This connected POD call link is incomplete.');
    }

    if (rate_limit_exceeded('pod_connected_call_entry', request_ip(), 30, 3600)) {
        http_response_code(429);
        throw new RuntimeException('Too many connected call attempts were received. Try again later.');
    }

    $context = pod_authorize_connected_call_token($token);
    session_regenerate_id(true);
    $_SESSION['pod_connected_call_context']['authorized_at'] = time();

    $contactId = (int)($context['crm_contact_id'] ?? 0);
    $contactEmail = strtolower(trim((string)($context['contact_email'] ?? '')));

    if (
        $contactId > 0
        && ($contactEmail === '' || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL))
    ) {
        $contactEmail = 'pod-' . substr(
            hash('sha256', (string)$context['remote_pod_uuid']),
            0,
            24
        ) . '@local.invalid';
        db()->prepare(
            'UPDATE crm_contacts
             SET email=:email,updated_at=UTC_TIMESTAMP()
             WHERE id=:id
               AND (
                    email IS NULL OR email=""
                    OR email NOT LIKE "%@%"
               )'
        )->execute([
            'email' => $contactEmail,
            'id' => $contactId,
        ]);
    }

    redirect('connected-call.php');
} catch (Throwable $exception) {
    if (http_response_code() < 400) http_response_code(403);
    pod_clear_connected_call_context();
    $error = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Connected POD call unavailable</title>
    <style>
        body{margin:0;background:#f4f6f8;color:#17202c;font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}
        main{width:min(100% - 32px,680px);margin:10vh auto;padding:30px;border:1px solid #dde3ea;border-radius:22px;background:#fff;box-shadow:0 18px 60px rgba(20,31,48,.08)}
        span{display:block;color:#667085;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
        h1{margin:.45rem 0 .75rem;font-size:clamp(1.7rem,4vw,2.45rem)}
        p{color:#536174}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.actions a{display:inline-flex;border-radius:999px;padding:11px 17px;background:#111827;color:#fff;text-decoration:none;font-weight:800}.actions a.secondary{background:#edf1f6;color:#263246}
    </style>
</head>
<body>
<main>
    <span>POD connected calling</span>
    <h1>This relationship call link is unavailable.</h1>
    <p><?= e($error !== '' ? $error : 'The connected call could not be authorized.') ?></p>
    <p>You can still use the public Call Us page. Existing browser calling and voicemail remain available.</p>
    <div class="actions">
        <a href="<?= e(app_url('call-dave.php')) ?>">Open public Call Us</a>
        <a class="secondary" href="<?= e(app_url('index.php')) ?>">Public profile</a>
    </div>
</main>
</body>
</html>

<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require __DIR__ . '/portal/bootstrap.php';
require_once __DIR__ . '/portal/pod-follow-handoff.php';

header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$intentUrl = trim((string)($_GET['intent_url'] ?? $_SESSION['pod_follow_pending_intent_url'] ?? ''));
$intent = null;

try {
    if ($intentUrl === '') throw new RuntimeException('The POD follow request is missing.');
    $intent = pod_follow_fetch_remote_intent($intentUrl);

    $user = current_user();
    if (!$user) {
        $_SESSION['pod_follow_pending_intent_url'] = $intentUrl;
        $returnTo = pod_follow_local_login_return($intentUrl);
        redirect('portal/login.php?role=pod&return_to=' . rawurlencode($returnTo));
    }

    unset($_SESSION['pod_follow_pending_intent_url']);
    enforce_authenticated_action_limit($user);
    if (rate_limit_exceeded('pod_follow_authorize', (string)$user['id'], 30, 3600)) {
        throw new RuntimeException('Too many POD follow actions were requested. Wait briefly and try again.');
    }
    if (!nmm_module_enabled('social_feed') || !activitypub_settings()['enabled']) {
        throw new RuntimeException('Enable Social Feed and ActivityPub on your POD before following another POD.');
    }

    $targetActor = trim((string)$intent['target_actor']);
    $followingId = federated_interactions_follow_actor($targetActor, (int)$user['id']);
    if ($followingId <= 0) throw new RuntimeException('The POD could not create the follow relationship.');

    try {
        activitypub_process_delivery_queue(20);
    } catch (Throwable $deliveryException) {
        log_activity('pod_follow_delivery_deferred', 'activitypub_following', $followingId, [
            'target_actor' => $targetActor,
            'error' => mb_substr($deliveryException->getMessage(), 0, 500),
        ]);
    }

    $statement = db()->prepare('SELECT status FROM activitypub_following WHERE id=:id LIMIT 1');
    $statement->execute(['id' => $followingId]);
    $followingStatus = (string)($statement->fetchColumn() ?: 'pending');
    $result = $followingStatus === 'accepted' ? 'following' : 'pending';
    $homeOrigin = pod_configured_origin();
    $homeActor = activitypub_actor_url();

    log_activity('pod_follow_one_click_completed', 'activitypub_following', $followingId, [
        'target_actor' => $targetActor,
        'status' => $followingStatus,
        'return_origin' => pod_follow_origin((string)$intent['return_url']),
    ]);

    redirect(pod_follow_append_result(
        (string)$intent['return_url'],
        $result,
        $homeOrigin,
        $homeActor
    ));
} catch (Throwable $exception) {
    unset($_SESSION['pod_follow_pending_intent_url']);
    $message = mb_substr($exception->getMessage(), 0, 180);
    log_activity('pod_follow_one_click_failed', null, null, [
        'intent_url' => mb_substr($intentUrl, 0, 1000),
        'error' => $message,
    ]);
    if (is_array($intent) && !empty($intent['return_url'])) {
        redirect(pod_follow_append_result(
            (string)$intent['return_url'],
            'error',
            pod_configured_origin(),
            '',
            $message
        ));
    }
    http_response_code(400);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>POD Follow Could Not Continue</title>
        <link rel="stylesheet" href="<?=e(app_url('assets/css/portal.css'))?>">
    </head>
    <body class="auth-body">
        <main class="auth-shell">
            <section class="auth-card">
                <a class="auth-logo" href="<?=e(app_url('index.php'))?>"><img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>"></a>
                <div class="auth-heading"><span>POD follow</span><h1>Follow could not continue</h1></div>
                <div class="alert alert-error"><?=e($message)?></div>
                <p class="auth-return"><a href="<?=e(app_url('index.php'))?>">Return to this POD</a></p>
            </section>
        </main>
    </body>
    </html>
    <?php
}

<?php
declare(strict_types=1);

function v66q15_fail(string $message): never
{
    fwrite(STDERR, "v66Q.15 one-click POD follow failure: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') v66q15_fail('Unable to read ' . $path);
    return $content;
};
$require = static function (string $source, string $needle, string $label): void {
    if (!str_contains($source, $needle)) v66q15_fail($label . ' missing: ' . $needle);
};
$forbid = static function (string $source, string $needle, string $label): void {
    if (str_contains($source, $needle)) v66q15_fail($label . ' retains forbidden behavior: ' . $needle);
};

$handoff = $read('portal/pod-follow-handoff.php');
$intent = $read('pod-follow-intent.php');
$authorize = $read('pod-follow-authorize.php');
$login = $read('portal/login.php');
$follow = $read('portal/public-follow.php');
$followJs = $read('assets/js/public-follow-v66q9.js');
$followCss = $read('assets/css/public-follow-v66q9.css');
$federation = $read('portal/federated-interactions.php');

foreach ([
    'function pod_follow_create_intent',
    'function pod_follow_verify_intent_token',
    "hash_hmac('sha256'",
    "'expires_at' => $now + 10 * 60",
    'function pod_follow_fetch_remote_intent',
    'activitypub_fetch_json($intentUrl, 1)',
    'pod_follow_same_origin($intentUrl, $targetActor)',
    'pod_follow_same_origin($returnUrl, $targetActor)',
    'function pod_follow_safe_login_return',
    "$path !== 'pod-follow-authorize.php'",
] as $contract) {
    $require($handoff, $contract, 'Signed POD follow handoff');
}
foreach ([
    'same_origin_request()',
    'verify_csrf()',
    "rate_limit_exceeded('pod_follow_intent'",
    'pod_follow_create_intent($returnUrl)',
    "'protocol' => 'pod-follow-launch-1'",
    'pod_follow_verify_intent_token($token)',
] as $contract) {
    $require($intent, $contract, 'Public follow intent endpoint');
}
foreach ([
    'pod_follow_fetch_remote_intent($intentUrl)',
    "$_SESSION['pod_follow_pending_intent_url']",
    "portal/login.php?role=pod&return_to=",
    'federated_interactions_follow_actor($targetActor',
    'activitypub_process_delivery_queue(20)',
    "SELECT status FROM activitypub_following",
    "'pod_follow_one_click_completed'",
    'pod_follow_append_result(',
] as $contract) {
    $require($authorize, $contract, 'Authenticated home POD authorization');
}
foreach ([
    "['admin', 'client', 'pod']",
    'function attempt_pod_account_login',
    'pod_follow_safe_login_return',
    "attempt_pod_account_login(input('email')",
    "'Sign in and follow'",
    'your POD will send the signed ActivityPub Follow request',
] as $contract) {
    $require($login, $contract, 'POD login resume');
}
foreach ([
    "'intent_endpoint' => app_url('pod-follow-intent.php')",
    'data-follow-target-actor',
    'data-follow-intent-endpoint',
    'data-follow-csrf',
    'data-follow-pod-form',
    'data-follow-home-pod',
    'Sign in to your POD to follow',
    'POD / HomeServer',
    'RSS Feed',
] as $contract) {
    $require($follow, $contract, 'Public one-click Follow modal');
}
foreach ([
    "const HOME_POD_KEY = 'vp3.homePodOrigin.v1'",
    'normalizeHomePodOrigin',
    "method: 'POST'",
    "'X-CSRF-Token': csrfToken",
    "new URL('/pod-follow-authorize.php', origin)",
    "authorizeUrl.searchParams.set('intent_url', intentUrl)",
    'window.location.assign(authorizeUrl.toString())',
    "url.searchParams.get('pod_follow')",
    "trigger.textContent = 'Following'",
    'applyReturnedFollowState()',
] as $contract) {
    $require($followJs, $contract, 'One-click Follow browser runtime');
}
foreach ([
    '.public-follow-pod-form',
    '.public-follow-known-pod',
    '[data-follow-button-state="following"]',
    '[data-follow-status-type="error"]',
] as $contract) {
    $require($followCss, $contract, 'One-click Follow presentation');
}
foreach ([
    'function federated_interactions_follow_actor',
    "'type' => 'Follow'",
    'activitypub_queue_delivery($outboxId',
    'ON DUPLICATE KEY UPDATE follow_activity_uri',
    'function federated_interactions_unfollow_actor',
] as $contract) {
    $require($federation, $contract, 'Retained outbound ActivityPub graph');
}

$forbid($follow, 'name="fediverse_handle"', 'Normal POD Follow modal');
$forbid($authorize, 'password', 'Cross-POD follow authorization');
$forbid($handoff . $intent . $authorize . $login . $follow . $followJs, 'CREATE TABLE', 'Runtime schema mutation');
$forbid($handoff . $intent . $authorize . $login . $follow . $followJs, 'ALTER TABLE', 'Runtime schema mutation');
$forbid($handoff . $intent . $authorize . $login . $follow . $followJs, 'DROP TABLE', 'Runtime schema mutation');

echo "v66Q.15 signed one-click POD follow and login resume contract passed.\n";

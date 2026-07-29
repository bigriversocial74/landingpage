<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require dirname(__DIR__, 2) . '/portal/bootstrap.php';
require_once dirname(__DIR__, 2) . '/portal/vp3-licensing.php';
require_once dirname(__DIR__, 2) . '/portal/vp3-license-policy.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$authorized = false;
$user = current_user();
if (is_array($user) && (string)($user['role'] ?? '') === 'admin') {
    $providedCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $authorized = $providedCsrf !== '' && hash_equals(csrf_token(), $providedCsrf);
}

if (!$authorized) {
    $configured = trim((string)(nmm_config('vp3_licensing')['update_worker_token'] ?? ''));
    $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($configured !== '' && str_starts_with($authorization, 'Bearer ')) {
        $provided = trim(substr($authorization, 7));
        $authorized = strlen($provided) === strlen($configured) && hash_equals($configured, $provided);
    }
}

if (!$authorized) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'authorization_required']);
    exit;
}

$maximum = 128 * 1024;
$raw = file_get_contents('php://input', false, null, 0, $maximum + 1);
if (!is_string($raw) || strlen($raw) > $maximum) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'request_too_large']);
    exit;
}

try {
    $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) {
        throw new RuntimeException('Update manifest must be a JSON object.');
    }

    $eligibility = vp3_update_eligibility($manifest);
    $policy = vp3_managed_updates_policy();
    $eligible = !empty($eligibility['eligible']) && !empty($policy['automatic_updates_enabled']);

    if (!$eligible && empty($policy['automatic_updates_enabled'])) {
        $eligibility['reasons'] = array_values(array_unique(array_merge(
            is_array($eligibility['reasons'] ?? null) ? $eligibility['reasons'] : [],
            ['vp3_license_required_for_managed_updates']
        )));
    }

    http_response_code($eligible ? 200 : 403);
    echo json_encode([
        'ok' => $eligible,
        'eligibility' => $eligibility,
        'policy' => $policy,
        'site_operational' => true,
        'manual_deployment_allowed' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'update_eligibility_failed',
        'message' => mb_substr($exception->getMessage(), 0, 300),
        'site_operational' => true,
        'manual_deployment_allowed' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

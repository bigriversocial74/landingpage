<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);
require dirname(__DIR__, 2) . '/portal/bootstrap.php';
require_once dirname(__DIR__, 2) . '/portal/vp3-update-core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$flagPath = vp3_update_work_root() . '/maintenance.flag';
$raw = is_file($flagPath) ? file_get_contents($flagPath) : false;
$flag = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($flag)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'health_window_closed']);
    exit;
}

$provided = trim((string)($_SERVER['HTTP_X_VP3_HEALTH_TOKEN'] ?? ''));
$expectedHash = strtolower(trim((string)($flag['health_token_hash'] ?? '')));
if (
    $provided === ''
    || !preg_match('/^[a-f0-9]{64}$/', $expectedHash)
    || !hash_equals($expectedHash, hash('sha256', $provided))
) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'authorization_required']);
    exit;
}

$expectedVersion = trim((string)($_SERVER['HTTP_X_VP3_EXPECTED_VERSION'] ?? ''));
$targetVersion = trim((string)($flag['target_version'] ?? ''));
if ($expectedVersion === '' || $targetVersion === '' || !hash_equals($targetVersion, $expectedVersion)) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'target_version_mismatch']);
    exit;
}

$checks = [
    'database' => false,
    'bootstrap_present' => is_file(NMM_ROOT . '/portal/bootstrap.php'),
    'index_present' => is_file(NMM_ROOT . '/index.php'),
    'config_preserved' => is_file(NMM_ROOT . '/config.php'),
    'storage_preserved' => is_dir(NMM_ROOT . '/storage'),
    'storage_writable' => is_writable(NMM_ROOT . '/storage'),
    'version_matches' => hash_equals($expectedVersion, vp3_update_installed_version()),
];
try {
    $checks['database'] = (int)db()->query('SELECT 1')->fetchColumn() === 1;
} catch (Throwable) {
    $checks['database'] = false;
}

$ok = !in_array(false, $checks, true);
http_response_code($ok ? 200 : 503);
echo json_encode([
    'ok' => $ok,
    'installed_version' => vp3_update_installed_version(),
    'checks' => $checks,
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

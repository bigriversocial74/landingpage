<?php
declare(strict_types=1);

function recovery_source_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$core = file_get_contents($root . '/portal/incident-response.php');
$admin = file_get_contents($root . '/portal/recovery-center.php');
$operations = file_get_contents($root . '/portal/operations-analytics-extensions.php');
$worker = file_get_contents($root . '/cron/process-recovery.php');
$migration = file_get_contents($root . '/database/incident_response_runbooks_v66m.sql');
$fresh = file_get_contents($root . '/database/north_mountain_portal_v66m.sql');
$spec = file_get_contents($root . '/INCIDENT-RESPONSE-RUNBOOKS-SPEC-v66M.md');
$style = file_get_contents($root . '/assets/css/recovery-center.css');
foreach (compact('core','admin','operations','worker','migration','fresh','spec','style') as $name => $source) {
    recovery_source_assert(is_string($source), 'Required v66M source is unreadable: ' . $name);
}

$runbookKeys = [
    'notification_queue_recovery',
    'activitypub_delivery_recovery',
    'websub_delivery_recovery',
    'automation_event_recovery',
    'feed_source_recovery',
    'operations_window_rebuild',
    'vp3_license_refresh',
    'vp3_update_check',
    'incident_escalation',
];
foreach ($runbookKeys as $key) {
    recovery_source_assert(substr_count($core, "'{$key}' => [") === 1, 'Permanent runbook is missing: ' . $key);
}

$handlers = [
    'notification.release_expired','notification.retry_failed','notification.process_batch',
    'activitypub.retry_failed','activitypub.process_batch',
    'websub.retry_failed','websub.process_batch',
    'automation.recover_approvals','automation.retry_failed','automation.process_batch',
    'feed.requeue_failed','operations.rebuild_window','vp3.license_refresh','vp3.update_check','incident.escalate',
];
foreach ($handlers as $handler) {
    recovery_source_assert(substr_count($core, "'handler' => '{$handler}'") >= 1, 'Allowlisted handler is missing: ' . $handler);
}
recovery_source_assert(substr_count($core, "default:\n            throw new RuntimeException('Recovery handler is not allowlisted.')") >= 2, 'Handler allowlist does not fail closed.');

foreach (['shell_exec','exec','passthru','proc_open','popen','eval','assert'] as $forbiddenFunction) {
    recovery_source_assert(
        !preg_match('/\\b' . preg_quote($forbiddenFunction, '/') . '\\s*\\(/', $core),
        'Arbitrary execution primitive entered recovery core: ' . $forbiddenFunction
    );
}
recovery_source_assert(!str_contains($core, '->install('), 'Recovery gained software-install authority.');
recovery_source_assert(!str_contains($core, '->prepare('), 'Recovery gained update-prepare authority.');
recovery_source_assert(!str_contains($core, 'runScheduled('), 'Recovery gained unattended update authority.');
recovery_source_assert(str_contains($core, '$agent->check(null, \'system\')'), 'VP3 recovery is not limited to an update-availability check.');
recovery_source_assert(str_contains($core, "'homeserver_tool_execution' => false"), 'HomeServer tool execution prohibition is missing.');
recovery_source_assert(str_contains($core, "'software_install' => false"), 'Software-install prohibition is missing.');
recovery_source_assert(str_contains($core, "'arbitrary_sql' => false"), 'Arbitrary-SQL prohibition is missing.');

$hashPosition = strpos($core, '$hash = hash(\'sha256\'');
$generatedPosition = strpos($core, '$plan[\'generated_at\'] = gmdate(DATE_ATOM)');
recovery_source_assert($hashPosition !== false && $generatedPosition !== false && $hashPosition < $generatedPosition, 'Simulation hash includes volatile generation time.');
recovery_source_assert(str_contains($core, 'recovery_json_encode($plan));'), 'Simulation hash is not bound to the deterministic plan.');
recovery_source_assert(str_contains($core, 'request_hash=IF(status IN ("consumed","approved"),request_hash,VALUES(request_hash))'), 'Approved request hash can be silently replaced.');
recovery_source_assert(str_contains($core, 'expires_at=IF(status IN ("consumed","approved"),expires_at,VALUES(expires_at))'), 'Approved request expiry can be silently extended.');
$idempotencyPosition = strpos($core, 'SELECT * FROM recovery_executions WHERE idempotency_key=:idempotency_key');
$stalePosition = strpos($core, 'The recovery simulation is stale or expired.');
recovery_source_assert($idempotencyPosition !== false && $stalePosition !== false && $idempotencyPosition < $stalePosition, 'Execution idempotency is evaluated too late.');
recovery_source_assert(str_contains($core, 'status=IF(attempt_count<max_attempts,"queued","failed")'), 'Execution failures are not retried within the bounded attempt ceiling.');
recovery_source_assert(str_contains($core, 'operations_analytics_run_extended'), 'Post-repair verification does not include extended v66L metrics.');
recovery_source_assert(str_contains($core, 'recovery_recover_expired_leases'), 'Interrupted execution recovery is missing.');
recovery_source_assert(str_contains($core, 'verification_status'), 'Durable health verification is missing.');
recovery_source_assert(str_contains($core, 'recovery_action_receipts'), 'Immutable recovery receipt path is missing.');

recovery_source_assert(str_contains($worker, "PHP_SAPI !== 'cli'"), 'Recovery worker is not CLI-only.');
recovery_source_assert(str_contains($worker, 'recovery_run_worker($limit)'), 'Recovery worker does not use the governed engine.');
recovery_source_assert(str_contains($admin, "require_role('admin')"), 'Recovery Center is not administrator-only.');
recovery_source_assert(str_contains($admin, 'verify_csrf()'), 'Recovery Center mutations are not CSRF-protected.');
recovery_source_assert(str_contains($admin, 'enforce_authenticated_action_limit($user)'), 'Recovery Center mutations are not rate-limited.');
recovery_source_assert(str_contains($admin, 'data-recovery-center-nav'), 'Recovery Center navigation marker is missing.');
recovery_source_assert(str_contains($operations, 'data-recovery-center-entry'), 'Operations Analytics does not expose the Recovery Center entry.');
recovery_source_assert(str_contains($operations, "app_url('portal/recovery-center.php')"), 'Operations Recovery Center link is not canonical.');
recovery_source_assert(str_contains($style, '.recovery-shell'), 'Recovery Center responsive workspace styles are missing.');

foreach ([
    'recovery_settings','recovery_runbooks','recovery_runbook_versions','recovery_recommendations',
    'recovery_simulations','recovery_approvals','recovery_executions','recovery_execution_steps','recovery_action_receipts',
] as $table) {
    recovery_source_assert(substr_count($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'Migration table contract failed: ' . $table);
}
recovery_source_assert(str_contains($migration, 'VALUES (1,0,1,0)'), 'Recovery is not disabled and dry-run by default.');
recovery_source_assert(str_contains($migration, 'UNIQUE KEY uq_recovery_execution_idempotency'), 'Execution idempotency key is missing.');
recovery_source_assert(str_contains($migration, 'UNIQUE KEY uq_recovery_step_idempotency'), 'Step idempotency key is missing.');
recovery_source_assert(str_contains($migration, 'UNIQUE KEY uq_recovery_approval_simulation'), 'Approval/simulation binding is missing.');
recovery_source_assert(!preg_match('/(?:message_body|email_body|crm_note|transcript|private_key|credential|entitlement_token|manifest_json)\s+(?:TEXT|LONGTEXT|JSON)/i', $migration), 'Recovery schema appears to persist private source content or secrets.');
recovery_source_assert(str_contains($fresh, 'SOURCE database/north_mountain_portal_v66l.sql;'), 'Fresh-install entrypoint does not preserve v66L ordering.');
recovery_source_assert(str_contains($fresh, 'SOURCE database/incident_response_runbooks_v66m.sql;'), 'Fresh-install entrypoint omits v66M migration.');
recovery_source_assert(str_contains($spec, 'No arbitrary execution'), 'Permanent authority boundary is absent from the specification.');

foreach ([
    'tools/harden-incident-response-v66m.py',
    '.github/workflows/harden-incident-response-v66m.yml',
    'tools/link-recovery-center-v66m.py',
    '.github/workflows/link-recovery-center-v66m.yml',
    'tools/repair-v66m-certification.py',
    '.github/workflows/repair-v66m-certification.yml',
    'tools/repair-incident-response-v66m.py',
    '.github/workflows/repair-incident-response-v66m.yml',
] as $temporaryPath) {
    recovery_source_assert(!file_exists($root . '/' . $temporaryPath), 'Temporary v66M machinery remains: ' . $temporaryPath);
}

fwrite(STDOUT, "Incident Response Runbooks v66M source regression passed.\n");

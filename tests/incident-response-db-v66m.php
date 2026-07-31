<?php
declare(strict_types=1);

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306', getenv('DB_NAME') ?: 'nmm'),
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: 'root',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

function db(): PDO
{
    global $pdo;
    return $pdo;
}

$GLOBALS['recovery_test_events'] = [];
function automation_capture_event(array $event): int
{
    $GLOBALS['recovery_test_events'][] = $event;
    return count($GLOBALS['recovery_test_events']);
}

require dirname(__DIR__) . '/portal/incident-response.php';

function recovery_test_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function recovery_test_incident(string $uuid, string $checkKey, string $family, string $status = 'critical'): int
{
    $openKey = hash('sha256', $checkKey);
    db()->prepare(
        'INSERT INTO operations_health_incidents
         (incident_uuid,open_key,check_key,metric_key,metric_family,highest_status,reason_code,opened_at,last_seen_at,
          opening_evidence_json,latest_evidence_json)
         VALUES (:incident_uuid,:open_key,:check_key,:metric_key,:metric_family,:highest_status,"threshold_critical",UTC_TIMESTAMP(),UTC_TIMESTAMP(),
          :evidence_json,:evidence_json)'
    )->execute([
        'incident_uuid' => $uuid,
        'open_key' => $openKey,
        'check_key' => $checkKey,
        'metric_key' => $checkKey,
        'metric_family' => $family,
        'highest_status' => $status,
        'evidence_json' => json_encode(['aggregate_only' => true, 'test' => 'v66m'], JSON_THROW_ON_ERROR),
    ]);
    return (int)db()->lastInsertId();
}

recovery_test_assert(recovery_schema_available(), 'Recovery schema is unavailable.');

foreach ([
    'recovery_action_receipts',
    'recovery_execution_steps',
    'recovery_executions',
    'recovery_approvals',
    'recovery_simulations',
    'recovery_recommendations',
    'recovery_runbook_versions',
    'recovery_runbooks',
] as $table) {
    db()->exec('DELETE FROM ' . $table);
}
db()->exec("DELETE FROM operations_health_incidents WHERE incident_uuid LIKE '666d%' OR check_key LIKE 'v66m.%'");
db()->exec("DELETE FROM operations_health_state WHERE check_key LIKE 'v66m.%'");
db()->exec("DELETE FROM feed_sources WHERE canonical_hash IN ('" . hash('sha256', 'v66m-feed-one') . "','" . hash('sha256', 'v66m-feed-two') . "')");
db()->exec('UPDATE recovery_settings SET enabled=1,dry_run=1,emergency_disabled=0,worker_batch_size=5,approval_expiry_minutes=60,simulation_ttl_minutes=30,execution_lease_seconds=60 WHERE id=1');
db()->exec('UPDATE operations_analytics_settings SET enabled=1,report_frequency="off" WHERE id=1');
db()->exec(
    "INSERT INTO operations_health_policies
     (check_key,enabled,comparison,attention_threshold,degraded_threshold,critical_threshold,minimum_sample_count)
     VALUES ('feed.source.error_depth',1,'greater_or_equal',1,2,3,0)
     ON DUPLICATE KEY UPDATE enabled=1,comparison='greater_or_equal',attention_threshold=1,degraded_threshold=2,critical_threshold=3,minimum_sample_count=0"
);

$catalogSync = recovery_sync_catalog(null);
recovery_test_assert((int)$catalogSync['runbooks_synced'] === 9, 'The complete permanent runbook catalog was not synchronized.');
recovery_test_assert((int)db()->query('SELECT COUNT(*) FROM recovery_runbooks')->fetchColumn() === 9, 'Runbook catalog count is incorrect.');
recovery_test_assert((int)db()->query('SELECT COUNT(*) FROM recovery_runbook_versions')->fetchColumn() === 9, 'Initial immutable runbook versions were not created.');
$repeatSync = recovery_sync_catalog(null);
recovery_test_assert((int)$repeatSync['versions_created'] === 0, 'Repeat catalog synchronization created duplicate versions.');

$privateMarker = 'PRIVATE-FEED-ERROR-MUST-NOT-BE-COPIED';
$feedInsert = db()->prepare(
    'INSERT INTO feed_sources
     (feed_url,canonical_url,canonical_hash,site_url,title,feed_format,status,failure_count,last_error,created_at,updated_at)
     VALUES (:feed_url,:canonical_url,:canonical_hash,:site_url,:title,"rss","error",3,:last_error,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
);
$feedInsert->execute([
    'feed_url' => 'https://example.invalid/v66m-one.xml',
    'canonical_url' => 'https://example.invalid/v66m-one.xml',
    'canonical_hash' => hash('sha256', 'v66m-feed-one'),
    'site_url' => 'https://example.invalid/',
    'title' => 'v66M recovery fixture one',
    'last_error' => $privateMarker,
]);

$incidentId = recovery_test_incident('666d0000-0000-4000-8000-000000000001', 'feed.source.error_depth', 'feed_reader');
recovery_refresh_recommendations();
$recommendation = db()->query(
    'SELECT recommendation.*,runbook.runbook_key FROM recovery_recommendations recommendation
     JOIN recovery_runbooks runbook ON runbook.id=recommendation.runbook_id
     WHERE recommendation.incident_id=' . $incidentId . ' LIMIT 1'
)->fetch();
recovery_test_assert($recommendation !== false && $recommendation['runbook_key'] === 'feed_source_recovery', 'Feed incident did not receive the deterministic recovery runbook.');

$simulationOne = recovery_simulate($incidentId, (int)$recommendation['runbook_id'], 1);
$simulationTwo = recovery_simulate($incidentId, (int)$recommendation['runbook_id'], 1);
recovery_test_assert((int)$simulationOne['id'] === (int)$simulationTwo['id'], 'Repeat current-version simulation created duplicate evidence.');
recovery_test_assert((string)$simulationOne['simulation_hash'] === (string)$simulationTwo['simulation_hash'], 'Simulation hash is not deterministic.');
$plan = recovery_json_decode((string)$simulationTwo['plan_json'], []);
recovery_test_assert((int)($plan['steps'][0]['candidate_count'] ?? 0) >= 1, 'Simulation did not identify the bounded feed candidate.');
recovery_test_assert(!str_contains((string)$simulationTwo['plan_json'], $privateMarker), 'Private source error leaked into the recovery plan.');

$approval = recovery_request_approval((int)$simulationTwo['id'], 1);
recovery_test_assert($approval !== null && (string)$approval['status'] === 'pending', 'Approval request was not created.');
recovery_test_assert(recovery_resolve_approval((int)$approval['id'], 'approved', 1), 'Approval could not be resolved.');
$approved = db()->query('SELECT * FROM recovery_approvals WHERE id=' . (int)$approval['id'])->fetch();
$approvedHash = (string)$approved['request_hash'];
$approvedExpiry = (string)$approved['expires_at'];
$repeatApproval = recovery_request_approval((int)$simulationTwo['id'], 2);
recovery_test_assert((string)$repeatApproval['status'] === 'approved', 'Approved request was reset by a repeat request.');
recovery_test_assert((string)$repeatApproval['request_hash'] === $approvedHash, 'Approved request hash changed.');
recovery_test_assert((string)$repeatApproval['expires_at'] === $approvedExpiry, 'Approved request expiry was extended.');
recovery_test_assert((int)$repeatApproval['requested_by_user_id'] === (int)$approved['requested_by_user_id'], 'Approved request actor changed.');

$dryRunExecution = recovery_queue_execution((int)$simulationTwo['id'], 1);
recovery_test_assert((string)$dryRunExecution['status'] === 'simulated', 'Dry-run did not create a simulated execution.');
$dryRunRepeat = recovery_queue_execution((int)$simulationTwo['id'], 1);
recovery_test_assert((int)$dryRunRepeat['id'] === (int)$dryRunExecution['id'], 'Consumed simulation did not return its idempotent execution.');
recovery_test_assert((int)db()->query('SELECT COUNT(*) FROM recovery_execution_steps WHERE execution_id=' . (int)$dryRunExecution['id'] . ' AND status="simulated"')->fetchColumn() === 1, 'Dry-run step evidence is missing.');
recovery_test_assert((int)db()->query('SELECT COUNT(*) FROM recovery_action_receipts WHERE execution_id=' . (int)$dryRunExecution['id'] . ' AND status="simulated"')->fetchColumn() === 1, 'Dry-run receipt is missing.');
recovery_test_assert((string)db()->query("SELECT status FROM feed_sources WHERE canonical_hash='" . hash('sha256', 'v66m-feed-one') . "'")->fetchColumn() === 'error', 'Dry-run mutated the canonical feed source.');

$privateCopies = 0;
foreach ([
    'recovery_simulations' => ['input_json','plan_json'],
    'recovery_execution_steps' => ['input_json','output_json'],
    'recovery_action_receipts' => ['before_json','after_json'],
] as $table => $columns) {
    foreach ($columns as $column) {
        $statement = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} LIKE :marker");
        $statement->execute(['marker' => '%' . $privateMarker . '%']);
        $privateCopies += (int)$statement->fetchColumn();
    }
}
recovery_test_assert($privateCopies === 0, 'Private source content leaked into recovery evidence.');

// Remove the dry-run evidence so the same open incident can be exercised live.
db()->exec('DELETE FROM recovery_executions WHERE id=' . (int)$dryRunExecution['id']);
db()->exec('DELETE FROM recovery_approvals WHERE simulation_id=' . (int)$simulationTwo['id']);
db()->exec('DELETE FROM recovery_simulations WHERE id=' . (int)$simulationTwo['id']);
db()->exec('UPDATE recovery_settings SET dry_run=0 WHERE id=1');

$liveSimulation = recovery_simulate($incidentId, (int)$recommendation['runbook_id'], 1);
$liveApproval = recovery_request_approval((int)$liveSimulation['id'], 1);
recovery_test_assert($liveApproval !== null && recovery_resolve_approval((int)$liveApproval['id'], 'approved', 1), 'Live recovery approval failed.');
$liveExecution = recovery_queue_execution((int)$liveSimulation['id'], 1);
recovery_test_assert((string)$liveExecution['status'] === 'queued', 'Live recovery was not queued.');

// Prove restart recovery for both the parent execution and its first step.
db()->exec(
    'UPDATE recovery_executions SET status="running",lease_token="expired-test",leased_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE),attempt_count=1
     WHERE id=' . (int)$liveExecution['id']
);
db()->exec(
    'UPDATE recovery_execution_steps SET status="running",lease_token="expired-step",leased_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE),attempt_count=1
     WHERE execution_id=' . (int)$liveExecution['id'] . ' AND step_index=0'
);
$leaseRecovery = recovery_recover_expired_leases();
recovery_test_assert((int)$leaseRecovery['executions'] === 1 && (int)$leaseRecovery['steps'] === 1, 'Expired recovery leases were not recovered.');
recovery_test_assert((string)db()->query('SELECT status FROM recovery_executions WHERE id=' . (int)$liveExecution['id'])->fetchColumn() === 'queued', 'Interrupted execution was not requeued.');

$worker = recovery_run_worker(1);
recovery_test_assert((int)$worker['processed'] === 1, 'Recovery worker did not process the queued execution.');
$completed = db()->query('SELECT * FROM recovery_executions WHERE id=' . (int)$liveExecution['id'])->fetch();
recovery_test_assert(in_array((string)$completed['status'], ['completed','partially_completed'], true), 'Bounded recovery execution did not finish.');
recovery_test_assert((string)db()->query("SELECT status FROM feed_sources WHERE canonical_hash='" . hash('sha256', 'v66m-feed-one') . "'")->fetchColumn() === 'active', 'Recovery did not requeue the failed feed source.');
recovery_test_assert((int)db()->query('SELECT COUNT(*) FROM recovery_action_receipts WHERE execution_id=' . (int)$liveExecution['id'] . ' AND status IN ("applied","no_change")')->fetchColumn() >= 1, 'Applied action receipt is missing.');
recovery_test_assert((int)db()->query('SELECT COUNT(*) FROM recovery_action_receipts WHERE execution_id=' . (int)$liveExecution['id'] . ' AND action_type="health.verification" AND status="verified"')->fetchColumn() === 1, 'Health verification receipt is missing.');
$incident = recovery_incident($incidentId);
recovery_test_assert($incident !== null && !empty($incident['recovered_at']), 'Extended Section 66L verification did not recover the feed incident.');
recovery_test_assert((string)$completed['verification_status'] === 'healthy', 'Recovered execution was not marked healthy.');

// Prove execution failures retry until the bounded ceiling, then fail permanently.
$retryIncidentId = recovery_test_incident('666d0000-0000-4000-8000-000000000002', 'v66m.worker.retry', 'operations');
$operationsRunbook = db()->query("SELECT id FROM recovery_runbooks WHERE runbook_key='operations_window_rebuild' LIMIT 1")->fetchColumn();
$retrySimulation = recovery_simulate($retryIncidentId, (int)$operationsRunbook, 1);
$retryExecution = recovery_queue_execution((int)$retrySimulation['id'], 1);
db()->exec("UPDATE recovery_execution_steps SET handler_key='forbidden.test' WHERE execution_id=" . (int)$retryExecution['id']);
$retryOne = recovery_run_worker(1);
$retryState = db()->query('SELECT * FROM recovery_executions WHERE id=' . (int)$retryExecution['id'])->fetch();
recovery_test_assert((string)$retryState['status'] === 'queued', 'First execution failure did not requeue within the attempt ceiling.');
db()->exec('UPDATE recovery_executions SET attempt_count=max_attempts-1 WHERE id=' . (int)$retryExecution['id']);
db()->exec('UPDATE recovery_execution_steps SET attempt_count=max_attempts-1,status="failed" WHERE execution_id=' . (int)$retryExecution['id']);
$retryFinal = recovery_run_worker(1);
$finalState = db()->query('SELECT * FROM recovery_executions WHERE id=' . (int)$retryExecution['id'])->fetch();
recovery_test_assert((string)$finalState['status'] === 'failed', 'Execution did not fail after exhausting bounded attempts.');
recovery_test_assert((string)$finalState['error_code'] === 'recovery_execution_failed', 'Permanent failure evidence is missing.');

// Emergency disable must prevent claims without deleting the queue.
db()->exec('UPDATE recovery_settings SET emergency_disabled=1 WHERE id=1');
$disabled = recovery_run_worker(5);
recovery_test_assert((string)$disabled['status'] === 'emergency_disabled' && (int)$disabled['processed'] === 0, 'Emergency disable did not stop worker claims.');

fwrite(STDOUT, "Incident Response Runbooks v66M database regression passed.\n");

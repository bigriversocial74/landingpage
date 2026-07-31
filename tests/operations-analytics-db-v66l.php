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

$GLOBALS['operations_test_events'] = [];
function automation_capture_event(array $event): int
{
    $GLOBALS['operations_test_events'][] = $event;
    return count($GLOBALS['operations_test_events']);
}

require dirname(__DIR__) . '/portal/operations-analytics.php';

function operations_test_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

operations_test_assert(operations_analytics_schema_available(), 'Operations analytics schema is unavailable.');

foreach ([
    'operations_worker_runs',
    'operations_report_runs',
    'operations_health_incidents',
    'operations_health_state',
    'operations_metric_snapshots',
] as $table) {
    db()->exec('DELETE FROM ' . $table);
}

db()->exec("DELETE FROM unified_inbox_workflow WHERE source_type='v66l_test'");
db()->exec("DELETE FROM automation_events WHERE source_type='v66l_test'");
db()->exec('UPDATE operations_analytics_settings SET enabled=1 WHERE id=1');

[$windowStart, $windowEnd] = operations_analytics_window_bounds('hour');
$oldCreatedAt = $windowEnd->modify('-5 hours')->format('Y-m-d H:i:s');
$privateMarker = 'PRIVATE-PAYLOAD-MUST-NOT-BE-COPIED';

$eventInsert = db()->prepare(
    'INSERT INTO automation_events
     (event_uuid,dedupe_key,event_key,source_type,source_id,category,priority,payload_json,occurred_at,status,available_at,created_at)
     VALUES
     (:event_uuid,:dedupe_key,:event_key,:source_type,:source_id,:category,:priority,:payload_json,:occurred_at,:status,:available_at,:created_at)'
);
$eventInsert->execute([
    'event_uuid' => '666c0000-0000-4000-8000-000000000001',
    'dedupe_key' => hash('sha256', 'v66l-test-event'),
    'event_key' => 'system',
    'source_type' => 'v66l_test',
    'source_id' => 66001,
    'category' => 'system',
    'priority' => 'normal',
    'payload_json' => json_encode(['private_body' => $privateMarker], JSON_THROW_ON_ERROR),
    'occurred_at' => $oldCreatedAt,
    'status' => 'pending',
    'available_at' => $oldCreatedAt,
    'created_at' => $oldCreatedAt,
]);

$inboxInsert = db()->prepare(
    'INSERT INTO unified_inbox_workflow
     (source_type,source_id,workflow_status,priority,needs_response,created_at,updated_at)
     VALUES (:source_type,:source_id,"open","high",1,:created_at,:updated_at)'
);
for ($index = 1; $index <= 12; $index++) {
    $inboxInsert->execute([
        'source_type' => 'v66l_test',
        'source_id' => 660000 + $index,
        'created_at' => $oldCreatedAt,
        'updated_at' => $oldCreatedAt,
    ]);
}

$first = operations_analytics_run('hour', true);
operations_test_assert(($first['status'] ?? '') === 'completed', 'Initial analytics run did not complete.');
operations_test_assert((int)$first['metrics_written'] === count(operations_analytics_metric_catalog()), 'Initial run did not write the complete metric catalog.');

$metricStatement = db()->prepare(
    'SELECT metric_value,sample_count FROM operations_metric_snapshots
     WHERE metric_key=:metric_key AND window_type="hour" AND window_started_at=:window_started_at LIMIT 1'
);
$metricStatement->execute(['metric_key' => 'automation.event.oldest_minutes', 'window_started_at' => $windowStart->format('Y-m-d H:i:s')]);
$automationAge = $metricStatement->fetch();
operations_test_assert($automationAge !== false && (float)$automationAge['metric_value'] >= 240, 'Oldest automation age was not aggregated correctly.');
operations_test_assert((int)$automationAge['sample_count'] >= 1, 'Automation age sample count is missing.');

$metricStatement->execute(['metric_key' => 'unified_inbox.needs_response', 'window_started_at' => $windowStart->format('Y-m-d H:i:s')]);
$needsResponse = $metricStatement->fetch();
operations_test_assert($needsResponse !== false && (int)$needsResponse['metric_value'] >= 12, 'Unified Inbox needs-response workload was not aggregated.');

$statusStatement = db()->prepare('SELECT health_status FROM operations_health_state WHERE check_key=:check_key LIMIT 1');
$statusStatement->execute(['check_key' => 'automation.event.oldest_minutes']);
operations_test_assert($statusStatement->fetchColumn() === 'critical', 'Old automation work did not open a critical health state.');
$statusStatement->execute(['check_key' => 'unified_inbox.needs_response']);
operations_test_assert($statusStatement->fetchColumn() === 'attention', 'Unified Inbox workload did not open an attention health state.');
operations_test_assert((int)db()->query('SELECT COUNT(*) FROM operations_health_incidents WHERE recovered_at IS NULL')->fetchColumn() >= 2, 'Expected health incidents were not opened.');

$privateCopies = 0;
foreach (['operations_metric_snapshots' => 'aggregate_json', 'operations_health_state' => 'evidence_json', 'operations_health_incidents' => 'latest_evidence_json'] as $table => $column) {
    $statement = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} LIKE :marker");
    $statement->execute(['marker' => '%' . $privateMarker . '%']);
    $privateCopies += (int)$statement->fetchColumn();
}
operations_test_assert($privateCopies === 0, 'Private source payload content leaked into aggregate analytics evidence.');

$second = operations_analytics_run('hour', true);
operations_test_assert(($second['status'] ?? '') === 'completed', 'Repeat analytics run did not complete.');
operations_test_assert((int)db()->query('SELECT COUNT(*) FROM operations_worker_runs')->fetchColumn() === 1, 'Repeat window created a duplicate worker-run record.');
operations_test_assert(
    (int)db()->query('SELECT COUNT(*) FROM operations_metric_snapshots')->fetchColumn() === count(operations_analytics_metric_catalog()),
    'Repeat window created duplicate snapshots.'
);
operations_test_assert((int)db()->query('SELECT COUNT(*) FROM operations_health_incidents WHERE recovered_at IS NULL')->fetchColumn() >= 2, 'Repeat evaluation lost open incident evidence.');

$eventComplete = db()->prepare(
    'UPDATE automation_events SET status="completed",completed_at=:completed_at,updated_at=:updated_at
     WHERE source_type="v66l_test"'
);
$eventComplete->execute([
    'completed_at' => $windowEnd->modify('-5 minutes')->format('Y-m-d H:i:s'),
    'updated_at' => $windowEnd->modify('-5 minutes')->format('Y-m-d H:i:s'),
]);
db()->exec("UPDATE unified_inbox_workflow SET workflow_status='resolved',needs_response=0 WHERE source_type='v66l_test'");

$recovery = operations_analytics_run('hour', true);
operations_test_assert(($recovery['status'] ?? '') === 'completed', 'Recovery analytics run did not complete.');
$statusStatement->execute(['check_key' => 'automation.event.oldest_minutes']);
operations_test_assert($statusStatement->fetchColumn() === 'healthy', 'Automation health did not recover after the queue cleared.');
$statusStatement->execute(['check_key' => 'unified_inbox.needs_response']);
operations_test_assert($statusStatement->fetchColumn() === 'healthy', 'Unified Inbox health did not recover after workload resolution.');
operations_test_assert((int)db()->query('SELECT COUNT(*) FROM operations_health_incidents WHERE recovered_at IS NULL')->fetchColumn() === 0, 'Recovered incidents remained open.');
operations_test_assert((int)db()->query('SELECT COUNT(*) FROM operations_health_incidents WHERE recovered_at IS NOT NULL')->fetchColumn() >= 2, 'Recovery evidence was not retained.');
operations_test_assert(count($GLOBALS['operations_test_events']) >= 2, 'Health transitions did not emit bounded automation events.');

fwrite(STDOUT, "Operations Analytics v66L database regression passed.\n");

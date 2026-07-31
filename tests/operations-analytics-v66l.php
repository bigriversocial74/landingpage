<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/operations-analytics.php';

function operations_source_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$source = file_get_contents($root . '/portal/operations-analytics.php');
$admin = file_get_contents($root . '/portal/operations-admin.php');
$notifications = file_get_contents($root . '/portal/notifications.php');
$worker = file_get_contents($root . '/cron/process-operations-analytics.php');
$migration = file_get_contents($root . '/database/operations_analytics_v66l.sql');
$styles = file_get_contents($root . '/assets/css/operations-analytics.css');
$spec = file_get_contents($root . '/OPERATIONS-ANALYTICS-SPEC-v66L.md');
operations_source_assert(
    is_string($source) && is_string($admin) && is_string($notifications) && is_string($worker)
        && is_string($migration) && is_string($styles) && is_string($spec),
    'Required v66L source files are unreadable.'
);

$catalog = operations_analytics_metric_catalog();
operations_source_assert(count($catalog) >= 20, 'The initial metric catalog is incomplete.');
$allowedKinds = ['gauge_count', 'oldest_minutes', 'window_count', 'window_sum'];
$forbiddenFields = ['payload_json', 'message_body', 'body_html', 'body_text', 'crm_note', 'transcript', 'private_key', 'credential', 'authorization'];
foreach ($catalog as $metricKey => $definition) {
    operations_source_assert((bool)preg_match('/^[a-z0-9_.-]{3,120}$/', (string)$metricKey), 'Metric key is not bounded.');
    operations_source_assert(in_array((string)($definition['kind'] ?? ''), $allowedKinds, true), 'Metric kind is not allowlisted.');
    operations_source_assert((bool)preg_match('/^[a-z0-9_]+$/', (string)($definition['table'] ?? '')), 'Metric source table is not allowlisted.');
    foreach ((array)($definition['columns'] ?? []) as $column) {
        operations_source_assert((bool)preg_match('/^[a-z0-9_]+$/', (string)$column), 'Metric source column is not allowlisted.');
        operations_source_assert(!in_array(strtolower((string)$column), $forbiddenFields, true), 'Private content column entered the metric catalog.');
    }
}

operations_source_assert(str_contains($worker, "PHP_SAPI !== 'cli'"), 'Analytics worker is not CLI-only.');
operations_source_assert(str_contains($worker, "['hour', 'day']"), 'Analytics worker window types are not bounded.');
operations_source_assert(str_contains($source, 'operations_analytics_column_exists'), 'Partial-schema feature detection is missing.');
operations_source_assert(str_contains($source, "'available' => false"), 'Unavailable metrics do not degrade safely.');
operations_source_assert(str_contains($source, "'collection_mode'"), 'Snapshot collection mode is not retained.');
operations_source_assert(str_contains($source, "'operations.health_transition'"), 'Bounded automation health transition is missing.');
operations_source_assert(str_contains($source, "'payload' => ["), 'Health transition payload is missing.');
operations_source_assert(!str_contains($source, 'send_allowed'), 'Analytics introduced autonomous send authority.');
operations_source_assert(!str_contains($source, 'tool_execution_allowed'), 'Analytics introduced HomeServer tool authority.');
operations_source_assert(!preg_match('/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+(?:notification_delivery_queue|activitypub_deliveries|automation_events|unified_inbox_workflow)\b/i', $source), 'Analytics mutates a canonical source table.');
operations_source_assert(!str_contains($source, ':window_end),0) AS metric_value') || str_contains($source, ':age_end'), 'Oldest-age query reuses a native PDO placeholder.');

operations_source_assert(str_contains($admin, "['view' => 'operations'"), 'Operations administrator URL is missing.');
operations_source_assert(str_contains($admin, 'operations_admin_export_csv'), 'Aggregate CSV export is missing.');
operations_source_assert(str_contains($admin, "preg_match('/^[=+\\-@]/'"), 'CSV formula-injection protection is missing.');
operations_source_assert(str_contains($admin, "'aggregate_only' => true"), 'Report evidence is not explicitly aggregate-only.');
operations_source_assert(str_contains($admin, 'operations_admin_generate_report'), 'Manual aggregate reporting is missing.');
operations_source_assert(str_contains($admin, 'operations_save_policy'), 'Deterministic policy administration is missing.');
operations_source_assert(str_contains($admin, 'operations_run_hourly'), 'Owner-controlled hourly collection is missing.');
operations_source_assert(!preg_match('/SELECT\s+.*(?:payload_json|body|note|transcript|private_key|credential)/is', $admin), 'Administrator analytics queries source private content.');
operations_source_assert(str_contains($notifications, "require_once __DIR__ . '/operations-analytics.php';"), 'Operations core is not loaded by the retained admin integration point.');
operations_source_assert(str_contains($notifications, 'operations_admin_portal_bootstrap'), 'Operations administrator bootstrap is missing.');
operations_source_assert(str_contains($notifications, 'portal/admin.php?view=operations'), 'Operations navigation entry is missing.');
operations_source_assert(str_contains($notifications, 'operations_admin_export_csv'), 'Administrator export authorization path is missing.');
operations_source_assert(str_contains($notifications, 'require_role(\'admin\')'), 'Administrator role enforcement is missing.');
operations_source_assert(str_contains($notifications, 'verify_csrf()'), 'Administrator mutation CSRF enforcement is missing.');
operations_source_assert(str_contains($styles, '.operations-shell'), 'Operations workspace styles are missing.');

foreach ([
    'operations_analytics_settings',
    'operations_metric_snapshots',
    'operations_health_policies',
    'operations_health_state',
    'operations_health_incidents',
    'operations_report_runs',
    'operations_worker_runs',
] as $table) {
    operations_source_assert(substr_count($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'Migration table contract failed for ' . $table . '.');
}
operations_source_assert(str_contains($migration, 'UNIQUE KEY uq_operations_metric_window'), 'Snapshot idempotency key is missing.');
operations_source_assert(str_contains($migration, 'UNIQUE KEY uq_operations_worker_window'), 'Worker-window idempotency key is missing.');
operations_source_assert(str_contains($migration, 'UNIQUE KEY uq_operations_incident_open'), 'Open-incident idempotency key is missing.');
operations_source_assert(!preg_match('/(?:message|email|crm|transcript|credential|private_key).*?(?:TEXT|JSON)/i', $migration), 'Migration appears to persist private source content.');
operations_source_assert(str_contains($spec, 'must not copy'), 'Permanent privacy boundary is missing from the specification.');
operations_source_assert(str_contains($spec, 'Canonical source tables remain authoritative'), 'Canonical source authority is not documented.');

fwrite(STDOUT, "Operations Analytics v66L source regression passed.\n");

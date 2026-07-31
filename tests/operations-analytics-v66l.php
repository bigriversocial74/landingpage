<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/operations-analytics.php';
require dirname(__DIR__) . '/portal/operations-analytics-extensions.php';

function operations_source_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$source = file_get_contents($root . '/portal/operations-analytics.php');
$extended = file_get_contents($root . '/portal/operations-analytics-extensions.php');
$admin = file_get_contents($root . '/portal/operations-admin.php');
$notifications = file_get_contents($root . '/portal/notifications.php');
$worker = file_get_contents($root . '/cron/process-operations-analytics.php');
$migration = file_get_contents($root . '/database/operations_analytics_v66l.sql');
$freshInstall = file_get_contents($root . '/database/north_mountain_portal_v66l.sql');
$styles = file_get_contents($root . '/assets/css/operations-analytics.css');
$extendedStyles = file_get_contents($root . '/assets/css/operations-analytics-extended.css');
$spec = file_get_contents($root . '/OPERATIONS-ANALYTICS-SPEC-v66L.md');
$setup = file_get_contents($root . '/OPERATIONS-ANALYTICS-SETUP-v66L.md');
operations_source_assert(
    is_string($source) && is_string($extended) && is_string($admin) && is_string($notifications)
        && is_string($worker) && is_string($migration) && is_string($freshInstall)
        && is_string($styles) && is_string($extendedStyles) && is_string($spec) && is_string($setup),
    'Required v66L source files are unreadable.'
);

$coreCatalog = operations_analytics_metric_catalog();
$extendedCatalog = operations_analytics_extended_catalog();
operations_source_assert(count($coreCatalog) >= 20, 'The core metric catalog is incomplete.');
operations_source_assert(count($extendedCatalog) >= 20, 'The extended metric catalog is incomplete.');
$allowedKinds = ['gauge_count','oldest_minutes','window_count','window_sum','ratio_percent','latest_age_minutes','status_value'];
$forbiddenFields = ['payload_json','message_body','body_html','body_text','crm_note','transcript','private_key','credential','authorization','manifest_json','metadata_json'];
foreach ([$coreCatalog, $extendedCatalog] as $catalog) {
    foreach ($catalog as $metricKey => $definition) {
        operations_source_assert((bool)preg_match('/^[a-z0-9_.-]{3,120}$/', (string)$metricKey), 'Metric key is not bounded.');
        operations_source_assert(in_array((string)($definition['kind'] ?? ''), $allowedKinds, true), 'Metric kind is not allowlisted.');
        operations_source_assert((bool)preg_match('/^[a-z0-9_]+$/', (string)($definition['table'] ?? '')), 'Metric source table is not allowlisted.');
        foreach ((array)($definition['columns'] ?? []) as $column) {
            operations_source_assert((bool)preg_match('/^[a-z0-9_]+$/', (string)$column), 'Metric source column is not allowlisted.');
            operations_source_assert(!in_array(strtolower((string)$column), $forbiddenFields, true), 'Private content column entered the metric catalog.');
        }
    }
}

operations_source_assert(str_contains($worker, "PHP_SAPI !== 'cli'"), 'Analytics worker is not CLI-only.');
operations_source_assert(str_contains($worker, "['hour', 'day']"), 'Analytics worker window types are not bounded.');
operations_source_assert(str_contains($worker, 'operations_analytics_run_extended'), 'Analytics worker does not collect the complete metric catalog.');
operations_source_assert(str_contains($worker, "require_once dirname(__DIR__) . '/portal/notifications.php';"), 'Scheduled reports do not use Notification Delivery.');
operations_source_assert(str_contains($source, 'operations_analytics_column_exists'), 'Partial-schema feature detection is missing.');
operations_source_assert(str_contains($source, "'available' => false"), 'Unavailable metrics do not degrade safely.');
operations_source_assert(str_contains($source, "'collection_mode'"), 'Snapshot collection mode is not retained.');
operations_source_assert(str_contains($source, "'operations.health_transition'"), 'Bounded automation health transition is missing.');
operations_source_assert(!str_contains($source, 'send_allowed'), 'Analytics introduced autonomous send authority.');
operations_source_assert(!str_contains($source, 'tool_execution_allowed'), 'Analytics introduced HomeServer tool authority.');
operations_source_assert(!preg_match('/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+(?:notification_delivery_queue|activitypub_deliveries|automation_events|unified_inbox_workflow)\b/i', $source), 'Analytics mutates a canonical source table.');
operations_source_assert(!str_contains($source, ':window_end),0) AS metric_value') || str_contains($source, ':age_end'), 'Oldest-age query reuses a native PDO placeholder.');

operations_source_assert(str_contains($extended, 'ratio_percent'), 'Failure-ratio aggregation is missing.');
operations_source_assert(str_contains($extended, 'latest_age_minutes'), 'Worker staleness aggregation is missing.');
operations_source_assert(str_contains($extended, 'status_value'), 'License lifecycle health aggregation is missing.');
operations_source_assert(str_contains($extended, "'feed_reader'"), 'Feed Reader health is missing.');
operations_source_assert(str_contains($extended, "'syndication'"), 'Syndication health is missing.');
operations_source_assert(str_contains($extended, "'vp3_license'"), 'VP3 license health is missing.');
operations_source_assert(str_contains($extended, "'vp3_updates'"), 'VP3 update health is missing.');
operations_source_assert(str_contains($extended, 'operations_reporting_run_if_due'), 'Scheduled report processing is missing.');
operations_source_assert(str_contains($extended, 'notification_create_for_role'), 'Scheduled reports bypass Notification Delivery.');
operations_source_assert(str_contains($extended, "'aggregate_only' => true"), 'Scheduled report evidence is not aggregate-only.');
operations_source_assert(str_contains($extended, "['24h','7d','30d']"), 'Required trend windows are missing.');
operations_source_assert(str_contains($extended, 'operations_extended_sparkline'), 'Trend visualization is missing.');
operations_source_assert(!preg_match('/\b(?:manifest_json|metadata_json|entitlement_json|summary|content_html|source_excerpt)\b/i', implode(',', array_map(static fn(array $definition): string => implode(',', (array)($definition['columns'] ?? [])), $extendedCatalog))), 'Extended metrics include private or large source payload columns.');

operations_source_assert(str_contains($admin, "['view' => 'operations'"), 'Operations administrator URL is missing.');
operations_source_assert(str_contains($admin, 'operations_admin_export_csv'), 'Aggregate CSV export is missing.');
operations_source_assert(str_contains($admin, "preg_match('/^[=+\\-@]/'"), 'CSV formula-injection protection is missing.');
operations_source_assert(str_contains($admin, "'aggregate_only' => true"), 'Manual report evidence is not explicitly aggregate-only.');
operations_source_assert(str_contains($admin, 'operations_admin_generate_report'), 'Manual aggregate reporting is missing.');
operations_source_assert(str_contains($admin, 'operations_save_policy'), 'Deterministic policy administration is missing.');
operations_source_assert(!preg_match('/\b(?:notification_delivery_queue|activitypub_deliveries|automation_events|unified_inbox_workflow)\b/i', $admin), 'Administrator workspace queries canonical source tables instead of aggregate evidence.');
operations_source_assert(str_contains($notifications, "require_once __DIR__ . '/operations-analytics-extensions.php';"), 'Extended analytics is not loaded by the retained admin integration point.');
operations_source_assert(str_contains($notifications, 'operations_admin_portal_bootstrap'), 'Operations administrator bootstrap is missing.');
operations_source_assert(str_contains($notifications, 'portal/admin.php?view=operations'), 'Operations navigation entry is missing.');
operations_source_assert(str_contains($notifications, 'operations_extended_handle_admin_action'), 'Extended administrator actions are not routed.');
operations_source_assert(str_contains($notifications, 'operations_extended_decorate'), 'Trend and schedule workspace extension is not rendered.');
operations_source_assert(str_contains($notifications, 'require_role(\'admin\')'), 'Administrator role enforcement is missing.');
operations_source_assert(str_contains($notifications, 'verify_csrf()'), 'Administrator mutation CSRF enforcement is missing.');
operations_source_assert(str_contains($styles, '.operations-shell'), 'Operations workspace styles are missing.');
operations_source_assert(str_contains($extendedStyles, '.operations-trend-grid'), 'Operations trend styles are missing.');

foreach (['operations_analytics_settings','operations_metric_snapshots','operations_health_policies','operations_health_state','operations_health_incidents','operations_report_runs','operations_worker_runs'] as $table) {
    operations_source_assert(substr_count($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'Migration table contract failed for ' . $table . '.');
}
foreach (['report_frequency','report_hour_utc','report_weekday_utc','last_scheduled_report_end_at'] as $column) operations_source_assert(str_contains($migration, $column), 'Scheduled reporting schema is missing ' . $column . '.');
operations_source_assert(str_contains($migration, 'UNIQUE KEY uq_operations_metric_window'), 'Snapshot idempotency key is missing.');
operations_source_assert(str_contains($migration, 'UNIQUE KEY uq_operations_worker_window'), 'Worker-window idempotency key is missing.');
operations_source_assert(str_contains($migration, 'UNIQUE KEY uq_operations_incident_open'), 'Open-incident idempotency key is missing.');
operations_source_assert(!preg_match('/(?:message|email|crm|transcript|credential|private_key).*?(?:TEXT|JSON)/i', $migration), 'Migration appears to persist private source content.');

$orderedSources = [
    'SOURCE database/north_mountain_portal.sql;',
    'SOURCE database/vp3_pod_licensing_v64.sql;',
    'SOURCE database/vp3_pod_managed_updates_v65.sql;',
    'SOURCE database/operations_analytics_v66l.sql;',
];
$lastPosition = -1;
foreach ($orderedSources as $sourceLine) {
    $position = strpos($freshInstall, $sourceLine);
    operations_source_assert($position !== false && $position > $lastPosition, 'Fresh-install order is incomplete or unsafe: ' . $sourceLine);
    $lastPosition = $position;
}
operations_source_assert(str_contains($setup, 'database/north_mountain_portal_v66l.sql'), 'Deployment guide does not use the certified fresh-install entrypoint.');
operations_source_assert(str_contains($setup, 'preserving the live `config.php`'), 'Deployment guide does not preserve live configuration.');
operations_source_assert(str_contains($setup, 'complete `storage/` directory'), 'Deployment guide does not preserve storage.');
operations_source_assert(str_contains($setup, 'cron/process-operations-analytics.php hour'), 'Hourly worker deployment is missing.');
operations_source_assert(str_contains($setup, 'cron/process-operations-analytics.php day'), 'Daily worker deployment is missing.');
operations_source_assert(str_contains($setup, 'Notification Delivery'), 'Scheduled-report delivery authority is not documented.');
operations_source_assert(str_contains($setup, 'DROP TABLE IF EXISTS operations_worker_runs;'), 'Rollback order is missing.');
operations_source_assert(str_contains($spec, 'must not copy'), 'Permanent privacy boundary is missing from the specification.');
operations_source_assert(str_contains($spec, 'Canonical source tables remain authoritative'), 'Canonical source authority is not documented.');

fwrite(STDOUT, "Operations Analytics v66L source regression passed.\n");

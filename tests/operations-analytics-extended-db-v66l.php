<?php
declare(strict_types=1);

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306', getenv('DB_NAME') ?: 'nmm'),
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: 'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

function db(): PDO
{
    global $pdo;
    return $pdo;
}

$GLOBALS['operations_extended_events'] = [];
$GLOBALS['operations_extended_notifications'] = [];
function automation_capture_event(array $event): int
{
    $GLOBALS['operations_extended_events'][] = $event;
    return count($GLOBALS['operations_extended_events']);
}
function notification_create_for_role(string $role, string $category, string $title, ?string $body = null, ?string $linkUrl = null, ?string $entityType = null, ?int $entityId = null, string $priority = 'normal'): void
{
    $GLOBALS['operations_extended_notifications'][] = compact('role','category','title','body','linkUrl','entityType','entityId','priority');
}
function operations_admin_label(string $value): string
{
    return ucwords(str_replace(['.','_','-'], ' ', $value));
}

require dirname(__DIR__) . '/portal/operations-analytics.php';
require dirname(__DIR__) . '/portal/operations-analytics-extensions.php';

function operations_extended_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

foreach (['operations_worker_runs','operations_report_runs','operations_health_incidents','operations_health_state','operations_metric_snapshots'] as $table) db()->exec('DELETE FROM ' . $table);
db()->exec("DELETE FROM syndication_websub_deliveries WHERE topic_url LIKE 'https://v66l.test/%'");
db()->exec("DELETE FROM feed_refresh_runs WHERE source_id IN (SELECT id FROM feed_sources WHERE canonical_hash='" . hash('sha256', 'v66l-feed') . "')");
db()->exec("DELETE FROM feed_sources WHERE canonical_hash='" . hash('sha256', 'v66l-feed') . "'");
db()->exec("DELETE FROM vp3_update_jobs WHERE job_uuid LIKE '666c-%'");
db()->exec("UPDATE operations_analytics_settings SET enabled=1,report_frequency='off',last_scheduled_report_end_at=NULL WHERE id=1");

[$hourStart, $hourEnd] = operations_analytics_window_bounds('hour');
$old = $hourEnd->modify('-8 hours')->format('Y-m-d H:i:s');
$recent = $hourEnd->modify('-10 minutes')->format('Y-m-d H:i:s');
$privateMarker = 'EXTENDED-PRIVATE-CONTENT-MUST-NOT-COPY';

$websub = db()->prepare(
    'INSERT INTO syndication_websub_deliveries
     (topic_url,hub_url,event_type,payload_sha256,status,attempt_count,created_at,updated_at)
     VALUES (:topic_url,:hub_url,"publish",:payload_sha256,"pending",4,:created_at,:updated_at)'
);
$websub->execute([
    'topic_url' => 'https://v66l.test/' . $privateMarker,
    'hub_url' => 'https://hub.v66l.test/',
    'payload_sha256' => hash('sha256', 'v66l-websub'),
    'created_at' => $old,
    'updated_at' => $old,
]);

$feed = db()->prepare(
    'INSERT INTO feed_sources
     (feed_url,canonical_url,canonical_hash,title,feed_format,status,last_success_at,failure_count,created_at,updated_at)
     VALUES (:feed_url,:canonical_url,:canonical_hash,:title,"rss","error",:last_success_at,8,:created_at,:updated_at)'
);
$feed->execute([
    'feed_url' => 'https://feed.v66l.test/rss',
    'canonical_url' => 'https://feed.v66l.test/rss',
    'canonical_hash' => hash('sha256', 'v66l-feed'),
    'title' => $privateMarker,
    'last_success_at' => $old,
    'created_at' => $old,
    'updated_at' => $old,
]);
$feedId = (int)db()->lastInsertId();
$refresh = db()->prepare(
    'INSERT INTO feed_refresh_runs
     (source_id,trigger_type,status,http_status,item_count,new_item_count,started_at,completed_at)
     VALUES (:source_id,"scheduled",:status,:http_status,0,0,:started_at,:completed_at)'
);
for ($index = 0; $index < 6; $index++) {
    $refresh->execute([
        'source_id' => $feedId,
        'status' => $index < 4 ? 'failed' : 'success',
        'http_status' => $index < 4 ? 500 : 200,
        'started_at' => $recent,
        'completed_at' => $recent,
    ]);
}

db()->exec("UPDATE vp3_license_configuration SET license_status='expired',last_successful_validation_at='" . $old . "' WHERE id=1");
$job = db()->prepare(
    'INSERT INTO vp3_update_jobs
     (job_uuid,requested_by_type,operation,status,created_at,updated_at,completed_at)
     VALUES (:job_uuid,"system",:operation,:status,:created_at,:updated_at,:completed_at)'
);
$job->execute(['job_uuid' => '666c-0000-4000-8000-000000000001', 'operation' => 'check', 'status' => 'queued', 'created_at' => $old, 'updated_at' => $old, 'completed_at' => null]);
$job->execute(['job_uuid' => '666c-0000-4000-8000-000000000002', 'operation' => 'install', 'status' => 'failed', 'created_at' => $recent, 'updated_at' => $recent, 'completed_at' => $recent]);

$result = operations_analytics_run_extended('hour', true);
$expectedMetrics = count(operations_analytics_metric_catalog()) + count(operations_analytics_extended_catalog());
operations_extended_assert(($result['status'] ?? '') === 'completed', 'Extended operations run did not complete.');
operations_extended_assert((int)$result['metrics_written'] === $expectedMetrics, 'Extended metric catalog was not fully collected.');
operations_extended_assert((int)db()->query('SELECT COUNT(*) FROM operations_metric_snapshots')->fetchColumn() === $expectedMetrics, 'Extended snapshots were not idempotently persisted.');

$metric = db()->prepare('SELECT metric_value,sample_count FROM operations_metric_snapshots WHERE metric_key=:metric_key AND window_type="hour" LIMIT 1');
foreach ([
    'syndication.websub.oldest_minutes' => 240,
    'feed.refresh.failure_percent' => 50,
    'feed.source.error_depth' => 1,
    'vp3.license.status_risk' => 3,
    'vp3.update.job_depth' => 1,
] as $metricKey => $minimum) {
    $metric->execute(['metric_key' => $metricKey]);
    $row = $metric->fetch();
    operations_extended_assert($row !== false && (float)$row['metric_value'] >= $minimum, 'Extended metric failed: ' . $metricKey);
}

$status = db()->prepare('SELECT health_status FROM operations_health_state WHERE check_key=:check_key LIMIT 1');
$status->execute(['check_key' => 'syndication.websub.oldest_minutes']);
operations_extended_assert($status->fetchColumn() === 'critical', 'WebSub queue age did not become critical.');
$status->execute(['check_key' => 'feed.refresh.failure_percent']);
operations_extended_assert($status->fetchColumn() === 'critical', 'Feed failure ratio did not become critical.');
$status->execute(['check_key' => 'vp3.license.status_risk']);
operations_extended_assert($status->fetchColumn() === 'critical', 'Expired VP3 license state did not become critical.');

$privateCopies = 0;
foreach (['operations_metric_snapshots' => 'aggregate_json','operations_health_state' => 'evidence_json','operations_health_incidents' => 'latest_evidence_json'] as $table => $column) {
    $statement = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} LIKE :marker");
    $statement->execute(['marker' => '%' . $privateMarker . '%']);
    $privateCopies += (int)$statement->fetchColumn();
}
operations_extended_assert($privateCopies === 0, 'Private source content leaked into extended analytics evidence.');

$repeat = operations_analytics_run_extended('hour', true);
operations_extended_assert((int)$repeat['metrics_written'] === $expectedMetrics, 'Repeat extended run did not rebuild the complete catalog.');
operations_extended_assert((int)db()->query('SELECT COUNT(*) FROM operations_metric_snapshots')->fetchColumn() === $expectedMetrics, 'Repeat extended run duplicated snapshots.');
operations_extended_assert((int)db()->query('SELECT COUNT(*) FROM operations_worker_runs')->fetchColumn() === 1, 'Repeat extended run duplicated worker evidence.');

$daily = operations_analytics_run_extended('day', true);
operations_extended_assert(($daily['status'] ?? '') === 'completed', 'Daily extended collection did not complete.');
[$dayStart, $dayEnd] = operations_analytics_window_bounds('day');
$reportId = operations_reporting_generate('daily', $dayStart, $dayEnd, null);
operations_extended_assert($reportId > 0, 'Scheduled aggregate report was not generated.');
operations_extended_assert(count($GLOBALS['operations_extended_notifications']) === 1, 'Scheduled report did not use the Notification Delivery entry path.');
$report = db()->prepare('SELECT summary_json FROM operations_report_runs WHERE id=:id LIMIT 1');
$report->execute(['id' => $reportId]);
$summary = (string)$report->fetchColumn();
operations_extended_assert(str_contains($summary, '"aggregate_only":true'), 'Scheduled report is not aggregate-only.');
operations_extended_assert(!str_contains($summary, $privateMarker), 'Private source content leaked into the scheduled report.');

$scheduleWindow = operations_reporting_due_window(
    ['frequency' => 'weekly','hour_utc' => 15,'weekday_utc' => 1,'last_end_at' => null],
    new DateTimeImmutable('2026-08-03 16:00:00', new DateTimeZone('UTC'))
);
operations_extended_assert($scheduleWindow !== null && $scheduleWindow[0]->format('Y-m-d') === '2026-07-27' && $scheduleWindow[1]->format('Y-m-d') === '2026-08-03', 'Weekly report scheduling is not deterministic.');

fwrite(STDOUT, "Operations Analytics v66L extended database regression passed.\n");

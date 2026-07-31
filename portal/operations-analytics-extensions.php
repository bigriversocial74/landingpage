<?php
declare(strict_types=1);

require_once __DIR__ . '/operations-analytics.php';

function operations_analytics_extended_catalog(): array
{
    return [
        'syndication.websub.depth' => [
            'family' => 'syndication', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'syndication_websub_deliveries', 'where' => "status IN ('pending','delivering')", 'columns' => ['status','created_at'], 'created_column' => 'created_at',
        ],
        'syndication.websub.oldest_minutes' => [
            'family' => 'syndication', 'unit' => 'minutes', 'kind' => 'oldest_minutes',
            'table' => 'syndication_websub_deliveries', 'where' => "status IN ('pending','delivering')", 'columns' => ['status','created_at'], 'time_column' => 'created_at',
        ],
        'syndication.websub.delivered' => [
            'family' => 'syndication', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'syndication_websub_deliveries', 'where' => "status='delivered'", 'columns' => ['status','delivered_at'], 'time_column' => 'delivered_at',
        ],
        'syndication.websub.failed' => [
            'family' => 'syndication', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'syndication_websub_deliveries', 'where' => "status='failed'", 'columns' => ['status','updated_at'], 'time_column' => 'updated_at',
        ],
        'syndication.websub.failure_percent' => [
            'family' => 'syndication', 'unit' => 'percent', 'kind' => 'ratio_percent',
            'table' => 'syndication_websub_deliveries', 'columns' => ['status','updated_at'], 'time_column' => 'updated_at',
            'denominator_where' => "status IN ('delivered','failed')", 'numerator_where' => "status='failed'",
        ],
        'feed.refresh.success' => [
            'family' => 'feed_reader', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'feed_refresh_runs', 'where' => "status IN ('success','not_modified')", 'columns' => ['status','completed_at'], 'time_column' => 'completed_at',
        ],
        'feed.refresh.failed' => [
            'family' => 'feed_reader', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'feed_refresh_runs', 'where' => "status='failed'", 'columns' => ['status','completed_at'], 'time_column' => 'completed_at',
        ],
        'feed.refresh.failure_percent' => [
            'family' => 'feed_reader', 'unit' => 'percent', 'kind' => 'ratio_percent',
            'table' => 'feed_refresh_runs', 'columns' => ['status','completed_at'], 'time_column' => 'completed_at',
            'denominator_where' => "status IN ('success','not_modified','failed')", 'numerator_where' => "status='failed'",
        ],
        'feed.source.error_depth' => [
            'family' => 'feed_reader', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'feed_sources', 'where' => "status='error'", 'columns' => ['status','created_at'], 'created_column' => 'created_at',
        ],
        'feed.source.oldest_success_minutes' => [
            'family' => 'feed_reader', 'unit' => 'minutes', 'kind' => 'oldest_minutes',
            'table' => 'feed_sources', 'where' => "status='active' AND last_success_at IS NOT NULL", 'columns' => ['status','last_success_at'], 'time_column' => 'last_success_at',
        ],
        'notification.delivery.failure_percent' => [
            'family' => 'notification_delivery', 'unit' => 'percent', 'kind' => 'ratio_percent',
            'table' => 'notification_delivery_queue', 'columns' => ['status','updated_at'], 'time_column' => 'updated_at',
            'denominator_where' => "status IN ('sent','failed')", 'numerator_where' => "status='failed'",
        ],
        'activitypub.delivery.failure_percent' => [
            'family' => 'activitypub', 'unit' => 'percent', 'kind' => 'ratio_percent',
            'table' => 'activitypub_deliveries', 'columns' => ['status','updated_at'], 'time_column' => 'updated_at',
            'denominator_where' => "status IN ('delivered','failed')", 'numerator_where' => "status='failed'",
        ],
        'automation.execution.failure_percent' => [
            'family' => 'automation', 'unit' => 'percent', 'kind' => 'ratio_percent',
            'table' => 'automation_executions', 'columns' => ['status','created_at'], 'time_column' => 'created_at',
            'denominator_where' => "status IN ('executed','partially_executed','failed')", 'numerator_where' => "status='failed'",
        ],
        'vp3.license.status_risk' => [
            'family' => 'vp3_license', 'unit' => 'risk', 'kind' => 'status_value',
            'table' => 'vp3_license_configuration', 'columns' => ['license_status','updated_at'], 'status_column' => 'license_status', 'order_column' => 'updated_at',
            'status_map' => ['active' => 0, 'grace' => 1, 'unknown' => 2, 'suspended' => 2, 'expired' => 3, 'terminated' => 4],
        ],
        'vp3.license.last_success_minutes' => [
            'family' => 'vp3_license', 'unit' => 'minutes', 'kind' => 'latest_age_minutes',
            'table' => 'vp3_license_configuration', 'where' => 'last_successful_validation_at IS NOT NULL',
            'columns' => ['last_successful_validation_at'], 'time_column' => 'last_successful_validation_at',
        ],
        'vp3.license.validation_errors' => [
            'family' => 'vp3_license', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'vp3_license_validation_receipts', 'where' => "outcome IN ('warning','denied','error')", 'columns' => ['outcome','created_at'], 'time_column' => 'created_at',
        ],
        'vp3.update.job_depth' => [
            'family' => 'vp3_updates', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'vp3_update_jobs', 'where' => "status IN ('queued','checking','downloading','verifying','staging','backing_up','installing','migrating','health_check','rolling_back')", 'columns' => ['status','created_at'], 'created_column' => 'created_at',
        ],
        'vp3.update.failed' => [
            'family' => 'vp3_updates', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'vp3_update_jobs', 'where' => "status='failed'", 'columns' => ['status','completed_at'], 'time_column' => 'completed_at',
        ],
        'vp3.update.completed' => [
            'family' => 'vp3_updates', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'vp3_update_jobs', 'where' => "status IN ('completed','rolled_back')", 'columns' => ['status','completed_at'], 'time_column' => 'completed_at',
        ],
        'vp3.update.last_success_minutes' => [
            'family' => 'vp3_updates', 'unit' => 'minutes', 'kind' => 'latest_age_minutes',
            'table' => 'vp3_update_jobs', 'where' => "status IN ('completed','rolled_back') AND completed_at IS NOT NULL", 'columns' => ['status','completed_at'], 'time_column' => 'completed_at',
        ],
        'operations.collector.last_success_minutes' => [
            'family' => 'operations', 'unit' => 'minutes', 'kind' => 'latest_age_minutes',
            'table' => 'operations_worker_runs', 'where' => "status='completed' AND completed_at IS NOT NULL", 'columns' => ['status','completed_at'], 'time_column' => 'completed_at',
        ],
    ];
}

function operations_analytics_collect_extended_metric(array $definition, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $kind = (string)$definition['kind'];
    if (in_array($kind, ['gauge_count','oldest_minutes','window_count','window_sum'], true)) {
        return operations_analytics_collect_metric($definition, $start, $end);
    }
    if (!operations_analytics_source_available($definition)) return ['available' => false, 'value' => 0.0, 'sample_count' => 0];
    $table = operations_analytics_valid_identifier((string)$definition['table']);
    $windowStart = $start->format('Y-m-d H:i:s');
    $windowEnd = $end->format('Y-m-d H:i:s');

    if ($kind === 'ratio_percent') {
        $timeColumn = operations_analytics_valid_identifier((string)$definition['time_column']);
        $denominatorWhere = trim((string)$definition['denominator_where']);
        $numeratorWhere = trim((string)$definition['numerator_where']);
        $statement = db()->prepare(
            "SELECT COUNT(*) AS sample_count,
                    COALESCE(SUM(CASE WHEN {$numeratorWhere} THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*),0),0) AS metric_value
             FROM {$table}
             WHERE {$denominatorWhere} AND {$timeColumn}>=:window_start AND {$timeColumn}<:window_end"
        );
        $statement->execute(['window_start' => $windowStart, 'window_end' => $windowEnd]);
        $row = $statement->fetch() ?: [];
        return ['available' => true, 'value' => (float)($row['metric_value'] ?? 0), 'sample_count' => (int)($row['sample_count'] ?? 0)];
    }

    if ($kind === 'latest_age_minutes') {
        $timeColumn = operations_analytics_valid_identifier((string)$definition['time_column']);
        $where = trim((string)($definition['where'] ?? '1=1')) ?: '1=1';
        $statement = db()->prepare(
            "SELECT COUNT({$timeColumn}) AS sample_count,
                    COALESCE(TIMESTAMPDIFF(MINUTE,MAX({$timeColumn}),:age_end),0) AS metric_value
             FROM {$table} WHERE {$where} AND {$timeColumn}<:filter_end"
        );
        $statement->execute(['age_end' => $windowEnd, 'filter_end' => $windowEnd]);
        $row = $statement->fetch() ?: [];
        return ['available' => (int)($row['sample_count'] ?? 0) > 0, 'value' => max(0.0, (float)($row['metric_value'] ?? 0)), 'sample_count' => (int)($row['sample_count'] ?? 0)];
    }

    if ($kind === 'status_value') {
        $statusColumn = operations_analytics_valid_identifier((string)$definition['status_column']);
        $orderColumn = operations_analytics_valid_identifier((string)$definition['order_column']);
        $statement = db()->query("SELECT {$statusColumn} AS source_status FROM {$table} ORDER BY {$orderColumn} DESC LIMIT 1");
        $status = (string)($statement->fetchColumn() ?: '');
        $map = (array)$definition['status_map'];
        return ['available' => $status !== '' && array_key_exists($status, $map), 'value' => (float)($map[$status] ?? 0), 'sample_count' => $status === '' ? 0 : 1];
    }

    throw new RuntimeException('Unsupported extended analytics metric kind.');
}

function operations_analytics_apply_extended_health(string $metricKey, array $definition, array $policy, string $windowType, DateTimeImmutable $start): array
{
    $snapshotStatement = db()->prepare(
        'SELECT * FROM operations_metric_snapshots
         WHERE metric_key=:metric_key AND window_type=:window_type AND window_started_at=:window_started_at LIMIT 1'
    );
    $snapshotStatement->execute(['metric_key' => $metricKey, 'window_type' => $windowType, 'window_started_at' => $start->format('Y-m-d H:i:s')]);
    $snapshot = $snapshotStatement->fetch();
    $value = $snapshot ? (float)$snapshot['metric_value'] : null;
    $sampleCount = $snapshot ? (int)$snapshot['sample_count'] : 0;
    if ($value === null || $sampleCount < (int)$policy['minimum_sample_count']) {
        $status = 'unknown';
        $threshold = null;
        $reasonCode = 'metric_unavailable';
    } else {
        [$status, $threshold] = operations_analytics_policy_status($value, $policy);
        $reasonCode = $status === 'healthy' ? 'within_policy' : 'threshold_' . $status;
    }

    $existingStatement = db()->prepare('SELECT * FROM operations_health_state WHERE check_key=:check_key LIMIT 1');
    $existingStatement->execute(['check_key' => $metricKey]);
    $existing = $existingStatement->fetch() ?: null;
    $previousStatus = $existing ? (string)$existing['health_status'] : 'unknown';
    $changed = $previousStatus !== $status;
    $evaluatedAt = gmdate('Y-m-d H:i:s');
    $firstUnhealthyAt = in_array($status, ['attention','degraded','critical'], true)
        ? (($existing && !empty($existing['first_unhealthy_at'])) ? $existing['first_unhealthy_at'] : $evaluatedAt)
        : null;
    $recoveredAt = $status === 'healthy' && in_array($previousStatus, ['attention','degraded','critical'], true)
        ? $evaluatedAt
        : ($existing['recovered_at'] ?? null);
    $evidence = operations_analytics_json_encode([
        'window_type' => $windowType,
        'window_started_at' => $start->format('Y-m-d H:i:s'),
        'sample_count' => $sampleCount,
        'unit' => (string)$definition['unit'],
        'observed_value' => $value,
        'threshold_value' => $threshold,
        'aggregate_only' => true,
    ]);

    db()->prepare(
        'INSERT INTO operations_health_state
         (check_key,metric_key,metric_family,health_status,reason_code,observed_value,threshold_value,evidence_json,
          evaluated_at,last_changed_at,first_unhealthy_at,recovered_at)
         VALUES
         (:check_key,:metric_key,:metric_family,:health_status,:reason_code,:observed_value,:threshold_value,:evidence_json,
          :evaluated_at,:last_changed_at,:first_unhealthy_at,:recovered_at)
         ON DUPLICATE KEY UPDATE metric_key=VALUES(metric_key),metric_family=VALUES(metric_family),reason_code=VALUES(reason_code),
          observed_value=VALUES(observed_value),threshold_value=VALUES(threshold_value),evidence_json=VALUES(evidence_json),
          evaluated_at=VALUES(evaluated_at),last_changed_at=VALUES(last_changed_at),first_unhealthy_at=VALUES(first_unhealthy_at),
          recovered_at=VALUES(recovered_at),health_status=VALUES(health_status)'
    )->execute([
        'check_key' => $metricKey,
        'metric_key' => $metricKey,
        'metric_family' => (string)$definition['family'],
        'health_status' => $status,
        'reason_code' => $reasonCode,
        'observed_value' => $value === null ? null : number_format($value, 6, '.', ''),
        'threshold_value' => $threshold === null ? null : number_format((float)$threshold, 6, '.', ''),
        'evidence_json' => $evidence,
        'evaluated_at' => $evaluatedAt,
        'last_changed_at' => $changed ? $evaluatedAt : ($existing['last_changed_at'] ?? $evaluatedAt),
        'first_unhealthy_at' => $firstUnhealthyAt,
        'recovered_at' => $recoveredAt,
    ]);

    $opened = 0;
    $recovered = 0;
    $openKey = hash('sha256', $metricKey);
    if (in_array($status, ['attention','degraded','critical'], true)) {
        $incidentStatement = db()->prepare('SELECT * FROM operations_health_incidents WHERE open_key=:open_key LIMIT 1');
        $incidentStatement->execute(['open_key' => $openKey]);
        $incident = $incidentStatement->fetch();
        if (!$incident) {
            db()->prepare(
                'INSERT INTO operations_health_incidents
                 (incident_uuid,open_key,check_key,metric_key,metric_family,highest_status,reason_code,opened_at,last_seen_at,
                  opening_evidence_json,latest_evidence_json)
                 VALUES (:incident_uuid,:open_key,:check_key,:metric_key,:metric_family,:highest_status,:reason_code,
                  :opened_at,:last_seen_at,:opening_evidence_json,:latest_evidence_json)'
            )->execute([
                'incident_uuid' => operations_analytics_uuid(), 'open_key' => $openKey, 'check_key' => $metricKey,
                'metric_key' => $metricKey, 'metric_family' => (string)$definition['family'], 'highest_status' => $status,
                'reason_code' => $reasonCode, 'opened_at' => $evaluatedAt, 'last_seen_at' => $evaluatedAt,
                'opening_evidence_json' => $evidence, 'latest_evidence_json' => $evidence,
            ]);
            $opened = 1;
        } else {
            $highest = operations_analytics_health_rank($status) > operations_analytics_health_rank((string)$incident['highest_status'])
                ? $status : (string)$incident['highest_status'];
            db()->prepare(
                'UPDATE operations_health_incidents SET highest_status=:highest_status,reason_code=:reason_code,
                 last_seen_at=:last_seen_at,occurrence_count=occurrence_count+1,latest_evidence_json=:latest_evidence_json WHERE id=:id'
            )->execute(['highest_status' => $highest, 'reason_code' => $reasonCode, 'last_seen_at' => $evaluatedAt, 'latest_evidence_json' => $evidence, 'id' => (int)$incident['id']]);
        }
    } elseif ($status === 'healthy') {
        $statement = db()->prepare(
            'UPDATE operations_health_incidents SET open_key=NULL,recovered_at=:recovered_at,last_seen_at=:last_seen_at,
             recovery_evidence_json=:evidence_json WHERE open_key=:open_key AND recovered_at IS NULL'
        );
        $statement->execute(['recovered_at' => $evaluatedAt, 'last_seen_at' => $evaluatedAt, 'evidence_json' => $evidence, 'open_key' => $openKey]);
        $recovered = $statement->rowCount();
    }

    if ($changed) {
        operations_analytics_emit_health_transition([
            'check_key' => $metricKey, 'metric_key' => $metricKey, 'metric_family' => (string)$definition['family'],
            'health_status' => $status, 'reason_code' => $reasonCode, 'observed_value' => $value,
            'threshold_value' => $threshold, 'evaluated_at' => $evaluatedAt,
        ], $previousStatus);
    }
    return ['opened' => $opened, 'recovered' => $recovered];
}

function operations_analytics_collect_extensions(string $windowType, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $written = 0;
    foreach (operations_analytics_extended_catalog() as $metricKey => $definition) {
        operations_analytics_upsert_snapshot($metricKey, $definition, $windowType, $start, $end, operations_analytics_collect_extended_metric($definition, $start, $end));
        $written++;
    }
    $policies = db()->query('SELECT * FROM operations_health_policies WHERE enabled=1 ORDER BY check_key')->fetchAll();
    $catalog = operations_analytics_extended_catalog();
    $checks = 0;
    $opened = 0;
    $recovered = 0;
    foreach ($policies as $policy) {
        $metricKey = (string)$policy['check_key'];
        if (!isset($catalog[$metricKey])) continue;
        $result = operations_analytics_apply_extended_health($metricKey, $catalog[$metricKey], $policy, $windowType, $start);
        $checks++;
        $opened += $result['opened'];
        $recovered += $result['recovered'];
    }
    return ['metrics_written' => $written, 'checks_evaluated' => $checks, 'incidents_opened' => $opened, 'incidents_recovered' => $recovered];
}

function operations_reporting_schedule(): array
{
    if (!operations_analytics_schema_available()) return ['frequency' => 'off', 'hour_utc' => 15, 'weekday_utc' => 1, 'last_end_at' => null];
    $row = db()->query('SELECT report_frequency,report_hour_utc,report_weekday_utc,last_scheduled_report_end_at FROM operations_analytics_settings WHERE id=1')->fetch() ?: [];
    $frequency = (string)($row['report_frequency'] ?? 'off');
    if (!in_array($frequency, ['off','daily','weekly','monthly'], true)) $frequency = 'off';
    return [
        'frequency' => $frequency,
        'hour_utc' => max(0, min(23, (int)($row['report_hour_utc'] ?? 15))),
        'weekday_utc' => max(1, min(7, (int)($row['report_weekday_utc'] ?? 1))),
        'last_end_at' => $row['last_scheduled_report_end_at'] ?? null,
    ];
}

function operations_reporting_due_window(array $schedule, ?DateTimeImmutable $now = null): ?array
{
    if (($schedule['frequency'] ?? 'off') === 'off') return null;
    $utc = new DateTimeZone('UTC');
    $now = ($now ?? new DateTimeImmutable('now', $utc))->setTimezone($utc);
    $hour = (int)$schedule['hour_utc'];
    $frequency = (string)$schedule['frequency'];
    if ($frequency === 'daily') {
        $end = new DateTimeImmutable($now->format('Y-m-d 00:00:00'), $utc);
        if ((int)$now->format('G') < $hour) $end = $end->modify('-1 day');
        $start = $end->modify('-1 day');
    } elseif ($frequency === 'weekly') {
        $end = new DateTimeImmutable($now->format('Y-m-d 00:00:00'), $utc);
        while ((int)$end->format('N') !== (int)$schedule['weekday_utc']) $end = $end->modify('-1 day');
        if ($now < $end->setTime($hour, 0)) $end = $end->modify('-7 days');
        $start = $end->modify('-7 days');
    } else {
        $end = new DateTimeImmutable($now->format('Y-m-01 00:00:00'), $utc);
        if ($now < $end->setTime($hour, 0)) $end = $end->modify('-1 month');
        $start = $end->modify('-1 month');
    }
    if (!empty($schedule['last_end_at']) && strtotime((string)$schedule['last_end_at']) >= $end->getTimestamp()) return null;
    return [$start, $end, $frequency];
}

function operations_reporting_generate(string $frequency, DateTimeImmutable $start, DateTimeImmutable $end, ?int $userId = null): int
{
    $metricStatement = db()->prepare(
        'SELECT metric_family,metric_key,unit,SUM(metric_value) AS total_value,AVG(metric_value) AS average_value,
         MAX(metric_value) AS maximum_value,COUNT(*) AS window_count
         FROM operations_metric_snapshots WHERE window_type="day" AND window_started_at>=:window_start AND window_started_at<:window_end
         GROUP BY metric_family,metric_key,unit ORDER BY metric_family,metric_key'
    );
    $metricStatement->execute(['window_start' => $start->format('Y-m-d H:i:s'), 'window_end' => $end->format('Y-m-d H:i:s')]);
    $metrics = array_map(static fn(array $row): array => [
        'metric_family' => (string)$row['metric_family'], 'metric_key' => (string)$row['metric_key'], 'unit' => (string)$row['unit'],
        'total_value' => (float)$row['total_value'], 'average_value' => (float)$row['average_value'],
        'maximum_value' => (float)$row['maximum_value'], 'window_count' => (int)$row['window_count'],
    ], $metricStatement->fetchAll());
    $incidentStatement = db()->prepare(
        'SELECT highest_status,COUNT(*) AS incident_count FROM operations_health_incidents
         WHERE opened_at>=:window_start AND opened_at<:window_end GROUP BY highest_status'
    );
    $incidentStatement->execute(['window_start' => $start->format('Y-m-d H:i:s'), 'window_end' => $end->format('Y-m-d H:i:s')]);
    $incidentCounts = ['attention' => 0, 'degraded' => 0, 'critical' => 0];
    foreach ($incidentStatement->fetchAll() as $row) $incidentCounts[(string)$row['highest_status']] = (int)$row['incident_count'];
    $summary = [
        'version' => 'v66L', 'aggregate_only' => true, 'frequency' => $frequency,
        'metric_count' => count($metrics), 'metrics' => $metrics, 'incident_counts' => $incidentCounts,
    ];
    db()->prepare(
        'INSERT INTO operations_report_runs
         (report_uuid,frequency,window_started_at,window_ended_at,status,summary_json,generated_by_user_id,completed_at)
         VALUES (:report_uuid,:frequency,:window_started_at,:window_ended_at,"completed",:summary_json,:user_id,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE status="completed",summary_json=VALUES(summary_json),generated_by_user_id=VALUES(generated_by_user_id),completed_at=UTC_TIMESTAMP()'
    )->execute([
        'report_uuid' => operations_analytics_uuid(), 'frequency' => $frequency,
        'window_started_at' => $start->format('Y-m-d H:i:s'), 'window_ended_at' => $end->format('Y-m-d H:i:s'),
        'summary_json' => operations_analytics_json_encode($summary), 'user_id' => $userId,
    ]);
    $statement = db()->prepare('SELECT id FROM operations_report_runs WHERE frequency=:frequency AND window_started_at=:window_start AND window_ended_at=:window_end LIMIT 1');
    $statement->execute(['frequency' => $frequency, 'window_start' => $start->format('Y-m-d H:i:s'), 'window_end' => $end->format('Y-m-d H:i:s')]);
    $reportId = (int)$statement->fetchColumn();
    if (function_exists('notification_create_for_role')) {
        $incidentTotal = array_sum($incidentCounts);
        notification_create_for_role(
            'admin', 'system', operations_admin_label($frequency) . ' POD operations report',
            sprintf('Aggregate report complete: %d tracked metrics and %d operational incidents. No source content is included.', count($metrics), $incidentTotal),
            'portal/admin.php?view=operations&section=reports&report=' . $reportId,
            'operations_report', $reportId, $incidentCounts['critical'] > 0 ? 'high' : 'normal'
        );
    }
    return $reportId;
}

function operations_reporting_run_if_due(): ?int
{
    $schedule = operations_reporting_schedule();
    $window = operations_reporting_due_window($schedule);
    if ($window === null) return null;
    [$start, $end, $frequency] = $window;
    $reportId = operations_reporting_generate($frequency, $start, $end, null);
    db()->prepare('UPDATE operations_analytics_settings SET last_scheduled_report_end_at=:window_end WHERE id=1')
        ->execute(['window_end' => $end->format('Y-m-d H:i:s')]);
    return $reportId;
}

function operations_analytics_run_extended(string $windowType = 'hour', bool $force = false): array
{
    $result = operations_analytics_run($windowType, $force);
    if (($result['status'] ?? '') !== 'completed') return $result;
    $start = new DateTimeImmutable((string)$result['window_started_at']);
    $end = new DateTimeImmutable((string)$result['window_ended_at']);
    try {
        db()->beginTransaction();
        $extended = operations_analytics_collect_extensions($windowType, $start, $end);
        db()->prepare(
            'UPDATE operations_worker_runs SET metrics_written=metrics_written+:metrics_written,
             checks_evaluated=checks_evaluated+:checks_evaluated,incidents_opened=incidents_opened+:incidents_opened,
             incidents_recovered=incidents_recovered+:incidents_recovered
             WHERE window_type=:window_type AND window_started_at=:window_started_at'
        )->execute([
            'metrics_written' => $extended['metrics_written'], 'checks_evaluated' => $extended['checks_evaluated'],
            'incidents_opened' => $extended['incidents_opened'], 'incidents_recovered' => $extended['incidents_recovered'],
            'window_type' => $windowType, 'window_started_at' => $start->format('Y-m-d H:i:s'),
        ]);
        db()->commit();
        $result['metrics_written'] += $extended['metrics_written'];
        $result['checks_evaluated'] += $extended['checks_evaluated'];
        $result['incidents_opened'] += $extended['incidents_opened'];
        $result['incidents_recovered'] += $extended['incidents_recovered'];
        $result['scheduled_report_id'] = operations_reporting_run_if_due();
        return $result;
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        db()->prepare(
            'UPDATE operations_worker_runs SET status="failed",error_code="analytics_extension_failed",error_message=:error_message,completed_at=UTC_TIMESTAMP()
             WHERE window_type=:window_type AND window_started_at=:window_started_at'
        )->execute([
            'error_message' => mb_substr(trim(preg_replace('/\s+/u', ' ', $exception->getMessage()) ?? ''), 0, 1000),
            'window_type' => $windowType, 'window_started_at' => $start->format('Y-m-d H:i:s'),
        ]);
        throw $exception;
    }
}

function operations_extended_handle_admin_action(string $action, array $user): bool
{
    if ($action === 'operations_run_hourly' || $action === 'operations_run_daily') {
        $windowType = $action === 'operations_run_daily' ? 'day' : 'hour';
        $result = operations_analytics_run_extended($windowType, true);
        flash('success', sprintf('Operations collection completed: %d metrics and %d checks.', $result['metrics_written'] ?? 0, $result['checks_evaluated'] ?? 0));
        operations_admin_redirect($windowType === 'day' ? 'metrics' : 'overview', $windowType === 'day' ? ['window' => 'day'] : []);
    }
    if ($action === 'operations_save_report_schedule') {
        $frequency = input('report_frequency', 'off');
        if (!in_array($frequency, ['off','daily','weekly','monthly'], true)) throw new RuntimeException('Unsupported report frequency.');
        db()->prepare(
            'UPDATE operations_analytics_settings SET report_frequency=:frequency,report_hour_utc=:hour_utc,
             report_weekday_utc=:weekday_utc,updated_by_user_id=:user_id WHERE id=1'
        )->execute([
            'frequency' => $frequency, 'hour_utc' => max(0, min(23, int_input('report_hour_utc', 15))),
            'weekday_utc' => max(1, min(7, int_input('report_weekday_utc', 1))), 'user_id' => (int)$user['id'],
        ]);
        if (function_exists('log_activity')) log_activity('operations_report_schedule_updated', 'operations_analytics_settings', 1);
        flash('success', 'Operations report schedule updated.');
        operations_admin_redirect('settings');
    }
    return false;
}

function operations_extended_trend_data(string $range): array
{
    [$windowType, $limit] = match ($range) {
        '7d' => ['day', 7], '30d' => ['day', 30], default => ['hour', 24],
    };
    $keys = [
        'notification.queue.depth','activitypub.delivery.depth','automation.event.depth','unified_inbox.needs_response',
        'syndication.websub.depth','feed.source.error_depth','vp3.license.status_risk','vp3.update.job_depth',
    ];
    $result = [];
    foreach ($keys as $metricKey) {
        $statement = db()->prepare(
            'SELECT metric_value,unit,window_started_at FROM operations_metric_snapshots
             WHERE metric_key=:metric_key AND window_type=:window_type ORDER BY window_started_at DESC LIMIT ' . $limit
        );
        $statement->execute(['metric_key' => $metricKey, 'window_type' => $windowType]);
        $rows = array_reverse($statement->fetchAll());
        if (!$rows) continue;
        $result[$metricKey] = ['unit' => (string)$rows[0]['unit'], 'values' => array_map(static fn(array $row): float => (float)$row['metric_value'], $rows)];
    }
    return $result;
}

function operations_extended_sparkline(array $values): string
{
    if (!$values) return '';
    $min = min($values);
    $max = max($values);
    $spread = max(0.000001, $max - $min);
    $count = count($values);
    $points = [];
    foreach ($values as $index => $value) {
        $x = $count <= 1 ? 50 : ($index / ($count - 1)) * 100;
        $y = 28 - ((($value - $min) / $spread) * 24);
        $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
    }
    return '<svg class="operations-sparkline" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true"><polyline points="' . e(implode(' ', $points)) . '"></polyline></svg>';
}

function operations_extended_decorate(string $html): string
{
    if ((string)($_GET['view'] ?? '') !== 'operations' || !str_contains($html, '</main>')) return $html;
    $css = '<link rel="stylesheet" href="' . e(app_url('assets/css/operations-analytics-extended.css?v=20260731-v66L')) . '">';
    $html = str_replace('</head>', $css . '</head>', $html);
    $section = (string)($_GET['section'] ?? 'overview');
    $addition = '';
    if ($section === 'overview' && is_file(__DIR__ . '/recovery-center.php')) {
        $addition .= '<section class="operations-card operations-recovery-entry"><header class="operations-card-header"><div><h2>Incident Recovery Center</h2><p>Simulate, approve, execute, and verify allowlisted recovery runbooks for active operational incidents.</p></div><a class="operations-button primary" data-recovery-center-entry href="' . e(app_url('portal/recovery-center.php')) . '">Open Recovery Center</a></header></section>';
    }
    if ($section === 'overview') {
        $range = (string)($_GET['range'] ?? '24h');
        if (!in_array($range, ['24h','7d','30d'], true)) $range = '24h';
        $addition .= '<section class="operations-card operations-trends"><header class="operations-card-header"><div><h2>Operational trends</h2><p>Aggregate movement across completed windows.</p></div><div class="operations-actions">';
        foreach (['24h' => '24 hours','7d' => '7 days','30d' => '30 days'] as $key => $label) {
            $addition .= '<a class="operations-button small' . ($range === $key ? ' primary' : '') . '" href="' . e(app_url(operations_admin_url('overview', ['range' => $key]))) . '">' . e($label) . '</a>';
        }
        $addition .= '</div></header><div class="operations-trend-grid">';
        $trends = operations_extended_trend_data($range);
        if (!$trends) $addition .= '<div class="operations-empty">Trend history will appear after hourly and daily windows are collected.</div>';
        foreach ($trends as $metricKey => $trend) {
            $values = $trend['values'];
            $current = (float)end($values);
            $addition .= '<article class="operations-trend"><span>' . e(operations_admin_metric_label($metricKey)) . '</span><strong>' . e(operations_admin_format_metric($current, (string)$trend['unit'])) . '</strong>' . operations_extended_sparkline($values) . '<small>Min ' . e(operations_admin_format_metric((float)min($values), (string)$trend['unit'])) . ' · Max ' . e(operations_admin_format_metric((float)max($values), (string)$trend['unit'])) . '</small></article>';
        }
        $addition .= '</div></section>';
    }
    if ($section === 'settings') {
        $schedule = operations_reporting_schedule();
        $addition .= '<section class="operations-card"><header class="operations-card-header"><div><h2>Scheduled owner report</h2><p>Delivered through the existing Notification Delivery preferences and queue.</p></div></header><div class="operations-card-body"><form class="operations-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="operations_save_report_schedule"><label>Frequency<select name="report_frequency">';
        foreach (['off' => 'Off','daily' => 'Daily','weekly' => 'Weekly','monthly' => 'Monthly'] as $value => $label) $addition .= '<option value="' . e($value) . '"' . ($schedule['frequency'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        $addition .= '</select></label><label>Delivery hour (UTC)<input name="report_hour_utc" type="number" min="0" max="23" value="' . (int)$schedule['hour_utc'] . '"></label><label>Weekly delivery day<select name="report_weekday_utc">';
        foreach ([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'] as $value => $label) $addition .= '<option value="' . $value . '"' . ((int)$schedule['weekday_utc'] === $value ? ' selected' : '') . '>' . $label . '</option>';
        $addition .= '</select></label><button class="operations-button primary" type="submit">Save report schedule</button></form><p class="operations-help">Scheduled reports contain aggregate metrics and incident counts only. Channel delivery follows each administrator’s saved Notification Delivery preferences, quiet hours, and content authorization.</p></div></section>';
    }
    return str_replace('</main>', $addition . '</main>', $html);
}

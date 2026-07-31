<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-operations-analytics-v66L */

function operations_analytics_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    if (!preg_match('/^[a-z0-9_]+$/', $table)) return false;
    try {
        $statement = db()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE() AND table_name=:table_name'
        );
        $statement->execute(['table_name' => $table]);
        return $cache[$table] = (int)$statement->fetchColumn() === 1;
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function operations_analytics_schema_available(): bool
{
    foreach ([
        'operations_analytics_settings',
        'operations_metric_snapshots',
        'operations_health_policies',
        'operations_health_state',
        'operations_health_incidents',
        'operations_report_runs',
        'operations_worker_runs',
    ] as $table) {
        if (!operations_analytics_table_exists($table)) return false;
    }
    return true;
}

function operations_analytics_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function operations_analytics_json_encode(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function operations_analytics_settings(): array
{
    $defaults = [
        'enabled' => false,
        'hourly_retention_days' => 90,
        'daily_retention_days' => 730,
        'incident_retention_days' => 730,
        'report_retention_days' => 730,
    ];
    if (!operations_analytics_schema_available()) return $defaults;
    try {
        $row = db()->query('SELECT * FROM operations_analytics_settings WHERE id=1 LIMIT 1')->fetch();
        if (!$row) return $defaults;
        return [
            'enabled' => !empty($row['enabled']),
            'hourly_retention_days' => max(7, min(730, (int)$row['hourly_retention_days'])),
            'daily_retention_days' => max(30, min(3650, (int)$row['daily_retention_days'])),
            'incident_retention_days' => max(30, min(3650, (int)$row['incident_retention_days'])),
            'report_retention_days' => max(30, min(3650, (int)$row['report_retention_days'])),
        ];
    } catch (Throwable) {
        return $defaults;
    }
}

function operations_analytics_metric_catalog(): array
{
    return [
        'notification.queue.depth' => [
            'family' => 'notification_delivery', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'notification_delivery_queue', 'where' => "status IN ('pending','leased')", 'created_column' => 'created_at',
        ],
        'notification.queue.oldest_minutes' => [
            'family' => 'notification_delivery', 'unit' => 'minutes', 'kind' => 'oldest_minutes',
            'table' => 'notification_delivery_queue', 'where' => "status IN ('pending','leased')", 'time_column' => 'created_at',
        ],
        'notification.delivery.sent' => [
            'family' => 'notification_delivery', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'notification_delivery_queue', 'where' => "status='sent'", 'time_column' => 'sent_at',
        ],
        'notification.delivery.failed' => [
            'family' => 'notification_delivery', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'notification_delivery_queue', 'where' => "status='failed'", 'time_column' => 'updated_at',
        ],
        'notification.delivery.suppressed' => [
            'family' => 'notification_delivery', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'notification_delivery_queue', 'where' => "status='suppressed'", 'time_column' => 'updated_at',
        ],
        'notification.delivery.retry_attempts' => [
            'family' => 'notification_delivery', 'unit' => 'count', 'kind' => 'window_sum',
            'table' => 'notification_delivery_queue', 'where' => 'attempt_count>0', 'time_column' => 'updated_at', 'value_column' => 'attempt_count',
        ],
        'activitypub.delivery.depth' => [
            'family' => 'activitypub', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'activitypub_deliveries', 'where' => "status IN ('pending','delivering')", 'created_column' => 'created_at',
        ],
        'activitypub.delivery.oldest_minutes' => [
            'family' => 'activitypub', 'unit' => 'minutes', 'kind' => 'oldest_minutes',
            'table' => 'activitypub_deliveries', 'where' => "status IN ('pending','delivering')", 'time_column' => 'created_at',
        ],
        'activitypub.delivery.delivered' => [
            'family' => 'activitypub', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'activitypub_deliveries', 'where' => "status='delivered'", 'time_column' => 'delivered_at',
        ],
        'activitypub.delivery.failed' => [
            'family' => 'activitypub', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'activitypub_deliveries', 'where' => "status='failed'", 'time_column' => 'updated_at',
        ],
        'activitypub.delivery.retry_attempts' => [
            'family' => 'activitypub', 'unit' => 'count', 'kind' => 'window_sum',
            'table' => 'activitypub_deliveries', 'where' => 'attempt_count>0', 'time_column' => 'updated_at', 'value_column' => 'attempt_count',
        ],
        'automation.event.depth' => [
            'family' => 'automation', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'automation_events', 'where' => "status IN ('pending','processing')", 'created_column' => 'created_at',
        ],
        'automation.event.oldest_minutes' => [
            'family' => 'automation', 'unit' => 'minutes', 'kind' => 'oldest_minutes',
            'table' => 'automation_events', 'where' => "status IN ('pending','processing')", 'time_column' => 'created_at',
        ],
        'automation.event.completed' => [
            'family' => 'automation', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'automation_events', 'where' => "status='completed'", 'time_column' => 'completed_at',
        ],
        'automation.event.failed' => [
            'family' => 'automation', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'automation_events', 'where' => "status='failed'", 'time_column' => 'updated_at',
        ],
        'automation.execution.executed' => [
            'family' => 'automation', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'automation_executions', 'where' => "status IN ('executed','partially_executed')", 'time_column' => 'created_at',
        ],
        'automation.execution.failed' => [
            'family' => 'automation', 'unit' => 'count', 'kind' => 'window_count',
            'table' => 'automation_executions', 'where' => "status='failed'", 'time_column' => 'created_at',
        ],
        'automation.approval.depth' => [
            'family' => 'automation', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'automation_approvals', 'where' => "status IN ('pending','approved')", 'created_column' => 'created_at',
        ],
        'unified_inbox.open' => [
            'family' => 'unified_inbox', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'unified_inbox_workflow', 'where' => "workflow_status IN ('open','waiting')", 'created_column' => 'created_at',
        ],
        'unified_inbox.needs_response' => [
            'family' => 'unified_inbox', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'unified_inbox_workflow', 'where' => "workflow_status<>'resolved' AND needs_response=1", 'created_column' => 'created_at',
        ],
        'unified_inbox.high_priority' => [
            'family' => 'unified_inbox', 'unit' => 'count', 'kind' => 'gauge_count',
            'table' => 'unified_inbox_workflow', 'where' => "workflow_status<>'resolved' AND priority IN ('high','urgent')", 'created_column' => 'created_at',
        ],
    ];
}

function operations_analytics_valid_identifier(string $identifier): string
{
    if (!preg_match('/^[a-z0-9_]+$/', $identifier)) throw new RuntimeException('Invalid analytics source identifier.');
    return $identifier;
}

function operations_analytics_collect_metric(array $definition, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $table = operations_analytics_valid_identifier((string)$definition['table']);
    if (!operations_analytics_table_exists($table)) return ['available' => false, 'value' => 0.0, 'sample_count' => 0];

    $where = trim((string)($definition['where'] ?? '1=1')) ?: '1=1';
    $kind = (string)$definition['kind'];
    $parameters = ['window_start' => $start->format('Y-m-d H:i:s'), 'window_end' => $end->format('Y-m-d H:i:s')];

    if ($kind === 'gauge_count') {
        $createdColumn = operations_analytics_valid_identifier((string)($definition['created_column'] ?? 'created_at'));
        $statement = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where} AND {$createdColumn}<:window_end");
        $statement->execute(['window_end' => $parameters['window_end']]);
        $value = (float)$statement->fetchColumn();
        return ['available' => true, 'value' => $value, 'sample_count' => (int)$value];
    }

    $timeColumn = operations_analytics_valid_identifier((string)($definition['time_column'] ?? 'created_at'));
    if ($kind === 'oldest_minutes') {
        $statement = db()->prepare(
            "SELECT COUNT(*) AS sample_count,
                    COALESCE(TIMESTAMPDIFF(MINUTE,MIN({$timeColumn}),:window_end),0) AS metric_value
             FROM {$table} WHERE {$where} AND {$timeColumn}<:window_end"
        );
        $statement->execute(['window_end' => $parameters['window_end']]);
        $row = $statement->fetch() ?: [];
        return ['available' => true, 'value' => max(0.0, (float)($row['metric_value'] ?? 0)), 'sample_count' => (int)($row['sample_count'] ?? 0)];
    }

    if ($kind === 'window_count') {
        $statement = db()->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE {$where} AND {$timeColumn}>=:window_start AND {$timeColumn}<:window_end"
        );
        $statement->execute($parameters);
        $value = (float)$statement->fetchColumn();
        return ['available' => true, 'value' => $value, 'sample_count' => (int)$value];
    }

    if ($kind === 'window_sum') {
        $valueColumn = operations_analytics_valid_identifier((string)$definition['value_column']);
        $statement = db()->prepare(
            "SELECT COUNT(*) AS sample_count,COALESCE(SUM({$valueColumn}),0) AS metric_value
             FROM {$table}
             WHERE {$where} AND {$timeColumn}>=:window_start AND {$timeColumn}<:window_end"
        );
        $statement->execute($parameters);
        $row = $statement->fetch() ?: [];
        return ['available' => true, 'value' => (float)($row['metric_value'] ?? 0), 'sample_count' => (int)($row['sample_count'] ?? 0)];
    }

    throw new RuntimeException('Unsupported analytics metric kind.');
}

function operations_analytics_upsert_snapshot(
    string $metricKey,
    array $definition,
    string $windowType,
    DateTimeImmutable $start,
    DateTimeImmutable $end,
    array $result
): void {
    $aggregate = [
        'available' => !empty($result['available']),
        'collection_mode' => str_starts_with((string)$definition['kind'], 'gauge') || $definition['kind'] === 'oldest_minutes'
            ? 'current_state_at_collection'
            : 'window_aggregate',
    ];
    db()->prepare(
        'INSERT INTO operations_metric_snapshots
         (metric_key,metric_family,window_type,window_started_at,window_ended_at,metric_value,sample_count,unit,aggregate_json,collected_at)
         VALUES
         (:metric_key,:metric_family,:window_type,:window_started_at,:window_ended_at,:metric_value,:sample_count,:unit,:aggregate_json,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
           metric_family=VALUES(metric_family),window_ended_at=VALUES(window_ended_at),metric_value=VALUES(metric_value),
           sample_count=VALUES(sample_count),unit=VALUES(unit),aggregate_json=VALUES(aggregate_json),collected_at=UTC_TIMESTAMP()'
    )->execute([
        'metric_key' => $metricKey,
        'metric_family' => (string)$definition['family'],
        'window_type' => $windowType,
        'window_started_at' => $start->format('Y-m-d H:i:s'),
        'window_ended_at' => $end->format('Y-m-d H:i:s'),
        'metric_value' => number_format((float)$result['value'], 6, '.', ''),
        'sample_count' => max(0, (int)$result['sample_count']),
        'unit' => (string)$definition['unit'],
        'aggregate_json' => operations_analytics_json_encode($aggregate),
    ]);
}

function operations_analytics_collect_window(string $windowType, DateTimeImmutable $start, DateTimeImmutable $end): int
{
    if (!in_array($windowType, ['hour', 'day'], true)) throw new RuntimeException('Unsupported analytics window.');
    $written = 0;
    foreach (operations_analytics_metric_catalog() as $metricKey => $definition) {
        $result = operations_analytics_collect_metric($definition, $start, $end);
        operations_analytics_upsert_snapshot($metricKey, $definition, $windowType, $start, $end, $result);
        $written++;
    }
    return $written;
}

function operations_analytics_health_rank(string $status): int
{
    return ['unknown' => 0, 'healthy' => 1, 'attention' => 2, 'degraded' => 3, 'critical' => 4][$status] ?? 0;
}

function operations_analytics_policy_status(float $value, array $policy): array
{
    $comparison = (string)($policy['comparison'] ?? 'greater_or_equal');
    $levels = [
        'critical' => isset($policy['critical_threshold']) ? (float)$policy['critical_threshold'] : null,
        'degraded' => isset($policy['degraded_threshold']) ? (float)$policy['degraded_threshold'] : null,
        'attention' => isset($policy['attention_threshold']) ? (float)$policy['attention_threshold'] : null,
    ];
    foreach ($levels as $status => $threshold) {
        if ($threshold === null) continue;
        $matched = $comparison === 'less_or_equal' ? $value <= $threshold : $value >= $threshold;
        if ($matched) return [$status, $threshold];
    }
    return ['healthy', null];
}

function operations_analytics_emit_health_transition(array $state, string $previousStatus): void
{
    if (!function_exists('automation_capture_event')) return;
    try {
        automation_capture_event([
            'event_key' => 'operations.health_transition',
            'source_type' => 'operations_health',
            'source_id' => 0,
            'category' => 'system',
            'priority' => in_array($state['health_status'], ['degraded', 'critical'], true) ? 'high' : 'normal',
            'occurred_at' => $state['evaluated_at'],
            'dedupe_key' => hash('sha256', implode('|', [
                'operations.health_transition', $state['check_key'], $previousStatus, $state['health_status'], $state['evaluated_at'],
            ])),
            'payload' => [
                'check_key' => $state['check_key'],
                'metric_key' => $state['metric_key'],
                'metric_family' => $state['metric_family'],
                'previous_status' => $previousStatus,
                'health_status' => $state['health_status'],
                'reason_code' => $state['reason_code'],
                'observed_value' => $state['observed_value'],
                'threshold_value' => $state['threshold_value'],
            ],
        ]);
    } catch (Throwable) {
    }
}

function operations_analytics_evaluate_health(string $windowType, DateTimeImmutable $start): array
{
    $policies = db()->query('SELECT * FROM operations_health_policies WHERE enabled=1 ORDER BY check_key')->fetchAll();
    $catalog = operations_analytics_metric_catalog();
    $checks = 0;
    $opened = 0;
    $recovered = 0;

    foreach ($policies as $policy) {
        $checkKey = (string)$policy['check_key'];
        $definition = $catalog[$checkKey] ?? null;
        if (!$definition) continue;
        $snapshot = db()->prepare(
            'SELECT * FROM operations_metric_snapshots
             WHERE metric_key=:metric_key AND window_type=:window_type AND window_started_at=:window_started_at LIMIT 1'
        );
        $snapshot->execute([
            'metric_key' => $checkKey,
            'window_type' => $windowType,
            'window_started_at' => $start->format('Y-m-d H:i:s'),
        ]);
        $row = $snapshot->fetch();
        $value = $row ? (float)$row['metric_value'] : null;
        $sampleCount = $row ? (int)$row['sample_count'] : 0;
        if ($value === null || $sampleCount < (int)$policy['minimum_sample_count']) {
            $status = 'unknown';
            $threshold = null;
            $reasonCode = 'metric_unavailable';
        } else {
            [$status, $threshold] = operations_analytics_policy_status($value, $policy);
            $reasonCode = $status === 'healthy' ? 'within_policy' : 'threshold_' . $status;
        }

        $existingStatement = db()->prepare('SELECT * FROM operations_health_state WHERE check_key=:check_key LIMIT 1');
        $existingStatement->execute(['check_key' => $checkKey]);
        $existing = $existingStatement->fetch() ?: null;
        $previousStatus = $existing ? (string)$existing['health_status'] : 'unknown';
        $changed = $previousStatus !== $status;
        $evaluatedAt = gmdate('Y-m-d H:i:s');
        $firstUnhealthyAt = in_array($status, ['attention', 'degraded', 'critical'], true)
            ? (($existing && !empty($existing['first_unhealthy_at'])) ? $existing['first_unhealthy_at'] : $evaluatedAt)
            : null;
        $recoveredAt = $status === 'healthy' && in_array($previousStatus, ['attention', 'degraded', 'critical'], true)
            ? $evaluatedAt
            : ($existing['recovered_at'] ?? null);
        $evidence = operations_analytics_json_encode([
            'window_type' => $windowType,
            'window_started_at' => $start->format('Y-m-d H:i:s'),
            'sample_count' => $sampleCount,
            'unit' => (string)$definition['unit'],
        ]);

        db()->prepare(
            'INSERT INTO operations_health_state
             (check_key,metric_key,metric_family,health_status,reason_code,observed_value,threshold_value,evidence_json,
              evaluated_at,last_changed_at,first_unhealthy_at,recovered_at)
             VALUES
             (:check_key,:metric_key,:metric_family,:health_status,:reason_code,:observed_value,:threshold_value,:evidence_json,
              :evaluated_at,:last_changed_at,:first_unhealthy_at,:recovered_at)
             ON DUPLICATE KEY UPDATE
               metric_key=VALUES(metric_key),metric_family=VALUES(metric_family),health_status=VALUES(health_status),
               reason_code=VALUES(reason_code),observed_value=VALUES(observed_value),threshold_value=VALUES(threshold_value),
               evidence_json=VALUES(evidence_json),evaluated_at=VALUES(evaluated_at),
               last_changed_at=IF(health_status<>VALUES(health_status),VALUES(last_changed_at),last_changed_at),
               first_unhealthy_at=VALUES(first_unhealthy_at),recovered_at=VALUES(recovered_at)'
        )->execute([
            'check_key' => $checkKey,
            'metric_key' => $checkKey,
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

        if (in_array($status, ['attention', 'degraded', 'critical'], true)) {
            $openKey = hash('sha256', $checkKey);
            $incidentStatement = db()->prepare('SELECT * FROM operations_health_incidents WHERE open_key=:open_key LIMIT 1');
            $incidentStatement->execute(['open_key' => $openKey]);
            $incident = $incidentStatement->fetch();
            if (!$incident) {
                db()->prepare(
                    'INSERT INTO operations_health_incidents
                     (incident_uuid,open_key,check_key,metric_key,metric_family,highest_status,reason_code,opened_at,last_seen_at,
                      opening_evidence_json,latest_evidence_json)
                     VALUES
                     (:incident_uuid,:open_key,:check_key,:metric_key,:metric_family,:highest_status,:reason_code,:opened_at,:last_seen_at,
                      :opening_evidence_json,:latest_evidence_json)'
                )->execute([
                    'incident_uuid' => operations_analytics_uuid(),
                    'open_key' => $openKey,
                    'check_key' => $checkKey,
                    'metric_key' => $checkKey,
                    'metric_family' => (string)$definition['family'],
                    'highest_status' => $status,
                    'reason_code' => $reasonCode,
                    'opened_at' => $evaluatedAt,
                    'last_seen_at' => $evaluatedAt,
                    'opening_evidence_json' => $evidence,
                    'latest_evidence_json' => $evidence,
                ]);
                $opened++;
            } else {
                $highest = operations_analytics_health_rank($status) > operations_analytics_health_rank((string)$incident['highest_status'])
                    ? $status
                    : (string)$incident['highest_status'];
                db()->prepare(
                    'UPDATE operations_health_incidents
                     SET highest_status=:highest_status,reason_code=:reason_code,last_seen_at=:last_seen_at,
                         occurrence_count=occurrence_count+1,latest_evidence_json=:latest_evidence_json
                     WHERE id=:id'
                )->execute([
                    'highest_status' => $highest,
                    'reason_code' => $reasonCode,
                    'last_seen_at' => $evaluatedAt,
                    'latest_evidence_json' => $evidence,
                    'id' => (int)$incident['id'],
                ]);
            }
        } elseif ($status === 'healthy') {
            $openKey = hash('sha256', $checkKey);
            $statement = db()->prepare(
                'UPDATE operations_health_incidents
                 SET open_key=NULL,recovered_at=:recovered_at,last_seen_at=:recovered_at,recovery_evidence_json=:evidence_json
                 WHERE open_key=:open_key AND recovered_at IS NULL'
            );
            $statement->execute(['recovered_at' => $evaluatedAt, 'evidence_json' => $evidence, 'open_key' => $openKey]);
            if ($statement->rowCount() > 0) $recovered += $statement->rowCount();
        }

        if ($changed) {
            operations_analytics_emit_health_transition([
                'check_key' => $checkKey,
                'metric_key' => $checkKey,
                'metric_family' => (string)$definition['family'],
                'health_status' => $status,
                'reason_code' => $reasonCode,
                'observed_value' => $value,
                'threshold_value' => $threshold,
                'evaluated_at' => $evaluatedAt,
            ], $previousStatus);
        }
        $checks++;
    }

    return ['checks_evaluated' => $checks, 'incidents_opened' => $opened, 'incidents_recovered' => $recovered];
}

function operations_analytics_window_bounds(string $windowType, ?DateTimeImmutable $reference = null): array
{
    $timezone = new DateTimeZone('UTC');
    $reference = ($reference ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    if ($windowType === 'hour') {
        $end = new DateTimeImmutable($reference->format('Y-m-d H:00:00'), $timezone);
        return [$end->modify('-1 hour'), $end];
    }
    if ($windowType === 'day') {
        $end = new DateTimeImmutable($reference->format('Y-m-d 00:00:00'), $timezone);
        return [$end->modify('-1 day'), $end];
    }
    throw new RuntimeException('Unsupported analytics window.');
}

function operations_analytics_cleanup(): array
{
    $settings = operations_analytics_settings();
    $hourly = db()->prepare("DELETE FROM operations_metric_snapshots WHERE window_type='hour' AND window_started_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL :days DAY)");
    $hourly->bindValue(':days', $settings['hourly_retention_days'], PDO::PARAM_INT);
    $hourly->execute();
    $daily = db()->prepare("DELETE FROM operations_metric_snapshots WHERE window_type='day' AND window_started_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL :days DAY)");
    $daily->bindValue(':days', $settings['daily_retention_days'], PDO::PARAM_INT);
    $daily->execute();
    $incidents = db()->prepare('DELETE FROM operations_health_incidents WHERE recovered_at IS NOT NULL AND recovered_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL :days DAY)');
    $incidents->bindValue(':days', $settings['incident_retention_days'], PDO::PARAM_INT);
    $incidents->execute();
    $reports = db()->prepare('DELETE FROM operations_report_runs WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL :days DAY)');
    $reports->bindValue(':days', $settings['report_retention_days'], PDO::PARAM_INT);
    $reports->execute();
    return [
        'hourly_snapshots_deleted' => $hourly->rowCount(),
        'daily_snapshots_deleted' => $daily->rowCount(),
        'incidents_deleted' => $incidents->rowCount(),
        'reports_deleted' => $reports->rowCount(),
    ];
}

function operations_analytics_run(string $windowType = 'hour', bool $force = false): array
{
    if (!operations_analytics_schema_available()) throw new RuntimeException('Import database/operations_analytics_v66l.sql first.');
    $settings = operations_analytics_settings();
    if (!$force && !$settings['enabled']) return ['status' => 'disabled', 'window_type' => $windowType];
    [$start, $end] = operations_analytics_window_bounds($windowType);
    $runUuid = operations_analytics_uuid();

    db()->prepare(
        'INSERT INTO operations_worker_runs
         (run_uuid,window_type,window_started_at,window_ended_at,status,started_at)
         VALUES (:run_uuid,:window_type,:window_started_at,:window_ended_at,"running",UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE run_uuid=VALUES(run_uuid),status="running",metrics_written=0,checks_evaluated=0,
             incidents_opened=0,incidents_recovered=0,error_code=NULL,error_message=NULL,started_at=UTC_TIMESTAMP(),completed_at=NULL'
    )->execute([
        'run_uuid' => $runUuid,
        'window_type' => $windowType,
        'window_started_at' => $start->format('Y-m-d H:i:s'),
        'window_ended_at' => $end->format('Y-m-d H:i:s'),
    ]);
    $runStatement = db()->prepare('SELECT id FROM operations_worker_runs WHERE window_type=:window_type AND window_started_at=:window_started_at LIMIT 1');
    $runStatement->execute(['window_type' => $windowType, 'window_started_at' => $start->format('Y-m-d H:i:s')]);
    $runId = (int)$runStatement->fetchColumn();

    try {
        db()->beginTransaction();
        $metricsWritten = operations_analytics_collect_window($windowType, $start, $end);
        $health = operations_analytics_evaluate_health($windowType, $start);
        db()->prepare(
            'UPDATE operations_worker_runs
             SET status="completed",metrics_written=:metrics_written,checks_evaluated=:checks_evaluated,
                 incidents_opened=:incidents_opened,incidents_recovered=:incidents_recovered,completed_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute([
            'metrics_written' => $metricsWritten,
            'checks_evaluated' => $health['checks_evaluated'],
            'incidents_opened' => $health['incidents_opened'],
            'incidents_recovered' => $health['incidents_recovered'],
            'id' => $runId,
        ]);
        db()->commit();
        $cleanup = operations_analytics_cleanup();
        return [
            'status' => 'completed',
            'window_type' => $windowType,
            'window_started_at' => $start->format(DATE_ATOM),
            'window_ended_at' => $end->format(DATE_ATOM),
            'metrics_written' => $metricsWritten,
        ] + $health + ['cleanup' => $cleanup];
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        db()->prepare(
            'UPDATE operations_worker_runs SET status="failed",error_code=:error_code,error_message=:error_message,completed_at=UTC_TIMESTAMP() WHERE id=:id'
        )->execute([
            'error_code' => 'analytics_run_failed',
            'error_message' => mb_substr(trim(preg_replace('/\s+/u', ' ', $exception->getMessage()) ?? ''), 0, 1000),
            'id' => $runId,
        ]);
        throw $exception;
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/operations-analytics.php';

function operations_admin_url(string $section = 'overview', array $parameters = []): string
{
    return 'portal/admin.php?' . http_build_query(['view' => 'operations', 'section' => $section] + $parameters);
}

function operations_admin_redirect(string $section = 'overview', array $parameters = []): never
{
    redirect(operations_admin_url($section, $parameters));
}

function operations_admin_label(string $value): string
{
    return ucwords(str_replace(['.', '_', '-'], ' ', $value));
}

function operations_admin_metric_labels(): array
{
    return [
        'notification.queue.depth' => 'Notification queue depth',
        'notification.queue.oldest_minutes' => 'Oldest notification work',
        'notification.delivery.sent' => 'Notifications sent',
        'notification.delivery.failed' => 'Notification failures',
        'notification.delivery.suppressed' => 'Notifications suppressed',
        'notification.delivery.retry_attempts' => 'Notification retry attempts',
        'activitypub.delivery.depth' => 'ActivityPub delivery queue',
        'activitypub.delivery.oldest_minutes' => 'Oldest ActivityPub delivery',
        'activitypub.delivery.delivered' => 'ActivityPub deliveries completed',
        'activitypub.delivery.failed' => 'ActivityPub delivery failures',
        'activitypub.delivery.retry_attempts' => 'ActivityPub retry attempts',
        'automation.event.depth' => 'Automation event queue',
        'automation.event.oldest_minutes' => 'Oldest automation event',
        'automation.event.completed' => 'Automation events completed',
        'automation.event.failed' => 'Automation event failures',
        'automation.execution.executed' => 'Automation executions',
        'automation.execution.failed' => 'Automation execution failures',
        'automation.approval.depth' => 'Automation approval backlog',
        'unified_inbox.open' => 'Open Unified Inbox items',
        'unified_inbox.needs_response' => 'Unified Inbox needs response',
        'unified_inbox.high_priority' => 'High-priority Inbox items',
    ];
}

function operations_admin_metric_label(string $metricKey): string
{
    return operations_admin_metric_labels()[$metricKey] ?? operations_admin_label($metricKey);
}

function operations_admin_family_label(string $family): string
{
    return [
        'notification_delivery' => 'Notification Delivery',
        'activitypub' => 'ActivityPub',
        'automation' => 'Automation Rules',
        'unified_inbox' => 'Unified Social Inbox',
    ][$family] ?? operations_admin_label($family);
}

function operations_admin_family_url(string $family): string
{
    return match ($family) {
        'automation' => app_url('portal/admin.php?view=automation'),
        'unified_inbox' => app_url('portal/admin.php?view=unified-inbox'),
        'activitypub' => app_url('portal/admin.php?view=activitypub'),
        'notification_delivery' => app_url('portal/admin.php?view=notifications'),
        default => app_url(operations_admin_url('metrics')),
    };
}

function operations_admin_latest_snapshots(string $windowType = 'hour'): array
{
    if (!operations_analytics_schema_available()) return [];
    if (!in_array($windowType, ['hour', 'day'], true)) $windowType = 'hour';
    $statement = db()->prepare(
        'SELECT snapshot.*
         FROM operations_metric_snapshots snapshot
         INNER JOIN (
            SELECT metric_key,MAX(window_started_at) AS latest_window
            FROM operations_metric_snapshots
            WHERE window_type=:inner_window_type
            GROUP BY metric_key
         ) latest
           ON latest.metric_key=snapshot.metric_key
          AND latest.latest_window=snapshot.window_started_at
         WHERE snapshot.window_type=:outer_window_type
         ORDER BY snapshot.metric_family,snapshot.metric_key'
    );
    $statement->execute(['inner_window_type' => $windowType, 'outer_window_type' => $windowType]);
    return $statement->fetchAll();
}

function operations_admin_health_states(): array
{
    if (!operations_analytics_schema_available()) return [];
    return db()->query(
        "SELECT * FROM operations_health_state
         ORDER BY FIELD(health_status,'critical','degraded','attention','unknown','healthy'),metric_family,check_key"
    )->fetchAll();
}

function operations_admin_incidents(bool $activeOnly = false, int $limit = 100): array
{
    if (!operations_analytics_schema_available()) return [];
    $limit = max(1, min(500, $limit));
    $where = $activeOnly ? 'WHERE recovered_at IS NULL' : '';
    return db()->query(
        "SELECT * FROM operations_health_incidents {$where}
         ORDER BY (recovered_at IS NULL) DESC,last_seen_at DESC,id DESC LIMIT {$limit}"
    )->fetchAll();
}

function operations_admin_worker_runs(int $limit = 20): array
{
    if (!operations_analytics_schema_available()) return [];
    $limit = max(1, min(100, $limit));
    return db()->query('SELECT * FROM operations_worker_runs ORDER BY started_at DESC,id DESC LIMIT ' . $limit)->fetchAll();
}

function operations_admin_reports(int $limit = 50): array
{
    if (!operations_analytics_schema_available()) return [];
    $limit = max(1, min(200, $limit));
    return db()->query('SELECT * FROM operations_report_runs ORDER BY created_at DESC,id DESC LIMIT ' . $limit)->fetchAll();
}

function operations_admin_overall_status(array $states): string
{
    $overall = 'unknown';
    foreach ($states as $state) {
        $candidate = (string)$state['health_status'];
        if (operations_analytics_health_rank($candidate) > operations_analytics_health_rank($overall)) $overall = $candidate;
    }
    return $overall;
}

function operations_admin_format_metric(float $value, string $unit): string
{
    if ($unit === 'minutes') {
        if ($value >= 1440) return number_format($value / 1440, 1) . ' days';
        if ($value >= 60) return number_format($value / 60, 1) . ' hours';
        return number_format($value, 0) . ' min';
    }
    if ($unit === 'percent') return number_format($value, 1) . '%';
    return number_format($value, abs($value - round($value)) > 0.000001 ? 2 : 0);
}

function operations_admin_previous_metric(string $metricKey, string $windowType, string $currentStart): ?float
{
    $statement = db()->prepare(
        'SELECT metric_value FROM operations_metric_snapshots
         WHERE metric_key=:metric_key AND window_type=:window_type AND window_started_at<:current_start
         ORDER BY window_started_at DESC LIMIT 1'
    );
    $statement->execute(['metric_key' => $metricKey, 'window_type' => $windowType, 'current_start' => $currentStart]);
    $value = $statement->fetchColumn();
    return $value === false ? null : (float)$value;
}

function operations_admin_generate_report(int $days, int $userId): int
{
    if (!operations_analytics_schema_available()) throw new RuntimeException('Import database/operations_analytics_v66l.sql first.');
    $days = max(1, min(365, $days));
    $end = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $start = $end->modify('-' . $days . ' days');

    $familyStatement = db()->prepare(
        'SELECT metric_family,metric_key,unit,SUM(metric_value) AS total_value,AVG(metric_value) AS average_value,
                MAX(metric_value) AS maximum_value,COUNT(*) AS window_count
         FROM operations_metric_snapshots
         WHERE window_type="day" AND window_started_at>=:window_start AND window_started_at<:window_end
         GROUP BY metric_family,metric_key,unit
         ORDER BY metric_family,metric_key'
    );
    $familyStatement->execute([
        'window_start' => $start->format('Y-m-d H:i:s'),
        'window_end' => $end->format('Y-m-d H:i:s'),
    ]);
    $metrics = [];
    foreach ($familyStatement->fetchAll() as $row) {
        $metrics[] = [
            'metric_family' => (string)$row['metric_family'],
            'metric_key' => (string)$row['metric_key'],
            'unit' => (string)$row['unit'],
            'total_value' => (float)$row['total_value'],
            'average_value' => (float)$row['average_value'],
            'maximum_value' => (float)$row['maximum_value'],
            'window_count' => (int)$row['window_count'],
        ];
    }

    $incidentStatement = db()->prepare(
        'SELECT metric_family,highest_status,COUNT(*) AS incident_count
         FROM operations_health_incidents
         WHERE opened_at>=:window_start AND opened_at<:window_end
         GROUP BY metric_family,highest_status
         ORDER BY metric_family,highest_status'
    );
    $incidentStatement->execute([
        'window_start' => $start->format('Y-m-d H:i:s'),
        'window_end' => $end->format('Y-m-d H:i:s'),
    ]);
    $incidents = array_map(static fn(array $row): array => [
        'metric_family' => (string)$row['metric_family'],
        'highest_status' => (string)$row['highest_status'],
        'incident_count' => (int)$row['incident_count'],
    ], $incidentStatement->fetchAll());

    $summary = [
        'version' => 'v66L',
        'aggregate_only' => true,
        'window_days' => $days,
        'metric_count' => count($metrics),
        'metrics' => $metrics,
        'incidents' => $incidents,
    ];
    db()->prepare(
        'INSERT INTO operations_report_runs
         (report_uuid,frequency,window_started_at,window_ended_at,status,summary_json,generated_by_user_id,completed_at)
         VALUES (:report_uuid,"manual",:window_started_at,:window_ended_at,"completed",:summary_json,:user_id,UTC_TIMESTAMP())'
    )->execute([
        'report_uuid' => operations_analytics_uuid(),
        'window_started_at' => $start->format('Y-m-d H:i:s'),
        'window_ended_at' => $end->format('Y-m-d H:i:s'),
        'summary_json' => operations_analytics_json_encode($summary),
        'user_id' => $userId > 0 ? $userId : null,
    ]);
    return (int)db()->lastInsertId();
}

function operations_admin_csv_cell(mixed $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$value)) ?? '');
    if ($value !== '' && preg_match('/^[=+\-@]/', $value)) $value = "'" . $value;
    return $value;
}

function operations_admin_export_csv(): never
{
    $rows = operations_admin_latest_snapshots('hour');
    $health = [];
    foreach (operations_admin_health_states() as $state) $health[(string)$state['metric_key']] = $state;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pod-operations-' . gmdate('Ymd-His') . '.csv"');
    header('Cache-Control: private, no-store, max-age=0');
    $output = fopen('php://output', 'wb');
    if ($output === false) throw new RuntimeException('Unable to create export.');
    fputcsv($output, ['Metric family','Metric','Value','Unit','Sample count','Health','Reason','Window start','Collected at']);
    foreach ($rows as $row) {
        $state = $health[(string)$row['metric_key']] ?? [];
        fputcsv($output, array_map('operations_admin_csv_cell', [
            operations_admin_family_label((string)$row['metric_family']),
            operations_admin_metric_label((string)$row['metric_key']),
            (string)$row['metric_value'],
            (string)$row['unit'],
            (string)$row['sample_count'],
            (string)($state['health_status'] ?? 'unknown'),
            (string)($state['reason_code'] ?? 'metric_unavailable'),
            (string)$row['window_started_at'],
            (string)$row['collected_at'],
        ]));
    }
    fclose($output);
    exit;
}

function operations_handle_admin_action(string $action, array $user): bool
{
    if (!str_starts_with($action, 'operations_')) return false;
    if (!operations_analytics_schema_available()) throw new RuntimeException('Import database/operations_analytics_v66l.sql first.');
    $userId = (int)$user['id'];

    if ($action === 'operations_run_hourly') {
        $result = operations_analytics_run('hour', true);
        flash('success', sprintf('Operations collection completed: %d metrics and %d health checks.', $result['metrics_written'] ?? 0, $result['checks_evaluated'] ?? 0));
        operations_admin_redirect('overview');
    }

    if ($action === 'operations_run_daily') {
        $result = operations_analytics_run('day', true);
        flash('success', sprintf('Daily operations collection completed: %d metrics.', $result['metrics_written'] ?? 0));
        operations_admin_redirect('metrics', ['window' => 'day']);
    }

    if ($action === 'operations_save_settings') {
        db()->prepare(
            'UPDATE operations_analytics_settings
             SET enabled=:enabled,hourly_retention_days=:hourly_days,daily_retention_days=:daily_days,
                 incident_retention_days=:incident_days,report_retention_days=:report_days,updated_by_user_id=:user_id
             WHERE id=1'
        )->execute([
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'hourly_days' => max(7, min(730, int_input('hourly_retention_days', 90))),
            'daily_days' => max(30, min(3650, int_input('daily_retention_days', 730))),
            'incident_days' => max(30, min(3650, int_input('incident_retention_days', 730))),
            'report_days' => max(30, min(3650, int_input('report_retention_days', 730))),
            'user_id' => $userId,
        ]);
        if (function_exists('log_activity')) log_activity('operations_analytics_settings_updated', 'operations_analytics_settings', 1);
        flash('success', 'Operations analytics settings updated.');
        operations_admin_redirect('settings');
    }

    if ($action === 'operations_save_policy') {
        $checkKey = input('check_key');
        if (!isset(operations_analytics_metric_catalog()[$checkKey])) throw new RuntimeException('Unknown health check.');
        $comparison = input('comparison', 'greater_or_equal');
        if (!in_array($comparison, ['greater_or_equal', 'less_or_equal'], true)) throw new RuntimeException('Unsupported comparison.');
        $values = [];
        foreach (['attention_threshold','degraded_threshold','critical_threshold'] as $field) {
            $raw = trim(input($field));
            $values[$field] = $raw === '' ? null : max(0, (float)$raw);
        }
        db()->prepare(
            'UPDATE operations_health_policies
             SET enabled=:enabled,comparison=:comparison,attention_threshold=:attention_threshold,
                 degraded_threshold=:degraded_threshold,critical_threshold=:critical_threshold,
                 minimum_sample_count=:minimum_sample_count,updated_by_user_id=:user_id
             WHERE check_key=:check_key'
        )->execute([
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'comparison' => $comparison,
            'attention_threshold' => $values['attention_threshold'],
            'degraded_threshold' => $values['degraded_threshold'],
            'critical_threshold' => $values['critical_threshold'],
            'minimum_sample_count' => max(0, int_input('minimum_sample_count', 0)),
            'user_id' => $userId,
            'check_key' => $checkKey,
        ]);
        if (function_exists('log_activity')) log_activity('operations_health_policy_updated', 'operations_health_policy', 0, ['check_key' => $checkKey]);
        flash('success', 'Health policy updated.');
        operations_admin_redirect('settings', ['policy' => $checkKey]);
    }

    if ($action === 'operations_generate_report') {
        $reportId = operations_admin_generate_report(int_input('days', 30), $userId);
        if (function_exists('log_activity')) log_activity('operations_report_generated', 'operations_report', $reportId);
        flash('success', 'Aggregate operations report generated.');
        operations_admin_redirect('reports', ['report' => $reportId]);
    }

    throw new RuntimeException('Unsupported Operations request.');
}

function operations_admin_status_chip(string $status): string
{
    return '<span class="operations-chip ' . e($status) . '">' . e(operations_admin_label($status)) . '</span>';
}

function operations_admin_nav(string $active): void
{
    echo '<nav class="operations-nav" aria-label="Operations Analytics">';
    foreach (['overview' => 'Overview','metrics' => 'Metrics','incidents' => 'Incidents','reports' => 'Reports','settings' => 'Settings'] as $key => $label) {
        echo '<a class="' . ($active === $key ? 'active' : '') . '" href="' . e(app_url(operations_admin_url($key))) . '">' . e($label) . '</a>';
    }
    echo '</nav>';
}

function operations_admin_render_overview(): void
{
    $states = operations_admin_health_states();
    $snapshots = operations_admin_latest_snapshots('hour');
    $activeIncidents = operations_admin_incidents(true, 12);
    $runs = operations_admin_worker_runs(6);
    $overall = operations_admin_overall_status($states);
    $statusCounts = array_fill_keys(['healthy','attention','degraded','critical','unknown'], 0);
    foreach ($states as $state) $statusCounts[(string)$state['health_status']]++;

    echo '<div class="operations-stats">';
    foreach ([
        'Active incidents' => count($activeIncidents),
        'Critical checks' => $statusCounts['critical'],
        'Needs attention' => $statusCounts['attention'] + $statusCounts['degraded'],
        'Healthy checks' => $statusCounts['healthy'],
        'Tracked metrics' => count($snapshots),
    ] as $label => $value) echo '<article class="operations-stat"><strong>' . (int)$value . '</strong><span>' . e($label) . '</span></article>';
    echo '</div>';

    echo '<div class="operations-grid"><section class="operations-card"><header class="operations-card-header"><h2>System health</h2><a class="operations-button small" href="' . e(app_url(operations_admin_url('metrics'))) . '">All metrics</a></header><div class="operations-list">';
    if (!$states) echo '<div class="operations-empty">Run the first aggregate collection to evaluate system health.</div>';
    foreach (array_slice($states, 0, 12) as $state) {
        echo '<article class="operations-row"><div><h3>' . e(operations_admin_metric_label((string)$state['metric_key'])) . '</h3><p>' . e(operations_admin_family_label((string)$state['metric_family'])) . ' · ' . e(operations_admin_label((string)$state['reason_code'])) . '</p><div class="operations-row-meta">' . operations_admin_status_chip((string)$state['health_status']) . '<span class="operations-chip">' . e(operations_admin_format_metric((float)($state['observed_value'] ?? 0), str_contains((string)$state['metric_key'], 'minutes') ? 'minutes' : 'count')) . '</span></div></div><a class="operations-button small" href="' . e(operations_admin_family_url((string)$state['metric_family'])) . '">Open source</a></article>';
    }
    echo '</div></section><aside class="operations-card"><header class="operations-card-header"><h2>Collection status</h2></header><div class="operations-card-body">';
    echo '<div class="operations-overall ' . e($overall) . '"><span>Overall POD health</span><strong>' . e(operations_admin_label($overall)) . '</strong></div>';
    echo '<p class="operations-help">This workspace contains aggregate operational metadata only. Canonical messages, contacts, calls, posts, notifications, and credentials remain in their source systems.</p>';
    echo '<form method="post" class="operations-inline">' . csrf_field() . '<input type="hidden" name="action" value="operations_run_hourly"><button class="operations-button primary" type="submit">Collect completed hour</button></form>';
    echo '<a class="operations-button" href="' . e(app_url(operations_admin_url('overview', ['export' => 'csv']))) . '">Export aggregate CSV</a>';
    echo '</div></aside></div>';

    echo '<div class="operations-grid"><section class="operations-card"><header class="operations-card-header"><h2>Active incidents</h2><a class="operations-button small" href="' . e(app_url(operations_admin_url('incidents'))) . '">Incident history</a></header><div class="operations-list">';
    if (!$activeIncidents) echo '<div class="operations-empty">No active operational incidents.</div>';
    foreach ($activeIncidents as $incident) {
        echo '<article class="operations-row"><div><h3>' . e(operations_admin_metric_label((string)$incident['metric_key'])) . '</h3><p>' . e(operations_admin_label((string)$incident['reason_code'])) . ' · opened ' . e(format_datetime((string)$incident['opened_at'])) . '</p><div class="operations-row-meta">' . operations_admin_status_chip((string)$incident['highest_status']) . '<span class="operations-chip">' . (int)$incident['occurrence_count'] . ' observations</span></div></div><a class="operations-button small" href="' . e(operations_admin_family_url((string)$incident['metric_family'])) . '">Investigate</a></article>';
    }
    echo '</div></section><aside class="operations-card"><header class="operations-card-header"><h2>Recent collector runs</h2></header><div class="operations-list compact">';
    if (!$runs) echo '<div class="operations-empty">No collector runs yet.</div>';
    foreach ($runs as $run) {
        echo '<article class="operations-run"><div><strong>' . e(operations_admin_label((string)$run['window_type'])) . '</strong><span>' . e(format_datetime((string)$run['started_at'])) . '</span></div>' . operations_admin_status_chip((string)$run['status']) . '</article>';
    }
    echo '</div></aside></div>';
}

function operations_admin_render_metrics(): void
{
    $windowType = (string)($_GET['window'] ?? 'hour');
    if (!in_array($windowType, ['hour','day'], true)) $windowType = 'hour';
    $rows = operations_admin_latest_snapshots($windowType);
    $health = [];
    foreach (operations_admin_health_states() as $state) $health[(string)$state['metric_key']] = $state;
    echo '<section class="operations-card"><header class="operations-card-header"><div><h2>' . e(operations_admin_label($windowType)) . ' metrics</h2><p>Latest aggregate snapshot with previous-window comparison.</p></div><div class="operations-actions"><a class="operations-button small" href="' . e(app_url(operations_admin_url('metrics', ['window' => $windowType === 'hour' ? 'day' : 'hour']))) . '">Show ' . ($windowType === 'hour' ? 'daily' : 'hourly') . '</a><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="operations_run_' . e($windowType === 'hour' ? 'hourly' : 'daily') . '"><button class="operations-button primary small" type="submit">Collect now</button></form></div></header><div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Metric</th><th>Family</th><th>Value</th><th>Previous</th><th>Health</th><th>Window</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="6" class="operations-empty">No aggregate snapshots are available.</td></tr>';
    foreach ($rows as $row) {
        $previous = operations_admin_previous_metric((string)$row['metric_key'], $windowType, (string)$row['window_started_at']);
        $state = $health[(string)$row['metric_key']] ?? [];
        echo '<tr><td><strong>' . e(operations_admin_metric_label((string)$row['metric_key'])) . '</strong><small>' . e((string)$row['metric_key']) . '</small></td><td>' . e(operations_admin_family_label((string)$row['metric_family'])) . '</td><td>' . e(operations_admin_format_metric((float)$row['metric_value'], (string)$row['unit'])) . '<small>' . (int)$row['sample_count'] . ' samples</small></td><td>' . ($previous === null ? '—' : e(operations_admin_format_metric($previous, (string)$row['unit']))) . '</td><td>' . operations_admin_status_chip((string)($state['health_status'] ?? 'unknown')) . '</td><td>' . e(format_datetime((string)$row['window_started_at'])) . '</td></tr>';
    }
    echo '</tbody></table></div></section>';
}

function operations_admin_render_incidents(): void
{
    $rows = operations_admin_incidents(false, 200);
    echo '<section class="operations-card"><header class="operations-card-header"><div><h2>Incident history</h2><p>Deterministic threshold breaches and retained recovery evidence.</p></div></header><div class="operations-list">';
    if (!$rows) echo '<div class="operations-empty">No incidents have been recorded.</div>';
    foreach ($rows as $row) {
        $active = empty($row['recovered_at']);
        echo '<article class="operations-row"><div><h3>' . e(operations_admin_metric_label((string)$row['metric_key'])) . '</h3><p>' . e(operations_admin_label((string)$row['reason_code'])) . ' · opened ' . e(format_datetime((string)$row['opened_at'])) . ($active ? '' : ' · recovered ' . e(format_datetime((string)$row['recovered_at']))) . '</p><div class="operations-row-meta">' . operations_admin_status_chip($active ? (string)$row['highest_status'] : 'recovered') . '<span class="operations-chip">' . (int)$row['occurrence_count'] . ' observations</span><span class="operations-chip">' . e(operations_admin_family_label((string)$row['metric_family'])) . '</span></div></div><a class="operations-button small" href="' . e(operations_admin_family_url((string)$row['metric_family'])) . '">Open source</a></article>';
    }
    echo '</div></section>';
}

function operations_admin_render_reports(): void
{
    $reports = operations_admin_reports();
    $selectedId = max(0, (int)($_GET['report'] ?? 0));
    echo '<div class="operations-grid"><section class="operations-card"><header class="operations-card-header"><div><h2>Aggregate reports</h2><p>Owner-generated operational summaries without source content.</p></div></header><div class="operations-list">';
    if (!$reports) echo '<div class="operations-empty">No operations reports have been generated.</div>';
    foreach ($reports as $report) {
        echo '<article class="operations-row"><div><h3>' . e(operations_admin_label((string)$report['frequency'])) . ' operations report</h3><p>' . e(format_datetime((string)$report['window_started_at'])) . ' through ' . e(format_datetime((string)$report['window_ended_at'])) . '</p><div class="operations-row-meta">' . operations_admin_status_chip((string)$report['status']) . '</div></div><a class="operations-button small" href="' . e(app_url(operations_admin_url('reports', ['report' => (int)$report['id']]))) . '">View</a></article>';
    }
    echo '</div></section><aside class="operations-card"><header class="operations-card-header"><h2>Generate report</h2></header><div class="operations-card-body"><form class="operations-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="operations_generate_report"><label>Aggregate window<select name="days"><option value="1">Last day</option><option value="7">Last 7 days</option><option value="30" selected>Last 30 days</option><option value="90">Last 90 days</option></select></label><button class="operations-button primary" type="submit">Generate report</button></form></div></aside></div>';
    if ($selectedId > 0) {
        foreach ($reports as $report) {
            if ((int)$report['id'] !== $selectedId) continue;
            $summary = json_decode((string)($report['summary_json'] ?? ''), true);
            if (!is_array($summary)) $summary = [];
            echo '<section class="operations-card"><header class="operations-card-header"><h2>Report #' . $selectedId . '</h2>' . operations_admin_status_chip((string)$report['status']) . '</header><div class="operations-card-body"><p class="operations-help">Aggregate-only report, version ' . e((string)($summary['version'] ?? 'v66L')) . '. It contains no source message bodies, notes, transcripts, credentials, or private knowledge.</p><div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Metric</th><th>Family</th><th>Average</th><th>Maximum</th><th>Windows</th></tr></thead><tbody>';
            foreach ((array)($summary['metrics'] ?? []) as $metric) {
                echo '<tr><td>' . e(operations_admin_metric_label((string)($metric['metric_key'] ?? ''))) . '</td><td>' . e(operations_admin_family_label((string)($metric['metric_family'] ?? ''))) . '</td><td>' . e(operations_admin_format_metric((float)($metric['average_value'] ?? 0), (string)($metric['unit'] ?? 'count'))) . '</td><td>' . e(operations_admin_format_metric((float)($metric['maximum_value'] ?? 0), (string)($metric['unit'] ?? 'count'))) . '</td><td>' . (int)($metric['window_count'] ?? 0) . '</td></tr>';
            }
            echo '</tbody></table></div></div></section>';
            break;
        }
    }
}

function operations_admin_render_settings(): void
{
    $settings = operations_analytics_settings();
    $policies = operations_analytics_schema_available()
        ? db()->query('SELECT * FROM operations_health_policies ORDER BY check_key')->fetchAll()
        : [];
    echo '<div class="operations-grid"><section class="operations-card"><header class="operations-card-header"><h2>Collection settings</h2></header><div class="operations-card-body"><form class="operations-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="operations_save_settings"><label class="operations-check"><input type="checkbox" name="enabled" value="1"' . ($settings['enabled'] ? ' checked' : '') . '><span>Enable scheduled aggregate collection</span></label><label>Hourly retention days<input name="hourly_retention_days" type="number" min="7" max="730" value="' . (int)$settings['hourly_retention_days'] . '"></label><label>Daily retention days<input name="daily_retention_days" type="number" min="30" max="3650" value="' . (int)$settings['daily_retention_days'] . '"></label><label>Recovered incident retention days<input name="incident_retention_days" type="number" min="30" max="3650" value="' . (int)$settings['incident_retention_days'] . '"></label><label>Report retention days<input name="report_retention_days" type="number" min="30" max="3650" value="' . (int)$settings['report_retention_days'] . '"></label><button class="operations-button primary" type="submit">Save settings</button></form></div></section><aside class="operations-card"><header class="operations-card-header"><h2>Authority boundary</h2></header><div class="operations-card-body"><p class="operations-help">Analytics reads allowlisted operational columns and stores aggregate numbers only. It cannot send, publish, purchase, delete, alter canonical source records, or execute HomeServer tools.</p><p class="operations-help">The worker remains disabled until explicitly enabled and scheduled.</p></div></aside></div>';
    echo '<section class="operations-card"><header class="operations-card-header"><div><h2>Health policies</h2><p>Thresholds are deterministic and evaluated against aggregate snapshots.</p></div></header><div class="operations-policy-grid">';
    foreach ($policies as $policy) {
        echo '<form class="operations-policy" method="post">' . csrf_field() . '<input type="hidden" name="action" value="operations_save_policy"><input type="hidden" name="check_key" value="' . e((string)$policy['check_key']) . '"><header><strong>' . e(operations_admin_metric_label((string)$policy['check_key'])) . '</strong><code>' . e((string)$policy['check_key']) . '</code></header><label class="operations-check"><input type="checkbox" name="enabled" value="1"' . (!empty($policy['enabled']) ? ' checked' : '') . '><span>Enabled</span></label><label>Comparison<select name="comparison"><option value="greater_or_equal"' . ((string)$policy['comparison'] === 'greater_or_equal' ? ' selected' : '') . '>Value at or above</option><option value="less_or_equal"' . ((string)$policy['comparison'] === 'less_or_equal' ? ' selected' : '') . '>Value at or below</option></select></label><div class="operations-thresholds"><label>Attention<input name="attention_threshold" type="number" min="0" step="0.01" value="' . e((string)$policy['attention_threshold']) . '"></label><label>Degraded<input name="degraded_threshold" type="number" min="0" step="0.01" value="' . e((string)$policy['degraded_threshold']) . '"></label><label>Critical<input name="critical_threshold" type="number" min="0" step="0.01" value="' . e((string)$policy['critical_threshold']) . '"></label></div><label>Minimum samples<input name="minimum_sample_count" type="number" min="0" value="' . (int)$policy['minimum_sample_count'] . '"></label><button class="operations-button" type="submit">Save policy</button></form>';
    }
    echo '</div></section>';
}

function operations_render_admin(array $user): void
{
    $section = (string)($_GET['section'] ?? 'overview');
    if (!in_array($section, ['overview','metrics','incidents','reports','settings'], true)) $section = 'overview';
    $settings = operations_analytics_settings();
    $states = operations_admin_health_states();
    $overall = operations_admin_overall_status($states);
    echo '<main class="operations-shell"><section class="operations-hero"><div><p class="operations-eyebrow">Section 66L</p><h1>POD Operations</h1><p>Aggregate health, queue pressure, delivery performance, incident recovery, and owner reports across the POD.</p></div><div class="operations-mode ' . e($settings['enabled'] ? $overall : 'off') . '">' . e($settings['enabled'] ? operations_admin_label($overall) : 'Collection off') . '</div></section>';
    operations_admin_nav($section);
    if (!operations_analytics_schema_available()) {
        echo '<div class="operations-warning"><strong>Database migration required.</strong> Import <code>database/operations_analytics_v66l.sql</code> before using this workspace.</div></main>';
        return;
    }
    match ($section) {
        'metrics' => operations_admin_render_metrics(),
        'incidents' => operations_admin_render_incidents(),
        'reports' => operations_admin_render_reports(),
        'settings' => operations_admin_render_settings(),
        default => operations_admin_render_overview(),
    };
    echo '</main>';
}

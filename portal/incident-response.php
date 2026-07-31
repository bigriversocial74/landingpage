<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-incident-response-runbooks-v66M */

require_once __DIR__ . '/operations-analytics-extensions.php';

function recovery_table_exists(string $table): bool
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

function recovery_schema_available(): bool
{
    foreach ([
        'recovery_settings',
        'recovery_runbooks',
        'recovery_runbook_versions',
        'recovery_recommendations',
        'recovery_simulations',
        'recovery_approvals',
        'recovery_executions',
        'recovery_execution_steps',
        'recovery_action_receipts',
    ] as $table) {
        if (!recovery_table_exists($table)) return false;
    }
    return operations_analytics_schema_available();
}

function recovery_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function recovery_json_encode(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function recovery_json_decode(?string $value, mixed $fallback = []): mixed
{
    if ($value === null || trim($value) === '') return $fallback;
    try {
        return json_decode($value, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return $fallback;
    }
}

function recovery_settings(): array
{
    $defaults = [
        'enabled' => false,
        'dry_run' => true,
        'emergency_disabled' => false,
        'worker_batch_size' => 10,
        'approval_expiry_minutes' => 60,
        'simulation_ttl_minutes' => 30,
        'execution_lease_seconds' => 300,
        'execution_retention_days' => 365,
    ];
    if (!recovery_table_exists('recovery_settings')) return $defaults;
    try {
        $row = db()->query('SELECT * FROM recovery_settings WHERE id=1 LIMIT 1')->fetch();
        if (!$row) return $defaults;
        return [
            'enabled' => (bool)$row['enabled'],
            'dry_run' => (bool)$row['dry_run'],
            'emergency_disabled' => (bool)$row['emergency_disabled'],
            'worker_batch_size' => max(1, min(25, (int)$row['worker_batch_size'])),
            'approval_expiry_minutes' => max(5, min(1440, (int)$row['approval_expiry_minutes'])),
            'simulation_ttl_minutes' => max(5, min(240, (int)$row['simulation_ttl_minutes'])),
            'execution_lease_seconds' => max(60, min(1800, (int)$row['execution_lease_seconds'])),
            'execution_retention_days' => max(30, min(3650, (int)$row['execution_retention_days'])),
        ];
    } catch (Throwable) {
        return $defaults;
    }
}

function recovery_save_settings(array $values, int $userId): void
{
    if (!recovery_schema_available()) throw new RuntimeException('Import database/incident_response_runbooks_v66m.sql first.');
    db()->prepare(
        'UPDATE recovery_settings SET enabled=:enabled,dry_run=:dry_run,emergency_disabled=:emergency_disabled,
         worker_batch_size=:worker_batch_size,approval_expiry_minutes=:approval_expiry_minutes,
         simulation_ttl_minutes=:simulation_ttl_minutes,execution_lease_seconds=:execution_lease_seconds,
         execution_retention_days=:execution_retention_days,updated_by_user_id=:updated_by_user_id WHERE id=1'
    )->execute([
        'enabled' => !empty($values['enabled']) ? 1 : 0,
        'dry_run' => !empty($values['dry_run']) ? 1 : 0,
        'emergency_disabled' => !empty($values['emergency_disabled']) ? 1 : 0,
        'worker_batch_size' => max(1, min(25, (int)($values['worker_batch_size'] ?? 10))),
        'approval_expiry_minutes' => max(5, min(1440, (int)($values['approval_expiry_minutes'] ?? 60))),
        'simulation_ttl_minutes' => max(5, min(240, (int)($values['simulation_ttl_minutes'] ?? 30))),
        'execution_lease_seconds' => max(60, min(1800, (int)($values['execution_lease_seconds'] ?? 300))),
        'execution_retention_days' => max(30, min(3650, (int)($values['execution_retention_days'] ?? 365))),
        'updated_by_user_id' => $userId > 0 ? $userId : null,
    ]);
}

function recovery_runbook_catalog(): array
{
    return [
        'notification_queue_recovery' => [
            'name' => 'Recover Notification Delivery queue',
            'description' => 'Release expired leases, requeue retryable failures, and process a bounded delivery batch.',
            'family' => 'notification_delivery', 'impact' => 'medium', 'approval_required' => true,
            'cooldown_minutes' => 10, 'max_concurrency' => 1, 'max_attempts' => 3,
            'verify_check' => 'notification.queue.depth',
            'steps' => [
                ['key' => 'release_expired', 'handler' => 'notification.release_expired', 'input' => ['limit' => 25]],
                ['key' => 'retry_failed', 'handler' => 'notification.retry_failed', 'input' => ['limit' => 25]],
                ['key' => 'process_batch', 'handler' => 'notification.process_batch', 'input' => ['limit' => 25]],
            ],
        ],
        'activitypub_delivery_recovery' => [
            'name' => 'Recover ActivityPub delivery queue',
            'description' => 'Requeue failed federation deliveries and process a bounded signed-delivery batch.',
            'family' => 'activitypub', 'impact' => 'medium', 'approval_required' => true,
            'cooldown_minutes' => 10, 'max_concurrency' => 1, 'max_attempts' => 3,
            'verify_check' => 'activitypub.delivery.depth',
            'steps' => [
                ['key' => 'retry_failed', 'handler' => 'activitypub.retry_failed', 'input' => ['limit' => 25]],
                ['key' => 'process_batch', 'handler' => 'activitypub.process_batch', 'input' => ['limit' => 20]],
            ],
        ],
        'websub_delivery_recovery' => [
            'name' => 'Recover WebSub delivery queue',
            'description' => 'Requeue failed WebSub deliveries and process a bounded delivery batch.',
            'family' => 'syndication', 'impact' => 'medium', 'approval_required' => true,
            'cooldown_minutes' => 10, 'max_concurrency' => 1, 'max_attempts' => 3,
            'verify_check' => 'syndication.websub.depth',
            'steps' => [
                ['key' => 'retry_failed', 'handler' => 'websub.retry_failed', 'input' => ['limit' => 25]],
                ['key' => 'process_batch', 'handler' => 'websub.process_batch', 'input' => ['limit' => 20]],
            ],
        ],
        'automation_event_recovery' => [
            'name' => 'Recover Automation Rules processing',
            'description' => 'Recover interrupted approvals, requeue retryable events, and process a bounded automation batch.',
            'family' => 'automation', 'impact' => 'high', 'approval_required' => true,
            'cooldown_minutes' => 15, 'max_concurrency' => 1, 'max_attempts' => 2,
            'verify_check' => 'automation.event.depth',
            'steps' => [
                ['key' => 'recover_approvals', 'handler' => 'automation.recover_approvals', 'input' => []],
                ['key' => 'retry_failed', 'handler' => 'automation.retry_failed', 'input' => ['limit' => 25]],
                ['key' => 'process_batch', 'handler' => 'automation.process_batch', 'input' => ['limit' => 25]],
            ],
        ],
        'feed_source_recovery' => [
            'name' => 'Requeue failed Feed Reader sources',
            'description' => 'Return a bounded set of failed feed sources to the existing scheduled refresh queue.',
            'family' => 'feed_reader', 'impact' => 'medium', 'approval_required' => true,
            'cooldown_minutes' => 30, 'max_concurrency' => 1, 'max_attempts' => 3,
            'verify_check' => 'feed.source.error_depth',
            'steps' => [
                ['key' => 'requeue_failed', 'handler' => 'feed.requeue_failed', 'input' => ['limit' => 25]],
            ],
        ],
        'operations_window_rebuild' => [
            'name' => 'Rebuild Operations Analytics window',
            'description' => 'Idempotently rebuild one bounded hourly or daily aggregate window and re-evaluate health.',
            'family' => 'operations', 'impact' => 'low', 'approval_required' => false,
            'cooldown_minutes' => 5, 'max_concurrency' => 1, 'max_attempts' => 3,
            'verify_check' => 'operations.collector.last_success_minutes',
            'steps' => [
                ['key' => 'rebuild_window', 'handler' => 'operations.rebuild_window', 'input' => ['window_type' => 'hour']],
            ],
        ],
        'vp3_license_refresh' => [
            'name' => 'Refresh VP3 license state',
            'description' => 'Run the retained signed entitlement validation, heartbeat, and storage measurement boundary.',
            'family' => 'vp3_license', 'impact' => 'high', 'approval_required' => true,
            'cooldown_minutes' => 30, 'max_concurrency' => 1, 'max_attempts' => 2,
            'verify_check' => 'vp3.license.status_risk',
            'steps' => [
                ['key' => 'validate_license', 'handler' => 'vp3.license_refresh', 'input' => []],
            ],
        ],
        'vp3_update_check' => [
            'name' => 'Check for VP3 POD updates',
            'description' => 'Run a signed update-availability check only. Installation, preparation, and rollback are prohibited.',
            'family' => 'vp3_updates', 'impact' => 'medium', 'approval_required' => true,
            'cooldown_minutes' => 30, 'max_concurrency' => 1, 'max_attempts' => 2,
            'verify_check' => 'vp3.update.job_depth',
            'steps' => [
                ['key' => 'check_updates', 'handler' => 'vp3.update_check', 'input' => []],
            ],
        ],
        'incident_escalation' => [
            'name' => 'Escalate unresolved incident',
            'description' => 'Create an administrator notification through the retained Notification Delivery pipeline.',
            'family' => 'operations', 'impact' => 'low', 'approval_required' => false,
            'cooldown_minutes' => 60, 'max_concurrency' => 1, 'max_attempts' => 3,
            'verify_check' => null,
            'steps' => [
                ['key' => 'notify_owner', 'handler' => 'incident.escalate', 'input' => []],
            ],
        ],
    ];
}

function recovery_definition_payload(string $key, array $definition): array
{
    return [
        'version' => 1,
        'runbook_key' => $key,
        'name' => (string)$definition['name'],
        'description' => (string)$definition['description'],
        'family' => (string)$definition['family'],
        'impact' => (string)$definition['impact'],
        'approval_required' => (bool)$definition['approval_required'],
        'cooldown_minutes' => (int)$definition['cooldown_minutes'],
        'max_concurrency' => (int)$definition['max_concurrency'],
        'max_attempts' => (int)$definition['max_attempts'],
        'verify_check' => $definition['verify_check'],
        'steps' => array_values($definition['steps']),
        'authority' => [
            'allowlisted_handlers_only' => true,
            'arbitrary_shell' => false,
            'arbitrary_sql' => false,
            'caller_urls' => false,
            'publishing' => false,
            'payments' => false,
            'software_install' => false,
            'homeserver_tool_execution' => false,
        ],
    ];
}

function recovery_sync_catalog(?int $userId = null): array
{
    if (!recovery_schema_available()) throw new RuntimeException('Import database/incident_response_runbooks_v66m.sql first.');
    $synced = 0;
    $versioned = 0;
    foreach (recovery_runbook_catalog() as $key => $definition) {
        $payload = recovery_definition_payload($key, $definition);
        $json = recovery_json_encode($payload);
        $hash = hash('sha256', $json);
        $uuidHash = hash('sha256', 'nmm-recovery-runbook:' . $key);
        $uuid = substr($uuidHash, 0, 8) . '-' . substr($uuidHash, 8, 4) . '-4'
            . substr($uuidHash, 13, 3) . '-8' . substr($uuidHash, 17, 3) . '-' . substr($uuidHash, 20, 12);
        db()->prepare(
            'INSERT INTO recovery_runbooks
             (runbook_uuid,runbook_key,name,description,metric_family,impact,status,approval_required,
              cooldown_minutes,max_concurrency,max_attempts,current_version,created_by_user_id,updated_by_user_id)
             VALUES
             (:runbook_uuid,:runbook_key,:name,:description,:metric_family,:impact,"active",:approval_required,
              :cooldown_minutes,:max_concurrency,:max_attempts,0,:created_by_user_id,:updated_by_user_id)
             ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),metric_family=VALUES(metric_family),
              impact=VALUES(impact),approval_required=VALUES(approval_required),cooldown_minutes=VALUES(cooldown_minutes),
              max_concurrency=VALUES(max_concurrency),max_attempts=VALUES(max_attempts),updated_by_user_id=VALUES(updated_by_user_id)'
        )->execute([
            'runbook_uuid' => $uuid,
            'runbook_key' => $key,
            'name' => mb_substr((string)$definition['name'], 0, 190),
            'description' => mb_substr((string)$definition['description'], 0, 1000),
            'metric_family' => (string)$definition['family'],
            'impact' => (string)$definition['impact'],
            'approval_required' => !empty($definition['approval_required']) ? 1 : 0,
            'cooldown_minutes' => max(0, min(1440, (int)$definition['cooldown_minutes'])),
            'max_concurrency' => max(1, min(10, (int)$definition['max_concurrency'])),
            'max_attempts' => max(1, min(10, (int)$definition['max_attempts'])),
            'created_by_user_id' => $userId && $userId > 0 ? $userId : null,
            'updated_by_user_id' => $userId && $userId > 0 ? $userId : null,
        ]);
        $runbookStatement = db()->prepare('SELECT * FROM recovery_runbooks WHERE runbook_key=:runbook_key LIMIT 1');
        $runbookStatement->execute(['runbook_key' => $key]);
        $runbook = $runbookStatement->fetch();
        if (!$runbook) throw new RuntimeException('Runbook catalog synchronization failed.');
        $versionStatement = db()->prepare(
            'SELECT * FROM recovery_runbook_versions WHERE runbook_id=:runbook_id AND definition_hash=:definition_hash LIMIT 1'
        );
        $versionStatement->execute(['runbook_id' => (int)$runbook['id'], 'definition_hash' => $hash]);
        $version = $versionStatement->fetch();
        if (!$version) {
            $next = (int)db()->query('SELECT COALESCE(MAX(version_number),0)+1 FROM recovery_runbook_versions WHERE runbook_id=' . (int)$runbook['id'])->fetchColumn();
            db()->prepare(
                'INSERT INTO recovery_runbook_versions
                 (runbook_id,version_number,definition_hash,definition_json,created_by_user_id)
                 VALUES (:runbook_id,:version_number,:definition_hash,:definition_json,:created_by_user_id)'
            )->execute([
                'runbook_id' => (int)$runbook['id'],
                'version_number' => $next,
                'definition_hash' => $hash,
                'definition_json' => $json,
                'created_by_user_id' => $userId && $userId > 0 ? $userId : null,
            ]);
            $versionNumber = $next;
            $versioned++;
        } else {
            $versionNumber = (int)$version['version_number'];
        }
        db()->prepare('UPDATE recovery_runbooks SET current_version=:current_version WHERE id=:id')->execute([
            'current_version' => $versionNumber,
            'id' => (int)$runbook['id'],
        ]);
        $synced++;
    }
    return ['runbooks_synced' => $synced, 'versions_created' => $versioned];
}

function recovery_runbooks(): array
{
    if (!recovery_schema_available()) return [];
    return db()->query(
        'SELECT runbook.*,version.id AS version_id,version.definition_hash,version.definition_json
         FROM recovery_runbooks runbook
         LEFT JOIN recovery_runbook_versions version
           ON version.runbook_id=runbook.id AND version.version_number=runbook.current_version
         ORDER BY runbook.metric_family,runbook.name,runbook.id'
    )->fetchAll();
}

function recovery_runbook(int $runbookId): ?array
{
    if ($runbookId <= 0 || !recovery_schema_available()) return null;
    $statement = db()->prepare(
        'SELECT runbook.*,version.id AS version_id,version.definition_hash,version.definition_json
         FROM recovery_runbooks runbook
         LEFT JOIN recovery_runbook_versions version
           ON version.runbook_id=runbook.id AND version.version_number=runbook.current_version
         WHERE runbook.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $runbookId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function recovery_incident(int $incidentId): ?array
{
    if ($incidentId <= 0 || !operations_analytics_schema_available()) return null;
    $statement = db()->prepare('SELECT * FROM operations_health_incidents WHERE id=:id LIMIT 1');
    $statement->execute(['id' => $incidentId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function recovery_runbook_key_for_incident(array $incident): string
{
    $family = (string)($incident['metric_family'] ?? '');
    $check = (string)($incident['check_key'] ?? '');
    return match ($family) {
        'notification_delivery' => 'notification_queue_recovery',
        'activitypub' => 'activitypub_delivery_recovery',
        'syndication' => 'websub_delivery_recovery',
        'automation' => 'automation_event_recovery',
        'feed_reader' => 'feed_source_recovery',
        'vp3_license' => 'vp3_license_refresh',
        'vp3_updates' => 'vp3_update_check',
        'operations' => str_contains($check, 'collector') ? 'operations_window_rebuild' : 'incident_escalation',
        default => 'incident_escalation',
    };
}

function recovery_refresh_recommendations(): int
{
    if (!recovery_schema_available()) return 0;
    $incidents = db()->query(
        'SELECT * FROM operations_health_incidents WHERE recovered_at IS NULL ORDER BY highest_status DESC,last_seen_at DESC,id DESC'
    )->fetchAll();
    $inserted = 0;
    foreach ($incidents as $incident) {
        $key = recovery_runbook_key_for_incident($incident);
        $statement = db()->prepare('SELECT id FROM recovery_runbooks WHERE runbook_key=:runbook_key AND status="active" LIMIT 1');
        $statement->execute(['runbook_key' => $key]);
        $runbookId = (int)$statement->fetchColumn();
        if ($runbookId <= 0) continue;
        $priority = match ((string)$incident['highest_status']) {
            'critical' => 10,
            'degraded' => 20,
            default => 30,
        };
        $write = db()->prepare(
            'INSERT INTO recovery_recommendations
             (incident_id,runbook_id,status,reason_code,priority_order)
             VALUES (:incident_id,:runbook_id,"recommended",:reason_code,:priority_order)
             ON DUPLICATE KEY UPDATE reason_code=VALUES(reason_code),priority_order=VALUES(priority_order),
              status=IF(status="superseded","recommended",status)'
        );
        $write->execute([
            'incident_id' => (int)$incident['id'],
            'runbook_id' => $runbookId,
            'reason_code' => 'family_' . ((string)$incident['metric_family'] ?: 'unknown'),
            'priority_order' => $priority,
        ]);
        $inserted += $write->rowCount() > 0 ? 1 : 0;
    }
    db()->exec(
        'UPDATE recovery_recommendations recommendation
         JOIN operations_health_incidents incident ON incident.id=recommendation.incident_id
         SET recommendation.status="superseded"
         WHERE incident.recovered_at IS NOT NULL AND recommendation.status IN ("recommended","accepted")'
    );
    return $inserted;
}

function recovery_recommendations(bool $activeOnly = true): array
{
    if (!recovery_schema_available()) return [];
    $where = $activeOnly ? 'WHERE incident.recovered_at IS NULL AND recommendation.status IN ("recommended","accepted")' : '';
    return db()->query(
        'SELECT recommendation.*,incident.incident_uuid,incident.check_key,incident.metric_key,incident.metric_family,
                incident.highest_status,incident.reason_code AS incident_reason_code,incident.opened_at,incident.last_seen_at,
                incident.recovered_at,runbook.name AS runbook_name,runbook.impact,runbook.approval_required,
                runbook.current_version,runbook.status AS runbook_status
         FROM recovery_recommendations recommendation
         JOIN operations_health_incidents incident ON incident.id=recommendation.incident_id
         JOIN recovery_runbooks runbook ON runbook.id=recommendation.runbook_id
         ' . $where . '
         ORDER BY recommendation.priority_order,incident.last_seen_at DESC,recommendation.id DESC'
    )->fetchAll();
}

function recovery_bounded_input(string $handler, array $input): array
{
    return match ($handler) {
        'notification.release_expired', 'notification.retry_failed', 'notification.process_batch',
        'activitypub.retry_failed', 'activitypub.process_batch',
        'websub.retry_failed', 'websub.process_batch',
        'automation.retry_failed', 'automation.process_batch',
        'feed.requeue_failed' => ['limit' => max(1, min(25, (int)($input['limit'] ?? 10)))],
        'operations.rebuild_window' => ['window_type' => in_array(($input['window_type'] ?? 'hour'), ['hour','day'], true) ? $input['window_type'] : 'hour'],
        default => [],
    };
}

function recovery_handler_preview(string $handler, array $input, array $incident): array
{
    $input = recovery_bounded_input($handler, $input);
    $limit = (int)($input['limit'] ?? 0);
    $count = 0;
    $available = true;
    switch ($handler) {
        case 'notification.release_expired':
            $available = recovery_table_exists('notification_delivery_queue');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM notification_delivery_queue WHERE status='leased' AND leased_until<UTC_TIMESTAMP() ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'notification.retry_failed':
            $available = recovery_table_exists('notification_delivery_queue');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM notification_delivery_queue WHERE status='failed' AND attempt_count<max_attempts ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'notification.process_batch':
            $available = recovery_table_exists('notification_delivery_queue');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM notification_delivery_queue WHERE status='pending' AND available_at<=UTC_TIMESTAMP() ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'activitypub.retry_failed':
            $available = recovery_table_exists('activitypub_deliveries');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM activitypub_deliveries WHERE status='failed' AND attempt_count<10 ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'activitypub.process_batch':
            $available = recovery_table_exists('activitypub_deliveries');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM activitypub_deliveries WHERE status='pending' AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()) ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'websub.retry_failed':
            $available = recovery_table_exists('syndication_websub_deliveries');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM syndication_websub_deliveries WHERE status='failed' AND attempt_count<10 ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'websub.process_batch':
            $available = recovery_table_exists('syndication_websub_deliveries');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM syndication_websub_deliveries WHERE status='pending' AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()) ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'automation.recover_approvals':
            $available = recovery_table_exists('automation_approvals');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM automation_approvals WHERE status='approved' AND resolved_at IS NOT NULL")->fetchColumn();
            break;
        case 'automation.retry_failed':
            $available = recovery_table_exists('automation_events');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM automation_events WHERE status='failed' AND attempt_count<max_attempts ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'automation.process_batch':
            $available = recovery_table_exists('automation_events');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM automation_events WHERE status='pending' AND available_at<=UTC_TIMESTAMP() ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'feed.requeue_failed':
            $available = recovery_table_exists('feed_sources');
            if ($available) $count = (int)db()->query("SELECT COUNT(*) FROM (SELECT id FROM feed_sources WHERE status='error' ORDER BY id LIMIT {$limit}) q")->fetchColumn();
            break;
        case 'operations.rebuild_window':
            $available = operations_analytics_schema_available();
            $count = $available ? 1 : 0;
            break;
        case 'vp3.license_refresh':
            $available = recovery_table_exists('vp3_license_configuration');
            $count = $available ? 1 : 0;
            break;
        case 'vp3.update_check':
            $available = recovery_table_exists('vp3_update_jobs');
            $count = $available ? 1 : 0;
            break;
        case 'incident.escalate':
            $available = recovery_table_exists('portal_notifications');
            $count = $available ? 1 : 0;
            break;
        default:
            throw new RuntimeException('Recovery handler is not allowlisted.');
    }
    return [
        'handler' => $handler,
        'available' => $available,
        'candidate_count' => $count,
        'bounded_input' => $input,
        'incident_id' => (int)$incident['id'],
        'aggregate_only' => true,
    ];
}

function recovery_simulate(int $incidentId, int $runbookId, int $userId, array $overrides = []): array
{
    if (!recovery_schema_available()) throw new RuntimeException('Import database/incident_response_runbooks_v66m.sql first.');
    recovery_sync_catalog($userId);
    $incident = recovery_incident($incidentId);
    if (!$incident || !empty($incident['recovered_at'])) throw new RuntimeException('The selected incident is not active.');
    $runbook = recovery_runbook($runbookId);
    if (!$runbook || (string)$runbook['status'] !== 'active' || empty($runbook['version_id'])) throw new RuntimeException('The selected runbook is not active.');
    $definition = recovery_json_decode((string)$runbook['definition_json'], []);
    if (!is_array($definition) || empty($definition['steps'])) throw new RuntimeException('The current runbook version is invalid.');
    $steps = [];
    foreach (array_values($definition['steps']) as $index => $step) {
        $handler = (string)($step['handler'] ?? '');
        $input = is_array($step['input'] ?? null) ? $step['input'] : [];
        if ($handler === 'operations.rebuild_window' && isset($overrides['window_type'])) {
            $input['window_type'] = $overrides['window_type'];
        }
        $preview = recovery_handler_preview($handler, $input, $incident);
        $steps[] = [
            'index' => $index,
            'step_key' => (string)($step['key'] ?? ('step_' . $index)),
            'handler' => $handler,
            'input' => $preview['bounded_input'],
            'available' => $preview['available'],
            'candidate_count' => $preview['candidate_count'],
        ];
    }
    $plan = [
        'incident' => [
            'id' => (int)$incident['id'],
            'check_key' => (string)$incident['check_key'],
            'metric_family' => (string)$incident['metric_family'],
            'highest_status' => (string)$incident['highest_status'],
        ],
        'runbook' => [
            'id' => (int)$runbook['id'],
            'key' => (string)$runbook['runbook_key'],
            'version' => (int)$runbook['current_version'],
            'definition_hash' => (string)$runbook['definition_hash'],
            'impact' => (string)$runbook['impact'],
            'approval_required' => (bool)$runbook['approval_required'],
        ],
        'steps' => $steps,
        'verify_check' => $definition['verify_check'] ?? null,
        'authority' => $definition['authority'] ?? [],
    ];
    $inputJson = recovery_json_encode(['window_type' => $overrides['window_type'] ?? null]);
    $hash = hash('sha256', (string)$runbook['definition_hash'] . '|' . $incidentId . '|' . $inputJson . '|' . recovery_json_encode($plan));
    $plan['generated_at'] = gmdate(DATE_ATOM);
    $planJson = recovery_json_encode($plan);
    $settings = recovery_settings();
    $expiresAt = gmdate('Y-m-d H:i:s', time() + ((int)$settings['simulation_ttl_minutes'] * 60));
    db()->prepare(
        'UPDATE recovery_simulations SET status="stale"
         WHERE incident_id=:incident_id AND runbook_id=:runbook_id AND status="valid" AND simulation_hash<>:simulation_hash'
    )->execute(['incident_id' => $incidentId, 'runbook_id' => $runbookId, 'simulation_hash' => $hash]);
    db()->prepare(
        'INSERT INTO recovery_simulations
         (simulation_uuid,incident_id,runbook_id,runbook_version_id,simulation_hash,input_json,plan_json,status,expires_at,created_by_user_id)
         VALUES (:simulation_uuid,:incident_id,:runbook_id,:runbook_version_id,:simulation_hash,:input_json,:plan_json,"valid",:expires_at,:created_by_user_id)
         ON DUPLICATE KEY UPDATE plan_json=VALUES(plan_json),input_json=VALUES(input_json),status="valid",expires_at=VALUES(expires_at),
          created_by_user_id=VALUES(created_by_user_id),created_at=UTC_TIMESTAMP()'
    )->execute([
        'simulation_uuid' => recovery_uuid(),
        'incident_id' => $incidentId,
        'runbook_id' => $runbookId,
        'runbook_version_id' => (int)$runbook['version_id'],
        'simulation_hash' => $hash,
        'input_json' => $inputJson,
        'plan_json' => $planJson,
        'expires_at' => $expiresAt,
        'created_by_user_id' => $userId > 0 ? $userId : null,
    ]);
    $statement = db()->prepare(
        'SELECT simulation.*,runbook.name AS runbook_name,runbook.impact,runbook.approval_required
         FROM recovery_simulations simulation JOIN recovery_runbooks runbook ON runbook.id=simulation.runbook_id
         WHERE simulation.incident_id=:incident_id AND simulation.runbook_version_id=:runbook_version_id
           AND simulation.simulation_hash=:simulation_hash LIMIT 1'
    );
    $statement->execute(['incident_id' => $incidentId, 'runbook_version_id' => (int)$runbook['version_id'], 'simulation_hash' => $hash]);
    $simulation = $statement->fetch();
    if (!$simulation) throw new RuntimeException('Recovery simulation could not be persisted.');
    return $simulation;
}

function recovery_simulation(int $simulationId): ?array
{
    if ($simulationId <= 0 || !recovery_schema_available()) return null;
    $statement = db()->prepare(
        'SELECT simulation.*,runbook.name AS runbook_name,runbook.runbook_key,runbook.impact,runbook.approval_required,
                runbook.current_version,version.version_number,version.definition_hash,version.definition_json,
                incident.check_key,incident.metric_family,incident.highest_status,incident.recovered_at
         FROM recovery_simulations simulation
         JOIN recovery_runbooks runbook ON runbook.id=simulation.runbook_id
         JOIN recovery_runbook_versions version ON version.id=simulation.runbook_version_id
         JOIN operations_health_incidents incident ON incident.id=simulation.incident_id
         WHERE simulation.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $simulationId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function recovery_request_approval(int $simulationId, int $userId): ?array
{
    $simulation = recovery_simulation($simulationId);
    if (!$simulation) throw new RuntimeException('Recovery simulation not found.');
    if (!(bool)$simulation['approval_required']) return null;
    if ((string)$simulation['status'] !== 'valid' || strtotime((string)$simulation['expires_at']) <= time()) throw new RuntimeException('The simulation is stale or expired.');
    if (!empty($simulation['recovered_at'])) throw new RuntimeException('The incident has already recovered.');
    $settings = recovery_settings();
    $expiresAt = gmdate('Y-m-d H:i:s', time() + ((int)$settings['approval_expiry_minutes'] * 60));
    $requestHash = hash('sha256', implode('|', [
        (string)$simulation['simulation_hash'],
        (string)$simulation['incident_id'],
        (string)$simulation['runbook_id'],
        (string)$simulation['runbook_version_id'],
        $expiresAt,
    ]));
    db()->prepare(
        'INSERT INTO recovery_approvals
         (approval_uuid,simulation_id,incident_id,runbook_id,runbook_version_id,status,request_hash,requested_by_user_id,expires_at)
         VALUES (:approval_uuid,:simulation_id,:incident_id,:runbook_id,:runbook_version_id,"pending",:request_hash,:requested_by_user_id,:expires_at)
         ON DUPLICATE KEY UPDATE
          request_hash=IF(status IN ("consumed","approved"),request_hash,VALUES(request_hash)),
          requested_by_user_id=IF(status IN ("consumed","approved"),requested_by_user_id,VALUES(requested_by_user_id)),
          expires_at=IF(status IN ("consumed","approved"),expires_at,VALUES(expires_at)),
          status=IF(status IN ("consumed","approved"),status,"pending"),
          resolved_by_user_id=IF(status IN ("consumed","approved"),resolved_by_user_id,NULL),
          resolved_at=IF(status IN ("consumed","approved"),resolved_at,NULL)'
    )->execute([
        'approval_uuid' => recovery_uuid(),
        'simulation_id' => $simulationId,
        'incident_id' => (int)$simulation['incident_id'],
        'runbook_id' => (int)$simulation['runbook_id'],
        'runbook_version_id' => (int)$simulation['runbook_version_id'],
        'request_hash' => $requestHash,
        'requested_by_user_id' => $userId > 0 ? $userId : null,
        'expires_at' => $expiresAt,
    ]);
    $statement = db()->prepare('SELECT * FROM recovery_approvals WHERE simulation_id=:simulation_id LIMIT 1');
    $statement->execute(['simulation_id' => $simulationId]);
    return $statement->fetch() ?: null;
}

function recovery_resolve_approval(int $approvalId, string $decision, int $userId): bool
{
    if (!in_array($decision, ['approved','rejected'], true)) throw new RuntimeException('Unsupported approval decision.');
    $statement = db()->prepare(
        'UPDATE recovery_approvals SET status=:status,resolved_by_user_id=:resolved_by_user_id,resolved_at=UTC_TIMESTAMP()
         WHERE id=:id AND status="pending" AND expires_at>UTC_TIMESTAMP()'
    );
    $statement->execute(['status' => $decision, 'resolved_by_user_id' => $userId > 0 ? $userId : null, 'id' => $approvalId]);
    return $statement->rowCount() === 1;
}

function recovery_expire_evidence(): array
{
    if (!recovery_schema_available()) return ['simulations' => 0, 'approvals' => 0];
    $simulations = db()->exec('UPDATE recovery_simulations SET status="stale" WHERE status="valid" AND expires_at<=UTC_TIMESTAMP()');
    $approvals = db()->exec('UPDATE recovery_approvals SET status="expired",resolved_at=UTC_TIMESTAMP() WHERE status="pending" AND expires_at<=UTC_TIMESTAMP()');
    return ['simulations' => max(0, (int)$simulations), 'approvals' => max(0, (int)$approvals)];
}

function recovery_create_execution_steps(int $executionId, array $definition): void
{
    foreach (array_values((array)($definition['steps'] ?? [])) as $index => $step) {
        $handler = (string)($step['handler'] ?? '');
        $input = recovery_bounded_input($handler, is_array($step['input'] ?? null) ? $step['input'] : []);
        $key = hash('sha256', $executionId . '|' . $index . '|' . $handler . '|' . recovery_json_encode($input));
        db()->prepare(
            'INSERT IGNORE INTO recovery_execution_steps
             (execution_id,step_index,step_key,handler_key,idempotency_key,status,input_json,max_attempts)
             VALUES (:execution_id,:step_index,:step_key,:handler_key,:idempotency_key,"pending",:input_json,:max_attempts)'
        )->execute([
            'execution_id' => $executionId,
            'step_index' => $index,
            'step_key' => mb_substr((string)($step['key'] ?? ('step_' . $index)), 0, 120),
            'handler_key' => $handler,
            'idempotency_key' => $key,
            'input_json' => recovery_json_encode($input),
            'max_attempts' => 3,
        ]);
    }
}

function recovery_queue_execution(int $simulationId, int $userId): array
{
    if (!recovery_schema_available()) throw new RuntimeException('Import database/incident_response_runbooks_v66m.sql first.');
    recovery_expire_evidence();
    $settings = recovery_settings();
    if (!$settings['enabled']) throw new RuntimeException('Recovery execution is disabled.');
    if ($settings['emergency_disabled']) throw new RuntimeException('Recovery emergency disable is active.');
    $simulation = recovery_simulation($simulationId);
    if (!$simulation) throw new RuntimeException('Recovery simulation not found.');
    $idempotencyKey = hash('sha256', implode('|', [
        (string)$simulation['incident_id'],
        (string)$simulation['runbook_id'],
        (string)$simulation['runbook_version_id'],
        (string)$simulation['simulation_hash'],
    ]));
    $existingStatement = db()->prepare('SELECT * FROM recovery_executions WHERE idempotency_key=:idempotency_key LIMIT 1');
    $existingStatement->execute(['idempotency_key' => $idempotencyKey]);
    $existing = $existingStatement->fetch();
    if ($existing) return $existing;
    if ((string)$simulation['status'] !== 'valid' || strtotime((string)$simulation['expires_at']) <= time()) {
        throw new RuntimeException('The recovery simulation is stale or expired.');
    }
    if (!empty($simulation['recovered_at'])) throw new RuntimeException('The incident has already recovered.');
    if ((int)$simulation['version_number'] !== (int)$simulation['current_version']) throw new RuntimeException('The runbook version changed after simulation.');
    $definition = recovery_json_decode((string)$simulation['definition_json'], []);
    if (!is_array($definition)) throw new RuntimeException('The runbook definition is invalid.');
    $approval = null;
    if ((bool)$simulation['approval_required']) {
        $statement = db()->prepare('SELECT * FROM recovery_approvals WHERE simulation_id=:simulation_id LIMIT 1');
        $statement->execute(['simulation_id' => $simulationId]);
        $approval = $statement->fetch();
        if (!$approval || (string)$approval['status'] !== 'approved' || strtotime((string)$approval['expires_at']) <= time()) {
            throw new RuntimeException('A current approved recovery request is required.');
        }
    }
    $cooldown = max(0, min(1440, (int)($definition['cooldown_minutes'] ?? 0)));
    if ($cooldown > 0) {
        $statement = db()->prepare(
            "SELECT COUNT(*) FROM recovery_executions
             WHERE runbook_id=:runbook_id AND incident_id=:incident_id
               AND status IN ('queued','running','verifying','completed','partially_completed')
               AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$cooldown} MINUTE)"
        );
        $statement->execute(['runbook_id' => (int)$simulation['runbook_id'], 'incident_id' => (int)$simulation['incident_id']]);
        if ((int)$statement->fetchColumn() > 0) throw new RuntimeException('The runbook cooldown is still active for this incident.');
    }
    $maxConcurrency = max(1, min(10, (int)($definition['max_concurrency'] ?? 1)));
    $statement = db()->prepare(
        "SELECT COUNT(*) FROM recovery_executions WHERE runbook_id=:runbook_id AND status IN ('queued','running','verifying')"
    );
    $statement->execute(['runbook_id' => (int)$simulation['runbook_id']]);
    if ((int)$statement->fetchColumn() >= $maxConcurrency) throw new RuntimeException('The runbook concurrency limit is active.');

    $status = $settings['dry_run'] ? 'simulated' : 'queued';
    db()->prepare(
        'INSERT INTO recovery_executions
         (execution_uuid,incident_id,runbook_id,runbook_version_id,simulation_id,approval_id,idempotency_key,status,impact,
          requested_by_user_id,max_attempts,verification_status,completed_at)
         VALUES (:execution_uuid,:incident_id,:runbook_id,:runbook_version_id,:simulation_id,:approval_id,:idempotency_key,:status,:impact,
          :requested_by_user_id,:max_attempts,:verification_status,:completed_at)'
    )->execute([
        'execution_uuid' => recovery_uuid(),
        'incident_id' => (int)$simulation['incident_id'],
        'runbook_id' => (int)$simulation['runbook_id'],
        'runbook_version_id' => (int)$simulation['runbook_version_id'],
        'simulation_id' => $simulationId,
        'approval_id' => $approval ? (int)$approval['id'] : null,
        'idempotency_key' => $idempotencyKey,
        'status' => $status,
        'impact' => (string)$simulation['impact'],
        'requested_by_user_id' => $userId > 0 ? $userId : null,
        'max_attempts' => max(1, min(10, (int)($definition['max_attempts'] ?? 3))),
        'verification_status' => $settings['dry_run'] ? 'unknown' : 'pending',
        'completed_at' => $settings['dry_run'] ? gmdate('Y-m-d H:i:s') : null,
    ]);
    $executionId = (int)db()->lastInsertId();
    recovery_create_execution_steps($executionId, $definition);
    if ($settings['dry_run']) {
        $steps = db()->query('SELECT * FROM recovery_execution_steps WHERE execution_id=' . $executionId . ' ORDER BY step_index')->fetchAll();
        foreach ($steps as $step) {
            $input = recovery_json_decode((string)$step['input_json'], []);
            $incident = recovery_incident((int)$simulation['incident_id']) ?: ['id' => (int)$simulation['incident_id']];
            $preview = recovery_handler_preview((string)$step['handler_key'], is_array($input) ? $input : [], $incident);
            db()->prepare('UPDATE recovery_execution_steps SET status="simulated",output_json=:output_json,completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute([
                'output_json' => recovery_json_encode($preview), 'id' => (int)$step['id'],
            ]);
            recovery_write_receipt($executionId, (int)$step['id'], (string)$step['handler_key'], 'simulated', null, $preview);
        }
    }
    db()->prepare('UPDATE recovery_simulations SET status="consumed" WHERE id=:id')->execute(['id' => $simulationId]);
    if ($approval) db()->prepare('UPDATE recovery_approvals SET status="consumed" WHERE id=:id')->execute(['id' => (int)$approval['id']]);
    $statement = db()->prepare('SELECT * FROM recovery_executions WHERE id=:id LIMIT 1');
    $statement->execute(['id' => $executionId]);
    return $statement->fetch() ?: [];
}

function recovery_write_receipt(int $executionId, ?int $stepId, string $actionType, string $status, mixed $before, mixed $after): void
{
    $beforeJson = $before === null ? null : recovery_json_encode($before);
    $afterJson = $after === null ? null : recovery_json_encode($after);
    $hash = hash('sha256', $actionType . '|' . $status . '|' . ($beforeJson ?? '') . '|' . ($afterJson ?? ''));
    db()->prepare(
        'INSERT INTO recovery_action_receipts
         (receipt_uuid,execution_id,step_id,action_type,status,before_json,after_json,result_hash)
         VALUES (:receipt_uuid,:execution_id,:step_id,:action_type,:status,:before_json,:after_json,:result_hash)
         ON DUPLICATE KEY UPDATE status=VALUES(status),before_json=VALUES(before_json),after_json=VALUES(after_json),result_hash=VALUES(result_hash)'
    )->execute([
        'receipt_uuid' => recovery_uuid(),
        'execution_id' => $executionId,
        'step_id' => $stepId,
        'action_type' => mb_substr($actionType, 0, 120),
        'status' => $status,
        'before_json' => $beforeJson,
        'after_json' => $afterJson,
        'result_hash' => $hash,
    ]);
}

function recovery_handler_execute(string $handler, array $input, array $incident): array
{
    $input = recovery_bounded_input($handler, $input);
    $limit = (int)($input['limit'] ?? 0);
    switch ($handler) {
        case 'notification.release_expired':
            $affected = db()->exec(
                "UPDATE notification_delivery_queue SET status='pending',lease_token=NULL,leased_until=NULL,available_at=UTC_TIMESTAMP()
                 WHERE status='leased' AND leased_until<UTC_TIMESTAMP() ORDER BY id LIMIT {$limit}"
            );
            return ['affected' => max(0, (int)$affected)];
        case 'notification.retry_failed':
            $affected = db()->exec(
                "UPDATE notification_delivery_queue SET status='pending',available_at=UTC_TIMESTAMP(),lease_token=NULL,leased_until=NULL,
                 last_error_code=NULL,last_error_message=NULL
                 WHERE status='failed' AND attempt_count<max_attempts ORDER BY id LIMIT {$limit}"
            );
            return ['affected' => max(0, (int)$affected)];
        case 'notification.process_batch':
            require_once __DIR__ . '/notifications.php';
            require_once __DIR__ . '/notification-delivery.php';
            return ['worker' => notification_delivery_run($limit)];
        case 'activitypub.retry_failed':
            $affected = db()->exec(
                "UPDATE activitypub_deliveries SET status='pending',next_attempt_at=UTC_TIMESTAMP(),last_error=NULL
                 WHERE status='failed' AND attempt_count<10 ORDER BY id LIMIT {$limit}"
            );
            return ['affected' => max(0, (int)$affected)];
        case 'activitypub.process_batch':
            require_once __DIR__ . '/activitypub-service.php';
            return ['processed' => activitypub_process_delivery_queue($limit)];
        case 'websub.retry_failed':
            $affected = db()->exec(
                "UPDATE syndication_websub_deliveries SET status='pending',next_attempt_at=UTC_TIMESTAMP(),last_error=NULL
                 WHERE status='failed' AND attempt_count<10 ORDER BY id LIMIT {$limit}"
            );
            return ['affected' => max(0, (int)$affected)];
        case 'websub.process_batch':
            require_once __DIR__ . '/websub-service.php';
            return ['processed' => syndication_process_websub_queue($limit)];
        case 'automation.recover_approvals':
            require_once __DIR__ . '/automation-rules.php';
            require_once __DIR__ . '/automation-recovery.php';
            return ['recovered' => automation_recover_interrupted_approvals_complete()];
        case 'automation.retry_failed':
            $affected = db()->exec(
                "UPDATE automation_events SET status='pending',available_at=UTC_TIMESTAMP(),lease_token=NULL,leased_until=NULL,
                 last_error_code=NULL,last_error_message=NULL
                 WHERE status='failed' AND attempt_count<max_attempts ORDER BY id LIMIT {$limit}"
            );
            return ['affected' => max(0, (int)$affected)];
        case 'automation.process_batch':
            require_once __DIR__ . '/automation-rules.php';
            return ['worker' => automation_run($limit)];
        case 'feed.requeue_failed':
            $affected = db()->exec(
                "UPDATE feed_sources SET status='active',next_refresh_at=UTC_TIMESTAMP(),refresh_lock_until=NULL
                 WHERE status='error' ORDER BY id LIMIT {$limit}"
            );
            return ['affected' => max(0, (int)$affected), 'scheduled_only' => true];
        case 'operations.rebuild_window':
            return ['analytics' => operations_analytics_run_extended((string)$input['window_type'], true)];
        case 'vp3.license_refresh':
            require_once __DIR__ . '/vp3-license-settings-store.php';
            require_once __DIR__ . '/vp3-update-version-override.php';
            require_once __DIR__ . '/vp3-licensing.php';
            $service = vp3_license_service();
            $current = $service->validateNow();
            try { $service->heartbeat(); } catch (Throwable $heartbeatError) { error_log('Recovery VP3 heartbeat failed: ' . $heartbeatError->getMessage()); }
            $storage = $service->storage(true);
            return [
                'license_status' => (string)($current['status'] ?? 'unknown'),
                'connection_state' => (string)($current['connection_state'] ?? 'unknown'),
                'offline_lease_valid' => (bool)($current['offline_lease_valid'] ?? false),
                'storage_warning_state' => (string)($storage['warning_state'] ?? 'unknown'),
            ];
        case 'vp3.update_check':
            require_once __DIR__ . '/vp3-update-core.php';
            $lock = vp3_update_acquire_operation_lock();
            try {
                $agent = new Vp3UpdateAgent();
                return ['check' => $agent->check(null, 'system')];
            } finally {
                vp3_update_release_operation_lock($lock);
            }
        case 'incident.escalate':
            require_once __DIR__ . '/notifications.php';
            notification_create_for_role(
                'admin',
                'system',
                'Recovery incident requires attention',
                'Operations incident ' . (string)($incident['check_key'] ?? ('#' . (int)$incident['id'])) . ' remains unresolved.',
                'portal/recovery-center.php?incident=' . (int)$incident['id'],
                'operations_health_incident',
                (int)$incident['id'],
                in_array((string)($incident['highest_status'] ?? ''), ['critical','degraded'], true) ? 'urgent' : 'high'
            );
            return ['notified_role' => 'admin', 'aggregate_only' => true];
        default:
            throw new RuntimeException('Recovery handler is not allowlisted.');
    }
}

function recovery_recover_expired_leases(): array
{
    if (!recovery_schema_available()) return ['executions' => 0, 'steps' => 0];
    $steps = db()->exec(
        'UPDATE recovery_execution_steps
         SET status=IF(attempt_count<max_attempts,"pending","failed"),lease_token=NULL,leased_until=NULL,
             error_code=IF(attempt_count<max_attempts,NULL,"step_attempts_exhausted"),
             error_message=IF(attempt_count<max_attempts,NULL,"Recovery step attempts exhausted after lease expiry.")
         WHERE status="running" AND leased_until<UTC_TIMESTAMP()'
    );
    $executions = db()->exec(
        'UPDATE recovery_executions
         SET status=IF(attempt_count<max_attempts,"queued","failed"),lease_token=NULL,leased_until=NULL,
             error_code=IF(attempt_count<max_attempts,NULL,"execution_attempts_exhausted"),
             error_message=IF(attempt_count<max_attempts,NULL,"Recovery execution attempts exhausted after lease expiry."),
             completed_at=IF(attempt_count<max_attempts,NULL,UTC_TIMESTAMP())
         WHERE status IN ("running","verifying") AND leased_until<UTC_TIMESTAMP()'
    );
    return ['executions' => max(0, (int)$executions), 'steps' => max(0, (int)$steps)];
}

function recovery_claim_execution(): ?array
{
    $settings = recovery_settings();
    $leaseSeconds = (int)$settings['execution_lease_seconds'];
    db()->beginTransaction();
    try {
        $row = db()->query(
            "SELECT * FROM recovery_executions
             WHERE status='queued' AND attempt_count<max_attempts
             ORDER BY created_at,id LIMIT 1 FOR UPDATE"
        )->fetch();
        if (!$row) {
            db()->commit();
            return null;
        }
        $token = hash('sha256', random_bytes(32));
        $statement = db()->prepare(
            "UPDATE recovery_executions SET status='running',lease_token=:lease_token,
             leased_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL {$leaseSeconds} SECOND),attempt_count=attempt_count+1,
             started_at=COALESCE(started_at,UTC_TIMESTAMP()),error_code=NULL,error_message=NULL
             WHERE id=:id AND status='queued'"
        );
        $statement->execute(['lease_token' => $token, 'id' => (int)$row['id']]);
        db()->commit();
        if ($statement->rowCount() !== 1) return null;
        $row['lease_token'] = $token;
        $row['status'] = 'running';
        return $row;
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        throw $exception;
    }
}

function recovery_verify_execution(array $execution, array $definition): array
{
    $incident = recovery_incident((int)$execution['incident_id']);
    $analyticsResult = null;
    try {
        $analyticsResult = operations_analytics_run_extended('hour', true);
    } catch (Throwable $analyticsError) {
        $analyticsResult = ['status' => 'failed', 'error_code' => 'verification_analytics_failed'];
    }
    $incident = recovery_incident((int)$execution['incident_id']) ?: $incident;
    $check = (string)($definition['verify_check'] ?? ($incident['check_key'] ?? ''));
    $state = null;
    if ($check !== '') {
        $statement = db()->prepare('SELECT * FROM operations_health_state WHERE check_key=:check_key LIMIT 1');
        $statement->execute(['check_key' => $check]);
        $state = $statement->fetch() ?: null;
    }
    $healthy = ($incident && !empty($incident['recovered_at'])) || ($state && (string)$state['health_status'] === 'healthy');
    return [
        'status' => $healthy ? 'healthy' : ($state ? 'unresolved' : 'unknown'),
        'check_key' => $check,
        'health_status' => $state['health_status'] ?? null,
        'reason_code' => $state['reason_code'] ?? null,
        'observed_value' => isset($state['observed_value']) ? (float)$state['observed_value'] : null,
        'incident_recovered_at' => $incident['recovered_at'] ?? null,
        'analytics' => $analyticsResult,
        'aggregate_only' => true,
        'verified_at' => gmdate(DATE_ATOM),
    ];
}

function recovery_escalate_failure(array $execution, string $message): void
{
    try {
        require_once __DIR__ . '/notifications.php';
        notification_create_for_role(
            'admin',
            'system',
            'Recovery execution failed',
            mb_substr(trim(preg_replace('/\s+/u', ' ', $message) ?? ''), 0, 500),
            'portal/recovery-center.php?execution=' . (int)$execution['id'],
            'recovery_execution',
            (int)$execution['id'],
            'urgent'
        );
    } catch (Throwable $notificationError) {
        error_log('Recovery failure escalation failed: ' . $notificationError->getMessage());
    }
}

function recovery_process_execution(array $execution): array
{
    $versionStatement = db()->prepare('SELECT * FROM recovery_runbook_versions WHERE id=:id LIMIT 1');
    $versionStatement->execute(['id' => (int)$execution['runbook_version_id']]);
    $version = $versionStatement->fetch();
    if (!$version) throw new RuntimeException('Recovery runbook version is missing.');
    $definition = recovery_json_decode((string)$version['definition_json'], []);
    if (!is_array($definition)) throw new RuntimeException('Recovery runbook definition is invalid.');
    recovery_create_execution_steps((int)$execution['id'], $definition);
    $incident = recovery_incident((int)$execution['incident_id']);
    if (!$incident) throw new RuntimeException('Recovery incident is missing.');
    $steps = db()->query('SELECT * FROM recovery_execution_steps WHERE execution_id=' . (int)$execution['id'] . ' ORDER BY step_index')->fetchAll();
    $completed = 0;
    foreach ($steps as $step) {
        if (in_array((string)$step['status'], ['completed','skipped'], true)) {
            $completed++;
            continue;
        }
        $settings = recovery_settings();
        $leaseSeconds = (int)$settings['execution_lease_seconds'];
        $token = hash('sha256', random_bytes(32));
        $claim = db()->prepare(
            "UPDATE recovery_execution_steps SET status='running',lease_token=:lease_token,
             leased_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL {$leaseSeconds} SECOND),attempt_count=attempt_count+1,
             started_at=COALESCE(started_at,UTC_TIMESTAMP()),error_code=NULL,error_message=NULL
             WHERE id=:id AND status IN ('pending','failed') AND attempt_count<max_attempts"
        );
        $claim->execute(['lease_token' => $token, 'id' => (int)$step['id']]);
        if ($claim->rowCount() !== 1) throw new RuntimeException('Recovery step could not be claimed.');
        $input = recovery_json_decode((string)$step['input_json'], []);
        try {
            $before = recovery_handler_preview((string)$step['handler_key'], is_array($input) ? $input : [], $incident);
            $result = recovery_handler_execute((string)$step['handler_key'], is_array($input) ? $input : [], $incident);
            $after = recovery_handler_preview((string)$step['handler_key'], is_array($input) ? $input : [], $incident);
            $output = ['result' => $result, 'before' => $before, 'after' => $after];
            db()->prepare(
                'UPDATE recovery_execution_steps SET status="completed",output_json=:output_json,lease_token=NULL,leased_until=NULL,
                 completed_at=UTC_TIMESTAMP() WHERE id=:id AND lease_token=:lease_token'
            )->execute(['output_json' => recovery_json_encode($output), 'id' => (int)$step['id'], 'lease_token' => $token]);
            $receiptStatus = ((int)($before['candidate_count'] ?? 0) === 0 && (int)($after['candidate_count'] ?? 0) === 0) ? 'no_change' : 'applied';
            recovery_write_receipt((int)$execution['id'], (int)$step['id'], (string)$step['handler_key'], $receiptStatus, $before, $output);
            $completed++;
        } catch (Throwable $stepError) {
            db()->prepare(
                'UPDATE recovery_execution_steps SET status="failed",lease_token=NULL,leased_until=NULL,error_code=:error_code,
                 error_message=:error_message,completed_at=UTC_TIMESTAMP() WHERE id=:id'
            )->execute([
                'error_code' => 'recovery_step_failed',
                'error_message' => mb_substr(trim(preg_replace('/\s+/u', ' ', $stepError->getMessage()) ?? ''), 0, 1000),
                'id' => (int)$step['id'],
            ]);
            recovery_write_receipt((int)$execution['id'], (int)$step['id'], (string)$step['handler_key'], 'failed', null, ['error_code' => 'recovery_step_failed']);
            throw $stepError;
        }
    }
    db()->prepare('UPDATE recovery_executions SET status="verifying" WHERE id=:id')->execute(['id' => (int)$execution['id']]);
    $verification = recovery_verify_execution($execution, $definition);
    $finalStatus = $verification['status'] === 'healthy' ? 'completed' : 'partially_completed';
    db()->prepare(
        'UPDATE recovery_executions SET status=:status,verification_status=:verification_status,verification_json=:verification_json,
         lease_token=NULL,leased_until=NULL,completed_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute([
        'status' => $finalStatus,
        'verification_status' => $verification['status'],
        'verification_json' => recovery_json_encode($verification),
        'id' => (int)$execution['id'],
    ]);
    recovery_write_receipt((int)$execution['id'], null, 'health.verification', 'verified', null, $verification);
    if ($verification['status'] !== 'healthy') recovery_escalate_failure($execution, 'Recovery completed but the incident remains unresolved.');
    return ['execution_id' => (int)$execution['id'], 'steps_completed' => $completed, 'verification' => $verification, 'status' => $finalStatus];
}

function recovery_run_worker(int $limit = 0): array
{
    if (!recovery_schema_available()) throw new RuntimeException('Import database/incident_response_runbooks_v66m.sql first.');
    $settings = recovery_settings();
    $limit = max(1, min(25, $limit > 0 ? $limit : (int)$settings['worker_batch_size']));
    if (!$settings['enabled']) return ['status' => 'disabled', 'processed' => 0];
    if ($settings['emergency_disabled']) return ['status' => 'emergency_disabled', 'processed' => 0];
    recovery_expire_evidence();
    $recovered = recovery_recover_expired_leases();
    $results = [];
    for ($index = 0; $index < $limit; $index++) {
        $execution = recovery_claim_execution();
        if (!$execution) break;
        try {
            $results[] = recovery_process_execution($execution);
        } catch (Throwable $exception) {
            db()->prepare(
                'UPDATE recovery_executions
                 SET status=IF(attempt_count<max_attempts,"queued","failed"),lease_token=NULL,leased_until=NULL,
                     error_code=:error_code,error_message=:error_message,
                     completed_at=IF(attempt_count<max_attempts,NULL,UTC_TIMESTAMP())
                 WHERE id=:id'
            )->execute([
                'error_code' => 'recovery_execution_failed',
                'error_message' => mb_substr(trim(preg_replace('/\s+/u', ' ', $exception->getMessage()) ?? ''), 0, 1000),
                'id' => (int)$execution['id'],
            ]);
            $statusStatement = db()->prepare('SELECT status FROM recovery_executions WHERE id=:id LIMIT 1');
            $statusStatement->execute(['id' => (int)$execution['id']]);
            $retryStatus = (string)$statusStatement->fetchColumn();
            if ($retryStatus === 'failed') recovery_escalate_failure($execution, $exception->getMessage());
            $results[] = ['execution_id' => (int)$execution['id'], 'status' => $retryStatus, 'error_code' => 'recovery_execution_failed'];
        }
    }
    $cleanup = recovery_cleanup();
    return ['status' => 'completed', 'processed' => count($results), 'results' => $results, 'recovered_leases' => $recovered, 'cleanup' => $cleanup];
}

function recovery_cleanup(): array
{
    if (!recovery_schema_available()) return ['executions_deleted' => 0];
    $settings = recovery_settings();
    $days = max(30, min(3650, (int)$settings['execution_retention_days']));
    $deleted = db()->exec(
        "DELETE FROM recovery_executions
         WHERE status IN ('completed','partially_completed','failed','cancelled','simulated')
           AND completed_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY)"
    );
    return ['executions_deleted' => max(0, (int)$deleted)];
}

function recovery_recent_simulations(int $limit = 20): array
{
    if (!recovery_schema_available()) return [];
    $limit = max(1, min(100, $limit));
    return db()->query(
        'SELECT simulation.*,runbook.name AS runbook_name,runbook.impact,runbook.approval_required,
                incident.check_key,incident.highest_status
         FROM recovery_simulations simulation
         JOIN recovery_runbooks runbook ON runbook.id=simulation.runbook_id
         JOIN operations_health_incidents incident ON incident.id=simulation.incident_id
         ORDER BY simulation.created_at DESC,simulation.id DESC LIMIT ' . $limit
    )->fetchAll();
}

function recovery_pending_approvals(int $limit = 50): array
{
    if (!recovery_schema_available()) return [];
    $limit = max(1, min(100, $limit));
    return db()->query(
        'SELECT approval.*,runbook.name AS runbook_name,runbook.impact,incident.check_key,incident.highest_status,
                simulation.simulation_hash,simulation.plan_json
         FROM recovery_approvals approval
         JOIN recovery_runbooks runbook ON runbook.id=approval.runbook_id
         JOIN operations_health_incidents incident ON incident.id=approval.incident_id
         JOIN recovery_simulations simulation ON simulation.id=approval.simulation_id
         WHERE approval.status="pending" AND approval.expires_at>UTC_TIMESTAMP()
         ORDER BY incident.highest_status DESC,approval.created_at,approval.id LIMIT ' . $limit
    )->fetchAll();
}

function recovery_recent_executions(int $limit = 50): array
{
    if (!recovery_schema_available()) return [];
    $limit = max(1, min(100, $limit));
    return db()->query(
        'SELECT execution.*,runbook.name AS runbook_name,runbook.runbook_key,incident.check_key,incident.highest_status,
                approval.status AS approval_status
         FROM recovery_executions execution
         JOIN recovery_runbooks runbook ON runbook.id=execution.runbook_id
         JOIN operations_health_incidents incident ON incident.id=execution.incident_id
         LEFT JOIN recovery_approvals approval ON approval.id=execution.approval_id
         ORDER BY execution.created_at DESC,execution.id DESC LIMIT ' . $limit
    )->fetchAll();
}

function recovery_execution_detail(int $executionId): ?array
{
    if ($executionId <= 0 || !recovery_schema_available()) return null;
    $statement = db()->prepare(
        'SELECT execution.*,runbook.name AS runbook_name,runbook.runbook_key,incident.check_key,incident.highest_status
         FROM recovery_executions execution
         JOIN recovery_runbooks runbook ON runbook.id=execution.runbook_id
         JOIN operations_health_incidents incident ON incident.id=execution.incident_id
         WHERE execution.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $executionId]);
    $execution = $statement->fetch();
    if (!$execution) return null;
    $stepsStatement = db()->prepare('SELECT * FROM recovery_execution_steps WHERE execution_id=:execution_id ORDER BY step_index');
    $stepsStatement->execute(['execution_id' => $executionId]);
    $receiptsStatement = db()->prepare('SELECT * FROM recovery_action_receipts WHERE execution_id=:execution_id ORDER BY created_at,id');
    $receiptsStatement->execute(['execution_id' => $executionId]);
    $execution['steps'] = $stepsStatement->fetchAll();
    $execution['receipts'] = $receiptsStatement->fetchAll();
    return $execution;
}

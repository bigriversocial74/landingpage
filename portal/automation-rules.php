<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-automation-rules-v66K */

require_once __DIR__ . '/homeserver-adapter.php';

function automation_table_exists(string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
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

function automation_schema_available(): bool
{
    foreach ([
        'automation_settings',
        'automation_rules',
        'automation_rule_versions',
        'automation_events',
        'automation_executions',
        'automation_action_receipts',
        'automation_approvals',
        'automation_rule_counters',
    ] as $table) {
        if (!automation_table_exists($table)) return false;
    }
    return true;
}

function automation_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function automation_json_encode(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function automation_json_decode(?string $value, mixed $fallback = []): mixed
{
    if ($value === null || trim($value) === '') return $fallback;
    try {
        return json_decode($value, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return $fallback;
    }
}

function automation_clean_text(mixed $value, int $limit = 4000): string
{
    $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$value)) ?? '');
    return mb_substr($value, 0, max(0, $limit));
}

function automation_sanitize_payload(mixed $value, int $depth = 0): mixed
{
    if ($depth > 6) return '[depth-limited]';
    if (is_array($value)) {
        $clean = [];
        foreach (array_slice($value, 0, 80, true) as $key => $item) {
            $key = mb_substr((string)$key, 0, 100);
            if (preg_match('/(?:password|secret|token|authorization|cookie|credential|private[_-]?key)/i', $key)) {
                $clean[$key] = '[redacted]';
            } else {
                $clean[$key] = automation_sanitize_payload($item, $depth + 1);
            }
        }
        return $clean;
    }
    if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
    return automation_clean_text($value, 4000);
}

function automation_settings(): array
{
    $defaults = [
        'enabled' => false,
        'dry_run' => true,
        'worker_batch_size' => 25,
        'approval_expiry_hours' => 72,
        'event_retention_days' => 90,
        'execution_retention_days' => 365,
    ];
    if (!automation_schema_available()) return $defaults;
    try {
        $row = db()->query('SELECT * FROM automation_settings WHERE id=1 LIMIT 1')->fetch();
        if (!$row) return $defaults;
        return [
            'enabled' => !empty($row['enabled']),
            'dry_run' => !empty($row['dry_run']),
            'worker_batch_size' => max(1, min(100, (int)$row['worker_batch_size'])),
            'approval_expiry_hours' => max(1, min(720, (int)$row['approval_expiry_hours'])),
            'event_retention_days' => max(7, min(730, (int)$row['event_retention_days'])),
            'execution_retention_days' => max(30, min(2555, (int)$row['execution_retention_days'])),
        ];
    } catch (Throwable) {
        return $defaults;
    }
}

function automation_update_settings(array $values, int $userId): void
{
    if (!automation_schema_available()) throw new RuntimeException('Import database/automation_rules_v66k.sql first.');
    db()->prepare(
        'UPDATE automation_settings
         SET enabled=:enabled,dry_run=:dry_run,worker_batch_size=:worker_batch_size,
             approval_expiry_hours=:approval_expiry_hours,event_retention_days=:event_retention_days,
             execution_retention_days=:execution_retention_days,updated_by_user_id=:user_id
         WHERE id=1'
    )->execute([
        'enabled' => !empty($values['enabled']) ? 1 : 0,
        'dry_run' => !empty($values['dry_run']) ? 1 : 0,
        'worker_batch_size' => max(1, min(100, (int)($values['worker_batch_size'] ?? 25))),
        'approval_expiry_hours' => max(1, min(720, (int)($values['approval_expiry_hours'] ?? 72))),
        'event_retention_days' => max(7, min(730, (int)($values['event_retention_days'] ?? 90))),
        'execution_retention_days' => max(30, min(2555, (int)($values['execution_retention_days'] ?? 365))),
        'user_id' => $userId > 0 ? $userId : null,
    ]);
}

function automation_priority_rank(string $priority): int
{
    return ['low' => 1, 'normal' => 2, 'high' => 3, 'urgent' => 4][$priority] ?? 2;
}

function automation_event_catalog(): array
{
    $base = [
        'call_request' => 'Call request',
        'voicemail' => 'Voicemail',
        'website_inquiry' => 'Website inquiry',
        'local_message' => 'Local message',
        'pod_message' => 'POD message',
        'federated_message' => 'Federated message',
        'federated_message_request' => 'Federated message request',
        'comment' => 'Comment',
        'reply' => 'Reply',
        'moderation' => 'Moderation item',
        'reaction' => 'Reaction',
        'follow' => 'Follow activity',
        'mention' => 'Mention',
        'delivery_failure' => 'Delivery failure',
        'homeserver_issue' => 'HomeServer issue',
        'system' => 'System event',
        '*' => 'Any event',
    ];
    if (function_exists('notification_delivery_event_catalog')) {
        try {
            foreach (notification_delivery_event_catalog() as $key => $definition) {
                $base[(string)$key] = is_array($definition)
                    ? (string)($definition['label'] ?? status_label((string)$key))
                    : status_label((string)$key);
            }
        } catch (Throwable) {
        }
    }
    return $base;
}

function automation_notification_event_key(array $notification): string
{
    if (function_exists('notification_delivery_event_key')) {
        try {
            $key = trim((string)notification_delivery_event_key($notification));
            if ($key !== '') return mb_substr($key, 0, 100);
        } catch (Throwable) {
        }
    }
    $entity = strtolower((string)($notification['entity_type'] ?? ''));
    $title = strtolower((string)($notification['title'] ?? ''));
    if (str_contains($entity, 'voicemail') || str_contains($title, 'voicemail')) return 'voicemail';
    if (str_contains($entity, 'call')) return 'call_request';
    if (str_contains($entity, 'lead') || str_contains($title, 'inquiry')) return 'website_inquiry';
    if (str_contains($entity, 'activitypub_message')) return str_contains($title, 'request') ? 'federated_message_request' : 'federated_message';
    if (str_contains($entity, 'pod_message')) return 'pod_message';
    if (str_contains($entity, 'communication')) return 'local_message';
    if (str_contains($entity, 'comment')) return str_contains($title, 'reply') ? 'reply' : 'comment';
    if (str_contains($entity, 'reaction')) return 'reaction';
    if (str_contains($entity, 'follow')) return 'follow';
    if (str_contains($entity, 'remote_post')) return 'mention';
    if (str_contains($entity, 'delivery') && str_contains($title, 'fail')) return 'delivery_failure';
    if (str_contains($title, 'homeserver')) return 'homeserver_issue';
    return (string)($notification['category'] ?? 'system') ?: 'system';
}

function automation_notification_source(array $notification): array
{
    $entity = strtolower(trim((string)($notification['entity_type'] ?? '')));
    $entityId = max(0, (int)($notification['entity_id'] ?? 0));
    $mapping = [
        'communication_thread' => 'communication',
        'pod_message_thread' => 'pod_message',
        'activitypub_message_thread' => 'federated_message',
        'content_comment' => 'content_comment',
        'activitypub_remote_comment' => 'federated_comment',
        'activitypub_remote_reaction' => 'federated_reaction',
        'activitypub_follower' => 'federated_follow',
        'activitypub_following' => 'federated_follow',
        'activitypub_remote_post' => 'federated_post',
        'activitypub_remote_post_action' => 'federated_timeline_action',
        'lead' => 'lead',
        'call_center_request' => 'call_center',
        'call_center_voicemail' => 'call_center',
    ];
    if (isset($mapping[$entity]) && $entityId > 0) {
        return [$mapping[$entity], $entityId];
    }
    return ['notification', (int)$notification['id']];
}

function automation_capture_event(array $event): int
{
    if (!automation_schema_available()) return 0;
    $settings = automation_settings();
    if (!$settings['enabled']) return 0;

    $eventKey = trim((string)($event['event_key'] ?? 'system')) ?: 'system';
    $sourceType = trim((string)($event['source_type'] ?? 'system')) ?: 'system';
    if (!preg_match('/^[a-z0-9_.-]{1,100}$/i', $eventKey)) $eventKey = 'system';
    if (!preg_match('/^[a-z0-9_.-]{1,80}$/i', $sourceType)) $sourceType = 'system';
    $priority = (string)($event['priority'] ?? 'normal');
    if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) $priority = 'normal';
    $sourceId = max(0, (int)($event['source_id'] ?? 0));
    $recipientId = max(0, (int)($event['recipient_user_id'] ?? 0));
    $occurredAt = trim((string)($event['occurred_at'] ?? ''));
    if ($occurredAt === '' || strtotime($occurredAt) === false) $occurredAt = gmdate('Y-m-d H:i:s');
    else $occurredAt = gmdate('Y-m-d H:i:s', (int)strtotime($occurredAt));
    $payload = automation_sanitize_payload($event['payload'] ?? []);
    $dedupeSource = trim((string)($event['dedupe_key'] ?? ''));
    if ($dedupeSource === '') {
        $dedupeSource = implode('|', [$eventKey, $sourceType, (string)$sourceId, $occurredAt, automation_json_encode($payload)]);
    }
    $dedupeKey = hash('sha256', $dedupeSource);

    try {
        $statement = db()->prepare(
            'INSERT INTO automation_events
                (event_uuid,dedupe_key,event_key,source_type,source_id,recipient_user_id,
                 category,priority,payload_json,occurred_at,status,available_at)
             VALUES
                (:event_uuid,:dedupe_key,:event_key,:source_type,:source_id,:recipient_user_id,
                 :category,:priority,:payload_json,:occurred_at,"pending",UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)'
        );
        $statement->execute([
            'event_uuid' => automation_uuid(),
            'dedupe_key' => $dedupeKey,
            'event_key' => mb_substr($eventKey, 0, 100),
            'source_type' => mb_substr($sourceType, 0, 80),
            'source_id' => $sourceId > 0 ? $sourceId : null,
            'recipient_user_id' => $recipientId > 0 ? $recipientId : null,
            'category' => mb_substr(trim((string)($event['category'] ?? '')), 0, 80) ?: null,
            'priority' => $priority,
            'payload_json' => automation_json_encode($payload),
            'occurred_at' => $occurredAt,
        ]);
        return (int)db()->lastInsertId();
    } catch (Throwable $exception) {
        error_log('North Mountain Media automation event capture failed: ' . $exception->getMessage());
        return 0;
    }
}

function automation_capture_notification(int $notificationId): int
{
    if ($notificationId <= 0 || !automation_schema_available()) return 0;
    try {
        $statement = db()->prepare('SELECT * FROM portal_notifications WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $notificationId]);
        $notification = $statement->fetch();
        if (!$notification) return 0;
        $entityType = strtolower((string)($notification['entity_type'] ?? ''));
        if (str_starts_with($entityType, 'automation_')) return 0;
        [$sourceType, $sourceId] = automation_notification_source($notification);
        $payload = [
            'notification_id' => (int)$notification['id'],
            'title' => automation_clean_text($notification['title'] ?? '', 190),
            'body' => automation_clean_text($notification['body'] ?? '', 4000),
            'link_url' => automation_clean_text($notification['link_url'] ?? '', 500),
            'entity_type' => (string)($notification['entity_type'] ?? ''),
            'entity_id' => (int)($notification['entity_id'] ?? 0),
            'inbox_source_type' => $sourceType,
            'inbox_source_id' => $sourceId,
            'crm_contact_id' => 0,
        ];
        return automation_capture_event([
            'event_key' => automation_notification_event_key($notification),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'recipient_user_id' => (int)$notification['recipient_user_id'],
            'category' => (string)$notification['category'],
            'priority' => (string)$notification['priority'],
            'payload' => $payload,
            'occurred_at' => (string)$notification['created_at'],
            'dedupe_key' => 'portal_notification:' . $notificationId,
        ]);
    } catch (Throwable $exception) {
        error_log('North Mountain Media automation notification capture failed: ' . $exception->getMessage());
        return 0;
    }
}

function automation_condition_fields(): array
{
    return [
        'event_key', 'source_type', 'source_id', 'category', 'priority', 'recipient_user_id',
        'title', 'body', 'entity_type', 'entity_id', 'participant', 'workflow_status',
        'assigned_user_id', 'needs_response', 'crm_contact_id', 'occurred_hour', 'occurred_weekday',
    ];
}

function automation_condition_operators(): array
{
    return [
        'equals', 'not_equals', 'contains', 'not_contains', 'starts_with',
        'in', 'not_in', 'exists', 'not_exists', 'priority_at_least',
    ];
}

function automation_action_catalog(): array
{
    return [
        'set_priority' => 'Set inbox priority',
        'assign_user' => 'Assign administrator',
        'set_needs_response' => 'Set needs response',
        'set_workflow_status' => 'Set workflow status',
        'set_pinned' => 'Pin or unpin',
        'set_snooze_minutes' => 'Snooze',
        'archive_for_recipient' => 'Archive for recipient',
        'create_notification' => 'Create in-app notification',
        'add_crm_activity' => 'Add CRM activity',
        'set_crm_follow_up_days' => 'Set CRM follow-up',
        'homeserver_proposal' => 'Request approval-only HomeServer proposal',
    ];
}

function automation_homeserver_capabilities(): array
{
    return ['message_summary', 'suggest_reply', 'workflow_classification', 'notification_summary'];
}

function automation_validate_conditions(mixed $conditions): array
{
    if (!is_array($conditions)) throw new RuntimeException('Conditions must be a JSON array.');
    if (count($conditions) > 20) throw new RuntimeException('A rule may contain at most 20 conditions.');
    $clean = [];
    foreach ($conditions as $index => $condition) {
        if (!is_array($condition)) throw new RuntimeException('Condition ' . ($index + 1) . ' is invalid.');
        $field = trim((string)($condition['field'] ?? ''));
        $operator = trim((string)($condition['operator'] ?? 'equals'));
        if (!in_array($field, automation_condition_fields(), true)) throw new RuntimeException('Condition field is not allowed: ' . $field);
        if (!in_array($operator, automation_condition_operators(), true)) throw new RuntimeException('Condition operator is not allowed: ' . $operator);
        $value = automation_sanitize_payload($condition['value'] ?? null);
        if (is_string($value)) $value = mb_substr($value, 0, 500);
        $clean[] = ['field' => $field, 'operator' => $operator, 'value' => $value];
    }
    return $clean;
}

function automation_validate_actions(mixed $actions): array
{
    if (!is_array($actions) || !$actions) throw new RuntimeException('Add at least one rule action.');
    if (count($actions) > 12) throw new RuntimeException('A rule may contain at most 12 actions.');
    $allowed = automation_action_catalog();
    $clean = [];
    foreach ($actions as $index => $action) {
        if (!is_array($action)) throw new RuntimeException('Action ' . ($index + 1) . ' is invalid.');
        $type = trim((string)($action['type'] ?? ''));
        if (!isset($allowed[$type])) throw new RuntimeException('Action type is not allowed: ' . $type);
        $parameters = is_array($action['parameters'] ?? null) ? $action['parameters'] : [];
        $parameters = automation_sanitize_payload($parameters);
        switch ($type) {
            case 'set_priority':
                $value = (string)($parameters['value'] ?? 'normal');
                if (!in_array($value, ['low', 'normal', 'high', 'urgent'], true)) throw new RuntimeException('Invalid priority action.');
                $parameters = ['value' => $value];
                break;
            case 'assign_user':
                $parameters = ['user_id' => max(0, (int)($parameters['user_id'] ?? 0))];
                break;
            case 'set_needs_response':
            case 'set_pinned':
            case 'archive_for_recipient':
                $parameters = ['value' => !empty($parameters['value'])];
                break;
            case 'set_workflow_status':
                $value = (string)($parameters['value'] ?? 'open');
                if (!in_array($value, ['open', 'waiting', 'resolved'], true)) throw new RuntimeException('Invalid workflow status action.');
                $parameters = ['value' => $value];
                break;
            case 'set_snooze_minutes':
                $parameters = ['minutes' => max(1, min(10080, (int)($parameters['minutes'] ?? 60)))];
                break;
            case 'create_notification':
                $title = automation_clean_text($parameters['title'] ?? 'Automation update', 190);
                if ($title === '') throw new RuntimeException('Notification actions require a title.');
                $priority = (string)($parameters['priority'] ?? 'normal');
                if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) $priority = 'normal';
                $parameters = [
                    'recipient_user_id' => max(0, (int)($parameters['recipient_user_id'] ?? 0)),
                    'title' => $title,
                    'body' => automation_clean_text($parameters['body'] ?? '', 1000),
                    'priority' => $priority,
                ];
                break;
            case 'add_crm_activity':
                $subject = automation_clean_text($parameters['subject'] ?? 'Automation update', 190);
                if ($subject === '') throw new RuntimeException('CRM activity actions require a subject.');
                $parameters = [
                    'subject' => $subject,
                    'body' => automation_clean_text($parameters['body'] ?? '', 2000),
                ];
                break;
            case 'set_crm_follow_up_days':
                $parameters = ['days' => max(0, min(365, (int)($parameters['days'] ?? 1)))];
                break;
            case 'homeserver_proposal':
                $capability = trim((string)($parameters['capability'] ?? 'message_summary'));
                if (!in_array($capability, automation_homeserver_capabilities(), true)) throw new RuntimeException('HomeServer capability is not allowed.');
                $parameters = [
                    'capability' => $capability,
                    'instruction' => automation_clean_text($parameters['instruction'] ?? '', 500),
                ];
                break;
        }
        $clean[] = ['type' => $type, 'parameters' => $parameters];
    }
    return $clean;
}

function automation_validate_rule(array $values): array
{
    $name = automation_clean_text($values['name'] ?? '', 190);
    if ($name === '') throw new RuntimeException('Enter a rule name.');
    $eventKey = trim((string)($values['event_key'] ?? '*')) ?: '*';
    if (!isset(automation_event_catalog()[$eventKey])) throw new RuntimeException('Select a supported event.');
    $sourceType = trim((string)($values['source_type'] ?? ''));
    if ($sourceType !== '' && !preg_match('/^[a-z0-9_.-]{1,80}$/i', $sourceType)) throw new RuntimeException('The source type is invalid.');
    $mode = (string)($values['condition_mode'] ?? 'all');
    if (!in_array($mode, ['all', 'any'], true)) $mode = 'all';
    $conditions = automation_validate_conditions($values['conditions'] ?? []);
    $actions = automation_validate_actions($values['actions'] ?? []);
    $startsAt = automation_rule_datetime($values['starts_at'] ?? null);
    $expiresAt = automation_rule_datetime($values['expires_at'] ?? null);
    if ($startsAt !== null && $expiresAt !== null && $expiresAt <= $startsAt) throw new RuntimeException('The rule expiration must be after its start.');
    return [
        'name' => $name,
        'description' => automation_clean_text($values['description'] ?? '', 4000) ?: null,
        'event_key' => $eventKey,
        'source_type' => $sourceType !== '' ? $sourceType : null,
        'priority_order' => max(1, min(100000, (int)($values['priority_order'] ?? 100))),
        'stop_processing' => !empty($values['stop_processing']) ? 1 : 0,
        'condition_mode' => $mode,
        'conditions_json' => automation_json_encode($conditions),
        'actions_json' => automation_json_encode($actions),
        'max_executions_per_hour' => max(1, min(10000, (int)($values['max_executions_per_hour'] ?? 60))),
        'max_executions_per_day' => max(1, min(100000, (int)($values['max_executions_per_day'] ?? 500))),
        'starts_at' => $startsAt,
        'expires_at' => $expiresAt,
    ];
}

function automation_rule_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime(str_replace('T', ' ', $value));
    if ($timestamp === false) throw new RuntimeException('Enter a valid rule date.');
    return gmdate('Y-m-d H:i:s', $timestamp);
}

function automation_rule_snapshot(array $rule): array
{
    return [
        'name' => (string)$rule['name'],
        'description' => $rule['description'] ?? null,
        'event_key' => (string)$rule['event_key'],
        'source_type' => $rule['source_type'] ?? null,
        'priority_order' => (int)$rule['priority_order'],
        'stop_processing' => !empty($rule['stop_processing']),
        'condition_mode' => (string)$rule['condition_mode'],
        'conditions' => automation_json_decode((string)$rule['conditions_json'], []),
        'actions' => automation_json_decode((string)$rule['actions_json'], []),
        'max_executions_per_hour' => (int)$rule['max_executions_per_hour'],
        'max_executions_per_day' => (int)$rule['max_executions_per_day'],
        'starts_at' => $rule['starts_at'] ?? null,
        'expires_at' => $rule['expires_at'] ?? null,
    ];
}

function automation_rule_snapshot_hash(array $rule): string
{
    return hash('sha256', automation_json_encode(automation_rule_snapshot($rule)));
}

function automation_save_rule(int $ruleId, array $values, int $userId): int
{
    if (!automation_schema_available()) throw new RuntimeException('Import database/automation_rules_v66k.sql first.');
    $clean = automation_validate_rule($values);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($ruleId > 0) {
            $existingStatement = $pdo->prepare('SELECT * FROM automation_rules WHERE id=:id FOR UPDATE');
            $existingStatement->execute(['id' => $ruleId]);
            $existing = $existingStatement->fetch();
            if (!$existing) throw new RuntimeException('Automation rule not found.');
            $statement = $pdo->prepare(
                'UPDATE automation_rules
                 SET name=:name,description=:description,status="draft",event_key=:event_key,
                     source_type=:source_type,priority_order=:priority_order,stop_processing=:stop_processing,
                     condition_mode=:condition_mode,conditions_json=:conditions_json,actions_json=:actions_json,
                     max_executions_per_hour=:max_executions_per_hour,max_executions_per_day=:max_executions_per_day,
                     starts_at=:starts_at,expires_at=:expires_at,updated_by_user_id=:user_id
                 WHERE id=:id'
            );
            $statement->execute($clean + ['user_id' => $userId, 'id' => $ruleId]);
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO automation_rules
                    (rule_uuid,name,description,status,event_key,source_type,priority_order,stop_processing,
                     condition_mode,conditions_json,actions_json,max_executions_per_hour,max_executions_per_day,
                     starts_at,expires_at,created_by_user_id,updated_by_user_id)
                 VALUES
                    (:rule_uuid,:name,:description,"draft",:event_key,:source_type,:priority_order,:stop_processing,
                     :condition_mode,:conditions_json,:actions_json,:max_executions_per_hour,:max_executions_per_day,
                     :starts_at,:expires_at,:created_by_user_id,:updated_by_user_id)'
            );
            $statement->execute($clean + [
      'rule_uuid' => automation_uuid(),
      'created_by_user_id' => $userId,
      'updated_by_user_id' => $userId,
  ]);
            $ruleId = (int)$pdo->lastInsertId();
        }
        $versionStatement = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM automation_rule_versions WHERE rule_id=:rule_id');
        $versionStatement->execute(['rule_id' => $ruleId]);
        $version = (int)$versionStatement->fetchColumn();
        $ruleStatement = $pdo->prepare('SELECT * FROM automation_rules WHERE id=:id');
        $ruleStatement->execute(['id' => $ruleId]);
        $rule = $ruleStatement->fetch();
        $pdo->prepare(
            'INSERT INTO automation_rule_versions (rule_id,version_number,snapshot_json,created_by_user_id)
             VALUES (:rule_id,:version_number,:snapshot_json,:user_id)'
        )->execute([
            'rule_id' => $ruleId,
            'version_number' => $version,
            'snapshot_json' => automation_json_encode(automation_rule_snapshot($rule)),
            'user_id' => $userId,
        ]);
        $pdo->commit();
        return $ruleId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function automation_rule(int $ruleId): ?array
{
    if ($ruleId <= 0 || !automation_schema_available()) return null;
    $statement = db()->prepare('SELECT * FROM automation_rules WHERE id=:id LIMIT 1');
    $statement->execute(['id' => $ruleId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function automation_rules(): array
{
    if (!automation_schema_available()) return [];
    return db()->query(
        'SELECT rule.*,
                (SELECT COUNT(*) FROM automation_executions execution WHERE execution.rule_id=rule.id) AS execution_total,
                (SELECT COUNT(*) FROM automation_approvals approval JOIN automation_executions execution ON execution.id=approval.execution_id WHERE execution.rule_id=rule.id AND approval.status="pending") AS pending_approvals
         FROM automation_rules rule
         ORDER BY rule.priority_order,rule.id'
    )->fetchAll();
}

function automation_event_context(array $event): array
{
    $payload = automation_json_decode((string)$event['payload_json'], []);
    if (!is_array($payload)) $payload = [];
    $context = $payload;
    $context['event_key'] = (string)$event['event_key'];
    $context['source_type'] = (string)$event['source_type'];
    $context['source_id'] = (int)($event['source_id'] ?? 0);
    $context['recipient_user_id'] = (int)($event['recipient_user_id'] ?? 0);
    $context['category'] = (string)($event['category'] ?? '');
    $context['priority'] = (string)$event['priority'];
    $occurred = strtotime((string)$event['occurred_at']) ?: time();
    $context['occurred_hour'] = (int)gmdate('G', $occurred);
    $context['occurred_weekday'] = (int)gmdate('w', $occurred);
    return $context;
}

function automation_condition_value(array $context, string $field): mixed
{
    return $context[$field] ?? null;
}

function automation_values_equal(mixed $left, mixed $right): bool
{
    if (is_bool($left) || is_bool($right)) return filter_var($left, FILTER_VALIDATE_BOOL) === filter_var($right, FILTER_VALIDATE_BOOL);
    if (is_numeric($left) && is_numeric($right)) return (string)+$left === (string)+$right;
    return mb_strtolower(trim((string)$left)) === mb_strtolower(trim((string)$right));
}

function automation_condition_matches(array $condition, array $context): bool
{
    $field = (string)$condition['field'];
    $operator = (string)$condition['operator'];
    $expected = $condition['value'] ?? null;
    $actual = automation_condition_value($context, $field);
    $actualText = mb_strtolower((string)$actual);
    $expectedText = mb_strtolower((string)$expected);
    $list = is_array($expected) ? $expected : array_values(array_filter(array_map('trim', explode(',', (string)$expected)), static fn(string $value): bool => $value !== ''));
    return match ($operator) {
        'equals' => automation_values_equal($actual, $expected),
        'not_equals' => !automation_values_equal($actual, $expected),
        'contains' => $expectedText !== '' && str_contains($actualText, $expectedText),
        'not_contains' => $expectedText === '' || !str_contains($actualText, $expectedText),
        'starts_with' => $expectedText !== '' && str_starts_with($actualText, $expectedText),
        'in' => (bool)array_filter($list, static fn(mixed $item): bool => automation_values_equal($actual, $item)),
        'not_in' => !(bool)array_filter($list, static fn(mixed $item): bool => automation_values_equal($actual, $item)),
        'exists' => $actual !== null && $actual !== '',
        'not_exists' => $actual === null || $actual === '',
        'priority_at_least' => automation_priority_rank((string)$actual) >= automation_priority_rank((string)$expected),
        default => false,
    };
}

function automation_rule_matches(array $rule, array $event): array
{
    if ((string)$rule['event_key'] !== '*' && (string)$rule['event_key'] !== (string)$event['event_key']) return [false, []];
    if (!empty($rule['source_type']) && (string)$rule['source_type'] !== (string)$event['source_type']) return [false, []];
    $conditions = automation_json_decode((string)$rule['conditions_json'], []);
    if (!is_array($conditions)) $conditions = [];
    $context = automation_event_context($event);
    $results = [];
    foreach ($conditions as $condition) {
        $results[] = [
            'field' => (string)($condition['field'] ?? ''),
            'operator' => (string)($condition['operator'] ?? ''),
            'matched' => is_array($condition) && automation_condition_matches($condition, $context),
        ];
    }
    if (!$conditions) return [true, $results];
    $matched = (string)$rule['condition_mode'] === 'any'
        ? (bool)array_filter($results, static fn(array $result): bool => $result['matched'])
        : !array_filter($results, static fn(array $result): bool => !$result['matched']);
    return [$matched, $results];
}

function automation_simulation_key(array $rule): string
{
    return hash('sha256', 'simulation|' . (int)$rule['id'] . '|' . automation_rule_snapshot_hash($rule));
}

function automation_rule_has_current_simulation(array $rule): bool
{
    $statement = db()->prepare(
        'SELECT matched_json FROM automation_executions
         WHERE rule_id=:rule_id AND idempotency_key=:idempotency_key AND status="simulated" LIMIT 1'
    );
    $statement->execute(['rule_id' => (int)$rule['id'], 'idempotency_key' => automation_simulation_key($rule)]);
    $matched = $statement->fetchColumn();
    if ($matched === false) return false;
    $evidence = automation_json_decode((string)$matched, []);
    return is_array($evidence) && ($evidence['matched'] ?? null) === true;
}

function automation_simulate_rule(int $ruleId, array $sample, int $userId): array
{
    $rule = automation_rule($ruleId);
    if (!$rule) throw new RuntimeException('Automation rule not found.');
    $event = [
        'id' => null,
        'event_key' => trim((string)($sample['event_key'] ?? $rule['event_key'])) ?: 'system',
        'source_type' => trim((string)($sample['source_type'] ?? $rule['source_type'] ?? 'notification')) ?: 'notification',
        'source_id' => max(1, (int)($sample['source_id'] ?? 1)),
        'recipient_user_id' => max(0, (int)($sample['recipient_user_id'] ?? $userId)),
        'category' => trim((string)($sample['category'] ?? 'system')),
        'priority' => in_array((string)($sample['priority'] ?? 'normal'), ['low','normal','high','urgent'], true) ? (string)$sample['priority'] : 'normal',
        'payload_json' => automation_json_encode(automation_sanitize_payload($sample['payload'] ?? [])),
        'occurred_at' => gmdate('Y-m-d H:i:s'),
    ];
    [$matched, $conditionResults] = automation_rule_matches($rule, $event);
    $actions = automation_json_decode((string)$rule['actions_json'], []);
    $key = automation_simulation_key($rule);
    db()->prepare(
        'INSERT INTO automation_executions
            (execution_uuid,event_id,rule_id,idempotency_key,status,matched_json,proposed_actions_json,applied_actions_json,completed_at)
         VALUES
            (:execution_uuid,NULL,:rule_id,:idempotency_key,"simulated",:matched_json,:proposed_actions_json,"[]",UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE matched_json=VALUES(matched_json),proposed_actions_json=VALUES(proposed_actions_json),completed_at=UTC_TIMESTAMP()'
    )->execute([
        'execution_uuid' => automation_uuid(),
        'rule_id' => $ruleId,
        'idempotency_key' => $key,
        'matched_json' => automation_json_encode(['matched' => $matched, 'conditions' => $conditionResults, 'sample' => $event]),
        'proposed_actions_json' => automation_json_encode($actions),
    ]);
    return ['matched' => $matched, 'conditions' => $conditionResults, 'actions' => $actions];
}

function automation_set_rule_status(int $ruleId, string $status, int $userId): void
{
    if (!in_array($status, ['draft','active','paused','disabled'], true)) throw new RuntimeException('Invalid rule status.');
    $rule = automation_rule($ruleId);
    if (!$rule) throw new RuntimeException('Automation rule not found.');
    if ($status === 'active' && !empty($rule['expires_at']) && strtotime((string)$rule['expires_at']) <= time()) {
        throw new RuntimeException('An expired rule cannot be activated.');
    }
    if ($status === 'active' && !automation_rule_has_current_simulation($rule)) {
        throw new RuntimeException('Run a matching current simulation before activating this rule.');
    }
    db()->prepare('UPDATE automation_rules SET status=:status,updated_by_user_id=:user_id WHERE id=:id')
        ->execute(['status' => $status, 'user_id' => $userId, 'id' => $ruleId]);
}

function automation_rule_limit_reserve(array $rule): bool
{
    $pdo = db();
    $hourStart = gmdate('Y-m-d H:00:00');
    $dayStart = gmdate('Y-m-d 00:00:00');
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO automation_rule_counters (rule_id,window_type,window_start,execution_count)
             VALUES (:rule_id,:window_type,:window_start,0)'
        );
        foreach ([['hour',$hourStart],['day',$dayStart]] as [$type,$start]) {
            $insert->execute(['rule_id' => (int)$rule['id'], 'window_type' => $type, 'window_start' => $start]);
        }
        $select = $pdo->prepare(
            'SELECT window_type,execution_count FROM automation_rule_counters
             WHERE rule_id=:rule_id AND ((window_type="hour" AND window_start=:hour_start) OR (window_type="day" AND window_start=:day_start))
             FOR UPDATE'
        );
        $select->execute(['rule_id' => (int)$rule['id'], 'hour_start' => $hourStart, 'day_start' => $dayStart]);
        $counts = ['hour' => 0, 'day' => 0];
        foreach ($select->fetchAll() as $row) $counts[(string)$row['window_type']] = (int)$row['execution_count'];
        if ($counts['hour'] >= (int)$rule['max_executions_per_hour'] || $counts['day'] >= (int)$rule['max_executions_per_day']) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare(
            'UPDATE automation_rule_counters SET execution_count=execution_count+1
             WHERE rule_id=:rule_id AND ((window_type="hour" AND window_start=:hour_start) OR (window_type="day" AND window_start=:day_start))'
        )->execute(['rule_id' => (int)$rule['id'], 'hour_start' => $hourStart, 'day_start' => $dayStart]);
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function automation_active_admin_or_null(int $userId): ?int
{
    if ($userId <= 0) return null;
    $statement = db()->prepare('SELECT id FROM users WHERE id=:id AND role="admin" AND status="active" LIMIT 1');
    $statement->execute(['id' => $userId]);
    return $statement->fetchColumn() ? $userId : null;
}

function automation_inbox_target(array $event): array
{
    $context = automation_event_context($event);
    $sourceType = trim((string)($context['inbox_source_type'] ?? $event['source_type'] ?? 'notification')) ?: 'notification';
    $sourceId = max(0, (int)($context['inbox_source_id'] ?? $event['source_id'] ?? 0));
    $allowed = [
        'communication','pod_message','federated_message','content_comment','federated_comment',
        'federated_reaction','federated_follow','federated_post','federated_timeline_action',
        'lead','call_center','notification',
    ];
    if (!in_array($sourceType, $allowed, true) || $sourceId <= 0) return ['', 0];
    return [$sourceType, $sourceId];
}

function automation_ensure_workflow(array $event): array
{
    [$sourceType, $sourceId] = automation_inbox_target($event);
    if ($sourceType === '' || !automation_table_exists('unified_inbox_workflow')) throw new RuntimeException('This event has no supported Unified Inbox target.');
    db()->prepare(
        'INSERT IGNORE INTO unified_inbox_workflow
            (source_type,source_id,workflow_status,priority,needs_response,pinned)
         VALUES (:source_type,:source_id,"open",:priority,0,0)'
    )->execute(['source_type' => $sourceType, 'source_id' => $sourceId, 'priority' => (string)$event['priority']]);
    $statement = db()->prepare('SELECT * FROM unified_inbox_workflow WHERE source_type=:source_type AND source_id=:source_id LIMIT 1');
    $statement->execute(['source_type' => $sourceType, 'source_id' => $sourceId]);
    $row = $statement->fetch();
    if (!$row) throw new RuntimeException('Unable to initialize the Unified Inbox workflow target.');
    return $row;
}

function automation_owner_recipient(array $event): int
{
    $candidate = automation_active_admin_or_null((int)($event['recipient_user_id'] ?? 0));
    if ($candidate !== null) return $candidate;
    try {
        $id = db()->query('SELECT id FROM users WHERE role="admin" AND status="active" ORDER BY id LIMIT 1')->fetchColumn();
        return $id ? (int)$id : 0;
    } catch (Throwable) {
        return 0;
    }
}

function automation_notify_owner(
    array $event,
    string $title,
    string $body,
    string $entityType,
    int $entityId,
    string $priority = 'high'
): int {
    $recipientId = automation_owner_recipient($event);
    if ($recipientId <= 0) return 0;
    try {
        if (!function_exists('notification_create')) require_once __DIR__ . '/notifications.php';
        return notification_create(
            $recipientId,
            'system',
            automation_clean_text($title, 190),
            automation_clean_text($body, 1000),
            'portal/admin.php?view=automation',
            $entityType,
            $entityId,
            $priority
        );
    } catch (Throwable $exception) {
        error_log('North Mountain Media automation owner notification failed: ' . $exception->getMessage());
        return 0;
    }
}

function automation_refresh_execution_status(int $executionId): string
{
    $statement = db()->prepare('SELECT status FROM automation_action_receipts WHERE execution_id=:execution_id ORDER BY action_index');
    $statement->execute(['execution_id' => $executionId]);
    $statuses = array_map('strval', array_column($statement->fetchAll(), 'status'));
    if (!$statuses) return 'failed';
    $pending = count(array_filter($statuses, static fn(string $status): bool => in_array($status, ['awaiting_approval', 'approved'], true)));
    $failed = count(array_filter($statuses, static fn(string $status): bool => $status === 'failed'));
    $applied = count(array_filter($statuses, static fn(string $status): bool => $status === 'applied'));
    if ($pending > 0) $status = 'awaiting_approval';
    elseif ($failed > 0) $status = $applied > 0 ? 'partially_executed' : 'failed';
    else $status = 'executed';
    db()->prepare(
        'UPDATE automation_executions
         SET status=:status,error_code=:error_code,error_message=:error_message,completed_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'error_code' => $failed > 0 ? 'action_failure' : null,
        'error_message' => $failed > 0 ? $failed . ' automation action(s) failed.' : null,
        'id' => $executionId,
    ]);
    return $status;
}

function automation_apply_action(array $event, array $action, int $executionId, int $actionIndex): array
{
    $type = (string)$action['type'];
    $parameters = is_array($action['parameters'] ?? null) ? $action['parameters'] : [];
    $before = [];
    $after = [];
    $status = 'applied';

    if (in_array($type, ['set_priority','assign_user','set_needs_response','set_workflow_status','set_pinned','set_snooze_minutes'], true)) {
        $workflow = automation_ensure_workflow($event);
        $before = $workflow;
        $updates = [];
        $bindings = ['id' => (int)$workflow['id']];
        if ($type === 'set_priority') { $updates[] = 'priority=:priority'; $bindings['priority'] = (string)$parameters['value']; }
        if ($type === 'assign_user') { $updates[] = 'assigned_user_id=:assigned_user_id'; $bindings['assigned_user_id'] = automation_active_admin_or_null((int)$parameters['user_id']); }
        if ($type === 'set_needs_response') { $updates[] = 'needs_response=:needs_response'; $bindings['needs_response'] = !empty($parameters['value']) ? 1 : 0; }
        if ($type === 'set_workflow_status') { $updates[] = 'workflow_status=:workflow_status'; $bindings['workflow_status'] = (string)$parameters['value']; }
        if ($type === 'set_pinned') { $updates[] = 'pinned=:pinned'; $bindings['pinned'] = !empty($parameters['value']) ? 1 : 0; }
        if ($type === 'set_snooze_minutes') { $updates[] = 'snoozed_until=:snoozed_until'; $bindings['snoozed_until'] = gmdate('Y-m-d H:i:s', time() + (int)$parameters['minutes'] * 60); }
        db()->prepare('UPDATE unified_inbox_workflow SET ' . implode(',', $updates) . ' WHERE id=:id')->execute($bindings);
        $statement = db()->prepare('SELECT * FROM unified_inbox_workflow WHERE id=:id');
        $statement->execute(['id' => (int)$workflow['id']]);
        $after = $statement->fetch() ?: [];
    } elseif ($type === 'archive_for_recipient') {
        [$sourceType, $sourceId] = automation_inbox_target($event);
        $recipientId = (int)($event['recipient_user_id'] ?? 0);
        if ($sourceType === '' || $recipientId <= 0 || !automation_table_exists('unified_inbox_user_state')) throw new RuntimeException('This event has no archivable recipient target.');
        $statement = db()->prepare('SELECT * FROM unified_inbox_user_state WHERE user_id=:user_id AND source_type=:source_type AND source_id=:source_id');
        $statement->execute(['user_id' => $recipientId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $before = $statement->fetch() ?: [];
        $archivedAt = !empty($parameters['value']) ? gmdate('Y-m-d H:i:s') : null;
        db()->prepare(
            'INSERT INTO unified_inbox_user_state (user_id,source_type,source_id,archived_at)
             VALUES (:user_id,:source_type,:source_id,:archived_at)
             ON DUPLICATE KEY UPDATE archived_at=VALUES(archived_at)'
        )->execute(['user_id' => $recipientId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'archived_at' => $archivedAt]);
        $after = ['archived_at' => $archivedAt];
    } elseif ($type === 'create_notification') {
        if (!function_exists('notification_create')) require_once __DIR__ . '/notifications.php';
        $recipientId = (int)($parameters['recipient_user_id'] ?? 0);
        if ($recipientId <= 0) $recipientId = (int)($event['recipient_user_id'] ?? 0);
        if ($recipientId <= 0) throw new RuntimeException('The notification action has no recipient.');
        $notificationId = notification_create(
            $recipientId,
            'system',
            (string)$parameters['title'],
            (string)$parameters['body'],
            'portal/admin.php?view=automation&execution=' . $executionId,
            'automation_execution',
            $executionId,
            (string)$parameters['priority']
        );
        if ($notificationId <= 0) throw new RuntimeException('The automation notification could not be created.');
        $after = ['notification_id' => $notificationId];
    } elseif ($type === 'add_crm_activity' || $type === 'set_crm_follow_up_days') {
        $context = automation_event_context($event);
        $contactId = max(0, (int)($context['crm_contact_id'] ?? 0));
        if ($contactId <= 0 || !automation_table_exists('crm_contacts')) throw new RuntimeException('This event has no CRM contact.');
        if ($type === 'add_crm_activity') {
            db()->prepare(
                'INSERT INTO crm_activities (contact_id,admin_user_id,activity_type,subject,body)
                 VALUES (:contact_id,NULL,"system",:subject,:body)'
            )->execute([
                'contact_id' => $contactId,
                'subject' => (string)$parameters['subject'],
                'body' => (string)$parameters['body'],
            ]);
            $after = ['crm_activity_id' => (int)db()->lastInsertId(), 'contact_id' => $contactId];
        } else {
            $statement = db()->prepare('SELECT next_follow_up_at FROM crm_contacts WHERE id=:id');
            $statement->execute(['id' => $contactId]);
            $before = ['next_follow_up_at' => $statement->fetchColumn() ?: null];
            $followUp = gmdate('Y-m-d H:i:s', time() + (int)$parameters['days'] * 86400);
            db()->prepare('UPDATE crm_contacts SET next_follow_up_at=:follow_up WHERE id=:id')->execute(['follow_up' => $followUp, 'id' => $contactId]);
            $after = ['next_follow_up_at' => $followUp, 'contact_id' => $contactId];
        }
    } elseif ($type === 'homeserver_proposal') {
        $status = 'awaiting_approval';
        $settings = automation_settings();
        $context = automation_event_context($event);
        $request = [
            'wrapper' => 'rss-pod',
            'resource_authority' => 'automation_event',
            'proposal_only' => true,
            'send_allowed' => false,
            'tool_execution_allowed' => false,
            'capability' => (string)$parameters['capability'],
            'instruction' => (string)$parameters['instruction'],
            'event' => [
                'event_key' => (string)$event['event_key'],
                'source_type' => (string)$event['source_type'],
                'source_id' => (int)($event['source_id'] ?? 0),
                'priority' => (string)$event['priority'],
                'title' => automation_clean_text($context['title'] ?? '', 190),
                'preview' => automation_clean_text($context['body'] ?? '', 1200),
            ],
        ];
        $requestJson = automation_json_encode($request);
        $requestHash = hash('sha256', $requestJson);
        $after = [
            'approval_request' => $request,
            'request_hash' => $requestHash,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $settings['approval_expiry_hours'] * 3600),
        ];
    } else {
        throw new RuntimeException('The automation action is not supported.');
    }

    db()->prepare(
        'INSERT INTO automation_action_receipts
            (execution_id,action_index,action_type,status,before_json,after_json)
         VALUES (:execution_id,:action_index,:action_type,:status,:before_json,:after_json)'
    )->execute([
        'execution_id' => $executionId,
        'action_index' => $actionIndex,
        'action_type' => $type,
        'status' => $status,
        'before_json' => automation_json_encode($before),
        'after_json' => automation_json_encode($after),
    ]);
    $receiptId = (int)db()->lastInsertId();

    if ($status === 'awaiting_approval') {
        db()->prepare(
            'INSERT INTO automation_approvals
                (approval_uuid,execution_id,action_receipt_id,approval_type,status,capability,request_hash,request_json,expires_at)
             VALUES
                (:approval_uuid,:execution_id,:receipt_id,"homeserver_proposal","pending",:capability,:request_hash,:request_json,:expires_at)'
        )->execute([
            'approval_uuid' => automation_uuid(),
            'execution_id' => $executionId,
            'receipt_id' => $receiptId,
            'capability' => (string)$parameters['capability'],
            'request_hash' => (string)$after['request_hash'],
            'request_json' => automation_json_encode($after['approval_request']),
            'expires_at' => (string)$after['expires_at'],
        ]);
        $approvalId = (int)db()->lastInsertId();
        automation_notify_owner(
            $event,
            'Automation approval required',
            'A HomeServer proposal is waiting for explicit owner approval.',
            'automation_approval',
            $approvalId,
            'high'
        );
    }
    return ['status' => $status, 'type' => $type, 'before' => $before, 'after' => $after];
}

function automation_execution_insert(array $event, array $rule, string $status, array $matched, array $actions): int
{
    $eventId = (int)($event['id'] ?? 0);
    $key = hash('sha256', 'event|' . $eventId . '|rule|' . (int)$rule['id']);
    $statement = db()->prepare(
        'INSERT IGNORE INTO automation_executions
            (execution_uuid,event_id,rule_id,idempotency_key,status,matched_json,proposed_actions_json,applied_actions_json)
         VALUES
            (:execution_uuid,:event_id,:rule_id,:idempotency_key,:status,:matched_json,:proposed_actions_json,"[]")'
    );
    $statement->execute([
        'execution_uuid' => automation_uuid(),
        'event_id' => $eventId > 0 ? $eventId : null,
        'rule_id' => (int)$rule['id'],
        'idempotency_key' => $key,
        'status' => $status,
        'matched_json' => automation_json_encode($matched),
        'proposed_actions_json' => automation_json_encode($actions),
    ]);
    if ($statement->rowCount() !== 1) return 0;
    return (int)db()->lastInsertId();
}

function automation_process_rule(array $event, array $rule, bool $dryRun): array
{
    [$matched, $conditionResults] = automation_rule_matches($rule, $event);
    db()->prepare('UPDATE automation_rules SET last_evaluated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => (int)$rule['id']]);
    $actions = automation_json_decode((string)$rule['actions_json'], []);
    if (!$matched) {
        $executionId = automation_execution_insert($event, $rule, 'no_match', ['matched' => false, 'conditions' => $conditionResults], $actions);
        if ($executionId <= 0) return ['matched' => false, 'stop' => false, 'status' => 'idempotent'];
        db()->prepare('UPDATE automation_executions SET completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => false, 'stop' => false, 'status' => 'no_match'];
    }
    if ($dryRun) {
        $executionId = automation_execution_insert($event, $rule, 'simulated', ['matched' => true, 'conditions' => $conditionResults, 'global_dry_run' => true], $actions);
        if ($executionId <= 0) return ['matched' => true, 'stop' => false, 'status' => 'idempotent'];
        db()->prepare('UPDATE automation_executions SET completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => 'simulated'];
    }
    if (!automation_rule_limit_reserve($rule)) {
        $executionId = automation_execution_insert($event, $rule, 'suppressed', ['matched' => true, 'conditions' => $conditionResults, 'reason' => 'execution_limit'], $actions);
        if ($executionId <= 0) return ['matched' => true, 'stop' => false, 'status' => 'idempotent'];
        db()->prepare('UPDATE automation_executions SET error_code="execution_limit",error_message="The rule execution limit was reached.",completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => 'suppressed'];
    }

    $executionId = automation_execution_insert($event, $rule, 'matched', ['matched' => true, 'conditions' => $conditionResults], $actions);
    if ($executionId <= 0) return ['matched' => true, 'stop' => false, 'status' => 'idempotent'];
    $applied = [];
    $failures = 0;
    $approvals = 0;
    foreach ($actions as $index => $action) {
        try {
            $result = automation_apply_action($event, $action, $executionId, (int)$index);
            $applied[] = $result;
            if ($result['status'] === 'awaiting_approval') $approvals++;
        } catch (Throwable $exception) {
            $failures++;
            $type = (string)($action['type'] ?? 'unknown');
            db()->prepare(
                'INSERT INTO automation_action_receipts
                    (execution_id,action_index,action_type,status,error_code,error_message)
                 VALUES (:execution_id,:action_index,:action_type,"failed","action_failed",:error_message)'
            )->execute([
                'execution_id' => $executionId,
                'action_index' => (int)$index,
                'action_type' => mb_substr($type, 0, 80),
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            $applied[] = ['type' => $type, 'status' => 'failed', 'message' => $exception->getMessage()];
        }
    }
    $status = $failures > 0
        ? ($failures === count($actions) ? 'failed' : 'partially_executed')
        : ($approvals > 0 ? 'awaiting_approval' : 'executed');
    db()->prepare(
        'UPDATE automation_executions
         SET status=:status,applied_actions_json=:applied_actions_json,
             error_code=:error_code,error_message=:error_message,completed_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute([
        'status' => $status,
        'applied_actions_json' => automation_json_encode($applied),
        'error_code' => $failures > 0 ? 'action_failure' : null,
        'error_message' => $failures > 0 ? $failures . ' automation action(s) failed.' : null,
        'id' => $executionId,
    ]);
    if ($failures > 0) {
        automation_notify_owner(
            $event,
            'Automation action failed',
            $failures . ' action(s) failed. Review the immutable execution receipts.',
            'automation_execution_failure',
            $executionId,
            'high'
        );
    }
    db()->prepare(
        'UPDATE automation_rules SET last_triggered_at=UTC_TIMESTAMP(),execution_count=execution_count+1 WHERE id=:id'
    )->execute(['id' => (int)$rule['id']]);
    return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => $status];
}

function automation_claim_events(int $limit): array
{
    $limit = max(1, min(100, $limit));
    $pdo = db();
    $pdo->exec(
        'UPDATE automation_events
         SET status="failed",lease_token=NULL,leased_until=NULL,last_error_code="lease_expired",
             last_error_message="The automation worker lease expired at the attempt limit.",completed_at=UTC_TIMESTAMP()
         WHERE status="processing" AND leased_until<UTC_TIMESTAMP() AND attempt_count>=max_attempts'
    );
    $pdo->exec(
        'UPDATE automation_events
         SET status="pending",lease_token=NULL,leased_until=NULL,available_at=UTC_TIMESTAMP()
         WHERE status="processing" AND leased_until<UTC_TIMESTAMP() AND attempt_count<max_attempts'
    );
    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    try {
        $rows = $pdo->query(
            'SELECT id FROM automation_events
             WHERE status="pending" AND available_at<=UTC_TIMESTAMP()
             ORDER BY FIELD(priority,"urgent","high","normal","low"),occurred_at,id
             LIMIT ' . $limit . ' FOR UPDATE'
        )->fetchAll();
        $ids = array_values(array_filter(array_map(static fn(array $row): int => (int)$row['id'], $rows)));
        if (!$ids) {
            $pdo->commit();
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare(
            'UPDATE automation_events
             SET status="processing",lease_token=?,leased_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE),attempt_count=attempt_count+1
             WHERE id IN (' . $placeholders . ') AND status="pending"'
        );
        $statement->execute(array_merge([$token], $ids));
        $pdo->commit();
        $statement = $pdo->prepare('SELECT * FROM automation_events WHERE lease_token=:lease_token AND status="processing" ORDER BY id');
        $statement->execute(['lease_token' => $token]);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function automation_process_event(array $event): array
{
    $settings = automation_settings();
    if (!$settings['enabled']) {
        db()->prepare('UPDATE automation_events SET status="suppressed",lease_token=NULL,leased_until=NULL,completed_at=UTC_TIMESTAMP() WHERE id=:id')
            ->execute(['id' => (int)$event['id']]);
        return ['status' => 'suppressed', 'matched' => 0];
    }
    $statement = db()->prepare(
        'SELECT * FROM automation_rules
         WHERE status="active"
           AND (event_key="*" OR event_key=:event_key)
           AND (source_type IS NULL OR source_type=:source_type)
           AND (starts_at IS NULL OR starts_at<=UTC_TIMESTAMP())
           AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())
         ORDER BY priority_order,id'
    );
    $statement->execute(['event_key' => (string)$event['event_key'], 'source_type' => (string)$event['source_type']]);
    $rules = $statement->fetchAll();
    $matched = 0;
    $failed = 0;
    try {
        foreach ($rules as $rule) {
            $result = automation_process_rule($event, $rule, $settings['dry_run']);
            if ($result['matched']) $matched++;
            if (in_array($result['status'], ['failed','partially_executed'], true)) $failed++;
            if ($result['matched'] && $result['stop']) break;
        }
        db()->prepare(
            'UPDATE automation_events
             SET status=:status,matched_rule_count=:matched_rule_count,lease_token=NULL,leased_until=NULL,
                 last_error_code=:error_code,last_error_message=:error_message,completed_at=UTC_TIMESTAMP()
             WHERE id=:id'
        )->execute([
            'status' => $failed > 0 ? 'failed' : 'completed',
            'matched_rule_count' => $matched,
            'error_code' => $failed > 0 ? 'rule_action_failure' : null,
            'error_message' => $failed > 0 ? $failed . ' matched rule(s) had action failures.' : null,
            'id' => (int)$event['id'],
        ]);
        return ['status' => $failed > 0 ? 'failed' : 'completed', 'matched' => $matched];
    } catch (Throwable $exception) {
        $attempts = (int)$event['attempt_count'];
        $permanent = $attempts >= (int)$event['max_attempts'];
        $delay = min(3600, 60 * (2 ** max(0, $attempts - 1)));
        db()->prepare(
            'UPDATE automation_events
             SET status=:status,available_at=:available_at,lease_token=NULL,leased_until=NULL,
                 last_error_code="event_processing_failed",last_error_message=:message,
                 completed_at=CASE WHEN :completed_status="failed" THEN UTC_TIMESTAMP() ELSE NULL END
             WHERE id=:id'
        )->execute([
            'status' => $permanent ? 'failed' : 'pending',
            'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
            'message' => mb_substr($exception->getMessage(), 0, 1000),
            'completed_status' => $permanent ? 'failed' : 'pending',
            'id' => (int)$event['id'],
        ]);
        return ['status' => $permanent ? 'failed' : 'pending', 'matched' => 0, 'error' => $exception->getMessage()];
    }
}

function automation_run(int $limit = 0): array
{
    if (!automation_schema_available()) return ['processed' => 0, 'completed' => 0, 'failed' => 0];
    automation_expire_rules();
    $settings = automation_settings();
    if (!$settings['enabled']) return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'disabled' => true];
    $events = automation_claim_events($limit > 0 ? $limit : $settings['worker_batch_size']);
    $summary = ['processed' => 0, 'completed' => 0, 'failed' => 0];
    foreach ($events as $event) {
        $result = automation_process_event($event);
        $summary['processed']++;
        if ($result['status'] === 'completed') $summary['completed']++;
        elseif ($result['status'] === 'failed') $summary['failed']++;
    }
    automation_expire_approvals();
    automation_cleanup();
    return $summary;
}

function automation_expire_rules(): int
{
    if (!automation_schema_available()) return 0;
    $statement = db()->prepare(
        'UPDATE automation_rules
         SET status="expired"
         WHERE status="active" AND expires_at IS NOT NULL AND expires_at<=UTC_TIMESTAMP()'
    );
    $statement->execute();
    return $statement->rowCount();
}

function automation_recover_interrupted_approvals(): int
{
    if (!automation_schema_available()) return 0;
    $rows = db()->query(
        'SELECT approval.id,approval.execution_id,approval.action_receipt_id
         FROM automation_approvals approval
         WHERE approval.status="approved"
           AND approval.resolved_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)'
    )->fetchAll();
    if (!$rows) return 0;
    $statement = db()->prepare(
        'UPDATE automation_approvals approval
         JOIN automation_action_receipts receipt ON receipt.id=approval.action_receipt_id
         SET approval.status="failed",
             approval.result_json=:result_json,
             receipt.status="failed",
             receipt.error_code="approval_worker_interrupted",
             receipt.error_message="The approved HomeServer request was interrupted before a result was stored."
         WHERE approval.id=:id AND approval.status="approved"'
    );
    $recovered = 0;
    foreach ($rows as $row) {
        $statement->execute([
            'id' => (int)$row['id'],
            'result_json' => automation_json_encode([
                'ok' => false,
                'available' => false,
                'message' => 'The approved HomeServer request was interrupted before a result was stored.',
            ]),
        ]);
        if ($statement->rowCount() !== 1) continue;
        $recovered++;
        automation_refresh_execution_status((int)$row['execution_id']);
    }
    return $recovered;
}

function automation_expire_approvals(): int
{
    if (!automation_schema_available()) return 0;
    automation_recover_interrupted_approvals();
    $executionIds = db()->query(
        'SELECT DISTINCT execution_id FROM automation_approvals
         WHERE status="pending" AND expires_at<=UTC_TIMESTAMP()'
    )->fetchAll(PDO::FETCH_COLUMN);
    $statement = db()->prepare(
        'UPDATE automation_approvals approval
         JOIN automation_action_receipts receipt ON receipt.id=approval.action_receipt_id
         SET approval.status="expired",approval.resolved_at=UTC_TIMESTAMP(),receipt.status="rejected",
             receipt.error_code="approval_expired",receipt.error_message="The approval request expired."
         WHERE approval.status="pending" AND approval.expires_at<=UTC_TIMESTAMP()'
    );
    $statement->execute();
    foreach ($executionIds as $executionId) automation_refresh_execution_status((int)$executionId);
    return $statement->rowCount();
}

function automation_resolve_approval(int $approvalId, string $decision, int $userId): array
{
    if (!in_array($decision, ['approve','reject'], true)) throw new RuntimeException('Invalid approval decision.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'SELECT approval.*,receipt.action_type,execution.event_id,
                    event.event_key,event.source_type,event.source_id,event.recipient_user_id,
                    event.priority,event.payload_json,event.occurred_at
             FROM automation_approvals approval
             JOIN automation_action_receipts receipt ON receipt.id=approval.action_receipt_id
             JOIN automation_executions execution ON execution.id=approval.execution_id
             LEFT JOIN automation_events event ON event.id=execution.event_id
             WHERE approval.id=:id FOR UPDATE'
        );
        $statement->execute(['id' => $approvalId]);
        $approval = $statement->fetch();
        if (!$approval) throw new RuntimeException('Approval request not found.');
        if ((string)$approval['status'] !== 'pending') throw new RuntimeException('This approval request is not pending.');
        if (strtotime((string)$approval['expires_at']) <= time()) throw new RuntimeException('This approval request has expired.');
        if ($decision === 'reject') {
            $pdo->prepare('UPDATE automation_approvals SET status="rejected",resolved_by_user_id=:user_id,resolved_at=UTC_TIMESTAMP() WHERE id=:id')
                ->execute(['user_id' => $userId, 'id' => $approvalId]);
            $pdo->prepare('UPDATE automation_action_receipts SET status="rejected",approved_by_user_id=:user_id,approved_at=UTC_TIMESTAMP(),error_code=NULL,error_message=NULL WHERE id=:id')
                ->execute(['user_id' => $userId, 'id' => (int)$approval['action_receipt_id']]);
            $pdo->commit();
            automation_refresh_execution_status((int)$approval['execution_id']);
            return ['status' => 'rejected'];
        }
        $request = automation_json_decode((string)$approval['request_json'], []);
        if (!is_array($request) || hash('sha256', automation_json_encode($request)) !== (string)$approval['request_hash']) {
            throw new RuntimeException('The approval request integrity check failed.');
        }
        $capability = (string)$approval['capability'];
        if (!in_array($capability, automation_homeserver_capabilities(), true)) throw new RuntimeException('The HomeServer capability is no longer allowed.');
        if (($request['proposal_only'] ?? null) !== true || ($request['send_allowed'] ?? null) !== false || ($request['tool_execution_allowed'] ?? null) !== false) {
            throw new RuntimeException('The HomeServer approval boundary is invalid.');
        }
        $pdo->prepare('UPDATE automation_approvals SET status="approved",resolved_by_user_id=:user_id,resolved_at=UTC_TIMESTAMP(),result_json=NULL WHERE id=:id')
            ->execute(['user_id' => $userId, 'id' => $approvalId]);
        $pdo->prepare('UPDATE automation_action_receipts SET status="approved",approved_by_user_id=:user_id,approved_at=UTC_TIMESTAMP(),error_code=NULL,error_message=NULL WHERE id=:id')
            ->execute(['user_id' => $userId, 'id' => (int)$approval['action_receipt_id']]);
        $pdo->commit();
        try {
            $result = homeserver_request($capability, $request);
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'available' => false, 'message' => $exception->getMessage()];
        }
        $safeResult = [
            'ok' => !empty($result['ok']),
            'available' => !empty($result['available']),
            'capability' => $capability,
            'message' => automation_clean_text($result['message'] ?? '', 500),
            'proposal' => automation_clean_text($result['proposal'] ?? $result['summary'] ?? $result['draft'] ?? '', 4000),
            'receipt_id' => automation_clean_text($result['receipt_id'] ?? $result['job_id'] ?? '', 255),
        ];
        $finalStatus = $safeResult['ok'] ? 'completed' : 'failed';
        $approvalFinalize = db()->prepare(
            'UPDATE automation_approvals SET status=:status,result_json=:result_json
             WHERE id=:id AND status="approved"'
        );
        $approvalFinalize->execute([
            'status' => $finalStatus,
            'result_json' => automation_json_encode($safeResult),
            'id' => $approvalId,
        ]);
        if ($approvalFinalize->rowCount() !== 1) return ['status' => 'superseded', 'result' => $safeResult];
        db()->prepare('UPDATE automation_action_receipts SET status=:status,after_json=:after_json,error_code=:error_code,error_message=:error_message WHERE id=:id')
            ->execute([
                'status' => $safeResult['ok'] ? 'applied' : 'failed',
                'after_json' => automation_json_encode($safeResult),
                'error_code' => $safeResult['ok'] ? null : 'homeserver_proposal_failed',
                'error_message' => $safeResult['ok'] ? null : ($safeResult['message'] ?: 'The HomeServer proposal failed.'),
                'id' => (int)$approval['action_receipt_id'],
            ]);
        $executionStatus = automation_refresh_execution_status((int)$approval['execution_id']);
        if (!$safeResult['ok']) {
            $event = [
                'event_key' => (string)($approval['event_key'] ?? 'system'),
                'source_type' => (string)($approval['source_type'] ?? 'automation'),
                'source_id' => (int)($approval['source_id'] ?? 0),
                'recipient_user_id' => (int)($approval['recipient_user_id'] ?? 0),
                'priority' => (string)($approval['priority'] ?? 'high'),
                'payload_json' => (string)($approval['payload_json'] ?? '{}'),
                'occurred_at' => (string)($approval['occurred_at'] ?? gmdate('Y-m-d H:i:s')),
            ];
            automation_notify_owner(
                $event,
                'HomeServer automation proposal failed',
                $safeResult['message'] ?: 'The approved proposal could not be completed.',
                'automation_approval_failure',
                $approvalId,
                'high'
            );
        }
        return ['status' => $finalStatus, 'execution_status' => $executionStatus, 'result' => $safeResult];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function automation_retry_approval(int $approvalId, int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT * FROM automation_approvals WHERE id=:id FOR UPDATE');
        $statement->execute(['id' => $approvalId]);
        $approval = $statement->fetch();
        if (!$approval) throw new RuntimeException('Approval request not found.');
        if ((string)$approval['status'] !== 'failed') throw new RuntimeException('Only failed proposal requests can be retried.');
        if (strtotime((string)$approval['expires_at']) <= time()) throw new RuntimeException('This approval request has expired.');
        $request = automation_json_decode((string)$approval['request_json'], []);
        if (!is_array($request) || hash('sha256', automation_json_encode($request)) !== (string)$approval['request_hash']) {
            throw new RuntimeException('The approval request integrity check failed.');
        }
        if (($request['proposal_only'] ?? null) !== true || ($request['send_allowed'] ?? null) !== false || ($request['tool_execution_allowed'] ?? null) !== false) {
            throw new RuntimeException('The HomeServer approval boundary is invalid.');
        }
        $pdo->prepare(
            'UPDATE automation_approvals SET status="pending",result_json=NULL,resolved_by_user_id=NULL,resolved_at=NULL WHERE id=:id'
        )->execute(['id' => $approvalId]);
        $pdo->prepare(
            'UPDATE automation_action_receipts
             SET status="awaiting_approval",after_json=NULL,error_code=NULL,error_message=NULL,
                 approved_by_user_id=NULL,approved_at=NULL WHERE id=:id'
        )->execute(['id' => (int)$approval['action_receipt_id']]);
        $pdo->commit();
        automation_refresh_execution_status((int)$approval['execution_id']);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function automation_retry_event(int $eventId): void
{
    $statement = db()->prepare(
        'SELECT event.*,(SELECT COUNT(*) FROM automation_executions execution WHERE execution.event_id=event.id) AS execution_total
         FROM automation_events event WHERE event.id=:id LIMIT 1'
    );
    $statement->execute(['id' => $eventId]);
    $event = $statement->fetch();
    if (!$event) throw new RuntimeException('Automation event not found.');
    if (!in_array((string)$event['status'], ['failed','suppressed'], true)) throw new RuntimeException('Only failed or suppressed events can be retried.');
    if ((int)$event['attempt_count'] >= (int)$event['max_attempts']) throw new RuntimeException('This event has reached its attempt limit.');
    if ((int)$event['execution_total'] > 0) {
        throw new RuntimeException('This event already has immutable execution receipts and cannot be replayed. Retry its failed approval or review the execution instead.');
    }
    db()->prepare(
        'UPDATE automation_events
         SET status="pending",available_at=UTC_TIMESTAMP(),lease_token=NULL,leased_until=NULL,
             last_error_code=NULL,last_error_message=NULL,completed_at=NULL WHERE id=:id'
    )->execute(['id' => $eventId]);
}

function automation_cleanup(): array
{
    if (!automation_schema_available()) return ['events' => 0, 'executions' => 0, 'counters' => 0];
    $settings = automation_settings();
    $events = db()->exec(
        'DELETE FROM automation_events
         WHERE status IN ("completed","failed","suppressed")
           AND created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . (int)$settings['event_retention_days'] . ' DAY)'
    );
    $executions = db()->exec(
        'DELETE FROM automation_executions
         WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . (int)$settings['execution_retention_days'] . ' DAY)'
    );
    $counters = db()->exec('DELETE FROM automation_rule_counters WHERE window_start<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY)');
    return ['events' => (int)$events, 'executions' => (int)$executions, 'counters' => (int)$counters];
}

function automation_health(): array
{
    if (!automation_schema_available()) return ['schema' => false];
    $settings = automation_settings();
    $counts = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0, 'suppressed' => 0];
    foreach (db()->query('SELECT status,COUNT(*) AS total FROM automation_events GROUP BY status')->fetchAll() as $row) {
        $counts[(string)$row['status']] = (int)$row['total'];
    }
    return [
        'schema' => true,
        'enabled' => $settings['enabled'],
        'dry_run' => $settings['dry_run'],
        'events' => $counts,
        'active_rules' => (int)db()->query('SELECT COUNT(*) FROM automation_rules WHERE status="active"')->fetchColumn(),
        'pending_approvals' => (int)db()->query('SELECT COUNT(*) FROM automation_approvals WHERE status="pending" AND expires_at>UTC_TIMESTAMP()')->fetchColumn(),
        'failed_executions' => (int)db()->query('SELECT COUNT(*) FROM automation_executions WHERE status IN ("failed","partially_executed")')->fetchColumn(),
    ];
}

function automation_recent_events(int $limit = 50): array
{
    if (!automation_schema_available()) return [];
    $limit = max(1, min(200, $limit));
    return db()->query('SELECT * FROM automation_events ORDER BY created_at DESC,id DESC LIMIT ' . $limit)->fetchAll();
}

function automation_recent_executions(int $limit = 50): array
{
    if (!automation_schema_available()) return [];
    $limit = max(1, min(200, $limit));
    return db()->query(
        'SELECT execution.*,rule.name AS rule_name,event.event_key,event.source_type,event.source_id
         FROM automation_executions execution
         JOIN automation_rules rule ON rule.id=execution.rule_id
         LEFT JOIN automation_events event ON event.id=execution.event_id
         ORDER BY execution.created_at DESC,execution.id DESC LIMIT ' . $limit
    )->fetchAll();
}

function automation_pending_approvals(int $limit = 100): array
{
    if (!automation_schema_available()) return [];
    $limit = max(1, min(200, $limit));
    return db()->query(
        'SELECT approval.*,rule.name AS rule_name,event.event_key,event.source_type,event.source_id
         FROM automation_approvals approval
         JOIN automation_executions execution ON execution.id=approval.execution_id
         JOIN automation_rules rule ON rule.id=execution.rule_id
         LEFT JOIN automation_events event ON event.id=execution.event_id
         WHERE approval.status IN ("pending","failed")
         ORDER BY FIELD(approval.status,"pending","failed"),approval.created_at,approval.id LIMIT ' . $limit
    )->fetchAll();
}

<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'nmm'
        ),
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: 'root',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function nmm_config(?string $section = null): array
{
    $config = ['app' => ['base_url' => 'https://pod.example.test', 'timezone' => 'America/Phoenix'], 'homeserver' => []];
    if ($section === null) return $config;
    return is_array($config[$section] ?? null) ? $config[$section] : [];
}

function setting(string $key, ?string $fallback = null): ?string
{
    return $fallback;
}

function app_url(string $path = ''): string
{
    return 'https://pod.example.test' . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function status_label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function homeserver_connector_status(): array
{
    return [
        'paired' => true,
        'online' => true,
        'endpoint' => 'http://127.0.0.1:47831',
        'last_seen_at' => gmdate('Y-m-d H:i:s'),
        'capabilities' => ['message_summary'],
    ];
}

function homeserver_connector_capability_available(string $capability): bool
{
    return $capability === 'message_summary';
}

$GLOBALS['v66k_homeserver_fail_once'] = false;

function homeserver_connector_request(string $capability, array $payload): array
{
    if ($capability !== 'message_summary') throw new RuntimeException('Unexpected HomeServer capability.');
    if (($payload['wrapper'] ?? '') !== 'rss-pod') throw new RuntimeException('The wrapper authority is missing.');
    if (($payload['resource_authority'] ?? '') !== 'automation_event') throw new RuntimeException('The resource authority is missing.');
    if (($payload['proposal_only'] ?? null) !== true) throw new RuntimeException('The request must be proposal-only.');
    if (($payload['send_allowed'] ?? null) !== false) throw new RuntimeException('The request must deny send authority.');
    if (($payload['tool_execution_allowed'] ?? null) !== false) throw new RuntimeException('The request must deny tool execution.');
    if (!empty($GLOBALS['v66k_homeserver_fail_once'])) {
        $GLOBALS['v66k_homeserver_fail_once'] = false;
        throw new RuntimeException('Synthetic HomeServer transport failure.');
    }
    return ['ok' => true, 'available' => true, 'summary' => 'Owner-reviewed test proposal.', 'receipt_id' => 'v66k-receipt'];
}

function notification_create(
    int $recipientUserId,
    string $category,
    string $title,
    ?string $body = null,
    ?string $linkUrl = null,
    ?string $entityType = null,
    ?int $entityId = null,
    string $priority = 'normal'
): int {
    $statement = db()->prepare(
        'INSERT INTO portal_notifications
            (recipient_user_id,category,title,body,link_url,entity_type,entity_id,priority)
         VALUES (:recipient_user_id,:category,:title,:body,:link_url,:entity_type,:entity_id,:priority)'
    );
    $statement->execute([
        'recipient_user_id' => $recipientUserId,
        'category' => $category,
        'title' => $title,
        'body' => $body,
        'link_url' => $linkUrl,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'priority' => $priority,
    ]);
    $id = (int)db()->lastInsertId();
    if (function_exists('automation_capture_notification')) automation_capture_notification($id);
    return $id;
}

require dirname(__DIR__) . '/portal/automation-rules.php';
require_once dirname(__DIR__) . '/portal/automation-recovery.php';

function v66k_db_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = db();
$suffix = substr(hash('sha256', (string)getmypid() . microtime(true)), 0, 12);
$pdo->prepare(
    'INSERT INTO users (role,email,password_hash,display_name,status)
     VALUES ("admin",:email,:password_hash,:display_name,"active")'
)->execute([
    'email' => 'v66k-owner-' . $suffix . '@example.test',
    'password_hash' => password_hash('Certification-v66K-Password!', PASSWORD_DEFAULT),
    'display_name' => 'v66K Owner',
]);
$ownerId = (int)$pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO users (role,email,password_hash,display_name,status)
     VALUES ("admin",:email,:password_hash,:display_name,"active")'
)->execute([
    'email' => 'v66k-assignee-' . $suffix . '@example.test',
    'password_hash' => password_hash('Certification-v66K-Assignee!', PASSWORD_DEFAULT),
    'display_name' => 'v66K Assignee',
]);
$assigneeId = (int)$pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO crm_contacts (display_name,lifecycle_stage,source)
     VALUES (:display_name,"lead","automation-test")'
)->execute(['display_name' => 'v66K Contact']);
$contactId = (int)$pdo->lastInsertId();

$ruleIds = [];
$notificationIds = [];

try {
    automation_update_settings([
        'enabled' => true,
        'dry_run' => false,
        'worker_batch_size' => 25,
        'approval_expiry_hours' => 72,
        'event_retention_days' => 90,
        'execution_retention_days' => 365,
    ], $ownerId);

    $ruleId = automation_save_rule(0, [
        'name' => 'Urgent review routing',
        'description' => 'Routes high-priority review events.',
        'event_key' => 'system',
        'source_type' => 'lead',
        'priority_order' => 10,
        'stop_processing' => true,
        'condition_mode' => 'all',
        'conditions' => [
            ['field' => 'priority', 'operator' => 'priority_at_least', 'value' => 'high'],
            ['field' => 'title', 'operator' => 'contains', 'value' => 'review'],
        ],
        'actions' => [
            ['type' => 'set_priority', 'parameters' => ['value' => 'urgent']],
            ['type' => 'assign_user', 'parameters' => ['user_id' => $assigneeId]],
            ['type' => 'set_needs_response', 'parameters' => ['value' => true]],
            ['type' => 'set_workflow_status', 'parameters' => ['value' => 'waiting']],
            ['type' => 'set_pinned', 'parameters' => ['value' => true]],
            ['type' => 'add_crm_activity', 'parameters' => ['subject' => 'Automation routed review', 'body' => 'High-priority event routed for review.']],
            ['type' => 'set_crm_follow_up_days', 'parameters' => ['days' => 2]],
            ['type' => 'create_notification', 'parameters' => ['title' => 'Automation routed an event', 'body' => 'Open the Action Center.', 'priority' => 'high']],
            ['type' => 'homeserver_proposal', 'parameters' => ['capability' => 'message_summary', 'instruction' => 'Summarize for owner review.']],
        ],
        'max_executions_per_hour' => 10,
        'max_executions_per_day' => 100,
    ], $ownerId);
    $ruleIds[] = $ruleId;
    $rule = automation_rule($ruleId);
    v66k_db_assert($rule !== null && (string)$rule['status'] === 'draft', 'New rules must remain drafts.');

    $activationBlocked = false;
    try {
        automation_set_rule_status($ruleId, 'active', $ownerId);
    } catch (RuntimeException) {
        $activationBlocked = true;
    }
    v66k_db_assert($activationBlocked, 'Activation must require a current simulation.');

    $nonMatchingRuleId = automation_save_rule(0, [
        'name' => 'Nonmatching simulation gate',
        'event_key' => 'system',
        'source_type' => 'notification',
        'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'required phrase']],
        'actions' => [['type' => 'set_priority', 'parameters' => ['value' => 'high']]],
        'max_executions_per_hour' => 10,
        'max_executions_per_day' => 100,
    ], $ownerId);
    $ruleIds[] = $nonMatchingRuleId;
    $nonMatchingSimulation = automation_simulate_rule($nonMatchingRuleId, [
        'event_key' => 'system',
        'source_type' => 'notification',
        'priority' => 'normal',
        'payload' => ['title' => 'Different sample'],
    ], $ownerId);
    v66k_db_assert($nonMatchingSimulation['matched'] === false, 'The nonmatching simulation fixture must not match.');
    $nonMatchingActivationBlocked = false;
    try { automation_set_rule_status($nonMatchingRuleId, 'active', $ownerId); } catch (RuntimeException) { $nonMatchingActivationBlocked = true; }
    v66k_db_assert($nonMatchingActivationBlocked, 'A nonmatching simulation must not authorize rule activation.');

    $simulation = automation_simulate_rule($ruleId, [
        'event_key' => 'system',
        'source_type' => 'lead',
        'source_id' => 7001,
        'recipient_user_id' => $ownerId,
        'category' => 'system',
        'priority' => 'high',
        'payload' => [
            'title' => 'Needs review',
            'body' => 'Synthetic review event.',
            'inbox_source_type' => 'lead',
            'inbox_source_id' => 7001,
            'crm_contact_id' => $contactId,
        ],
    ], $ownerId);
    v66k_db_assert($simulation['matched'] === true, 'The current rule simulation should match.');
    automation_set_rule_status($ruleId, 'active', $ownerId);

    $eventId = automation_capture_event([
        'event_key' => 'system',
        'source_type' => 'lead',
        'source_id' => 7001,
        'recipient_user_id' => $ownerId,
        'category' => 'system',
        'priority' => 'high',
        'payload' => [
            'title' => 'Lead needs review',
            'body' => 'Please review this qualified inquiry.',
            'entity_type' => 'lead',
            'entity_id' => 7001,
            'inbox_source_type' => 'lead',
            'inbox_source_id' => 7001,
            'crm_contact_id' => $contactId,
        ],
        'dedupe_key' => 'v66k-main-event-' . $suffix,
    ]);
    v66k_db_assert($eventId > 0, 'The automation event was not queued.');
    $duplicateId = automation_capture_event([
        'event_key' => 'system',
        'source_type' => 'lead',
        'source_id' => 7001,
        'recipient_user_id' => $ownerId,
        'priority' => 'high',
        'payload' => ['title' => 'Duplicate'],
        'dedupe_key' => 'v66k-main-event-' . $suffix,
    ]);
    v66k_db_assert($duplicateId === $eventId, 'Automation event capture must be idempotent.');

    $run = automation_run(25);
    v66k_db_assert((int)$run['processed'] === 1 && (int)$run['completed'] === 1, 'The automation worker did not complete the event.');

    $workflowStatement = $pdo->prepare('SELECT * FROM unified_inbox_workflow WHERE source_type="lead" AND source_id=7001');
    $workflowStatement->execute();
    $workflow = $workflowStatement->fetch();
    v66k_db_assert((string)$workflow['priority'] === 'urgent', 'The priority action did not apply.');
    v66k_db_assert((int)$workflow['assigned_user_id'] === $assigneeId, 'The assignment action did not apply.');
    v66k_db_assert((int)$workflow['needs_response'] === 1, 'The needs-response action did not apply.');
    v66k_db_assert((string)$workflow['workflow_status'] === 'waiting', 'The workflow-status action did not apply.');
    v66k_db_assert((int)$workflow['pinned'] === 1, 'The pin action did not apply.');

    $activityStatement = $pdo->prepare('SELECT COUNT(*) FROM crm_activities WHERE contact_id=:contact_id AND subject="Automation routed review"');
    $activityStatement->execute(['contact_id' => $contactId]);
    v66k_db_assert((int)$activityStatement->fetchColumn() === 1, 'The CRM activity action did not apply exactly once.');
    $followUpStatement = $pdo->prepare('SELECT next_follow_up_at FROM crm_contacts WHERE id=:id');
    $followUpStatement->execute(['id' => $contactId]);
    v66k_db_assert((string)$followUpStatement->fetchColumn() !== '', 'The CRM follow-up action did not apply.');

    $notificationStatement = $pdo->prepare('SELECT id FROM portal_notifications WHERE entity_type="automation_execution" ORDER BY id DESC LIMIT 1');
    $notificationStatement->execute();
    $automationNotificationId = (int)$notificationStatement->fetchColumn();
    v66k_db_assert($automationNotificationId > 0, 'The in-app notification action did not apply.');
    $notificationIds[] = $automationNotificationId;
    $recursiveStatement = $pdo->prepare('SELECT COUNT(*) FROM automation_events WHERE dedupe_key=:dedupe_key');
    $recursiveStatement->execute(['dedupe_key' => hash('sha256', 'portal_notification:' . $automationNotificationId)]);
    v66k_db_assert((int)$recursiveStatement->fetchColumn() === 0, 'Automation-created notifications must not recursively create events.');

    $executionStatement = $pdo->prepare('SELECT * FROM automation_executions WHERE event_id=:event_id AND rule_id=:rule_id');
    $executionStatement->execute(['event_id' => $eventId, 'rule_id' => $ruleId]);
    $execution = $executionStatement->fetch();
    v66k_db_assert((string)$execution['status'] === 'awaiting_approval', 'The HomeServer proposal must leave the execution awaiting approval.');
    $approvalStatement = $pdo->prepare('SELECT * FROM automation_approvals WHERE execution_id=:execution_id');
    $approvalStatement->execute(['execution_id' => (int)$execution['id']]);
    $approval = $approvalStatement->fetch();
    v66k_db_assert((string)$approval['status'] === 'pending', 'The HomeServer proposal approval was not created.');

    $resolved = automation_resolve_approval((int)$approval['id'], 'approve', $ownerId);
    v66k_db_assert((string)$resolved['status'] === 'completed', 'The approved HomeServer proposal did not complete.');
    $approvalStatement->execute(['execution_id' => (int)$execution['id']]);
    $approval = $approvalStatement->fetch();
    $approvalResult = json_decode((string)$approval['result_json'], true);
    v66k_db_assert((string)$approval['status'] === 'completed', 'The approval status was not finalized.');
    v66k_db_assert(($approvalResult['proposal'] ?? '') === 'Owner-reviewed test proposal.', 'The bounded HomeServer proposal was not retained.');
    $executionStatement->execute(['event_id' => $eventId, 'rule_id' => $ruleId]);
    v66k_db_assert((string)$executionStatement->fetch()['status'] === 'executed', 'A completed approval must finalize the parent execution.');
    $approvalNotice = $pdo->prepare('SELECT COUNT(*) FROM portal_notifications WHERE entity_type="automation_approval" AND recipient_user_id=:recipient_user_id');
    $approvalNotice->execute(['recipient_user_id' => $ownerId]);
    v66k_db_assert((int)$approvalNotice->fetchColumn() >= 1, 'Approval requests must create an owner-visible notification.');

    automation_process_event(array_merge(
        $pdo->query('SELECT * FROM automation_events WHERE id=' . (int)$eventId)->fetch(),
        ['status' => 'processing']
    ));
    $activityStatement->execute(['contact_id' => $contactId]);
    v66k_db_assert((int)$activityStatement->fetchColumn() === 1, 'Event/rule idempotency must prevent duplicate actions.');

    $retryEventId = automation_capture_event([
        'event_key' => 'system',
        'source_type' => 'lead',
        'source_id' => 7003,
        'recipient_user_id' => $ownerId,
        'category' => 'system',
        'priority' => 'high',
        'payload' => [
            'title' => 'Second lead needs review',
            'body' => 'Synthetic proposal retry event.',
            'inbox_source_type' => 'lead',
            'inbox_source_id' => 7003,
            'crm_contact_id' => $contactId,
        ],
        'dedupe_key' => 'v66k-retry-event-' . $suffix,
    ]);
    automation_run(25);
    $retryExecutionStatement = $pdo->prepare('SELECT * FROM automation_executions WHERE event_id=:event_id AND rule_id=:rule_id');
    $retryExecutionStatement->execute(['event_id' => $retryEventId, 'rule_id' => $ruleId]);
    $retryExecution = $retryExecutionStatement->fetch();
    $retryApprovalStatement = $pdo->prepare('SELECT * FROM automation_approvals WHERE execution_id=:execution_id');
    $retryApprovalStatement->execute(['execution_id' => (int)$retryExecution['id']]);
    $retryApproval = $retryApprovalStatement->fetch();
    $GLOBALS['v66k_homeserver_fail_once'] = true;
    $failedProposal = automation_resolve_approval((int)$retryApproval['id'], 'approve', $ownerId);
    v66k_db_assert((string)$failedProposal['status'] === 'failed', 'A HomeServer transport failure must become durable approval failure evidence.');
    $retryExecutionStatement->execute(['event_id' => $retryEventId, 'rule_id' => $ruleId]);
    v66k_db_assert((string)$retryExecutionStatement->fetch()['status'] === 'partially_executed', 'A failed proposal must refresh the parent execution status.');
    automation_retry_approval((int)$retryApproval['id'], $ownerId);
    $retriedProposal = automation_resolve_approval((int)$retryApproval['id'], 'approve', $ownerId);
    v66k_db_assert((string)$retriedProposal['status'] === 'completed', 'The retried HomeServer proposal did not complete.');

    $pdo->prepare(
        'UPDATE automation_approvals
         SET status="approved",resolved_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),result_json=NULL
         WHERE id=:id'
    )->execute(['id' => (int)$retryApproval['id']]);
    $pdo->prepare(
        'UPDATE automation_action_receipts SET status="approved",error_code=NULL,error_message=NULL WHERE id=:id'
    )->execute(['id' => (int)$retryApproval['action_receipt_id']]);
    v66k_db_assert(automation_recover_interrupted_approvals_complete() === 1, 'Interrupted approved requests must be recovered exactly once across driver row-count semantics.');
    $retryExecutionStatement->execute(['event_id' => $retryEventId, 'rule_id' => $ruleId]);
    v66k_db_assert((string)$retryExecutionStatement->fetch()['status'] === 'partially_executed', 'Recovered interrupted approval evidence must refresh the parent execution.');
    automation_retry_approval((int)$retryApproval['id'], $ownerId);
    $restartRetry = automation_resolve_approval((int)$retryApproval['id'], 'approve', $ownerId);
    v66k_db_assert((string)$restartRetry['status'] === 'completed', 'A recovered approval must complete after explicit retry.');

    $limitedRuleId = automation_save_rule(0, [
        'name' => 'Limited test rule',
        'event_key' => 'delivery_failure',
        'source_type' => 'notification',
        'priority_order' => 20,
        'conditions' => [],
        'actions' => [['type' => 'set_pinned', 'parameters' => ['value' => true]]],
        'max_executions_per_hour' => 1,
        'max_executions_per_day' => 1,
    ], $ownerId);
    $ruleIds[] = $limitedRuleId;
    automation_simulate_rule($limitedRuleId, ['event_key' => 'delivery_failure', 'source_type' => 'notification', 'priority' => 'normal', 'payload' => ['inbox_source_type' => 'notification', 'inbox_source_id' => 8101]], $ownerId);
    automation_set_rule_status($limitedRuleId, 'active', $ownerId);
    foreach ([8101, 8102] as $sourceId) {
        automation_capture_event([
            'event_key' => 'delivery_failure',
            'source_type' => 'notification',
            'source_id' => $sourceId,
            'recipient_user_id' => $ownerId,
            'priority' => 'normal',
            'payload' => ['title' => 'Delivery failed', 'inbox_source_type' => 'notification', 'inbox_source_id' => $sourceId],
            'dedupe_key' => 'v66k-limit-' . $suffix . '-' . $sourceId,
        ]);
    }
    automation_run(25);
    $suppressedStatement = $pdo->prepare('SELECT COUNT(*) FROM automation_executions WHERE rule_id=:rule_id AND status="suppressed"');
    $suppressedStatement->execute(['rule_id' => $limitedRuleId]);
    v66k_db_assert((int)$suppressedStatement->fetchColumn() === 1, 'The rule execution limit did not suppress the second trigger.');

    $counterBeforeDryRun = $pdo->prepare('SELECT COALESCE(SUM(execution_count),0) FROM automation_rule_counters WHERE rule_id=:rule_id');
    $counterBeforeDryRun->execute(['rule_id' => $ruleId]);
    $liveCountBeforeDryRun = (int)$counterBeforeDryRun->fetchColumn();

    automation_update_settings([
        'enabled' => true,
        'dry_run' => true,
        'worker_batch_size' => 25,
        'approval_expiry_hours' => 72,
        'event_retention_days' => 90,
        'execution_retention_days' => 365,
    ], $ownerId);
    $dryEventId = automation_capture_event([
        'event_key' => 'system',
        'source_type' => 'lead',
        'source_id' => 7002,
        'recipient_user_id' => $ownerId,
        'priority' => 'high',
        'payload' => ['title' => 'Dry review event', 'inbox_source_type' => 'lead', 'inbox_source_id' => 7002, 'crm_contact_id' => $contactId],
        'dedupe_key' => 'v66k-dry-' . $suffix,
    ]);
    automation_run(25);
    $dryExecution = $pdo->prepare('SELECT status FROM automation_executions WHERE event_id=:event_id AND rule_id=:rule_id');
    $dryExecution->execute(['event_id' => $dryEventId, 'rule_id' => $ruleId]);
    v66k_db_assert((string)$dryExecution->fetchColumn() === 'simulated', 'Global dry-run must record simulation instead of applying actions.');
    $dryWorkflow = $pdo->query('SELECT COUNT(*) FROM unified_inbox_workflow WHERE source_type="lead" AND source_id=7002')->fetchColumn();
    v66k_db_assert((int)$dryWorkflow === 0, 'Global dry-run must not create workflow state.');
    $counterBeforeDryRun->execute(['rule_id' => $ruleId]);
    v66k_db_assert((int)$counterBeforeDryRun->fetchColumn() === $liveCountBeforeDryRun, 'Global dry-run must not consume live rule limits.');

    $pdo->prepare(
        'INSERT INTO automation_events
            (event_uuid,dedupe_key,event_key,source_type,source_id,recipient_user_id,priority,payload_json,occurred_at,status,lease_token,leased_until,attempt_count,max_attempts)
         VALUES (:event_uuid,:dedupe_key,"system","notification",9999,:recipient_user_id,"normal","{}",UTC_TIMESTAMP(),"processing",:lease_token,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),3,3)'
    )->execute([
        'event_uuid' => automation_uuid(),
        'dedupe_key' => hash('sha256', 'v66k-expired-lease-' . $suffix),
        'recipient_user_id' => $ownerId,
        'lease_token' => bin2hex(random_bytes(32)),
    ]);
    $staleId = (int)$pdo->lastInsertId();
    automation_claim_events(1);
    $staleStatement = $pdo->prepare('SELECT status,last_error_code FROM automation_events WHERE id=:id');
    $staleStatement->execute(['id' => $staleId]);
    $stale = $staleStatement->fetch();
    v66k_db_assert((string)$stale['status'] === 'failed' && (string)$stale['last_error_code'] === 'lease_expired', 'Expired leases at the attempt limit must become durable failures.');

    automation_update_settings([
        'enabled' => true,
        'dry_run' => false,
        'worker_batch_size' => 25,
        'approval_expiry_hours' => 72,
        'event_retention_days' => 7,
        'execution_retention_days' => 30,
    ], $ownerId);
    $pdo->prepare(
        'INSERT INTO automation_events
            (event_uuid,dedupe_key,event_key,source_type,source_id,recipient_user_id,priority,payload_json,occurred_at,status,completed_at,created_at)
         VALUES (:event_uuid,:dedupe_key,"system","notification",9901,:recipient_user_id,"normal","{}",DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 DAY),"completed",DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 DAY))'
    )->execute([
        'event_uuid' => automation_uuid(),
        'dedupe_key' => hash('sha256', 'v66k-retention-event-' . $suffix),
        'recipient_user_id' => $ownerId,
    ]);
    $retentionEventId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO automation_executions
            (execution_uuid,event_id,rule_id,idempotency_key,status,matched_json,proposed_actions_json,applied_actions_json,completed_at,created_at)
         VALUES (:execution_uuid,:event_id,:rule_id,:idempotency_key,"executed","{}","[]","[]",DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 DAY))'
    )->execute([
        'execution_uuid' => automation_uuid(),
        'event_id' => $retentionEventId,
        'rule_id' => $ruleId,
        'idempotency_key' => hash('sha256', 'v66k-retention-execution-' . $suffix),
    ]);
    $retentionExecutionId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO automation_action_receipts (execution_id,action_index,action_type,status,before_json,after_json,created_at)
         VALUES (:execution_id,0,"set_priority","applied","{}","{}",DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 DAY))'
    )->execute(['execution_id' => $retentionExecutionId]);
    $retentionReceiptId = (int)$pdo->lastInsertId();
    automation_cleanup();
    $retentionEventStatement = $pdo->prepare('SELECT COUNT(*) FROM automation_events WHERE id=:id');
    $retentionEventStatement->execute(['id' => $retentionEventId]);
    v66k_db_assert((int)$retentionEventStatement->fetchColumn() === 0, 'Expired event evidence should follow the event retention policy.');
    $retentionExecutionStatement = $pdo->prepare('SELECT event_id FROM automation_executions WHERE id=:id');
    $retentionExecutionStatement->execute(['id' => $retentionExecutionId]);
    v66k_db_assert($retentionExecutionStatement->fetchColumn() === null, 'Execution evidence must survive event cleanup with a null event link.');
    $retentionReceiptStatement = $pdo->prepare('SELECT COUNT(*) FROM automation_action_receipts WHERE id=:id');
    $retentionReceiptStatement->execute(['id' => $retentionReceiptId]);
    v66k_db_assert((int)$retentionReceiptStatement->fetchColumn() === 1, 'Action receipts must survive the shorter event-retention window.');

    fwrite(STDOUT, "Automation Rules v66K database integration passed.\n");
} finally {
    $pdo->prepare('DELETE FROM portal_notifications WHERE recipient_user_id IN (:owner_id,:assignee_id)')->execute(['owner_id' => $ownerId, 'assignee_id' => $assigneeId]);
    $pdo->prepare('DELETE FROM automation_events WHERE recipient_user_id IN (:owner_id,:assignee_id)')->execute(['owner_id' => $ownerId, 'assignee_id' => $assigneeId]);
    foreach (array_reverse($ruleIds) as $ruleId) $pdo->prepare('DELETE FROM automation_rules WHERE id=:id')->execute(['id' => $ruleId]);
    $pdo->prepare('DELETE FROM unified_inbox_workflow WHERE source_type IN ("lead","notification") AND source_id IN (7001,7002,7003,8101,8102)')->execute();
    $pdo->prepare('DELETE FROM crm_activities WHERE contact_id=:contact_id')->execute(['contact_id' => $contactId]);
    $pdo->prepare('DELETE FROM crm_contacts WHERE id=:id')->execute(['id' => $contactId]);
    $pdo->prepare('DELETE FROM users WHERE id IN (:owner_id,:assignee_id)')->execute(['owner_id' => $ownerId, 'assignee_id' => $assigneeId]);
    $pdo->exec('UPDATE automation_settings SET enabled=0,dry_run=1,event_retention_days=90,execution_retention_days=365,updated_by_user_id=NULL WHERE id=1');
}

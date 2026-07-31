from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: str, old: str, new: str, label: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one match, found {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


def replace_between(path: str, start: str, end: str, replacement: str, label: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    start_index = text.find(start)
    if start_index < 0:
        raise SystemExit(f"{label}: start marker not found")
    end_index = text.find(end, start_index + len(start))
    if end_index < 0:
        raise SystemExit(f"{label}: end marker not found")
    file.write_text(text[:start_index] + replacement + text[end_index:], encoding="utf-8")


def insert_before_once(path: str, marker: str, insertion: str, label: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    if insertion.strip() in text:
        return
    count = text.count(marker)
    if count != 1:
        raise SystemExit(f"{label}: expected one marker, found {count}")
    file.write_text(text.replace(marker, insertion + marker, 1), encoding="utf-8")


# A simulation authorizes activation only when the current rule snapshot
# actually matched its bounded sample.
replace_once(
    "portal/automation-rules.php",
    '''function automation_rule_has_current_simulation(array $rule): bool
{
    $statement = db()->prepare(
        'SELECT id FROM automation_executions
         WHERE rule_id=:rule_id AND idempotency_key=:idempotency_key AND status="simulated" LIMIT 1'
    );
    $statement->execute(['rule_id' => (int)$rule['id'], 'idempotency_key' => automation_simulation_key($rule)]);
    return (bool)$statement->fetchColumn();
}''',
    '''function automation_rule_has_current_simulation(array $rule): bool
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
}''',
    "matching simulation activation gate",
)
replace_once(
    "portal/automation-rules.php",
    '''    if ($status === 'active' && !automation_rule_has_current_simulation($rule)) {
        throw new RuntimeException('Run a current simulation before activating this rule.');
    }''',
    '''    if ($status === 'active' && !empty($rule['expires_at']) && strtotime((string)$rule['expires_at']) <= time()) {
        throw new RuntimeException('An expired rule cannot be activated.');
    }
    if ($status === 'active' && !automation_rule_has_current_simulation($rule)) {
        throw new RuntimeException('Run a matching current simulation before activating this rule.');
    }''',
    "rule activation policy",
)

# Owner visibility and execution-state helpers.
helpers = '''function automation_owner_recipient(array $event): int
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

'''
insert_before_once(
    "portal/automation-rules.php",
    "function automation_apply_action(array $event, array $action, int $executionId, int $actionIndex): array\n{",
    helpers,
    "owner and execution helpers",
)

# Approval creation is owner-visible.
replace_once(
    "portal/automation-rules.php",
    '''        ])->execute([
            'approval_uuid' => automation_uuid(),
            'execution_id' => $executionId,
            'receipt_id' => $receiptId,
            'capability' => (string)$parameters['capability'],
            'request_hash' => (string)$after['request_hash'],
            'request_json' => automation_json_encode($after['approval_request']),
            'expires_at' => (string)$after['expires_at'],
        ]);
    }''',
    '''        ])->execute([
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
    }''',
    "approval owner notification",
)

# Claim each event/rule execution exactly once, even under worker races.
replace_between(
    "portal/automation-rules.php",
    "function automation_execution_insert(array $event, array $rule, string $status, array $matched, array $actions): int\n{",
    "function automation_process_rule(array $event, array $rule, bool $dryRun): array\n{",
    '''function automation_execution_insert(array $event, array $rule, string $status, array $matched, array $actions): int
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

''',
    "atomic execution claim",
)

# Dry-run evidence must not consume live rate limits, and duplicate execution
# claims must never reapply actions.
replace_once(
    "portal/automation-rules.php",
    '''        $executionId = automation_execution_insert($event, $rule, 'no_match', ['matched' => false, 'conditions' => $conditionResults], $actions);
        db()->prepare('UPDATE automation_executions SET completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);''',
    '''        $executionId = automation_execution_insert($event, $rule, 'no_match', ['matched' => false, 'conditions' => $conditionResults], $actions);
        if ($executionId <= 0) return ['matched' => false, 'stop' => false, 'status' => 'idempotent'];
        db()->prepare('UPDATE automation_executions SET completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);''',
    "no-match idempotency",
)
replace_once(
    "portal/automation-rules.php",
    '''    if (!automation_rule_limit_reserve($rule)) {
        $executionId = automation_execution_insert($event, $rule, 'suppressed', ['matched' => true, 'conditions' => $conditionResults, 'reason' => 'execution_limit'], $actions);
        db()->prepare('UPDATE automation_executions SET error_code="execution_limit",error_message="The rule execution limit was reached.",completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => 'suppressed'];
    }
    if ($dryRun) {
        $executionId = automation_execution_insert($event, $rule, 'simulated', ['matched' => true, 'conditions' => $conditionResults, 'global_dry_run' => true], $actions);
        db()->prepare('UPDATE automation_executions SET completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => 'simulated'];
    }''',
    '''    if ($dryRun) {
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
    }''',
    "dry-run and limit ordering",
)
replace_once(
    "portal/automation-rules.php",
    "    $executionId = automation_execution_insert($event, $rule, 'matched', ['matched' => true, 'conditions' => $conditionResults], $actions);\n    $applied = [];",
    "    $executionId = automation_execution_insert($event, $rule, 'matched', ['matched' => true, 'conditions' => $conditionResults], $actions);\n    if ($executionId <= 0) return ['matched' => true, 'stop' => false, 'status' => 'idempotent'];\n    $applied = [];",
    "matched execution idempotency",
)
replace_once(
    "portal/automation-rules.php",
    "    db()->prepare(\n        'UPDATE automation_rules SET last_triggered_at=UTC_TIMESTAMP(),execution_count=execution_count+1 WHERE id=:id'",
    "    if ($failures > 0) {\n        automation_notify_owner(\n            $event,\n            'Automation action failed',\n            $failures . ' action(s) failed. Review the immutable execution receipts.',\n            'automation_execution_failure',\n            $executionId,\n            'high'\n        );\n    }\n    db()->prepare(\n        'UPDATE automation_rules SET last_triggered_at=UTC_TIMESTAMP(),execution_count=execution_count+1 WHERE id=:id'",
    "action failure escalation",
)

# Expire rules before claiming work.
insert_before_once(
    "portal/automation-rules.php",
    "function automation_recover_interrupted_approvals(): int\n{",
    '''function automation_expire_rules(): int
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

''',
    "automatic rule expiration",
)
replace_once(
    "portal/automation-rules.php",
    "    $settings = automation_settings();\n    if (!$settings['enabled']) return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'disabled' => true];",
    "    automation_expire_rules();\n    $settings = automation_settings();\n    if (!$settings['enabled']) return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'disabled' => true];",
    "rule expiration worker invocation",
)

# Expiration, approval execution, retry, and event replay state machines.
replace_between(
    "portal/automation-rules.php",
    "function automation_expire_approvals(): int\n{",
    "function automation_resolve_approval(int $approvalId, string $decision, int $userId): array\n{",
    '''function automation_expire_approvals(): int
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

''',
    "approval expiration state refresh",
)
replace_between(
    "portal/automation-rules.php",
    "function automation_resolve_approval(int $approvalId, string $decision, int $userId): array\n{",
    "function automation_retry_event(int $eventId): void\n{",
    '''function automation_resolve_approval(int $approvalId, string $decision, int $userId): array
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

''',
    "approval resolution and retry state machine",
)
replace_between(
    "portal/automation-rules.php",
    "function automation_retry_event(int $eventId): void\n{",
    "function automation_cleanup(): array\n{",
    '''function automation_retry_event(int $eventId): void
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

''',
    "event replay guard",
)
replace_once(
    "portal/automation-rules.php",
    '''         WHERE approval.status="pending"
         ORDER BY approval.created_at,approval.id LIMIT ''',
    '''         WHERE approval.status IN ("pending","failed")
         ORDER BY FIELD(approval.status,"pending","failed"),approval.created_at,approval.id LIMIT ''',
    "reviewable approval query",
)

# Administrator policy and retry controls.
replace_once(
    "portal/automation-admin.php",
    "automation_update_settings($settings + ['enabled' => false], $userId);",
    "automation_update_settings(array_replace($settings, ['enabled' => false]), $userId);",
    "emergency disable semantics",
)
replace_between(
    "portal/automation-admin.php",
    "    if ($action === 'automation_delete_rule') {\n",
    "    if ($action === 'automation_process_queue') {\n",
    "",
    "immutable rule history action",
)
replace_once(
    "portal/automation-admin.php",
    "        if (in_array((string)$rule['status'], ['draft', 'disabled'], true)) echo '<form class=\"automation-inline-form\" method=\"post\" data-confirm-message=\"Permanently delete this rule and its audit history?\">' . csrf_field() . '<input type=\"hidden\" name=\"action\" value=\"automation_delete_rule\"><input type=\"hidden\" name=\"rule_id\" value=\"' . (int)$rule['id'] . '\"><button class=\"automation-button danger\" type=\"submit\">Delete</button></form>';",
    "        echo '<span class=\"automation-rights\">Rule versions and execution history are retained. Disable a rule instead of deleting it.</span>';",
    "immutable rule history UI",
)
replace_once(
    "portal/automation-admin.php",
    "    if ($action === 'automation_create_test_event') {",
    "    if ($action === 'automation_retry_approval') {\n        automation_retry_approval(int_input('approval_id'), $userId);\n        flash('success', 'The failed HomeServer proposal returned to the approval queue.');\n        automation_admin_redirect('approvals');\n    }\n\n    if ($action === 'automation_create_test_event') {",
    "approval retry action",
)
replace_once(
    "portal/automation-admin.php",
    "    if (!$approvals) echo '<div class=\"automation-empty\">No approval requests are waiting.</div>';",
    "    if (!$approvals) echo '<div class=\"automation-empty\">No approval requests require review or retry.</div>';",
    "approval empty state",
)
replace_once(
    "portal/automation-admin.php",
    '''        echo '<article class="automation-row"><div><h3>' . e($approval['rule_name']) . '</h3><p>' . e(status_label((string)$approval['capability'])) . ' · ' . e(status_label((string)($approval['event_key'] ?? 'event'))) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$approval['status']) . '<span class="automation-chip">Expires ' . e(format_datetime((string)$approval['expires_at'])) . '</span></div><details style="margin-top:10px"><summary>Review bounded request</summary><code class="automation-code">' . e(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></details></div><div class="automation-actions"><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="approve"><button class="automation-button primary small" type="submit">Approve proposal</button></form><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="reject"><button class="automation-button danger small" type="submit">Reject</button></form></div></article>';''',
    '''        $approvalActions = '<div class="automation-actions">';
        if ((string)$approval['status'] === 'pending') {
            $approvalActions .= '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="approve"><button class="automation-button primary small" type="submit">Approve proposal</button></form><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="reject"><button class="automation-button danger small" type="submit">Reject</button></form>';
        } else {
            $approvalActions .= '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_retry_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><button class="automation-button small" type="submit">Return to approval queue</button></form>';
        }
        $approvalActions .= '</div>';
        echo '<article class="automation-row"><div><h3>' . e($approval['rule_name']) . '</h3><p>' . e(status_label((string)$approval['capability'])) . ' · ' . e(status_label((string)($approval['event_key'] ?? 'event'))) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$approval['status']) . '<span class="automation-chip">Expires ' . e(format_datetime((string)$approval['expires_at'])) . '</span></div><details style="margin-top:10px"><summary>Review bounded request</summary><code class="automation-code">' . e(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></details></div>' . $approvalActions . '</article>';''',
    "approval retry UI",
)

# Preserve longer-lived execution evidence after event cleanup.
for path in ["database/automation_rules_v66k.sql", "database/north_mountain_portal.sql"]:
    replace_once(
        path,
        "FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE CASCADE",
        "FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE SET NULL",
        f"execution audit retention in {path}",
    )

# Permanent source and cleanup contracts.
source_path = ROOT / "tests/automation-rules-v66k.php"
source = source_path.read_text(encoding="utf-8")
replace_once(
    "tests/automation-rules-v66k.php",
    "v66k_assert(str_contains($admin, 'Run a current simulation before activating this rule.'), 'The Action Center must enforce simulation before activation.');",
    "v66k_assert(str_contains($core, \"($evidence['matched'] ?? null) === true\"), 'Only a matching current simulation may authorize activation.');\nv66k_assert(str_contains($admin, \"array_replace($settings, ['enabled' => false])\"), 'Emergency disable must replace the enabled setting.');\nv66k_assert(!str_contains($admin, 'DELETE FROM automation_rules'), 'Rule and audit history must not be hard-deleted.');\nv66k_assert(strpos($core, 'if ($dryRun)') < strpos($core, 'if (!automation_rule_limit_reserve($rule))'), 'Dry-run must not consume live execution limits.');\nv66k_assert(str_contains($core, 'automation_refresh_execution_status'), 'Approval outcomes must refresh parent execution state.');\nv66k_assert(str_contains($core, 'automation_retry_approval'), 'Failed proposal requests must have a bounded retry path.');\nv66k_assert(str_contains($core, 'automation_notify_owner'), 'Approvals and failures must be visible to the owner.');\nv66k_assert(str_contains($core, 'automation_expire_rules'), 'Expired active rules must be closed automatically.');\nv66k_assert(str_contains($migration, 'FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE SET NULL'), 'Event cleanup must preserve longer-lived execution evidence.');",
    "production policy source assertions",
)
cleanup_old = '''v66k_assert(!is_file($root . '/tools/apply-automation-rules-v66k.py'), 'The temporary v66K integration script must be removed.');
v66k_assert(!is_file($root . '/.github/workflows/apply-automation-rules-v66k.yml'), 'The temporary v66K integration workflow must be removed.');'''
cleanup_new = '''$temporaryPaths = [
    'tools/apply-automation-rules-v66k.py',
    'tools/audit-retention-finalize-automation-rules-v66k.py',
    'tools/finalize-automation-rules-v66k.py',
    'tools/policy-finalize-automation-rules-v66k.py',
    'tools/restart-safety-finalize-automation-rules-v66k.py',
    'tools/restart-safety-finalize-automation-rules-v66k-v2.py',
    'tools/restart-safety-finalize-automation-rules-v66k-v3.py',
    'tools/harden-automation-rules-v66k-production.py',
    '.github/workflows/apply-automation-rules-v66k.yml',
    '.github/workflows/apply-complete-automation-rules-v66k-final.yml',
    '.github/workflows/apply-complete-automation-rules-v66k-final-macos.yml',
    '.github/workflows/audit-retention-finalize-automation-rules-v66k.yml',
    '.github/workflows/finalize-automation-rules-v66k.yml',
    '.github/workflows/policy-finalize-automation-rules-v66k.yml',
    '.github/workflows/restart-safety-finalize-automation-rules-v66k.yml',
    '.github/workflows/restart-safety-finalize-automation-rules-v66k-macos.yml',
    '.github/workflows/harden-automation-rules-v66k-production.yml',
];
foreach ($temporaryPaths as $temporaryPath) {
    v66k_assert(!is_file($root . '/' . $temporaryPath), 'Temporary v66K controller remains: ' . $temporaryPath);
}'''
replace_once("tests/automation-rules-v66k.php", cleanup_old, cleanup_new, "complete temporary cleanup contract")

# Permanent workflow checks every temporary path after this controller is removed.
replace_once(
    ".github/workflows/automation-rules-quality.yml",
    '''          test ! -e tools/apply-automation-rules-v66k.py
          test ! -e .github/workflows/apply-automation-rules-v66k.yml''',
    '''          for path in \
            tools/apply-automation-rules-v66k.py \
            tools/audit-retention-finalize-automation-rules-v66k.py \
            tools/finalize-automation-rules-v66k.py \
            tools/policy-finalize-automation-rules-v66k.py \
            tools/restart-safety-finalize-automation-rules-v66k.py \
            tools/restart-safety-finalize-automation-rules-v66k-v2.py \
            tools/restart-safety-finalize-automation-rules-v66k-v3.py \
            tools/harden-automation-rules-v66k-production.py \
            .github/workflows/apply-automation-rules-v66k.yml \
            .github/workflows/apply-complete-automation-rules-v66k-final.yml \
            .github/workflows/apply-complete-automation-rules-v66k-final-macos.yml \
            .github/workflows/audit-retention-finalize-automation-rules-v66k.yml \
            .github/workflows/finalize-automation-rules-v66k.yml \
            .github/workflows/policy-finalize-automation-rules-v66k.yml \
            .github/workflows/restart-safety-finalize-automation-rules-v66k.yml \
            .github/workflows/restart-safety-finalize-automation-rules-v66k-macos.yml \
            .github/workflows/harden-automation-rules-v66k-production.yml; do
              test ! -e "$path"
          done''',
    "permanent cleanup workflow contract",
)

# Live dual-engine tests: matching simulation, approval retry and restart safety,
# dry-run counters, and audit retention.
replace_once(
    "tests/automation-rules-db-v66k.php",
    "function homeserver_connector_request(string $capability, array $payload): array\n{",
    "$GLOBALS['v66k_homeserver_fail_once'] = false;\n\nfunction homeserver_connector_request(string $capability, array $payload): array\n{",
    "HomeServer failure fixture flag",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    if (($payload['tool_execution_allowed'] ?? null) !== false) throw new RuntimeException('The request must deny tool execution.');\n    return ['ok' => true, 'available' => true, 'summary' => 'Owner-reviewed test proposal.', 'receipt_id' => 'v66k-receipt'];",
    "    if (($payload['tool_execution_allowed'] ?? null) !== false) throw new RuntimeException('The request must deny tool execution.');\n    if (!empty($GLOBALS['v66k_homeserver_fail_once'])) {\n        $GLOBALS['v66k_homeserver_fail_once'] = false;\n        throw new RuntimeException('Synthetic HomeServer transport failure.');\n    }\n    return ['ok' => true, 'available' => true, 'summary' => 'Owner-reviewed test proposal.', 'receipt_id' => 'v66k-receipt'];",
    "HomeServer failure fixture",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    v66k_db_assert($activationBlocked, 'Activation must require a current simulation.');\n\n    $simulation = automation_simulate_rule($ruleId, [",
    "    v66k_db_assert($activationBlocked, 'Activation must require a current simulation.');\n\n    $nonMatchingRuleId = automation_save_rule(0, [\n        'name' => 'Nonmatching simulation gate',\n        'event_key' => 'system',\n        'source_type' => 'notification',\n        'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'required phrase']],\n        'actions' => [['type' => 'set_priority', 'parameters' => ['value' => 'high']]],\n        'max_executions_per_hour' => 10,\n        'max_executions_per_day' => 100,\n    ], $ownerId);\n    $ruleIds[] = $nonMatchingRuleId;\n    $nonMatchingSimulation = automation_simulate_rule($nonMatchingRuleId, [\n        'event_key' => 'system',\n        'source_type' => 'notification',\n        'priority' => 'normal',\n        'payload' => ['title' => 'Different sample'],\n    ], $ownerId);\n    v66k_db_assert($nonMatchingSimulation['matched'] === false, 'The nonmatching simulation fixture must not match.');\n    $nonMatchingActivationBlocked = false;\n    try { automation_set_rule_status($nonMatchingRuleId, 'active', $ownerId); } catch (RuntimeException) { $nonMatchingActivationBlocked = true; }\n    v66k_db_assert($nonMatchingActivationBlocked, 'A nonmatching simulation must not authorize rule activation.');\n\n    $simulation = automation_simulate_rule($ruleId, [",
    "nonmatching simulation database gate",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    v66k_db_assert(($approvalResult['proposal'] ?? '') === 'Owner-reviewed test proposal.', 'The bounded HomeServer proposal was not retained.');",
    "    v66k_db_assert(($approvalResult['proposal'] ?? '') === 'Owner-reviewed test proposal.', 'The bounded HomeServer proposal was not retained.');\n    $executionStatement->execute(['event_id' => $eventId, 'rule_id' => $ruleId]);\n    v66k_db_assert((string)$executionStatement->fetch()['status'] === 'executed', 'A completed approval must finalize the parent execution.');\n    $approvalNotice = $pdo->prepare('SELECT COUNT(*) FROM portal_notifications WHERE entity_type=\"automation_approval\" AND recipient_user_id=:recipient_user_id');\n    $approvalNotice->execute(['recipient_user_id' => $ownerId]);\n    v66k_db_assert((int)$approvalNotice->fetchColumn() >= 1, 'Approval requests must create an owner-visible notification.');",
    "approval completion assertions",
)
# Replace the broken undefined restart fixture with a complete retry scenario.
replace_between(
    "tests/automation-rules-db-v66k.php",
    "\n\n    $pdo->prepare(\n        'UPDATE automation_approvals\n         SET status=\"approved\",resolved_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),result_json=NULL",
    "    $limitedRuleId = automation_save_rule(0, [",
    '''

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
    v66k_db_assert(automation_recover_interrupted_approvals() === 1, 'Interrupted approved requests must be recovered exactly once.');
    automation_retry_approval((int)$retryApproval['id'], $ownerId);
    $restartRetry = automation_resolve_approval((int)$retryApproval['id'], 'approve', $ownerId);
    v66k_db_assert((string)$restartRetry['status'] === 'completed', 'A recovered approval must complete after explicit retry.');

''',
    "complete approval retry fixture",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    automation_update_settings([\n        'enabled' => true,\n        'dry_run' => true,",
    "    $counterBeforeDryRun = $pdo->prepare('SELECT COALESCE(SUM(execution_count),0) FROM automation_rule_counters WHERE rule_id=:rule_id');\n    $counterBeforeDryRun->execute(['rule_id' => $ruleId]);\n    $liveCountBeforeDryRun = (int)$counterBeforeDryRun->fetchColumn();\n\n    automation_update_settings([\n        'enabled' => true,\n        'dry_run' => true,",
    "dry-run counter baseline",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    v66k_db_assert((int)$dryWorkflow === 0, 'Global dry-run must not create workflow state.');",
    "    v66k_db_assert((int)$dryWorkflow === 0, 'Global dry-run must not create workflow state.');\n    $counterBeforeDryRun->execute(['rule_id' => $ruleId]);\n    v66k_db_assert((int)$counterBeforeDryRun->fetchColumn() === $liveCountBeforeDryRun, 'Global dry-run must not consume live rule limits.');",
    "dry-run counter assertion",
)
retention = '''
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

'''
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    fwrite(STDOUT, \"Automation Rules v66K database integration passed.\\n\");",
    retention + "    fwrite(STDOUT, \"Automation Rules v66K database integration passed.\\n\");",
    "audit retention database scenario",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "source_id IN (7001,7002,8101,8102)",
    "source_id IN (7001,7002,7003,8101,8102)",
    "retry fixture cleanup",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "$pdo->exec('UPDATE automation_settings SET enabled=0,dry_run=1,updated_by_user_id=NULL WHERE id=1');",
    "$pdo->exec('UPDATE automation_settings SET enabled=0,dry_run=1,event_retention_days=90,execution_retention_days=365,updated_by_user_id=NULL WHERE id=1');",
    "settings cleanup restoration",
)

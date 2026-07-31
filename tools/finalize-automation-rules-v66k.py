from pathlib import Path
import runpy

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


# Apply the original deterministic application integration if it has not
# already landed from the earlier controller run.
admin_app = (ROOT / "portal/admin.php").read_text(encoding="utf-8")
if "require_once __DIR__ . '/automation-admin.php';" not in admin_app:
    runpy.run_path(str(ROOT / "tools/apply-automation-rules-v66k.py"), run_name="__main__")

helpers = r'''function automation_owner_recipient(array $event): int
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
    "automation owner and execution helpers",
)

old_approval_block = r'''    if ($status === 'awaiting_approval') {
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
    }
'''
new_approval_block = r'''    if ($status === 'awaiting_approval') {
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
'''
replace_once(
    "portal/automation-rules.php",
    old_approval_block,
    new_approval_block,
    "approval owner notification",
)

replace_once(
    "portal/automation-rules.php",
    "    db()->prepare(\n        'UPDATE automation_rules SET last_triggered_at=UTC_TIMESTAMP(),execution_count=execution_count+1 WHERE id=:id'",
    "    if ($failures > 0) {\n        automation_notify_owner(\n            $event,\n            'Automation action failed',\n            $failures . ' action(s) failed. Review the immutable execution receipts.',\n            'automation_execution_failure',\n            $executionId,\n            'high'\n        );\n    }\n    db()->prepare(\n        'UPDATE automation_rules SET last_triggered_at=UTC_TIMESTAMP(),execution_count=execution_count+1 WHERE id=:id'",
    "execution failure escalation",
)

expire_function = r'''function automation_expire_approvals(): int
{
    if (!automation_schema_available()) return 0;
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

'''
replace_between(
    "portal/automation-rules.php",
    "function automation_expire_approvals(): int\n{",
    "function automation_resolve_approval(int $approvalId, string $decision, int $userId): array\n{",
    expire_function,
    "approval expiration state refresh",
)

resolve_and_retry = r'''function automation_resolve_approval(int $approvalId, string $decision, int $userId): array
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
            $result = [
                'ok' => false,
                'available' => false,
                'message' => $exception->getMessage(),
            ];
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
        db()->prepare('UPDATE automation_approvals SET status=:status,result_json=:result_json WHERE id=:id')
            ->execute(['status' => $finalStatus, 'result_json' => automation_json_encode($safeResult), 'id' => $approvalId]);
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
            'UPDATE automation_approvals
             SET status="pending",result_json=NULL,resolved_by_user_id=NULL,resolved_at=NULL
             WHERE id=:id'
        )->execute(['id' => $approvalId]);
        $pdo->prepare(
            'UPDATE automation_action_receipts
             SET status="awaiting_approval",after_json=NULL,error_code=NULL,error_message=NULL,
                 approved_by_user_id=NULL,approved_at=NULL
             WHERE id=:id'
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
             last_error_code=NULL,last_error_message=NULL,completed_at=NULL
         WHERE id=:id'
    )->execute(['id' => $eventId]);
}

'''
replace_between(
    "portal/automation-rules.php",
    "function automation_resolve_approval(int $approvalId, string $decision, int $userId): array\n{",
    "function automation_cleanup(): array\n{",
    resolve_and_retry,
    "approval execution and retry state machine",
)

replace_once(
    "portal/automation-rules.php",
    "         WHERE approval.status=\"pending\"\n         ORDER BY approval.created_at,approval.id LIMIT '",
    "         WHERE approval.status IN (\"pending\",\"failed\")\n         ORDER BY FIELD(approval.status,\"pending\",\"failed\"),approval.created_at,approval.id LIMIT '",
    "reviewable approvals query",
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

old_approval_row = r'''        echo '<article class="automation-row"><div><h3>' . e($approval['rule_name']) . '</h3><p>' . e(status_label((string)$approval['capability'])) . ' · ' . e(status_label((string)($approval['event_key'] ?? 'event'))) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$approval['status']) . '<span class="automation-chip">Expires ' . e(format_datetime((string)$approval['expires_at'])) . '</span></div><details style="margin-top:10px"><summary>Review bounded request</summary><code class="automation-code">' . e(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></details></div><div class="automation-actions"><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="approve"><button class="automation-button primary small" type="submit">Approve proposal</button></form><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="reject"><button class="automation-button danger small" type="submit">Reject</button></form></div></article>';
'''
new_approval_row = r'''        $approvalActions = '<div class="automation-actions">';
        if ((string)$approval['status'] === 'pending') {
            $approvalActions .= '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="approve"><button class="automation-button primary small" type="submit">Approve proposal</button></form><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_resolve_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><input type="hidden" name="decision" value="reject"><button class="automation-button danger small" type="submit">Reject</button></form>';
        } else {
            $approvalActions .= '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="automation_retry_approval"><input type="hidden" name="approval_id" value="' . (int)$approval['id'] . '"><button class="automation-button small" type="submit">Return to approval queue</button></form>';
        }
        $approvalActions .= '</div>';
        echo '<article class="automation-row"><div><h3>' . e($approval['rule_name']) . '</h3><p>' . e(status_label((string)$approval['capability'])) . ' · ' . e(status_label((string)($approval['event_key'] ?? 'event'))) . '</p><div class="automation-row-meta">' . automation_admin_status_chip((string)$approval['status']) . '<span class="automation-chip">Expires ' . e(format_datetime((string)$approval['expires_at'])) . '</span></div><details style="margin-top:10px"><summary>Review bounded request</summary><code class="automation-code">' . e(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></details></div>' . $approvalActions . '</article>';
'''
replace_once(
    "portal/automation-admin.php",
    old_approval_row,
    new_approval_row,
    "approval retry controls",
)

# Extend the live database certification with execution-state finalization and
# a failed HomeServer proposal retry.
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
    "    v66k_db_assert(($approvalResult['proposal'] ?? '') === 'Owner-reviewed test proposal.', 'The bounded HomeServer proposal was not retained.');",
    "    v66k_db_assert(($approvalResult['proposal'] ?? '') === 'Owner-reviewed test proposal.', 'The bounded HomeServer proposal was not retained.');\n    $executionStatement->execute(['event_id' => $eventId, 'rule_id' => $ruleId]);\n    v66k_db_assert((string)$executionStatement->fetch()['status'] === 'executed', 'A completed approval must finalize the parent execution.');\n    $approvalNotice = $pdo->prepare('SELECT COUNT(*) FROM portal_notifications WHERE entity_type=\"automation_approval\" AND recipient_user_id=:recipient_user_id');\n    $approvalNotice->execute(['recipient_user_id' => $ownerId]);\n    v66k_db_assert((int)$approvalNotice->fetchColumn() >= 1, 'Approval requests must create an owner-visible notification.');",
    "approval completion database assertions",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    v66k_db_assert((int)$activityStatement->fetchColumn() === 1, 'Event/rule idempotency must prevent duplicate actions.');\n\n    $limitedRuleId = automation_save_rule(0, [",
    "    v66k_db_assert((int)$activityStatement->fetchColumn() === 1, 'Event/rule idempotency must prevent duplicate actions.');\n\n    $retryEventId = automation_capture_event([\n        'event_key' => 'system',\n        'source_type' => 'lead',\n        'source_id' => 7003,\n        'recipient_user_id' => $ownerId,\n        'category' => 'system',\n        'priority' => 'high',\n        'payload' => [\n            'title' => 'Second lead needs review',\n            'body' => 'Synthetic proposal retry event.',\n            'inbox_source_type' => 'lead',\n            'inbox_source_id' => 7003,\n            'crm_contact_id' => $contactId,\n        ],\n        'dedupe_key' => 'v66k-retry-event-' . $suffix,\n    ]);\n    automation_run(25);\n    $retryExecutionStatement = $pdo->prepare('SELECT * FROM automation_executions WHERE event_id=:event_id AND rule_id=:rule_id');\n    $retryExecutionStatement->execute(['event_id' => $retryEventId, 'rule_id' => $ruleId]);\n    $retryExecution = $retryExecutionStatement->fetch();\n    $retryApprovalStatement = $pdo->prepare('SELECT * FROM automation_approvals WHERE execution_id=:execution_id');\n    $retryApprovalStatement->execute(['execution_id' => (int)$retryExecution['id']]);\n    $retryApproval = $retryApprovalStatement->fetch();\n    $GLOBALS['v66k_homeserver_fail_once'] = true;\n    $failedProposal = automation_resolve_approval((int)$retryApproval['id'], 'approve', $ownerId);\n    v66k_db_assert((string)$failedProposal['status'] === 'failed', 'A HomeServer transport failure must become durable approval failure evidence.');\n    $retryExecutionStatement->execute(['event_id' => $retryEventId, 'rule_id' => $ruleId]);\n    v66k_db_assert((string)$retryExecutionStatement->fetch()['status'] === 'partially_executed', 'A failed proposal must refresh the parent execution status.');\n    automation_retry_approval((int)$retryApproval['id'], $ownerId);\n    $retryApprovalStatement->execute(['execution_id' => (int)$retryExecution['id']]);\n    v66k_db_assert((string)$retryApprovalStatement->fetch()['status'] === 'pending', 'A failed proposal must return safely to the approval queue.');\n    $retriedProposal = automation_resolve_approval((int)$retryApproval['id'], 'approve', $ownerId);\n    v66k_db_assert((string)$retriedProposal['status'] === 'completed', 'The retried HomeServer proposal did not complete.');\n    $retryExecutionStatement->execute(['event_id' => $retryEventId, 'rule_id' => $ruleId]);\n    v66k_db_assert((string)$retryExecutionStatement->fetch()['status'] === 'executed', 'A successful retry must finalize the parent execution.');\n\n    $limitedRuleId = automation_save_rule(0, [",
    "approval retry database scenario",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "source_id IN (7001,7002,8101,8102)",
    "source_id IN (7001,7002,7003,8101,8102)",
    "approval retry fixture cleanup",
)

# Extend source contracts for the final approval/replay boundary.
replace_once(
    "tests/automation-rules-v66k.php",
    "v66k_assert(str_contains($core, 'automation_rule_has_current_simulation'), 'Active rules must require a current simulation.');",
    "v66k_assert(str_contains($core, 'automation_rule_has_current_simulation'), 'Active rules must require a current simulation.');\nv66k_assert(str_contains($core, 'automation_refresh_execution_status'), 'Approval outcomes must refresh parent execution state.');\nv66k_assert(str_contains($core, 'automation_retry_approval'), 'Failed proposal requests must have a bounded retry path.');\nv66k_assert(str_contains($core, 'automation_notify_owner'), 'Approvals and failures must be visible to the owner.');",
    "final approval source contracts",
)

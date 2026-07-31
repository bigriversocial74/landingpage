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


# Ensure every prior integration, policy, and retention repair is present.
core = (ROOT / "portal/automation-rules.php").read_text(encoding="utf-8")
if "ON DELETE SET NULL" not in (ROOT / "database/automation_rules_v66k.sql").read_text(encoding="utf-8"):
    runpy.run_path(str(ROOT / "tools/audit-retention-finalize-automation-rules-v66k.py"), run_name="__main__")

recovery = '''function automation_recover_interrupted_approvals(): int
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

'''
replace_once(
    "portal/automation-rules.php",
    "function automation_expire_approvals(): int\n{",
    recovery + "function automation_expire_approvals(): int\n{",
    "interrupted approval recovery function",
)
replace_once(
    "portal/automation-rules.php",
    "function automation_expire_approvals(): int\n{\n    if (!automation_schema_available()) return 0;",
    "function automation_expire_approvals(): int\n{\n    if (!automation_schema_available()) return 0;\n    automation_recover_interrupted_approvals();",
    "approval recovery invocation",
)

old_finalize = '''        $finalStatus = $safeResult['ok'] ? 'completed' : 'failed';
        db()->prepare('UPDATE automation_approvals SET status=:status,result_json=:result_json WHERE id=:id')
            ->execute(['status' => $finalStatus, 'result_json' => automation_json_encode($safeResult), 'id' => $approvalId]);
        db()->prepare('UPDATE automation_action_receipts SET status=:status,after_json=:after_json,error_code=:error_code,error_message=:error_message WHERE id=:id')
            ->execute([
                'status' => $safeResult['ok'] ? 'applied' : 'failed',
                'after_json' => automation_json_encode($safeResult),
                'error_code' => $safeResult['ok'] ? null : 'homeserver_proposal_failed',
                'error_message' => $safeResult['ok'] ? null : ($safeResult['message'] ?: 'The HomeServer proposal failed.'),
                'id' => (int)$approval['action_receipt_id'],
            ]);'''
new_finalize = '''        $finalStatus = $safeResult['ok'] ? 'completed' : 'failed';
        $approvalFinalize = db()->prepare(
            'UPDATE automation_approvals
             SET status=:status,result_json=:result_json
             WHERE id=:id AND status="approved"'
        );
        $approvalFinalize->execute([
            'status' => $finalStatus,
            'result_json' => automation_json_encode($safeResult),
            'id' => $approvalId,
        ]);
        if ($approvalFinalize->rowCount() !== 1) {
            return ['status' => 'superseded', 'result' => $safeResult];
        }
        db()->prepare('UPDATE automation_action_receipts SET status=:status,after_json=:after_json,error_code=:error_code,error_message=:error_message WHERE id=:id')
            ->execute([
                'status' => $safeResult['ok'] ? 'applied' : 'failed',
                'after_json' => automation_json_encode($safeResult),
                'error_code' => $safeResult['ok'] ? null : 'homeserver_proposal_failed',
                'error_message' => $safeResult['ok'] ? null : ($safeResult['message'] ?: 'The HomeServer proposal failed.'),
                'id' => (int)$approval['action_receipt_id'],
            ]);'''
replace_once(
    "portal/automation-rules.php",
    old_finalize,
    new_finalize,
    "approval compare-and-set finalization",
)

replace_once(
    "tests/automation-rules-v66k.php",
    "v66k_assert(str_contains($core, 'automation_retry_approval'), 'Failed proposal requests must have a bounded retry path.');",
    "v66k_assert(str_contains($core, 'automation_retry_approval'), 'Failed proposal requests must have a bounded retry path.');\nv66k_assert(str_contains($core, 'automation_recover_interrupted_approvals'), 'Interrupted approved requests must become retryable failure evidence.');\nv66k_assert(str_contains($core, 'WHERE id=:id AND status=\"approved\"'), 'HomeServer results must use compare-and-set finalization.');",
    "restart-safe approval source contracts",
)

restart_scenario = r'''
    $pdo->prepare(
        'UPDATE automation_approvals
         SET status="approved",resolved_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),result_json=NULL
         WHERE id=:id'
    )->execute(['id' => (int)$retryApproval['id']]);
    $pdo->prepare(
        'UPDATE automation_action_receipts
         SET status="approved",error_code=NULL,error_message=NULL
         WHERE id=:id'
    )->execute(['id' => (int)$retryApproval['action_receipt_id']]);
    v66k_db_assert(automation_recover_interrupted_approvals() === 1, 'Interrupted approved requests must be recovered exactly once.');
    $retryApprovalStatement->execute(['execution_id' => (int)$retryExecution['id']]);
    v66k_db_assert((string)$retryApprovalStatement->fetch()['status'] === 'failed', 'Interrupted approval recovery must create retryable failure evidence.');
    automation_retry_approval((int)$retryApproval['id'], $ownerId);
    $restartRetry = automation_resolve_approval((int)$retryApproval['id'], 'approve', $ownerId);
    v66k_db_assert((string)$restartRetry['status'] === 'completed', 'A recovered approval must complete after explicit retry.');
'''
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    $limitedRuleId = automation_save_rule(0, [",
    restart_scenario + "\n    $limitedRuleId = automation_save_rule(0, [",
    "interrupted approval database scenario",
)

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


# Ensure all earlier integration, approval, and policy hardening is applied.
core = (ROOT / "portal/automation-rules.php").read_text(encoding="utf-8")
if "function automation_expire_rules" not in core:
    runpy.run_path(str(ROOT / "tools/policy-finalize-automation-rules-v66k.py"), run_name="__main__")

# Event retention must not erase longer-lived execution, action, approval, or
# version evidence. Preserve executions by nulling their event link.
for path in ["database/automation_rules_v66k.sql", "database/north_mountain_portal.sql"]:
    replace_once(
        path,
        "CONSTRAINT fk_automation_executions_event\n        FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE CASCADE",
        "CONSTRAINT fk_automation_executions_event\n        FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE SET NULL",
        f"execution audit retention in {path}",
    )

replace_once(
    "tests/automation-rules-v66k.php",
    "v66k_assert(!str_contains($admin, 'DELETE FROM automation_rules'), 'Rule and audit history must not be hard-deleted.');",
    "v66k_assert(!str_contains($admin, 'DELETE FROM automation_rules'), 'Rule and audit history must not be hard-deleted.');\nv66k_assert(str_contains($migration, 'FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE SET NULL'), 'Event cleanup must preserve longer-lived execution evidence.');",
    "execution retention source contract",
)

retention_scenario = r'''
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
        'INSERT INTO automation_action_receipts
            (execution_id,action_index,action_type,status,before_json,after_json,created_at)
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
    retention_scenario + "\n    fwrite(STDOUT, \"Automation Rules v66K database integration passed.\\n\");",
    "live audit retention scenario",
)
replace_once(
    "tests/automation-rules-db-v66k.php",
    "$pdo->exec('UPDATE automation_settings SET enabled=0,dry_run=1,updated_by_user_id=NULL WHERE id=1');",
    "$pdo->exec('UPDATE automation_settings SET enabled=0,dry_run=1,event_retention_days=90,execution_retention_days=365,updated_by_user_id=NULL WHERE id=1');",
    "automation settings fixture restoration",
)

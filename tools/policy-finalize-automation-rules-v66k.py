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


# Ensure the complete integration and approval hardening are present first.
core = (ROOT / "portal/automation-rules.php").read_text(encoding="utf-8")
if "function automation_refresh_execution_status" not in core:
    runpy.run_path(str(ROOT / "tools/finalize-automation-rules-v66k.py"), run_name="__main__")

# Emergency disable must replace the enabled value; PHP array union preserves
# the left-hand value and could otherwise leave automation active.
replace_once(
    "portal/automation-admin.php",
    "automation_update_settings($settings + ['enabled' => false], $userId);",
    "automation_update_settings(array_replace($settings, ['enabled' => false]), $userId);",
    "emergency disable replacement semantics",
)

# A current simulation authorizes activation only when the current snapshot
# actually matched the bounded sample.
old_simulation = '''function automation_rule_has_current_simulation(array $rule): bool
{
    $statement = db()->prepare(
        'SELECT id FROM automation_executions
         WHERE rule_id=:rule_id AND idempotency_key=:idempotency_key AND status="simulated" LIMIT 1'
    );
    $statement->execute(['rule_id' => (int)$rule['id'], 'idempotency_key' => automation_simulation_key($rule)]);
    return (bool)$statement->fetchColumn();
}'''
new_simulation = '''function automation_rule_has_current_simulation(array $rule): bool
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
}'''
replace_once(
    "portal/automation-rules.php",
    old_simulation,
    new_simulation,
    "matching simulation activation gate",
)

replace_once(
    "portal/automation-rules.php",
    "    if ($status === 'active' && !automation_rule_has_current_simulation($rule)) {\n        throw new RuntimeException('Run a current simulation before activating this rule.');\n    }",
    "    if ($status === 'active' && !empty($rule['expires_at']) && strtotime((string)$rule['expires_at']) <= time()) {\n        throw new RuntimeException('An expired rule cannot be activated.');\n    }\n    if ($status === 'active' && !automation_rule_has_current_simulation($rule)) {\n        throw new RuntimeException('Run a matching current simulation before activating this rule.');\n    }",
    "expired and matching activation gate",
)

# Global dry-run records evidence without consuming the rule's live hourly or
# daily execution allowance.
old_order = '''    if (!automation_rule_limit_reserve($rule)) {
        $executionId = automation_execution_insert($event, $rule, 'suppressed', ['matched' => true, 'conditions' => $conditionResults, 'reason' => 'execution_limit'], $actions);
        db()->prepare('UPDATE automation_executions SET error_code="execution_limit",error_message="The rule execution limit was reached.",completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => 'suppressed'];
    }
    if ($dryRun) {
        $executionId = automation_execution_insert($event, $rule, 'simulated', ['matched' => true, 'conditions' => $conditionResults, 'global_dry_run' => true], $actions);
        db()->prepare('UPDATE automation_executions SET completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => 'simulated'];
    }'''
new_order = '''    if ($dryRun) {
        $executionId = automation_execution_insert($event, $rule, 'simulated', ['matched' => true, 'conditions' => $conditionResults, 'global_dry_run' => true], $actions);
        db()->prepare('UPDATE automation_executions SET completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => 'simulated'];
    }
    if (!automation_rule_limit_reserve($rule)) {
        $executionId = automation_execution_insert($event, $rule, 'suppressed', ['matched' => true, 'conditions' => $conditionResults, 'reason' => 'execution_limit'], $actions);
        db()->prepare('UPDATE automation_executions SET error_code="execution_limit",error_message="The rule execution limit was reached.",completed_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $executionId]);
        return ['matched' => true, 'stop' => !empty($rule['stop_processing']), 'status' => 'suppressed'];
    }'''
replace_once(
    "portal/automation-rules.php",
    old_order,
    new_order,
    "dry-run limit isolation",
)

# Rule history is audit evidence. Rules may be disabled but never hard-deleted
# through the Action Center.
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

expire_rules = '''function automation_expire_rules(): int
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

'''
replace_once(
    "portal/automation-rules.php",
    "function automation_expire_approvals(): int\n{",
    expire_rules + "function automation_expire_approvals(): int\n{",
    "automatic rule expiration",
)
replace_once(
    "portal/automation-rules.php",
    "    $settings = automation_settings();\n    if (!$settings['enabled']) return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'disabled' => true];",
    "    automation_expire_rules();\n    $settings = automation_settings();\n    if (!$settings['enabled']) return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'disabled' => true];",
    "worker rule expiration",
)

# Permanent source contracts.
replace_once(
    "tests/automation-rules-v66k.php",
    "v66k_assert(str_contains($admin, 'automation_emergency_disable'), 'The Action Center must expose an emergency disable.');",
    "v66k_assert(str_contains($admin, 'automation_emergency_disable'), 'The Action Center must expose an emergency disable.');\nv66k_assert(str_contains($admin, \"array_replace($settings, ['enabled' => false])\"), 'Emergency disable must replace the enabled setting.');\nv66k_assert(!str_contains($admin, 'DELETE FROM automation_rules'), 'Rule and audit history must not be hard-deleted.');\nv66k_assert(str_contains($core, \"($evidence['matched'] ?? null) === true\"), 'Only a matching current simulation may authorize activation.');\nv66k_assert(strpos($core, 'if ($dryRun)') < strpos($core, 'if (!automation_rule_limit_reserve($rule))'), 'Dry-run must not consume live execution limits.');",
    "final policy source contracts",
)

# Database certification for nonmatching activation and dry-run counters.
replace_once(
    "tests/automation-rules-db-v66k.php",
    "    v66k_db_assert($activationBlocked, 'Activation must require a current simulation.');\n\n    $simulation = automation_simulate_rule($ruleId, [",
    "    v66k_db_assert($activationBlocked, 'Activation must require a current simulation.');\n\n    $nonMatchingRuleId = automation_save_rule(0, [\n        'name' => 'Nonmatching simulation gate',\n        'event_key' => 'system',\n        'source_type' => 'notification',\n        'conditions' => [['field' => 'title', 'operator' => 'contains', 'value' => 'required phrase']],\n        'actions' => [['type' => 'set_priority', 'parameters' => ['value' => 'high']]],\n        'max_executions_per_hour' => 10,\n        'max_executions_per_day' => 100,\n    ], $ownerId);\n    $ruleIds[] = $nonMatchingRuleId;\n    $nonMatchingSimulation = automation_simulate_rule($nonMatchingRuleId, [\n        'event_key' => 'system',\n        'source_type' => 'notification',\n        'priority' => 'normal',\n        'payload' => ['title' => 'Different sample'],\n    ], $ownerId);\n    v66k_db_assert($nonMatchingSimulation['matched'] === false, 'The nonmatching simulation fixture must not match.');\n    $nonMatchingActivationBlocked = false;\n    try {\n        automation_set_rule_status($nonMatchingRuleId, 'active', $ownerId);\n    } catch (RuntimeException) {\n        $nonMatchingActivationBlocked = true;\n    }\n    v66k_db_assert($nonMatchingActivationBlocked, 'A nonmatching simulation must not authorize rule activation.');\n\n    $simulation = automation_simulate_rule($ruleId, [",
    "nonmatching simulation database gate",
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

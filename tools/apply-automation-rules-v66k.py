from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: str, old: str, new: str, label: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one match, found {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


def insert_before_once(path: str, marker: str, insertion: str, label: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    if insertion.strip() in text:
        return
    count = text.count(marker)
    if count != 1:
        raise SystemExit(f"{label}: expected one marker, found {count}")
    file.write_text(text.replace(marker, insertion + marker, 1), encoding="utf-8")


# Repair source defects found during the first production review.
replace_once(
    "portal/automation-admin.php",
    "        $field = trim((string)$field;\n        );",
    "        $field = trim((string)$field);",
    "condition builder syntax",
)
replace_once(
    "portal/automation-rules.php",
    "    if (is_numeric($left) && is_numeric($right)) return (string)+$left === (string)+$right;",
    "    if (is_numeric($left) && is_numeric($right)) return (float)$left === (float)$right;",
    "numeric condition equality",
)

# Event/rule pairs are immutable execution units. Replayed events must observe
# the existing receipt and never reserve another limit slot or apply actions.
old_process_start = '''function automation_process_rule(array $event, array $rule, bool $dryRun): array
{
    [$matched, $conditionResults] = automation_rule_matches($rule, $event);'''
new_process_start = '''function automation_process_rule(array $event, array $rule, bool $dryRun): array
{
    $eventId = (int)($event['id'] ?? 0);
    if ($eventId > 0) {
        $existingStatement = db()->prepare(
            'SELECT status FROM automation_executions
             WHERE event_id=:event_id AND rule_id=:rule_id LIMIT 1'
        );
        $existingStatement->execute([
            'event_id' => $eventId,
            'rule_id' => (int)$rule['id'],
        ]);
        $existingStatus = $existingStatement->fetchColumn();
        if ($existingStatus !== false) {
            $existingMatched = (string)$existingStatus !== 'no_match';
            return [
                'matched' => $existingMatched,
                'stop' => $existingMatched && !empty($rule['stop_processing']),
                'status' => 'idempotent',
            ];
        }
    }

    [$matched, $conditionResults] = automation_rule_matches($rule, $event);'''
replace_once(
    "portal/automation-rules.php",
    old_process_start,
    new_process_start,
    "event rule idempotency",
)

# Load and route the Action Center in the administrator application.
replace_once(
    "portal/admin.php",
    "require_once __DIR__ . '/notification-delivery-admin.php';\n$user=require_role('admin');",
    "require_once __DIR__ . '/notification-delivery-admin.php';\nrequire_once __DIR__ . '/automation-admin.php';\n$user=require_role('admin');",
    "administrator automation include",
)
replace_once(
    "portal/admin.php",
    "'syndication','federation','delivery','events'",
    "'syndication','federation','delivery','automation','events'",
    "administrator automation view allowlist",
)
replace_once(
    "portal/admin.php",
    "    try{\n        if(notification_delivery_handle_admin_action($action,$user)){",
    "    try{\n        if(automation_handle_admin_action($action,$user)){\n            exit;\n        }\n        if(notification_delivery_handle_admin_action($action,$user)){",
    "administrator automation POST routing",
)
insert_before_once(
    "portal/admin.php",
    "if($view==='inbox'){",
    "if($view==='automation'){\n    automation_render_admin($user);\n}\n\n",
    "administrator automation renderer",
)

# Canonical notifications feed the automation queue nonblockingly. Automation-
# generated notifications are ignored by the engine to prevent loops.
replace_once(
    "portal/notifications.php",
    "require_once __DIR__ . '/notification-delivery.php';",
    "require_once __DIR__ . '/notification-delivery.php';\nrequire_once __DIR__ . '/automation-rules.php';",
    "notification automation adapter include",
)
replace_once(
    "portal/notifications.php",
    "        try {\n            notification_delivery_enqueue_notification($notificationId);\n        } catch (Throwable $deliveryException) {\n            error_log('North Mountain Media external notification enqueue failed: ' . $deliveryException->getMessage());\n        }\n        return $notificationId;",
    "        try {\n            notification_delivery_enqueue_notification($notificationId);\n        } catch (Throwable $deliveryException) {\n            error_log('North Mountain Media external notification enqueue failed: ' . $deliveryException->getMessage());\n        }\n        try {\n            automation_capture_notification($notificationId);\n        } catch (Throwable $automationException) {\n            error_log('North Mountain Media automation event capture failed: ' . $automationException->getMessage());\n        }\n        return $notificationId;",
    "canonical notification automation capture",
)

# Navigation and page-specific assets.
replace_once(
    "portal/bootstrap.php",
    "            'notifications' => 'Notifications',\n        ],",
    "            'notifications' => 'Notifications',\n            'automation' => 'Automation',\n        ],",
    "administrator automation navigation",
)
replace_once(
    "portal/bootstrap.php",
    "    <?php if($active==='federation'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/activitypub-admin.css?v=20260730-v66F')) ?>\"><?php endif;?>",
    "    <?php if($active==='federation'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/activitypub-admin.css?v=20260730-v66F')) ?>\"><?php endif;?>\n    <?php if($active==='automation'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/automation-center.css?v=20260730-v66K')) ?>\"><?php endif;?>",
    "automation stylesheet boundary",
)
replace_once(
    "portal/bootstrap.php",
    "                                <a href=\"<?= e(app_url('portal/admin.php?view=notifications')) ?>\"><span>Open</span><strong>Notifications</strong><small>Unread portal activity</small></a>",
    "                                <a href=\"<?= e(app_url('portal/admin.php?view=notifications')) ?>\"><span>Open</span><strong>Notifications</strong><small>Unread portal activity</small></a>\n                                <a href=\"<?= e(app_url('portal/admin.php?view=automation')) ?>\"><span>Manage</span><strong>Automation</strong><small>Rules, routing, approvals, and receipts</small></a>",
    "automation quick action",
)
replace_once(
    "portal/bootstrap.php",
    "<?php if($active==='feeds'):?><script src=\"<?= e(app_url('assets/js/feed-reader.js?v=20260728-content-controls-v62.1')) ?>\"></script><?php endif;?>\n</body>",
    "<?php if($active==='feeds'):?><script src=\"<?= e(app_url('assets/js/feed-reader.js?v=20260728-content-controls-v62.1')) ?>\"></script><?php endif;?>\n<?php if($active==='automation'):?><script src=\"<?= e(app_url('assets/js/automation-center.js?v=20260730-v66K')) ?>\"></script><?php endif;?>\n</body>",
    "automation JavaScript boundary",
)

# Append the additive migration to fresh installs exactly once.
fresh_path = ROOT / "database/north_mountain_portal.sql"
migration_path = ROOT / "database/automation_rules_v66k.sql"
fresh = fresh_path.read_text(encoding="utf-8").rstrip()
migration = migration_path.read_text(encoding="utf-8").strip()
marker = "-- Section 66K: Automation Rules, Routing & Action Center"
if marker not in fresh:
    fresh += "\n\n-- ============================================================\n"
    fresh += "-- Section 66K fresh-install schema\n"
    fresh += "-- ============================================================\n"
    fresh += migration + "\n"
fresh_path.write_text(fresh, encoding="utf-8")

# Make the permanent source test certify the fresh schema and replay guard.
replace_once(
    "tests/automation-rules-v66k.php",
    "$migration = file_get_contents($root . '/database/automation_rules_v66k.sql');",
    "$migration = file_get_contents($root . '/database/automation_rules_v66k.sql');\n$fresh = file_get_contents($root . '/database/north_mountain_portal.sql');",
    "fresh schema source load",
)
replace_once(
    "tests/automation-rules-v66k.php",
    "foreach ([$core, $admin, $worker, $javascript, $css, $migration] as $source) {",
    "foreach ([$core, $admin, $worker, $javascript, $css, $migration, $fresh] as $source) {",
    "fresh schema source requirement",
)
replace_once(
    "tests/automation-rules-v66k.php",
    "    v66k_assert(substr_count($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'The v66K migration must define ' . $table . ' exactly once.');\n}",
    "    v66k_assert(substr_count($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'The v66K migration must define ' . $table . ' exactly once.');\n    v66k_assert(substr_count($fresh, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'The fresh schema must define ' . $table . ' exactly once.');\n}",
    "fresh schema table contract",
)
replace_once(
    "tests/automation-rules-v66k.php",
    "v66k_assert(str_contains($core, 'automation_rule_has_current_simulation'), 'Active rules must require a current simulation.');",
    "v66k_assert(str_contains($core, 'automation_rule_has_current_simulation'), 'Active rules must require a current simulation.');\nv66k_assert(str_contains($core, \"'status' => 'idempotent'\"), 'Replayed event/rule pairs must skip duplicate actions.');",
    "replay source contract",
)

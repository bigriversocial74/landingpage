<?php
declare(strict_types=1);

function nmm_config(?string $section = null): array
{
    $config = ['app' => ['base_url' => 'https://pod.example.test'], 'homeserver' => []];
    if ($section === null) return $config;
    return is_array($config[$section] ?? null) ? $config[$section] : [];
}

function setting(string $key, ?string $fallback = null): ?string
{
    return $fallback;
}

function status_label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function app_url(string $path = ''): string
{
    return 'https://pod.example.test' . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

require dirname(__DIR__) . '/portal/automation-rules.php';

function v66k_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$core = file_get_contents($root . '/portal/automation-rules.php');
$admin = file_get_contents($root . '/portal/automation-admin.php');
$worker = file_get_contents($root . '/cron/process-automation.php');
$javascript = file_get_contents($root . '/assets/js/automation-center.js');
$css = file_get_contents($root . '/assets/css/automation-center.css');
$migration = file_get_contents($root . '/database/automation_rules_v66k.sql');

foreach ([$core, $admin, $worker, $javascript, $css, $migration] as $source) {
    v66k_assert(is_string($source) && $source !== '', 'A required v66K source file is empty.');
}

$tables = [
    'automation_settings',
    'automation_rules',
    'automation_rule_versions',
    'automation_events',
    'automation_executions',
    'automation_action_receipts',
    'automation_approvals',
    'automation_rule_counters',
];
foreach ($tables as $table) {
    v66k_assert(substr_count($migration, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1, 'The v66K migration must define ' . $table . ' exactly once.');
}

$uuidA = automation_uuid();
$uuidB = automation_uuid();
v66k_assert($uuidA !== $uuidB, 'Automation UUIDs must be unique.');
v66k_assert((bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $uuidA), 'Automation UUIDs must be RFC 4122 version 4.');

$sanitized = automation_sanitize_payload([
    'title' => 'Safe title',
    'api_token' => 'must-not-survive',
    'nested' => ['password' => 'must-not-survive', 'body' => '<b>Safe</b> body'],
]);
v66k_assert($sanitized['api_token'] === '[redacted]', 'Automation payloads must redact token fields.');
v66k_assert($sanitized['nested']['password'] === '[redacted]', 'Automation payloads must redact nested password fields.');
v66k_assert($sanitized['nested']['body'] === 'Safe body', 'Automation payloads must strip markup.');

$conditions = automation_validate_conditions([
    ['field' => 'priority', 'operator' => 'priority_at_least', 'value' => 'high'],
    ['field' => 'title', 'operator' => 'contains', 'value' => 'review'],
]);
v66k_assert(count($conditions) === 2, 'Valid conditions were not preserved.');
$actions = automation_validate_actions([
    ['type' => 'set_priority', 'parameters' => ['value' => 'urgent']],
    ['type' => 'homeserver_proposal', 'parameters' => ['capability' => 'message_summary', 'instruction' => 'Summarize for owner review']],
]);
v66k_assert(count($actions) === 2, 'Valid actions were not preserved.');
v66k_assert($actions[1]['parameters']['capability'] === 'message_summary', 'The HomeServer capability allowlist was not preserved.');

$dangerousRejected = false;
try {
    automation_validate_actions([['type' => 'send_message', 'parameters' => ['body' => 'No']]]);
} catch (RuntimeException) {
    $dangerousRejected = true;
}
v66k_assert($dangerousRejected, 'Autonomous message sending must not be an allowed action.');

$context = ['priority' => 'urgent', 'title' => 'Needs review', 'source_type' => 'federated_message'];
v66k_assert(automation_condition_matches(['field' => 'priority', 'operator' => 'priority_at_least', 'value' => 'high'], $context), 'Priority threshold matching failed.');
v66k_assert(automation_condition_matches(['field' => 'title', 'operator' => 'contains', 'value' => 'review'], $context), 'Text containment matching failed.');
v66k_assert(!automation_condition_matches(['field' => 'source_type', 'operator' => 'equals', 'value' => 'call_center'], $context), 'Equality matching accepted the wrong source.');

$notification = [
    'id' => 7,
    'entity_type' => 'activitypub_message_thread',
    'entity_id' => 9,
    'title' => 'New federated message request',
    'category' => 'message',
];
[$sourceType, $sourceId] = automation_notification_source($notification);
v66k_assert($sourceType === 'federated_message' && $sourceId === 9, 'Notification-to-inbox source mapping failed.');
v66k_assert(automation_notification_event_key($notification) === 'federated_message_request', 'Notification event classification failed.');

v66k_assert(str_contains($core, "'proposal_only' => true"), 'HomeServer automation requests must be proposal-only.');
v66k_assert(str_contains($core, "'send_allowed' => false"), 'HomeServer automation requests must deny send authority.');
v66k_assert(str_contains($core, "'tool_execution_allowed' => false"), 'HomeServer automation requests must deny tool execution.');
v66k_assert(str_contains($core, 'automation_rule_has_current_simulation'), 'Active rules must require a current simulation.');
v66k_assert(str_contains($core, 'uq_automation_executions_event_rule') || str_contains($migration, 'uq_automation_executions_event_rule'), 'Event/rule execution idempotency must be permanent.');
v66k_assert(str_contains($core, "str_starts_with(\$entityType, 'automation_')"), 'Automation-created notifications must not recursively create automation events.');
v66k_assert(str_contains($admin, 'automation_emergency_disable'), 'The Action Center must expose an emergency disable.');
v66k_assert(str_contains($core, '($evidence[\'matched\'] ?? null) === true'), 'Only a matching current simulation may authorize activation.');
v66k_assert(str_contains($admin, "array_replace(\$settings, ['enabled' => false])"), 'Emergency disable must replace the enabled setting.');
v66k_assert(str_contains($core, ':starts_at,:expires_at,:created_by_user_id,:updated_by_user_id)'), 'Rule creation must use distinct native PDO placeholders.');
v66k_assert(!str_contains($core, ':starts_at,:expires_at,:user_id,:user_id)'), 'Rule creation must not reuse a native PDO named placeholder.');
v66k_assert(!str_contains($admin, 'DELETE FROM automation_rules'), 'Rule and audit history must not be hard-deleted.');
v66k_assert(strpos($core, 'if ($dryRun)') < strpos($core, 'if (!automation_rule_limit_reserve($rule))'), 'Dry-run must not consume live execution limits.');
v66k_assert(str_contains($core, 'automation_refresh_execution_status'), 'Approval outcomes must refresh parent execution state.');
v66k_assert(str_contains($core, 'automation_retry_approval'), 'Failed proposal requests must have a bounded retry path.');
v66k_assert(str_contains($core, 'automation_notify_owner'), 'Approvals and failures must be visible to the owner.');
v66k_assert(str_contains($core, 'automation_expire_rules'), 'Expired active rules must be closed automatically.');
v66k_assert(str_contains($migration, 'FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE SET NULL'), 'Event cleanup must preserve longer-lived execution evidence.');
v66k_assert(str_contains($worker, "PHP_SAPI !== 'cli'"), 'The automation worker must be CLI-only.');
v66k_assert(!preg_match("#https?://[^\\s\"']+\\.js#i", $javascript), 'Automation JavaScript must not load third-party scripts.');
v66k_assert(str_contains($css, '.automation-mode.off'), 'The Action Center must visibly distinguish disabled mode.');

$temporaryPaths = [
    'tools/apply-automation-rules-v66k.py',
    'tools/audit-retention-finalize-automation-rules-v66k.py',
    'tools/finalize-automation-rules-v66k.py',
    'tools/policy-finalize-automation-rules-v66k.py',
    'tools/restart-safety-finalize-automation-rules-v66k.py',
    'tools/restart-safety-finalize-automation-rules-v66k-v2.py',
    'tools/restart-safety-finalize-automation-rules-v66k-v3.py',
    'tools/harden-automation-rules-v66k-production.py',
    'tools/harden-automation-rules-v66k-production-v2.py',
    'tools/harden-automation-rules-v66k-production-v3.py',
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
}

v66k_assert(str_contains($core, 'automation_recover_interrupted_approvals'), 'Interrupted approved requests must become retryable failure evidence.');
v66k_assert(str_contains($core, 'WHERE id=:id AND status="approved"'), 'HomeServer results must use compare-and-set finalization.');

fwrite(STDOUT, "Automation Rules v66K source/security regression passed.\n");
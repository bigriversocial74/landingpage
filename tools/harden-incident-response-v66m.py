#!/usr/bin/env python3
from pathlib import Path
import re

CORE = Path('portal/incident-response.php')
PAGE = Path('portal/recovery-center.php')

core = CORE.read_text(encoding='utf-8')
page = PAGE.read_text(encoding='utf-8')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count == 0 and new in text:
        return text
    if count != 1:
        raise SystemExit(f'{label}: expected exactly one match, found {count}')
    return text.replace(old, new, 1)

core = replace_once(
    core,
    "require_once __DIR__ . '/operations-analytics.php';",
    "require_once __DIR__ . '/operations-analytics-extensions.php';",
    'extended analytics include',
)

old_plan = """        'verify_check' => $definition['verify_check'] ?? null,
        'authority' => $definition['authority'] ?? [],
        'generated_at' => gmdate(DATE_ATOM),
    ];
    $inputJson = recovery_json_encode(['window_type' => $overrides['window_type'] ?? null]);
    $planJson = recovery_json_encode($plan);
    $hash = hash('sha256', (string)$runbook['definition_hash'] . '|' . $incidentId . '|' . $inputJson . '|' . $planJson);"""
new_plan = """        'verify_check' => $definition['verify_check'] ?? null,
        'authority' => $definition['authority'] ?? [],
    ];
    $inputJson = recovery_json_encode(['window_type' => $overrides['window_type'] ?? null]);
    $hash = hash('sha256', (string)$runbook['definition_hash'] . '|' . $incidentId . '|' . $inputJson . '|' . recovery_json_encode($plan));
    $plan['generated_at'] = gmdate(DATE_ATOM);
    $planJson = recovery_json_encode($plan);"""
core = replace_once(core, old_plan, new_plan, 'deterministic simulation hash')

old_approval = """         ON DUPLICATE KEY UPDATE status=IF(status IN (\"consumed\",\"approved\"),status,\"pending\"),request_hash=VALUES(request_hash),
          requested_by_user_id=VALUES(requested_by_user_id),expires_at=VALUES(expires_at),resolved_by_user_id=NULL,resolved_at=NULL'"""
new_approval = """         ON DUPLICATE KEY UPDATE
          request_hash=IF(status IN (\"consumed\",\"approved\"),request_hash,VALUES(request_hash)),
          requested_by_user_id=IF(status IN (\"consumed\",\"approved\"),requested_by_user_id,VALUES(requested_by_user_id)),
          expires_at=IF(status IN (\"consumed\",\"approved\"),expires_at,VALUES(expires_at)),
          status=IF(status IN (\"consumed\",\"approved\"),status,\"pending\"),
          resolved_by_user_id=IF(status IN (\"consumed\",\"approved\"),resolved_by_user_id,NULL),
          resolved_at=IF(status IN (\"consumed\",\"approved\"),resolved_at,NULL)'"""
core = replace_once(core, old_approval, new_approval, 'approval immutability')

old_queue = """    $simulation = recovery_simulation($simulationId);
    if (!$simulation || (string)$simulation['status'] !== 'valid' || strtotime((string)$simulation['expires_at']) <= time()) {
        throw new RuntimeException('The recovery simulation is stale or expired.');
    }
    if (!empty($simulation['recovered_at'])) throw new RuntimeException('The incident has already recovered.');
    if ((int)$simulation['version_number'] !== (int)$simulation['current_version']) throw new RuntimeException('The runbook version changed after simulation.');
    $definition = recovery_json_decode((string)$simulation['definition_json'], []);"""
new_queue = """    $simulation = recovery_simulation($simulationId);
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
    $definition = recovery_json_decode((string)$simulation['definition_json'], []);"""
core = replace_once(core, old_queue, new_queue, 'early execution idempotency')

old_late_key = """    $idempotencyKey = hash('sha256', implode('|', [
        (string)$simulation['incident_id'],
        (string)$simulation['runbook_id'],
        (string)$simulation['runbook_version_id'],
        (string)$simulation['simulation_hash'],
    ]));
    $existingStatement = db()->prepare('SELECT * FROM recovery_executions WHERE idempotency_key=:idempotency_key LIMIT 1');
    $existingStatement->execute(['idempotency_key' => $idempotencyKey]);
    $existing = $existingStatement->fetch();
    if ($existing) return $existing;

    $status = $settings['dry_run'] ? 'simulated' : 'queued';"""
core = replace_once(core, old_late_key, "    $status = $settings['dry_run'] ? 'simulated' : 'queued';", 'remove late idempotency')

core = replace_once(
    core,
    "return ['analytics' => operations_analytics_run((string)$input['window_type'], true)];",
    "return ['analytics' => operations_analytics_run_extended((string)$input['window_type'], true)];",
    'extended rebuild handler',
)
core = replace_once(
    core,
    "$analyticsResult = operations_analytics_run('hour', true);",
    "$analyticsResult = operations_analytics_run_extended('hour', true);",
    'extended verification',
)

old_worker_failure = """            db()->prepare(
                'UPDATE recovery_executions SET status=\"failed\",lease_token=NULL,leased_until=NULL,error_code=:error_code,
                 error_message=:error_message,completed_at=UTC_TIMESTAMP() WHERE id=:id'
            )->execute([
                'error_code' => 'recovery_execution_failed',
                'error_message' => mb_substr(trim(preg_replace('/\\s+/u', ' ', $exception->getMessage()) ?? ''), 0, 1000),
                'id' => (int)$execution['id'],
            ]);
            recovery_escalate_failure($execution, $exception->getMessage());
            $results[] = ['execution_id' => (int)$execution['id'], 'status' => 'failed', 'error_code' => 'recovery_execution_failed'];"""
new_worker_failure = """            db()->prepare(
                'UPDATE recovery_executions
                 SET status=IF(attempt_count<max_attempts,\"queued\",\"failed\"),lease_token=NULL,leased_until=NULL,
                     error_code=:error_code,error_message=:error_message,
                     completed_at=IF(attempt_count<max_attempts,NULL,UTC_TIMESTAMP())
                 WHERE id=:id'
            )->execute([
                'error_code' => 'recovery_execution_failed',
                'error_message' => mb_substr(trim(preg_replace('/\\s+/u', ' ', $exception->getMessage()) ?? ''), 0, 1000),
                'id' => (int)$execution['id'],
            ]);
            $statusStatement = db()->prepare('SELECT status FROM recovery_executions WHERE id=:id LIMIT 1');
            $statusStatement->execute(['id' => (int)$execution['id']]);
            $retryStatus = (string)$statusStatement->fetchColumn();
            if ($retryStatus === 'failed') recovery_escalate_failure($execution, $exception->getMessage());
            $results[] = ['execution_id' => (int)$execution['id'], 'status' => $retryStatus, 'error_code' => 'recovery_execution_failed'];"""
core = replace_once(core, old_worker_failure, new_worker_failure, 'bounded execution retry')

old_nav = """if (!str_contains($html, 'portal/recovery-center.php')) {
    $link = '<a class=\"active\" href=\"' . e(app_url('portal/recovery-center.php')) . '\">Recovery Center</a>';"""
new_nav = """if (!str_contains($html, 'data-recovery-center-nav')) {
    $link = '<a class=\"active\" data-recovery-center-nav href=\"' . e(app_url('portal/recovery-center.php')) . '\">Recovery Center</a>';"""
page = replace_once(page, old_nav, new_nav, 'recovery navigation marker')

CORE.write_text(core, encoding='utf-8')
PAGE.write_text(page, encoding='utf-8')
print('Section 66M hardening applied.')

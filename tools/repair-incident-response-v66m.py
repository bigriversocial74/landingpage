#!/usr/bin/env python3
from pathlib import Path

path = Path('tests/incident-response-db-v66m.php')
source = path.read_text(encoding='utf-8')

anchor = "recovery_test_assert(recovery_schema_available(), 'Recovery schema is unavailable.');\n"
insert = """recovery_test_assert(recovery_schema_available(), 'Recovery schema is unavailable.');

db()->prepare(
    'INSERT INTO users (role,email,password_hash,display_name,status)
     VALUES (\"admin\",:email,:password_hash,:display_name,\"active\")
     ON DUPLICATE KEY UPDATE role=\"admin\",status=\"active\",display_name=VALUES(display_name)'
)->execute([
    'email' => 'v66m-admin-one@example.invalid',
    'password_hash' => password_hash('v66m-test-password-one', PASSWORD_DEFAULT),
    'display_name' => 'v66M Administrator One',
]);
$adminStatement = db()->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
$adminStatement->execute(['email' => 'v66m-admin-one@example.invalid']);
$adminUserId = (int)$adminStatement->fetchColumn();

db()->prepare(
    'INSERT INTO users (role,email,password_hash,display_name,status)
     VALUES (\"admin\",:email,:password_hash,:display_name,\"active\")
     ON DUPLICATE KEY UPDATE role=\"admin\",status=\"active\",display_name=VALUES(display_name)'
)->execute([
    'email' => 'v66m-admin-two@example.invalid',
    'password_hash' => password_hash('v66m-test-password-two', PASSWORD_DEFAULT),
    'display_name' => 'v66M Administrator Two',
]);
$secondAdminStatement = db()->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
$secondAdminStatement->execute(['email' => 'v66m-admin-two@example.invalid']);
$secondAdminUserId = (int)$secondAdminStatement->fetchColumn();
recovery_test_assert($adminUserId > 0 && $secondAdminUserId > 0, 'Administrator fixtures were not created.');
"""
if anchor not in source:
    if '$adminUserId = (int)$adminStatement->fetchColumn();' not in source:
        raise SystemExit('Administrator fixture anchor not found.')
else:
    source = source.replace(anchor, insert, 1)

replacements = {
    'recovery_simulate($incidentId, (int)$recommendation[\'runbook_id\'], 1)': 'recovery_simulate($incidentId, (int)$recommendation[\'runbook_id\'], $adminUserId)',
    'recovery_request_approval((int)$simulationTwo[\'id\'], 1)': 'recovery_request_approval((int)$simulationTwo[\'id\'], $adminUserId)',
    'recovery_resolve_approval((int)$approval[\'id\'], \'approved\', 1)': 'recovery_resolve_approval((int)$approval[\'id\'], \'approved\', $adminUserId)',
    'recovery_request_approval((int)$simulationTwo[\'id\'], 2)': 'recovery_request_approval((int)$simulationTwo[\'id\'], $secondAdminUserId)',
    'recovery_queue_execution((int)$simulationTwo[\'id\'], 1)': 'recovery_queue_execution((int)$simulationTwo[\'id\'], $adminUserId)',
    'recovery_simulate($incidentId, (int)$recommendation[\'runbook_id\'], 1)': 'recovery_simulate($incidentId, (int)$recommendation[\'runbook_id\'], $adminUserId)',
    'recovery_request_approval((int)$liveSimulation[\'id\'], 1)': 'recovery_request_approval((int)$liveSimulation[\'id\'], $adminUserId)',
    'recovery_resolve_approval((int)$liveApproval[\'id\'], \'approved\', 1)': 'recovery_resolve_approval((int)$liveApproval[\'id\'], \'approved\', $adminUserId)',
    'recovery_queue_execution((int)$liveSimulation[\'id\'], 1)': 'recovery_queue_execution((int)$liveSimulation[\'id\'], $adminUserId)',
    'recovery_simulate($retryIncidentId, (int)$operationsRunbook, 1)': 'recovery_simulate($retryIncidentId, (int)$operationsRunbook, $adminUserId)',
    'recovery_queue_execution((int)$retrySimulation[\'id\'], 1)': 'recovery_queue_execution((int)$retrySimulation[\'id\'], $adminUserId)',
}
for old, new in replacements.items():
    source = source.replace(old, new)

path.write_text(source, encoding='utf-8')
print('v66M administrator fixtures added.')

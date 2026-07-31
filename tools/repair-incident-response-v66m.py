#!/usr/bin/env python3
from pathlib import Path

source_test = Path('tests/incident-response-v66m.php')
source = source_test.read_text(encoding='utf-8')
replacements = {
    "!str_contains($core, '->install(')": "!str_contains($core, '$agent->install(')",
    "!str_contains($core, '->prepare(')": "!str_contains($core, '$agent->prepare(')",
}
for old, new in replacements.items():
    if old in source:
        source = source.replace(old, new, 1)
    elif new not in source:
        raise SystemExit(f'Missing source assertion: {old}')
source_test.write_text(source, encoding='utf-8')

db_test = Path('tests/incident-response-db-v66m.php')
database_source = db_test.read_text(encoding='utf-8')
stub = """$GLOBALS['recovery_test_events'] = [];
function automation_capture_event(array $event): int
{
    $GLOBALS['recovery_test_events'][] = $event;
    return count($GLOBALS['recovery_test_events']);
}

"""
if stub in database_source:
    database_source = database_source.replace(stub, '', 1)
elif "$GLOBALS['recovery_test_events']" in database_source:
    raise SystemExit('Unexpected automation test stub shape.')
db_test.write_text(database_source, encoding='utf-8')
print('v66M source assertions narrowed and conflicting automation stub removed.')

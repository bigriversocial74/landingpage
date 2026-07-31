#!/usr/bin/env python3
from pathlib import Path

path = Path('tests/incident-response-v66m.php')
source = path.read_text(encoding='utf-8')
replacements = {
    "!str_contains($core, '->install(')": "!str_contains($core, '$agent->install(')",
    "!str_contains($core, '->prepare(')": "!str_contains($core, '$agent->prepare(')",
}
for old, new in replacements.items():
    if old in source:
        source = source.replace(old, new, 1)
    elif new not in source:
        raise SystemExit(f'Missing source assertion: {old}')
path.write_text(source, encoding='utf-8')
print('v66M update-agent authority assertions narrowed.')

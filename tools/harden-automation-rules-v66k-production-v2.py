from pathlib import Path

root = Path(__file__).resolve().parents[1]
source_path = root / 'tools/harden-automation-rules-v66k-production.py'
source = source_path.read_text(encoding='utf-8')
old = "'''        ])->execute([\n            'approval_uuid'"
new = "'''        )->execute([\n            'approval_uuid'"
if old not in source:
    raise SystemExit('approval execute controller correction anchor not found')
source = source.replace(old, new, 1)
exec(compile(source, str(source_path), 'exec'))

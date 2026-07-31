from pathlib import Path

root = Path(__file__).resolve().parents[1]
source_path = root / 'tools/harden-automation-rules-v66k-production.py'
source = source_path.read_text(encoding='utf-8')

approval_old = "'''        ])->execute([\n            'approval_uuid'"
approval_new = "'''        )->execute([\n            'approval_uuid'"
if approval_old not in source:
    raise SystemExit('approval execute controller correction anchor not found')
source = source.replace(approval_old, approval_new, 1)

start_marker = '# Preserve longer-lived execution evidence after event cleanup.\n'
end_marker = '# Permanent source and cleanup contracts.\n'
start = source.find(start_marker)
end = source.find(end_marker, start + len(start_marker))
if start < 0 or end < 0:
    raise SystemExit('fresh schema controller section not found')
fresh_logic = '''# Preserve longer-lived execution evidence after event cleanup and append
# the complete repeat-safe v66K schema to fresh installs when it is absent.
replace_once(
    "database/automation_rules_v66k.sql",
    "FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE CASCADE",
    "FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE SET NULL",
    "execution audit retention in additive migration",
)
fresh_path = ROOT / "database/north_mountain_portal.sql"
fresh = fresh_path.read_text(encoding="utf-8")
if "CREATE TABLE IF NOT EXISTS automation_settings" not in fresh:
    migration = (ROOT / "database/automation_rules_v66k.sql").read_text(encoding="utf-8")
    fresh_path.write_text(fresh.rstrip() + "\\n\\n" + migration.rstrip() + "\\n", encoding="utf-8")
else:
    replace_once(
        "database/north_mountain_portal.sql",
        "FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE CASCADE",
        "FOREIGN KEY (event_id) REFERENCES automation_events(id) ON DELETE SET NULL",
        "execution audit retention in fresh schema",
    )

'''
source = source[:start] + fresh_logic + source[end:]

source = source.replace(
    "    'tools/harden-automation-rules-v66k-production.py',\n",
    "    'tools/harden-automation-rules-v66k-production.py',\n    'tools/harden-automation-rules-v66k-production-v2.py',\n    'tools/harden-automation-rules-v66k-production-v3.py',\n",
    1,
)
source = source.replace(
    "            tools/harden-automation-rules-v66k-production.py \\\n",
    "            tools/harden-automation-rules-v66k-production.py \\\n            tools/harden-automation-rules-v66k-production-v2.py \\\n            tools/harden-automation-rules-v66k-production-v3.py \\\n",
    1,
)
exec(compile(source, str(source_path), 'exec'))

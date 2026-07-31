from pathlib import Path
import runpy

ROOT = Path(__file__).resolve().parents[1]
runpy.run_path(str(ROOT / 'tools/restart-safety-finalize-automation-rules-v66k-v2.py'), run_name='__main__')

path = ROOT / 'portal/automation-admin.php'
text = path.read_text(encoding='utf-8')
old = "        $field = trim((string)$field;\n        );"
if old not in text:
    raise SystemExit('condition builder syntax: malformed source was not found')
path.write_text(text.replace(old, "        $field = trim((string)$field);", 1), encoding='utf-8')

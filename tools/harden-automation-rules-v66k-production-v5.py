from pathlib import Path
import runpy

root = Path(__file__).resolve().parents[1]
runpy.run_path(str(root / 'tools/harden-automation-rules-v66k-production-v4.py'), run_name='__main__')

path = root / 'tests/automation-rules-v66k.php'
text = path.read_text(encoding='utf-8')
old = "v66k_assert(str_contains($core, \"($evidence['matched'] ?? null) === true\"), 'Only a matching current simulation may authorize activation.');"
new = "v66k_assert(str_contains($core, '($evidence[\\'matched\\'] ?? null) === true'), 'Only a matching current simulation may authorize activation.');"
if old not in text:
    raise SystemExit('matching simulation assertion syntax anchor not found')
text = text.replace(old, new, 1)
text = text.replace(
    "    'tools/harden-automation-rules-v66k-production-v4.py',\n",
    "    'tools/harden-automation-rules-v66k-production-v4.py',\n    'tools/harden-automation-rules-v66k-production-v5.py',\n",
    1,
)
path.write_text(text, encoding='utf-8')

workflow = root / '.github/workflows/automation-rules-quality.yml'
workflow_text = workflow.read_text(encoding='utf-8')
workflow_text = workflow_text.replace(
    "            tools/harden-automation-rules-v66k-production-v4.py \\\n",
    "            tools/harden-automation-rules-v66k-production-v4.py \\\n            tools/harden-automation-rules-v66k-production-v5.py \\\n",
    1,
)
workflow.write_text(workflow_text, encoding='utf-8')

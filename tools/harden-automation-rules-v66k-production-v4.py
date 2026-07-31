from pathlib import Path

root = Path(__file__).resolve().parents[1]
source_path = root / 'tools/harden-automation-rules-v66k-production-v3.py'
source = source_path.read_text(encoding='utf-8')
source = source.replace(
    "source = source.replace(approval_old, approval_new, 1)",
    "source = source.replace(approval_old, approval_new)",
    1,
)
source = source.replace(
    "    'tools/harden-automation-rules-v66k-production-v3.py',\n",
    "    'tools/harden-automation-rules-v66k-production-v3.py',\n    'tools/harden-automation-rules-v66k-production-v4.py',\n",
    1,
)
source = source.replace(
    "            tools/harden-automation-rules-v66k-production-v3.py \\\n",
    "            tools/harden-automation-rules-v66k-production-v3.py \\\n            tools/harden-automation-rules-v66k-production-v4.py \\\n",
    1,
)
exec(compile(source, str(source_path), 'exec'))

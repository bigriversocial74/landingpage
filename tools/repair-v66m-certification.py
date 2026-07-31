#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: Path, old: str, new: str, label: str) -> None:
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count == 0 and new in text:
        return
    if count != 1:
        raise SystemExit(f'{label}: expected one match, found {count}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


db_test = Path('tests/incident-response-db-v66m.php')
replace_once(
    db_test,
    ':evidence_json,:evidence_json)',
    ':evidence_json,:latest_evidence_json)',
    'distinct incident evidence placeholders',
)
replace_once(
    db_test,
    "        'evidence_json' => json_encode(['aggregate_only' => true, 'test' => 'v66m'], JSON_THROW_ON_ERROR),\n    ]);",
    "        'evidence_json' => json_encode(['aggregate_only' => true, 'test' => 'v66m'], JSON_THROW_ON_ERROR),\n        'latest_evidence_json' => json_encode(['aggregate_only' => true, 'test' => 'v66m'], JSON_THROW_ON_ERROR),\n    ]);",
    'incident evidence parameters',
)

workflow = Path('.github/workflows/incident-response-quality.yml')
replace_once(
    workflow,
    "      - 'portal/incident-response.php'\n      - 'portal/recovery-center.php'",
    "      - 'portal/incident-response.php'\n      - 'portal/operations-analytics-extensions.php'\n      - 'portal/recovery-center.php'",
    'Operations integration workflow path',
)
replace_once(
    workflow,
    "            .github/workflows/harden-incident-response-v66m.yml \\\n            .github/workflows/repair-incident-response-v66m.yml \\",
    "            .github/workflows/harden-incident-response-v66m.yml \\\n            tools/link-recovery-center-v66m.py \\\n            .github/workflows/link-recovery-center-v66m.yml \\\n            tools/repair-v66m-certification.py \\\n            .github/workflows/repair-v66m-certification.yml \\\n            .github/workflows/repair-incident-response-v66m.yml \\",
    'temporary cleanup paths',
)
replace_once(
    workflow,
    "          grep -F 'data-recovery-center-nav' portal/recovery-center.php >/dev/null\n",
    "          grep -F 'data-recovery-center-nav' portal/recovery-center.php >/dev/null\n          grep -F 'data-recovery-center-entry' portal/operations-analytics-extensions.php >/dev/null\n",
    'Operations entry assertion',
)
print('v66M certification fixture repaired.')

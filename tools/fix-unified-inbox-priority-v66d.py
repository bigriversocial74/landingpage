from pathlib import Path

path = Path(__file__).resolve().parents[1] / 'portal/unified-inbox.php'
text = path.read_text(encoding='utf-8')
old = "$priority = (string)($values['priority'] ?? 'normal');"
new = "$priority = (string)($values['native_priority'] ?? $values['priority'] ?? 'normal');"
if text.count(old) != 1:
    raise SystemExit(f'Expected one priority normalization boundary, found {text.count(old)}')
path.write_text(text.replace(old, new, 1), encoding='utf-8')
print('Unified Inbox native priority preservation repaired.')

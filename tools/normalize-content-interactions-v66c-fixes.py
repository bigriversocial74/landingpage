from pathlib import Path

path = Path('tools/fix-content-interactions-v66c.py')
source = path.read_text(encoding='utf-8')
marker = "replace_once('tests/content-interactions-v66c.php',\n'''if (!content_interactions_can_edit"
start = source.find(marker)
if start < 0:
    raise SystemExit('Unable to locate brittle Content Interactions test augmentation block.')
end = source.find("\nreplace_once('tests/content-interactions-v66c.php',", start + len(marker))
if end < 0:
    raise SystemExit('Unable to close brittle Content Interactions test augmentation block.')
source = source[:start] + source[end + 1:]
path.write_text(source, encoding='utf-8')
print('Content Interactions hardening test patch normalized.')

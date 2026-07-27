from pathlib import Path

path = Path('tools/v617_editor_js.py')
text = path.read_text()
replacements = {
    ", render_canvas + '\\n\\n  const moveBlock', text, count=1, flags=re.S)": ", lambda _match: render_canvas + '\\n\\n  const moveBlock', text, count=1, flags=re.S)",
    ", render_lists + '\\n\\n  const fieldDefinitions', text, count=1, flags=re.S)": ", lambda _match: render_lists + '\\n\\n  const fieldDefinitions', text, count=1, flags=re.S)",
    ", field_defs + '\\n\\n  const selectedItem', text, count=1, flags=re.S)": ", lambda _match: field_defs + '\\n\\n  const selectedItem', text, count=1, flags=re.S)",
    ", render_inspector + '\\n\\n  const selectItem', text, count=1, flags=re.S)": ", lambda _match: render_inspector + '\\n\\n  const selectItem', text, count=1, flags=re.S)",
    ", activate, text, count=1, flags=re.S)": ", lambda _match: activate, text, count=1, flags=re.S)",
}
for old, new in replacements.items():
    if old not in text:
        raise SystemExit(f'Missing generator replacement target: {old}')
    text = text.replace(old, new, 1)
path.write_text(text)

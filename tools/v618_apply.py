from pathlib import Path
import re

_original_subn = re.subn

def literal_subn(pattern, replacement, string, count=0, flags=0):
    if isinstance(replacement, str):
        return _original_subn(pattern, lambda _match: replacement, string, count=count, flags=flags)
    return _original_subn(pattern, replacement, string, count=count, flags=flags)

re.subn = literal_subn
parts = [Path(f'tools/v618_chunk_{index}.txt').read_text(encoding='utf-8') for index in range(1, 6)]
source = ''.join(parts)
exec(compile(source, 'v618-staged-generator', 'exec'))

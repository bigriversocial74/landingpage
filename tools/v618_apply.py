from pathlib import Path

parts = [Path(f'tools/v618_chunk_{index}.txt').read_text(encoding='utf-8') for index in range(1, 6)]
source = ''.join(parts)
exec(compile(source, 'v618-staged-generator', 'exec'))

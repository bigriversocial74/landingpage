#!/usr/bin/env python3
from pathlib import Path

LIVE_LINK = """<link
    rel=\"stylesheet\"
    href=\"<?=e(app_url(
        'assets/css/music-mobile-upgrade-v66n.css'
        . '?v=20260731-mobile-player-v66n'
    ))?>\"
>
"""
PREVIEW_LINK = '<link rel="stylesheet" href="assets/css/music-mobile-upgrade-v66n.css?v=20260731-mobile-player-v66n">\n'

for name in ('music-library.php', 'music-collection.php'):
    path = Path(name)
    source = path.read_text(encoding='utf-8')
    if 'music-mobile-upgrade-v66n.css' not in source:
        source = source.replace('</head>', LIVE_LINK + '</head>', 1)
    path.write_text(source, encoding='utf-8')

for name in ('music-library-preview.php', 'music-collection-preview.php'):
    path = Path(name)
    source = path.read_text(encoding='utf-8')
    if 'music-mobile-upgrade-v66n.css' not in source:
        source = source.replace('</head>', PREVIEW_LINK + '</head>', 1)
    path.write_text(source, encoding='utf-8')

print('Music Library mobile stylesheet linked to live and preview pages.')

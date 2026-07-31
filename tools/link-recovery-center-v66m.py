#!/usr/bin/env python3
from pathlib import Path

path = Path('portal/operations-analytics-extensions.php')
source = path.read_text(encoding='utf-8')
old = """    $section = (string)($_GET['section'] ?? 'overview');
    $addition = '';
    if ($section === 'overview') {"""
new = """    $section = (string)($_GET['section'] ?? 'overview');
    $addition = '';
    if ($section === 'overview' && is_file(__DIR__ . '/recovery-center.php')) {
        $addition .= '<section class=\"operations-card operations-recovery-entry\"><header class=\"operations-card-header\"><div><h2>Incident Recovery Center</h2><p>Simulate, approve, execute, and verify allowlisted recovery runbooks for active operational incidents.</p></div><a class=\"operations-button primary\" data-recovery-center-entry href=\"' . e(app_url('portal/recovery-center.php')) . '\">Open Recovery Center</a></header></section>';
    }
    if ($section === 'overview') {"""
if old not in source:
    if 'data-recovery-center-entry' in source:
        print('Recovery Center entry already present.')
        raise SystemExit(0)
    raise SystemExit('Operations integration anchor not found.')
path.write_text(source.replace(old, new, 1), encoding='utf-8')
print('Recovery Center Operations entry added.')

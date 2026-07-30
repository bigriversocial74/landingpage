from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8')


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected one anchor, found {count}')
    return content.replace(old, new, 1)


http = read('portal/activitypub-http.php')
http = replace_once(
    http,
    r'''    if (!preg_match_all('/([A-Za-z][A-Za-z0-9_-]*)="((?:\\.|[^"\\])*)"/', $value, $matches, PREG_SET_ORDER)) {
''',
    r'''    if (!preg_match_all('/([A-Za-z][A-Za-z0-9_-]*)="((?:\\\\.|[^"\\\\])*)"/', $value, $matches, PREG_SET_ORDER)) {
''',
    'HTTP Signature quoted-value parser',
)
write('portal/activitypub-http.php', http)

pure = read('tests/activitypub-v66f.php')
pure = replace_once(
    pure,
    '''    ['Undo activity',"$activityType === 'Undo'",$source['service']],
''',
    '''    ['Undo activity',"\\$activityType === 'Undo'",$source['service']],
''',
    'Undo source-test literal',
)
pure = replace_once(
    pure,
    '''    ['Delete publication hook',"activitypub_blog_event($id, 'Delete'",$source['publishing']],
''',
    '''    ['Delete publication hook',"activitypub_blog_event(\\$id, 'Delete'",$source['publishing']],
''',
    'Delete-hook source-test literal',
)
pure = replace_once(
    pure,
    '''    'tools/harden-activitypub-v66f.py','.github/workflows/harden-activitypub-v66f.yml',
''',
    '''    'tools/harden-activitypub-v66f.py','.github/workflows/harden-activitypub-v66f.yml',
    'tools/repair-activitypub-certification-v66f.py','.github/workflows/repair-activitypub-certification-v66f.yml',
''',
    'repair workflow cleanup contract',
)
write('tests/activitypub-v66f.php', pure)

workflow = read('.github/workflows/activitypub-federation-quality.yml')
workflow = replace_once(
    workflow,
    '''          test ! -e .github/workflows/harden-activitypub-v66f.yml
''',
    '''          test ! -e .github/workflows/harden-activitypub-v66f.yml
          test ! -e tools/repair-activitypub-certification-v66f.py
          test ! -e .github/workflows/repair-activitypub-certification-v66f.yml
''',
    'permanent cleanup contract',
)
write('.github/workflows/activitypub-federation-quality.yml', workflow)

schema = read('database/north_mountain_portal.sql')
activity_migration = read('database/activitypub_federation_v66f.sql')
activity_section = activity_migration.replace(
    "SELECT 'North Mountain Media ActivityPub Federation v66F migration complete' AS migration_status;",
    '',
).strip()
if schema.count(activity_section) != 1:
    raise SystemExit(
        'fresh schema ActivityPub block: expected one exact migration block, '
        f'found {schema.count(activity_section)}'
    )
schema = schema.replace(activity_section, '', 1).rstrip()

pod_marker = 'CREATE TABLE IF NOT EXISTS pod_identities ('
if pod_marker not in schema:
    pod_section = read('database/pod_identity_relationships_v63.sql').strip()
    schema += '\n\n-- Fresh-install dependency: POD Identity & Relationships v63\n' + pod_section

schema += '\n\n' + activity_section + '\n'

if schema.count(pod_marker) != 1:
    raise SystemExit(
        'fresh schema must define pod_identities exactly once; '
        f'found {schema.count(pod_marker)}'
    )
for table in [
    'activitypub_actor_keys', 'activitypub_remote_actors',
    'activitypub_followers', 'activitypub_inbox_activities',
    'activitypub_outbox_activities', 'activitypub_deliveries',
]:
    marker = 'CREATE TABLE IF NOT EXISTS ' + table
    if schema.count(marker) != 1:
        raise SystemExit(f'fresh schema must define {table} exactly once')

pod_position = schema.index(pod_marker)
activity_position = schema.index('CREATE TABLE IF NOT EXISTS activitypub_actor_keys (')
if activity_position <= pod_position:
    raise SystemExit('ActivityPub tables must follow POD identity tables in the fresh schema')

write('database/north_mountain_portal.sql', schema)
print('ActivityPub v66F certification defects repaired.')

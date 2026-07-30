from pathlib import Path

root = Path(__file__).resolve().parents[1]
core = root / 'portal/federated-interactions.php'
content = core.read_text(encoding='utf-8')
old = '''    $reaction = db()->prepare(
        'UPDATE activitypub_remote_reactions SET status="deleted",updated_at=UTC_TIMESTAMP()
         WHERE remote_actor_id=:actor_id AND (activity_uri=:object_uri OR object_uri=:object_uri) AND status<>"deleted"'
    );
    $reaction->execute(['actor_id' => (int)$remoteActor['id'], 'object_uri' => $objectUri]);
'''
new = '''    $reaction = db()->prepare(
        'UPDATE activitypub_remote_reactions SET status="deleted",updated_at=UTC_TIMESTAMP()
         WHERE remote_actor_id=:actor_id
           AND (activity_uri=:activity_uri OR object_uri=:target_object_uri)
           AND status<>"deleted"'
    );
    $reaction->execute([
        'actor_id' => (int)$remoteActor['id'],
        'activity_uri' => $objectUri,
        'target_object_uri' => $objectUri,
    ]);
'''
if content.count(old) != 1:
    raise SystemExit(f'Remote Delete SQL anchor count: {content.count(old)}')
core.write_text(content.replace(old, new, 1), encoding='utf-8')

test = root / 'tests/federated-interactions-v66g.php'
test_content = test.read_text(encoding='utf-8')
anchor = "    ['remote reaction ownership', 'cannot change ownership, target, or type', $source['core']],\n"
addition = anchor + "    ['native PDO Delete placeholders', 'target_object_uri', $source['core']],\n"
if test_content.count(anchor) != 1:
    raise SystemExit(f'Native PDO regression anchor count: {test_content.count(anchor)}')
test.write_text(test_content.replace(anchor, addition, 1), encoding='utf-8')

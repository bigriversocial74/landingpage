from pathlib import Path

root = Path(__file__).resolve().parents[1]
path = root / 'portal/federated-interactions.php'
content = path.read_text(encoding='utf-8')
old = "    $remote = activitypub_remote_actor($actorUri, true);\n"
new = "    // Reuse a recently verified actor and refresh automatically when the 24-hour cache expires.\n    $remote = activitypub_remote_actor($actorUri, false);\n"
if content.count(old) != 1:
    raise SystemExit(f'Follow actor cache anchor count: {content.count(old)}')
path.write_text(content.replace(old, new, 1), encoding='utf-8')

test = root / 'tests/federated-interactions-v66g.php'
test_content = test.read_text(encoding='utf-8')
anchor = "    ['signed outbound Follow', \"'type' => 'Follow'\", $source['core']],\n"
addition = anchor + "    ['verified actor cache reuse', 'activitypub_remote_actor($actorUri, false)', $source['core']],\n"
if test_content.count(anchor) != 1:
    raise SystemExit(f'Cache regression anchor count: {test_content.count(anchor)}')
test.write_text(test_content.replace(anchor, addition, 1), encoding='utf-8')

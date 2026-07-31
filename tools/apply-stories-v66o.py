#!/usr/bin/env python3
from __future__ import annotations

import base64
import io
import tarfile
from pathlib import Path

ROOT = Path.cwd().resolve()
PARTS = [Path(f'tools/stories-v66o.payload.{index:02d}') for index in range(5)]

missing = [str(path) for path in PARTS if not path.is_file()]
if missing:
    raise SystemExit('Missing Stories payload segments: ' + ', '.join(missing))

encoded = ''.join(path.read_text(encoding='utf-8').strip() for path in PARTS)
archive = base64.b64decode(encoded, validate=True)

with tarfile.open(fileobj=io.BytesIO(archive), mode='r:gz') as bundle:
    members = bundle.getmembers()
    for member in members:
        target = (ROOT / member.name).resolve()
        if ROOT != target and ROOT not in target.parents:
            raise SystemExit(f'Unsafe payload path: {member.name}')
        if member.issym() or member.islnk():
            raise SystemExit(f'Links are not permitted in payload: {member.name}')
    bundle.extractall(ROOT, members=members, filter='data')

service = Path('portal/activitypub-service.php')
source = service.read_text(encoding='utf-8')
if "require_once __DIR__ . '/stories-service.php';" not in source:
    source = source.replace(
        "require_once __DIR__ . '/federated-timeline.php';\n",
        "require_once __DIR__ . '/federated-timeline.php';\nrequire_once __DIR__ . '/stories-service.php';\n",
        1,
    )
if 'stories_process_inbound($inboxId, $payload, $remote)' not in source:
    source = source.replace(
        "        } elseif (federated_timeline_process_inbound($inboxId, $payload, $remote)) {\n            activitypub_update_inbox_status($inboxId, 'accepted');\n",
        "        } elseif (stories_process_inbound($inboxId, $payload, $remote)) {\n            activitypub_update_inbox_status($inboxId, 'accepted');\n        } elseif (federated_timeline_process_inbound($inboxId, $payload, $remote)) {\n            activitypub_update_inbox_status($inboxId, 'accepted');\n",
        1,
    )
if 'UPDATE pod_stories SET status="deleted"' not in source:
    source = source.replace(
        "            if (federated_messaging_schema_available()) {\n",
        "            if (stories_schema_available()) {\n                db()->prepare('UPDATE pod_stories SET status=\"deleted\",deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE remote_actor_id=:actor_id AND direction=\"remote\"')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n            }\n            if (federated_messaging_schema_available()) {\n",
        1,
    )
service.write_text(source, encoding='utf-8')

feed = Path('portal/federated-feed.php')
source = feed.read_text(encoding='utf-8')
if 'assets/css/stories-v66o.css' not in source:
    source = source.replace(
        '<link rel="stylesheet" href="<?=e(app_url(\'assets/css/federated-timeline.css?v=20260730-v66H\'))?>">',
        '<link rel="stylesheet" href="<?=e(app_url(\'assets/css/federated-timeline.css?v=20260730-v66H\'))?>">\n<link rel="stylesheet" href="<?=e(app_url(\'assets/css/stories-v66o.css?v=20260731-v66O\'))?>">',
        1,
    )
if 'data-stories-app' not in source:
    source = source.replace(
        '<div class="ft-shell">',
        '<div class="ft-shell" data-stories-app data-story-view-endpoint="<?=e(app_url(\'api/story-view.php\'))?>" data-csrf="<?=e(csrf_token())?>">',
        1,
    )
if 'stories_render_rail($userId,24)' not in source:
    source = source.replace(
        '</section>\n\n<?php if(!$schemaAvailable):?>',
        '</section>\n\n<?php stories_render_rail($userId,24);?>\n\n<?php if(!$schemaAvailable):?>',
        1,
    )
if 'stories_render_viewer()' not in source:
    source = source.replace(
        '</div>\n<?php portal_footer(); ?>',
        '<?php stories_render_viewer();?>\n</div>\n<script src="<?=e(app_url(\'assets/js/stories-v66o.js?v=20260731-v66O\'))?>"></script>\n<?php portal_footer(); ?>',
        1,
    )
feed.write_text(source, encoding='utf-8')

print('Stories v66O permanent source extracted and integrated.')
# Existing workflow trigger commit.

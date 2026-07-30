from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one anchor, found {count}")
    return content.replace(old, new, 1)


# Avoid a hard dependency cycle inside the standalone federation module.
federated = read("portal/federated-interactions.php")
federated = replace_once(
    federated,
    "require_once __DIR__ . '/content-interactions.php';\n\n",
    "",
    "federated core content-interactions require",
)
write("portal/federated-interactions.php", federated)

# ActivityPub runtime integration.
service = read("portal/activitypub-service.php")
service = replace_once(
    service,
    "require_once __DIR__ . '/activitypub-http.php';\nrequire_once __DIR__ . '/notifications.php';\n",
    "require_once __DIR__ . '/activitypub-http.php';\nrequire_once __DIR__ . '/notifications.php';\nrequire_once __DIR__ . '/content-interactions.php';\nrequire_once __DIR__ . '/federated-interactions.php';\n",
    "ActivityPub dependencies",
)
service = replace_once(
    service,
    "if (!in_array($activityType, ['Create', 'Update', 'Delete', 'Accept', 'Reject'], true)) {",
    "if (!in_array($activityType, ['Create', 'Update', 'Delete', 'Accept', 'Reject', 'Follow', 'Undo', 'Like', 'Announce'], true)) {",
    "ActivityPub outbox activity types",
)
service = replace_once(
    service,
    "        } elseif ($activityType === 'Undo') {",
    "        } elseif (federated_interactions_process_inbound($inboxId, $payload, $remote)) {\n            activitypub_update_inbox_status($inboxId, 'accepted');\n        } elseif ($activityType === 'Undo') {",
    "ActivityPub inbound federated interaction bridge",
)
service = replace_once(
    service,
    "            db()->prepare(\n                'UPDATE activitypub_followers SET status=\"removed\",moderated_at=UTC_TIMESTAMP()\n                 WHERE remote_actor_id=:actor_id'\n            )->execute(['actor_id' => (int)$remote['id']]);\n            activitypub_update_inbox_status($inboxId, 'accepted');",
    "            db()->prepare(\n                'UPDATE activitypub_followers SET status=\"removed\",moderated_at=UTC_TIMESTAMP()\n                 WHERE remote_actor_id=:actor_id'\n            )->execute(['actor_id' => (int)$remote['id']]);\n            if (federated_interactions_schema_available()) {\n                db()->prepare('UPDATE activitypub_following SET status=\"removed\",removed_at=UTC_TIMESTAMP() WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n                db()->prepare('UPDATE activitypub_remote_comments SET status=\"deleted\",deleted_at=UTC_TIMESTAMP() WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n                db()->prepare('UPDATE activitypub_remote_reactions SET status=\"deleted\" WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n            }\n            activitypub_update_inbox_status($inboxId, 'accepted');",
    "ActivityPub remote actor deletion cleanup",
)
service = replace_once(
    service,
    "function activitypub_following_document(): array\n{\n    return [\n        '@context' => 'https://www.w3.org/ns/activitystreams',\n        'id' => activitypub_following_url(),\n        'type' => 'OrderedCollection',\n        'totalItems' => 0,\n        'orderedItems' => [],\n    ];\n}",
    "function activitypub_following_document(): array\n{\n    if (function_exists('federated_interactions_following_document')\n        && federated_interactions_schema_available()) {\n        return federated_interactions_following_document();\n    }\n    return [\n        '@context' => 'https://www.w3.org/ns/activitystreams',\n        'id' => activitypub_following_url(),\n        'type' => 'OrderedCollection',\n        'totalItems' => 0,\n        'orderedItems' => [],\n    ];\n}",
    "ActivityPub Following collection",
)
write("portal/activitypub-service.php", service)

# Blog interaction lifecycle integration.
content = read("portal/content-interactions.php")
content = replace_once(
    content,
    "require_once __DIR__ . '/notifications.php';\n",
    "require_once __DIR__ . '/notifications.php';\nif (defined('NMM_BOOTSTRAPPED') && is_file(__DIR__ . '/activitypub-service.php')) {\n    require_once __DIR__ . '/activitypub-service.php';\n}\n",
    "Content interaction federation bootstrap",
)
content = replace_once(
    content,
    "    } else {\n        content_interactions_notify_participants($comment);\n    }\n    log_activity('content_comment_created'",
    "    } else {\n        content_interactions_notify_participants($comment);\n        if (function_exists('federated_interactions_local_comment_event')) {\n            federated_interactions_local_comment_event($commentId, 'Create', $userId);\n        }\n    }\n    log_activity('content_comment_created'",
    "Local comment Create federation hook",
)
content = replace_once(
    content,
    "    if (($user['role'] ?? '') !== 'admin') {\n        content_interactions_notify_admins('Edited Blog comment awaiting moderation', mb_substr($body, 0, 240), 'portal/admin.php?view=blog&moderation=1', $commentId);\n    }\n    return ['id' => $commentId, 'status' => ($user['role'] ?? '') === 'admin' ? (string)$comment['status'] : 'pending'];",
    "    if (($user['role'] ?? '') !== 'admin') {\n        content_interactions_notify_admins('Edited Blog comment awaiting moderation', mb_substr($body, 0, 240), 'portal/admin.php?view=blog&moderation=1', $commentId);\n    } elseif ((string)$comment['status'] === 'approved' && function_exists('federated_interactions_local_comment_event')) {\n        federated_interactions_local_comment_event($commentId, 'Update', (int)$user['id']);\n    }\n    return ['id' => $commentId, 'status' => ($user['role'] ?? '') === 'admin' ? (string)$comment['status'] : 'pending'];",
    "Local comment Update federation hook",
)
content = replace_once(
    content,
    "    db()->prepare(\n        'UPDATE content_comments SET status=\"deleted\",body=\"\",deleted_at=UTC_TIMESTAMP(),deleted_by=:deleted_by WHERE id=:id'\n    )->execute(['deleted_by' => (int)$user['id'], 'id' => $commentId]);\n    log_activity('content_comment_deleted'",
    "    db()->prepare(\n        'UPDATE content_comments SET status=\"deleted\",body=\"\",deleted_at=UTC_TIMESTAMP(),deleted_by=:deleted_by WHERE id=:id'\n    )->execute(['deleted_by' => (int)$user['id'], 'id' => $commentId]);\n    if ((string)$comment['status'] === 'approved' && function_exists('federated_interactions_local_comment_event')) {\n        federated_interactions_local_comment_event($commentId, 'Delete', (int)$user['id'], $comment);\n    }\n    log_activity('content_comment_deleted'",
    "Local comment Delete federation hook",
)
content = replace_once(
    content,
    "    return ['active' => $active, 'counts' => content_interactions_reaction_summary($targetType, $contentType, $targetId)];",
    "    if (function_exists('federated_interactions_local_reaction_event')) {\n        federated_interactions_local_reaction_event(\n            $userId, $targetType, $contentType, $targetId, $existing, $active\n        );\n    }\n    return ['active' => $active, 'counts' => content_interactions_reaction_summary($targetType, $contentType, $targetId)];",
    "Local reaction federation hook",
)
content = replace_once(
    content,
    "    }\n    log_activity('content_comment_moderated', 'content_comment', $commentId, ['status' => $status]);",
    "    }\n    if (function_exists('federated_interactions_local_comment_event')) {\n        if ($status === 'approved') {\n            $event = federated_interactions_local_map('comment', (string)$commentId) ? 'Update' : 'Create';\n            federated_interactions_local_comment_event($commentId, $event, $moderatorId);\n        } elseif ((string)$comment['status'] === 'approved') {\n            federated_interactions_local_comment_event($commentId, 'Delete', $moderatorId, $comment);\n        }\n    }\n    log_activity('content_comment_moderated', 'content_comment', $commentId, ['status' => $status]);",
    "Moderated local comment federation hook",
)
content = replace_once(
    content,
    "    </section>\n    <?php\n}",
    "    </section>\n    <?php\n    if (function_exists('federated_interactions_render_public')) {\n        federated_interactions_render_public($post);\n    }\n}",
    "Public federated conversation rendering",
)
write("portal/content-interactions.php", content)

# ActivityPub administrator integration.
admin = read("portal/activitypub-admin.php")
admin = replace_once(
    admin,
    "require_once __DIR__ . '/activitypub-service.php';\n",
    "require_once __DIR__ . '/activitypub-service.php';\nrequire_once __DIR__ . '/federated-interactions-admin.php';\n",
    "ActivityPub admin dependency",
)
admin = replace_once(
    admin,
    "function activitypub_handle_admin_action(string $action, array $user): bool\n{\n    $actions = [",
    "function activitypub_handle_admin_action(string $action, array $user): bool\n{\n    if (federated_interactions_handle_admin_action($action, $user)) return true;\n    $actions = [",
    "ActivityPub admin federated action bridge",
)
admin = replace_once(
    admin,
    "<section class=\"panel\" id=\"deliveries\">",
    "<?php federated_interactions_render_admin($user); ?>\n\n<section class=\"panel\" id=\"deliveries\">",
    "ActivityPub admin federated workspace",
)
write("portal/activitypub-admin.php", admin)

# Unified Inbox normalization.
inbox = read("portal/unified-inbox.php")
inbox = replace_once(
    inbox,
    "if (is_file(__DIR__ . '/content-interactions.php')) require_once __DIR__ . '/content-interactions.php';\n",
    "if (is_file(__DIR__ . '/content-interactions.php')) require_once __DIR__ . '/content-interactions.php';\nif (is_file(__DIR__ . '/federated-interactions.php')) require_once __DIR__ . '/federated-interactions.php';\n",
    "Unified Inbox federated dependency",
)
inbox = replace_once(
    inbox,
    "        'content_comment' => ['label' => 'Blog Activity', 'category' => 'social', 'icon' => '♥'],\n",
    "        'content_comment' => ['label' => 'Blog Activity', 'category' => 'social', 'icon' => '♥'],\n        'federated_comment' => ['label' => 'Federated Reply', 'category' => 'social', 'icon' => '◌'],\n        'federated_reaction' => ['label' => 'Federated Reaction', 'category' => 'social', 'icon' => '↻'],\n        'federated_follow' => ['label' => 'Federated Follow', 'category' => 'social', 'icon' => '◎'],\n",
    "Unified Inbox federated source catalog",
)
inbox = replace_once(
    inbox,
    "$duplicateEntities = ['content_comment', 'communication_call', 'communication_thread', 'call_center_request', 'pod_message', 'pod_message_thread'];",
    "$duplicateEntities = ['content_comment', 'federated_comment', 'federated_reaction', 'federated_follow', 'communication_call', 'communication_thread', 'call_center_request', 'pod_message', 'pod_message_thread'];",
    "Unified Inbox federated notification deduplication",
)
inbox = replace_once(
    inbox,
    "        unified_inbox_comment_items((int)$user['id']),\n        unified_inbox_lead_items(),",
    "        unified_inbox_comment_items((int)$user['id']),\n        function_exists('federated_interactions_inbox_items') ? federated_interactions_inbox_items() : [],\n        unified_inbox_lead_items(),",
    "Unified Inbox federated item collection",
)
write("portal/unified-inbox.php", inbox)

# Existing CSS entry points import the v66G stylesheet.
for css_path in ["assets/css/activitypub-admin.css", "assets/css/content-interactions.css"]:
    css = read(css_path)
    import_line = "@import url('federated-interactions.css');\n"
    if import_line not in css:
        css = import_line + css
    write(css_path, css)

# Fresh installation schema includes the repeat-safe additive v66G migration at the true end.
schema = read("database/north_mountain_portal.sql")
migration = read("database/federated_interactions_v66g.sql")
marker = "-- Fresh-install dependency: Federated Interactions v66G"
if marker not in schema:
    schema = schema.rstrip() + "\n\n" + marker + "\n" + migration.rstrip() + "\n"
write("database/north_mountain_portal.sql", schema)

print("Federated Interactions v66G integration patch applied.")

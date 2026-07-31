from pathlib import Path


def replace_once(path: str, old: str, new: str, label: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one match, found {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


replace_once(
    "portal/activitypub-service.php",
    "require_once __DIR__ . '/federated-timeline.php';",
    "require_once __DIR__ . '/federated-timeline.php';\nrequire_once __DIR__ . '/federated-messaging.php';",
    "ActivityPub messaging require",
)
replace_once(
    "portal/activitypub-service.php",
    "        } elseif (federated_interactions_process_inbound($inboxId, $payload, $remote)) {\n            activitypub_update_inbox_status($inboxId, 'accepted');",
    "        } elseif (federated_messaging_process_inbound($inboxId, $payload, $remote)) {\n            activitypub_update_inbox_status($inboxId, 'accepted');\n        } elseif (federated_interactions_process_inbound($inboxId, $payload, $remote)) {\n            activitypub_update_inbox_status($inboxId, 'accepted');",
    "ActivityPub inbound messaging bridge",
)
replace_once(
    "portal/activitypub-service.php",
    "            if (federated_timeline_schema_available()) {\n                db()->prepare('UPDATE activitypub_remote_posts SET status=\"deleted\",deleted_at=UTC_TIMESTAMP() WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n                db()->prepare('UPDATE activitypub_remote_post_actions action JOIN activitypub_remote_posts post ON post.id=action.remote_post_id SET action.status=\"failed\",action.last_error=\"Remote actor deleted\" WHERE post.remote_actor_id=:actor_id AND action.status=\"active\"')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n            }\n            activitypub_update_inbox_status($inboxId, 'accepted');",
    "            if (federated_timeline_schema_available()) {\n                db()->prepare('UPDATE activitypub_remote_posts SET status=\"deleted\",deleted_at=UTC_TIMESTAMP() WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n                db()->prepare('UPDATE activitypub_remote_post_actions action JOIN activitypub_remote_posts post ON post.id=action.remote_post_id SET action.status=\"failed\",action.last_error=\"Remote actor deleted\" WHERE post.remote_actor_id=:actor_id AND action.status=\"active\"')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n            }\n            if (federated_messaging_schema_available()) {\n                db()->prepare('UPDATE activitypub_message_threads SET status=\"blocked\",needs_response=0 WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n                db()->prepare('UPDATE activitypub_messages SET status=\"failed\",last_error=\"Remote actor deleted\" WHERE remote_actor_id=:actor_id AND direction=\"outbound\" AND status IN (\"visible\",\"edited\")')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n            }\n            activitypub_update_inbox_status($inboxId, 'accepted');",
    "Remote actor message containment",
)
replace_once(
    "portal/activitypub-service.php",
    "        federated_timeline_sync_delivery($delivery, $result);\n        $processed[] = ['id' => (int)$delivery['id']] + $result;",
    "        federated_timeline_sync_delivery($delivery, $result);\n        federated_messaging_sync_delivery($delivery, $result);\n        $processed[] = ['id' => (int)$delivery['id']] + $result;",
    "Message delivery synchronization",
)
replace_once(
    "portal/activitypub-service.php",
    "    federated_timeline_reset_delivery($deliveryId);\n}",
    "    federated_timeline_reset_delivery($deliveryId);\n    federated_messaging_reset_delivery($deliveryId);\n}",
    "Message retry synchronization",
)

replace_once(
    "portal/federated-messaging.php",
    "    $trusted = $trust !== 'unknown';\n    $risk = federated_messaging_risk_score($remoteActor, $body, $attachments, $trusted);",
    "    $trusted = $trust !== 'unknown';\n    if ($settings['accept_mode'] === 'trusted' && !$trusted) return true;\n    $risk = federated_messaging_risk_score($remoteActor, $body, $attachments, $trusted);",
    "Trusted-only message policy",
)
replace_once(
    "portal/federated-messaging.php",
    "        federated_messaging_notify('Federated message held as spam', $body, 'activitypub_message', $messageId, 'high');",
    "        federated_messaging_notify('Federated message held as spam', $body, 'activitypub_message_thread', (int)$thread['id'], 'high');",
    "Spam deep link repair",
)
replace_once(
    "portal/federated-messaging.php",
    "    if (!federated_interactions_actor_allowed($thread)) {\n        throw new RuntimeException('The remote actor or domain is blocked.');\n    }",
    "    if (!federated_interactions_actor_allowed([\n        'id' => (int)$thread['remote_actor_id'],\n        'actor_uri' => (string)$thread['actor_uri'],\n        'status' => (string)($thread['actor_status'] ?? 'active'),\n    ])) {\n        throw new RuntimeException('The remote actor or domain is blocked.');\n    }",
    "Outbound actor-control shape",
)

replace_once(
    "portal/federated-messages.php",
    "            $enabled = isset($_POST['messages_enabled']) ? '1' : '0';",
    "            $enabled = input('messages_enabled') === '1' ? '1' : '0';",
    "Message enabled setting",
)
replace_once(
    "portal/federated-messages.php",
    "            $assist = isset($_POST['homeserver_assistance']) ? '1' : '0';",
    "            $assist = input('homeserver_assistance') === '1' ? '1' : '0';",
    "HomeServer assistance setting",
)
replace_once(
    "portal/federated-messages.php",
    "        <input type=\"hidden\" name=\"messages_enabled\" value=\"1\" <?=$settings['enabled']?'':'disabled'?>>\n        <?php if($settings['enabled']):?><input type=\"checkbox\" name=\"messages_enabled\" value=\"1\" checked hidden><?php endif;?>\n        <?php if($settings['homeserver_assistance']):?><input type=\"checkbox\" name=\"homeserver_assistance\" value=\"1\" checked hidden><?php endif;?>\n",
    "",
    "Remove duplicate settings controls",
)

replace_once(
    "portal/unified-inbox.php",
    "if (is_file(__DIR__ . '/federated-timeline.php')) require_once __DIR__ . '/federated-timeline.php';",
    "if (is_file(__DIR__ . '/federated-timeline.php')) require_once __DIR__ . '/federated-timeline.php';\nif (is_file(__DIR__ . '/federated-messaging.php')) require_once __DIR__ . '/federated-messaging.php';",
    "Unified Inbox messaging require",
)
replace_once(
    "portal/unified-inbox.php",
    "        'pod_message' => ['label' => 'POD Messages', 'category' => 'messages', 'icon' => '◈'],",
    "        'pod_message' => ['label' => 'POD Messages', 'category' => 'messages', 'icon' => '◈'],\n        'federated_message' => ['label' => 'Federated Messages', 'category' => 'messages', 'icon' => '@'],",
    "Unified Inbox source catalog",
)
replace_once(
    "portal/unified-inbox.php",
    "    $duplicateEntities = ['content_comment', 'federated_comment', 'federated_reaction', 'federated_follow', 'federated_post', 'federated_timeline_action', 'communication_call', 'communication_thread', 'call_center_request', 'pod_message', 'pod_message_thread'];",
    "    $duplicateEntities = ['content_comment', 'federated_comment', 'federated_reaction', 'federated_follow', 'federated_post', 'federated_timeline_action', 'activitypub_message', 'activitypub_message_thread', 'communication_call', 'communication_thread', 'call_center_request', 'pod_message', 'pod_message_thread'];",
    "Unified Inbox notification deduplication",
)

inbox = Path("portal/unified-inbox.php")
text = inbox.read_text(encoding="utf-8")
anchor = "function unified_inbox_state_maps(int $userId): array\n{"
if text.count(anchor) != 1:
    raise SystemExit(f"Unified Inbox adapter anchor: expected one match, found {text.count(anchor)}")
adapter = '''function unified_inbox_federated_message_items(int $userId): array
{
    if (!function_exists('federated_messaging_inbox_items')) return [];
    try {
        $rows = federated_messaging_inbox_items($userId);
    } catch (Throwable) {
        return [];
    }
    $items = [];
    foreach ($rows as $row) {
        $items[] = unified_inbox_item([
            'source_type' => 'federated_message',
            'source_id' => (int)$row['source_id'],
            'title' => (string)$row['title'],
            'participant' => (string)$row['actor_name'],
            'preview' => (string)$row['preview'],
            'occurred_at' => (string)$row['occurred_at'],
            'native_unread' => !empty($row['unread']),
            'native_status' => !empty($row['needs_response']) ? 'waiting' : 'open',
            'native_priority' => (string)$row['priority'],
            'native_needs_response' => !empty($row['needs_response']),
            'href' => app_url((string)$row['deep_link']),
            'metadata' => ['thread_key' => (string)$row['thread_key']],
        ]);
    }
    return $items;
}

'''
text = text.replace(anchor, adapter + anchor, 1)
inbox.write_text(text, encoding="utf-8")
replace_once(
    "portal/unified-inbox.php",
    "        unified_inbox_pod_items(),\n        unified_inbox_comment_items((int)$user['id']),",
    "        unified_inbox_pod_items(),\n        unified_inbox_federated_message_items((int)$user['id']),\n        unified_inbox_comment_items((int)$user['id']),",
    "Unified Inbox message collection",
)

replace_once(
    "portal/activitypub-admin.php",
    "<div><span>Sections 66F–66H</span><h2>ActivityPub Federation</h2><p>Publish Blog articles, manage federated relationships, and operate a private followed-network timeline through signed delivery and durable receipts.</p><a class=\"activitypub-timeline-link\" href=\"<?=e(app_url('portal/federated-feed.php'))?>\">Open Federated Timeline</a></div>",
    "<div><span>Sections 66F–66I</span><h2>ActivityPub Federation</h2><p>Publish Blog articles, manage federated relationships, operate a private followed-network timeline, and keep social direct messages separate from trusted POD Messages.</p><div class=\"activitypub-actions\"><a class=\"activitypub-timeline-link\" href=\"<?=e(app_url('portal/federated-feed.php'))?>\">Open Federated Timeline</a><a class=\"activitypub-timeline-link\" href=\"<?=e(app_url('portal/federated-messages.php'))?>\">Open Federated Messages</a></div></div>",
    "Federation admin message link",
)

replace_once(
    "cron/process-activitypub.php",
    "$timelineExpired = federated_timeline_cleanup();",
    "$timelineExpired = federated_timeline_cleanup();\n$messageExpired = federated_messaging_cleanup();",
    "Federated message cleanup worker",
)
replace_once(
    "cron/process-activitypub.php",
    "echo 'Removed ' . $timelineExpired . ' expired unsaved timeline entries.' . PHP_EOL;",
    "echo 'Removed ' . $timelineExpired . ' expired unsaved timeline entries.' . PHP_EOL;\necho 'Removed ' . $messageExpired . ' expired closed federated messages.' . PHP_EOL;",
    "Federated message cleanup output",
)

schema = Path("database/north_mountain_portal.sql")
schema_text = schema.read_text(encoding="utf-8")
if "CREATE TABLE IF NOT EXISTS activitypub_message_threads" in schema_text:
    raise SystemExit("Fresh schema already contains v66I tables")
migration = Path("database/federated_messaging_v66i.sql").read_text(encoding="utf-8")
schema.write_text(schema_text.rstrip() + "\n\n" + migration + "\n", encoding="utf-8")

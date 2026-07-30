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


# Harden and optimize the timeline core before integration.
core = read('portal/federated-timeline.php')
core = replace_once(
    core,
    "    $uuid = pod_uuid_v4();\n    db()->prepare(\n",
    "    $uuid = pod_uuid_v4();\n    $rawObject = $activity['object'] ?? '';\n    $actionObjectUri = is_array($rawObject)\n        ? trim((string)($rawObject['inReplyTo'] ?? $rawObject['id'] ?? ''))\n        : trim((string)$rawObject);\n    db()->prepare(\n",
    'safe action object extraction',
)
core = replace_once(
    core,
    "        'object_uri' => (string)($activity['object']['inReplyTo'] ?? $activity['object'] ?? ''),\n",
    "        'object_uri' => $actionObjectUri,\n",
    'safe action object assignment',
)
old_cleanup = """    $days = federated_timeline_settings()['retention_days'];
    $statement = db()->prepare(
        'DELETE post FROM activitypub_remote_posts post
         WHERE COALESCE(post.source_published_at,post.created_at)<DATE_SUB(UTC_TIMESTAMP(),INTERVAL :retention_days DAY)
           AND NOT EXISTS (
                SELECT 1 FROM activitypub_timeline_user_state state
                WHERE state.remote_post_id=post.id AND state.saved_at IS NOT NULL
           )
           AND NOT EXISTS (
                SELECT 1 FROM activitypub_remote_post_actions action
                WHERE action.remote_post_id=post.id
           )'
    );
    $statement->bindValue('retention_days', $days, PDO::PARAM_INT);
    $statement->execute();
"""
new_cleanup = """    $days = federated_timeline_settings()['retention_days'];
    $statement = db()->prepare(
        'DELETE post FROM activitypub_remote_posts post
         WHERE COALESCE(post.source_published_at,post.created_at)<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ' . $days . ' DAY)
           AND NOT EXISTS (
                SELECT 1 FROM activitypub_timeline_user_state state
                WHERE state.remote_post_id=post.id AND state.saved_at IS NOT NULL
           )
           AND NOT EXISTS (
                SELECT 1 FROM activitypub_remote_post_actions action
                WHERE action.remote_post_id=post.id
           )'
    );
    $statement->execute();
"""
core = replace_once(core, old_cleanup, new_cleanup, 'bounded retention SQL')

sync_functions = r'''
function federated_timeline_sync_delivery(array $delivery, array $result): void
{
    if (!federated_timeline_schema_available()) return;
    $outboxId = (int)($delivery['outbox_activity_id'] ?? 0);
    $remoteActorId = (int)($delivery['remote_actor_id'] ?? 0);
    if ($outboxId <= 0 || $remoteActorId <= 0) return;
    $statement = db()->prepare(
        'UPDATE activitypub_remote_post_actions action
         JOIN activitypub_remote_posts post ON post.id=action.remote_post_id
         SET action.status=CASE WHEN :delivered=1 THEN "active" ELSE "failed" END,
             action.last_error=CASE WHEN :delivered2=1 THEN NULL ELSE :last_error END,
             action.updated_at=UTC_TIMESTAMP()
         WHERE action.outbox_activity_id=:outbox_id
           AND post.remote_actor_id=:remote_actor_id
           AND action.status NOT IN ("undone","deleted")'
    );
    $statement->execute([
        'delivered' => !empty($result['ok']) ? 1 : 0,
        'delivered2' => !empty($result['ok']) ? 1 : 0,
        'last_error' => !empty($result['ok']) ? null : mb_substr((string)($result['error'] ?? 'Delivery failed.'), 0, 1000),
        'outbox_id' => $outboxId,
        'remote_actor_id' => $remoteActorId,
    ]);
}

function federated_timeline_reset_delivery(int $deliveryId): void
{
    if (!federated_timeline_schema_available() || $deliveryId <= 0) return;
    db()->prepare(
        'UPDATE activitypub_remote_post_actions action
         JOIN activitypub_deliveries delivery ON delivery.outbox_activity_id=action.outbox_activity_id
         JOIN activitypub_remote_posts post ON post.id=action.remote_post_id
         SET action.status="active",action.last_error=NULL,action.updated_at=UTC_TIMESTAMP()
         WHERE delivery.id=:delivery_id
           AND delivery.remote_actor_id=post.remote_actor_id
           AND action.status="failed"'
    )->execute(['delivery_id' => $deliveryId]);
}

function federated_timeline_actions_for_posts(array $postIds): array
{
    if (!federated_timeline_schema_available()) return [];
    $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds), static fn(int $id): bool => $id > 0)));
    if (!$postIds) return [];
    $rows = db()->query(
        'SELECT id,remote_post_id,action_type,reply_text,reply_object_uri,status,created_at
         FROM activitypub_remote_post_actions
         WHERE remote_post_id IN (' . implode(',', $postIds) . ')
         ORDER BY remote_post_id,id DESC'
    )->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $postId = (int)$row['remote_post_id'];
        $result[$postId] ??= ['like' => null, 'announce' => null, 'replies' => []];
        $type = (string)$row['action_type'];
        if ($type === 'reply') {
            if (count($result[$postId]['replies']) < 20) $result[$postId]['replies'][] = $row;
        } elseif (in_array($type, ['like', 'announce'], true) && $result[$postId][$type] === null && (string)$row['status'] === 'active') {
            $result[$postId][$type] = $row;
        }
    }
    return $result;
}

'''
core = replace_once(
    core,
    "function federated_timeline_inbox_items(): array\n",
    sync_functions + "function federated_timeline_inbox_items(): array\n",
    'timeline delivery/action helpers',
)
write('portal/federated-timeline.php', core)

# Repair the private operator page and batch action loading.
page = read('portal/federated-feed.php')
page = replace_once(
    page,
    "if (is_post()) {\n    verify_csrf();\n",
    "if (is_post()) {\n    if (!same_origin_request()) { http_response_code(403); exit('Cross-origin request denied.'); }\n    verify_csrf();\n",
    'timeline same-origin POST boundary',
)
page = replace_once(
    page,
    "] : [];\n$actors = $schemaAvailable ? db()->query(\n",
    "] : [];\n$actionsByPost = $schemaAvailable\n    ? federated_timeline_actions_for_posts(array_column($posts, 'id'))\n    : [];\n$actors = $schemaAvailable ? db()->query(\n",
    'batched timeline actions',
)
old_loop = '''<?php foreach($posts as $post):
    $attachments=json_decode((string)($post['attachments_json']??''),true); if(!is_array($attachments))$attachments=[];
    $tags=json_decode((string)($post['tags_json']??''),true); if(!is_array($tags))$tags=[];
    $likeAction=federated_timeline_active_action((int)$post['id'],'like');
    $boostAction=federated_timeline_active_action((int)$post['id'],'announce');
    $replyActions=db()->prepare('SELECT id,reply_text,reply_object_uri,status,created_at FROM activitypub_remote_post_actions WHERE remote_post_id=:post_id AND action_type="reply" ORDER BY id DESC LIMIT 20');
    $replyActions->execute(['post_id'=>(int)$post['id']]); $replies=$replyActions->fetchAll();
?>'''
new_loop = '''<?php foreach($posts as $post):
    $attachments=json_decode((string)($post['attachments_json']??''),true); if(!is_array($attachments))$attachments=[];
    $tags=json_decode((string)($post['tags_json']??''),true); if(!is_array($tags))$tags=[];
    $postActions=$actionsByPost[(int)$post['id']]??['like'=>null,'announce'=>null,'replies'=>[]];
    $likeAction=$postActions['like'];
    $boostAction=$postActions['announce'];
    $replies=$postActions['replies'];
    $stateActions=[
        empty($post['read_at'])?'read':'unread'=>empty($post['read_at'])?'Mark read':'Mark unread',
        empty($post['saved_at'])?'save':'unsave'=>empty($post['saved_at'])?'Save':'Unsave',
        empty($post['hidden_at'])?'hide':'unhide'=>empty($post['hidden_at'])?'Hide':'Unhide',
    ];
?>'''
page = replace_once(page, old_loop, new_loop, 'timeline action N+1 removal')
page = replace_once(
    page,
    "<?php foreach([empty($post['read_at'])?'read':'unread'=>empty($post['read_at'])?'Mark read':'Mark unread',empty($post['saved_at'])?'save':'unsave'=>empty($post['saved_at'])?'Save':'Unsave',empty($post['hidden_at'])?'hide':'unhide'=>empty($post['hidden_at'])?'Hide':'Unhide'] as $stateAction=>$label):?>",
    "<?php foreach($stateActions as $stateAction=>$label):?>",
    'timeline state action syntax',
)
write('portal/federated-feed.php', page)

# WebFinger JRD is JSON and uses the same outbound DNS/TLS/rebinding protections.
http = read('portal/activitypub-http.php')
http = replace_once(
    http,
    "$allowedTypes = ['application/activity+json', 'application/ld+json', 'application/json'];",
    "$allowedTypes = ['application/activity+json', 'application/ld+json', 'application/json', 'application/jrd+json'];",
    'WebFinger JRD content type',
)
write('portal/activitypub-http.php', http)

# Inbound timeline, actor deletion containment, and delivery-state synchronization.
service = read('portal/activitypub-service.php')
service = replace_once(
    service,
    "require_once __DIR__ . '/federated-interactions.php';\n",
    "require_once __DIR__ . '/federated-interactions.php';\nrequire_once __DIR__ . '/federated-timeline.php';\n",
    'ActivityPub timeline dependency',
)
service = replace_once(
    service,
    "        } elseif ($activityType === 'Undo') {\n",
    "        } elseif (federated_timeline_process_inbound($inboxId, $payload, $remote)) {\n            activitypub_update_inbox_status($inboxId, 'accepted');\n        } elseif ($activityType === 'Undo') {\n",
    'ActivityPub timeline inbound bridge',
)
service = replace_once(
    service,
    "                db()->prepare('UPDATE activitypub_remote_reactions SET status=\"deleted\" WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n            }\n",
    "                db()->prepare('UPDATE activitypub_remote_reactions SET status=\"deleted\" WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n            }\n            if (federated_timeline_schema_available()) {\n                db()->prepare('UPDATE activitypub_remote_posts SET status=\"deleted\",deleted_at=UTC_TIMESTAMP() WHERE remote_actor_id=:actor_id')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n                db()->prepare('UPDATE activitypub_remote_post_actions action JOIN activitypub_remote_posts post ON post.id=action.remote_post_id SET action.status=\"failed\",action.last_error=\"Remote actor deleted\" WHERE post.remote_actor_id=:actor_id AND action.status=\"active\"')\n                    ->execute(['actor_id' => (int)$remote['id']]);\n            }\n",
    'remote actor timeline containment',
)
service = replace_once(
    service,
    "        $processed[] = ['id' => (int)$delivery['id']] + $result;\n",
    "        federated_timeline_sync_delivery($delivery, $result);\n        $processed[] = ['id' => (int)$delivery['id']] + $result;\n",
    'timeline delivery status synchronization',
)
service = replace_once(
    service,
    "    )->execute(['id' => $deliveryId]);\n}\n\nfunction activitypub_followers",
    "    )->execute(['id' => $deliveryId]);\n    federated_timeline_reset_delivery($deliveryId);\n}\n\nfunction activitypub_followers",
    'timeline delivery retry synchronization',
)
write('portal/activitypub-service.php', service)

# Actor/domain blocks contain timeline data immediately.
interactions = read('portal/federated-interactions.php')
interactions = replace_once(
    interactions,
    "        db()->prepare('UPDATE activitypub_remote_reactions SET status=\"undone\",updated_at=UTC_TIMESTAMP() WHERE remote_actor_id=:id AND status=\"active\"')->execute(['id' => $remoteActorId]);\n",
    "        db()->prepare('UPDATE activitypub_remote_reactions SET status=\"undone\",updated_at=UTC_TIMESTAMP() WHERE remote_actor_id=:id AND status=\"active\"')->execute(['id' => $remoteActorId]);\n        if (function_exists('federated_timeline_schema_available') && federated_timeline_schema_available()) {\n            db()->prepare('UPDATE activitypub_remote_posts SET status=\"hidden\",moderation_note=\"Remote actor blocked\",moderated_by_user_id=:user_id,moderated_at=UTC_TIMESTAMP() WHERE remote_actor_id=:id AND status IN (\"pending\",\"active\")')\n                ->execute(['user_id' => $userId, 'id' => $remoteActorId]);\n            db()->prepare('UPDATE activitypub_remote_post_actions action JOIN activitypub_remote_posts post ON post.id=action.remote_post_id SET action.status=\"failed\",action.last_error=\"Remote actor blocked\" WHERE post.remote_actor_id=:id AND action.status=\"active\"')\n                ->execute(['id' => $remoteActorId]);\n        }\n",
    'timeline actor block containment',
)
write('portal/federated-interactions.php', interactions)

# Unified Inbox catalog, deduplication, and collection.
inbox = read('portal/unified-inbox.php')
inbox = replace_once(
    inbox,
    "if (is_file(__DIR__ . '/federated-interactions.php')) require_once __DIR__ . '/federated-interactions.php';\n",
    "if (is_file(__DIR__ . '/federated-interactions.php')) require_once __DIR__ . '/federated-interactions.php';\nif (is_file(__DIR__ . '/federated-timeline.php')) require_once __DIR__ . '/federated-timeline.php';\n",
    'Unified Inbox timeline dependency',
)
inbox = replace_once(
    inbox,
    "        'federated_follow' => ['label' => 'Federated Follow', 'category' => 'social', 'icon' => '◎'],\n",
    "        'federated_follow' => ['label' => 'Federated Follow', 'category' => 'social', 'icon' => '◎'],\n        'federated_post' => ['label' => 'Federated Mention', 'category' => 'social', 'icon' => '@'],\n        'federated_timeline_action' => ['label' => 'Federated Action', 'category' => 'social', 'icon' => '↗'],\n",
    'Unified Inbox timeline source catalog',
)
inbox = replace_once(
    inbox,
    "'federated_comment', 'federated_reaction', 'federated_follow',",
    "'federated_comment', 'federated_reaction', 'federated_follow', 'federated_post', 'federated_timeline_action',",
    'Unified Inbox timeline notification deduplication',
)
inbox = replace_once(
    inbox,
    "        function_exists('federated_interactions_inbox_items') ? federated_interactions_inbox_items() : [],\n        unified_inbox_lead_items(),",
    "        function_exists('federated_interactions_inbox_items') ? federated_interactions_inbox_items() : [],\n        function_exists('federated_timeline_inbox_items') ? federated_timeline_inbox_items() : [],\n        unified_inbox_lead_items(),",
    'Unified Inbox timeline collection',
)
write('portal/unified-inbox.php', inbox)

# Federation workspace links directly to the private timeline.
admin = read('portal/activitypub-admin.php')
admin = replace_once(
    admin,
    "<div><span>Section 66F</span><h2>ActivityPub Federation</h2><p>Publish Blog articles to the fediverse through one owner-controlled POD actor, moderated followers, signed delivery, and durable receipts.</p></div>\n",
    "<div><span>Sections 66F–66H</span><h2>ActivityPub Federation</h2><p>Publish Blog articles, manage federated relationships, and operate a private followed-network timeline through signed delivery and durable receipts.</p><a class=\"activitypub-timeline-link\" href=\"<?=e(app_url('portal/federated-feed.php'))?>\">Open Federated Timeline</a></div>\n",
    'ActivityPub timeline navigation',
)
write('portal/activitypub-admin.php', admin)

# Retention runs with the existing worker after delivery processing.
cron = read('cron/process-activitypub.php')
cron = replace_once(
    cron,
    "$results = activitypub_process_delivery_queue($limit);\n",
    "$results = activitypub_process_delivery_queue($limit);\n$timelineExpired = federated_timeline_cleanup();\n",
    'timeline retention worker',
)
cron = replace_once(
    cron,
    "echo 'Processed ' . count($results) . ' ActivityPub deliveries.' . PHP_EOL;\n",
    "echo 'Processed ' . count($results) . ' ActivityPub deliveries.' . PHP_EOL;\necho 'Removed ' . $timelineExpired . ' expired unsaved timeline entries.' . PHP_EOL;\n",
    'timeline retention receipt',
)
write('cron/process-activitypub.php', cron)

# Fresh install includes v66H at the true end.
schema = read('database/north_mountain_portal.sql')
migration = read('database/federated_timeline_v66h.sql')
marker = '-- Fresh-install dependency: Federated Timeline v66H'
if marker not in schema:
    schema = schema.rstrip() + '\n\n' + marker + '\n' + migration.rstrip() + '\n'
write('database/north_mountain_portal.sql', schema)

print('Federated Timeline v66H integration patch applied.')

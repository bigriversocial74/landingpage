from pathlib import Path

root = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (root / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (root / path).write_text(content, encoding='utf-8')


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected one anchor, found {count}')
    return content.replace(old, new, 1)


core = read('portal/federated-interactions.php')
core = replace_once(
    core,
    "    $comment = $id > 0 ? content_interactions_comment($id) : null;\n    return $comment && ($comment['status'] ?? '') === 'approved' ? $id : 0;\n",
    "    $comment = $id > 0 ? content_interactions_comment($id) : null;\n    return $comment\n        && ($comment['status'] ?? '') === 'approved'\n        && (string)($comment['content_type'] ?? '') === 'blog_post'\n        && (int)($comment['depth'] ?? 0) === 0\n        ? $id\n        : 0;\n",
    'local comment reply target depth',
)
core = replace_once(
    core,
    "    $remoteComment = federated_interactions_remote_comment_from_uri($uri);\n    if ($remoteComment && ($remoteComment['status'] ?? '') === 'approved') {\n        return [\n",
    "    $remoteComment = federated_interactions_remote_comment_from_uri($uri);\n    if (\n        $remoteComment\n        && ($remoteComment['status'] ?? '') === 'approved'\n        && (int)($remoteComment['parent_remote_comment_id'] ?? 0) === 0\n        && (int)($remoteComment['parent_local_comment_id'] ?? 0) === 0\n    ) {\n        return [\n",
    'remote reply target depth',
)
core = replace_once(
    core,
    "    $target = federated_interactions_resolve_target($inReplyTo);\n    if (!$target) return false;\n    $body = federated_interactions_clean_remote_text",
    "    $target = federated_interactions_resolve_target($inReplyTo);\n    if (!$target) return false;\n    $interactionSettings = content_interactions_settings('blog_post', (int)$target['blog_post_id']);\n    if (!(int)$interactionSettings['comments_enabled']) return false;\n    if (\n        $interactionSettings['comments_closed_at']\n        && strtotime((string)$interactionSettings['comments_closed_at']) <= time()\n    ) return false;\n    if (\n        ((int)($target['local_comment_id'] ?? 0) > 0 || (int)($target['remote_comment_id'] ?? 0) > 0)\n        && !(int)$interactionSettings['replies_enabled']\n    ) return false;\n    $body = federated_interactions_clean_remote_text",
    'remote reply post policy',
)
core = replace_once(
    core,
    "    if ($existing && (int)$existing['remote_actor_id'] !== (int)$remoteActor['id']) {\n        throw new RuntimeException('A federated reply object cannot change ownership.');\n    }\n",
    "    if ($existing && (int)$existing['remote_actor_id'] !== (int)$remoteActor['id']) {\n        throw new RuntimeException('A federated reply object cannot change ownership.');\n    }\n    if (\n        $existing\n        && (\n            (int)$existing['blog_post_id'] !== (int)$target['blog_post_id']\n            || (int)($existing['parent_local_comment_id'] ?? 0) !== (int)($target['local_comment_id'] ?? 0)\n            || (int)($existing['parent_remote_comment_id'] ?? 0) !== (int)($target['remote_comment_id'] ?? 0)\n        )\n    ) {\n        throw new RuntimeException('A federated reply object cannot change its conversation target.');\n    }\n",
    'remote reply target immutability',
)
core = replace_once(
    core,
    "    $target = federated_interactions_resolve_target($objectUri);\n    if (!$target) return false;\n    $activityUri = trim((string)($payload['id'] ?? ''));\n    db()->prepare(\n",
    "    $target = federated_interactions_resolve_target($objectUri);\n    if (!$target) return false;\n    if (!(int)content_interactions_settings('blog_post', (int)$target['blog_post_id'])['reactions_enabled']) {\n        return false;\n    }\n    $activityUri = trim((string)($payload['id'] ?? ''));\n    $existingStatement = db()->prepare(\n        'SELECT remote_actor_id,object_uri,reaction_type FROM activitypub_remote_reactions\n         WHERE activity_uri=:activity_uri LIMIT 1'\n    );\n    $existingStatement->execute(['activity_uri' => $activityUri]);\n    $existingReaction = $existingStatement->fetch();\n    if (\n        $existingReaction\n        && (\n            (int)$existingReaction['remote_actor_id'] !== (int)$remoteActor['id']\n            || activitypub_normalize_url((string)$existingReaction['object_uri']) !== activitypub_normalize_url($objectUri)\n            || (string)$existingReaction['reaction_type'] !== strtolower($type)\n        )\n    ) {\n        throw new RuntimeException('A federated reaction activity cannot change ownership, target, or type.');\n    }\n    db()->prepare(\n",
    'remote reaction ownership and post policy',
)
core = replace_once(
    core,
    "function federated_interactions_process_follow_response(array $payload): bool\n",
    "function federated_interactions_process_follow_response(array $payload, array $remoteActor): bool\n",
    'follow response signature',
)
core = replace_once(
    core,
    "         WHERE follow_activity_uri=:follow_uri AND status=\"pending\"'\n    );\n    $statement->execute(['status' => $status, 'accepted' => $status, 'follow_uri' => $followUri]);\n",
    "         WHERE follow_activity_uri=:follow_uri AND remote_actor_id=:actor_id AND status=\"pending\"'\n    );\n    $statement->execute([\n        'status' => $status,\n        'accepted' => $status,\n        'follow_uri' => $followUri,\n        'actor_id' => (int)$remoteActor['id'],\n    ]);\n",
    'follow response verified actor ownership',
)
core = replace_once(
    core,
    "    if (in_array($type, ['Accept', 'Reject'], true)) return federated_interactions_process_follow_response($payload);\n",
    "    if (in_array($type, ['Accept', 'Reject'], true)) {\n        return federated_interactions_process_follow_response($payload, $remoteActor);\n    }\n",
    'follow response call',
)
comment_event_end = "    return $outboxId;\n}\n\nfunction federated_interactions_target_uri"
comment_event_replacement = "    return $outboxId;\n}\n\nfunction federated_interactions_safe_comment_event(\n    int $commentId,\n    string $eventType,\n    ?int $actorUserId = null,\n    ?array $snapshot = null\n): int {\n    try {\n        return federated_interactions_local_comment_event($commentId, $eventType, $actorUserId, $snapshot);\n    } catch (Throwable $exception) {\n        log_activity('federated_comment_delivery_deferred', 'content_comment', $commentId, [\n            'event_type' => $eventType,\n            'error' => mb_substr($exception->getMessage(), 0, 500),\n        ]);\n        return 0;\n    }\n}\n\nfunction federated_interactions_target_uri"
core = replace_once(core, comment_event_end, comment_event_replacement, 'nonblocking comment wrapper')
reaction_event_end = "    federated_interactions_save_local_map(\n        'reaction', $entityKey, $targetType === 'content' ? $targetId : 0,\n        $targetType === 'comment' ? $targetId : null, $objectUri,\n        (string)$like['id'], (string)$like['id'], hash('sha256', $payload), 'active', $userId\n    );\n}\n\nfunction federated_interactions_follow_actor"
reaction_event_replacement = "    federated_interactions_save_local_map(\n        'reaction', $entityKey, $targetType === 'content' ? $targetId : 0,\n        $targetType === 'comment' ? $targetId : null, $objectUri,\n        (string)$like['id'], (string)$like['id'], hash('sha256', $payload), 'active', $userId\n    );\n}\n\nfunction federated_interactions_safe_reaction_event(\n    int $userId,\n    string $targetType,\n    string $contentType,\n    int $targetId,\n    string $previousReaction,\n    string $activeReaction\n): void {\n    try {\n        federated_interactions_local_reaction_event(\n            $userId, $targetType, $contentType, $targetId, $previousReaction, $activeReaction\n        );\n    } catch (Throwable $exception) {\n        log_activity('federated_reaction_delivery_deferred', 'content_reaction', $targetId, [\n            'target_type' => $targetType,\n            'error' => mb_substr($exception->getMessage(), 0, 500),\n        ]);\n    }\n}\n\nfunction federated_interactions_follow_actor"
core = replace_once(core, reaction_event_end, reaction_event_replacement, 'nonblocking reaction wrapper')
write('portal/federated-interactions.php', core)

content = read('portal/content-interactions.php')
content = content.replace("function_exists('federated_interactions_local_comment_event')", "function_exists('federated_interactions_safe_comment_event')")
content = content.replace('federated_interactions_local_comment_event(', 'federated_interactions_safe_comment_event(')
content = content.replace("function_exists('federated_interactions_local_reaction_event')", "function_exists('federated_interactions_safe_reaction_event')")
content = content.replace('federated_interactions_local_reaction_event(', 'federated_interactions_safe_reaction_event(')
content = replace_once(
    content,
    "        log_activity('content_comment_auto_hidden', 'content_comment', $commentId, ['open_reports' => $count]);\n",
    "        if (function_exists('federated_interactions_safe_comment_event')) {\n            federated_interactions_safe_comment_event($commentId, 'Delete', null, $comment);\n        }\n        log_activity('content_comment_auto_hidden', 'content_comment', $commentId, ['open_reports' => $count]);\n",
    'auto-hidden comment federation cleanup',
)
content = replace_once(
    content,
    "        if ($status === 'approved') {\n            $event = federated_interactions_local_map('comment', (string)$commentId) ? 'Update' : 'Create';\n            federated_interactions_safe_comment_event($commentId, $event, $moderatorId);\n",
    "        if ($status === 'approved') {\n            $map = federated_interactions_local_map('comment', (string)$commentId);\n            $event = !$map || (string)($map['status'] ?? '') === 'deleted' ? 'Create' : 'Update';\n            federated_interactions_safe_comment_event($commentId, $event, $moderatorId);\n",
    'reapproved local comment recreation',
)
write('portal/content-interactions.php', content)

test = read('tests/federated-interactions-v66g.php')
anchor = "    ['remote object ownership', 'cannot change ownership', $source['core']],\n"
addition = anchor + "    ['remote target immutability', 'cannot change its conversation target', $source['core']],\n    ['remote reaction ownership', 'cannot change ownership, target, or type', $source['core']],\n    ['verified Follow response actor', 'remote_actor_id=:actor_id', $source['core']],\n    ['post comment policy', \"content_interactions_settings('blog_post'\", $source['core']],\n    ['nonblocking local federation', 'federated_interactions_safe_comment_event', $source['content'] . $source['core']],\n"
test = replace_once(test, anchor, addition, 'security regression checks')
write('tests/federated-interactions-v66g.php', test)

# The database test must pass the verified remote actor into Accept/Reject processing.
db_test = read('tests/federated-interactions-db-v66g.php')
db_test = replace_once(
    db_test,
    "    if (!federated_interactions_process_follow_response($accept)) $fail('Outbound Follow Accept processing failed.');\n",
    "    if (!federated_interactions_process_follow_response($accept, $remoteActor)) {\n        $fail('Outbound Follow Accept processing failed.');\n    }\n",
    'database Follow response call',
)
write('tests/federated-interactions-db-v66g.php', db_test)

from pathlib import Path


def replace_once(path: str, old: str, new: str, label: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one match, found {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


core = "portal/federated-messaging.php"
page = "portal/federated-messages.php"
source_test = "tests/federated-messaging-v66i.php"
db_test = "tests/federated-messaging-db-v66i.php"

replace_once(
    core,
    "function federated_messaging_risk_score(array $remoteActor, string $body, array $attachments, bool $trusted): int\n{",
    '''function federated_messaging_domain_hour_count(string $actorUri): int
{
    if (!federated_messaging_schema_available()) return 0;
    $host = strtolower((string)parse_url($actorUri, PHP_URL_HOST));
    if ($host === '') return 0;
    $rows = db()->query(
        'SELECT actor.actor_uri,COUNT(*) AS message_count
         FROM activitypub_messages message
         JOIN activitypub_remote_actors actor ON actor.id=message.remote_actor_id
         WHERE message.direction="inbound"
           AND message.created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)
         GROUP BY actor.id,actor.actor_uri
         LIMIT 500'
    )->fetchAll();
    $count = 0;
    foreach ($rows as $row) {
        if (strtolower((string)parse_url((string)$row['actor_uri'], PHP_URL_HOST)) === $host) {
            $count += (int)$row['message_count'];
        }
    }
    return $count;
}

function federated_messaging_risk_score(array $remoteActor, string $body, array $attachments, bool $trusted): int
{''',
    "domain rate helper",
)
replace_once(
    core,
    "    if (federated_messaging_actor_hour_count((int)$remoteActor['id']) >= $settings['actor_hourly_limit']) {\n        throw new RuntimeException('The remote actor exceeded the federated message rate limit.');\n    }",
    "    if (federated_messaging_actor_hour_count((int)$remoteActor['id']) >= $settings['actor_hourly_limit']) {\n        throw new RuntimeException('The remote actor exceeded the federated message rate limit.');\n    }\n    if (federated_messaging_domain_hour_count((string)$remoteActor['actor_uri']) >= $settings['actor_hourly_limit'] * 4) {\n        throw new RuntimeException('The remote domain exceeded the federated message rate limit.');\n    }",
    "domain rate enforcement",
)
replace_once(
    core,
    "function federated_messaging_set_user_state(int $threadId, int $userId, string $action): void\n{",
    '''function federated_messaging_mark_unread(int $threadId, int $userId): void
{
    if ($threadId <= 0 || $userId <= 0) return;
    federated_messaging_require_schema();
    db()->prepare(
        'INSERT INTO activitypub_message_user_state (thread_id,user_id,last_read_message_id,read_at)
         VALUES (:thread_id,:user_id,NULL,NULL)
         ON DUPLICATE KEY UPDATE last_read_message_id=NULL,read_at=NULL'
    )->execute(['thread_id' => $threadId, 'user_id' => $userId]);
}

function federated_messaging_set_user_state(int $threadId, int $userId, string $action): void
{''',
    "mark unread helper",
)
replace_once(
    core,
    "    if (!in_array($decision, ['accept','reject','reopen','close','block'], true)) {\n        throw new RuntimeException('Unsupported federated message moderation decision.');\n    }",
    "    if (!in_array($decision, ['accept','reject','reopen','close','block','report','delete_local'], true)) {\n        throw new RuntimeException('Unsupported federated message moderation decision.');\n    }\n    if ($decision === 'delete_local') {\n        federated_messaging_event($threadId, null, 'thread_deleted_local', $note !== '' ? $note : null, null, $userId);\n        db()->prepare('DELETE FROM activitypub_message_threads WHERE id=:id')->execute(['id' => $threadId]);\n        return;\n    }\n    if ($decision === 'report') {\n        $reportNote = mb_substr(trim($note), 0, 1000) ?: 'Reported by the POD owner';\n        federated_messaging_event($threadId, null, 'thread_reported', $reportNote, [\n            'remote_actor_id' => (int)$thread['remote_actor_id'],\n            'actor_uri' => (string)$thread['actor_uri'],\n        ], $userId);\n        federated_messaging_notify('Federated conversation reported', $reportNote, 'activitypub_message_thread', $threadId, 'high');\n        return;\n    }",
    "report and local delete decisions",
)

replace_once(
    page,
    "        } elseif (in_array($action, ['archive','unarchive','mute','unmute','pin','unpin','hide','unhide'], true)) {\n            federated_messaging_set_user_state($threadId, (int)$user['id'], $action);\n            flash('success', 'Conversation state updated.');\n        } elseif (in_array($action, ['accept','reject','reopen','close','block'], true)) {",
    "        } elseif (in_array($action, ['archive','unarchive','mute','unmute','pin','unpin','hide','unhide'], true)) {\n            federated_messaging_set_user_state($threadId, (int)$user['id'], $action);\n            flash('success', 'Conversation state updated.');\n        } elseif ($action === 'mark_unread') {\n            federated_messaging_mark_unread($threadId, (int)$user['id']);\n            $_SESSION['federated_message_keep_unread_once'] = $threadId;\n            flash('success', 'Conversation marked unread.');\n        } elseif (in_array($action, ['accept','reject','reopen','close','block','report','delete_local'], true)) {",
    "page moderation actions",
)
replace_once(
    page,
    "            federated_messaging_moderate_thread($threadId, $action, (int)$user['id'], input('moderation_note'));\n            flash('success', 'Federated conversation updated.');",
    "            federated_messaging_moderate_thread($threadId, $action, (int)$user['id'], input('moderation_note'));\n            if ($action === 'delete_local') $threadId = 0;\n            flash('success', $action === 'delete_local' ? 'The local federated conversation copy was deleted.' : 'Federated conversation updated.');",
    "page local delete redirect",
)
replace_once(
    page,
    "$messages = $selectedThread ? federated_messaging_thread_messages((int)$selectedThread['id']) : [];\nif ($selectedThread) federated_messaging_mark_read((int)$selectedThread['id'], (int)$user['id']);",
    '''$messages = $selectedThread ? federated_messaging_thread_messages((int)$selectedThread['id']) : [];
$keepUnread = (int)($_SESSION['federated_message_keep_unread_once'] ?? 0) === $selectedThreadId;
unset($_SESSION['federated_message_keep_unread_once']);
if ($selectedThread && !$keepUnread) federated_messaging_mark_read((int)$selectedThread['id'], (int)$user['id']);
$selectedState = null;
if ($selectedThread) {
    $stateStatement = db()->prepare(
        'SELECT * FROM activitypub_message_user_state WHERE thread_id=:thread_id AND user_id=:user_id LIMIT 1'
    );
    $stateStatement->execute(['thread_id' => $selectedThreadId, 'user_id' => (int)$user['id']]);
    $selectedState = $stateStatement->fetch() ?: [];
}''',
    "selected thread state",
)
replace_once(
    page,
    "$draftText = is_array($assistOnce) ? (string)($assistOnce['text'] ?? '') : '';",
    "$draftText = is_array($assistOnce) && in_array((string)($assistOnce['kind'] ?? ''), ['draft','translate'], true)\n    ? (string)($assistOnce['text'] ?? '') : '';",
    "summary reply prefill boundary",
)
replace_once(
    page,
    '''        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="pin"><button class="fm-button secondary" type="submit">Pin</button></form>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="archive"><button class="fm-button secondary" type="submit">Archive</button></form>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="mute"><button class="fm-button secondary" type="submit">Mute</button></form>''',
    '''        <?php $pinAction=!empty($selectedState['pinned_at'])?'unpin':'pin';$archiveAction=!empty($selectedState['archived_at'])?'unarchive':'archive';$muteAction=!empty($selectedState['muted_at'])?'unmute':'mute';?>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="<?=e($pinAction)?>"><button class="fm-button secondary" type="submit"><?=e(ucfirst($pinAction))?></button></form>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="<?=e($archiveAction)?>"><button class="fm-button secondary" type="submit"><?=e(ucfirst($archiveAction))?></button></form>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="<?=e($muteAction)?>"><button class="fm-button secondary" type="submit"><?=e(ucfirst($muteAction))?></button></form>
        <form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="mark_unread"><button class="fm-button secondary" type="submit">Mark unread</button></form>''',
    "toggle and unread controls",
)
replace_once(
    page,
    "    <?php if((string)$selectedThread['status']==='request'):?><div class=\"fm-request-controls\">",
    '''    <details><summary>Conversation safety</summary><div class="fm-request-controls"><form class="fm-form" method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="report"><label><span>Report note</span><input name="moderation_note" maxlength="1000" placeholder="Reason for the local report" required></label><button class="fm-button secondary" type="submit">Record report</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$selectedThreadId?>"><input type="hidden" name="action" value="delete_local"><input type="hidden" name="moderation_note" value="Deleted by the POD owner"><button class="fm-button danger" type="submit">Delete local copy</button></form></div></details>

    <?php if((string)$selectedThread['status']==='request'):?><div class="fm-request-controls">''',
    "report and local delete controls",
)

replace_once(
    source_test,
    "    ['actor hourly limit', 'federated_messaging_actor_hour_count', $source['core']],",
    "    ['actor hourly limit', 'federated_messaging_actor_hour_count', $source['core']],\n    ['domain hourly limit', 'federated_messaging_domain_hour_count', $source['core']],",
    "source domain limit assertion",
)
replace_once(
    source_test,
    "    ['same-origin actions', 'same_origin_request()', $source['page']],",
    "    ['same-origin actions', 'same_origin_request()', $source['page']],\n    ['durable mark unread', 'federated_messaging_mark_unread', $source['core'] . $source['page']],\n    ['local report evidence', \"'thread_reported'\", $source['core']],\n    ['owner local deletion', \"'delete_local'\", $source['core'] . $source['page']],\n    ['summary prefill boundary', \"['draft','translate']\", $source['page']],",
    "source safety control assertions",
)

replace_once(
    db_test,
    "$pdo = db();\n$pdo->exec(\"INSERT INTO users(role,email,password_hash,display_name,status) VALUES ('admin','v66i@example.test','x','V66I Admin','active')\");",
    "$pdo = db();\n$pdo->prepare('UPDATE settings SET setting_value=\"1\" WHERE setting_key=\"activitypub_messages_enabled\"')->execute();\n$pdo->prepare('UPDATE settings SET setting_value=\"requests\" WHERE setting_key=\"activitypub_messages_accept_mode\"')->execute();\n$pdo->exec(\"INSERT INTO users(role,email,password_hash,display_name,status) VALUES ('admin','v66i@example.test','x','V66I Admin','active')\");",
    "enable DB integration feature",
)
replace_once(
    db_test,
    "federated_messaging_set_user_state((int)$aliceThread['id'], $userId, 'pin');\nfederated_messaging_set_user_state((int)$aliceThread['id'], $userId, 'archive');",
    "federated_messaging_set_user_state((int)$aliceThread['id'], $userId, 'pin');\nfederated_messaging_set_user_state((int)$aliceThread['id'], $userId, 'archive');\nfederated_messaging_mark_unread((int)$aliceThread['id'], $userId);\nfederated_messaging_moderate_thread((int)$aliceThread['id'], 'report', $userId, 'Synthetic safety report');",
    "DB state and report coverage",
)
replace_once(
    db_test,
    "$assert(!empty($state['pinned_at']) && !empty($state['archived_at']), 'Per-user conversation state failed.');",
    "$assert(!empty($state['pinned_at']) && !empty($state['archived_at']) && empty($state['read_at']), 'Per-user conversation state or mark-unread failed.');\n$reportCount = (int)$pdo->query('SELECT COUNT(*) FROM activitypub_message_events WHERE thread_id=' . (int)$aliceThread['id'] . ' AND event_type=\"thread_reported\"')->fetchColumn();\n$assert($reportCount === 1, 'Local federated conversation report evidence was not stored.');",
    "DB report assertion",
)

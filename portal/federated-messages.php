<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-federated-messages-v66Q7 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/activitypub-service.php';
require_once __DIR__ . '/homeserver-adapter.php';
require_once __DIR__ . '/federated-messaging.php';

$user = require_role('admin');
$userId = (int)$user['id'];

$saveSetting = static function (string $key, string $value): void {
    db()->prepare(
        'INSERT INTO settings(setting_key,setting_value)
         VALUES(:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    )->execute([
        'setting_key' => $key,
        'setting_value' => $value,
    ]);
};

if (is_post()) {
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Cross-origin request denied.');
    }

    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action = input('action');
    $threadId = int_input('thread_id');

    try {
        if ($action === 'save_settings') {
            $mode = input('accept_mode', 'requests');
            if (!in_array($mode, ['requests', 'trusted', 'none'], true)) {
                $mode = 'requests';
            }
            $saveSetting('activitypub_messages_enabled', input('messages_enabled') === '1' ? '1' : '0');
            $saveSetting('activitypub_messages_accept_mode', $mode);
            $saveSetting('activitypub_messages_retention_days', (string)max(7, min(730, int_input('retention_days', 180))));
            $saveSetting('activitypub_messages_actor_hourly_limit', (string)max(3, min(120, int_input('actor_hourly_limit', 30))));
            $saveSetting('activitypub_messages_remote_media_mode', 'link_only');
            $saveSetting('activitypub_messages_homeserver_assistance', input('homeserver_assistance') === '1' ? '1' : '0');
            flash('success', 'Federated Message settings were saved.');
        } elseif (in_array($action, ['archive','unarchive','mute','unmute','pin','unpin','hide','unhide'], true)) {
            federated_messaging_set_user_state($threadId, $userId, $action);
            flash('success', 'Conversation state was updated.');
        } elseif ($action === 'mark_unread') {
            federated_messaging_mark_unread($threadId, $userId);
            $_SESSION['federated_message_keep_unread_once'] = $threadId;
        } elseif (in_array($action, ['accept','reject','reopen','close','block','report','delete_local'], true)) {
            federated_messaging_moderate_thread(
                $threadId,
                $action,
                $userId,
                input('moderation_note')
            );
            if ($action === 'delete_local') {
                $threadId = 0;
            }
            flash('success', $action === 'delete_local'
                ? 'The local federated conversation copy was deleted.'
                : 'Federated conversation was updated.');
        } elseif ($action === 'send_message') {
            federated_messaging_send(
                $threadId,
                input('body'),
                $userId,
                input('in_reply_to') ?: null
            );
            flash('success', 'Federated message was queued for signed delivery.');
        } elseif ($action === 'edit_message') {
            $message = federated_messaging_edit_outbound(
                int_input('message_id'),
                input('body'),
                $userId
            );
            $threadId = (int)($message['thread_id'] ?? $threadId);
            flash('success', 'Federated message update was queued.');
        } elseif ($action === 'delete_message') {
            $message = federated_messaging_message(int_input('message_id'));
            if (!$message) {
                throw new RuntimeException('The federated message was not found.');
            }
            $threadId = (int)$message['thread_id'];
            federated_messaging_delete_outbound((int)$message['id'], $userId);
            flash('success', 'Federated message deletion was queued.');
        } elseif ($action === 'retry_delivery') {
            activitypub_retry_delivery(int_input('delivery_id'));
            flash('success', 'Federated message delivery was reset for retry.');
        } elseif ($action === 'assist') {
            $result = federated_messaging_assist(
                $threadId,
                int_input('message_id') ?: null,
                input('assist_kind'),
                $userId,
                input('target_language')
            );
            $_SESSION['federated_message_assist_once'] = [
                'thread_id' => $threadId,
                'kind' => input('assist_kind'),
                'text' => (string)($result['text'] ?? ''),
                'message' => (string)($result['message'] ?? ''),
                'status' => (string)($result['status'] ?? ''),
                'created_at' => time(),
            ];
            flash(
                !empty($result['ok']) ? 'success' : 'error',
                !empty($result['ok'])
                    ? 'HomeServer returned a private proposal. Review it before sending.'
                    : (string)($result['message'] ?? 'HomeServer assistance was unavailable.')
            );
        } else {
            throw new RuntimeException('Unsupported Federated Messages action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('portal/federated-messages.php' . ($threadId > 0 ? '?thread=' . $threadId : ''));
}

$schemaAvailable = federated_messaging_schema_available();
$settings = federated_messaging_settings();
$filter = trim((string)($_GET['filter'] ?? 'inbox'));
if (!in_array($filter, ['inbox','requests','unread','pinned','muted','archived'], true)) {
    $filter = 'inbox';
}
$search = trim((string)($_GET['q'] ?? ''));
$threads = [];
$selectedThreadId = query_int('thread');
$selectedThread = null;
$messages = [];
$selectedState = [];
$runtimeError = '';

if ($schemaAvailable) {
    try {
        $threads = federated_messaging_threads($userId, $filter, $search);
        if ($selectedThreadId > 0) {
            $selectedThread = federated_messaging_thread($selectedThreadId);
        }
        if (!$selectedThread && $threads) {
            $selectedThread = $threads[0];
            $selectedThreadId = (int)$selectedThread['id'];
        }
        if ($selectedThread) {
            $messages = federated_messaging_thread_messages($selectedThreadId);
            $keepUnread = (int)($_SESSION['federated_message_keep_unread_once'] ?? 0) === $selectedThreadId;
            unset($_SESSION['federated_message_keep_unread_once']);
            if (!$keepUnread) {
                federated_messaging_mark_read($selectedThreadId, $userId);
            }
            $stateStatement = db()->prepare(
                'SELECT * FROM activitypub_message_user_state
                 WHERE thread_id=:thread_id AND user_id=:user_id
                 LIMIT 1'
            );
            $stateStatement->execute([
                'thread_id' => $selectedThreadId,
                'user_id' => $userId,
            ]);
            $selectedState = $stateStatement->fetch() ?: [];
        }
    } catch (Throwable $exception) {
        $runtimeError = 'Federated Messages could not load its conversation data.';
        log_activity('federated_messages_load_failed', null, null, [
            'error' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
}

try {
    $homeServer = homeserver_adapter_status();
} catch (Throwable) {
    $homeServer = [
        'mode' => 'standalone',
        'paired' => false,
        'online' => false,
        'capabilities' => [],
    ];
}

$assistOnce = $_SESSION['federated_message_assist_once'] ?? null;
unset($_SESSION['federated_message_assist_once']);
if (
    !is_array($assistOnce)
    || (int)($assistOnce['thread_id'] ?? 0) !== $selectedThreadId
    || time() - (int)($assistOnce['created_at'] ?? 0) > 900
) {
    $assistOnce = null;
}
$draftText = is_array($assistOnce)
    && in_array((string)($assistOnce['kind'] ?? ''), ['draft', 'translate'], true)
        ? (string)($assistOnce['text'] ?? '')
        : '';

portal_header('Federated Messages', 'communications', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/federated-messaging.css?v=20260731-v66Q7'))?>">

<div class="fm-shell">
    <section class="fm-panel fm-hero">
        <div>
            <span class="fm-kicker">ActivityPub social messaging</span>
            <h2>Federated Messages</h2>
            <p class="fm-muted">Signed social messages remain separate from trusted POD Messages. Unknown senders enter requests and remote media remains link-only.</p>
        </div>
        <div class="fm-actions">
            <a class="fm-button secondary" href="<?=e(app_url('portal/pod-messages.php'))?>">Private POD Messages</a>
            <a class="fm-button secondary" href="<?=e(app_url('portal/federated-feed.php'))?>">Federated Timeline</a>
            <a class="fm-button secondary" href="<?=e(app_url('portal/admin.php?view=federation'))?>">Federation controls</a>
            <a class="fm-button secondary" href="<?=e(app_url('portal/admin.php?view=delivery'))?>">Notification Delivery</a>
        </div>
    </section>

    <?php if (!$schemaAvailable): ?>
        <section class="fm-warning" role="status">
            <strong>Federated Messages is temporarily unavailable.</strong>
            <span>The page could not verify its required schema. Existing migrations are not assumed missing.</span>
        </section>
    <?php elseif ($runtimeError !== ''): ?>
        <section class="fm-warning" role="status">
            <strong><?=e($runtimeError)?></strong>
            <span>The failure was contained so the portal did not return HTTP 500.</span>
        </section>
    <?php else: ?>
        <section class="fm-panel">
            <span class="fm-kicker">Channel policy</span>
            <h2>Safety and assistance</h2>
            <form class="fm-form" method="post">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_settings">
                <div class="fm-settings">
                    <label><span>Message channel</span><select name="messages_enabled"><option value="1" <?=$settings['enabled'] ? 'selected' : ''?>>Enabled</option><option value="0" <?=!$settings['enabled'] ? 'selected' : ''?>>Disabled</option></select></label>
                    <label><span>Unknown senders</span><select name="accept_mode"><option value="requests" <?=$settings['accept_mode'] === 'requests' ? 'selected' : ''?>>Message requests</option><option value="trusted" <?=$settings['accept_mode'] === 'trusted' ? 'selected' : ''?>>Trusted relationships only</option><option value="none" <?=$settings['accept_mode'] === 'none' ? 'selected' : ''?>>Do not receive</option></select></label>
                    <label><span>Retention days</span><input type="number" min="7" max="730" name="retention_days" value="<?=$settings['retention_days']?>"></label>
                    <label><span>Per-user hourly limit</span><input type="number" min="3" max="120" name="actor_hourly_limit" value="<?=$settings['actor_hourly_limit']?>"></label>
                    <label><span>Remote media</span><input value="Link only" disabled></label>
                    <label><span>HomeServer</span><select name="homeserver_assistance"><option value="1" <?=$settings['homeserver_assistance'] ? 'selected' : ''?>>Owner-approved assistance</option><option value="0" <?=!$settings['homeserver_assistance'] ? 'selected' : ''?>>Disabled</option></select></label>
                </div>
                <div class="fm-actions">
                    <button class="fm-button" type="submit">Save policy</button>
                    <span class="fm-badge <?=$homeServer['mode'] === 'connected' ? 'safe' : ''?>">HomeServer: <?=e((string)$homeServer['mode'])?></span>
                </div>
            </form>
        </section>

        <div class="fm-layout">
            <aside class="fm-panel fm-nav">
                <span class="fm-kicker">Queues</span>
                <h2>Views</h2>
                <nav class="fm-filters">
                    <?php foreach (['inbox'=>'Inbox','requests'=>'Requests','unread'=>'Unread','pinned'=>'Pinned','muted'=>'Muted','archived'=>'Archived'] as $key => $label): ?>
                        <a class="<?=$filter === $key ? 'active' : ''?>" href="<?=e(app_url('portal/federated-messages.php?filter=' . $key))?>"><?=e($label)?></a>
                    <?php endforeach; ?>
                </nav>
                <form class="fm-search" method="get">
                    <input type="hidden" name="filter" value="<?=e($filter)?>">
                    <label><span class="fm-kicker">Search</span><input name="q" value="<?=e($search)?>" placeholder="User or message"></label>
                    <button class="fm-button secondary" type="submit">Search</button>
                </form>
            </aside>

            <section class="fm-panel fm-threads">
                <span class="fm-kicker">Conversations</span>
                <h2><?=e(ucfirst($filter))?></h2>
                <div class="fm-list">
                    <?php if (!$threads): ?>
                        <div class="fm-empty">No federated conversations match this view.</div>
                    <?php endif; ?>
                    <?php foreach ($threads as $thread): ?>
                        <?php
                        $name = (string)(
                            $thread['display_name']
                            ?: $thread['preferred_username']
                            ?: $thread['actor_uri']
                        );
                        $unread = (int)($thread['last_read_message_id'] ?? 0)
                            < (int)($thread['last_message_id'] ?? 0);
                        ?>
                        <a
                            class="fm-item <?=(int)$thread['id'] === $selectedThreadId ? 'active' : ''?>"
                            href="<?=e(app_url('portal/federated-messages.php?' . http_build_query(['filter'=>$filter,'q'=>$search,'thread'=>(int)$thread['id']])))?>"
                        >
                            <header>
                                <h3><?=e($name)?></h3>
                                <span class="fm-badge <?=e((string)$thread['status'])?>"><?=e(status_label((string)$thread['status']))?></span>
                            </header>
                            <p><?=e(mb_substr((string)($thread['last_message_body'] ?? ''), 0, 130))?></p>
                            <div class="fm-actions">
                                <?php if ($unread): ?><span class="fm-badge unread">Unread</span><?php endif; ?>
                                <?php if ((int)($thread['needs_response'] ?? 0) === 1): ?><span class="fm-badge request">Needs response</span><?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <main class="fm-panel fm-conversation">
                <?php if (!$selectedThread): ?>
                    <div class="fm-empty">
                        <strong>No conversation selected.</strong>
                        <span>Federated conversations will appear here when messages arrive.</span>
                    </div>
                <?php else: ?>
                    <?php
                    $name = (string)(
                        $selectedThread['display_name']
                        ?: $selectedThread['preferred_username']
                        ?: $selectedThread['actor_uri']
                    );
                    $pinAction = !empty($selectedState['pinned_at']) ? 'unpin' : 'pin';
                    $archiveAction = !empty($selectedState['archived_at']) ? 'unarchive' : 'archive';
                    $muteAction = !empty($selectedState['muted_at']) ? 'unmute' : 'mute';
                    ?>
                    <div class="fm-thread-head">
                        <div>
                            <span class="fm-kicker">Federated conversation</span>
                            <h2><?=e($name)?></h2>
                            <div class="fm-id"><?=e((string)$selectedThread['actor_uri'])?></div>
                        </div>
                        <div class="fm-actions">
                            <?php foreach ([$pinAction, $archiveAction, $muteAction, 'mark_unread'] as $stateAction): ?>
                                <form method="post">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="thread_id" value="<?=$selectedThreadId?>">
                                    <input type="hidden" name="action" value="<?=e($stateAction)?>">
                                    <button class="fm-button secondary" type="submit"><?=e(status_label($stateAction))?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ((string)$selectedThread['status'] === 'request'): ?>
                        <div class="fm-request-controls">
                            <strong>Message request</strong>
                            <span>This user cannot receive a reply until the conversation is accepted.</span>
                            <?php foreach (['accept'=>'Accept','reject'=>'Reject','block'=>'Block user'] as $moderationAction => $label): ?>
                                <form method="post">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="thread_id" value="<?=$selectedThreadId?>">
                                    <input type="hidden" name="action" value="<?=e($moderationAction)?>">
                                    <button class="fm-button <?=$moderationAction === 'block' ? 'danger' : 'secondary'?>" type="submit"><?=e($label)?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="fm-timeline">
                        <?php if (!$messages): ?>
                            <div class="fm-empty">No messages are stored in this conversation.</div>
                        <?php endif; ?>
                        <?php foreach ($messages as $message): ?>
                            <?php
                            $classes = ['fm-message', (string)$message['direction']];
                            if (in_array((string)$message['status'], ['request','failed','deleted'], true)) {
                                $classes[] = (string)$message['status'];
                            }
                            $attachments = json_decode((string)($message['attachments_json'] ?? ''), true);
                            if (!is_array($attachments)) {
                                $attachments = [];
                            }
                            ?>
                            <article class="<?=e(implode(' ', $classes))?>">
                                <header>
                                    <strong><?=e((string)$message['direction'] === 'outbound' ? 'You' : $name)?></strong>
                                    <span><?=e(format_datetime((string)$message['created_at']))?></span>
                                </header>
                                <p><?=(string)$message['status'] === 'deleted' ? 'Message deleted.' : nl2br(e((string)($message['body_text'] ?? '')))?></p>
                                <?php if ($attachments): ?>
                                    <div class="fm-attachments">
                                        <?php foreach ($attachments as $attachment): ?>
                                            <a href="<?=e((string)$attachment['url'])?>" target="_blank" rel="noopener noreferrer nofollow">Open <?=e((string)($attachment['name'] ?: $attachment['type'] ?: 'attachment'))?> externally</a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <footer><span><?=e(status_label((string)$message['status']))?></span></footer>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!in_array((string)$selectedThread['status'], ['request','rejected','blocked','closed','deleted'], true)): ?>
                        <section class="fm-compose">
                            <?php if ($assistOnce): ?>
                                <div class="fm-assist-result"><strong>HomeServer proposal</strong><p><?=e((string)($assistOnce['message'] ?? ''))?></p></div>
                            <?php endif; ?>
                            <form class="fm-form" method="post">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="thread_id" value="<?=$selectedThreadId?>">
                                <label><span>Message</span><textarea name="body" maxlength="<?=$settings['max_body']?>" required><?=e($draftText)?></textarea></label>
                                <button class="fm-button" type="submit">Send signed message</button>
                            </form>
                            <?php if ($settings['homeserver_assistance']): ?>
                                <form class="fm-actions" method="post">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="assist">
                                    <input type="hidden" name="thread_id" value="<?=$selectedThreadId?>">
                                    <button class="fm-button secondary" name="assist_kind" value="draft" type="submit">Draft privately</button>
                                    <button class="fm-button secondary" name="assist_kind" value="summarize" type="submit">Summarize privately</button>
                                </form>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</div>

<?php portal_footer(); ?>

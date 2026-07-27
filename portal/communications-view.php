<?php
declare(strict_types=1);

require_once __DIR__ . '/communications.php';

function communication_render_message(
    array $message,
    array $user
): void {
    $own = (int)($message['sender_user_id'] ?? 0) === (int)$user['id'];
    $type = (string)$message['message_type'];
    $mediaUrl = !empty($message['attachment_id'])
        ? app_url(
            'portal/communication-media.php?id=' .
            (int)$message['attachment_id']
        )
        : '';
    $downloadUrl = $mediaUrl !== ''
        ? $mediaUrl . '&download=1'
        : '';
    ?>
    <article
        class="communication-message <?= $own ? 'own' : '' ?> type-<?= e($type) ?>"
        data-message-id="<?= (int)$message['id'] ?>"
    >
        <header>
            <strong><?= e(
                $message['sender_name']
                ?: status_label((string)$message['sender_role'])
            ) ?></strong>
            <time><?= e(format_datetime($message['created_at'])) ?></time>
        </header>

        <?php if (!empty($message['body'])): ?>
            <div class="communication-message-body">
                <?= nl2br(e($message['body'])) ?>
            </div>
        <?php endif; ?>

        <?php if (
            !empty($message['attachment_id'])
            && in_array(
                $message['media_kind'],
                ['audio', 'video', 'image'],
                true
            )
        ): ?>
            <div class="communication-media">
                <?php if ($message['media_kind'] === 'audio'): ?>
                    <audio controls preload="metadata" src="<?= e($mediaUrl) ?>"></audio>
                <?php elseif ($message['media_kind'] === 'video'): ?>
                    <video controls preload="metadata" playsinline src="<?= e($mediaUrl) ?>"></video>
                <?php else: ?>
                    <img loading="lazy" src="<?= e($mediaUrl) ?>" alt="<?= e($message['original_name'] ?: 'Shared image') ?>">
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (
            !empty($message['attachment_id'])
            && !in_array(
                $message['media_kind'],
                ['audio', 'video', 'image'],
                true
            )
        ): ?>
            <a class="communication-file-card" href="<?= e($downloadUrl) ?>">
                <span><?= e(strtoupper((string)$message['extension'])) ?></span>
                <span>
                    <strong><?= e($message['original_name']) ?></strong>
                    <small><?= e(format_bytes((int)$message['size_bytes'])) ?></small>
                </span>
            </a>
        <?php endif; ?>

        <?php if (
            !empty($message['transcript_id'])
            && $message['transcript_status'] === 'approved'
            && (
                $user['role'] === 'admin'
                || (int)$message['transcript_shared_with_client'] === 1
            )
            && !empty($message['transcript_reviewed_text'])
        ): ?>
            <details class="communication-transcript">
                <summary>Reviewed transcript</summary>
                <div><?= nl2br(e($message['transcript_reviewed_text'])) ?></div>
            </details>
        <?php endif; ?>

        <footer>
            <span><?= e(status_label($type)) ?></span>
            <?php if (!empty($message['attachment_id'])): ?>
                <a href="<?= e($downloadUrl) ?>">Download</a>
            <?php endif; ?>
        </footer>
    </article>
    <?php
}

function communication_render_page(
    array $user,
    bool $isAdmin
): void {
    if (!communication_enabled()) {
        ?>
        <section class="panel">
            <div class="empty-state">Communications are disabled in the portal configuration.</div>
        </section>
        <?php
        return;
    }

    $threads = communication_thread_list($user);
    $selectedThreadId = query_int('thread');
    $requestedProjectId = query_int('project');
    $requestedClientId = $isAdmin ? query_int('client') : (int)$user['id'];
    $showNewThreadForm = isset($_GET['new']);

    if ($selectedThreadId <= 0 && $requestedProjectId > 0) {
        foreach ($threads as $candidateThread) {
            if ((int)$candidateThread['project_id'] === $requestedProjectId) {
                $selectedThreadId = (int)$candidateThread['id'];
                break;
            }
        }

        if ($selectedThreadId <= 0) {
            $showNewThreadForm = true;
        }
    }

    if (
        $selectedThreadId <= 0
        && $threads
        && $requestedProjectId <= 0
        && !$showNewThreadForm
    ) {
        $selectedThreadId = (int)$threads[0]['id'];
    }

    $selectedThread = $selectedThreadId > 0
        ? communication_thread($selectedThreadId, $user)
        : null;

    if ($selectedThread) {
        communication_ensure_member(
            $selectedThreadId,
            (int)$user['id'],
            (string)$user['role']
        );
    }

    $messages = $selectedThread
        ? communication_messages(
            $selectedThreadId,
            $user,
            0,
            250
        )
        : [];

    if ($selectedThread) {
        $lastMessageId = $messages
            ? (int)end($messages)['id']
            : null;
        reset($messages);
        communication_mark_read(
            $selectedThreadId,
            $user,
            $lastMessageId
        );
    }

    $transcripts = $selectedThread
        ? communication_thread_transcripts(
            $selectedThreadId,
            $user
        )
        : [];

    $activeCall = $selectedThread
        ? communication_active_call_for_thread(
            $selectedThreadId,
            $user
        )
        : null;

    $clients = $isAdmin
        ? db()->query(
            'SELECT id, display_name, company
             FROM users
             WHERE role = "client"
               AND status = "active"
             ORDER BY COALESCE(company, display_name), display_name'
        )->fetchAll()
        : [];

    $projectsStatement = $isAdmin
        ? db()->query(
            'SELECT id, client_user_id, title
             FROM projects
             WHERE status <> "archived"
             ORDER BY updated_at DESC'
        )
        : db()->prepare(
            'SELECT id, client_user_id, title
             FROM projects
             WHERE client_user_id = :client_user_id
               AND status <> "archived"
             ORDER BY updated_at DESC'
        );

    if (!$isAdmin) {
        $projectsStatement->execute([
            'client_user_id' => $user['id'],
        ]);
    }

    $projects = $projectsStatement->fetchAll();

    $administrators = $isAdmin
        ? db()->query(
            'SELECT id, display_name
             FROM users
             WHERE role = "admin"
               AND status = "active"
             ORDER BY display_name'
        )->fetchAll()
        : [];

    $threadProjects = [];

    if ($selectedThread) {
        $statement = db()->prepare(
            'SELECT id, title
             FROM projects
             WHERE client_user_id = :client_user_id
               AND status <> "archived"
             ORDER BY updated_at DESC'
        );
        $statement->execute([
            'client_user_id' => $selectedThread['client_user_id'],
        ]);
        $threadProjects = $statement->fetchAll();
    }

    $lastMessageId = $messages
        ? (int)end($messages)['id']
        : 0;
    reset($messages);

    $config = communication_config();
    $iceServersJson = json_encode(
        communication_safe_ice_servers(),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?: '[]';
    ?>
    <section
        class="communications-app"
        data-communications-app
        data-role="<?= e($user['role']) ?>"
        data-user-id="<?= (int)$user['id'] ?>"
        data-user-name="<?= e($user['display_name']) ?>"
        data-thread-id="<?= $selectedThread ? (int)$selectedThread['id'] : 0 ?>"
        data-last-message-id="<?= $lastMessageId ?>"
        data-call-id="<?= $activeCall ? (int)$activeCall['id'] : 0 ?>"
        data-csrf-token="<?= e(csrf_token()) ?>"
        data-api-url="<?= e(app_url('portal/communications-api.php')) ?>"
        data-upload-url="<?= e(app_url('portal/communications-upload.php')) ?>"
        data-media-url="<?= e(app_url('portal/communication-media.php')) ?>"
        data-portal-url="<?= e(app_url(
            'portal/' .
            ($isAdmin ? 'admin.php' : 'client.php') .
            '?view=communications'
        )) ?>"
        data-poll-interval="<?= max(
            1200,
            min(10000, (int)($config['poll_interval_ms'] ?? 2500))
        ) ?>"
        data-recording-enabled="<?= (bool)($config['call_recording_enabled'] ?? false) ? '1' : '0' ?>"
        data-ice-servers="<?= e($iceServersJson) ?>"
    >
        <aside class="communications-sidebar">
            <div class="communications-sidebar-head">
                <div>
                    <span>Secure workspace</span>
                    <h2>Conversations</h2>
                </div>
                <button
                    class="button button-small"
                    type="button"
                    data-new-thread-toggle
                >New</button>
            </div>

            <form class="communications-new-thread" data-new-thread-form <?= $showNewThreadForm ? '' : 'hidden' ?>>
                <h3>Start a conversation</h3>

                <?php if ($isAdmin): ?>
                    <label class="field">
                        <span>Client</span>
                        <select name="client_user_id" data-thread-client required>
                            <option value="">Select client</option>
                            <?php foreach ($clients as $client): ?>
                                <option
                                    value="<?= (int)$client['id'] ?>"
                                    <?= $requestedClientId === (int)$client['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($client['company'] ?: $client['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>

                <label class="field">
                    <span>Project</span>
                    <select name="project_id" data-thread-project>
                        <option value="">General</option>
                        <?php foreach ($projects as $project): ?>
                            <option
                                value="<?= (int)$project['id'] ?>"
                                data-client-id="<?= (int)$project['client_user_id'] ?>"
                                <?= $requestedProjectId === (int)$project['id'] ? 'selected' : '' ?>
                            ><?= e($project['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span>Subject</span>
                    <input name="subject" maxlength="190" required>
                </label>

                <label class="field">
                    <span>Opening message</span>
                    <textarea name="body" maxlength="12000"></textarea>
                </label>

                <div class="form-footer">
                    <button class="button button-primary" type="submit">Create</button>
                    <button class="button" type="button" data-new-thread-cancel>Cancel</button>
                </div>
            </form>

            <div class="communications-thread-list">
                <?php foreach ($threads as $thread): ?>
                    <a
                        class="communications-thread <?= (int)$thread['id'] === $selectedThreadId ? 'active' : '' ?>"
                        href="?view=communications&thread=<?= (int)$thread['id'] ?>"
                    >
                        <span class="communications-thread-avatar">
                            <?= e(strtoupper(substr(
                                (string)($isAdmin
                                    ? $thread['client_name']
                                    : $thread['assigned_admin_name']
                                ),
                                0,
                                1
                            ))) ?>
                        </span>

                        <span class="communications-thread-copy">
                            <strong><?= e($thread['subject']) ?></strong>
                            <small>
                                <?= e(
                                    $isAdmin
                                        ? ($thread['client_company'] ?: $thread['client_name'])
                                        : ($thread['assigned_admin_name'] ?: 'North Mountain Media')
                                ) ?>
                                <?php if ($thread['project_title']): ?>
                                    · <?= e($thread['project_title']) ?>
                                <?php endif; ?>
                            </small>
                            <?php
                            $threadPreview = (string)($thread['latest_message'] ?: 'No messages yet.');
                            $threadPreview = strlen($threadPreview) > 85
                                ? substr($threadPreview, 0, 82) . '...'
                                : $threadPreview;
                            ?>
                            <em><?= e($threadPreview) ?></em>
                        </span>

                        <?php if ((int)$thread['unread_count'] > 0): ?>
                            <span class="communications-unread">
                                <?= (int)$thread['unread_count'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!$threads): ?>
                    <div class="communications-empty">
                        No conversations yet.
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <main class="communications-main">
            <?php if (!$selectedThread): ?>
                <div class="communications-empty-main">
                    <span>Communications Center</span>
                    <h2>Start a secure conversation</h2>
                    <p>Text messages, files, voice notes, calls, recordings, and reviewed transcripts stay together in one client thread.</p>
                    <button class="button button-primary" type="button" data-new-thread-toggle>Create conversation</button>
                </div>
            <?php else: ?>
                <header class="communications-header">
                    <div>
                        <span>
                            <?= e(
                                $isAdmin
                                    ? ($selectedThread['client_company'] ?: $selectedThread['client_name'])
                                    : ($selectedThread['assigned_admin_name'] ?: 'North Mountain Media')
                            ) ?>
                        </span>
                        <h2><?= e($selectedThread['subject']) ?></h2>
                        <small>
                            <?= e($selectedThread['project_title'] ?: 'General conversation') ?>
                            · <?= e(status_label($selectedThread['status'])) ?>
                            · <?= e(status_label($selectedThread['priority'])) ?> priority
                        </small>
                    </div>

                    <div class="communications-header-actions">
                        <?php if ($isAdmin): ?>
                            <button
                                class="button button-primary"
                                type="button"
                                data-call-start
                            >Audio call</button>
                        <?php else: ?>
                            <a
                                class="button button-primary"
                                href="?view=call-center"
                            >Call Us</a>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                            <button
                                class="button"
                                type="button"
                                data-thread-settings-toggle
                            >Settings</button>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if ($isAdmin): ?>
                    <form class="communications-thread-settings" data-thread-settings hidden>
                        <h3>Conversation settings</h3>

                        <div class="form-grid">
                            <label class="field">
                                <span>Status</span>
                                <select name="status">
                                    <?php foreach ([
                                        'open',
                                        'waiting_admin',
                                        'waiting_client',
                                        'resolved',
                                        'archived',
                                    ] as $status): ?>
                                        <option
                                            value="<?= e($status) ?>"
                                            <?= $selectedThread['status'] === $status ? 'selected' : '' ?>
                                        ><?= e(status_label($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field">
                                <span>Priority</span>
                                <select name="priority">
                                    <?php foreach ([
                                        'low',
                                        'normal',
                                        'high',
                                        'urgent',
                                    ] as $priority): ?>
                                        <option
                                            value="<?= e($priority) ?>"
                                            <?= $selectedThread['priority'] === $priority ? 'selected' : '' ?>
                                        ><?= e(status_label($priority)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field">
                                <span>Assigned administrator</span>
                                <select name="assigned_admin_user_id">
                                    <?php foreach ($administrators as $administrator): ?>
                                        <option
                                            value="<?= (int)$administrator['id'] ?>"
                                            <?= (int)$selectedThread['assigned_admin_user_id'] === (int)$administrator['id'] ? 'selected' : '' ?>
                                        ><?= e($administrator['display_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field">
                                <span>Project</span>
                                <select name="project_id">
                                    <option value="">General</option>
                                    <?php foreach ($threadProjects as $project): ?>
                                        <option
                                            value="<?= (int)$project['id'] ?>"
                                            <?= (int)$selectedThread['project_id'] === (int)$project['id'] ? 'selected' : '' ?>
                                        ><?= e($project['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="form-footer">
                            <button class="button button-primary" type="submit">Save settings</button>

                            <?php if ($selectedThread['crm_contact_id']): ?>
                                <a
                                    class="button"
                                    href="?view=crm&id=<?= (int)$selectedThread['crm_contact_id'] ?>"
                                >Open CRM contact</a>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>

                <section
                    class="communications-timeline"
                    data-message-list
                    aria-live="polite"
                >
                    <?php foreach ($messages as $message): ?>
                        <?php communication_render_message($message, $user); ?>
                    <?php endforeach; ?>

                    <?php if (!$messages): ?>
                        <div class="communications-empty" data-empty-messages>
                            No messages have been sent.
                        </div>
                    <?php endif; ?>
                </section>

                <section class="communications-composer">
                    <div class="communications-recording-preview" data-voice-preview hidden>
                        <div>
                            <span class="recording-dot"></span>
                            <strong data-voice-status>Voice recording</strong>
                            <small data-voice-duration>00:00</small>
                        </div>
                        <audio controls data-voice-audio hidden></audio>
                    </div>

                    <form data-message-form>
                        <textarea
                            name="body"
                            maxlength="12000"
                            placeholder="Write a message…"
                            aria-label="Message"
                        ></textarea>

                        <?php if ($isAdmin): ?>
                            <label class="communications-internal-note">
                                <input type="checkbox" name="internal_note" value="1">
                                <span>Internal CRM note</span>
                            </label>
                        <?php endif; ?>

                        <div class="communications-composer-actions">
                            <div>
                                <button
                                    class="communication-icon-button"
                                    type="button"
                                    data-attachment-trigger
                                    title="Attach a file"
                                >＋</button>

                                <button
                                    class="communication-icon-button"
                                    type="button"
                                    data-voice-record
                                    title="Record a voice message"
                                >●</button>

                                <input
                                    type="file"
                                    data-attachment-input
                                    hidden
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.md,.csv,.json,.xml,.zip,.jpg,.jpeg,.png,.gif,.webp,.mp3,.wav,.m4a,.aac,.ogg,.oga,.webm,.flac,.mp4,.m4v,.mov,.ogv"
                                >
                            </div>

                            <button class="button button-primary" type="submit">
                                Send
                            </button>
                        </div>
                    </form>
                </section>

                <?php if ($isAdmin && $transcripts): ?>
                    <section class="communications-transcripts">
                        <header>
                            <div>
                                <span>Private review</span>
                                <h2>Transcripts</h2>
                            </div>
                            <small><?= count($transcripts) ?> record<?= count($transcripts) === 1 ? '' : 's' ?></small>
                        </header>

                        <div class="communications-transcript-list">
                            <?php foreach ($transcripts as $transcript): ?>
                                <form
                                    class="communications-transcript-review"
                                    data-transcript-form
                                    data-transcript-id="<?= (int)$transcript['id'] ?>"
                                >
                                    <header>
                                        <div>
                                            <strong>
                                                <?= e(status_label($transcript['source_type'])) ?>
                                                <?php if ($transcript['original_name']): ?>
                                                    · <?= e($transcript['original_name']) ?>
                                                <?php endif; ?>
                                            </strong>
                                            <small>
                                                <?= e(status_label($transcript['status'])) ?>
                                                · <?= e(format_datetime($transcript['updated_at'])) ?>
                                            </small>
                                        </div>

                                        <span class="status status-<?= e(
                                            $transcript['status'] === 'approved'
                                                ? 'active'
                                                : 'planning'
                                        ) ?>">
                                            <?= e(status_label($transcript['status'])) ?>
                                        </span>
                                    </header>

                                    <label class="field">
                                        <span>Raw transcript or notes</span>
                                        <textarea name="raw_text"><?= e($transcript['raw_text'] ?? '') ?></textarea>
                                    </label>

                                    <label class="field">
                                        <span>Reviewed transcript</span>
                                        <textarea name="reviewed_text"><?= e($transcript['reviewed_text'] ?? '') ?></textarea>
                                    </label>

                                    <label class="checkbox-row">
                                        <input
                                            type="checkbox"
                                            name="shared_with_client"
                                            value="1"
                                            <?= (int)$transcript['shared_with_client'] === 1 ? 'checked' : '' ?>
                                        >
                                        <span>Share the approved transcript with the client</span>
                                    </label>

                                    <div class="form-footer">
                                        <button class="button" type="submit" data-transcript-save>
                                            Save review
                                        </button>
                                        <button class="button button-primary" type="submit" data-transcript-approve>
                                            Approve transcript
                                        </button>

                                        <?php if ($transcript['status'] === 'approved'): ?>
                                            <?php if ($transcript['knowledge_asset_id']): ?>
                                                <a
                                                    class="button"
                                                    href="?view=knowledge&asset=<?= (int)$transcript['knowledge_asset_id'] ?>"
                                                >Open Knowledge draft</a>
                                            <?php else: ?>
                                                <button
                                                    class="button"
                                                    type="button"
                                                    data-transcript-knowledge
                                                >Create Knowledge draft</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </main>

        <audio
            data-remote-audio
            autoplay
            playsinline
            hidden
        ></audio>

        <section class="communication-call-overlay" data-incoming-call hidden>
            <div class="communication-call-dialog">
                <span class="communication-call-pulse"></span>
                <small>Incoming audio call</small>
                <h2 data-incoming-caller>North Mountain Media</h2>
                <p data-incoming-subject>Secure portal conversation</p>
                <div>
                    <button class="button button-primary" type="button" data-call-accept>Accept</button>
                    <button class="button button-danger" type="button" data-call-decline>Decline</button>
                </div>
            </div>
        </section>

        <section class="communication-active-call" data-active-call hidden>
            <div class="communication-active-call-person">
                <span class="communication-call-pulse"></span>
                <div>
                    <small data-call-status>Connecting…</small>
                    <strong data-call-person>Audio call</strong>
                </div>
            </div>

            <time data-call-duration>00:00</time>

            <div class="communication-active-call-actions">
                <button type="button" data-call-mute>Mute</button>
                <button type="button" data-call-record-request>Request recording</button>
                <button class="end" type="button" data-call-end>End call</button>
            </div>
        </section>

        <section class="communication-consent-overlay" data-recording-consent hidden>
            <div class="communication-consent-dialog">
                <span>Recording request</span>
                <h2>Do you consent to recording this audio call?</h2>
                <p>The recording will begin only after both participants agree. A visible recording indicator will remain on screen.</p>
                <div>
                    <button class="button button-primary" type="button" data-recording-consent-grant>I consent</button>
                    <button class="button button-danger" type="button" data-recording-consent-decline>Do not record</button>
                </div>
            </div>
        </section>

        <div class="communication-toast" data-communications-toast hidden></div>
    </section>
    <script src="<?= e(app_url('assets/js/communications.js?v=20260726-communications-v18')) ?>"></script>
    <?php
}

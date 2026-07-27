<?php
declare(strict_types=1);

require_once __DIR__ . '/call-center.php';

function call_center_render_admin(array $user): void
{
    $filter = trim((string)($_GET['status'] ?? ''));
    $requests = call_center_admin_requests($filter !== '' ? $filter : null);
    $metrics = call_center_admin_metrics();
    $selectedId = query_int('request');

    if ($selectedId <= 0 && $requests) {
        $selectedId = (int)$requests[0]['id'];
    }

    $selected = $selectedId > 0 ? call_center_request($selectedId) : null;
    $events = $selected ? call_center_request_events($selectedId) : [];
    $mediaRecords = $selected
        ? call_center_request_media($selectedId)
        : [];
    $admins = db()->query(
        'SELECT id,display_name
         FROM users
         WHERE role="admin"
           AND status="active"
         ORDER BY display_name'
    )->fetchAll();

    $publicStatus = call_center_public_status();
    $publicMessage = call_center_public_status_message();
    $publicMaxRings = call_center_max_rings();
    $activeGreeting = call_center_active_greeting();

    $ringingStatement = db()->query(
        'SELECT id
         FROM call_center_requests
         WHERE source="public"
           AND request_type="live_call"
           AND status="ringing"
           AND expires_at>=UTC_TIMESTAMP()
         ORDER BY
            priority="urgent" DESC,
            priority="high" DESC,
            ringing_at ASC
         LIMIT 1'
    );
    $ringingId = (int)($ringingStatement->fetchColumn() ?: 0);
    $ringing = $ringingId > 0
        ? call_center_request($ringingId)
        : null;

    $activeStatement = db()->prepare(
        'SELECT id
         FROM call_center_requests
         WHERE source="public"
           AND request_type="live_call"
           AND status="accepted"
           AND assigned_admin_user_id=:admin_user_id
         ORDER BY answered_at DESC,id DESC
         LIMIT 1'
    );
    $activeStatement->execute([
        'admin_user_id' => $user['id'],
    ]);
    $activeRequestId = (int)($activeStatement->fetchColumn() ?: 0);
    $activeRequest = $activeRequestId > 0
        ? call_center_request($activeRequestId)
        : null;
    ?>
    <section
        class="call-center-app"
        data-call-center-app
        data-role="admin"
        data-user-id="<?= (int)$user['id'] ?>"
        data-csrf-token="<?= e(csrf_token()) ?>"
        data-api-url="<?= e(app_url('portal/call-center-api.php')) ?>"
        data-greeting-upload-url="<?= e(app_url('portal/call-center-greeting-upload.php')) ?>"
        data-notification-api="<?= e(app_url('portal/notifications-api.php')) ?>"
        data-selected-request-id="<?= $selected ? (int)$selected['id'] : 0 ?>"
        data-active-request-id="<?= $activeRequestId ?>"
        data-ice-servers="<?= e(json_encode(
            communication_safe_ice_servers(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '[]') ?>"
    >
        <div class="call-center-metrics">
            <article>
                <span>Today</span>
                <strong><?= (int)($metrics['today'] ?? 0) ?></strong>
                <small>New call activity</small>
            </article>
            <article>
                <span>Waiting</span>
                <strong><?= (int)($metrics['waiting'] ?? 0) ?></strong>
                <small><?= (int)($metrics['ringing'] ?? 0) ?> ringing now</small>
            </article>
            <article>
                <span>Completed</span>
                <strong><?= (int)($metrics['completed'] ?? 0) ?></strong>
                <small><?= (int)($metrics['active'] ?? 0) ?> connected now</small>
            </article>
            <article>
                <span>Missed</span>
                <strong><?= (int)($metrics['missed'] ?? 0) ?></strong>
                <small>Needs follow-up</small>
            </article>
            <article>
                <span>Voicemails</span>
                <strong><?= (int)($metrics['voicemail_total'] ?? 0) ?></strong>
                <small>Recorded messages</small>
            </article>
            <article>
                <span>Messages</span>
                <strong><?= (int)($metrics['message_total'] ?? 0) ?></strong>
                <small>Written and callback requests</small>
            </article>
            <article>
                <span>Avg. response</span>
                <strong><?= e(call_center_seconds_label(
                    isset($metrics['average_response_seconds'])
                        ? (int)$metrics['average_response_seconds']
                        : null
                )) ?></strong>
                <small>Request to response</small>
            </article>
            <article>
                <span>Avg. duration</span>
                <strong><?= e(call_center_seconds_label(
                    isset($metrics['average_duration_seconds'])
                        ? (int)$metrics['average_duration_seconds']
                        : null
                )) ?></strong>
                <small>Connected calls</small>
            </article>
        </div>

        <nav class="call-center-filters" aria-label="Call Center filters">
            <div class="call-center-filter-links">
                <?php
                $filters = [
                    '' => 'All',
                    'ringing' => 'Ringing',
                    'new' => 'New',
                    'queued' => 'Queued',
                    'scheduled' => 'Scheduled',
                    'voicemail' => 'Voicemail',
                    'missed' => 'Missed',
                    'completed' => 'Completed',
                    'resolved' => 'Resolved',
                ];
                ?>
                <?php foreach ($filters as $value => $label): ?>
                    <a
                        class="<?= $filter === $value ? 'active' : '' ?>"
                        href="?view=call-center<?= $value !== '' ? '&status=' . urlencode($value) : '' ?>"
                    ><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>

            <button
                class="call-center-settings-button"
                type="button"
                data-call-center-settings-open
                aria-label="Open Call Center settings"
                title="Call Center settings"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 8.25A3.75 3.75 0 1 0 12 15.75 3.75 3.75 0 0 0 12 8.25Zm9 3.75-2.08-.72a7.3 7.3 0 0 0-.58-1.4l.96-1.98-1.2-1.2-1.98.96a7.3 7.3 0 0 0-1.4-.58L14 5h-4l-.72 2.08a7.3 7.3 0 0 0-1.4.58L5.9 6.7 4.7 7.9l.96 1.98a7.3 7.3 0 0 0-.58 1.4L3 12v1.7l2.08.72c.14.49.34.96.58 1.4L4.7 17.8 5.9 19l1.98-.96c.44.24.91.44 1.4.58L10 20.7h4l.72-2.08c.49-.14.96-.34 1.4-.58l1.98.96 1.2-1.2-.96-1.98c.24-.44.44-.91.58-1.4L21 13.7V12Z"/>
                </svg>
            </button>
        </nav>

        <section
            class="call-center-settings-modal"
            data-call-center-settings-modal
            hidden
            role="dialog"
            aria-modal="true"
            aria-labelledby="call-center-settings-title"
        >
            <button
                class="call-center-settings-backdrop"
                type="button"
                data-call-center-settings-close
                aria-label="Close Call Center settings"
            ></button>

            <div class="call-center-settings-dialog">
                <header>
                    <div>
                        <span>Call Center</span>
                        <h2 id="call-center-settings-title">Settings</h2>
                        <p>Control public availability, ringing behavior, sound, and the voicemail greeting.</p>
                    </div>
                    <button
                        class="call-center-settings-close"
                        type="button"
                        data-call-center-settings-close
                        aria-label="Close settings"
                    >×</button>
                </header>

                <nav
                    class="call-center-settings-tabs"
                    role="tablist"
                    aria-label="Call Center settings sections"
                >
                    <button
                        class="active"
                        type="button"
                        role="tab"
                        id="call-center-tab-settings"
                        aria-selected="true"
                        aria-controls="call-center-panel-settings"
                        data-call-center-settings-tab="settings"
                    >Settings</button>

                    <button
                        type="button"
                        role="tab"
                        id="call-center-tab-voicemail"
                        aria-selected="false"
                        aria-controls="call-center-panel-voicemail"
                        data-call-center-settings-tab="voicemail"
                    >Voicemail</button>
                </nav>

                <div class="call-center-settings-panels">
                    <section
                        class="call-center-settings-pane active"
                        id="call-center-panel-settings"
                        role="tabpanel"
                        aria-labelledby="call-center-tab-settings"
                        data-call-center-settings-pane="settings"
                    >
                        <form class="call-center-line-status" data-line-status-form>
                            <label>
                                <span>Public line</span>
                                <select name="public_call_status">
                                    <?php foreach (['available', 'busy', 'offline'] as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $publicStatus === $status ? 'selected' : '' ?>>
                                            <?= e(status_label($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Max rings</span>
                                <input
                                    name="public_call_max_rings"
                                    type="number"
                                    min="1"
                                    max="12"
                                    value="<?= (int)$publicMaxRings ?>"
                                >
                                <small>
                                    Voicemail begins after approximately
                                    <?= (int)call_center_ring_cycle_seconds() ?>
                                    seconds per ring.
                                </small>
                            </label>

                            <label class="full">
                                <span>Status message</span>
                                <input
                                    name="public_call_message"
                                    value="<?= e($publicMessage) ?>"
                                    maxlength="500"
                                >
                            </label>

                            <div class="call-center-setting-actions">
                                <button
                                    class="button button-primary"
                                    type="submit"
                                >Save Call Center settings</button>

                                <button
                                    class="button"
                                    type="button"
                                    data-call-sound-toggle
                                >Enable call sounds</button>

                                <a
                                    class="button"
                                    href="<?= e(app_url('call-dave.php')) ?>"
                                    target="_blank"
                                    rel="noopener"
                                >Open public line</a>
                            </div>
                        </form>
                    </section>

                    <section
                        class="call-center-settings-pane"
                        id="call-center-panel-voicemail"
                        role="tabpanel"
                        aria-labelledby="call-center-tab-voicemail"
                        data-call-center-settings-pane="voicemail"
                        hidden
                    >
                        <section class="call-center-greeting-panel">
                            <header>
                                <div>
                                    <span>Voicemail greeting</span>
                                    <h3>Greeting played after max rings</h3>
                                    <p>
                                        Record, preview, replace, and activate
                                        the greeting callers hear before leaving voicemail.
                                    </p>
                                </div>

                                <?php if ($activeGreeting): ?>
                                    <span class="status status-active">Active</span>
                                <?php else: ?>
                                    <span class="status status-planning">Default prompt</span>
                                <?php endif; ?>
                            </header>

                            <?php if ($activeGreeting): ?>
                                <audio
                                    class="call-center-greeting-current"
                                    controls
                                    preload="metadata"
                                    src="<?= e(app_url('api/call-center-greeting.php?v=' . rawurlencode((string)$activeGreeting['updated_at']))) ?>"
                                ></audio>

                                <small>
                                    Recorded by <?= e($activeGreeting['admin_name']) ?>
                                    · <?= e(format_datetime($activeGreeting['updated_at'])) ?>
                                    · <?= e(call_center_seconds_label(
                                        $activeGreeting['duration_seconds'] !== null
                                            ? (int)round((float)$activeGreeting['duration_seconds'])
                                            : null
                                    )) ?>
                                </small>
                            <?php endif; ?>

                            <div
                                class="call-center-greeting-recorder"
                                data-greeting-recorder
                            >
                                <div>
                                    <strong>New greeting</strong>
                                    <small data-greeting-status>
                                        Select Record greeting when ready.
                                    </small>
                                </div>

                                <time data-greeting-duration>00:00</time>

                                <div
                                    class="call-center-greeting-meter"
                                    data-greeting-meter
                                >
                                    <span></span><span></span><span></span><span></span>
                                    <span></span><span></span><span></span><span></span>
                                </div>

                                <div class="call-center-greeting-actions">
                                    <button
                                        class="button button-primary"
                                        type="button"
                                        data-greeting-record
                                    >Record greeting</button>

                                    <button
                                        class="button"
                                        type="button"
                                        data-greeting-stop
                                        disabled
                                    >Stop</button>

                                    <button
                                        class="button"
                                        type="button"
                                        data-greeting-reset
                                        disabled
                                    >Record again</button>

                                    <button
                                        class="button button-primary"
                                        type="button"
                                        data-greeting-save
                                        disabled
                                    >Save active greeting</button>
                                </div>

                                <audio
                                    data-greeting-preview
                                    controls
                                    preload="metadata"
                                    hidden
                                ></audio>
                            </div>
                        </section>
                    </section>
                </div>
            </div>
        </section>

        <div class="call-center-layout">
            <section class="call-center-queue">
                <header>
                    <div>
                        <span>Queue and history</span>
                        <h3><?= count($requests) ?> records</h3>
                    </div>
                </header>

                <div class="call-center-request-list">
                    <?php foreach ($requests as $request): ?>
                        <?php
                        $callerName = $request['caller_name'] ?: 'Unknown caller';
                        $isSelected = (int)$request['id'] === $selectedId;
                        ?>
                        <a
                            class="call-center-request-card <?= $isSelected ? 'active' : '' ?> status-<?= e($request['status']) ?>"
                            href="?view=call-center&request=<?= (int)$request['id'] ?><?= $filter !== '' ? '&status=' . urlencode($filter) : '' ?>"
                        >
                            <span class="call-center-source">
                                <?= e($request['source'] === 'public' ? 'Public' : 'Client') ?>
                            </span>

                            <div>
                                <strong><?= e($callerName) ?></strong>
                                <small>
                                    <?= e(call_center_request_type_label($request)) ?>
                                    · <?= e($request['subject']) ?>
                                    <?php if ($request['caller_company']): ?>
                                        · <?= e($request['caller_company']) ?>
                                    <?php endif; ?>
                                </small>
                                <em>
                                    <?= e(format_datetime($request['requested_at'])) ?>
                                    · <?= e(status_label($request['status'])) ?>
                                </em>
                            </div>

                            <span class="status status-<?= e(
                                in_array($request['status'], ['ringing', 'missed'], true)
                                    ? 'on_hold'
                                    : (
                                        in_array($request['status'], ['completed', 'resolved'], true)
                                            ? 'active'
                                            : 'planning'
                                    )
                            ) ?>">
                                <?= e(status_label($request['status'])) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>

                    <?php if (!$requests): ?>
                        <div class="empty-state">No call activity matches this filter.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="call-center-detail">
                <?php if (!$selected): ?>
                    <div class="call-center-empty">
                        <span>Call Center</span>
                        <h3>No call request selected</h3>
                        <p>New public calls and client callback requests will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $callerName = $selected['guest_name']
                        ?: $selected['client_name']
                        ?: $selected['contact_name']
                        ?: 'Unknown caller';
                    $callerEmail = $selected['guest_email']
                        ?: $selected['client_email']
                        ?: $selected['contact_email'];
                    $callerPhone = $selected['guest_phone']
                        ?: $selected['client_phone']
                        ?: $selected['contact_phone'];
                    $callerCompany = $selected['guest_company']
                        ?: $selected['client_company']
                        ?: $selected['contact_company'];
                    ?>
                    <header class="call-center-detail-header">
                        <div>
                            <span><?= e(status_label($selected['source'])) ?> <?= e(call_center_request_type_label($selected)) ?></span>
                            <h3><?= e($callerName) ?></h3>
                            <p><?= e($selected['subject']) ?></p>
                        </div>

                        <span class="status status-<?= e(
                            in_array($selected['status'], ['ringing', 'missed'], true)
                                ? 'on_hold'
                                : (
                                    in_array($selected['status'], ['completed', 'resolved'], true)
                                        ? 'active'
                                        : 'planning'
                                )
                        ) ?>">
                            <?= e(status_label($selected['status'])) ?>
                        </span>
                    </header>

                    <div class="call-center-contact-grid">
                        <article>
                            <span>Email</span>
                            <strong><?= e($callerEmail ?: 'Not provided') ?></strong>
                        </article>
                        <article>
                            <span>Phone</span>
                            <strong><?= e($callerPhone ?: 'Not provided') ?></strong>
                        </article>
                        <article>
                            <span>Company</span>
                            <strong><?= e($callerCompany ?: 'Not provided') ?></strong>
                        </article>
                        <article>
                            <span>CRM stage</span>
                            <strong><?= e(status_label($selected['contact_stage'] ?: 'lead')) ?></strong>
                        </article>
                    </div>

                    <section class="call-center-message">
                        <span>Caller message</span>
                        <p><?= nl2br(e($selected['message'] ?: 'No message was provided.')) ?></p>
                    </section>

                    <div class="call-center-time-grid">
                        <article><span>Requested</span><strong><?= e(format_datetime($selected['requested_at'])) ?></strong></article>
                        <article><span>Preferred</span><strong><?= e(format_datetime($selected['preferred_at'])) ?></strong></article>
                        <article><span>First response</span><strong><?= e(format_datetime($selected['first_response_at'])) ?></strong></article>
                        <article><span>Answered</span><strong><?= e(format_datetime($selected['answered_at'])) ?></strong></article>
                        <article><span>Ended</span><strong><?= e(format_datetime($selected['ended_at'])) ?></strong></article>
                        <article><span>Duration</span><strong><?= e(call_center_seconds_label(
                            $selected['duration_seconds'] !== null
                                ? (int)$selected['duration_seconds']
                                : null
                        )) ?></strong></article>
                        <article><span>Contact attempts</span><strong><?= (int)$selected['attempt_count'] ?></strong></article>
                        <article><span>Last contact</span><strong><?= e(format_datetime($selected['last_contact_at'])) ?></strong></article>
                        <article><span>Disposition</span><strong><?= e(status_label($selected['disposition'])) ?></strong></article>
                        <article><span>Assigned to</span><strong><?= e($selected['assigned_admin_name'] ?: 'Unassigned') ?></strong></article>
                    </div>

                    <section class="call-center-contact-stats">
                        <header><span>Contact call management</span><h3>Relationship history</h3></header>
                        <div>
                            <article><span>Requests</span><strong><?= (int)($selected['contact_total_requests'] ?? 0) ?></strong></article>
                            <article><span>Calls</span><strong><?= (int)($selected['contact_total_calls'] ?? 0) ?></strong></article>
                            <article><span>Completed</span><strong><?= (int)($selected['contact_completed_calls'] ?? 0) ?></strong></article>
                            <article><span>Missed</span><strong><?= (int)($selected['contact_missed_calls'] ?? 0) ?></strong></article>
                            <article><span>Declined</span><strong><?= (int)($selected['contact_declined_calls'] ?? 0) ?></strong></article>
                            <article><span>Voicemails</span><strong><?= (int)($selected['contact_total_voicemails'] ?? 0) ?></strong></article>
                            <article><span>Messages</span><strong><?= (int)($selected['contact_total_messages'] ?? 0) ?></strong></article>
                            <article><span>Total talk time</span><strong><?= e(call_center_seconds_label(
                                isset($selected['contact_total_duration_seconds'])
                                    ? (int)$selected['contact_total_duration_seconds']
                                    : null
                            )) ?></strong></article>
                        </div>
                    </section>

                    <?php if (
                        $selected['source'] === 'public'
                        && $selected['request_type'] === 'live_call'
                        && $selected['status'] === 'ringing'
                    ): ?>
                        <div class="call-center-live-actions">
                            <button
                                class="button button-primary"
                                type="button"
                                data-public-call-accept
                                data-request-id="<?= (int)$selected['id'] ?>"
                            >Answer public call</button>

                            <button
                                class="button button-danger"
                                type="button"
                                data-public-call-decline
                                data-request-id="<?= (int)$selected['id'] ?>"
                            >Decline</button>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        !empty($selected['communication_call_id'])
                        && !empty($selected['communication_thread_id'])
                        && in_array(
                            $selected['status'],
                            ['ringing', 'accepted'],
                            true
                        )
                    ): ?>
                        <div class="call-center-live-actions">
                            <a
                                class="button button-primary"
                                href="?view=communications&thread=<?= (int)$selected['communication_thread_id'] ?>"
                            >
                                <?= $selected['status'] === 'ringing'
                                    ? 'Open conversation to answer'
                                    : 'Open active portal call' ?>
                            </a>
                            <span>
                                Authenticated client-portal audio call
                            </span>
                        </div>
                    <?php endif; ?>


                    <?php if ($mediaRecords): ?>
                        <section class="call-center-media-panel">
                            <header>
                                <div>
                                    <span>Voice media</span>
                                    <h3>Voicemail and recordings</h3>
                                </div>
                                <small><?= count($mediaRecords) ?> file<?= count($mediaRecords) === 1 ? '' : 's' ?></small>
                            </header>

                            <?php foreach ($mediaRecords as $media): ?>
                                <article class="call-center-media-card">
                                    <div class="call-center-media-player">
                                        <div>
                                            <strong><?= e(status_label($media['media_type'])) ?></strong>
                                            <small>
                                                <?= e(format_datetime($media['created_at'])) ?>
                                                · <?= e(format_bytes((int)$media['size_bytes'])) ?>
                                                <?php if ($media['duration_seconds'] !== null): ?>
                                                    · <?= e(call_center_seconds_label((int)round((float)$media['duration_seconds']))) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>

                                        <audio
                                            controls
                                            preload="metadata"
                                            src="<?= e(app_url('portal/call-center-media.php?id=' . (int)$media['id'])) ?>"
                                        ></audio>

                                        <a
                                            class="button button-small"
                                            href="<?= e(app_url('portal/call-center-media.php?id=' . (int)$media['id'] . '&download=1')) ?>"
                                        >Download</a>
                                    </div>

                                    <form
                                        class="call-center-transcript-form"
                                        data-media-transcript-form
                                    >
                                        <input type="hidden" name="request_id" value="<?= (int)$selected['id'] ?>">
                                        <input type="hidden" name="media_id" value="<?= (int)$media['id'] ?>">

                                        <div class="call-center-transcript-status">
                                            <span>Transcript</span>
                                            <strong><?= e(status_label($media['transcript_status'])) ?></strong>
                                            <small>
                                                Manual review works now. The future private HomeServer worker can populate the raw transcript field.
                                            </small>
                                        </div>

                                        <label class="field full">
                                            <span>Raw/local transcript</span>
                                            <textarea
                                                name="raw_transcript_text"
                                                placeholder="Paste a local transcription result here when available."
                                            ><?= e($media['raw_transcript_text'] ?? '') ?></textarea>
                                        </label>

                                        <label class="field full">
                                            <span>Reviewed transcript</span>
                                            <textarea
                                                name="reviewed_transcript_text"
                                                placeholder="Correct names, punctuation, and meaning before approval."
                                            ><?= e($media['reviewed_transcript_text'] ?? '') ?></textarea>
                                        </label>

                                        <div class="form-footer">
                                            <button
                                                class="button"
                                                type="submit"
                                                name="transcript_status"
                                                value="review"
                                            >Save review</button>
                                            <button
                                                class="button button-primary"
                                                type="submit"
                                                name="transcript_status"
                                                value="approved"
                                            >Approve transcript</button>
                                        </div>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>

                    <form class="call-center-management-form" data-call-management-form>
                        <input type="hidden" name="request_id" value="<?= (int)$selected['id'] ?>">

                        <div class="form-grid">
                            <label class="field">
                                <span>Status</span>
                                <select name="status">
                                    <?php foreach ([
                                        'new','queued','scheduled','ringing','accepted',
                                        'completed','missed','declined','cancelled',
                                        'failed','voicemail','resolved','spam'
                                    ] as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $selected['status'] === $status ? 'selected' : '' ?>>
                                            <?= e(status_label($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field">
                                <span>Disposition</span>
                                <select name="disposition">
                                    <?php foreach ([
                                        'unassigned','connected','callback_scheduled',
                                        'left_message','no_answer','not_available',
                                        'declined','resolved','spam'
                                    ] as $disposition): ?>
                                        <option value="<?= e($disposition) ?>" <?= $selected['disposition'] === $disposition ? 'selected' : '' ?>>
                                            <?= e(status_label($disposition)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field">
                                <span>Priority</span>
                                <select name="priority">
                                    <?php foreach (['low','normal','high','urgent'] as $priority): ?>
                                        <option value="<?= e($priority) ?>" <?= $selected['priority'] === $priority ? 'selected' : '' ?>>
                                            <?= e(status_label($priority)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field">
                                <span>Assigned administrator</span>
                                <select name="assigned_admin_user_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($admins as $admin): ?>
                                        <option value="<?= (int)$admin['id'] ?>" <?= (int)$selected['assigned_admin_user_id'] === (int)$admin['id'] ? 'selected' : '' ?>>
                                            <?= e($admin['display_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field full">
                                <span>Preferred callback time</span>
                                <input
                                    type="datetime-local"
                                    name="preferred_at"
                                    value="<?= e($selected['preferred_at'] ? date('Y-m-d\TH:i', strtotime($selected['preferred_at'])) : '') ?>"
                                >
                            </label>

                            <label class="field full">
                                <span>Admin notes</span>
                                <textarea name="admin_notes"><?= e($selected['admin_notes'] ?? '') ?></textarea>
                            </label>

                            <label class="field full">
                                <span>Call transcript / summary</span>
                                <textarea name="transcript_text" style="min-height:180px"><?= e($selected['transcript_text'] ?? '') ?></textarea>
                            </label>
                        </div>

                        <div class="form-footer">
                            <button class="button button-primary" type="submit">Save call record</button>
                            <button
                                class="button"
                                type="button"
                                data-call-log-attempt
                                data-request-id="<?= (int)$selected['id'] ?>"
                            >Log contact attempt</button>

                            <?php if ($selected['crm_contact_id']): ?>
                                <a class="button" href="?view=crm&id=<?= (int)$selected['crm_contact_id'] ?>">Open CRM contact</a>
                            <?php endif; ?>

                            <?php if ($selected['communication_thread_id']): ?>
                                <a class="button" href="?view=communications&thread=<?= (int)$selected['communication_thread_id'] ?>">Open conversation</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <section class="call-center-event-feed">
                        <header><span>Audit trail</span><h3>Call events</h3></header>

                        <?php foreach ($events as $event): ?>
                            <article>
                                <span></span>
                                <div>
                                    <strong><?= e(status_label($event['event_type'])) ?></strong>
                                    <?php if ($event['notes']): ?><p><?= nl2br(e($event['notes'])) ?></p><?php endif; ?>
                                    <small>
                                        <?= e($event['actor_name'] ?: 'System') ?>
                                        · <?= e(format_datetime($event['event_at'])) ?>
                                    </small>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php if (!$events): ?>
                            <div class="empty-state">No call events have been recorded.</div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </section>
        </div>

        <audio data-public-remote-audio autoplay playsinline hidden></audio>

        <section class="call-center-incoming" data-public-incoming-call <?= $ringing && !$activeRequest ? '' : 'hidden' ?>>
            <div>
                <span class="communication-call-pulse"></span>
                <small>Public browser call</small>
                <h2 data-public-caller><?= e($ringing['caller_name'] ?? 'Website visitor') ?></h2>
                <p data-public-subject><?= e($ringing['subject'] ?? 'Incoming public call') ?></p>

                <section
                    class="call-center-microphone-status"
                    data-admin-microphone-check
                    data-state="checking"
                >
                    <span aria-hidden="true"></span>
                    <div>
                        <strong>Administrator microphone</strong>
                        <small data-admin-microphone-diagnostic>
                            Checking browser microphone access…
                        </small>
                    </div>
                    <button type="button" data-admin-microphone-test>
                        Test microphone
                    </button>
                </section>

                <button
                    class="call-center-sound-unlock"
                    type="button"
                    data-call-sound-toggle
                >
                    Enable audible ringtone
                </button>

                <div class="call-center-incoming-actions">
                    <button
                        class="button button-primary"
                        type="button"
                        data-public-call-accept
                        data-request-id="<?= $ringing ? (int)$ringing['id'] : 0 ?>"
                    >Answer</button>
                    <button
                        class="button button-danger"
                        type="button"
                        data-public-call-decline
                        data-request-id="<?= $ringing ? (int)$ringing['id'] : 0 ?>"
                    >Decline</button>
                </div>
            </div>
        </section>

        <section class="communication-active-call" data-public-active-call <?= $activeRequest ? '' : 'hidden' ?>>
            <div class="communication-active-call-person">
                <span class="communication-call-pulse"></span>
                <div>
                    <small data-public-call-status>Connecting…</small>
                    <strong data-public-call-person><?= e($activeRequest['guest_name'] ?? 'Public caller') ?></strong>
                </div>
            </div>
            <time data-public-call-duration>00:00</time>
            <div class="communication-active-call-actions">
                <button type="button" data-public-call-mute>Mute</button>
                <button class="end" type="button" data-public-call-end>End call</button>
            </div>
        </section>

        <div class="communication-toast" data-call-center-toast hidden></div>
    </section>

    <script src="<?= e(app_url('assets/js/call-center.js?v=20260727-site-controls-landing-v60')) ?>"></script>
    <?php
}

function call_center_render_client(array $user): void
{
    $requests = call_center_client_requests((int)$user['id']);
    $latestThreadStatement = db()->prepare(
        'SELECT id
         FROM communication_threads
         WHERE client_user_id=:client_user_id
           AND status<>"archived"
         ORDER BY COALESCE(last_message_at,created_at) DESC
         LIMIT 1'
    );
    $latestThreadStatement->execute(['client_user_id' => $user['id']]);
    $latestThreadId = (int)($latestThreadStatement->fetchColumn() ?: 0);
    ?>
    <section
        class="call-center-app client-call-center"
        data-call-center-app
        data-role="client"
        data-user-id="<?= (int)$user['id'] ?>"
        data-csrf-token="<?= e(csrf_token()) ?>"
        data-api-url="<?= e(app_url('portal/call-center-api.php')) ?>"
    >
        <header class="client-call-hero">
            <div>
                <span>Client assistance</span>
                <h2>Call Us</h2>
                <p>Send Dave a call request with the topic, urgency, and best time to reach you. The request becomes part of your secure communication and project history.</p>
            </div>

            <button class="button button-primary" type="button" data-client-call-prompt>
                Request a call
            </button>
        </header>

        <form class="client-call-request-form" data-client-call-form hidden>
            <header>
                <div>
                    <span>Call request</span>
                    <h3>What should Dave know before calling?</h3>
                </div>
                <button type="button" data-client-call-close aria-label="Close call request">×</button>
            </header>

            <div class="form-grid">
                <label class="field full">
                    <span>Topic</span>
                    <input
                        name="subject"
                        maxlength="190"
                        placeholder="Project review, question, support, planning…"
                        required
                    >
                </label>

                <label class="field full">
                    <span>Message</span>
                    <textarea
                        name="message"
                        maxlength="8000"
                        placeholder="Describe what you want to discuss so Dave can prepare."
                        required
                    ></textarea>
                </label>

                <label class="field">
                    <span>Best time</span>
                    <input type="datetime-local" name="preferred_at">
                </label>

                <label class="field">
                    <span>Priority</span>
                    <select name="priority">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                        <option value="low">Low</option>
                    </select>
                </label>
            </div>

            <div class="form-footer">
                <button class="button button-primary" type="submit">Send call request</button>
                <button class="button" type="button" data-client-call-close>Cancel</button>
            </div>
        </form>

        <div class="client-call-grid">
            <section class="panel">
                <header class="panel-header">
                    <h2>Call request history</h2>
                    <span><?= count($requests) ?> records</span>
                </header>

                <?php if (!$requests): ?>
                    <div class="empty-state">No call requests have been submitted.</div>
                <?php else: ?>
                    <div class="client-call-history">
                        <?php foreach ($requests as $request): ?>
                            <article>
                                <header>
                                    <div>
                                        <strong><?= e($request['subject']) ?></strong>
                                        <small><?= e(format_datetime($request['requested_at'])) ?></small>
                                    </div>
                                    <span class="status status-<?= e(
                                        in_array($request['status'], ['completed', 'resolved'], true)
                                            ? 'active'
                                            : (
                                                in_array($request['status'], ['missed', 'declined'], true)
                                                    ? 'on_hold'
                                                    : 'planning'
                                            )
                                    ) ?>">
                                        <?= e(status_label($request['status'])) ?>
                                    </span>
                                </header>

                                <p><?= nl2br(e($request['message'] ?: 'No message provided.')) ?></p>

                                <div class="card-meta">
                                    <span>Preferred <?= e(format_datetime($request['preferred_at'])) ?></span>
                                    <span><?= e(status_label($request['priority'])) ?> priority</span>
                                    <span><?= e(status_label($request['disposition'])) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="stack">
                <section class="panel">
                    <header class="panel-header"><h2>Other ways to connect</h2></header>
                    <div class="panel-body stack">
                        <?php if ($latestThreadId > 0): ?>
                            <a class="button button-primary" href="?view=communications&thread=<?= $latestThreadId ?>">Open secure conversation</a>
                        <?php else: ?>
                            <a class="button button-primary" href="?view=communications&new=1">Start secure conversation</a>
                        <?php endif; ?>
                        <?php $publicEmail = public_contact_email(); ?>
                        <?php if ($publicEmail !== ''): ?>
                            <a class="button" href="mailto:<?= e($publicEmail) ?>">Email Dave</a>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="panel">
                    <header class="panel-header"><h2>How requests work</h2></header>
                    <div class="panel-body">
                        <ol class="call-center-steps">
                            <li>Your request is saved to the Call Center and secure Communications history.</li>
                            <li>Dave receives a notification with the topic and requested time.</li>
                            <li>Status, callback attempts, notes, and outcomes remain available here.</li>
                        </ol>
                    </div>
                </section>
            </aside>
        </div>

        <div class="communication-toast" data-call-center-toast hidden></div>
    </section>

    <script src="<?= e(app_url('assets/js/call-center.js?v=20260727-site-controls-landing-v60')) ?>"></script>
    <?php
}

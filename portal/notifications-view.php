<?php
declare(strict_types=1);

require_once __DIR__ . '/notifications.php';

function notification_render_feed(array $user): void
{
    $notifications = notification_recent((int)$user['id'], 100, false);
    $unread = notification_unread_count((int)$user['id']);
    ?>
    <section class="notification-page">
        <header class="notification-page-header">
            <div>
                <span>Activity feed</span>
                <h2>Notifications</h2>
                <p>Calls, messages, contact requests, transcripts, projects, and system activity.</p>
            </div>

            <?php if ($unread > 0): ?>
                <form method="post" data-notification-mark-all>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_all_notifications_read">
                    <button class="button" type="submit">Mark all read</button>
                </form>
            <?php endif; ?>
        </header>

        <div class="notification-feed">
            <?php foreach ($notifications as $notification): ?>
                <article
                    class="notification-feed-item <?= (int)$notification['is_read'] === 0 ? 'unread' : '' ?>"
                    data-notification-id="<?= (int)$notification['id'] ?>"
                >
                    <span class="notification-feed-icon" aria-hidden="true">
                        <?= e(notification_category_icon((string)$notification['category'])) ?>
                    </span>

                    <div class="notification-feed-copy">
                        <header>
                            <strong><?= e($notification['title']) ?></strong>
                            <time><?= e(format_datetime($notification['created_at'])) ?></time>
                        </header>

                        <?php if ($notification['body']): ?>
                            <p><?= nl2br(e($notification['body'])) ?></p>
                        <?php endif; ?>

                        <div>
                            <span class="status status-<?= e(
                                in_array($notification['priority'], ['high', 'urgent'], true)
                                    ? 'on_hold'
                                    : 'planning'
                            ) ?>">
                                <?= e(status_label($notification['category'])) ?>
                            </span>

                            <?php if ((int)$notification['is_read'] === 0): ?>
                                <span class="notification-unread-label">Unread</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="notification-feed-actions">
                        <a
                            class="button button-small"
                            href="<?= e(notification_portal_link($user, $notification['link_url'])) ?>"
                            data-notification-open
                        >Open</a>

                        <?php if ((int)$notification['is_read'] === 0): ?>
                            <button
                                class="button button-small"
                                type="button"
                                data-notification-read
                            >Mark read</button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (!$notifications): ?>
                <div class="empty-state">No notifications have been created.</div>
            <?php endif; ?>
        </div>
    </section>

    <script src="<?= e(app_url('assets/js/notifications.js?v=20260727-site-controls-landing-v60')) ?>"></script>
    <?php
}

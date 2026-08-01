<?php
declare(strict_types=1);

/* North Mountain Media build: 20260731-portal-shell-v66Q7 */

function portal_header(string $title, string $active, array $user): void
{
    $GLOBALS['nmm_portal_active_view'] = $active;
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $script = $isAdmin ? 'admin.php' : 'client.php';
    $flashes = pull_flashes();

    require_once __DIR__ . '/notifications.php';

    $notificationApiUrl = app_url('portal/notifications-api.php');
    $callCenterApiUrl = $isAdmin ? app_url('portal/call-center-api.php') : '';
    $adminAssistantApiUrl = $isAdmin ? app_url('portal/admin-assistant-api.php') : '';
    $notificationCount = notification_unread_count((int)$user['id']);
    $recentNotifications = notification_recent((int)$user['id'], 6, false);
    $notificationsUrl = app_url('portal/' . $script . '?view=notifications');

    // The default authenticated shell always uses this one shared sidebar template.
    $portalSidebarGroups = portal_navigation_groups($user);
    $portalSidebarHomeUrl = app_url('portal/' . $script);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?=e(csrf_token())?>">
    <title><?=e($title)?> — <?=e(setting('site_name', 'North Mountain Media'))?></title>
    <link rel="stylesheet" href="<?=e(app_url('assets/css/portal.css?v=20260731-v66Q7'))?>">
    <link rel="stylesheet" href="<?=e(app_url('assets/css/publishing-center-v66q.css?v=20260731-v66Q7'))?>">
    <link rel="stylesheet" href="<?=e(app_url('assets/css/portal-shell-v66q7.css?v=20260731-v66Q7'))?>">
    <?php if ($active === 'feeds'): ?><link rel="stylesheet" href="<?=e(app_url('assets/css/feed-reader.css?v=20260728-content-controls-v62.1'))?>"><?php endif; ?>
    <?php if ($active === 'inbox'): ?><link rel="stylesheet" href="<?=e(app_url('assets/css/unified-inbox.css?v=20260730-v66D'))?>"><?php endif; ?>
    <?php if ($active === 'syndication'): ?><link rel="stylesheet" href="<?=e(app_url('assets/css/syndication-admin.css?v=20260730-v66E'))?>"><?php endif; ?>
    <?php if ($active === 'federation'): ?><link rel="stylesheet" href="<?=e(app_url('assets/css/activitypub-admin.css?v=20260730-v66F'))?>"><?php endif; ?>
</head>
<body
    class="portal-body"
    data-portal-role="<?=e((string)$user['role'])?>"
    data-notification-api="<?=e($notificationApiUrl)?>"
    data-call-center-api="<?=e($callCenterApiUrl)?>"
    data-admin-assistant-api="<?=e($adminAssistantApiUrl)?>"
    data-portal-active="<?=e($active)?>"
    data-portal-modal="<?=isset($_GET['modal']) && $_GET['modal'] === '1' ? '1' : '0'?>"
>
<div class="portal-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <button
        class="portal-sidebar-backdrop"
        data-sidebar-close
        type="button"
        aria-label="Close navigation"
    ></button>

    <main class="portal-main">
        <header class="portal-topbar">
            <button class="portal-menu-button" data-sidebar-open type="button" aria-label="Open navigation">
                <span></span><span></span><span></span>
            </button>
            <?php nmm_render_mobile_brand(); ?>

            <div class="portal-title-block">
                <span><?=e($isAdmin ? 'North Mountain Media' : 'Client workspace')?></span>
                <h1><?=e($title)?></h1>
            </div>

            <div class="portal-header-user">
                <div class="portal-notification-wrap">
                    <button
                        class="portal-notification-button"
                        type="button"
                        data-notification-toggle
                        aria-expanded="false"
                        aria-label="Open notifications"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M10 21h4"></path>
                        </svg>
                        <span data-notification-count <?=$notificationCount > 0 ? '' : 'hidden'?>><?=$notificationCount?></span>
                    </button>

                    <section class="portal-notification-menu" data-notification-menu hidden>
                        <header>
                            <div><span>Activity feed</span><strong>Notifications</strong></div>
                            <a href="<?=e($notificationsUrl)?>">View all</a>
                        </header>
                        <div data-notification-preview-list>
                            <?php foreach ($recentNotifications as $notification): ?>
                                <a
                                    class="<?=(int)$notification['is_read'] === 0 ? 'unread' : ''?>"
                                    href="<?=e(notification_portal_link($user, $notification['link_url']))?>"
                                    data-notification-preview
                                    data-notification-id="<?=(int)$notification['id']?>"
                                >
                                    <span><?=e(notification_category_icon((string)$notification['category']))?></span>
                                    <span>
                                        <strong><?=e($notification['title'])?></strong>
                                        <small><?=e(format_datetime($notification['created_at']))?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                            <?php if (!$recentNotifications): ?>
                                <div class="portal-notification-empty">No notifications yet.</div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <?php require __DIR__ . '/account-menu.php'; ?>
            </div>
        </header>

        <div class="portal-content">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div>
            <?php endforeach; ?>
    <?php
}

function portal_footer(): void
{
    $footerUser = current_user();
    $isAdmin = $footerUser && ($footerUser['role'] ?? '') === 'admin';
    $active = (string)($GLOBALS['nmm_portal_active_view'] ?? '');
    ?>
        </div>

        <?php if ($isAdmin): ?>
            <section class="admin-assistant-loading" data-admin-assistant-loading aria-live="polite" hidden>
                <div class="admin-assistant-loading-orb" aria-hidden="true"><span></span><span></span><span></span></div>
                <strong>Querying North Mountain data</strong>
                <small>Reviewing calls, communications, CRM, and current work.</small>
            </section>

            <section class="admin-assistant-chat" data-admin-assistant-chat aria-label="North Mountain administrator assistant" hidden>
                <header>
                    <div><span>North Mountain Admin Assistant</span><strong>Operations chat</strong></div>
                    <div>
                        <button type="button" data-admin-chat-new>New chat</button>
                        <button type="button" data-admin-chat-close aria-label="Close assistant">×</button>
                    </div>
                </header>
                <div class="admin-assistant-messages" data-admin-assistant-messages aria-live="polite"></div>
            </section>

            <section class="admin-assistant-footer" data-admin-assistant-footer>
                <button
                    class="admin-assistant-launcher-backdrop"
                    type="button"
                    data-admin-launcher-backdrop
                    aria-label="Close administrator tools"
                    hidden
                ></button>

                <div
                    class="admin-assistant-quick-menu admin-assistant-launcher-modal"
                    data-admin-assistant-quick-menu
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="admin-launcher-title"
                    hidden
                >
                    <header class="admin-assistant-launcher-header">
                        <div><span>Administrator tools</span><strong id="admin-launcher-title">Quick access</strong></div>
                        <button type="button" data-admin-quick-close aria-label="Close administrator tools">×</button>
                    </header>

                    <nav class="admin-assistant-launcher-tabs" role="tablist" aria-label="Administrator tool categories">
                        <button
                            type="button"
                            id="admin-launcher-tab-queries"
                            role="tab"
                            aria-selected="true"
                            aria-controls="admin-launcher-panel-queries"
                            data-admin-launcher-tab="queries"
                            class="is-active"
                        >Data queries</button>
                        <button
                            type="button"
                            id="admin-launcher-tab-publishing"
                            role="tab"
                            aria-selected="false"
                            aria-controls="admin-launcher-panel-publishing"
                            data-admin-launcher-tab="publishing"
                        >Publishing</button>
                        <button
                            type="button"
                            id="admin-launcher-tab-actions"
                            role="tab"
                            aria-selected="false"
                            aria-controls="admin-launcher-panel-actions"
                            data-admin-launcher-tab="actions"
                        >Actions</button>
                    </nav>

                    <div class="admin-assistant-launcher-body">
                        <section
                            class="admin-assistant-launcher-panel is-active"
                            id="admin-launcher-panel-queries"
                            role="tabpanel"
                            aria-labelledby="admin-launcher-tab-queries"
                            data-admin-launcher-panel="queries"
                        >
                            <div class="admin-assistant-launcher-intro">
                                <strong>Quick data queries</strong>
                                <span>Ask the assistant to review live portal records.</span>
                            </div>
                            <div class="admin-assistant-query-grid">
                                <button type="button" data-admin-quick-prompt="Most recent call history"><strong>Recent calls</strong><small>Review the latest call history</small></button>
                                <button type="button" data-admin-quick-prompt="Missed messages"><strong>Missed messages</strong><small>Find calls and messages needing attention</small></button>
                                <button type="button" data-admin-quick-prompt="CRM contacts needing attention"><strong>CRM attention</strong><small>Surface contacts needing follow-up</small></button>
                                <?php if (nmm_module_enabled('music_library')): ?>
                                    <button type="button" data-admin-quick-prompt="Music Library"><strong>Music Library</strong><small>Review catalog and listening activity</small></button>
                                <?php endif; ?>
                                <button type="button" data-admin-quick-prompt="Visitor activity"><strong>Visitor activity</strong><small>Inspect recent website engagement</small></button>
                                <?php if (nmm_module_enabled('clients')): ?>
                                    <button type="button" data-admin-quick-prompt="Unread communications"><strong>Communications</strong><small>Show unread conversations</small></button>
                                    <button type="button" data-admin-quick-prompt="Open projects"><strong>Open projects</strong><small>Summarize current project work</small></button>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section
                            class="admin-assistant-launcher-panel"
                            id="admin-launcher-panel-publishing"
                            role="tabpanel"
                            aria-labelledby="admin-launcher-tab-publishing"
                            data-admin-launcher-panel="publishing"
                            hidden
                        >
                            <div class="admin-assistant-launcher-intro">
                                <strong>Create and publish</strong>
                                <span>Only post types enabled in Settings appear here. Select one to load its form here; each option remains a direct link if enhancement is unavailable.</span>
                            </div>
                            <?php publishing_center_render_footer_links(); ?>
                        </section>

                        <section
                            class="admin-assistant-launcher-panel"
                            id="admin-launcher-panel-actions"
                            role="tabpanel"
                            aria-labelledby="admin-launcher-tab-actions"
                            data-admin-launcher-panel="actions"
                            hidden
                        >
                            <div class="admin-assistant-launcher-intro">
                                <strong>Dashboard actions</strong>
                                <span>Open operational tools and settings.</span>
                            </div>
                            <div class="admin-assistant-action-grid">
                                <?php if (nmm_module_enabled('call_us')): ?>
                                    <a href="<?=e(app_url('portal/admin.php?view=call-center'))?>"><span>Open</span><strong>Call Center</strong><small>Calls, voicemail, and callbacks</small></a>
                                <?php endif; ?>
                                <?php if (nmm_module_enabled('clients')): ?>
                                    <a href="<?=e(app_url('portal/admin.php?view=communications'))?>"><span>Open</span><strong>Communications</strong><small>Messages and conversations</small></a>
                                <?php endif; ?>
                                <a href="<?=e(app_url('portal/admin.php?view=crm'))?>"><span>Open</span><strong>CRM</strong><small>Contacts and opportunities</small></a>
                                <a href="<?=e(app_url('portal/admin.php?view=analytics'))?>"><span>Open</span><strong>Visitor Intelligence</strong><small>Traffic and conversion analytics</small></a>
                                <a href="<?=e(app_url('portal/admin.php?view=site-analytics'))?>"><span>Open</span><strong>Site Analytics</strong><small>Website and media activity</small></a>
                                <?php if (nmm_module_enabled('landing_page')): ?>
                                    <a href="<?=e(app_url('portal/admin.php?view=builder'))?>"><span>Build</span><strong>Page Editor</strong><small>Pages, sections, and blocks</small></a>
                                <?php endif; ?>
                                <a href="<?=e(app_url('portal/admin.php?view=menus'))?>"><span>Manage</span><strong>Navigation</strong><small>Menus and locations</small></a>
                                <a href="<?=e(app_url('portal/admin.php?view=settings'))?>"><span>Manage</span><strong>Settings</strong><small>Modules and publishing availability</small></a>
                            </div>
                        </section>
                    </div>
                </div>

                <form class="admin-assistant-composer" data-admin-assistant-form>
                    <button
                        class="admin-assistant-plus"
                        type="button"
                        data-admin-quick-toggle
                        aria-expanded="false"
                        aria-label="Open administrator tools"
                    >+</button>
                    <textarea
                        rows="1"
                        maxlength="500"
                        data-admin-assistant-input
                        placeholder="Ask about calls, messages, CRM contacts, projects, clients, or notifications…"
                        aria-label="Ask the administrator assistant"
                    ></textarea>
                    <button class="admin-assistant-submit" type="submit" aria-label="Send query">↑</button>
                </form>
                <small>Uses protected, predefined queries against the live portal database.</small>
            </section>
        <?php endif; ?>
    </main>
</div>

<section class="portal-confirm-modal" data-confirm-modal aria-hidden="true" hidden>
    <button class="portal-confirm-backdrop" type="button" data-confirm-cancel aria-label="Cancel confirmation"></button>
    <div
        class="portal-confirm-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="portal-confirm-title"
        aria-describedby="portal-confirm-message"
    >
        <div class="portal-confirm-icon" aria-hidden="true">!</div>
        <div class="portal-confirm-copy">
            <span data-confirm-eyebrow>Confirm action</span>
            <h2 id="portal-confirm-title" data-confirm-title>Are you sure?</h2>
            <p id="portal-confirm-message" data-confirm-message>This action cannot be undone.</p>
        </div>
        <div class="portal-confirm-actions">
            <button class="button" type="button" data-confirm-cancel>Cancel</button>
            <button class="button button-danger" type="button" data-confirm-accept>Continue</button>
        </div>
    </div>
</section>

<script src="<?=e(app_url('assets/js/portal.js?v=20260731-v66Q7'))?>"></script>
<?php if ($active === 'feeds'): ?>
    <script src="<?=e(app_url('assets/js/feed-reader.js?v=20260728-content-controls-v62.1'))?>"></script>
<?php endif; ?>
</body>
</html>
    <?php
}

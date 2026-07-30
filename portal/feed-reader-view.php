<?php
declare(strict_types=1);

/* North Mountain Media build: 20260728-social-feed-reader-v62.2 */

require_once __DIR__ . '/feed-reader-core.php';
require_once __DIR__ . '/feed-reader-media.php';

function feed_reader_portal_url(array $user, array $query = []): string
{
    $script = ($user['role'] ?? '') === 'admin' ? 'admin.php' : 'client.php';
    $query = ['view' => 'feeds'] + $query;
    return app_url('portal/' . $script . '?' . http_build_query($query));
}

function feed_reader_dashboard_url(array $user): string
{
    $script = ($user['role'] ?? '') === 'admin' ? 'admin.php' : 'client.php';
    return app_url('portal/' . $script);
}

function feed_reader_redirect(array $user, array $query = []): never
{
    redirect(feed_reader_portal_url($user, $query));
}

function feed_reader_return_query(): array
{
    $allowed = ['state', 'source', 'folder', 'q', 'item'];
    $query = [];
    foreach ($allowed as $key) {
        $value = trim((string)($_POST['return_' . $key] ?? ''));
        if ($value !== '') {
            $query[$key] = mb_substr($value, 0, 500);
        }
    }
    return $query;
}

function feed_reader_handle_portal_action(string $action, array $user): bool
{
    $actions = [
        'add_feed_subscription',
        'save_feed_folder',
        'delete_feed_folder',
        'update_feed_subscription',
        'delete_feed_subscription',
        'refresh_feed_source',
        'refresh_all_feeds',
        'mark_all_feed_items_read',
        'import_feed_opml',
        'save_feed_collection',
        'delete_feed_collection',
    ];

    if (!in_array($action, $actions, true)) {
        return false;
    }

    feed_reader_require_enabled();
    if (!feed_reader_schema_available()) {
        throw new RuntimeException('Import database/rss_feed_reader_v62.sql before using Feed Reader actions.');
    }

    $userId = (int)$user['id'];
    $returnQuery = feed_reader_return_query();

    if ($action === 'add_feed_subscription') {
        if (rate_limit_exceeded('feed_subscribe', (string)$userId, 10, 3600)) {
            throw new RuntimeException('Too many feed subscriptions were attempted. Wait before trying again.');
        }
        $source = feed_reader_subscribe(
            $userId,
            input('feed_url'),
            int_input('folder_id'),
            input('display_title')
        );
        flash('success', 'Subscribed to ' . ((string)($source['title'] ?? '') ?: 'the feed') . '.');
        feed_reader_redirect($user, ['source' => (int)$source['id']]);
    }

    if ($action === 'save_feed_folder') {
        $folderId = feed_reader_save_folder($userId, int_input('folder_id'), input('folder_name'));
        flash('success', 'Feed folder saved.');
        feed_reader_redirect($user, ['folder' => $folderId]);
    }

    if ($action === 'delete_feed_folder') {
        feed_reader_delete_folder($userId, int_input('folder_id'));
        flash('success', 'Feed folder removed. Its subscriptions remain available under All feeds.');
        feed_reader_redirect($user);
    }

    if ($action === 'update_feed_subscription') {
        $sourceId = int_input('source_id');
        feed_reader_update_subscription(
            $userId,
            $sourceId,
            int_input('folder_id'),
            input('display_title'),
            input('subscription_status')
        );
        flash('success', 'Feed subscription updated.');
        feed_reader_redirect($user, ['source' => $sourceId]);
    }

    if ($action === 'delete_feed_subscription') {
        feed_reader_unsubscribe($userId, int_input('source_id'));
        flash('success', 'Feed subscription removed.');
        feed_reader_redirect($user);
    }

    if ($action === 'refresh_feed_source') {
        $sourceId = int_input('source_id');
        if (!feed_reader_source_for_user($userId, $sourceId)) {
            throw new RuntimeException('The feed source is not subscribed by this account.');
        }
        if (rate_limit_exceeded('feed_manual_refresh', $userId . '|' . $sourceId, 5, 300)) {
            throw new RuntimeException('This feed was refreshed too frequently. Wait a few minutes and try again.');
        }
        $result = feed_reader_refresh_source($sourceId, 'manual', $userId, true);
        $message = match ((string)($result['status'] ?? 'success')) {
            'not_modified' => 'Feed checked. No new version was available.',
            'skipped' => 'The feed is already being refreshed.',
            default => 'Feed refreshed. ' . (int)($result['new_item_count'] ?? 0) . ' new item(s) were added.',
        };
        flash('success', $message);
        feed_reader_redirect($user, ['source' => $sourceId]);
    }

    if ($action === 'refresh_all_feeds') {
        if (rate_limit_exceeded('feed_refresh_all', (string)$userId, 2, 600)) {
            throw new RuntimeException('All feeds were refreshed recently. Wait a few minutes and try again.');
        }
        $subscriptions = feed_reader_subscriptions($userId);
        $success = 0;
        $failed = 0;
        $newItems = 0;
        foreach (array_slice($subscriptions, 0, 50) as $subscription) {
            if (($subscription['subscription_status'] ?? '') === 'paused') {
                continue;
            }
            try {
                $result = feed_reader_refresh_source((int)$subscription['source_id'], 'manual', $userId, true);
                $success++;
                $newItems += (int)($result['new_item_count'] ?? 0);
            } catch (Throwable) {
                $failed++;
            }
        }
        log_activity('feeds_refreshed', 'feed_subscription', null, [
            'success' => $success,
            'failed' => $failed,
            'new_items' => $newItems,
        ]);
        flash(
            $failed > 0 ? 'warning' : 'success',
            'Feed refresh completed: ' . $success . ' checked, ' . $newItems . ' new item(s) and ' . $failed . ' failure(s).'
        );
        feed_reader_redirect($user, $returnQuery);
    }

    if ($action === 'mark_all_feed_items_read') {
        $changed = feed_reader_mark_all_read(
            $userId,
            int_input('source_id'),
            int_input('folder_id')
        );
        flash('success', 'Items were marked read. ' . $changed . ' state record(s) were updated.');
        feed_reader_redirect($user, $returnQuery);
    }

    if ($action === 'save_feed_collection') {
        $collectionId = feed_reader_save_collection($userId, int_input('collection_id'), input('collection_name'));
        flash('success', 'Feed collection saved.');
        feed_reader_redirect($user, ['collection' => $collectionId]);
    }

    if ($action === 'delete_feed_collection') {
        feed_reader_delete_collection($userId, int_input('collection_id'));
        flash('success', 'Feed collection removed.');
        feed_reader_redirect($user);
    }

    if ($action === 'import_feed_opml') {
        if (rate_limit_exceeded('feed_opml_import', (string)$userId, 2, 3600)) {
            throw new RuntimeException('OPML imports were attempted too frequently. Wait before trying again.');
        }
        $file = $_FILES['opml_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Choose an OPML file to import.');
        }
        if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new RuntimeException('The OPML file must be 2 MB or smaller.');
        }
        $xml = file_get_contents((string)$file['tmp_name']);
        if ($xml === false) {
            throw new RuntimeException('The OPML file could not be read.');
        }
        $result = feed_reader_import_opml($userId, $xml);
        $message = $result['imported'] . ' of ' . $result['total'] . ' feed(s) imported.';
        if ($result['failed'] > 0) {
            $message .= ' ' . $result['failed'] . ' failed validation or retrieval.';
        }
        flash($result['failed'] > 0 ? 'warning' : 'success', $message);
        feed_reader_redirect($user);
    }

    return true;
}

function feed_reader_filter_url(array $user, array $changes = []): string
{
    $current = [
        'state' => trim((string)($_GET['state'] ?? '')),
        'source' => max(0, (int)($_GET['source'] ?? 0)),
        'folder' => max(0, (int)($_GET['folder'] ?? 0)),
        'q' => trim((string)($_GET['q'] ?? '')),
        'item' => max(0, (int)($_GET['item'] ?? 0)),
    ];
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '' || $value === 0) {
            unset($current[$key]);
        } else {
            $current[$key] = $value;
        }
    }
    return feed_reader_portal_url($user, $current);
}

function feed_reader_state_button(
    string $state,
    bool $active,
    string $label,
    string $activeLabel,
    string $icon
): string {
    return '<button type="button" class="feed-reader-state-button' . ($active ? ' is-active' : '') . '"'
        . ' data-feed-state="' . e($state) . '"'
        . ' data-feed-state-value="' . ($active ? '0' : '1') . '"'
        . ' aria-pressed="' . ($active ? 'true' : 'false') . '"'
        . ' title="' . e($active ? $activeLabel : $label) . '">'
        . '<span aria-hidden="true">' . e($icon) . '</span>'
        . '<span>' . e($active ? $activeLabel : $label) . '</span>'
        . '</button>';
}

function feed_reader_source_avatar(array $source, string $class = ''): string
{
    $title = (string)($source['display_title'] ?? $source['subscription_title'] ?? $source['title'] ?? $source['source_title'] ?? 'Feed');
    $image = trim((string)($source['image_url'] ?? ''));
    $className = 'feed-reader-source-avatar' . ($class !== '' ? ' ' . $class : '');
    if ($image !== '') {
        return '<span class="' . e($className) . '"><img src="' . e($image) . '" alt="" loading="lazy" referrerpolicy="no-referrer"></span>';
    }
    $letter = mb_strtoupper(mb_substr($title !== '' ? $title : 'F', 0, 1));
    return '<span class="' . e($className) . '" aria-hidden="true">' . e($letter) . '</span>';
}

function feed_reader_render_navigation(
    array $user,
    string $state,
    int $sourceId,
    int $folderId,
    array $folders,
    array $subscriptions,
    array $counts
): void {
    ?>
    <aside class="feed-reader-navigation" data-feed-sidebar aria-label="Feed Reader navigation">
        <header class="feed-reader-navigation-header">
            <a href="<?=e(feed_reader_dashboard_url($user))?>">← Back to portal</a>
            <span>Personal reader</span>
            <strong>Feed Reader</strong>
        </header>

        <nav class="feed-reader-smart-folders" aria-label="Feed filters">
            <a class="<?=$state==='all'&&$sourceId===0&&$folderId===0?'active':''?>" href="<?=e(feed_reader_portal_url($user))?>"><span>All items</span><strong><?=(int)($counts['total']??0)?></strong></a>
            <a class="<?=$state==='unread'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'unread','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Unread</span><strong><?=(int)($counts['unread']??0)?></strong></a>
            <a class="<?=$state==='starred'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'starred','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Starred</span><strong><?=(int)($counts['starred']??0)?></strong></a>
            <a class="<?=$state==='saved'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'saved','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Saved</span><strong><?=(int)($counts['saved']??0)?></strong></a>
            <a class="<?=$state==='listened'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'listened','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Listened</span><strong><?=(int)($counts['listened']??0)?></strong></a>
            <a class="<?=$state==='notes'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'notes','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Notes</span><strong><?=(int)($counts['notes']??0)?></strong></a>
            <a class="<?=$state==='archived'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'archived','source'=>null,'folder'=>null,'item'=>null]))?>"><span>Archived</span><strong><?=(int)($counts['archived']??0)?></strong></a>
        </nav>

        <section class="feed-reader-source-section">
            <header><span>Folders</span><button type="button" data-feed-folder-toggle aria-label="Create folder">＋</button></header>
            <form method="post" class="feed-reader-inline-form" data-feed-folder-form hidden>
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_feed_folder">
                <input name="folder_name" maxlength="190" placeholder="Folder name" required>
                <button type="submit">Save</button>
            </form>
            <nav>
                <?php foreach($folders as $folder):?>
                <a class="<?=$folderId===(int)$folder['id']?'active':''?>" href="<?=e(feed_reader_filter_url($user,['folder'=>(int)$folder['id'],'source'=>null,'item'=>null]))?>">
                    <span><?=e($folder['name'])?></span><strong><?=(int)$folder['unread_count']?></strong>
                </a>
                <?php endforeach;?>
                <?php if(!$folders):?><small>No folders yet.</small><?php endif;?>
            </nav>
        </section>

        <section class="feed-reader-source-section feed-reader-subscription-nav">
            <header><span>Subscriptions</span><strong><?=count($subscriptions)?></strong></header>
            <nav>
                <?php foreach($subscriptions as $subscription):?>
                <a
                    class="<?=$sourceId===(int)$subscription['source_id']?'active':''?> <?=e($subscription['source_status'])?>"
                    href="<?=e(feed_reader_filter_url($user,['source'=>(int)$subscription['source_id'],'folder'=>null,'item'=>null]))?>"
                    title="<?=e($subscription['last_error']?:$subscription['feed_url'])?>"
                >
                    <?=feed_reader_source_avatar($subscription)?>
                    <span><strong><?=e($subscription['display_title']?:$subscription['title'])?></strong><small><?=e($subscription['source_status']==='error'?'Refresh error':($subscription['folder_name']?:strtoupper($subscription['feed_format'])))?></small></span>
                    <em><?=(int)$subscription['unread_count']?></em>
                </a>
                <?php endforeach;?>
                <?php if(!$subscriptions):?><small>Add an RSS or Atom source to begin.</small><?php endif;?>
            </nav>
        </section>

        <footer class="feed-reader-navigation-footer">
            <button type="button" data-feed-settings-open>Settings</button>
            <button type="button" data-feed-dialog-open>Add feed</button>
        </footer>
    </aside>
    <?php
}

function feed_reader_render_settings_dialog(
    array $folders,
    array $subscriptions,
    array $recentRefreshes,
    array $config,
    string $opmlUrl,
    bool $mediaReady,
    array $collections
): void {
    $healthy = count(array_filter($subscriptions, static fn(array $source): bool => ($source['source_status'] ?? '') === 'active'));
    $errors = count(array_filter($subscriptions, static fn(array $source): bool => ($source['source_status'] ?? '') === 'error'));
    ?>
    <dialog class="feed-reader-dialog feed-reader-settings-dialog" data-feed-settings-dialog>
        <div class="feed-reader-settings-card">
            <header class="feed-reader-settings-header">
                <div><span>Reader controls</span><h2>Feed Reader settings</h2><p>Manage subscriptions, OPML portability, source health, and refresh evidence.</p></div>
                <button type="button" data-feed-settings-close aria-label="Close settings">×</button>
            </header>

            <div class="feed-reader-settings-body">
                <div class="feed-reader-management-grid">
                    <section class="feed-reader-settings-panel">
                        <span class="eyebrow">OPML</span>
                        <h3>Import or export subscriptions</h3>
                        <p>Move folders and feed URLs between compatible readers. Every imported source is validated before it is saved.</p>
                        <form method="post" enctype="multipart/form-data" class="form-grid">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="import_feed_opml">
                            <label class="field full"><span>OPML file</span><input type="file" name="opml_file" accept=".opml,.xml,text/xml,application/xml" required><small>Maximum 2 MB and up to <?=$config['max_sources_per_user']?> feeds.</small></label>
                            <div class="form-footer full"><button class="button button-primary" type="submit">Import OPML</button><a class="button" href="<?=e($opmlUrl)?>">Export OPML</a></div>
                        </form>
                    </section>

                    <section class="feed-reader-settings-panel">
                        <span class="eyebrow">Refresh service</span>
                        <h3>Health and scheduling</h3>
                        <p>Feeds use conditional requests, retry backoff, response limits, redirect validation, and refresh locks. Scheduled refresh interval: <?=$config['refresh_minutes']?> minutes.</p>
                        <div class="feed-reader-health-summary">
                            <span><strong><?=$healthy?></strong> healthy</span>
                            <span><strong><?=$errors?></strong> errors</span>
                            <span><strong><?=$config['max_response_bytes']/1048576?></strong> MB limit</span>
                        </div>
                    </section>
                </div>

                <section class="feed-reader-settings-section">
                    <header><div><span>Sources</span><h3>Manage subscriptions</h3></div><strong><?=count($subscriptions)?> total</strong></header>
                    <div class="feed-reader-subscription-cards">
                        <?php foreach($subscriptions as $subscription):?>
                        <article class="feed-reader-subscription-card">
                            <header>
                                <?=feed_reader_source_avatar($subscription)?>
                                <div><span class="status status-<?=e($subscription['source_status'])?>"><?=e(status_label($subscription['source_status']))?></span><h4><?=e($subscription['display_title']?:$subscription['title'])?></h4><small><?=e($subscription['feed_url'])?></small></div>
                                <strong><?=(int)$subscription['unread_count']?> unread</strong>
                            </header>
                            <?php if($subscription['last_error']):?><p class="feed-reader-source-error"><?=e($subscription['last_error'])?></p><?php endif;?>
                            <p>Last success: <?=e(format_datetime($subscription['last_success_at']))?> · Next refresh: <?=e(format_datetime($subscription['next_refresh_at']))?></p>
                            <form method="post" class="form-grid">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="update_feed_subscription">
                                <input type="hidden" name="source_id" value="<?=(int)$subscription['source_id']?>">
                                <label class="field"><span>Display title</span><input name="display_title" maxlength="255" value="<?=e($subscription['display_title']??'')?>" placeholder="<?=e($subscription['title'])?>"></label>
                                <label class="field"><span>Folder</span><select name="folder_id"><option value="0">No folder</option><?php foreach($folders as $folder):?><option value="<?=(int)$folder['id']?>" <?=(int)$subscription['folder_id']===(int)$folder['id']?'selected':''?>><?=e($folder['name'])?></option><?php endforeach;?></select></label>
                                <label class="field"><span>Subscription</span><select name="subscription_status"><option value="active" <?=$subscription['subscription_status']==='active'?'selected':''?>>Active</option><option value="paused" <?=$subscription['subscription_status']==='paused'?'selected':''?>>Paused</option></select></label>
                                <div class="form-footer full"><button class="button" type="submit">Save changes</button></div>
                            </form>
                            <div class="feed-reader-card-actions">
                                <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="refresh_feed_source"><input type="hidden" name="source_id" value="<?=(int)$subscription['source_id']?>"><button class="button" type="submit">Refresh now</button></form>
                                <form method="post" data-confirm="Remove this feed subscription?"><?=csrf_field()?><input type="hidden" name="action" value="delete_feed_subscription"><input type="hidden" name="source_id" value="<?=(int)$subscription['source_id']?>"><button class="button button-danger" type="submit">Unsubscribe</button></form>
                            </div>
                        </article>
                        <?php endforeach;?>
                        <?php if(!$subscriptions):?><div class="feed-reader-settings-empty">No subscriptions yet. Close settings and use Add feed to subscribe.</div><?php endif;?>
                    </div>
                </section>

                <?php if($mediaReady):?><section class="feed-reader-settings-section">
                    <header><div><span>Private organization</span><h3>Collections</h3></div><strong><?=count($collections)?> total</strong></header>
                    <form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="save_feed_collection"><label class="field"><span>New collection</span><input name="collection_name" maxlength="190" placeholder="Listen later" required></label><div class="form-footer"><button class="button" type="submit">Create collection</button></div></form>
                    <div class="feed-reader-collections-grid"><?php foreach($collections as $collection):?><article class="feed-reader-collection-card"><strong><?=e($collection['name'])?></strong><p><?=(int)$collection['item_count']?> item(s)</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_feed_collection"><input type="hidden" name="collection_id" value="<?=(int)$collection['id']?>"><button class="button button-danger" type="submit">Delete</button></form></article><?php endforeach;?><?php if(!$collections):?><p>Create a collection to organize articles, podcasts, and videos.</p><?php endif;?></div>
                </section><?php endif;?>

                <section class="feed-reader-settings-section feed-reader-refresh-history">
                    <header><div><span>Evidence</span><h3>Recent refresh history</h3></div></header>
                    <?php if($recentRefreshes):?>
                    <div class="feed-reader-refresh-table">
                        <div class="feed-reader-refresh-head"><span>Source</span><span>Trigger</span><span>Status</span><span>Items</span><span>Started</span></div>
                        <?php foreach($recentRefreshes as $run):?><div><strong><?=e($run['source_title'])?></strong><span><?=e(status_label($run['trigger_type']))?></span><span class="status status-<?=e($run['status'])?>"><?=e(status_label($run['status']))?></span><span><?=(int)$run['new_item_count']?> new / <?=(int)$run['item_count']?></span><time><?=e(format_datetime($run['started_at']))?></time><?php if($run['error_message']):?><small><?=e($run['error_message'])?></small><?php endif;?></div><?php endforeach;?>
                    </div>
                    <?php else:?><div class="feed-reader-settings-empty">No refresh history yet.</div><?php endif;?>
                </section>
            </div>

            <footer class="feed-reader-settings-footer"><button class="button button-primary" type="button" data-feed-settings-close>Done</button></footer>
        </div>
    </dialog>
    <?php
}

function feed_reader_render(array $user): void
{
    $userId = (int)$user['id'];
    $config = feed_reader_config();

    if (!$config['enabled']) {
        ?><section class="panel"><div class="empty-state">Feed Reader is disabled in the deployment configuration.</div></section><?php
        return;
    }

    if (!feed_reader_schema_available()) {
        ?>
        <section class="panel feed-reader-setup-required">
            <div class="panel-body">
                <span>Database setup required</span>
                <h2>Import the v62 Feed Reader migration</h2>
                <p>Import <code>database/rss_feed_reader_v62.sql</code>, then reload this page. The migration is additive and does not modify existing Blog, CRM, Music, analytics, or client records.</p>
            </div>
        </section>
        <?php
        return;
    }

    $mediaReady = feed_reader_media_schema_available();
    $state = in_array((string)($_GET['state'] ?? ''), ['unread', 'starred', 'saved', 'listened', 'notes', 'archived'], true)
        ? (string)$_GET['state']
        : 'all';
    $sourceId = max(0, (int)($_GET['source'] ?? 0));
    $folderId = max(0, (int)($_GET['folder'] ?? 0));
    $search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 190);
    $selectedItemId = max(0, (int)($_GET['item'] ?? 0));
    $filters = [
        'state' => $state,
        'source_id' => $sourceId,
        'folder_id' => $folderId,
        'q' => $search,
    ];

    $folders = feed_reader_folders($userId);
    $subscriptions = feed_reader_subscriptions($userId);
    $counts = feed_reader_counts($userId) + feed_reader_media_counts($userId);
    $items = feed_reader_enrich_media_items($userId, feed_reader_items($userId, $filters, 150));
    $selectedRows = $selectedItemId > 0 ? feed_reader_enrich_media_items($userId, array_filter([feed_reader_item_for_user($userId, $selectedItemId)])) : [];
    $selected = $selectedRows[0] ?? null;
    $collections = feed_reader_collections($userId);
    $recentRefreshes = feed_reader_recent_refreshes($userId, 20);
    $selectedId = (int)($selected['id'] ?? 0);
    $csrf = csrf_token();
    $apiUrl = app_url('portal/feed-reader-api.php');
    $opmlUrl = app_url('portal/feed-reader-opml.php');
    $returnFields = [
        'state' => $state !== 'all' ? $state : '',
        'source' => $sourceId > 0 ? (string)$sourceId : '',
        'folder' => $folderId > 0 ? (string)$folderId : '',
        'q' => $search,
        'item' => $selectedId > 0 ? (string)$selectedId : '',
    ];
    $backUrl = feed_reader_filter_url($user, ['item' => null]);
    $matchingSource = array_values(array_filter($subscriptions, static fn(array $subscription): bool => (int)$subscription['source_id'] === $sourceId));
    $matchingFolder = array_values(array_filter($folders, static fn(array $folder): bool => (int)$folder['id'] === $folderId));
    $activeLabel = $sourceId > 0
        ? (string)($matchingSource[0]['display_title'] ?? $matchingSource[0]['title'] ?? '')
        : ($folderId > 0 ? (string)($matchingFolder[0]['name'] ?? '') : status_label($state));
    if ($activeLabel === '') {
        $activeLabel = status_label($state);
    }
    ?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/feed-reader-social.css?v=20260728-social-feed-reader-v62-2'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/feed-reader-media-v66b.css?v=20260730-v66B'))?>">
<div
    class="feed-reader-shell feed-reader-social-shell <?=$selectedId>0?'has-selected-item':''?>"
    data-social-feed-reader
    data-feed-api="<?=e($apiUrl)?>"
    data-feed-csrf="<?=e($csrf)?>"
    data-selected-item="<?=$selectedId?>"
    data-feed-media-ready="<?=$mediaReady?'1':'0'?>"
>
    <?php feed_reader_render_navigation($user, $state, $sourceId, $folderId, $folders, $subscriptions, $counts); ?>

    <header class="feed-reader-toolbar">
        <div>
            <span>Personal information stream</span>
            <h2><?=$selectedId>0?'Article reader':'Feed Reader'?></h2>
            <p><?=$selectedId>0?'Read the full sanitized article, manage its private status, or return to your feed.':'A private, social-style stream built from the RSS and Atom sources you choose.'?></p>
        </div>
        <div class="feed-reader-toolbar-actions">
            <button class="feed-reader-icon-button" type="button" data-feed-settings-open aria-label="Open Feed Reader settings" title="Feed Reader settings">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20.3h-3v-.08a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7 15a1.7 1.7 0 0 0-1.56-1.03H5.3v-3h.14A1.7 1.7 0 0 0 7 9.94a1.7 1.7 0 0 0-.34-1.88L6.6 8l2.12-2.12.06.06a1.7 1.7 0 0 0 1.88.34A1.7 1.7 0 0 0 11.7 4.7v-.08h3v.08a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06L19.8 8l-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.03h.14v3h-.14A1.7 1.7 0 0 0 19.4 15Z"></path></svg>
            </button>
            <form method="post">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="refresh_all_feeds">
                <?php foreach($returnFields as $key=>$value):?><input type="hidden" name="return_<?=e($key)?>" value="<?=e($value)?>"><?php endforeach;?>
                <button class="button" type="submit">Refresh all</button>
            </form>
            <button class="button button-primary" type="button" data-feed-dialog-open>Add feed</button>
        </div>
    </header>

    <?php if(!$mediaReady):?><div class="feed-reader-media-setup"><strong>Feed Reader Media migration required.</strong> Import <code>database/feed_reader_media_v66b.sql</code> to enable durable playback, listened state, notes, and collections. Base reading and subscriptions remain available.</div><?php endif;?>

    <?php if($selectedId>0 && $selected):?>
    <article class="feed-reader-article-page" data-feed-article>
        <header class="feed-reader-article-header">
            <a class="feed-reader-back-link" href="<?=e($backUrl)?>" data-feed-back>← Back to feed</a>
            <div class="feed-reader-article-source">
                <?=feed_reader_source_avatar($selected,'large')?>
                <span><a href="<?=e($selected['site_url']?:$selected['feed_url'])?>" target="_blank" rel="noopener noreferrer nofollow"><?=e($selected['subscription_title'])?></a><small><?=e(format_datetime($selected['published_at']?:$selected['discovered_at']))?></small></span>
            </div>
            <div class="feed-reader-reading-actions" data-item-id="<?=$selectedId?>">
                <?=feed_reader_state_button('read',(bool)$selected['is_read'],'Mark read','Mark unread','✓')?>
                <?=feed_reader_state_button('starred',(bool)$selected['is_starred'],'Star','Unstar','★')?>
                <?=feed_reader_state_button('saved',(bool)$selected['is_saved'],'Save','Unsave','◆')?>
                <?=feed_reader_state_button('archived',(bool)$selected['is_archived'],'Archive','Restore','▣')?>
            </div>
        </header>
        <div class="feed-reader-article-content">
            <?php if($selected['image_url']):?><img class="feed-reader-hero-image" src="<?=e($selected['image_url'])?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif;?>
            <span class="feed-reader-reading-source"><?=e($selected['source_title'])?></span>
            <h1><?=e($selected['title'])?></h1>
            <?php if($selected['author_name']):?><p class="feed-reader-byline">By <?=e($selected['author_name'])?></p><?php endif;?>
            <div class="feed-reader-article-body">
                <?=$selected['content_html']?:('<p>'.e($selected['summary']?:'This feed item does not provide article content.').'</p>')?>
            </div>
            <?=feed_reader_media_markup($selected,'article')?>
            <?php if($mediaReady):?>
            <section class="feed-reader-private-tools" data-item-id="<?=$selectedId?>">
                <span class="eyebrow">Private workspace</span><h3>Notes and collections</h3><p>These notes and collection memberships are visible only to this portal account.</p>
                <textarea data-feed-note maxlength="8000" placeholder="Add a private note about this item"><?=e($selected['note_text'])?></textarea>
                <div class="feed-reader-private-actions"><button class="button" type="button" data-feed-note-save>Save note</button>
                <select data-feed-collection-select><option value="">Choose collection</option><?php foreach($collections as $collection):?><option value="<?=(int)$collection['id']?>" <?=in_array((int)$collection['id'],$selected['collection_ids'],true)?'disabled':''?>><?=e($collection['name'])?><?=in_array((int)$collection['id'],$selected['collection_ids'],true)?' · added':''?></option><?php endforeach;?></select>
                <button class="button" type="button" data-feed-collection-add <?=$collections?'':'disabled'?>>Add to collection</button></div>
            </section>
            <?php endif;?>
            <footer class="feed-reader-article-footer">
                <a class="button" href="<?=e($backUrl)?>">← Back to feed</a>
                <?php if($selected['canonical_url']):?><a class="button button-primary" href="<?=e($selected['canonical_url'])?>" target="_blank" rel="noopener noreferrer nofollow">Read original article ↗</a><?php endif;?>
            </footer>
        </div>
    </article>
    <?php else:?>
    <form class="feed-reader-search" method="get" role="search">
        <input type="hidden" name="view" value="feeds">
        <?php if($state!=='all'):?><input type="hidden" name="state" value="<?=e($state)?>"><?php endif;?>
        <?php if($sourceId>0):?><input type="hidden" name="source" value="<?=$sourceId?>"><?php endif;?>
        <?php if($folderId>0):?><input type="hidden" name="folder" value="<?=$folderId?>"><?php endif;?>
        <label><span class="sr-only">Search feed items</span><input name="q" value="<?=e($search)?>" placeholder="Search titles, summaries, authors, and sources" data-feed-search-input></label>
        <button class="button" type="submit">Search</button>
        <?php if($search!==''):?><a class="button" href="<?=e(feed_reader_filter_url($user,['q'=>null,'item'=>null]))?>">Clear</a><?php endif;?>
    </form>

    <div class="feed-reader-layout feed-reader-social-layout">
        <main class="feed-reader-social-feed" aria-label="Feed items">
            <header class="feed-reader-stream-header">
                <div><span><?=e($activeLabel)?></span><h3><?=count($items)?> story<?=count($items)===1?'':'ies'?></h3></div>
                <form method="post">
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="mark_all_feed_items_read">
                    <input type="hidden" name="source_id" value="<?=$sourceId?>">
                    <input type="hidden" name="folder_id" value="<?=$folderId?>">
                    <?php foreach($returnFields as $key=>$value):?><input type="hidden" name="return_<?=e($key)?>" value="<?=e($value)?>"><?php endforeach;?>
                    <button type="submit">Mark all read</button>
                </form>
            </header>

            <div class="feed-reader-social-items" data-feed-items tabindex="-1">
                <?php foreach($items as $item):?>
                <?php $itemUrl=feed_reader_filter_url($user,['item'=>(int)$item['id']]); $published=$item['published_at']?:$item['discovered_at']; ?>
                <article class="feed-reader-social-card <?=!(int)$item['is_read']?'unread':''?> <?=(int)$item['is_listened']?'is-listened':''?> <?=$item['note_text']!==''?'has-note':''?>" data-feed-item-row data-item-id="<?=(int)$item['id']?>">
                    <header>
                        <?=feed_reader_source_avatar($item)?>
                        <div><strong><?=e($item['subscription_title'])?></strong><span><?=e($item['author_name']?:'Published feed')?> · <time><?=e(format_date($published,'M j, Y'))?></time></span></div>
                        <?php if(!(int)$item['is_read']):?><i title="Unread"></i><?php endif;?>
                    </header>
                    <a class="feed-reader-social-story" href="<?=e($itemUrl)?>" data-feed-item-link>
                        <h3><?=e($item['title'])?></h3>
                        <p><?=e($item['summary']?:'Open the article to continue reading.')?></p>
                        <?php if($item['image_url'] && ($item['media']['kind']??'')!=='video'):?><img src="<?=e($item['image_url'])?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif;?>
                    </a>
                    <?=feed_reader_media_markup($item,'card')?>
                    <?php if((int)$item['is_listened'] || $item['note_text']!==''):?><div class="feed-reader-media-badges"><?php if((int)$item['is_listened']):?><span>Listened</span><?php endif;?><?php if($item['note_text']!==''):?><span>Private note</span><?php endif;?></div><?php endif;?>
                    <footer data-item-id="<?=(int)$item['id']?>">
                        <a href="<?=e($itemUrl)?>" data-feed-item-link>Read article</a>
                        <div>
                            <?=feed_reader_state_button('starred',(bool)$item['is_starred'],'Star','Unstar','★')?>
                            <?=feed_reader_state_button('saved',(bool)$item['is_saved'],'Save','Unsave','◆')?>
                            <?=feed_reader_state_button('archived',(bool)$item['is_archived'],'Archive','Restore','▣')?>
                        </div>
                    </footer>
                </article>
                <?php endforeach;?>
                <?php if(!$items):?><div class="feed-reader-empty"><strong>No matching stories</strong><span>Try another source, folder, state, or search—or add a new RSS feed.</span><button class="button button-primary" type="button" data-feed-dialog-open>Add feed</button></div><?php endif;?>
            </div>
        </main>

        <aside class="feed-reader-stream-summary" aria-label="Reader snapshot">
            <section><span>Your reader</span><h3>Private by design</h3><p>Your subscriptions and reading states stay attached to your portal account.</p></section>
            <div class="feed-reader-summary-grid">
                <article><strong><?=(int)($counts['unread']??0)?></strong><span>Unread</span></article>
                <article><strong><?=(int)($counts['saved']??0)?></strong><span>Saved</span></article>
                <article><strong><?=(int)($counts['starred']??0)?></strong><span>Starred</span></article>
                <article><strong><?=count($subscriptions)?></strong><span>Sources</span></article>
            </div>
            <section><span>Reader shortcuts</span><p><kbd>/</kbd> Search · <kbd>J</kbd>/<kbd>K</kbd> move through stories</p></section>
        </aside>
    </div>
    <?php endif;?>

    <?php feed_reader_render_settings_dialog($folders, $subscriptions, $recentRefreshes, $config, $opmlUrl, $mediaReady, $collections); ?>

    <dialog class="feed-reader-dialog feed-reader-add-dialog" data-feed-dialog>
        <form method="post" class="feed-reader-dialog-card">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="add_feed_subscription">
            <header><div><span>New subscription</span><h2>Add RSS or Atom feed</h2></div><button type="button" data-feed-dialog-close aria-label="Close">×</button></header>
            <p>Paste an RSS/Atom URL, website URL, YouTube channel, handle, or playlist URL. The server validates DNS, redirects, response size, XML structure, and imported HTML before saving anything.</p>
            <label class="field full"><span>Feed URL</span><input type="url" name="feed_url" maxlength="2000" placeholder="https://example.com/feed.xml or https://youtube.com/@channel" required></label>
            <label class="field full"><span>Display title <small>optional</small></span><input name="display_title" maxlength="255"></label>
            <label class="field full"><span>Folder</span><select name="folder_id"><option value="0">No folder</option><?php foreach($folders as $folder):?><option value="<?=(int)$folder['id']?>"><?=e($folder['name'])?></option><?php endforeach;?></select></label>
            <footer><button class="button" type="button" data-feed-dialog-close>Cancel</button><button class="button button-primary" type="submit">Validate and subscribe</button></footer>
        </form>
    </dialog>
    <section class="feed-reader-player" data-feed-player-shell hidden aria-label="Feed audio player">
        <img class="feed-reader-player-cover" data-feed-player-cover alt="" hidden>
        <div class="feed-reader-player-copy"><strong data-feed-player-title>Feed audio</strong><span data-feed-player-source>Feed Reader</span></div>
        <audio controls preload="metadata" data-feed-player-audio></audio>
        <button type="button" data-feed-player-prev aria-label="Previous audio">←</button>
        <select data-feed-player-speed aria-label="Playback speed"><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option></select>
        <button type="button" data-feed-player-next aria-label="Next audio">→</button>
        <button type="button" data-feed-player-close aria-label="Close player">×</button>
    </section>
</div>
<script src="<?=e(app_url('assets/js/feed-reader-social.js?v=20260730-feed-reader-media-v66B'))?>"></script>
    <?php
}

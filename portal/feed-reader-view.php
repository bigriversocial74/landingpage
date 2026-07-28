<?php
declare(strict_types=1);

/* North Mountain Media build: 20260728-rss-feed-reader-v62 */

require_once __DIR__ . '/feed-reader-core.php';

function feed_reader_portal_url(array $user, array $query = []): string
{
    $script = ($user['role'] ?? '') === 'admin' ? 'admin.php' : 'client.php';
    $query = ['view' => 'feeds'] + $query;
    return app_url('portal/' . $script . '?' . http_build_query($query));
}

function feed_reader_redirect(array $user, array $query = []): never
{
    redirect(feed_reader_portal_url($user, $query));
}

function feed_reader_return_query(): array
{
    $allowed = ['state','source','folder','q','item'];
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

function feed_reader_render(array $user): void
{
    $userId = (int)$user['id'];
    $config = feed_reader_config();

    if (!$config['enabled']) {
        ?>
        <section class="panel"><div class="empty-state">Feed Reader is disabled in the deployment configuration.</div></section>
        <?php
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

    $state = in_array((string)($_GET['state'] ?? ''), ['unread','starred','saved','archived'], true)
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
    $counts = feed_reader_counts($userId);
    $items = feed_reader_items($userId, $filters, 250);
    $selected = $selectedItemId > 0
        ? feed_reader_item_for_user($userId, $selectedItemId)
        : ($items[0] ?? null);
    $recentRefreshes = feed_reader_recent_refreshes($userId, 20);
    $selectedId = (int)($selected['id'] ?? 0);
    $explicitSelectedId = $selectedItemId > 0 ? $selectedId : 0;
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
    ?>
<div
    class="feed-reader-shell <?= $explicitSelectedId > 0 ? 'has-selected-item' : '' ?>"
    data-feed-reader
    data-feed-api="<?=e($apiUrl)?>"
    data-feed-csrf="<?=e($csrf)?>"
    data-selected-item="<?=$explicitSelectedId?>"
>
    <header class="feed-reader-toolbar">
        <div>
            <span>Personal information stream</span>
            <h2>Feed Reader</h2>
            <p>Subscribe to external RSS and Atom sources, organize them into folders, and keep read, saved, starred, and archived states private to your account.</p>
        </div>
        <div class="feed-reader-toolbar-actions">
            <form method="post">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="refresh_all_feeds">
                <?php foreach($returnFields as $key=>$value):?><input type="hidden" name="return_<?=e($key)?>" value="<?=e($value)?>"><?php endforeach;?>
                <button class="button" type="submit">Refresh all</button>
            </form>
            <button class="button button-primary" type="button" data-feed-dialog-open>Add feed</button>
        </div>
    </header>

    <section class="feed-reader-stat-grid" aria-label="Feed Reader totals">
        <article><span>Subscriptions</span><strong><?=count($subscriptions)?></strong><small><?=count($folders)?> folder(s)</small></article>
        <article><span>Unread</span><strong><?=(int)($counts['unread']??0)?></strong><small>Ready to review</small></article>
        <article><span>Starred</span><strong><?=(int)($counts['starred']??0)?></strong><small>Priority items</small></article>
        <article><span>Saved</span><strong><?=(int)($counts['saved']??0)?></strong><small>Reference library</small></article>
        <article><span>Archived</span><strong><?=(int)($counts['archived']??0)?></strong><small>Cleared from inbox</small></article>
    </section>

    <form class="feed-reader-search" method="get" role="search">
        <input type="hidden" name="view" value="feeds">
        <?php if($state!=='all'):?><input type="hidden" name="state" value="<?=e($state)?>"><?php endif;?>
        <?php if($sourceId>0):?><input type="hidden" name="source" value="<?=$sourceId?>"><?php endif;?>
        <?php if($folderId>0):?><input type="hidden" name="folder" value="<?=$folderId?>"><?php endif;?>
        <label>
            <span class="sr-only">Search feed items</span>
            <input name="q" value="<?=e($search)?>" placeholder="Search titles, summaries, authors, and sources" data-feed-search-input>
        </label>
        <button class="button" type="submit">Search</button>
        <?php if($search!==''):?><a class="button" href="<?=e(feed_reader_filter_url($user,['q'=>null]))?>">Clear</a><?php endif;?>
    </form>

    <div class="feed-reader-layout">
        <aside class="feed-reader-sources" aria-label="Feed sources and folders">
            <nav class="feed-reader-smart-folders" aria-label="Feed filters">
                <a class="<?=$state==='all'&&$sourceId===0&&$folderId===0?'active':''?>" href="<?=e(feed_reader_portal_url($user))?>"><span>All items</span><strong><?=(int)($counts['total']??0)?></strong></a>
                <a class="<?=$state==='unread'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'unread','source'=>null,'folder'=>null]))?>"><span>Unread</span><strong><?=(int)($counts['unread']??0)?></strong></a>
                <a class="<?=$state==='starred'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'starred','source'=>null,'folder'=>null]))?>"><span>Starred</span><strong><?=(int)($counts['starred']??0)?></strong></a>
                <a class="<?=$state==='saved'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'saved','source'=>null,'folder'=>null]))?>"><span>Saved</span><strong><?=(int)($counts['saved']??0)?></strong></a>
                <a class="<?=$state==='archived'?'active':''?>" href="<?=e(feed_reader_filter_url($user,['state'=>'archived','source'=>null,'folder'=>null]))?>"><span>Archived</span><strong><?=(int)($counts['archived']??0)?></strong></a>
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
                    <a class="<?=$folderId===(int)$folder['id']?'active':''?>" href="<?=e(feed_reader_filter_url($user,['folder'=>(int)$folder['id'],'source'=>null]))?>">
                        <span><?=e($folder['name'])?></span><strong><?=(int)$folder['unread_count']?></strong>
                    </a>
                    <?php endforeach;?>
                    <?php if(!$folders):?><small>No folders yet.</small><?php endif;?>
                </nav>
            </section>

            <section class="feed-reader-source-section">
                <header><span>Subscriptions</span><strong><?=count($subscriptions)?></strong></header>
                <nav>
                    <?php foreach($subscriptions as $subscription):?>
                    <a
                        class="<?=$sourceId===(int)$subscription['source_id']?'active':''?> <?=e($subscription['source_status'])?>"
                        href="<?=e(feed_reader_filter_url($user,['source'=>(int)$subscription['source_id'],'folder'=>null]))?>"
                        title="<?=e($subscription['last_error']?:$subscription['feed_url'])?>"
                    >
                        <span class="feed-reader-source-icon">
                            <?php if($subscription['image_url']):?><img src="<?=e($subscription['image_url'])?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php else:?><?=e(mb_strtoupper(mb_substr((string)($subscription['display_title']?:$subscription['title']),0,1)))?><?php endif;?>
                        </span>
                        <span><strong><?=e($subscription['display_title']?:$subscription['title'])?></strong><small><?=e($subscription['source_status']==='error'?'Refresh error':($subscription['folder_name']?:strtoupper($subscription['feed_format'])))?></small></span>
                        <em><?=(int)$subscription['unread_count']?></em>
                    </a>
                    <?php endforeach;?>
                    <?php if(!$subscriptions):?><small>Add an RSS or Atom URL to begin.</small><?php endif;?>
                </nav>
            </section>

            <footer class="feed-reader-source-footer">
                <a href="<?=e($opmlUrl)?>">Export OPML</a>
                <button type="button" data-feed-manage-toggle>Manage feeds</button>
            </footer>
        </aside>

        <section class="feed-reader-item-list" aria-label="Feed items">
            <header>
                <div><span><?=e(status_label($state))?></span><strong><?=count($items)?> item(s)</strong></div>
                <form method="post">
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="mark_all_feed_items_read">
                    <input type="hidden" name="source_id" value="<?=$sourceId?>">
                    <input type="hidden" name="folder_id" value="<?=$folderId?>">
                    <?php foreach($returnFields as $key=>$value):?><input type="hidden" name="return_<?=e($key)?>" value="<?=e($value)?>"><?php endforeach;?>
                    <button type="submit">Mark all read</button>
                </form>
            </header>
            <div class="feed-reader-items" data-feed-items tabindex="-1">
                <?php foreach($items as $item):?>
                <?php
                $itemUrl=feed_reader_filter_url($user,['item'=>(int)$item['id']]);
                $published=$item['published_at']?:$item['discovered_at'];
                ?>
                <article
                    class="feed-reader-item-row <?=$selectedId===(int)$item['id']?'active':''?> <?=!(int)$item['is_read']?'unread':''?>"
                    data-feed-item-row
                    data-item-id="<?=(int)$item['id']?>"
                >
                    <a href="<?=e($itemUrl)?>" data-feed-item-link>
                        <?php if($item['image_url']):?><img src="<?=e($item['image_url'])?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif;?>
                        <span class="feed-reader-item-copy">
                            <small><strong><?=e($item['subscription_title'])?></strong><time><?=e(format_date($published,'M j'))?></time></small>
                            <h3><?=e($item['title'])?></h3>
                            <p><?=e($item['summary']?:'Open the original article to continue reading.')?></p>
                            <span><?=e($item['author_name']?:'')?></span>
                        </span>
                    </a>
                    <div class="feed-reader-row-flags" aria-label="Item status">
                        <?php if((int)$item['is_starred']):?><span title="Starred">★</span><?php endif;?>
                        <?php if((int)$item['is_saved']):?><span title="Saved">◆</span><?php endif;?>
                    </div>
                </article>
                <?php endforeach;?>
                <?php if(!$items):?><div class="feed-reader-empty"><strong>No matching feed items</strong><span>Try another folder, source, state, or search.</span></div><?php endif;?>
            </div>
        </section>

        <article class="feed-reader-reading-pane" aria-label="Selected feed item" data-feed-reading-pane>
            <?php if($selected):?>
            <header class="feed-reader-reading-header">
                <button type="button" class="feed-reader-mobile-back" data-feed-mobile-back>← Items</button>
                <div>
                    <a href="<?=e($selected['site_url']?:$selected['feed_url'])?>" target="_blank" rel="noopener noreferrer nofollow"><?=e($selected['subscription_title'])?></a>
                    <span><?=e(format_datetime($selected['published_at']?:$selected['discovered_at']))?></span>
                </div>
                <div class="feed-reader-reading-actions" data-item-id="<?=$selectedId?>">
                    <?=feed_reader_state_button('read',(bool)$selected['is_read'],'Mark read','Mark unread','✓')?>
                    <?=feed_reader_state_button('starred',(bool)$selected['is_starred'],'Star','Unstar','★')?>
                    <?=feed_reader_state_button('saved',(bool)$selected['is_saved'],'Save','Unsave','◆')?>
                    <?=feed_reader_state_button('archived',(bool)$selected['is_archived'],'Archive','Restore','▣')?>
                </div>
            </header>
            <div class="feed-reader-reading-content">
                <?php if($selected['image_url']):?><img class="feed-reader-hero-image" src="<?=e($selected['image_url'])?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endif;?>
                <span class="feed-reader-reading-source"><?=e($selected['source_title'])?></span>
                <h1><?=e($selected['title'])?></h1>
                <?php if($selected['author_name']):?><p class="feed-reader-byline">By <?=e($selected['author_name'])?></p><?php endif;?>
                <div class="feed-reader-article-body">
                    <?=$selected['content_html']?:('<p>'.e($selected['summary']?:'This feed item does not provide article content.').'</p>')?>
                </div>
                <?php if($selected['enclosure_url']):?>
                    <?php if(str_starts_with((string)$selected['enclosure_type'],'audio/')):?>
                        <audio controls preload="metadata" src="<?=e($selected['enclosure_url'])?>"></audio>
                    <?php else:?>
                        <a class="button" href="<?=e($selected['enclosure_url'])?>" target="_blank" rel="noopener noreferrer nofollow">Open attachment</a>
                    <?php endif;?>
                <?php endif;?>
                <?php if($selected['canonical_url']):?><p class="feed-reader-original-link"><a class="button button-primary" href="<?=e($selected['canonical_url'])?>" target="_blank" rel="noopener noreferrer nofollow">Read original article ↗</a></p><?php endif;?>
            </div>
            <?php else:?>
                <div class="feed-reader-reading-empty"><strong>Select an item</strong><span>The full sanitized article, source details, and private reading controls will appear here.</span></div>
            <?php endif;?>
        </article>
    </div>

    <section class="feed-reader-management" data-feed-management hidden>
        <header class="panel-header"><div><span>Subscriptions and interoperability</span><h2>Manage feeds</h2></div><button type="button" class="button" data-feed-manage-close>Close</button></header>
        <div class="feed-reader-management-grid">
            <section class="panel">
                <div class="panel-body">
                    <span class="eyebrow">OPML</span>
                    <h3>Import subscriptions</h3>
                    <p>Import folders and feed URLs from another reader. Each URL is securely validated before subscription.</p>
                    <form method="post" enctype="multipart/form-data" class="form-grid">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="import_feed_opml">
                        <label class="field full"><span>OPML file</span><input type="file" name="opml_file" accept=".opml,.xml,text/xml,application/xml" required><small>Maximum 2 MB and up to <?=$config['max_sources_per_user']?> feeds.</small></label>
                        <div class="form-footer full"><button class="button button-primary" type="submit">Import OPML</button><a class="button" href="<?=e($opmlUrl)?>">Export OPML</a></div>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body">
                    <span class="eyebrow">Refresh service</span>
                    <h3>Health and scheduling</h3>
                    <p>Feeds use ETag and Last-Modified headers, retry backoff, response limits, redirect validation, and refresh locks. Scheduled refresh interval: <?=$config['refresh_minutes']?> minutes.</p>
                    <div class="feed-reader-health-summary">
                        <span><strong><?=count(array_filter($subscriptions,fn($s)=>$s['source_status']==='active'))?></strong> healthy</span>
                        <span><strong><?=count(array_filter($subscriptions,fn($s)=>$s['source_status']==='error'))?></strong> errors</span>
                        <span><strong><?=$config['max_response_bytes']/1048576?></strong> MB limit</span>
                    </div>
                </div>
            </section>
        </div>

        <div class="feed-reader-subscription-cards">
            <?php foreach($subscriptions as $subscription):?>
            <article class="panel feed-reader-subscription-card">
                <div class="panel-body">
                    <header><div><span class="status status-<?=e($subscription['source_status'])?>"><?=e(status_label($subscription['source_status']))?></span><h3><?=e($subscription['display_title']?:$subscription['title'])?></h3><small><?=e($subscription['feed_url'])?></small></div><strong><?=(int)$subscription['unread_count']?> unread</strong></header>
                    <?php if($subscription['last_error']):?><p class="feed-reader-source-error"><?=e($subscription['last_error'])?></p><?php endif;?>
                    <p>Last success: <?=e(format_datetime($subscription['last_success_at']))?> · Next refresh: <?=e(format_datetime($subscription['next_refresh_at']))?></p>
                    <form method="post" class="form-grid">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="update_feed_subscription">
                        <input type="hidden" name="source_id" value="<?=(int)$subscription['source_id']?>">
                        <label class="field"><span>Display title</span><input name="display_title" maxlength="255" value="<?=e($subscription['display_title']??'')?>" placeholder="<?=e($subscription['title'])?>"></label>
                        <label class="field"><span>Folder</span><select name="folder_id"><option value="0">No folder</option><?php foreach($folders as $folder):?><option value="<?=(int)$folder['id']?>" <?=(int)$subscription['folder_id']===(int)$folder['id']?'selected':''?>><?=e($folder['name'])?></option><?php endforeach;?></select></label>
                        <label class="field"><span>Subscription</span><select name="subscription_status"><option value="active" <?=$subscription['subscription_status']==='active'?'selected':''?>>Active</option><option value="paused" <?=$subscription['subscription_status']==='paused'?'selected':''?>>Paused</option></select></label>
                        <div class="form-footer full"><button class="button" type="submit">Save</button></div>
                    </form>
                    <div class="feed-reader-card-actions">
                        <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="refresh_feed_source"><input type="hidden" name="source_id" value="<?=(int)$subscription['source_id']?>"><button class="button" type="submit">Refresh now</button></form>
                        <form method="post" data-confirm="Remove this feed subscription?"><?=csrf_field()?><input type="hidden" name="action" value="delete_feed_subscription"><input type="hidden" name="source_id" value="<?=(int)$subscription['source_id']?>"><button class="button button-danger" type="submit">Unsubscribe</button></form>
                    </div>
                </div>
            </article>
            <?php endforeach;?>
        </div>

        <section class="panel feed-reader-refresh-history">
            <header class="panel-header"><div><span>Evidence</span><h2>Recent refresh history</h2></div></header>
            <?php if($recentRefreshes):?><div class="feed-reader-refresh-table"><div class="feed-reader-refresh-head"><span>Source</span><span>Trigger</span><span>Status</span><span>Items</span><span>Started</span></div><?php foreach($recentRefreshes as $run):?><div><strong><?=e($run['source_title'])?></strong><span><?=e(status_label($run['trigger_type']))?></span><span class="status status-<?=e($run['status'])?>"><?=e(status_label($run['status']))?></span><span><?=(int)$run['new_item_count']?> new / <?=(int)$run['item_count']?></span><time><?=e(format_datetime($run['started_at']))?></time><?php if($run['error_message']):?><small><?=e($run['error_message'])?></small><?php endif;?></div><?php endforeach;?></div><?php else:?><div class="empty-state">No refresh history yet.</div><?php endif;?>
        </section>
    </section>

    <dialog class="feed-reader-dialog" data-feed-dialog>
        <form method="post" class="feed-reader-dialog-card">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="add_feed_subscription">
            <header><div><span>New subscription</span><h2>Add RSS or Atom feed</h2></div><button type="button" data-feed-dialog-close aria-label="Close">×</button></header>
            <p>Paste a public HTTP or HTTPS feed URL. The server validates DNS, redirects, response size, XML structure, and imported HTML before saving anything.</p>
            <label class="field full"><span>Feed URL</span><input type="url" name="feed_url" maxlength="2000" placeholder="https://example.com/feed.xml" required autofocus></label>
            <label class="field full"><span>Display title <small>optional</small></span><input name="display_title" maxlength="255"></label>
            <label class="field full"><span>Folder</span><select name="folder_id"><option value="0">No folder</option><?php foreach($folders as $folder):?><option value="<?=(int)$folder['id']?>"><?=e($folder['name'])?></option><?php endforeach;?></select></label>
            <footer><button class="button" type="button" data-feed-dialog-close>Cancel</button><button class="button button-primary" type="submit">Validate and subscribe</button></footer>
        </form>
    </dialog>
</div>
    <?php
}

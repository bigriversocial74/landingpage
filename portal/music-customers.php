<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_role('admin');
if (!music_customer_lifecycle_ready()) {
    portal_header('Music Customers', 'music', $user);
    ?>
    <section class="panel">
        <div class="panel-body">
            <div class="alert alert-warning">Import <code>database/music_customer_accounts_v66q21.sql</code> before managing secure customer lifecycles.</div>
            <a class="button" href="<?=e(app_url('portal/admin.php?view=music&section=customers'))?>">Return to Music Library</a>
        </div>
    </section>
    <?php
    portal_footer();
    exit;
}

if (is_post()) {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }
    enforce_authenticated_action_limit($user);
    $action = input('action');
    $customerId = int_input('customer_id');

    try {
        $customer = db()->prepare(
            'SELECT customer.id,customer.email,customer.display_name,customer.status,
                    state.email_verified_at,state.pending_email,state.auth_version
             FROM users customer
             LEFT JOIN music_customer_account_state state ON state.user_id=customer.id
             WHERE customer.id=:id AND customer.role="customer" LIMIT 1'
        );
        $customer->execute(['id' => $customerId]);
        $selected = $customer->fetch();
        if (!$selected) throw new RuntimeException('Customer account not found.');

        if ($action === 'set_customer_status') {
            $status = input('status') === 'inactive' ? 'inactive' : 'active';
            db()->prepare(
                'UPDATE users SET status=:status WHERE id=:id AND role="customer"'
            )->execute(['status' => $status, 'id' => $customerId]);
            if ($status === 'inactive') {
                db()->prepare(
                    'UPDATE music_customer_account_state
                     SET auth_version=auth_version+1 WHERE user_id=:user_id'
                )->execute(['user_id' => $customerId]);
            }
            log_activity('music_customer_status_updated', 'user', $customerId, [
                'status' => $status,
            ]);
            flash('success', 'Customer account status updated.');
        } elseif ($action === 'issue_customer_reset') {
            $reset = music_customer_issue_admin_reset(
                $customerId,
                (int)$user['id']
            );
            flash('success', $reset['sent']
                ? 'A one-time password-reset link was emailed to the customer. No temporary password was created or displayed.'
                : 'Email delivery was unavailable. Copy this one-time 30-minute reset link now: ' . $reset['url']);
        } elseif ($action === 'send_customer_verification') {
            $state = music_customer_state($customerId, true);
            if (!empty($state['email_verified_at'])) {
                throw new RuntimeException('This customer email is already verified.');
            }
            $delivery = music_customer_send_verification_link($customerId);
            if (!$delivery['sent']) {
                throw new RuntimeException($delivery['rate_limited']
                    ? 'A verification link was requested too recently.'
                    : 'The verification email could not be sent.');
            }
            flash('success', 'A one-time verification link was emailed to the customer.');
        } elseif ($action === 'revoke_customer_sessions') {
            db()->prepare(
                'UPDATE music_customer_account_state
                 SET auth_version=auth_version+1 WHERE user_id=:user_id'
            )->execute(['user_id' => $customerId]);
            db()->prepare(
                'UPDATE music_customer_account_tokens
                 SET consumed_at=COALESCE(consumed_at,UTC_TIMESTAMP())
                 WHERE user_id=:user_id AND consumed_at IS NULL'
            )->execute(['user_id' => $customerId]);
            log_activity('music_customer_sessions_revoked', 'user', $customerId);
            flash('success', 'Customer sessions and unused account links were revoked.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('portal/music-customers.php?customer=' . $customerId);
}

$query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$params = [];
$where = '';
if ($query !== '') {
    $where = ' AND (customer.display_name LIKE :search_name OR customer.email LIKE :search_email)';
    $params = [
        'search_name' => '%' . $query . '%',
        'search_email' => '%' . $query . '%',
    ];
}
$statement = db()->prepare(
    'SELECT customer.id,customer.display_name,customer.email,customer.status,
            customer.last_login_at,customer.created_at,
            state.email_verified_at,state.pending_email,state.auth_version,
            state.last_verification_sent_at,state.last_password_reset_at,
            COUNT(DISTINCT playlist.id) AS playlist_count,
            COUNT(item.track_id) AS saved_track_count
     FROM users customer
     LEFT JOIN music_customer_account_state state ON state.user_id=customer.id
     LEFT JOIN music_customer_playlists playlist
       ON playlist.customer_user_id=customer.id
      AND playlist.status="active"
     LEFT JOIN music_customer_playlist_tracks item
       ON item.playlist_id=playlist.id
     WHERE customer.role="customer"' . $where . '
     GROUP BY customer.id,state.user_id
     ORDER BY FIELD(customer.status,"active","inactive"),customer.created_at DESC
     LIMIT 500'
);
$statement->execute($params);
$customers = $statement->fetchAll();

$selectedId = query_int('customer');
$selectedCustomer = null;
foreach ($customers as $customer) {
    if ((int)$customer['id'] === $selectedId) {
        $selectedCustomer = $customer;
        break;
    }
}
if ($selectedId > 0 && !$selectedCustomer) {
    $selectedStatement = db()->prepare(
        'SELECT customer.id,customer.display_name,customer.email,customer.status,
                customer.last_login_at,customer.created_at,
                state.email_verified_at,state.pending_email,state.auth_version,
                state.last_verification_sent_at,state.last_password_reset_at,
                (SELECT COUNT(*) FROM music_customer_playlists playlist
                 WHERE playlist.customer_user_id=customer.id AND playlist.status="active") AS playlist_count,
                (SELECT COUNT(*) FROM music_customer_playlist_tracks item
                 JOIN music_customer_playlists playlist ON playlist.id=item.playlist_id
                 WHERE playlist.customer_user_id=customer.id AND playlist.status="active") AS saved_track_count
         FROM users customer
         LEFT JOIN music_customer_account_state state ON state.user_id=customer.id
         WHERE customer.id=:id AND customer.role="customer" LIMIT 1'
    );
    $selectedStatement->execute(['id' => $selectedId]);
    $selectedCustomer = $selectedStatement->fetch() ?: null;
}

$flashes = pull_flashes();
portal_header('Music Customers', 'music', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q21.css?v=20260802-v66Q21'))?>">
<div class="page-actions">
    <a class="button" href="<?=e(app_url('portal/admin.php?view=music&section=customers'))?>">Back to Music Library</a>
    <a class="button" href="<?=e(app_url('music-library.php'))?>" target="_blank" rel="noopener">Open public library</a>
</div>
<?php foreach ($flashes as $flash): ?>
    <div class="alert alert-<?=e($flash['type'])?>" role="<?=$flash['type'] === 'error' ? 'alert' : 'status'?>"><?=e($flash['message'])?></div>
<?php endforeach; ?>
<div class="stats-grid">
    <article class="stat-card"><span>Customer accounts</span><strong><?=count($customers)?></strong><small>Current search result</small></article>
    <article class="stat-card"><span>Active customers</span><strong><?=count(array_filter($customers, static fn(array $row): bool => $row['status'] === 'active'))?></strong><small>Can sign in</small></article>
    <article class="stat-card"><span>Verified emails</span><strong><?=count(array_filter($customers, static fn(array $row): bool => !empty($row['email_verified_at'])))?></strong><small>Confirmed listener identities</small></article>
    <article class="stat-card"><span>Saved tracks</span><strong><?=array_sum(array_map(static fn(array $row): int => (int)$row['saved_track_count'], $customers))?></strong><small>Playlist entries</small></article>
</div>
<form method="get" class="form-panel" style="margin-bottom:18px">
    <input type="hidden" name="view" value="customers">
    <div class="form-grid">
        <label class="field full"><span>Search customer name or email</span><input type="search" name="q" maxlength="100" value="<?=e($query)?>"></label>
    </div>
    <div class="form-footer"><button class="button button-primary" type="submit">Search customers</button><?php if ($query !== ''): ?><a class="button" href="<?=e(app_url('portal/music-customers.php'))?>">Clear</a><?php endif; ?></div>
</form>
<div class="dashboard-grid">
    <section class="panel">
        <header class="panel-header"><h2>Customer accounts</h2><span><?=count($customers)?> shown</span></header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Customer</th><th>Status</th><th>Verification</th><th>Playlists</th><th>Saved tracks</th><th>Last login</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><a href="<?=e(app_url('portal/music-customers.php?customer=' . (int)$customer['id']))?>"><?=e($customer['display_name'])?></a><br><small><?=e($customer['email'])?></small></td>
                        <td><span class="status status-<?=e($customer['status'])?>"><?=e($customer['status'])?></span></td>
                        <td><?=empty($customer['email_verified_at']) ? 'Pending' : 'Verified'?></td>
                        <td><?=(int)$customer['playlist_count']?></td>
                        <td><?=(int)$customer['saved_track_count']?></td>
                        <td><?=e(format_datetime($customer['last_login_at']))?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$customers): ?>
                    <tr><td colspan="6"><div class="empty-state">No customer accounts matched the search.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <?php if (!$selectedCustomer): ?>
            <div class="panel"><div class="empty-state">Select a customer account to manage access.</div></div>
        <?php else: ?>
            <section class="panel">
                <header class="panel-header"><h2><?=e($selectedCustomer['display_name'])?></h2><span>Customer</span></header>
                <div class="panel-body">
                    <p><strong><?=e($selectedCustomer['email'])?></strong><br><small>Joined <?=e(format_date($selectedCustomer['created_at']))?></small></p>
                    <p><?= (int)$selectedCustomer['playlist_count'] ?> playlists · <?= (int)$selectedCustomer['saved_track_count'] ?> saved tracks</p>
                    <div class="music-customer-admin-lifecycle">
                        <span>Email verification <strong><?=empty($selectedCustomer['email_verified_at']) ? 'Pending' : e(format_datetime($selectedCustomer['email_verified_at']))?></strong></span>
                        <span>Pending email <strong><?=e($selectedCustomer['pending_email'] ?: 'None')?></strong></span>
                        <span>Session version <strong><?=(int)($selectedCustomer['auth_version'] ?? 1)?></strong></span>
                        <span>Last verification sent <strong><?=e(format_datetime($selectedCustomer['last_verification_sent_at']))?></strong></span>
                        <span>Last password reset <strong><?=e(format_datetime($selectedCustomer['last_password_reset_at']))?></strong></span>
                    </div>
                    <form method="post" class="form-panel" style="box-shadow:none;padding:0">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="set_customer_status">
                        <input type="hidden" name="customer_id" value="<?=(int)$selectedCustomer['id']?>">
                        <label class="field"><span>Account status</span><select name="status"><option value="active" <?=$selectedCustomer['status'] === 'active' ? 'selected' : ''?>>Active</option><option value="inactive" <?=$selectedCustomer['status'] === 'inactive' ? 'selected' : ''?>>Inactive</option></select></label>
                        <div class="form-footer"><button class="button button-primary" type="submit">Save status</button></div>
                    </form>
                    <div class="stack" style="margin-top:14px">
                        <?php if (empty($selectedCustomer['email_verified_at'])): ?>
                            <form method="post">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="send_customer_verification">
                                <input type="hidden" name="customer_id" value="<?=(int)$selectedCustomer['id']?>">
                                <button class="button" type="submit">Send verification link</button>
                            </form>
                        <?php endif; ?>
                        <form method="post">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="issue_customer_reset">
                            <input type="hidden" name="customer_id" value="<?=(int)$selectedCustomer['id']?>">
                            <button class="button button-danger" type="submit" data-confirm="Issue a one-time reset link for this customer?">Issue secure reset link</button>
                        </form>
                        <form method="post">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="revoke_customer_sessions">
                            <input type="hidden" name="customer_id" value="<?=(int)$selectedCustomer['id']?>">
                            <button class="button" type="submit" data-confirm="Revoke all customer sessions and unused account links?">Revoke sessions and links</button>
                        </form>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </section>
</div>
<?php portal_footer(); ?>

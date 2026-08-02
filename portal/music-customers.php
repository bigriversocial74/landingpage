<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_role('admin');
if (!music_customer_accounts_ready()) {
    portal_header('Music Customers', 'music', $user);
    ?>
    <section class="panel">
        <div class="panel-body">
            <div class="alert alert-warning">Import <code>database/music_customer_accounts_v66q20.sql</code> before managing customer accounts.</div>
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
            'SELECT id,email,display_name,status
             FROM users WHERE id=:id AND role="customer" LIMIT 1'
        );
        $customer->execute(['id' => $customerId]);
        $selected = $customer->fetch();
        if (!$selected) throw new RuntimeException('Customer account not found.');

        if ($action === 'set_customer_status') {
            $status = input('status') === 'inactive' ? 'inactive' : 'active';
            db()->prepare(
                'UPDATE users SET status=:status WHERE id=:id AND role="customer"'
            )->execute(['status' => $status, 'id' => $customerId]);
            log_activity('music_customer_status_updated', 'user', $customerId, [
                'status' => $status,
            ]);
            flash('success', 'Customer account status updated.');
        } elseif ($action === 'reset_customer_password') {
            $password = random_password();
            db()->prepare(
                'UPDATE users
                 SET password_hash=:password_hash,must_change_password=1
                 WHERE id=:id AND role="customer"'
            )->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $customerId,
            ]);
            log_activity('music_customer_password_reset', 'user', $customerId);
            flash('success', 'Customer temporary password: ' . $password . ' — copy it now.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('portal/music-customers.php?customer=' . $customerId);
}

$customers = db()->query(
    'SELECT customer.id,customer.display_name,customer.email,customer.status,
            customer.last_login_at,customer.created_at,
            COUNT(DISTINCT playlist.id) AS playlist_count,
            COUNT(item.track_id) AS saved_track_count
     FROM users customer
     LEFT JOIN music_customer_playlists playlist
       ON playlist.customer_user_id=customer.id
      AND playlist.status="active"
     LEFT JOIN music_customer_playlist_tracks item
       ON item.playlist_id=playlist.id
     WHERE customer.role="customer"
     GROUP BY customer.id
     ORDER BY FIELD(customer.status,"active","inactive"),customer.created_at DESC'
)->fetchAll();

$selectedId = query_int('customer');
$selectedCustomer = null;
foreach ($customers as $customer) {
    if ((int)$customer['id'] === $selectedId) {
        $selectedCustomer = $customer;
        break;
    }
}

$flashes = pull_flashes();
portal_header('Music Customers', 'music', $user);
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))?>">
<div class="page-actions">
    <a class="button" href="<?=e(app_url('portal/admin.php?view=music&section=customers'))?>">Back to Music Library</a>
    <a class="button" href="<?=e(app_url('music-library.php'))?>" target="_blank" rel="noopener">Open public library</a>
</div>
<?php foreach ($flashes as $flash): ?>
    <div class="alert alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div>
<?php endforeach; ?>
<div class="stats-grid">
    <article class="stat-card"><span>Customer accounts</span><strong><?=count($customers)?></strong><small>Registered listeners</small></article>
    <article class="stat-card"><span>Active customers</span><strong><?=count(array_filter($customers, static fn(array $row): bool => $row['status'] === 'active'))?></strong><small>Can sign in</small></article>
    <article class="stat-card"><span>Private playlists</span><strong><?=array_sum(array_map(static fn(array $row): int => (int)$row['playlist_count'], $customers))?></strong><small>Customer owned</small></article>
    <article class="stat-card"><span>Saved tracks</span><strong><?=array_sum(array_map(static fn(array $row): int => (int)$row['saved_track_count'], $customers))?></strong><small>Playlist entries</small></article>
</div>
<div class="dashboard-grid">
    <section class="panel">
        <header class="panel-header"><h2>Customer accounts</h2><span><?=count($customers)?> total</span></header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Customer</th><th>Status</th><th>Playlists</th><th>Saved tracks</th><th>Last login</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><a href="<?=e(app_url('portal/music-customers.php?customer=' . (int)$customer['id']))?>"><?=e($customer['display_name'])?></a><br><small><?=e($customer['email'])?></small></td>
                        <td><span class="status status-<?=e($customer['status'])?>"><?=e($customer['status'])?></span></td>
                        <td><?=(int)$customer['playlist_count']?></td>
                        <td><?=(int)$customer['saved_track_count']?></td>
                        <td><?=e(format_datetime($customer['last_login_at']))?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$customers): ?>
                    <tr><td colspan="5"><div class="empty-state">No customer accounts have registered.</div></td></tr>
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
                    <form method="post" class="form-panel" style="box-shadow:none;padding:0">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="set_customer_status">
                        <input type="hidden" name="customer_id" value="<?=(int)$selectedCustomer['id']?>">
                        <label class="field"><span>Account status</span><select name="status"><option value="active" <?=$selectedCustomer['status'] === 'active' ? 'selected' : ''?>>Active</option><option value="inactive" <?=$selectedCustomer['status'] === 'inactive' ? 'selected' : ''?>>Inactive</option></select></label>
                        <div class="form-footer"><button class="button button-primary" type="submit">Save status</button></div>
                    </form>
                    <form method="post" style="margin-top:14px">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="reset_customer_password">
                        <input type="hidden" name="customer_id" value="<?=(int)$selectedCustomer['id']?>">
                        <button class="button button-danger" type="submit" data-confirm="Reset this customer's password?">Reset password</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </section>
</div>
<?php portal_footer(); ?>

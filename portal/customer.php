<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/music-library.php';

$user = require_music_customer_v21();
$state = (array)$user['music_customer_state'];
$mustChangePassword = (int)($user['must_change_password'] ?? 0) === 1;
$view = (string)($_GET['view'] ?? 'playlists');
if (!in_array($view, ['playlists', 'library', 'account'], true)) {
    $view = 'playlists';
}
if ($mustChangePassword) {
    $view = 'account';
}

if (is_post()) {
    verify_csrf();
    if (!same_origin_request()) {
        http_response_code(403);
        exit('Invalid request origin.');
    }
    enforce_authenticated_action_limit($user);
    $action = input('action');

    try {
        if ($mustChangePassword && $action !== 'change_password') {
            throw new RuntimeException(
                'Change your temporary password before using the music account.'
            );
        }

        if ($action === 'create_playlist') {
            $playlistId = music_customer_create_playlist_v21(
                (int)$user['id'],
                input('title'),
                input('description')
            );
            flash('success', 'Playlist created.');
            redirect('portal/customer.php?view=playlists&id=' . $playlistId);
        }

        if ($action === 'update_playlist') {
            $playlistId = int_input('playlist_id');
            music_customer_update_playlist_v21(
                $playlistId,
                (int)$user['id'],
                input('title'),
                input('description')
            );
            flash('success', 'Playlist updated.');
            redirect('portal/customer.php?view=playlists&id=' . $playlistId);
        }

        if ($action === 'delete_playlist') {
            if (!hash_equals('DELETE', input('delete_confirmation'))) {
                throw new RuntimeException('Type DELETE to confirm playlist deletion.');
            }
            music_customer_delete_playlist_v21(
                int_input('playlist_id'),
                (int)$user['id']
            );
            flash('success', 'Playlist deleted.');
            redirect('portal/customer.php?view=playlists');
        }

        if ($action === 'add_track') {
            $playlistId = int_input('playlist_id');
            $added = music_customer_add_track_v21(
                $playlistId,
                int_input('track_id'),
                (int)$user['id']
            );
            flash(
                'success',
                $added
                    ? 'Track added to your playlist.'
                    : 'That track is already in the selected playlist.'
            );
            $query = mb_substr(trim(input('return_query')), 0, 100);
            $page = max(1, int_input('return_page', 1));
            redirect(
                'portal/customer.php?view=library'
                . ($query !== '' ? '&q=' . rawurlencode($query) : '')
                . ($page > 1 ? '&page=' . $page : '')
            );
        }

        if ($action === 'remove_track') {
            $playlistId = int_input('playlist_id');
            $removed = music_customer_remove_track_v21(
                $playlistId,
                int_input('track_id'),
                (int)$user['id']
            );
            flash(
                'success',
                $removed ? 'Track removed from your playlist.' : 'The track was already removed.'
            );
            redirect('portal/customer.php?view=playlists&id=' . $playlistId);
        }

        if ($action === 'move_track') {
            $playlistId = int_input('playlist_id');
            music_customer_move_track_v21(
                $playlistId,
                int_input('track_id'),
                (int)$user['id'],
                input('direction')
            );
            flash('success', 'Playlist order updated.');
            redirect('portal/customer.php?view=playlists&id=' . $playlistId);
        }

        if ($action === 'save_account_profile') {
            $requestedEmail = strtolower(trim(input('email')));
            $emailPending = false;
            if (!hash_equals(strtolower((string)$user['email']), $requestedEmail)) {
                $result = music_customer_request_email_change_final(
                    $user,
                    $requestedEmail,
                    (string)($_POST['current_password'] ?? '')
                );
                $emailPending = (bool)$result['pending'];
            }

            save_account_profile(
                (int)$user['id'],
                [
                    'display_name' => input('display_name'),
                    'email' => (string)$user['email'],
                    'company' => '',
                    'phone' => '',
                ],
                $_FILES['profile_image'] ?? null,
                isset($_POST['remove_profile_image'])
            );
            flash(
                'success',
                $emailPending
                    ? 'Profile saved. Confirm the one-time link sent to the new email before it replaces the current address.'
                    : 'Account settings updated.'
            );
            redirect('portal/customer.php?view=account');
        }

        if ($action === 'change_password') {
            music_customer_change_password_v21(
                $user,
                (string)($_POST['current_password'] ?? ''),
                (string)($_POST['new_password'] ?? ''),
                (string)($_POST['confirm_password'] ?? '')
            );
            flash(
                'success',
                'Password updated. Other customer sessions and unused account links were revoked.'
            );
            redirect('portal/customer.php?view=account');
        }

        if ($action === 'delete_account') {
            music_customer_delete_account_v21(
                $user,
                (string)($_POST['current_password'] ?? ''),
                input('delete_confirmation')
            );
            logout_user();
            redirect('music-library.php?customer_account=deleted');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        $redirect = 'portal/customer.php?view=' . $view;
        $playlistId = int_input('playlist_id');
        if ($playlistId > 0 && $view === 'playlists') {
            $redirect .= '&id=' . $playlistId;
        }
        redirect($redirect);
    }
}

$playlists = music_customer_playlists((int)$user['id']);
$selectedId = query_int('id');
if ($selectedId <= 0 && $playlists) {
    $selectedId = (int)$playlists[0]['id'];
}
$selectedPlaylist = $selectedId > 0
    ? music_customer_visible_playlist($selectedId, (int)$user['id'])
    : null;
$trackPage = music_customer_public_track_page_final(
    (string)($_GET['q'] ?? ''),
    query_int('page', 1),
    25
);
$tracks = $trackPage['tracks'];
$flashes = pull_flashes();
$title = match ($view) {
    'library' => 'Add Music',
    'account' => 'Account Settings',
    default => 'My Playlists',
};
$pendingEmail = trim((string)($state['pending_email'] ?? ''));
$firstPaginationPage = max(1, (int)$trackPage['page'] - 3);
$lastPaginationPage = min((int)$trackPage['pages'], $firstPaginationPage + 6);
$firstPaginationPage = max(1, $lastPaginationPage - 6);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=e($title)?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))?>">
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q21.css?v=20260802-v66Q21'))?>">
</head>
<body class="music-customer-body">
<a class="music-customer-skip-link" href="#customer-content">Skip to account content</a>
<div class="music-customer-shell">
<header class="music-customer-header">
    <a class="music-customer-brand" href="<?=e(app_url('music-library.php'))?>">
        <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
    </a>
    <nav aria-label="Customer account">
        <?php if (!$mustChangePassword): ?>
            <a class="<?=$view === 'playlists' ? 'is-active' : ''?>" <?=$view === 'playlists' ? 'aria-current="page"' : ''?> href="<?=e(app_url('portal/customer.php?view=playlists'))?>">My Playlists</a>
            <a class="<?=$view === 'library' ? 'is-active' : ''?>" <?=$view === 'library' ? 'aria-current="page"' : ''?> href="<?=e(app_url('portal/customer.php?view=library'))?>">Add Music</a>
            <a href="<?=e(app_url('music-library.php'))?>">Music Library</a>
        <?php endif; ?>
        <a class="<?=$view === 'account' ? 'is-active' : ''?>" <?=$view === 'account' ? 'aria-current="page"' : ''?> href="<?=e(app_url('portal/customer.php?view=account'))?>"><?=e($user['display_name'])?></a>
        <a class="music-customer-logout" href="<?=e(app_url('portal/logout.php'))?>">Sign out</a>
    </nav>
</header>

<main class="music-customer-main" id="customer-content">
    <section class="music-customer-hero">
        <div>
            <span>Customer music account</span>
            <h1><?=e($title)?></h1>
            <p><?= $mustChangePassword
                ? 'Change the temporary password before using playlists or browsing your account library.'
                : ($view === 'library'
                    ? 'Search published tracks and add them to any private playlist.'
                    : ($view === 'account'
                        ? 'Manage your verified listener identity, password, profile photo, and account data.'
                        : 'Create private playlists, arrange the track order, and keep your music organized.')) ?></p>
        </div>
        <?php if (!$mustChangePassword && $view !== 'library'): ?>
            <a href="<?=e(app_url('portal/customer.php?view=library'))?>">Browse tracks</a>
        <?php endif; ?>
    </section>

    <?php foreach ($flashes as $flash): ?>
        <div class="music-customer-alert music-customer-alert-<?=e($flash['type'])?>" role="<?=$flash['type'] === 'error' ? 'alert' : 'status'?>"><?=e($flash['message'])?></div>
    <?php endforeach; ?>
    <?php if ($mustChangePassword): ?>
        <div class="music-customer-alert music-customer-alert-error" role="alert">Your password was reset by an administrator. Choose a new password to continue.</div>
    <?php endif; ?>

    <?php if ($view === 'playlists'): ?>
        <div class="music-customer-grid">
            <section class="music-customer-card">
                <header><h2>Your playlists</h2><span><?=count($playlists)?> total</span></header>
                <div class="music-customer-card-body">
                    <div class="music-customer-list">
                        <?php foreach ($playlists as $playlist): ?>
                            <a class="<?=(int)$playlist['id'] === $selectedId ? 'is-active' : ''?>" <?=(int)$playlist['id'] === $selectedId ? 'aria-current="page"' : ''?> href="<?=e(app_url('portal/customer.php?view=playlists&id=' . (int)$playlist['id']))?>">
                                <span><strong><?=e($playlist['title'])?></strong><small><?=e($playlist['description'] ?: 'Private playlist')?></small></span>
                                <b><?=(int)$playlist['track_count']?></b>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$playlists): ?>
                        <div class="music-customer-empty">No playlists yet. Create your first one below.</div>
                    <?php endif; ?>
                    <form method="post" class="music-customer-form" style="margin-top:18px">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="create_playlist">
                        <label class="music-customer-field"><span>New playlist name</span><input name="title" maxlength="120" <?=isset($_GET['new']) ? 'autofocus' : ''?> required></label>
                        <label class="music-customer-field"><span>Description</span><textarea name="description" maxlength="1000"></textarea></label>
                        <button class="music-customer-button" type="submit">Create playlist</button>
                    </form>
                </div>
            </section>

            <section class="music-customer-card">
                <header><h2><?=e($selectedPlaylist['title'] ?? 'Playlist details')?></h2><?php if ($selectedPlaylist): ?><span><?=count($selectedPlaylist['tracks'])?> tracks</span><?php endif; ?></header>
                <div class="music-customer-card-body">
                    <?php if (!$selectedPlaylist): ?>
                        <div class="music-customer-empty">Select or create a playlist.</div>
                    <?php else: ?>
                        <div class="music-customer-playlist-summary">
                            <div><h2><?=e($selectedPlaylist['title'])?></h2><p><?=e($selectedPlaylist['description'] ?: 'Private customer playlist')?></p></div>
                            <a class="music-customer-button music-customer-button-secondary" href="<?=e(app_url('portal/customer.php?view=library'))?>">Add tracks</a>
                        </div>
                        <?php if ($selectedPlaylist['tracks']): ?>
                            <table class="music-customer-table">
                                <caption><?=count($selectedPlaylist['tracks'])?> tracks in playlist order</caption>
                                <thead><tr><th>Track</th><th>Album</th><th>Time</th><th>Order and remove</th></tr></thead>
                                <tbody>
                                <?php $trackCount = count($selectedPlaylist['tracks']); ?>
                                <?php foreach ($selectedPlaylist['tracks'] as $index => $track): ?>
                                    <tr>
                                        <td data-label="Track"><div class="music-customer-track"><img src="<?=e(music_cover_url('track', (int)$track['id']))?>" alt=""><span><strong><?=e($track['title'])?></strong><span><?=e($track['artist_name'])?></span></span></div></td>
                                        <td data-label="Album"><?=e($track['album_title'] ?: 'Single')?></td>
                                        <td data-label="Time"><?=e(music_duration_label(isset($track['duration_seconds']) ? (int)$track['duration_seconds'] : null))?></td>
                                        <td data-label="Actions"><div class="music-customer-track-actions">
                                            <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="move_track"><input type="hidden" name="playlist_id" value="<?=(int)$selectedPlaylist['id']?>"><input type="hidden" name="track_id" value="<?=(int)$track['id']?>"><input type="hidden" name="direction" value="up"><button class="music-customer-icon-button" type="submit" aria-label="Move <?=e($track['title'])?> up" <?=$index === 0 ? 'disabled' : ''?>>↑</button></form>
                                            <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="move_track"><input type="hidden" name="playlist_id" value="<?=(int)$selectedPlaylist['id']?>"><input type="hidden" name="track_id" value="<?=(int)$track['id']?>"><input type="hidden" name="direction" value="down"><button class="music-customer-icon-button" type="submit" aria-label="Move <?=e($track['title'])?> down" <?=$index === $trackCount - 1 ? 'disabled' : ''?>>↓</button></form>
                                            <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="remove_track"><input type="hidden" name="playlist_id" value="<?=(int)$selectedPlaylist['id']?>"><input type="hidden" name="track_id" value="<?=(int)$track['id']?>"><button class="music-customer-icon-button" type="submit" aria-label="Remove <?=e($track['title'])?> from playlist">Remove</button></form>
                                        </div></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="music-customer-empty">This playlist is empty. Add tracks from the library.</div>
                        <?php endif; ?>
                        <div class="music-customer-account-grid" style="margin-top:18px">
                            <form method="post" class="music-customer-form">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="update_playlist">
                                <input type="hidden" name="playlist_id" value="<?=(int)$selectedPlaylist['id']?>">
                                <label class="music-customer-field"><span>Playlist name</span><input name="title" maxlength="120" value="<?=e($selectedPlaylist['title'])?>" required></label>
                                <label class="music-customer-field"><span>Description</span><textarea name="description" maxlength="1000"><?=e($selectedPlaylist['description'] ?? '')?></textarea></label>
                                <button class="music-customer-button" type="submit">Save playlist</button>
                            </form>
                            <form method="post" class="music-customer-form">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="delete_playlist">
                                <input type="hidden" name="playlist_id" value="<?=(int)$selectedPlaylist['id']?>">
                                <p class="music-customer-inline-help">Deleting this playlist removes only its saved track list.</p>
                                <label class="music-customer-field"><span>Type DELETE to confirm</span><input name="delete_confirmation" pattern="DELETE" autocomplete="off" required></label>
                                <button class="music-customer-button music-customer-button-danger" type="submit">Delete playlist</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <?php if ($view === 'library'): ?>
        <section class="music-customer-card">
            <header><h2>Published tracks</h2><span><?=number_format((int)$trackPage['total'])?> available</span></header>
            <div class="music-customer-card-body">
                <div class="music-customer-toolbar">
                    <form method="get" class="music-customer-search">
                        <input type="hidden" name="view" value="library">
                        <label><span>Search music</span><input type="search" name="q" maxlength="100" value="<?=e($trackPage['query'])?>" placeholder="Track, artist, album, or genre"></label>
                        <button class="music-customer-button" type="submit">Search</button>
                    </form>
                    <span class="music-customer-result-summary">Page <?=(int)$trackPage['page']?> of <?=(int)$trackPage['pages']?></span>
                </div>
                <?php if (!$playlists): ?>
                    <div class="music-customer-alert" role="status">Create a playlist before adding tracks. <a href="<?=e(app_url('portal/customer.php?view=playlists&new=1'))?>">Create playlist</a></div>
                <?php endif; ?>
                <?php if ($tracks): ?>
                    <table class="music-customer-table">
                        <caption>Search results from the public Music Library</caption>
                        <thead><tr><th>Track</th><th>Album</th><th>Time</th><th>Add to playlist</th></tr></thead>
                        <tbody>
                        <?php foreach ($tracks as $track): ?>
                            <tr>
                                <td data-label="Track"><div class="music-customer-track"><img src="<?=e($track['cover_url'])?>" alt=""><span><strong><?=e($track['title'])?></strong><span><?=e($track['artist'])?></span></span></div></td>
                                <td data-label="Album"><?=e($track['album'] ?: 'Single')?></td>
                                <td data-label="Time"><?=e($track['duration_label'])?></td>
                                <td data-label="Add to playlist">
                                    <?php if ($playlists): ?>
                                        <form method="post" class="music-customer-inline-form">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="add_track">
                                            <input type="hidden" name="track_id" value="<?=(int)$track['id']?>">
                                            <input type="hidden" name="return_query" value="<?=e($trackPage['query'])?>">
                                            <input type="hidden" name="return_page" value="<?=(int)$trackPage['page']?>">
                                            <label class="music-customer-visually-hidden" for="playlist-<?=(int)$track['id']?>">Playlist for <?=e($track['title'])?></label>
                                            <select id="playlist-<?=(int)$track['id']?>" name="playlist_id" required><?php foreach ($playlists as $playlist): ?><option value="<?=(int)$playlist['id']?>"><?=e($playlist['title'])?></option><?php endforeach; ?></select>
                                            <button type="submit">Add</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ((int)$trackPage['pages'] > 1): ?>
                        <nav class="music-customer-pagination" aria-label="Music search pages">
                            <?php if ((int)$trackPage['page'] > 1): ?><a href="<?=e(app_url('portal/customer.php?view=library&page=' . ((int)$trackPage['page'] - 1) . ($trackPage['query'] !== '' ? '&q=' . rawurlencode($trackPage['query']) : '')))?>">Previous</a><?php endif; ?>
                            <?php for ($pageNumber = $firstPaginationPage; $pageNumber <= $lastPaginationPage; $pageNumber++): ?>
                                <?php if ($pageNumber === (int)$trackPage['page']): ?><span aria-current="page"><?=$pageNumber?></span><?php else: ?><a href="<?=e(app_url('portal/customer.php?view=library&page=' . $pageNumber . ($trackPage['query'] !== '' ? '&q=' . rawurlencode($trackPage['query']) : '')))?>"><?=$pageNumber?></a><?php endif; ?>
                            <?php endfor; ?>
                            <?php if ((int)$trackPage['page'] < (int)$trackPage['pages']): ?><a href="<?=e(app_url('portal/customer.php?view=library&page=' . ((int)$trackPage['page'] + 1) . ($trackPage['query'] !== '' ? '&q=' . rawurlencode($trackPage['query']) : '')))?>">Next</a><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="music-customer-empty">No published tracks matched your search.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($view === 'account'): ?>
        <dl class="music-customer-meta-list">
            <div><dt>Account type</dt><dd>Customer listener</dd></div>
            <div><dt>Email status</dt><dd><?=empty($state['email_verified_at']) ? 'Not verified' : 'Verified ' . e(format_date((string)$state['email_verified_at']))?></dd></div>
            <div><dt>Last login</dt><dd><?=e(format_datetime($user['last_login_at'] ?? null))?></dd></div>
        </dl>
        <div class="music-customer-account-grid">
            <?php if (!$mustChangePassword): ?>
                <section class="music-customer-card">
                    <header><h2>Listener profile</h2><span>Customer</span></header>
                    <div class="music-customer-card-body">
                        <?php if ($pendingEmail !== ''): ?><div class="music-customer-notice">Pending email: <strong><?=e($pendingEmail)?></strong>. It will replace the current address only after confirmation.</div><?php endif; ?>
                        <form method="post" enctype="multipart/form-data" class="music-customer-form" style="margin-top:14px">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="save_account_profile">
                            <label class="music-customer-field"><span>Name</span><input name="display_name" maxlength="160" value="<?=e($user['display_name'])?>" autocomplete="name" required></label>
                            <label class="music-customer-field"><span>Email</span><input type="email" name="email" maxlength="190" value="<?=e($user['email'])?>" autocomplete="email" required><small>Changing email sends a one-time confirmation to the new address.</small></label>
                            <label class="music-customer-field"><span>Current password for email changes</span><input type="password" name="current_password" autocomplete="current-password"><small>Leave blank when the email address is unchanged.</small></label>
                            <label class="music-customer-field"><span>Profile photo</span><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                            <?php if (!empty($user['profile_image_stored_name'])): ?><label class="music-customer-field"><span><input type="checkbox" name="remove_profile_image" value="1"> Remove current photo</span></label><?php endif; ?>
                            <button class="music-customer-button" type="submit">Save profile</button>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
            <section class="music-customer-card">
                <header><h2>Change password</h2><span>Security</span></header>
                <div class="music-customer-card-body">
                    <p class="music-customer-card-subheading">Changing the password revokes other customer sessions and every unused verification or reset link.</p>
                    <form method="post" class="music-customer-form">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="change_password">
                        <label class="music-customer-field"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                        <label class="music-customer-field"><span>New password</span><input type="password" name="new_password" maxlength="256" autocomplete="new-password" required></label>
                        <label class="music-customer-field"><span>Confirm new password</span><input type="password" name="confirm_password" maxlength="256" autocomplete="new-password" required></label>
                        <button class="music-customer-button" type="submit">Update password</button>
                    </form>
                    <?php if (music_customer_password_recovery_enabled()): ?><p class="music-customer-inline-help" style="margin-top:14px"><a href="<?=e(app_url('portal/customer-password.php'))?>">Request a password-reset email</a></p><?php endif; ?>
                </div>
            </section>
        </div>
        <?php if (!$mustChangePassword): ?>
            <section class="music-customer-card music-customer-danger-zone" style="margin-top:18px">
                <header><h2>Delete customer account</h2><span>Permanent</span></header>
                <div class="music-customer-card-body">
                    <p class="music-customer-card-subheading">This permanently deletes the customer login, private playlists, playlist entries, account tokens, and profile record. Published music remains unchanged.</p>
                    <form method="post" class="music-customer-form">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="delete_account">
                        <label class="music-customer-field"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                        <label class="music-customer-field"><span>Type DELETE to confirm</span><input name="delete_confirmation" pattern="DELETE" autocomplete="off" required></label>
                        <button class="music-customer-button music-customer-button-danger" type="submit">Delete my customer account</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
</div>
</body>
</html>

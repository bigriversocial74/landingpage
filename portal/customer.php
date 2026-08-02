<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/music-library.php';

$user = require_music_customer();
$view = (string)($_GET['view'] ?? 'playlists');
if (!in_array($view, ['playlists', 'library', 'account'], true)) {
    $view = 'playlists';
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
        if ($action === 'create_playlist') {
            $playlistId = music_customer_create_playlist(
                (int)$user['id'],
                input('title'),
                input('description')
            );
            flash('success', 'Playlist created.');
            redirect('portal/customer.php?view=playlists&id=' . $playlistId);
        }

        if ($action === 'update_playlist') {
            $playlistId = int_input('playlist_id');
            music_customer_update_playlist(
                $playlistId,
                (int)$user['id'],
                input('title'),
                input('description')
            );
            flash('success', 'Playlist updated.');
            redirect('portal/customer.php?view=playlists&id=' . $playlistId);
        }

        if ($action === 'delete_playlist') {
            music_customer_delete_playlist(
                int_input('playlist_id'),
                (int)$user['id']
            );
            flash('success', 'Playlist deleted.');
            redirect('portal/customer.php?view=playlists');
        }

        if ($action === 'add_track') {
            $playlistId = int_input('playlist_id');
            music_customer_add_track(
                $playlistId,
                int_input('track_id'),
                (int)$user['id']
            );
            flash('success', 'Track added to your playlist.');
            redirect('portal/customer.php?view=' . ($view === 'library' ? 'library' : 'playlists&id=' . $playlistId));
        }

        if ($action === 'remove_track') {
            $playlistId = int_input('playlist_id');
            music_customer_remove_track(
                $playlistId,
                int_input('track_id'),
                (int)$user['id']
            );
            flash('success', 'Track removed from your playlist.');
            redirect('portal/customer.php?view=playlists&id=' . $playlistId);
        }

        if ($action === 'save_account_profile') {
            save_account_profile(
                (int)$user['id'],
                [
                    'display_name' => input('display_name'),
                    'email' => input('email'),
                    'company' => '',
                    'phone' => '',
                ],
                $_FILES['profile_image'] ?? null,
                isset($_POST['remove_profile_image'])
            );
            flash('success', 'Account settings updated.');
            redirect('portal/customer.php?view=account');
        }

        if ($action === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            $statement = db()->prepare('SELECT password_hash FROM users WHERE id=:id');
            $statement->execute(['id' => $user['id']]);
            if (!password_verify($current, (string)$statement->fetchColumn())) {
                throw new RuntimeException('Current password is not correct.');
            }
            $errors = password_policy_errors($new, (string)$user['email']);
            if ($errors) throw new RuntimeException(implode(' ', $errors));
            if (!hash_equals($new, $confirm)) {
                throw new RuntimeException('The new passwords do not match.');
            }

            db()->prepare(
                'UPDATE users
                 SET password_hash=:password_hash,must_change_password=0
                 WHERE id=:id AND role="customer"'
            )->execute([
                'password_hash' => password_hash($new, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
            log_activity('music_customer_password_changed', 'user', (int)$user['id']);
            flash('success', 'Password updated.');
            redirect('portal/customer.php?view=account');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        $redirect = 'portal/customer.php?view=' . $view;
        $playlistId = int_input('playlist_id');
        if ($playlistId > 0 && $view === 'playlists') $redirect .= '&id=' . $playlistId;
        redirect($redirect);
    }
}

$playlists = music_customer_playlists((int)$user['id']);
$selectedId = query_int('id');
if ($selectedId <= 0 && $playlists) $selectedId = (int)$playlists[0]['id'];
$selectedPlaylist = $selectedId > 0
    ? music_customer_playlist($selectedId, (int)$user['id'])
    : null;
$tracks = music_public_tracks();
$flashes = pull_flashes();
$title = match ($view) {
    'library' => 'Add Music',
    'account' => 'Account Settings',
    default => 'My Playlists',
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=e($title)?> — North Mountain Media</title>
<link rel="stylesheet" href="<?=e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))?>">
</head>
<body class="music-customer-body">
<div class="music-customer-shell">
    <header class="music-customer-header">
        <a class="music-customer-brand" href="<?=e(app_url('music-library.php'))?>">
            <img src="<?=e(nmm_site_logo_url())?>" alt="<?=e(nmm_site_logo_alt())?>">
        </a>
        <nav aria-label="Customer account">
            <a class="<?=$view === 'playlists' ? 'is-active' : ''?>" href="<?=e(app_url('portal/customer.php?view=playlists'))?>">My Playlists</a>
            <a class="<?=$view === 'library' ? 'is-active' : ''?>" href="<?=e(app_url('portal/customer.php?view=library'))?>">Add Music</a>
            <a href="<?=e(app_url('music-library.php'))?>">Music Library</a>
            <a class="<?=$view === 'account' ? 'is-active' : ''?>" href="<?=e(app_url('portal/customer.php?view=account'))?>"><?=e($user['display_name'])?></a>
            <a class="music-customer-logout" href="<?=e(app_url('portal/logout.php'))?>">Sign out</a>
        </nav>
    </header>

    <main class="music-customer-main">
        <section class="music-customer-hero">
            <div>
                <span>Customer music account</span>
                <h1><?=e($title)?></h1>
                <p><?= $view === 'library'
                    ? 'Add published tracks to any of your private playlists.'
                    : ($view === 'account'
                        ? 'Manage your listener profile and password.'
                        : 'Create private playlists and organize your favorite North Mountain Media tracks.') ?></p>
            </div>
            <?php if ($view !== 'library'): ?>
                <a href="<?=e(app_url('portal/customer.php?view=library'))?>">Browse tracks</a>
            <?php endif; ?>
        </section>

        <?php foreach ($flashes as $flash): ?>
            <div class="music-customer-alert music-customer-alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div>
        <?php endforeach; ?>

        <?php if ($view === 'playlists'): ?>
            <div class="music-customer-grid">
                <div class="music-customer-card">
                    <header><h2>Your playlists</h2><span><?=count($playlists)?> total</span></header>
                    <div class="music-customer-card-body">
                        <div class="music-customer-list">
                            <?php foreach ($playlists as $playlist): ?>
                                <a class="<?=(int)$playlist['id'] === $selectedId ? 'is-active' : ''?>" href="<?=e(app_url('portal/customer.php?view=playlists&id=' . (int)$playlist['id']))?>">
                                    <span>
                                        <strong><?=e($playlist['title'])?></strong>
                                        <small><?=e($playlist['description'] ?: 'Private playlist')?></small>
                                    </span>
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
                            <label class="music-customer-field">
                                <span>New playlist name</span>
                                <input name="title" maxlength="120" required>
                            </label>
                            <label class="music-customer-field">
                                <span>Description</span>
                                <textarea name="description" maxlength="1000"></textarea>
                            </label>
                            <button class="music-customer-button" type="submit">Create playlist</button>
                        </form>
                    </div>
                </div>

                <section class="music-customer-card">
                    <header>
                        <h2><?=e($selectedPlaylist['title'] ?? 'Playlist details')?></h2>
                        <?php if ($selectedPlaylist): ?><span><?=count($selectedPlaylist['tracks'])?> tracks</span><?php endif; ?>
                    </header>
                    <div class="music-customer-card-body">
                        <?php if (!$selectedPlaylist): ?>
                            <div class="music-customer-empty">Select or create a playlist.</div>
                        <?php else: ?>
                            <div class="music-customer-playlist-summary">
                                <div>
                                    <h2><?=e($selectedPlaylist['title'])?></h2>
                                    <p><?=e($selectedPlaylist['description'] ?: 'Private customer playlist')?></p>
                                </div>
                                <div class="music-customer-playlist-actions">
                                    <a class="music-customer-button music-customer-button-secondary" href="<?=e(app_url('portal/customer.php?view=library'))?>">Add tracks</a>
                                </div>
                            </div>

                            <?php if ($selectedPlaylist['tracks']): ?>
                                <table class="music-customer-table">
                                    <thead><tr><th>Track</th><th>Album</th><th>Time</th><th></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($selectedPlaylist['tracks'] as $track): ?>
                                        <tr>
                                            <td>
                                                <div class="music-customer-track">
                                                    <img src="<?=e(music_cover_url('track', (int)$track['id']))?>" alt="">
                                                    <span><strong><?=e($track['title'])?></strong><span><?=e($track['artist_name'])?></span></span>
                                                </div>
                                            </td>
                                            <td><?=e($track['album_title'] ?: 'Single')?></td>
                                            <td><?=e(music_duration_label(isset($track['duration_seconds']) ? (int)$track['duration_seconds'] : null))?></td>
                                            <td>
                                                <form method="post" class="music-customer-inline-form">
                                                    <?=csrf_field()?>
                                                    <input type="hidden" name="action" value="remove_track">
                                                    <input type="hidden" name="playlist_id" value="<?=(int)$selectedPlaylist['id']?>">
                                                    <input type="hidden" name="track_id" value="<?=(int)$track['id']?>">
                                                    <button type="submit">Remove</button>
                                                </form>
                                            </td>
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
                                <form method="post" class="music-customer-form" onsubmit="return confirm('Delete this playlist?');">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="delete_playlist">
                                    <input type="hidden" name="playlist_id" value="<?=(int)$selectedPlaylist['id']?>">
                                    <p style="margin:0;color:#6e7b89;font-size:12px;line-height:1.6">Deleting a playlist removes its saved track list. It does not remove music from the public library.</p>
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
                <header><h2>Published tracks</h2><span><?=count($tracks)?> available</span></header>
                <div class="music-customer-card-body">
                    <?php if (!$playlists): ?>
                        <div class="music-customer-alert">Create a playlist before adding tracks. <a href="<?=e(app_url('portal/customer.php?view=playlists&new=1'))?>">Create playlist</a></div>
                    <?php endif; ?>
                    <?php if ($tracks): ?>
                        <table class="music-customer-table">
                            <thead><tr><th>Track</th><th>Album</th><th>Time</th><th>Add to playlist</th></tr></thead>
                            <tbody>
                            <?php foreach ($tracks as $track): ?>
                                <tr>
                                    <td>
                                        <div class="music-customer-track">
                                            <img src="<?=e($track['cover_url'])?>" alt="">
                                            <span><strong><?=e($track['title'])?></strong><span><?=e($track['artist'])?></span></span>
                                        </div>
                                    </td>
                                    <td><?=e($track['album'] ?: 'Single')?></td>
                                    <td><?=e($track['duration_label'])?></td>
                                    <td>
                                        <?php if ($playlists): ?>
                                            <form method="post" class="music-customer-inline-form">
                                                <?=csrf_field()?>
                                                <input type="hidden" name="action" value="add_track">
                                                <input type="hidden" name="track_id" value="<?=(int)$track['id']?>">
                                                <select name="playlist_id" aria-label="Playlist" required>
                                                    <?php foreach ($playlists as $playlist): ?>
                                                        <option value="<?=(int)$playlist['id']?>"><?=e($playlist['title'])?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit">Add</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="music-customer-empty">No published tracks are available.</div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($view === 'account'): ?>
            <div class="music-customer-account-grid">
                <section class="music-customer-card">
                    <header><h2>Listener profile</h2><span>Customer</span></header>
                    <div class="music-customer-card-body">
                        <form method="post" enctype="multipart/form-data" class="music-customer-form">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="save_account_profile">
                            <label class="music-customer-field"><span>Name</span><input name="display_name" maxlength="160" value="<?=e($user['display_name'])?>" required></label>
                            <label class="music-customer-field"><span>Email</span><input type="email" name="email" maxlength="190" value="<?=e($user['email'])?>" required></label>
                            <label class="music-customer-field"><span>Profile photo</span><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                            <?php if (!empty($user['profile_image_stored_name'])): ?>
                                <label class="music-customer-field"><span><input type="checkbox" name="remove_profile_image" value="1"> Remove current photo</span></label>
                            <?php endif; ?>
                            <button class="music-customer-button" type="submit">Save profile</button>
                        </form>
                    </div>
                </section>
                <section class="music-customer-card">
                    <header><h2>Change password</h2><span>Security</span></header>
                    <div class="music-customer-card-body">
                        <form method="post" class="music-customer-form">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="change_password">
                            <label class="music-customer-field"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                            <label class="music-customer-field"><span>New password</span><input type="password" name="new_password" maxlength="256" autocomplete="new-password" required></label>
                            <label class="music-customer-field"><span>Confirm new password</span><input type="password" name="confirm_password" maxlength="256" autocomplete="new-password" required></label>
                            <button class="music-customer-button" type="submit">Update password</button>
                        </form>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>

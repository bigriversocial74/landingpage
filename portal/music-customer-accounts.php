<?php
declare(strict_types=1);

/* North Mountain Media build: 20260802-music-customer-accounts-v66Q20 */

function music_customer_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;

    try {
        $statement = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                   "music_customer_playlists",
                   "music_customer_playlist_tracks"
               )'
        );
        $tablesReady = (int)$statement->fetchColumn() === 2;

        $roleStatement = db()->query(
            'SELECT column_type
             FROM information_schema.columns
             WHERE table_schema=DATABASE()
               AND table_name="users"
               AND column_name="role"
             LIMIT 1'
        );
        $columnType = strtolower((string)$roleStatement->fetchColumn());
        $roleReady = $columnType !== '' && (
            !str_starts_with($columnType, 'enum(')
            || str_contains($columnType, "'customer'")
        );

        $available = $tablesReady && $roleReady;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function music_customer_accounts_enabled(): bool
{
    try {
        return setting('music_customer_accounts_enabled', '0') === '1';
    } catch (Throwable) {
        return false;
    }
}

function music_customer_accounts_ready(): bool
{
    return music_customer_schema_available();
}

function music_customer_accounts_active(): bool
{
    return music_customer_accounts_enabled() && music_customer_accounts_ready();
}

function music_customer_save_enabled(bool $enabled): void
{
    if ($enabled && !music_customer_accounts_ready()) {
        throw new RuntimeException(
            'Import database/music_customer_accounts_v66q20.sql before enabling customer accounts.'
        );
    }

    db()->prepare(
        'INSERT INTO settings (setting_key,setting_value)
         VALUES ("music_customer_accounts_enabled",:value)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    )->execute(['value' => $enabled ? '1' : '0']);
}

function music_customer_home_for_role(string $role): string
{
    return match ($role) {
        'admin' => 'portal/admin.php',
        'client' => 'portal/client.php',
        'customer' => 'portal/customer.php',
        default => 'index.php',
    };
}

function music_customer_start_session(int $userId): void
{
    session_regenerate_id(true);
    rotate_csrf_token();
    $_SESSION['user_id'] = $userId;
    $_SESSION['session_created_at'] = time();
    $_SESSION['session_last_activity'] = time();
    $_SESSION['session_regenerated_at'] = time();
    $_SESSION['session_user_agent'] = hash(
        'sha256',
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
    db()->prepare(
        'UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=:id'
    )->execute(['id' => $userId]);
}

function require_music_customer(): array
{
    $user = current_user();
    if (!$user || (string)($user['role'] ?? '') !== 'customer') {
        redirect('portal/login.php?role=customer');
    }
    if (!music_customer_accounts_active()) {
        logout_user();
        redirect('music-library.php?customer_accounts=disabled');
    }
    return $user;
}

function music_customer_account_counts(): array
{
    if (!music_customer_accounts_ready()) {
        return ['customers' => 0, 'playlists' => 0, 'tracks' => 0];
    }

    try {
        return [
            'customers' => (int)db()->query(
                'SELECT COUNT(*) FROM users WHERE role="customer"'
            )->fetchColumn(),
            'playlists' => (int)db()->query(
                'SELECT COUNT(*) FROM music_customer_playlists'
            )->fetchColumn(),
            'tracks' => (int)db()->query(
                'SELECT COUNT(*) FROM music_customer_playlist_tracks'
            )->fetchColumn(),
        ];
    } catch (Throwable) {
        return ['customers' => 0, 'playlists' => 0, 'tracks' => 0];
    }
}

function music_customer_playlists(int $userId): array
{
    if (!music_customer_accounts_ready() || $userId <= 0) return [];

    $statement = db()->prepare(
        'SELECT playlist.*,
                COUNT(item.track_id) AS track_count,
                COALESCE(SUM(track.duration_seconds),0) AS total_seconds
         FROM music_customer_playlists playlist
         LEFT JOIN music_customer_playlist_tracks item
           ON item.playlist_id=playlist.id
         LEFT JOIN music_tracks track
           ON track.id=item.track_id
          AND track.status="active"
         WHERE playlist.customer_user_id=:user_id
           AND playlist.status="active"
         GROUP BY playlist.id
         ORDER BY playlist.updated_at DESC,playlist.id DESC'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function music_customer_playlist(int $playlistId, int $userId): ?array
{
    if (!music_customer_accounts_ready() || $playlistId <= 0 || $userId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
         FROM music_customer_playlists
         WHERE id=:playlist_id
           AND customer_user_id=:user_id
           AND status="active"
         LIMIT 1'
    );
    $statement->execute([
        'playlist_id' => $playlistId,
        'user_id' => $userId,
    ]);
    $playlist = $statement->fetch();
    if (!$playlist) return null;

    $tracks = db()->prepare(
        'SELECT item.position,item.added_at,track.*,
                album.title AS album_title
         FROM music_customer_playlist_tracks item
         JOIN music_tracks track
           ON track.id=item.track_id
          AND track.status="active"
         LEFT JOIN music_albums album ON album.id=track.album_id
         WHERE item.playlist_id=:playlist_id
         ORDER BY item.position ASC,item.added_at ASC,item.track_id ASC'
    );
    $tracks->execute(['playlist_id' => $playlistId]);
    $playlist['tracks'] = $tracks->fetchAll();
    return $playlist;
}

function music_customer_unique_playlist_slug(int $userId, string $title, int $ignoreId = 0): string
{
    $base = substr(slugify($title), 0, 150);
    $slug = $base;
    $suffix = 2;

    while (true) {
        $statement = db()->prepare(
            'SELECT id
             FROM music_customer_playlists
             WHERE customer_user_id=:user_id
               AND slug=:slug
               AND id<>:ignore_id
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'slug' => $slug,
            'ignore_id' => $ignoreId,
        ]);
        if (!$statement->fetchColumn()) return $slug;
        $slug = substr($base, 0, 140) . '-' . $suffix;
        $suffix++;
    }
}

function music_customer_create_playlist(int $userId, string $title, string $description = ''): int
{
    $title = trim($title);
    $description = trim($description);
    if ($userId <= 0 || $title === '' || mb_strlen($title) > 120) {
        throw new RuntimeException('Enter a playlist name of 120 characters or fewer.');
    }
    if (mb_strlen($description) > 1000) {
        throw new RuntimeException('Playlist descriptions must be 1,000 characters or fewer.');
    }

    $count = db()->prepare(
        'SELECT COUNT(*) FROM music_customer_playlists
         WHERE customer_user_id=:user_id AND status="active"'
    );
    $count->execute(['user_id' => $userId]);
    if ((int)$count->fetchColumn() >= 100) {
        throw new RuntimeException('This account has reached the 100-playlist limit.');
    }

    $statement = db()->prepare(
        'INSERT INTO music_customer_playlists
            (customer_user_id,title,slug,description,status)
         VALUES
            (:user_id,:title,:slug,:description,"active")'
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => $title,
        'slug' => music_customer_unique_playlist_slug($userId, $title),
        'description' => $description !== '' ? $description : null,
    ]);
    $playlistId = (int)db()->lastInsertId();
    log_activity('music_customer_playlist_created', 'music_customer_playlist', $playlistId);
    return $playlistId;
}

function music_customer_update_playlist(
    int $playlistId,
    int $userId,
    string $title,
    string $description = ''
): void {
    $playlist = music_customer_playlist($playlistId, $userId);
    if (!$playlist) throw new RuntimeException('Playlist not found.');

    $title = trim($title);
    $description = trim($description);
    if ($title === '' || mb_strlen($title) > 120) {
        throw new RuntimeException('Enter a playlist name of 120 characters or fewer.');
    }
    if (mb_strlen($description) > 1000) {
        throw new RuntimeException('Playlist descriptions must be 1,000 characters or fewer.');
    }

    db()->prepare(
        'UPDATE music_customer_playlists
         SET title=:title,slug=:slug,description=:description
         WHERE id=:playlist_id AND customer_user_id=:user_id'
    )->execute([
        'title' => $title,
        'slug' => music_customer_unique_playlist_slug($userId, $title, $playlistId),
        'description' => $description !== '' ? $description : null,
        'playlist_id' => $playlistId,
        'user_id' => $userId,
    ]);
    log_activity('music_customer_playlist_updated', 'music_customer_playlist', $playlistId);
}

function music_customer_delete_playlist(int $playlistId, int $userId): void
{
    $statement = db()->prepare(
        'DELETE FROM music_customer_playlists
         WHERE id=:playlist_id AND customer_user_id=:user_id'
    );
    $statement->execute([
        'playlist_id' => $playlistId,
        'user_id' => $userId,
    ]);
    if ($statement->rowCount() < 1) throw new RuntimeException('Playlist not found.');
    log_activity('music_customer_playlist_deleted', 'music_customer_playlist', $playlistId);
}

function music_customer_add_track(int $playlistId, int $trackId, int $userId): void
{
    if (!music_customer_playlist($playlistId, $userId)) {
        throw new RuntimeException('Playlist not found.');
    }

    $track = db()->prepare(
        'SELECT id FROM music_tracks
         WHERE id=:track_id AND status="active" LIMIT 1'
    );
    $track->execute(['track_id' => $trackId]);
    if (!$track->fetchColumn()) throw new RuntimeException('Track not found.');

    $count = db()->prepare(
        'SELECT COUNT(*) FROM music_customer_playlist_tracks
         WHERE playlist_id=:playlist_id'
    );
    $count->execute(['playlist_id' => $playlistId]);
    if ((int)$count->fetchColumn() >= 500) {
        throw new RuntimeException('This playlist has reached the 500-track limit.');
    }

    $position = db()->prepare(
        'SELECT COALESCE(MAX(position),0)+1
         FROM music_customer_playlist_tracks
         WHERE playlist_id=:playlist_id'
    );
    $position->execute(['playlist_id' => $playlistId]);

    db()->prepare(
        'INSERT IGNORE INTO music_customer_playlist_tracks
            (playlist_id,track_id,position,added_by)
         VALUES
            (:playlist_id,:track_id,:position,:user_id)'
    )->execute([
        'playlist_id' => $playlistId,
        'track_id' => $trackId,
        'position' => (int)$position->fetchColumn(),
        'user_id' => $userId,
    ]);

    db()->prepare(
        'UPDATE music_customer_playlists SET updated_at=UTC_TIMESTAMP()
         WHERE id=:playlist_id AND customer_user_id=:user_id'
    )->execute(['playlist_id' => $playlistId, 'user_id' => $userId]);
    log_activity('music_customer_track_added', 'music_customer_playlist', $playlistId, [
        'track_id' => $trackId,
    ]);
}

function music_customer_remove_track(int $playlistId, int $trackId, int $userId): void
{
    if (!music_customer_playlist($playlistId, $userId)) {
        throw new RuntimeException('Playlist not found.');
    }

    db()->prepare(
        'DELETE FROM music_customer_playlist_tracks
         WHERE playlist_id=:playlist_id AND track_id=:track_id'
    )->execute(['playlist_id' => $playlistId, 'track_id' => $trackId]);

    db()->prepare(
        'UPDATE music_customer_playlists SET updated_at=UTC_TIMESTAMP()
         WHERE id=:playlist_id AND customer_user_id=:user_id'
    )->execute(['playlist_id' => $playlistId, 'user_id' => $userId]);
    log_activity('music_customer_track_removed', 'music_customer_playlist', $playlistId, [
        'track_id' => $trackId,
    ]);
}

function music_customer_admin_panel_html(): string
{
    $ready = music_customer_accounts_ready();
    $enabled = music_customer_accounts_enabled();
    $counts = music_customer_account_counts();

    ob_start();
    ?>
    <section class="panel music-customer-admin-panel" data-music-customer-admin>
        <header class="panel-header">
            <div>
                <span class="music-customer-eyebrow">Listener accounts</span>
                <h2>Customer accounts and playlists</h2>
            </div>
            <span class="status status-<?=$enabled && $ready ? 'active' : 'inactive'?>">
                <?=$enabled && $ready ? 'Enabled' : 'Disabled'?>
            </span>
        </header>
        <div class="panel-body">
            <p class="music-customer-admin-copy">
                Allow listeners to create a customer account, sign in, and build private playlists without receiving client-project or administrator access.
            </p>
            <div class="music-customer-admin-stats">
                <span><strong><?=$counts['customers']?></strong> customers</span>
                <span><strong><?=$counts['playlists']?></strong> playlists</span>
                <span><strong><?=$counts['tracks']?></strong> saved tracks</span>
            </div>
            <?php if (!$ready): ?>
                <div class="alert alert-warning">
                    Import <code>database/music_customer_accounts_v66q20.sql</code> before enabling this account type.
                </div>
            <?php endif; ?>
            <form method="post" action="<?=e(app_url('portal/admin.php?view=music&section=customers'))?>">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_music_customer_accounts">
                <label class="music-customer-toggle">
                    <input type="checkbox" name="enabled" value="1" <?=$enabled ? 'checked' : ''?> <?=$ready ? '' : 'disabled'?>>
                    <span>
                        <strong>Enable customer account type</strong>
                        <small>Shows customer sign-in and account creation on the public Music Library.</small>
                    </span>
                </label>
                <div class="form-footer music-customer-admin-actions">
                    <button class="button button-primary" type="submit" <?=$ready ? '' : 'disabled'?>>Save customer access</button>
                    <?php if ($ready): ?>
                        <a class="button" href="<?=e(app_url('portal/music-customers.php'))?>">Manage customers</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>
    <?php
    return trim((string)ob_get_clean());
}

function music_customer_inject_admin_panel(string $html): string
{
    $asset = '<link rel="stylesheet" href="'
        . e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))
        . '">';
    if (!str_contains($html, 'music-customer-accounts-v66q20.css')) {
        $html = preg_replace('#</head>#i', $asset . '</head>', $html, 1) ?? $html;
    }

    if (!str_contains($html, 'data-music-customer-admin')) {
        $marker = '<div class="portal-content">';
        $html = str_replace(
            $marker,
            $marker . music_customer_admin_panel_html(),
            $html,
            $count
        );
    }
    return $html;
}

function music_customer_public_strip_html(): string
{
    if (!music_customer_accounts_active()) return '';
    $user = current_user();

    ob_start();
    ?>
    <section class="music-customer-public-strip" data-music-customer-strip>
        <div>
            <span>Listener account</span>
            <?php if ($user && (string)$user['role'] === 'customer'): ?>
                <strong>Build and manage your private playlists.</strong>
            <?php else: ?>
                <strong>Create playlists and keep your music organized.</strong>
            <?php endif; ?>
        </div>
        <nav aria-label="Customer music account">
            <?php if ($user && (string)$user['role'] === 'customer'): ?>
                <a href="<?=e(app_url('portal/customer.php?view=playlists'))?>">My playlists</a>
                <a href="<?=e(app_url('portal/customer.php?view=library'))?>">Add music</a>
            <?php elseif (!$user): ?>
                <a href="<?=e(app_url('portal/customer-register.php'))?>">Create account</a>
                <a href="<?=e(app_url('portal/login.php?role=customer'))?>">Customer sign in</a>
            <?php endif; ?>
        </nav>
    </section>
    <?php
    return trim((string)ob_get_clean());
}

function music_customer_inject_public_library(string $html): string
{
    if (!music_customer_accounts_active()) return $html;

    $asset = '<link rel="stylesheet" href="'
        . e(app_url('assets/css/music-customer-accounts-v66q20.css?v=20260802-v66Q20'))
        . '">';
    if (!str_contains($html, 'music-customer-accounts-v66q20.css')) {
        $html = preg_replace('#</head>#i', $asset . '</head>', $html, 1) ?? $html;
    }

    if (!str_contains($html, 'data-music-customer-strip')) {
        $html = preg_replace(
            '#(<main\s+class="music-library-dashboard"[^>]*>)#i',
            '$1' . music_customer_public_strip_html(),
            $html,
            1
        ) ?? $html;
    }
    return $html;
}

function music_customer_bootstrap_runtime(): void
{
    if (PHP_SAPI === 'cli') return;

    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $view = (string)($_GET['view'] ?? '');

    if (
        $script === 'admin.php'
        && is_post()
        && input('action') === 'save_music_customer_accounts'
    ) {
        $user = require_role('admin');
        verify_csrf();
        if (!same_origin_request()) {
            http_response_code(403);
            exit('Invalid request origin.');
        }
        enforce_authenticated_action_limit($user);
        try {
            $enabled = isset($_POST['enabled']);
            music_customer_save_enabled($enabled);
            log_activity('music_customer_accounts_updated', 'settings', null, [
                'enabled' => $enabled,
            ]);
            flash('success', $enabled
                ? 'Customer accounts and private playlists are enabled.'
                : 'Customer accounts are disabled. Existing playlist data was preserved.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('portal/admin.php?view=music&section=customers');
    }

    if ($script === 'admin.php' && $view === 'music') {
        ob_start('music_customer_inject_admin_panel');
    }
    if ($script === 'music-library.php') {
        ob_start('music_customer_inject_public_library');
    }
}

music_customer_bootstrap_runtime();

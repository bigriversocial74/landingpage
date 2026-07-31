<?php
declare(strict_types=1);

require_once __DIR__ . '/notification-delivery.php';
require_once __DIR__ . '/automation-rules.php';
require_once __DIR__ . '/operations-analytics.php';

function automation_admin_portal_decorate(string $html): string
{
    if (!function_exists('app_url') || !str_contains($html, 'data-admin-navigation')) {
        return $html;
    }

    $active = (string)($_GET['view'] ?? '') === 'automation';
    if (!str_contains($html, 'portal/admin.php?view=automation')) {
        $link = '<a class="' . ($active ? 'active' : '') . '" href="'
            . e(app_url('portal/admin.php?view=automation'))
            . '">Action Center</a>';
        $decorated = preg_replace(
            '/(<div\s+class="portal-nav-group-links"\s+id="admin-nav-system"[^>]*>)/s',
            '$1' . $link,
            $html,
            1
        );
        if (is_string($decorated)) {
            $html = $decorated;
        }
    }

    if ($active) {
        $css = '<link rel="stylesheet" href="'
            . e(app_url('assets/css/automation-center.css?v=20260731-v66K'))
            . '">';
        $javascript = '<script src="'
            . e(app_url('assets/js/automation-center.js?v=20260731-v66K'))
            . '" defer></script>';
        $html = str_replace('</head>', $css . '</head>', $html);
        $html = str_replace('</body>', $javascript . '</body>', $html);
    }

    return $html;
}

function automation_admin_portal_bootstrap(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }
    $bootstrapped = true;

    $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($script !== 'admin.php') {
        return;
    }

    ob_start('automation_admin_portal_decorate');

    if ((string)($_GET['view'] ?? '') !== 'automation') {
        return;
    }

    require_once __DIR__ . '/automation-admin.php';
    require_once __DIR__ . '/automation-recovery.php';

    $user = require_role('admin');
    automation_recover_interrupted_approvals_complete();

    if (is_post()) {
        verify_csrf();
        enforce_authenticated_action_limit($user);
        try {
            $action = input('action');
            if (!automation_handle_admin_action($action, $user)) {
                throw new RuntimeException('Unsupported Automation Action Center request.');
            }
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            automation_admin_redirect(
                trim((string)($_GET['section'] ?? 'overview')) ?: 'overview'
            );
        }
    }

    portal_header('Automation Action Center', 'automation', $user);
    automation_render_admin($user);
    portal_footer();
    exit;
}

automation_admin_portal_bootstrap();

function operations_admin_portal_decorate(string $html): string
{
    if (!function_exists('app_url') || !str_contains($html, 'data-admin-navigation')) return $html;
    $active = (string)($_GET['view'] ?? '') === 'operations';
    if (!str_contains($html, 'portal/admin.php?view=operations')) {
        $link = '<a class="' . ($active ? 'active' : '') . '" href="'
            . e(app_url('portal/admin.php?view=operations'))
            . '">Operations</a>';
        $decorated = preg_replace(
            '/(<div\s+class="portal-nav-group-links"\s+id="admin-nav-system"[^>]*>)/s',
            '$1' . $link,
            $html,
            1
        );
        if (is_string($decorated)) $html = $decorated;
    }
    if ($active) {
        $css = '<link rel="stylesheet" href="'
            . e(app_url('assets/css/operations-analytics.css?v=20260731-v66L'))
            . '">';
        $html = str_replace('</head>', $css . '</head>', $html);
    }
    return $html;
}

function operations_admin_portal_bootstrap(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) return;
    $bootstrapped = true;
    if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== 'admin.php') return;

    ob_start('operations_admin_portal_decorate');
    if ((string)($_GET['view'] ?? '') !== 'operations') return;

    require_once __DIR__ . '/operations-admin.php';
    $user = require_role('admin');

    if ((string)($_GET['export'] ?? '') === 'csv') {
        enforce_authenticated_action_limit($user);
        operations_admin_export_csv();
    }

    if (is_post()) {
        verify_csrf();
        enforce_authenticated_action_limit($user);
        try {
            $action = input('action');
            if (!operations_handle_admin_action($action, $user)) {
                throw new RuntimeException('Unsupported Operations request.');
            }
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
            operations_admin_redirect(trim((string)($_GET['section'] ?? 'overview')) ?: 'overview');
        }
    }

    portal_header('POD Operations', 'operations', $user);
    operations_render_admin($user);
    portal_footer();
    exit;
}

operations_admin_portal_bootstrap();

function notification_create(
    int $recipientUserId,
    string $category,
    string $title,
    ?string $body = null,
    ?string $linkUrl = null,
    ?string $entityType = null,
    ?int $entityId = null,
    string $priority = 'normal'
): int {
    if ($recipientUserId <= 0 || trim($title) === '') {
        return 0;
    }

    $categories = ['call', 'message', 'contact', 'transcript', 'project', 'system'];
    $priorities = ['low', 'normal', 'high', 'urgent'];

    if (!in_array($category, $categories, true)) {
        $category = 'system';
    }

    if (!in_array($priority, $priorities, true)) {
        $priority = 'normal';
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO portal_notifications
                (recipient_user_id,category,title,body,link_url,
                 entity_type,entity_id,priority)
             VALUES
                (:recipient_user_id,:category,:title,:body,:link_url,
                 :entity_type,:entity_id,:priority)'
        );
        $statement->execute([
            'recipient_user_id' => $recipientUserId,
            'category' => $category,
            'title' => substr(trim($title), 0, 190),
            'body' => $body !== null ? substr(trim($body), 0, 4000) : null,
            'link_url' => $linkUrl !== null ? substr(trim($linkUrl), 0, 500) : null,
            'entity_type' => $entityType !== null ? substr(trim($entityType), 0, 80) : null,
            'entity_id' => $entityId,
            'priority' => $priority,
        ]);

        $notificationId = (int)db()->lastInsertId();
        try {
            notification_delivery_enqueue_notification($notificationId);
        } catch (Throwable $deliveryException) {
            error_log('North Mountain Media external notification enqueue failed: ' . $deliveryException->getMessage());
        }
        try {
            automation_capture_notification($notificationId);
        } catch (Throwable $automationException) {
            error_log('North Mountain Media automation notification capture failed: ' . $automationException->getMessage());
        }
        return $notificationId;
    } catch (Throwable $exception) {
        error_log('North Mountain Media notification create failed: ' . $exception->getMessage());
        return 0;
    }
}

function notification_create_for_role(
    string $role,
    string $category,
    string $title,
    ?string $body = null,
    ?string $linkUrl = null,
    ?string $entityType = null,
    ?int $entityId = null,
    string $priority = 'normal'
): void {
    if (!in_array($role, ['admin', 'client'], true)) {
        return;
    }

    try {
        $statement = db()->prepare(
            'SELECT id
             FROM users
             WHERE role = :role
               AND status = "active"'
        );
        $statement->execute(['role' => $role]);

        foreach ($statement->fetchAll() as $row) {
            notification_create(
                (int)$row['id'],
                $category,
                $title,
                $body,
                $linkUrl,
                $entityType,
                $entityId,
                $priority
            );
        }
    } catch (Throwable $exception) {
        error_log('North Mountain Media role notification failed: ' . $exception->getMessage());
    }
}

function notification_unread_count(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    try {
        $statement = db()->prepare(
            'SELECT COUNT(*)
             FROM portal_notifications
             WHERE recipient_user_id = :recipient_user_id
               AND is_read = 0'
        );
        $statement->execute(['recipient_user_id' => $userId]);

        return (int)$statement->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function notification_recent(
    int $userId,
    int $limit = 8,
    bool $unreadOnly = false
): array {
    $limit = max(1, min(100, $limit));

    try {
        $sql = 'SELECT *
                FROM portal_notifications
                WHERE recipient_user_id = :recipient_user_id';

        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }

        $sql .= ' ORDER BY is_read ASC, created_at DESC, id DESC
                  LIMIT ' . $limit;

        $statement = db()->prepare($sql);
        $statement->execute(['recipient_user_id' => $userId]);

        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function notification_mark_read(int $notificationId, int $userId): bool
{
    if ($notificationId <= 0 || $userId <= 0) {
        return false;
    }

    try {
        $statement = db()->prepare(
            'UPDATE portal_notifications
             SET is_read = 1,
                 read_at = COALESCE(read_at, UTC_TIMESTAMP())
             WHERE id = :id
               AND recipient_user_id = :recipient_user_id'
        );
        $statement->execute([
            'id' => $notificationId,
            'recipient_user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    } catch (Throwable) {
        return false;
    }
}

function notification_mark_all_read(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    try {
        $statement = db()->prepare(
            'UPDATE portal_notifications
             SET is_read = 1,
                 read_at = COALESCE(read_at, UTC_TIMESTAMP())
             WHERE recipient_user_id = :recipient_user_id
               AND is_read = 0'
        );
        $statement->execute(['recipient_user_id' => $userId]);

        return $statement->rowCount();
    } catch (Throwable) {
        return 0;
    }
}

function notification_mark_entity_read(
    string $entityType,
    int $entityId,
    ?int $recipientUserId = null
): int {
    $entityType = trim($entityType);

    if ($entityType === '' || $entityId <= 0) {
        return 0;
    }

    try {
        $sql = 'UPDATE portal_notifications
                SET is_read=1,
                    read_at=COALESCE(read_at,UTC_TIMESTAMP())
                WHERE entity_type=:entity_type
                  AND entity_id=:entity_id
                  AND is_read=0';
        $parameters = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ];

        if ($recipientUserId !== null && $recipientUserId > 0) {
            $sql .= ' AND recipient_user_id=:recipient_user_id';
            $parameters['recipient_user_id'] = $recipientUserId;
        }

        $statement = db()->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount();
    } catch (Throwable) {
        return 0;
    }
}

function notification_category_icon(string $category): string
{
    return match ($category) {
        'call' => '☎',
        'message' => '✉',
        'contact' => '●',
        'transcript' => 'T',
        'project' => '◆',
        default => '•',
    };
}

function notification_portal_link(array $user, ?string $linkUrl): string
{
    $script = $user['role'] === 'admin' ? 'admin.php' : 'client.php';
    $fallback = app_url('portal/' . $script . '?view=notifications');
    $linkUrl = trim((string)$linkUrl);

    if ($linkUrl === '') {
        return $fallback;
    }

    if (preg_match('#^https?://#i', $linkUrl)) {
        return $fallback;
    }

    return app_url(ltrim($linkUrl, '/'));
}

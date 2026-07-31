<?php
declare(strict_types=1);

require_once __DIR__ . '/notification-delivery.php';
require_once __DIR__ . '/automation-rules.php';

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

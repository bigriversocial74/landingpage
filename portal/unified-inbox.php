<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-unified-social-inbox-v66D */

require_once __DIR__ . '/communications.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/homeserver-adapter.php';
if (is_file(__DIR__ . '/pod-messaging.php')) require_once __DIR__ . '/pod-messaging.php';
if (is_file(__DIR__ . '/content-interactions.php')) require_once __DIR__ . '/content-interactions.php';
if (is_file(__DIR__ . '/federated-interactions.php')) require_once __DIR__ . '/federated-interactions.php';

function unified_inbox_source_catalog(): array
{
    return [
        'communication' => ['label' => 'Communications', 'category' => 'messages', 'icon' => '✉'],
        'pod_message' => ['label' => 'POD Messages', 'category' => 'messages', 'icon' => '◈'],
        'content_comment' => ['label' => 'Blog Activity', 'category' => 'social', 'icon' => '♥'],
        'federated_comment' => ['label' => 'Federated Reply', 'category' => 'social', 'icon' => '◌'],
        'federated_reaction' => ['label' => 'Federated Reaction', 'category' => 'social', 'icon' => '↻'],
        'federated_follow' => ['label' => 'Federated Follow', 'category' => 'social', 'icon' => '◎'],
        'lead' => ['label' => 'Inquiries', 'category' => 'inquiries', 'icon' => '◎'],
        'call_center' => ['label' => 'Calls & Voicemail', 'category' => 'calls', 'icon' => '☎'],
        'notification' => ['label' => 'Notifications', 'category' => 'system', 'icon' => '●'],
    ];
}

function unified_inbox_table_exists(string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-z0-9_]+$/', $table)) return false;
    try {
        $statement = db()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE() AND table_name=:table_name'
        );
        $statement->execute(['table_name' => $table]);
        return $cache[$table] = (int)$statement->fetchColumn() === 1;
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function unified_inbox_schema_available(): bool
{
    return unified_inbox_table_exists('unified_inbox_workflow')
        && unified_inbox_table_exists('unified_inbox_user_state');
}

function unified_inbox_clean_preview(?string $value, int $limit = 220): string
{
    $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$value)) ?? '');
    return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1) . '…' : $value;
}

function unified_inbox_item(array $values): array
{
    $catalog = unified_inbox_source_catalog();
    $sourceType = (string)($values['source_type'] ?? 'notification');
    $definition = $catalog[$sourceType] ?? $catalog['notification'];
    $priority = (string)($values['native_priority'] ?? $values['priority'] ?? 'normal');
    if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) $priority = 'normal';
    $occurredAt = trim((string)($values['occurred_at'] ?? '')) ?: gmdate('Y-m-d H:i:s');

    return [
        'source_type' => $sourceType,
        'source_id' => max(0, (int)($values['source_id'] ?? 0)),
        'source_label' => (string)($values['source_label'] ?? $definition['label']),
        'category' => (string)($values['category'] ?? $definition['category']),
        'icon' => (string)($values['icon'] ?? $definition['icon']),
        'title' => trim((string)($values['title'] ?? 'Activity')),
        'participant' => trim((string)($values['participant'] ?? '')),
        'preview' => unified_inbox_clean_preview($values['preview'] ?? ''),
        'occurred_at' => $occurredAt,
        'native_unread' => !empty($values['native_unread']),
        'native_status' => trim((string)($values['native_status'] ?? 'open')),
        'native_priority' => $priority,
        'native_needs_response' => !empty($values['native_needs_response']),
        'href' => trim((string)($values['href'] ?? '')),
        'crm_contact_id' => max(0, (int)($values['crm_contact_id'] ?? 0)),
        'metadata' => is_array($values['metadata'] ?? null) ? $values['metadata'] : [],
    ];
}

function unified_inbox_communication_items(array $user): array
{
    if (!function_exists('communication_thread_list')) return [];
    try {
        $rows = communication_thread_list($user);
    } catch (Throwable) {
        return [];
    }
    $items = [];
    foreach (array_slice($rows, 0, 150) as $row) {
        $participant = trim((string)($row['client_company'] ?: $row['client_name'] ?? 'Client'));
        $status = (string)($row['status'] ?? 'open');
        $items[] = unified_inbox_item([
            'source_type' => 'communication',
            'source_id' => (int)$row['id'],
            'title' => (string)$row['subject'],
            'participant' => $participant,
            'preview' => (string)($row['latest_message'] ?? ''),
            'occurred_at' => (string)($row['last_message_at'] ?? $row['updated_at'] ?? $row['created_at']),
            'native_unread' => (int)($row['unread_count'] ?? 0) > 0,
            'native_status' => $status,
            'native_needs_response' => $status === 'waiting_admin',
            'href' => app_url('portal/admin.php?view=communications&thread=' . (int)$row['id']),
            'crm_contact_id' => (int)($row['crm_contact_id'] ?? 0),
            'metadata' => [
                'project' => (string)($row['project_title'] ?? ''),
                'assigned_to' => (string)($row['assigned_admin_name'] ?? ''),
                'unread_count' => (int)($row['unread_count'] ?? 0),
            ],
        ]);
    }
    return $items;
}

function unified_inbox_pod_items(): array
{
    if (!function_exists('pod_messaging_schema_available')
        || !pod_messaging_schema_available()
        || !function_exists('pod_message_threads')) return [];
    try {
        $rows = pod_message_threads();
    } catch (Throwable) {
        return [];
    }
    $items = [];
    foreach (array_slice($rows, 0, 150) as $row) {
        $unread = (int)($row['unread_count'] ?? 0);
        $participant = trim((string)($row['contact_name'] ?: $row['remote_pod_name'] ?? 'Connected POD'));
        $items[] = unified_inbox_item([
            'source_type' => 'pod_message',
            'source_id' => (int)$row['id'],
            'title' => (string)$row['subject'],
            'participant' => $participant,
            'preview' => (string)($row['last_message_body'] ?? ''),
            'occurred_at' => (string)($row['last_message_at'] ?? $row['updated_at'] ?? $row['created_at']),
            'native_unread' => $unread > 0,
            'native_status' => (string)($row['status'] ?? 'open'),
            'native_needs_response' => $unread > 0,
            'href' => app_url('portal/pod-messages.php?thread=' . (int)$row['id']),
            'crm_contact_id' => (int)($row['crm_contact_id'] ?? 0),
            'metadata' => [
                'remote_pod_uuid' => (string)($row['remote_pod_uuid'] ?? ''),
                'delivery_status' => (string)($row['last_delivery_status'] ?? ''),
                'unread_count' => $unread,
            ],
        ]);
    }
    return $items;
}

function unified_inbox_comment_items(int $userId): array
{
    if (!function_exists('content_interactions_schema_available')
        || !content_interactions_schema_available()) return [];
    try {
        $statement = db()->prepare(
            'SELECT comment.id,comment.parent_id,comment.body,comment.status,comment.report_count,
                    comment.created_at,comment.updated_at,user.display_name AS author_name,
                    post.title AS post_title,post.slug AS post_slug,
                    (SELECT COUNT(*) FROM portal_notifications notification
                     WHERE notification.recipient_user_id=:viewer_id
                       AND notification.entity_type="content_comment"
                       AND notification.entity_id=comment.id
                       AND notification.is_read=0) AS notification_unread
             FROM content_comments comment
             JOIN users user ON user.id=comment.author_user_id
             LEFT JOIN blog_posts post ON post.id=comment.content_id
             WHERE comment.content_type="blog_post" AND comment.status<>"deleted"
             ORDER BY COALESCE(comment.updated_at,comment.created_at) DESC,comment.id DESC
             LIMIT 150'
        );
        $statement->execute(['viewer_id' => $userId]);
        $rows = $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
    $items = [];
    foreach ($rows as $row) {
        $pending = (string)$row['status'] === 'pending';
        $reported = (int)$row['report_count'] > 0;
        $items[] = unified_inbox_item([
            'source_type' => 'content_comment',
            'source_id' => (int)$row['id'],
            'title' => ((int)($row['parent_id'] ?? 0) > 0 ? 'Reply on ' : 'Comment on ') . ((string)($row['post_title'] ?? '') ?: 'Blog post'),
            'participant' => (string)$row['author_name'],
            'preview' => (string)$row['body'],
            'occurred_at' => (string)($row['updated_at'] ?? $row['created_at']),
            'native_unread' => $pending || $reported || (int)($row['notification_unread'] ?? 0) > 0,
            'native_status' => (string)$row['status'],
            'native_priority' => $reported ? 'high' : 'normal',
            'native_needs_response' => $pending || $reported,
            'href' => app_url('portal/admin.php?view=blog&moderation=1#comment-' . (int)$row['id']),
            'metadata' => [
                'post' => (string)($row['post_title'] ?? ''),
                'reports' => (int)$row['report_count'],
            ],
        ]);
    }
    return $items;
}

function unified_inbox_lead_items(): array
{
    if (!unified_inbox_table_exists('leads')) return [];
    try {
        $rows = db()->query(
            'SELECT lead.*,opportunity.id AS opportunity_id,opportunity.stage AS opportunity_stage,
                    contact.id AS crm_contact_id
             FROM leads lead
             LEFT JOIN crm_opportunities opportunity ON opportunity.lead_id=lead.id
             LEFT JOIN crm_contacts contact ON contact.id=opportunity.contact_id
             ORDER BY lead.updated_at DESC,lead.id DESC LIMIT 120'
        )->fetchAll();
    } catch (Throwable) {
        return [];
    }
    $items = [];
    foreach ($rows as $row) {
        $status = (string)($row['opportunity_stage'] ?: $row['status']);
        $needsResponse = in_array($status, ['new', 'reviewing', 'qualified'], true);
        $items[] = unified_inbox_item([
            'source_type' => 'lead',
            'source_id' => (int)$row['id'],
            'title' => (string)($row['opportunity'] ?: 'Website inquiry'),
            'participant' => trim((string)$row['name'] . ((string)$row['company'] !== '' ? ' · ' . (string)$row['company'] : '')),
            'preview' => (string)$row['message'],
            'occurred_at' => (string)($row['updated_at'] ?? $row['created_at']),
            'native_unread' => $status === 'new',
            'native_status' => $status,
            'native_priority' => $status === 'new' ? 'high' : 'normal',
            'native_needs_response' => $needsResponse,
            'href' => app_url('portal/admin.php?view=leads&lead=' . (int)$row['id']),
            'crm_contact_id' => (int)($row['crm_contact_id'] ?? 0),
            'metadata' => [
                'email' => (string)$row['email'],
                'source' => (string)$row['source'],
            ],
        ]);
    }
    return $items;
}

function unified_inbox_call_items(): array
{
    if (!unified_inbox_table_exists('call_center_requests')) return [];
    try {
        $rows = db()->query(
            'SELECT request.*,contact.display_name AS contact_name,client.display_name AS client_name,
                    admin.display_name AS assigned_admin_name
             FROM call_center_requests request
             LEFT JOIN crm_contacts contact ON contact.id=request.crm_contact_id
             LEFT JOIN users client ON client.id=request.client_user_id
             LEFT JOIN users admin ON admin.id=request.assigned_admin_user_id
             ORDER BY COALESCE(request.last_contact_at,request.updated_at,request.requested_at) DESC,request.id DESC
             LIMIT 150'
        )->fetchAll();
    } catch (Throwable) {
        return [];
    }
    $items = [];
    foreach ($rows as $row) {
        $participant = trim((string)($row['contact_name'] ?: $row['client_name'] ?: $row['guest_name'] ?: 'Unknown caller'));
        $unread = (string)($row['message_stage'] ?? 'new') === 'new';
        $needsResponse = in_array((string)$row['status'], ['new', 'queued', 'scheduled', 'ringing', 'missed', 'voicemail'], true)
            && !in_array((string)($row['message_stage'] ?? ''), ['resolved', 'archived'], true);
        $requestType = (string)$row['request_type'];
        $items[] = unified_inbox_item([
            'source_type' => 'call_center',
            'source_id' => (int)$row['id'],
            'source_label' => $requestType === 'voicemail' ? 'Voicemail' : 'Call Center',
            'category' => $requestType === 'voicemail' ? 'voicemail' : 'calls',
            'title' => (string)$row['subject'],
            'participant' => $participant,
            'preview' => (string)($row['message'] ?: $row['transcript_text'] ?? ''),
            'occurred_at' => (string)($row['last_contact_at'] ?? $row['updated_at'] ?? $row['requested_at']),
            'native_unread' => $unread,
            'native_status' => (string)$row['status'],
            'native_priority' => (string)$row['priority'],
            'native_needs_response' => $needsResponse,
            'href' => app_url('portal/admin.php?view=call-center&request=' . (int)$row['id']),
            'crm_contact_id' => (int)($row['crm_contact_id'] ?? 0),
            'metadata' => [
                'request_type' => $requestType,
                'message_stage' => (string)($row['message_stage'] ?? ''),
                'assigned_to' => (string)($row['assigned_admin_name'] ?? ''),
                'phone' => (string)($row['guest_phone'] ?? ''),
            ],
        ]);
    }
    return $items;
}

function unified_inbox_notification_items(int $userId): array
{
    if (!unified_inbox_table_exists('portal_notifications')) return [];
    $duplicateEntities = ['content_comment', 'federated_comment', 'federated_reaction', 'federated_follow', 'communication_call', 'communication_thread', 'call_center_request', 'pod_message', 'pod_message_thread'];
    try {
        $statement = db()->prepare(
            'SELECT * FROM portal_notifications WHERE recipient_user_id=:user_id
             ORDER BY created_at DESC,id DESC LIMIT 150'
        );
        $statement->execute(['user_id' => $userId]);
        $rows = $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
    $items = [];
    foreach ($rows as $row) {
        if (in_array((string)($row['entity_type'] ?? ''), $duplicateEntities, true)) continue;
        $link = trim((string)($row['link_url'] ?? ''));
        if ($link !== '' && !preg_match('#^(?:https?://|/)#i', $link)) $link = app_url(ltrim($link, '/'));
        $items[] = unified_inbox_item([
            'source_type' => 'notification',
            'source_id' => (int)$row['id'],
            'source_label' => 'Notification',
            'category' => (string)$row['category'] === 'call' ? 'calls' : 'system',
            'title' => (string)$row['title'],
            'participant' => status_label((string)$row['category']),
            'preview' => (string)($row['body'] ?? ''),
            'occurred_at' => (string)$row['created_at'],
            'native_unread' => !(int)$row['is_read'],
            'native_status' => (int)$row['is_read'] ? 'read' : 'unread',
            'native_priority' => (string)$row['priority'],
            'native_needs_response' => false,
            'href' => $link,
            'metadata' => ['entity_type' => (string)($row['entity_type'] ?? '')],
        ]);
    }
    return $items;
}

function unified_inbox_state_maps(int $userId): array
{
    if (!unified_inbox_schema_available()) return [[], []];
    try {
        $workflow = [];
        foreach (db()->query(
            'SELECT workflow.*,assigned.display_name AS assigned_name
             FROM unified_inbox_workflow workflow
             LEFT JOIN users assigned ON assigned.id=workflow.assigned_user_id'
        )->fetchAll() as $row) {
            $workflow[$row['source_type'] . ':' . $row['source_id']] = $row;
        }
        $statement = db()->prepare('SELECT * FROM unified_inbox_user_state WHERE user_id=:user_id');
        $statement->execute(['user_id' => $userId]);
        $userState = [];
        foreach ($statement->fetchAll() as $row) {
            $userState[$row['source_type'] . ':' . $row['source_id']] = $row;
        }
        return [$workflow, $userState];
    } catch (Throwable) {
        return [[], []];
    }
}

function unified_inbox_collect(array $user): array
{
    $items = array_merge(
        unified_inbox_communication_items($user),
        unified_inbox_pod_items(),
        unified_inbox_comment_items((int)$user['id']),
        function_exists('federated_interactions_inbox_items') ? federated_interactions_inbox_items() : [],
        unified_inbox_lead_items(),
        unified_inbox_call_items(),
        unified_inbox_notification_items((int)$user['id'])
    );
    [$workflowMap, $userStateMap] = unified_inbox_state_maps((int)$user['id']);
    $now = time();
    foreach ($items as &$item) {
        $key = $item['source_type'] . ':' . $item['source_id'];
        $workflow = $workflowMap[$key] ?? [];
        $userState = $userStateMap[$key] ?? [];
        $readOverride = (string)($userState['read_override'] ?? 'inherit');
        $item['key'] = $key;
        $item['workflow_status'] = (string)($workflow['workflow_status'] ?? 'open');
        $item['priority'] = (string)($workflow['priority'] ?? $item['native_priority']);
        $item['assigned_user_id'] = (int)($workflow['assigned_user_id'] ?? 0);
        $item['assigned_name'] = (string)($workflow['assigned_name'] ?? '');
        $item['needs_response'] = array_key_exists('needs_response', $workflow)
            ? (bool)$workflow['needs_response'] : $item['native_needs_response'];
        $item['pinned'] = !empty($workflow['pinned']);
        $item['snoozed_until'] = $workflow['snoozed_until'] ?? null;
        $item['snoozed'] = !empty($item['snoozed_until']) && (strtotime((string)$item['snoozed_until']) ?: 0) > $now;
        $item['note'] = (string)($workflow['note'] ?? '');
        $item['unread'] = $readOverride === 'read' ? false : ($readOverride === 'unread' ? true : $item['native_unread']);
        $item['archived'] = !empty($userState['archived_at']);
        $item['last_viewed_at'] = $userState['last_viewed_at'] ?? null;
    }
    unset($item);
    return $items;
}

function unified_inbox_filter_items(array $items, array $filters): array
{
    $query = mb_strtolower(trim((string)($filters['q'] ?? '')));
    $channel = trim((string)($filters['channel'] ?? 'all'));
    $queue = trim((string)($filters['queue'] ?? 'active'));
    $showArchived = !empty($filters['archived']);
    $userId = (int)($filters['user_id'] ?? 0);
    $items = array_values(array_filter($items, static function (array $item) use ($query, $channel, $queue, $showArchived, $userId): bool {
        if ($item['archived'] !== $showArchived) return false;
        if (!$showArchived && $item['snoozed'] && $queue !== 'snoozed') return false;
        if ($channel !== 'all' && $item['category'] !== $channel && $item['source_type'] !== $channel) return false;
        if ($query !== '') {
            $haystack = mb_strtolower(implode(' ', [$item['title'], $item['participant'], $item['preview'], $item['source_label']]));
            if (!str_contains($haystack, $query)) return false;
        }
        return match ($queue) {
            'unread' => $item['unread'],
            'needs-response' => $item['needs_response'],
            'pinned' => $item['pinned'],
            'assigned' => $item['assigned_user_id'] === $userId,
            'resolved' => $item['workflow_status'] === 'resolved',
            'snoozed' => $item['snoozed'],
            'all' => true,
            default => $item['workflow_status'] !== 'resolved',
        };
    }));
    $priorityWeight = ['urgent' => 4, 'high' => 3, 'normal' => 2, 'low' => 1];
    usort($items, static function (array $left, array $right) use ($priorityWeight): int {
        return ((int)$right['pinned'] <=> (int)$left['pinned'])
            ?: ((int)$right['unread'] <=> (int)$left['unread'])
            ?: ((int)$right['needs_response'] <=> (int)$left['needs_response'])
            ?: (($priorityWeight[$right['priority']] ?? 0) <=> ($priorityWeight[$left['priority']] ?? 0))
            ?: ((strtotime((string)$right['occurred_at']) ?: 0) <=> (strtotime((string)$left['occurred_at']) ?: 0));
    });
    return $items;
}

function unified_inbox_validate_source(string $sourceType, int $sourceId): void
{
    if (!isset(unified_inbox_source_catalog()[$sourceType]) || $sourceId <= 0) {
        throw new RuntimeException('The inbox item is invalid.');
    }
}

function unified_inbox_admin_id_or_null(int $userId): ?int
{
    if ($userId <= 0) return null;
    $statement = db()->prepare('SELECT id FROM users WHERE id=:id AND role="admin" AND status="active" LIMIT 1');
    $statement->execute(['id' => $userId]);
    if (!$statement->fetchColumn()) throw new RuntimeException('Select an active administrator.');
    return $userId;
}

function unified_inbox_return_url(string $sourceType, int $sourceId): string
{
    $parameters = ['view' => 'inbox', 'focus' => $sourceType . ':' . $sourceId];
    foreach (['q', 'channel', 'queue'] as $field) {
        $value = trim((string)($_POST[$field] ?? ''));
        if ($value !== '') $parameters[$field] = mb_substr($value, 0, 100);
    }
    if (!empty($_POST['archived'])) $parameters['archived'] = '1';
    return 'portal/admin.php?' . http_build_query($parameters);
}

function unified_inbox_handle_admin_action(string $action, array $user): bool
{
    if (!str_starts_with($action, 'unified_inbox_')) return false;
    if (!unified_inbox_schema_available()) throw new RuntimeException('Import database/unified_social_inbox_v66d.sql first.');
    $sourceType = trim(input('source_type'));
    $sourceId = int_input('source_id');
    unified_inbox_validate_source($sourceType, $sourceId);
    $userId = (int)$user['id'];

    if ($action === 'unified_inbox_save') {
        $status = input('workflow_status');
        if (!in_array($status, ['open', 'waiting', 'resolved'], true)) $status = 'open';
        $priority = input('priority');
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) $priority = 'normal';
        $snoozedUntil = nullable_input('snoozed_until');
        if ($snoozedUntil !== null) {
            $timestamp = strtotime(str_replace('T', ' ', $snoozedUntil));
            if ($timestamp === false) throw new RuntimeException('Enter a valid snooze date.');
            $snoozedUntil = gmdate('Y-m-d H:i:s', $timestamp);
        }
        db()->prepare(
            'INSERT INTO unified_inbox_workflow
                (source_type,source_id,workflow_status,priority,assigned_user_id,needs_response,pinned,snoozed_until,note,updated_by_user_id)
             VALUES
                (:source_type,:source_id,:workflow_status,:priority,:assigned_user_id,:needs_response,:pinned,:snoozed_until,:note,:updated_by_user_id)
             ON DUPLICATE KEY UPDATE workflow_status=VALUES(workflow_status),priority=VALUES(priority),
                assigned_user_id=VALUES(assigned_user_id),needs_response=VALUES(needs_response),pinned=VALUES(pinned),
                snoozed_until=VALUES(snoozed_until),note=VALUES(note),updated_by_user_id=VALUES(updated_by_user_id)'
        )->execute([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'workflow_status' => $status,
            'priority' => $priority,
            'assigned_user_id' => unified_inbox_admin_id_or_null(int_input('assigned_user_id')),
            'needs_response' => isset($_POST['needs_response']) ? 1 : 0,
            'pinned' => isset($_POST['pinned']) ? 1 : 0,
            'snoozed_until' => $snoozedUntil,
            'note' => nullable_input('note'),
            'updated_by_user_id' => $userId,
        ]);
        log_activity('unified_inbox_workflow_saved', $sourceType, $sourceId, ['status' => $status, 'priority' => $priority]);
        flash('success', 'Inbox workflow updated.');
    } elseif ($action === 'unified_inbox_mark') {
        $readOverride = input('read_override');
        if (!in_array($readOverride, ['inherit', 'read', 'unread'], true)) $readOverride = 'inherit';
        db()->prepare(
            'INSERT INTO unified_inbox_user_state (user_id,source_type,source_id,read_override,last_viewed_at)
             VALUES (:user_id,:source_type,:source_id,:read_override,UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE read_override=VALUES(read_override),last_viewed_at=UTC_TIMESTAMP()'
        )->execute(['user_id' => $userId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'read_override' => $readOverride]);
        flash('success', $readOverride === 'read' ? 'Inbox item marked read.' : ($readOverride === 'unread' ? 'Inbox item marked unread.' : 'Native read state restored.'));
    } elseif ($action === 'unified_inbox_archive') {
        $archive = isset($_POST['archive']);
        db()->prepare(
            'INSERT INTO unified_inbox_user_state (user_id,source_type,source_id,archived_at,last_viewed_at)
             VALUES (:user_id,:source_type,:source_id,:archived_at,UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE archived_at=VALUES(archived_at),last_viewed_at=UTC_TIMESTAMP()'
        )->execute([
            'user_id' => $userId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'archived_at' => $archive ? gmdate('Y-m-d H:i:s') : null,
        ]);
        flash('success', $archive ? 'Inbox item archived.' : 'Inbox item restored.');
    } else {
        throw new RuntimeException('Unsupported inbox action.');
    }
    redirect(unified_inbox_return_url($sourceType, $sourceId));
    return true;
}

function unified_inbox_render(array $user): void
{
    $filters = [
        'q' => trim((string)($_GET['q'] ?? '')),
        'channel' => trim((string)($_GET['channel'] ?? 'all')) ?: 'all',
        'queue' => trim((string)($_GET['queue'] ?? 'active')) ?: 'active',
        'archived' => query_int('archived') === 1,
        'user_id' => (int)$user['id'],
    ];
    $allItems = unified_inbox_collect($user);
    $items = unified_inbox_filter_items($allItems, $filters);
    $focus = trim((string)($_GET['focus'] ?? ''));
    $selected = null;
    foreach ($items as $item) {
        if ($item['key'] === $focus) { $selected = $item; break; }
    }
    if (!$selected && $items) $selected = $items[0];
    $counts = [
        'total' => count($allItems),
        'unread' => count(array_filter($allItems, static fn(array $item): bool => $item['unread'] && !$item['archived'])),
        'needs_response' => count(array_filter($allItems, static fn(array $item): bool => $item['needs_response'] && !$item['archived'])),
        'pinned' => count(array_filter($allItems, static fn(array $item): bool => $item['pinned'] && !$item['archived'])),
    ];
    $administrators = db()->query('SELECT id,display_name FROM users WHERE role="admin" AND status="active" ORDER BY display_name')->fetchAll();
    $homeServer = homeserver_adapter_status();
    $schemaReady = unified_inbox_schema_available();
    $baseQuery = ['view' => 'inbox'];
    $linkFor = static function (array $changes) use ($filters, $baseQuery): string {
        $query = $baseQuery + [
            'q' => $filters['q'], 'channel' => $filters['channel'], 'queue' => $filters['queue'],
            'archived' => $filters['archived'] ? 1 : null,
        ];
        foreach ($changes as $key => $value) $query[$key] = $value;
        $query = array_filter($query, static fn($value): bool => $value !== null && $value !== '');
        return app_url('portal/admin.php?' . http_build_query($query));
    };
    ?>
    <div class="unified-inbox" data-unified-inbox>
        <section class="unified-inbox-hero">
            <div><span>Unified Social Inbox · v66D</span><h2>Every conversation. One operating view.</h2><p>Messages remain in their original secure systems. This workspace normalizes their status, priority, assignment, and response workflow.</p></div>
            <div class="unified-inbox-health <?=e($homeServer['mode'])?>"><strong><?=e($homeServer['mode']==='connected'?'HomeServer connected':($homeServer['mode']==='offline'?'HomeServer offline':'Standalone POD'))?></strong><span><?=e($homeServer['mode']==='connected'?'Private intelligence is available.':'The inbox remains fully operational without private HomeServer services.')?></span></div>
        </section>
        <?php if(!$schemaReady):?><div class="unified-inbox-warning"><strong>Workflow migration required.</strong> Import <code>database/unified_social_inbox_v66d.sql</code>. Source items are visible, but shared workflow controls are read-only.</div><?php endif;?>
        <section class="unified-inbox-stats">
            <a href="<?=e($linkFor(['queue'=>'all']))?>"><span>All activity</span><strong><?=$counts['total']?></strong></a>
            <a href="<?=e($linkFor(['queue'=>'unread']))?>"><span>Unread</span><strong><?=$counts['unread']?></strong></a>
            <a href="<?=e($linkFor(['queue'=>'needs-response']))?>"><span>Needs response</span><strong><?=$counts['needs_response']?></strong></a>
            <a href="<?=e($linkFor(['queue'=>'pinned']))?>"><span>Pinned</span><strong><?=$counts['pinned']?></strong></a>
        </section>
        <div class="unified-inbox-layout">
            <aside class="unified-inbox-filters">
                <form method="get"><input type="hidden" name="view" value="inbox"><label><span>Search</span><input type="search" name="q" value="<?=e($filters['q'])?>" placeholder="Name, subject, message"></label><input type="hidden" name="channel" value="<?=e($filters['channel'])?>"><input type="hidden" name="queue" value="<?=e($filters['queue'])?>"><button type="submit">Search inbox</button></form>
                <nav><span>Queues</span><?php foreach(['active'=>'Active','unread'=>'Unread','needs-response'=>'Needs response','assigned'=>'Assigned to me','pinned'=>'Pinned','snoozed'=>'Snoozed','resolved'=>'Resolved','all'=>'All'] as $key=>$label):?><a class="<?=$filters['queue']===$key?'active':''?>" href="<?=e($linkFor(['queue'=>$key]))?>"><?=e($label)?></a><?php endforeach;?></nav>
                <nav><span>Channels</span><?php foreach(['all'=>'All channels','messages'=>'Messages','social'=>'Social','inquiries'=>'Inquiries','calls'=>'Calls','voicemail'=>'Voicemail','system'=>'System'] as $key=>$label):?><a class="<?=$filters['channel']===$key?'active':''?>" href="<?=e($linkFor(['channel'=>$key]))?>"><?=e($label)?></a><?php endforeach;?></nav>
                <a class="unified-inbox-archive-link" href="<?=e($linkFor(['archived'=>$filters['archived']?null:1]))?>"><?=$filters['archived']?'Back to inbox':'Archived items'?></a>
            </aside>
            <section class="unified-inbox-list" aria-label="Inbox items">
                <header><div><span><?=count($items)?> results</span><h2><?=e(status_label($filters['queue']))?></h2></div></header>
                <?php if(!$items):?><div class="unified-inbox-empty">No items match this inbox view.</div><?php endif;?>
                <?php foreach($items as $item):?><a class="unified-inbox-item <?=$selected&&$selected['key']===$item['key']?'active':''?> <?=$item['unread']?'unread':''?>" href="<?=e($linkFor(['focus'=>$item['key']]))?>" data-inbox-item>
                    <span class="unified-inbox-icon"><?=e($item['icon'])?></span><span class="unified-inbox-copy"><span class="unified-inbox-source"><?=e($item['source_label'])?><?php if($item['pinned']):?> · Pinned<?php endif;?></span><strong><?=e($item['title'])?></strong><small><?=e($item['participant'])?></small><em><?=e($item['preview']?:'No preview available.')?></em><span class="unified-inbox-tags"><?php if($item['needs_response']):?><b>Needs response</b><?php endif;?><i class="priority-<?=e($item['priority'])?>"><?=e(status_label($item['priority']))?></i><time><?=e(format_datetime($item['occurred_at']))?></time></span></span><?php if($item['unread']):?><span class="unified-inbox-unread" aria-label="Unread"></span><?php endif;?>
                </a><?php endforeach;?>
            </section>
            <section class="unified-inbox-preview">
                <?php if(!$selected):?><div class="unified-inbox-empty">Select an inbox item to review its context and workflow.</div><?php else:?>
                    <header><div><span><?=e($selected['source_label'])?></span><h2><?=e($selected['title'])?></h2><p><?=e($selected['participant'])?></p></div><div class="unified-inbox-actions"><?php if($selected['href']!==''):?><a href="<?=e($selected['href'])?>">Open source</a><?php endif;?></div></header>
                    <div class="unified-inbox-message"><p><?=nl2br(e($selected['preview']?:'No message preview is available.'))?></p></div>
                    <dl><div><dt>Received</dt><dd><?=e(format_datetime($selected['occurred_at']))?></dd></div><div><dt>Native status</dt><dd><?=e(status_label($selected['native_status']))?></dd></div><div><dt>Channel</dt><dd><?=e(status_label($selected['category']))?></dd></div><?php if($selected['crm_contact_id']>0):?><div><dt>CRM contact</dt><dd><a href="<?=e(app_url('portal/admin.php?view=crm&id='.$selected['crm_contact_id']))?>">Open relationship</a></dd></div><?php endif;?></dl>
                    <section class="unified-inbox-ai"><div><span>Private intelligence</span><strong><?=e($homeServer['mode']==='connected'?'HomeServer enhancements ready':'Available after HomeServer pairing')?></strong></div><button type="button" <?=homeserver_capability_available('message_summary')?'':'disabled'?>>Summarize</button><button type="button" <?=homeserver_capability_available('suggest_reply')?'':'disabled'?>>Suggest reply</button><p>The POD never sends private conversation content to an unavailable or unauthorized service.</p></section>
                    <?php if($schemaReady):?>
                    <form class="unified-inbox-workflow" method="post"><?=csrf_field()?><input type="hidden" name="action" value="unified_inbox_save"><input type="hidden" name="source_type" value="<?=e($selected['source_type'])?>"><input type="hidden" name="source_id" value="<?=$selected['source_id']?>"><input type="hidden" name="q" value="<?=e($filters['q'])?>"><input type="hidden" name="channel" value="<?=e($filters['channel'])?>"><input type="hidden" name="queue" value="<?=e($filters['queue'])?>"><?php if($filters['archived']):?><input type="hidden" name="archived" value="1"><?php endif;?>
                        <h3>Operator workflow</h3><div class="unified-inbox-fields"><label><span>Status</span><select name="workflow_status"><?php foreach(['open','waiting','resolved'] as $value):?><option value="<?=$value?>" <?=$selected['workflow_status']===$value?'selected':''?>><?=e(status_label($value))?></option><?php endforeach;?></select></label><label><span>Priority</span><select name="priority"><?php foreach(['low','normal','high','urgent'] as $value):?><option value="<?=$value?>" <?=$selected['priority']===$value?'selected':''?>><?=e(status_label($value))?></option><?php endforeach;?></select></label><label><span>Assigned to</span><select name="assigned_user_id"><option value="0">Unassigned</option><?php foreach($administrators as $administrator):?><option value="<?=(int)$administrator['id']?>" <?=$selected['assigned_user_id']===(int)$administrator['id']?'selected':''?>><?=e($administrator['display_name'])?></option><?php endforeach;?></select></label><label><span>Snooze until</span><input type="datetime-local" name="snoozed_until" value="<?=e($selected['snoozed_until']?date('Y-m-d\TH:i',strtotime((string)$selected['snoozed_until'])):'')?>"></label></div><label><span>Private operator note</span><textarea name="note" maxlength="4000"><?=e($selected['note'])?></textarea></label><div class="unified-inbox-checks"><label><input type="checkbox" name="needs_response" value="1" <?=$selected['needs_response']?'checked':''?>> Needs response</label><label><input type="checkbox" name="pinned" value="1" <?=$selected['pinned']?'checked':''?>> Pin item</label></div><button type="submit">Save workflow</button>
                    </form>
                    <div class="unified-inbox-secondary-actions"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="unified_inbox_mark"><input type="hidden" name="source_type" value="<?=e($selected['source_type'])?>"><input type="hidden" name="source_id" value="<?=$selected['source_id']?>"><input type="hidden" name="read_override" value="<?=$selected['unread']?'read':'unread'?>"><button type="submit">Mark <?=$selected['unread']?'read':'unread'?></button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="unified_inbox_archive"><input type="hidden" name="source_type" value="<?=e($selected['source_type'])?>"><input type="hidden" name="source_id" value="<?=$selected['source_id']?>"><?php if(!$selected['archived']):?><input type="hidden" name="archive" value="1"><?php endif;?><button type="submit"><?=$selected['archived']?'Restore':'Archive'?></button></form></div>
                    <?php endif;?>
                <?php endif;?>
            </section>
        </div>
    </div>
    <script src="<?=e(app_url('assets/js/unified-inbox.js?v=20260730-v66D'))?>" defer></script>
    <?php
}

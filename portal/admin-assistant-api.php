<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/call-center.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/visitor-intelligence.php';
require_once __DIR__ . '/music-library.php';
require_once __DIR__ . '/events-calendar.php';

$user = current_user();

if (!$user || $user['role'] !== 'admin') {
    json_response([
        'ok' => false,
        'message' => 'Administrator authentication required.',
    ], 401);
}

if (!is_post()) {
    json_response([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

if (!same_origin_request()) {
    json_response([
        'ok' => false,
        'message' => 'Invalid request origin.',
    ], 403);
}

verify_csrf();
enforce_authenticated_action_limit($user);

$payload = json_decode(
    (string)file_get_contents('php://input'),
    true
);

if (!is_array($payload)) {
    $payload = $_POST;
}

$query = trim((string)($payload['query'] ?? ''));

if ($query === '') {
    json_response([
        'ok' => false,
        'message' => 'Enter a data question.',
    ], 422);
}

if (strlen($query) > 500) {
    json_response([
        'ok' => false,
        'message' => 'The data question is too long.',
    ], 422);
}

function admin_assistant_intent(string $query): string
{
    $normalized = strtolower($query);

    if (
        str_contains($normalized, 'recent call')
        || (
            str_contains($normalized, 'call history')
            && !str_contains($normalized, 'missed')
        )
    ) {
        return 'recent_calls';
    }

    if (
        str_contains($normalized, 'missed')
        || str_contains($normalized, 'voicemail')
        || str_contains($normalized, 'left message')
    ) {
        return 'missed_messages';
    }

    if (
        str_contains($normalized, 'communication')
        || str_contains($normalized, 'inbox')
        || str_contains($normalized, 'unread message')
    ) {
        return 'communications';
    }

    if (
        str_contains($normalized, 'crm')
        || str_contains($normalized, 'contact needing')
        || str_contains($normalized, 'follow up')
        || str_contains($normalized, 'follow-up')
    ) {
        return 'crm_attention';
    }

    if (
        str_contains($normalized, 'music library')
        || str_contains($normalized, 'top songs')
        || str_contains($normalized, 'music plays')
        || str_contains($normalized, 'albums')
        || str_contains($normalized, 'playlists')
    ) {
        return 'music_library';
    }

    if (
        str_contains($normalized, 'event')
        || str_contains($normalized, 'calendar')
        || str_contains($normalized, 'registration')
        || str_contains($normalized, 'attendee')
        || str_contains($normalized, 'waitlist')
    ) {
        return 'events';
    }

    if (
        str_contains($normalized, 'visitor')
        || str_contains($normalized, 'traffic')
        || str_contains($normalized, 'portfolio performance')
        || str_contains($normalized, 'portfolio analytics')
        || str_contains($normalized, 'conversion rate')
        || str_contains($normalized, 'returning contact')
        || str_contains($normalized, 'return visit')
    ) {
        return 'visitor_intelligence';
    }

    if (
        str_contains($normalized, 'project')
        || str_contains($normalized, 'current work')
    ) {
        return 'projects';
    }

    if (
        str_contains($normalized, 'notification')
        || str_contains($normalized, 'alert')
    ) {
        return 'notifications';
    }

    if (
        str_contains($normalized, 'client')
        || str_contains($normalized, 'customer account')
    ) {
        return 'clients';
    }

    return 'summary';
}

function admin_assistant_item(
    string $title,
    string $meta,
    string $detail,
    string $url,
    string $badge = ''
): array {
    return [
        'title' => $title,
        'meta' => $meta,
        'detail' => $detail,
        'url' => $url,
        'badge' => $badge,
    ];
}

function admin_assistant_recent_calls(): array
{
    $statement = db()->query(
        'SELECT request_record.*,
                contact.display_name AS contact_name,
                contact.company AS contact_company
         FROM call_center_requests request_record
         LEFT JOIN crm_contacts contact
           ON contact.id=request_record.crm_contact_id
         ORDER BY request_record.requested_at DESC,
                  request_record.id DESC
         LIMIT 10'
    );
    $rows = $statement->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $name = trim((string)(
            $row['contact_name']
            ?? $row['guest_name']
            ?? 'Unknown caller'
        ));
        $company = trim((string)(
            $row['contact_company']
            ?? $row['guest_company']
            ?? ''
        ));
        $type = call_center_request_type_label($row);
        $meta = $type
            . ' · '
            . status_label((string)$row['status'])
            . ' · '
            . format_datetime($row['requested_at']);
        $detailParts = [];

        if ($company !== '') {
            $detailParts[] = $company;
        }

        if (!empty($row['subject'])) {
            $detailParts[] = (string)$row['subject'];
        }

        if (!empty($row['duration_seconds'])) {
            $detailParts[] = call_center_seconds_label(
                (int)$row['duration_seconds']
            );
        }

        $items[] = admin_assistant_item(
            $name,
            $meta,
            implode(' · ', $detailParts),
            app_url(
                'portal/admin.php?view=call-center&request='
                . (int)$row['id']
            ),
            status_label((string)$row['status'])
        );
    }

    return [
        'title' => 'Most recent call history',
        'summary' => $rows
            ? 'The latest ' . count($rows) . ' Call Center records are shown below.'
            : 'No Call Center history is available yet.',
        'items' => $items,
    ];
}

function admin_assistant_missed_messages(): array
{
    $statement = db()->query(
        'SELECT request_record.*,
                contact.display_name AS contact_name,
                contact.company AS contact_company,
                (
                    SELECT COUNT(*)
                    FROM call_center_media media_record
                    WHERE media_record.request_id=request_record.id
                ) AS media_count
         FROM call_center_requests request_record
         LEFT JOIN crm_contacts contact
           ON contact.id=request_record.crm_contact_id
         WHERE request_record.status IN (
                "missed","voicemail","new","queued"
               )
           AND request_record.status NOT IN ("resolved","spam")
         ORDER BY
            CASE request_record.priority
                WHEN "urgent" THEN 1
                WHEN "high" THEN 2
                WHEN "normal" THEN 3
                ELSE 4
            END,
            request_record.requested_at DESC
         LIMIT 12'
    );
    $rows = $statement->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $name = trim((string)(
            $row['contact_name']
            ?? $row['guest_name']
            ?? 'Unknown contact'
        ));
        $type = call_center_request_type_label($row);
        $hasRecording = (int)$row['media_count'] > 0;
        $detail = trim((string)($row['message'] ?? ''));

        if ($detail === '') {
            $detail = (string)$row['subject'];
        }

        if ($hasRecording) {
            $detail = 'Recorded voicemail · ' . $detail;
        }

        $items[] = admin_assistant_item(
            $name,
            $type
                . ' · '
                . status_label((string)$row['status'])
                . ' · '
                . format_datetime($row['requested_at']),
            $detail,
            app_url(
                'portal/admin.php?view=call-center&request='
                . (int)$row['id']
            ),
            (string)$row['priority']
        );
    }

    return [
        'title' => 'Missed calls and messages',
        'summary' => $rows
            ? count($rows) . ' Call Center records may need follow-up.'
            : 'There are no missed calls or pending voicemail messages.',
        'items' => $items,
    ];
}

function admin_assistant_crm_attention(): array
{
    $statement = db()->query(
        'SELECT contact.*,
                (
                    SELECT COUNT(*)
                    FROM crm_opportunities opportunity
                    WHERE opportunity.contact_id=contact.id
                      AND opportunity.stage NOT IN ("won","lost")
                ) AS open_opportunities,
                (
                    SELECT COUNT(*)
                    FROM call_center_requests request_record
                    WHERE request_record.crm_contact_id=contact.id
                      AND request_record.status IN (
                        "missed","voicemail","new","queued"
                      )
                ) AS pending_call_items
         FROM crm_contacts contact
         WHERE contact.lifecycle_stage<>"closed"
           AND (
                contact.next_follow_up_at IS NOT NULL
                AND contact.next_follow_up_at<=UTC_TIMESTAMP()
                OR contact.last_contacted_at IS NULL
                OR EXISTS (
                    SELECT 1
                    FROM call_center_requests pending_request
                    WHERE pending_request.crm_contact_id=contact.id
                      AND pending_request.status IN (
                        "missed","voicemail","new","queued"
                      )
                )
                OR EXISTS (
                    SELECT 1
                    FROM crm_opportunities pending_opportunity
                    WHERE pending_opportunity.contact_id=contact.id
                      AND pending_opportunity.stage NOT IN ("won","lost")
                      AND pending_opportunity.next_action_at IS NOT NULL
                      AND pending_opportunity.next_action_at<=UTC_TIMESTAMP()
                )
           )
         ORDER BY
            CASE
                WHEN contact.next_follow_up_at IS NOT NULL
                 AND contact.next_follow_up_at<=UTC_TIMESTAMP()
                THEN 0
                ELSE 1
            END,
            contact.next_follow_up_at ASC,
            contact.last_inquiry_at DESC,
            contact.updated_at DESC
         LIMIT 12'
    );
    $rows = $statement->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $reasons = [];

        if (
            !empty($row['next_follow_up_at'])
            && strtotime((string)$row['next_follow_up_at']) <= time()
        ) {
            $reasons[] = 'Follow-up due '
                . format_datetime($row['next_follow_up_at']);
        }

        if (empty($row['last_contacted_at'])) {
            $reasons[] = 'Not contacted yet';
        }

        if ((int)$row['pending_call_items'] > 0) {
            $reasons[] = (int)$row['pending_call_items']
                . ' pending call/message item'
                . ((int)$row['pending_call_items'] === 1 ? '' : 's');
        }

        if ((int)$row['open_opportunities'] > 0) {
            $reasons[] = (int)$row['open_opportunities']
                . ' open opportunit'
                . ((int)$row['open_opportunities'] === 1 ? 'y' : 'ies');
        }

        $items[] = admin_assistant_item(
            (string)$row['display_name'],
            status_label((string)$row['lifecycle_stage'])
                . (
                    !empty($row['company'])
                        ? ' · ' . (string)$row['company']
                        : ''
                ),
            implode(' · ', $reasons),
            app_url(
                'portal/admin.php?view=crm&id='
                . (int)$row['id']
            ),
            'Attention'
        );
    }

    return [
        'title' => 'CRM contacts needing attention',
        'summary' => $rows
            ? count($rows) . ' CRM contacts have overdue or unresolved activity.'
            : 'No CRM contacts currently meet the attention rules.',
        'items' => $items,
    ];
}

function admin_assistant_communications(): array
{
    $statement = db()->query(
        'SELECT thread.*,
                client.display_name AS client_name,
                client.company AS client_company,
                (
                    SELECT message.body
                    FROM communication_messages message
                    WHERE message.thread_id=thread.id
                      AND message.sender_role="client"
                    ORDER BY message.id DESC
                    LIMIT 1
                ) AS latest_client_message
         FROM communication_threads thread
         JOIN users client ON client.id=thread.client_user_id
         WHERE thread.status IN ("waiting_admin","open")
         ORDER BY
            CASE thread.priority
                WHEN "urgent" THEN 1
                WHEN "high" THEN 2
                WHEN "normal" THEN 3
                ELSE 4
            END,
            thread.last_message_at DESC,
            thread.updated_at DESC
         LIMIT 12'
    );
    $rows = $statement->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $detail = trim((string)($row['latest_client_message'] ?? ''));

        if ($detail === '') {
            $detail = 'Open communication thread';
        }

        if (strlen($detail) > 180) {
            $detail = substr($detail, 0, 177) . '…';
        }

        $items[] = admin_assistant_item(
            (string)$row['subject'],
            (string)$row['client_name']
                . (
                    !empty($row['client_company'])
                        ? ' · ' . (string)$row['client_company']
                        : ''
                )
                . ' · '
                . format_datetime(
                    $row['last_message_at'] ?? $row['updated_at']
                ),
            $detail,
            app_url(
                'portal/admin.php?view=communications&thread='
                . (int)$row['id']
            ),
            status_label((string)$row['status'])
        );
    }

    return [
        'title' => 'Unread and waiting communications',
        'summary' => $rows
            ? count($rows) . ' communication threads are open or waiting for an administrator.'
            : 'No communication threads are waiting for an administrator.',
        'items' => $items,
    ];
}

function admin_assistant_music_library(): array
{
    if (!music_library_schema_available()) {
        return [
            'title' => 'Music Library',
            'summary' =>
                'Import database/music_library_v44.sql before music tracks, albums, playlists, and play results can be queried.',
            'items' => [],
        ];
    }

    $tracks = music_admin_tracks();
    usort(
        $tracks,
        static fn(array $left,array $right): int =>
            (int)$right['play_count']
            <=>(int)$left['play_count']
    );
    $tracks=array_slice($tracks,0,10);
    $albums = music_admin_albums();
    $playlists = music_admin_playlists();
    $items = [];

    foreach(array_slice($tracks,0,6) as $track){
        $items[] = admin_assistant_item(
            (string)$track['title'],
            (string)$track['artist_name']
                . (
                    $track['album_title']
                        ? ' · '.$track['album_title']
                        : ' · Single'
                ),
            (int)$track['play_count']
                . ' plays · '
                . status_label(
                    (string)$track['status']
                )
                . ' · '
                . music_duration_label(
                    $track['duration_seconds']!==null
                        ?(int)$track['duration_seconds']
                        :null
                ),
            app_url(
                'portal/admin.php?view=music&section=tracks&edit='
                .(int)$track['id']
            ),
            (int)$track['play_count']>0
                ?'Played'
                :'Song'
        );
    }

    $summary = count($tracks)
        . ' songs · '
        . count($albums)
        . ' albums · '
        . count($playlists)
        . ' playlists';

    return [
        'title' => 'Music Library',
        'summary' =>
            $summary
            . '. Results use protected track, collection, and first-party play data.',
        'items' => $items,
    ];
}

function admin_assistant_events(): array
{
    if (!events_schema_available()) {
        return [
            'title' => 'Events & Calendar',
            'summary' =>
                'Import database/events_calendar_v57.sql before events, registrations, reminders, and calendar analytics can be queried.',
            'items' => [],
        ];
    }

    $rows = array_slice(
        events_admin_events([
            'from' => gmdate('Y-m-d H:i:s'),
        ]),
        0,
        10
    );
    $items = [];

    foreach ($rows as $event) {
        $items[] = admin_assistant_item(
            (string)$event['title'],
            events_format_short_date($event)
                . ' · '
                . events_location_label($event),
            ((int)$event['registered_count'] + (int)$event['confirmed_count'])
                . ' registered · '
                . (int)$event['waitlist_count']
                . ' waitlisted · '
                . status_label((string)$event['status']),
            app_url(
                'portal/admin.php?view=events&edit=' . (int)$event['id']
            ),
            events_types()[$event['event_type']] ?? 'Event'
        );
    }

    $stats = events_admin_stats();

    return [
        'title' => 'Events & Calendar',
        'summary' =>
            (int)$stats['upcoming'] . ' upcoming events · '
            . (int)$stats['registrations'] . ' registrations · '
            . (int)$stats['waitlist'] . ' waitlisted · '
            . (int)$stats['reminders_ready'] . ' reminders due.',
        'items' => $items,
    ];
}

function admin_assistant_visitor_intelligence(): array
{
    if (!visitor_intelligence_schema_available()) {
        return [
            'title' => 'Visitor Intelligence',
            'summary' =>
                'Import database/visitor_intelligence_v43.sql before visitor and portfolio activity can be queried.',
            'items' => [],
        ];
    }

    $summary = visitor_intelligence_summary(30);
    $projects = array_slice(
        visitor_intelligence_portfolio_metrics(30),
        0,
        8
    );
    $items = [];

    $items[] = admin_assistant_item(
        '30-day visitor summary',
        (int)($summary['unique_visitors'] ?? 0)
            . ' visitors · '
            . (int)($summary['sessions'] ?? 0)
            . ' sessions',
        (int)($summary['portfolio_views'] ?? 0)
            . ' portfolio views · '
            . (int)($summary['chat_prompts'] ?? 0)
            . ' chat prompts · '
            . (int)($summary['conversions'] ?? 0)
            . ' conversion actions',
        app_url('portal/admin.php?view=analytics'),
        'Analytics'
    );

    foreach ($projects as $project) {
        $items[] = admin_assistant_item(
            (string)$project['title'],
            (int)$project['views']
                . ' views · '
                . (int)$project['unique_visitors']
                . ' visitors',
            (int)$project['project_clicks']
                . ' project clicks · '
                . (int)$project['inquiry_intents']
                . ' inquiry intents · '
                . (int)$project['conversions']
                . ' conversions',
            app_url(
                'portal/admin.php?view=portfolio&edit='
                . (int)$project['id']
            ),
            (int)$project['conversions'] > 0
                ? 'Converting'
                : 'Portfolio'
        );
    }

    return [
        'title' => 'Visitor and portfolio intelligence',
        'summary' =>
            'These results use first-party sessions and attributed portfolio, chat, resume, call, voicemail, and contact-form activity from the last 30 days.',
        'items' => $items,
    ];
}

function admin_assistant_projects(): array
{
    $statement = db()->query(
        'SELECT project.*,
                client.display_name AS client_name,
                client.company AS client_company
         FROM projects project
         JOIN users client ON client.id=project.client_user_id
         WHERE project.status NOT IN ("completed","archived")
         ORDER BY
            CASE project.priority
                WHEN "urgent" THEN 1
                WHEN "high" THEN 2
                WHEN "normal" THEN 3
                ELSE 4
            END,
            project.due_date IS NULL,
            project.due_date ASC,
            project.updated_at DESC
         LIMIT 12'
    );
    $rows = $statement->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $details = [(int)$row['progress'] . '% complete'];

        if (!empty($row['due_date'])) {
            $details[] = 'Due ' . (string)$row['due_date'];
        }

        if (!empty($row['next_milestone'])) {
            $details[] = (string)$row['next_milestone'];
        }

        $items[] = admin_assistant_item(
            (string)$row['title'],
            (string)($row['client_company'] ?: $row['client_name'])
                . ' · '
                . status_label((string)$row['status']),
            implode(' · ', $details),
            app_url(
                'portal/admin.php?view=projects&edit='
                . (int)$row['id']
            ),
            (string)$row['priority']
        );
    }

    return [
        'title' => 'Open projects',
        'summary' => $rows
            ? count($rows) . ' projects are currently open.'
            : 'There are no open projects.',
        'items' => $items,
    ];
}

function admin_assistant_notifications(int $userId): array
{
    $statement = db()->prepare(
        'SELECT *
         FROM portal_notifications
         WHERE recipient_user_id=:user_id
           AND is_read=0
         ORDER BY
            CASE priority
                WHEN "urgent" THEN 1
                WHEN "high" THEN 2
                WHEN "normal" THEN 3
                ELSE 4
            END,
            created_at DESC
         LIMIT 12'
    );
    $statement->execute(['user_id' => $userId]);
    $rows = $statement->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $items[] = admin_assistant_item(
            (string)$row['title'],
            status_label((string)$row['category'])
                . ' · '
                . format_datetime($row['created_at']),
            trim((string)($row['body'] ?? '')),
            notification_portal_link(
                ['role' => 'admin'],
                (string)($row['link_url'] ?? '')
            ),
            (string)$row['priority']
        );
    }

    return [
        'title' => 'Unread notifications',
        'summary' => $rows
            ? count($rows) . ' unread notifications are available.'
            : 'There are no unread notifications.',
        'items' => $items,
    ];
}

function admin_assistant_clients(): array
{
    $statement = db()->query(
        'SELECT client.*,
                (
                    SELECT COUNT(*)
                    FROM projects project
                    WHERE project.client_user_id=client.id
                      AND project.status NOT IN ("completed","archived")
                ) AS open_projects,
                (
                    SELECT COUNT(*)
                    FROM communication_threads thread
                    WHERE thread.client_user_id=client.id
                      AND thread.status IN ("waiting_admin","open")
                ) AS open_threads
         FROM users client
         WHERE client.role="client"
           AND client.status="active"
         ORDER BY client.updated_at DESC
         LIMIT 12'
    );
    $rows = $statement->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $details = [
            (int)$row['open_projects'] . ' open project'
                . ((int)$row['open_projects'] === 1 ? '' : 's'),
            (int)$row['open_threads'] . ' open communication'
                . ((int)$row['open_threads'] === 1 ? '' : 's'),
        ];

        $items[] = admin_assistant_item(
            (string)$row['display_name'],
            (string)($row['company'] ?: $row['email']),
            implode(' · ', $details),
            app_url(
                'portal/admin.php?view=clients&edit='
                . (int)$row['id']
            ),
            'Client'
        );
    }

    return [
        'title' => 'Active clients',
        'summary' => $rows
            ? count($rows) . ' active client accounts are shown.'
            : 'There are no active client accounts.',
        'items' => $items,
    ];
}

function admin_assistant_summary(int $userId): array
{
    $metrics = [
        'Call Center waiting' => (int)db()->query(
            'SELECT COUNT(*)
             FROM call_center_requests
             WHERE status IN ("new","queued","scheduled","ringing","voicemail","missed")'
        )->fetchColumn(),
        'CRM follow-ups due' => (int)db()->query(
            'SELECT COUNT(*)
             FROM crm_contacts
             WHERE lifecycle_stage<>"closed"
               AND next_follow_up_at IS NOT NULL
               AND next_follow_up_at<=UTC_TIMESTAMP()'
        )->fetchColumn(),
        'Open communication threads' => (int)db()->query(
            'SELECT COUNT(*)
             FROM communication_threads
             WHERE status IN ("waiting_admin","open")'
        )->fetchColumn(),
        'Open projects' => (int)db()->query(
            'SELECT COUNT(*)
             FROM projects
             WHERE status NOT IN ("completed","archived")'
        )->fetchColumn(),
    ];

    if (visitor_intelligence_schema_available()) {
        $visitorMetrics = visitor_intelligence_summary(30);
        $metrics['Visitors last 30 days'] = (int)(
            $visitorMetrics['unique_visitors'] ?? 0
        );
        $metrics['Portfolio views last 30 days'] = (int)(
            $visitorMetrics['portfolio_views'] ?? 0
        );
    }

    $notificationStatement = db()->prepare(
        'SELECT COUNT(*)
         FROM portal_notifications
         WHERE recipient_user_id=:user_id
           AND is_read=0'
    );
    $notificationStatement->execute(['user_id' => $userId]);
    $metrics['Unread notifications'] = (int)$notificationStatement->fetchColumn();

    $items = [];

    foreach ($metrics as $label => $value) {
        $url = match ($label) {
            'Call Center waiting' =>
                app_url('portal/admin.php?view=call-center'),
            'CRM follow-ups due' =>
                app_url('portal/admin.php?view=crm'),
            'Open communication threads' =>
                app_url('portal/admin.php?view=communications'),
            'Open projects' =>
                app_url('portal/admin.php?view=projects'),
            'Visitors last 30 days',
            'Portfolio views last 30 days' =>
                app_url('portal/admin.php?view=analytics'),
            default =>
                app_url('portal/admin.php?view=notifications'),
        };

        $items[] = admin_assistant_item(
            $label,
            (string)$value,
            $value > 0
                ? 'Open the related workspace to review the records.'
                : 'No immediate items in this category.',
            $url,
            $value > 0 ? 'Review' : 'Clear'
        );
    }

    return [
        'title' => 'Administrator data summary',
        'summary' =>
            'I can query calls, missed messages, CRM follow-ups, the Music Library, Events & Calendar, visitor intelligence, portfolio performance, communications, projects, clients, and notifications using protected predefined database queries.',
        'items' => $items,
    ];
}

$intent = admin_assistant_intent($query);

try {
    $result = match ($intent) {
        'recent_calls' => admin_assistant_recent_calls(),
        'missed_messages' => admin_assistant_missed_messages(),
        'crm_attention' => admin_assistant_crm_attention(),
        'communications' => admin_assistant_communications(),
        'music_library' =>
            admin_assistant_music_library(),
        'events' => admin_assistant_events(),
        'visitor_intelligence' =>
            admin_assistant_visitor_intelligence(),
        'projects' => admin_assistant_projects(),
        'notifications' => admin_assistant_notifications((int)$user['id']),
        'clients' => admin_assistant_clients(),
        default => admin_assistant_summary((int)$user['id']),
    };

    log_activity(
        'admin_assistant_query',
        'admin_assistant',
        null,
        [
            'intent' => $intent,
            'query' => substr($query, 0, 500),
            'result_count' => count($result['items'] ?? []),
        ]
    );

    json_response([
        'ok' => true,
        'intent' => $intent,
        'query' => $query,
        'title' => $result['title'],
        'summary' => $result['summary'],
        'items' => $result['items'],
        'suggestions' => [
            'Most recent call history',
            'Music Library',
            'Upcoming events',
            'Portfolio performance',
            'Visitor activity',
            'CRM contacts needing attention',
        ],
        'generated_at' => gmdate('Y-m-d H:i:s'),
    ]);
} catch (Throwable $exception) {
    log_activity(
        'admin_assistant_query_failed',
        'admin_assistant',
        null,
        [
            'intent' => $intent,
            'query' => substr($query, 0, 500),
            'error' => substr($exception->getMessage(), 0, 500),
        ]
    );

    json_response([
        'ok' => false,
        'message' =>
            'The administrator assistant could not query that data right now.',
    ], 500);
}

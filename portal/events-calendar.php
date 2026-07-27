<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

function events_schema_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "calendar_events",
                    "calendar_event_registrations",
                    "calendar_event_reminders"
               )'
        );
        $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function events_settings(): array
{
    $timezone = trim((string)setting(
        'events_default_timezone',
        'America/Phoenix'
    ));

    try {
        new DateTimeZone($timezone);
    } catch (Throwable) {
        $timezone = 'America/Phoenix';
    }

    return [
        'title' => trim((string)setting(
            'events_title',
            'Events'
        )),
        'intro' => trim((string)setting(
            'events_intro',
            'Upcoming events, sessions, and appearances.'
        )),
        'description' => trim((string)setting(
            'events_description',
            'Browse upcoming North Mountain Media events, workshops, performances, meetings, and live sessions.'
        )),
        'default_timezone' => $timezone,
        'default_location' => trim((string)setting(
            'events_default_location',
            'Phoenix, Arizona'
        )),
        'posts_per_page' => max(
            3,
            min(48, (int)setting('events_posts_per_page', '12'))
        ),
        'calendar_start_monday' => setting(
            'events_calendar_start_monday',
            '0'
        ) === '1',
        'ics_enabled' => setting(
            'events_ics_enabled',
            '1'
        ) !== '0',
    ];
}

function events_save_setting(string $key, ?string $value): void
{
    db()->prepare(
        'INSERT INTO settings (setting_key,setting_value)
         VALUES (:setting_key,:setting_value)
         ON DUPLICATE KEY UPDATE
            setting_value=VALUES(setting_value)'
    )->execute([
        'setting_key' => $key,
        'setting_value' => $value,
    ]);
}

function events_types(): array
{
    return [
        'meeting' => 'Meeting',
        'webinar' => 'Webinar',
        'workshop' => 'Workshop',
        'performance' => 'Performance',
        'community' => 'Community',
        'launch' => 'Launch',
        'deadline' => 'Deadline',
        'other' => 'Other',
    ];
}

function events_formats(): array
{
    return [
        'in_person' => 'In person',
        'virtual' => 'Virtual',
        'hybrid' => 'Hybrid',
    ];
}

function events_statuses(): array
{
    return [
        'draft' => 'Draft',
        'published' => 'Published',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'archived' => 'Archived',
    ];
}

function events_registration_statuses(): array
{
    return [
        'registered' => 'Registered',
        'waitlist' => 'Waitlist',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'attended' => 'Attended',
        'no_show' => 'No show',
    ];
}

function events_timezone_options(): array
{
    return [
        'America/Phoenix' => 'Arizona — America/Phoenix',
        'America/Los_Angeles' => 'Pacific — America/Los_Angeles',
        'America/Denver' => 'Mountain — America/Denver',
        'America/Chicago' => 'Central — America/Chicago',
        'America/New_York' => 'Eastern — America/New_York',
        'UTC' => 'UTC',
    ];
}

function events_valid_timezone(string $timezone): string
{
    $timezone = trim($timezone);

    try {
        new DateTimeZone($timezone);
        return $timezone;
    } catch (Throwable) {
        return events_settings()['default_timezone'];
    }
}

function events_normalize_datetime(
    ?string $value,
    string $timezone
): ?string {
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $timezone = events_valid_timezone($timezone);
    $local = DateTimeImmutable::createFromFormat(
        'Y-m-d\TH:i',
        $value,
        new DateTimeZone($timezone)
    );

    if (!$local) {
        $local = new DateTimeImmutable(
            $value,
            new DateTimeZone($timezone)
        );
    }

    return $local
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s');
}

function events_local_input(
    ?string $utc,
    string $timezone
): string {
    $utc = trim((string)$utc);

    if ($utc === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable(
            $utc,
            new DateTimeZone('UTC')
        ))->setTimezone(
            new DateTimeZone(events_valid_timezone($timezone))
        )->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function events_local_datetime(
    ?string $utc,
    string $timezone
): ?DateTimeImmutable {
    $utc = trim((string)$utc);

    if ($utc === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable(
            $utc,
            new DateTimeZone('UTC')
        ))->setTimezone(
            new DateTimeZone(events_valid_timezone($timezone))
        );
    } catch (Throwable) {
        return null;
    }
}

function events_format_date(array $event): string
{
    $date = events_local_datetime(
        $event['start_at'] ?? null,
        (string)($event['timezone'] ?? events_settings()['default_timezone'])
    );

    if (!$date) {
        return 'Date to be announced';
    }

    if (!empty($event['all_day'])) {
        return $date->format('l, F j, Y') . ' · All day';
    }

    $end = events_local_datetime(
        $event['end_at'] ?? null,
        (string)($event['timezone'] ?? events_settings()['default_timezone'])
    );

    if ($end && $end->format('Y-m-d') === $date->format('Y-m-d')) {
        return $date->format('l, F j, Y · g:i A')
            . '–'
            . $end->format('g:i A');
    }

    if ($end) {
        return $date->format('M j, Y · g:i A')
            . ' – '
            . $end->format('M j, Y · g:i A');
    }

    return $date->format('l, F j, Y · g:i A');
}

function events_format_short_date(array $event): string
{
    $date = events_local_datetime(
        $event['start_at'] ?? null,
        (string)($event['timezone'] ?? events_settings()['default_timezone'])
    );

    if (!$date) {
        return 'TBA';
    }

    return !empty($event['all_day'])
        ? $date->format('M j')
        : $date->format('M j · g:i A');
}

function events_month_context(?string $month = null): array
{
    $settings = events_settings();
    $timezone = new DateTimeZone($settings['default_timezone']);
    $month = trim((string)$month);

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = (new DateTimeImmutable('now', $timezone))
            ->format('Y-m');
    }

    $first = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $month . '-01',
        $timezone
    );

    if (!$first) {
        $first = new DateTimeImmutable(
            'first day of this month',
            $timezone
        );
    }

    $last = $first->modify('last day of this month');
    $weekStart = $settings['calendar_start_monday'] ? 1 : 0;
    $firstWeekday = (int)$first->format('w');
    $leadingDays = ($firstWeekday - $weekStart + 7) % 7;
    $gridStart = $first->modify('-' . $leadingDays . ' days');
    $gridEnd = $gridStart->modify('+41 days')->setTime(23, 59, 59);

    return [
        'month' => $first->format('Y-m'),
        'label' => $first->format('F Y'),
        'first' => $first,
        'last' => $last,
        'grid_start' => $gridStart,
        'grid_end' => $gridEnd,
        'utc_start' => $gridStart
            ->setTime(0, 0)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s'),
        'utc_end' => $gridEnd
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s'),
        'previous' => $first->modify('-1 month')->format('Y-m'),
        'next' => $first->modify('+1 month')->format('Y-m'),
        'week_start' => $weekStart,
    ];
}

function events_cover_storage_directory(): string
{
    $directory = NMM_ROOT . '/storage/event-covers';

    if (
        !is_dir($directory)
        && !mkdir($directory, 0750, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'The event cover storage directory could not be created.'
        );
    }

    return $directory;
}

function events_cover_limit_bytes(): int
{
    return max(
        1024 * 1024,
        (int)(nmm_config('uploads')['max_event_image_bytes'] ?? 10 * 1024 * 1024)
    );
}

function events_cover_url(array $event): string
{
    if (empty($event['cover_stored_name']) || empty($event['id'])) {
        return '';
    }

    $version = rawurlencode((string)(
        $event['updated_at']
        ?? $event['published_at']
        ?? '1'
    ));

    return app_url(
        'event-cover.php?id=' . (int)$event['id'] . '&v=' . $version
    );
}

function events_store_cover(
    int $eventId,
    array $upload,
    ?array $existing = null
): array {
    if (
        (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
        === UPLOAD_ERR_NO_FILE
    ) {
        return [];
    }

    if (
        (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException('The event cover upload failed.');
    }

    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    $limit = events_cover_limit_bytes();

    if (
        $temporary === ''
        || !is_uploaded_file($temporary)
        || $size <= 0
        || $size > $limit
    ) {
        throw new RuntimeException(
            'Event covers must be valid image uploads no larger than '
            . format_bytes($limit)
            . '.'
        );
    }

    $info = @getimagesize($temporary);

    if (!is_array($info)) {
        throw new RuntimeException('The event cover is not a valid image.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException(
            'Event covers must be JPG, PNG, WebP, or GIF.'
        );
    }

    $storedName = sprintf(
        'event-%d-%s.%s',
        $eventId,
        bin2hex(random_bytes(18)),
        $extensions[$mime]
    );
    $destination = events_cover_storage_directory() . '/' . $storedName;

    if (!move_uploaded_file($temporary, $destination)) {
        throw new RuntimeException('The event cover could not be stored.');
    }

    chmod($destination, 0640);

    $oldPath = '';

    if (!empty($existing['cover_stored_name'])) {
        $oldPath = events_cover_storage_directory()
            . '/'
            . basename((string)$existing['cover_stored_name']);
    }

    if ($oldPath !== '' && is_file($oldPath)) {
        @unlink($oldPath);
    }

    return [
        'cover_original_name' => substr(
            basename((string)($upload['name'] ?? 'event-cover')),
            0,
            255
        ),
        'cover_stored_name' => $storedName,
        'cover_mime_type' => $mime,
        'cover_size_bytes' => filesize($destination),
        'cover_width_px' => (int)($info[0] ?? 0) ?: null,
        'cover_height_px' => (int)($info[1] ?? 0) ?: null,
    ];
}

function events_delete_cover_file(array $event): void
{
    if (empty($event['cover_stored_name'])) {
        return;
    }

    $path = events_cover_storage_directory()
        . '/'
        . basename((string)$event['cover_stored_name']);

    if (is_file($path)) {
        @unlink($path);
    }
}

function events_tag_list(?string $tags): array
{
    $items = preg_split('/[,\n]+/', (string)$tags) ?: [];
    $output = [];

    foreach ($items as $item) {
        $item = trim($item);

        if ($item !== '' && !in_array($item, $output, true)) {
            $output[] = $item;
        }
    }

    return array_slice($output, 0, 24);
}

function events_location_label(array $event): string
{
    $format = (string)($event['format_type'] ?? 'in_person');
    $parts = array_values(array_filter([
        trim((string)($event['location_name'] ?? '')),
        trim((string)($event['city'] ?? '')),
        trim((string)($event['region'] ?? '')),
    ]));
    $physical = implode(', ', $parts);

    if ($format === 'virtual') {
        return 'Online event';
    }

    if ($format === 'hybrid') {
        return $physical !== ''
            ? $physical . ' + online'
            : 'In person + online';
    }

    return $physical !== '' ? $physical : 'Location to be announced';
}

function events_capacity_summary(array $event): array
{
    $capacity = (int)($event['capacity'] ?? 0);
    $registered = (int)($event['registered_count'] ?? 0);
    $confirmed = (int)($event['confirmed_count'] ?? 0);
    $attending = $registered + $confirmed;
    $remaining = $capacity > 0
        ? max(0, $capacity - $attending)
        : null;

    return [
        'capacity' => $capacity,
        'attending' => $attending,
        'remaining' => $remaining,
        'full' => $capacity > 0 && $remaining === 0,
        'label' => $capacity > 0
            ? $remaining . ' of ' . $capacity . ' spaces available'
            : 'Open registration',
    ];
}

function events_registration_state(array $event): array
{
    if (empty($event['registration_enabled'])) {
        return [
            'open' => false,
            'status' => 'disabled',
            'label' => 'Registration is not required',
        ];
    }

    if (($event['status'] ?? '') === 'cancelled') {
        return [
            'open' => false,
            'status' => 'cancelled',
            'label' => 'Event cancelled',
        ];
    }

    $deadline = trim((string)($event['registration_deadline'] ?? ''));

    if ($deadline !== '' && strtotime($deadline . ' UTC') < time()) {
        return [
            'open' => false,
            'status' => 'closed',
            'label' => 'Registration closed',
        ];
    }

    if (strtotime((string)$event['start_at'] . ' UTC') <= time()) {
        return [
            'open' => false,
            'status' => 'started',
            'label' => 'This event has started',
        ];
    }

    $capacity = events_capacity_summary($event);

    if ($capacity['full']) {
        if (!empty($event['waitlist_enabled'])) {
            return [
                'open' => true,
                'status' => 'waitlist',
                'label' => 'Join the waitlist',
            ];
        }

        return [
            'open' => false,
            'status' => 'full',
            'label' => 'Event is full',
        ];
    }

    return [
        'open' => true,
        'status' => 'open',
        'label' => 'Register for this event',
    ];
}

function events_select_columns(): string
{
    return 'event.*,
            (SELECT COALESCE(SUM(registration.party_size),0)
             FROM calendar_event_registrations registration
             WHERE registration.event_id=event.id
               AND registration.status="registered") AS registered_count,
            (SELECT COALESCE(SUM(registration.party_size),0)
             FROM calendar_event_registrations registration
             WHERE registration.event_id=event.id
               AND registration.status="confirmed") AS confirmed_count,
            (SELECT COALESCE(SUM(registration.party_size),0)
             FROM calendar_event_registrations registration
             WHERE registration.event_id=event.id
               AND registration.status="waitlist") AS waitlist_count,
            (SELECT COALESCE(SUM(registration.party_size),0)
             FROM calendar_event_registrations registration
             WHERE registration.event_id=event.id
               AND registration.status="attended") AS attended_count';
}

function events_admin_event(int $eventId): ?array
{
    if (!events_schema_available() || $eventId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT ' . events_select_columns() . '
         FROM calendar_events event
         WHERE event.id=:event_id
         LIMIT 1'
    );
    $statement->execute(['event_id' => $eventId]);
    $event = $statement->fetch();

    return $event ?: null;
}

function events_unique_slug(string $slug, int $ignoreId = 0): string
{
    $slug = slugify($slug);

    if ($slug === '') {
        $slug = 'event';
    }

    $candidate = $slug;
    $suffix = 2;

    while (true) {
        $statement = db()->prepare(
            'SELECT id
             FROM calendar_events
             WHERE slug=:slug
               AND id<>:ignore_id
             LIMIT 1'
        );
        $statement->execute([
            'slug' => $candidate,
            'ignore_id' => $ignoreId,
        ]);

        if (!$statement->fetchColumn()) {
            return $candidate;
        }

        $candidate = substr(
            $slug,
            0,
            max(1, 180 - strlen((string)$suffix))
        ) . '-' . $suffix;
        $suffix++;
    }
}

function events_admin_events(array $filters = []): array
{
    if (!events_schema_available()) {
        return [];
    }

    $where = ['1=1'];
    $parameters = [];
    $status = trim((string)($filters['status'] ?? ''));
    $type = trim((string)($filters['type'] ?? ''));
    $search = trim((string)($filters['search'] ?? ''));
    $from = trim((string)($filters['from'] ?? ''));
    $to = trim((string)($filters['to'] ?? ''));

    if (isset(events_statuses()[$status])) {
        $where[] = 'event.status=:status';
        $parameters['status'] = $status;
    }

    if (isset(events_types()[$type])) {
        $where[] = 'event.event_type=:event_type';
        $parameters['event_type'] = $type;
    }

    if ($search !== '') {
        $where[] = '(
            event.title LIKE :search
            OR event.summary LIKE :search
            OR event.description LIKE :search
            OR event.location_name LIKE :search
            OR event.tags LIKE :search
        )';
        $parameters['search'] = '%' . $search . '%';
    }

    if ($from !== '') {
        $where[] = 'event.start_at>=:from_at';
        $parameters['from_at'] = $from;
    }

    if ($to !== '') {
        $where[] = 'event.start_at<=:to_at';
        $parameters['to_at'] = $to;
    }

    $statement = db()->prepare(
        'SELECT ' . events_select_columns() . '
         FROM calendar_events event
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY event.start_at ASC,event.featured DESC,event.id DESC
         LIMIT 500'
    );
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function events_public_events(array $filters = []): array
{
    if (!events_schema_available()) {
        return [];
    }

    $where = [
        'event.status="published"',
        'event.visibility="public"',
        '(event.published_at IS NULL OR event.published_at<=UTC_TIMESTAMP())',
    ];
    $parameters = [];
    $type = trim((string)($filters['type'] ?? ''));
    $search = trim((string)($filters['search'] ?? ''));
    $from = trim((string)($filters['from'] ?? ''));
    $to = trim((string)($filters['to'] ?? ''));
    $includePast = !empty($filters['include_past']);
    $limit = max(1, min(250, (int)($filters['limit'] ?? 100)));
    $offset = max(0, (int)($filters['offset'] ?? 0));

    if (!$includePast && $from === '') {
        $where[] = 'COALESCE(event.end_at,event.start_at)>=UTC_TIMESTAMP()';
    }

    if (isset(events_types()[$type])) {
        $where[] = 'event.event_type=:event_type';
        $parameters['event_type'] = $type;
    }

    if ($search !== '') {
        $where[] = '(
            event.title LIKE :search
            OR event.summary LIKE :search
            OR event.description LIKE :search
            OR event.location_name LIKE :search
            OR event.tags LIKE :search
        )';
        $parameters['search'] = '%' . $search . '%';
    }

    if ($from !== '') {
        $where[] = 'COALESCE(event.end_at,event.start_at)>=:from_at';
        $parameters['from_at'] = $from;
    }

    if ($to !== '') {
        $where[] = 'event.start_at<=:to_at';
        $parameters['to_at'] = $to;
    }

    $statement = db()->prepare(
        'SELECT ' . events_select_columns() . '
         FROM calendar_events event
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY event.featured DESC,event.start_at ASC,event.id ASC
         LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $statement->execute($parameters);
    $events = $statement->fetchAll();

    foreach ($events as &$event) {
        $event['url'] = app_url(
            'event.php?slug=' . rawurlencode((string)$event['slug'])
        );
        $event['cover_url'] = events_cover_url($event);
        $event['tags_list'] = events_tag_list($event['tags'] ?? null);
        $event['date_label'] = events_format_date($event);
        $event['short_date_label'] = events_format_short_date($event);
        $event['location_label'] = events_location_label($event);
        $event['registration_state'] = events_registration_state($event);
        $event['capacity_summary'] = events_capacity_summary($event);
    }
    unset($event);

    return $events;
}

function events_public_count(array $filters = []): int
{
    if (!events_schema_available()) {
        return 0;
    }

    $where = [
        'status="published"',
        'visibility="public"',
        '(published_at IS NULL OR published_at<=UTC_TIMESTAMP())',
    ];
    $parameters = [];
    $type = trim((string)($filters['type'] ?? ''));
    $search = trim((string)($filters['search'] ?? ''));
    $from = trim((string)($filters['from'] ?? ''));
    $to = trim((string)($filters['to'] ?? ''));
    $includePast = !empty($filters['include_past']);

    if (!$includePast && $from === '') {
        $where[] = 'COALESCE(end_at,start_at)>=UTC_TIMESTAMP()';
    }

    if (isset(events_types()[$type])) {
        $where[] = 'event_type=:event_type';
        $parameters['event_type'] = $type;
    }

    if ($search !== '') {
        $where[] = '(
            title LIKE :search
            OR summary LIKE :search
            OR description LIKE :search
            OR location_name LIKE :search
            OR tags LIKE :search
        )';
        $parameters['search'] = '%' . $search . '%';
    }

    if ($from !== '') {
        $where[] = 'COALESCE(end_at,start_at)>=:from_at';
        $parameters['from_at'] = $from;
    }

    if ($to !== '') {
        $where[] = 'start_at<=:to_at';
        $parameters['to_at'] = $to;
    }

    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM calendar_events
         WHERE ' . implode(' AND ', $where)
    );
    $statement->execute($parameters);

    return (int)$statement->fetchColumn();
}

function events_public_event_by_slug(
    string $slug,
    bool $allowUnlisted = true
): ?array {
    if (!events_schema_available()) {
        return null;
    }

    $slug = slugify($slug);

    if ($slug === '') {
        return null;
    }

    $visibility = $allowUnlisted
        ? 'event.visibility IN ("public","unlisted")'
        : 'event.visibility="public"';
    $statement = db()->prepare(
        'SELECT ' . events_select_columns() . '
         FROM calendar_events event
         WHERE event.slug=:slug
           AND event.status IN ("published","cancelled","completed")
           AND ' . $visibility . '
           AND (
                event.published_at IS NULL
                OR event.published_at<=UTC_TIMESTAMP()
           )
         LIMIT 1'
    );
    $statement->execute(['slug' => $slug]);
    $event = $statement->fetch();

    if (!$event) {
        return null;
    }

    $event['url'] = app_url(
        'event.php?slug=' . rawurlencode((string)$event['slug'])
    );
    $event['cover_url'] = events_cover_url($event);
    $event['tags_list'] = events_tag_list($event['tags'] ?? null);
    $event['date_label'] = events_format_date($event);
    $event['short_date_label'] = events_format_short_date($event);
    $event['location_label'] = events_location_label($event);
    $event['registration_state'] = events_registration_state($event);
    $event['capacity_summary'] = events_capacity_summary($event);

    return $event;
}

function events_public_preview(int $eventId): ?array
{
    $event = events_admin_event($eventId);

    if (!$event) {
        return null;
    }

    $event['url'] = app_url(
        'event.php?preview=1&id=' . (int)$event['id']
    );
    $event['cover_url'] = events_cover_url($event);
    $event['tags_list'] = events_tag_list($event['tags'] ?? null);
    $event['date_label'] = events_format_date($event);
    $event['short_date_label'] = events_format_short_date($event);
    $event['location_label'] = events_location_label($event);
    $event['registration_state'] = events_registration_state($event);
    $event['capacity_summary'] = events_capacity_summary($event);

    return $event;
}

function events_calendar_days(
    array $monthContext,
    array $events
): array {
    $days = [];
    $cursor = $monthContext['grid_start'];

    for ($index = 0; $index < 42; $index++) {
        $key = $cursor->format('Y-m-d');
        $days[$key] = [
            'date' => $cursor,
            'current_month' => $cursor->format('Y-m') === $monthContext['month'],
            'today' => $key === (new DateTimeImmutable(
                'now',
                $cursor->getTimezone()
            ))->format('Y-m-d'),
            'events' => [],
        ];
        $cursor = $cursor->modify('+1 day');
    }

    foreach ($events as $event) {
        $date = events_local_datetime(
            $event['start_at'] ?? null,
            (string)($event['timezone'] ?? events_settings()['default_timezone'])
        );

        if (!$date) {
            continue;
        }

        $key = $date->format('Y-m-d');

        if (isset($days[$key])) {
            $days[$key]['events'][] = $event;
        }
    }

    return array_values($days);
}

function events_registrations(int $eventId): array
{
    if (!events_schema_available() || $eventId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT registration.*,
                contact.lifecycle_stage,
                contact.owner_user_id
         FROM calendar_event_registrations registration
         LEFT JOIN crm_contacts contact
           ON contact.id=registration.crm_contact_id
         WHERE registration.event_id=:event_id
         ORDER BY FIELD(
            registration.status,
            "confirmed","registered","waitlist","attended",
            "no_show","cancelled"
         ),registration.registered_at DESC,registration.id DESC'
    );
    $statement->execute(['event_id' => $eventId]);

    return $statement->fetchAll();
}

function events_reminders(int $eventId): array
{
    if (!events_schema_available() || $eventId <= 0) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT reminder.*,
                registration.display_name,
                registration.email,
                registration.status AS registration_status
         FROM calendar_event_reminders reminder
         JOIN calendar_event_registrations registration
           ON registration.id=reminder.registration_id
         WHERE reminder.event_id=:event_id
         ORDER BY reminder.scheduled_for ASC,reminder.id ASC'
    );
    $statement->execute(['event_id' => $eventId]);

    return $statement->fetchAll();
}

function events_admin_stats(): array
{
    if (!events_schema_available()) {
        return [
            'upcoming' => 0,
            'published' => 0,
            'registrations' => 0,
            'waitlist' => 0,
            'reminders_ready' => 0,
        ];
    }

    $statement = db()->query(
        'SELECT
            (SELECT COUNT(*)
             FROM calendar_events
             WHERE status IN ("published","draft")
               AND COALESCE(end_at,start_at)>=UTC_TIMESTAMP()) AS upcoming,
            (SELECT COUNT(*)
             FROM calendar_events
             WHERE status="published") AS published,
            (SELECT COUNT(*)
             FROM calendar_event_registrations
             WHERE status IN ("registered","confirmed","attended")) AS registrations,
            (SELECT COUNT(*)
             FROM calendar_event_registrations
             WHERE status="waitlist") AS waitlist,
            (SELECT COUNT(*)
             FROM calendar_event_reminders
             WHERE status IN ("pending","ready")
               AND scheduled_for<=UTC_TIMESTAMP()) AS reminders_ready'
    );

    $row = $statement->fetch() ?: [];

    return array_map(
        static fn(mixed $value): int => (int)($value ?? 0),
        $row
    );
}

function events_analytics(int $days = 30): array
{
    $days = max(1, min(365, $days));

    if (!visitor_intelligence_schema_available()) {
        return [];
    }

    try {
        $statement = db()->query(
            'SELECT
                SUM(event_type="events_calendar_view") AS calendar_views,
                SUM(event_type="event_detail_view") AS event_views,
                SUM(event_type="event_registration_submit") AS registrations,
                SUM(event_type="event_ics_download") AS calendar_downloads,
                COUNT(DISTINCT CASE
                    WHEN event_type="event_detail_view"
                    THEN JSON_UNQUOTE(
                        JSON_EXTRACT(metadata_json,"$.event_id")
                    ) END
                ) AS events_reached
             FROM visitor_events
             WHERE occurred_at>=UTC_TIMESTAMP()-INTERVAL '
             . $days
             . ' DAY'
        );

        return array_map(
            static fn(mixed $value): int => (int)($value ?? 0),
            $statement->fetch() ?: []
        );
    } catch (Throwable) {
        return [];
    }
}

function events_event_metrics(int $days = 30): array
{
    $days = max(1, min(365, $days));

    if (
        !events_schema_available()
        || !visitor_intelligence_schema_available()
    ) {
        return [];
    }

    try {
        return db()->query(
            'SELECT event.id,event.title,event.slug,event.status,event.start_at,
                    COUNT(visit.id) AS views,
                    COUNT(DISTINCT visit.visitor_id) AS visitors,
                    (SELECT COUNT(*)
                     FROM calendar_event_registrations registration
                     WHERE registration.event_id=event.id
                       AND registration.status IN (
                            "registered","confirmed","attended"
                       )) AS registrations,
                    MAX(visit.occurred_at) AS last_view_at
             FROM calendar_events event
             LEFT JOIN visitor_events visit
               ON visit.event_type="event_detail_view"
              AND CAST(
                    JSON_UNQUOTE(
                        JSON_EXTRACT(visit.metadata_json,"$.event_id")
                    ) AS UNSIGNED
                  )=event.id
              AND visit.occurred_at>=UTC_TIMESTAMP()-INTERVAL '
             . $days
             . ' DAY
             GROUP BY event.id
             ORDER BY views DESC,event.start_at ASC,event.id DESC
             LIMIT 25'
        )->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function events_find_or_create_contact(array $registration): int
{
    $email = strtolower(trim((string)$registration['email']));
    $name = trim((string)$registration['display_name']);
    $company = trim((string)($registration['company'] ?? ''));
    $phone = trim((string)($registration['phone'] ?? ''));

    $statement = db()->prepare(
        'SELECT id
         FROM crm_contacts
         WHERE email=:email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $contactId = (int)($statement->fetchColumn() ?: 0);

    if ($contactId > 0) {
        db()->prepare(
            'UPDATE crm_contacts
             SET display_name=CASE
                    WHEN display_name="" THEN :display_name
                    ELSE display_name
                 END,
                 company=COALESCE(NULLIF(company,""),:company),
                 phone=COALESCE(NULLIF(phone,""),:phone),
                 source=CASE
                    WHEN source="" THEN "event_registration"
                    ELSE source
                 END,
                 last_inquiry_at=UTC_TIMESTAMP()
             WHERE id=:contact_id'
        )->execute([
            'display_name' => $name,
            'company' => $company !== '' ? $company : null,
            'phone' => $phone !== '' ? $phone : null,
            'contact_id' => $contactId,
        ]);

        return $contactId;
    }

    db()->prepare(
        'INSERT INTO crm_contacts
            (email,display_name,company,phone,lifecycle_stage,source,last_inquiry_at)
         VALUES
            (:email,:display_name,:company,:phone,"lead","event_registration",UTC_TIMESTAMP())'
    )->execute([
        'email' => $email,
        'display_name' => $name,
        'company' => $company !== '' ? $company : null,
        'phone' => $phone !== '' ? $phone : null,
    ]);

    return (int)db()->lastInsertId();
}

function events_schedule_reminder(
    array $event,
    int $registrationId
): void {
    $hours = max(1, min(720, (int)($event['reminder_hours'] ?? 24)));
    $scheduled = (new DateTimeImmutable(
        (string)$event['start_at'],
        new DateTimeZone('UTC')
    ))->modify('-' . $hours . ' hours');

    if ($scheduled->getTimestamp() <= time()) {
        $scheduled = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    db()->prepare(
        'INSERT IGNORE INTO calendar_event_reminders
            (event_id,registration_id,reminder_type,scheduled_for,status)
         VALUES
            (:event_id,:registration_id,"email",:scheduled_for,"pending")'
    )->execute([
        'event_id' => (int)$event['id'],
        'registration_id' => $registrationId,
        'scheduled_for' => $scheduled->format('Y-m-d H:i:s'),
    ]);
}

function events_register_public(
    array $event,
    array $input,
    ?array $portalUser = null
): array {
    $state = events_registration_state($event);

    if (!$state['open']) {
        throw new RuntimeException($state['label']);
    }

    $name = substr(trim((string)($input['display_name'] ?? '')), 0, 160);
    $email = strtolower(substr(trim((string)($input['email'] ?? '')), 0, 190));
    $phone = substr(trim((string)($input['phone'] ?? '')), 0, 60);
    $company = substr(trim((string)($input['company'] ?? '')), 0, 190);
    $notes = substr(trim((string)($input['notes'] ?? '')), 0, 4000);
    $partySize = max(1, min(20, (int)($input['party_size'] ?? 1)));
    $reminderOptIn = !empty($input['reminder_opt_in']);

    if ($name === '') {
        throw new RuntimeException('Enter your name.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    $registrationStatus = 'registered';
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $eventLock = $pdo->prepare(
            'SELECT capacity,waitlist_enabled
             FROM calendar_events
             WHERE id=:event_id
             LIMIT 1
             FOR UPDATE'
        );
        $eventLock->execute(['event_id' => (int)$event['id']]);
        $lockedEvent = $eventLock->fetch();

        if (!$lockedEvent) {
            throw new RuntimeException('The requested event is unavailable.');
        }

        $existingSeat = $pdo->prepare(
            'SELECT id,party_size,status
             FROM calendar_event_registrations
             WHERE event_id=:event_id
               AND email=:email
             LIMIT 1
             FOR UPDATE'
        );
        $existingSeat->execute([
            'event_id' => (int)$event['id'],
            'email' => $email,
        ]);
        $existingSeatRecord = $existingSeat->fetch();
        $usedStatement = $pdo->prepare(
            'SELECT COALESCE(SUM(party_size),0)
             FROM calendar_event_registrations
             WHERE event_id=:event_id
               AND status IN ("registered","confirmed","attended")
               AND id<>:existing_id'
        );
        $usedStatement->execute([
            'event_id' => (int)$event['id'],
            'existing_id' => (int)($existingSeatRecord['id'] ?? 0),
        ]);
        $usedSeats = (int)$usedStatement->fetchColumn();
        $lockedCapacity = (int)($lockedEvent['capacity'] ?? 0);

        if ($lockedCapacity > 0 && ($usedSeats + $partySize) > $lockedCapacity) {
            if (!empty($lockedEvent['waitlist_enabled'])) {
                $registrationStatus = 'waitlist';
            } else {
                throw new RuntimeException('The event no longer has enough space for this party.');
            }
        }

        $contactId = events_find_or_create_contact([
            'display_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
        ]);
        $existing = $pdo->prepare(
            'SELECT id,status,confirmation_token
             FROM calendar_event_registrations
             WHERE event_id=:event_id
               AND email=:email
             LIMIT 1
             FOR UPDATE'
        );
        $existing->execute([
            'event_id' => (int)$event['id'],
            'email' => $email,
        ]);
        $record = $existing->fetch();
        $token = $record
            ? (string)$record['confirmation_token']
            : bin2hex(random_bytes(32));

        if ($record) {
            $registrationId = (int)$record['id'];
            $pdo->prepare(
                'UPDATE calendar_event_registrations
                 SET crm_contact_id=:crm_contact_id,
                     user_id=:user_id,
                     display_name=:display_name,
                     phone=:phone,
                     company=:company,
                     party_size=:party_size,
                     status=:status,
                     notes=:notes,
                     reminder_opt_in=:reminder_opt_in,
                     registered_at=UTC_TIMESTAMP(),
                     cancelled_at=NULL,
                     checked_in_at=NULL
                 WHERE id=:registration_id'
            )->execute([
                'crm_contact_id' => $contactId,
                'user_id' => $portalUser ? (int)$portalUser['id'] : null,
                'display_name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'company' => $company !== '' ? $company : null,
                'party_size' => $partySize,
                'status' => $registrationStatus,
                'notes' => $notes !== '' ? $notes : null,
                'reminder_opt_in' => $reminderOptIn ? 1 : 0,
                'registration_id' => $registrationId,
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO calendar_event_registrations
                    (event_id,crm_contact_id,user_id,display_name,email,phone,
                     company,party_size,status,source,notes,confirmation_token,
                     reminder_opt_in)
                 VALUES
                    (:event_id,:crm_contact_id,:user_id,:display_name,:email,
                     :phone,:company,:party_size,:status,"public_event",:notes,
                     :confirmation_token,:reminder_opt_in)'
            )->execute([
                'event_id' => (int)$event['id'],
                'crm_contact_id' => $contactId,
                'user_id' => $portalUser ? (int)$portalUser['id'] : null,
                'display_name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'company' => $company !== '' ? $company : null,
                'party_size' => $partySize,
                'status' => $registrationStatus,
                'notes' => $notes !== '' ? $notes : null,
                'confirmation_token' => $token,
                'reminder_opt_in' => $reminderOptIn ? 1 : 0,
            ]);
            $registrationId = (int)$pdo->lastInsertId();
        }

        $pdo->prepare(
            'INSERT INTO crm_activities
                (contact_id,admin_user_id,activity_type,subject,body)
             VALUES
                (:contact_id,NULL,"meeting",:subject,:body)'
        )->execute([
            'contact_id' => $contactId,
            'subject' => 'Event registration: ' . (string)$event['title'],
            'body' => implode("\n", array_filter([
                'Status: ' . ($registrationStatus === 'waitlist' ? 'Waitlist' : 'Registered'),
                'Party size: ' . $partySize,
                $company !== '' ? 'Company: ' . $company : '',
                $phone !== '' ? 'Phone: ' . $phone : '',
                $notes !== '' ? 'Notes: ' . $notes : '',
            ])),
        ]);

        if ($reminderOptIn) {
            events_schedule_reminder($event, $registrationId);
        } else {
            $pdo->prepare(
                'UPDATE calendar_event_reminders
                 SET status="cancelled"
                 WHERE registration_id=:registration_id
                   AND status IN ("pending","ready")'
            )->execute(['registration_id' => $registrationId]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    require_once __DIR__ . '/notifications.php';
    notification_create_for_role(
        'admin',
        'contact',
        $registrationStatus === 'waitlist'
            ? 'New event waitlist registration'
            : 'New event registration',
        $name . ' registered for ' . (string)$event['title'] . '.',
        'portal/admin.php?view=events&edit=' . (int)$event['id'],
        'event_registration',
        $registrationId,
        'normal'
    );

    try {
        visitor_intelligence_attach_contact(
            $contactId,
            'event_registration_submit',
            [
                'event_label' => (string)$event['title'],
                'page_path' => 'event.php?slug=' . (string)$event['slug'],
                'metadata' => [
                    'event_id' => (int)$event['id'],
                    'event_slug' => (string)$event['slug'],
                    'registration_id' => $registrationId,
                    'registration_status' => $registrationStatus,
                    'party_size' => $partySize,
                ],
            ]
        );
    } catch (Throwable $exception) {
        error_log(
            'North Mountain Media event attribution failed: '
            . $exception->getMessage()
        );
    }

    return [
        'registration_id' => $registrationId,
        'contact_id' => $contactId,
        'status' => $registrationStatus,
        'token' => $token,
        'message' => $registrationStatus === 'waitlist'
            ? 'You joined the waitlist.'
            : 'Your registration is saved.',
        'confirmation_url' => app_url(
            'event-registration.php?token=' . rawurlencode($token)
        ),
    ];
}

function events_registration_by_token(string $token): ?array
{
    if (!events_schema_available()) {
        return null;
    }

    $token = strtolower(trim($token));

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT registration.*,event.title,event.slug,event.start_at,event.end_at,
                event.timezone,event.all_day,event.format_type,event.location_name,
                event.city,event.region,event.virtual_url,event.status AS event_status
         FROM calendar_event_registrations registration
         JOIN calendar_events event ON event.id=registration.event_id
         WHERE registration.confirmation_token=:confirmation_token
         LIMIT 1'
    );
    $statement->execute(['confirmation_token' => $token]);
    $registration = $statement->fetch();

    if (!$registration) {
        return null;
    }

    $registration['date_label'] = events_format_date($registration);
    $registration['location_label'] = events_location_label($registration);

    return $registration;
}

function events_cancel_registration(string $token): ?array
{
    $registration = events_registration_by_token($token);

    if (!$registration) {
        return null;
    }

    if (!in_array(
        (string)$registration['status'],
        ['cancelled', 'attended', 'no_show'],
        true
    )) {
        db()->prepare(
            'UPDATE calendar_event_registrations
             SET status="cancelled",
                 cancelled_at=UTC_TIMESTAMP()
             WHERE id=:registration_id'
        )->execute([
            'registration_id' => (int)$registration['id'],
        ]);
        db()->prepare(
            'UPDATE calendar_event_reminders
             SET status="cancelled"
             WHERE registration_id=:registration_id
               AND status IN ("pending","ready")'
        )->execute([
            'registration_id' => (int)$registration['id'],
        ]);

        if ((int)($registration['crm_contact_id'] ?? 0) > 0) {
            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,"status_change",:subject,:body)'
            )->execute([
                'contact_id' => (int)$registration['crm_contact_id'],
                'subject' => 'Event registration cancelled',
                'body' => (string)$registration['title'],
            ]);
        }
    }

    return events_registration_by_token($token);
}

function events_absolute_url(string $path): string
{
    $base = trim((string)(nmm_config('app')['base_url'] ?? ''));

    if ($base !== '') {
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    $scheme = (
        !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off'
    ) ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = rtrim(dirname($script), '/.');

    return $scheme
        . '://'
        . $host
        . ($directory !== '' ? $directory : '')
        . '/'
        . ltrim($path, '/');
}

function events_ics_escape(string $value): string
{
    return str_replace(
        ["\\", ";", ",", "\r\n", "\r", "\n"],
        ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"],
        $value
    );
}

function events_ics_datetime(?string $utc, bool $allDay = false): string
{
    if ($utc === null || trim($utc) === '') {
        return '';
    }

    $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

    return $allDay
        ? $date->format('Ymd')
        : $date->format('Ymd\THis\Z');
}

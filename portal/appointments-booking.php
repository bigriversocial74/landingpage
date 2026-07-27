<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

require_once __DIR__ . '/events-calendar.php';

function booking_schema_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    if (!function_exists('db') || !defined('NMM_BOOTSTRAPPED')) {
        return false;
    }

    try {
        $statement = db()->query(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name IN (
                    "booking_types",
                    "booking_availability_rules",
                    "booking_blackouts",
                    "booking_day_locks",
                    "appointments",
                    "appointment_reminders"
               )'
        );
        $available = (int)$statement->fetchColumn() === 6;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function booking_settings(): array
{
    $timezone = trim((string)setting(
        'bookings_default_timezone',
        'America/Phoenix'
    ));

    try {
        new DateTimeZone($timezone);
    } catch (Throwable) {
        $timezone = 'America/Phoenix';
    }

    return [
        'enabled' => setting('bookings_enabled', '0') === '1',
        'title' => trim((string)setting(
            'bookings_title',
            'Book a Meeting'
        )),
        'intro' => trim((string)setting(
            'bookings_intro',
            'Choose an available time to talk about your project.'
        )),
        'description' => trim((string)setting(
            'bookings_description',
            'Schedule a consultation, project review, product demonstration, or support session with North Mountain Media.'
        )),
        'default_timezone' => $timezone,
        'default_location' => trim((string)setting(
            'bookings_default_location',
            'Phoenix, Arizona'
        )),
        'reminder_hours' => max(
            1,
            min(720, (int)setting('bookings_reminder_hours', '24'))
        ),
        'public_window_days' => max(
            7,
            min(365, (int)setting('bookings_public_window_days', '60'))
        ),
        'sidebar_label' => trim((string)setting(
            'bookings_sidebar_label',
            'Bookings'
        )) ?: 'Bookings',
        'calendar_conflicts' => setting(
            'bookings_calendar_conflicts',
            '1'
        ) !== '0',
    ];
}

function booking_save_setting(string $key, ?string $value): void
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

function booking_timezones(): array
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

function booking_valid_timezone(string $timezone): string
{
    $timezone = trim($timezone);

    try {
        new DateTimeZone($timezone);
        return $timezone;
    } catch (Throwable) {
        return booking_settings()['default_timezone'];
    }
}

function booking_weekdays(): array
{
    return [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];
}

function booking_statuses(): array
{
    return [
        'requested' => 'Requested',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
    ];
}

function booking_location_modes(): array
{
    return [
        'phone' => 'Phone',
        'video' => 'Video',
        'in_person' => 'In person',
        'client_choice' => 'Client choice',
    ];
}

function booking_confirmation_modes(): array
{
    return [
        'request' => 'Request approval',
        'automatic' => 'Confirm automatically',
    ];
}

function booking_primary_owner_id(): int
{
    try {
        $profile = function_exists('primary_admin_profile')
            ? primary_admin_profile()
            : null;

        if ($profile && (int)($profile['id'] ?? 0) > 0) {
            return (int)$profile['id'];
        }

        return (int)(
            db()->query(
                'SELECT id
                 FROM users
                 WHERE role="admin" AND status="active"
                 ORDER BY id ASC
                 LIMIT 1'
            )->fetchColumn() ?: 0
        );
    } catch (Throwable) {
        return 0;
    }
}

function booking_type_owner_id(array $type): int
{
    $owner = (int)($type['owner_user_id'] ?? 0);
    return $owner > 0 ? $owner : booking_primary_owner_id();
}

function booking_types(bool $activeOnly = false): array
{
    if (!booking_schema_available()) {
        return [];
    }

    $sql = 'SELECT type.*,owner.display_name AS owner_name
            FROM booking_types type
            LEFT JOIN users owner ON owner.id=type.owner_user_id';

    if ($activeOnly) {
        $sql .= ' WHERE type.status="active"';
    }

    $sql .= ' ORDER BY type.sort_order,type.name,type.id';

    try {
        return db()->query($sql)->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function booking_type_by_id(int $typeId): ?array
{
    if ($typeId <= 0 || !booking_schema_available()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT type.*,owner.display_name AS owner_name
         FROM booking_types type
         LEFT JOIN users owner ON owner.id=type.owner_user_id
         WHERE type.id=:type_id
         LIMIT 1'
    );
    $statement->execute(['type_id' => $typeId]);
    return $statement->fetch() ?: null;
}

function booking_type_by_slug(string $slug, bool $activeOnly = true): ?array
{
    $slug = trim($slug);

    if ($slug === '' || !booking_schema_available()) {
        return null;
    }

    $sql = 'SELECT type.*,owner.display_name AS owner_name
            FROM booking_types type
            LEFT JOIN users owner ON owner.id=type.owner_user_id
            WHERE type.slug=:slug';

    if ($activeOnly) {
        $sql .= ' AND type.status="active"';
    }

    $sql .= ' LIMIT 1';

    $statement = db()->prepare($sql);
    $statement->execute(['slug' => $slug]);
    return $statement->fetch() ?: null;
}

function booking_unique_slug(string $value, int $excludeId = 0): string
{
    $base = slugify($value);
    $candidate = $base;
    $suffix = 2;

    while (true) {
        $statement = db()->prepare(
            'SELECT id
             FROM booking_types
             WHERE slug=:slug AND id<>:exclude_id
             LIMIT 1'
        );
        $statement->execute([
            'slug' => $candidate,
            'exclude_id' => $excludeId,
        ]);

        if (!$statement->fetchColumn()) {
            return $candidate;
        }

        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
}

function booking_rules(
    ?int $typeId = null,
    ?int $dayOfWeek = null,
    bool $activeOnly = false
): array {
    if (!booking_schema_available()) {
        return [];
    }

    $conditions = [];
    $parameters = [];

    if ($typeId !== null && $typeId > 0) {
        $conditions[] = '(rule.booking_type_id IS NULL OR rule.booking_type_id=:type_id)';
        $parameters['type_id'] = $typeId;
    }

    if ($dayOfWeek !== null) {
        $conditions[] = 'rule.day_of_week=:day_of_week';
        $parameters['day_of_week'] = $dayOfWeek;
    }

    if ($activeOnly) {
        $conditions[] = 'rule.active=1';
    }

    $sql = 'SELECT rule.*,type.name AS booking_type_name,
                   owner.display_name AS owner_name
            FROM booking_availability_rules rule
            LEFT JOIN booking_types type ON type.id=rule.booking_type_id
            LEFT JOIN users owner ON owner.id=rule.owner_user_id';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY rule.day_of_week,rule.sort_order,rule.start_time,rule.id';

    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function booking_blackouts(
    ?string $fromUtc = null,
    ?string $toUtc = null
): array {
    if (!booking_schema_available()) {
        return [];
    }

    $conditions = [];
    $parameters = [];

    if ($fromUtc !== null && $toUtc !== null) {
        $conditions[] = 'blackout.start_at<:to_utc';
        $conditions[] = 'blackout.end_at>:from_utc';
        $parameters['from_utc'] = $fromUtc;
        $parameters['to_utc'] = $toUtc;
    }

    $sql = 'SELECT blackout.*,owner.display_name AS owner_name
            FROM booking_blackouts blackout
            LEFT JOIN users owner ON owner.id=blackout.owner_user_id';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY blackout.start_at,blackout.id';

    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function booking_local_to_utc(
    string $value,
    string $timezone
): ?string {
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $timezone = booking_valid_timezone($timezone);
    $local = DateTimeImmutable::createFromFormat(
        'Y-m-d\TH:i',
        $value,
        new DateTimeZone($timezone)
    );

    if (!$local) {
        try {
            $local = new DateTimeImmutable(
                $value,
                new DateTimeZone($timezone)
            );
        } catch (Throwable) {
            return null;
        }
    }

    return $local
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s');
}

function booking_utc_to_local_input(
    ?string $utc,
    string $timezone
): string {
    if ($utc === null || trim($utc) === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable(
            $utc,
            new DateTimeZone('UTC')
        ))->setTimezone(
            new DateTimeZone(booking_valid_timezone($timezone))
        )->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function booking_utc_datetime(
    ?string $utc,
    string $timezone
): ?DateTimeImmutable {
    if ($utc === null || trim($utc) === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable(
            $utc,
            new DateTimeZone('UTC')
        ))->setTimezone(
            new DateTimeZone(booking_valid_timezone($timezone))
        );
    } catch (Throwable) {
        return null;
    }
}

function booking_rule_applies(array $rule, string $localDate): bool
{
    if (empty($rule['active'])) {
        return false;
    }

    if (
        !empty($rule['valid_from'])
        && $localDate < (string)$rule['valid_from']
    ) {
        return false;
    }

    if (
        !empty($rule['valid_until'])
        && $localDate > (string)$rule['valid_until']
    ) {
        return false;
    }

    return true;
}

function booking_busy_intervals(
    int $ownerUserId,
    string $fromUtc,
    string $toUtc,
    int $excludeAppointmentId = 0
): array {
    $intervals = [];

    $appointmentStatement = db()->prepare(
        'SELECT id,start_at,end_at,"appointment" AS interval_type
         FROM appointments
         WHERE owner_user_id=:owner_user_id
           AND status IN ("requested","confirmed")
           AND start_at<:to_utc
           AND end_at>:from_utc
           AND id<>:exclude_id'
    );
    $appointmentStatement->execute([
        'owner_user_id' => $ownerUserId,
        'from_utc' => $fromUtc,
        'to_utc' => $toUtc,
        'exclude_id' => $excludeAppointmentId,
    ]);

    foreach ($appointmentStatement->fetchAll() as $row) {
        $intervals[] = [
            'start_at' => (string)$row['start_at'],
            'end_at' => (string)$row['end_at'],
            'type' => 'appointment',
            'id' => (int)$row['id'],
        ];
    }

    $blackoutStatement = db()->prepare(
        'SELECT id,start_at,end_at
         FROM booking_blackouts
         WHERE (owner_user_id IS NULL OR owner_user_id=:owner_user_id)
           AND start_at<:to_utc
           AND end_at>:from_utc'
    );
    $blackoutStatement->execute([
        'owner_user_id' => $ownerUserId,
        'from_utc' => $fromUtc,
        'to_utc' => $toUtc,
    ]);

    foreach ($blackoutStatement->fetchAll() as $row) {
        $intervals[] = [
            'start_at' => (string)$row['start_at'],
            'end_at' => (string)$row['end_at'],
            'type' => 'blackout',
            'id' => (int)$row['id'],
        ];
    }

    $settings = booking_settings();

    if (
        $settings['calendar_conflicts']
        && function_exists('events_schema_available')
        && events_schema_available()
    ) {
        $eventStatement = db()->prepare(
            'SELECT id,start_at,
                    COALESCE(end_at,DATE_ADD(start_at,INTERVAL 1 HOUR)) AS end_at
             FROM calendar_events
             WHERE status NOT IN ("cancelled","archived")
               AND start_at<:to_utc
               AND COALESCE(end_at,DATE_ADD(start_at,INTERVAL 1 HOUR))>:from_utc'
        );
        $eventStatement->execute([
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
        ]);

        foreach ($eventStatement->fetchAll() as $row) {
            $intervals[] = [
                'start_at' => (string)$row['start_at'],
                'end_at' => (string)$row['end_at'],
                'type' => 'event',
                'id' => (int)$row['id'],
            ];
        }
    }

    return $intervals;
}

function booking_intervals_overlap(
    DateTimeImmutable $start,
    DateTimeImmutable $end,
    array $interval
): bool {
    try {
        $busyStart = new DateTimeImmutable(
            (string)$interval['start_at'],
            new DateTimeZone('UTC')
        );
        $busyEnd = new DateTimeImmutable(
            (string)$interval['end_at'],
            new DateTimeZone('UTC')
        );
    } catch (Throwable) {
        return false;
    }

    return $start < $busyEnd && $end > $busyStart;
}

function booking_slot_secret(): string
{
    $security = function_exists('nmm_config')
        ? nmm_config('security')
        : [];
    $secret = trim((string)($security['booking_slot_secret'] ?? ''));

    if ($secret !== '') {
        return $secret;
    }

    $app = function_exists('nmm_config')
        ? nmm_config('app')
        : [];

    return hash(
        'sha256',
        (string)($app['setup_token'] ?? 'north-mountain-media')
        . '|booking-slot|'
        . (string)($app['session_name'] ?? 'nmm_portal')
    );
}

function booking_base64url_encode(string $value): string
{
    return rtrim(
        strtr(base64_encode($value), '+/', '-_'),
        '='
    );
}

function booking_base64url_decode(string $value): string
{
    $padding = strlen($value) % 4;

    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode(
        strtr($value, '-_', '+/'),
        true
    );

    return $decoded === false ? '' : $decoded;
}

function booking_slot_token(
    int $typeId,
    string $startUtc,
    string $displayTimezone
): string {
    $payload = booking_base64url_encode(json_encode([
        'type_id' => $typeId,
        'start_utc' => $startUtc,
        'timezone' => booking_valid_timezone($displayTimezone),
    ], JSON_UNESCAPED_SLASHES) ?: '');

    $signature = hash_hmac(
        'sha256',
        $payload,
        booking_slot_secret()
    );

    return $payload . '.' . $signature;
}

function booking_parse_slot_token(string $token): ?array
{
    $parts = explode('.', trim($token), 2);

    if (count($parts) !== 2) {
        return null;
    }

    [$payload, $signature] = $parts;
    $expected = hash_hmac(
        'sha256',
        $payload,
        booking_slot_secret()
    );

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $data = json_decode(
        booking_base64url_decode($payload),
        true
    );

    if (
        !is_array($data)
        || (int)($data['type_id'] ?? 0) <= 0
        || trim((string)($data['start_utc'] ?? '')) === ''
    ) {
        return null;
    }

    return [
        'type_id' => (int)$data['type_id'],
        'start_utc' => (string)$data['start_utc'],
        'timezone' => booking_valid_timezone(
            (string)($data['timezone'] ?? '')
        ),
    ];
}

function booking_slots_for_date(
    array $type,
    string $displayDate,
    string $displayTimezone,
    int $excludeAppointmentId = 0,
    int $limit = 60
): array {
    $displayTimezone = booking_valid_timezone($displayTimezone);
    $displayDate = trim($displayDate);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $displayDate)) {
        return [];
    }

    $ownerUserId = booking_type_owner_id($type);

    if ($ownerUserId <= 0) {
        return [];
    }

    $duration = max(10, min(480, (int)$type['duration_minutes']));
    $bufferBefore = max(0, min(240, (int)$type['buffer_before_minutes']));
    $bufferAfter = max(0, min(240, (int)$type['buffer_after_minutes']));
    $interval = max(5, min(240, (int)$type['slot_interval_minutes']));
    $minimumNotice = max(0, (int)$type['minimum_notice_hours']);
    $maximumDays = max(1, (int)$type['maximum_days_ahead']);
    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $earliestUtc = $nowUtc->modify('+' . $minimumNotice . ' hours');
    $latestUtc = $nowUtc->modify('+' . $maximumDays . ' days');

    try {
        $viewerDate = new DateTimeImmutable(
            $displayDate . ' 12:00:00',
            new DateTimeZone($displayTimezone)
        );
    } catch (Throwable) {
        return [];
    }

    $candidateOwnerDates = [];
    $settingsTimezone = booking_settings()['default_timezone'];

    foreach ([-1, 0, 1] as $offset) {
        $candidateOwnerDates[] = $viewerDate
            ->modify(($offset >= 0 ? '+' : '') . $offset . ' days')
            ->setTimezone(new DateTimeZone($settingsTimezone))
            ->format('Y-m-d');
    }

    $candidateOwnerDates = array_values(array_unique($candidateOwnerDates));
    $slots = [];

    foreach ($candidateOwnerDates as $ownerDate) {
        $weekday = (int)(new DateTimeImmutable(
            $ownerDate . ' 12:00:00',
            new DateTimeZone($settingsTimezone)
        ))->format('w');
        $rules = booking_rules((int)$type['id'], $weekday, true);

        foreach ($rules as $rule) {
            $ruleTimezone = booking_valid_timezone(
                (string)$rule['timezone']
            );
            $ruleDate = (new DateTimeImmutable(
                $ownerDate . ' 12:00:00',
                new DateTimeZone($settingsTimezone)
            ))->setTimezone(
                new DateTimeZone($ruleTimezone)
            )->format('Y-m-d');

            if (!booking_rule_applies($rule, $ruleDate)) {
                continue;
            }

            try {
                $windowStartLocal = new DateTimeImmutable(
                    $ruleDate . ' ' . (string)$rule['start_time'],
                    new DateTimeZone($ruleTimezone)
                );
                $windowEndLocal = new DateTimeImmutable(
                    $ruleDate . ' ' . (string)$rule['end_time'],
                    new DateTimeZone($ruleTimezone)
                );
            } catch (Throwable) {
                continue;
            }

            if ($windowEndLocal <= $windowStartLocal) {
                continue;
            }

            $queryStartUtc = $windowStartLocal
                ->modify('-' . $bufferBefore . ' minutes')
                ->setTimezone(new DateTimeZone('UTC'));
            $queryEndUtc = $windowEndLocal
                ->modify('+' . $bufferAfter . ' minutes')
                ->setTimezone(new DateTimeZone('UTC'));
            $busy = booking_busy_intervals(
                $ownerUserId,
                $queryStartUtc->format('Y-m-d H:i:s'),
                $queryEndUtc->format('Y-m-d H:i:s'),
                $excludeAppointmentId
            );

            for (
                $slotLocal = $windowStartLocal;
                $slotLocal < $windowEndLocal;
                $slotLocal = $slotLocal->modify('+' . $interval . ' minutes')
            ) {
                $slotEndLocal = $slotLocal->modify(
                    '+' . $duration . ' minutes'
                );
                $bufferEndLocal = $slotEndLocal->modify(
                    '+' . $bufferAfter . ' minutes'
                );

                if ($bufferEndLocal > $windowEndLocal) {
                    continue;
                }

                $slotStartUtc = $slotLocal
                    ->setTimezone(new DateTimeZone('UTC'));
                $slotEndUtc = $slotEndLocal
                    ->setTimezone(new DateTimeZone('UTC'));
                $bufferStartUtc = $slotStartUtc->modify(
                    '-' . $bufferBefore . ' minutes'
                );
                $bufferEndUtc = $slotEndUtc->modify(
                    '+' . $bufferAfter . ' minutes'
                );

                if (
                    $slotStartUtc < $earliestUtc
                    || $slotStartUtc > $latestUtc
                ) {
                    continue;
                }

                $displayStart = $slotStartUtc->setTimezone(
                    new DateTimeZone($displayTimezone)
                );

                if ($displayStart->format('Y-m-d') !== $displayDate) {
                    continue;
                }

                $conflict = false;

                foreach ($busy as $busyInterval) {
                    if (
                        booking_intervals_overlap(
                            $bufferStartUtc,
                            $bufferEndUtc,
                            $busyInterval
                        )
                    ) {
                        $conflict = true;
                        break;
                    }
                }

                if ($conflict) {
                    continue;
                }

                $displayEnd = $slotEndUtc->setTimezone(
                    new DateTimeZone($displayTimezone)
                );
                $startUtcValue = $slotStartUtc->format('Y-m-d H:i:s');

                $slots[$startUtcValue] = [
                    'type_id' => (int)$type['id'],
                    'start_utc' => $startUtcValue,
                    'end_utc' => $slotEndUtc->format('Y-m-d H:i:s'),
                    'display_timezone' => $displayTimezone,
                    'date_label' => $displayStart->format('l, F j, Y'),
                    'time_label' => $displayStart->format('g:i A')
                        . '–'
                        . $displayEnd->format('g:i A'),
                    'short_time_label' => $displayStart->format('g:i A'),
                    'token' => booking_slot_token(
                        (int)$type['id'],
                        $startUtcValue,
                        $displayTimezone
                    ),
                ];

                if (count($slots) >= max(1, $limit)) {
                    break 3;
                }
            }
        }
    }

    ksort($slots);
    return array_values($slots);
}

function booking_next_available_dates(
    array $type,
    string $displayTimezone,
    int $days = 14,
    int $dateLimit = 10,
    int $excludeAppointmentId = 0
): array {
    $displayTimezone = booking_valid_timezone($displayTimezone);
    $days = max(1, min(365, $days));
    $dateLimit = max(1, min(60, $dateLimit));
    $today = new DateTimeImmutable(
        'today',
        new DateTimeZone($displayTimezone)
    );
    $dates = [];

    for ($offset = 0; $offset <= $days; $offset++) {
        $date = $today->modify('+' . $offset . ' days');
        $dateValue = $date->format('Y-m-d');
        $slots = booking_slots_for_date(
            $type,
            $dateValue,
            $displayTimezone,
            $excludeAppointmentId,
            1
        );

        if ($slots) {
            $dates[] = [
                'value' => $dateValue,
                'label' => $date->format('D, M j'),
                'long_label' => $date->format('l, F j, Y'),
            ];
        }

        if (count($dates) >= $dateLimit) {
            break;
        }
    }

    return $dates;
}

function booking_slot_is_available(
    array $type,
    string $startUtc,
    string $displayTimezone,
    int $excludeAppointmentId = 0
): ?array {
    try {
        $displayDate = (new DateTimeImmutable(
            $startUtc,
            new DateTimeZone('UTC')
        ))->setTimezone(
            new DateTimeZone(booking_valid_timezone($displayTimezone))
        )->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }

    foreach (
        booking_slots_for_date(
            $type,
            $displayDate,
            $displayTimezone,
            $excludeAppointmentId,
            120
        ) as $slot
    ) {
        if ($slot['start_utc'] === $startUtc) {
            return $slot;
        }
    }

    return null;
}

function booking_has_public_availability(bool $refresh = false): bool
{
    static $available = null;

    if ($available !== null && !$refresh) {
        return $available;
    }

    if (
        !booking_schema_available()
        || !booking_settings()['enabled']
    ) {
        return $available = false;
    }

    if (
        !$refresh
        && isset($_SESSION['nmm_booking_availability_cache'])
        && is_array($_SESSION['nmm_booking_availability_cache'])
        && (int)($_SESSION['nmm_booking_availability_cache']['expires'] ?? 0) > time()
    ) {
        return $available = !empty(
            $_SESSION['nmm_booking_availability_cache']['available']
        );
    }

    $settings = booking_settings();
    $types = booking_types(true);
    $available = false;

    foreach ($types as $type) {
        $window = min(
            $settings['public_window_days'],
            max(1, (int)$type['maximum_days_ahead'])
        );
        $dates = booking_next_available_dates(
            $type,
            $settings['default_timezone'],
            $window,
            1
        );

        if ($dates) {
            $available = true;
            break;
        }
    }

    $_SESSION['nmm_booking_availability_cache'] = [
        'available' => $available,
        'expires' => time() + 120,
    ];

    return $available;
}

function booking_public_link_available(): bool
{
    try {
        return booking_has_public_availability();
    } catch (Throwable) {
        return false;
    }
}

function booking_format_appointment(
    array $appointment,
    ?string $timezone = null
): array {
    $timezone = booking_valid_timezone(
        $timezone ?: (string)($appointment['timezone'] ?? '')
    );
    $start = booking_utc_datetime(
        (string)($appointment['start_at'] ?? ''),
        $timezone
    );
    $end = booking_utc_datetime(
        (string)($appointment['end_at'] ?? ''),
        $timezone
    );

    $appointment['display_timezone'] = $timezone;
    $appointment['date_label'] = $start
        ? $start->format('l, F j, Y')
        : 'Date unavailable';
    $appointment['time_label'] = $start
        ? $start->format('g:i A')
            . ($end ? '–' . $end->format('g:i A') : '')
        : 'Time unavailable';
    $appointment['short_date_label'] = $start
        ? $start->format('M j · g:i A')
        : 'TBA';

    return $appointment;
}

function booking_appointment_by_id(int $appointmentId): ?array
{
    if ($appointmentId <= 0 || !booking_schema_available()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT appointment.*,type.name AS booking_type_name,
                type.slug AS booking_type_slug,
                type.duration_minutes,type.buffer_before_minutes,
                type.buffer_after_minutes,type.minimum_notice_hours,
                type.maximum_days_ahead,type.slot_interval_minutes,
                type.confirmation_mode,type.location_mode AS type_location_mode,
                type.color_hex,type.description AS type_description,
                contact.display_name AS crm_contact_name,
                opportunity.title AS opportunity_title,
                owner.display_name AS owner_name
         FROM appointments appointment
         JOIN booking_types type ON type.id=appointment.booking_type_id
         JOIN users owner ON owner.id=appointment.owner_user_id
         LEFT JOIN crm_contacts contact ON contact.id=appointment.crm_contact_id
         LEFT JOIN crm_opportunities opportunity ON opportunity.id=appointment.crm_opportunity_id
         WHERE appointment.id=:appointment_id
         LIMIT 1'
    );
    $statement->execute(['appointment_id' => $appointmentId]);
    $appointment = $statement->fetch();

    return $appointment
        ? booking_format_appointment($appointment)
        : null;
}

function booking_appointment_by_token(string $token): ?array
{
    $token = trim($token);

    if (
        !preg_match('/^[a-f0-9]{64}$/', $token)
        || !booking_schema_available()
    ) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT appointment.*,type.name AS booking_type_name,
                type.slug AS booking_type_slug,
                type.duration_minutes,type.buffer_before_minutes,
                type.buffer_after_minutes,type.minimum_notice_hours,
                type.maximum_days_ahead,type.slot_interval_minutes,
                type.confirmation_mode,type.location_mode AS type_location_mode,
                type.color_hex,type.description AS type_description,
                owner.display_name AS owner_name
         FROM appointments appointment
         JOIN booking_types type ON type.id=appointment.booking_type_id
         JOIN users owner ON owner.id=appointment.owner_user_id
         WHERE appointment.confirmation_token=:token
         LIMIT 1'
    );
    $statement->execute(['token' => $token]);
    $appointment = $statement->fetch();

    return $appointment
        ? booking_format_appointment($appointment)
        : null;
}

function booking_appointments(array $filters = []): array
{
    if (!booking_schema_available()) {
        return [];
    }

    $conditions = [];
    $parameters = [];

    if (!empty($filters['from'])) {
        $conditions[] = 'appointment.start_at>=:from_utc';
        $parameters['from_utc'] = $filters['from'];
    }

    if (!empty($filters['to'])) {
        $conditions[] = 'appointment.start_at<:to_utc';
        $parameters['to_utc'] = $filters['to'];
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'appointment.status=:status';
        $parameters['status'] = $filters['status'];
    }

    if (!empty($filters['type_id'])) {
        $conditions[] = 'appointment.booking_type_id=:type_id';
        $parameters['type_id'] = (int)$filters['type_id'];
    }

    $limit = max(1, min(1000, (int)($filters['limit'] ?? 250)));
    $sql = 'SELECT appointment.*,type.name AS booking_type_name,
                   type.slug AS booking_type_slug,type.color_hex,
                   owner.display_name AS owner_name
            FROM appointments appointment
            JOIN booking_types type ON type.id=appointment.booking_type_id
            JOIN users owner ON owner.id=appointment.owner_user_id';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY appointment.start_at,appointment.id LIMIT ' . $limit;
    $statement = db()->prepare($sql);
    $statement->execute($parameters);

    return array_map(
        static fn(array $row): array =>
            booking_format_appointment($row),
        $statement->fetchAll()
    );
}

function booking_reminders(?int $appointmentId = null): array
{
    if (!booking_schema_available()) {
        return [];
    }

    $sql = 'SELECT reminder.*,appointment.display_name,
                   appointment.email,appointment.start_at,
                   appointment.timezone,appointment.status AS appointment_status,
                   type.name AS booking_type_name
            FROM appointment_reminders reminder
            JOIN appointments appointment ON appointment.id=reminder.appointment_id
            JOIN booking_types type ON type.id=appointment.booking_type_id';
    $parameters = [];

    if ($appointmentId !== null && $appointmentId > 0) {
        $sql .= ' WHERE reminder.appointment_id=:appointment_id';
        $parameters['appointment_id'] = $appointmentId;
    }

    $sql .= ' ORDER BY FIELD(reminder.status,"ready","pending","failed","sent","cancelled"),
                      reminder.scheduled_for,reminder.id';

    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function booking_find_or_create_contact(array $data): int
{
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $name = trim((string)($data['display_name'] ?? ''));
    $phone = trim((string)($data['phone'] ?? ''));
    $company = trim((string)($data['company'] ?? ''));

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
             SET display_name=:display_name,
                 phone=COALESCE(NULLIF(:phone,""),phone),
                 company=COALESCE(NULLIF(:company,""),company),
                 last_inquiry_at=UTC_TIMESTAMP()
             WHERE id=:contact_id'
        )->execute([
            'display_name' => $name,
            'phone' => $phone,
            'company' => $company,
            'contact_id' => $contactId,
        ]);

        return $contactId;
    }

    db()->prepare(
        'INSERT INTO crm_contacts
            (email,display_name,company,phone,lifecycle_stage,source,last_inquiry_at)
         VALUES
            (:email,:display_name,:company,:phone,"lead","appointment_booking",UTC_TIMESTAMP())'
    )->execute([
        'email' => $email,
        'display_name' => $name,
        'company' => $company !== '' ? $company : null,
        'phone' => $phone !== '' ? $phone : null,
    ]);

    return (int)db()->lastInsertId();
}

function booking_create_opportunity(
    int $contactId,
    array $type,
    string $subject,
    string $notes
): ?int {
    if (empty($type['create_opportunity'])) {
        return null;
    }

    $title = trim($subject);

    if ($title === '') {
        $title = (string)$type['name'] . ' appointment';
    }

    db()->prepare(
        'INSERT INTO crm_opportunities
            (contact_id,title,opportunity_type,stage,probability,
             next_action,next_action_at,source,message)
         VALUES
            (:contact_id,:title,:opportunity_type,"new",10,
             "Review appointment request",UTC_TIMESTAMP(),
             "appointment_booking",:message)'
    )->execute([
        'contact_id' => $contactId,
        'title' => substr($title, 0, 190),
        'opportunity_type' => substr(
            trim((string)($type['opportunity_type'] ?? 'Appointment')),
            0,
            120
        ),
        'message' => $notes !== '' ? $notes : null,
    ]);

    return (int)db()->lastInsertId();
}

function booking_schedule_reminder(
    array $appointment,
    ?int $hours = null
): void {
    $hours = $hours ?? booking_settings()['reminder_hours'];
    $hours = max(1, min(720, $hours));

    $start = new DateTimeImmutable(
        (string)$appointment['start_at'],
        new DateTimeZone('UTC')
    );
    $scheduled = $start->modify('-' . $hours . ' hours');
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $status = $scheduled <= $now ? 'ready' : 'pending';

    db()->prepare(
        'INSERT INTO appointment_reminders
            (appointment_id,reminder_type,scheduled_for,status)
         VALUES
            (:appointment_id,"email",:scheduled_for,:status)
         ON DUPLICATE KEY UPDATE
            status=VALUES(status),
            last_error=NULL,
            sent_at=NULL'
    )->execute([
        'appointment_id' => (int)$appointment['id'],
        'scheduled_for' => $scheduled->format('Y-m-d H:i:s'),
        'status' => $status,
    ]);
}

function booking_location_choice(
    array $type,
    string $requestedMode
): string {
    $typeMode = (string)$type['location_mode'];

    if ($typeMode !== 'client_choice') {
        return in_array(
            $typeMode,
            ['phone', 'video', 'in_person'],
            true
        ) ? $typeMode : 'video';
    }

    return in_array(
        $requestedMode,
        ['phone', 'video', 'in_person'],
        true
    ) ? $requestedMode : 'video';
}

function booking_create_public(
    array $type,
    array $slot,
    array $data,
    ?array $portalUser = null
): array {
    $name = substr(trim((string)($data['display_name'] ?? '')), 0, 160);
    $email = strtolower(substr(trim((string)($data['email'] ?? '')), 0, 190));
    $phone = substr(trim((string)($data['phone'] ?? '')), 0, 60);
    $company = substr(trim((string)($data['company'] ?? '')), 0, 190);
    $subject = substr(trim((string)($data['subject'] ?? '')), 0, 190);
    $notes = substr(trim((string)($data['notes'] ?? '')), 0, 5000);
    $reminderOptIn = !empty($data['reminder_opt_in']);
    $locationMode = booking_location_choice(
        $type,
        (string)($data['location_mode'] ?? '')
    );

    if ($name === '') {
        throw new RuntimeException('Enter your name.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    $ownerUserId = booking_type_owner_id($type);

    if ($ownerUserId <= 0) {
        throw new RuntimeException('No appointment owner is available.');
    }

    $startUtc = new DateTimeImmutable(
        (string)$slot['start_utc'],
        new DateTimeZone('UTC')
    );
    $endUtc = new DateTimeImmutable(
        (string)$slot['end_utc'],
        new DateTimeZone('UTC')
    );
    $ownerDate = $startUtc->setTimezone(
        new DateTimeZone(booking_settings()['default_timezone'])
    )->format('Y-m-d');
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'INSERT IGNORE INTO booking_day_locks
                (owner_user_id,local_date)
             VALUES (:owner_user_id,:local_date)'
        )->execute([
            'owner_user_id' => $ownerUserId,
            'local_date' => $ownerDate,
        ]);
        $lock = $pdo->prepare(
            'SELECT owner_user_id
             FROM booking_day_locks
             WHERE owner_user_id=:owner_user_id
               AND local_date=:local_date
             FOR UPDATE'
        );
        $lock->execute([
            'owner_user_id' => $ownerUserId,
            'local_date' => $ownerDate,
        ]);

        $available = booking_slot_is_available(
            $type,
            (string)$slot['start_utc'],
            (string)$slot['display_timezone']
        );

        if (!$available) {
            throw new RuntimeException(
                'That appointment time is no longer available.'
            );
        }

        $contactId = booking_find_or_create_contact([
            'display_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
        ]);
        $opportunityId = booking_create_opportunity(
            $contactId,
            $type,
            $subject,
            $notes
        );
        $status = $type['confirmation_mode'] === 'automatic'
            ? 'confirmed'
            : 'requested';
        $token = bin2hex(random_bytes(32));
        $locationDetails = trim((string)($type['default_location'] ?? ''));
        $meetingUrl = trim((string)($type['default_meeting_url'] ?? ''));

        $pdo->prepare(
            'INSERT INTO appointments
                (booking_type_id,owner_user_id,crm_contact_id,
                 crm_opportunity_id,user_id,status,start_at,end_at,timezone,
                 location_mode,location_details,meeting_url,display_name,
                 email,phone,company,subject,notes,confirmation_token,
                 reminder_opt_in,confirmed_at)
             VALUES
                (:booking_type_id,:owner_user_id,:crm_contact_id,
                 :crm_opportunity_id,:user_id,:status,:start_at,:end_at,
                 :timezone,:location_mode,:location_details,:meeting_url,
                 :display_name,:email,:phone,:company,:subject,:notes,
                 :confirmation_token,:reminder_opt_in,:confirmed_at)'
        )->execute([
            'booking_type_id' => (int)$type['id'],
            'owner_user_id' => $ownerUserId,
            'crm_contact_id' => $contactId,
            'crm_opportunity_id' => $opportunityId,
            'user_id' => $portalUser ? (int)$portalUser['id'] : null,
            'status' => $status,
            'start_at' => $startUtc->format('Y-m-d H:i:s'),
            'end_at' => $endUtc->format('Y-m-d H:i:s'),
            'timezone' => booking_valid_timezone(
                (string)$slot['display_timezone']
            ),
            'location_mode' => $locationMode,
            'location_details' => $locationDetails !== '' ? $locationDetails : null,
            'meeting_url' => $meetingUrl !== '' ? $meetingUrl : null,
            'display_name' => $name,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'company' => $company !== '' ? $company : null,
            'subject' => $subject !== '' ? $subject : null,
            'notes' => $notes !== '' ? $notes : null,
            'confirmation_token' => $token,
            'reminder_opt_in' => $reminderOptIn ? 1 : 0,
            'confirmed_at' => $status === 'confirmed'
                ? gmdate('Y-m-d H:i:s')
                : null,
        ]);
        $appointmentId = (int)$pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO crm_activities
                (contact_id,opportunity_id,activity_type,subject,body)
             VALUES
                (:contact_id,:opportunity_id,"meeting",:subject,:body)'
        )->execute([
            'contact_id' => $contactId,
            'opportunity_id' => $opportunityId,
            'subject' => 'Appointment booked: ' . (string)$type['name'],
            'body' => implode("\n", array_filter([
                'Status: ' . booking_statuses()[$status],
                'Start: ' . (string)$slot['date_label']
                    . ' · ' . (string)$slot['time_label'],
                'Timezone: ' . (string)$slot['display_timezone'],
                'Location: ' . booking_location_modes()[$locationMode],
                $subject !== '' ? 'Subject: ' . $subject : '',
                $notes !== '' ? 'Notes: ' . $notes : '',
            ])),
        ]);

        if ($reminderOptIn) {
            booking_schedule_reminder([
                'id' => $appointmentId,
                'start_at' => $startUtc->format('Y-m-d H:i:s'),
            ]);
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
        $status === 'confirmed'
            ? 'New confirmed appointment'
            : 'New appointment request',
        $name . ' booked ' . (string)$type['name'] . '.',
        'portal/admin.php?view=bookings&appointment=' . $appointmentId,
        'appointment',
        $appointmentId,
        'normal'
    );

    try {
        if (function_exists('visitor_intelligence_attach_contact')) {
            visitor_intelligence_attach_contact(
                $contactId,
                'appointment_booking_submit',
                [
                    'event_label' => (string)$type['name'],
                    'page_path' => 'booking.php',
                    'crm_opportunity_id' => $opportunityId,
                    'metadata' => [
                        'appointment_id' => $appointmentId,
                        'booking_type_id' => (int)$type['id'],
                        'booking_type_slug' => (string)$type['slug'],
                        'appointment_status' => $status,
                        'start_at' => (string)$slot['start_utc'],
                    ],
                ]
            );
        }
    } catch (Throwable $exception) {
        error_log(
            'North Mountain Media booking attribution failed: '
            . $exception->getMessage()
        );
    }

    unset($_SESSION['nmm_booking_availability_cache']);

    return [
        'appointment_id' => $appointmentId,
        'contact_id' => $contactId,
        'opportunity_id' => $opportunityId,
        'status' => $status,
        'token' => $token,
        'message' => $status === 'confirmed'
            ? 'Your appointment is confirmed.'
            : 'Your appointment request was received.',
        'confirmation_url' => 'appointment.php?token=' . rawurlencode($token),
    ];
}

function booking_cancel_appointment(string $token): ?array
{
    $appointment = booking_appointment_by_token($token);

    if (!$appointment) {
        return null;
    }

    if (!in_array(
        (string)$appointment['status'],
        ['cancelled', 'completed', 'no_show'],
        true
    )) {
        db()->prepare(
            'UPDATE appointments
             SET status="cancelled",
                 cancelled_at=UTC_TIMESTAMP()
             WHERE id=:appointment_id'
        )->execute([
            'appointment_id' => (int)$appointment['id'],
        ]);
        db()->prepare(
            'UPDATE appointment_reminders
             SET status="cancelled"
             WHERE appointment_id=:appointment_id
               AND status IN ("pending","ready")'
        )->execute([
            'appointment_id' => (int)$appointment['id'],
        ]);

        if ((int)($appointment['crm_contact_id'] ?? 0) > 0) {
            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,opportunity_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:opportunity_id,"status_change",
                     "Appointment cancelled",:body)'
            )->execute([
                'contact_id' => (int)$appointment['crm_contact_id'],
                'opportunity_id' => (int)($appointment['crm_opportunity_id'] ?? 0) ?: null,
                'body' => (string)$appointment['booking_type_name']
                    . ' · ' . (string)$appointment['date_label']
                    . ' · ' . (string)$appointment['time_label'],
            ]);
        }

        require_once __DIR__ . '/notifications.php';
        notification_create_for_role(
            'admin',
            'contact',
            'Appointment cancelled',
            (string)$appointment['display_name']
                . ' cancelled '
                . (string)$appointment['booking_type_name']
                . '.',
            'portal/admin.php?view=bookings&appointment='
                . (int)$appointment['id'],
            'appointment',
            (int)$appointment['id'],
            'normal'
        );
        unset($_SESSION['nmm_booking_availability_cache']);
    }

    return booking_appointment_by_token($token);
}

function booking_reschedule_appointment(
    string $token,
    array $slot
): ?array {
    $appointment = booking_appointment_by_token($token);

    if (!$appointment) {
        return null;
    }

    if (!in_array(
        (string)$appointment['status'],
        ['requested', 'confirmed'],
        true
    )) {
        throw new RuntimeException(
            'This appointment can no longer be rescheduled.'
        );
    }

    $type = booking_type_by_id((int)$appointment['booking_type_id']);

    if (!$type) {
        throw new RuntimeException('Appointment type unavailable.');
    }

    $ownerUserId = (int)$appointment['owner_user_id'];
    $startUtc = new DateTimeImmutable(
        (string)$slot['start_utc'],
        new DateTimeZone('UTC')
    );
    $endUtc = new DateTimeImmutable(
        (string)$slot['end_utc'],
        new DateTimeZone('UTC')
    );
    $ownerDate = $startUtc->setTimezone(
        new DateTimeZone(booking_settings()['default_timezone'])
    )->format('Y-m-d');
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'INSERT IGNORE INTO booking_day_locks
                (owner_user_id,local_date)
             VALUES (:owner_user_id,:local_date)'
        )->execute([
            'owner_user_id' => $ownerUserId,
            'local_date' => $ownerDate,
        ]);
        $lock = $pdo->prepare(
            'SELECT owner_user_id
             FROM booking_day_locks
             WHERE owner_user_id=:owner_user_id
               AND local_date=:local_date
             FOR UPDATE'
        );
        $lock->execute([
            'owner_user_id' => $ownerUserId,
            'local_date' => $ownerDate,
        ]);

        $available = booking_slot_is_available(
            $type,
            (string)$slot['start_utc'],
            (string)$slot['display_timezone'],
            (int)$appointment['id']
        );

        if (!$available) {
            throw new RuntimeException(
                'That replacement time is no longer available.'
            );
        }

        $pdo->prepare(
            'UPDATE appointments
             SET previous_start_at=start_at,
                 previous_end_at=end_at,
                 start_at=:start_at,
                 end_at=:end_at,
                 timezone=:timezone,
                 reschedule_count=reschedule_count+1,
                 rescheduled_at=UTC_TIMESTAMP(),
                 cancelled_at=NULL
             WHERE id=:appointment_id'
        )->execute([
            'start_at' => $startUtc->format('Y-m-d H:i:s'),
            'end_at' => $endUtc->format('Y-m-d H:i:s'),
            'timezone' => (string)$slot['display_timezone'],
            'appointment_id' => (int)$appointment['id'],
        ]);
        $pdo->prepare(
            'UPDATE appointment_reminders
             SET status="cancelled"
             WHERE appointment_id=:appointment_id
               AND status IN ("pending","ready")'
        )->execute([
            'appointment_id' => (int)$appointment['id'],
        ]);

        if (!empty($appointment['reminder_opt_in'])) {
            booking_schedule_reminder([
                'id' => (int)$appointment['id'],
                'start_at' => $startUtc->format('Y-m-d H:i:s'),
            ]);
        }

        if ((int)($appointment['crm_contact_id'] ?? 0) > 0) {
            $pdo->prepare(
                'INSERT INTO crm_activities
                    (contact_id,opportunity_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:opportunity_id,"meeting",
                     "Appointment rescheduled",:body)'
            )->execute([
                'contact_id' => (int)$appointment['crm_contact_id'],
                'opportunity_id' => (int)($appointment['crm_opportunity_id'] ?? 0) ?: null,
                'body' => (string)$slot['date_label']
                    . ' · ' . (string)$slot['time_label']
                    . ' · ' . (string)$slot['display_timezone'],
            ]);
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
        'Appointment rescheduled',
        (string)$appointment['display_name']
            . ' selected a new time for '
            . (string)$appointment['booking_type_name']
            . '.',
        'portal/admin.php?view=bookings&appointment='
            . (int)$appointment['id'],
        'appointment',
        (int)$appointment['id'],
        'normal'
    );
    unset($_SESSION['nmm_booking_availability_cache']);

    return booking_appointment_by_token($token);
}

function booking_admin_stats(): array
{
    if (!booking_schema_available()) {
        return [];
    }

    $row = db()->query(
        'SELECT
            SUM(start_at>=UTC_TIMESTAMP()
                AND status IN ("requested","confirmed")) AS upcoming,
            SUM(status="requested") AS requested,
            SUM(status="confirmed") AS confirmed,
            SUM(status="completed") AS completed,
            SUM(status="cancelled") AS cancelled
         FROM appointments'
    )->fetch() ?: [];
    $row['reminders_ready'] = (int)(
        db()->query(
            'SELECT COUNT(*)
             FROM appointment_reminders
             WHERE status IN ("ready","failed")
               OR (
                    status="pending"
                    AND scheduled_for<=UTC_TIMESTAMP()
               )'
        )->fetchColumn() ?: 0
    );

    return array_map(
        static fn(mixed $value): int => (int)($value ?? 0),
        $row
    );
}

function booking_analytics(int $days = 30): array
{
    $days = max(1, min(365, $days));
    $result = [
        'page_views' => 0,
        'slot_views' => 0,
        'submissions' => 0,
        'reschedules' => 0,
        'cancellations' => 0,
        'appointments' => [],
    ];

    if (
        function_exists('visitor_intelligence_schema_available')
        && visitor_intelligence_schema_available()
    ) {
        $statement = db()->query(
            'SELECT
                SUM(event_type="booking_page_view") AS page_views,
                SUM(event_type="booking_slot_view") AS slot_views,
                SUM(event_type="appointment_booking_submit") AS submissions,
                SUM(event_type="appointment_rescheduled") AS reschedules,
                SUM(event_type="appointment_cancelled") AS cancellations
             FROM visitor_events
             WHERE occurred_at>=UTC_TIMESTAMP()-INTERVAL '
             . $days
             . ' DAY'
        );
        $row = $statement->fetch() ?: [];

        foreach (
            ['page_views','slot_views','submissions','reschedules','cancellations']
            as $key
        ) {
            $result[$key] = (int)($row[$key] ?? 0);
        }
    }

    $statement = db()->query(
        'SELECT type.id,type.name,type.slug,type.color_hex,
                COUNT(appointment.id) AS appointments,
                SUM(appointment.status="requested") AS requested,
                SUM(appointment.status="confirmed") AS confirmed,
                SUM(appointment.status="completed") AS completed,
                SUM(appointment.status="cancelled") AS cancelled
         FROM booking_types type
         LEFT JOIN appointments appointment
           ON appointment.booking_type_id=type.id
          AND appointment.created_at>=UTC_TIMESTAMP()-INTERVAL '
         . $days
         . ' DAY
         GROUP BY type.id,type.name,type.slug,type.color_hex
         ORDER BY appointments DESC,type.sort_order,type.name'
    );
    $result['appointments'] = $statement->fetchAll();

    return $result;
}

function booking_absolute_url(string $path): string
{
    $base = trim((string)(nmm_config('app')['base_url'] ?? ''));

    if ($base !== '') {
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    $scheme = (
        !empty($_SERVER['HTTPS'])
        && strtolower((string)$_SERVER['HTTPS']) !== 'off'
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

function booking_ics_escape(string $value): string
{
    return str_replace(
        ["\\", ";", ",", "\r\n", "\r", "\n"],
        ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"],
        $value
    );
}

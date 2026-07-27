<?php
/* North Mountain Media build: 20260727-site-controls-landing-v60 */
declare(strict_types=1);

function visitor_intelligence_config(): array
{
    return nmm_config('visitor_intelligence') + [
        'enabled' => true,
        'visitor_cookie_days' => 365,
        'session_minutes' => 30,
        'event_rate_limit' => 240,
        'event_rate_window_seconds' => 3600,
        'respect_global_privacy_control' => true,
        'respect_do_not_track' => false,
        'store_chat_prompt_text' => true,
        'chat_prompt_max_length' => 1000,
        'hash_secret' => '',
        'homeserver_export_enabled' => false,
    ];
}

function visitor_intelligence_schema_available(): bool
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
                    "visitor_profiles",
                    "visitor_sessions",
                    "visitor_events"
               )'
        );
        $available = (int)$statement->fetchColumn() === 3;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function visitor_intelligence_privacy_disabled(): bool
{
    $config = visitor_intelligence_config();

    if (!(bool)$config['enabled']) {
        return true;
    }

    $gpc = trim((string)($_SERVER['HTTP_SEC_GPC'] ?? ''));
    $dnt = trim((string)($_SERVER['HTTP_DNT'] ?? ''));

    if (
        (bool)$config['respect_global_privacy_control']
        && $gpc === '1'
    ) {
        return true;
    }

    return (
        (bool)$config['respect_do_not_track']
        && $dnt === '1'
    );
}

function visitor_intelligence_hash_secret(): string
{
    $config = visitor_intelligence_config();
    $secret = trim((string)($config['hash_secret'] ?? ''));

    if ($secret !== '') {
        return $secret;
    }

    $app = nmm_config('app');
    $fallback = trim((string)($app['setup_token'] ?? ''));

    if ($fallback !== '') {
        return $fallback;
    }

    return hash(
        'sha256',
        (string)($app['session_name'] ?? 'nmm_portal')
        . '|'
        . (string)(nmm_config('database')['name'] ?? 'north_mountain_media')
    );
}

function visitor_intelligence_hash(string $token): string
{
    return hash_hmac(
        'sha256',
        $token,
        visitor_intelligence_hash_secret()
    );
}

function visitor_intelligence_cookie_secure(): bool
{
    $baseUrl = strtolower(
        (string)(nmm_config('app')['base_url'] ?? '')
    );

    return (
        str_starts_with($baseUrl, 'https://')
        || (
            !empty($_SERVER['HTTPS'])
            && strtolower((string)$_SERVER['HTTPS']) !== 'off'
        )
    );
}

function visitor_intelligence_set_cookie(
    string $name,
    string $value,
    int $expires
): void {
    if (headers_sent()) {
        return;
    }

    setcookie(
        $name,
        $value,
        [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => visitor_intelligence_cookie_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

function visitor_intelligence_token(
    string $cookieName,
    int $expires
): string {
    $existing = strtolower(
        trim((string)($_COOKIE[$cookieName] ?? ''))
    );

    if (preg_match('/^[a-f0-9]{64}$/', $existing)) {
        visitor_intelligence_set_cookie(
            $cookieName,
            $existing,
            $expires
        );

        return $existing;
    }

    $token = bin2hex(random_bytes(32));
    $_COOKIE[$cookieName] = $token;

    visitor_intelligence_set_cookie(
        $cookieName,
        $token,
        $expires
    );

    return $token;
}

function visitor_intelligence_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr(
        (ord($bytes[6]) & 0x0f) | 0x40
    );
    $bytes[8] = chr(
        (ord($bytes[8]) & 0x3f) | 0x80
    );
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function visitor_intelligence_text(
    mixed $value,
    int $maximum
): string {
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text)
        ?? '';

    return substr($text, 0, max(0, $maximum));
}

function visitor_intelligence_path(mixed $value): string
{
    $path = visitor_intelligence_text($value, 500);

    if ($path === '') {
        $path = (string)parse_url(
            (string)($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
    }

    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    return substr($path, 0, 500);
}

function visitor_intelligence_url(
    mixed $value,
    int $maximum = 1000
): string {
    $url = visitor_intelligence_text($value, $maximum);

    if ($url === '') {
        return '';
    }

    if (
        str_starts_with($url, '/')
        || preg_match('#^https?://#i', $url)
    ) {
        return $url;
    }

    return '';
}

function visitor_intelligence_referrer_host(
    string $referrer
): string {
    $host = strtolower(
        (string)parse_url($referrer, PHP_URL_HOST)
    );

    return substr(
        preg_replace('/[^a-z0-9.\-]/i', '', $host) ?? '',
        0,
        255
    );
}

function visitor_intelligence_device(
    string $userAgent
): string {
    $value = strtolower($userAgent);

    if (
        str_contains($value, 'ipad')
        || str_contains($value, 'tablet')
    ) {
        return 'tablet';
    }

    if (
        str_contains($value, 'mobile')
        || str_contains($value, 'iphone')
        || str_contains($value, 'android')
    ) {
        return 'mobile';
    }

    if ($value === '') {
        return 'unknown';
    }

    return 'desktop';
}

function visitor_intelligence_browser(
    string $userAgent
): string {
    $checks = [
        'edg/' => 'Edge',
        'opr/' => 'Opera',
        'firefox/' => 'Firefox',
        'chrome/' => 'Chrome',
        'safari/' => 'Safari',
    ];

    $lower = strtolower($userAgent);

    foreach ($checks as $needle => $label) {
        if (str_contains($lower, $needle)) {
            return $label;
        }
    }

    return $userAgent !== '' ? 'Other' : 'Unknown';
}

function visitor_intelligence_platform(
    string $userAgent
): string {
    $lower = strtolower($userAgent);

    foreach ([
        'windows' => 'Windows',
        'android' => 'Android',
        'iphone' => 'iOS',
        'ipad' => 'iPadOS',
        'macintosh' => 'macOS',
        'linux' => 'Linux',
    ] as $needle => $label) {
        if (str_contains($lower, $needle)) {
            return $label;
        }
    }

    return $userAgent !== '' ? 'Other' : 'Unknown';
}

function visitor_intelligence_metadata(
    mixed $metadata
): array {
    if (!is_array($metadata)) {
        return [];
    }

    $clean = [];

    foreach (array_slice($metadata, 0, 30, true) as $key => $value) {
        $cleanKey = preg_replace(
            '/[^a-z0-9_\-]/i',
            '',
            substr((string)$key, 0, 64)
        ) ?? '';

        if ($cleanKey === '') {
            continue;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            $clean[$cleanKey] = $value;
            continue;
        }

        if (is_string($value) || $value === null) {
            $clean[$cleanKey] = $value === null
                ? null
                : visitor_intelligence_text($value, 2000);
            continue;
        }

        if (is_array($value)) {
            $clean[$cleanKey] = array_values(array_map(
                static fn(mixed $item): string =>
                    visitor_intelligence_text($item, 250),
                array_slice($value, 0, 20)
            ));
        }
    }

    return $clean;
}

function visitor_intelligence_context(
    array $client = []
): ?array {
    if (
        visitor_intelligence_privacy_disabled()
        || !visitor_intelligence_schema_available()
    ) {
        return null;
    }

    $config = visitor_intelligence_config();
    $now = time();
    $visitorExpires = $now + (
        max(30, (int)$config['visitor_cookie_days'])
        * 86400
    );
    $sessionExpires = $now + (
        max(5, (int)$config['session_minutes'])
        * 60
    );

    $visitorToken = visitor_intelligence_token(
        'nmm_visitor',
        $visitorExpires
    );
    $sessionToken = visitor_intelligence_token(
        'nmm_visit_session',
        $sessionExpires
    );

    $visitorHash = visitor_intelligence_hash($visitorToken);
    $sessionHash = visitor_intelligence_hash($sessionToken);
    $path = visitor_intelligence_path(
        $client['page_path'] ?? ''
    );
    $referrer = visitor_intelligence_url(
        $client['referrer'] ?? (
            $_SERVER['HTTP_REFERER'] ?? ''
        )
    );
    $referrerHost = visitor_intelligence_referrer_host(
        $referrer
    );
    $userAgent = substr(
        (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        0,
        500
    );
    $device = visitor_intelligence_text(
        $client['device_type']
            ?? visitor_intelligence_device($userAgent),
        40
    );
    $browser = visitor_intelligence_text(
        $client['browser_family']
            ?? visitor_intelligence_browser($userAgent),
        80
    );
    $platform = visitor_intelligence_text(
        $client['platform']
            ?? visitor_intelligence_platform($userAgent),
        80
    );
    $language = visitor_intelligence_text(
        $client['language']
            ?? ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''),
        40
    );
    $timezone = visitor_intelligence_text(
        $client['timezone'] ?? '',
        100
    );
    $viewportWidth = max(
        0,
        min(20000, (int)($client['viewport_width'] ?? 0))
    );
    $viewportHeight = max(
        0,
        min(20000, (int)($client['viewport_height'] ?? 0))
    );
    $utmSource = visitor_intelligence_text(
        $client['utm_source'] ?? '',
        190
    );
    $utmMedium = visitor_intelligence_text(
        $client['utm_medium'] ?? '',
        190
    );
    $utmCampaign = visitor_intelligence_text(
        $client['utm_campaign'] ?? '',
        190
    );
    $utmTerm = visitor_intelligence_text(
        $client['utm_term'] ?? '',
        190
    );
    $utmContent = visitor_intelligence_text(
        $client['utm_content'] ?? '',
        190
    );

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $visitorStatement = $pdo->prepare(
            'SELECT *
             FROM visitor_profiles
             WHERE visitor_token_hash=:visitor_hash
             LIMIT 1
             FOR UPDATE'
        );
        $visitorStatement->execute([
            'visitor_hash' => $visitorHash,
        ]);
        $visitor = $visitorStatement->fetch();

        if (!$visitor) {
            $pdo->prepare(
                'INSERT INTO visitor_profiles
                    (visitor_token_hash,first_landing_path,
                     first_referrer_url,first_referrer_host,
                     first_utm_source,first_utm_medium,
                     first_utm_campaign,last_path,
                     last_device_type,last_browser_family,
                     last_platform,last_language,last_timezone,
                     visit_count,session_count,total_events)
                 VALUES
                    (:visitor_hash,:landing_path,
                     :referrer_url,:referrer_host,
                     :utm_source,:utm_medium,
                     :utm_campaign,:last_path,
                     :device_type,:browser_family,
                     :platform,:language,:timezone_name,
                     0,0,0)'
            )->execute([
                'visitor_hash' => $visitorHash,
                'landing_path' => $path,
                'referrer_url' => $referrer !== '' ? $referrer : null,
                'referrer_host' => $referrerHost !== ''
                    ? $referrerHost
                    : null,
                'utm_source' => $utmSource !== '' ? $utmSource : null,
                'utm_medium' => $utmMedium !== '' ? $utmMedium : null,
                'utm_campaign' => $utmCampaign !== ''
                    ? $utmCampaign
                    : null,
                'last_path' => $path,
                'device_type' => $device,
                'browser_family' => $browser,
                'platform' => $platform,
                'language' => $language,
                'timezone_name' => $timezone !== ''
                    ? $timezone
                    : null,
            ]);
            $visitorId = (int)$pdo->lastInsertId();
            $visitor = [
                'id' => $visitorId,
                'identified_contact_id' => null,
            ];
        } else {
            $visitorId = (int)$visitor['id'];
        }

        $sessionStatement = $pdo->prepare(
            'SELECT *
             FROM visitor_sessions
             WHERE session_token_hash=:session_hash
               AND visitor_id=:visitor_id
             LIMIT 1
             FOR UPDATE'
        );
        $sessionStatement->execute([
            'session_hash' => $sessionHash,
            'visitor_id' => $visitorId,
        ]);
        $session = $sessionStatement->fetch();
        $newSession = false;

        if (!$session) {
            $pdo->prepare(
                'INSERT INTO visitor_sessions
                    (visitor_id,session_token_hash,crm_contact_id,
                     landing_path,current_path,referrer_url,
                     referrer_host,utm_source,utm_medium,
                     utm_campaign,utm_term,utm_content,
                     device_type,browser_family,platform,
                     language,timezone_name,viewport_width,
                     viewport_height)
                 VALUES
                    (:visitor_id,:session_hash,:crm_contact_id,
                     :landing_path,:current_path,:referrer_url,
                     :referrer_host,:utm_source,:utm_medium,
                     :utm_campaign,:utm_term,:utm_content,
                     :device_type,:browser_family,:platform,
                     :language,:timezone_name,:viewport_width,
                     :viewport_height)'
            )->execute([
                'visitor_id' => $visitorId,
                'session_hash' => $sessionHash,
                'crm_contact_id' => (
                    (int)($visitor['identified_contact_id'] ?? 0) > 0
                        ? (int)$visitor['identified_contact_id']
                        : null
                ),
                'landing_path' => $path,
                'current_path' => $path,
                'referrer_url' => $referrer !== '' ? $referrer : null,
                'referrer_host' => $referrerHost !== ''
                    ? $referrerHost
                    : null,
                'utm_source' => $utmSource !== '' ? $utmSource : null,
                'utm_medium' => $utmMedium !== '' ? $utmMedium : null,
                'utm_campaign' => $utmCampaign !== ''
                    ? $utmCampaign
                    : null,
                'utm_term' => $utmTerm !== '' ? $utmTerm : null,
                'utm_content' => $utmContent !== ''
                    ? $utmContent
                    : null,
                'device_type' => $device,
                'browser_family' => $browser,
                'platform' => $platform,
                'language' => $language,
                'timezone_name' => $timezone !== ''
                    ? $timezone
                    : null,
                'viewport_width' => $viewportWidth > 0
                    ? $viewportWidth
                    : null,
                'viewport_height' => $viewportHeight > 0
                    ? $viewportHeight
                    : null,
            ]);
            $sessionId = (int)$pdo->lastInsertId();
            $session = [
                'id' => $sessionId,
                'crm_contact_id' => $visitor['identified_contact_id'] ?? null,
                'last_portfolio_project_id' => null,
            ];
            $newSession = true;

            $pdo->prepare(
                'UPDATE visitor_profiles
                 SET session_count=session_count+1,
                     visit_count=visit_count+1
                 WHERE id=:visitor_id'
            )->execute(['visitor_id' => $visitorId]);
        } else {
            $sessionId = (int)$session['id'];
        }

        $pdo->prepare(
            'UPDATE visitor_profiles
             SET last_seen_at=UTC_TIMESTAMP(),
                 last_path=:last_path,
                 last_device_type=:device_type,
                 last_browser_family=:browser_family,
                 last_platform=:platform,
                 last_language=:language,
                 last_timezone=:timezone_name
             WHERE id=:visitor_id'
        )->execute([
            'last_path' => $path,
            'device_type' => $device,
            'browser_family' => $browser,
            'platform' => $platform,
            'language' => $language,
            'timezone_name' => $timezone !== ''
                ? $timezone
                : null,
            'visitor_id' => $visitorId,
        ]);

        $pdo->prepare(
            'UPDATE visitor_sessions
             SET last_activity_at=UTC_TIMESTAMP(),
                 current_path=:current_path,
                 device_type=:device_type,
                 browser_family=:browser_family,
                 platform=:platform,
                 language=:language,
                 timezone_name=:timezone_name,
                 viewport_width=COALESCE(:viewport_width,viewport_width),
                 viewport_height=COALESCE(:viewport_height,viewport_height)
             WHERE id=:session_id'
        )->execute([
            'current_path' => $path,
            'device_type' => $device,
            'browser_family' => $browser,
            'platform' => $platform,
            'language' => $language,
            'timezone_name' => $timezone !== ''
                ? $timezone
                : null,
            'viewport_width' => $viewportWidth > 0
                ? $viewportWidth
                : null,
            'viewport_height' => $viewportHeight > 0
                ? $viewportHeight
                : null,
            'session_id' => $sessionId,
        ]);

        $pdo->commit();

        return [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'crm_contact_id' => (
                (int)($session['crm_contact_id'] ?? 0) > 0
                    ? (int)$session['crm_contact_id']
                    : (
                        (int)($visitor['identified_contact_id'] ?? 0) > 0
                            ? (int)$visitor['identified_contact_id']
                            : null
                    )
            ),
            'last_portfolio_project_id' => (
                (int)($session['last_portfolio_project_id'] ?? 0) > 0
                    ? (int)$session['last_portfolio_project_id']
                    : null
            ),
            'new_session' => $newSession,
            'page_path' => $path,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function visitor_intelligence_project_id(
    ?string $slug
): ?int {
    $slug = strtolower(
        visitor_intelligence_text($slug ?? '', 190)
    );

    if ($slug === '') {
        return null;
    }

    try {
        $statement = db()->prepare(
            'SELECT id
             FROM portfolio_projects
             WHERE slug=:slug
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $id = (int)($statement->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
    } catch (Throwable) {
        return null;
    }
}

function visitor_intelligence_track(
    string $eventType,
    array $data = [],
    ?array $context = null
): ?int {
    if (
        visitor_intelligence_privacy_disabled()
        || !visitor_intelligence_schema_available()
    ) {
        return null;
    }

    $allowedTypes = [
        'page_view',
        'page_engagement',
        'portfolio_view',
        'portfolio_gallery',
        'portfolio_link_click',
        'project_inquiry_intent',
        'music_library_view',
        'music_album_view',
        'music_playlist_view',
        'music_track_play',
        'blog_archive_view',
        'blog_post_view',
        'events_calendar_view',
        'event_detail_view',
        'event_registration_submit',
        'event_ics_download',
        'booking_page_view',
        'booking_slot_view',
        'appointment_booking_submit',
        'appointment_rescheduled',
        'appointment_cancelled',
        'appointment_ics_download',
        'intake_page_view',
        'intake_submitted',
        'proposal_viewed',
        'proposal_accepted',
        'proposal_declined',
        'proposal_pdf_downloaded',
        'resume_post_view',
        'chat_prompt',
        'resume_view',
        'resume_download',
        'call_widget_open',
        'call_started',
        'callback_requested',
        'public_message_submitted',
        'voicemail_started',
        'voicemail_submitted',
        'contact_form_open',
        'contact_form_submitted',
        'crm_identified',
        'return_visit',
    ];

    if (!in_array($eventType, $allowedTypes, true)) {
        return null;
    }

    $context ??= visitor_intelligence_context($data);

    if (!$context) {
        return null;
    }

    $metadata = visitor_intelligence_metadata(
        $data['metadata'] ?? []
    );
    $config = visitor_intelligence_config();

    if (
        $eventType === 'chat_prompt'
        && !(bool)$config['store_chat_prompt_text']
    ) {
        unset($metadata['prompt']);
    }

    if (
        isset($metadata['prompt'])
        && is_string($metadata['prompt'])
    ) {
        $metadata['prompt'] = substr(
            $metadata['prompt'],
            0,
            max(
                100,
                (int)$config['chat_prompt_max_length']
            )
        );
    }

    $projectId = (int)($data['portfolio_project_id'] ?? 0);

    if ($projectId <= 0) {
        $projectId = (int)(
            visitor_intelligence_project_id(
                isset($data['portfolio_slug'])
                    ? (string)$data['portfolio_slug']
                    : null
            ) ?? 0
        );
    }

    if (
        $projectId <= 0
        && (int)($context['last_portfolio_project_id'] ?? 0) > 0
    ) {
        $projectId = (int)$context['last_portfolio_project_id'];
    }

    $pagePath = visitor_intelligence_path(
        $data['page_path'] ?? $context['page_path'] ?? ''
    );
    $targetUrl = visitor_intelligence_url(
        $data['target_url'] ?? ''
    );
    $eventLabel = visitor_intelligence_text(
        $data['event_label'] ?? '',
        190
    );
    $duration = max(
        0,
        min(86400, (int)($data['duration_seconds'] ?? 0))
    );
    $crmContactId = (int)(
        $data['crm_contact_id']
        ?? $context['crm_contact_id']
        ?? 0
    );
    $crmOpportunityId = (int)(
        $data['crm_opportunity_id'] ?? 0
    );

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            'INSERT INTO visitor_events
                (event_uuid,visitor_id,session_id,
                 crm_contact_id,crm_opportunity_id,
                 portfolio_project_id,event_type,
                 event_label,page_path,target_url,
                 metadata_json,duration_seconds)
             VALUES
                (:event_uuid,:visitor_id,:session_id,
                 :crm_contact_id,:crm_opportunity_id,
                 :portfolio_project_id,:event_type,
                 :event_label,:page_path,:target_url,
                 :metadata_json,:duration_seconds)'
        );
        $statement->execute([
            'event_uuid' => visitor_intelligence_uuid(),
            'visitor_id' => (int)$context['visitor_id'],
            'session_id' => (int)$context['session_id'],
            'crm_contact_id' => $crmContactId > 0
                ? $crmContactId
                : null,
            'crm_opportunity_id' => $crmOpportunityId > 0
                ? $crmOpportunityId
                : null,
            'portfolio_project_id' => $projectId > 0
                ? $projectId
                : null,
            'event_type' => $eventType,
            'event_label' => $eventLabel !== ''
                ? $eventLabel
                : null,
            'page_path' => $pagePath,
            'target_url' => $targetUrl !== ''
                ? $targetUrl
                : null,
            'metadata_json' => $metadata
                ? json_encode(
                    $metadata,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                )
                : null,
            'duration_seconds' => $duration > 0
                ? $duration
                : null,
        ]);
        $eventId = (int)$pdo->lastInsertId();

        $sessionUpdate = (
            'UPDATE visitor_sessions
             SET last_activity_at=UTC_TIMESTAMP(),
                 current_path=:current_path,
                 event_count=event_count+1'
            . (
                $eventType === 'page_view'
                    ? ', page_view_count=page_view_count+1'
                    : ''
            )
            . (
                $duration > 0
                    ? ', active_seconds=active_seconds+:active_seconds'
                    : ''
            )
            . (
                $projectId > 0
                && in_array(
                    $eventType,
                    [
                        'portfolio_view',
                        'portfolio_gallery',
                        'portfolio_link_click',
                        'project_inquiry_intent',
                    ],
                    true
                )
                    ? ', last_portfolio_project_id=:project_id'
                    : ''
            )
            . (
                $eventType === 'page_engagement'
                    ? ', ended_at=UTC_TIMESTAMP()'
                    : ''
            )
            . ' WHERE id=:session_id'
        );

        $sessionParams = [
            'current_path' => $pagePath,
            'session_id' => (int)$context['session_id'],
        ];

        if ($duration > 0) {
            $sessionParams['active_seconds'] = $duration;
        }

        if (
            $projectId > 0
            && in_array(
                $eventType,
                [
                    'portfolio_view',
                    'portfolio_gallery',
                    'portfolio_link_click',
                    'project_inquiry_intent',
                ],
                true
            )
        ) {
            $sessionParams['project_id'] = $projectId;
        }

        $pdo->prepare($sessionUpdate)->execute(
            $sessionParams
        );

        $profileUpdate = (
            'UPDATE visitor_profiles
             SET last_seen_at=UTC_TIMESTAMP(),
                 last_path=:last_path,
                 total_events=total_events+1'
            . (
                $duration > 0
                    ? ', total_active_seconds=total_active_seconds+:active_seconds'
                    : ''
            )
            . ' WHERE id=:visitor_id'
        );

        $profileParams = [
            'last_path' => $pagePath,
            'visitor_id' => (int)$context['visitor_id'],
        ];

        if ($duration > 0) {
            $profileParams['active_seconds'] = $duration;
        }

        $pdo->prepare($profileUpdate)->execute(
            $profileParams
        );

        $pdo->commit();

        return $eventId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function visitor_intelligence_rotate_identity(
    array $data = []
): ?array {
    if (
        visitor_intelligence_privacy_disabled()
        || !visitor_intelligence_schema_available()
    ) {
        return null;
    }

    $config = visitor_intelligence_config();
    $now = time();
    $visitorToken = bin2hex(random_bytes(32));
    $sessionToken = bin2hex(random_bytes(32));

    $_COOKIE['nmm_visitor'] = $visitorToken;
    $_COOKIE['nmm_visit_session'] = $sessionToken;

    visitor_intelligence_set_cookie(
        'nmm_visitor',
        $visitorToken,
        $now + (
            max(30, (int)$config['visitor_cookie_days'])
            * 86400
        )
    );
    visitor_intelligence_set_cookie(
        'nmm_visit_session',
        $sessionToken,
        $now + (
            max(5, (int)$config['session_minutes'])
            * 60
        )
    );

    return visitor_intelligence_context($data);
}

function visitor_intelligence_attach_contact(
    int $contactId,
    string $eventType = 'crm_identified',
    array $data = []
): ?int {
    if (
        $contactId <= 0
        || visitor_intelligence_privacy_disabled()
        || !visitor_intelligence_schema_available()
    ) {
        return null;
    }

    $context = visitor_intelligence_context($data);

    if (!$context) {
        return null;
    }

    $identityStatement = db()->prepare(
        'SELECT identified_contact_id
         FROM visitor_profiles
         WHERE id=:visitor_id
         LIMIT 1'
    );
    $identityStatement->execute([
        'visitor_id' => (int)$context['visitor_id'],
    ]);
    $existingContactId = (int)(
        $identityStatement->fetchColumn() ?: 0
    );

    if (
        $existingContactId > 0
        && $existingContactId !== $contactId
    ) {
        $context = visitor_intelligence_rotate_identity(
            $data
        );

        if (!$context) {
            return null;
        }
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'UPDATE visitor_profiles
             SET identified_contact_id=:contact_id,
                 identified_at=COALESCE(
                    identified_at,
                    UTC_TIMESTAMP()
                 )
             WHERE id=:visitor_id'
        )->execute([
            'contact_id' => $contactId,
            'visitor_id' => (int)$context['visitor_id'],
        ]);

        $pdo->prepare(
            'UPDATE visitor_sessions
             SET crm_contact_id=:contact_id
             WHERE visitor_id=:visitor_id'
        )->execute([
            'contact_id' => $contactId,
            'visitor_id' => (int)$context['visitor_id'],
        ]);

        $pdo->prepare(
            'UPDATE visitor_events
             SET crm_contact_id=:contact_id
             WHERE visitor_id=:visitor_id
               AND crm_contact_id IS NULL'
        )->execute([
            'contact_id' => $contactId,
            'visitor_id' => (int)$context['visitor_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    $context['crm_contact_id'] = $contactId;

    return visitor_intelligence_track(
        $eventType,
        $data + ['crm_contact_id' => $contactId],
        $context
    );
}

function visitor_intelligence_event_label(
    string $eventType
): string {
    return [
        'page_view' => 'Page viewed',
        'page_engagement' => 'Page engagement',
        'portfolio_view' => 'Portfolio viewed',
        'portfolio_gallery' => 'Portfolio gallery viewed',
        'portfolio_link_click' => 'Project link opened',
        'project_inquiry_intent' => 'Project inquiry intent',
        'music_library_view' => 'Music Library viewed',
        'music_album_view' => 'Music album viewed',
        'music_playlist_view' => 'Music playlist viewed',
        'music_track_play' => 'Music track played',
        'blog_archive_view' => 'Blog archive viewed',
        'blog_post_view' => 'Blog post viewed',
        'events_calendar_view' => 'Events calendar viewed',
        'event_detail_view' => 'Event viewed',
        'event_registration_submit' => 'Event registration submitted',
        'event_ics_download' => 'Event calendar downloaded',
        'booking_page_view' => 'Booking page viewed',
        'booking_slot_view' => 'Appointment times viewed',
        'appointment_booking_submit' => 'Appointment booked',
        'appointment_rescheduled' => 'Appointment rescheduled',
        'appointment_cancelled' => 'Appointment cancelled',
        'appointment_ics_download' => 'Appointment calendar downloaded',
        'intake_page_view' => 'Project intake viewed',
        'intake_submitted' => 'Project intake submitted',
        'proposal_viewed' => 'Proposal viewed',
        'proposal_accepted' => 'Proposal accepted',
        'proposal_declined' => 'Proposal declined',
        'proposal_pdf_downloaded' => 'Proposal PDF downloaded',
        'resume_post_view' => 'Resume post viewed',
        'chat_prompt' => 'Chat prompt submitted',
        'resume_view' => 'Resume viewed',
        'resume_download' => 'Resume downloaded',
        'call_widget_open' => 'Call Center opened',
        'call_started' => 'Browser call started',
        'callback_requested' => 'Callback requested',
        'public_message_submitted' => 'Public message submitted',
        'voicemail_started' => 'Voicemail recording started',
        'voicemail_submitted' => 'Voicemail submitted',
        'contact_form_open' => 'Contact form opened',
        'contact_form_submitted' => 'Contact form submitted',
        'crm_identified' => 'Visitor identified',
        'return_visit' => 'Known contact returned',
    ][$eventType] ?? status_label($eventType);
}

function visitor_intelligence_metadata_decode(
    mixed $value
): array {
    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function visitor_intelligence_summary(
    int $days
): array {
    $days = max(1, min(365, $days));

    if (!visitor_intelligence_schema_available()) {
        return [];
    }

    $statement = db()->query(
        'SELECT
            COUNT(DISTINCT visitor_id) AS unique_visitors,
            COUNT(DISTINCT session_id) AS sessions,
            COUNT(*) AS events,
            COUNT(DISTINCT CASE
                WHEN crm_contact_id IS NOT NULL
                THEN visitor_id END
            ) AS known_visitors,
            SUM(event_type="portfolio_view") AS portfolio_views,
            SUM(event_type="portfolio_link_click") AS project_clicks,
            SUM(event_type="chat_prompt") AS chat_prompts,
            SUM(event_type="music_track_play") AS music_plays,
            COUNT(DISTINCT CASE
                WHEN event_type="music_track_play"
                THEN JSON_UNQUOTE(
                    JSON_EXTRACT(metadata_json,"$.track_id")
                ) END
            ) AS music_tracks_played,
            SUM(event_type="resume_view") AS resume_views,
            SUM(event_type="resume_download") AS resume_downloads,
            SUM(event_type IN (
                "call_started",
                "callback_requested",
                "voicemail_submitted"
            )) AS voice_contacts,
            SUM(event_type="contact_form_submitted") AS contact_forms,
            COUNT(DISTINCT crm_opportunity_id) AS opportunities,
            SUM(event_type="return_visit") AS return_visits,
            SUM(event_type IN (
                "contact_form_submitted",
                "call_started",
                "callback_requested",
                "voicemail_submitted",
                "appointment_booking_submit",
                "intake_submitted",
                "proposal_accepted"
            )) AS conversions,
            COALESCE(SUM(duration_seconds),0) AS active_seconds
         FROM visitor_events
         WHERE occurred_at>=UTC_TIMESTAMP()-INTERVAL '
         . $days
         . ' DAY'
    );

    $row = $statement->fetch() ?: [];

    return array_map(
        static fn(mixed $value): int => (int)($value ?? 0),
        $row
    );
}

function visitor_intelligence_portfolio_metrics(
    int $days
): array {
    $days = max(1, min(365, $days));

    if (!visitor_intelligence_schema_available()) {
        return [];
    }

    $statement = db()->query(
        'SELECT project.id,
                project.title,
                project.slug,
                project.status,
                project.featured,
                project.sort_order,
                COUNT(event.id) AS total_events,
                SUM(event.event_type="portfolio_view") AS views,
                COUNT(DISTINCT CASE
                    WHEN event.event_type="portfolio_view"
                    THEN event.visitor_id END
                ) AS unique_visitors,
                SUM(event.event_type="portfolio_gallery") AS gallery_actions,
                SUM(event.event_type="portfolio_link_click") AS project_clicks,
                SUM(event.event_type="project_inquiry_intent") AS inquiry_intents,
                SUM(event.event_type="chat_prompt") AS chat_prompts,
                SUM(event.event_type IN (
                    "contact_form_submitted",
                    "call_started",
                    "callback_requested",
                    "voicemail_submitted",
                    "appointment_booking_submit",
                    "intake_submitted",
                    "proposal_accepted"
                )) AS conversions,
                COALESCE(SUM(event.duration_seconds),0) AS active_seconds,
                MAX(event.occurred_at) AS last_activity_at
         FROM portfolio_projects project
         LEFT JOIN visitor_events event
           ON event.portfolio_project_id=project.id
          AND event.occurred_at>=UTC_TIMESTAMP()-INTERVAL '
         . $days
         . ' DAY
         GROUP BY project.id
         ORDER BY
            views DESC,
            project_clicks DESC,
            project.featured DESC,
            project.sort_order ASC'
    );

    return $statement->fetchAll();
}

function visitor_intelligence_daily_trend(
    int $days
): array {
    $days = max(7, min(90, $days));

    if (!visitor_intelligence_schema_available()) {
        return [];
    }

    $statement = db()->query(
        'SELECT DATE(occurred_at) AS activity_date,
                COUNT(DISTINCT visitor_id) AS visitors,
                COUNT(DISTINCT session_id) AS sessions,
                SUM(event_type="portfolio_view") AS portfolio_views,
                SUM(event_type IN (
                    "contact_form_submitted",
                    "call_started",
                    "callback_requested",
                    "voicemail_submitted",
                    "appointment_booking_submit",
                    "intake_submitted",
                    "proposal_accepted"
                )) AS conversions
         FROM visitor_events
         WHERE occurred_at>=UTC_DATE()-INTERVAL '
         . ($days - 1)
         . ' DAY
         GROUP BY DATE(occurred_at)
         ORDER BY activity_date ASC'
    );
    $indexed = [];

    foreach ($statement->fetchAll() as $row) {
        $indexed[(string)$row['activity_date']] = $row;
    }

    $output = [];

    for ($offset = $days - 1; $offset >= 0; $offset--) {
        $date = gmdate(
            'Y-m-d',
            strtotime('-' . $offset . ' days')
        );
        $row = $indexed[$date] ?? [];

        $output[] = [
            'date' => $date,
            'visitors' => (int)($row['visitors'] ?? 0),
            'sessions' => (int)($row['sessions'] ?? 0),
            'portfolio_views' => (int)(
                $row['portfolio_views'] ?? 0
            ),
            'conversions' => (int)(
                $row['conversions'] ?? 0
            ),
        ];
    }

    return $output;
}

function visitor_intelligence_recent_visitors(
    int $limit = 30
): array {
    $limit = max(1, min(100, $limit));

    if (!visitor_intelligence_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT profile.*,
                contact.display_name AS contact_name,
                contact.email AS contact_email,
                session.id AS latest_session_id,
                session.started_at AS latest_session_started_at,
                session.last_activity_at AS latest_session_activity_at,
                session.landing_path,
                session.current_path,
                session.referrer_host,
                session.device_type,
                session.platform,
                session.page_view_count,
                session.event_count,
                session.active_seconds,
                project.title AS last_project_title,
                project.slug AS last_project_slug
         FROM visitor_profiles profile
         LEFT JOIN crm_contacts contact
           ON contact.id=profile.identified_contact_id
         LEFT JOIN visitor_sessions session
           ON session.id=(
                SELECT newest_session.id
                FROM visitor_sessions newest_session
                WHERE newest_session.visitor_id=profile.id
                ORDER BY newest_session.last_activity_at DESC,
                         newest_session.id DESC
                LIMIT 1
           )
         LEFT JOIN portfolio_projects project
           ON project.id=session.last_portfolio_project_id
         ORDER BY profile.last_seen_at DESC
         LIMIT ' . $limit
    )->fetchAll();
}

function visitor_intelligence_visitor_profile(
    int $visitorId
): ?array {
    if (
        $visitorId <= 0
        || !visitor_intelligence_schema_available()
    ) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT profile.*,
                contact.display_name AS contact_name,
                contact.email AS contact_email,
                contact.company AS contact_company,
                COUNT(DISTINCT session.id) AS session_total,
                COUNT(DISTINCT event.id) AS event_total,
                MIN(session.started_at) AS first_session_at,
                MAX(session.last_activity_at) AS last_session_at
         FROM visitor_profiles profile
         LEFT JOIN crm_contacts contact
           ON contact.id=profile.identified_contact_id
         LEFT JOIN visitor_sessions session
           ON session.visitor_id=profile.id
         LEFT JOIN visitor_events event
           ON event.visitor_id=profile.id
         WHERE profile.id=:visitor_id
         GROUP BY profile.id
         LIMIT 1'
    );
    $statement->execute(['visitor_id' => $visitorId]);

    return $statement->fetch() ?: null;
}

function visitor_intelligence_visitor_events(
    int $visitorId,
    int $limit = 100
): array {
    $limit = max(1, min(250, $limit));

    if (
        $visitorId <= 0
        || !visitor_intelligence_schema_available()
    ) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT event.*,
                project.title AS project_title,
                project.slug AS project_slug,
                contact.display_name AS contact_name
         FROM visitor_events event
         LEFT JOIN portfolio_projects project
           ON project.id=event.portfolio_project_id
         LEFT JOIN crm_contacts contact
           ON contact.id=event.crm_contact_id
         WHERE event.visitor_id=:visitor_id
         ORDER BY event.occurred_at DESC,event.id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['visitor_id' => $visitorId]);

    return $statement->fetchAll();
}

function visitor_intelligence_contact_summary(
    int $contactId
): array {
    if (
        $contactId <= 0
        || !visitor_intelligence_schema_available()
    ) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT
            COUNT(DISTINCT visitor_id) AS visitor_profiles,
            COUNT(DISTINCT session_id) AS sessions,
            COUNT(*) AS events,
            SUM(event_type="page_view") AS page_views,
            SUM(event_type="portfolio_view") AS portfolio_views,
            SUM(event_type="chat_prompt") AS chat_prompts,
            SUM(event_type="music_track_play") AS music_plays,
            COUNT(DISTINCT CASE
                WHEN event_type="music_track_play"
                THEN JSON_UNQUOTE(
                    JSON_EXTRACT(metadata_json,"$.track_id")
                ) END
            ) AS music_tracks_played,
            SUM(event_type="resume_view") AS resume_views,
            SUM(event_type="resume_download") AS resume_downloads,
            MAX(occurred_at) AS last_seen_at,
            MIN(occurred_at) AS first_seen_at
         FROM visitor_events
         WHERE crm_contact_id=:contact_id'
    );
    $statement->execute(['contact_id' => $contactId]);

    return $statement->fetch() ?: [];
}

function visitor_intelligence_contact_events(
    int $contactId,
    int $limit = 100
): array {
    $limit = max(1, min(250, $limit));

    if (
        $contactId <= 0
        || !visitor_intelligence_schema_available()
    ) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT event.*,
                project.title AS project_title,
                project.slug AS project_slug,
                opportunity.title AS opportunity_title,
                opportunity.stage AS opportunity_stage,
                session.referrer_host,
                session.device_type,
                session.platform
         FROM visitor_events event
         LEFT JOIN portfolio_projects project
           ON project.id=event.portfolio_project_id
         LEFT JOIN crm_opportunities opportunity
           ON opportunity.id=event.crm_opportunity_id
         LEFT JOIN visitor_sessions session
           ON session.id=event.session_id
         WHERE event.crm_contact_id=:contact_id
         ORDER BY event.occurred_at DESC,event.id DESC
         LIMIT ' . $limit
    );
    $statement->execute(['contact_id' => $contactId]);

    return $statement->fetchAll();
}

function visitor_intelligence_top_referrers(
    int $days,
    int $limit = 10
): array {
    $days = max(1, min(365, $days));
    $limit = max(1, min(25, $limit));

    if (!visitor_intelligence_schema_available()) {
        return [];
    }

    return db()->query(
        'SELECT COALESCE(
                    NULLIF(referrer_host,""),
                    "Direct / internal"
                ) AS referrer,
                COUNT(*) AS sessions,
                COUNT(DISTINCT visitor_id) AS visitors
         FROM visitor_sessions
         WHERE started_at>=UTC_TIMESTAMP()-INTERVAL '
         . $days
         . ' DAY
         GROUP BY COALESCE(NULLIF(referrer_host,""),"Direct / internal")
         ORDER BY sessions DESC,visitors DESC
         LIMIT ' . $limit
    )->fetchAll();
}

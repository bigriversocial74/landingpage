<?php
declare(strict_types=1);

define('NMM_PUBLIC_PAGE', true);

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/visitor-intelligence.php';

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

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 24000) {
    json_response([
        'ok' => false,
        'message' => 'Request is too large.',
    ], 413);
}

if (visitor_intelligence_privacy_disabled()) {
    json_response([
        'ok' => true,
        'tracked' => false,
        'privacy_disabled' => true,
    ]);
}

if (!visitor_intelligence_schema_available()) {
    json_response([
        'ok' => true,
        'tracked' => false,
        'migration_required' => true,
    ]);
}

$contentType = strtolower(
    (string)($_SERVER['CONTENT_TYPE'] ?? '')
);
$payload = str_contains(
    $contentType,
    'application/json'
)
    ? json_decode(
        (string)file_get_contents('php://input'),
        true
    )
    : $_POST;

if (!is_array($payload)) {
    json_response([
        'ok' => false,
        'message' => 'Invalid activity payload.',
    ], 400);
}

$config = visitor_intelligence_config();
$ip = request_ip();
$visitorRateKey = (
    $_COOKIE['nmm_visitor'] ?? $ip
);

if (
    rate_limit_exceeded(
        'visitor_activity',
        (string)$visitorRateKey,
        max(30, (int)$config['event_rate_limit']),
        max(
            60,
            (int)$config['event_rate_window_seconds']
        )
    )
) {
    json_response([
        'ok' => false,
        'message' => 'Activity limit reached.',
    ], 429);
}

$eventType = visitor_intelligence_text(
    $payload['event_type'] ?? '',
    64
);

if ($eventType === '') {
    json_response([
        'ok' => false,
        'message' => 'Activity type is required.',
    ], 422);
}

$client = [
    'page_path' => $payload['page_path'] ?? '',
    'referrer' => $payload['referrer'] ?? '',
    'device_type' => $payload['device_type'] ?? '',
    'browser_family' => $payload['browser_family'] ?? '',
    'platform' => $payload['platform'] ?? '',
    'language' => $payload['language'] ?? '',
    'timezone' => $payload['timezone'] ?? '',
    'viewport_width' => $payload['viewport_width'] ?? 0,
    'viewport_height' => $payload['viewport_height'] ?? 0,
    'utm_source' => $payload['utm_source'] ?? '',
    'utm_medium' => $payload['utm_medium'] ?? '',
    'utm_campaign' => $payload['utm_campaign'] ?? '',
    'utm_term' => $payload['utm_term'] ?? '',
    'utm_content' => $payload['utm_content'] ?? '',
];

try {
    $context = visitor_intelligence_context($client);

    if (
        $context
        && !empty($context['new_session'])
        && (int)($context['crm_contact_id'] ?? 0) > 0
        && $eventType !== 'return_visit'
    ) {
        visitor_intelligence_track(
            'return_visit',
            [
                'event_label' => 'Known CRM contact returned',
                'page_path' => $payload['page_path'] ?? '',
                'metadata' => [
                    'referrer' => $payload['referrer'] ?? '',
                ],
            ],
            $context
        );
    }

    $eventId = visitor_intelligence_track(
        $eventType,
        [
            'page_path' => $payload['page_path'] ?? '',
            'target_url' => $payload['target_url'] ?? '',
            'event_label' => $payload['event_label'] ?? '',
            'duration_seconds' => $payload['duration_seconds'] ?? 0,
            'portfolio_slug' => $payload['portfolio_slug'] ?? '',
            'metadata' => $payload['metadata'] ?? [],
        ],
        $context
    );

    json_response([
        'ok' => true,
        'tracked' => $eventId !== null,
        'event_id' => $eventId,
    ]);
} catch (Throwable $exception) {
    error_log(
        'North Mountain Media visitor activity failed: '
        . $exception->getMessage()
    );

    json_response([
        'ok' => true,
        'tracked' => false,
    ]);
}

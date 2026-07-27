<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/bootstrap.php';
if(!nmm_module_enabled('events'))json_response(['ok'=>false,'message'=>'This public module is currently unavailable.'],404);
require_once dirname(__DIR__) . '/portal/visitor-intelligence.php';
require_once dirname(__DIR__) . '/portal/events-calendar.php';

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

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$jsonBody = str_contains($contentType, 'application/json');
$isJson = $jsonBody || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$data = $jsonBody
    ? json_decode((string)file_get_contents('php://input'), true)
    : $_POST;

if (!is_array($data)) {
    json_response([
        'ok' => false,
        'message' => 'Invalid event request.',
    ], 400);
}

$action = trim((string)($data['action'] ?? 'register'));

try {
    if ($action === 'cancel') {
        $token = trim((string)($data['token'] ?? ''));
        $registration = events_cancel_registration($token);

        if (!$registration) {
            throw new RuntimeException('Registration not found.');
        }

        if ($isJson) {
            json_response([
                'ok' => true,
                'message' => 'Your registration is cancelled.',
                'status' => 'cancelled',
            ]);
        }

        redirect(
            'event-registration.php?token=' . rawurlencode($token) . '&cancelled=1'
        );
    }

    $slug = trim((string)($data['event_slug'] ?? ''));
    $event = events_public_event_by_slug($slug, true);

    if (!$event) {
        throw new RuntimeException('The requested event is unavailable.');
    }

    $identity = strtolower(trim((string)($data['email'] ?? '')));
    $identity .= '|' . client_ip();

    if (rate_limit_exceeded('event_registration', $identity, 8, 3600)) {
        throw new RuntimeException(
            'Too many registration attempts. Please try again later.'
        );
    }

    $result = events_register_public(
        $event,
        $data,
        current_user()
    );

    if ($isJson) {
        json_response([
            'ok' => true,
            'message' => $result['message'],
            'status' => $result['status'],
            'confirmation_url' => $result['confirmation_url'],
        ]);
    }

    redirect($result['confirmation_url']);
} catch (Throwable $exception) {
    error_log(
        'North Mountain Media event registration failed: '
        . $exception->getMessage()
    );

    if ($isJson) {
        json_response([
            'ok' => false,
            'message' => $exception->getMessage(),
        ], 422);
    }

    $fallbackSlug = rawurlencode(trim((string)($data['event_slug'] ?? '')));
    redirect(
        'event.php?slug=' . $fallbackSlug
        . '&registration_error=' . rawurlencode($exception->getMessage())
        . '#register'
    );
}

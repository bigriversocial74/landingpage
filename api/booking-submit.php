<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/bootstrap.php';
if(!nmm_module_enabled('bookings'))json_response(['ok'=>false,'message'=>'This public module is currently unavailable.'],404);
require_once dirname(__DIR__) . '/portal/visitor-intelligence.php';
require_once dirname(__DIR__) . '/portal/events-calendar.php';
require_once dirname(__DIR__) . '/portal/appointments-booking.php';

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
$isJson = $jsonBody
    || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))
        === 'xmlhttprequest';
$data = $jsonBody
    ? json_decode((string)file_get_contents('php://input'), true)
    : $_POST;

if (!is_array($data)) {
    json_response([
        'ok' => false,
        'message' => 'Invalid booking request.',
    ], 400);
}

$action = trim((string)($data['action'] ?? 'create'));

try {
    if (!booking_schema_available()) {
        throw new RuntimeException(
            'Appointments & Booking is not installed.'
        );
    }

    if ($action === 'cancel') {
        $token = strtolower(trim((string)($data['token'] ?? '')));
        $appointment = booking_cancel_appointment($token);

        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        try {
            if ((int)($appointment['crm_contact_id'] ?? 0) > 0) {
                visitor_intelligence_attach_contact(
                    (int)$appointment['crm_contact_id'],
                    'appointment_cancelled',
                    [
                        'event_label' =>
                            (string)$appointment['booking_type_name'],
                        'page_path' => 'appointment.php',
                        'crm_opportunity_id' =>
                            (int)($appointment['crm_opportunity_id'] ?? 0)
                                ?: null,
                        'metadata' => [
                            'appointment_id' => (int)$appointment['id'],
                            'booking_type_id' =>
                                (int)$appointment['booking_type_id'],
                        ],
                    ]
                );
            }
        } catch (Throwable $exception) {
            error_log(
                'Booking cancellation attribution failed: '
                . $exception->getMessage()
            );
        }

        if ($isJson) {
            json_response([
                'ok' => true,
                'message' => 'Your appointment is cancelled.',
                'status' => 'cancelled',
            ]);
        }

        redirect(
            'appointment.php?token='
            . rawurlencode($token)
            . '&cancelled=1'
        );
    }

    if ($action === 'reschedule') {
        $token = strtolower(trim((string)($data['token'] ?? '')));
        $appointment = booking_appointment_by_token($token);
        $slotData = booking_parse_slot_token(
            (string)($data['slot_token'] ?? '')
        );

        if (!$appointment || !$slotData) {
            throw new RuntimeException(
                'The appointment or replacement time is invalid.'
            );
        }

        if (
            (int)$appointment['booking_type_id']
            !== (int)$slotData['type_id']
        ) {
            throw new RuntimeException(
                'The replacement time does not match this appointment.'
            );
        }

        $type = booking_type_by_id((int)$appointment['booking_type_id']);
        $slot = $type
            ? booking_slot_is_available(
                $type,
                $slotData['start_utc'],
                $slotData['timezone'],
                (int)$appointment['id']
            )
            : null;

        if (!$type || !$slot) {
            throw new RuntimeException(
                'That replacement time is no longer available.'
            );
        }

        $updated = booking_reschedule_appointment($token, $slot);

        if (!$updated) {
            throw new RuntimeException(
                'The appointment could not be rescheduled.'
            );
        }

        try {
            if ((int)($updated['crm_contact_id'] ?? 0) > 0) {
                visitor_intelligence_attach_contact(
                    (int)$updated['crm_contact_id'],
                    'appointment_rescheduled',
                    [
                        'event_label' =>
                            (string)$updated['booking_type_name'],
                        'page_path' => 'appointment.php',
                        'crm_opportunity_id' =>
                            (int)($updated['crm_opportunity_id'] ?? 0)
                                ?: null,
                        'metadata' => [
                            'appointment_id' => (int)$updated['id'],
                            'booking_type_id' =>
                                (int)$updated['booking_type_id'],
                            'start_at' => (string)$updated['start_at'],
                        ],
                    ]
                );
            }
        } catch (Throwable $exception) {
            error_log(
                'Booking reschedule attribution failed: '
                . $exception->getMessage()
            );
        }

        if ($isJson) {
            json_response([
                'ok' => true,
                'message' => 'Your appointment was rescheduled.',
                'confirmation_url' =>
                    'appointment.php?token=' . rawurlencode($token),
            ]);
        }

        redirect(
            'appointment.php?token='
            . rawurlencode($token)
            . '&rescheduled=1'
        );
    }

    if ($action !== 'create') {
        throw new RuntimeException('Unsupported booking action.');
    }

    if (!booking_settings()['enabled']) {
        throw new RuntimeException(
            'Online booking is currently unavailable.'
        );
    }

    $slotData = booking_parse_slot_token(
        (string)($data['slot_token'] ?? '')
    );

    if (!$slotData) {
        throw new RuntimeException(
            'The selected appointment time is invalid.'
        );
    }

    $type = booking_type_by_id((int)$slotData['type_id']);

    if (!$type || $type['status'] !== 'active') {
        throw new RuntimeException(
            'The selected appointment type is unavailable.'
        );
    }

    $email = strtolower(trim((string)($data['email'] ?? '')));
    $identity = $email . '|' . request_ip();

    if (rate_limit_exceeded('appointment_booking', $identity, 8, 3600)) {
        throw new RuntimeException(
            'Too many booking attempts. Please try again later.'
        );
    }

    $slot = booking_slot_is_available(
        $type,
        $slotData['start_utc'],
        $slotData['timezone']
    );

    if (!$slot) {
        throw new RuntimeException(
            'That appointment time is no longer available.'
        );
    }

    $result = booking_create_public(
        $type,
        $slot,
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
        'North Mountain Media booking request failed: '
        . $exception->getMessage()
    );

    if ($isJson) {
        json_response([
            'ok' => false,
            'message' => $exception->getMessage(),
        ], 422);
    }

    if (in_array($action, ['cancel','reschedule'], true)) {
        $token = rawurlencode(
            strtolower(trim((string)($data['token'] ?? '')))
        );
        redirect(
            'appointment.php?token=' . $token
            . '&booking_error='
            . rawurlencode($exception->getMessage())
        );
    }

    redirect(
        'booking.php?booking_error='
        . rawurlencode($exception->getMessage())
    );
}

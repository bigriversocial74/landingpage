<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/call-center.php';
require_once __DIR__ . '/crm-messages.php';

$user = require_role('admin');

if (!is_post()) {
    json_response([
        'ok' => false,
        'message' => 'POST is required.',
    ], 405);
}

if (!same_origin_request()) {
    json_response([
        'ok' => false,
        'message' => 'The request origin was not accepted.',
    ], 403);
}

verify_csrf();
enforce_authenticated_action_limit($user);

$action = input('action');

try {
    if ($action === 'list') {
        $contactId = int_input('contact_id');

        if ($contactId <= 0) {
            throw new RuntimeException('Select a CRM contact.');
        }

        $contactStatement = db()->prepare(
            'SELECT id,display_name
             FROM crm_contacts
             WHERE id=:contact_id
             LIMIT 1'
        );
        $contactStatement->execute(['contact_id' => $contactId]);
        $contact = $contactStatement->fetch();

        if (!$contact) {
            throw new RuntimeException('The CRM contact was not found.');
        }

        $items = array_map(
            static function (array $row): array {
                $mediaId = (int)($row['media_id'] ?? 0);
                $typeLabel = call_center_request_type_label($row);
                $transcript = trim((string)(
                    $row['media_transcript']
                    ?? $row['transcript_text']
                    ?? ''
                ));

                return [
                    'request_id' => (int)$row['id'],
                    'contact_id' => (int)$row['crm_contact_id'],
                    'type' => $typeLabel,
                    'subject' => trim((string)($row['subject'] ?? 'Message')),
                    'message' => trim((string)($row['message'] ?? '')),
                    'transcript' => $transcript,
                    'requested_at' => (string)($row['requested_at'] ?? ''),
                    'requested_at_label' => format_datetime(
                        $row['requested_at'] ?? null
                    ),
                    'request_status' => (string)($row['status'] ?? 'new'),
                    'request_status_label' => status_label(
                        (string)($row['status'] ?? 'new')
                    ),
                    'stage' => (string)($row['message_stage'] ?? 'new'),
                    'stage_label' => crm_message_stage_label(
                        (string)($row['message_stage'] ?? 'new')
                    ),
                    'listened_at' => (string)($row['listened_at'] ?? ''),
                    'listened_at_label' => format_datetime(
                        $row['listened_at'] ?? null
                    ),
                    'media_id' => $mediaId,
                    'media_url' => $mediaId > 0
                        ? app_url(
                            'portal/call-center-media.php?id=' . $mediaId
                        )
                        : '',
                    'duration_seconds' => (float)(
                        $row['duration_seconds'] ?? 0
                    ),
                    'record_url' => app_url(
                        'portal/admin.php?view=call-center&request='
                        . (int)$row['id']
                    ),
                ];
            },
            crm_contact_messages($contactId)
        );

        json_response([
            'ok' => true,
            'contact' => [
                'id' => (int)$contact['id'],
                'name' => (string)$contact['display_name'],
            ],
            'stage_options' => crm_message_stage_options(),
            'migration_ready' => crm_message_stage_columns_available(),
            'items' => $items,
        ]);
    }

    if ($action === 'update_stage') {
        $requestId = int_input('request_id');
        $contactId = int_input('contact_id');
        $stage = input('stage');
        $automatic = input('automatic') === '1';

        $result = crm_update_message_stage(
            $requestId,
            $contactId,
            $stage,
            (int)$user['id'],
            $automatic
        );

        json_response([
            'ok' => true,
            'result' => $result,
        ]);
    }

    throw new RuntimeException('The CRM message action is not supported.');
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/communications.php';

$user = current_user();

if (!$user || !in_array($user['role'], ['admin', 'client'], true)) {
    json_response([
        'ok' => false,
        'message' => 'Authentication required.',
    ], 401);
}

if (!communication_enabled()) {
    json_response([
        'ok' => false,
        'message' => 'Communications are disabled.',
    ], 503);
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

$action = input('action');
$threadId = int_input('thread_id');
$callId = int_input('call_id');
$durationSeconds = max(
    0,
    min(86400, (float)($_POST['duration_seconds'] ?? 0))
);

if (!in_array(
    $action,
    ['attachment', 'voice_note', 'call_recording'],
    true
)) {
    json_response([
        'ok' => false,
        'message' => 'Invalid communications upload action.',
    ], 422);
}

try {
    $thread = communication_require_thread($threadId, $user);

    if (
        !isset($_FILES['communication_file'])
        || !is_array($_FILES['communication_file'])
    ) {
        throw new RuntimeException('Select or record a file to upload.');
    }

    $upload = $_FILES['communication_file'];

    if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'The communication file upload did not complete successfully.'
        );
    }

    $config = communication_config();
    $maximum = match ($action) {
        'voice_note' => max(
            5 * 1024 * 1024,
            (int)($config['max_voice_note_bytes'] ?? 50 * 1024 * 1024)
        ),
        'call_recording' => max(
            10 * 1024 * 1024,
            (int)($config['max_call_recording_bytes'] ?? 200 * 1024 * 1024)
        ),
        default => max(
            5 * 1024 * 1024,
            (int)($config['max_attachment_bytes'] ?? 25 * 1024 * 1024)
        ),
    };

    $size = (int)$upload['size'];

    if ($size <= 0 || $size > $maximum) {
        throw new RuntimeException(
            'The file exceeds the upload limit of ' .
            format_bytes($maximum) .
            '.'
        );
    }

    $temporary = (string)$upload['tmp_name'];
    $originalName = basename((string)$upload['name']);
    $detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary)
        ?: 'application/octet-stream';
    $extension = strtolower(
        pathinfo($originalName, PATHINFO_EXTENSION)
    );

    $mimeExtensionMap = [
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'video/ogg' => 'ogv',
        'audio/mp4' => 'm4a',
        'video/mp4' => 'mp4',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
    ];

    if (
        $extension === ''
        && isset($mimeExtensionMap[$detectedMime])
    ) {
        $extension = $mimeExtensionMap[$detectedMime];
        $originalName = $action . '-' .
            gmdate('Ymd-His') . '.' . $extension;
    }

    $allowed = communication_allowed_extensions();

    if (!isset($allowed[$extension])) {
        throw new RuntimeException(
            'Unsupported communication file type: .' . $extension
        );
    }

    [$preferredMime, $mediaKind] = $allowed[$extension];

    if (str_starts_with($detectedMime, 'image/')) {
        $mediaKind = 'image';
        $preferredMime = $detectedMime;
    } elseif (str_starts_with($detectedMime, 'audio/')) {
        $mediaKind = 'audio';
        $preferredMime = $detectedMime;
    } elseif (str_starts_with($detectedMime, 'video/')) {
        $mediaKind = 'video';
        $preferredMime = $detectedMime;
    }

    if (
        in_array($action, ['voice_note', 'call_recording'], true)
        && in_array($detectedMime, ['audio/webm', 'video/webm'], true)
    ) {
        $mediaKind = 'audio';
        $preferredMime = 'audio/webm';
    }

    if (
        in_array($action, ['voice_note', 'call_recording'], true)
        && !in_array($mediaKind, ['audio', 'video'], true)
    ) {
        throw new RuntimeException(
            'Voice notes and call recordings must contain audio or video media.'
        );
    }

    $compatible = (
        $detectedMime === $preferredMime
        || $detectedMime === 'application/octet-stream'
        || $detectedMime === 'application/zip'
        || str_starts_with($detectedMime, $mediaKind . '/')
        || (
            $mediaKind === 'document'
            && in_array(
                $detectedMime,
                [
                    'application/pdf',
                    'application/msword',
                    'application/rtf',
                    'text/plain',
                ],
                true
            )
        )
        || (
            $mediaKind === 'data'
            && in_array(
                $detectedMime,
                [
                    'text/csv',
                    'application/json',
                    'application/xml',
                    'text/xml',
                    'application/zip',
                ],
                true
            )
        )
    );

    if (!$compatible) {
        throw new RuntimeException(
            'The uploaded file content does not match its extension.'
        );
    }

    if ($action === 'call_recording') {
        if ($user['role'] !== 'admin') {
            throw new RuntimeException(
                'Only the administrator recorder can store a consented call recording.'
            );
        }

        $call = communication_require_call($callId, $user);

        if ((int)$call['thread_id'] !== $threadId) {
            throw new RuntimeException(
                'The call recording does not belong to this conversation.'
            );
        }

        if (
            !in_array(
                $call['recording_status'],
                ['consented', 'recording'],
                true
            )
            || $call['initiator_recording_consent'] !== 'granted'
            || $call['recipient_recording_consent'] !== 'granted'
        ) {
            throw new RuntimeException(
                'The recording cannot be stored without both participants’ consent.'
            );
        }
    }

    $attachmentId = communication_create_attachment(
        $threadId,
        (int)$user['id'],
        $temporary,
        $originalName,
        $extension,
        $preferredMime,
        $mediaKind,
        $size,
        $durationSeconds > 0 ? $durationSeconds : null
    );

    $transcriptId = null;
    $messageType = 'file';
    $body = trim((string)($_POST['caption'] ?? ''));

    if ($action === 'voice_note') {
        $messageType = 'voice';
        $body = $body !== '' ? $body : 'Voice message';

        $statement = db()->prepare(
            'INSERT INTO communication_transcripts
                (thread_id, source_attachment_id, source_type, status)
             VALUES
                (:thread_id, :source_attachment_id, "voice_message", "draft")'
        );
        $statement->execute([
            'thread_id' => $threadId,
            'source_attachment_id' => $attachmentId,
        ]);
        $transcriptId = (int)db()->lastInsertId();
    }

    if ($action === 'call_recording') {
        $messageType = 'call_recording';
        $body = 'Consented call recording';

        $statement = db()->prepare(
            'INSERT INTO communication_transcripts
                (thread_id, source_attachment_id, call_id,
                 source_type, status)
             VALUES
                (:thread_id, :source_attachment_id, :call_id,
                 "call_recording", "draft")'
        );
        $statement->execute([
            'thread_id' => $threadId,
            'source_attachment_id' => $attachmentId,
            'call_id' => $callId,
        ]);
        $transcriptId = (int)db()->lastInsertId();

        db()->prepare(
            'UPDATE communication_calls
             SET recording_status = "available",
                 recording_attachment_id = :attachment_id
             WHERE id = :id'
        )->execute([
            'attachment_id' => $attachmentId,
            'id' => $callId,
        ]);
    }

    $messageId = communication_insert_message(
        $threadId,
        (int)$user['id'],
        (string)$user['role'],
        $messageType,
        $body !== '' ? $body : $originalName,
        $attachmentId,
        $callId > 0 ? $callId : null,
        $transcriptId
    );

    $uploadRecipientId = $user['role'] === 'admin'
        ? (int)$thread['client_user_id']
        : (int)($thread['assigned_admin_user_id'] ?: communication_default_admin_id());

    if ($uploadRecipientId > 0) {
        notification_create(
            $uploadRecipientId,
            $action === 'voice_note' ? 'message' : 'message',
            match ($action) {
                'voice_note' => 'New voice message',
                'call_recording' => 'Call recording available',
                default => 'New communication file',
            },
            $user['display_name'] . ' shared ' . $originalName . '.',
            'portal/' .
                ($user['role'] === 'admin' ? 'client.php' : 'admin.php') .
                '?view=communications&thread=' . $threadId,
            'communication_attachment',
            $attachmentId,
            $action === 'call_recording' ? 'high' : 'normal'
        );
    }

    communication_log_crm_activity(
        $threadId,
        $action === 'call_recording' ? 'call' : 'note',
        match ($action) {
            'voice_note' => 'Voice message shared',
            'call_recording' => 'Consented call recording stored',
            default => 'Communication file shared',
        },
        $originalName,
        $user['role'] === 'admin' ? (int)$user['id'] : null
    );

    log_activity(
        'communication_file_uploaded',
        'communication_attachment',
        $attachmentId,
        [
            'thread_id' => $threadId,
            'message_id' => $messageId,
            'action' => $action,
            'media_kind' => $mediaKind,
        ]
    );

    json_response([
        'ok' => true,
        'attachment_id' => $attachmentId,
        'message_id' => $messageId,
        'transcript_id' => $transcriptId,
    ]);
} catch (Throwable $exception) {
    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

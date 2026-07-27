<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/bootstrap.php';
if(!nmm_module_enabled('call_us'))json_response(['ok'=>false,'message'=>'This public module is currently unavailable.'],404);
require_once dirname(__DIR__) . '/portal/call-center.php';
require_once dirname(__DIR__) . '/portal/visitor-intelligence.php';

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!same_origin_request()) {
    json_response(['ok' => false, 'message' => 'Invalid request origin.'], 403);
}

verify_csrf();

$config = call_center_config();

if (!(bool)($config['voicemail_enabled'] ?? true)) {
    json_response([
        'ok' => false,
        'message' => 'Public voicemail is currently unavailable.',
    ], 503);
}

$maximumBytes = max(
    1024 * 1024,
    min(
        50 * 1024 * 1024,
        (int)($config['voicemail_max_bytes'] ?? 12 * 1024 * 1024)
    )
);
$maximumSeconds = max(
    15,
    min(
        600,
        (int)($config['voicemail_max_seconds'] ?? 180)
    )
);

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $maximumBytes + 262144) {
    json_response([
        'ok' => false,
        'message' => 'The voicemail recording exceeds the upload limit.',
    ], 413);
}

function public_voicemail_identity_email(
    string $name,
    string $email,
    string $phone,
    string $ip
): string {
    if ($email !== '') {
        return $email;
    }

    return 'public-call-' . substr(
        hash(
            'sha256',
            strtolower($name) . '|' . $phone . '|' . $ip
        ),
        0,
        24
    ) . '@local.invalid';
}

$pdo = null;
$storedMediaPath = null;

try {
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        json_response([
            'ok' => true,
            'message' => 'Your voicemail was received.',
        ]);
    }

    if (
        !isset($_FILES['voicemail'])
        || !is_array($_FILES['voicemail'])
    ) {
        throw new RuntimeException(
            'Record a voicemail before submitting.'
        );
    }

    $upload = $_FILES['voicemail'];

    if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'The voicemail recording did not upload successfully.'
        );
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $company = trim((string)($_POST['company'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = '';
    $durationSeconds = (float)($_POST['duration_seconds'] ?? 0);

    if ($name === '' || strlen($name) > 160) {
        throw new RuntimeException('Enter your name.');
    }

    if (
        $email !== ''
        && (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 190
        )
    ) {
        throw new RuntimeException(
            'Enter a valid email address or leave it blank.'
        );
    }

    if (
        strlen($phone) > 60
        || strlen($company) > 190
        || strlen($subject) > 190
        || strlen($message) > 8000
    ) {
        throw new RuntimeException(
            'One of the optional voicemail fields is too long.'
        );
    }

    if (
        !is_finite($durationSeconds)
        || $durationSeconds <= 0
        || $durationSeconds > $maximumSeconds + 3
    ) {
        throw new RuntimeException(
            'The voicemail duration is invalid.'
        );
    }

    $sizeBytes = (int)$upload['size'];

    if ($sizeBytes <= 0 || $sizeBytes > $maximumBytes) {
        throw new RuntimeException(
            'The voicemail recording exceeds the upload limit.'
        );
    }

    $originalName = basename(
        (string)($upload['name'] ?? 'voicemail.webm')
    );
    $extension = strtolower(
        pathinfo($originalName, PATHINFO_EXTENSION)
    );
    $allowed = call_center_media_allowed_extensions();

    if (!isset($allowed[$extension])) {
        throw new RuntimeException(
            'The browser produced an unsupported voicemail format.'
        );
    }

    $temporaryPath = (string)$upload['tmp_name'];
    $detectedMime = (
        new finfo(FILEINFO_MIME_TYPE)
    )->file($temporaryPath) ?: 'application/octet-stream';
    [$preferredMime] = $allowed[$extension];

    $compatibleMimes = array_unique([
        $preferredMime,
        'application/octet-stream',
        'video/webm',
        'video/mp4',
        'audio/x-wav',
        'application/ogg',
    ]);

    if (
        !in_array($detectedMime, $compatibleMimes, true)
        && !str_starts_with($detectedMime, 'audio/')
        && !(
            $extension === 'webm'
            && str_starts_with($detectedMime, 'video/')
        )
    ) {
        throw new RuntimeException(
            'The voicemail file content does not match its audio format.'
        );
    }

    $ip = request_ip();
    $security = nmm_config('security');
    $window = max(
        60,
        (int)($security['contact_window_seconds'] ?? 3600)
    );
    $ipLimit = max(
        1,
        (int)($config['public_ip_limit'] ?? 4)
    );
    $identityLimit = max(
        1,
        (int)($config['public_email_limit'] ?? 3)
    );
    $identityKey = $email !== ''
        ? $email
        : strtolower($name) . '|' . $phone;

    if (
        rate_limit_exceeded(
            'public_voicemail_ip',
            $ip,
            $ipLimit,
            $window
        )
        || rate_limit_exceeded(
            'public_voicemail_identity',
            $identityKey,
            $identityLimit,
            $window
        )
    ) {
        throw new RuntimeException(
            'Too many voicemail requests were submitted. Try again later.'
        );
    }

    $crmEmail = public_voicemail_identity_email(
        $name,
        $email,
        $phone,
        $ip
    );
    $subject = $subject !== ''
        ? $subject
        : 'Public voicemail';

    $pdo = db();
    $pdo->beginTransaction();

    $contactId = call_center_upsert_public_contact(
        $name,
        $crmEmail,
        $phone !== '' ? $phone : null,
        $company !== '' ? $company : null
    );
    $adminId = call_center_default_admin_id();

    $request = call_center_create_request([
        'source' => 'public',
        'request_type' => 'voicemail',
        'crm_contact_id' => $contactId,
        'assigned_admin_user_id' =>
            $adminId > 0 ? $adminId : null,
        'guest_name' => $name,
        'guest_email' => $email !== '' ? $email : null,
        'guest_phone' => $phone !== '' ? $phone : null,
        'guest_company' => $company !== '' ? $company : null,
        'subject' => $subject,
        'message' => $message !== '' ? $message : null,
        'priority' => 'high',
        'status' => 'voicemail',
        'disposition' => 'left_message',
        'queued_at' => gmdate('Y-m-d H:i:s'),
        'ip_address' => $ip,
        'user_agent' => substr(
            (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            0,
            500
        ),
    ]);
    $requestId = (int)$request['id'];

    $mediaId = call_center_store_media(
        $requestId,
        $temporaryPath,
        $originalName,
        $extension,
        $preferredMime,
        $sizeBytes,
        min($durationSeconds, (float)$maximumSeconds),
        'voicemail',
        null
    );
    $storedMedia = call_center_media($mediaId);
    $storedMediaPath = $storedMedia
        ? call_center_media_storage_path(
            (string)$storedMedia['stored_name']
        )
        : null;

    if (
        (bool)(
            $config['local_voicemail_transcription_enabled']
            ?? false
        )
    ) {
        db()->prepare(
            'UPDATE call_center_media
             SET transcript_status="queued",
                 transcription_source="local"
             WHERE id=:id'
        )->execute(['id' => $mediaId]);
    }

    db()->prepare(
        'UPDATE call_center_requests
         SET ended_at=UTC_TIMESTAMP(),
             duration_seconds=:duration_seconds,
             last_contact_at=UTC_TIMESTAMP()
         WHERE id=:id'
    )->execute([
        'duration_seconds' =>
            (int)round(min($durationSeconds, (float)$maximumSeconds)),
        'id' => $requestId,
    ]);

    db()->prepare(
        'INSERT INTO crm_activities
            (contact_id,activity_type,subject,body)
         VALUES
            (:contact_id,"call","Voicemail received",:body)'
    )->execute([
        'contact_id' => $contactId,
        'body' => $subject . (
            $message !== ''
                ? "\n\n" . $message
                : ''
        ),
    ]);

    $pdo->commit();

    call_center_event(
        $requestId,
        'public_voicemail_received',
        null,
        $message !== '' ? $message : null,
        [
            'media_id' => $mediaId,
            'duration_seconds' => $durationSeconds,
            'guest_name' => $name,
            'guest_email' => $email !== '' ? $email : null,
        ]
    );

    notification_create_for_role(
        'admin',
        'call',
        'New voicemail from ' . $name,
        $subject . (
            $message !== ''
                ? ' — ' . $message
                : ''
        ),
        'portal/admin.php?view=call-center&request=' . $requestId,
        'call_center_request',
        $requestId,
        'urgent'
    );

    call_center_refresh_contact_stats($contactId);

    try {
        visitor_intelligence_attach_contact(
            $contactId,
            'voicemail_submitted',
            [
                'event_label' => $subject,
                'duration_seconds' => (int)round(
                    min(
                        $durationSeconds,
                        (float)$maximumSeconds
                    )
                ),
                'metadata' => [
                    'request_id' => $requestId,
                    'media_id' => $mediaId,
                ],
            ]
        );
    } catch (Throwable $trackingException) {
        error_log(
            'North Mountain Media voicemail attribution failed: '
            . $trackingException->getMessage()
        );
    }

    json_response([
        'ok' => true,
        'request_id' => $requestId,
        'media_id' => $mediaId,
        'message' =>
            'Your voicemail was delivered to Dave’s Call Center.',
    ]);
} catch (Throwable $exception) {
    if (
        $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    if (
        is_string($storedMediaPath)
        && $storedMediaPath !== ''
        && is_file($storedMediaPath)
    ) {
        @unlink($storedMediaPath);
    }

    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

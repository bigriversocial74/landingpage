<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/call-center.php';

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

if (
    !isset($_FILES['greeting'])
    || !is_array($_FILES['greeting'])
) {
    json_response([
        'ok' => false,
        'message' => 'Record a voicemail greeting before saving.',
    ], 422);
}

$upload = $_FILES['greeting'];

if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
    json_response([
        'ok' => false,
        'message' => 'The voicemail greeting did not upload successfully.',
    ], 422);
}

$maximumBytes = 12 * 1024 * 1024;
$maximumSeconds = 120;
$sizeBytes = (int)$upload['size'];
$durationSeconds = (float)($_POST['duration_seconds'] ?? 0);

if ($sizeBytes <= 0 || $sizeBytes > $maximumBytes) {
    json_response([
        'ok' => false,
        'message' => 'The voicemail greeting exceeds the 12 MB limit.',
    ], 422);
}

if (
    !is_finite($durationSeconds)
    || $durationSeconds <= 0
    || $durationSeconds > $maximumSeconds + 3
) {
    json_response([
        'ok' => false,
        'message' => 'The voicemail greeting duration is invalid.',
    ], 422);
}

$originalName = basename(
    (string)($upload['name'] ?? 'voicemail-greeting.webm')
);
$extension = strtolower(
    pathinfo($originalName, PATHINFO_EXTENSION)
);
$allowed = call_center_greeting_allowed_extensions();

if (!isset($allowed[$extension])) {
    json_response([
        'ok' => false,
        'message' => 'Unsupported voicemail greeting format.',
    ], 422);
}

$temporaryPath = (string)$upload['tmp_name'];
$detectedMime = (
    new finfo(FILEINFO_MIME_TYPE)
)->file($temporaryPath) ?: 'application/octet-stream';
$preferredMime = $allowed[$extension];

if (
    !str_starts_with($detectedMime, 'audio/')
    && !(
        $extension === 'webm'
        && str_starts_with($detectedMime, 'video/')
    )
    && $detectedMime !== 'application/octet-stream'
) {
    json_response([
        'ok' => false,
        'message' => 'The greeting file content is not valid audio.',
    ], 422);
}

$storedName = bin2hex(random_bytes(24)) . '.' . $extension;
$destination = call_center_greeting_storage_path($storedName);

if (!move_uploaded_file($temporaryPath, $destination)) {
    json_response([
        'ok' => false,
        'message' => 'The server could not store the voicemail greeting.',
    ], 500);
}

chmod($destination, 0640);
$sha256 = hash_file('sha256', $destination);

if ($sha256 === false) {
    @unlink($destination);
    json_response([
        'ok' => false,
        'message' => 'The server could not verify the voicemail greeting.',
    ], 500);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $oldStatement = $pdo->query(
        'SELECT stored_name
         FROM call_center_greetings
         WHERE is_active=1'
    );
    $oldStoredNames = array_values(array_filter(array_map(
        'strval',
        $oldStatement->fetchAll(PDO::FETCH_COLUMN)
    )));

    $pdo->exec(
        'UPDATE call_center_greetings
         SET is_active=0'
    );

    $statement = $pdo->prepare(
        'INSERT INTO call_center_greetings
            (admin_user_id,original_name,stored_name,extension,mime_type,
             size_bytes,duration_seconds,sha256,is_active)
         VALUES
            (:admin_user_id,:original_name,:stored_name,:extension,:mime_type,
             :size_bytes,:duration_seconds,:sha256,1)'
    );
    $statement->execute([
        'admin_user_id' => $user['id'],
        'original_name' => substr($originalName, 0, 255),
        'stored_name' => $storedName,
        'extension' => $extension,
        'mime_type' => $preferredMime,
        'size_bytes' => $sizeBytes,
        'duration_seconds' => min(
            $durationSeconds,
            (float)$maximumSeconds
        ),
        'sha256' => $sha256,
    ]);

    $greetingId = (int)$pdo->lastInsertId();
    $pdo->commit();

    foreach ($oldStoredNames as $oldStoredName) {
        $oldPath = call_center_greeting_storage_path($oldStoredName);

        if (
            $oldStoredName !== $storedName
            && is_file($oldPath)
        ) {
            @unlink($oldPath);
        }
    }

    log_activity(
        'call_center_greeting_updated',
        'call_center_greeting',
        $greetingId,
        [
            'duration_seconds' => $durationSeconds,
            'mime_type' => $preferredMime,
        ]
    );

    json_response([
        'ok' => true,
        'greeting' => call_center_active_greeting(),
        'message' => 'The active voicemail greeting was updated.',
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    @unlink($destination);

    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 422);
}

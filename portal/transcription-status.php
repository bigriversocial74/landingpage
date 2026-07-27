<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = require_role('admin');
$jobId = query_int('id');

if ($jobId <= 0) {
    json_response([
        'ok' => false,
        'message' => 'Transcription job not found.',
    ], 404);
}

$statement = db()->prepare(
    'SELECT id, asset_id, status, model, attempt_count, max_attempts,
            error_message, queued_at, started_at, completed_at, updated_at
     FROM knowledge_transcription_jobs
     WHERE id = :id
     LIMIT 1'
);
$statement->execute(['id' => $jobId]);
$job = $statement->fetch();

if (!$job) {
    json_response([
        'ok' => false,
        'message' => 'Transcription job not found.',
    ], 404);
}

json_response([
    'ok' => true,
    'job' => [
        'id' => (int)$job['id'],
        'asset_id' => (int)$job['asset_id'],
        'status' => (string)$job['status'],
        'model' => (string)$job['model'],
        'attempt_count' => (int)$job['attempt_count'],
        'max_attempts' => (int)$job['max_attempts'],
        'error_message' => $job['error_message'],
        'queued_at' => $job['queued_at'],
        'started_at' => $job['started_at'],
        'completed_at' => $job['completed_at'],
        'updated_at' => $job['updated_at'],
    ],
]);

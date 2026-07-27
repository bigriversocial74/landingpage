<?php
declare(strict_types=1);

require_once __DIR__ . '/knowledge-assets.php';

function transcription_config(): array
{
    return nmm_config('transcription');
}

function transcription_enabled(): bool
{
    $config = transcription_config();

    return (bool)($config['enabled'] ?? false)
        && trim((string)($config['api_key'] ?? '')) !== ''
        && function_exists('curl_init');
}

function transcription_supported_asset(array $asset): bool
{
    return in_array(
        (string)($asset['media_kind'] ?? ''),
        ['audio', 'video'],
        true
    );
}

function transcription_latest_job(int $assetId): ?array
{
    $statement = db()->prepare(
        'SELECT j.*,
                requester.display_name AS requested_by_name,
                reviewer.display_name AS reviewed_by_name
         FROM knowledge_transcription_jobs j
         JOIN users requester ON requester.id = j.requested_by
         LEFT JOIN users reviewer ON reviewer.id = j.reviewed_by
         WHERE j.asset_id = :asset_id
         ORDER BY j.id DESC
         LIMIT 1'
    );
    $statement->execute(['asset_id' => $assetId]);
    $job = $statement->fetch();

    return $job ?: null;
}

function transcription_queue(
    int $assetId,
    int $requestedBy,
    bool $speakerDiarization = false,
    ?string $language = null,
    ?string $prompt = null,
    bool $force = false
): int {
    $assetStatement = db()->prepare(
        'SELECT *
         FROM knowledge_assets
         WHERE id = :id
         LIMIT 1'
    );
    $assetStatement->execute(['id' => $assetId]);
    $asset = $assetStatement->fetch();

    if (!$asset || !transcription_supported_asset($asset)) {
        throw new RuntimeException(
            'Automatic transcription is available only for audio and video assets.'
        );
    }

    $config = transcription_config();

    if (!(bool)($config['enabled'] ?? false)) {
        throw new RuntimeException(
            'Automatic transcription is disabled in config.php.'
        );
    }

    if (trim((string)($config['api_key'] ?? '')) === '') {
        throw new RuntimeException(
            'Add OPENAI_API_KEY to the server environment or transcription.api_key in config.php.'
        );
    }

    if (!$force) {
        $existing = db()->prepare(
            'SELECT id
             FROM knowledge_transcription_jobs
             WHERE asset_id = :asset_id
               AND status IN ("queued", "processing", "review")
             ORDER BY id DESC
             LIMIT 1'
        );
        $existing->execute(['asset_id' => $assetId]);
        $existingId = (int)($existing->fetchColumn() ?: 0);

        if ($existingId > 0) {
            return $existingId;
        }
    }

    $model = $speakerDiarization
        ? (string)($config['diarization_model'] ?? 'gpt-4o-transcribe-diarize')
        : (string)($config['model'] ?? 'gpt-4o-mini-transcribe');

    $language = $language ?? trim((string)($config['language'] ?? ''));
    $prompt = $prompt ?? trim((string)($config['prompt'] ?? ''));
    $maxAttempts = max(1, min(10, (int)($config['max_attempts'] ?? 3)));

    $statement = db()->prepare(
        'INSERT INTO knowledge_transcription_jobs
            (asset_id, status, provider, model, language, prompt,
             speaker_diarization, max_attempts, requested_by)
         VALUES
            (:asset_id, "queued", "openai", :model, :language, :prompt,
             :speaker_diarization, :max_attempts, :requested_by)'
    );
    $statement->execute([
        'asset_id' => $assetId,
        'model' => $model,
        'language' => $language !== '' ? $language : null,
        'prompt' => $prompt !== '' ? $prompt : null,
        'speaker_diarization' => $speakerDiarization ? 1 : 0,
        'max_attempts' => $maxAttempts,
        'requested_by' => $requestedBy,
    ]);

    $jobId = (int)db()->lastInsertId();

    log_activity(
        'knowledge_transcription_queued',
        'knowledge_transcription_job',
        $jobId,
        [
            'asset_id' => $assetId,
            'model' => $model,
            'speaker_diarization' => $speakerDiarization,
        ]
    );

    return $jobId;
}

function transcription_resolve_binary(string $configured): ?string
{
    $configured = trim($configured);

    if ($configured === '') {
        return null;
    }

    if (
        str_contains($configured, DIRECTORY_SEPARATOR)
        && is_file($configured)
        && is_executable($configured)
    ) {
        return $configured;
    }

    if (!knowledge_shell_allowed('shell_exec')) {
        return null;
    }

    $resolved = trim(
        (string)@shell_exec(
            'command -v ' . escapeshellarg($configured) . ' 2>/dev/null'
        )
    );

    return $resolved !== '' ? $resolved : null;
}

function transcription_create_workspace(int $jobId): string
{
    $base = NMM_ROOT . '/storage/transcription-temp';

    if (!is_dir($base) && !mkdir($base, 0770, true) && !is_dir($base)) {
        throw new RuntimeException(
            'Could not create the transcription temporary directory.'
        );
    }

    $workspace = $base . '/job-' . $jobId . '-' . bin2hex(random_bytes(8));

    if (!mkdir($workspace, 0770, true) && !is_dir($workspace)) {
        throw new RuntimeException(
            'Could not create a temporary transcription workspace.'
        );
    }

    return $workspace;
}

function transcription_remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            transcription_remove_directory($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($directory);
}

function transcription_prepare_chunks(
    array $asset,
    int $jobId
): array {
    $source = knowledge_storage_path((string)$asset['stored_name']);

    if (!is_file($source)) {
        throw new RuntimeException(
            'The source audio or video file is missing.'
        );
    }

    $config = transcription_config();
    $maximum = max(
        5 * 1024 * 1024,
        (int)($config['max_api_file_bytes'] ?? 24 * 1024 * 1024)
    );
    $extension = strtolower((string)$asset['extension']);
    $directExtensions = [
        'flac', 'mp3', 'mp4', 'mpeg', 'mpga',
        'm4a', 'ogg', 'wav', 'webm',
    ];
    $size = filesize($source);

    if (
        $size !== false
        && $size <= $maximum
        && in_array($extension, $directExtensions, true)
    ) {
        return [
            'workspace' => null,
            'chunks' => [[
                'path' => $source,
                'offset_seconds' => 0,
                'original' => true,
            ]],
        ];
    }

    $ffmpeg = transcription_resolve_binary(
        (string)($config['ffmpeg_path'] ?? 'ffmpeg')
    );

    if ($ffmpeg === null) {
        throw new RuntimeException(
            'This file must be converted or split, but FFmpeg is not available. Install FFmpeg or upload a supported file below the configured API size limit.'
        );
    }

    $workspace = transcription_create_workspace($jobId);
    $chunkSeconds = max(
        300,
        min(1800, (int)($config['chunk_seconds'] ?? 900))
    );
    $outputPattern = $workspace . '/chunk-%04d.mp3';

    $command = escapeshellarg($ffmpeg)
        . ' -hide_banner -loglevel error -y'
        . ' -i ' . escapeshellarg($source)
        . ' -map 0:a:0 -vn -ac 1 -ar 16000 -b:a 64k'
        . ' -f segment -segment_time ' . $chunkSeconds
        . ' -reset_timestamps 1 '
        . escapeshellarg($outputPattern)
        . ' 2>&1';

    $output = [];
    $exitCode = 1;

    if (!function_exists('exec')) {
        transcription_remove_directory($workspace);
        throw new RuntimeException(
            'PHP exec() is required for FFmpeg conversion and chunking.'
        );
    }

    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        transcription_remove_directory($workspace);
        throw new RuntimeException(
            'FFmpeg could not prepare the media: ' .
            trim(implode("\n", array_slice($output, -8)))
        );
    }

    $files = glob($workspace . '/chunk-*.mp3') ?: [];
    sort($files, SORT_NATURAL);

    if (!$files) {
        transcription_remove_directory($workspace);
        throw new RuntimeException(
            'FFmpeg did not create any transcription chunks.'
        );
    }

    $chunks = [];

    foreach ($files as $index => $file) {
        $chunkSize = filesize($file);

        if ($chunkSize === false || $chunkSize <= 0) {
            transcription_remove_directory($workspace);
            throw new RuntimeException(
                'One of the generated transcription chunks is empty.'
            );
        }

        if ($chunkSize > $maximum) {
            transcription_remove_directory($workspace);
            throw new RuntimeException(
                'A generated transcription chunk still exceeds the configured API size limit. Reduce transcription.chunk_seconds.'
            );
        }

        $chunks[] = [
            'path' => $file,
            'offset_seconds' => $index * $chunkSeconds,
            'original' => false,
        ];
    }

    return [
        'workspace' => $workspace,
        'chunks' => $chunks,
    ];
}

function transcription_format_timestamp(float $seconds): string
{
    $seconds = max(0, (int)round($seconds));
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;

    return sprintf(
        '%02d:%02d:%02d',
        $hours,
        $minutes,
        $remaining
    );
}

function transcription_call_openai(
    string $filePath,
    array $job
): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'The PHP cURL extension is required for automatic transcription.'
        );
    }

    $config = transcription_config();
    $apiKey = trim((string)($config['api_key'] ?? ''));

    if ($apiKey === '') {
        throw new RuntimeException(
            'The OpenAI API key is not configured.'
        );
    }

    $base = rtrim(
        (string)($config['api_base'] ?? 'https://api.openai.com/v1'),
        '/'
    );
    $url = $base . '/audio/transcriptions';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath)
        ?: 'application/octet-stream';

    $fields = [
        'file' => new CURLFile(
            $filePath,
            $mime,
            basename($filePath)
        ),
        'model' => (string)$job['model'],
    ];

    if ((int)$job['speaker_diarization'] === 1) {
        $fields['response_format'] = 'diarized_json';
        $fields['chunking_strategy'] = 'auto';
    } else {
        $fields['response_format'] = 'json';

        if (!empty($job['language'])) {
            $fields['language'] = (string)$job['language'];
        }

        if (!empty($job['prompt'])) {
            $fields['prompt'] = (string)$job['prompt'];
        }
    }

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => max(
            120,
            (int)($config['request_timeout_seconds'] ?? 900)
        ),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ]);

    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException(
            'The transcription request failed: ' . $curlError
        );
    }

    try {
        $decoded = json_decode(
            (string)$body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (Throwable) {
        throw new RuntimeException(
            'The transcription service returned an invalid response.'
        );
    }

    if ($status < 200 || $status >= 300) {
        $message = (string)(
            $decoded['error']['message']
            ?? $decoded['message']
            ?? 'Unknown transcription API error.'
        );

        throw new RuntimeException(
            'Transcription API error (' . $status . '): ' . $message
        );
    }

    if (!is_array($decoded) || trim((string)($decoded['text'] ?? '')) === '') {
        throw new RuntimeException(
            'The transcription service returned no transcript text.'
        );
    }

    return $decoded;
}

function transcription_merge_results(
    array $results
): array {
    $paragraphs = [];
    $segments = [];
    $usage = [];
    $responses = [];

    foreach ($results as $index => $result) {
        $offset = (float)($result['offset_seconds'] ?? 0);
        $response = $result['response'] ?? [];
        $responseMeta = $response;
        unset($responseMeta['text'], $responseMeta['segments']);
        $responses[] = $responseMeta;

        if (!empty($response['usage'])) {
            $usage[] = $response['usage'];
        }

        $responseSegments = is_array($response['segments'] ?? null)
            ? $response['segments']
            : [];

        if ($responseSegments) {
            $currentSpeaker = null;
            $currentText = '';
            $currentStart = 0.0;
            $currentEnd = 0.0;

            foreach ($responseSegments as $segment) {
                $speaker = trim((string)($segment['speaker'] ?? 'Speaker'));
                $text = knowledge_clean_text((string)($segment['text'] ?? ''));

                if ($text === '') {
                    continue;
                }

                $start = $offset + (float)($segment['start'] ?? 0);
                $end = $offset + (float)($segment['end'] ?? $start);

                $segments[] = [
                    'speaker' => $speaker,
                    'start' => $start,
                    'end' => $end,
                    'text' => $text,
                ];

                if ($currentSpeaker === $speaker && $start - $currentEnd < 2.5) {
                    $currentText .= ' ' . $text;
                    $currentEnd = $end;
                    continue;
                }

                if ($currentText !== '') {
                    $paragraphs[] =
                        '[' . transcription_format_timestamp($currentStart) . '] '
                        . $currentSpeaker . ': '
                        . knowledge_clean_text($currentText);
                }

                $currentSpeaker = $speaker;
                $currentText = $text;
                $currentStart = $start;
                $currentEnd = $end;
            }

            if ($currentText !== '') {
                $paragraphs[] =
                    '[' . transcription_format_timestamp($currentStart) . '] '
                    . $currentSpeaker . ': '
                    . knowledge_clean_text($currentText);
            }
        } else {
            $text = knowledge_clean_text((string)($response['text'] ?? ''));

            if ($text !== '') {
                $label = count($results) > 1
                    ? 'Part ' . ($index + 1)
                    : null;

                $paragraphs[] = $label
                    ? '[' . transcription_format_timestamp($offset) . '] '
                        . $label . "\n" . $text
                    : $text;
            }
        }
    }

    return [
        'text' => knowledge_clean_text(implode("\n\n", $paragraphs)),
        'segments' => $segments,
        'usage' => $usage,
        'responses' => $responses,
    ];
}

function transcription_claim_next_job(
    ?int $specificJobId = null,
    bool $ignoreSchedule = false
): ?array
{
    $pdo = db();

    $pdo->beginTransaction();

    try {
        $pdo->exec(
            'UPDATE knowledge_transcription_jobs
             SET status = "failed",
                 completed_at = UTC_TIMESTAMP(),
                 error_message = "Processing lock expired after the maximum attempt count."
             WHERE status = "processing"
               AND started_at < (UTC_TIMESTAMP() - INTERVAL 45 MINUTE)
               AND attempt_count >= max_attempts'
        );

        $pdo->exec(
            'UPDATE knowledge_transcription_jobs
             SET status = "queued",
                 started_at = NULL,
                 next_attempt_at = UTC_TIMESTAMP(),
                 error_message = "Recovered after a stale processing lock."
             WHERE status = "processing"
               AND started_at < (UTC_TIMESTAMP() - INTERVAL 45 MINUTE)
               AND attempt_count < max_attempts'
        );

        $sql = 'SELECT j.*, a.original_name, a.stored_name, a.extension,
                       a.mime_type, a.media_kind, a.title, a.category
                FROM knowledge_transcription_jobs j
                JOIN knowledge_assets a ON a.id = j.asset_id
                WHERE j.status = "queued"
                  AND j.attempt_count < j.max_attempts';

        if (!$ignoreSchedule) {
            $sql .= ' AND j.next_attempt_at <= UTC_TIMESTAMP()';
        }

        $parameters = [];

        if ($specificJobId !== null) {
            $sql .= ' AND j.id = :job_id';
            $parameters['job_id'] = $specificJobId;
        }

        $sql .= ' ORDER BY j.queued_at ASC, j.id ASC LIMIT 1 FOR UPDATE';

        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        $job = $statement->fetch();

        if (!$job) {
            $pdo->commit();
            return null;
        }

        $update = $pdo->prepare(
            'UPDATE knowledge_transcription_jobs
             SET status = "processing",
                 started_at = UTC_TIMESTAMP(),
                 error_message = NULL,
                 attempt_count = attempt_count + 1
             WHERE id = :id'
        );
        $update->execute(['id' => $job['id']]);

        $pdo->commit();

        $job['status'] = 'processing';
        $job['attempt_count'] = (int)$job['attempt_count'] + 1;

        return $job;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function transcription_process_job(array $job): array
{
    $workspace = null;

    try {
        $asset = [
            'stored_name' => $job['stored_name'],
            'extension' => $job['extension'],
            'media_kind' => $job['media_kind'],
        ];

        $prepared = transcription_prepare_chunks(
            $asset,
            (int)$job['id']
        );
        $workspace = $prepared['workspace'];

        $results = [];

        foreach ($prepared['chunks'] as $chunk) {
            $results[] = [
                'offset_seconds' => $chunk['offset_seconds'],
                'response' => transcription_call_openai(
                    (string)$chunk['path'],
                    $job
                ),
            ];
        }

        $merged = transcription_merge_results($results);

        if ($merged['text'] === '') {
            throw new RuntimeException(
                'The transcription completed without usable text.'
            );
        }

        $summary = knowledge_auto_summary(
            $merged['text'],
            (string)$job['title']
        );
        $keywords = implode(
            ', ',
            knowledge_auto_keywords(
                $merged['text'],
                (string)$job['title']
            )
        );

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $jobUpdate = $pdo->prepare(
                'UPDATE knowledge_transcription_jobs
                 SET status = "review",
                     raw_transcript_text = :raw_transcript_text,
                     reviewed_transcript_text = :reviewed_transcript_text,
                     segments_json = :segments_json,
                     usage_json = :usage_json,
                     response_json = :response_json,
                     error_message = NULL,
                     completed_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            );
            $jobUpdate->execute([
                'raw_transcript_text' => $merged['text'],
                'reviewed_transcript_text' => $merged['text'],
                'segments_json' => $merged['segments']
                    ? json_encode(
                        $merged['segments'],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    )
                    : null,
                'usage_json' => $merged['usage']
                    ? json_encode(
                        $merged['usage'],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    )
                    : null,
                'response_json' => json_encode(
                    $merged['responses'],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
                'id' => $job['id'],
            ]);

            $assetUpdate = $pdo->prepare(
                'UPDATE knowledge_assets
                 SET extracted_text = :extracted_text,
                     extraction_method = :extraction_method,
                     extraction_status = "ready",
                     extraction_error = NULL,
                     summary = :summary,
                     keywords = :keywords
                 WHERE id = :asset_id'
            );
            $assetUpdate->execute([
                'extracted_text' => $merged['text'],
                'extraction_method' => 'openai:' . $job['model'],
                'summary' => $summary,
                'keywords' => $keywords,
                'asset_id' => $job['asset_id'],
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        log_activity(
            'knowledge_transcription_completed',
            'knowledge_transcription_job',
            (int)$job['id'],
            [
                'asset_id' => (int)$job['asset_id'],
                'model' => (string)$job['model'],
                'chunks' => count($results),
                'characters' => strlen($merged['text']),
            ]
        );

        return [
            'ok' => true,
            'job_id' => (int)$job['id'],
            'asset_id' => (int)$job['asset_id'],
            'status' => 'review',
            'characters' => strlen($merged['text']),
            'chunks' => count($results),
        ];
    } catch (Throwable $exception) {
        $retry = (int)$job['attempt_count'] < (int)$job['max_attempts'];
        $retryDelayMinutes = max(
            2,
            min(30, (int)$job['attempt_count'] * 5)
        );
        $nextAttemptAt = gmdate(
            'Y-m-d H:i:s',
            time() + ($retryDelayMinutes * 60)
        );

        db()->prepare(
            'UPDATE knowledge_transcription_jobs
             SET status = :status,
                 error_message = :error_message,
                 next_attempt_at = :next_attempt_at,
                 completed_at = CASE WHEN :status_value = "failed"
                                     THEN UTC_TIMESTAMP()
                                     ELSE completed_at END
             WHERE id = :id'
        )->execute([
            'status' => $retry ? 'queued' : 'failed',
            'status_value' => $retry ? 'queued' : 'failed',
            'error_message' => substr($exception->getMessage(), 0, 8000),
            'next_attempt_at' => $retry
                ? $nextAttemptAt
                : gmdate('Y-m-d H:i:s'),
            'id' => $job['id'],
        ]);

        log_activity(
            'knowledge_transcription_failed',
            'knowledge_transcription_job',
            (int)$job['id'],
            [
                'asset_id' => (int)$job['asset_id'],
                'retry_queued' => $retry,
                'error' => $exception->getMessage(),
            ]
        );

        return [
            'ok' => false,
            'job_id' => (int)$job['id'],
            'asset_id' => (int)$job['asset_id'],
            'status' => $retry ? 'queued' : 'failed',
            'error' => $exception->getMessage(),
        ];
    } finally {
        if (is_string($workspace) && $workspace !== '') {
            transcription_remove_directory($workspace);
        }
    }
}

function transcription_run_queue(
    int $limit = 1,
    ?int $specificJobId = null
): array {
    $limit = max(1, min(10, $limit));
    $results = [];

    for ($index = 0; $index < $limit; $index++) {
        $job = transcription_claim_next_job(
            $index === 0 ? $specificJobId : null,
            $specificJobId !== null
        );

        if (!$job) {
            break;
        }

        $results[] = transcription_process_job($job);

        if ($specificJobId !== null) {
            break;
        }
    }

    return $results;
}

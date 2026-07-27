# Automatic MP3/MP4 Transcription Setup

Build: `20260726-auto-transcription-v17`

## What the workflow does

1. An administrator uploads an audio or video file in **Admin → Knowledge Base**.
2. The site creates a transcription job automatically when transcription is configured.
3. The job moves through:
   - Queued
   - Processing
   - Review
   - Approved
4. The raw machine transcript is preserved.
5. The administrator edits a separate reviewed transcript.
6. **Approve and publish to chat** saves the reviewed transcript into the knowledge base and attaches the original audio/video player to matching chat responses.

The site never sends an unreviewed transcript into public chat automatically.

## 1. Import the migration

For an existing v16 installation, import:

`database/automatic_transcription_v17.sql`

New installations can use:

`database/north_mountain_portal.sql`

## 2. Configure the API key

Prefer a server environment variable:

`OPENAI_API_KEY`

The example configuration already reads it:

```php
'api_key' => getenv('OPENAI_API_KEY') ?: '',
```

When the hosting environment cannot provide environment variables, place the key in the private
`config.php` file. Never place it in JavaScript, HTML, the knowledge JSON, or a database setting
that client-side code can read.

## 3. Configure transcription

The new `transcription` section in `config.php` includes:

```php
'transcription' => [
    'enabled' => true,
    'api_key' => getenv('OPENAI_API_KEY') ?: '',
    'api_base' => 'https://api.openai.com/v1',
    'model' => 'gpt-4o-mini-transcribe',
    'diarization_model' => 'gpt-4o-transcribe-diarize',
    'language' => 'en',
    'prompt' => 'North Mountain Media, David Evans, Microgifter, Homestead, CRM, ecommerce',
    'auto_queue_on_upload' => true,
    'worker_token' => 'replace-with-a-long-random-transcription-worker-token',
    'max_api_file_bytes' => 24 * 1024 * 1024,
    'chunk_seconds' => 15 * 60,
    'request_timeout_seconds' => 15 * 60,
    'max_jobs_per_run' => 2,
    'max_attempts' => 3,
    'ffmpeg_path' => 'ffmpeg',
    'ffprobe_path' => 'ffprobe',
],
```

Replace `worker_token` with a long private random value.

## 4. Server requirements

Required for all automatic transcription:

- PHP 8.1+
- PHP cURL
- PHP Fileinfo
- PDO MySQL
- HTTPS outbound requests to the transcription API

Required for long recordings, MOV/M4V/AAC/OGV, or files over the configured per-request limit:

- FFmpeg
- PHP `exec()` enabled

FFmpeg converts media to mono 16 kHz MP3 and creates conservative 15-minute chunks.

## 5. Configure the worker

### Recommended: PHP CLI cron

Run once every minute:

```cron
* * * * * /usr/bin/php /absolute/path/to/transcription-worker.php --limit=2 >/dev/null 2>&1
```

Replace `/absolute/path/to/` with the real hosting path.

### cPanel-style web cron fallback

Use a private request header:

```cron
* * * * * /usr/bin/curl -fsS -H "X-Transcription-Token: YOUR_PRIVATE_WORKER_TOKEN" "https://your-domain.com/transcription-worker.php?limit=2" >/dev/null 2>&1
```

The CLI worker is preferred because the worker token does not pass through a web-server access log.

## 6. Test the first recording

1. Sign in as an administrator.
2. Open **Knowledge Base**.
3. Confirm the diagnostics show:
   - API transcription: Ready
   - FFmpeg: Available, when needed
   - Cron worker: Token configured
4. Upload a short MP3.
5. Confirm the status becomes **Queued**.
6. Wait for cron, or click **Process now**.
7. Confirm the status becomes **Review**.
8. Compare the raw transcript with the editable reviewed transcript.
9. Correct names, brands, punctuation, and speaker labels.
10. Click **Approve and publish to chat**.
11. Ask the public chat about a distinctive subject from the recording.
12. Confirm the answer includes the transcript source and audio player.

## Processing behavior

- Supported small files can be sent directly.
- Large or unsupported media is converted and split with FFmpeg.
- Failed jobs retry automatically up to the configured maximum.
- A processing lock older than 45 minutes is recovered.
- The administrator page polls the job status every eight seconds.
- The API response and usage metadata remain server-side.
- The raw and reviewed transcripts are stored separately.
- Publishing always uses the reviewed transcript.

## Troubleshooting

### Status remains Queued

The cron worker is not running, or the API key is unavailable to the PHP process. Use **Process now**
to test the request from the administrator page.

### FFmpeg unavailable

Set `ffmpeg_path` to the host's absolute FFmpeg path, such as `/usr/bin/ffmpeg`. Small supported
files under the configured limit can still transcribe directly.

### PHP cURL missing

Enable the PHP cURL extension through the hosting control panel or ask the host to enable it.

### Upload fails before queuing

Increase both PHP limits above the largest intended upload:

```ini
upload_max_filesize = 100M
post_max_size = 110M
max_execution_time = 900
```

### API request times out

Increase `request_timeout_seconds`, reduce `chunk_seconds`, and run the worker from PHP CLI rather
than a browser request.

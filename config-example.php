<?php
declare(strict_types=1);

/**
 * Copy this file to config.php and enter deployment-specific values.
 * Never commit or publicly expose config.php.
 */
return [
    'app' => [
        'name' => 'North Mountain Media',
        'base_url' => '', // Leave blank during setup; add the final HTTPS URL after installation.
        'timezone' => 'America/Phoenix',
        'session_name' => 'nmm_portal',
        'setup_token' => 'replace-with-a-long-random-one-time-token',
        'max_upload_bytes' => 15 * 1024 * 1024,
        'max_knowledge_upload_bytes' => 100 * 1024 * 1024,
        'max_portfolio_image_bytes' => 12 * 1024 * 1024,
        'max_blog_image_bytes' => 10 * 1024 * 1024,
        'max_event_image_bytes' => 10 * 1024 * 1024,
        'max_music_cover_bytes' => 8 * 1024 * 1024,
        'max_music_banner_bytes' => 12 * 1024 * 1024,
    ],

    'security' => [
        'force_https' => false, // Enable only after HTTPS and proxy settings are confirmed.
        'trusted_proxies' => [],
        'booking_slot_secret' => 'replace-with-a-long-random-booking-slot-secret',
        // Encrypts remote relationship call links stored by this POD.
        // Keep this private and stable. Rotating it invalidates stored remote links.
        'pod_call_link_secret' => 'replace-with-a-long-random-pod-call-link-secret',

        'session_idle_seconds' => 30 * 60,
        'session_absolute_seconds' => 12 * 60 * 60,
        'session_regenerate_seconds' => 15 * 60,

        'password_min_length' => 12,

        'login_email_limit' => 5,
        'login_ip_limit' => 20,
        'login_window_seconds' => 15 * 60,

        'contact_ip_limit' => 5,
        'contact_email_limit' => 3,
        'contact_window_seconds' => 60 * 60,

        'admin_action_limit' => 120,
        'client_action_limit' => 45,
        'action_window_seconds' => 60,
    ],

    'communications' => [
        'enabled' => true,

        // Calling and signaling are self-hosted. An empty list keeps the
        // existing direct-only browser calling behavior used by POD calling.
        'ice_servers' => [
            // Optional network traversal services can be configured by an
            // administrator later without changing the POD calling workflow.
        ],

        'poll_interval_ms' => 2500,
        'ring_seconds' => 45,
        'call_stale_seconds' => 120,
        'call_recording_enabled' => true,
        'max_attachment_bytes' => 25 * 1024 * 1024,
        'max_voice_note_bytes' => 50 * 1024 * 1024,
        'max_call_recording_bytes' => 200 * 1024 * 1024,
        'signal_retention_hours' => 24,
    ],

    'call_center' => [
        'enabled' => true,

        // Public callers can use browser audio only while the administrator
        // line status is set to available.
        'default_public_status' => 'available',
        // Number of audible ring cycles before voicemail.
        'public_max_rings' => 6,
        'public_ring_cycle_seconds' => 6,
        'public_call_stale_seconds' => 120,
        'public_token_minutes' => 30,
        'public_session_minutes' => 180,

        // Public request rate limits reuse security.contact_window_seconds.
        'public_ip_limit' => 4,
        'public_email_limit' => 3,

        // Public voicemail is recorded in the browser and stored privately.
        'voicemail_enabled' => true,
        'voicemail_max_bytes' => 12 * 1024 * 1024,
        'voicemail_max_seconds' => 180,

        // Reserved for the future private HomeServer transcription worker.
        // Manual transcript review works without enabling this.
        'local_voicemail_transcription_enabled' => false,

        'signal_retention_hours' => 24,
    ],

    'transcription' => [
        // Disabled by default. The future Microgifter HomeServer bridge can use
        // the existing queue and transcript-review workflow without a cloud API.
        'enabled' => false,

        // Optional cloud fallback only. Keep empty when local processing is used.
        'api_key' => getenv('OPENAI_API_KEY') ?: '',
        'api_base' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o-mini-transcribe',
        'diarization_model' => 'gpt-4o-transcribe-diarize',
        'language' => 'en',
        'prompt' => 'North Mountain Media, David Evans, Microgifter, Homestead, CRM, ecommerce, product operations',

        // Keep disabled until a transcription provider is deliberately configured.
        'auto_queue_on_upload' => false,

        'worker_token' => 'replace-with-a-long-random-transcription-worker-token',
        'max_api_file_bytes' => 24 * 1024 * 1024,
        'chunk_seconds' => 15 * 60,
        'request_timeout_seconds' => 15 * 60,
        'max_jobs_per_run' => 2,
        'max_attempts' => 3,
        'ffmpeg_path' => 'ffmpeg',
    ],



    'visitor_intelligence' => [
        'enabled' => true,

        // First-party anonymous visitor and session cookies. Raw cookie
        // tokens never enter the database; only salted SHA-256 hashes do.
        'visitor_cookie_days' => 365,
        'session_minutes' => 30,
        'event_rate_limit' => 240,
        'event_rate_window_seconds' => 60 * 60,

        // Privacy defaults. Global Privacy Control disables tracking.
        'respect_global_privacy_control' => true,
        'respect_do_not_track' => false,
        'store_chat_prompt_text' => true,
        'chat_prompt_max_length' => 1000,

        // Optional dedicated secret. When blank, the private setup token
        // is used as the hashing secret.
        'hash_secret' => '',

        // Reserved for the upcoming Microgifter HomeServer connection.
        // Events include stable UUIDs and export-state columns now, but
        // no remote connection is enabled in this release.
        'homeserver_export_enabled' => false,
    ],

    'feed_reader' => [
        'enabled' => true,
        'cron_token' => 'replace-with-a-long-random-feed-refresh-token',
        'refresh_minutes' => 30,
        'max_sources_per_user' => 100,
        'max_response_bytes' => 2 * 1024 * 1024,
        'connect_timeout_seconds' => 5,
        'request_timeout_seconds' => 20,
        'max_redirects' => 5,
        'max_items_per_feed' => 200,
        'refresh_batch_size' => 20,
        // Restrict remote fetches to standard web ports by default.
        'allowed_ports' => [80, 443],
        'user_agent' => 'NorthMountainMediaFeedReader/62 (+feed subscription service)',
    ],

    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'north_mountain_media',
        'username' => 'database_user',
        'password' => 'database_password',
        'charset' => 'utf8mb4',
    ],
];

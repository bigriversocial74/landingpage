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
        // Encrypts remote POD messaging credentials stored by this POD.
        // Keep this separate when possible and rotate only after replacing stored links.
        'pod_message_link_secret' => 'replace-with-a-long-random-pod-message-link-secret',
        // Encrypts POD-to-HomeServer provider payloads, results, artifacts, and
        // derives deterministic idempotent bearer credentials. Keep it private
        // and stable; rotation requires re-pairing all POD HomeServer devices.
        'pod_homeserver_bridge_secret' => 'replace-with-a-long-random-pod-homeserver-bridge-secret',
        // Encrypts the locally cached VP3 deployment credential and signed
        // entitlement token. Keep it private and stable through normal updates.
        'vp3_license_local_secret' => 'replace-with-a-long-random-vp3-license-local-secret',
        // Encrypts the local ActivityPub RSA private key. Keep this private and stable.
        'activitypub_secret' => 'replace-with-a-long-random-activitypub-private-key-secret',
        // Encrypts Web Push subscriptions and the stable VAPID private key.
        // Keep it private and stable; rotation requires browser re-enrollment.
        'notification_delivery_secret' => 'replace-with-a-long-random-notification-delivery-secret',

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

    'pod_homeserver' => [
        // Disabled until a coordinated HomeServer POD adapter is installed and
        // the owner deliberately enables this provider endpoint.
        'enabled' => false,
        'pairing_code_minutes' => 15,
        'request_skew_seconds' => 300,
        'nonce_retention_hours' => 24,
        'max_request_bytes' => 12 * 1024 * 1024,
        'max_audio_bytes' => 8 * 1024 * 1024,
        'artifact_ttl_minutes' => 60,
        'job_ttl_minutes' => 30,
        'lease_seconds' => 300,
        'max_attempts' => 3,
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

    'vp3_licensing' => [
        // VP3.me is the commercial authority. Provisioning assigns one POD and
        // one HomeServer license to each active Domain registration.
        'provider_id' => getenv('VP3_PROVIDER_ID') ?: 'vp3',
        'provider_name' => getenv('VP3_PROVIDER_NAME') ?: 'VP3.me',
        'provider_base_url' => getenv('VP3_PROVIDER_BASE_URL') ?: 'https://vp3.me',
        'api_version' => getenv('VP3_PROVIDER_API_VERSION') ?: 'v1',

        // Provisioned public identifiers. Do not reuse one global POD license
        // across multiple Domain registrations.
        'license_public_id' => getenv('VP3_LICENSE_PUBLIC_ID') ?: '',
        'account_public_id' => getenv('VP3_ACCOUNT_PUBLIC_ID') ?: '',
        'domain_registration_id' => getenv('VP3_DOMAIN_REGISTRATION_ID') ?: '',
        'domain' => getenv('VP3_DOMAIN') ?: '',
        'deployment_id' => getenv('VP3_DEPLOYMENT_ID') ?: '',

        // Prefer a secret manager or environment variable. The adapter encrypts
        // the credential locally before storing a fallback copy in the database.
        'deployment_credential' => getenv('VP3_DEPLOYMENT_CREDENTIAL') ?: '',
        'credential_version' => (int)(getenv('VP3_CREDENTIAL_VERSION') ?: 1),

        // Provisioning should supply this stable fingerprint. When omitted, the
        // POD creates a protected local seed and combines it with deployment
        // signals. Authorized replacement/rebind requires VP3 provisioning.
        'installation_fingerprint' => getenv('VP3_INSTALLATION_FINGERPRINT') ?: '',
        'installed_version' => getenv('POD_INSTALLED_VERSION') ?: '64.0.0',
        'token_version' => 1,

        // Local worker credentials. Never pass them in a query string.
        'cron_token' => getenv('VP3_LICENSE_CRON_TOKEN') ?: 'replace-with-a-long-random-vp3-license-cron-token',
        'update_worker_token' => getenv('VP3_UPDATE_WORKER_TOKEN') ?: 'replace-with-a-long-random-vp3-update-worker-token',

        'request_timeout_seconds' => 12,
        'max_response_bytes' => 1024 * 1024,
        'jwks_cache_seconds' => 3600,
        'storage_paths' => ['storage'],
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

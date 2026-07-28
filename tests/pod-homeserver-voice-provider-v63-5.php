<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/pod_homeserver_voice_provider_v63_5.sql',
    'service' => 'portal/pod-homeserver-provider.php',
    'console' => 'portal/pod-homeserver.php',
    'provider' => 'api/homeserver/v1/provider.php',
    'pairing' => 'api/homeserver/v1/pairing/exchange/index.php',
    'heartbeat' => 'api/homeserver/v1/devices/heartbeat/index.php',
    'poll' => 'api/homeserver/v1/voice/jobs/poll/index.php',
    'complete' => 'api/homeserver/v1/voice/jobs/complete/index.php',
    'fail' => 'api/homeserver/v1/voice/jobs/fail/index.php',
    'artifact' => 'api/homeserver/v1/voice/artifacts/read/index.php',
    'discovery' => 'pod-discovery.php',
    'config' => 'config-example.php',
    'storage' => 'storage/.htaccess',
    'contract' => 'POD-HOMESERVER-VOICE-PROVIDER-CONTRACT-v63.5.md',
    'setup' => 'POD-HOMESERVER-VOICE-PROVIDER-SETUP-v63.5.md',
    'pairing_fixture' => 'tests/fixtures/pod-homeserver-v63-5/pairing-request.json',
    'heartbeat_fixture' => 'tests/fixtures/pod-homeserver-v63-5/heartbeat-request.json',
    'complete_fixture' => 'tests/fixtures/pod-homeserver-v63-5/job-complete-request.json',
    'browser_voice' => 'assets/js/pod-receptionist-voice-v63-4.js',
    'receptionist' => 'portal/pod-agent-receptionist.php',
    'public_call' => 'call-dave.php',
    'connected_call' => 'connected-call.php',
    'public_call_script' => 'assets/js/public-call.js',
    'pod_messages' => 'portal/pod-messages.php',
];

$source = [];
foreach ($paths as $key => $path) {
    $content = @file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    $source[$key] = $content;
}

foreach (['pairing_fixture', 'heartbeat_fixture', 'complete_fixture'] as $key) {
    try {
        $decoded = json_decode($source[$key], true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Invalid JSON fixture {$paths[$key]}: {$exception->getMessage()}\n");
        exit(1);
    }
    if (!is_array($decoded)) {
        fwrite(STDERR, "Fixture {$paths[$key]} must decode to an object.\n");
        exit(1);
    }
}

$combinedEndpoints = $source['pairing'] . $source['heartbeat'] . $source['poll']
    . $source['complete'] . $source['fail'] . $source['artifact'];

$checks = [
    'pairing-code table' => ['pod_homeserver_pairing_codes', $source['migration']],
    'connection table' => ['pod_homeserver_connections', $source['migration']],
    'nonce table' => ['pod_homeserver_request_nonces', $source['migration']],
    'voice-job table' => ['pod_homeserver_voice_jobs', $source['migration']],
    'voice-artifact table' => ['pod_homeserver_voice_artifacts', $source['migration']],
    'voice-receipt table' => ['pod_homeserver_voice_receipts', $source['migration']],
    'idempotent pairing request' => ['pairing_request_id', $source['migration'] . $source['service']],
    'hash-only pairing code' => ['code_hash CHAR(64)', $source['migration']],
    'hash-only bearer token' => ['bearer_token_hash CHAR(64)', $source['migration']],
    'hash-only request nonce' => ['nonce_hash CHAR(64)', $source['migration']],
    'hash-only job lease' => ['lease_token_hash CHAR(64)', $source['migration']],
    'connection UUID' => ['connection_uuid CHAR(36)', $source['migration']],
    'device UUID' => ['device_id CHAR(36)', $source['migration']],
    'job UUID' => ['job_uuid CHAR(36)', $source['migration']],
    'receipt UUID' => ['receipt_uuid CHAR(36)', $source['migration']],
    'AES-GCM encryption' => ['aes-256-gcm', $source['service']],
    'dedicated bridge secret' => ['pod_homeserver_bridge_secret', $source['service'] . $source['config']],
    'Sodium Ed25519 verification' => ['sodium_crypto_sign_verify_detached', $source['service']],
    'canonical request method' => ['$method', $source['service']],
    'canonical request path' => ['$path', $source['service']],
    'canonical request timestamp' => ['$timestamp', $source['service']],
    'canonical request nonce' => ['$nonce', $source['service']],
    'canonical request body hash' => ["hash('sha256', $rawBody)", $source['service']],
    'nonce replay insert' => ['INSERT INTO pod_homeserver_request_nonces', $source['service']],
    'capability allowlist' => ['pod.voice.transcription.v1', $source['service']],
    'capability enforcement' => ['pod_homeserver_require_capability', $source['service']],
    'provider disabled default' => ["'enabled' => false", $source['config']],
    'one-time Sync Code console' => ['Issue POD Sync Code', $source['console']],
    'capability test console' => ['Queue capability test', $source['console']],
    'STT contract test' => ['queue_stt', $source['console']],
    'TTS contract test' => ['queue_tts', $source['console']],
    'atomic job lease' => ['LIMIT 1 FOR UPDATE', $source['service']],
    'lease retry recovery' => ['pod_homeserver_recover_jobs', $source['service']],
    'lease token hash validation' => ["hash('sha256', $leaseToken)", $source['service']],
    'bounded STT transcript' => ['strlen($transcript) > 12000', $source['service']],
    'bounded TTS formats' => ["['audio/mpeg','audio/wav','audio/ogg','audio/webm']", $source['service']],
    'artifact encrypted storage' => ['storage/pod-homeserver-voice', $source['service'] . $source['setup']],
    'artifact integrity hash' => ['content_hash', $source['migration'] . $source['service']],
    'artifact TTL cleanup' => ['pod_homeserver_cleanup_artifacts', $source['service'] . $source['console']],
    'artifact lease binding' => ['pod_homeserver_validate_job_lease', $source['service']],
    'connection revocation' => ['pod_homeserver_revoke_connection', $source['service'] . $source['console']],
    'pairing endpoint' => ['pod_homeserver_pair', $source['pairing']],
    'heartbeat endpoint' => ['pod_homeserver_heartbeat', $source['heartbeat']],
    'poll endpoint' => ['pod_homeserver_poll_job', $source['poll']],
    'complete endpoint' => ['pod_homeserver_complete_job', $source['complete']],
    'failure endpoint' => ['pod_homeserver_fail_job', $source['fail']],
    'artifact endpoint' => ['pod_homeserver_read_artifact', $source['artifact']],
    'bounded JSON envelope' => ['pod_homeserver_provider_response', $source['provider'] . $combinedEndpoints],
    'JSON-only endpoint' => ['application/json', $source['provider']],
    'bearer authentication' => ['Authorization: Bearer', $source['contract']],
    'exact pairing path' => ['/api/homeserver/v1/pairing/exchange', $source['service'] . $source['contract']],
    'exact heartbeat path' => ['/api/homeserver/v1/devices/heartbeat', $source['service'] . $source['contract']],
    'exact poll path' => ['/api/homeserver/v1/voice/jobs/poll', $source['service'] . $source['contract']],
    'exact complete path' => ['/api/homeserver/v1/voice/jobs/complete', $source['service'] . $source['contract']],
    'exact failure path' => ['/api/homeserver/v1/voice/jobs/fail', $source['service'] . $source['contract']],
    'exact artifact path' => ['/api/homeserver/v1/voice/artifacts/read', $source['service'] . $source['contract']],
    'provider discovery' => ["'homeserver_voice_provider'", $source['service'] . $source['discovery']],
    'provider foundation status' => ["'status' => 'provider_foundation'", $source['service']],
    'adapter requirement disclosure' => ["'coordinated_homeserver_adapter_required' => true", $source['service']],
    'storage access denied' => ['Require all denied', $source['storage']],
    'browser voice retained' => ['SpeechRecognition', $source['browser_voice']],
    'receptionist retained' => ['pod_receptionist_answer', $source['receptionist']],
    'public call retained' => ['data-public-call-form', $source['public_call']],
    'connected call retained' => ['Connected caller', $source['connected_call']],
    'existing WebRTC retained' => ['RTCPeerConnection', $source['public_call_script']],
    'POD messaging retained' => ['Private relationship conversations', $source['pod_messages']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'non-repeat-safe migration ALTER' => ['ALTER TABLE', $source['migration']],
    'plain bearer token column' => ['bearer_token VARCHAR', $source['migration']],
    'plain Sync Code column' => ['sync_code VARCHAR', $source['migration']],
    'plain lease token column' => ['lease_token VARCHAR', $source['migration']],
    'private signing key storage' => ['device_private_key', $source['migration'] . $source['service']],
    'query-string credential' => ["\$_GET['token']", $combinedEndpoints . $source['provider']],
    'wildcard CORS provider API' => ['Access-Control-Allow-Origin: *', $combinedEndpoints . $source['provider']],
    'raw audio database blob' => ['LONGBLOB', $source['migration']],
    'raw audio database field' => ['audio_base64 LONGTEXT', $source['migration']],
    'caller-controlled HomeServer URL' => ['homeserver_url', $source['service']],
    'STUN dependency' => ['stun:', $source['service'] . $source['console']],
    'TURN dependency' => ['turn:', $source['service'] . $source['console']],
    'live adapter claim' => ['status=live', strtolower($source['contract'] . $source['setup'])],
];

foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

if (!function_exists('sodium_crypto_sign_keypair')) {
    fwrite(STDERR, "The Sodium extension is required for the provider contract test.\n");
    exit(1);
}

$keypair = sodium_crypto_sign_keypair();
$secretKey = sodium_crypto_sign_secretkey($keypair);
$publicKey = sodium_crypto_sign_publickey($keypair);
$method = 'POST';
$path = '/api/homeserver/v1/devices/heartbeat';
$timestamp = '1785273600';
$nonce = 'fixture-nonce-0000000001';
$body = $source['heartbeat_fixture'];
$canonical = implode("\n", [$method, $path, $timestamp, $nonce, hash('sha256', $body)]);
$signature = sodium_crypto_sign_detached($canonical, $secretKey);
if (!sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey)) {
    fwrite(STDERR, "Ed25519 canonical signature fixture failed.\n");
    exit(1);
}
if (sodium_crypto_sign_verify_detached($signature, $canonical . 'tampered', $publicKey)) {
    fwrite(STDERR, "Tampered Ed25519 canonical fixture was accepted.\n");
    exit(1);
}

fwrite(STDOUT, "POD HomeServer Voice Provider Foundation v63.5 regression passed.\n");

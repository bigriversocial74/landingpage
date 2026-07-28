<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/pod_agent_voice_receptionist_v63_4.sql',
    'service' => 'portal/pod-agent-voice.php',
    'api' => 'api/pod-agent-voice.php',
    'admin' => 'portal/pod-voice.php',
    'public' => 'connected-receptionist.php',
    'bridge' => 'assets/js/pod-receptionist-v63-3.js',
    'voice' => 'assets/js/pod-receptionist-voice-v63-4.js',
    'style' => 'assets/css/pod-receptionist-voice-v63-4.css',
    'discovery' => 'pod-discovery.php',
    'htaccess' => '.htaccess',
    'public_call' => 'call-dave.php',
    'connected_call' => 'connected-call.php',
    'public_call_script' => 'assets/js/public-call.js',
    'receptionist_service' => 'portal/pod-agent-receptionist.php',
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

$checks = [
    'voice settings schema' => ['pod_agent_voice_settings', $source['migration']],
    'voice session schema' => ['pod_agent_voice_sessions', $source['migration']],
    'voice event schema' => ['pod_agent_voice_events', $source['migration']],
    'full voice capability mode' => ['full_voice', $source['migration'] . $source['service']],
    'recognition-only fallback' => ['recognition_only', $source['migration'] . $source['voice']],
    'synthesis-only fallback' => ['synthesis_only', $source['migration'] . $source['voice']],
    'text fallback' => ['text_only', $source['migration'] . $source['voice']],
    'recognized turn counter' => ['recognized_turns', $source['migration'] . $source['service']],
    'spoken turn counter' => ['spoken_turns', $source['migration'] . $source['service']],
    'voice error counter' => ['error_count', $source['migration'] . $source['service']],
    'SpeechRecognition support' => ['window.SpeechRecognition', $source['voice']],
    'webkit recognition fallback' => ['window.webkitSpeechRecognition', $source['voice']],
    'speech synthesis support' => ['speechSynthesis', $source['voice']],
    'push-to-talk control' => ['data-pr-voice-listen', $source['public'] . $source['voice']],
    'stop listening control' => ['data-pr-voice-stop', $source['public'] . $source['voice']],
    'spoken reply control' => ['data-pr-voice-spoken-replies', $source['public'] . $source['voice']],
    'hands-free control' => ['data-pr-voice-hands-free', $source['public'] . $source['voice']],
    'stop speaking control' => ['data-pr-voice-cancel-speech', $source['public'] . $source['voice']],
    'receptionist event bridge' => ['pod:receptionist-message', $source['bridge'] . $source['voice']],
    'text submission bridge' => ['PodReceptionist', $source['bridge'] . $source['voice']],
    'connected relationship API binding' => ['connectedRelationshipId', $source['api']],
    'CSRF-protected voice API' => ['verify_csrf()', $source['api']],
    'voice API rate limit' => ['rate_limit_exceeded', $source['api']],
    'voice settings workspace' => ['Browser speech settings', $source['admin']],
    'voice session history' => ['Browser voice sessions', $source['admin']],
    'raw audio privacy notice' => ['does not upload or store raw live audio', strtolower($source['public'] . $source['admin'])],
    'microphone permission policy' => ['connected-receptionist', $source['htaccess']],
    'voice discovery' => ["'agent_voice'", $source['service'] . $source['discovery']],
    'raw audio storage false' => ["'raw_audio_storage' => false", $source['service']],
    'raw audio upload false' => ["'raw_audio_upload_by_pod' => false", $source['service']],
    'existing receptionist retained' => ['pod_receptionist_answer', $source['receptionist_service']],
    'public call retained' => ['data-public-call-form', $source['public_call']],
    'connected call retained' => ['Connected caller', $source['connected_call']],
    'existing WebRTC retained' => ['RTCPeerConnection', $source['public_call_script']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'raw audio database column' => ['audio_blob', strtolower($source['migration'])],
    'raw recording database column' => ['recording_path', strtolower($source['migration'])],
    'recognized transcript database column' => ['transcript_text', strtolower($source['migration'])],
    'MediaRecorder capture' => ['MediaRecorder', $source['voice']],
    'manual getUserMedia capture' => ['getUserMedia', $source['voice']],
    'audio blob upload' => ['FormData', $source['voice']],
    'private knowledge retrieval' => ['knowledge_documents', $source['service']],
    'unrestricted model invocation' => ['OPENAI_API_KEY', $source['service']],
    'human call replacement' => ['RTCPeerConnection', $source['voice']],
    'inline voice JavaScript' => ['<script>', $source['public']],
];

foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "POD Browser Voice Receptionist v63.4 regression passed.\n");

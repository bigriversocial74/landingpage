<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/pod_identity_relationships_v63.sql',
    'service' => 'portal/pod-identity.php',
    'workspace' => 'portal/pod-connections.php',
    'discovery' => 'pod-discovery.php',
    'htaccess' => '.htaccess',
    'public_call' => 'call-dave.php',
    'public_call_script' => 'assets/js/public-call.js',
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
    'permanent POD identity' => ['pod_uuid', $source['migration'] . $source['service']],
    'single local identity guard' => ['local_key', $source['migration'] . $source['service']],
    'identity origin history' => ['pod_identity_origins', $source['migration'] . $source['service']],
    'remote POD identities' => ['pod_upsert_remote_identity', $source['service']],
    'relationship lifecycle' => ['pending_inbound', $source['migration'] . $source['workspace']],
    'relationship blocking' => ['blocked', $source['migration'] . $source['workspace']],
    'CRM relationship link' => ['crm_contact_id', $source['migration'] . $source['service']],
    'messaging permission' => ['messaging_permission', $source['migration'] . $source['workspace']],
    'calling permission' => ['calling_permission', $source['migration'] . $source['workspace']],
    'agent permission' => ['agent_permission', $source['migration'] . $source['workspace']],
    'relationship event receipts' => ['pod_relationship_events', $source['migration'] . $source['service']],
    'public discovery endpoint' => ['pod_discovery_document', $source['service'] . $source['discovery']],
    'well-known rewrite' => ['^\\.well-known/pod\\.json$ pod-discovery.php', $source['htaccess']],
    'protocol version' => ["'protocol' => 'pod-1'", $source['service']],
    'direct-only call capability' => ["'direct_only' => true", $source['service']],
    'public browser call retained' => ['data-public-call-form', $source['public_call']],
    'existing WebRTC retained' => ['RTCPeerConnection', $source['public_call_script']],
    'existing voicemail retained' => ['data-voicemail-recorder', $source['public_call']],
    'CSRF protected workspace' => ['verify_csrf()', $source['workspace']],
    'authenticated action limit' => ['enforce_authenticated_action_limit', $source['workspace']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'TURN dependency' => ['turn:', $source['service'] . $source['workspace']],
    'STUN dependency' => ['stun:', $source['service'] . $source['workspace']],
    'inline JavaScript handler' => ['onclick=', $source['workspace']],
    'plain-text private key' => ['PRIVATE KEY', $source['service'] . $source['workspace']],
];

foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "POD Identity and Relationships v63 regression passed.\n");

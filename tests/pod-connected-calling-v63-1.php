<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/pod_connected_calling_v63_1.sql',
    'service' => 'portal/pod-connected-calling.php',
    'contacts' => 'portal/pod-contacts-v63-1.php',
    'contacts_route' => 'portal/pod-contacts.php',
    'launcher' => 'portal/pod-call-launch.php',
    'entry' => 'pod-call.php',
    'connected_page' => 'connected-call.php',
    'connected_css' => 'assets/css/pod-connected-call-v63-1.css',
    'contacts_js' => 'assets/js/pod-contacts-v63-1.js',
    'discovery' => 'pod-discovery.php',
    'public_call' => 'call-dave.php',
    'public_call_script' => 'assets/js/public-call.js',
    'public_call_api' => 'api/public-call.php',
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
    'relationship call-link table' => ['pod_relationship_call_links', $source['migration']],
    'connected-call event table' => ['pod_connected_call_events', $source['migration']],
    'inbound token hashes only' => ['token_hash', $source['migration'] . $source['service']],
    'remote link ciphertext' => ['secret_ciphertext', $source['migration'] . $source['service']],
    'authenticated encryption' => ['aes-256-gcm', $source['service']],
    'relationship status enforcement' => ["status'] !== 'connected'", $source['service']],
    'calling permission enforcement' => ["calling_permission'] !== 'call'", $source['service']],
    'revocable inbound links' => ['pod_revoke_connected_call_link', $source['service'] . $source['contacts']],
    'encrypted remote links' => ['pod_save_remote_call_link', $source['service'] . $source['contacts']],
    'one-time link disclosure' => ['pod_call_link_once', $source['contacts']],
    'contact-list call launcher' => ['portal/pod-call-launch.php', $source['contacts']],
    'CSRF protected launch' => ['verify_csrf()', $source['launcher']],
    'no-referrer external launch' => ['Referrer-Policy: no-referrer', $source['launcher'] . $source['entry']],
    'short-lived connected context' => ['30 * 60', $source['service']],
    'token entry redirect' => ["redirect('connected-call.php')", $source['entry']],
    'connected identity context' => ['Connected caller', $source['connected_page']],
    'existing public-call API reused' => ["api/public-call.php", $source['connected_page']],
    'existing public-call script reused' => ["assets/js/public-call.js", $source['connected_page']],
    'existing voicemail markup reused' => ['data-voicemail-recorder', $source['connected_page']],
    'direct-only capability advertised' => ["'direct_only' => true", $source['service']],
    'relationship calling advertised' => ["'relationship_calling' => true", $source['service']],
    'discovery extension enabled' => ['pod_connected_calling_discovery', $source['discovery']],
    'CSP-safe contact controls' => ['assets/js/pod-contacts-v63-1.js', $source['contacts']],
    'public call page retained' => ['data-public-call-form', $source['public_call']],
    'existing WebRTC retained' => ['RTCPeerConnection', $source['public_call_script']],
    'same public call server retained' => ['public_call_token_request', $source['public_call_api']],
];

foreach ($checks as $label => [$needle, $haystack]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'raw inbound token storage' => ['token_value', $source['migration'] . $source['service']],
    'plain remote URL column' => ['remote_call_url VARCHAR', $source['migration']],
    'TURN dependency' => ['turn:', $source['service'] . $source['connected_page'] . $source['contacts']],
    'STUN dependency' => ['stun:', $source['service'] . $source['connected_page'] . $source['contacts']],
    'inline contact JavaScript' => ['<script>', $source['contacts']],
    'cross-origin API rewrite' => ['Access-Control-Allow-Origin: *', $source['public_call_api']],
];

foreach ($forbidden as $label => [$needle, $haystack]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$label}: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "POD Connected Contact Calling v63.1 regression passed.\n");
